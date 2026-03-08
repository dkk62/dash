<?php
require_once BASE_PATH . '/models/Period.php';
require_once BASE_PATH . '/models/Client.php';
require_once BASE_PATH . '/models/Account.php';
require_once BASE_PATH . '/models/Stage1Status.php';
require_once BASE_PATH . '/models/StageStatus.php';
require_once BASE_PATH . '/models/FileRecord.php';

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

    // Check role
    if (!hasRole(stageRoles($stage))) {
        http_response_code(403);
        setFlash('danger', 'You do not have permission for this stage.');
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

    // Check file upload
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        setFlash('danger', 'File upload error.');
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

    // Save new file
    ensureDir($dir);
    $safeName = sanitizeFilename($_FILES['file']['name']);
    $destPath = $dir . '/' . $safeName;
    move_uploaded_file($_FILES['file']['tmp_name'], $destPath);

    $relativePath = str_replace(UPLOAD_PATH . '/', '', $destPath);
    FileRecord::create($periodId, $stage, $accountId, $relativePath, $_FILES['file']['name'], $userId);

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

    // Log
    logAction($isReupload ? 'reupload' : 'upload', $userId, $periodId, $stage, $accountId, [
        'filename' => $_FILES['file']['name'],
    ]);

    setFlash('success', ucfirst($stage) . ' file uploaded successfully.');
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

    if (!hasRole(stageRoles($stage))) {
        setFlash('danger', 'You do not have permission for this stage.');
        redirect('?action=dashboard');
    }

    $fileRecord = FileRecord::getFirst($periodId, $stage, $accountId);
    if (!$fileRecord) {
        setFlash('danger', 'No file found for download.');
        redirect('?action=dashboard');
    }

    $fullPath = UPLOAD_PATH . '/' . $fileRecord['file_path'];
    if (!file_exists($fullPath)) {
        setFlash('danger', 'File not found on disk.');
        redirect('?action=dashboard');
    }

    $period = Period::find($periodId);

    // Update LED → orange (only if currently green)
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

    // Log
    logAction('download', $_SESSION['user_id'], $periodId, $stage, $accountId, [
        'filename' => $fileRecord['original_filename'],
    ]);

    // Serve file
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($fileRecord['original_filename']) . '"');
    header('Content-Length: ' . filesize($fullPath));
    readfile($fullPath);
    exit;
}
