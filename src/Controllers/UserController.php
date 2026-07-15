<?php
declare(strict_types=1);

/**
 * Controlador de gestión de usuarios.
 *
 * Rutas (todas protegidas — requieren Bearer token):
 *   GET  /api/users       — Listar todos los usuarios (sin contraseña)
 *   POST /api/users       — Crear un nuevo usuario
 *   PUT  /api/users/{id}  — Editar usuario existente
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
        $pathsRaw  = $this->input($body, 'paths', []);

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

        $paths = [];
        if (!is_array($pathsRaw)) {
            $errors[] = 'El campo paths debe ser un array de rutas.';
        } else {
            foreach ($pathsRaw as $path) {
                if (!is_string($path)) {
                    $errors[] = 'Todas las rutas deben ser cadenas de texto.';
                    break;
                }
                $value = trim($path);
                if ($value === '' || strlen($value) > 255) {
                    $errors[] = 'Cada ruta debe tener entre 1 y 255 caracteres.';
                    break;
                }
                $paths[$value] = true;
            }
            $paths = array_keys($paths);
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
        $this->userModel->setPathsForUser($id, $paths);
        $user = $this->userModel->findById($id);

        $this->jsonSuccess(
            UserModel::publicFields($user ?? []),
            'Usuario creado correctamente.',
            201
        );
    }

    // ── PUT /api/users/{id} ──────────────────────────────────────────────────

    /**
     * Edita datos de un usuario existente.
     *
     * Body JSON permitido:
     *   { email?, first_name?, last_name?, type?, paths? }
     *
     * @param array $params Parámetros de ruta (id)
     */
    public function update(array $params): void
    {
        $this->requireAuth();

        $id = isset($params['id']) ? (int)$params['id'] : 0;
        if ($id <= 0) {
            $this->jsonError('ID de usuario inválido.', 422);
            return;
        }

        $existingUser = $this->userModel->findById($id);
        if ($existingUser === null) {
            $this->jsonError('Usuario no encontrado.', 404);
            return;
        }

        $body = $this->getJsonBody();

        $hasEmail     = array_key_exists('email', $body);
        $hasFirstName = array_key_exists('first_name', $body);
        $hasLastName  = array_key_exists('last_name', $body);
        $hasType      = array_key_exists('type', $body);
        $hasPaths     = array_key_exists('paths', $body);

        if (!$hasEmail && !$hasFirstName && !$hasLastName && !$hasType && !$hasPaths) {
            $this->jsonError('No se han enviado cambios.', 422);
            return;
        }

        $errors = [];
        $updateData = [];

        if ($hasEmail) {
            $email = strtolower(trim((string)$this->input($body, 'email', '')));
            if ($email === '') {
                $errors[] = 'El campo email es obligatorio.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'El formato del email no es válido.';
            } else {
                $sameEmail = strtolower((string)($existingUser['email'] ?? '')) === $email;
                if (!$sameEmail) {
                    $dup = $this->userModel->findByEmail($email);
                    if ($dup !== null && (int)($dup['Id'] ?? 0) !== $id) {
                        $errors[] = 'Ya existe un usuario con ese email.';
                    }
                }
                $updateData['email'] = $email;
            }
        }

        if ($hasFirstName) {
            $firstName = trim((string)$this->input($body, 'first_name', ''));
            if ($firstName === '') {
                $errors[] = 'El nombre es obligatorio.';
            } else {
                $updateData['first_name'] = $firstName;
            }
        }

        if ($hasLastName) {
            $lastName = trim((string)$this->input($body, 'last_name', ''));
            if ($lastName === '') {
                $errors[] = 'Los apellidos son obligatorios.';
            } else {
                $updateData['last_name'] = $lastName;
            }
        }

        if ($hasType) {
            $type = strtoupper(trim((string)$this->input($body, 'type', 'DEFAULT')));
            if (!in_array($type, ['ADMIN', 'DEFAULT'], true)) {
                $errors[] = 'El tipo debe ser ADMIN o DEFAULT.';
            } else {
                $updateData['type'] = $type;
            }
        }

        $paths = [];
        if ($hasPaths) {
            $pathsRaw = $this->input($body, 'paths', []);
            if (!is_array($pathsRaw)) {
                $errors[] = 'El campo paths debe ser un array de rutas.';
            } else {
                foreach ($pathsRaw as $path) {
                    if (!is_string($path)) {
                        $errors[] = 'Todas las rutas deben ser cadenas de texto.';
                        break;
                    }
                    $value = trim($path);
                    if ($value === '' || strlen($value) > 255) {
                        $errors[] = 'Cada ruta debe tener entre 1 y 255 caracteres.';
                        break;
                    }
                    $paths[$value] = true;
                }
                $paths = array_keys($paths);
            }
        }

        if (!empty($errors)) {
            $this->jsonError('Datos inválidos.', 422, $errors);
            return;
        }

        if (!empty($updateData)) {
            $this->userModel->updateUser($id, $updateData);
        }

        if ($hasPaths) {
            $this->userModel->setPathsForUser($id, $paths);
        }

        $updated = $this->userModel->findById($id);
        $this->jsonSuccess(
            UserModel::publicFields($updated ?? []),
            'Usuario actualizado correctamente.'
        );
    }
}
