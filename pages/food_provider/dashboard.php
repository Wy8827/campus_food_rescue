<?php
session_start();
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/_provider_helpers.php';

requireRole('provider');

$userId = (int)($_SESSION['user_id'] ?? 0);
$providerId = getProviderId($conn, $userId);

if (!$providerId) {
    die("No provider profile is linked to this account yet. Please contact support.");
}

// Monthly food-rescue goal used for the progress bar (kg). A real
// deployment might store this per-provider; kept as a constant here
// to keep the demo simple and explainable.
define('MONTHLY_GOAL_KG', 60);

// ---------------------------------------------------------
// 1. ACTIVE LISTINGS
// ---------------------------------------------------------
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM food_listing WHERE provider_id = ? AND status = 'active'");
mysqli_stmt_bind_param($stmt, "i", $providerId);
mysqli_stmt_execute($stmt);
$activeListings = mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];
mysqli_stmt_close($stmt);

// ---------------------------------------------------------
// 2. PENDING PICKUPS (+ how many are about to expire in <15 mins)
// ---------------------------------------------------------
$stmt = mysqli_prepare($conn, "
    SELECT COUNT(*) AS total,
           SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, NOW(), c.reservation_expires_at) <= 15 THEN 1 ELSE 0 END) AS critical
    FROM claim c
    JOIN food_listing f ON c.listing_id = f.listing_id
    WHERE f.provider_id = ? AND c.status IN ('pending','confirmed')
");
mysqli_stmt_bind_param($stmt, "i", $providerId);
mysqli_stmt_execute($stmt);
$pendingRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
$pendingPickups = (int)($pendingRow['total'] ?? 0);
$criticalPickups = (int)($pendingRow['critical'] ?? 0);

// ---------------------------------------------------------
// 3. COMPLETED TODAY (+ % change vs yesterday)
// ---------------------------------------------------------
$stmt = mysqli_prepare($conn, "
    SELECT COUNT(*) FROM claim c
    JOIN food_listing f ON c.listing_id = f.listing_id
    WHERE f.provider_id = ? AND c.status = 'completed' AND DATE(c.confirmed_at) = CURDATE()
");
mysqli_stmt_bind_param($stmt, "i", $providerId);
mysqli_stmt_execute($stmt);
$completedToday = (int)mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "
    SELECT COUNT(*) FROM claim c
    JOIN food_listing f ON c.listing_id = f.listing_id
    WHERE f.provider_id = ? AND c.status = 'completed' AND DATE(c.confirmed_at) = CURDATE() - INTERVAL 1 DAY
");
mysqli_stmt_bind_param($stmt, "i", $providerId);
mysqli_stmt_execute($stmt);
$completedYesterday = (int)mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];
mysqli_stmt_close($stmt);

if ($completedYesterday > 0) {
    $pctChange = round((($completedToday - $completedYesterday) / $completedYesterday) * 100);
} else {
    $pctChange = $completedToday > 0 ? 100 : 0;
}

