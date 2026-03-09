<?php
require_once BASE_PATH . '/models/Period.php';
require_once BASE_PATH . '/models/Client.php';
require_once BASE_PATH . '/models/Account.php';
require_once BASE_PATH . '/models/Stage1Status.php';
require_once BASE_PATH . '/models/StageStatus.php';
require_once BASE_PATH . '/models/FileRecord.php';
require_once BASE_PATH . '/models/User.php';

function uploadNotifyTargetRole(string $stage): ?string {
    return match ($stage) {
        'stage1' => 'processor1',
        'stage2' => 'processor0',
        'stage3' => 'processor1',
        'stage4' => 'processor0',
        default  => null,
    };
}

function sendStageUploadNotifications(string $stage, array $period, ?int $accountId, array $uploadedOriginalNames, int $uploadedByUserId): array {
    $targetRole = uploadNotifyTargetRole($stage);
    if ($targetRole === null) {
        return ['target_role' => null, 'attempted' => 0, 'sent' => 0];
    }

    $recipients = User::byRole($targetRole);
    if (empty($recipients)) {
        return ['target_role' => $targetRole, 'attempted' => 0, 'sent' => 0];
    }

    $uploader = User::findById($uploadedByUserId);
    $client = Client::find((int) $period['client_id']);
    $accountName = null;
    if ($stage === 'stage1' && $accountId) {
        $acc = Account::find($accountId);
        $accountName = $acc['account_name'] ?? null;
    }

    $subject = 'Stage Upload Alert: ' . strtoupper($stage) . ' - ' . ($period['period_label'] ?? '');
    $body = "A new upload has been completed.\n\n"
          . "Stage: {$stage}\n"
          . "Client: " . ($client['name'] ?? 'N/A') . "\n"
          . "Period: " . ($period['period_label'] ?? 'N/A') . "\n"
          . ($accountName ? "Account: {$accountName}\n" : '')
          . "Uploaded by: " . ($uploader['name'] ?? 'User ID ' . $uploadedByUserId) . "\n"
          . "Files uploaded: " . count($uploadedOriginalNames) . "\n\n"
          . "File list:\n- " . implode("\n- ", $uploadedOriginalNames) . "\n\n"
          . "Please review the dashboard for next workflow action.";

    $mailerPath = BASE_PATH . '/vendor/PHPMailer/src/PHPMailer.php';
    if (!file_exists($mailerPath)) {
        logEmailFailure('stage_upload_notify', 'PHPMailer not found', [
            'stage' => $stage,
            'period_id' => $period['id'] ?? null,
            'target_role' => $targetRole,
            'mailer_path' => $mailerPath,
        ]);
        return ['target_role' => $targetRole, 'attempted' => count($recipients), 'sent' => 0];
    }

    require_once BASE_PATH . '/vendor/PHPMailer/src/Exception.php';
    require_once BASE_PATH . '/vendor/PHPMailer/src/PHPMailer.php';
    require_once BASE_PATH . '/vendor/PHPMailer/src/SMTP.php';

    $sent = 0;
    foreach ($recipients as $r) {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            if (SMTP_SECURE === 'ssl') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif (SMTP_SECURE === 'tls') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
            }
            $mail->Port       = SMTP_PORT;
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($r['email'], $r['name']);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->send();
            $sent++;
        } catch (\Exception $e) {
            logEmailFailure('stage_upload_notify', $e->getMessage(), [
                'stage' => $stage,
                'period_id' => $period['id'] ?? null,
                'target_role' => $targetRole,
                'recipient_email' => $r['email'] ?? null,
                'recipient_name' => $r['name'] ?? null,
            ]);
            continue;
        }
    }

    return ['target_role' => $targetRole, 'attempted' => count($recipients), 'sent' => $sent];
}

