<?php
declare(strict_types=1);

/**
 * Controlador base.
 *
 * Proporciona helpers de respuesta JSON y un método reutilizable
 * para exigir autenticación Bearer en cualquier endpoint.
 */
abstract class BaseController
{
    // ── Helpers de respuesta ──────────────────────────────────────────────────

    /**
     * @param mixed  $data
     * @param string $message
     * @param int    $status
     */
    protected function jsonSuccess($data = null, string $message = 'OK', int $status = 200): void
    {
        Response::success($data, $message, $status);
    }

    /**
     * @param string     $message
     * @param int        $status
     * @param array|null $errors
     */
    protected function jsonError(string $message, int $status = 400, array $errors = null): void
    {
        Response::error($message, $status, $errors);
    }

    // ── Autenticación ─────────────────────────────────────────────────────────

    /**
     * Exige un token Bearer válido.
     *
     * Si el token falta, expiró o tiene firma inválida, responde 401 y termina.
     * Si es válido, devuelve el payload como array asociativo.
     *
     * @return array El payload decodificado del token.
     */
    protected function requireAuth(): array
    {
        $authService = new AuthService();
        $payload     = $authService->authenticate();

        if ($payload === null) {
            Response::unauthorized('Authentication required');
        }

        return $payload;
    }

    // ── Utilidades ────────────────────────────────────────────────────────────

    /**
     * Decodifica el body JSON de la petición actual.
     *
     * @return array
     */
    protected function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Devuelve un valor de un array, con fallback.
     *
     * @param array  $data
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    protected function input(array $data, string $key, $default = null)
    {
        return isset($data[$key]) ? $data[$key] : $default;
    }
}
