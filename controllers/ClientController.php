<?php
require_once BASE_PATH . '/models/Client.php';
require_once BASE_PATH . '/models/User.php';

if ($action === 'client_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $id           = (int) ($_POST['id'] ?? 0);
    $name         = trim($_POST['name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $cycleType    = $_POST['cycle_type'] ?? 'monthly';
    $password     = trim($_POST['password'] ?? '');
    $processor0Id = !empty($_POST['processor0_id']) ? (int) $_POST['processor0_id'] : null;
    $processor1Id = !empty($_POST['processor1_id']) ? (int) $_POST['processor1_id'] : null;

    if ($name === '' || $email === '') {
        setFlash('danger', 'Name and email are required.');
        redirect('?action=clients');
    }

    if (!in_array($cycleType, ['monthly', 'yearly'])) {
        $cycleType = 'monthly';
    }

    try {
        if ($id > 0) {
            // Update existing - password is optional
            Client::update($id, $name, $email, $phone, $cycleType, $password ?: null, $processor0Id, $processor1Id);
            setFlash('success', 'Client updated.');
        } else {
            // Create new - password is optional
            $newClientId = Client::create($name, $email, $phone, $cycleType, $password ?: null, $processor0Id, $processor1Id);
            setFlash('success', 'Client created. Now add accounts for this client.');
            redirect('?action=accounts&client_id=' . $newClientId);
        }
    } catch (RuntimeException $e) {
        setFlash('danger', $e->getMessage());
    }
    redirect('?action=clients');
}

if ($action === 'client_archive' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    requireRole(['admin']);
    $id = (int) ($_POST['id'] ?? 0);
    $archive = (int) ($_POST['archive'] ?? 1);
    if ($id > 0) {
        Client::setArchived($id, (bool) $archive);
        setFlash('success', $archive ? 'Client archived.' : 'Client unarchived.');
    }
    redirect('?action=clients');
}

if ($action === 'client_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        Client::delete($id);
        setFlash('success', 'Client deleted.');
    }
    redirect('?action=clients');
}

// List + edit form
$clients = Client::all();
$editClient = null;
if ($action === 'client_edit' && isset($_GET['id'])) {
    $editClient = Client::find((int) $_GET['id']);
}

$processorUsers = User::byRoles(['processor0', 'processor1']);

include BASE_PATH . '/views/clients.php';
