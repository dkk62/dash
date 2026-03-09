-- Client Work Progress System - Final Database Schema
-- MariaDB 10.4.32
-- Generated: March 9, 2026

CREATE DATABASE IF NOT EXISTS `dash_system` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `dash_system`;

-- ============================================================================
-- Table: users
-- ============================================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('processor0','processor1','admin') NOT NULL DEFAULT 'processor0',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Users
INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `created_at`, `updated_at`) VALUES 
(1,'Admin','info@taxcheapo.com','$2y$10$BnTam3m/FVwI88CD/6rHnOTaBMwyKE6meB3Z2GCH3va2pe.6k4GBi','admin','2026-03-08 09:07:08','2026-03-08 09:16:54'),
(4,'Tax User','ekgrad@gmail.com','$2y$10$m7LpZY/Vv3udB90ox5kP3elMFrnHiElu9MxzTvTeSj2z8EXOQYDHm','processor0','2026-03-08 09:23:12','2026-03-08 14:40:30'),
(5,'Support User','kathotiadk@gmail.com','$2y$10$j8xKg1ndqbmPiZNHABKcQei8EQooNTgT7Bf9rUHqUebKhxDxem4G2','processor1','2026-03-08 09:23:12','2026-03-08 14:40:17');

-- ============================================================================
-- Table: clients
-- ============================================================================
CREATE TABLE IF NOT EXISTS `clients` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `password_hash` VARCHAR(255) DEFAULT NULL,
  `cycle_type` ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_clients_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: accounts (depends on clients)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `accounts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `client_id` INT(11) NOT NULL,
  `account_name` VARCHAR(150) NOT NULL,
  `bank_feed_mode` ENUM('manual','automatic') NOT NULL DEFAULT 'manual',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_accounts_client` (`client_id`),
  CONSTRAINT `accounts_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: periods (depends on clients)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `periods` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `client_id` INT(11) NOT NULL,
  `period_label` VARCHAR(50) NOT NULL,
  `is_locked` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_client_period` (`client_id`,`period_label`),
  KEY `idx_periods_client` (`client_id`),
  CONSTRAINT `periods_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: stage1_status (depends on periods and accounts)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `stage1_status` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `period_id` INT(11) NOT NULL,
  `account_id` INT(11) NOT NULL,
  `status` ENUM('grey','green','orange') NOT NULL DEFAULT 'grey',
  `last_upload_at` DATETIME DEFAULT NULL,
  `last_download_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_period_account` (`period_id`,`account_id`),
  KEY `account_id` (`account_id`),
  KEY `idx_s1_period` (`period_id`),
  CONSTRAINT `stage1_status_ibfk_1` FOREIGN KEY (`period_id`) REFERENCES `periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stage1_status_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: stage_status (depends on periods)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `stage_status` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `period_id` INT(11) NOT NULL,
  `stage_name` ENUM('stage2','stage3','stage4') NOT NULL,
  `status` ENUM('grey','green','orange') NOT NULL DEFAULT 'grey',
  `last_upload_at` DATETIME DEFAULT NULL,
  `last_download_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_period_stage` (`period_id`,`stage_name`),
  KEY `idx_ss_period` (`period_id`),
  CONSTRAINT `stage_status_ibfk_1` FOREIGN KEY (`period_id`) REFERENCES `periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: files (depends on periods, accounts, users)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `files` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `period_id` INT(11) NOT NULL,
  `stage_name` ENUM('stage1','stage2','stage3','stage4') NOT NULL,
  `account_id` INT(11) DEFAULT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `uploaded_by` INT(11) NOT NULL,
  `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `idx_files_period_stage` (`period_id`,`stage_name`),
  KEY `idx_files_account` (`account_id`),
  CONSTRAINT `files_ibfk_1` FOREIGN KEY (`period_id`) REFERENCES `periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `files_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `files_ibfk_3` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: logs (depends on users, clients)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `period_id` INT(11) DEFAULT NULL,
  `stage_name` VARCHAR(10) DEFAULT NULL,
  `account_id` INT(11) DEFAULT NULL,
  `action` ENUM('upload','download','reupload','reminder_sent','period_locked','period_unlocked','login') NOT NULL,
  `user_id` INT(11) DEFAULT NULL,
  `client_id` INT(11) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `metadata` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logs_period` (`period_id`),
  KEY `idx_logs_action` (`action`),
  KEY `idx_logs_user` (`user_id`),
  KEY `idx_logs_client` (`client_id`),
  KEY `idx_logs_created` (`created_at`),
  CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `logs_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: login_attempts
-- ============================================================================
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `identifier` VARCHAR(191) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `attempt_count` INT(11) NOT NULL DEFAULT 0,
  `first_attempt_at` DATETIME NOT NULL,
  `last_attempt_at` DATETIME NOT NULL,
  `locked_until` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_identifier_ip` (`identifier`,`ip_address`),
  KEY `idx_locked_until` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
