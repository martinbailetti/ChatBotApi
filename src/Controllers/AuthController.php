<?php
declare(strict_types=1);

/**
 * Controlador de autenticación local.
 *
 * Rutas:
 *   POST /api/auth/login     — Pública: recibe email+password, devuelve token.
 *   GET  /api/auth/me         — Protegida: devuelve el usuario autenticado.
 *   POST /api/auth/logout     — Tokens stateless: responde OK; el cliente borra el token.
 *   PUT  /api/auth/profile    — Protegida: actualiza first_name y last_name.
 *   PUT  /api/auth/password   — Protegida: cambia la contraseña (requiere contraseña actual).
 */
class AuthController extends BaseController
{
    /** @var UserModel */
    private $userModel;

    /** @var AuthService */
    private $authService;

    public function __construct()
    {
        $this->userModel   = new UserModel();
        $this->authService = new AuthService();
    }

    // ── POST /api/auth/login ──────────────────────────────────────────────────

    /**
     * Autentica un usuario con email y contraseña.
     * Responde con un token Bearer y los datos públicos del usuario.
     *
     * @param array $params Parámetros de ruta (vacío en este endpoint)
     */
    public function login(array $params): void
    {
        $body = $this->getJsonBody();

        $email    = trim((string)$this->input($body, 'email',    ''));
        $password = (string)$this->input($body, 'password', '');

        // ── Validación de campos obligatorios ─────────────────────────────────
        $errors = [];

        if ($email === '') {
            $errors[] = 'El campo email es obligatorio.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El formato del email no es válido.';
        }

        if ($password === '') {
            $errors[] = 'El campo password es obligatorio.';
        }

        if (!empty($errors)) {
            $this->jsonError('Datos de acceso inválidos.', 400, $errors);
            return;
        }

        // ── Buscar usuario y verificar contraseña ─────────────────────────────
        $user = $this->userModel->findByEmail($email);

        // Usamos un hash ficticio cuando el usuario no existe para que la
        // duración de la respuesta sea constante (evitar enumeración de emails).
        $hashToCheck = ($user !== null && isset($user['password']))
            ? $user['password']
            : '$2y$10$invalidhashplaceholderXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';

        $valid = password_verify($password, $hashToCheck);

        if (!$valid || $user === null) {
            // Mensaje genérico: no revelar si el email existe
            $this->jsonError('Credenciales incorrectas.', 401);
            return;
        }

        // ── Generar token ─────────────────────────────────────────────────────
        $tokenData = $this->authService->generateToken($user);

        $this->jsonSuccess([
            'token'      => $tokenData['token'],
            'expires_at' => $tokenData['expires_at'],
            'user'       => UserModel::publicFields($user),
        ]);
    }

    // ── GET /api/auth/me ──────────────────────────────────────────────────────

    /**
     * Devuelve los datos del usuario autenticado.
     * Requiere Authorization: Bearer {token}.
     *
     * @param array $params
     */
    public function me(array $params): void
    {
        $payload = $this->requireAuth();

        $userId = isset($payload['user_id']) ? (int)$payload['user_id'] : 0;
        if ($userId === 0) {
            $this->jsonError('Token inválido: user_id no encontrado.', 401);
            return;
        }

        $user = $this->userModel->findById($userId);

        if ($user === null) {
            $this->jsonError('Usuario no encontrado.', 404);
            return;
        }

        $this->jsonSuccess(UserModel::publicFields($user));
    }

    // ── POST /api/auth/logout ─────────────────────────────────────────────────

    /**
     * Logout stateless.
     *
     * Los tokens están firmados pero no almacenados en servidor, por lo que
     * la invalidación corresponde al cliente (borrar el token localmente).
     * Este endpoint responde OK para que el frontend pueda hacer un logout
     * consistente sin errores.
     *
     * @param array $params
     */
    public function logout(array $params): void
    {
        $this->jsonSuccess(null, 'Sesión cerrada correctamente.');
    }

    // ── PUT /api/auth/profile ─────────────────────────────────────────────────

    /**
     * Actualiza first_name y last_name del usuario autenticado.
     *
     * Body JSON: { first_name, last_name }
     *
     * @param array $params
     */
    public function updateProfile(array $params): void
    {
        $payload = $this->requireAuth();

        $userId = isset($payload['user_id']) ? (int)$payload['user_id'] : 0;
        if ($userId === 0) {
            $this->jsonError('Token inválido.', 401);
            return;
        }

        $body      = $this->getJsonBody();
        $firstName = trim((string)$this->input($body, 'first_name', ''));
        $lastName  = trim((string)$this->input($body, 'last_name',  ''));

        $errors = [];
        if ($firstName === '') $errors[] = 'El nombre es obligatorio.';
        if ($lastName  === '') $errors[] = 'Los apellidos son obligatorios.';

        if (!empty($errors)) {
            $this->jsonError('Datos inválidos.', 422, $errors);
            return;
        }

        $this->userModel->updateProfile($userId, [
            'first_name' => $firstName,
            'last_name'  => $lastName,
        ]);

        $updated = $this->userModel->findById($userId);
        $this->jsonSuccess(UserModel::publicFields($updated), 'Perfil actualizado correctamente.');
    }

    // ── PUT /api/auth/password ────────────────────────────────────────────────

    /**
     * Cambia la contraseña del usuario autenticado.
     * Requiere la contraseña actual para confirmar la identidad.
     *
     * Body JSON: { current_password, new_password }
     *
     * @param array $params
     */
    public function changePassword(array $params): void
    {
        $payload = $this->requireAuth();

        $userId = isset($payload['user_id']) ? (int)$payload['user_id'] : 0;
        if ($userId === 0) {
            $this->jsonError('Token inválido.', 401);
            return;
        }

        $body            = $this->getJsonBody();
        $currentPassword = (string)$this->input($body, 'current_password', '');
        $newPassword     = (string)$this->input($body, 'new_password',     '');

        $errors = [];
        if ($currentPassword === '') $errors[] = 'La contraseña actual es obligatoria.';
        if ($newPassword     === '') {
            $errors[] = 'La nueva contraseña es obligatoria.';
        } elseif (strlen($newPassword) < 8) {
            $errors[] = 'La nueva contraseña debe tener al menos 8 caracteres.';
        }

        if (!empty($errors)) {
            $this->jsonError('Datos inválidos.', 422, $errors);
            return;
        }

        // Verificar contraseña actual (necesita el hash → findByEmail)
        $userWithPassword = $this->userModel->findByEmail(
            $this->userModel->findById($userId)['email'] ?? ''
        );

        if ($userWithPassword === null || !password_verify($currentPassword, $userWithPassword['password'])) {
            $this->jsonError('La contraseña actual no es correcta.', 403);
            return;
        }

        $this->userModel->changePassword($userId, $newPassword);
        $this->jsonSuccess(null, 'Contraseña cambiada correctamente.');
    }
}
