<?php
require_once BASE_PATH . '/models/Setting.php';
require_once BASE_PATH . '/models/User.php';

if ($action === 'settings_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    // Permission: client creation/editing
    $clientPerms = $_POST['perm_client_edit'] ?? [];
    Setting::setPermissionUsers('perm_client_edit', array_map('intval', (array)$clientPerms));

    // Permission: sending reminder emails
    $reminderPerms = $_POST['perm_send_reminders'] ?? [];
    Setting::setPermissionUsers('perm_send_reminders', array_map('intval', (array)$reminderPerms));

    // Pending work report email
    $pendingEmail = trim($_POST['pending_report_email'] ?? '');
    if ($pendingEmail !== '' && !filter_var($pendingEmail, FILTER_VALIDATE_EMAIL)) {
        setFlash('danger', 'Invalid email address for pending work report.');
        redirect('?action=settings');
    }
    Setting::set('pending_report_email', $pendingEmail);

    setFlash('success', 'Settings saved successfully.');
    redirect('?action=settings');
}

// Load current settings
$allUsers = User::byRoles(['processor0', 'processor1']);
$clientEditUsers = Setting::getUsersWithPermission('perm_client_edit');
$reminderUsers = Setting::getUsersWithPermission('perm_send_reminders');
$pendingReportEmail = Setting::get('pending_report_email', '');

include BASE_PATH . '/views/settings.php';
