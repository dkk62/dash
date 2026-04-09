<?php
$pageTitle = 'Manage Clients';
ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-people"></i> Clients</h4>
    <button class="btn btn-primary" id="addClientBtn">
        <i class="bi bi-plus-lg me-1"></i>Add Client
    </button>
</div>

<table class="table table-bordered table-hover">
    <thead class="table-dark">
        <tr>
            <th>SL</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Password</th>
            <th>Cycle</th>
            <th>Processor 0</th>
            <th>Processor 1</th>
            <th style="min-width: 220px;">Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php $sl = 1; ?>
    <?php foreach ($clients as $c): ?>
        <tr>
            <td><?= $sl++ ?></td>
            <td>
                <?= e($c['name']) ?>
                <?php if (!empty($c['is_archived'])): ?>
                    <span class="badge bg-danger fw-bold ms-1">Archived</span>
                <?php endif; ?>
            </td>
            <td><?= e($c['email']) ?></td>
            <td><?= e($c['phone'] ?? '') ?></td>
            <td><?= !empty($c['password_hash']) ? '<span class="badge bg-success">Set</span>' : '<span class="badge bg-warning text-dark">Not Set</span>' ?></td>
            <td><span class="badge bg-secondary"><?= e($c['cycle_type']) ?></span></td>
            <td><?= $c['processor0_name'] ? e($c['processor0_name']) : '<span class="text-muted">—</span>' ?></td>
            <td><?= $c['processor1_name'] ? e($c['processor1_name']) : '<span class="text-muted">—</span>' ?></td>
            <td class="text-nowrap">
                <a href="<?= e(appUrl('?action=accounts&client_id=' . $c['id'])) ?>" class="btn btn-sm btn-info" title="Accounts">
                    <i class="bi bi-bank"></i>
                </a>
                <a href="<?= e(appUrl('?action=periods&client_id=' . $c['id'])) ?>" class="btn btn-sm btn-outline-dark" title="Periods">
                    <i class="bi bi-calendar3"></i>
                </a>
                <button type="button" class="btn btn-sm btn-outline-primary edit-client-btn" title="Edit"
                    data-id="<?= $c['id'] ?>"
                    data-name="<?= e($c['name']) ?>"
                    data-email="<?= e($c['email']) ?>"
                    data-phone="<?= e($c['phone'] ?? '') ?>"
                    data-cycle_type="<?= e($c['cycle_type']) ?>"
                    data-processor0_id="<?= (int)($c['processor0_id'] ?? 0) ?>"
                    data-processor1_id="<?= (int)($c['processor1_id'] ?? 0) ?>"
                    data-has_password="<?= !empty($c['password_hash']) ? '1' : '0' ?>"
                    data-is_archived="<?= (int)($c['is_archived'] ?? 0) ?>">
                    <i class="bi bi-pencil"></i>
                </button>
                <form method="POST" action="<?= e(appUrl('?action=client_delete')) ?>" class="d-inline confirm-delete"
                      data-confirm-title="Delete Client"
                      data-confirm-message="Are you sure you want to delete &quot;<?= e($c['name']) ?>&quot;?"
                      data-confirm-warning="This will permanently remove all accounts, periods, uploaded files, and related data for this client. This action cannot be undone.">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" title="Delete">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
                <?php if (hasRole(['admin'])): ?>
                <?php if (empty($c['is_archived'])): ?>
                <form method="POST" action="<?= e(appUrl('?action=client_archive')) ?>" class="d-inline confirm-delete"
                      data-confirm-title="Archive Client"
                      data-confirm-message="Are you sure you want to archive &quot;<?= e($c['name']) ?>&quot;?"
                      data-confirm-warning="This client will be hidden from the dashboard, pending list, and documents. You can unarchive it later."
                      data-confirm-button='<i class="bi bi-archive me-1"></i>Archive'>
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                    <input type="hidden" name="archive" value="1">
                    <button class="btn btn-sm btn-outline-secondary" title="Archive">
                        <i class="bi bi-archive"></i>
                    </button>
                </form>
                <?php else: ?>
                <form method="POST" action="<?= e(appUrl('?action=client_archive')) ?>" class="d-inline confirm-delete"
                      data-confirm-title="Unarchive Client"
                      data-confirm-message="Are you sure you want to unarchive &quot;<?= e($c['name']) ?>&quot;?"
                      data-confirm-warning="This client will reappear on the dashboard, pending list, and documents."
                      data-confirm-button='<i class="bi bi-archive me-1"></i>Unarchive'>
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                    <input type="hidden" name="archive" value="0">
                    <button class="btn btn-sm btn-outline-success" title="Unarchive">
                        <i class="bi bi-archive"></i>
                    </button>
                </form>
                <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($clients)): ?>
        <tr><td colspan="9" class="text-muted text-center">No clients yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<!-- Client Create/Edit Modal -->
