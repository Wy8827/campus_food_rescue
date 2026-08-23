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
    header("Location: " . BASE_URL . "/pages/$role/dashboard.php");
    exit();
}

// Not logged in — show landing page
include __DIR__ . '/landing.php';
?>
