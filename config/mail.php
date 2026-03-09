<?php
// Mail settings: Brevo SMTP for reliable delivery with SPF/DKIM
define('MAIL_FROM', envValue('MAIL_FROM', 'no-reply@example.com'));
define('MAIL_FROM_NAME', envValue('MAIL_FROM_NAME', 'Client Work Progress Dashboard'));
define('SMTP_HOST', envValue('SMTP_HOST', 'smtp.example.com'));
define('SMTP_USER', envValue('SMTP_USER', ''));
define('SMTP_PASS', envValue('SMTP_PASS', ''));
define('SMTP_PORT', (int) envValue('SMTP_PORT', '587'));
define('SMTP_SECURE', envValue('SMTP_SECURE', 'tls')); // 'tls' or 'ssl' or ''

// Backward-compatible aliases used by existing controllers
define('SMTP_FROM_EMAIL', MAIL_FROM);
define('SMTP_FROM_NAME', MAIL_FROM_NAME);
