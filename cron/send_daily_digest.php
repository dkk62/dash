<?php
/**
 * Daily Upload Digest — cron script
 *
 * Sends one consolidated email per target role covering all uploads
 * queued since the last run.
 *
 * Usage (Windows Task Scheduler):
 *   php C:\xampp\htdocs\dash\cron\send_daily_digest.php
 *
 * Recommended schedule: once daily at end of business (e.g. 5:00 PM).
 */

define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('LOG_PATH',    BASE_PATH . '/logs');

require_once BASE_PATH . '/config/env.php';
loadEnvFile(BASE_PATH . '/.env');

define('APP_BASE_URL', envValue('APP_BASE_URL', '/dash'));

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/mail.php';
require_once BASE_PATH . '/helpers/functions.php';
require_once BASE_PATH . '/models/User.php';
require_once BASE_PATH . '/models/Client.php';
require_once BASE_PATH . '/models/NotificationQueue.php';
require_once BASE_PATH . '/models/StageNote.php';

$rows = NotificationQueue::fetchUnsent();

if (empty($rows)) {
    echo '[' . date('Y-m-d H:i:s') . '] No pending notifications. Nothing to send.' . PHP_EOL;
    exit(0);
}

require_once BASE_PATH . '/vendor/PHPMailer/src/Exception.php';
require_once BASE_PATH . '/vendor/PHPMailer/src/PHPMailer.php';
require_once BASE_PATH . '/vendor/PHPMailer/src/SMTP.php';

$date = date('m/d/Y');

$stageLabels = [
    'stage1' => 'Initial Upload',
    'stage2' => 'Processing Completed',
    'stage3' => 'Reclassification Upload',
    'stage4' => 'Reclassification Completed',
];

$stageLabelsAll = [
    'stage1' => 'Stage 1 - Initial Upload',
    'stage2' => 'Stage 2 - Processing Completed',
    'stage3' => 'Stage 3 - Reclassification Upload',
    'stage4' => 'Stage 4 - Reclassification Completed',
];

// Get all non-admin users (processor0 + processor1) and send each a personalized digest.
$allProcessors = User::byRoles(['processor0', 'processor1']);

$allSentIds = [];

