<?php
$pageTitle = 'Documents';
$role = currentRole();
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
    <h4 class="mb-0"><i class="bi bi-file-earmark-text"></i> Client Documents - <?= date('m/d/Y') ?></h4>
</div>

<?php if ($role === 'client'): ?>
<?php if (!empty($clientEntities) && count($clientEntities) > 1): ?>
<div class="alert alert-secondary py-2 mb-3 d-flex align-items-center flex-wrap gap-2">
    <strong class="me-2"><i class="bi bi-building"></i> Select Entity:</strong>
    <?php foreach ($clientEntities as $entity): ?>
        <?php
            $isCurrentEntity = ((int) $entity['id'] === (int) ($selectedClientId ?? 0));
        ?>
        <a href="<?= e(appUrl('?action=documents&client_id=' . (int) $entity['id'])) ?>"
           class="btn btn-sm <?= $isCurrentEntity ? 'btn-dark' : 'btn-outline-dark' ?>">
            <?= e($entity['name']) ?>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="table-responsive">
<table class="table table-bordered table-hover dashboard-table doc-table align-middle">
    <thead class="table-dark">
        <tr>
            <th>Date</th>
            <th class="text-center" style="width:80px;">Files</th>
            <th class="text-center" style="width:110px;">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!$todayHasFiles): ?>
        <tr class="table-warning">
            <td class="fst-italic text-muted align-middle" style="font-size:0.85rem;">Upload files for a new day here</td>
            <td class="text-center align-middle">—</td>
            <td class="text-center align-middle">
                <form method="POST"
                      action="<?= e(appUrl('?action=doc_upload')) ?>"
                      enctype="multipart/form-data"
                      class="d-inline doc-upload-form"
                      data-client-name="<?= e($selectedClientName) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="client_id" value="<?= (int) $selectedClientId ?>">
                    <input type="file" name="files[]" class="d-none doc-file-input" multiple>
                    <button type="button" class="btn p-0 border-0 bg-transparent doc-upload-btn"
                            title="Upload documents for today">
                        <i class="bi bi-cloud-arrow-up action-icon-fallback upload-icon-fallback"></i>
                    </button>
                    <div class="upload-progress" hidden>
                        <div class="upload-progress-label">Uploading... <span class="upload-progress-percent">0%</span></div>
                        <div class="progress upload-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                            <div class="progress-bar upload-progress-bar" style="width: 0%"></div>
                        </div>
                    </div>
                </form>
            </td>
        </tr>
        <?php endif; ?>

        <?php if (empty($dateRows)): ?>
        <tr><td colspan="3" class="text-center text-muted py-3">No documents uploaded yet.</td></tr>
        <?php else: ?>
        <?php foreach ($dateRows as $dr):
            $drDate    = $dr['upload_date'];
            $drCount   = (int) $dr['file_count'];
            $drDateFmt = date('m/d/Y', strtotime($drDate));
            $isToday   = ($drDate === date('Y-m-d'));
        ?>
        <tr>
            <td class="fw-semibold align-middle"><?= e($drDateFmt) ?></td>
            <td class="text-center align-middle"><span class="badge bg-secondary"><?= $drCount ?></span></td>
            <td class="text-center align-middle">
                <div class="stage-actions">
                    <?php if ($isToday): ?>
                    <div class="stage-icon-wrap">
                        <form method="POST"
                              action="<?= e(appUrl('?action=doc_upload')) ?>"
                              enctype="multipart/form-data"
                              class="d-inline doc-upload-form"
                              data-client-name="<?= e($selectedClientName) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="client_id" value="<?= (int) $selectedClientId ?>">
                            <input type="file" name="files[]" class="d-none doc-file-input" multiple>
                            <button type="button" class="btn p-0 border-0 bg-transparent doc-upload-btn"
                                    title="Upload more documents for today">
                                <i class="bi bi-cloud-arrow-up action-icon-fallback upload-icon-fallback"></i>
                            </button>
                            <div class="upload-progress" hidden>
                                <div class="upload-progress-label">Uploading... <span class="upload-progress-percent">0%</span></div>
                                <div class="progress upload-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                                    <div class="progress-bar upload-progress-bar" style="width: 0%"></div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                    <div class="stage-icon-wrap">
                        <button type="button"
                                class="btn p-0 border-0 bg-transparent doc-date-view-btn"
                                data-client-id="<?= (int) $selectedClientId ?>"
                                data-date="<?= e($drDate) ?>"
                                data-date-label="<?= e($drDateFmt) ?>"
                                title="View files for <?= e($drDateFmt) ?>">
                            <i class="bi bi-folder2-open action-icon-fallback" style="color:#0d6efd;font-size:18px;"></i>
                        </button>
                    </div>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
