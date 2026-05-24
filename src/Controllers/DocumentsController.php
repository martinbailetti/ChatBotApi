<?php
declare(strict_types=1);

class DocumentsController extends BaseController
{
    private function baseUrl(): string
    {
        return rtrim(Config::get('SMIDOCS_BASE_URL', 'http://localhost:8888'), '/');
    }

    private function proxyGet(string $path, array $query = []): array
    {
        $url = $this->baseUrl() . $path;
        if ($query) {
            $filtered = array();
            foreach ($query as $k => $v) {
                if ($v !== '' && $v !== null) {
                    $filtered[$k] = $v;
                }
            }
            $url .= '?' . http_build_query($filtered);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($result === false || $error !== '') {
            throw new RuntimeException('cURL error: ' . $error);
        }
        return ['code' => $httpCode, 'data' => json_decode((string)$result, true)];
    }

    private function proxyDelete(string $path, array $query = []): array
    {
        $url = $this->baseUrl() . $path;
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($result === false || $error !== '') {
            throw new RuntimeException('cURL error: ' . $error);
        }
        return ['code' => $httpCode, 'data' => json_decode((string)$result, true)];
    }

    // GET /api/documents
    public function index(array $params): void
    {
        $this->requireAuth();
        try {
            $res = $this->proxyGet('/documentos', [
                'carpeta'   => $_GET['carpeta']   ?? '',
                'nombre'    => $_GET['nombre']    ?? '',
                'categoria' => $_GET['categoria'] ?? '',
                'limit'     => $_GET['limit']     ?? '',
            ]);
            if ($res['code'] >= 400) {
                $this->jsonError($res['data']['detail'] ?? 'Error al obtener documentos.', 502);
                return;
            }
            $this->jsonSuccess($res['data']);
        } catch (\Exception $e) {
            error_log('[DocumentsController::index] ' . $e->getMessage());
            $this->jsonError('No se pudo conectar con el servicio de documentos.', 503);
        }
    }

    // GET /api/documents/detail?ruta=...
    public function detail(array $params): void
    {
        $this->requireAuth();
        $ruta = trim($_GET['ruta'] ?? '');
        if ($ruta === '') {
            $this->jsonError('El parámetro ruta es obligatorio.', 422);
            return;
        }
        try {
            $res = $this->proxyGet('/documentos/detalle', ['ruta' => $ruta]);
            if ($res['code'] === 404) {
                $this->jsonError('Documento no encontrado.', 404);
                return;
            }
            if ($res['code'] >= 400) {
                $this->jsonError($res['data']['detail'] ?? 'Error.', 502);
                return;
            }
            $this->jsonSuccess($res['data']);
        } catch (\Exception $e) {
            error_log('[DocumentsController::detail] ' . $e->getMessage());
            $this->jsonError('No se pudo conectar con el servicio de documentos.', 503);
        }
    }

    // DELETE /api/documents?ruta=... (solo admin)
    public function destroy(array $params): void
    {
        $payload = $this->requireAuth();
        if (($payload['type'] ?? '') !== 'ADMIN') {
            $this->jsonError('Acceso denegado.', 403);
            return;
        }
        $ruta = trim($_GET['ruta'] ?? '');
        if ($ruta === '') {
            $this->jsonError('El parámetro ruta es obligatorio.', 422);
            return;
        }
        try {
            $res = $this->proxyDelete('/documentos', ['ruta' => $ruta]);
            if ($res['code'] >= 400) {
                $this->jsonError($res['data']['detail'] ?? 'Error al eliminar.', 502);
                return;
            }
            $this->jsonSuccess(null, 'Documento eliminado del vectorstore.');
        } catch (\Exception $e) {
            error_log('[DocumentsController::destroy] ' . $e->getMessage());
            $this->jsonError('No se pudo conectar con el servicio de documentos.', 503);
        }
    }
}
