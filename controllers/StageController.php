<?php
require_once BASE_PATH . '/models/Period.php';
require_once BASE_PATH . '/models/Client.php';
require_once BASE_PATH . '/models/Account.php';
require_once BASE_PATH . '/models/Stage1Status.php';
require_once BASE_PATH . '/models/StageStatus.php';
require_once BASE_PATH . '/models/FileRecord.php';
require_once BASE_PATH . '/models/User.php';
require_once BASE_PATH . '/models/NotificationQueue.php';
require_once BASE_PATH . '/models/StageNote.php';

function uploadNotifyTargetRole(string $stage): ?string {
    return match ($stage) {
        'stage1' => 'processor1',
        'stage2' => 'processor0',
        'stage3' => 'processor1',
        'stage4' => 'processor0',
        default  => null,
    };
}

function queueStageUploadNotification(string $stage, array $period, ?int $accountId, array $uploadedOriginalNames, int $uploadedByUserId): void {
    $targetRole = uploadNotifyTargetRole($stage);
    if ($targetRole === null) {
        return;
    }

    $client      = Client::find((int) $period['client_id']);
    $uploader    = User::findById($uploadedByUserId);
    $accountName = null;
    if ($stage === 'stage1' && $accountId) {
        $acc = Account::find($accountId);
        // Skip automatic bank feed accounts — their uploads are system-driven
        if (($acc['bank_feed_mode'] ?? 'manual') === 'automatic') {
            return;
        }
        $accountName = $acc['account_name'] ?? null;
    }

    NotificationQueue::enqueue(
        $targetRole,
        $stage,
        $client['name'] ?? 'N/A',
        $period['period_label'] ?? 'N/A',
        $accountName,
        $uploader['name'] ?? ('User ID ' . $uploadedByUserId),
        $uploadedOriginalNames,
        (int) ($client['id'] ?? 0)
    );
}

