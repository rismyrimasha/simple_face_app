CREATE DATABASE IF NOT EXISTS `faceapp` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `faceapp`;

CREATE TABLE IF NOT EXISTS `persons` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `age` INT NULL,
    `gender` VARCHAR(20) NULL,
    `address` TEXT NULL,
    `phone` VARCHAR(20) NULL,
    `email` VARCHAR(100) NULL,
    `notes` TEXT NULL,
    `photo_path` VARCHAR(255) NULL,
    `face_encoding` JSON NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