foreach ($allProcessors as $recipient) {
    $assignedClientIds = Client::getClientIdsForUser((int) $recipient['id']);
    $userRows = NotificationQueue::fetchUnsentForClients($assignedClientIds, $recipient['role']);

    if (empty($userRows)) {
        echo '[' . date('Y-m-d H:i:s') . "] No relevant uploads for {$recipient['email']}. Skipping." . PHP_EOL;
        continue;
    }

    // Build body — HTML table of uploads
    $subject = "Daily Upload Summary - {$date}";
    $h = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

    $uploadTableRows = '';
    foreach ($userRows as $r) {
        $fileNames  = json_decode($r['file_names'], true) ?: [];
        $stageLabel = $stageLabels[$r['stage']] ?? strtoupper($r['stage']);
        $fileList = implode('<br>', array_map(fn($fn) => $h($fn), $fileNames));
        $uploadTableRows .= "<tr>"
            . "<td style='padding:8px 12px;border:1px solid #dee2e6;'>{$h($r['client_name'])}</td>"
            . "<td style='padding:8px 12px;border:1px solid #dee2e6;'>{$h($r['period_label'])}</td>"
            . "<td style='padding:8px 12px;border:1px solid #dee2e6;'>{$h($stageLabel)}</td>"
            . "<td style='padding:8px 12px;border:1px solid #dee2e6;'>" . ($r['account_name'] !== null ? $h($r['account_name']) : '—') . "</td>"
            . "<td style='padding:8px 12px;border:1px solid #dee2e6;'>{$h($r['uploaded_by'])}</td>"
            . "<td style='padding:8px 12px;border:1px solid #dee2e6;font-size:12px;'>{$fileList}</td>"
            . "</tr>";
    }

    $thStyle = "padding:10px 12px;border:1px solid #dee2e6;background-color:#212529;color:#ffffff;text-align:left;font-size:13px;";
    $uploadTable = "<table width='100%' cellpadding='0' cellspacing='0' style='border-collapse:collapse;font-size:13px;margin:15px 0;'>"
        . "<thead><tr>"
        . "<th style='{$thStyle}'>Client</th>"
        . "<th style='{$thStyle}'>Period</th>"
        . "<th style='{$thStyle}'>Stage</th>"
        . "<th style='{$thStyle}'>Account</th>"
        . "<th style='{$thStyle}'>Uploaded By</th>"
        . "<th style='{$thStyle}'>Files</th>"
        . "</tr></thead><tbody>{$uploadTableRows}</tbody></table>";

    $innerHtml = "<p>Hello {$h($recipient['name'])},</p>"
               . "<p>The following uploads were completed today ({$h($date)}) for your assigned clients:</p>"
               . $uploadTable
               . "<p>Please review the dashboard for any required workflow actions.</p>";

    // Append stage notes summary for notes belonging to the user's assigned clients
    // Cron runs at 00:10 AM, so look at previous day's notes
    $yesterdayStr = date('Y-m-d', strtotime('-1 day'));
    $allNotes = StageNote::allNonEmpty($yesterdayStr);
    $relevantNotes = array_filter($allNotes, fn($n) => in_array((int)($n['client_id'] ?? 0), $assignedClientIds, true));
    if (!empty($relevantNotes)) {
        $noteTableRows = '';
        foreach ($relevantNotes as $n) {
            $entries = StageNote::parseEntries($n['note']);
            // Show only previous day's entries in the digest
            $yesterdayEntries = array_filter($entries, fn($e) => str_starts_with($e['at'] ?? '', $yesterdayStr));
            if (empty($yesterdayEntries)) continue;

            $sLabel = $stageLabelsAll[$n['stage_name']] ?? strtoupper($n['stage_name']);
            $noteLines = implode('<br>', array_map(fn($e) => "<span style='color:#6c757d;'>[{$h($e['at'])}]</span> <strong>{$h($e['by'])}</strong>: {$h($e['msg'])}", $yesterdayEntries));
            $noteTableRows .= "<tr>"
                . "<td style='padding:8px 12px;border:1px solid #dee2e6;'>{$h($n['client_name'])}</td>"
                . "<td style='padding:8px 12px;border:1px solid #dee2e6;'>{$h($n['period_label'])}</td>"
                . "<td style='padding:8px 12px;border:1px solid #dee2e6;'>{$h($sLabel)}</td>"
                . "<td style='padding:8px 12px;border:1px solid #dee2e6;'>" . (!empty($n['account_name']) ? $h($n['account_name']) : '—') . "</td>"
                . "<td style='padding:8px 12px;border:1px solid #dee2e6;font-size:12px;'>{$noteLines}</td>"
                . "</tr>";
        }
        if ($noteTableRows !== '') {
            $nThStyle = "padding:10px 12px;border:1px solid #dee2e6;background-color:#212529;color:#ffffff;text-align:left;font-size:13px;";
            $noteTable = "<table width='100%' cellpadding='0' cellspacing='0' style='border-collapse:collapse;font-size:13px;margin:15px 0;'>"
                . "<thead><tr>"
                . "<th style='{$nThStyle}'>Client</th>"
                . "<th style='{$nThStyle}'>Period</th>"
                . "<th style='{$nThStyle}'>Stage</th>"
                . "<th style='{$nThStyle}'>Account</th>"
                . "<th style='{$nThStyle}'>Notes</th>"
                . "</tr></thead><tbody>{$noteTableRows}</tbody></table>";
            $innerHtml .= "<hr style='border:none;border-top:1px solid #dee2e6;margin:20px 0;'>"
                       . "<p><strong>STAGE NOTES SUMMARY</strong></p>"
                       . $noteTable;
        }
    }

    $innerHtml .= "<p>Regards,<br>Work Progress System</p>";
    $body = wrapEmailHtml($innerHtml);

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host     = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        if (SMTP_SECURE === 'ssl') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif (SMTP_SECURE === 'tls') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
        }
        $mail->Port    = SMTP_PORT;
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addReplyTo('info@taxcheapo.com', SMTP_FROM_NAME);
        $mail->addCC('info@taxcheapo.com', 'TaxCheapo');
        $mail->addAddress($recipient['email'], $recipient['name']);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->send();
        echo '[' . date('Y-m-d H:i:s') . "] Sent digest to {$recipient['email']} ({$recipient['role']}, " . count($userRows) . " upload(s))." . PHP_EOL;
        foreach ($userRows as $r) {
            $allSentIds[] = (int) $r['id'];
        }
    } catch (\Exception $e) {
        echo '[' . date('Y-m-d H:i:s') . "] FAILED sending to {$recipient['email']}: " . $e->getMessage() . PHP_EOL;
    }
}

