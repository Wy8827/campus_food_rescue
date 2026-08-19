<?php session_start(); 

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';

$pdo = getDB();

$currentUserId = $_SESSION['user_id'] ?? null;
$admin_data = [];

try {
    $stmt = $pdo->prepare("SELECT user_name, email, role, security_question FROM user WHERE user_id = ? AND role = 'admin'");
    $stmt->execute([$currentUserId]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$admin_data) {
        die("Admin data not found.");
    }
} catch(PDOException $e){
    die("Database error: " . $e->getMessage());
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
    <link rel="stylesheet" href="../../assets/css/moderation.css">
    <link rel="stylesheet" href="../../assets/css/adminProfile.css">
    <link rel="stylesheet" href="../../assets/css/userManagement.css">
    <script src="../../assets/js/adminProfile.js" defer></script>
    <script type="module" src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.js"></script>
    <title>Account Settings</title>
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
                <div class="user-page-header">
                    <div class="user-page-header-left">
                        <h1 class="page-title">Account Settings</h1>
                        <p class="page-subtitle">Manage your administrator profile, security preferences, and system alerts.</p>
                    </div>

                    <div class="user-page-header-right">
                        <button class="export-button">Edit Profile</button>
                        <button class="add-user-button">Save Changes</button>
                    </div>
                </div>

                <div class="charts-section">
                    <div class="charts-column">
                        <div class="chart-card">
                            <h2 class="profile-title">Profile Information</h2>
                            <div class="profile-info-container">
                                <div class="profile-info-item">
                                    <span class="info-label">Username:</span> <br>
                                    <input type="text" class="info-value" value="<?php echo htmlspecialchars($admin_data['user_name']); ?>" readonly>
                                </div>

                                <div class="profile-info-item">
                                    <span class="info-label">Role:</span> <br>
                                    <div class="role-value"><span type="text" class="" value="<?php echo htmlspecialchars($admin_data['role']); ?>" readonly>Administrator</span></div>
                                </div>
                                
                                <div class="profile-info-item">
                                    <span class="info-label">Email:</span> <br>
                                    <input type="text" class="info-value" value="<?php echo htmlspecialchars($admin_data['email']); ?>" readonly>
                                </div>
                                
                            </div>
                        </div>

                        <div class="chart-card">
                            <h2 class="profile-title">Security Settings</h2>
                            <div class="security-settings-container">
                                <div class="security-setting-item">
                                    <div class="setting-label-container">
                                        <label class="setting-label">Security Question: </label>
                                        <label class="setting-value"><?php echo htmlspecialchars($admin_data['security_question']); ?></label>
                                    </div>
                                    <input type="text" name="security_answer" class="info-value" placeholder="Enter your answer" readonly>
                                </div>
                                <div class="security-setting-item">
                                    <h3 class="profile-title">Change Password</h3>
                                    <div class="profile-info-container">
                                        <div class="profile-info-item">
                                            <span class="info-label">Current Password:</span> <br>
                                            <input type="password" class="info-value" placeholder="Enter current password" readonly>
                                        </div>

                                        <div class="profile-info-item">
                                            <span class="info-label">New Password:</span> <br>
                                            <input type="password" class="info-value" placeholder="Enter new password" readonly>
                                        </div>
                                        
                                        <div class="profile-info-item">
                                            <span class="info-label">Confirm New Password:</span> <br>
                                            <input type="password" class="info-value" placeholder="Confirm new password" readonly>
                                        </div>
                                    </div>
                                    <button class="update-btn">Update Password</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    

                    <div class="side-column">
                        <div class="quick-links-container">
                            <span class = "quick-link-header">Admin Alerts</span>
                            <p style="color: #42493B;">Manage notifications for high-priority system events.</p>
                            
                            <div class="alert-setting">
                                <label class="alert-setting-item">
                                    Instant alert on 3+ flagged listings <img src="../../assets/images/off.png" alt="status off" class="switch">
                                </label>

                                <label class="alert-setting-item">
                                    New provider verification requests <img src="../../assets/images/off.png" alt="status off" class="switch">
                                </label>

                                <label class="alert-setting-item">
                                    Daily system health summary <img src="../../assets/images/off.png" alt="status off" class="switch" >
                                </label>
                            </div>
                        </div>

                        <div class="activity-container">
                            <span class = "quick-link-header">Active Session</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        const switches = document.querySelectorAll('.switch');
        switches.forEach(img => {
            img.addEventListener('click', () => {
                if (img.src.includes('off.png')) {
                    img.src = '../../assets/images/on.png';
                    img.alt = 'on Icon';
                } else {
                    img.src = '../../assets/images/off.png';
                    img.alt = 'off Icon';
                }
            });
        });
            
    </script>
    
</body>
</html>