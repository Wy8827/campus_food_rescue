<?php session_start(); ?>

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
        <!-- sidebar on the left -->
        <?php include '../../includes/sidebar.php'; ?>

        <div class="main-content">

            <div class="topbar-container">
                <?php include '../../includes/topbar.php'; ?>
            </div>

            <div class="content-container">
                <div>
                    <h1 class="page-title">Impact Analytics</h1>
                    <p class="page-subtitle">Visualize the impact of campus food rescue efforts through interactive charts and graphs.
                    Track metrics such as food donations, volunteer engagement, and environmental impact over time.</p>

                    <div class="summary-card-container">
                        <div class="summary-card">
                            <span class="card-title">MEALS SAVED</span>
                            <span class="card-value">453</span>
                        </div>

                        <div class="summary-card">
                            <span class="card-title">CO2 MITIGATION</span>
                            <span class="card-value">2.8<span class="unit">tons</span></span>
                        </div>

                        <div class="summary-card">
                            <span class="card-title">WATER CONSERVATION</span>
                            <span class="card-value">210K<span class="unit">gal</span></span>
                        </div>

                        <div class="summary-card">
                            <span class="card-title">ACTIVE VENDORS</span>
                            <span class="card-value">15</span>
                        </div>
                    </div>

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
                        const labels = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
                        // Graph 1：Waste Mitigation Trend Line (Chart.js Line Chart)
                        const ctxWaste = document.getElementById('wasteMitigationChart').getContext('2d');
                        new Chart(ctxWaste, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Waste Mitigation',
                                    data: [15, 10, 40, 20, 50, 23, 45],
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
                                        text: 'Waste Mitigation Trend Line',
                                        font: { size: 16, color: '#333' }
                                    }
                                },
                                scales: {
                                    y: { beginAtZero: true }
                                }
                            }
                        });

                        // Graph 2：Rescue Weight Trend (Chart.js Line Chart)
                        const ctxRescue = document.getElementById('rescueWeightChart').getContext('2d');
                        new Chart(ctxRescue, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Rescue Weight (kg)',
                                    data: [500, 100, 400, 200, 500, 230, 450],
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
                                        text: 'Rescue Weight Trend',
                                        font: { size: 16, color: '#333' }
                                    }
                                },
                                scales: {
                                    y: { beginAtZero: true }
                                }
                            }
                        });
                    </script>

                    <div class="user-list-container">
                        <span class="section-title">Vendor Contribution Analysis</span> </br>
                        <span class="section-subtitle">Detailed metrics per participating location</span>
                        <table class="user-list-table">
                            <thead>
                                <tr>
                                    <th>VENDOR ID</th>
                                    <th>VENDOR NAME</th>
                                    <th>RESCUE VOLUME</th>
                                    <th>CO2 MITIGATION</th>
                                    <th>EFFICIENCY</th>
                                    <th>STATUS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Example user row -->
                                <tr>
                                    <td>VND-0036</td>
                                    <td>Happy Bakery</td>
                                    <td>500 kg</td>
                                    <td>200 tons</td>
                                    <td>90%</td>
                                    <td>
                                        <button class="edit-button">Edit</button>
                                        <button class="suspend-button">Suspend</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>

                
            </div>

        </div>
    </div>
    
</body>
</html>