// ---- UPLOAD ----
if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    $failUpload = function (string $message, int $statusCode = 400) use ($isAjax): void {
        error_log("Upload failed ({$statusCode}): {$message}");
        if ($isAjax) {
            http_response_code($statusCode);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        }
        setFlash('danger', $message);
        redirect('?action=dashboard');
    };

    // Detect PHP post_max_size / upload_max_filesize exceeded:
    // when the combined POST body exceeds post_max_size, PHP silently
    // empties both $_POST and $_FILES, which causes CSRF and all
    // downstream checks to fail with misleading errors.
    if (empty($_POST) && empty($_FILES)) {
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength > 0) {
            $maxSize = ini_get('post_max_size') ?: 'unknown';
            error_log("Upload rejected: content-length {$contentLength} exceeds post_max_size ({$maxSize})");
            $failUpload(
                "The uploaded files are too large (server limit: {$maxSize}). "
                . 'Please reduce the file sizes or contact the administrator.',
                413
            );
        }
    }

    if (!verifyCsrf()) {
        $failUpload('Session expired or invalid token. Please reload the page and try again.', 403);
    }

    $successUpload = function (string $message) use ($isAjax): void {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $message]);
            exit;
        }
        setFlash('success', $message);
        redirect('?action=dashboard');
    };

    $periodId  = (int) ($_POST['period_id'] ?? 0);
    $stage     = $_POST['stage'] ?? '';
    $accountId = !empty($_POST['account_id']) ? (int) $_POST['account_id'] : null;

    // Validate stage name
    if (!in_array($stage, ['stage1', 'stage2', 'stage3', 'stage4'])) {
        $failUpload('Invalid stage.');
    }

    // Check upload role
    if (!hasRole(stageUploadRoles($stage))) {
        $failUpload('You do not have permission to upload to this stage.', 403);
    }

    // Clients can only upload to stage1
    if (currentRole() === 'client' && $stage !== 'stage1') {
        $failUpload('Clients can only upload to Stage 1.', 403);
    }

    // Stage 1 needs account_id
    if ($stage === 'stage1' && !$accountId) {
        $failUpload('Account is required for Stage 1.');
    }

    // Load period
    $period = Period::find($periodId);
    if (!$period) {
        $failUpload('Period not found.');
    }

    // Client ownership check: ensure the period belongs to one of their linked clients
    if (currentRole() === 'client') {
        $allowedClientIds = array_map('intval', $_SESSION['client_ids'] ?? []);
        if (!in_array((int) $period['client_id'], $allowedClientIds, true)) {
            $failUpload('This period does not belong to your account.', 403);
        }
    }

    // Check locked
    if ($period['is_locked']) {
        $failUpload('This period is locked. Uploads are disabled.');
    }

    // Check multi-file upload payload
    if (!isset($_FILES['files']) || !is_array($_FILES['files']['name'])) {
        $failUpload('Please select at least one file.');
    }

    $fileCount = count($_FILES['files']['name']);
    $validIndexes = [];
    for ($i = 0; $i < $fileCount; $i++) {
        if (($_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $validIndexes[] = $i;
        }
    }
    if (empty($validIndexes)) {
        $failUpload('No valid files selected for upload.');
    }

    $clientId = $period['client_id'];
    $userId   = $_SESSION['user_id'];

    // Determine if this is a re-upload
    $isReupload = FileRecord::hasFile($periodId, $stage, $accountId);

    // Delete-before-upload: remove old files from disk and DB
    $dir = stagePath($clientId, $periodId, $stage, $accountId);
    error_log("Upload: stage={$stage} period={$periodId} client={$clientId} account=" . ($accountId ?? 'null') . " dir={$dir} isReupload=" . ($isReupload ? 'yes' : 'no') . " fileCount=" . count($validIndexes));
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
            error_log("move_uploaded_file failed: {$tmpName} -> {$destPath} (is_uploaded_file=" . (is_uploaded_file($tmpName) ? 'yes' : 'no') . ", dir_exists=" . (is_dir($dir) ? 'yes' : 'no') . ", dir_writable=" . (is_writable($dir) ? 'yes' : 'no') . ")");
            continue;
        }

        $relativePath = str_replace(UPLOAD_PATH . '/', '', $destPath);
        FileRecord::create($periodId, $stage, $accountId, $relativePath, $origName, $userId);
        $uploadedOriginalNames[] = $origName;
    }

    if (empty($uploadedOriginalNames)) {
        $failUpload('Upload failed: none of the selected files could be saved.');
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

    $mailStats = ['target_role' => uploadNotifyTargetRole($stage), 'queued' => 1];
    queueStageUploadNotification($stage, $period, $accountId, $uploadedOriginalNames, $userId);

    // Log
    logAction($isReupload ? 'reupload' : 'upload', $userId, $periodId, $stage, $accountId, [
        'file_count' => count($uploadedOriginalNames),
        'filenames' => $uploadedOriginalNames,
        'notify_target_role' => $mailStats['target_role'],
        'notify_queued' => $mailStats['queued'],
    ]);

    $clientName = $period['client_name'] ?? (Client::find($clientId)['name'] ?? 'Unknown');
    $successUpload(ucfirst($stage) . ' upload complete for "' . $clientName . '": ' . count($uploadedOriginalNames) . ' file(s).');
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

    // Client ownership check
    if (currentRole() === 'client') {
        $period = Period::find($periodId);
        $allowedClientIds = array_map('intval', $_SESSION['client_ids'] ?? []);
        if (!$period || !in_array((int) $period['client_id'], $allowedClientIds, true)) {
            setFlash('danger', 'This period does not belong to your account.');
            redirect('?action=dashboard');
        }
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
    $client = Client::find((int) ($period['client_id'] ?? 0));
    $clientSafe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $client['name'] ?? ('client_' . ($period['client_id'] ?? 0)));
    $fileCount = count($existingFiles);

    // If only 1 file, download it directly. If 2+, create ZIP.
    if ($fileCount === 1) {
        // Single file download
        $singleFile = $existingFiles[0];
        $filePath = $singleFile['full_path'];
        $origName = basename($singleFile['name']);
        $fileName = $clientSafe . '_' . $origName;

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
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
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
    $zipName = $clientSafe . '_' . $stage . '_' . $periodSafe;
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

// ---- MARK DOWNLOADED (AJAX LED update without page reload) ----
if ($action === 'mark_downloaded' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!verifyCsrf()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }

    $periodId  = (int) ($_POST['period_id'] ?? 0);
    $stage     = $_POST['stage'] ?? '';
    $accountId = !empty($_POST['account_id']) ? (int) $_POST['account_id'] : null;

    if (!in_array($stage, ['stage1', 'stage2', 'stage3', 'stage4'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid stage.']);
        exit;
    }

    // Client ownership check
    if (currentRole() === 'client') {
        $period = Period::find($periodId);
        $allowedClientIds = array_map('intval', $_SESSION['client_ids'] ?? []);
        if (!$period || !in_array((int) $period['client_id'], $allowedClientIds, true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'This period does not belong to your account.']);
            exit;
        }
    }

    if (!hasRole(stageDownloadRoles($stage))) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Permission denied.']);
        exit;
    }

    if ($stage === 'stage1' && $accountId) {
        $s1 = Stage1Status::find($periodId, $accountId);
        if ($s1 && $s1['status'] === 'green') {
            Stage1Status::setOrange($periodId, $accountId);
        }
        $s1 = Stage1Status::find($periodId, $accountId);
        $newStatus = $s1 ? $s1['status'] : 'grey';
    } else {
        $ss = StageStatus::find($periodId, $stage);
        if ($ss && $ss['status'] === 'green') {
            StageStatus::setOrange($periodId, $stage);
        }
        $ss = StageStatus::find($periodId, $stage);
        $newStatus = $ss ? $ss['status'] : 'grey';
    }

    echo json_encode(['success' => true, 'status' => $newStatus]);
    exit;
}

// ---- PREVIEW FILE (inline view in browser) ----
if ($action === 'preview_file') {
    $fileId = (int) ($_GET['file_id'] ?? 0);
    if ($fileId <= 0) {
        http_response_code(400);
        echo 'Invalid file ID.';
        exit;
    }

    $fileRecord = FileRecord::findById($fileId);
    if (!$fileRecord) {
        http_response_code(404);
        echo 'File not found.';
        exit;
    }

    $stage = $fileRecord['stage_name'];
    $periodId = (int) $fileRecord['period_id'];

    // Client ownership check
    if (currentRole() === 'client') {
        $period = Period::find($periodId);
        $allowedClientIds = array_map('intval', $_SESSION['client_ids'] ?? []);
        if (!$period || !in_array((int) $period['client_id'], $allowedClientIds, true)) {
            http_response_code(403);
            echo 'This period does not belong to your account.';
            exit;
        }
    }

    $fullPath = UPLOAD_PATH . '/' . $fileRecord['file_path'];
    if (!file_exists($fullPath) || !is_file($fullPath)) {
        http_response_code(404);
        echo 'File not found on disk.';
        exit;
    }

    // Only allow previewable MIME types
    $ext = strtolower(pathinfo($fileRecord['original_filename'], PATHINFO_EXTENSION));
    $previewableMimes = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'svg'  => 'image/svg+xml',
        'bmp'  => 'image/bmp',
        'txt'  => 'text/plain',
        'csv'  => 'text/plain',
        'log'  => 'text/plain',
        'xml'  => 'text/xml',
        'json' => 'application/json',
        'htm'  => 'text/html',
        'html' => 'text/html',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls'  => 'application/vnd.ms-excel',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'doc'  => 'application/msword',
        'rtf'  => 'application/rtf',
    ];

    if (!isset($previewableMimes[$ext])) {
        http_response_code(415);
        echo 'This file type cannot be previewed.';
        exit;
    }

    $mime = $previewableMimes[$ext];
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . basename($fileRecord['original_filename']) . '"');
    header('Content-Length: ' . filesize($fullPath));
    header('X-Content-Type-Options: nosniff');
    readfile($fullPath);
    exit;
}

