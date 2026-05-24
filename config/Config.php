<?php
declare(strict_types=1);

/**
 * Lector de variables de entorno desde archivos .env.
 *
 * Prioridad:
 *   1. .env.{hostname}   (entorno específico de la máquina)
 *   2. .env              (fallback genérico)
 *
 * Las variables ya definidas en el entorno del proceso (putenv/Apache SetEnv)
 * no se sobreescriben, lo que permite inyección desde el servidor web.
 */
class Config
{
    /** @var bool */
    private static $loaded = false;

    /** @var array<string, string> */
    private static $vars = [];

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        // Archivo específico del host tiene prioridad
        $hostname  = gethostname();
        $envHost   = BASE_PATH . '/.env.' . $hostname;
        $envFallbk = BASE_PATH . '/.env';

        $file = file_exists($envHost) ? $envHost : (file_exists($envFallbk) ? $envFallbk : null);

        if ($file !== null) {
            self::parseFile($file);
        }

        self::$loaded = true;
    }

    /**
     * Obtiene una variable de entorno.
     * Comprueba primero $_ENV / getenv() (variables del proceso/servidor)
     * y después las cargadas desde el archivo .env.
     */
    public static function get(string $key, string $default = ''): string
    {
        // Variables del proceso tienen precedencia
        $env = getenv($key);
        if ($env !== false) {
            return (string)$env;
        }

        if (isset($_ENV[$key])) {
            return (string)$_ENV[$key];
        }

        return isset(self::$vars[$key]) ? self::$vars[$key] : $default;
    }

    // ──────────────────────────────────────────────────────────────────────────

    private static function parseFile(string $path): void
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            // Ignorar comentarios
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            // Solo procesar líneas con '='
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key   = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            // Eliminar comillas envolventes
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last  = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            // No sobreescribir variables del proceso
            if (getenv($key) === false && !isset($_ENV[$key])) {
                self::$vars[$key] = $value;
            }
        }
    }
}
