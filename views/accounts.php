<?php
$pageTitle = 'Accounts - ' . e($client['name']);
ob_start();
?>

<h4><i class="bi bi-bank"></i> Accounts for <?= e($client['name']) ?></h4>
<a href="<?= e(appUrl('?action=clients')) ?>" class="btn btn-sm btn-outline-secondary mb-3">
    <i class="bi bi-arrow-left"></i> Back to Clients
</a>

<div class="row">
    <div class="col-md-8">
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Account Name</th>
                    <th>Bank Feeds</th>
                    <th>Active</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($accounts as $a): ?>
                <?php $rowFormId = 'account-edit-' . (int) $a['id']; ?>
                <tr>
                    <td>
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            name="account_name"
                            value="<?= e($a['account_name']) ?>"
                            form="<?= $rowFormId ?>"
                            required
                        >
                    </td>
                    <td>
                        <div class="form-check form-switch m-0 d-inline-block">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                role="switch"
                                name="bank_feed_mode"
                                value="automatic"
                                form="<?= $rowFormId ?>"
                                <?= (($a['bank_feed_mode'] ?? 'manual') === 'automatic') ? 'checked' : '' ?>
                            >
                        </div>
                    </td>
                    <td>
                        <div class="form-check m-0 d-inline-block">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="is_active"
                                value="1"
                                form="<?= $rowFormId ?>"
                                <?= ((int) ($a['is_active'] ?? 1) === 1) ? 'checked' : '' ?>
                            >
                        </div>
                    </td>
                    <td>
                        <form method="POST" action="<?= e(appUrl('?action=account_save')) ?>" id="<?= $rowFormId ?>" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="client_id" value="<?= (int) $clientId ?>">
                            <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                        </form>
                        <button type="submit" class="btn btn-sm btn-outline-primary" form="<?= $rowFormId ?>" title="Save Changes">
                            <i class="bi bi-check2"></i>
                        </button>
                        <form method="POST" action="<?= e(appUrl('?action=account_delete')) ?>" class="d-inline"
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
                <tr><td colspan="4" class="text-muted text-center">No accounts yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="col-md-4">
        <h5>Add Account</h5>
        <form method="POST" action="<?= e(appUrl('?action=account_save')) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="client_id" value="<?= $clientId ?>">
            <input type="hidden" name="id" value="0">
            <div class="mb-3">
                <label class="form-label">Account Name *</label>
                <input type="text" name="account_name" class="form-control" required>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="bankFeedModeSwitch" name="bank_feed_mode" value="automatic">
                <label class="form-check-label" for="bankFeedModeSwitch">Bank Feeds: Automatic</label>
                <div class="form-text">Default is Manual when this is OFF.</div>
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
