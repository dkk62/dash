<?php
// Handles: forgot_password (form), do_forgot_password (submit), reset_password (form), do_reset_password (submit)

// ---- SHOW FORGOT PASSWORD FORM ----
if ($action === 'forgot_password') {
    include BASE_PATH . '/views/forgot_password.php';
    return;
}

// ---- PROCESS FORGOT PASSWORD SUBMISSION ----
if ($action === 'do_forgot_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        setFlash('danger', 'Please enter your email address.');
        redirect('?action=forgot_password');
    }

    $user = User::findByEmail($email);

    // Always show the same message to prevent email enumeration
    if ($user) {
        $db    = getDB();
        $token = bin2hex(random_bytes(32)); // 64-char hex token
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Delete any existing unused tokens for this email
        $db->prepare("DELETE FROM password_resets WHERE email=?")->execute([$email]);

        $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?,?,?)")
           ->execute([$email, $token, $expires]);

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $resetUrl  = $scheme . '://' . $host . appUrl('?action=reset_password&token=' . urlencode($token));
        $subject   = 'Password Reset - Work Progress System';
        $body      = "Hello {$user['name']},\n\n"
                   . "A password reset was requested for your account.\n\n"
                   . "Click the link below (valid for 1 hour):\n"
                   . $resetUrl . "\n\n"
                   . "If you did not request this, please ignore this email.\n\n"
                   . "Regards,\nWork Progress System";

        // Send via PHPMailer
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
                $mail->addReplyTo('info@taxcheapo.com', SMTP_FROM_NAME);
                $mail->addAddress($email, $user['name']);
                $mail->Subject = $subject;
                $mail->Body    = $body;
                $mail->send();
            } catch (\Exception $e) {
                logEmailFailure('password_reset_send', $e->getMessage(), [
                    'email' => $email,
                    'host' => $host,
                ]);
            }
        } else {
            logEmailFailure('password_reset_send', 'PHPMailer not found', [
                'email' => $email,
                'mailer_path' => $mailerPath,
            ]);
        }
    }

    setFlash('success', 'If that email is registered, a reset link has been sent.');
    redirect('?action=forgot_password');
}

// ---- SHOW RESET PASSWORD FORM ----
if ($action === 'reset_password') {
    $token = $_GET['token'] ?? '';
    $db    = getDB();
    $row   = $db->prepare("SELECT * FROM password_resets WHERE token=? AND used=0 AND expires_at > NOW()");
    $row->execute([$token]);
    $reset = $row->fetch();

    if (!$reset) {
        setFlash('danger', 'This reset link is invalid or has expired.');
        redirect('?action=forgot_password');
    }

    include BASE_PATH . '/views/reset_password.php';
    return;
}

// ---- PROCESS RESET PASSWORD SUBMISSION ----
if ($action === 'do_reset_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $token    = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        setFlash('danger', 'Password must be at least 8 characters.');
        redirect('?action=reset_password&token=' . urlencode($token));
    }

    if ($password !== $confirm) {
        setFlash('danger', 'Passwords do not match.');
        redirect('?action=reset_password&token=' . urlencode($token));
    }

    $db  = getDB();
    $row = $db->prepare("SELECT * FROM password_resets WHERE token=? AND used=0 AND expires_at > NOW()");
    $row->execute([$token]);
    $reset = $row->fetch();

    if (!$reset) {
        setFlash('danger', 'This reset link is invalid or has expired.');
        redirect('?action=forgot_password');
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $db->prepare("UPDATE users SET password_hash=? WHERE email=?")->execute([$hash, $reset['email']]);
    $db->prepare("UPDATE password_resets SET used=1 WHERE token=?")->execute([$token]);

    setFlash('success', 'Password reset successfully. Please log in.');
    redirect('?action=login');
}
