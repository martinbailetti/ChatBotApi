<?php
declare(strict_types=1);

class MessageModel
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getByConversation(int $conversationId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC"
        );
        $stmt->execute([$conversationId]);
        return $stmt->fetchAll();
    }

    public function getAll(array $filters = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['conversation_id'])) {
            $where[]  = 'm.conversation_id = ?';
            $params[] = (int)$filters['conversation_id'];
        }
        if (!empty($filters['role'])) {
            $where[]  = 'm.role = ?';
            $params[] = $filters['role'];
        }
        if (!empty($filters['user_id'])) {
            $where[]  = 'm.user_id = ?';
            $params[] = (int)$filters['user_id'];
        }

        $sql = "SELECT m.*, c.title AS conversation_title, u.email AS user_email
                FROM messages m
                LEFT JOIN conversations c ON m.conversation_id = c.id
                LEFT JOIN users u ON m.user_id = u.id";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY m.created_at DESC LIMIT 200';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function save(
        int     $conversationId,
        ?int    $userId,
        string  $role,
        string  $content,
        ?bool   $found                = null,
        ?string $model                = null,
        ?string $provider             = null,
        ?string $toolName             = null,
        ?int    $promptTokenCount     = null,
        ?int    $candidatesTokenCount = null,
        ?int    $totalTokenCount      = null,
        ?string $extra                = null,
        string  $status               = 'DEFAULT',
        ?string $statusInfo           = null
    ): array {
        $stmt = $this->db->prepare(
            "INSERT INTO messages
             (conversation_id, user_id, role, content, found, model, provider, tool_name,
              prompt_token_count, candidates_token_count, total_token_count,
              extra, status, status_info, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
        );
        $stmt->execute([
            $conversationId,
            $userId,
            $role,
            $content,
            (int)($found ?? true),
            $model,
            $provider,
            $toolName,
            $promptTokenCount,
            $candidatesTokenCount,
            $totalTokenCount,
            $extra,
            $status,
            $statusInfo,
        ]);
        $id    = (int)$this->db->lastInsertId();
        $stmt2 = $this->db->prepare("SELECT * FROM messages WHERE id = ?");
        $stmt2->execute([$id]);
        return $stmt2->fetch();
    }
}
