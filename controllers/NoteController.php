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
$note      = trim($_POST['note'] ?? '');

$validStages = ['stage1', 'stage2', 'stage3', 'stage4'];
if ($periodId <= 0 || !in_array($stage, $validStages, true)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Only the eligible uploader role for this stage may save a note
if (!hasRole(stageUploadRoles($stage))) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authorised to add notes to this stage']);
    exit;
}

if (mb_strlen($note) > 1000) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Note too long (max 1000 characters)']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
StageNote::save($periodId, $stage, $accountId, $note, $userId);

header('Content-Type: application/json');
echo json_encode(['success' => true, 'note' => $note]);
exit;
