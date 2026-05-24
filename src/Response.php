<?php
declare(strict_types=1);

/**
 * Helpers estáticos para respuestas JSON consistentes.
 *
 * Formato de éxito:  { "success": true,  "message": "...", "data": {...} }
 * Formato de error:  { "success": false, "message": "...", "errors": [...] }
 */
class Response
{
    /**
     * Respuesta de éxito.
     *
     * @param mixed       $data
     * @param string      $message
     * @param int         $status  Código HTTP (200, 201, etc.)
     */
    public static function success($data = null, string $message = 'OK', int $status = 200): void
    {
        http_response_code($status);
        $body = [
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ];
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Respuesta de error.
     *
     * @param string      $message
     * @param int         $status   Código HTTP (400, 401, 403, 404, 422, 500…)
     * @param array|null  $errors   Lista de errores de validación
     */
    public static function error(string $message, int $status = 400, array $errors = null): void
    {
        http_response_code($status);
        $body = [
            'success' => false,
            'message' => $message,
        ];
        if ($errors !== null) {
            $body['errors'] = $errors;
        }
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Respuesta 401 Unauthorized.
     */
    public static function unauthorized(string $message = 'Unauthorized'): void
    {
        header('WWW-Authenticate: Bearer realm="ChatBotApi"');
        self::error($message, 401);
    }

    /**
     * Respuesta 403 Forbidden.
     */
    public static function forbidden(string $message = 'Forbidden'): void
    {
        self::error($message, 403);
    }

    /**
     * Respuesta 404 Not Found.
     */
    public static function notFound(string $message = 'Not found'): void
    {
        self::error($message, 404);
    }

    /**
     * Respuesta 422 Unprocessable Entity (errores de validación).
     *
     * @param array $errors
     */
    public static function validationError(array $errors, string $message = 'Validation failed'): void
    {
        self::error($message, 422, $errors);
    }
}
