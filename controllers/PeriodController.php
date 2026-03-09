<?php
require_once BASE_PATH . '/models/Period.php';
require_once BASE_PATH . '/models/Client.php';
require_once BASE_PATH . '/models/Account.php';

$clientId = (int) ($_GET['client_id'] ?? $_POST['client_id'] ?? 0);

/**
 * Ensure client has at least one account before creating periods.
 */
function ensureClientHasAccounts(int $clientId): void {
    $accounts = Account::byClient($clientId);
    if (empty($accounts)) {
        setFlash('danger', 'Please create at least one account before adding periods.');
        redirect('?action=accounts&client_id=' . $clientId);
    }
}

/**
 * Create labels and return [createdCount, skippedCount].
 */
function createPeriodLabels(int $clientId, array $labels): array {
    $created = 0;
    $skipped = 0;
    foreach ($labels as $label) {
        try {
            Period::create($clientId, $label);
            $created++;
        } catch (\PDOException $ex) {
            if ($ex->getCode() == 23000) {
                $skipped++;
                continue;
            }
            throw $ex;
        }
    }
    return [$created, $skipped];
}

if ($action === 'period_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $label = trim($_POST['period_label'] ?? '');

    if ($label === '' || $clientId <= 0) {
        setFlash('danger', 'Period label and client are required.');
        redirect('?action=periods&client_id=' . $clientId);
    }

    // Standardize FY formatting when entered manually.
    if (preg_match('/^FY\s*([0-9]{2})$/i', $label, $m)) {
        $label = 'FY ' . $m[1];
    }

    ensureClientHasAccounts($clientId);

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

if ($action === 'period_generate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    if ($clientId <= 0) {
        setFlash('danger', 'Client is required.');
        redirect('?action=clients');
    }

    ensureClientHasAccounts($clientId);

    $mode = $_POST['mode'] ?? '';

    try {
        if ($mode === 'fiscal') {
            $fyLabel = trim($_POST['fy_label'] ?? '');
            $allowed = ['FY 24', 'FY 25', 'FY 26', 'FY 27', 'FY 28'];
            if (!in_array($fyLabel, $allowed, true)) {
                setFlash('danger', 'Invalid FY label selected.');
                redirect('?action=periods&client_id=' . $clientId);
            }

            [$created, $skipped] = createPeriodLabels($clientId, [$fyLabel]);
            setFlash('success', "Fiscal period result: {$created} created, {$skipped} skipped.");
            redirect('?action=periods&client_id=' . $clientId);
        }

        if ($mode === 'monthly_range') {
            $startYear = (int) ($_POST['start_year'] ?? 2026);
            $endYear   = (int) ($_POST['end_year'] ?? 2030);

            if ($startYear < 2026 || $endYear > 2030 || $startYear > $endYear) {
                setFlash('danger', 'Invalid monthly year range. Use 2026 to 2030.');
                redirect('?action=periods&client_id=' . $clientId);
            }

            $labels = [];
            for ($year = $startYear; $year <= $endYear; $year++) {
                for ($month = 1; $month <= 12; $month++) {
                    $labels[] = date('M y', strtotime("{$year}-{$month}-01"));
                }
            }

            [$created, $skipped] = createPeriodLabels($clientId, $labels);
            setFlash('success', "Monthly generation result: {$created} created, {$skipped} skipped.");
            redirect('?action=periods&client_id=' . $clientId);
        }

        setFlash('danger', 'Invalid generation mode.');
    } catch (\PDOException $ex) {
        setFlash('danger', 'Error: ' . $ex->getMessage());
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
sortPeriodsChronologically($periods);
include BASE_PATH . '/views/periods.php';
