<?php
declare(strict_types=1);

/**
 * Singleton PDO para MariaDB / MySQL.
 *
 * Características:
 *  - ERRMODE_EXCEPTION: todas las consultas lanzarán PDOException en error.
 *  - FETCH_ASSOC: los resultados como arrays asociativos por defecto.
 *  - Charset utf8mb4 para soporte completo de Unicode.
 *  - Prepared statements siempre; nunca concatenar input de usuario.
 */
class Database
{
    /** @var PDO|null */
    private static $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $host    = Config::get('DB_HOST',    '127.0.0.1');
            $port    = Config::get('DB_PORT',    '3306');
            $dbname  = Config::get('DB_NAME',    '');
            $user    = Config::get('DB_USER',    '');
            $pass    = Config::get('DB_PASS',    '');
            $charset = Config::get('DB_CHARSET', 'utf8mb4');

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $host, $port, $dbname, $charset
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci",
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // No exponer detalles de conexión al cliente
                http_response_code(503);
                echo json_encode([
                    'success' => false,
                    'message' => 'Service unavailable',
                ]);
                exit;
            }
        }

        return self::$instance;
    }
}
