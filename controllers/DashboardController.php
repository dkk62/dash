<?php
require_once BASE_PATH . '/models/Period.php';
require_once BASE_PATH . '/models/Client.php';
require_once BASE_PATH . '/models/Account.php';
require_once BASE_PATH . '/models/Stage1Status.php';
require_once BASE_PATH . '/models/StageStatus.php';
require_once BASE_PATH . '/models/FileRecord.php';

/**
 * Check if a period label is monthly format like "Jan 26".
 */
function isMonthlyLabel(string $label): bool {
    return (bool) preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+[0-9]{2}$/', $label);
}

/**
 * Check if a period label is fiscal format like "FY 26".
 */
function isFiscalLabel(string $label): bool {
    return (bool) preg_match('/^FY\s+[0-9]{2}$/', $label);
}

/**
 * Determine whether the period should be visible by date.
 * Monthly: show only up to the current month.
 * Fiscal: show only up to the current FY suffix.
 */
function isPeriodVisibleByDate(string $label): bool {
    $nowYear2 = (int) date('y');

    if (isMonthlyLabel($label)) {
        $periodTs = strtotime('01 ' . $label);
        if ($periodTs === false) {
            return false;
        }
        $periodYear = (int) date('y', $periodTs);
        $periodMonth = (int) date('n', $periodTs);
        $nowMonth = (int) date('n');

        if ($periodYear < $nowYear2) {
            return true;
        }
        if ($periodYear > $nowYear2) {
            return false;
        }
        return $periodMonth <= $nowMonth;
    }

    if (isFiscalLabel($label)) {
        preg_match('/^FY\s+([0-9]{2})$/', $label, $m);
        $fy = (int) ($m[1] ?? 0);
        return $fy <= $nowYear2;
    }

    // For custom labels, keep visible.
    return true;
}

/**
 * A period is considered active if any stage moved from grey or any file exists.
 */
function periodHasActivity(array $s1statuses, array $stageStatuses, array $s1Files, bool $has2, bool $has3, bool $has4): bool {
    // Stage1 status can be orange by default for automatic bank feeds.
    // Treat actual stage1 files as activity instead of raw status for visibility filtering.
    foreach (['stage2', 'stage3', 'stage4'] as $sn) {
        if (($stageStatuses[$sn]['status'] ?? 'grey') !== 'grey') {
            return true;
        }
    }
    if ($has2 || $has3 || $has4) {
        return true;
    }
    foreach ($s1Files as $exists) {
        if ($exists) {
            return true;
        }
    }
    return false;
}

// Load all periods with client info
$periods = Period::allWithClient();
sortPeriodsChronologically($periods);

// Filter periods if user is a client
if (currentRole() === 'client' && !empty($_SESSION['client_id'])) {
    $clientId = (int) $_SESSION['client_id'];
    $periods = array_filter($periods, function($p) use ($clientId) {
        return (int) $p['client_id'] === $clientId;
    });
}

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

    $hasStage2File = FileRecord::hasFile($pid, 'stage2');
    $hasStage3File = FileRecord::hasFile($pid, 'stage3');
    $hasStage4File = FileRecord::hasFile($pid, 'stage4');

    // Hide future empty rows from dashboard, but always show active rows.
    $hasActivity = periodHasActivity($s1statuses, $stageStatuses, $s1Files, $hasStage2File, $hasStage3File, $hasStage4File);
    $visibleByDate = isPeriodVisibleByDate($period['period_label']);
    if (!$hasActivity && !$visibleByDate) {
        continue;
    }

    $dashboardData[] = [
        'period'        => $period,
        's1statuses'    => $s1statuses,
        'stageStatuses' => $stageStatuses,
        'showReminder'  => $showReminder,
        'showLock'      => $showLock,
        's1Files'       => $s1Files,
        'hasStage2File' => $hasStage2File,
        'hasStage3File' => $hasStage3File,
        'hasStage4File' => $hasStage4File,
    ];
}

include BASE_PATH . '/views/dashboard.php';
