<?php 
session_start();

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';

requireRole('admin'); 

$pdo = getDB();

// 1. PENDING LISTINGS 
$stmtPending = $pdo->query("SELECT COUNT(*) FROM `food_listing` WHERE `status` = 'pending'");
$pendingListings = $stmtPending->fetchColumn();

// 2. ACTIVE USERS 
$stmtUsers = $pdo->query("SELECT COUNT(*) FROM `user` WHERE `account_status` = 'active'");
$activeUsers = $stmtUsers->fetchColumn();

// 3. TOTAL WEIGHT RESCUED 
// calculate formula: (completed claims / total quantity) * food weight
$stmtWeight = $pdo->query("
    SELECT SUM((c.portion_claimed / f.total_quantity) * f.weight_kg) 
    FROM `claim` c 
    JOIN `food_listing` f ON c.listing_id = f.listing_id 
    WHERE c.status = 'completed'
");
$totalWeightRescued = round($stmtWeight->fetchColumn() ?? 0, 2);

// 4. AVERAGE PICKUP TIME (平均取餐时间)
// calculate formula: average of minutes difference between confirmed_at and created_at for completed orders
$stmtTime = $pdo->query("
    SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, confirmed_at)) 
    FROM `claim` 
    WHERE status = 'completed' AND confirmed_at IS NOT NULL
");
$avgPickupTime = round($stmtTime->fetchColumn() ?? 0);

// ---------------------------------------------------------
// CHART data handling
// ---------------------------------------------------------

