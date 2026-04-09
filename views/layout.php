<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
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
                <li class="nav-item">
                    <a class="nav-link" href="<?= e(appUrl('?action=pending')) ?>">
                        <i class="bi bi-hourglass-split"></i> Pending Work
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= e(appUrl('?action=documents')) ?>">
                        <i class="bi bi-file-earmark-text"></i> Documents
                    </a>
                </li>
                <?php if (hasRole(['admin']) || hasClientPermission()): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= e(appUrl('?action=clients')) ?>">
                        <i class="bi bi-people"></i> Clients
                    </a>
                </li>
                <?php endif; ?>
                <?php if (hasRole(['admin'])): ?>
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
                <li class="nav-item">
                    <a class="nav-link" href="<?= e(appUrl('?action=settings')) ?>">
                        <i class="bi bi-gear"></i> Settings
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

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-danger">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="confirmDeleteLabel"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Deletion</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="confirmDeleteMessage" class="mb-2 fw-semibold"></p>
        <p id="confirmDeleteWarning" class="text-danger small mb-0"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteBtn"><i class="bi bi-trash me-1"></i>Delete</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/mammoth@1.8.0/mammoth.browser.min.js"></script>
<script src="<?= e(assetUrl('js/app.js') . '?v=' . (@filemtime(BASE_PATH . '/public/js/app.js') ?: time())) ?>"></script>
</body>
</html>
