<?php
$pageTitle = 'Dashboard';
$role = currentRole();
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
    <h4 class="mb-0"><i class="bi bi-grid-3x3"></i> Work Progress Dashboard - <?= date('m/d/Y') ?></h4>
    <a href="<?= e(appUrl('?action=dashboard_export')) ?>" class="btn btn-sm btn-outline-success">
        <i class="bi bi-file-earmark-excel"></i> Export .xlsx
    </a>
</div>

<?php if (empty($dashboardData)): ?>
    <div class="alert alert-info">
        No periods found. 
        <?php if (hasRole(['admin'])): ?>
            <a href="<?= e(appUrl('?action=clients')) ?>">Create clients and periods</a> to get started.
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
            <th class="text-center">Locked</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($dashboardData as $groupIdx => $data):
        $period = $data['period'];
        $pid    = $period['id'];
        $locked = $period['is_locked'];
        $s1rows = $data['s1statuses'];
        $stages = $data['stageStatuses'];
        $groupRows = max(1, count($s1rows));
        $groupClass = ($groupIdx % 2 === 0) ? 'group-even' : 'group-odd';
        $prevClientId = $groupIdx > 0 ? (int) $dashboardData[$groupIdx - 1]['period']['client_id'] : null;
        $isClientStart = ($groupIdx === 0 || (int) $period['client_id'] !== $prevClientId);
    ?>

        <?php for ($idx = 0; $idx < $groupRows; $idx++):
            $s1 = $s1rows[$idx] ?? null;
            $clientStartClass = ($idx === 0 && $isClientStart) ? 'client-start' : '';
        ?>
        <tr class="<?= $groupClass ?> <?= $clientStartClass ?> <?= $locked ? 'locked-row' : '' ?>">
            <?php if ($idx === 0): ?>
            <td rowspan="<?= $groupRows ?>" class="fw-bold align-middle client-cell">
                <?= e($period['client_name']) ?>
            </td>
            <td rowspan="<?= $groupRows ?>" class="align-middle period-cell">
                <?= e($period['period_label']) ?>
                <?php if ($locked): ?>
                    <br><span class="badge bg-danger"><i class="bi bi-lock-fill"></i> Locked</span>
                <?php endif; ?>
            </td>
            <?php endif; ?>

            <td>
                <?php if ($s1): ?>
                    <?= e($s1['account_name']) ?><?= (($s1['bank_feed_mode'] ?? 'manual') === 'automatic') ? ' (auto)' : '' ?>
                <?php else: ?>
                    <span class="text-muted">-</span>
                <?php endif; ?>
            </td>

            <td class="text-center">
                <?php if ($s1): ?>
                <div class="stage-actions">
                    <span class="led led-<?= e($s1['status']) ?>" title="<?= e(ucfirst($s1['status'])) ?>"></span>
                    <?php if (!$locked && hasRole(stageUploadRoles('stage1'))): ?>
                        <form method="POST" action="<?= e(appUrl('?action=upload')) ?>" enctype="multipart/form-data" class="d-inline upload-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="period_id" value="<?= $pid ?>">
                            <input type="hidden" name="stage" value="stage1">
                            <input type="hidden" name="account_id" value="<?= $s1['account_id'] ?>">
                            <input type="file" name="files[]" class="d-none file-input" required multiple>
                            <button type="button" class="btn p-0 border-0 bg-transparent upload-btn" title="Upload">
                                <img src="<?= e(assetUrl('img/upload.png')) ?>" alt="Upload" class="action-icon upload-icon" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                                <i class="bi bi-cloud-arrow-up action-icon-fallback upload-icon-fallback" style="display:none;"></i>
                            </button>
                            <div class="upload-progress" hidden>
                                <div class="upload-progress-label">Uploading... <span class="upload-progress-percent">0%</span></div>
                                <div class="progress upload-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                                    <div class="progress-bar upload-progress-bar" style="width: 0%"></div>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                    <?php $s1HasFile = $data['s1Files'][$s1['account_id']] ?? false; ?>
                    <?php if ($s1HasFile && hasRole(stageDownloadRoles('stage1'))): ?>
                        <a href="<?= e(appUrl('?action=download&period_id=' . $pid . '&stage=stage1&account_id=' . $s1['account_id'])) ?>" title="Download" class="download-link">
                            <img src="<?= e(assetUrl('img/download.png')) ?>" alt="Download" class="action-icon download-icon" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                            <i class="bi bi-cloud-arrow-down action-icon-fallback download-icon-fallback" style="display:none;"></i>
                        </a>
                    <?php else: ?>
                        <span class="download-link disabled-download" title="<?= $s1HasFile ? 'You do not have permission to download' : 'No file available to download' ?>">
                            <img src="<?= e(assetUrl('img/download.png')) ?>" alt="Download" class="action-icon download-icon" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                            <i class="bi bi-cloud-arrow-down action-icon-fallback download-icon-fallback" style="display:none;"></i>
                        </span>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                    <span class="text-muted">-</span>
                <?php endif; ?>
            </td>

            <?php if ($idx === 0): ?>
                <?php foreach (['stage2', 'stage3', 'stage4'] as $sn):
                    $ss = $stages[$sn] ?? ['status' => 'grey'];
                    $hasFile = $data['has' . ucfirst($sn) . 'File'] ?? false;
                ?>
                <td rowspan="<?= $groupRows ?>" class="text-center align-middle">
                    <div class="stage-actions">
                        <span class="led led-<?= e($ss['status']) ?>" title="<?= e(ucfirst($ss['status'])) ?>"></span>
                        <?php if (!$locked && hasRole(stageUploadRoles($sn))): ?>
                            <form method="POST" action="<?= e(appUrl('?action=upload')) ?>" enctype="multipart/form-data" class="d-inline upload-form">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="period_id" value="<?= $pid ?>">
                                <input type="hidden" name="stage" value="<?= $sn ?>">
                                <input type="file" name="files[]" class="d-none file-input" required multiple>
                                <button type="button" class="btn p-0 border-0 bg-transparent upload-btn" title="Upload">
                                    <img src="<?= e(assetUrl('img/upload.png')) ?>" alt="Upload" class="action-icon upload-icon" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                                    <i class="bi bi-cloud-arrow-up action-icon-fallback upload-icon-fallback" style="display:none;"></i>
                                </button>
                                <div class="upload-progress" hidden>
                                    <div class="upload-progress-label">Uploading... <span class="upload-progress-percent">0%</span></div>
                                    <div class="progress upload-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                                        <div class="progress-bar upload-progress-bar" style="width: 0%"></div>
                                    </div>
                                </div>
                            </form>
                        <?php endif; ?>
                        <?php if ($hasFile && hasRole(stageDownloadRoles($sn))): ?>
                            <a href="<?= e(appUrl('?action=download&period_id=' . $pid . '&stage=' . $sn)) ?>" title="Download" class="download-link">
                                <img src="<?= e(assetUrl('img/download.png')) ?>" alt="Download" class="action-icon download-icon" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                                <i class="bi bi-cloud-arrow-down action-icon-fallback download-icon-fallback" style="display:none;"></i>
                            </a>
                        <?php else: ?>
                            <span class="download-link disabled-download" title="<?= $hasFile ? 'You do not have permission to download' : 'No file available to download' ?>">
                                <img src="<?= e(assetUrl('img/download.png')) ?>" alt="Download" class="action-icon download-icon" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                                <i class="bi bi-cloud-arrow-down action-icon-fallback download-icon-fallback" style="display:none;"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                </td>
                <?php endforeach; ?>

                <td rowspan="<?= $groupRows ?>" class="text-center align-middle">
                    <?php if (!$locked && $data['showReminder'] && hasRole(['admin'])): ?>
                        <form method="POST" action="<?= e(appUrl('?action=reminder')) ?>" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="period_id" value="<?= $pid ?>">
                            <button type="submit" class="btn btn-sm btn-warning" title="Send Reminder"
                                    onclick="return confirm('Send reminder email to client?')">
                                <i class="bi bi-bell"></i> Remind
                            </button>
                        </form>
                    <?php endif; ?>
                </td>

                <td rowspan="<?= $groupRows ?>" class="text-center align-middle">
                    <?php if ($locked): ?>
                        <?php if (hasRole(['admin'])): ?>
                            <form method="POST" action="<?= e(appUrl('?action=unlock')) ?>" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="period_id" value="<?= $pid ?>">
                                <input type="hidden" name="mode" value="unlock">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Unlock Period"
                                        onclick="return confirm('Unlock this period? Uploads will be enabled again.')">
                                    <i class="bi bi-unlock"></i> Unlock
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="badge bg-danger" title="Locked"><i class="bi bi-lock-fill"></i></span>
                        <?php endif; ?>
                    <?php elseif ($data['showLock'] && hasRole(['admin'])): ?>
                        <form method="POST" action="<?= e(appUrl('?action=lock')) ?>" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="period_id" value="<?= $pid ?>">
                            <input type="hidden" name="mode" value="lock">
                            <button type="submit" class="btn btn-sm btn-danger" title="Lock Period"
                                    onclick="return confirm('Lock this period? This cannot be undone. Uploads will be disabled.')">
                                <i class="bi bi-lock"></i> Lock
                            </button>
                        </form>
                    <?php endif; ?>
                </td>
            <?php endif; ?>
        </tr>
        <?php endfor; ?>

    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php endif; ?>

<?php
$content = ob_get_clean();
include BASE_PATH . '/views/layout.php';
?>
