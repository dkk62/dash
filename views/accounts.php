<?php
$pageTitle = 'Accounts - ' . e($client['name']);
ob_start();
?>

<h4><i class="bi bi-bank"></i> Accounts for <?= e($client['name']) ?></h4>
<a href="/dash/?action=clients" class="btn btn-sm btn-outline-secondary mb-3">
    <i class="bi bi-arrow-left"></i> Back to Clients
</a>

<div class="row">
    <div class="col-md-8">
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Account Name</th>
                    <th>Active</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($accounts as $a): ?>
                <tr>
                    <td><?= e($a['account_name']) ?></td>
                    <td>
                        <?= $a['is_active'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?>
                    </td>
                    <td>
                        <form method="POST" action="/dash/?action=account_delete" class="d-inline"
                              onsubmit="return confirm('Delete this account?')">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <input type="hidden" name="client_id" value="<?= $clientId ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($accounts)): ?>
                <tr><td colspan="3" class="text-muted text-center">No accounts yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="col-md-4">
        <h5>Add Account</h5>
        <form method="POST" action="/dash/?action=account_save">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="client_id" value="<?= $clientId ?>">
            <div class="mb-3">
                <label class="form-label">Account Name *</label>
                <input type="text" name="account_name" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Add Account
            </button>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include BASE_PATH . '/views/layout.php';
?>
