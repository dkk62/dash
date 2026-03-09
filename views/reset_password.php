<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Work Progress System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(assetUrl('css/style.css')) ?>" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-5">
            <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
            <?php endif; ?>

            <div class="card shadow">
                <div class="card-body p-4">
                    <h4 class="text-center mb-1">
                        <i class="bi bi-shield-lock"></i> Set New Password
                    </h4>
                    <p class="text-muted text-center small mb-4">Choose a new password for <strong><?= e($reset['email']) ?></strong></p>

                    <form method="POST" action="<?= e(appUrl('?action=do_reset_password')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="token" value="<?= e($token) ?>">

                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="password" class="form-control" required
                                   minlength="8" autocomplete="new-password">
                            <div class="form-text">Minimum 8 characters.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" required
                                   minlength="8" autocomplete="new-password">
                        </div>
                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-check-lg"></i> Reset Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