// Mark all successfully covered rows as sent (deduplicated).
$allSentIds = array_unique($allSentIds);
if (!empty($allSentIds)) {
    NotificationQueue::markSent($allSentIds);
}

echo '[' . date('Y-m-d H:i:s') . '] Done. Marked ' . count($allSentIds) . ' notification(s) as sent.' . PHP_EOL;

// ============================================================================
// PENDING WORK REPORT FOR ADMIN
// ============================================================================
require_once BASE_PATH . '/models/Setting.php';
require_once BASE_PATH . '/models/Period.php';
require_once BASE_PATH . '/models/Account.php';
require_once BASE_PATH . '/models/Stage1Status.php';
require_once BASE_PATH . '/models/StageStatus.php';

$pendingReportEmail = Setting::get('pending_report_email', '');

if ($pendingReportEmail !== '' && $pendingReportEmail !== null && filter_var($pendingReportEmail, FILTER_VALIDATE_EMAIL)) {
    echo '[' . date('Y-m-d H:i:s') . '] Building pending work report for ' . $pendingReportEmail . '...' . PHP_EOL;

    $allPeriods = Period::allWithClient();

    usort($allPeriods, function (array $a, array $b): int {
        $aClient = $a['client_name'] ?? '';
        $bClient = $b['client_name'] ?? '';
        if ($aClient !== $bClient) return strcasecmp($aClient, $bClient);
        $aTs = periodLabelSortTimestamp((string)($a['period_label'] ?? ''), $a['created_at'] ?? null);
        $bTs = periodLabelSortTimestamp((string)($b['period_label'] ?? ''), $b['created_at'] ?? null);
        return $aTs <=> $bTs;
    });

    $allPeriods = array_values(array_filter($allPeriods, fn($p) => isPeriodBeforeCurrentPeriod($p['period_label'])));

    if (!empty($allPeriods)) {
        $allPeriodIds = array_map(fn($p) => (int)$p['id'], $allPeriods);
        $bulkS1     = Stage1Status::bulkByPeriods($allPeriodIds);
        $bulkStages = StageStatus::bulkByPeriods($allPeriodIds);

        $processorUsers = User::byRoles(['processor0', 'processor1']);
        $reportData = buildPendingReportData($processorUsers, $allPeriods, $bulkS1, $bulkStages);

        if (!empty($reportData)) {
            $reportDate = date('m/d/Y');
            $subject = "Pending Work Report - {$reportDate}";
            $htmlBody = buildPendingReportHtml($reportData, $reportDate);

            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host     = SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USER;
                $mail->Password = SMTP_PASS;
                if (SMTP_SECURE === 'ssl') {
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                } elseif (SMTP_SECURE === 'tls') {
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                } else {
                    $mail->SMTPSecure = '';
                }
                $mail->Port    = SMTP_PORT;
                $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                $mail->addAddress($pendingReportEmail);
                $mail->Subject = $subject;
                $mail->isHTML(true);
                $mail->Body    = $htmlBody;
                $mail->send();
                echo '[' . date('Y-m-d H:i:s') . "] Sent pending work report to {$pendingReportEmail}." . PHP_EOL;
            } catch (\Exception $e) {
                echo '[' . date('Y-m-d H:i:s') . "] FAILED sending pending work report to {$pendingReportEmail}: " . $e->getMessage() . PHP_EOL;
            }
        } else {
            echo '[' . date('Y-m-d H:i:s') . '] No pending work to report.' . PHP_EOL;
        }
    } else {
        echo '[' . date('Y-m-d H:i:s') . '] No past periods found for pending report.' . PHP_EOL;
    }
} else {
    echo '[' . date('Y-m-d H:i:s') . '] No pending report email configured. Skipping.' . PHP_EOL;
}
