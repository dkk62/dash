<?php
$pageTitle = 'Periods - ' . e($client['name']);
ob_start();
?>

<h4><i class="bi bi-calendar3"></i> Periods for <?= e($client['name']) ?></h4>
<a href="<?= e(appUrl('?action=clients')) ?>" class="btn btn-sm btn-outline-secondary mb-3">
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
                        <form method="POST" action="<?= e(appUrl('?action=period_delete')) ?>" class="d-inline confirm-delete"
                              data-confirm-title="Delete Period"
                              data-confirm-message="Are you sure you want to delete period &quot;<?= e($p['period_label']) ?>&quot;?"
                              data-confirm-warning="All stage statuses, uploaded files, and notes for this period will be permanently deleted. This action cannot be undone.">
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
        <?php if (($client['cycle_type'] ?? 'monthly') === 'monthly'): ?>
        <div class="card mb-3">
            <div class="card-header"><strong>Monthly Manual Add (Dropdown)</strong></div>
            <div class="card-body">
                <form method="POST" action="<?= e(appUrl('?action=period_save')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="client_id" value="<?= $clientId ?>">
                    <div class="mb-3">
                        <label class="form-label">Monthly Period</label>
                        <select name="period_label" class="form-select" required>
                            <?php
                            for ($year = 2025; $year <= 2030; $year++) {
                                for ($month = 1; $month <= 12; $month++) {
                                    $label = date('M y', strtotime("{$year}-{$month}-01"));
                                    echo '<option value="' . e($label) . '">' . e($label) . '</option>';
                                }
                            }
                            ?>
                        </select>
                        <div class="form-text">Choose one month at a time from <code>Jan 25</code> to <code>Dec 30</code>.</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-plus-lg"></i> Add Monthly Period
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if (($client['cycle_type'] ?? 'monthly') === 'yearly'): ?>
        <div class="card mb-3">
            <div class="card-header"><strong>Fiscal Year (Standardized)</strong></div>
            <div class="card-body">
                <form method="POST" action="<?= e(appUrl('?action=period_generate')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="client_id" value="<?= $clientId ?>">
                    <input type="hidden" name="mode" value="fiscal">
                    <div class="mb-3">
                        <label class="form-label">FY Label</label>
                        <select name="fy_label" class="form-select" required>
                            <?php foreach (['FY 24', 'FY 25', 'FY 26', 'FY 27', 'FY 28'] as $fy): ?>
                                <option value="<?= $fy ?>"><?= $fy ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-plus-lg"></i> Add FY Period
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if (($client['cycle_type'] ?? 'monthly') === 'monthly'): ?>
        <div class="card mb-3">
            <div class="card-header"><strong>Monthly Auto-Create</strong></div>
            <div class="card-body">
                <form method="POST" action="<?= e(appUrl('?action=period_generate')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="client_id" value="<?= $clientId ?>">
                    <input type="hidden" name="mode" value="monthly_range">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Start Year</label>
                            <select name="start_year" class="form-select" required>
                                <?php for ($y = 2026; $y <= 2030; $y++): ?>
                                    <option value="<?= $y ?>" <?= $y === 2026 ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">End Year</label>
                            <select name="end_year" class="form-select" required>
                                <?php for ($y = 2026; $y <= 2030; $y++): ?>
                                    <option value="<?= $y ?>" <?= $y === 2030 ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-text mt-2">Creates monthly periods in format <code>Jan 26</code> ... <code>Dec 30</code>.</div>
                    <button type="submit" class="btn btn-success w-100 mt-3"
                            onclick="return confirm('Auto-create monthly periods for selected year range?')">
                        <i class="bi bi-calendar2-plus"></i> Generate Monthly Periods
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php
$content = ob_get_clean();
include BASE_PATH . '/views/layout.php';
?>
