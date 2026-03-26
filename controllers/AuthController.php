<?php
require_once BASE_PATH . '/models/User.php';
require_once BASE_PATH . '/models/Client.php';
require_once BASE_PATH . '/models/LoginAttempt.php';

function lockoutMessage(int $seconds): string {
    $minutes = (int) ceil($seconds / 60);
    if ($minutes < 1) {
        $minutes = 1;
    }
    return 'Too many login attempts. Please wait ' . $minutes . ' minute(s) before trying again.';
}

if ($action === 'logout') {
    session_destroy();
    redirect('?action=login');
}


if ($action === 'do_login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $honeypot = trim($_POST['website'] ?? '');
    $ipAddress = getClientIp();

    if ($honeypot !== '') {
        LoginAttempt::recordFailure($email !== '' ? $email : 'unknown', $ipAddress);
        setFlash('danger', 'Invalid credentials.');
        redirect('?action=login');
    }

    $attemptState = LoginAttempt::getState($email !== '' ? $email : 'unknown', $ipAddress);
    if ($attemptState['is_locked']) {
        setFlash('danger', lockoutMessage((int) $attemptState['remaining_seconds']));
        redirect('?action=login');
    }

    if ($email === '' || $password === '') {
        setFlash('danger', 'Please enter email and password.');
        redirect('?action=login');
    }

    // Check users table first
    $user = User::findByEmail($email);
    if ($user && password_verify($password, $user['password_hash'])) {
        LoginAttempt::clear($email, $ipAddress);
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['user_type']  = 'user';
        logAction('login', $user['id']);
        redirect($user['role'] === 'admin' ? '?action=pending' : '?action=dashboard');
    }

    // Check clients table – collect all matching clients for this email
    $matchedClients = [];
    foreach (Client::findAllByEmail($email) as $c) {
        if ($c['password_hash'] && password_verify($password, $c['password_hash'])) {
            $matchedClients[] = $c;
        }
    }

    if (count($matchedClients) >= 1) {
        $primary = $matchedClients[0];
        LoginAttempt::clear($email, $ipAddress);
        $_SESSION['user_id']    = $primary['id'];
        $_SESSION['user_name']  = $primary['name'];
        $_SESSION['user_email'] = $primary['email'];
        $_SESSION['user_role']  = 'client';
        $_SESSION['user_type']  = 'client';
        $_SESSION['client_id']  = $primary['id'];
        $_SESSION['client_ids'] = array_column($matchedClients, 'id');
        foreach ($matchedClients as $mc) {
            logAction('login', null, null, null, null, $mc['id']);
        }
        redirect('?action=dashboard');
    }

    $failureState = LoginAttempt::recordFailure($email !== '' ? $email : 'unknown', $ipAddress);

    if ($failureState['is_locked']) {
        setFlash('danger', lockoutMessage((int) $failureState['remaining_seconds']));
    } else {
        setFlash('danger', 'Invalid credentials.');
    }
    redirect('?action=login');
}

// Show login form
include BASE_PATH . '/views/login.php';
