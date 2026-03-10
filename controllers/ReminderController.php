<?php
require_once BASE_PATH . '/models/Period.php';
require_once BASE_PATH . '/models/Client.php';
require_once BASE_PATH . '/models/Stage1Status.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('?action=dashboard');
}

requireCsrf();

$periodId = (int) ($_POST['period_id'] ?? 0);
$period   = Period::find($periodId);

if (!$period) {
    setFlash('danger', 'Period not found.');
    redirect('?action=dashboard');
}

$client = Client::find($period['client_id']);

// Find all clients sharing this email and build one combined email
$allClients = Client::findAllByEmail($client['email']);

$sections = '';
foreach ($allClients as $sharedClient) {
    $clientPeriods = Period::byClient((int) $sharedClient['id']);
    $clientSection = '';
    foreach ($clientPeriods as $p) {
        if ($p['is_locked']) {
            continue;
        }
        $pending = Stage1Status::pendingAccounts((int) $p['id']);
        if (empty($pending)) {
            continue;
        }
        $clientSection .= "Period: {$p['period_label']}\n";
        foreach ($pending as $acc) {
            $clientSection .= "{$acc['account_name']}: Pending\n";
        }
        $clientSection .= "\n";
    }
    if ($clientSection !== '') {
        $sections .= "Client: {$sharedClient['name']}\n" . $clientSection;
    }
}

if ($sections === '') {
    setFlash('info', 'No pending bank statements — reminder not needed.');
    redirect('?action=dashboard');
}

$subject = 'Reminder: Pending bank statements - ' . date('m/d/Y');
$body    = "Dear Client,\n\n"
         . "This is a reminder regarding pending bank statements for:\n\n"
         . $sections
         . "Please send us the required files at your earliest convenience.\n\n"
         . "Regards,\nWork Progress System";

// Send via PHPMailer (Brevo SMTP)
$emailSent = false;
$mailerPath = BASE_PATH . '/vendor/PHPMailer/src/PHPMailer.php';
if (file_exists($mailerPath)) {
    require_once BASE_PATH . '/vendor/PHPMailer/src/Exception.php';
    require_once BASE_PATH . '/vendor/PHPMailer/src/PHPMailer.php';
    require_once BASE_PATH . '/vendor/PHPMailer/src/SMTP.php';

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
        $mail->addAddress($client['email']);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        $emailSent = true;
    } catch (\Exception $e) {
        logEmailFailure('reminder_send', $e->getMessage(), [
            'client_email' => $client['email'] ?? null,
            'period_id' => $periodId,
            'period_label' => $period['period_label'] ?? null,
        ]);
        $emailSent = false;
    }
} else {
    logEmailFailure('reminder_send', 'PHPMailer not found', [
        'client_email' => $client['email'] ?? null,
        'period_id' => $periodId,
        'mailer_path' => $mailerPath,
    ]);
}

// Log action regardless
logAction('reminder_sent', $_SESSION['user_id'], $periodId, null, null, [
    'client_email' => $client['email'],
    'email_sent'   => $emailSent,
]);

if ($emailSent) {
    setFlash('success', 'Reminder email sent to ' . e($client['email']));
} else {
    setFlash('warning', 'Reminder logged but email could not be sent. Check SMTP configuration.');
}

redirect('?action=dashboard');
