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
        return $row !== false ? $row : null;
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
        return $stmt->fetchAll();
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
        ];
    }
}
