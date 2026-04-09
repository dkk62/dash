<?php
require_once BASE_PATH . '/models/Period.php';
require_once BASE_PATH . '/models/Client.php';
require_once BASE_PATH . '/models/Account.php';
require_once BASE_PATH . '/models/Stage1Status.php';
require_once BASE_PATH . '/models/StageStatus.php';
require_once BASE_PATH . '/models/User.php';

$role = currentRole();

// Load all periods with client info
$periods = Period::allWithClient();
sortPeriodsChronologically($periods);

// Exclude archived clients from pending list
$periods = array_values(array_filter($periods, fn($p) => !(int)($p['client_is_archived'] ?? 0)));

// Filter to only past periods (exclude current and future months)
$periods = array_values(array_filter($periods, fn($p) => isPeriodBeforeCurrentPeriod($p['period_label'])));

// Bulk-load all status data
$allPeriodIds = array_map(fn($p) => (int)$p['id'], $periods);
$bulkS1     = Stage1Status::bulkByPeriods($allPeriodIds);
$bulkStages = StageStatus::bulkByPeriods($allPeriodIds);

// Collect all period labels and client names
$allClientNames = [];
foreach ($periods as $p) {
    $cid = (int) $p['client_id'];
    $allClientNames[$cid] = $p['client_name'];
}

// Collect all unique labels, sorted chronologically
$allLabelsSet = [];
foreach ($periods as $p) {
    $allLabelsSet[$p['period_label']] = true;
}
$allLabels = array_keys($allLabelsSet);
usort($allLabels, fn($a, $b) => periodLabelSortTimestamp($a) <=> periodLabelSortTimestamp($b));

/**
 * Group periods by client from a filtered list.
 */
function groupPeriodsByClient(array $periods): array {
    $result = [];
    foreach ($periods as $p) {
        $cid = (int) $p['client_id'];
        $result[$cid][$p['period_label']] = $p;
    }
    return $result;
}

/**
 * Build pending data for a processor0-role user's clients.
 */
function buildProcessor0Pending(array $clientPeriods, array $bulkS1, array $bulkStages): array {
    $stage1Pending = [];
    $stage3Pending = [];

    foreach ($clientPeriods as $cid => $periodsByLabel) {
        foreach ($periodsByLabel as $label => $p) {
            $pid = (int) $p['id'];
            $s1statuses = $bulkS1[$pid] ?? [];
            $stageStatuses = $bulkStages[$pid] ?? [];

            foreach ($s1statuses as $s1) {
                if ($s1['status'] === 'grey') {
                    $aid = (int) $s1['account_id'];
                    if (!isset($stage1Pending[$cid][$aid])) {
                        $stage1Pending[$cid][$aid] = [
                            'account_name' => $s1['account_name'],
                            'bank_feed_mode' => $s1['bank_feed_mode'] ?? 'manual',
                            'periods' => [],
                        ];
                    }
                    $stage1Pending[$cid][$aid]['periods'][$label] = true;
                }
            }

            $stage2Status = $stageStatuses['stage2']['status'] ?? 'grey';
            $stage3Status = $stageStatuses['stage3']['status'] ?? 'grey';
            if ($stage2Status !== 'grey' && $stage3Status === 'grey') {
                if (!isset($stage3Pending[$cid])) {
                    $stage3Pending[$cid] = ['periods' => []];
                }
                $stage3Pending[$cid]['periods'][$label] = true;
            }
        }
    }

    return ['stage1' => $stage1Pending, 'stage3' => $stage3Pending];
}

/**
 * Build pending data for a processor1-role user's clients.
 */
