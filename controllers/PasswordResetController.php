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

    // Check users table first, then clients table
    $user = User::findByEmail($email);
    $client = Client::findByEmail($email);
    $account = null;
    $accountType = null;

    if ($user) {
        $account = $user;
        $accountType = 'user';
    } elseif ($client) {
        $account = $client;
        $accountType = 'client';
    }

    // Always show the same message to prevent email enumeration
    if ($account) {
        $db    = getDB();
        $token = bin2hex(random_bytes(32)); // 64-char hex token
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Delete any existing unused tokens for this email
        $db->prepare("DELETE FROM password_resets WHERE email=?")->execute([$email]);

        $db->prepare("INSERT INTO password_resets (email, token, account_type, expires_at) VALUES (?,?,?,?)")
           ->execute([$email, $token, $accountType, $expires]);

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $resetUrl  = $scheme . '://' . $host . appUrl('?action=reset_password&token=' . urlencode($token));
        $subject   = 'Password Reset - Work Progress System';
        $safeUrl   = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
        $safeName  = htmlspecialchars($account['name'], ENT_QUOTES, 'UTF-8');
        $innerHtml = "<p>Hello {$safeName},</p>"
                   . "<p>A password reset was requested for your account.</p>"
                   . "<p>Click the button below to reset your password (valid for 1 hour):</p>"
                   . "<p style='margin:20px 0;text-align:center;'><a href='{$safeUrl}' style='background-color:#0d6efd;color:#fff;padding:12px 28px;text-decoration:none;border-radius:5px;display:inline-block;font-weight:bold;'>Reset Password</a></p>"
                   . "<p style='font-size:13px;color:#666;'>Or copy and paste this link into your browser:<br><a href='{$safeUrl}'>{$safeUrl}</a></p>"
                   . "<p>If you did not request this, please ignore this email.</p>"
                   . "<p>Regards,<br>Work Progress System</p>";
        $body      = wrapEmailHtml($innerHtml);

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
                $mail->addAddress($email, $account['name']);
                $mail->isHTML(true);
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

    setFlash('success', 'If that email is registered, a reset link has been sent. Redirecting to login...');
    redirect('?action=forgot_password&sent=1');
}

// ---- SHOW RESET PASSWORD FORM ----
if ($action === 'reset_password') {
    $token = $_GET['token'] ?? '';
    $db    = getDB();
    $now   = date('Y-m-d H:i:s');
    $row   = $db->prepare("SELECT * FROM password_resets WHERE token=? AND used=0 AND expires_at > ?");
    $row->execute([$token, $now]);
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
    $now = date('Y-m-d H:i:s');
    $row = $db->prepare("SELECT * FROM password_resets WHERE token=? AND used=0 AND expires_at > ?");
    $row->execute([$token, $now]);
    $reset = $row->fetch();

    if (!$reset) {
        setFlash('danger', 'This reset link is invalid or has expired.');
        redirect('?action=forgot_password');
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    if (($reset['account_type'] ?? 'user') === 'client') {
        // Update ALL client records sharing this email
        $db->prepare("UPDATE clients SET password_hash=? WHERE email=?")->execute([$hash, $reset['email']]);
    } else {
        $db->prepare("UPDATE users SET password_hash=? WHERE email=?")->execute([$hash, $reset['email']]);
    }

    $db->prepare("UPDATE password_resets SET used=1 WHERE token=?")->execute([$token]);

    setFlash('success', 'Password reset successfully. Please log in.');
    redirect('?action=login');
}
