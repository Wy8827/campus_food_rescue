<?php
// ============================================================
//  ROOT ENTRY POINT
//  Redirects logged-in users to their dashboard
//  Shows landing page to guests
// ============================================================
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/constants.php';

if (isLoggedIn()) {
    $role = getRole();
    // Role name and folder name diverge for providers — this is the same
    // mapping already used correctly in login.php and includes/sidebar.php.
    // Passing $role straight into the path (e.g. "provider") 404s, since
    // the real folder is pages/food_provider/, not pages/provider/.
    $roleFolders = ['admin' => 'admin', 'provider' => 'food_provider', 'student' => 'student'];
    $folder = $roleFolders[$role] ?? 'student';
    header("Location: " . BASE_URL . "/pages/$folder/dashboard.php");
    exit();
}

// Not logged in — show landing page
include __DIR__ . '/landing.php';
?>