// ---- UPLOAD ----
if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $periodId  = (int) ($_POST['period_id'] ?? 0);
    $stage     = $_POST['stage'] ?? '';
    $accountId = !empty($_POST['account_id']) ? (int) $_POST['account_id'] : null;

    // Validate stage name
    if (!in_array($stage, ['stage1', 'stage2', 'stage3', 'stage4'])) {
        setFlash('danger', 'Invalid stage.');
        redirect('?action=dashboard');
    }

    // Check upload role
    if (!hasRole(stageUploadRoles($stage))) {
        http_response_code(403);
        setFlash('danger', 'You do not have permission to upload to this stage.');
        redirect('?action=dashboard');
    }

    // Clients can only upload to stage1
    if (currentRole() === 'client' && $stage !== 'stage1') {
        http_response_code(403);
        setFlash('danger', 'Clients can only upload to Stage 1.');
        redirect('?action=dashboard');
    }

    // Stage 1 needs account_id
    if ($stage === 'stage1' && !$accountId) {
        setFlash('danger', 'Account is required for Stage 1.');
        redirect('?action=dashboard');
    }

    // Load period
    $period = Period::find($periodId);
    if (!$period) {
        setFlash('danger', 'Period not found.');
        redirect('?action=dashboard');
    }

    // Check locked
    if ($period['is_locked']) {
        setFlash('danger', 'This period is locked. Uploads are disabled.');
        redirect('?action=dashboard');
    }

    // Check multi-file upload payload
    if (!isset($_FILES['files']) || !is_array($_FILES['files']['name'])) {
        setFlash('danger', 'Please select at least one file.');
        redirect('?action=dashboard');
    }

    $fileCount = count($_FILES['files']['name']);
    $validIndexes = [];
    for ($i = 0; $i < $fileCount; $i++) {
        if (($_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $validIndexes[] = $i;
        }
    }
    if (empty($validIndexes)) {
        setFlash('danger', 'No valid files selected for upload.');
        redirect('?action=dashboard');
    }

    $clientId = $period['client_id'];
    $userId   = $_SESSION['user_id'];

    // Determine if this is a re-upload
    $isReupload = FileRecord::hasFile($periodId, $stage, $accountId);

    // Delete-before-upload: remove old files from disk and DB
    $dir = stagePath($clientId, $periodId, $stage, $accountId);
    clearDirectory($dir);
    FileRecord::deleteForStage($periodId, $stage, $accountId);

    // Save new files
    ensureDir($dir);
    $uploadedOriginalNames = [];
    foreach ($validIndexes as $i) {
        $origName = $_FILES['files']['name'][$i];
        $tmpName  = $_FILES['files']['tmp_name'][$i];

        $safeName = sanitizeFilename($origName);
        $destPath = $dir . '/' . $safeName;
        if (!move_uploaded_file($tmpName, $destPath)) {
            continue;
        }

        $relativePath = str_replace(UPLOAD_PATH . '/', '', $destPath);
        FileRecord::create($periodId, $stage, $accountId, $relativePath, $origName, $userId);
        $uploadedOriginalNames[] = $origName;
    }

    if (empty($uploadedOriginalNames)) {
        setFlash('danger', 'Upload failed: none of the selected files could be saved.');
        redirect('?action=dashboard');
    }

    // Update LED → green
    if ($stage === 'stage1') {
        Stage1Status::setGreen($periodId, $accountId);
    } else {
        StageStatus::setGreen($periodId, $stage);
    }

    // Downstream reset
    $downstreamMap = [
        'stage1' => ['stage2', 'stage3', 'stage4'],
        'stage2' => ['stage3', 'stage4'],
        'stage3' => ['stage4'],
        'stage4' => [],
    ];

    foreach ($downstreamMap[$stage] as $ds) {
        StageStatus::resetToGrey($periodId, $ds);
        // Delete downstream files
        $dsDir = stagePath($clientId, $periodId, $ds);
        clearDirectory($dsDir);
        FileRecord::deleteForStage($periodId, $ds);
    }

    $mailStats = sendStageUploadNotifications($stage, $period, $accountId, $uploadedOriginalNames, $userId);

    // Log
    logAction($isReupload ? 'reupload' : 'upload', $userId, $periodId, $stage, $accountId, [
        'file_count' => count($uploadedOriginalNames),
        'filenames' => $uploadedOriginalNames,
        'notify_target_role' => $mailStats['target_role'],
        'notify_attempted' => $mailStats['attempted'],
        'notify_sent' => $mailStats['sent'],
    ]);

    setFlash('success', ucfirst($stage) . ' upload complete: ' . count($uploadedOriginalNames) . ' file(s).');
    redirect('?action=dashboard');
}

