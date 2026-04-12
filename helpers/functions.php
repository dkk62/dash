<?php

function basePath(): string {
    $base = defined('APP_BASE_URL') ? (string) APP_BASE_URL : '/dash';
    $base = trim($base);
    if ($base === '') {
        $base = '/';
    }
    if ($base[0] !== '/') {
        $base = '/' . $base;
    }
    return rtrim($base, '/');
}

function appUrl(string $path = ''): string {
    $base = basePath();
    if ($path === '' || $path === '/') {
        return $base . '/';
    }
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }
    if ($path[0] === '?') {
        return $base . '/' . $path;
    }
    return $base . '/' . ltrim($path, '/');
}

function assetUrl(string $path): string {
    return appUrl('public/' . ltrim($path, '/'));
}

function redirect(string $url): void {
    header('Location: ' . appUrl($url));
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

function logEmailFailure(string $context, string $errorMessage, array $metadata = []): void {
    $logDir = defined('LOG_PATH') ? LOG_PATH : (defined('BASE_PATH') ? BASE_PATH . '/logs' : __DIR__ . '/../logs');
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $entry = [
        'timestamp' => date('c'),
        'context' => $context,
        'error' => $errorMessage,
        'metadata' => $metadata,
        'ip' => getClientIp(),
    ];

    error_log(json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, 3, $logDir . '/email_failures.log');
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
    if ($files === false) {
        $files = scandir($dir);
        if ($files === false) return;
        $files = array_map(fn($f) => $dir . '/' . $f, array_diff($files, ['.', '..']));
    }
    foreach ($files as $f) {
        if (is_file($f)) unlink($f);
    }
}

/**
 * Recursively delete a directory and all its contents
 */
function deleteDirectory(string $dir): void {
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
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
function logAction(string $action, ?int $userId = null, ?int $periodId = null, ?string $stageName = null, ?int $accountId = null, int|array|null $clientIdOrMetadata = null, ?array $metadata = null): void {
    // Backward compatibility: many call sites pass metadata as the 6th argument.
    $clientId = null;
    if (is_array($clientIdOrMetadata)) {
        $metadata = $clientIdOrMetadata;
    } else {
        $clientId = $clientIdOrMetadata;
    }

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
 * Check if a period label is monthly format like "Jan 26".
 */
function isMonthlyLabel(string $label): bool {
    return (bool) preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+[0-9]{2}$/', $label);
}

/**
 * Check if a period label is fiscal format like "FY 26".
 */
function isFiscalLabel(string $label): bool {
    return (bool) preg_match('/^FY\s+[0-9]{2}$/', $label);
}

/**
 * Determine whether the period should be visible by date.
 * Monthly: show only up to the current month.
 * Fiscal: show only up to the current FY suffix.
 */
function isPeriodVisibleByDate(string $label): bool {
    $nowYear2 = (int) date('y');

    if (isMonthlyLabel($label)) {
        $periodTs = strtotime('01 ' . $label);
        if ($periodTs === false) {
            return false;
        }
        $periodYear = (int) date('y', $periodTs);
        $periodMonth = (int) date('n', $periodTs);
        $nowMonth = (int) date('n');

        if ($periodYear < $nowYear2) {
            return true;
        }
        if ($periodYear > $nowYear2) {
            return false;
        }
        return $periodMonth <= $nowMonth;
    }

    if (isFiscalLabel($label)) {
        preg_match('/^FY\s+([0-9]{2})$/', $label, $m);
        $fy = (int) ($m[1] ?? 0);
        return $fy <= $nowYear2;
    }

    // For custom labels, keep visible.
    return true;
}

/**
 * A period is considered active if any stage moved from grey or any file exists.
 */
function periodHasActivity(array $s1statuses, array $stageStatuses, array $s1Files, bool $has2, bool $has3, bool $has4): bool {
    foreach (['stage2', 'stage3', 'stage4'] as $sn) {
        if (($stageStatuses[$sn]['status'] ?? 'grey') !== 'grey') {
            return true;
        }
    }
    if ($has2 || $has3 || $has4) {
        return true;
    }
    foreach ($s1Files as $exists) {
        if ($exists) {
            return true;
        }
    }
    return false;
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
            return strcasecmp($aClient, $bClient);
        }

        $aTs = periodLabelSortTimestamp((string) ($a['period_label'] ?? ''), $a['created_at'] ?? null);
        $bTs = periodLabelSortTimestamp((string) ($b['period_label'] ?? ''), $b['created_at'] ?? null);

        if ($aTs === $bTs) {
            return strcmp((string) ($a['period_label'] ?? ''), (string) ($b['period_label'] ?? ''));
        }
        return $aTs <=> $bTs;
    });
}

