<?php
require_once BASE_PATH . '/models/Period.php';
require_once BASE_PATH . '/models/Stage1Status.php';
require_once BASE_PATH . '/models/StageStatus.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('?action=dashboard');
}

requireCsrf();

$periodId = (int) ($_POST['period_id'] ?? 0);
$period   = Period::find($periodId);

if (!$period) {
    setFlash('danger', 'Period not found.');
    redirect('?action=dashboard');
}

if ($period['is_locked']) {
    setFlash('info', 'Period is already locked.');
    redirect('?action=dashboard');
}

// Check all LEDs are orange
$s1AllOrange = Stage1Status::allOrange($periodId);
$s234AllOrange = StageStatus::allOrange($periodId);

if (!$s1AllOrange || !$s234AllOrange) {
    setFlash('danger', 'Cannot lock: not all stages are in Orange status.');
    redirect('?action=dashboard');
}

Period::lock($periodId);

logAction('period_locked', $_SESSION['user_id'], $periodId, null, null, [
    'period_label' => $period['period_label'],
]);

setFlash('success', 'Period locked successfully. Uploads are now disabled.');
redirect('?action=dashboard');
