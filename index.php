<?php
session_start();

define('BASE_PATH', __DIR__);
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('LOG_PATH', BASE_PATH . '/logs');

require_once BASE_PATH . '/config/env.php';
loadEnvFile(BASE_PATH . '/.env');

define('APP_BASE_URL', envValue('APP_BASE_URL', '/dash'));

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/mail.php';
require_once BASE_PATH . '/helpers/functions.php';

// Ensure uploads directory exists
if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}
if (!is_dir(LOG_PATH)) {
    mkdir(LOG_PATH, 0755, true);
}

$action = $_GET['action'] ?? 'dashboard';

// Public routes
$publicActions = ['login', 'do_login', 'forgot_password', 'do_forgot_password', 'reset_password', 'do_reset_password'];

if (!in_array($action, $publicActions) && !isLoggedIn()) {
    redirect('?action=login');
}

// Route to controller
switch ($action) {
    // Auth
    case 'login':
    case 'do_login':
    case 'logout':
        require_once BASE_PATH . '/controllers/AuthController.php';
        break;

    // Password reset (public)
    case 'forgot_password':
    case 'do_forgot_password':
    case 'reset_password':
    case 'do_reset_password':
        require_once BASE_PATH . '/models/User.php';
        require_once BASE_PATH . '/controllers/PasswordResetController.php';
        break;

    // Users
    case 'users':
    case 'user_save':
    case 'user_edit':
    case 'user_delete':
        requireRole(['admin']);
        require_once BASE_PATH . '/controllers/UserController.php';
        break;

    // Dashboard
    case 'dashboard':
    case 'dashboard_export':
        require_once BASE_PATH . '/controllers/DashboardController.php';
        break;

    // Pending Work
    case 'pending':
        require_once BASE_PATH . '/controllers/PendingController.php';
        break;

    // Clients
    case 'clients':
    case 'client_save':
    case 'client_edit':
    case 'client_delete':
        requireClientPermission();
        require_once BASE_PATH . '/controllers/ClientController.php';
        break;

    // Accounts
    case 'accounts':
    case 'account_save':
    case 'account_delete':
        requireClientPermission();
        require_once BASE_PATH . '/controllers/AccountController.php';
        break;

    // Periods
    case 'periods':
    case 'period_save':
    case 'period_generate':
    case 'period_delete':
        requireClientPermission();
        require_once BASE_PATH . '/controllers/PeriodController.php';
        break;

    // File operations
    case 'upload':
        require_once BASE_PATH . '/controllers/StageController.php';
        break;
    case 'download':
        require_once BASE_PATH . '/controllers/StageController.php';
        break;
    case 'mark_downloaded':
        require_once BASE_PATH . '/controllers/StageController.php';
        break;
    case 'check_existing_files':
        require_once BASE_PATH . '/controllers/StageController.php';
        break;
    case 'stage_files':
        require_once BASE_PATH . '/controllers/StageController.php';
        break;
    case 'preview_file':
        require_once BASE_PATH . '/controllers/StageController.php';
        break;

    // Reminder
    case 'reminder_bulk':
        requireReminderPermission();
        require_once BASE_PATH . '/controllers/ReminderBulkController.php';
        break;

    // Lock
    case 'lock':
    case 'unlock':
        requireRole(['admin']);
        require_once BASE_PATH . '/controllers/LockController.php';
        break;

    // Settings
    case 'settings':
    case 'settings_save':
        requireRole(['admin']);
        require_once BASE_PATH . '/controllers/SettingsController.php';
        break;

    // Logs
    case 'logs':
    case 'clear_logs':
        requireRole(['admin']);
        require_once BASE_PATH . '/controllers/LogController.php';
        break;

    // Stage notes
    case 'save_note':
        require_once BASE_PATH . '/controllers/NoteController.php';
        break;

    // Client documents (independent of stages)
    case 'documents':
    case 'doc_upload':
    case 'doc_files':
    case 'doc_download':
    case 'doc_download_stream':
    case 'doc_preview':
        require_once BASE_PATH . '/controllers/DocumentController.php';
        break;

    default:
        redirect('?action=dashboard');
        break;
}
