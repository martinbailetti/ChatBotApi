<?php
declare(strict_types=1);

class IngestionController extends BaseController
{
    private function baseUrl(): string
    {
        return rtrim(Config::get('DOCS_BASE_URL', 'http://localhost:8888'), '/');
    }

    // GET /api/ingestion/env (solo admin)
    public function env(array $params): void
    {
        $payload = $this->requireAuth();
        if (($payload['type'] ?? '') !== 'ADMIN') {
            $this->jsonError('Acceso denegado.', 403);
            return;
        }

        $url = $this->baseUrl() . '/config/env';
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($result === false || $error !== '') {
            error_log('[IngestionController::env] cURL error: ' . $error);
            $this->jsonError('No se pudo conectar con el servicio de configuración.', 503);
            return;
        }

        $data = json_decode((string)$result, true);
        if (!is_array($data)) {
            error_log('[IngestionController::env] Respuesta no JSON (HTTP ' . $httpCode . '): ' . substr((string)$result, 0, 200));
            $this->jsonError('Respuesta inesperada del servicio de configuración.', 502);
            return;
        }

        if ($httpCode >= 400) {
            $detail = isset($data['detail'])
                ? (is_string($data['detail']) ? $data['detail'] : json_encode($data['detail'], JSON_UNESCAPED_UNICODE))
                : 'Error consultando configuración del RAG.';
            $this->jsonError($detail, 502);
            return;
        }

        $this->jsonSuccess($data);
    }

    // GET /api/ingestion/status
    public function status(array $params): void
    {
        $this->requireAuth();
        $url = $this->baseUrl() . '/health';
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = ($result !== false) ? json_decode((string)$result, true) : null;
        $this->jsonSuccess([
            'online'    => $httpCode === 200,
            'http_code' => $httpCode,
            'info'      => $data,
        ]);
    }

    // GET /api/ingestion/pending (solo admin)
    public function pending(array $params): void
    {
        $payload = $this->requireAuth();
        if (($payload['type'] ?? '') !== 'ADMIN') {
            $this->jsonError('Acceso denegado.', 403);
            return;
        }

        $url = $this->baseUrl() . '/ingesta/pendientes';
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($result === false || $error !== '') {
            error_log('[IngestionController::pending] cURL error: ' . $error);
            $this->jsonError('No se pudo conectar con el servicio de ingesta.', 503);
            return;
        }

        $data = json_decode((string)$result, true);
        if (!is_array($data)) {
            error_log('[IngestionController::pending] Respuesta no JSON (HTTP ' . $httpCode . '): ' . substr((string)$result, 0, 200));
            $this->jsonError('Respuesta inesperada del servicio de ingesta.', 502);
            return;
        }

        if ($httpCode >= 400) {
            $detail = isset($data['detail'])
                ? (is_string($data['detail']) ? $data['detail'] : json_encode($data['detail'], JSON_UNESCAPED_UNICODE))
                : 'Error consultando documentos pendientes de ingesta.';
            $this->jsonError($detail, 502);
            return;
        }

        $this->jsonSuccess($data);
    }

    // POST /api/ingestion/sync (solo admin)
    public function sync(array $params): void
    {
        $payload = $this->requireAuth();
        if (($payload['type'] ?? '') !== 'ADMIN') {
            $this->jsonError('Acceso denegado.', 403);
            return;
        }

        $url = $this->baseUrl() . '/ingesta/sync';
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => '{}',
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($result === false || $error !== '') {
            error_log('[IngestionController::sync] cURL error: ' . $error);
            $this->jsonError('No se pudo conectar con el servicio de ingesta.', 503);
            return;
        }

        $data = json_decode((string)$result, true);
        if ($httpCode >= 400) {
            $detail = isset($data['detail'])
                ? (is_string($data['detail']) ? $data['detail'] : json_encode($data['detail'], JSON_UNESCAPED_UNICODE))
                : 'Error en la ingesta.';
            $this->jsonError($detail, 502);
            return;
        }
        $this->jsonSuccess($data, 'Sincronización iniciada.');
    }
}
