<?php
session_start();
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
requireRole('student');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/topbar.css">
    <link rel="stylesheet" href="../../assets/css/student/studentprofile.css">
    <title>Support | Campus Food Rescue</title>
</head>
<body>
<div class="dashboard-container">
    <?php include '../../includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="topbar-container"><?php include '../../includes/topbar.php'; ?></div>
        <div class="content-container">
            <h1 class="page-title">Support</h1>
            <p class="page-subtitle">Need help with a claim or pickup?</p>
            <div class="profile-card support-card">
                <h2>Campus Food Rescue Support</h2>
                <p>For a demo environment, contact the project administrator for help with food listings, claims, or account access.</p>
                <div class="support-contact"><strong>Support</strong><span>campusfoodrescue@example.com</span></div>
            </div>
        </div>
    </main>
</div>
</body>
</html>
