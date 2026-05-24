-- ============================================================
-- Migración 003 — Tabla users
-- Compatible con MariaDB 10.5
-- ============================================================

CREATE TABLE IF NOT EXISTS `users` (
    `Id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `email`       VARCHAR(255)  NOT NULL,
    `first_name`  VARCHAR(100)  NOT NULL,
    `last_name`   VARCHAR(100)  NOT NULL,
    `password`    VARCHAR(255)  NOT NULL,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`Id`),
    UNIQUE KEY `uk_users_email` (`email`),
    INDEX `idx_users_last_name` (`last_name`),
    INDEX `idx_users_created_at` (`created_at`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Nota de seguridad:
--   El campo `password` almacena hashes generados con
--   password_hash($plain, PASSWORD_DEFAULT) desde PHP.
--   NUNCA almacenar contraseñas en texto plano.
-- ============================================================
