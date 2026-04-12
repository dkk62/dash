<?php
require_once BASE_PATH . '/models/Client.php';
require_once BASE_PATH . '/models/ClientDocument.php';

// ---- DOCUMENTS LISTING PAGE ----
if ($action === 'documents') {
    $clients = Client::allActive();

    // Filter by role
    if (currentRole() === 'client') {
        $allowedClientIds = array_map('intval', (array) ($_SESSION['client_ids'] ?? []));
        $clients = array_values(array_filter($clients, fn($c) => in_array((int) $c['id'], $allowedClientIds, true)));
    } elseif (in_array(currentRole(), ['processor0', 'processor1'])) {
        $assignedClientIds = Client::getClientIdsForUser((int) $_SESSION['user_id']);
        $clients = array_values(array_filter($clients, fn($c) => in_array((int) $c['id'], $assignedClientIds, true)));
    }

    $allClientIds = array_map(fn($c) => (int) $c['id'], $clients);
    $docStatus = ClientDocument::bulkHasFiles($allClientIds);

    $clientData = [];
    foreach ($clients as $client) {
        $cid = (int) $client['id'];
        $clientData[] = [
            'client'      => $client,
            'hasDocFiles' => isset($docStatus[$cid]),
        ];
    }

    include BASE_PATH . '/views/documents.php';
    exit;
}

// ---- UPLOAD DOCUMENT ----
if ($action === 'doc_upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

    $fail = function (string $message, int $statusCode = 400) use ($isAjax): void {
        if ($isAjax) {
            http_response_code($statusCode);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        }
        setFlash('danger', $message);
        redirect('?action=documents');
    };

    // Detect PHP post_max_size exceeded
    if (empty($_POST) && empty($_FILES)) {
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength > 0) {
            $maxSize = ini_get('post_max_size') ?: 'unknown';
            $fail("The uploaded files are too large (server limit: {$maxSize}).", 413);
        }
    }

    if (!verifyCsrf()) {
        $fail('Session expired or invalid token. Please reload the page and try again.', 403);
    }

    $success = function (string $message) use ($isAjax): void {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $message]);
            exit;
        }
        setFlash('success', $message);
        redirect('?action=documents');
    };

    $clientId = (int) ($_POST['client_id'] ?? 0);
    $client = Client::find($clientId);
    if (!$client) {
        $fail('Client not found.');
    }

    // Client ownership check
    if (currentRole() === 'client') {
        $allowedClientIds = array_map('intval', $_SESSION['client_ids'] ?? []);
        if (!in_array($clientId, $allowedClientIds, true)) {
            $fail('This client does not belong to your account.', 403);
        }
    }

    if (!isset($_FILES['files']) || !is_array($_FILES['files']['name'])) {
        $fail('Please select at least one file.');
    }

    $fileCount = count($_FILES['files']['name']);
    $validIndexes = [];
    for ($i = 0; $i < $fileCount; $i++) {
        if (($_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $validIndexes[] = $i;
        }
    }
    if (empty($validIndexes)) {
        $fail('No valid files selected for upload.');
    }

    $userId = (int) $_SESSION['user_id'];
    $uploaderType = ($_SESSION['user_type'] ?? 'user') === 'client' ? 'client' : 'user';
    $dir = ClientDocument::docPath($clientId);
    ensureDir($dir);

    $uploadedNames = [];
    foreach ($validIndexes as $i) {
        $origName = $_FILES['files']['name'][$i];
        $tmpName  = $_FILES['files']['tmp_name'][$i];

        // Sanitize base name and handle duplicate filenames with numeric suffix
        $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($origName));
        $destPath = $dir . '/' . $safeName;

        if (file_exists($destPath)) {
            $pathInfo = pathinfo($safeName);
            $base = $pathInfo['filename'];
            $ext = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            $counter = 1;
            do {
                $safeName = $base . '_' . $counter . $ext;
                $destPath = $dir . '/' . $safeName;
                $counter++;
            } while (file_exists($destPath));
        }

        if (!move_uploaded_file($tmpName, $destPath)) {
            continue;
        }

        $relativePath = str_replace(UPLOAD_PATH . '/', '', $destPath);
        ClientDocument::create($clientId, $relativePath, $origName, $userId, $uploaderType);
        $uploadedNames[] = $origName;
    }

    if (empty($uploadedNames)) {
        $fail('Upload failed: none of the selected files could be saved.');
    }

    $success('Document upload complete for "' . ($client['name'] ?? 'Unknown') . '": ' . count($uploadedNames) . ' file(s).');
}

// ---- LIST DOCUMENT FILES (AJAX) ----
if ($action === 'doc_files') {
    header('Content-Type: application/json');

    $clientId = (int) ($_GET['client_id'] ?? 0);
    if (!$clientId) {
        echo json_encode(['files' => []]);
        exit;
    }

    // Client ownership check
    if (currentRole() === 'client') {
        $allowedClientIds = array_map('intval', $_SESSION['client_ids'] ?? []);
        if (!in_array($clientId, $allowedClientIds, true)) {
            http_response_code(403);
            echo json_encode(['files' => []]);
            exit;
        }
    }

    $files = ClientDocument::forClientDetailed($clientId);
    echo json_encode(['files' => $files]);
    exit;
}

