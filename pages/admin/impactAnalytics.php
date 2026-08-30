<?php  
session_start();  
require_once __DIR__ . '/../../config/constants.php';  
require_once __DIR__ . '/../../config/session.php';  
require_once __DIR__ . '/../../config/db.php';  

requireRole('admin');   

// 1. Fetch Total Meals Saved
$mealsQuery = mysqli_query($conn, "SELECT SUM(portion_claimed) AS total_meals FROM claim WHERE status = 'completed'"); 
$mealsSaved = mysqli_fetch_assoc($mealsQuery)['total_meals'] ?? 0; 

// 2. Fetch Total CO2 and Water Mitigated
$impactQuery = mysqli_query($conn, "SELECT SUM(co2_saved_kg) AS total_co2, SUM(water_saved_litre) AS total_water FROM impact_record"); 
$impactData = mysqli_fetch_assoc($impactQuery); 
$co2Mitigation = round($impactData['total_co2'] ?? 0, 2); 
$waterConservation = round($impactData['total_water'] ?? 0, 2); 

// 3. Count Active Vendors
$vendorQuery = mysqli_query($conn, "SELECT COUNT(*) AS active_vendors FROM provider WHERE provider_status = 'active'"); 
$activeVendors = mysqli_fetch_assoc($vendorQuery)['active_vendors'] ?? 0; 

// 4. Fetch Vendor Table Data
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
    GROUP BY p.provider_id"); 
$vendorContributions = mysqli_fetch_all($vendorListQuery, MYSQLI_ASSOC); 

// 5. Fetch Daily Trend Data (Last 7 Days)
$chartLabels = []; 
$co2TrendData = []; 
$weightTrendData = []; 
$trendData = []; 

for ($i = 6; $i >= 0; $i--) {     
    $dateKey = date('Y-m-d', strtotime("-$i days"));     
    $trendData[$dateKey] = [         
        'label' => date('D', strtotime("-$i days")),         
        'co2' => 0.0,         
        'weight' => 0.0     
    ]; 
}

$startDate = date('Y-m-d', strtotime('-6 days')); 