// ---------------------------------------------------------
// 4. TOTAL FOOD SAVED (all-time, kg) + CO2 offset
// ---------------------------------------------------------
$stmt = mysqli_prepare($conn, "
    SELECT COALESCE(SUM((c.portion_claimed / f.total_quantity) * f.weight_kg), 0) AS weight_saved
    FROM claim c
    JOIN food_listing f ON c.listing_id = f.listing_id
    WHERE f.provider_id = ? AND c.status = 'completed'
");
mysqli_stmt_bind_param($stmt, "i", $providerId);
mysqli_stmt_execute($stmt);
$totalFoodSaved = round(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['weight_saved'], 1);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "
    SELECT COALESCE(SUM(ir.co2_saved_kg), 0) AS co2
    FROM impact_record ir
    JOIN claim c ON ir.claim_id = c.claim_id
    JOIN food_listing f ON c.listing_id = f.listing_id
    WHERE f.provider_id = ?
");
mysqli_stmt_bind_param($stmt, "i", $providerId);
mysqli_stmt_execute($stmt);
$totalCo2 = round(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['co2'], 1);
mysqli_stmt_close($stmt);

// ---------------------------------------------------------
// 5. MONTHLY IMPACT (this calendar month)
// ---------------------------------------------------------
$stmt = mysqli_prepare($conn, "
    SELECT COALESCE(SUM((c.portion_claimed / f.total_quantity) * f.weight_kg), 0) AS weight_saved,
           COALESCE(SUM(ir.co2_saved_kg), 0) AS co2
    FROM claim c
    JOIN food_listing f ON c.listing_id = f.listing_id
    LEFT JOIN impact_record ir ON ir.claim_id = c.claim_id
    WHERE f.provider_id = ? AND c.status = 'completed'
      AND MONTH(c.confirmed_at) = MONTH(CURDATE()) AND YEAR(c.confirmed_at) = YEAR(CURDATE())
");
mysqli_stmt_bind_param($stmt, "i", $providerId);
mysqli_stmt_execute($stmt);
$monthRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
$monthWeight = round($monthRow['weight_saved'], 1);
$monthCo2 = round($monthRow['co2'], 1);
$monthProgress = min(100, round(($monthWeight / MONTHLY_GOAL_KG) * 100));

// ---------------------------------------------------------
// 6. ACTIVE LISTINGS TABLE (most urgent first)
// ---------------------------------------------------------
$stmt = mysqli_prepare($conn, "
    SELECT listing_id, food_name, remain_quantity, total_quantity, status, expires_at
    FROM food_listing
    WHERE provider_id = ? AND status IN ('active','fully_claimed')
    ORDER BY expires_at ASC
    LIMIT 6
");
mysqli_stmt_bind_param($stmt, "i", $providerId);
mysqli_stmt_execute($stmt);
$listingsRows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// ---------------------------------------------------------
// 7. RECENT ACTIVITY (latest claims across this provider's listings)
// ---------------------------------------------------------
$stmt = mysqli_prepare($conn, "
    SELECT c.claim_id, c.status, c.created_at, c.confirmed_at, f.food_name, u.user_name
    FROM claim c
    JOIN food_listing f ON c.listing_id = f.listing_id
    JOIN user u ON c.student_id = u.user_id
    WHERE f.provider_id = ?
    ORDER BY c.created_at DESC
    LIMIT 5
");
mysqli_stmt_bind_param($stmt, "i", $providerId);
mysqli_stmt_execute($stmt);
$recentActivity = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/topbar.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/provider/provider.css">
    <script type="module" src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.js"></script>
    <title>Provider Dashboard</title>
</head>
<body>
    <div class="dashboard-container">
        <?php $provider_pending_claims_badge = getPendingClaimsCount($conn, $providerId); include '../../includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="topbar-container">
                <?php include '../../includes/topbar.php'; ?>
            </div>

            <div class="content-container">
                <div class="user-page-header">
                    <div class="user-page-header-left">
                        <h1 class="page-title">Provider Dashboard</h1>
                        <p class="page-subtitle">Welcome back. Here's what's happening with your campus food recovery today.</p>
                    </div>
                    <div class="user-page-header-right">
                        <a href="createListing.php" class="add-user-button" style="display:flex; align-items:center; justify-content:center; text-decoration:none;">+ Create Listing</a>
                    </div>
                </div>

                <div class="summary-card-container">
                    <div class="summary-card">
                        <span class="card-title">ACTIVE LISTINGS</span>
                        <span class="card-value"><?= htmlspecialchars($activeListings) ?></span>
                    </div>

                    <div class="summary-card">
                        <span class="card-title">PENDING PICKUPS</span>
                        <span class="card-value"><?= htmlspecialchars($pendingPickups) ?></span>
                        <?php if ($criticalPickups > 0): ?>
                            <span style="font-size:12px; color:#B42318; font-weight:600;"><?= $criticalPickups ?> critical</span>
                        <?php else: ?>
                            <span style="font-size:12px; color:#667085;">none urgent</span>
                        <?php endif; ?>
                    </div>

                    <div class="summary-card">
                        <span class="card-title">COMPLETED TODAY</span>
                        <span class="card-value"><?= htmlspecialchars($completedToday) ?></span>
                        <span style="font-size:12px; color:<?= $pctChange >= 0 ? '#15803D' : '#B42318' ?>; font-weight:600;">
                            <?= $pctChange >= 0 ? '+' : '' ?><?= $pctChange ?>% vs yesterday
                        </span>
                    </div>

                    <div class="summary-card" style="background-color:#385E29; border-color:#385E29;">
                        <span class="card-title" style="color:#D9E8CE;">TOTAL FOOD SAVED</span>
                        <span class="card-value" style="color:#FFFFFF;"><?= htmlspecialchars($totalFoodSaved) ?><span class="unit" style="color:#D9E8CE;">kg</span></span>
                        <span style="font-size:12px; color:#D9E8CE;">&asymp; <?= htmlspecialchars($totalCo2) ?>kg CO2 offset</span>
                    </div>
                </div>

                <div class="charts-section">
                    <div class="charts-column">
                        <div class="chart-card">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                                <h3 style="color:#101828;">Active Listings</h3>
                                <a href="manageListings.php">View All</a>
                            </div>

                            <div class="user-list-container">
                            <table class="user-list-table">
                                <thead>
                                    <tr>
                                        <th>Food Item</th>
                                        <th>Qty Left/Total</th>
                                        <th>Status</th>
                                        <th>Expiry</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($listingsRows)): ?>
                                        <tr><td colspan="5" style="text-align:center; padding:24px; color:#667085;">No active listings yet. Create your first one!</td></tr>
                                    <?php else: foreach ($listingsRows as $row):
                                        $badge = listingStatusBadge($row['status'], (int)$row['remain_quantity']);
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['food_name']) ?></td>
                                            <td><?= (int)$row['remain_quantity'] ?>/<?= (int)$row['total_quantity'] ?></td>
                                            <td><span class="status-pill <?= $badge['class'] ?>"><?= $badge['label'] ?></span></td>
                                            <td><?= htmlspecialchars(timeLeftText($row['expires_at'])) ?></td>
                                            <td><a href="manageListings.php?id=<?= $row['listing_id'] ?>"><ion-icon name="create-outline"></ion-icon></a></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </div>

                    <div class="side-column">
                        <div class="quick-links-container" style="background-color:#385E29; color:#FFFFFF; text-align:center; padding:24px;">
                            <ion-icon name="qr-code-outline" style="font-size:40px;"></ion-icon>
                            <h3 style="margin-top:10px;">QR Scanner</h3>
                            <p style="font-size:13px; color:#D9E8CE; margin:8px 0 16px;">Confirm student pickups quickly by scanning their claim codes.</p>
                            <a href="qrScanner.php" style="display:block; background-color:#FFFFFF; color:#275300; padding:10px; border-radius:8px; font-weight:700; text-decoration:none;">Open Scanner</a>
                        </div>

                        <div class="activity-container">
                            <span class="quick-link-header">Monthly Impact</span>
                            <div style="padding: 0 10px;">
                                <div style="display:flex; justify-content:space-between; font-size:13px; color:#475467; margin-bottom:6px;">
                                    <span>Total food rescued</span><span style="font-weight:700;"><?= htmlspecialchars($monthWeight) ?> kg</span>
                                </div>
                                <div style="display:flex; justify-content:space-between; font-size:13px; color:#475467; margin-bottom:14px;">
                                    <span>CO2 emissions avoided</span><span style="font-weight:700;"><?= htmlspecialchars($monthCo2) ?> kg CO2e</span>
                                </div>
                                <div style="height:8px; background-color:#EAECF0; border-radius:4px; overflow:hidden;">
                                    <div style="height:100%; width:<?= $monthProgress ?>%; background-color:#385E29;"></div>
                                </div>
                                <div style="text-align:right; font-size:12px; color:#667085; margin-top:6px;"><?= $monthProgress ?>% of monthly goal</div>
                            </div>
                        </div>

                        <div class="activity-container">
                            <span class="quick-link-header">Recent Activity</span>
                            <div style="text-align:right; margin-top:-24px; margin-bottom:8px;">
                                <a href="claimTracker.php" style="font-size:12px;">View All</a>
                            </div>
                            <?php if (empty($recentActivity)): ?>
                                <p style="text-align:center; color:#98A2B3; font-size:13px; padding:16px 0;">No claims yet.</p>
                            <?php else: foreach ($recentActivity as $act): ?>
                                <div style="padding:10px 4px; border-bottom:1px solid #F2F4F7; font-size:13px;">
                                    <strong><?= htmlspecialchars($act['user_name']) ?></strong> &mdash;
                                    <?= htmlspecialchars($act['food_name']) ?>
                                    <div style="color:#667085; font-size:12px;">
                                        <?= ucfirst($act['status']) ?> &bull; <?= date('M j, g:ia', strtotime($act['created_at'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
