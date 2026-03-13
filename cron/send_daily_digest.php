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
require_once BASE_PATH . '/models/NotificationQueue.php';
require_once BASE_PATH . '/models/StageNote.php';

$rows = NotificationQueue::fetchUnsent();

if (empty($rows)) {
    echo '[' . date('Y-m-d H:i:s') . '] No pending notifications. Nothing to send.' . PHP_EOL;
    exit(0);
}

// Group rows by target_role
$byRole = [];
foreach ($rows as $row) {
    $byRole[$row['target_role']][] = $row;
}

require_once BASE_PATH . '/vendor/PHPMailer/src/Exception.php';
require_once BASE_PATH . '/vendor/PHPMailer/src/PHPMailer.php';
require_once BASE_PATH . '/vendor/PHPMailer/src/SMTP.php';

$date    = date('m/d/Y');
$allSent = [];

foreach ($byRole as $role => $roleRows) {
    $recipients = User::byRole($role);
    if (empty($recipients)) {
        echo '[' . date('Y-m-d H:i:s') . "] No recipients for role '{$role}'. Skipping." . PHP_EOL;
        continue;
    }

    $stageLabels = [
        'stage1' => 'Initial Upload',
        'stage2' => 'Processing Completed',
        'stage3' => 'Reclassification Upload',
        'stage4' => 'Reclassification Completed',
    ];

    // Build body — one section per upload row
    $lines = '';
    foreach ($roleRows as $r) {
        $fileNames  = json_decode($r['file_names'], true) ?: [];
        $stageLabel = $stageLabels[$r['stage']] ?? strtoupper($r['stage']);
        $lines .= "Client:   {$r['client_name']}\n";
        $lines .= "Period:   {$r['period_label']}\n";
        $lines .= "Stage:    {$stageLabel}\n";
        if ($r['account_name'] !== null) {
            $lines .= "Account:  {$r['account_name']}\n";
        }
        $lines .= "Uploaded by: {$r['uploaded_by']}\n";
        $lines .= "Files (" . count($fileNames) . "):\n";
        foreach ($fileNames as $fn) {
            $lines .= "  - {$fn}\n";
        }
        $lines .= "\n";
    }

    $subject = "Daily Upload Summary - {$date}";
    $body    = "Hello,\n\n"
             . "The following uploads were completed today ({$date}):\n\n"
             . $lines
             . "Please review the dashboard for any required workflow actions.\n\n";

    // Append stage notes summary (all non-empty notes, visible to all roles)
    $allNotes = StageNote::allNonEmpty();
    if (!empty($allNotes)) {
        $stageLabelsAll = [
            'stage1' => 'Stage 1 - Initial Upload',
            'stage2' => 'Stage 2 - Processing Completed',
            'stage3' => 'Stage 3 - Reclassification Upload',
            'stage4' => 'Stage 4 - Reclassification Completed',
        ];
        $body .= str_repeat('-', 40) . "\nSTAGE NOTES SUMMARY\n" . str_repeat('-', 40) . "\n";
        foreach ($allNotes as $n) {
            $sLabel = $stageLabelsAll[$n['stage_name']] ?? strtoupper($n['stage_name']);
            $body .= "Client:  {$n['client_name']}\n";
            $body .= "Period:  {$n['period_label']}\n";
            $body .= "Stage:   {$sLabel}\n";
            if (!empty($n['account_name'])) {
                $body .= "Account: {$n['account_name']}\n";
            }
            $body .= "Note:    {$n['note']}\n\n";
        }
    }

    $body .= "Regards,\nWork Progress System";

    $sentIds = [];
    foreach ($recipients as $recipient) {
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
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->send();
            echo '[' . date('Y-m-d H:i:s') . "] Sent digest to {$recipient['email']} ({$role}, " . count($roleRows) . " uploads)." . PHP_EOL;
        } catch (\Exception $e) {
            echo '[' . date('Y-m-d H:i:s') . "] FAILED sending to {$recipient['email']}: " . $e->getMessage() . PHP_EOL;
        }
    }

    // Mark all rows for this role as sent (regardless of per-recipient failures —
    // rows are tied to the role, not individual recipients)
    $sentIds = array_column($roleRows, 'id');
    NotificationQueue::markSent($sentIds);
    $allSent = array_merge($allSent, $sentIds);
}

echo '[' . date('Y-m-d H:i:s') . '] Done. Marked ' . count($allSent) . ' notification(s) as sent.' . PHP_EOL;
