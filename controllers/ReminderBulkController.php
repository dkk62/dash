<?php
require_once BASE_PATH . '/models/Period.php';
require_once BASE_PATH . '/models/Client.php';
require_once BASE_PATH . '/models/Stage1Status.php';
require_once BASE_PATH . '/models/Log.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('?action=dashboard');
}

requireCsrf();

// Load all periods and derive which unique client emails need reminders.
$periods = Period::allWithClient();

// Collect unique emails with pending (non-locked) reminders.
$emailTargets = [];  // email => ['client_ids' => [...], 'primary_client_id' => int, 'name' => string]

foreach ($periods as $period) {
    if ($period['is_locked']) {
        continue;
    }
    // Only remind for periods strictly before the current month.
    if (!isPeriodBeforeCurrentPeriod($period['period_label'])) {
        continue;
    }
    $pid       = (int)$period['id'];
    $email     = $period['client_email'] ?? '';
    $clientId  = (int)$period['client_id'];

    if ($email === '') {
        continue;
    }

    $s1statuses = Stage1Status::byPeriod($pid);
    $hasPending = false;
    foreach ($s1statuses as $s1) {
        if ($s1['status'] === 'grey') {
            $hasPending = true;
            break;
        }
    }

    if (!$hasPending) {
        continue;
    }

    if (!isset($emailTargets[$email])) {
        $emailTargets[$email] = [
            'name'              => $period['client_name'],
            'primary_client_id' => $clientId,
        ];
    }
}

if (empty($emailTargets)) {
    setFlash('info', 'No pending bank statements — no reminders needed.');
    redirect('?action=dashboard');
}

// Filter to only the emails the admin explicitly selected in the modal.
$selectedEmails = $_POST['selected_emails'] ?? [];
if (!empty($selectedEmails)) {
    // Whitelist: only keep emails that are valid pending targets.
    $selectedEmails = array_filter(
        array_map('strval', (array)$selectedEmails),
        fn($e) => isset($emailTargets[$e])
    );
    $emailTargets = array_intersect_key($emailTargets, array_flip($selectedEmails));
}

if (empty($emailTargets)) {
    setFlash('info', 'No clients selected — no reminders sent.');
    redirect('?action=dashboard');
}

// Load PHPMailer once.
$mailerAvailable = false;
$mailerPath = BASE_PATH . '/vendor/PHPMailer/src/PHPMailer.php';
if (file_exists($mailerPath)) {
    require_once BASE_PATH . '/vendor/PHPMailer/src/Exception.php';
    require_once BASE_PATH . '/vendor/PHPMailer/src/PHPMailer.php';
    require_once BASE_PATH . '/vendor/PHPMailer/src/SMTP.php';
    $mailerAvailable = true;
}

$sentCount  = 0;
$failCount  = 0;

foreach ($emailTargets as $email => $target) {
    // Build combined email body: one email covers all clients sharing this address.
    $allClients = Client::findAllByEmail($email);

    $sections = '';
    foreach ($allClients as $sharedClient) {
        $clientPeriods = Period::byClient((int)$sharedClient['id']);
        // Sort oldest-first so the email reads chronologically.
        usort($clientPeriods, function (array $a, array $b): int {
            $ta = periodLabelSortTimestamp($a['period_label'], $a['created_at'] ?? null);
            $tb = periodLabelSortTimestamp($b['period_label'], $b['created_at'] ?? null);
            return $ta <=> $tb;
        });
        // Build account => [period_labels] map for past pending periods.
        $accountPeriods = [];
        foreach ($clientPeriods as $p) {
            if ($p['is_locked']) {
                continue;
            }
            if (!isPeriodBeforeCurrentPeriod($p['period_label'])) {
                continue;
            }
            $pending = Stage1Status::pendingAccounts((int)$p['id']);
            foreach ($pending as $acc) {
                $accountPeriods[$acc['account_name']][] = $p['period_label'];
            }
        }
        if (empty($accountPeriods)) {
            continue;
        }
        $clientSection = '';
        foreach ($accountPeriods as $accountName => $periodLabels) {
            $clientSection .= "Account: {$accountName}: Pending\n";
            foreach ($periodLabels as $label) {
                $clientSection .= "Period: {$label}\n";
            }
            $clientSection .= "\n";
        }
        $sections .= "Client: {$sharedClient['name']}\n" . $clientSection;
    }

    if ($sections === '') {
        continue;
    }

    $subject = 'Reminder: Pending bank statements - ' . date('m/d/Y');
    $body    = "Dear Client,\n\n"
             . "This is a reminder regarding pending bank statements for:\n\n"
             . $sections
             . "Please send us the required files at your earliest convenience.\n\n"
             . "Regards,\nTaxCheapo Bookkeeping Work Progress System";

    $emailSent = false;

    if ($mailerAvailable) {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host     = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->Timeout  = 30;
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
            $mail->addAddress($email);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->send();
            $emailSent = true;
        } catch (\Throwable $e) {
            logEmailFailure('reminder_bulk', $e->getMessage(), [
                'client_email' => $email,
                'client_name'  => $target['name'],
            ]);
        }
    } else {
        logEmailFailure('reminder_bulk', 'PHPMailer not found', [
            'client_email' => $email,
        ]);
    }

    if ($emailSent) {
        $sentCount++;
    } else {
        $failCount++;
    }

    // Log one record per client sharing this email so every client's last-reminder date is updated.
    foreach ($allClients as $sharedClient) {
        try {
            logAction(
                'reminder_sent',
                $_SESSION['user_id'],
                null,
                null,
                null,
                (int)$sharedClient['id'],
                [
                    'client_email' => $email,
                    'email_sent'   => $emailSent,
                ]
            );
        } catch (\Throwable $logEx) {
            logEmailFailure('reminder_bulk_log', $logEx->getMessage(), [
                'client_email' => $email,
                'client_name'  => $sharedClient['name'],
            ]);
        }
    }
}

if ($sentCount > 0 && $failCount === 0) {
    setFlash('success', "Reminder emails sent to {$sentCount} client(s).");
} elseif ($sentCount > 0) {
    setFlash('warning', "Sent {$sentCount} reminder(s); {$failCount} could not be confirmed. Check SMTP logs.");
} elseif ($failCount > 0) {
    setFlash('danger', 'Reminder email delivery could not be confirmed. Check SMTP configuration and email_failures.log.');
} else {
    setFlash('info', 'No pending bank statements found for selected clients.');
}

redirect('?action=dashboard');
