<?php
$pageTitle = 'Dashboard';
$role = currentRole();
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
    <h4 class="mb-0"><i class="bi bi-grid-3x3"></i> Work Progress Dashboard - <?= date('m/d/Y') ?></h4>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ((hasRole(['admin']) || hasReminderPermission()) && !empty($reminderTargets)): ?>
        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#reminderModal">
            <i class="bi bi-bell"></i> Send Reminder
        </button>
        <?php endif; ?>
        <a href="<?= e(appUrl('?action=dashboard_export')) ?>" class="btn btn-sm btn-outline-success">
            <i class="bi bi-file-earmark-excel"></i> Export .xlsx
        </a>
    </div>
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
            <?php if (hasRole(['admin']) || hasReminderPermission()): ?><th class="text-center">Last Reminder</th><?php endif; ?>
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
                <?php $s1IsAuto = (($s1['bank_feed_mode'] ?? 'manual') === 'automatic'); ?>
                <div class="stage-actions">
                    <span class="led led-<?= e($s1['status']) ?>"
                          data-led
                          <?php if (!$s1IsAuto && in_array($s1['status'], ['green', 'orange'])): ?>data-led-clickable<?php endif; ?>
                          data-period-id="<?= $pid ?>"
                          data-stage="stage1"
                          data-account-id="<?= $s1['account_id'] ?>"
                          data-client-name="<?= e($period['client_name']) ?>"
                          data-period-label="<?= e($period['period_label']) ?>"
                          data-account-name="<?= e($s1['account_name']) ?>"
                          title="<?= e(ucfirst($s1['status'])) ?>"></span>
                    <?php if (!$locked && hasRole(stageUploadRoles('stage1'))): ?>
                        <div class="stage-icon-wrap">
                        <form method="POST" action="<?= e(appUrl('?action=upload')) ?>" enctype="multipart/form-data" class="d-inline upload-form" data-client-name="<?= e($period['client_name']) ?>">
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
                        <div class="stage-date-label"><?= !empty($s1['last_upload_at']) ? date('m/d', strtotime($s1['last_upload_at'])) : '' ?></div>
                        </div>
                    <?php endif; ?>
                    <div class="stage-icon-wrap">
                    <?php $s1HasFile = $data['s1Files'][$s1['account_id']] ?? false; ?>
                    <?php if ($s1HasFile && hasRole(stageDownloadRoles('stage1'))): ?>
                        <a href="<?= e(appUrl('?action=download&period_id=' . $pid . '&stage=stage1&account_id=' . $s1['account_id'])) ?>"
                           title="Download"
                           class="download-link"
                           data-download-trigger
                           data-period-id="<?= $pid ?>"
                           data-stage="stage1"
                           data-account-id="<?= $s1['account_id'] ?>">
                            <img src="<?= e(assetUrl('img/download.png')) ?>" alt="Download" class="action-icon download-icon" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                            <i class="bi bi-cloud-arrow-down action-icon-fallback download-icon-fallback" style="display:none;"></i>
                        </a>
                    <?php else: ?>
                        <span class="download-link disabled-download" title="<?= $s1HasFile ? 'You do not have permission to download' : 'No file available to download' ?>">
                            <img src="<?= e(assetUrl('img/download.png')) ?>" alt="Download" class="action-icon download-icon" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                            <i class="bi bi-cloud-arrow-down action-icon-fallback download-icon-fallback" style="display:none;"></i>
                        </span>
                    <?php endif; ?>
                    <div class="stage-date-label"><?= !empty($s1['last_download_at']) ? date('m/d', strtotime($s1['last_download_at'])) : '' ?></div>
                    </div>
                    <?php
                        $s1NoteKey = 'stage1_' . (int)$s1['account_id'];
                        $s1Note    = $data['notes'][$s1NoteKey] ?? '';
                        $s1HasNotes = $s1Note !== '';
                    ?>
                    <button type="button"
                            class="btn p-0 border-0 bg-transparent note-btn<?= $s1HasNotes ? ' note-has-content' : '' ?>"
                            data-bs-toggle="modal" data-bs-target="#noteModal"
                            data-period-id="<?= $pid ?>"
                            data-period-label="<?= e($period['period_label']) ?>"
                            data-stage="stage1"
                            data-account-id="<?= (int)$s1['account_id'] ?>"
                            data-note="<?= e($s1Note) ?>"
                            title="<?= $s1HasNotes ? 'View Notes' : 'Add Note' ?>">
                        <i class="bi bi-chat-left-text note-icon"></i>
                    </button>
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
                        <span class="led led-<?= e($ss['status']) ?>"
                              data-led
                              <?php if (in_array($ss['status'], ['green', 'orange'])): ?>data-led-clickable<?php endif; ?>
                              data-period-id="<?= $pid ?>"
                              data-stage="<?= $sn ?>"
                              data-client-name="<?= e($period['client_name']) ?>"
                              data-period-label="<?= e($period['period_label']) ?>"
                              title="<?= e(ucfirst($ss['status'])) ?>"></span>
                        <?php if (!$locked && hasRole(stageUploadRoles($sn))): ?>
                            <div class="stage-icon-wrap">
                            <form method="POST" action="<?= e(appUrl('?action=upload')) ?>" enctype="multipart/form-data" class="d-inline upload-form" data-client-name="<?= e($period['client_name']) ?>">
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
                            <div class="stage-date-label"><?= !empty($ss['last_upload_at']) ? date('m/d', strtotime($ss['last_upload_at'])) : '' ?></div>
                            </div>
                        <?php endif; ?>
                        <div class="stage-icon-wrap">
                        <?php if ($hasFile && hasRole(stageDownloadRoles($sn))): ?>
                            <a href="<?= e(appUrl('?action=download&period_id=' . $pid . '&stage=' . $sn)) ?>"
                               title="Download"
                               class="download-link"
                               data-download-trigger
                               data-period-id="<?= $pid ?>"
                               data-stage="<?= $sn ?>">
                                <img src="<?= e(assetUrl('img/download.png')) ?>" alt="Download" class="action-icon download-icon" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                                <i class="bi bi-cloud-arrow-down action-icon-fallback download-icon-fallback" style="display:none;"></i>
                            </a>
                        <?php else: ?>
                            <span class="download-link disabled-download" title="<?= $hasFile ? 'You do not have permission to download' : 'No file available to download' ?>">
                                <img src="<?= e(assetUrl('img/download.png')) ?>" alt="Download" class="action-icon download-icon" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                                <i class="bi bi-cloud-arrow-down action-icon-fallback download-icon-fallback" style="display:none;"></i>
                            </span>
                        <?php endif; ?>
                        <div class="stage-date-label"><?= !empty($ss['last_download_at']) ? date('m/d', strtotime($ss['last_download_at'])) : '' ?></div>
                        </div>
                        <?php
                            $ssNoteKey = $sn . '_0';
                            $ssNote    = $data['notes'][$ssNoteKey] ?? '';
                            $ssHasNotes = $ssNote !== '';
                        ?>
                        <button type="button"
                                class="btn p-0 border-0 bg-transparent note-btn<?= $ssHasNotes ? ' note-has-content' : '' ?>"
                                data-bs-toggle="modal" data-bs-target="#noteModal"
                                data-period-id="<?= $pid ?>"
                                data-period-label="<?= e($period['period_label']) ?>"
                                data-stage="<?= $sn ?>"
                                data-account-id="0"
                                data-note="<?= e($ssNote) ?>"
                                title="<?= $ssHasNotes ? 'View Notes' : 'Add Note' ?>">
                            <i class="bi bi-chat-left-text note-icon"></i>
                        </button>
                    </div>
                </td>
                <?php endforeach; ?>

                <?php if (hasRole(['admin']) || hasReminderPermission()): ?>
                <td rowspan="<?= $groupRows ?>" class="text-center align-middle">
                    <?php
                        $cid = (int)$period['client_id'];
                        $lastRemindedAt = $lastReminderByClientId[$cid] ?? null;
                    ?>
                    <?php if ($lastRemindedAt): ?>
                        <small class="text-muted"><?= date('m/d/Y', strtotime($lastRemindedAt)) ?></small>
                    <?php else: ?>
                        <small class="text-muted">—</small>
                    <?php endif; ?>
                </td>
                <?php endif; ?>

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