<div class="modal fade" id="clientModal" tabindex="-1" aria-labelledby="clientModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title" id="clientModalLabel"><i class="bi bi-person-plus me-2"></i>Add Client</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="<?= e(appUrl('?action=client_save')) ?>" id="clientForm">
        <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id" id="clientFormId" value="0">
            <div class="mb-3">
                <label class="form-label">Name *</label>
                <input type="text" name="name" id="clientFormName" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email *</label>
                <input type="email" name="email" id="clientFormEmail" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label" id="clientFormPasswordLabel">Password</label>
                <input type="password" name="password" id="clientFormPassword" class="form-control" placeholder="Set client login password">
                <small class="form-text text-muted">If provided, this will be the password for the client to login and access their dashboard.</small>
            </div>
            <div class="mb-3">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" id="clientFormPhone" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label">Cycle Type</label>
                <select name="cycle_type" id="clientFormCycleType" class="form-select">
                    <option value="monthly">Monthly</option>
                    <option value="yearly">Yearly</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Processor 0</label>
                <select name="processor0_id" id="clientFormProcessor0" class="form-select">
                    <option value="">— None —</option>
                    <?php foreach ($processorUsers as $pu): ?>
                        <?php if ($pu['role'] === 'processor0'): ?>
                        <option value="<?= $pu['id'] ?>"><?= e($pu['name']) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Processor 1</label>
                <select name="processor1_id" id="clientFormProcessor1" class="form-select">
                    <option value="">— None —</option>
                    <?php foreach ($processorUsers as $pu): ?>
                        <?php if ($pu['role'] === 'processor1'): ?>
                        <option value="<?= $pu['id'] ?>"><?= e($pu['name']) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="clientFormSubmitBtn">
                <i class="bi bi-check-lg me-1"></i><span id="clientFormSubmitText">Create</span>
            </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var clientModal = new bootstrap.Modal(document.getElementById('clientModal'));
    var form = document.getElementById('clientForm');

    function resetForm() {
        form.reset();
        document.getElementById('clientFormId').value = '0';
        document.getElementById('clientModalLabel').innerHTML = '<i class="bi bi-person-plus me-2"></i>Add Client';
        document.getElementById('clientFormPasswordLabel').textContent = 'Password';
        document.getElementById('clientFormPassword').required = false;
        document.getElementById('clientFormSubmitText').textContent = 'Create';
    }

    document.getElementById('addClientBtn').addEventListener('click', function() {
        resetForm();
        clientModal.show();
    });

    document.querySelectorAll('.edit-client-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            resetForm();
            document.getElementById('clientFormId').value = btn.dataset.id;
            document.getElementById('clientFormName').value = btn.dataset.name;
            document.getElementById('clientFormEmail').value = btn.dataset.email;
            document.getElementById('clientFormPhone').value = btn.dataset.phone;
            document.getElementById('clientFormCycleType').value = btn.dataset.cycle_type;
            document.getElementById('clientFormProcessor0').value = btn.dataset.processor0_id || '';
            document.getElementById('clientFormProcessor1').value = btn.dataset.processor1_id || '';
            document.getElementById('clientModalLabel').innerHTML = '<i class="bi bi-pencil me-2"></i>Edit Client';
            document.getElementById('clientFormPasswordLabel').textContent = 'Password (leave empty to keep current)';
            document.getElementById('clientFormPassword').required = false;
            document.getElementById('clientFormSubmitText').textContent = 'Update';

            clientModal.show();
        });
    });

    <?php if ($editClient): ?>
    // Auto-open modal when arriving via ?action=client_edit
    document.querySelector('.edit-client-btn[data-id="<?= (int)$editClient['id'] ?>"]')?.click();
    <?php endif; ?>
});
</script>

<?php
$content = ob_get_clean();
include BASE_PATH . '/views/layout.php';
?>
