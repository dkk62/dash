<?php
session_start();

define('BASE_PATH', __DIR__);
define('UPLOAD_PATH', BASE_PATH . '/uploads');

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/mail.php';
require_once BASE_PATH . '/helpers/functions.php';
require_once BASE_PATH . '/middleware/Auth.php';

// Ensure uploads directory exists
if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}

$action = $_GET['action'] ?? 'dashboard';

// Public routes
$publicActions = ['login', 'do_login'];

if (!in_array($action, $publicActions) && !isLoggedIn()) {
    redirect('?action=login');
}

// Route to controller
switch ($action) {
    // Auth
    case 'login':
    case 'do_login':
        require_once BASE_PATH . '/controllers/AuthController.php';
        break;
    case 'logout':
        require_once BASE_PATH . '/controllers/AuthController.php';
        break;

    // Dashboard
    case 'dashboard':
        require_once BASE_PATH . '/controllers/DashboardController.php';
        break;

    // Clients
    case 'clients':
    case 'client_save':
    case 'client_edit':
    case 'client_delete':
        requireRole(['admin']);
        require_once BASE_PATH . '/controllers/ClientController.php';
        break;

    // Accounts
    case 'accounts':
    case 'account_save':
    case 'account_delete':
        requireRole(['admin']);
        require_once BASE_PATH . '/controllers/AccountController.php';
        break;

    // Periods
    case 'periods':
    case 'period_save':
    case 'period_delete':
        requireRole(['admin']);
        require_once BASE_PATH . '/controllers/PeriodController.php';
        break;

    // File operations
    case 'upload':
        require_once BASE_PATH . '/controllers/StageController.php';
        break;
    case 'download':
        require_once BASE_PATH . '/controllers/StageController.php';
        break;

    // Reminder
    case 'reminder':
        requireRole(['admin']);
        require_once BASE_PATH . '/controllers/ReminderController.php';
        break;

    // Lock
    case 'lock':
        requireRole(['admin']);
        require_once BASE_PATH . '/controllers/LockController.php';
        break;

    // Logs
    case 'logs':
        requireRole(['admin']);
        require_once BASE_PATH . '/controllers/LogController.php';
        break;

    default:
        redirect('?action=dashboard');
        break;
}
