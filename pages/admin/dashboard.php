<?php  
session_start(); 
require_once __DIR__ . '/../../config/constants.php'; 
require_once __DIR__ . '/../../config/session.php'; 
require_once __DIR__ . '/../../config/db.php'; 

requireRole('admin');  

// --------------------------------------------------------- 
// 1. STATISTICAL METRICS RETRIEVAL
// --------------------------------------------------------- 

// Metric 1: Pending Listings
$stmtPending = mysqli_query($conn, "SELECT COUNT(*) FROM `food_listing` WHERE `status` = 'pending'"); 
$pendingListings = mysqli_fetch_row($stmtPending)[0]; 

// Metric 2: Active Users
$stmtUsers = mysqli_query($conn, "SELECT COUNT(*) FROM `user` WHERE `account_status` = 'active'"); 
$activeUsers = mysqli_fetch_row($stmtUsers)[0]; 

// Metric 3: Total Weight Rescued (in tons)
$stmtWeight = mysqli_query($conn, "     
    SELECT SUM((c.portion_claimed / f.total_quantity) * f.weight_kg)      
    FROM `claim` c      
    JOIN `food_listing` f ON c.listing_id = f.listing_id      
    WHERE c.status = 'completed'"); 
$totalWeightRescued = round((mysqli_fetch_row($stmtWeight)[0] ?? 0) / 1000, 2); 

// Metric 4: Average Pickup Time (in minutes)
$stmtTime = mysqli_query($conn, "     
    SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, confirmed_at))      
    FROM `claim`      
    WHERE status = 'completed' AND confirmed_at IS NOT NULL"); 
$avgPickupTime = round(mysqli_fetch_row($stmtTime)[0] ?? 0); 

// --------------------------------------------------------- 
// 2. CHART DATA PROCESSING
// --------------------------------------------------------- 

