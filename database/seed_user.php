<?php
declare(strict_types=1);

/**
 * Script CLI para crear un usuario inicial en la base de datos.
 *
 * Uso:
 *   php database/seed_user.php
 *
 * Requiere que el archivo .env (o .env.{hostname}) esté configurado.
 */

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/config/Config.php';
Config::load();

require_once BASE_PATH . '/config/Database.php';

// ── Datos del usuario de prueba ────────────────────────────────────────────────
$email     = 'martinbailetti@gmail.com';
$firstName = 'Martín';
$lastName  = 'Bailetti';
$password  = 'clavesecreta';

// ── Inserción ─────────────────────────────────────────────────────────────────
$pdo  = Database::getInstance();
$hash = password_hash($password, PASSWORD_DEFAULT);

// Verificar si ya existe
$check = $pdo->prepare('SELECT Id FROM users WHERE email = ? LIMIT 1');
$check->execute([strtolower(trim($email))]);

if ($check->fetch()) {
    echo "[INFO] El usuario '{$email}' ya existe. No se ha creado.\n";
    exit(0);
}

$stmt = $pdo->prepare(
    'INSERT INTO users (email, first_name, last_name, password) VALUES (?, ?, ?, ?)'
);
$stmt->execute([strtolower(trim($email)), $firstName, $lastName, $hash]);
$id = $pdo->lastInsertId();

echo "[OK] Usuario creado con Id={$id}, email={$email}\n";
echo "[OK] Password hash generado con PASSWORD_DEFAULT.\n";
