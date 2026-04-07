<?php
$pageTitle = 'Documents';
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
    <h4 class="mb-0"><i class="bi bi-file-earmark-text"></i> Client Documents - <?= date('m/d/Y') ?></h4>
</div>

<?php if (empty($clientData)): ?>
    <div class="alert alert-info">
        No clients found.
    </div>
<?php else: ?>

<div class="table-responsive">
<table class="table table-bordered table-hover dashboard-table doc-table align-middle">
    <thead class="table-dark">
        <tr>
            <th>Client</th>
            <th class="text-center">Documents</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($clientData as $cdIdx => $cd):
        $client  = $cd['client'];
        $cid     = (int) $client['id'];
        $hasDocFiles = $cd['hasDocFiles'];
        $groupClass = ($cdIdx % 2 === 0) ? 'group-even' : 'group-odd';
    ?>
        <tr class="<?= $groupClass ?> client-start">
            <td class="fw-bold align-middle client-cell">
                <?= e($client['name']) ?>
            </td>

            <td class="text-center align-middle">
                <div class="stage-actions">
                    <span class="led <?= $hasDocFiles ? 'led-green' : 'led-grey' ?>"
                          <?php if ($hasDocFiles): ?>data-doc-led-clickable<?php endif; ?>
                          data-client-id="<?= $cid ?>"
                          data-client-name="<?= e($client['name']) ?>"
                          title="<?= $hasDocFiles ? 'View Documents' : 'No documents' ?>"></span>

                    <div class="stage-icon-wrap">
                        <form method="POST" action="<?= e(appUrl('?action=doc_upload')) ?>" enctype="multipart/form-data" class="d-inline doc-upload-form" data-client-name="<?= e($client['name']) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="client_id" value="<?= $cid ?>">
                            <input type="file" name="files[]" class="d-none doc-file-input" multiple>
                            <button type="button" class="btn p-0 border-0 bg-transparent doc-upload-btn" title="Upload Document">
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

                    <div class="stage-icon-wrap">
                    <?php if ($hasDocFiles): ?>
                        <button type="button" class="btn p-0 border-0 bg-transparent doc-download-btn"
                                data-client-id="<?= $cid ?>"
                                data-client-name="<?= e($client['name']) ?>"
                                title="Download Documents">
                            <i class="bi bi-cloud-arrow-down action-icon-fallback download-icon-fallback"></i>
                        </button>
                    <?php else: ?>
                        <span class="download-link disabled-download" title="No documents uploaded">
                            <i class="bi bi-cloud-arrow-down action-icon-fallback download-icon-fallback"></i>
                        </span>
                    <?php endif; ?>
                    </div>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<!-- Document Files Modal (LED click - view uploaded files) -->
<div class="modal fade" id="docFilesModal" tabindex="-1" aria-labelledby="docFilesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:520px;">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title mb-0" id="docFilesModalLabel"><i class="bi bi-folder2-open"></i> Uploaded Documents</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body py-2">
        <div id="docViewMeta" class="text-muted small mb-2"></div>
        <div id="docViewLoading" class="text-center py-3">
          <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
          <span class="ms-2 text-muted small">Loading documents...</span>
        </div>
        <div id="docViewEmpty" class="text-muted small text-center py-3" style="display:none;">No documents found.</div>
        <div class="table-responsive" id="docViewTableWrap" style="display:none;">
          <table class="table table-sm table-bordered mb-0" style="font-size:0.8rem;">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>File Name</th>
                <th>Uploaded</th>
                <th>By</th>
              </tr>
            </thead>
            <tbody id="docViewBody"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Document Download Modal (select files to download) -->
<div class="modal fade" id="docDownloadModal" tabindex="-1" aria-labelledby="docDownloadModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:560px;">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title mb-0" id="docDownloadModalLabel"><i class="bi bi-download"></i> Download Documents</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body py-2">
        <div id="docDlMeta" class="text-muted small mb-2"></div>
        <div id="docDlLoading" class="text-center py-3">
          <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
          <span class="ms-2 text-muted small">Loading documents...</span>
        </div>
        <div id="docDlEmpty" class="text-muted small text-center py-3" style="display:none;">No documents found.</div>
        <div class="table-responsive" id="docDlTableWrap" style="display:none;">
          <table class="table table-sm table-bordered mb-0" style="font-size:0.8rem;">
            <thead class="table-light">
              <tr>
                <th style="width:2rem;"><input type="checkbox" id="docSelectAll" checked></th>
                <th>#</th>
                <th>File Name</th>
                <th>Uploaded</th>
                <th>By</th>
              </tr>
            </thead>
            <tbody id="docDlBody"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-warning btn-sm" id="docDownloadSelectedBtn" disabled>
          <i class="bi bi-download"></i> Download Selected
        </button>
      </div>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
include BASE_PATH . '/views/layout.php';
?>
