<?php
declare(strict_types=1);

class ConversationController extends BaseController
{
    /** @var ConversationModel */
    private $conversationModel;
    /** @var MessageModel */
    private $messageModel;

    public function __construct()
    {
        $this->conversationModel = new ConversationModel();
        $this->messageModel      = new MessageModel();
    }

    // GET /api/chat/conversations
    public function index(array $params): void
    {
        $payload = $this->requireAuth();
        $userId  = (int)($payload['user_id'] ?? 0);
        $isAdmin = ($payload['type'] ?? '') === 'ADMIN';

        $conversations = $this->conversationModel->listByUser($userId, $isAdmin);
        $this->jsonSuccess($conversations);
    }

    // POST /api/chat/conversations
    public function store(array $params): void
    {
        $payload = $this->requireAuth();
        $userId  = (int)($payload['user_id'] ?? 0);
        $body    = $this->getJsonBody();
        $title   = trim((string)$this->input($body, 'title', 'Nueva conversación'));

        $conv = $this->conversationModel->create($userId, $title ?: 'Nueva conversación');
        $this->jsonSuccess($conv, 'Conversación creada.', 201);
    }

    // GET /api/chat/conversations/{id}/messages
    public function messages(array $params): void
    {
        $payload = $this->requireAuth();
        $userId  = (int)($payload['user_id'] ?? 0);
        $isAdmin = ($payload['type'] ?? '') === 'ADMIN';
        $convId  = (int)($params['id'] ?? 0);

        $conv = $this->conversationModel->findById($convId);
        if (!$conv) {
            $this->jsonError('Conversación no encontrada.', 404);
            return;
        }
        if (!$isAdmin && (int)$conv['user_id'] !== $userId) {
            $this->jsonError('Acceso denegado.', 403);
            return;
        }

        $messages = $this->messageModel->getByConversation($convId);
        $this->jsonSuccess(['conversation' => $conv, 'messages' => $messages]);
    }

    // PATCH /api/chat/conversations/{id}/title
    public function updateTitle(array $params): void
    {
        $payload = $this->requireAuth();
        $userId  = (int)($payload['user_id'] ?? 0);
        $isAdmin = ($payload['type'] ?? '') === 'ADMIN';
        $convId  = (int)($params['id'] ?? 0);

        $conv = $this->conversationModel->findById($convId);
        if (!$conv) {
            $this->jsonError('Conversación no encontrada.', 404);
            return;
        }
        if (!$isAdmin && (int)$conv['user_id'] !== $userId) {
            $this->jsonError('Acceso denegado.', 403);
            return;
        }

        $body  = $this->getJsonBody();
        $title = trim((string)$this->input($body, 'title', ''));
        if ($title === '') {
            $this->jsonError('El título no puede estar vacío.', 422);
            return;
        }

        $updated = $this->conversationModel->rename($convId, $title);
        $this->jsonSuccess($updated);
    }

    // DELETE /api/chat/conversations/{id}
    public function destroy(array $params): void
    {
        $payload = $this->requireAuth();
        $userId  = (int)($payload['user_id'] ?? 0);
        $isAdmin = ($payload['type'] ?? '') === 'ADMIN';
        $convId  = (int)($params['id'] ?? 0);

        $conv = $this->conversationModel->findById($convId);
        if (!$conv) {
            $this->jsonError('Conversación no encontrada.', 404);
            return;
        }
        if (!$isAdmin && (int)$conv['user_id'] !== $userId) {
            $this->jsonError('Acceso denegado.', 403);
            return;
        }

        $this->conversationModel->softDelete($convId);
        $this->jsonSuccess(null, 'Conversación eliminada.');
    }
}
