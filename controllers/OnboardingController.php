<?php
require_once BASE_PATH . '/models/Client.php';
require_once BASE_PATH . '/models/OnboardingForm.php';
require_once BASE_PATH . '/models/ClientDocument.php';

// ---- Helper: resolve client ID & verify access ----
function resolveOnboardingClient(): array {
    if (currentRole() === 'client') {
        $clientIds = array_map('intval', (array) ($_SESSION['client_ids'] ?? []));
        $requestedId = (int) ($_GET['client_id'] ?? $_POST['client_id'] ?? 0);

        if ($requestedId > 0 && in_array($requestedId, $clientIds, true)) {
            $clientId = $requestedId;
        } else {
            // Find first entity with incomplete onboarding, or fall back to first
            $clientId = $clientIds[0] ?? 0;
            if (count($clientIds) > 1) {
                $db = getDB();
                $ph = implode(',', array_fill(0, count($clientIds), '?'));
                $stmt = $db->prepare("SELECT client_id FROM client_onboarding WHERE client_id IN ($ph) AND status IN ('submitted','reviewed')");
                $stmt->execute($clientIds);
                $doneIds = array_column($stmt->fetchAll(), 'client_id');
                $doneIds = array_map('intval', $doneIds);
                foreach ($clientIds as $cid) {
                    if (!in_array($cid, $doneIds, true)) {
                        $clientId = $cid;
                        break;
                    }
                }
            }
        }
    } else {
        $clientId = (int) ($_GET['client_id'] ?? $_POST['client_id'] ?? 0);
    }
    $client = Client::find($clientId);
    if (!$client) return ['error' => 'Client not found.'];
    if (currentRole() === 'client') {
        $allowed = array_map('intval', (array) ($_SESSION['client_ids'] ?? []));
        if (!in_array($clientId, $allowed, true)) return ['error' => 'Access denied.'];
    }
    return ['client' => $client, 'clientId' => $clientId];
}

function onboardingStagingPath(int $clientId): string {
    return UPLOAD_PATH . '/clients/' . $clientId . '/onboarding_staging';
}

function ajaxFail(string $msg, int $code = 400): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

// ---- VIEW ONBOARDING FORM ----
if ($action === 'onboarding') {
    $resolved = resolveOnboardingClient();
    if (isset($resolved['error'])) { setFlash('danger', $resolved['error']); redirect('?action=dashboard'); }
    $client   = $resolved['client'];
    $clientId = $resolved['clientId'];

    $onboarding = OnboardingForm::findByClientId($clientId);
    $formData   = $onboarding['form_data'] ?? [];
    $formStatus = $onboarding['status'] ?? 'new';
    $isClient   = (currentRole() === 'client');

    // Build entity list for multi-entity clients
    $clientEntities = [];
    if ($isClient) {
        $clientIds = array_map('intval', (array) ($_SESSION['client_ids'] ?? []));
        if (count($clientIds) > 1) {
            foreach ($clientIds as $cid) {
                $ent = Client::find($cid);
                if ($ent) {
                    $onb = OnboardingForm::findByClientId($cid);
                    $clientEntities[] = [
                        'id'     => $cid,
                        'name'   => $ent['name'],
                        'status' => $onb['status'] ?? 'new',
                    ];
                }
            }
        }
    }

    include BASE_PATH . '/views/onboarding.php';
    exit;
}

