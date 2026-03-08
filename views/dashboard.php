<?php
$pageTitle = 'Dashboard';
$role = currentRole();
ob_start();
?>

<h4 class="mb-3"><i class="bi bi-grid-3x3"></i> Work Progress Dashboard</h4>

<?php if (empty($dashboardData)): ?>
    <div class="alert alert-info">
        No periods found. 
        <?php if (hasRole(['admin'])): ?>
            <a href="/dash/?action=clients">Create clients and periods</a> to get started.
        <?php endif; ?>
    </div>
<?php else: ?>

<div class="table-responsive">
<table class="table table-bordered table-hover dashboard-table align-middle">
    <thead class="table-dark">
        <tr>
            <th>Client</th>
            <th>Period</th>
            <th>Account</th>
            <th class="text-center">Stage 1<br><small>Initial Upload</small></th>
            <th class="text-center">Stage 2<br><small>Processed</small></th>
            <th class="text-center">Stage 3<br><small>Reclassified</small></th>
            <th class="text-center">Stage 4<br><small>Reclass. Complete</small></th>
            <th class="text-center">Reminder</th>
            <th class="text-center">Lock</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($dashboardData as $data):
        $period = $data['period'];
        $pid    = $period['id'];
        $locked = $period['is_locked'];
        $s1rows = $data['s1statuses'];
        $stages = $data['stageStatuses'];
        $totalRows = count($s1rows) + 1; // account rows + 1 period row
    ?>

        <?php foreach ($s1rows as $idx => $s1): ?>
        <tr class="<?= $locked ? 'table-secondary locked-row' : '' ?>">
            <?php if ($idx === 0): ?>
            <td rowspan="<?= $totalRows ?>" class="fw-bold align-middle client-cell">
                <?= e($period['client_name']) ?>
            </td>
            <td rowspan="<?= $totalRows ?>" class="align-middle period-cell">
                <?= e($period['period_label']) ?>
                <?php if ($locked): ?>
                    <br><span class="badge bg-danger"><i class="bi bi-lock-fill"></i> LOCKED</span>
                <?php endif; ?>
            </td>
            <?php endif; ?>

            <td><?= e($s1['account_name']) ?></td>

            <!-- Stage 1 LED + actions (account-wise) -->
            <td class="text-center">
                <span class="led led-<?= e($s1['status']) ?>" title="<?= e(ucfirst($s1['status'])) ?>"></span>
                <?php if (!$locked && hasRole(stageRoles('stage1'))): ?>
                    <form method="POST" action="/dash/?action=upload" enctype="multipart/form-data" class="d-inline upload-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="period_id" value="<?= $pid ?>">
                        <input type="hidden" name="stage" value="stage1">
                        <input type="hidden" name="account_id" value="<?= $s1['account_id'] ?>">
                        <input type="file" name="file" class="d-none file-input" required>
                        <button type="button" class="btn btn-sm btn-outline-primary upload-btn" title="Upload">
                            <i class="bi bi-cloud-arrow-up"></i>
                        </button>
                    </form>
                <?php endif; ?>
                <?php if ($data['s1Files'][$s1['account_id']] ?? false): ?>
                    <a href="/dash/?action=download&period_id=<?= $pid ?>&stage=stage1&account_id=<?= $s1['account_id'] ?>"
                       class="btn btn-sm btn-outline-success" title="Download">
                        <i class="bi bi-cloud-arrow-down"></i>
                    </a>
                <?php endif; ?>
            </td>

            <!-- Empty stage 2-4 cells for account rows -->
            <td></td><td></td><td></td>
            <td></td><td></td>
        </tr>
        <?php endforeach; ?>

        <!-- Period summary row (Stages 2-4 + Reminder + Lock) -->
        <tr class="<?= $locked ? 'table-secondary locked-row' : 'table-light' ?>">
            <?php if (empty($s1rows)): ?>
            <td class="fw-bold align-middle client-cell"><?= e($period['client_name']) ?></td>
            <td class="align-middle period-cell">
                <?= e($period['period_label']) ?>
                <?php if ($locked): ?>
                    <br><span class="badge bg-danger"><i class="bi bi-lock-fill"></i> LOCKED</span>
                <?php endif; ?>
            </td>
            <?php endif; ?>
            <td class="text-muted fst-italic"><small>Period actions →</small></td>
            <td></td>

            <?php foreach (['stage2', 'stage3', 'stage4'] as $sn):
                $ss = $stages[$sn] ?? ['status' => 'grey'];
                $hasFile = $data['has' . ucfirst($sn) . 'File'] ?? false;
            ?>
            <td class="text-center">
                <span class="led led-<?= e($ss['status']) ?>" title="<?= e(ucfirst($ss['status'])) ?>"></span>
                <?php if (!$locked && hasRole(stageRoles($sn))): ?>
                    <form method="POST" action="/dash/?action=upload" enctype="multipart/form-data" class="d-inline upload-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="period_id" value="<?= $pid ?>">
                        <input type="hidden" name="stage" value="<?= $sn ?>">
                        <input type="file" name="file" class="d-none file-input" required>
                        <button type="button" class="btn btn-sm btn-outline-primary upload-btn" title="Upload">
                            <i class="bi bi-cloud-arrow-up"></i>
                        </button>
                    </form>
                <?php endif; ?>
                <?php if ($hasFile): ?>
                    <a href="/dash/?action=download&period_id=<?= $pid ?>&stage=<?= $sn ?>"
                       class="btn btn-sm btn-outline-success" title="Download">
                        <i class="bi bi-cloud-arrow-down"></i>
                    </a>
                <?php endif; ?>
            </td>
            <?php endforeach; ?>

            <!-- Reminder -->
            <td class="text-center">
                <?php if (!$locked && $data['showReminder'] && hasRole(['admin'])): ?>
                    <form method="POST" action="/dash/?action=reminder" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="period_id" value="<?= $pid ?>">
                        <button type="submit" class="btn btn-sm btn-warning" title="Send Reminder"
                                onclick="return confirm('Send reminder email to client?')">
                            <i class="bi bi-bell"></i> Remind
                        </button>
                    </form>
                <?php endif; ?>
            </td>

            <!-- Lock -->
            <td class="text-center">
                <?php if ($locked): ?>
                    <span class="badge bg-danger"><i class="bi bi-lock-fill"></i> Locked</span>
                <?php elseif ($data['showLock'] && hasRole(['admin'])): ?>
                    <form method="POST" action="/dash/?action=lock" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="period_id" value="<?= $pid ?>">
                        <button type="submit" class="btn btn-sm btn-danger" title="Lock Period"
                                onclick="return confirm('Lock this period? This cannot be undone. Uploads will be disabled.')">
                            <i class="bi bi-lock"></i> Lock
                        </button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>

    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php endif; ?>

<?php
$content = ob_get_clean();
include BASE_PATH . '/views/layout.php';
?>
