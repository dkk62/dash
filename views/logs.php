<?php
$pageTitle = 'Activity Logs';
ob_start();
?>

<h4><i class="bi bi-journal-text"></i> Activity Logs</h4>

<form method="GET" action="/dash/" class="row g-2 mb-3">
    <input type="hidden" name="action" value="logs">
    <div class="col-auto">
        <select name="filter_action" class="form-select form-select-sm">
            <option value="">All Actions</option>
            <?php foreach (['upload','download','reupload','reminder_sent','period_locked','period_unlocked','login'] as $a): ?>
                <option value="<?= $a ?>" <?= ($actionFilter ?? '') === $a ? 'selected' : '' ?>><?= $a ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <button class="btn btn-sm btn-primary">Filter</button>
        <a href="/dash/?action=logs" class="btn btn-sm btn-outline-secondary">Clear</a>
    </div>
</form>

<div class="table-responsive">
<table class="table table-bordered table-sm table-hover">
    <thead class="table-dark">
        <tr>
            <th>#</th>
            <th>Time</th>
            <th>User</th>
            <th>Action</th>
            <th>Client</th>
            <th>Period</th>
            <th>Stage</th>
            <th>Details</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($logs as $log): ?>
        <tr>
            <td><?= $log['id'] ?></td>
            <td><small><?= e($log['created_at']) ?></small></td>
            <td><?= e($log['user_name']) ?></td>
            <td>
                <?php
                $actionBadge = match($log['action']) {
                    'upload'         => 'bg-success',
                    'download'       => 'bg-info',
                    'reupload'       => 'bg-warning text-dark',
                    'reminder_sent'  => 'bg-warning text-dark',
                    'period_locked'  => 'bg-danger',
                    'period_unlocked'=> 'bg-secondary',
                    'login'          => 'bg-secondary',
                    default          => 'bg-dark',
                };
                ?>
                <span class="badge <?= $actionBadge ?>"><?= e($log['action']) ?></span>
            </td>
            <td><?= e($log['client_name'] ?? '-') ?></td>
            <td><?= e($log['period_label'] ?? '-') ?></td>
            <td><?= e($log['stage_name'] ?? '-') ?></td>
            <td>
                <?php
                $meta = $log['metadata'] ? json_decode($log['metadata'], true) : null;
                if ($meta && isset($meta['filename'])) {
                    echo '<small>' . e($meta['filename']) . '</small>';
                } elseif ($meta) {
                    echo '<small>' . e(json_encode($meta, JSON_UNESCAPED_UNICODE)) . '</small>';
                }
                ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($logs)): ?>
        <tr><td colspan="8" class="text-muted text-center">No logs found.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>

<?php
$content = ob_get_clean();
include BASE_PATH . '/views/layout.php';
?>