$co2Res = mysqli_query($conn, "     
    SELECT DATE(recorded_at) as dt, SUM(co2_saved_kg) as daily_co2      
    FROM impact_record      
    WHERE recorded_at >= '$startDate 00:00:00'      
    GROUP BY DATE(recorded_at)"); 
while ($row = mysqli_fetch_assoc($co2Res)) {     
    if (isset($trendData[$row['dt']])) {         
        $trendData[$row['dt']]['co2'] = (float)$row['daily_co2'];     
    } 
}

$weightRes = mysqli_query($conn, "     
    SELECT DATE(c.confirmed_at) as dt, SUM(f.weight_kg * (c.portion_claimed / f.total_quantity)) as daily_weight      
    FROM claim c     
    JOIN food_listing f ON c.listing_id = f.listing_id     
    WHERE c.status = 'completed' AND c.confirmed_at >= '$startDate 00:00:00'     
    GROUP BY DATE(c.confirmed_at)"); 
while ($row = mysqli_fetch_assoc($weightRes)) {     
    if (isset($trendData[$row['dt']])) {         
        $trendData[$row['dt']]['weight'] = (float)$row['daily_weight'];     
    } 
}

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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">     
    <link rel="stylesheet" href="../../assets/css/topbar.css">     
    <link rel="stylesheet" href="../../assets/css/dashboard.css">     
    <link rel="stylesheet" href="../../assets/css/userManagement.css">     
    <link rel="stylesheet" href="../../assets/css/impactAnalytics.css">     
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>     
    <script type="module" src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.esm.js"></script>     
    <script nomodule src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.js"></script>     
    <title>Impact Analytics - Campus Food Rescue</title> 
</head> 
<body>     
    <div class="dashboard-container">         
        <?php include '../../includes/sidebar.php'; ?>         
        <div class="main-content">             
            <div class="topbar-container">                 
                <?php include '../../includes/topbar.php'; ?>             
            </div>             
            <div class="content-container">                 
                <div class="dashboard-header">                     
                    <h1 class="page-title">Impact Analytics</h1>                     
                    <p class="page-subtitle">Track campus sustainability indicators, carbon mitigation, water saved, and vendor contributions.</p>                 
                </div>

                <!-- KPI Summary Cards -->                 
                <div class="summary-card-container">                     
                    <div class="summary-card">                         
                        <div class="card-meta">
                            <span class="card-title">MEALS SAVED</span>
                            <div class="card-icon-wrap icon-emerald">
                                <ion-icon name="nutrition-outline"></ion-icon>
                            </div>
                        </div>
                        <div class="card-value"><?= htmlspecialchars($mealsSaved) ?></div>                     
                    </div>                     

                    <div class="summary-card">                         
                        <div class="card-meta">
                            <span class="card-title">CO2 MITIGATION</span>
                            <div class="card-icon-wrap icon-blue">
                                <ion-icon name="leaf-outline"></ion-icon>
                            </div>
                        </div>
                        <div class="card-value"><?= htmlspecialchars($co2Mitigation) ?><span class="unit">kg</span></div>                     
                    </div>                     

                    <div class="summary-card">                         
                        <div class="card-meta">
                            <span class="card-title">WATER CONSERVATION</span>
                            <div class="card-icon-wrap icon-purple">
                                <ion-icon name="water-outline"></ion-icon>
                            </div>
                        </div>
                        <div class="card-value"><?= htmlspecialchars($waterConservation) ?><span class="unit">L</span></div>                     
                    </div>                     

                    <div class="summary-card">                         
                        <div class="card-meta">
                            <span class="card-title">ACTIVE VENDORS</span>
                            <div class="card-icon-wrap icon-amber">
                                <ion-icon name="storefront-outline"></ion-icon>
                            </div>
                        </div>
                        <div class="card-value"><?= htmlspecialchars($activeVendors) ?></div>                     
                    </div>                 
                </div>                 

                <!-- 7-Day Trend Charts -->                 
                <div class="analytics-charts-section">                     
                    <div class="chart-card">                         
                        <div class="card-section-header">
                            <h3 class="chart-title">7-Day Waste Mitigation</h3>
                            <span class="chart-subtitle">Calculated CO2 emissions avoided from rescued meals</span>
                        </div>
                        <div class="chart-canvas-wrapper">                             
                            <canvas id="wasteMitigationChart"></canvas>                         
                        </div>                     
                    </div>                     

                    <div class="chart-card">                         
                        <div class="card-section-header">
                            <h3 class="chart-title">7-Day Rescue Volume</h3>
                            <span class="chart-subtitle">Total kilograms of food saved across campus</span>
                        </div>
                        <div class="chart-canvas-wrapper">                             
                            <canvas id="rescueWeightChart"></canvas>                         
                        </div>                     
                    </div>                 
                </div>                 

                <!-- Vendor Contribution Breakdown Table -->                 
                <div class="dashboard-header" style="margin-top: 32px; margin-bottom: 16px;">
                    <h2 class="chart-title">Vendor Contribution Breakdown</h2>                 
                    <span class="chart-subtitle">Detailed metrics per participating dining location</span>                 
                </div>

                <div class="table-card">                     
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
                            <?php if(!empty($vendorContributions)): ?>
                                <?php foreach($vendorContributions as $vendor): 
                                    $vStatus = strtolower($vendor['provider_status'] ?? 'active');
                                ?>                             
                                    <tr>                                 
                                        <td><span class="card-ref-code">VND-<?= str_pad($vendor['provider_id'], 4, '0', STR_PAD_LEFT) ?></span></td>                                 
                                        <td><b><?= htmlspecialchars($vendor['provider_name']) ?></b></td>                                 
                                        <td><?= number_format($vendor['rescue_volume'] ?? 0, 2) ?> kg</td>                                 
                                        <td><?= number_format($vendor['co2_mitigation'] ?? 0, 2) ?> kg</td>                                 
                                        <td>
                                            <div class="status-indicator status-<?= $vStatus ?>">
                                                <span class="dot"></span>
                                                <span><?= ucfirst(htmlspecialchars($vendor['provider_status'])) ?></span>
                                            </div>
                                        </td>                             
                                    </tr>                             
                                <?php endforeach; ?>                         
                            <?php else: ?>
                                <tr><td colspan="5" class="table-empty-cell">No vendor records found.</td></tr>
                            <?php endif; ?>
                        </tbody>                     
                    </table>                 
                </div>             
            </div>         
        </div>     
    </div> 

    <!-- Chart Scripts -->
    <script>                     
        Chart.defaults.font.family = "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
        Chart.defaults.color = '#64748B';

        const labels = <?= json_encode($chartLabels) ?>;                     
        const co2Data = <?= json_encode($co2TrendData) ?>;                     
        const weightData = <?= json_encode($weightTrendData) ?>;                     

        // Chart 1: CO2 Mitigation Line Chart                     
        const ctxWaste = document.getElementById('wasteMitigationChart').getContext('2d');                     
        new Chart(ctxWaste, {                         
            type: 'line',                         
            data: {                             
                labels: labels,                             
                datasets: [{                                 
                    label: 'CO2 Saved (kg)',                                 
                    data: co2Data,                                 
                    borderColor: '#2563EB',                                  
                    backgroundColor: 'rgba(37, 99, 235, 0.08)',                                  
                    borderWidth: 2.5,                                 
                    tension: 0.35,                                  
                    fill: true,
                    pointBackgroundColor: '#2563EB',
                    pointRadius: 4                             
                }]                         
            },                         
            options: {                             
                responsive: true,                             
                maintainAspectRatio: false,                              
                plugins: {                                 
                    legend: { display: false }
                },                             
                scales: {                                 
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, grid: { color: '#F1F5F9' } }                             
                }                         
            }                     
        });                     

        // Chart 2: Rescue Weight Line Chart                     
        const ctxRescue = document.getElementById('rescueWeightChart').getContext('2d');                     
        new Chart(ctxRescue, {                         
            type: 'line',                         
            data: {                             
                labels: labels,                             
                datasets: [{                                 
                    label: 'Weight (kg)',                                 
                    data: weightData,                                 
                    borderColor: '#16A34A',                                  
                    backgroundColor: 'rgba(22, 163, 74, 0.08)',                                  
                    borderWidth: 2.5,                                 
                    tension: 0.35,                                  
                    fill: true,
                    pointBackgroundColor: '#16A34A',
                    pointRadius: 4                             
                }]                         
            },                         
            options: {                             
                responsive: true,                             
                maintainAspectRatio: false,                              
                plugins: {                                 
                    legend: { display: false }
                },                             
                scales: {                                 
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, grid: { color: '#F1F5F9' } }                             
                }                         
            }                     
        });                 
    </script>
</body> 
</html>