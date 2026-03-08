<?php
require_once BASE_PATH . '/models/Log.php';

$periodFilter = isset($_GET['period_id']) ? (int) $_GET['period_id'] : null;
$actionFilter = isset($_GET['filter_action']) && $_GET['filter_action'] !== '' ? $_GET['filter_action'] : null;

$logs = LogModel::recent(200, $periodFilter, $actionFilter);

include BASE_PATH . '/views/logs.php';
