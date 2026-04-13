<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Work Progress System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(assetUrl('css/style.css')) ?>" rel="stylesheet">
<?php if (isset($_GET['sent']) && $_GET['sent'] === '1'): ?>
<meta http-equiv="refresh" content="4;url=<?= e(appUrl('?action=login')) ?>">
<script>
    setTimeout(function() {
        window.location.href = "<?= e(appUrl('?action=login')) ?>";
    }, 4000);
</script>
<?php endif; ?>
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
                        <i class="bi bi-key"></i> Forgot Password
                    </h4>
                    <p class="text-muted text-center small mb-4">Enter your email and we'll send a reset link.</p>

                    <form method="POST" action="<?= e(appUrl('?action=do_forgot_password')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <?php $prefillEmail = $_GET['email'] ?? ''; ?>
                            <input type="email" name="email" class="form-control" required autofocus
                                   value="<?= e($prefillEmail) ?>"
                                   <?= $prefillEmail !== '' ? 'readonly' : '' ?>>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-send"></i> Send Reset Link
                        </button>
                    </form>

                    <div class="text-center mt-3">
                        <a href="<?= e(appUrl('?action=login')) ?>" class="text-muted small">
                            <i class="bi bi-arrow-left"></i> Back to Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
