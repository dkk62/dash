<?php $pageTitle = 'Login'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Work Progress System</title>
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

            <div class="card shadow overflow-hidden">
                <div class="text-center px-4 pt-4" style="background-color: #1F7A6B; padding-bottom: calc(1rem + 30px); border-radius: calc(0.375rem - 1px) calc(0.375rem - 1px) 0 0;">
                    <img src="<?= e(assetUrl('img/taxcheapo-logo-white.png')) ?>" alt="TaxCheapo" style="max-height: 64px; width: auto;">
                    <h5 class="mt-3 mb-0 fw-semibold text-white">Work Progress System</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="<?= e(appUrl('?action=do_login')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <div style="position:absolute; left:-10000px; top:auto; width:1px; height:1px; overflow:hidden;" aria-hidden="true">
                            <label for="website">Website</label>
                            <input id="website" type="text" name="website" tabindex="-1" autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn w-100 text-white" style="background-color: #1F7A6B; border-color: #1F7A6B;">
                            <i class="bi bi-box-arrow-in-right"></i> Login
                        </button>
                    </form>
                    <div class="text-center mt-3">
                        <a href="<?= e(appUrl('?action=forgot_password')) ?>" class="text-muted small">
                            <i class="bi bi-key"></i> Forgot your password?
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
