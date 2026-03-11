<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrfToken()) ?>">
    <title><?= e($pageTitle ?? 'Work Progress System') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(assetUrl('css/style.css') . '?v=' . (@filemtime(BASE_PATH . '/public/css/style.css') ?: time())) ?>" rel="stylesheet">
</head>
<body>
<?php if (isLoggedIn()): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand p-0" href="<?= e(appUrl('?action=dashboard')) ?>">
            <img src="<?= e(assetUrl('img/taxcheapo-logo-white.png')) ?>" alt="Work Progress" style="height:40px;width:auto;display:block;">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?= e(appUrl('?action=dashboard')) ?>">
                        <i class="bi bi-grid-3x3"></i> Dashboard
                    </a>
                </li>
                <?php if (hasRole(['admin'])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= e(appUrl('?action=clients')) ?>">
                        <i class="bi bi-people"></i> Clients
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= e(appUrl('?action=users')) ?>">
                        <i class="bi bi-person-gear"></i> Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= e(appUrl('?action=logs')) ?>">
                        <i class="bi bi-journal-text"></i> Logs
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <span class="nav-link text-light">
                        <i class="bi bi-person-circle"></i>
                        <?= e($_SESSION['user_name'] ?? '') ?>
                        <span class="badge bg-info"><?= e($_SESSION['user_role'] ?? '') ?></span>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= e(appUrl('?action=logout')) ?>">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<?php endif; ?>

<div class="container-fluid mt-3">
    <?php $flash = getFlash(); if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($flash['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?= $content ?? '' ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(assetUrl('js/app.js') . '?v=' . (@filemtime(BASE_PATH . '/public/js/app.js') ?: time())) ?>"></script>
</body>
</html>
