<?php
require_once BASE_PATH . '/models/Log.php';

if ($action === 'clear_logs') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('?action=logs');
    }
    verifyCsrf();
    LogModel::clearAll();
    setFlash('success', 'All logs have been cleared.');
    redirect('?action=logs');
}

$periodFilter = isset($_GET['period_id']) ? (int) $_GET['period_id'] : null;
$actionFilter = isset($_GET['filter_action']) && $_GET['filter_action'] !== '' ? $_GET['filter_action'] : null;

$logs = LogModel::recent(200, $periodFilter, $actionFilter);

include BASE_PATH . '/views/logs.php';
