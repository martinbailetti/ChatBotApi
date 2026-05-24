<?php
declare(strict_types=1);

/**
 * Controlador de gestión de usuarios.
 *
 * Rutas (todas protegidas — requieren Bearer token):
 *   GET  /api/users       — Listar todos los usuarios (sin contraseña)
 *   POST /api/users       — Crear un nuevo usuario
 */
class UserController extends BaseController
{
    /** @var UserModel */
    private $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    // ── GET /api/users ────────────────────────────────────────────────────────

    /**
     * Devuelve el listado completo de usuarios (campos públicos).
     *
     * @param array $params Parámetros de ruta (vacío)
     */
    public function index(array $params): void
    {
        $this->requireAuth();

        $users = $this->userModel->listAll();

        $this->jsonSuccess([
            'users' => $users,
            'total' => count($users),
        ]);
    }

    // ── POST /api/users ───────────────────────────────────────────────────────

    /**
     * Crea un nuevo usuario.
     *
     * Body JSON esperado:
     *   { email, first_name, last_name, password }
     *
     * @param array $params Parámetros de ruta (vacío)
     */
    public function create(array $params): void
    {
        $this->requireAuth();

        $body = $this->getJsonBody();

        $email     = trim((string)$this->input($body, 'email',      ''));
        $firstName = trim((string)$this->input($body, 'first_name', ''));
        $lastName  = trim((string)$this->input($body, 'last_name',  ''));
        $password  = (string)$this->input($body, 'password', '');
        $type      = strtoupper(trim((string)$this->input($body, 'type', 'DEFAULT')));

        // ── Validación ────────────────────────────────────────────────────────
        $errors = [];

        if ($email === '') {
            $errors[] = 'El campo email es obligatorio.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El formato del email no es válido.';
        }

        if ($firstName === '') {
            $errors[] = 'El nombre es obligatorio.';
        }

        if ($lastName === '') {
            $errors[] = 'Los apellidos son obligatorios.';
        }

        if ($password === '') {
            $errors[] = 'La contraseña es obligatoria.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        }

        if (!in_array($type, ['ADMIN', 'DEFAULT'], true)) {
            $errors[] = 'El tipo debe ser ADMIN o DEFAULT.';
        }

        if (!empty($errors)) {
            $this->jsonError('Datos inválidos.', 422, $errors);
            return;
        }

        // ── Comprobar duplicado ───────────────────────────────────────────────
        $existing = $this->userModel->findByEmail($email);
        if ($existing !== null) {
            $this->jsonError('Ya existe un usuario con ese email.', 409);
            return;
        }

        // ── Crear usuario ─────────────────────────────────────────────────────
        $id   = $this->userModel->create($email, $firstName, $lastName, $password, $type);
        $user = $this->userModel->findById($id);

        $this->jsonSuccess(
            UserModel::publicFields($user ?? []),
            'Usuario creado correctamente.',
            201
        );
    }
}
