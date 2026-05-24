<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/Config.php';
Config::load();

$host = Config::get('DB_HOST');
$port = Config::get('DB_PORT');
$name = Config::get('DB_NAME');
$user = Config::get('DB_USER');
$pass = Config::get('DB_PASS');

echo "HOST: $host\n";
echo "PORT: $port\n";
echo "NAME: $name\n";
echo "USER: $user\n";

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "Conexion OK\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
