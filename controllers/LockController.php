<?php
require_once BASE_PATH . '/models/Period.php';
require_once BASE_PATH . '/models/Stage1Status.php';
require_once BASE_PATH . '/models/StageStatus.php';

$isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

function lockJsonResponse(bool $success, string $message, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) lockJsonResponse(false, 'Invalid request method.', 405);
    redirect('?action=dashboard');
}

requireCsrf();

$periodId = (int) ($_POST['period_id'] ?? 0);
$period   = Period::find($periodId);
$mode     = $_POST['mode'] ?? 'lock';

if (!$period) {
    if ($isAjax) lockJsonResponse(false, 'Period not found.', 404);
    setFlash('danger', 'Period not found.');
    redirect('?action=dashboard');
}

if ($mode === 'unlock') {
    if (!$period['is_locked']) {
        if ($isAjax) lockJsonResponse(false, 'Period is already unlocked.');
        setFlash('info', 'Period is already unlocked.');
        redirect('?action=dashboard');
    }

    Period::unlock($periodId);
    logAction('period_unlocked', $_SESSION['user_id'], $periodId, null, null, [
        'period_label' => $period['period_label'],
    ]);

    if ($isAjax) lockJsonResponse(true, 'Period unlocked successfully. Uploads are enabled again.');
    setFlash('success', 'Period unlocked successfully. Uploads are enabled again.');
    redirect('?action=dashboard');
}

if ($period['is_locked']) {
    if ($isAjax) lockJsonResponse(false, 'Period is already locked.');
    setFlash('info', 'Period is already locked.');
    redirect('?action=dashboard');
}

// Check all LEDs are orange before locking
$s1AllOrange = Stage1Status::allOrange($periodId);
$s234AllOrange = StageStatus::allOrange($periodId);

if (!$s1AllOrange || !$s234AllOrange) {
    if ($isAjax) lockJsonResponse(false, 'Cannot lock: not all stages are in Orange status.', 422);
    setFlash('danger', 'Cannot lock: not all stages are in Orange status.');
    redirect('?action=dashboard');
}

Period::lock($periodId);

logAction('period_locked', $_SESSION['user_id'], $periodId, null, null, [
    'period_label' => $period['period_label'],
]);

if ($isAjax) lockJsonResponse(true, 'Period locked successfully. Uploads are now disabled.');
setFlash('success', 'Period locked successfully. Uploads are now disabled.');
redirect('?action=dashboard');
