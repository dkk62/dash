<?php
$pageTitle = 'Manage Users';
ob_start();
$roleBadge = ['admin' => 'bg-danger', 'processor0' => 'bg-primary', 'processor1' => 'bg-success'];
?>

<h4><i class="bi bi-person-gear"></i> Users</h4>

<div class="row">
    <div class="col-md-8">
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= e($u['name']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><span class="badge <?= $roleBadge[$u['role']] ?? 'bg-secondary' ?>"><?= e($u['role']) ?></span></td>
                    <td><small><?= e($u['created_at']) ?></small></td>
                    <td>
                        <a href="/dash/?action=user_edit&id=<?= $u['id'] ?>"
                           class="btn btn-sm btn-outline-primary" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                        <form method="POST" action="/dash/?action=user_delete" class="d-inline"
                              onsubmit="return confirm('Delete user <?= e(addslashes($u['name'])) ?>?')">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        <?php else: ?>
                            <span class="badge bg-secondary">You</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
                <tr><td colspan="5" class="text-muted text-center">No users found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <strong><?= $editUser ? '<i class="bi bi-pencil"></i> Edit User' : '<i class="bi bi-person-plus"></i> Add User' ?></strong>
            </div>
            <div class="card-body">
                <form method="POST" action="/dash/?action=user_save">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <?php if ($editUser): ?>
                        <input type="hidden" name="id" value="<?= $editUser['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-control" required
                               value="<?= e($editUser['name'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required
                               value="<?= e($editUser['email'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role *</label>
                        <select name="role" class="form-select">
                            <?php foreach (['processor0' => 'Processor 0 (Client)', 'processor1' => 'Processor 1 (Internal Staff)', 'admin' => 'Admin'] as $val => $label): ?>
                                <option value="<?= $val ?>" <?= ($editUser['role'] ?? '') === $val ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            Password <?= $editUser ? '<small class="text-muted">(leave blank to keep current)</small>' : '*' ?>
                        </label>
                        <input type="password" name="password" class="form-control"
                               <?= $editUser ? '' : 'required' ?> autocomplete="new-password">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> <?= $editUser ? 'Update' : 'Create' ?>
                        </button>
                        <?php if ($editUser): ?>
                            <a href="/dash/?action=users" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include BASE_PATH . '/views/layout.php';
?>
