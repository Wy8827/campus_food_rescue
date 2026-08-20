<?php 
session_start(); 
require_once __DIR__ . '/../../config/constants.php'; 
require_once __DIR__ . '/../../config/session.php'; 
require_once __DIR__ . '/../../config/db.php'; 

requireRole('admin');  

// ---------------------------------------------------- 
// 1. Fetch Total Meals Saved from the claim table
// ---------------------------------------------------- 
$mealsQuery = mysqli_query($conn, "SELECT SUM(portion_claimed) AS total_meals FROM claim WHERE status = 'completed'");
$mealsSaved = mysqli_fetch_assoc($mealsQuery)['total_meals'] ?? 0;

// ---------------------------------------------------- 
// 2. Fetch Total CO2 and Water Mitigated from impact_record
// ---------------------------------------------------- 
$impactQuery = mysqli_query($conn, "SELECT SUM(co2_saved_kg) AS total_co2, SUM(water_saved_litre) AS total_water FROM impact_record");
$impactData = mysqli_fetch_assoc($impactQuery);
$co2Mitigation = round($impactData['total_co2'] ?? 0, 2);
$waterConservation = round($impactData['total_water'] ?? 0, 2);

// ---------------------------------------------------- 
// 3. Count Active Vendors
// ---------------------------------------------------- 
$vendorQuery = mysqli_query($conn, "SELECT COUNT(*) AS active_vendors FROM provider WHERE provider_status = 'active'");
$activeVendors = mysqli_fetch_assoc($vendorQuery)['active_vendors'] ?? 0;

