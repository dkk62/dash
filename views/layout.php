<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Work Progress System') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/dash/public/css/style.css" rel="stylesheet">
</head>
<body>
<?php if (isLoggedIn()): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="/dash/?action=dashboard">
            <i class="bi bi-clipboard-data"></i> Work Progress
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/dash/?action=dashboard">
                        <i class="bi bi-grid-3x3"></i> Dashboard
                    </a>
                </li>
                <?php if (hasRole(['admin'])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="/dash/?action=clients">
                        <i class="bi bi-people"></i> Clients
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/dash/?action=logs">
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
                    <a class="nav-link" href="/dash/?action=logout">
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
<script src="/dash/public/js/app.js"></script>
</body>
</html>