/**
 * Return true if the period label represents a month strictly BEFORE the current calendar month.
 * Used for reminder filtering — only past periods (not the current or future month) need reminders.
 */
function isPeriodBeforeCurrentPeriod(string $label): bool {
    $nowYear2 = (int) date('y');
    $nowMonth = (int) date('n');

    // Monthly label: "Jan 26", "Feb 26", etc.
    if (preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+[0-9]{2}$/', $label)) {
        $periodTs = strtotime('01 ' . $label);
        if ($periodTs === false) {
            return false;
        }
        $periodYear  = (int) date('y', $periodTs);
        $periodMonth = (int) date('n', $periodTs);

        if ($periodYear < $nowYear2) {
            return true;
        }
        if ($periodYear > $nowYear2) {
            return false;
        }
        return $periodMonth < $nowMonth;
    }

    // Fiscal label: "FY 26"
    if (preg_match('/^FY\s+([0-9]{2})$/', $label, $m)) {
        return (int)$m[1] < $nowYear2;
    }

    // Custom labels: include in reminders.
    return true;
}

/**
 * Check if the current user has permission to manage clients (create/edit/delete).
 * Admin always has access; other users need the perm_client_edit setting.
 */
function hasClientPermission(): bool {
    if (hasRole(['admin'])) return true;
    if (!isLoggedIn()) return false;
    require_once BASE_PATH . '/models/Setting.php';
    return Setting::userHasPermission((int)$_SESSION['user_id'], 'perm_client_edit');
}

/**
 * Require client management permission or abort with 403.
 */
function requireClientPermission(): void {
    if (!hasClientPermission()) {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }
}

/**
 * Check if the current user has permission to send reminder emails.
 * Admin always has access; other users need the perm_send_reminders setting.
 */
function hasReminderPermission(): bool {
    if (hasRole(['admin'])) return true;
    if (!isLoggedIn()) return false;
    require_once BASE_PATH . '/models/Setting.php';
    return Setting::userHasPermission((int)$_SESSION['user_id'], 'perm_send_reminders');
}

/**
 * Require reminder permission or abort with 403.
 */
function requireReminderPermission(): void {
    if (!hasReminderPermission()) {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }
}

/**
 * Build structured pending report data for all processors.
 * Returns array of [ 'user_name'=>, 'role_label'=>, 'items'=>[ ['stage'=>, 'client'=>, 'account'=>|null, 'periods'=>[...]] ] ]
 */
