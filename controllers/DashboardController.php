<?php
require_once BASE_PATH . '/models/Period.php';
require_once BASE_PATH . '/models/Client.php';
require_once BASE_PATH . '/models/Account.php';
require_once BASE_PATH . '/models/Stage1Status.php';
require_once BASE_PATH . '/models/StageStatus.php';
require_once BASE_PATH . '/models/FileRecord.php';
require_once BASE_PATH . '/models/Log.php';
require_once BASE_PATH . '/models/StageNote.php';

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
    // Stage1 status can be orange by default for automatic bank feeds.
    // Treat actual stage1 files as activity instead of raw status for visibility filtering.
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

function xlsxColName(int $index): string {
    $name = '';
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $name = chr(65 + $mod) . $name;
        $index = intdiv($index - 1, 26);
    }
    return $name;
}

function xlsxEsc(string $value): string {
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function dashboardLedStyle(string $status): int {
    return match ($status) {
        'red' => 3,
        'orange' => 4,
        'green' => 5,
        default => 6,
    };
}

function buildDashboardExportRows(array $dashboardData): array {
    $rows = [[
        'CLIENT',
        'PERIOD',
        'ACCOUNT',
        'STAGE 1',
        'STAGE 2',
        'STAGE 3',
        'STAGE 4',
        'LOCKED',
    ]];

    foreach ($dashboardData as $data) {
        $period = $data['period'];
        $locked = (bool) $period['is_locked'];
        $s1rows = $data['s1statuses'];
        $stages = $data['stageStatuses'];
        $groupRows = max(1, count($s1rows));

        for ($idx = 0; $idx < $groupRows; $idx++) {
            $s1 = $s1rows[$idx] ?? null;
            $accountName = '-';
            $stage1Status = 'grey';
            if ($s1) {
                $accountName = $s1['account_name'] . (((($s1['bank_feed_mode'] ?? 'manual') === 'automatic') ? ' (auto)' : ''));
                $stage1Status = (string) ($s1['status'] ?? 'grey');
            }

            $rows[] = [
                (string) $period['client_name'],
                (string) $period['period_label'] . ($locked ? ' Locked' : ''),
                (string) $accountName,
                ['type' => 'led', 'status' => $stage1Status],
                ['type' => 'led', 'status' => (string) (($stages['stage2']['status'] ?? 'grey'))],
                ['type' => 'led', 'status' => (string) (($stages['stage3']['status'] ?? 'grey'))],
                ['type' => 'led', 'status' => (string) (($stages['stage4']['status'] ?? 'grey'))],
                $locked ? '🔒' : '',
            ];
        }
    }

    return $rows;
}

function exportDashboardXlsx(array $dashboardData): void {
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        echo 'XLSX export is unavailable because the PHP Zip extension is not enabled.';
        exit;
    }

    $rows = buildDashboardExportRows($dashboardData);
    $maxCol = 8;
    $maxRow = count($rows);
    $dimension = 'A1:' . xlsxColName($maxCol) . $maxRow;

    $sheetRowsXml = '';
    foreach ($rows as $rowIndex => $row) {
        $r = $rowIndex + 1;
        $rowAttrs = ($rowIndex === 0) ? ' ht="22" customHeight="1"' : '';
        $sheetRowsXml .= '<row r="' . $r . '"' . $rowAttrs . '>';

        foreach ($row as $colIndex => $cell) {
            $cName = xlsxColName($colIndex + 1) . $r;
            $style = 0;
            $value = '';

            if ($rowIndex === 0) {
                $style = 1;
                $value = (string) $cell;
            } elseif (is_array($cell) && ($cell['type'] ?? '') === 'led') {
                $style = dashboardLedStyle((string) ($cell['status'] ?? 'grey'));
                $value = '●';
            } else {
                $value = (string) $cell;
            }

            $sheetRowsXml .= '<c r="' . $cName . '" t="inlineStr" s="' . $style . '"><is><t>' . xlsxEsc($value) . '</t></is></c>';
        }

        $sheetRowsXml .= '</row>';
    }

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<dimension ref="' . $dimension . '"/>'
        . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="18" customHeight="1"/>'
        . '<cols>'
        . '<col min="1" max="1" width="22" customWidth="1"/>'
        . '<col min="2" max="2" width="16" customWidth="1"/>'
        . '<col min="3" max="3" width="26" customWidth="1"/>'
        . '<col min="4" max="8" width="12" customWidth="1"/>'
        . '</cols>'
        . '<sheetData>' . $sheetRowsXml . '</sheetData>'
        . '</worksheet>';

    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="6">'
        . '<font><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><color rgb="FF000000"/><name val="Calibri"/></font>'
        . '<font><sz val="20"/><color rgb="FFC62828"/><name val="Calibri"/></font>'
        . '<font><sz val="20"/><color rgb="FFEF6C00"/><name val="Calibri"/></font>'
        . '<font><sz val="20"/><color rgb="FF2E7D32"/><name val="Calibri"/></font>'
        . '<font><sz val="20"/><color rgb="FF9E9E9E"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="2">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFFFFFFF"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thick"><color rgb="FF000000"/></left><right style="thick"><color rgb="FF000000"/></right><top style="thick"><color rgb="FF000000"/></top><bottom style="thick"><color rgb="FF000000"/></bottom><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="7">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="1" fillId="1" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="5" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '</cellXfs>'
        . '</styleSheet>';

    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Dashboard" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';

    $workbookRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    $relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';

    $tmpFile = tempnam(sys_get_temp_dir(), 'dash_xlsx_');
    if ($tmpFile === false) {
        http_response_code(500);
        echo 'Unable to create temporary file for export.';
        exit;
    }

    $zip = new ZipArchive();
    if ($zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) {
        @unlink($tmpFile);
        http_response_code(500);
        echo 'Unable to create XLSX export file.';
        exit;
    }

    $zip->addFromString('[Content_Types].xml', $contentTypesXml);
    $zip->addFromString('_rels/.rels', $relsXml);
    $zip->addFromString('xl/workbook.xml', $workbookXml);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->addFromString('xl/styles.xml', $stylesXml);
    $zip->close();

    $fileName = 'work_progress_dashboard_' . date('Ymd_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($tmpFile));
    header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');

    readfile($tmpFile);
    @unlink($tmpFile);
    exit;
}

// Load all periods with client info
$periods = Period::allWithClient();
sortPeriodsChronologically($periods);

// Filter periods if user is a client
if (currentRole() === 'client') {
    $clientIds = !empty($_SESSION['client_ids'])
        ? array_map('intval', (array) $_SESSION['client_ids'])
        : (!empty($_SESSION['client_id']) ? [(int) $_SESSION['client_id']] : []);
    if (!empty($clientIds)) {
        $periods = array_values(array_filter($periods, function($p) use ($clientIds) {
            return in_array((int) $p['client_id'], $clientIds, true);
        }));
    }
}

// Bulk-load all status and file data in a few queries instead of per-period lookups.
$allPeriodIds = array_map(fn($p) => (int)$p['id'], $periods);
$bulkS1     = Stage1Status::bulkByPeriods($allPeriodIds);
$bulkStages = StageStatus::bulkByPeriods($allPeriodIds);
$bulkFiles  = FileRecord::bulkHasFiles($allPeriodIds);
$bulkNotes  = StageNote::bulkByPeriods($allPeriodIds);

// Build full dashboard data using pre-loaded bulk data (no additional queries).
$allDashboardData = [];

foreach ($periods as $period) {
    $pid = (int)$period['id'];

    $s1statuses    = $bulkS1[$pid]     ?? [];
    $stageStatuses = $bulkStages[$pid] ?? [];

    $s1Files = [];
    foreach ($s1statuses as $s1) {
        $aid = (int)$s1['account_id'];
        $s1Files[$aid] = isset($bulkFiles[$pid]['stage1'][$aid]);
    }

    $hasStage2File = isset($bulkFiles[$pid]['stage2']);
    $hasStage3File = isset($bulkFiles[$pid]['stage3']);
    $hasStage4File = isset($bulkFiles[$pid]['stage4']);

    // Hide future empty rows from dashboard, but always show active rows.
    $hasActivity   = periodHasActivity($s1statuses, $stageStatuses, $s1Files, $hasStage2File, $hasStage3File, $hasStage4File);
    $visibleByDate = isPeriodVisibleByDate($period['period_label']);
    if (!$hasActivity && !$visibleByDate) {
        continue;
    }

    // Derive reminder / lock flags from in-memory data (no extra queries).
    // Only grey (silver) accounts trigger a reminder — orange (auto/downloaded) and green are excluded.
    $showReminder = false;
    foreach ($s1statuses as $s1) {
        if ($s1['status'] === 'grey') {
            $showReminder = true;
            break;
        }
    }

    $s1AllOrange   = array_reduce($s1statuses,    fn($c, $s) => $c && $s['status'] === 'orange', true);
    $s234AllOrange = array_reduce($stageStatuses, fn($c, $s) => $c && $s['status'] === 'orange', true);
    $showLock      = $s1AllOrange && $s234AllOrange && !$period['is_locked'];

    $allDashboardData[] = [
        'period'        => $period,
        's1statuses'    => $s1statuses,
        'stageStatuses' => $stageStatuses,
        'showReminder'  => $showReminder,
        'showLock'      => $showLock,
        's1Files'       => $s1Files,
        'hasStage2File' => $hasStage2File,
        'hasStage3File' => $hasStage3File,
        'hasStage4File' => $hasStage4File,
        'notes'         => $bulkNotes[$pid] ?? [],
    ];
}

// XLSX export uses all dashboard data (no pagination).
if (($action ?? '') === 'dashboard_export') {
    exportDashboardXlsx($allDashboardData);
}

// Paginate by client — 10 clients per page.
$perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));

