<?php
require_once BASE_PATH . '/models/User.php';

if ($action === 'logout') {
    session_destroy();
    redirect('?action=login');
}

if ($action === 'do_login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        setFlash('danger', 'Please enter email and password.');
        redirect('?action=login');
    }

    $user = User::findByEmail($email);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        setFlash('danger', 'Invalid credentials.');
        redirect('?action=login');
    }

    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'];

    logAction('login', $user['id']);
    redirect('?action=dashboard');
}

// Show login form
include BASE_PATH . '/views/login.php';
