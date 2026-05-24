# ChatBotApi

API REST en PHP 7.4 y MariaDB 10.5 sin framework, con autenticación local por email/password y tokens Bearer firmados con HMAC-SHA256.

## Stack

| Capa | Tecnología |
|------|-----------|
| Lenguaje | PHP 7.4 |
| Base de datos | MariaDB 10.5 |
| Autenticación | Token HMAC-SHA256 local |
| Servidor web | Apache + mod_rewrite |

## Estructura

```
ChatBotApi/
├── index.php                          # Entry point: CORS, rutas, dispatch
├── .htaccess                          # mod_rewrite + Authorization header CGI
├── .env.example                       # Plantilla de variables de entorno
├── .env                               # Variables locales (NO subir al repo)
├── config/
│   ├── bootstrap.php                  # Autoloader, Config, Database, cabecera JSON
│   ├── Config.php                     # Lector .env.{hostname} con fallback a .env
│   └── Database.php                   # Singleton PDO (utf8mb4, ERRMODE_EXCEPTION)
├── src/
│   ├── Router.php                     # Enrutador con parámetros {id}
│   ├── Response.php                   # Respuestas JSON { success, message, data }
│   ├── Controllers/
│   │   ├── BaseController.php         # requireAuth(), jsonSuccess(), jsonError()
│   │   ├── AuthController.php         # login, me, logout
│   │   └── HealthController.php       # GET /api/health
│   ├── Models/
│   │   ├── BaseModel.php              # CRUD genérico con prepared statements
│   │   └── UserModel.php              # findByEmail, findById, create, publicFields
│   └── Services/
│       └── AuthService.php            # Generación y verificación de tokens HMAC
└── database/
    ├── 003_create_users.sql           # Migración: tabla users
    ├── seed_user.php                  # Script CLI para crear usuario de prueba
    └── 001_seed_users.example.sql     # Referencia SQL (sin hashes reales)
```

## Instalación

### 1. Configurar variables de entorno

```bash
# Opción A: archivo genérico
cp .env.example .env

# Opción B: archivo específico del equipo (recomendado)
# Config.php carga .env.{hostname} automáticamente si existe.
# En Windows: hostname devuelve el nombre del PC (ej. DESKTOP-HCBQ1DU)
cp .env.example .env.DESKTOP-HCBQ1DU
# Editar el archivo con los valores reales de la máquina
```

Variables obligatorias:

| Variable | Descripción |
|----------|-------------|
| `DB_HOST` | Host de MariaDB |
| `DB_NAME` | Nombre de la base de datos |
| `DB_USER` | Usuario de MariaDB |
| `DB_PASS` | Contraseña de MariaDB |
| `AUTH_SECRET` | Secreto HMAC — debe ser largo, aleatorio y único por entorno |
| `AUTH_TOKEN_TTL_SECONDS` | Duración del token en segundos (defecto: 28800 = 8 h) |
| `CORS_ALLOWED_ORIGINS` | Orígenes permitidos separados por coma |

> **Seguridad:** Genera `AUTH_SECRET` con `openssl rand -hex 64`. Nunca uses el valor de ejemplo en producción.

### 2. Ejecutar la migración

```sql
-- En el cliente MariaDB:
SOURCE database/003_create_users.sql;
```

### 3. Crear un usuario de prueba

```bash
php database/seed_user.php
```

Edita el script para cambiar email/contraseña antes de ejecutarlo.

### 4. Arrancar el servidor de desarrollo

El proyecto incluye `router.php` para usar el servidor integrado de PHP sin necesidad de Apache:

```bash
cd c:\Projects\ChatBotApi
php -S localhost:8888 router.php
```

La API quedará disponible en `http://localhost:8888`.  
Detén el servidor con **Ctrl + C**.

> El puerto `8888` coincide con `VITE_API_URL` del frontend ChatBot.  
> Si usas Apache/XAMPP, apunta el vHost a la raíz del proyecto; el `.htaccess` ya está configurado.

## Rutas

### Públicas

| Método | Ruta | Descripción |
|--------|------|-------------|
| `GET`  | `/api/health` | Estado de la API y conexión a BD |
| `POST` | `/api/auth/login` | Login con email + password |
| `POST` | `/api/auth/logout` | Logout (stateless, el cliente borra el token) |

### Protegidas (requieren `Authorization: Bearer {token}`)

| Método | Ruta | Descripción |
|--------|------|-------------|
| `GET`  | `/api/auth/me` | Datos del usuario autenticado |

## Ejemplos

### Login

```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "secret123"
}
```

Respuesta `200`:

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "token": "eyJhbGci...",
    "expires_at": "2026-05-16T20:00:00+00:00",
    "user": {
      "Id": 1,
      "email": "admin@example.com",
      "first_name": "Admin",
      "last_name": "Demo"
    }
  }
}
```

### Me (endpoint protegido)

```http
GET /api/auth/me
Authorization: Bearer eyJhbGci...
```

Respuesta `200`:

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "Id": 1,
    "email": "admin@example.com",
    "first_name": "Admin",
    "last_name": "Demo"
  }
}
```

## Proteger un endpoint propio

En cualquier controlador que extienda `BaseController`, llama a `requireAuth()` al inicio del método:

```php
public function miEndpoint(array $params): void
{
    $payload = $this->requireAuth(); // Responde 401 y termina si el token es inválido

    $userId = (int)$payload['user_id'];
    // ...
}
```

## Seguridad

- Contraseñas hasheadas con `password_hash(PASSWORD_DEFAULT)`.
- Verificación con `password_verify()`.
- Firmas HMAC comparadas con `hash_equals()` (evita timing attacks).
- Tiempo de respuesta constante en login aunque el email no exista.
- Mensajes de error genéricos: no revelan si el email existe.
- El campo `password` nunca se devuelve en respuestas JSON.
- Todos los accesos a BD usan prepared statements.
- `AUTH_SECRET` leído de `.env`; nunca hardcodeado.
