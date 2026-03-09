<?php
// Mail settings: Brevo SMTP for reliable delivery with SPF/DKIM
define('MAIL_FROM', 'no-reply@dashboard.taxcheapo.com');
define('MAIL_FROM_NAME', 'Client Work Progress Dashboard');
define('SMTP_HOST', 'smtp-relay.brevo.com');
define('SMTP_USER', '98f580001@smtp-brevo.com');
define('SMTP_PASS', 'WXDqwS9KCp0gzLsh');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl' or ''

// Backward-compatible aliases used by existing controllers
define('SMTP_FROM_EMAIL', MAIL_FROM);
define('SMTP_FROM_NAME', MAIL_FROM_NAME);
