<?php
// ============================================================
//  SESSION CONFIG — include at top of every protected page
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getRole() {
    return $_SESSION['role'] ?? null;
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getUserName() {
    return $_SESSION['user_name'] ?? 'User';
}

// Redirect to login if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/pages/auth/login.php');
        exit();
    }
}

// Redirect if role does not match
function requireRole($role) {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/pages/auth/login.php');
        exit();
    }
    if (getRole() !== $role) {
        header('Location: ' . BASE_URL . '/pages/auth/login.php?error=unauthorized');
        exit();
    }
}
?>
