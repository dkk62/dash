<?php
require_once BASE_PATH . '/models/Client.php';

if ($action === 'client_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $id        = (int) ($_POST['id'] ?? 0);
    $name      = trim($_POST['name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $cycleType = $_POST['cycle_type'] ?? 'monthly';
    $password  = trim($_POST['password'] ?? '');

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
            Client::update($id, $name, $email, $phone, $cycleType, $password ?: null);
            setFlash('success', 'Client updated.');
        } else {
            // Create new - password is optional
            Client::create($name, $email, $phone, $cycleType, $password ?: null);
            setFlash('success', 'Client created.');
        }
    } catch (RuntimeException $e) {
        setFlash('danger', $e->getMessage());
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

include BASE_PATH . '/views/clients.php';
