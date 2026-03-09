# Work Progress Dashboard (PHP)

A role-based workflow system for managing client file processing across four stages (`stage1` to `stage4`) with status LEDs, reminders, period locking, and activity logs.

This project is designed for XAMPP-style hosting at `/dash` and stores uploaded files under `uploads/clients/...`.

## Table of Contents

- Overview
- Core Workflow
- Roles and Permissions
- Tech Stack
- Prerequisites
- Installation and Setup
- Configuration
- First Login and Bootstrapping Users
- How to Use (By Role)
- Stage/Status Rules
- Logs and Auditing
- File Storage Layout
- Security Features
- Troubleshooting
- Project Structure
- Future Improvements

## Overview

The application tracks work progress per **client** and **period**.

Each period flows through these stages:

1. `stage1` (per-account initial uploads)
2. `stage2` (processed)
3. `stage3` (reclassified)
4. `stage4` (reclass complete)

Each stage has an LED status:

- `grey`: waiting / not yet uploaded
- `green`: uploaded and ready for downstream processing
- `orange`: downloaded/consumed by next role

Admins can send reminder emails and lock/unlock periods. Upload/download activity is logged.

## Core Workflow

1. Admin creates clients.
2. Admin creates accounts for each client.
3. Admin creates periods (manual, fiscal, or monthly range).
4. Users/clients upload files in allowed stages.
5. Downstream users download files and progress statuses.
6. Admin sends reminders for pending Stage 1 items.
7. When all stages are orange, admin locks the period.

Important behavior:

- Uploading to a stage sets that stage LED to `green`.
- Downloading from a `green` stage sets it to `orange`.
- Re-uploading upstream clears downstream stage files and resets downstream LEDs to `grey`.
- Locked periods block all uploads until unlocked.

## Roles and Permissions

### System Roles

- `admin`: full access to users, clients, accounts, periods, logs, reminders, lock/unlock.
- `processor0`: stage worker for Stage 1 and Stage 3 uploads.
- `processor1`: stage worker for Stage 2 and Stage 4 uploads.
- `client`: can log in (if client password is set), upload Stage 1, and download permitted stage outputs.

### Upload Permission Matrix

- `stage1`: `processor0`, `admin`, `client`
- `stage2`: `processor1`, `admin`
- `stage3`: `processor0`, `admin`
- `stage4`: `processor1`, `admin`

### Download Permission Matrix

- `stage1`: `processor1`, `admin`, `client`
- `stage2`: `processor0`, `admin`, `client`
- `stage3`: `processor1`, `admin`, `client`
- `stage4`: `processor0`, `admin`, `client`

## Tech Stack

- PHP (procedural routing + MVC-style controllers/models)
- MariaDB/MySQL
- Bootstrap 5 + Bootstrap Icons
- PHPMailer (included in `vendor/PHPMailer`)
- Apache rewrite via `.htaccess`

## Prerequisites

- Windows + XAMPP (Apache + MySQL)
- PHP 8.x recommended
- PHP extensions:
  - `pdo_mysql`
  - `openssl`
  - `mbstring`
  - `zip` (required for multi-file download ZIP)
- Write permissions for `uploads/`

## Installation and Setup

### 1. Place project in XAMPP htdocs

Expected path:

`c:\xampp\htdocs\dash`

### 2. Configure Apache rewrite

`.htaccess` is already included and routes requests to `index.php` under `/dash/`.

Make sure `mod_rewrite` is enabled in Apache.

### 3. Create database schema

Run `schema.sql` in phpMyAdmin or MySQL CLI.

Example (CLI):

```sql
SOURCE c:/xampp/htdocs/dash/schema.sql;
```

### 4. Password reset table

`password_resets` is included in `schema.sql`.
If you imported an older schema before this update, run this once:

