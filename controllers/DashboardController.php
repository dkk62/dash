<?php
require_once BASE_PATH . '/models/Period.php';
require_once BASE_PATH . '/models/Client.php';
require_once BASE_PATH . '/models/Account.php';
require_once BASE_PATH . '/models/Stage1Status.php';
require_once BASE_PATH . '/models/StageStatus.php';
require_once BASE_PATH . '/models/FileRecord.php';

// Load all periods with client info
$periods = Period::allWithClient();

// Build dashboard data
$dashboardData = [];

foreach ($periods as $period) {
    $pid = $period['id'];
    $cid = $period['client_id'];

    // Stage 1 statuses (account-wise)
    $s1statuses = Stage1Status::byPeriod($pid);

    // Stages 2-4 statuses
    $stageStatuses = StageStatus::byPeriod($pid);

    // Check if reminder should be shown
    $showReminder = Stage1Status::anyGreyOrOrange($pid);

    // Check if lock button should be shown
    $s1AllOrange   = Stage1Status::allOrange($pid);
    $s234AllOrange = StageStatus::allOrange($pid);
    $showLock      = $s1AllOrange && $s234AllOrange && !$period['is_locked'];

    // Check which stages have files
    $s1Files = [];
    foreach ($s1statuses as $s1) {
        $s1Files[$s1['account_id']] = FileRecord::hasFile($pid, 'stage1', $s1['account_id']);
    }

    $dashboardData[] = [
        'period'        => $period,
        's1statuses'    => $s1statuses,
        'stageStatuses' => $stageStatuses,
        'showReminder'  => $showReminder,
        'showLock'      => $showLock,
        's1Files'       => $s1Files,
        'hasStage2File' => FileRecord::hasFile($pid, 'stage2'),
        'hasStage3File' => FileRecord::hasFile($pid, 'stage3'),
        'hasStage4File' => FileRecord::hasFile($pid, 'stage4'),
    ];
}

include BASE_PATH . '/views/dashboard.php';
