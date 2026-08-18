<?php session_start(); 
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
    <script src="https://cdn.plot.ly/plotly-3.6.0.min.js" charset="utf-8"></script>
    <script type="module" src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.js"></script>
    <title>Audit Log</title>
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
                    <h1 class="page-title">Audit Log</h1>
                    <p class="page-subtitle">System activity records and security monitoring.</p>

                    <div class="summary-card-container">
                        <div class="summary-card">
                            <span class="card-title">TOTAL ENTRIES</span>
                            <span class="card-value">323</span>
                        </div>

                        <div class="summary-card">
                            <span class="card-title">CRITICAL ACTIONS(24H)</span>
                            <span class="card-value">10</span>
                        </div>

                        <div class="summary-card">
                            <span class="card-title">ACTIVE ADMIN</span>
                            <span class="card-value">2</span>
                        </div>

                        <div class="summary-card">
                            <span class="card-title">LOG RETENTION</span>
                            <span class="card-value">90<span class="unit">Days</span></span>
                        </div>
                    </div>

                    
                    <span class="section-title">Vendor Contribution Analysis</span> </br>
                    <span class="section-subtitle">Detailed metrics per participating location</span>
                    <div class="user-list-container">
                        <table class="user-list-table">
                            <thead>
                                <tr>
                                    <th>TIMESTAMP</th>
                                    <th>ADMIN NAME/ID</th>
                                    <th>ACTION TYPE</th>
                                    <th>TARGET ENTITY</th>
                                    <th>DEVICES</th>
                                    <th>DETAILS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>2023-10-01 10:30:00</td>
                                    <td>Admin User</td>
                                    <td>User Created</td>
                                    <td>VND-0036</td>
                                    <td>Desktop</td>
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