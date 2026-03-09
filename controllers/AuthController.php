<?php
require_once BASE_PATH . '/models/User.php';
require_once BASE_PATH . '/models/Client.php';

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

    // Check users table first
    $user = User::findByEmail($email);
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['user_type']  = 'user';
        logAction('login', $user['id']);
        redirect('?action=dashboard');
    }

    // Check clients table
    $client = Client::findByEmail($email);
    if ($client && $client['password_hash'] && password_verify($password, $client['password_hash'])) {
        $_SESSION['user_id']    = $client['id'];
        $_SESSION['user_name']  = $client['name'];
        $_SESSION['user_email'] = $client['email'];
        $_SESSION['user_role']  = 'client';
        $_SESSION['user_type']  = 'client';
        $_SESSION['client_id']  = $client['id'];
        logAction('login', null, null, null, null, $client['id']);
        redirect('?action=dashboard');
    }

    setFlash('danger', 'Invalid credentials.');
    redirect('?action=login');
}

// Show login form
include BASE_PATH . '/views/login.php';
