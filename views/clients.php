<?php
$pageTitle = 'Manage Clients';
ob_start();
?>

<div class="row">
    <div class="col-md-8">
        <h4><i class="bi bi-people"></i> Clients</h4>
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Cycle</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($clients as $c): ?>
                <tr>
                    <td><?= e($c['name']) ?></td>
                    <td><?= e($c['email']) ?></td>
                    <td><?= e($c['phone'] ?? '') ?></td>
                    <td><span class="badge bg-secondary"><?= e($c['cycle_type']) ?></span></td>
                    <td>
                        <a href="/dash/?action=accounts&client_id=<?= $c['id'] ?>" class="btn btn-sm btn-info" title="Accounts">
                            <i class="bi bi-bank"></i>
                        </a>
                        <a href="/dash/?action=periods&client_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-dark" title="Periods">
                            <i class="bi bi-calendar3"></i>
                        </a>
                        <a href="/dash/?action=client_edit&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST" action="/dash/?action=client_delete" class="d-inline"
                              onsubmit="return confirm('Delete this client and all related data?')">
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
                <tr><td colspan="5" class="text-muted text-center">No clients yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="col-md-4">
        <h4><?= $editClient ? 'Edit Client' : 'Add Client' ?></h4>
        <form method="POST" action="/dash/?action=client_save">
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
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg"></i> <?= $editClient ? 'Update' : 'Create' ?>
            </button>
            <?php if ($editClient): ?>
                <a href="/dash/?action=clients" class="btn btn-secondary">Cancel</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include BASE_PATH . '/views/layout.php';
?>