function buildPendingReportData(array $processorUsers, array $allPeriods, array $bulkS1, array $bulkStages): array {
    $reportData = [];

    foreach ($processorUsers as $user) {
        $uid = (int)$user['id'];
        $userClientIds = Client::getClientIdsForUser($uid);
        if (empty($userClientIds)) continue;

        $clientIdSet = array_flip($userClientIds);
        $userPeriods = array_values(array_filter($allPeriods, fn($p) => isset($clientIdSet[(int)$p['client_id']])));
        if (empty($userPeriods)) continue;

        $clientPeriods = [];
        $clientNames = [];
        foreach ($userPeriods as $p) {
            $cid = (int)$p['client_id'];
            $clientPeriods[$cid][$p['period_label']] = $p;
            $clientNames[$cid] = $p['client_name'];
        }

        // Collect items grouped by stage+client+account => periods[]
        $items = [];

        if ($user['role'] === 'processor0') {
            // Stage 1 pending (grey) — grouped by client+account
            $s1Groups = []; // key => ['client'=>, 'account'=>, 'periods'=>[]]
            foreach ($clientPeriods as $cid => $periodsByLabel) {
                foreach ($periodsByLabel as $label => $p) {
                    $pid = (int)$p['id'];
                    foreach ($bulkS1[$pid] ?? [] as $s1) {
                        if ($s1['status'] === 'grey') {
                            $acctName = $s1['account_name'];
                            if (($s1['bank_feed_mode'] ?? 'manual') === 'automatic') {
                                $acctName .= ' (auto)';
                            }
                            $key = $cid . '|' . $s1['account_id'];
                            if (!isset($s1Groups[$key])) {
                                $s1Groups[$key] = ['stage' => 'Stage 1', 'client' => $clientNames[$cid], 'account' => $acctName, 'periods' => []];
                            }
                            $s1Groups[$key]['periods'][] = $label;
                        }
                    }
                }
            }
            foreach ($s1Groups as $g) $items[] = $g;

            // Stage 3 pending — grouped by client
            $s3Groups = [];
            foreach ($clientPeriods as $cid => $periodsByLabel) {
                foreach ($periodsByLabel as $label => $p) {
                    $pid = (int)$p['id'];
                    $stageStatuses = $bulkStages[$pid] ?? [];
                    $s2Status = $stageStatuses['stage2']['status'] ?? 'grey';
                    $s3Status = $stageStatuses['stage3']['status'] ?? 'grey';
                    if ($s2Status !== 'grey' && $s3Status === 'grey') {
                        if (!isset($s3Groups[$cid])) {
                            $s3Groups[$cid] = ['stage' => 'Stage 3', 'client' => $clientNames[$cid], 'account' => null, 'periods' => []];
                        }
                        $s3Groups[$cid]['periods'][] = $label;
                    }
                }
            }
            foreach ($s3Groups as $g) $items[] = $g;

        } elseif ($user['role'] === 'processor1') {
            // Stage 2 pending — grouped by client
            $s2Groups = [];
            foreach ($clientPeriods as $cid => $periodsByLabel) {
                foreach ($periodsByLabel as $label => $p) {
                    $pid = (int)$p['id'];
                    $s1statuses = $bulkS1[$pid] ?? [];
                    $stageStatuses = $bulkStages[$pid] ?? [];
                    $allS1NonGrey = !empty($s1statuses);
                    foreach ($s1statuses as $s1) {
                        if ($s1['status'] === 'grey') { $allS1NonGrey = false; break; }
                    }
                    $s2Status = $stageStatuses['stage2']['status'] ?? 'grey';
                    if ($allS1NonGrey && $s2Status === 'grey') {
                        if (!isset($s2Groups[$cid])) {
                            $s2Groups[$cid] = ['stage' => 'Stage 2', 'client' => $clientNames[$cid], 'account' => null, 'periods' => []];
                        }
                        $s2Groups[$cid]['periods'][] = $label;
                    }
                }
            }
            foreach ($s2Groups as $g) $items[] = $g;

            // Stage 4 pending — grouped by client
            $s4Groups = [];
            foreach ($clientPeriods as $cid => $periodsByLabel) {
                foreach ($periodsByLabel as $label => $p) {
                    $pid = (int)$p['id'];
                    $stageStatuses = $bulkStages[$pid] ?? [];
                    $s3Status = $stageStatuses['stage3']['status'] ?? 'grey';
                    $s4Status = $stageStatuses['stage4']['status'] ?? 'grey';
                    if ($s3Status !== 'grey' && $s4Status === 'grey') {
                        if (!isset($s4Groups[$cid])) {
                            $s4Groups[$cid] = ['stage' => 'Stage 4', 'client' => $clientNames[$cid], 'account' => null, 'periods' => []];
                        }
                        $s4Groups[$cid]['periods'][] = $label;
                    }
                }
            }
            foreach ($s4Groups as $g) $items[] = $g;
        }

        if (!empty($items)) {
            $roleLabel = $user['role'] === 'processor0' ? 'Processor 0' : 'Processor 1';
            $reportData[] = [
                'user_name'  => $user['name'],
                'role_label' => $roleLabel,
                'items'      => $items,
            ];
        }
    }

    return $reportData;
}

/**
 * Build mobile-friendly HTML email body for the pending work report.
 */
