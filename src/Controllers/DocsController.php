<?php
declare(strict_types=1);

/**
 * Controlador proxy hacia el servidor rag (ChatIA).
 *
 * Rutas (todas protegidas — requieren Bearer token):
 *   POST /api/chat/query  — Envía una pregunta al servidor rag y devuelve la respuesta.
 *
 * Variables de entorno necesarias:
 *   DOCS_CHAT_URL      — URL completa del endpoint /consulta de rag
 *   DOCS_CHAT_TIMEOUT  — Timeout en segundos (default 30)
 */
class DocsController extends BaseController
{
    // ── POST /api/chat/query ──────────────────────────────────────────────────

    /**
     * Recibe { pregunta, conversation_id?, conversation? } y las reenvía a rag.
     * Persiste la conversación y los mensajes en BD. Devuelve la respuesta normalizada.
     *
     * @param array $params
     */
    public function query(array $params): void
    {
        $payload  = $this->requireAuth();
        $userId   = (int)($payload['user_id'] ?? 0);

        $body     = $this->getJsonBody();
        $pregunta = trim((string)$this->input($body, 'pregunta', ''));

        if ($pregunta === '') {
            $this->jsonError('La pregunta es obligatoria.', 422);
            return;
        }

        $chatUrl = Config::get('DOCS_CHAT_URL', '');
        $timeout = max(5, (int)Config::get('DOCS_CHAT_TIMEOUT', '30'));

        if ($chatUrl === '') {
            $this->jsonError('El servicio de chat no está configurado en el servidor.', 503);
            return;
        }

        // ── Gestión de conversación ───────────────────────────────────────────
        $convModel = new ConversationModel();
        $msgModel  = new MessageModel();

        $conversationId = (int)$this->input($body, 'conversation_id', 0);
        if ($conversationId > 0) {
            $conv = $convModel->findById($conversationId);
            // Si no existe o no pertenece al usuario, crear una nueva
            if (!$conv || (int)$conv['user_id'] !== $userId) {
                $conversationId = 0;
            }
        }
        if ($conversationId === 0) {
            $firstWords     = mb_substr($pregunta, 0, 60);
            $conv           = $convModel->create($userId, $firstWords ?: 'Nueva conversación');
            $conversationId = (int)$conv['id'];
        }

        // Guardar mensaje del usuario
        $msgModel->save($conversationId, $userId, 'user', $pregunta);

        // ── Construir payload para rag ─────────────────────────────────────
        $payload = ['pregunta' => $pregunta];

        // Historial conversacional opcional: array de {role, content}
        $conversation = $this->input($body, 'conversation', null);
        if (is_array($conversation) && count($conversation) > 0) {
            $payload['conversation'] = $conversation;
        }

        // ── Llamada HTTP a rag ─────────────────────────────────────────────
        $ch = curl_init($chatUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $result    = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($result === false || $curlError !== '') {
            error_log('[DocsController] cURL error: ' . $curlError);
            $this->jsonError('No se pudo conectar con el servicio de chat.', 503);
            return;
        }

        $data = json_decode($result, true);
        if (!is_array($data)) {
            error_log('[DocsController] Respuesta no JSON (HTTP ' . $httpCode . '): ' . substr((string)$result, 0, 200));
            $this->jsonError('Respuesta inesperada del servicio de chat.', 502);
            return;
        }

        if ($httpCode >= 400) {
            // FastAPI usa el campo 'detail'; otros servicios pueden usar 'message'
            $msg = $data['detail'] ?? $data['message'] ?? 'Error en el servicio de chat.';
            $this->jsonError((string)$msg, 502);
            return;
        }

        // ── Normalizar campos de la respuesta de rag ───────────────────────
        $text               = $data['respuesta'] ?? $data['answer'] ?? $data['text'] ?? $data['message'] ?? '';
        $sources            = $data['fuentes']   ?? $data['sources'] ?? [];
        $found              = isset($data['found'])              ? (bool)$data['found']              : true;
        $greeting           = isset($data['greeting'])           ? (bool)$data['greeting']           : false;
        $needsClarification = isset($data['needs_clarification']) ? (bool)$data['needs_clarification'] : false;
        $allSources         = isset($data['all_sources'])        ? (bool)$data['all_sources']        : false;

        $modelName            = isset($data['model'])    ? (string)$data['model']    : null;
        $providerName         = isset($data['provider']) ? (string)$data['provider'] : 'rag';
        $usage                = isset($data['usage']) && is_array($data['usage']) ? $data['usage'] : [];
        $promptTokenCount     = isset($usage['prompt_token_count'])     ? (int)$usage['prompt_token_count']     : null;
        $candidatesTokenCount = isset($usage['candidates_token_count']) ? (int)$usage['candidates_token_count'] : null;
        $totalTokenCount      = isset($usage['total_token_count'])      ? (int)$usage['total_token_count']      : null;
        $extra                = (isset($data['debug_info']) || !empty($sources))
            ? json_encode(['sources' => $sources, 'debug_info' => $data['debug_info'] ?? null], JSON_UNESCAPED_UNICODE)
            : null;

        // Guardar respuesta del asistente
        $msgModel->save($conversationId, null, 'assistant', $text, $found, $modelName, $providerName, null, $promptTokenCount, $candidatesTokenCount, $totalTokenCount, $extra);
        $convModel->touch($conversationId);

        $this->jsonSuccess([
            'text'                => $text,
            'sources'             => $sources,
            'found'               => $found,
            'greeting'            => $greeting,
            'needs_clarification' => $needsClarification,
            'all_sources'         => $allSources,
            'conversation_id'     => $conversationId,
        ]);
    }
}
