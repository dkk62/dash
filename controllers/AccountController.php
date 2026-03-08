<?php
require_once BASE_PATH . '/models/Account.php';
require_once BASE_PATH . '/models/Client.php';

$clientId = (int) ($_GET['client_id'] ?? $_POST['client_id'] ?? 0);

if ($action === 'account_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $id   = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['account_name'] ?? '');

    if ($name === '' || $clientId <= 0) {
        setFlash('danger', 'Account name and client are required.');
        redirect('?action=accounts&client_id=' . $clientId);
    }

    if ($id > 0) {
        $isActive = isset($_POST['is_active']) ? true : false;
        Account::update($id, $name, $isActive);
        setFlash('success', 'Account updated.');
    } else {
        Account::create($clientId, $name);
        setFlash('success', 'Account created.');
    }
    redirect('?action=accounts&client_id=' . $clientId);
}

if ($action === 'account_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        Account::delete($id);
        setFlash('success', 'Account deleted.');
    }
    redirect('?action=accounts&client_id=' . $clientId);
}

$client = Client::find($clientId);
if (!$client) {
    setFlash('danger', 'Client not found.');
    redirect('?action=clients');
}

$accounts = Account::byClient($clientId);
include BASE_PATH . '/views/accounts.php';
