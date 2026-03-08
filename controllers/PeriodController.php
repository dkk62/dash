<?php
require_once BASE_PATH . '/models/Period.php';
require_once BASE_PATH . '/models/Client.php';
require_once BASE_PATH . '/models/Account.php';

$clientId = (int) ($_GET['client_id'] ?? $_POST['client_id'] ?? 0);

if ($action === 'period_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $label = trim($_POST['period_label'] ?? '');

    if ($label === '' || $clientId <= 0) {
        setFlash('danger', 'Period label and client are required.');
        redirect('?action=periods&client_id=' . $clientId);
    }

    // Check accounts exist
    $accounts = Account::byClient($clientId);
    if (empty($accounts)) {
        setFlash('danger', 'Please create at least one account before adding periods.');
        redirect('?action=accounts&client_id=' . $clientId);
    }

    try {
        Period::create($clientId, $label);
        setFlash('success', 'Period created with status rows initialized.');
    } catch (\PDOException $ex) {
        if ($ex->getCode() == 23000) {
            setFlash('danger', 'This period label already exists for this client.');
        } else {
            setFlash('danger', 'Error: ' . $ex->getMessage());
        }
    }
    redirect('?action=periods&client_id=' . $clientId);
}

if ($action === 'period_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        Period::delete($id);
        setFlash('success', 'Period deleted.');
    }
    redirect('?action=periods&client_id=' . $clientId);
}

$client = Client::find($clientId);
if (!$client) {
    setFlash('danger', 'Client not found.');
    redirect('?action=clients');
}

$periods = Period::byClient($clientId);
include BASE_PATH . '/views/periods.php';
