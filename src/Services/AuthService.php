<?php
declare(strict_types=1);

/**
 * Servicio de autenticación local con tokens HMAC-SHA256.
 *
 * Formato del token:
 *   base64url(header).base64url(payload).base64url(signature)
 *
 * Donde:
 *   header  = {"alg":"HS256","typ":"JWT-like"}
 *   payload = {"sub": <int>, "user_id": <int>, "email": "...",
 *              "first_name": "...", "last_name": "...",
 *              "iat": <unix>, "exp": <unix>}
 *   signature = HMAC-SHA256(header.payload, AUTH_SECRET)
 *
 * Compatibilidad PHP 7.4: sin str_starts_with, str_contains,
 * operador nullsafe, arrow functions ni match.
 *
 * Seguridad:
 *  - Firma verificada con hash_equals() para evitar timing attacks.
 *  - Expiración obligatoria (AUTH_TOKEN_TTL_SECONDS, defecto 28800 = 8 h).
 *  - AUTH_SECRET leído de .env; nunca hardcodeado.
 */
class AuthService
{
    /** @var string */
    private $secret;

    /** @var int Segundos de vida del token */
    private $ttl;

    public function __construct()
    {
        $this->secret = Config::get('AUTH_SECRET', '');
        $this->ttl    = (int)Config::get('AUTH_TOKEN_TTL_SECONDS', '28800');

        if ($this->secret === '') {
            // En producción esto no debe ocurrir nunca
            error_log('[AuthService] AUTH_SECRET no está definido en .env');
        }
    }

    // ── Generación de token ────────────────────────────────────────────────────

    /**
     * Genera un token firmado para el usuario dado.
     *
     * @param array $user  Registro de la tabla users (con Id, email, first_name, last_name)
     * @return array ['token' => string, 'expires_at' => string ISO-8601]
     */
    public function generateToken(array $user): array
    {
        $now = time();
        $exp = $now + $this->ttl;

        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT-like',
        ]));

        $payload = $this->base64UrlEncode(json_encode([
            'sub'        => (int)$user['Id'],
            'user_id'    => (int)$user['Id'],
            'email'      => $user['email'],
            'first_name' => $user['first_name'],
            'last_name'  => $user['last_name'],
            'iat'        => $now,
            'exp'        => $exp,
        ]));

        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', $header . '.' . $payload, $this->secret, true)
        );

        $token     = $header . '.' . $payload . '.' . $signature;
        $expiresAt = gmdate('Y-m-d\TH:i:s\+00:00', $exp);

        return [
            'token'      => $token,
            'expires_at' => $expiresAt,
        ];
    }

    // ── Validación de token ────────────────────────────────────────────────────

    /**
     * Autentica la petición actual leyendo el header Authorization.
     *
     * Devuelve el payload si el token es válido, o null si no.
     *
     * @return array|null
     */
    public function authenticate(): ?array
    {
        $rawToken = $this->extractBearerToken();
        if ($rawToken === null) {
            return null;
        }
        return $this->verifyToken($rawToken);
    }

    /**
     * Verifica la firma y la expiración de un token.
     *
     * @param  string $token
     * @return array|null  Payload decodificado, o null si inválido
     */
    public function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        list($header, $payload, $signature) = $parts;

        // Re-calcular firma esperada
        $expectedSig = $this->base64UrlEncode(
            hash_hmac('sha256', $header . '.' . $payload, $this->secret, true)
        );

        // Comparación segura contra timing attacks
        if (!hash_equals($expectedSig, $signature)) {
            return null;
        }

        // Decodificar payload
        $decoded = json_decode($this->base64UrlDecode($payload), true);
        if (!is_array($decoded)) {
            return null;
        }

        // Verificar expiración
        if (!isset($decoded['exp']) || $decoded['exp'] < time()) {
            return null;
        }

        return $decoded;
    }

    // ── Extracción del token ───────────────────────────────────────────────────

    /**
     * Lee el token Bearer del header Authorization de la petición.
     *
     * Comprueba en orden:
     *   1. $_SERVER['HTTP_AUTHORIZATION']          (Apache mod_php)
     *   2. $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] (Apache CGI via .htaccess)
     *   3. getallheaders()                          (otros entornos)
     *
     * @return string|null
     */
    public function extractBearerToken(): ?string
    {
        $header = null;

        if (isset($_SERVER['HTTP_AUTHORIZATION']) && $_SERVER['HTTP_AUTHORIZATION'] !== '') {
            $header = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) && $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] !== '') {
            $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (function_exists('getallheaders')) {
            $headers = getallheaders();
            // Búsqueda case-insensitive
            foreach ($headers as $name => $value) {
                if (strtolower($name) === 'authorization') {
                    $header = $value;
                    break;
                }
            }
        }

        if ($header === null) {
            return null;
        }

        // Verificar que empieza por "Bearer " (case-insensitive)
        if (stripos($header, 'Bearer ') !== 0) {
            return null;
        }

        $token = trim(substr($header, 7));
        return $token !== '' ? $token : null;
    }

    // ── Utilidades base64url ──────────────────────────────────────────────────

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $padded = strtr($data, '-_', '+/');
        $mod    = strlen($padded) % 4;
        if ($mod !== 0) {
            $padded .= str_repeat('=', 4 - $mod);
        }
        return base64_decode($padded);
    }
}