<?php if ($totalPages > 1): ?>
<nav aria-label="Dashboard pagination" class="mt-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <small class="text-muted">
            Showing clients
            <?= (($page - 1) * $perPage + 1) ?>–<?= min($page * $perPage, $totalClients) ?>
            of <?= $totalClients ?>
        </small>
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= e(appUrl('?action=dashboard&page=' . ($page - 1))) ?>" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
            <?php
            $window = 2;
            $start  = max(1, $page - $window);
            $end    = min($totalPages, $page + $window);
            if ($start > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="<?= e(appUrl('?action=dashboard&page=1')) ?>">1</a>
                </li>
                <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
            <?php endif; ?>
            <?php for ($p = $start; $p <= $end; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= e(appUrl('?action=dashboard&page=' . $p)) ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
            <?php if ($end < $totalPages): ?>
                <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                <li class="page-item">
                    <a class="page-link" href="<?= e(appUrl('?action=dashboard&page=' . $totalPages)) ?>"><?= $totalPages ?></a>
                </li>
            <?php endif; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= e(appUrl('?action=dashboard&page=' . ($page + 1))) ?>" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        </ul>
    </div>
</nav>
<?php endif; ?>

<?php endif; ?>

<!-- Stage Note Modal -->
<div class="modal fade" id="noteModal" tabindex="-1" aria-labelledby="noteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:460px;">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title mb-0" id="noteModalLabel"><i class="bi bi-chat-left-text"></i> Stage Notes</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <div id="noteModalMeta" class="text-muted small px-3 pt-2 pb-1"></div>
        <div id="noteChatArea" class="note-chat-area px-3"></div>
        <div id="noteChatEmpty" class="text-muted small text-center py-3" style="display:none;">No notes yet.</div>
      </div>
      <div class="modal-footer py-2 px-3">
        <div class="input-group input-group-sm w-100 align-items-end">
          <textarea id="noteMessageInput" class="form-control" placeholder="Type a note..." maxlength="1000" rows="1" style="resize:none;overflow:hidden;"></textarea>
          <button type="button" class="btn btn-primary" id="noteSendBtn" title="Send">
            <i class="bi bi-send"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Stage Files Modal -->
<div class="modal fade" id="stageFilesModal" tabindex="-1" aria-labelledby="stageFilesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:520px;">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title mb-0" id="stageFilesModalLabel"><i class="bi bi-folder2-open"></i> Uploaded Files</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body py-2">
        <div id="stageFilesMeta" class="text-muted small mb-2"></div>
        <div id="stageFilesLoading" class="text-center py-3">
          <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
          <span class="ms-2 text-muted small">Loading files...</span>
        </div>
        <div id="stageFilesEmpty" class="text-muted small text-center py-3" style="display:none;">No files found.</div>
        <div class="table-responsive" id="stageFilesTableWrap" style="display:none;">
          <table class="table table-sm table-bordered mb-0" style="font-size:0.8rem;">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>File Name</th>
                <th>Uploaded</th>
                <th>By</th>
              </tr>
            </thead>
            <tbody id="stageFilesBody"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php if ((hasRole(['admin']) || hasReminderPermission()) && !empty($reminderTargets)): ?>
<style>
.reminder-modal-dialog { max-width: 95vw; }
@media (min-width: 768px) { .reminder-modal-dialog { max-width: 66vw; } }
</style>
<!-- Send Reminder Confirmation Modal -->
<div class="modal fade" id="reminderModal" tabindex="-1" aria-labelledby="reminderModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered reminder-modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="reminderModalLabel"><i class="bi bi-bell"></i> Confirm Send Reminders</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="<?= e(appUrl('?action=reminder_bulk')) ?>" id="reminderForm">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <div class="modal-body">
          <p class="mb-2">Select the clients to whom you want to send a reminder email:</p>
          <div class="mb-2">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="reminderSelectAll" checked>
              <label class="form-check-label fw-semibold" for="reminderSelectAll">Select All</label>
            </div>
          </div>
          <table class="table table-sm table-bordered align-middle mb-1" id="reminderClientList">
            <thead class="table-light">
              <tr>
                <th style="width:2rem;"></th>
                <th>Client</th>
                <th>Email</th>
                <th class="text-nowrap">Last Reminder</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($reminderTargets as $t): ?>
            <tr>
              <td class="text-center">
                <input class="form-check-input reminder-client-check" type="checkbox"
                       name="selected_emails[]" value="<?= e($t['email']) ?>"
                       id="rc_<?= e(md5($t['email'])) ?>" checked>
              </td>
              <td>
                <label class="form-check-label fw-semibold" for="rc_<?= e(md5($t['email'])) ?>"><?= e($t['name']) ?></label>
              </td>
              <td><small class="text-muted"><?= e($t['email']) ?></small></td>
              <td class="text-nowrap">
                <small class="text-muted">
                  <?= $t['last_reminder'] ? date('m/d/Y', strtotime($t['last_reminder'])) : '<span class="fst-italic">Never</span>' ?>
                </small>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning" id="reminderSubmitBtn"><i class="bi bi-send"></i> Send Reminders</button>
        </div>
      </form>
      <script>
      (function () {
        const selectAll   = document.getElementById('reminderSelectAll');
        const checks      = () => document.querySelectorAll('.reminder-client-check');
        const submitBtn   = document.getElementById('reminderSubmitBtn');

        function syncSubmitBtn() {
          const anyChecked = [...checks()].some(c => c.checked);
          submitBtn.disabled = !anyChecked;
        }

        selectAll.addEventListener('change', function () {
          checks().forEach(c => { c.checked = this.checked; });
          syncSubmitBtn();
        });

        document.getElementById('reminderClientList').addEventListener('change', function () {
          const all  = [...checks()];
          const checked = all.filter(c => c.checked);
          selectAll.checked       = checked.length === all.length;
          selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
          syncSubmitBtn();
        });
      })();
      </script>
    </div>
  </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include BASE_PATH . '/views/layout.php';
?>
