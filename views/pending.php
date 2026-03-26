<?php
$pageTitle = 'Pending Work';
$role = currentRole();
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
    <h4 class="mb-0"><i class="bi bi-hourglass-split"></i> Pending Work</h4>
</div>

<?php
/**
 * Flatten pending data into display rows.
 */
function buildP0Rows(array $p0Data, array $clientNames): array {
    $stage1Rows = [];
    $stage3Rows = [];
    foreach ($p0Data['stage1'] as $cid => $accounts) {
        foreach ($accounts as $aid => $info) {
            $acctDisplay = $info['account_name'];
            if (($info['bank_feed_mode'] ?? 'manual') === 'automatic') {
                $acctDisplay .= ' (auto)';
            }
            $stage1Rows[] = [
                'client_name'  => $clientNames[$cid] ?? '',
                'account_name' => $acctDisplay,
                'periods'      => $info['periods'],
            ];
        }
    }
    foreach ($p0Data['stage3'] as $cid => $info) {
        $stage3Rows[] = [
            'client_name' => $clientNames[$cid] ?? '',
            'periods'     => $info['periods'],
        ];
    }
    return [$stage1Rows, $stage3Rows];
}

function buildP1Rows(array $p1Data, array $clientNames): array {
    $stage2Rows = [];
    $stage4Rows = [];
    foreach ($p1Data['stage2'] as $cid => $info) {
        $stage2Rows[] = [
            'client_name' => $clientNames[$cid] ?? '',
            'periods'     => $info['periods'],
        ];
    }
    foreach ($p1Data['stage4'] as $cid => $info) {
        $stage4Rows[] = [
            'client_name' => $clientNames[$cid] ?? '',
            'periods'     => $info['periods'],
        ];
    }
    return [$stage2Rows, $stage4Rows];
}

// Helper to render a period grid table
function renderPendingGrid(string $title, string $stageLabel, array $clientRows, array $allLabels, bool $showAccount = false): void {
    if (empty($clientRows)) {
        echo '<div class="alert alert-success mb-4"><i class="bi bi-check-circle"></i> ' . e($title) . ' &mdash; No pending items.</div>';
        return;
    }
    ?>
    <div class="card mb-4 pending-card">
        <div class="card-header bg-dark text-white">
            <strong><?= e($title) ?></strong>
            <span class="badge bg-warning text-dark ms-2"><?= e($stageLabel) ?></span>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm mb-0 pending-table align-middle">
                <thead class="table-secondary">
                    <tr>
                        <th class="sticky-col">Client</th>
                        <?php if ($showAccount): ?><th>Account</th><?php endif; ?>
                        <?php foreach ($allLabels as $label): ?>
                            <th class="text-center period-col"><?= e($label) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($clientRows as $row): ?>
                    <tr>
                        <td class="fw-bold sticky-col"><?= e($row['client_name']) ?></td>
                        <?php if ($showAccount): ?><td><?= e($row['account_name']) ?></td><?php endif; ?>
                        <?php foreach ($allLabels as $label): ?>
                            <td class="text-center">
                                <?php if (!empty($row['periods'][$label])): ?>
                                    <span class="led led-grey" title="Pending"></span>
                                <?php else: ?>
                                    <span class="text-muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
?>

<?php if ($role === 'admin'): ?>
    <?php if (empty($adminPendingByUser)): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> No pending work across all processors.</div>
    <?php else: ?>
        <?php foreach ($adminPendingByUser as $uid => $entry):
            $user = $entry['user'];
            $userName = $user['name'];
            $userRole = $user['role'];
            $roleLabel = $userRole === 'processor0' ? 'Processor 0' : 'Processor 1';
        ?>
            <div class="card mb-4 border-secondary">
                <div class="card-header bg-secondary text-white d-flex align-items-center gap-2">
                    <i class="bi bi-person-fill"></i>
                    <strong><?= e($userName) ?></strong>
                    <span class="badge bg-info"><?= e($roleLabel) ?></span>
                </div>
                <div class="card-body p-2">
                    <?php if ($entry['p0Data']): ?>
                        <?php [$s1Rows, $s3Rows] = buildP0Rows($entry['p0Data'], $allClientNames); ?>
                        <?php renderPendingGrid('Stage 1 — Initial Upload Pending', 'Stage 1', $s1Rows, $allLabels, true); ?>
                        <?php renderPendingGrid('Stage 3 — Reclassification Pending', 'Stage 3', $s3Rows, $allLabels, false); ?>
                    <?php endif; ?>
                    <?php if ($entry['p1Data']): ?>
                        <?php [$s2Rows, $s4Rows] = buildP1Rows($entry['p1Data'], $allClientNames); ?>
                        <?php renderPendingGrid('Stage 2 — Processing Pending', 'Stage 2', $s2Rows, $allLabels, false); ?>
                        <?php renderPendingGrid('Stage 4 — Reclass. Complete Pending', 'Stage 4', $s4Rows, $allLabels, false); ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php elseif ($role === 'processor0'): ?>
    <?php [$s1Rows, $s3Rows] = buildP0Rows($p0Data, $allClientNames); ?>
    <?php renderPendingGrid('Stage 1 — Initial Upload Pending', 'Stage 1', $s1Rows, $allLabels, true); ?>
    <?php renderPendingGrid('Stage 3 — Reclassification Pending (Stage 2 completed)', 'Stage 3', $s3Rows, $allLabels, false); ?>
<?php elseif ($role === 'processor1'): ?>
    <?php [$s2Rows, $s4Rows] = buildP1Rows($p1Data, $allClientNames); ?>
    <?php renderPendingGrid('Stage 2 — Processing Pending (All Stage 1 completed)', 'Stage 2', $s2Rows, $allLabels, false); ?>
    <?php renderPendingGrid('Stage 4 — Reclass. Complete Pending (Stage 3 completed)', 'Stage 4', $s4Rows, $allLabels, false); ?>
<?php else: ?>
    <div class="alert alert-info">Pending work view is available for processor and admin roles.</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include BASE_PATH . '/views/layout.php';
?>
