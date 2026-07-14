<?php
declare(strict_types=1);

/**
 * Configuracion del servidor (solo admin).
 *
 * Ruta:
 *   GET /api/config/server
 */
class ServerConfigController extends BaseController
{
    public function index(array $params): void
    {
        $payload = $this->requireAuth();
        if (($payload['type'] ?? '') !== 'ADMIN') {
            $this->jsonError('Acceso denegado.', 403);
            return;
        }

        $config = Config::all(['DB_PASS', 'AUTH_SECRET']);

        $this->jsonSuccess([
            'env_file' => Config::loadedFileName(),
            'timezone' => date_default_timezone_get(),
            'php_version' => PHP_VERSION,
            'hostname' => gethostname() ?: null,
            'config' => $config,
        ]);
    }
}
