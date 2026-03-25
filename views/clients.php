<?php
$pageTitle = 'Manage Clients';
ob_start();
?>

<div class="row">
    <div class="col-md-9">
        <h4><i class="bi bi-people"></i> Clients</h4>
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
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php $sl = 1; ?>
            <?php foreach ($clients as $c): ?>
                <tr>
                    <td><?= $sl++ ?></td>
                    <td><?= e($c['name']) ?></td>
                    <td><?= e($c['email']) ?></td>
                    <td><?= e($c['phone'] ?? '') ?></td>
                    <td><?= !empty($c['password_hash']) ? '<span class="badge bg-success">Set</span>' : '<span class="badge bg-warning text-dark">Not Set</span>' ?></td>
                    <td><span class="badge bg-secondary"><?= e($c['cycle_type']) ?></span></td>
                    <td><?= $c['processor0_name'] ? e($c['processor0_name']) : '<span class="text-muted">—</span>' ?></td>
                    <td><?= $c['processor1_name'] ? e($c['processor1_name']) : '<span class="text-muted">—</span>' ?></td>
                    <td>
                        <a href="<?= e(appUrl('?action=accounts&client_id=' . $c['id'])) ?>" class="btn btn-sm btn-info" title="Accounts">
                            <i class="bi bi-bank"></i>
                        </a>
                        <a href="<?= e(appUrl('?action=periods&client_id=' . $c['id'])) ?>" class="btn btn-sm btn-outline-dark" title="Periods">
                            <i class="bi bi-calendar3"></i>
                        </a>
                        <a href="<?= e(appUrl('?action=client_edit&id=' . $c['id'])) ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </a>
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
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($clients)): ?>
                <tr><td colspan="9" class="text-muted text-center">No clients yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="col-md-3">
        <h4><?= $editClient ? 'Edit Client' : 'Add Client' ?></h4>
        <form method="POST" action="<?= e(appUrl('?action=client_save')) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <?php if ($editClient): ?>
                <input type="hidden" name="id" value="<?= $editClient['id'] ?>">
            <?php endif; ?>
            <div class="mb-3">
                <label class="form-label">Name *</label>
                <input type="text" name="name" class="form-control" required
                       value="<?= e($editClient['name'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Email *</label>
                <input type="email" name="email" class="form-control" required
                       value="<?= e($editClient['email'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label"><?= $editClient ? 'Password (leave empty to keep current)' : 'Password' ?></label>
                <input type="password" name="password" class="form-control" 
                       <?= !$editClient ? 'required' : '' ?> placeholder="Set client login password">
                <small class="form-text text-muted">If provided, this will be the password for the client to login and access their dashboard.</small>
            </div>
            <div class="mb-3">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control"
                       value="<?= e($editClient['phone'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Cycle Type</label>
                <select name="cycle_type" class="form-select">
                    <option value="monthly" <?= ($editClient['cycle_type'] ?? '') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                    <option value="yearly" <?= ($editClient['cycle_type'] ?? '') === 'yearly' ? 'selected' : '' ?>>Yearly</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Processor 0</label>
                <select name="processor0_id" class="form-select">
                    <option value="">— None —</option>
                    <?php foreach ($processorUsers as $pu): ?>
                        <?php if ($pu['role'] === 'processor0'): ?>
                        <option value="<?= $pu['id'] ?>"
                            <?= ((int)($editClient['processor0_id'] ?? 0) === (int)$pu['id']) ? 'selected' : '' ?>>
                            <?= e($pu['name']) ?>
                        </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Processor 1</label>
                <select name="processor1_id" class="form-select">
                    <option value="">— None —</option>
                    <?php foreach ($processorUsers as $pu): ?>
                        <?php if ($pu['role'] === 'processor1'): ?>
                        <option value="<?= $pu['id'] ?>"
                            <?= ((int)($editClient['processor1_id'] ?? 0) === (int)$pu['id']) ? 'selected' : '' ?>>
                            <?= e($pu['name']) ?>
                        </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg"></i> <?= $editClient ? 'Update' : 'Create' ?>
            </button>
            <?php if ($editClient): ?>
                <a href="<?= e(appUrl('?action=clients')) ?>" class="btn btn-secondary">Cancel</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include BASE_PATH . '/views/layout.php';
?>
