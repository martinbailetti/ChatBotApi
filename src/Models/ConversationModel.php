<?php
declare(strict_types=1);

class ConversationModel
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function listByUser(int $userId, bool $isAdmin = false): array
    {
        if ($isAdmin) {
            $stmt = $this->db->prepare(
                "SELECT c.*, u.email AS user_email, u.first_name, u.last_name,
                        COUNT(m.id) AS message_count
                 FROM conversations c
                 LEFT JOIN users u ON c.user_id = u.id
                 LEFT JOIN messages m ON m.conversation_id = c.id
                 WHERE c.is_archived = 0
                 GROUP BY c.id
                 ORDER BY c.updated_at DESC
                 LIMIT 200"
            );
            $stmt->execute();
        } else {
            $stmt = $this->db->prepare(
                "SELECT c.*, COUNT(m.id) AS message_count
                 FROM conversations c
                 LEFT JOIN messages m ON m.conversation_id = c.id
                 WHERE c.user_id = ? AND c.is_archived = 0
                 GROUP BY c.id
                 ORDER BY c.updated_at DESC
                 LIMIT 100"
            );
            $stmt->execute([$userId]);
        }
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM conversations WHERE id = ? AND is_archived = 0"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $userId, string $title = 'Nueva conversación'): array
    {
        $stmt = $this->db->prepare(
            "INSERT INTO conversations (user_id, title, created_at, updated_at) VALUES (?, ?, NOW(), NOW())"
        );
        $stmt->execute([$userId, $title]);
        return $this->findById((int)$this->db->lastInsertId());
    }

    public function rename(int $id, string $title): ?array
    {
        $title = mb_substr(trim($title), 0, 255);
        $stmt  = $this->db->prepare(
            "UPDATE conversations SET title = ?, updated_at = NOW() WHERE id = ? AND is_archived = 0"
        );
        $stmt->execute([$title, $id]);
        return $this->findById($id);
    }

    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE conversations SET is_archived = 1, updated_at = NOW() WHERE id = ? AND is_archived = 0"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function touch(int $id): void
    {
        $this->db->prepare(
            "UPDATE conversations SET updated_at = NOW() WHERE id = ?"
        )->execute([$id]);
    }
}