// Chart 1: Peak Claim Hours 
$stmtPeak = $pdo->query("
    SELECT HOUR(created_at) as claim_hour, COUNT(*) as claim_count 
    FROM `claim` 
    GROUP BY HOUR(created_at) 
    ORDER BY claim_hour
");
$peakData = $stmtPeak->fetchAll(PDO::FETCH_ASSOC);
$peakLabels = [];
$peakCounts = [];
foreach ($peakData as $row) {
    // format time as 8am, 2pm, etc.
    $peakLabels[] = date("ga", strtotime($row['claim_hour'].":00"));
    $peakCounts[] = $row['claim_count'];
}

// Chart 2: Rescued By Location 
$stmtLocation = $pdo->query("
    SELECT f.pickup_location, COUNT(c.claim_id) as claim_count 
    FROM `claim` c 
    JOIN `food_listing` f ON c.listing_id = f.listing_id 
    WHERE c.status = 'completed' 
    GROUP BY f.pickup_location
");
$locationData = $stmtLocation->fetchAll(PDO::FETCH_ASSOC);
$locationLabels = array_column($locationData, 'pickup_location');
$locationCounts = array_column($locationData, 'claim_count');

// Chart 3: Rescued By Category 
$stmtCategory = $pdo->query("
    SELECT t.tag_name, COUNT(c.claim_id) as claim_count 
    FROM `claim` c 
    JOIN `food_listing` f ON c.listing_id = f.listing_id 
    JOIN `food_listing_tags` flt ON f.listing_id = flt.listing_id 
    JOIN `food_tags` t ON flt.tag_id = t.tag_id 
    WHERE c.status = 'completed' 
    GROUP BY t.tag_name
");
$categoryData = $stmtCategory->fetchAll(PDO::FETCH_ASSOC);
$categoryLabels = array_column($categoryData, 'tag_name');
$categoryCounts = array_column($categoryData, 'claim_count');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/topbar.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script type="module" src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.js"></script>
    <title>Dashboard</title>
</head>
<body>
    <div class="dashboard-container">
        <!-- sidebar on the left -->
        <?php include '../../includes/sidebar.php'; ?>

        <div class="main-content">

            <div class="topbar-container">
                <?php include '../../includes/topbar.php'; ?>
            </div>

            <div class="content-container">
                <h1 class="page-title">System Overview</h1>
                <p class="page-subtitle">Monitor campus-wide food rescue logistics and impact.</p>

                <div class="summary-card-container">
                    <div class="summary-card">
                        <span class="card-title">PENDING LISTINGS</span>
                        <span class="card-value"><?= htmlspecialchars($pendingListings) ?></span>
                    </div>

                    <div class="summary-card">
                        <span class="card-title">ACTIVE USERS</span>
                        <span class="card-value"><?= htmlspecialchars($activeUsers) ?></span>
                    </div>

                    <div class="summary-card">
                        <span class="card-title">TOTAL WEIGHT RESCUED</span>
                        <span class="card-value"><?= htmlspecialchars($totalWeightRescued) ?><span class="unit">tons</span></span>
                    </div>

                    <div class="summary-card">
                        <span class="card-title">AVERAGE PICKUP TIME</span>
                        <span class="card-value"><?= htmlspecialchars($avgPickupTime) ?><span class="unit">mins</span></span>
                    </div>
                </div>

                <div class="charts-section">
                    <div class="charts-column">
                        <div class="chart-card">
                            <canvas id="peakClaimHourChart"></canvas>
                        </div>
                            <div class="chart-card">
                                <h3 style="text-align:center; margin-bottom: 15px; color: #333;">Food Impact Overview</h3>
                                <div class="pie-container">
                                    <div class="pie-wrapper">
                                        <canvas id="locationPieChart"></canvas>
                                    </div>
                                    <div class="pie-wrapper">
                                        <canvas id="categoryPieChart"></canvas>
                                    </div>
                                </div>
                            </div>
                    </div>

                    <div class="side-column">
                        <div class="quick-links-container">
                            <span class = "quick-link-header">Quick Links</span>
                            
                            <div class="quick-links-list">
                                <a href="moderation.php" class="quick-link-item">
                                    <span><img src="../../assets/images/moderationlogo.png" alt="Quick Link Icon" width = "18"></span>List Moderation
                                </a>

                                <a href="userManagement.php" class="quick-link-item">
                                    <span><img src="../../assets/images/user.png" alt="Quick Link Icon" width = "18"></span>User Management
                                </a>

                                <a href="impactAnalytics.php" class="quick-link-item">
                                    <span><img src="../../assets/images/analytics.png" alt="Quick Link Icon" width = "18"></span>Impact Analytics
                                </a>

                                <a href="auditLog.php" class="quick-link-item">
                                    <span><img src="../../assets/images/audit.png" alt="Quick Link Icon" width = "18"></span>Audit Log
                                </a>
                            </div>
                        </div>

                        <div class="activity-container">
                            <span class = "quick-link-header">Recent Activity</span>
                        </div>
                    </div>
                    

                </div>

                <!-- one script initialize two Plotly graphs -->
                <script>
                    const context = document.getElementById('peakClaimHourChart').getContext('2d');

                    const chartData = {
                        labels: <?= json_encode($peakLabels) ?>,
                        datasets: [{ 
                            label: 'Peak Claim Hours',
                            data: <?= json_encode($peakCounts) ?>, 
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.2)'
                            ],
                            borderColor: [
                                'rgb(255, 99, 132)'
                            ],
                            borderWidth: 1
                        }]
                    };

                    const config = {
                        type: 'bar',
                        data: chartData,
                        options: {
                            responsive: true,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'Peak Claim Hours',
                                    font: {
                                        size: 16,
                                        color: '#333'
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true 
                                }
                            }
                        }
                    };

                    const peakClaimHoursChart = new Chart(context, config);


                    
                    // 2. Location Pie Chart
                    const ctxLoc = document.getElementById('locationPieChart').getContext('2d');
                    new Chart(ctxLoc, {
                        type: 'pie',
                        data: {
                            labels: <?= json_encode($locationLabels) ?>, // Dynamic Locations
                            datasets: [{
                                data: <?= json_encode($locationCounts) ?>, // Dynamic Counts
                                backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF']
                            }]
                        },
                        options: { plugins: { title: { display: true, text: 'Rescued By Location' } } }
                    });

                    // 3. Category Pie Chart
                    const ctxCat = document.getElementById('categoryPieChart').getContext('2d');
                    new Chart(ctxCat, {
                        type: 'pie',
                        data: {
                            labels: <?= json_encode($categoryLabels) ?>, // Dynamic Tags
                            datasets: [{
                                data: <?= json_encode($categoryCounts) ?>, // Dynamic Counts
                                backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0']
                            }]
                        },
                        options: { plugins: { title: { display: true, text: 'Rescued By Category' } } }
                    });
                </script>

                

            </div>

            
        </div>
    </div>
    
</body>
</html>