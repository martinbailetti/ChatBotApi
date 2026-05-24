<?php
declare(strict_types=1);

/**
 * Router HTTP simple sin dependencias externas.
 *
 * Soporta:
 *  - Métodos: GET, POST, PUT, PATCH, DELETE
 *  - Parámetros de ruta: /api/items/{id}
 *  - Controladores como ['NombreController', 'metodo']
 */
class Router
{
    /** @var array<string, array<int, array{pattern: string, params: list<string>, handler: array{0: string, 1: string}}>> */
    private $routes = [];

    // ── Registro de rutas ──────────────────────────────────────────────────────

    public function get(string $path, array $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, array $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    public function patch(string $path, array $handler): void
    {
        $this->addRoute('PATCH', $path, $handler);
    }

    public function delete(string $path, array $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    // ── Dispatch ───────────────────────────────────────────────────────────────

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);

        // Normalizar URI: quitar query string y trailing slash (excepto raíz)
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = '/' . trim($uri, '/');

        $routes = isset($this->routes[$method]) ? $this->routes[$method] : [];

        foreach ($routes as $route) {
            $params = [];
            if (preg_match($route['pattern'], $uri, $matches)) {
                foreach ($route['params'] as $name) {
                    $params[$name] = isset($matches[$name]) ? urldecode($matches[$name]) : '';
                }
                $this->callHandler($route['handler'], $params);
                return;
            }
        }

        // 404
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Route not found']);
    }

    // ── Privados ───────────────────────────────────────────────────────────────

    private function addRoute(string $method, string $path, array $handler): void
    {
        $paramNames = [];

        // Convertir {id} → grupo nombrado (?P<id>[^/]+)
        $pattern = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function (array $m) use (&$paramNames): string {
            $paramNames[] = $m[1];
            return '(?P<' . $m[1] . '>[^/]+)';
        }, $path);

        $pattern = '#^' . $pattern . '$#';

        $this->routes[$method][] = [
            'pattern' => $pattern,
            'params'  => $paramNames,
            'handler' => $handler,
        ];
    }

    private function callHandler(array $handler, array $params): void
    {
        list($controllerName, $method) = $handler;

        if (!class_exists($controllerName)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Controller not found: ' . $controllerName]);
            return;
        }

        $controller = new $controllerName();

        if (!method_exists($controller, $method)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Method not found: ' . $method]);
            return;
        }

        $controller->{$method}($params);
    }
}
