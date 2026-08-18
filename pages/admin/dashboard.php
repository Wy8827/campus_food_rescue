<?php 
    session_start();

    require_once __DIR__ . '/../../config/constants.php';
    require_once __DIR__ . '/../../config/session.php';
    require_once __DIR__ . '/../../config/db.php';

    // 检查是否登录并且角色是 admin，如果不符合会自动跳转到登录页
    requireRole('admin'); 

    $pdo = getDB();

    $stmt = $pdo->prepare("SELECT user_id, user_name, email, role, account_status FROM `user`");
    $stmt->execute();
    $users = $stmt->fetchAll();
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
                        <span class="card-value">14</span>
                    </div>

                    <div class="summary-card">
                        <span class="card-title">ACTIVE USERS</span>
                        <span class="card-value">28</span>
                    </div>

                    <div class="summary-card">
                        <span class="card-title">TOTAL WEIGHT RESCUED</span>
                        <span class="card-value">120<span class="unit">tons</span></span>
                    </div>

                    <div class="summary-card">
                        <span class="card-title">AVERAGE PICKUP TIME</span>
                        <span class="card-value">20<span class="unit">mins</span></span>
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
                        labels: ['8am', '10am', '12pm', '2pm', '4pm'],
                        datasets: [{ 
                            label: 'Peak Claim Hours',
                            data: [10, 20, 30, 40, 50], 
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
                            labels: ["Student Coop", "Library", "APU Canteen", "Mamak", "Event"],
                            datasets: [{
                                data: [76, 96, 118, 140, 182],
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
                            labels: ["Beverages", "Fresh Fruit", "Bread", "Boxed Meals"],
                            datasets: [{
                                data: [10, 15, 30, 45],
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