// ---- DOWNLOAD SELECTED DOCUMENTS ----
if ($action === 'doc_download' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!verifyCsrf()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }

    $clientId = (int) ($_POST['client_id'] ?? 0);
    $fileIds = $_POST['file_ids'] ?? [];
    if (!is_array($fileIds)) {
        $fileIds = [$fileIds];
    }
    $fileIds = array_map('intval', array_filter($fileIds));

    if (empty($fileIds) || !$clientId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No files selected.']);
        exit;
    }

    $client = Client::find($clientId);
    if (!$client) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Client not found.']);
        exit;
    }

    // Client ownership check
    if (currentRole() === 'client') {
        $allowedClientIds = array_map('intval', $_SESSION['client_ids'] ?? []);
        if (!in_array($clientId, $allowedClientIds, true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            exit;
        }
    }

    $records = ClientDocument::findByIds($fileIds);
    // Filter to only files that belong to this client
    $records = array_filter($records, fn($r) => (int) $r['client_id'] === $clientId);

    $existingFiles = [];
    foreach ($records as $r) {
        $fullPath = UPLOAD_PATH . '/' . $r['file_path'];
        if (file_exists($fullPath) && is_file($fullPath)) {
            $existingFiles[] = [
                'full_path' => $fullPath,
                'name' => $r['original_filename'],
            ];
        }
    }

    if (empty($existingFiles)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No files found on disk.']);
        exit;
    }

    // Build a download token so the actual file streaming happens via GET
    $token = bin2hex(random_bytes(16));
    $_SESSION['doc_download_' . $token] = [
        'files' => $existingFiles,
        'client' => $client,
        'expires' => time() + 120,
    ];

    echo json_encode(['success' => true, 'token' => $token]);
    exit;
}

// ---- STREAM DOCUMENT DOWNLOAD (GET with token) ----
if ($action === 'doc_download_stream') {
    $token = $_GET['token'] ?? '';
    $key = 'doc_download_' . $token;

    if (empty($token) || empty($_SESSION[$key])) {
        setFlash('danger', 'Download link expired or invalid.');
        redirect('?action=documents');
    }

    $data = $_SESSION[$key];
    unset($_SESSION[$key]);

    if (($data['expires'] ?? 0) < time()) {
        setFlash('danger', 'Download link expired.');
        redirect('?action=documents');
    }

    $existingFiles = $data['files'];
    $client = $data['client'];
    $clientSafe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $client['name'] ?? ('client_' . ($client['id'] ?? 0)));

    if (count($existingFiles) === 1) {
        $singleFile = $existingFiles[0];
        $filePath = $singleFile['full_path'];
        $fileName = $clientSafe . '_' . basename($singleFile['name']);

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }

    // Multiple files: ZIP
    if (!class_exists('ZipArchive')) {
        setFlash('danger', 'ZIP extension is not enabled on this server.');
        redirect('?action=documents');
    }

    $zipPath = tempnam(sys_get_temp_dir(), 'dash_doc_zip_');
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
        setFlash('danger', 'Could not create ZIP archive.');
        redirect('?action=documents');
    }

    $usedNames = [];
    foreach ($existingFiles as $f) {
        $entryName = basename($f['name']);
        if (isset($usedNames[$entryName])) {
            $pathInfo = pathinfo($entryName);
            $base = $pathInfo['filename'];
            $ext = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
            $counter = 1;
            do {
                $entryName = $base . '_' . $counter . $ext;
                $counter++;
            } while (isset($usedNames[$entryName]));
        }
        $usedNames[$entryName] = true;
        $zip->addFile($f['full_path'], $entryName);
    }
    $zip->close();

    $zipName = $clientSafe . '_documents.zip';

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipName . '"');
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath);
    @unlink($zipPath);
    exit;
}

// ---- PREVIEW DOCUMENT FILE (inline view in browser) ----
if ($action === 'doc_preview') {
    $docId = (int) ($_GET['doc_id'] ?? 0);
    if ($docId <= 0) {
        http_response_code(400);
        echo 'Invalid document ID.';
        exit;
    }

    $docRecord = ClientDocument::findById($docId);
    if (!$docRecord) {
        http_response_code(404);
        echo 'Document not found.';
        exit;
    }

    $clientId = (int) $docRecord['client_id'];

    // Client ownership check
    if (currentRole() === 'client') {
        $allowedClientIds = array_map('intval', $_SESSION['client_ids'] ?? []);
        if (!in_array($clientId, $allowedClientIds, true)) {
            http_response_code(403);
            echo 'This document does not belong to your account.';
            exit;
        }
    }

    $fullPath = UPLOAD_PATH . '/' . $docRecord['file_path'];
    if (!file_exists($fullPath) || !is_file($fullPath)) {
        http_response_code(404);
        echo 'File not found on disk.';
        exit;
    }

    $ext = strtolower(pathinfo($docRecord['original_filename'], PATHINFO_EXTENSION));
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
    header('Content-Disposition: inline; filename="' . basename($docRecord['original_filename']) . '"');
    header('Content-Length: ' . filesize($fullPath));
    header('X-Content-Type-Options: nosniff');
    readfile($fullPath);
    exit;
}
