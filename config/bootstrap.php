<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// ─── Autoloader propio ────────────────────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    // Mapeo de namespaces / clases a rutas de archivo
    $map = [
        'Config'     => BASE_PATH . '/config/Config.php',
        'Database'   => BASE_PATH . '/config/Database.php',
        'Router'     => BASE_PATH . '/src/Router.php',
        'Response'   => BASE_PATH . '/src/Response.php',
    ];

    if (isset($map[$class])) {
        require_once $map[$class];
        return;
    }

    // Clases en src/Controllers/
    $controllerFile = BASE_PATH . '/src/Controllers/' . $class . '.php';
    if (file_exists($controllerFile)) {
        require_once $controllerFile;
        return;
    }

    // Clases en src/Models/
    $modelFile = BASE_PATH . '/src/Models/' . $class . '.php';
    if (file_exists($modelFile)) {
        require_once $modelFile;
        return;
    }

    // Clases en src/Services/
    $serviceFile = BASE_PATH . '/src/Services/' . $class . '.php';
    if (file_exists($serviceFile)) {
        require_once $serviceFile;
        return;
    }

    // Clases en src/
    $srcFile = BASE_PATH . '/src/' . $class . '.php';
    if (file_exists($srcFile)) {
        require_once $srcFile;
    }
});

// ─── Cargar Config y establecer zona horaria ──────────────────────────────────
require_once BASE_PATH . '/config/Config.php';
Config::load();

$tz = Config::get('APP_TIMEZONE', 'UTC');
date_default_timezone_set($tz);

// ─── Cargar Database (lazy singleton: no conecta hasta que se use) ────────────
require_once BASE_PATH . '/config/Database.php';

// ─── Cabecera global JSON ─────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
