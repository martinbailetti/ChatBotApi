CREATE TABLE IF NOT EXISTS `conversations` (
  `id`          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT(20) UNSIGNED NOT NULL,
  `title`       VARCHAR(255) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `is_archived` TINYINT(1) NULL DEFAULT '0',
  `meta`        LONGTEXT NULL DEFAULT NULL COLLATE 'utf8mb4_bin',
  `created_at`  TIMESTAMP NULL DEFAULT NULL,
  `updated_at`  TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `conversations_user_idx` (`user_id`, `updated_at`)
) COLLATE='utf8mb4_unicode_ci' ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `messages` (
  `id`              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` BIGINT(20) UNSIGNED NOT NULL,
  `user_id`         BIGINT(20) UNSIGNED NULL DEFAULT NULL,
  `role`            VARCHAR(20) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `content`         LONGTEXT NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `found`           TINYINT(1) NULL DEFAULT NULL,
  `model`           VARCHAR(120) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
  `provider`        VARCHAR(120) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
  `tool_name`       VARCHAR(120) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci',
  `tokens_used`     INT(10) UNSIGNED NULL DEFAULT NULL,
  `extra`           LONGTEXT NULL DEFAULT NULL COLLATE 'utf8mb4_bin',
  `created_at`      TIMESTAMP NULL DEFAULT NULL,
  `updated_at`      TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `messages_conv_idx` (`conversation_id`, `created_at`),
  INDEX `messages_user_idx` (`user_id`)
) COLLATE='utf8mb4_unicode_ci' ENGINE=InnoDB;

-- Si las tablas ya existen sin la columna found, ejecutar:
-- ALTER TABLE `messages` ADD COLUMN `found` TINYINT(1) NULL DEFAULT NULL AFTER `content`;
