<?php
declare(strict_types=1);

/**
 * Controlador de healthcheck.
 *
 * GET /api/health — Endpoint público para monitorización.
 */
class HealthController extends BaseController
{
    /**
     * Devuelve el estado de la API y comprueba la conexión a la base de datos.
     *
     * @param array $params
     */
    public function index(array $params): void
    {
        $dbStatus = 'ok';

        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->query('SELECT 1');
            $stmt->fetch();
        } catch (Exception $e) {
            $dbStatus = 'error';
        }

        $status = $dbStatus === 'ok' ? 200 : 503;

        http_response_code($status);
        echo json_encode([
            'success' => $dbStatus === 'ok',
            'message' => 'ChatBotApi',
            'data'    => [
                'status'    => $dbStatus === 'ok' ? 'healthy' : 'degraded',
                'database'  => $dbStatus,
                'timestamp' => gmdate('Y-m-d\TH:i:s\+00:00'),
                'php'       => PHP_VERSION,
            ],
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
}