// ---- SAVE SINGLE SECTION (AJAX) ----
if ($action === 'onboarding_save_section' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!verifyCsrf()) ajaxFail('Session expired. Please reload.', 403);

    $resolved = resolveOnboardingClient();
    if (isset($resolved['error'])) ajaxFail($resolved['error'], 403);
    $clientId = $resolved['clientId'];

    $section  = $_POST['section'] ?? '';
    $existing = OnboardingForm::findByClientId($clientId);
    $formData = $existing['form_data'] ?? [];
    $isEdit   = $existing && !in_array($existing['status'] ?? 'new', ['new', 'draft']);

    switch ($section) {
        case 'business_info':
            $formData['business_name'] = trim($_POST['business_name'] ?? '');
            $formData['ein']           = trim($_POST['ein'] ?? '');
            $formData['entity_type']   = trim($_POST['entity_type'] ?? '');
            $formData['start_date']    = trim($_POST['start_date'] ?? '');
            break;

        case 'banking':
            // Bank accounts (repeatable: bank name + account number)
            $formData['bank_accounts'] = [];
            if (!empty($_POST['ba_bank']) && is_array($_POST['ba_bank'])) {
                foreach ($_POST['ba_bank'] as $i => $b) {
                    $b = trim($b); $a = trim($_POST['ba_account'][$i] ?? '');
                    if ($b !== '' || $a !== '') $formData['bank_accounts'][] = ['bank'=>$b,'account'=>$a];
                }
            }
            // Credit cards (repeatable: bank/issuer + account number)
            $formData['credit_cards'] = [];
            if (!empty($_POST['cc_bank']) && is_array($_POST['cc_bank'])) {
                foreach ($_POST['cc_bank'] as $i => $b) {
                    $b = trim($b); $a = trim($_POST['cc_account'][$i] ?? '');
                    if ($b !== '' || $a !== '') $formData['credit_cards'][] = ['bank'=>$b,'account'=>$a];
                }
            }
            // Loans (repeatable: lender + account number)
            $formData['loans'] = [];
            if (!empty($_POST['ln_bank']) && is_array($_POST['ln_bank'])) {
                foreach ($_POST['ln_bank'] as $i => $b) {
                    $b = trim($b); $a = trim($_POST['ln_account'][$i] ?? '');
                    if ($b !== '' || $a !== '') $formData['loans'][] = ['bank'=>$b,'account'=>$a];
                }
            }
            // Merchant accounts (repeatable: name)
            $formData['merchant_accounts'] = [];
            if (!empty($_POST['mr_name']) && is_array($_POST['mr_name'])) {
                foreach ($_POST['mr_name'] as $n) {
                    $n = trim($n);
                    if ($n !== '') $formData['merchant_accounts'][] = ['name'=>$n];
                }
            }
            $formData['auto_payments']     = [];
            if (!empty($_POST['ap_vendor']) && is_array($_POST['ap_vendor'])) {
                foreach ($_POST['ap_vendor'] as $i => $v) {
                    $v = trim($v); $a = trim($_POST['ap_amount'][$i] ?? '');
                    $m = trim($_POST['ap_monthly'][$i] ?? ''); $c = trim($_POST['ap_category'][$i] ?? '');
                    if ($v !== '' || $a !== '') $formData['auto_payments'][] = ['vendor'=>$v,'amount'=>$a,'monthly'=>$m,'category'=>$c];
                }
            }
            break;

        case 'income_property':
            $formData['rental_income'] = [];
            if (!empty($_POST['ri_address']) && is_array($_POST['ri_address'])) {
                foreach ($_POST['ri_address'] as $i => $addr) {
                    $addr = trim($addr); $t = trim($_POST['ri_tenant'][$i] ?? '');
                    $r = trim($_POST['ri_rent'][$i] ?? ''); $d = trim($_POST['ri_deposit'][$i] ?? '');
                    if ($addr !== '' || $t !== '') $formData['rental_income'][] = ['address'=>$addr,'tenant'=>$t,'rent'=>$r,'deposit'=>$d];
                }
            }
            $formData['fixed_expenses']    = $_POST['fixed_expenses'] ?? [];
            $formData['income_sources']    = $_POST['income_sources'] ?? [];
            $formData['income_source_other'] = trim($_POST['income_source_other'] ?? '');
            break;

        case 'employees':
            $formData['has_employees']        = trim($_POST['has_employees'] ?? '');
            $formData['has_payroll_provider']  = trim($_POST['has_payroll_provider'] ?? '');
            $formData['payroll_provider_name'] = trim($_POST['payroll_provider_name'] ?? '');
            $formData['pay_frequency']         = trim($_POST['pay_frequency'] ?? '');
            $formData['num_employees']         = trim($_POST['num_employees'] ?? '');
            $formData['pays_relatives']        = trim($_POST['pays_relatives'] ?? '');
            $formData['relatives'] = [];
            if (!empty($_POST['rel_name']) && is_array($_POST['rel_name'])) {
                foreach ($_POST['rel_name'] as $i => $n) {
                    $n = trim($n); if ($n === '') continue;
                    $formData['relatives'][] = [
                        'name'=>$n, 'relationship'=>trim($_POST['rel_relationship'][$i] ?? ''),
                        'amount'=>trim($_POST['rel_amount'][$i] ?? ''),
                        'pay_type'=>trim($_POST['rel_pay_type'][$i] ?? ''),
                    ];
                }
            }
            $formData['pays_contractors'] = trim($_POST['pays_contractors'] ?? '');
            $formData['contractors'] = [];
            if (!empty($_POST['con_name']) && is_array($_POST['con_name'])) {
                foreach ($_POST['con_name'] as $i => $n) {
                    $n = trim($n); if ($n === '') continue;
                    $formData['contractors'][] = [
                        'name'=>$n, 'service'=>trim($_POST['con_service'][$i] ?? ''),
                        'paid_monthly'=>trim($_POST['con_monthly'][$i] ?? ''),
                        'needs_1099'=>trim($_POST['con_1099'][$i] ?? ''),
                    ];
                }
            }
            $formData['w9_collected']  = trim($_POST['w9_collected'] ?? '');
            $formData['w9_submitted']  = trim($_POST['w9_submitted'] ?? '');
            $formData['w9_new_notify'] = trim($_POST['w9_new_notify'] ?? '');
            break;

        case 'tax_compliance':
            $formData['collects_sales_tax']  = trim($_POST['collects_sales_tax'] ?? '');
            $formData['tax_jurisdictions']   = trim($_POST['tax_jurisdictions'] ?? '');
            $formData['business_reinstated'] = trim($_POST['business_reinstated'] ?? '');
            $formData['annual_report_filed'] = trim($_POST['annual_report_filed'] ?? '');
            $formData['additional_notes']    = trim($_POST['additional_notes'] ?? '');
            break;

        default:
            ajaxFail('Unknown section.');
    }

    // Track saved sections
    $saved = $formData['_sections_saved'] ?? [];
    if (!in_array($section, $saved)) $saved[] = $section;
    $formData['_sections_saved'] = $saved;

    // Stage file uploads (not dispatched to documents until final submit)
    if (!$isEdit && !empty($_FILES)) {
        $stagingDir = onboardingStagingPath($clientId);
        ensureDir($stagingDir);
        $stagedFiles = $formData['_staged_files'] ?? [];

        foreach ($_FILES as $field => $fileGroup) {
            if ($field === 'csrf_token') continue;
            if (!is_array($fileGroup['name'])) continue;
            $count = count($fileGroup['name']);
            for ($i = 0; $i < $count; $i++) {
                if (($fileGroup['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                $origName = $fileGroup['name'][$i];
                $tmpName  = $fileGroup['tmp_name'][$i];
                $fileId   = bin2hex(random_bytes(8));
                $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($origName));
                $destName = $fileId . '_' . $safeName;
                $destPath = $stagingDir . '/' . $destName;

                if (move_uploaded_file($tmpName, $destPath)) {
                    $stagedFiles[] = [
                        'id'            => $fileId,
                        'field'         => $field,
                        'original_name' => $origName,
                        'staged_name'   => $destName,
                    ];
                }
            }
        }
        $formData['_staged_files'] = $stagedFiles;
    }

    OnboardingForm::save($clientId, $formData, 'draft');
    echo json_encode(['success' => true, 'message' => 'Section saved.', 'sections_saved' => $formData['_sections_saved'], 'staged_files' => $formData['_staged_files'] ?? []]);
    exit;
}

// ---- DELETE STAGED FILE (AJAX) ----
if ($action === 'onboarding_delete_file' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!verifyCsrf()) ajaxFail('Session expired.', 403);

    $resolved = resolveOnboardingClient();
    if (isset($resolved['error'])) ajaxFail($resolved['error'], 403);
    $clientId = $resolved['clientId'];

    $fileId   = $_POST['file_id'] ?? '';
    $existing = OnboardingForm::findByClientId($clientId);
    if (!$existing) ajaxFail('No onboarding data found.');

    $formData    = $existing['form_data'];
    $stagedFiles = $formData['_staged_files'] ?? [];
    $found       = false;

    foreach ($stagedFiles as $idx => $sf) {
        if ($sf['id'] === $fileId) {
            // Delete physical file
            $filePath = onboardingStagingPath($clientId) . '/' . $sf['staged_name'];
            if (file_exists($filePath)) @unlink($filePath);
            array_splice($stagedFiles, $idx, 1);
            $found = true;
            break;
        }
    }

    if (!$found) ajaxFail('File not found.');

    $formData['_staged_files'] = $stagedFiles;
    OnboardingForm::save($clientId, $formData, $existing['status']);
    echo json_encode(['success' => true, 'message' => 'File removed.', 'staged_files' => $stagedFiles]);
    exit;
}