function buildPendingReportHtml(array $reportData, string $reportDate): string {
    $h = function(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };

    $html = '<!DOCTYPE html><html><head><meta charset="utf-8">'
          . '<meta name="viewport" content="width=device-width,initial-scale=1">'
          . '<style>'
          . 'body{margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#333;background:#f4f4f4;}'
          . '.wrap{max-width:720px;margin:0 auto;background:#fff;}'
          . '.header{background:#1F7A6B;color:#fff;padding:14px 16px;}'
          . '.header h1{margin:0;font-size:16px;font-weight:600;}'
          . '.header p{margin:3px 0 0;font-size:11px;opacity:.85;}'
          . '.section{padding:0 8px;}'
          . '.user-header{background:#343a40;color:#fff;padding:8px 12px;margin:12px 0 0;border-radius:5px 5px 0 0;font-size:13px;}'
          . '.user-header .badge{background:#17a2b8;padding:2px 6px;border-radius:4px;font-size:10px;margin-left:6px;}'
          . 'table{width:100%;border-collapse:collapse;margin-bottom:12px;}'
          . 'table th{background:#e9ecef;text-align:left;padding:5px 7px;font-size:11px;border:1px solid #dee2e6;white-space:nowrap;}'
          . 'table td{padding:5px 7px;border:1px solid #dee2e6;font-size:11px;vertical-align:top;}'
          . '.stage-badge{display:inline-block;padding:1px 6px;border-radius:3px;font-size:10px;font-weight:600;color:#fff;white-space:nowrap;}'
          . '.s1{background:#6c757d;}.s2{background:#0d6efd;}.s3{background:#fd7e14;}.s4{background:#6f42c1;}'
          . '.period-tag{display:inline-block;background:#fff3cd;color:#664d03;border:1px solid #ffecb5;padding:0 5px;border-radius:3px;font-size:10px;margin:1px 1px;white-space:nowrap;}'
          . '.footer{padding:12px 16px;font-size:10px;color:#888;border-top:1px solid #eee;margin-top:6px;}'
          . '@media(max-width:480px){table th,table td{padding:4px 5px;font-size:10px;} .header h1{font-size:14px;}}'
          . '</style></head><body>'
          . '<div class="wrap">';

    $html .= '<div class="header">'
           . '<h1>Pending Work Report</h1>'
           . '<p>Date: ' . $h($reportDate) . '</p>'
           . '</div>';

    $html .= '<div class="section">';

    $stageBadgeClass = ['Stage 1' => 's1', 'Stage 2' => 's2', 'Stage 3' => 's3', 'Stage 4' => 's4'];

    foreach ($reportData as $section) {
        $html .= '<div class="user-header">'
               . $h($section['user_name'])
               . '<span class="badge">' . $h($section['role_label']) . '</span>'
               . '</div>';

        $html .= '<table><thead><tr>'
               . '<th>Stage</th>'
               . '<th>Client</th>'
               . '<th>Account</th>'
               . '<th>Pending Periods</th>'
               . '</tr></thead><tbody>';

        foreach ($section['items'] as $item) {
            $cls = $stageBadgeClass[$item['stage']] ?? 's1';
            $html .= '<tr>';
            $html .= '<td><span class="stage-badge ' . $cls . '">' . $h($item['stage']) . '</span></td>';
            $html .= '<td>' . $h($item['client']) . '</td>';
            $html .= '<td>' . ($item['account'] ? $h($item['account']) : '&mdash;') . '</td>';
            $html .= '<td>';
            foreach ($item['periods'] as $pl) {
                $html .= '<span class="period-tag">' . $h($pl) . '</span> ';
            }
            $html .= '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
    }

    $html .= '</div>';
    $html .= '<div class="footer">Work Progress System &bull; This is an automated report.</div>';
    $html .= '</div></body></html>';

    return $html;
}

/**
 * Check whether the logged-in client has completed onboarding.
 */
function isClientOnboardingComplete(): bool {
    if (currentRole() !== 'client') return true;
    $clientIds = array_map('intval', (array) ($_SESSION['client_ids'] ?? []));
    if (empty($clientIds)) return true;
    $db = getDB();
    $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
    $stmt = $db->prepare("SELECT client_id, status FROM client_onboarding WHERE client_id IN ($placeholders)");
    $stmt->execute($clientIds);
    $completed = [];
    while ($row = $stmt->fetch()) {
        if (in_array($row['status'], ['submitted', 'reviewed'], true)) {
            $completed[] = (int) $row['client_id'];
        }
    }
    // All entities must have submitted/reviewed onboarding
    foreach ($clientIds as $cid) {
        if (!in_array($cid, $completed, true)) return false;
    }
    return true;
}