// Chart 1: Peak Claim Hours  
$stmtPeak = mysqli_query($conn, "     
    SELECT HOUR(created_at) as claim_hour, COUNT(*) as claim_count      
    FROM `claim`      
    GROUP BY HOUR(created_at)      
    ORDER BY claim_hour"); 
$peakData = mysqli_fetch_all($stmtPeak, MYSQLI_ASSOC); 

$peakLabels = []; 
$peakCounts = []; 
foreach ($peakData as $row) {     
    $peakLabels[] = date("ga", strtotime($row['claim_hour'].":00"));     
    $peakCounts[] = (int)$row['claim_count']; 
}

// Chart 2: Rescued By Location  
$stmtLocation = mysqli_query($conn, "     
    SELECT f.pickup_location, COUNT(c.claim_id) as claim_count      
    FROM `claim` c      
    JOIN `food_listing` f ON c.listing_id = f.listing_id      
    WHERE c.status = 'completed'      
    GROUP BY f.pickup_location"); 
$locationData = mysqli_fetch_all($stmtLocation, MYSQLI_ASSOC); 
$locationLabels = array_column($locationData, 'pickup_location'); 
$locationCounts = array_map('intval', array_column($locationData, 'claim_count')); 

// Chart 3: Rescued By Category  
$stmtCategory = mysqli_query($conn, "     
    SELECT t.tag_name, COUNT(c.claim_id) as claim_count      
    FROM `claim` c      
    JOIN `food_listing` f ON c.listing_id = f.listing_id      
    JOIN `food_listing_tags` flt ON f.listing_id = flt.listing_id      
    JOIN `food_tags` t ON flt.tag_id = t.tag_id      
    WHERE c.status = 'completed'      
    GROUP BY t.tag_name"); 
$categoryData = mysqli_fetch_all($stmtCategory, MYSQLI_ASSOC); 
$categoryLabels = array_column($categoryData, 'tag_name'); 
$categoryCounts = array_map('intval', array_column($categoryData, 'claim_count')); 

// --------------------------------------------------------- 
// 3. RECENT ACTIVITY: Fetch latest 5 audit logs from combined tables
// --------------------------------------------------------- 
$recentLogsLimit = 5;
$recentLogQuery = "
    SELECT * FROM (
        SELECT 
            lal.performed_at AS timestamp,
            u.user_name AS admin_name,
            lal.admin_id AS admin_id,
            lal.action_type AS action_type,
            CONCAT('Listing ID: ', lal.listing_id) AS target_entity,
            lal.notes AS details
        FROM listing_audit_log lal
        JOIN user u ON lal.admin_id = u.user_id
        UNION ALL
        SELECT 
            ual.performed_at AS timestamp,
            u.user_name AS admin_name,
            ual.admin_id AS admin_id,
            ual.action_type AS action_type,
            CONCAT('User ID: ', ual.affected_user_id) AS target_entity,
            ual.notes AS details
        FROM user_audit_log ual
        JOIN user u ON ual.admin_id = u.user_id
    ) AS combined_logs
    ORDER BY timestamp DESC
    LIMIT ?";

$stmtRecent = mysqli_prepare($conn, $recentLogQuery);
mysqli_stmt_bind_param($stmtRecent, "i", $recentLogsLimit);
mysqli_stmt_execute($stmtRecent);
$recentLogsResult = mysqli_stmt_get_result($stmtRecent);
$recentLogs = mysqli_fetch_all($recentLogsResult, MYSQLI_ASSOC);
mysqli_stmt_close($stmtRecent);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/topbar.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script type="module" src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.js"></script>
    <title>Campus Food Rescue - Dashboard</title>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar Component -->
        <?php include '../../includes/sidebar.php'; ?>

        <div class="main-content">
            <!-- Topbar Component -->
            <div class="topbar-container">
                <?php include '../../includes/topbar.php'; ?>
            </div>

            <!-- Admin Scope ID -->
            <div class="content-container" id="admin-dashboard">
                <!-- Page Header Header -->
                <div class="dashboard-header">
                    <h1 class="page-title">System Overview</h1>
                    <p class="page-subtitle">Monitor campus-wide food rescue logistics, active participants, and operations.</p>
                </div>

                <!-- KPI Summary Cards Grid -->
                <div class="summary-card-container">
                    <div class="summary-card">
                        <div class="card-meta">
                            <span class="card-title">PENDING LISTINGS</span>
                            <div class="card-icon-wrap icon-amber">
                                <ion-icon name="time-outline"></ion-icon>
                            </div>
                        </div>
                        <div class="card-value"><?= htmlspecialchars($pendingListings) ?></div>
                    </div>

                    <div class="summary-card">
                        <div class="card-meta">
                            <span class="card-title">ACTIVE USERS</span>
                            <div class="card-icon-wrap icon-blue">
                                <ion-icon name="people-outline"></ion-icon>
                            </div>
                        </div>
                        <div class="card-value"><?= htmlspecialchars($activeUsers) ?></div>
                    </div>

                    <div class="summary-card">
                        <div class="card-meta">
                            <span class="card-title">TOTAL WEIGHT RESCUED</span>
                            <div class="card-icon-wrap icon-emerald">
                                <ion-icon name="scale-outline"></ion-icon>
                            </div>
                        </div>
                        <div class="card-value"><?= htmlspecialchars($totalWeightRescued) ?><span class="unit">tons</span></div>
                    </div>

                    <div class="summary-card">
                        <div class="card-meta">
                            <span class="card-title">AVERAGE PICKUP TIME</span>
                            <div class="card-icon-wrap icon-purple">
                                <ion-icon name="stopwatch-outline"></ion-icon>
                            </div>
                        </div>
                        <div class="card-value"><?= htmlspecialchars($avgPickupTime) ?><span class="unit">mins</span></div>
                    </div>
                </div>

                <!-- Main Layout Grid: Visual Analytics + Activity Column -->
                <div class="charts-section">
                    <!-- Left Column: Graphs -->
                    <div class="charts-column">
                        <!-- Bar Chart: Peak Claim Hours -->
                        <div class="chart-card">
                            <div class="card-section-header">
                                <h3 class="chart-title">Peak Claim Hours</h3>
                                <span class="chart-subtitle">Distribution of food reservation times across campus</span>
                            </div>
                            <div class="chart-canvas-wrapper">
                                <canvas id="peakClaimHourChart"></canvas>
                            </div>
                        </div>

                        <!-- Donut Charts: Rescued By Location & Category -->
                        <div class="chart-card">
                            <div class="card-section-header">
                                <h3 class="chart-title">Food Impact Breakdown</h3>
                                <span class="chart-subtitle">Distribution by pickup zones and food categories</span>
                            </div>
                            <div class="pie-container">
                                <div class="pie-wrapper">
                                    <h4 class="pie-subheading">Rescued By Location</h4>
                                    <div class="pie-canvas-box">
                                        <canvas id="locationPieChart"></canvas>
                                    </div>
                                </div>
                                <div class="pie-wrapper">
                                    <h4 class="pie-subheading">Rescued By Category</h4>
                                    <div class="pie-canvas-box">
                                        <canvas id="categoryPieChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Quick Links & Recent Activity -->
                    <div class="side-column">
                        <!-- Quick Links Navigation Block -->
                        <div class="side-panel-card quick-links-card">
                            <div class="side-panel-header">
                                <span class="panel-title">Quick Actions</span>
                            </div>
                            <div class="quick-links-grid">
                                <a href="moderation.php" class="quick-link-tile">
                                    <div class="tile-icon-bg bg-emerald">
                                        <ion-icon name="checkbox-outline"></ion-icon>
                                    </div>
                                    <span class="tile-label">List Moderation</span>
                                </a>

                                <a href="userManagement.php" class="quick-link-tile">
                                    <div class="tile-icon-bg bg-blue">
                                        <ion-icon name="people-circle-outline"></ion-icon>
                                    </div>
                                    <span class="tile-label">User Management</span>
                                </a>

                                <a href="impactAnalytics.php" class="quick-link-tile">
                                    <div class="tile-icon-bg bg-amber">
                                        <ion-icon name="stats-chart-outline"></ion-icon>
                                    </div>
                                    <span class="tile-label">Impact Analytics</span>
                                </a>

                                <a href="auditLog.php" class="quick-link-tile">
                                    <div class="tile-icon-bg bg-purple">
                                        <ion-icon name="document-text-outline"></ion-icon>
                                    </div>
                                    <span class="tile-label">Audit Log</span>
                                </a>
                            </div>
                        </div>

                        <!-- Recent Activity Log Block -->
                        <div class="side-panel-card activity-card">
                            <div class="side-panel-header flex-between">
                                <span class="panel-title">Recent Activity</span>
                                <span class="badge-dot-live">Live</span>
                            </div>

                            <div class="activity-feed">
                                <?php if (!empty($recentLogs)): ?>
                                    <?php foreach ($recentLogs as $log): 
                                        $words = explode(" ", trim($log['admin_name']));
                                        $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                                        $formattedAction = ucwords(str_replace('_', ' ', htmlspecialchars($log['action_type'])));
                                        $actionKey = strtolower($log['action_type']);
                                        $formattedTime = date("M d, H:i", strtotime($log['timestamp']));
                                    ?>
                                        <div class="activity-feed-item">
                                            <div class="activity-avatar-circle"><?= $initials ?></div>
                                            <div class="activity-body">
                                                <div class="activity-header-line">
                                                    <span class="activity-user-name"><?= htmlspecialchars($log['admin_name']) ?></span>
                                                    <span class="activity-timestamp"><?= $formattedTime ?></span>
                                                </div>
                                                <div class="activity-tag-row">
                                                    <span class="activity-badge tag-<?= $actionKey ?>"><?= $formattedAction ?></span>
                                                </div>
                                                <div class="activity-meta-text">
                                                    <span class="activity-target"><?= htmlspecialchars($log['target_entity']) ?></span>
                                                </div>
                                                <?php if (!empty($log['details'])): ?>
                                                    <div class="activity-note-quote"><?= htmlspecialchars($log['details']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <a href="auditLog.php" class="view-all-link">
                                        <span>View Full Audit Log</span>
                                        <ion-icon name="arrow-forward-outline"></ion-icon>
                                    </a>
                                <?php else: ?>
                                    <div class="empty-state-text">No recent audit logs available.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Initialize Chart.js with Modern Color Palette -->
                <script>
                    Chart.defaults.font.family = "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
                    Chart.defaults.color = '#64748B';

                    const ctxPeak = document.getElementById('peakClaimHourChart').getContext('2d');
                    new Chart(ctxPeak, {
                        type: 'bar',
                        data: {
                            labels: <?= json_encode($peakLabels) ?>,
                            datasets: [{ 
                                label: 'Claims count',
                                data: <?= json_encode($peakCounts) ?>, 
                                backgroundColor: 'rgba(39, 83, 0, 0.85)',
                                hoverBackgroundColor: '#275300',
                                borderRadius: 6,
                                maxBarThickness: 38
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: '#1E293B',
                                    padding: 10,
                                    cornerRadius: 8,
                                    titleFont: { size: 12, weight: '600' },
                                    bodyFont: { size: 13 }
                                }
                            },
                            scales: {
                                x: {
                                    grid: { display: false },
                                    ticks: { font: { size: 12, weight: '500' } }
                                },
                                y: {
                                    beginAtZero: true,
                                    grid: { color: '#F1F5F9' },
                                    ticks: { precision: 0, font: { size: 12 } }
                                }
                            }
                        }
                    });

                    const modernPalette = ['#275300', '#3B82F6', '#F59E0B', '#10B981', '#8B5CF6', '#EC4899', '#64748B'];

                    const ctxLoc = document.getElementById('locationPieChart').getContext('2d');
                    new Chart(ctxLoc, {
                        type: 'doughnut',
                        data: {
                            labels: <?= json_encode($locationLabels) ?>,
                            datasets: [{
                                data: <?= json_encode($locationCounts) ?>,
                                backgroundColor: modernPalette,
                                borderWidth: 2,
                                borderColor: '#FFFFFF',
                                hoverOffset: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '68%',
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: { boxWidth: 10, usePointStyle: true, padding: 12, font: { size: 11 } }
                                }
                            }
                        }
                    });

                    const ctxCat = document.getElementById('categoryPieChart').getContext('2d');
                    new Chart(ctxCat, {
                        type: 'doughnut',
                        data: {
                            labels: <?= json_encode($categoryLabels) ?>,
                            datasets: [{
                                data: <?= json_encode($categoryCounts) ?>,
                                backgroundColor: modernPalette.slice().reverse(),
                                borderWidth: 2,
                                borderColor: '#FFFFFF',
                                hoverOffset: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '68%',
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: { boxWidth: 10, usePointStyle: true, padding: 12, font: { size: 11 } }
                                }
                            }
                        }
                    });
                </script>
            </div>
        </div>
    </div>
</body>
</html>