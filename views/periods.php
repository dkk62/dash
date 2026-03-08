<?php
$pageTitle = 'Periods - ' . e($client['name']);
ob_start();
?>

<h4><i class="bi bi-calendar3"></i> Periods for <?= e($client['name']) ?></h4>
<a href="/dash/?action=clients" class="btn btn-sm btn-outline-secondary mb-3">
    <i class="bi bi-arrow-left"></i> Back to Clients
</a>

<div class="row">
    <div class="col-md-8">
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Period Label</th>
                    <th>Locked</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($periods as $p): ?>
                <tr>
                    <td><?= e($p['period_label']) ?></td>
                    <td>
                        <?= $p['is_locked']
                            ? '<span class="badge bg-danger"><i class="bi bi-lock-fill"></i> Locked</span>'
                            : '<span class="badge bg-success">Open</span>' ?>
                    </td>
                    <td><?= e($p['created_at']) ?></td>
                    <td>
                        <?php if (!$p['is_locked']): ?>
                        <form method="POST" action="/dash/?action=period_delete" class="d-inline"
                              onsubmit="return confirm('Delete this period and all its data?')">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="client_id" value="<?= $clientId ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($periods)): ?>
                <tr><td colspan="4" class="text-muted text-center">No periods yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="col-md-4">
        <h5>Add Period</h5>
        <form method="POST" action="/dash/?action=period_save">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="client_id" value="<?= $clientId ?>">
            <div class="mb-3">
                <label class="form-label">Period Label *</label>
                <input type="text" name="period_label" class="form-control" required
                       placeholder="e.g., Jan 2026 or FY 2025-26">
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Add Period
            </button>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include BASE_PATH . '/views/layout.php';
?>
