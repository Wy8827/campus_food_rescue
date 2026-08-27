<?php
session_start();
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
requireRole('student');

$studentId = (int) ($_SESSION['user_id'] ?? 0);
$userStmt = mysqli_prepare($conn, "SELECT user_id, user_name, email, created_at, no_show_count, account_status FROM user WHERE user_id = ? AND role = 'student' LIMIT 1");
mysqli_stmt_bind_param($userStmt, 'i', $studentId);
mysqli_stmt_execute($userStmt);
$userResult = mysqli_stmt_get_result($userStmt);
$user = mysqli_fetch_assoc($userResult);

if (!$user) {
    die('Student account could not be found.');
}

$impactSql = "
    SELECT
        COALESCE(SUM(c.portion_claimed), 0) AS meals_rescued,
        COALESCE(SUM(ir.co2_saved_kg), 0) AS co2_saved,
        COALESCE(SUM(ir.water_saved_litre), 0) AS water_saved
    FROM impact_record ir
    INNER JOIN claim c ON c.claim_id = ir.claim_id
    WHERE c.student_id = ?
      AND c.status = 'completed'
";
$impactStmt = mysqli_prepare($conn, $impactSql);
mysqli_stmt_bind_param($impactStmt, 'i', $studentId);
mysqli_stmt_execute($impactStmt);
$impact = mysqli_fetch_assoc(mysqli_stmt_get_result($impactStmt));

$claimCountStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total_claims FROM claim WHERE student_id = ?");
mysqli_stmt_bind_param($claimCountStmt, 'i', $studentId);
mysqli_stmt_execute($claimCountStmt);
$totalClaims = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($claimCountStmt))['total_claims'];

$topLocationStmt = mysqli_prepare($conn, "
    SELECT fl.pickup_location, COUNT(*) AS claim_count
    FROM claim c
    INNER JOIN food_listing fl ON fl.listing_id = c.listing_id
    WHERE c.student_id = ?
    GROUP BY fl.pickup_location
    ORDER BY claim_count DESC
    LIMIT 1
");
mysqli_stmt_bind_param($topLocationStmt, 'i', $studentId);
mysqli_stmt_execute($topLocationStmt);
$topLocation = mysqli_fetch_assoc(mysqli_stmt_get_result($topLocationStmt));

$meals = (int) ($impact['meals_rescued'] ?? 0);
$co2 = (float) ($impact['co2_saved'] ?? 0);
$water = (float) ($impact['water_saved'] ?? 0);

$achievements = [
    ['title' => '1st Save', 'description' => 'Made your first claim', 'earned' => $totalClaims >= 1, 'icon' => '★'],
    ['title' => 'Food Saver', 'description' => '5 meals rescued', 'earned' => $meals >= 5, 'icon' => '♻'],
    ['title' => 'Zero Waste Hero', 'description' => '10 meals rescued', 'earned' => $meals >= 10, 'icon' => '✓'],
];
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
    <title>My Profile | Campus Food Rescue</title>
</head>
<body>
<div class="dashboard-container">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="topbar-container">
            <?php include '../../includes/topbar.php'; ?>
        </div>

        <div class="content-container profile-page">
            <h1 class="page-title">Settings | My Profile</h1>
            <p class="page-subtitle">Manage your profile, preferences, track your impact, and customise your rescue experience.</p>

            <section class="profile-layout">
                <div class="profile-main">
                    <div class="profile-card identity-card">
                        <div class="profile-avatar">
                            <img src="../../assets/images/user.png" alt="Profile picture">
                        </div>
                        <div class="identity-copy">
                            <h2><?= htmlspecialchars($user['user_name']) ?></h2>
                            <p><?= htmlspecialchars($user['email']) ?></p>
                            <span class="profile-level">Campus Food Rescuer</span>
                        </div>
                        <a href="editprofile.php" class="outline-button">Edit Profile</a>
                    </div>

                    <div class="impact-stat-grid">
                        <div class="impact-stat-card"><span>Meals Saved</span><strong><?= $meals ?></strong><small>Completed rescues</small></div>
                        <div class="impact-stat-card"><span>CO₂ Avoided</span><strong><?= number_format($co2, 1) ?> kg</strong><small>Audited impact</small></div>
                        <div class="impact-stat-card"><span>Water Saved</span><strong><?= number_format($water) ?> L</strong><small>Audited impact</small></div>
                    </div>
                </div>

                <aside class="profile-side">
                    <div class="profile-card achievement-card">
                        <div class="section-heading"><h2>Achievements</h2></div>
                        <div class="achievement-list">
                            <?php foreach ($achievements as $achievement): ?>
                                <div class="achievement <?= $achievement['earned'] ? 'earned' : '' ?>">
                                    <span class="achievement-icon"><?= $achievement['icon'] ?></span>
                                    <div><strong><?= htmlspecialchars($achievement['title']) ?></strong><small><?= htmlspecialchars($achievement['description']) ?></small></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </aside>
            </section>
        </div>
    </main>
</div>
</body>
</html>