// ---- DOWNLOAD ----
if ($action === 'download') {
    $periodId  = (int) ($_GET['period_id'] ?? 0);
    $stage     = $_GET['stage'] ?? '';
    $accountId = !empty($_GET['account_id']) ? (int) $_GET['account_id'] : null;

    if (!in_array($stage, ['stage1', 'stage2', 'stage3', 'stage4'])) {
        setFlash('danger', 'Invalid stage.');
        redirect('?action=dashboard');
    }

    if (!hasRole(stageDownloadRoles($stage))) {
        setFlash('danger', 'You do not have permission to download from this stage.');
        redirect('?action=dashboard');
    }

    $fileRecords = FileRecord::forStage($periodId, $stage, $accountId);
    if (empty($fileRecords)) {
        setFlash('danger', 'No files found for download.');
        redirect('?action=dashboard');
    }

    $existingFiles = [];
    foreach ($fileRecords as $fr) {
        $fullPath = UPLOAD_PATH . '/' . $fr['file_path'];
        if (file_exists($fullPath) && is_file($fullPath)) {
            $existingFiles[] = [
                'full_path' => $fullPath,
                'name' => $fr['original_filename'],
            ];
        }
    }
    if (empty($existingFiles)) {
        setFlash('danger', 'Files were not found on disk.');
        redirect('?action=dashboard');
    }

    $period = Period::find($periodId);
    $fileCount = count($existingFiles);

    // If only 1 file, download it directly. If 2+, create ZIP.
    if ($fileCount === 1) {
        // Single file download
        $singleFile = $existingFiles[0];
        $filePath = $singleFile['full_path'];
        $fileName = $singleFile['name'];

        if (!file_exists($filePath) || !is_file($filePath)) {
            setFlash('danger', 'File not found.');
            redirect('?action=dashboard');
        }

        // Update LED status
        if ($stage === 'stage1' && $accountId) {
            $s1 = Stage1Status::find($periodId, $accountId);
            if ($s1 && $s1['status'] === 'green') {
                Stage1Status::setOrange($periodId, $accountId);
            }
        } else {
            $ss = StageStatus::find($periodId, $stage);
            if ($ss && $ss['status'] === 'green') {
                StageStatus::setOrange($periodId, $stage);
            }
        }

        logAction('download', $_SESSION['user_id'], $periodId, $stage, $accountId, [
            'file_count' => $fileCount,
            'type' => 'single',
        ]);

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }

    // Multiple files: create ZIP
    if (!class_exists('ZipArchive')) {
        setFlash('danger', 'ZIP extension is not enabled on this server.');
        redirect('?action=dashboard');
    }

    $zipPath = tempnam(sys_get_temp_dir(), 'dash_zip_');
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
        setFlash('danger', 'Could not create ZIP archive.');
        redirect('?action=dashboard');
    }

    $usedNames = [];
    foreach ($existingFiles as $idx => $f) {
        $entryName = basename($f['name']);
        if (isset($usedNames[$entryName])) {
            $entryName = ($idx + 1) . '_' . $entryName;
        }
        $usedNames[$entryName] = true;
        $zip->addFile($f['full_path'], $entryName);
    }
    $zip->close();

    if (!file_exists($zipPath) || filesize($zipPath) <= 0) {
        @unlink($zipPath);
        setFlash('danger', 'ZIP creation failed. Download was not completed.');
        redirect('?action=dashboard');
    }

    // Download considered successful at this point: ZIP exists and is streamable.
    if ($stage === 'stage1' && $accountId) {
        $s1 = Stage1Status::find($periodId, $accountId);
        if ($s1 && $s1['status'] === 'green') {
            Stage1Status::setOrange($periodId, $accountId);
        }
    } else {
        $ss = StageStatus::find($periodId, $stage);
        if ($ss && $ss['status'] === 'green') {
            StageStatus::setOrange($periodId, $stage);
        }
    }

    logAction('download', $_SESSION['user_id'], $periodId, $stage, $accountId, [
        'file_count' => $fileCount,
        'type' => 'zip',
    ]);

    $periodSafe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $period['period_label'] ?? ('period_' . $periodId));
    $zipName = $stage . '_' . $periodSafe;
    if ($accountId) {
        $zipName .= '_acc_' . $accountId;
    }
    $zipName .= '.zip';

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipName . '"');
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath);
    @unlink($zipPath);
    exit;
}
