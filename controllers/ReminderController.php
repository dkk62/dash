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
$pending = Stage1Status::pendingAccounts($periodId);

if (empty($pending)) {
    setFlash('info', 'No pending accounts — reminder not needed.');
    redirect('?action=dashboard');
}

// Build email
$accountList = '';
foreach ($pending as $p) {
    $statusLabel = $p['status'] === 'grey' ? 'Not uploaded' : 'Downloaded (awaiting re-upload)';
    $accountList .= "  - {$p['account_name']}: {$statusLabel}\n";
}

$subject = "Reminder: Pending uploads for {$client['name']} - {$period['period_label']}";
$body    = "Dear Client,\n\n"
         . "This is a reminder regarding pending uploads for:\n"
         . "Client: {$client['name']}\n"
         . "Period: {$period['period_label']}\n\n"
         . "The following accounts require action:\n"
         . $accountList . "\n"
         . "Please upload the required files at your earliest convenience.\n\n"
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
        $mail->addAddress($client['email'], $client['name']);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        $emailSent = true;
    } catch (\Exception $e) {
        // Log error but don't block
        $emailSent = false;
    }
}

// Log action regardless
logAction('reminder_sent', $_SESSION['user_id'], $periodId, null, null, [
    'client_email' => $client['email'],
    'pending_accounts' => array_column($pending, 'account_name'),
    'email_sent' => $emailSent,
]);

if ($emailSent) {
    setFlash('success', 'Reminder email sent to ' . e($client['email']));
} else {
    setFlash('warning', 'Reminder logged but email could not be sent. Check SMTP configuration.');
}

redirect('?action=dashboard');
