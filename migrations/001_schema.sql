-- Client Work Progress System - Database Schema
-- MariaDB 10.4.32

CREATE DATABASE IF NOT EXISTS `dash_system` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `dash_system`;

-- Users
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(191) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('processor0','processor1','admin') NOT NULL DEFAULT 'processor0',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Clients
CREATE TABLE IF NOT EXISTS `clients` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(191) NOT NULL,
    `phone` VARCHAR(30) DEFAULT NULL,
    `cycle_type` ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Accounts (per client)
CREATE TABLE IF NOT EXISTS `accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT NOT NULL,
    `account_name` VARCHAR(150) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE,
    INDEX `idx_accounts_client` (`client_id`)
) ENGINE=InnoDB;

-- Periods (per client)
CREATE TABLE IF NOT EXISTS `periods` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT NOT NULL,
    `period_label` VARCHAR(50) NOT NULL,
    `is_locked` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_client_period` (`client_id`, `period_label`),
    INDEX `idx_periods_client` (`client_id`)
) ENGINE=InnoDB;

-- Stage 1 status (account-wise per period)
CREATE TABLE IF NOT EXISTS `stage1_status` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `period_id` INT NOT NULL,
    `account_id` INT NOT NULL,
    `status` ENUM('grey','green','orange') NOT NULL DEFAULT 'grey',
    `last_upload_at` DATETIME DEFAULT NULL,
    `last_download_at` DATETIME DEFAULT NULL,
    FOREIGN KEY (`period_id`) REFERENCES `periods`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_period_account` (`period_id`, `account_id`),
    INDEX `idx_s1_period` (`period_id`)
) ENGINE=InnoDB;

-- Stages 2-4 status (period-wise)
CREATE TABLE IF NOT EXISTS `stage_status` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `period_id` INT NOT NULL,
    `stage_name` ENUM('stage2','stage3','stage4') NOT NULL,
    `status` ENUM('grey','green','orange') NOT NULL DEFAULT 'grey',
    `last_upload_at` DATETIME DEFAULT NULL,
    `last_download_at` DATETIME DEFAULT NULL,
    FOREIGN KEY (`period_id`) REFERENCES `periods`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_period_stage` (`period_id`, `stage_name`),
    INDEX `idx_ss_period` (`period_id`)
) ENGINE=InnoDB;

-- Files
CREATE TABLE IF NOT EXISTS `files` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `period_id` INT NOT NULL,
    `stage_name` ENUM('stage1','stage2','stage3','stage4') NOT NULL,
    `account_id` INT DEFAULT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `original_filename` VARCHAR(255) NOT NULL,
    `uploaded_by` INT NOT NULL,
    `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`period_id`) REFERENCES `periods`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`),
    INDEX `idx_files_period_stage` (`period_id`, `stage_name`),
    INDEX `idx_files_account` (`account_id`)
) ENGINE=InnoDB;

-- Logs
CREATE TABLE IF NOT EXISTS `logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `period_id` INT DEFAULT NULL,
    `stage_name` VARCHAR(10) DEFAULT NULL,
    `account_id` INT DEFAULT NULL,
    `action` ENUM('upload','download','reupload','reminder_sent','period_locked','login') NOT NULL,
    `user_id` INT NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `metadata` JSON DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
    INDEX `idx_logs_period` (`period_id`),
    INDEX `idx_logs_action` (`action`),
    INDEX `idx_logs_user` (`user_id`),
    INDEX `idx_logs_created` (`created_at`)
) ENGINE=InnoDB;

-- Seed admin user (password: admin123)
INSERT INTO `users` (`name`, `email`, `password_hash`, `role`) VALUES
('Admin', 'admin@system.com', '$2y$10$placeholder', 'admin');