```sql
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(191) NOT NULL,
  `token` VARCHAR(128) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token` (`token`),
  KEY `idx_email` (`email`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5. Configure database credentials

Copy `.env.example` to `.env` and update:

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

`config/database.php` now reads values from environment variables.

### 6. Configure mail (SMTP)

In `.env`, set:

- `MAIL_FROM`
- `MAIL_FROM_NAME`
- `SMTP_HOST`
- `SMTP_USER`
- `SMTP_PASS`
- `SMTP_PORT`
- `SMTP_SECURE`

`config/mail.php` now reads values from environment variables.

### 7. Configure application base URL

In `.env`, set:

- `APP_BASE_URL` (example: `/dash`)

All links/assets now use this configurable base URL instead of hardcoded `/dash/`.

This is used for:

- forgot-password reset emails
- reminder emails
- stage upload notification emails

### 8. Ensure upload directory exists and is writable

The app auto-creates `uploads/` if missing, but Apache/PHP must have write permission.

### 9. Start Apache and MySQL

From XAMPP control panel, start both services.

### 10. Open application

`http://localhost/dash/`

## Configuration

### Base assumptions in code

- Application base URL is driven by `.env` `APP_BASE_URL`.
- `redirect()` uses the configured base URL.
- Static asset URLs are generated from the configured base URL.

If you deploy under a different path, update:

- `.env` `APP_BASE_URL`
- `.htaccess` `RewriteBase`

## First Login and Bootstrapping Users

`schema.sql` includes seeded users with existing hashes, but you may not know those passwords.

Recommended: create/update an admin account manually after import.

Example:

```sql
INSERT INTO users (name, email, password_hash, role)
VALUES (
  'Admin',
  'admin@system.com',
  '$2y$10$wHzwxW3hGl8tRzW5mE97w.0Vj4I7o9s3SBjQ3Y1IhBqf8eQAzqzRe',
  'admin'
)
ON DUPLICATE KEY UPDATE role='admin';
```

Then set/reset password using:

- a known hash you generate, or
- the app's forgot-password flow (once SMTP is working).

## How to Use (By Role)

### Admin Guide

1. Log in as admin.
2. Open `Clients` and create a client.
3. For each client, open `Accounts` and add one or more accounts.
4. Open `Periods` for that client and:
   - add one FY period, or
   - generate monthly periods (year range 2026-2030).
5. Use `Dashboard` to monitor Stage 1-4 LEDs.
6. Use `Remind` when Stage 1 has `grey/orange` pending accounts.
7. Lock period only after all stage LEDs are orange.
8. Review `Logs` for audit trail.

### Processor 0 Guide (`processor0`)

- Upload to `stage1` and `stage3`.
- Download from `stage2` and `stage4`.
- Use dashboard upload/download icons in the corresponding columns.

### Processor 1 Guide (`processor1`)

- Upload to `stage2` and `stage4`.
- Download from `stage1` and `stage3`.

### Client Guide (`client`)

- Requires client password set in `Clients` screen.
- Can upload only to Stage 1.
- Can download files when available and permitted by stage rules.
- Sees only their own periods on dashboard.

## Stage/Status Rules

### Upload behavior

- Upload supports multiple files.
- Stage 1 uploads are account-specific (`account_id` required).
- Existing files for that same stage target are removed before new upload.
- Re-upload logs as `reupload`.

### Download behavior

- Single file: direct download.
- Multiple files: zipped download (`ZipArchive` required).
- If stage was `green`, successful download sets it to `orange`.

### Downstream reset behavior

On upload, downstream stages are reset:

- Upload Stage 1 resets Stage 2, 3, 4 to grey and clears their files.
- Upload Stage 2 resets Stage 3, 4.
- Upload Stage 3 resets Stage 4.

## Logs and Auditing

Logged actions include:

- `login`
- `upload`
- `reupload`
- `download`
- `reminder_sent`
- `period_locked`
- `period_unlocked`

Logs are visible in `?action=logs` (admin only), with action filters and metadata.

## File Storage Layout

Uploads are stored under:

`uploads/clients/{client_id}/{period_id}/...`

Structure:

- Stage 1: `stage1/{account_id}/`
- Stage 2: `stage2/`
- Stage 3: `stage3/`
- Stage 4: `stage4/`

Database file records are stored in `files` table with original filename + relative path.

## Security Features

- Session-based authentication
- Role-based authorization checks
- CSRF token on POST actions
- Password hashing (`password_hash` / bcrypt)
- Login attempt throttling (max 3 failures, 1 hour lockout)
- Forgot-password flow with token expiry (1 hour)
- Basic bot trap on login form (honeypot field)

## Operational Logs

- Structured email send failures are written to `logs/email_failures.log` as JSON lines.
- Contexts include password reset emails, reminder emails, and stage upload notifications.

## Troubleshooting

### "Invalid CSRF token"

- Ensure forms include `csrf_token`.
- Ensure session is active and cookies are enabled.

### Login always fails

- Verify user email exists in `users` or `clients`.
- Confirm password hash is valid.
- Check `login_attempts` lockout state.

### Forgot password says success but no email arrives

- Check SMTP settings in `config/mail.php`.
- Verify firewall/port for SMTP.
- Confirm PHPMailer files exist in `vendor/PHPMailer/src`.

### Password reset link fails

- Ensure `password_resets` table exists (re-run latest `schema.sql` or run the SQL in Step 4).
- Ensure token is unused and not expired.

### ZIP download error

- Enable PHP `zip` extension.

### Upload works but files missing

- Check filesystem permissions for `uploads/`.
- Verify Apache user can create directories/files.

### Redirects or assets break

- Confirm `.env` `APP_BASE_URL` matches the deployed subpath.
- Ensure `.htaccess` `RewriteBase` matches the same subpath.

## Project Structure

- `index.php`: front controller/router
- `controllers/`: request action handlers
- `models/`: DB access and domain operations
- `views/`: Bootstrap templates
- `helpers/functions.php`: shared helpers (auth checks, CSRF, logging, stage rules)
- `config/database.php`: DB connection constants
- `config/mail.php`: SMTP constants
- `uploads/`: runtime file storage
- `public/`: CSS/JS/assets

## Future Improvements

- Add automated tests for role matrix, stage resets, and lock/unlock conditions.
- Add centralized log rotation/retention for runtime logs.