function buildProcessor1Pending(array $clientPeriods, array $bulkS1, array $bulkStages): array {
    $stage2Pending = [];
    $stage4Pending = [];

    foreach ($clientPeriods as $cid => $periodsByLabel) {
        foreach ($periodsByLabel as $label => $p) {
            $pid = (int) $p['id'];
            $s1statuses = $bulkS1[$pid] ?? [];
            $stageStatuses = $bulkStages[$pid] ?? [];

            $allS1NonGrey = !empty($s1statuses);
            foreach ($s1statuses as $s1) {
                if ($s1['status'] === 'grey') {
                    $allS1NonGrey = false;
                    break;
                }
            }
            $stage2Status = $stageStatuses['stage2']['status'] ?? 'grey';
            if ($allS1NonGrey && $stage2Status === 'grey') {
                if (!isset($stage2Pending[$cid])) {
                    $stage2Pending[$cid] = ['periods' => []];
                }
                $stage2Pending[$cid]['periods'][$label] = true;
            }

            $stage3Status = $stageStatuses['stage3']['status'] ?? 'grey';
            $stage4Status = $stageStatuses['stage4']['status'] ?? 'grey';
            if ($stage3Status !== 'grey' && $stage4Status === 'grey') {
                if (!isset($stage4Pending[$cid])) {
                    $stage4Pending[$cid] = ['periods' => []];
                }
                $stage4Pending[$cid]['periods'][$label] = true;
            }
        }
    }

    return ['stage2' => $stage2Pending, 'stage4' => $stage4Pending];
}

/**
 * Filter periods to only those belonging to given client IDs.
 */
function filterPeriodsByClients(array $periods, array $clientIds): array {
    $set = array_flip($clientIds);
    return array_values(array_filter($periods, fn($p) => isset($set[(int) $p['client_id']])));
}

// Build data based on role
if ($role === 'admin') {
    // Admin: build per-user pending data for all processors
    $processorUsers = User::byRoles(['processor0', 'processor1']);
    $adminPendingByUser = []; // [userId => ['user'=>..., 'p0Data'=>..., 'p1Data'=>...]]

    foreach ($processorUsers as $user) {
        $uid = (int) $user['id'];
        $userClientIds = Client::getClientIdsForUser($uid);
        if (empty($userClientIds)) continue;

        $userPeriods = filterPeriodsByClients($periods, $userClientIds);
        if (empty($userPeriods)) continue;

        $userClientPeriods = groupPeriodsByClient($userPeriods);

        $p0 = null;
        $p1 = null;
        if ($user['role'] === 'processor0') {
            $p0 = buildProcessor0Pending($userClientPeriods, $bulkS1, $bulkStages);
        } elseif ($user['role'] === 'processor1') {
            $p1 = buildProcessor1Pending($userClientPeriods, $bulkS1, $bulkStages);
        }

        // Only include if there's actual pending work
        $hasPending = false;
        if ($p0) {
            $hasPending = !empty($p0['stage1']) || !empty($p0['stage3']);
        }
        if ($p1) {
            $hasPending = $hasPending || !empty($p1['stage2']) || !empty($p1['stage4']);
        }
        if (!$hasPending) continue;

        $adminPendingByUser[$uid] = [
            'user' => $user,
            'p0Data' => $p0,
            'p1Data' => $p1,
        ];
    }
} elseif ($role === 'processor0') {
    $assignedClientIds = Client::getClientIdsForUser((int) $_SESSION['user_id']);
    $myPeriods = filterPeriodsByClients($periods, $assignedClientIds);
    $myClientPeriods = groupPeriodsByClient($myPeriods);
    $p0Data = buildProcessor0Pending($myClientPeriods, $bulkS1, $bulkStages);
} elseif ($role === 'processor1') {
    $assignedClientIds = Client::getClientIdsForUser((int) $_SESSION['user_id']);
    $myPeriods = filterPeriodsByClients($periods, $assignedClientIds);
    $myClientPeriods = groupPeriodsByClient($myPeriods);
    $p1Data = buildProcessor1Pending($myClientPeriods, $bulkS1, $bulkStages);
}

include BASE_PATH . '/views/pending.php';