// ---------------------------------------------------- 
// 4. Fetch Vendor Table Data using the pre-built view or manual join
// ---------------------------------------------------- 
$vendorListQuery = mysqli_query($conn, "
    SELECT 
        p.provider_id, 
        p.provider_name, 
        p.provider_status,
        SUM(f.weight_kg * (c.portion_claimed / f.total_quantity)) AS rescue_volume, 
        SUM(i.co2_saved_kg) AS co2_mitigation
    FROM provider p
    LEFT JOIN food_listing f ON p.provider_id = f.provider_id
    LEFT JOIN claim c ON f.listing_id = c.listing_id AND c.status = 'completed'
    LEFT JOIN impact_record i ON c.claim_id = i.claim_id
    GROUP BY p.provider_id
");
$vendorContributions = mysqli_fetch_all($vendorListQuery, MYSQLI_ASSOC);

// ---------------------------------------------------- 
// 5. Optimized: Fetch Daily Trend Data for Charts (Last 7 Days)
// Using single queries with GROUP BY instead of loop queries.
// ---------------------------------------------------- 
$chartLabels = [];
$co2TrendData = [];
$weightTrendData = [];

// Initialize an array for the last 7 days with default zero values
$trendData = [];
for ($i = 6; $i >= 0; $i--) {
    $dateKey = date('Y-m-d', strtotime("-$i days"));
    $trendData[$dateKey] = [
        'label' => date('D', strtotime("-$i days")), // e.g., "Mon", "Tue"
        'co2' => 0.0,
        'weight' => 0.0
    ];
}

$startDate = date('Y-m-d', strtotime('-6 days'));

// Fetch CO2 trend grouped by date in a single query
$co2Res = mysqli_query($conn, "
    SELECT DATE(recorded_at) as dt, SUM(co2_saved_kg) as daily_co2 
    FROM impact_record 
    WHERE recorded_at >= '$startDate 00:00:00' 
    GROUP BY DATE(recorded_at)
");
while ($row = mysqli_fetch_assoc($co2Res)) {
    if (isset($trendData[$row['dt']])) {
        $trendData[$row['dt']]['co2'] = (float)$row['daily_co2'];
    }
}

// Fetch Rescue Weight trend grouped by date in a single query
$weightRes = mysqli_query($conn, "
    SELECT DATE(c.confirmed_at) as dt, SUM(f.weight_kg * (c.portion_claimed / f.total_quantity)) as daily_weight 
    FROM claim c
    JOIN food_listing f ON c.listing_id = f.listing_id
    WHERE c.status = 'completed' AND c.confirmed_at >= '$startDate 00:00:00'
    GROUP BY DATE(c.confirmed_at)
");
while ($row = mysqli_fetch_assoc($weightRes)) {
    if (isset($trendData[$row['dt']])) {
        $trendData[$row['dt']]['weight'] = (float)$row['daily_weight'];
    }
}

// Flatten data for Chart.js JSON encoding
foreach ($trendData as $data) {
    $chartLabels[] = $data['label'];
    $co2TrendData[] = $data['co2'];
    $weightTrendData[] = $data['weight'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/topbar.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/userManagement.css">
    <link rel="stylesheet" href="../../assets/css/impactAnalytics.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.plot.ly/plotly-3.6.0.min.js" charset="utf-8"></script>
    <script type="module" src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.js"></script>
    <title>Impact Analytics</title>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar on the left -->
        <?php include '../../includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="topbar-container">
                <?php include '../../includes/topbar.php'; ?>
            </div>

            <div class="content-container">
                <div>
                    <h1 class="page-title">Impact Analytics</h1>
                    <p class="page-subtitle">Visualize the impact of campus food rescue efforts through interactive charts and graphs. Track metrics such as food donations, volunteer engagement, and environmental impact over time.</p>

                    <!-- Summary Cards Section -->
                    <div class="summary-card-container">
                        <div class="summary-card">
                            <span class="card-title">MEALS SAVED</span>
                            <span class="card-value"><?= htmlspecialchars($mealsSaved) ?></span>
                        </div>

                        <div class="summary-card">
                            <span class="card-title">CO2 MITIGATION</span>
                            <span class="card-value"><?= htmlspecialchars($co2Mitigation) ?><span class="unit">kg</span></span>
                        </div>

                        <div class="summary-card">
                            <span class="card-title">WATER CONSERVATION</span>
                            <span class="card-value"><?= htmlspecialchars($waterConservation) ?><span class="unit">L</span></span>
                        </div>

                        <div class="summary-card">
                            <span class="card-title">ACTIVE VENDORS</span>
                            <span class="card-value"><?= htmlspecialchars($activeVendors) ?></span>
                        </div>
                    </div>

                    <!-- Charts Section -->
                    <div class="analytics-charts-section">
                        <div class="chart-card">
                            <div style="position: relative; height:360px; width:100%;">
                                <canvas id="wasteMitigationChart"></canvas>
                            </div>
                        </div>
                        <div class="chart-card">
                            <div style="position: relative; height:360px; width:100%;">
                                <canvas id="rescueWeightChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <script>
                        // Pass dynamic PHP arrays to JavaScript
                        const labels = <?= json_encode($chartLabels) ?>;
                        const co2Data = <?= json_encode($co2TrendData) ?>;
                        const weightData = <?= json_encode($weightTrendData) ?>;

                        // Chart 1: CO2 Mitigation Trend Line Chart
                        const ctxWaste = document.getElementById('wasteMitigationChart').getContext('2d');
                        new Chart(ctxWaste, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Waste Mitigation (kg CO2)',
                                    data: co2Data,
                                    borderColor: '#1565c0', 
                                    backgroundColor: 'rgba(21, 101, 192, 0.2)', 
                                    borderWidth: 2,
                                    tension: 0.3, 
                                    fill: true    
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false, 
                                plugins: {
                                    title: {
                                        display: true,
                                        text: '7-Day CO2 Mitigation Trend',
                                        font: { size: 16 }
                                    }
                                },
                                scales: {
                                    y: { beginAtZero: true }
                                }
                            }
                        });

                        // Chart 2: Rescue Weight Trend Line Chart
                        const ctxRescue = document.getElementById('rescueWeightChart').getContext('2d');
                        new Chart(ctxRescue, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Rescue Weight (kg)',
                                    data: weightData,
                                    borderColor: '#2e7d32', 
                                    backgroundColor: 'rgba(46, 125, 50, 0.2)', 
                                    borderWidth: 2,
                                    tension: 0.3, 
                                    fill: true    
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false, 
                                plugins: {
                                    title: {
                                        display: true,
                                        text: '7-Day Rescue Weight Trend',
                                        font: { size: 16 }
                                    }
                                },
                                scales: {
                                    y: { beginAtZero: true }
                                }
                            }
                        });
                    </script>

                    <!-- Vendor Contribution Table -->
                    <span class="section-title">Vendor Contribution Analysis</span> <br>
                    <span class="section-subtitle">Detailed metrics per participating location</span>
                    <div class="user-list-container">
                        <table class="user-list-table">
                            <thead>
                                <tr>
                                    <th>VENDOR ID</th>
                                    <th>VENDOR NAME</th>
                                    <th>RESCUE VOLUME</th>
                                    <th>CO2 MITIGATION</th>
                                    <th>STATUS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($vendorContributions as $vendor): ?>
                                <tr>
                                    <td>VND-<?= str_pad($vendor['provider_id'], 4, '0', STR_PAD_LEFT) ?></td>
                                    <td><?= htmlspecialchars($vendor['provider_name']) ?></td>
                                    <td><?= number_format($vendor['rescue_volume'] ?? 0, 2) ?> kg</td>
                                    <td><?= number_format($vendor['co2_mitigation'] ?? 0, 2) ?> kg</td>
                                    <td><?= ucfirst(htmlspecialchars($vendor['provider_status'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>
</body>
</html>