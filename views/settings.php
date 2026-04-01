<?php
$pageTitle = 'Settings';
ob_start();
?>

<h4><i class="bi bi-gear"></i> Settings</h4>

<form method="POST" action="<?= e(appUrl('?action=settings_save')) ?>">
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

    <div class="row">
        <div class="col-md-6">
            <!-- Permission: Client Creation/Editing -->
            <div class="card mb-4">
                <div class="card-header bg-dark text-white">
                    <i class="bi bi-people"></i> Client Creation / Editing Permission
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Select users who are allowed to create and edit clients (in addition to admin).</p>
                    <?php if (empty($allUsers)): ?>
                        <p class="text-muted">No processor users found.</p>
                    <?php else: ?>
                        <?php foreach ($allUsers as $u): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox"
                                       name="perm_client_edit[]"
                                       value="<?= (int)$u['id'] ?>"
                                       id="pce_<?= (int)$u['id'] ?>"
                                       <?= in_array((int)$u['id'], $clientEditUsers) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="pce_<?= (int)$u['id'] ?>">
                                    <?= e($u['name']) ?>
                                    <span class="badge bg-info"><?= e($u['role']) ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Permission: Sending Reminder Emails -->
            <div class="card mb-4">
                <div class="card-header bg-dark text-white">
                    <i class="bi bi-envelope"></i> Send Reminder Emails Permission
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Select users who are allowed to send reminder emails to clients (in addition to admin).</p>
                    <?php if (empty($allUsers)): ?>
                        <p class="text-muted">No processor users found.</p>
                    <?php else: ?>
                        <?php foreach ($allUsers as $u): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox"
                                       name="perm_send_reminders[]"
                                       value="<?= (int)$u['id'] ?>"
                                       id="psr_<?= (int)$u['id'] ?>"
                                       <?= in_array((int)$u['id'], $reminderUsers) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="psr_<?= (int)$u['id'] ?>">
                                    <?= e($u['name']) ?>
                                    <span class="badge bg-info"><?= e($u['role']) ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <!-- Pending Work Report Email -->
            <div class="card mb-4">
                <div class="card-header bg-dark text-white">
                    <i class="bi bi-mailbox"></i> Pending Work Report Email
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Set an email address to receive the daily pending work report for admin after the daily digest is sent.
                        Leave empty to disable.
                    </p>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="pending_report_email" class="form-control"
                               value="<?= e($pendingReportEmail ?? '') ?>"
                               placeholder="e.g. admin@example.com">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-lg"></i> Save Settings
    </button>
</form>

<?php
$content = ob_get_clean();
include BASE_PATH . '/views/layout.php';
?>