</div>

<?php else: ?>
<!-- Admin / Processor view -->
<?php if (empty($clientData)): ?>
    <div class="alert alert-info">No clients found.</div>
<?php else: ?>
<div class="table-responsive">
<table class="table table-bordered table-hover dashboard-table doc-table align-middle">
    <thead class="table-dark">
        <tr>
            <th>Client</th>
            <th class="text-center">Total Files</th>
            <th class="text-center">Not Downloaded</th>
            <th class="text-center">Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($clientData as $cdIdx => $cd):
        $client     = $cd['client'];
        $cid        = (int) $client['id'];
        $totalFiles = (int) $cd['totalFiles'];
        $newFiles   = (int) $cd['newFiles'];
        $groupClass = ($cdIdx % 2 === 0) ? 'group-even' : 'group-odd';
    ?>
        <tr class="<?= $groupClass ?> client-start">
            <td class="fw-bold align-middle client-cell"><?= e($client['name']) ?></td>
            <td class="text-center align-middle">
                <?php if ($totalFiles > 0): ?>
                    <span class="badge bg-secondary"><?= $totalFiles ?></span>
                <?php else: ?>
                    <span class="text-muted">—</span>
                <?php endif; ?>
            </td>
            <td class="text-center align-middle">
                <?php if ($newFiles > 0): ?>
                    <span class="badge bg-success"><?= $newFiles ?></span>
                <?php else: ?>
                    <span class="text-muted">—</span>
                <?php endif; ?>
            </td>
            <td class="text-center align-middle">
                <div class="stage-actions">
                    <div class="stage-icon-wrap">
                    <?php if ($totalFiles > 0): ?>
                        <button type="button" class="btn p-0 border-0 bg-transparent admin-doc-view-btn"
                                data-client-id="<?= $cid ?>"
                                data-client-name="<?= e($client['name']) ?>"
                                title="View Documents">
                            <i class="bi bi-folder2-open action-icon-fallback" style="color:#0d6efd;font-size:18px;"></i>
                        </button>
                    <?php else: ?>
                        <span title="No documents uploaded">
                            <i class="bi bi-folder2-open action-icon-fallback" style="color:#aaa;font-size:18px;"></i>
                        </span>
                    <?php endif; ?>
                    </div>
                    <div class="stage-icon-wrap">
                        <form method="POST"
                              action="<?= e(appUrl('?action=doc_upload')) ?>"
                              enctype="multipart/form-data"
                              class="d-inline doc-upload-form"
                              data-client-name="<?= e($client['name']) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="client_id" value="<?= $cid ?>">
                            <input type="file" name="files[]" class="d-none doc-file-input" multiple>
                            <button type="button" class="btn p-0 border-0 bg-transparent doc-upload-btn"
                                    title="Upload documents for <?= e($client['name']) ?>">
                                <i class="bi bi-cloud-arrow-up action-icon-fallback upload-icon-fallback"></i>
                            </button>
                            <div class="upload-progress" hidden>
                                <div class="upload-progress-label">Uploading... <span class="upload-progress-percent">0%</span></div>
                                <div class="progress upload-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                                    <div class="progress-bar upload-progress-bar" style="width: 0%"></div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Date Files Modal (client: view files for a specific date) -->
