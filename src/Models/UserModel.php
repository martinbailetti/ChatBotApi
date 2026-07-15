<?php
declare(strict_types=1);

/**
 * Modelo para la tabla `users`.
 *
 * Seguridad:
 *  - Los emails se normalizan siempre con strtolower + trim.
 *  - Las contraseñas se hashean con password_hash(PASSWORD_DEFAULT).
 *  - El campo `password` nunca se devuelve en las respuestas públicas;
 *    usa stripPassword() para limpiar el array antes de enviarlo.
 */
class UserModel extends BaseModel
{
    protected $table      = 'users';
    protected $primaryKey = 'Id';

    /**
     * Normaliza un listado de rutas: trim, únicas y máximo 255 chars.
     *
     * @param array $paths
     * @return array
     */
    private function normalizePaths(array $paths): array
    {
        $normalized = [];
        foreach ($paths as $path) {
            if (!is_string($path)) {
                continue;
            }
            $value = trim($path);
            if ($value === '' || strlen($value) > 255) {
                continue;
            }
            $normalized[$value] = true;
        }
        return array_keys($normalized);
    }

    /**
     * Obtiene un mapa user_id => paths[] para un conjunto de IDs.
     *
     * @param array $userIds
     * @return array<int, array>
     */
    private function listPathsByUserIds(array $userIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $userIds), function (int $id): bool {
            return $id > 0;
        }));

        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $sql = 'SELECT `user_id`, `path` FROM `user_has_paths`'
             . ' WHERE `user_id` IN (' . $placeholders . ') ORDER BY `path` ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $uid = (int)$row['user_id'];
            if (!isset($map[$uid])) {
                $map[$uid] = [];
            }
            $map[$uid][] = (string)$row['path'];
        }

        return $map;
    }

    // ── Consultas ─────────────────────────────────────────────────────────────

    /**
     * Busca un usuario por email (case-insensitive).
     * Incluye el campo `password` para validación interna.
     *
     * @param string $email
     * @return array|null
     */
    public function findByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        $sql   = 'SELECT * FROM `users` WHERE `email` = ? LIMIT 1';
        $stmt  = $this->db->prepare($sql);
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Busca un usuario por ID.
     * No incluye el hash de contraseña.
     *
     * @param int $id
     * @return array|null
     */
    public function findById($id): ?array
    {
        $sql  = 'SELECT `Id`, `email`, `first_name`, `last_name`, `type`, `created_at`, `updated_at`'
              . ' FROM `users` WHERE `Id` = ? LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([(int)$id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $pathsMap = $this->listPathsByUserIds([(int)$row['Id']]);
        $row['paths'] = isset($pathsMap[(int)$row['Id']]) ? $pathsMap[(int)$row['Id']] : [];

        return $row;
    }

    // ── Escritura ─────────────────────────────────────────────────────────────

    /**
     * Crea un nuevo usuario. Hashea la contraseña antes de guardarla.
     *
     * @param string $email
     * @param string $firstName
     * @param string $lastName
     * @param string $plainPassword
     * @return int ID del nuevo usuario
     */
    public function create(string $email, string $firstName, string $lastName, string $plainPassword, string $type = 'DEFAULT'): int
    {
        $email    = strtolower(trim($email));
        $hash     = password_hash($plainPassword, PASSWORD_DEFAULT);
        $type     = in_array(strtoupper($type), ['ADMIN', 'DEFAULT'], true) ? strtoupper($type) : 'DEFAULT';

        return $this->insert([
            'email'      => $email,
            'first_name' => trim($firstName),
            'last_name'  => trim($lastName),
            'password'   => $hash,
            'type'       => $type,
        ]);
    }

    /**
     * Actualiza email, nombre y/o apellido de un usuario.
     *
     * @param int    $id
     * @param array  $data  Puede contener: email, first_name, last_name
     * @return int Filas afectadas
     */
    public function updateProfile(int $id, array $data): int
    {
        $allowed = ['email', 'first_name', 'last_name'];
        $updates = [];

        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $value = trim((string)$data[$field]);
                if ($field === 'email') {
                    $value = strtolower($value);
                }
                $updates[$field] = $value;
            }
        }

        if (empty($updates)) {
            return 0;
        }

        return $this->update($id, $updates);
    }

    /**
     * Actualiza datos de usuario para administración.
     *
     * @param int   $id
     * @param array $data Puede contener: email, first_name, last_name, type
     * @return int Filas afectadas
     */
    public function updateUser(int $id, array $data): int
    {
        $allowed = ['email', 'first_name', 'last_name', 'type'];
        $updates = [];

        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $value = trim((string)$data[$field]);
                if ($field === 'email') {
                    $value = strtolower($value);
                }
                if ($field === 'type') {
                    $value = strtoupper($value);
                }
                $updates[$field] = $value;
            }
        }

        if (empty($updates)) {
            return 0;
        }

        return $this->update($id, $updates);
    }

    /**
     * Sustituye completamente las rutas permitidas de un usuario.
     *
     * @param int   $userId
     * @param array $paths
     */
    public function setPathsForUser(int $userId, array $paths): void
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return;
        }

        $paths = $this->normalizePaths($paths);

        $this->db->beginTransaction();
        try {
            $deleteStmt = $this->db->prepare('DELETE FROM `user_has_paths` WHERE `user_id` = ?');
            $deleteStmt->execute([$userId]);

            if (!empty($paths)) {
                $insertStmt = $this->db->prepare(
                    'INSERT INTO `user_has_paths` (`user_id`, `path`, `created_at`, `updated_at`) VALUES (?, ?, NOW(), NOW())'
                );
                foreach ($paths as $path) {
                    $insertStmt->execute([$userId, $path]);
                }
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Cambia la contraseña de un usuario.
     *
     * @param int    $id
     * @param string $newPlainPassword
     * @return int Filas afectadas
     */
    public function changePassword(int $id, string $newPlainPassword): int
    {
        $hash = password_hash($newPlainPassword, PASSWORD_DEFAULT);
        return $this->update($id, ['password' => $hash]);
    }

    // ── Utilidades públicas ────────────────────────────────────────────────────

    /**
     * Devuelve todos los usuarios sin el campo password, ordenados por Id.
     *
     * @return array
     */
    public function listAll(): array
    {
        $sql  = 'SELECT `Id`, `email`, `first_name`, `last_name`, `type`, `created_at`'
              . ' FROM `users` ORDER BY `Id` ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        $users = $stmt->fetchAll();
        if (empty($users)) {
            return [];
        }

        $ids = array_map(function (array $user): int {
            return (int)$user['Id'];
        }, $users);
        $pathsMap = $this->listPathsByUserIds($ids);

        foreach ($users as $idx => $user) {
            $uid = (int)$user['Id'];
            $users[$idx]['paths'] = isset($pathsMap[$uid]) ? $pathsMap[$uid] : [];
        }

        return $users;
    }

    /**
     * Elimina el campo `password` de un array de usuario.
     * Úsalo siempre antes de devolver datos de usuario al cliente.
     *
     * @param array $user
     * @return array
     */
    public static function stripPassword(array $user): array
    {
        unset($user['password']);
        return $user;
    }

    /**
     * Devuelve solo los campos públicos de un usuario (sin password).
     *
     * @param array $user
     * @return array
     */
    public static function publicFields(array $user): array
    {
        return [
            'Id'         => isset($user['Id'])         ? $user['Id']         : null,
            'email'      => isset($user['email'])      ? $user['email']      : null,
            'first_name' => isset($user['first_name']) ? $user['first_name'] : null,
            'last_name'  => isset($user['last_name'])  ? $user['last_name']  : null,
            'type'       => isset($user['type'])       ? $user['type']       : 'DEFAULT',
            'paths'      => (isset($user['paths']) && is_array($user['paths'])) ? $user['paths'] : [],
        ];
    }
}
