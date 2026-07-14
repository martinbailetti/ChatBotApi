-- ============================================================
-- Migración 003 — Tabla users
-- Compatible con MariaDB 10.5
-- ============================================================
CREATE TABLE `users` (
	`Id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`email` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`first_name` VARCHAR(100) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`last_name` VARCHAR(100) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`type` VARCHAR(100) NOT NULL DEFAULT 'USER' COLLATE 'utf8mb4_unicode_ci',
	`password` VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
	`created_at` DATETIME NOT NULL DEFAULT current_timestamp(),
	`updated_at` DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
	PRIMARY KEY (`Id`) USING BTREE,
	UNIQUE INDEX `uk_users_email` (`email`) USING BTREE,
	INDEX `idx_users_last_name` (`last_name`) USING BTREE,
	INDEX `idx_users_created_at` (`created_at`) USING BTREE
)
COLLATE='utf8mb4_unicode_ci'
ENGINE=InnoDB
AUTO_INCREMENT=1
;
-- ============================================================
-- Nota de seguridad:
--   El campo `password` almacena hashes generados con
--   password_hash($plain, PASSWORD_DEFAULT) desde PHP.
--   NUNCA almacenar contraseñas en texto plano.
-- ============================================================