// ---- DELETE SINGLE FILE (AJAX) ----
if ($action === 'delete_file' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    error_reporting(0);
    @ini_set('display_errors', '0');
    header('Content-Type: application/json');

    try {
        if (!verifyCsrf()) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
            exit;
        }

        $fileId = (int) ($_POST['file_id'] ?? 0);
        if ($fileId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid file ID.']);
            exit;
        }

        $fileRecord = FileRecord::findById($fileId);
        if (!$fileRecord) {
            echo json_encode(['success' => false, 'message' => 'File not found.']);
            exit;
        }

        $stage     = $fileRecord['stage_name'];
        $periodId  = (int) $fileRecord['period_id'];
        $accountId = $fileRecord['account_id'] ? (int) $fileRecord['account_id'] : null;
        $period    = null;

        // Client ownership check
        if (currentRole() === 'client') {
            $period = Period::find($periodId);
            $allowedClientIds = array_map('intval', $_SESSION['client_ids'] ?? []);
            if (!$period || !in_array((int) $period['client_id'], $allowedClientIds, true)) {
                echo json_encode(['success' => false, 'message' => 'This period does not belong to your account.']);
                exit;
            }
        }

        // Check locked
        if (!$period) { $period = Period::find($periodId); }
        if ($period && $period['is_locked']) {
            echo json_encode(['success' => false, 'message' => 'This period is locked. Deletions are disabled.']);
            exit;
        }

        // Delete from disk
        $fullPath = UPLOAD_PATH . '/' . $fileRecord['file_path'];
        if (file_exists($fullPath) && is_file($fullPath)) {
            @unlink($fullPath);
        }

        // Delete from DB
        FileRecord::deleteById($fileId);

        // Log the deletion
        $userId = (int) $_SESSION['user_id'];
        logAction('file_delete', $userId, $periodId, $stage, $accountId, [
            'file_id'  => $fileId,
            'filename' => $fileRecord['original_filename'],
        ]);

        // Check if any files remain in this stage; if not, reset LED to grey
        $remainingFiles = FileRecord::forStage($periodId, $stage, $accountId);
        $filesRemaining = count($remainingFiles);
        if ($filesRemaining === 0) {
            if ($stage === 'stage1' && $accountId) {
                Stage1Status::resetToGrey($periodId, $accountId);
            } else {
                StageStatus::resetToGrey($periodId, $stage);
            }
        }

        // Record deletion in stage notes/comments (inline, no dependency on StageNote method signature)
        $noteAccountId = (int) ($accountId ?? 0);
        $updatedNote = '[]';
        try {
            $db = getDB();
            $deleter = User::findById($userId);
            $deleterName = $deleter ? $deleter['name'] : ('User #' . $userId);
            $noteMsg = 'Deleted file: ' . $fileRecord['original_filename'] . ' by ' . $deleterName . ' on ' . date('m/d/Y h:i A');

            // Load existing note
            $stmt = $db->prepare("SELECT note FROM stage_notes WHERE period_id=? AND stage_name=? AND account_id=?");
            $stmt->execute([$periodId, $stage, $noteAccountId]);
            $existing = $stmt->fetchColumn();

            $entries = [];
            if ($existing) {
                $decoded = json_decode($existing, true);
                if (is_array($decoded)) {
                    $entries = $decoded;
                } elseif (trim($existing) !== '') {
                    $entries = [['by' => 'System', 'at' => '', 'msg' => $existing]];
                }
            }

            $entries[] = ['by' => 'System', 'at' => date('Y-m-d H:i'), 'msg' => $noteMsg];
            $json = json_encode($entries, JSON_UNESCAPED_UNICODE);

            $save = $db->prepare(
                "INSERT INTO stage_notes (period_id, stage_name, account_id, note, updated_by, updated_at)
                 VALUES (?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE note=VALUES(note), updated_by=VALUES(updated_by), updated_at=NOW()"
            );
            $save->execute([$periodId, $stage, $noteAccountId, $json, $userId]);

            // Fetch updated notes
            $stmt2 = $db->prepare("SELECT note FROM stage_notes WHERE period_id=? AND stage_name=? AND account_id=?");
            $stmt2->execute([$periodId, $stage, $noteAccountId]);
            $updatedNote = $stmt2->fetchColumn() ?: '[]';
        } catch (\Throwable $noteErr) {
            error_log('delete_file note error: ' . $noteErr->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => 'File deleted.',
            'updated_note' => $updatedNote,
            'note_account_id' => $noteAccountId,
            'files_remaining' => $filesRemaining,
        ]);
    } catch (\Throwable $e) {
        error_log('delete_file error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}

// ---- UPLOAD SINGLE FILE (AJAX, appends to existing) ----
if ($action === 'upload_single' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    error_reporting(0);
    @ini_set('display_errors', '0');
    header('Content-Type: application/json');

    try {
        if (!verifyCsrf()) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
            exit;
        }

        $periodId  = (int) ($_POST['period_id'] ?? 0);
        $stage     = $_POST['stage'] ?? '';
        $accountId = !empty($_POST['account_id']) ? (int) $_POST['account_id'] : null;

        if (!in_array($stage, ['stage1', 'stage2', 'stage3', 'stage4'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid stage.']);
            exit;
        }

        if (!hasRole(stageUploadRoles($stage))) {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to upload to this stage.']);
            exit;
        }

        if (currentRole() === 'client' && $stage !== 'stage1') {
            echo json_encode(['success' => false, 'message' => 'Clients can only upload to Stage 1.']);
            exit;
        }

        if ($stage === 'stage1' && !$accountId) {
            echo json_encode(['success' => false, 'message' => 'Account is required for Stage 1.']);
            exit;
        }

        $period = Period::find($periodId);
        if (!$period) {
            echo json_encode(['success' => false, 'message' => 'Period not found.']);
            exit;
        }

        if (currentRole() === 'client') {
            $allowedClientIds = array_map('intval', $_SESSION['client_ids'] ?? []);
            if (!in_array((int) $period['client_id'], $allowedClientIds, true)) {
                echo json_encode(['success' => false, 'message' => 'This period does not belong to your account.']);
                exit;
            }
        }

        if ($period['is_locked']) {
            echo json_encode(['success' => false, 'message' => 'This period is locked. Uploads are disabled.']);
            exit;
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Please select a valid file.']);
            exit;
        }

        $clientId = $period['client_id'];
        $userId   = $_SESSION['user_id'];
        $dir      = stagePath($clientId, $periodId, $stage, $accountId);
        ensureDir($dir);

        $origName = $_FILES['file']['name'];
        $tmpName  = $_FILES['file']['tmp_name'];
        $safeName = sanitizeFilename($origName);
        $destPath = $dir . '/' . $safeName;

        if (!move_uploaded_file($tmpName, $destPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save file.']);
            exit;
        }

        $relativePath = str_replace(UPLOAD_PATH . '/', '', $destPath);
        FileRecord::create($periodId, $stage, $accountId, $relativePath, $origName, $userId);

        // Ensure LED is green
        if ($stage === 'stage1') {
            Stage1Status::setGreen($periodId, $accountId);
        } else {
            StageStatus::setGreen($periodId, $stage);
        }

        logAction('upload_single', $userId, $periodId, $stage, $accountId, [
            'filename' => $origName,
        ]);

        echo json_encode(['success' => true, 'message' => 'File uploaded successfully.']);
    } catch (\Throwable $e) {
        error_log('upload_single error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}

// ---- CHECK EXISTING FILES (pre-upload confirmation) ----
if ($action === 'stage_files') {
    header('Content-Type: application/json');

    $periodId  = (int) ($_GET['period_id'] ?? 0);
    $stage     = $_GET['stage'] ?? '';
    $accountId = !empty($_GET['account_id']) ? (int) $_GET['account_id'] : null;

    if (!in_array($stage, ['stage1', 'stage2', 'stage3', 'stage4'])) {
        echo json_encode(['files' => []]);
        exit;
    }

    $files = FileRecord::forStageDetailed($periodId, $stage, $accountId);
    echo json_encode(['files' => $files]);
    exit;
}

if ($action === 'check_existing_files') {
    header('Content-Type: application/json');

    $periodId  = (int) ($_GET['period_id'] ?? 0);
    $stage     = $_GET['stage'] ?? '';
    $accountId = !empty($_GET['account_id']) ? (int) $_GET['account_id'] : null;

    if (!in_array($stage, ['stage1', 'stage2', 'stage3', 'stage4'])) {
        echo json_encode(['files' => []]);
        exit;
    }

    $fileRecords = FileRecord::forStage($periodId, $stage, $accountId);
    $names = array_map(fn($f) => $f['original_filename'], $fileRecords);

    echo json_encode(['files' => $names]);
    exit;
}
