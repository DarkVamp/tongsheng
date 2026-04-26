-- Tongsheng — initiales Datenbankschema
-- In phpMyAdmin ausführen (einmalig beim ersten Deployment)

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `users` (
    `id`          INT          NOT NULL AUTO_INCREMENT,
    `email`       VARCHAR(180) NOT NULL,
    `family_name` VARCHAR(255) NOT NULL,
    `role`        VARCHAR(20)  NOT NULL DEFAULT 'family',
    `password`    VARCHAR(255) NOT NULL,
    `api_token`   VARCHAR(64)  DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `UNIQ_EMAIL` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `recordings` (
    `id`          INT          NOT NULL AUTO_INCREMENT,
    `user_id`     INT          NOT NULL,
    `filename`    VARCHAR(255) NOT NULL,
    `mime_type`   VARCHAR(10)  NOT NULL,
    `file_size`   INT          NOT NULL,
    `recorded_at` DATETIME     NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    `delete_at`   DATETIME     DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    PRIMARY KEY (`id`),
    KEY `IDX_USER` (`user_id`),
    CONSTRAINT `FK_RECORDING_USER`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `comments` (
    `id`           INT      NOT NULL AUTO_INCREMENT,
    `recording_id` INT      NOT NULL,
    `content`      LONGTEXT NOT NULL,
    `created_at`   DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    PRIMARY KEY (`id`),
    KEY `IDX_RECORDING` (`recording_id`),
    CONSTRAINT `FK_COMMENT_RECORDING`
        FOREIGN KEY (`recording_id`) REFERENCES `recordings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
