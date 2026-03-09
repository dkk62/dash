<?php

function redirect(string $url): void {
    header('Location: /dash/' . ltrim($url, '/'));
    exit;
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'id'   => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email'=> $_SESSION['user_email'],
        'role' => $_SESSION['user_role'],
    ];
}

function currentRole(): string {
    return $_SESSION['user_role'] ?? '';
}

function hasRole(array $roles): bool {
    return in_array(currentRole(), $roles, true);
}

function requireRole(array $roles): void {
    if (!hasRole($roles)) {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function sanitizeFilename(string $name): string {
    $name = basename($name);
    $name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $name);
    return time() . '_' . bin2hex(random_bytes(3)) . '_' . $name;
}

function getClientIp(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function setFlash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): bool {
    $token = $_POST['csrf_token'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function requireCsrf(): void {
    if (!verifyCsrf()) {
        http_response_code(403);
        echo 'Invalid CSRF token.';
        exit;
    }
}

/**
 * Delete all files in a directory (not recursive into subdirs)
 */
function clearDirectory(string $dir): void {
    if (!is_dir($dir)) return;
    $files = glob($dir . '/*');
    foreach ($files as $f) {
        if (is_file($f)) unlink($f);
    }
}

/**
 * Build the storage path for a stage
 */
function stagePath(int $clientId, int $periodId, string $stage, ?int $accountId = null): string {
    $base = UPLOAD_PATH . '/clients/' . $clientId . '/' . $periodId;
    if ($stage === 'stage1' && $accountId !== null) {
        return $base . '/stage1/' . $accountId;
    }
    return $base . '/' . $stage;
}

function ensureDir(string $dir): void {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

/**
 * Insert a log record
 */
function logAction(string $action, ?int $userId = null, ?int $periodId = null, ?string $stageName = null, ?int $accountId = null, ?int $clientId = null, ?array $metadata = null): void {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO logs (period_id, stage_name, account_id, action, user_id, client_id, ip_address, metadata) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $periodId,
        $stageName,
        $accountId,
        $action,
        $userId,
        $clientId,
        getClientIp(),
        $metadata ? json_encode($metadata) : null,
    ]);
}

/**
 * Determine which roles can UPLOAD files to each stage
 */
function stageUploadRoles(string $stage): array {
    return match ($stage) {
        'stage1' => ['processor0', 'admin', 'client'],
        'stage2' => ['processor1', 'admin'],
        'stage3' => ['processor0', 'admin'],
        'stage4' => ['processor1', 'admin'],
        default  => ['admin'],
    };
}

/**
 * Determine which roles can DOWNLOAD files from each stage
 */
function stageDownloadRoles(string $stage): array {
    return match ($stage) {
        'stage1' => ['processor1', 'admin', 'client'],
        'stage2' => ['processor0', 'admin', 'client'],
        'stage3' => ['processor1', 'admin', 'client'],
        'stage4' => ['processor0', 'admin', 'client'],
        default  => ['admin'],
    };
}

/**
 * Legacy function for backward compatibility (deprecated)
 */
function stageRoles(string $stage): array {
    return stageUploadRoles($stage);
}

/**
 * Convert a period label into a sortable timestamp.
 * Supports:
 * - Monthly labels: "Jan 26"
 * - Fiscal labels: "FY 26"
 * Falls back to created_at timestamp for custom labels.
 */
function periodLabelSortTimestamp(string $label, ?string $createdAt = null): int {
    if (preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+[0-9]{2}$/', $label)) {
        $ts = strtotime('01 ' . $label);
        if ($ts !== false) {
            return $ts;
        }
    }

    if (preg_match('/^FY\s+([0-9]{2})$/', $label, $m)) {
        $year = 2000 + (int) $m[1];
        $ts = strtotime($year . '-01-01');
        if ($ts !== false) {
            return $ts;
        }
    }

    if (!empty($createdAt)) {
        $ts = strtotime($createdAt);
        if ($ts !== false) {
            return $ts;
        }
    }

    return 0;
}

/**
 * Sort periods chronologically by period label date representation.
 * If client_name is present, periods are grouped by client first.
 */
function sortPeriodsChronologically(array &$periods): void {
    usort($periods, function (array $a, array $b): int {
        $aClient = $a['client_name'] ?? '';
        $bClient = $b['client_name'] ?? '';
        if ($aClient !== $bClient) {
            return strcmp($aClient, $bClient);
        }

        $aTs = periodLabelSortTimestamp((string) ($a['period_label'] ?? ''), $a['created_at'] ?? null);
        $bTs = periodLabelSortTimestamp((string) ($b['period_label'] ?? ''), $b['created_at'] ?? null);

        if ($aTs === $bTs) {
            return strcmp((string) ($a['period_label'] ?? ''), (string) ($b['period_label'] ?? ''));
        }
        return $aTs <=> $bTs;
    });
}
