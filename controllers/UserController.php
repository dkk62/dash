<?php
require_once BASE_PATH . '/models/User.php';

if ($action === 'user_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $id       = (int) ($_POST['id'] ?? 0);
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = $_POST['role'] ?? 'processor0';
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '') {
        setFlash('danger', 'Name and email are required.');
        redirect('?action=users');
    }

    if (!in_array($role, ['processor0', 'processor1', 'admin'])) {
        $role = 'processor0';
    }

    if ($id > 0) {
        // Edit existing user
        $db = getDB();
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET name=?, email=?, role=?, password_hash=? WHERE id=?");
            $stmt->execute([$name, $email, $role, $hash, $id]);
        } else {
            $stmt = $db->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?");
            $stmt->execute([$name, $email, $role, $id]);
        }
        setFlash('success', 'User updated.');
    } else {
        // Create new user — password required
        if ($password === '') {
            setFlash('danger', 'Password is required when creating a new user.');
            redirect('?action=users');
        }
        User::create($name, $email, $password, $role);
        setFlash('success', 'User created.');
    }
    redirect('?action=users');
}

if ($action === 'user_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    // Prevent deleting own account
    if ($id === (int) $_SESSION['user_id']) {
        setFlash('danger', 'You cannot delete your own account.');
        redirect('?action=users');
    }
    if ($id > 0) {
        $db = getDB();
        $db->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
        setFlash('success', 'User deleted.');
    }
    redirect('?action=users');
}

$users = User::all();
$editUser = null;
if ($action === 'user_edit' && isset($_GET['id'])) {
    $editUser = User::findById((int) $_GET['id']);
}

include BASE_PATH . '/views/users.php';