<div class="modal fade" id="docDateFilesModal" tabindex="-1" aria-labelledby="docDateFilesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:560px;">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title mb-0" id="docDateFilesModalLabel"><i class="bi bi-folder2-open"></i> Files</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body py-2">
        <div id="docDateMeta" class="text-muted small mb-2"></div>
        <div id="docDateLoading" class="text-center py-3">
          <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
          <span class="ms-2 text-muted small">Loading...</span>
        </div>
        <div id="docDateEmpty" class="text-muted small text-center py-3" style="display:none;">No files found.</div>
        <div class="table-responsive" id="docDateTableWrap" style="display:none;">
          <table class="table table-sm table-bordered mb-0" style="font-size:0.8rem;">
            <thead class="table-light">
              <tr><th>#</th><th>File Name</th><th>Time</th><th>By</th><th class="text-center">Actions</th></tr>
            </thead>
            <tbody id="docDateBody"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-warning btn-sm" id="docDateDownloadBtn" style="display:none;">
          <i class="bi bi-download"></i> Download All
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Admin Document Modal (date breakdown + file drill-down) -->
<div class="modal fade" id="adminDocModal" tabindex="-1" aria-labelledby="adminDocModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:640px;">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title mb-0" id="adminDocModalLabel"><i class="bi bi-folder2-open"></i> Documents</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body py-2">
        <div id="adminDocDatePanel">
          <div id="adminDocDateMeta" class="text-muted small mb-2"></div>
          <div id="adminDocDateLoading" class="text-center py-3">
            <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
            <span class="ms-2 text-muted small">Loading...</span>
          </div>
          <div id="adminDocDateEmpty" class="text-muted small text-center py-3" style="display:none;">No documents found.</div>
          <div class="table-responsive" id="adminDocDateTableWrap" style="display:none;">
            <table class="table table-sm table-bordered mb-0" style="font-size:0.8rem;">
              <thead class="table-light">
                <tr><th>Date</th><th class="text-center">Files</th><th class="text-center">View</th></tr>
              </thead>
              <tbody id="adminDocDateBody"></tbody>
            </table>
          </div>
        </div>
        <div id="adminDocFilePanel" style="display:none;">
          <div class="mb-2 d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="adminDocBackBtn">
              <i class="bi bi-arrow-left"></i> Back
            </button>
            <span id="adminDocFileMeta" class="text-muted small"></span>
          </div>
          <div id="adminDocFileLoading" class="text-center py-3">
            <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
            <span class="ms-2 text-muted small">Loading files...</span>
          </div>
          <div id="adminDocFileEmpty" class="text-muted small text-center py-3" style="display:none;">No files found.</div>
          <div class="table-responsive" id="adminDocFileTableWrap" style="display:none;">
            <table class="table table-sm table-bordered mb-0" style="font-size:0.8rem;">
              <thead class="table-light">
                <tr><th>#</th><th>File Name</th><th>Time</th><th>By</th><th class="text-center">Actions</th></tr>
              </thead>
              <tbody id="adminDocFileBody"></tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-warning btn-sm" id="adminDocDownloadDateBtn" style="display:none;">
          <i class="bi bi-download"></i> Download Date Files
        </button>
      </div>
    </div>
  </div>
</div>

<!-- File Preview Modal -->
<div class="modal fade" id="filePreviewModal" tabindex="-1" aria-labelledby="filePreviewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title mb-0 text-truncate" id="filePreviewModalLabel"><i class="bi bi-file-earmark-text"></i> <span id="filePreviewTitle"></span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-2 d-flex flex-column">
        <div id="filePreviewLoading" class="text-center py-4">
          <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
          <span class="ms-2 text-muted small">Loading preview...</span>
        </div>
        <div id="filePreviewBody"></div>
      </div>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
include BASE_PATH . '/views/layout.php';
?>