// Extract unique client IDs in display order.
$orderedClientIds = [];
$seenClientIds    = [];
foreach ($allDashboardData as $data) {
    $cid = (int)$data['period']['client_id'];
    if (!isset($seenClientIds[$cid])) {
        $seenClientIds[$cid] = true;
        $orderedClientIds[]  = $cid;
    }
}

$totalClients = count($orderedClientIds);
$totalPages   = max(1, (int)ceil($totalClients / $perPage));
$page         = min($page, $totalPages);

$pageClientSet = array_flip(array_slice($orderedClientIds, ($page - 1) * $perPage, $perPage));
$dashboardData = array_values(array_filter($allDashboardData, fn($d) => isset($pageClientSet[(int)$d['period']['client_id']])));

// --- Reminder data (admin only) ---
// Last reminder sent date for every client (across all pages).
$lastReminderByClientId = [];
if (hasRole(['admin'])) {
    $lastReminderByClientId = LogModel::lastReminderByClients($orderedClientIds);

    // Build list of unique email targets that have pending reminders, across ALL periods/pages.
    $reminderTargets    = [];  // [['name'=>..., 'email'=>...], ...]
    $reminderEmailsSeen = [];
    foreach ($allDashboardData as $data) {
        if (!$data['showReminder'] || $data['period']['is_locked']) {
            continue;
        }
        $email = $data['period']['client_email'] ?? '';
        if ($email === '' || isset($reminderEmailsSeen[$email])) {
            continue;
        }
        $reminderEmailsSeen[$email] = true;
        $cid = (int)$data['period']['client_id'];
        $reminderTargets[] = [
            'name'          => $data['period']['client_name'],
            'email'         => $email,
            'last_reminder' => $lastReminderByClientId[$cid] ?? null,
        ];
    }
}

include BASE_PATH . '/views/dashboard.php';
