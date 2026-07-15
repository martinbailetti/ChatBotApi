<?php
declare(strict_types=1);

/**
 * FaqController — gestiona FAQs a traves del endpoint /faqs del servidor RAG.
 *
 * Formato del archivo markdown remoto:
 *   # Título
 *
 *   Descripción opcional
 *
 *   ---
 *
 *   ## Pregunta 1
 *
 *   Respuesta 1
 *
 * Rutas:
 *   GET  /api/faqs  — Devuelve { title, description, faqs:[{pregunta,respuesta}] }
 *   POST /api/faqs  — Guarda { title, description, faqs:[{pregunta,respuesta}] } (solo ADMIN)
 */
class FaqController extends BaseController
{
    private function baseUrl(): string
    {
        return rtrim(Config::get('DOCS_BASE_URL', 'http://localhost:8888'), '/');
    }

    private function proxyGetFaqsText(): array
    {
        $url = $this->baseUrl() . '/faqs';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($result === false || $error !== '') {
            throw new RuntimeException('cURL error: ' . $error);
        }

        $data = json_decode((string)$result, true);
        return [
            'code' => $httpCode,
            'data' => is_array($data) ? $data : null,
            'raw'  => (string)$result,
        ];
    }

    private function proxyPutFaqsText(string $texto): array
    {
        $url = $this->baseUrl() . '/faqs';
        $payload = json_encode(['texto' => $texto], JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            throw new RuntimeException('No se pudo serializar el payload de FAQs.');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        ]);

        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($result === false || $error !== '') {
            throw new RuntimeException('cURL error: ' . $error);
        }

        $data = json_decode((string)$result, true);
        return [
            'code' => $httpCode,
            'data' => is_array($data) ? $data : null,
            'raw'  => (string)$result,
        ];
    }

    private function ragErrorDetail(array $proxyResult, string $fallback): string
    {
        $data = $proxyResult['data'] ?? null;
        if (is_array($data) && isset($data['detail'])) {
            $detail = $data['detail'];
            if (is_string($detail) && trim($detail) !== '') {
                return trim($detail);
            }
            if (is_array($detail)) {
                $json = json_encode($detail, JSON_UNESCAPED_UNICODE);
                if (is_string($json) && $json !== '') {
                    return $json;
                }
            }
        }
        return $fallback;
    }

    private function parseFaqs($content)
    {
        $faqs  = array();
        $parts = preg_split('/^## /m', $content);
        array_shift($parts);

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') continue;
            $lines     = explode("\n", $part, 2);
            $pregunta  = trim($lines[0]);
            $respuesta = isset($lines[1]) ? trim($lines[1], " \n-") : '';
            if ($pregunta !== '') {
                $faqs[] = array('pregunta' => $pregunta, 'respuesta' => $respuesta);
            }
        }
        return $faqs;
    }

    private function extractHeader($content)
    {
        $title = '';
        if (preg_match('/^# (.+)$/m', $content, $m)) {
            $title = trim($m[1]);
        }
        $beforeH2    = preg_split('/^## /m', $content, 2)[0];
        $beforeH2    = preg_replace('/^# .+\n?/m', '', $beforeH2);
        $description = trim($beforeH2, " \n-");
        return array('title' => $title, 'description' => $description);
    }

    private function buildMarkdown($title, $description, $faqs)
    {
        $lines = array();
        if ($title !== '') { $lines[] = '# ' . $title; $lines[] = ''; }
        if ($description !== '') { $lines[] = $description; $lines[] = ''; }
        foreach ($faqs as $faq) {
            $pregunta  = trim($faq['pregunta'] ?? '');
            $respuesta = trim($faq['respuesta'] ?? '');
            if ($pregunta === '') continue;
            $lines[] = '---'; $lines[] = '';
            $lines[] = '## ' . $pregunta; $lines[] = '';
            if ($respuesta !== '') { $lines[] = $respuesta; $lines[] = ''; }
        }
        return implode("\n", $lines);
    }

    // GET /api/faqs
    public function index(array $params)
    {
        $this->requireAuth();
        try {
            $res = $this->proxyGetFaqsText();
            if ($res['code'] === 404) {
                $this->jsonSuccess(array('title' => '', 'description' => '', 'faqs' => array()));
                return;
            }
            if ($res['code'] >= 400) {
                $this->jsonError($this->ragErrorDetail($res, 'Error al obtener FAQs desde el RAG.'), 502);
                return;
            }

            if (!is_array($res['data'])) {
                error_log('[FaqController::index] Respuesta no JSON de /faqs: ' . substr((string)($res['raw'] ?? ''), 0, 200));
                $this->jsonError('Respuesta inesperada del servicio de FAQs.', 502);
                return;
            }

            $content = (string)($res['data']['texto'] ?? '');
            $header  = $this->extractHeader($content);
            $this->jsonSuccess(array(
                'title'       => $header['title'],
                'description' => $header['description'],
                'faqs'        => $this->parseFaqs($content),
            ));
        } catch (\Exception $e) {
            error_log('[FaqController::index] ' . $e->getMessage());
            $this->jsonError('No se pudo conectar con el servicio de FAQs.', 503);
        }
    }

    // POST /api/faqs  (solo ADMIN)
    public function save(array $params)
    {
        $payload = $this->requireAuth();
        if (($payload['type'] ?? '') !== 'ADMIN') { $this->jsonError('Acceso denegado.', 403); return; }

        $body        = $this->getJsonBody();
        $title       = trim((string)($body['title'] ?? ''));
        $description = trim((string)($body['description'] ?? ''));
        $faqs        = isset($body['faqs']) && is_array($body['faqs']) ? $body['faqs'] : array();

        $markdown = $this->buildMarkdown($title, $description, $faqs);

        try {
            $res = $this->proxyPutFaqsText($markdown);
            if ($res['code'] >= 400) {
                $this->jsonError($this->ragErrorDetail($res, 'No se pudo guardar el archivo de FAQs en el RAG.'), 502);
                return;
            }

            $storedText = $markdown;
            if (is_array($res['data']) && isset($res['data']['texto']) && is_string($res['data']['texto'])) {
                $storedText = (string)$res['data']['texto'];
            }

            $header = $this->extractHeader($storedText);
            $this->jsonSuccess(array(
                'title'       => $header['title'],
                'description' => $header['description'],
                'faqs'        => $this->parseFaqs($storedText),
            ), 'FAQs guardadas correctamente.');
        } catch (\Exception $e) {
            error_log('[FaqController::save] ' . $e->getMessage());
            $this->jsonError('No se pudo conectar con el servicio de FAQs.', 503);
        }
    }
}