// ---- LIST STAGED FILES (AJAX) ----
if ($action === 'onboarding_staged_files') {
    header('Content-Type: application/json');
    $resolved = resolveOnboardingClient();
    if (isset($resolved['error'])) ajaxFail($resolved['error'], 403);
    $clientId = $resolved['clientId'];

    $existing    = OnboardingForm::findByClientId($clientId);
    $stagedFiles = ($existing['form_data']['_staged_files'] ?? []);
    echo json_encode(['success' => true, 'staged_files' => $stagedFiles]);
    exit;
}

// ---- FINAL SUBMIT ----
if ($action === 'onboarding_submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!verifyCsrf()) ajaxFail('Session expired.', 403);

    $resolved = resolveOnboardingClient();
    if (isset($resolved['error'])) ajaxFail($resolved['error'], 403);
    $clientId = $resolved['clientId'];
    $client   = $resolved['client'];

    $existing = OnboardingForm::findByClientId($clientId);
    if (!$existing) ajaxFail('No onboarding data found. Please save at least one section first.');

    $formData    = $existing['form_data'];
    $stagedFiles = $formData['_staged_files'] ?? [];

    // Move staged files to document repository
    if (!empty($stagedFiles)) {
        $stagingDir = onboardingStagingPath($clientId);
        $docDir     = ClientDocument::docPath($clientId);
        ensureDir($docDir);

        $userId       = (int) $_SESSION['user_id'];
        $uploaderType = ($_SESSION['user_type'] ?? 'user') === 'client' ? 'client' : 'user';

        foreach ($stagedFiles as $sf) {
            $srcPath  = $stagingDir . '/' . $sf['staged_name'];
            if (!file_exists($srcPath)) continue;

            $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($sf['original_name']));
            $destPath = $docDir . '/' . $safeName;

            // Handle duplicate filenames
            if (file_exists($destPath)) {
                $pi   = pathinfo($safeName);
                $base = $pi['filename'];
                $ext  = isset($pi['extension']) ? '.' . $pi['extension'] : '';
                $ctr  = 1;
                do {
                    $safeName = $base . '_' . $ctr . $ext;
                    $destPath = $docDir . '/' . $safeName;
                    $ctr++;
                } while (file_exists($destPath));
            }

            if (rename($srcPath, $destPath)) {
                $relativePath = str_replace(UPLOAD_PATH . '/', '', $destPath);
                ClientDocument::create($clientId, $relativePath, $sf['original_name'], $userId, $uploaderType);
            }
        }

        // Clean up staging directory
        if (is_dir($stagingDir)) {
            $remaining = glob($stagingDir . '/*');
            if (empty($remaining)) @rmdir($stagingDir);
        }
    }

    // Remove staging metadata from form data
    unset($formData['_staged_files']);
    OnboardingForm::save($clientId, $formData, 'submitted');
    echo json_encode(['success' => true, 'message' => 'Onboarding form submitted successfully! All documents have been moved to your Documents section.']);
    exit;
}

// ---- ADMIN: MARK ONBOARDING AS REVIEWED ----
if ($action === 'onboarding_review' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole(['admin']);
    if (!verifyCsrf()) { setFlash('danger', 'Invalid token.'); redirect('?action=dashboard'); }
    $clientId = (int) ($_POST['client_id'] ?? 0);
    if ($clientId) {
        OnboardingForm::markReviewed($clientId, (int) $_SESSION['user_id']);
        setFlash('success', 'Onboarding marked as reviewed.');
    }
    redirect('?action=onboarding&client_id=' . $clientId);
}
