<?php
require_once BASE_PATH . '/models/StageNote.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

requireCsrf();

$periodId  = (int)($_POST['period_id'] ?? 0);
$stage     = trim($_POST['stage'] ?? '');
$accountId = (int)($_POST['account_id'] ?? 0);
$message   = trim($_POST['message'] ?? '');

$validStages = ['stage1', 'stage2', 'stage3', 'stage4'];
if ($periodId <= 0 || !in_array($stage, $validStages, true)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

if ($message === '') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
    exit;
}

if (mb_strlen($message) > 1000) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Message too long (max 1000 characters)']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$entry = StageNote::append($periodId, $stage, $accountId, $message, $userId);

header('Content-Type: application/json');
echo json_encode(['success' => true, 'entry' => $entry]);
exit;
