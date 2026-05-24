<?php
/**
 * Router para el servidor integrado de PHP.
 * Uso: php -S localhost:8888 router.php
 *
 * Replica el comportamiento del .htaccess:
 *  - Si la URL corresponde a un fichero real (p.ej. un asset), se sirve directamente.
 *  - Cualquier otra petición se delega a index.php.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Servir archivos estáticos existentes tal cual
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Todo lo demás → index.php
require __DIR__ . '/index.php';
