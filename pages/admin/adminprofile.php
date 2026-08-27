<?php  
session_start();  
require_once __DIR__ . '/../../config/constants.php';  
require_once __DIR__ . '/../../config/session.php';  
require_once __DIR__ . '/../../config/db.php';  

requireRole('admin');   

$currentUserId = $_SESSION['user_id'] ?? null; 
$admin_data = []; 
$success_msg = ''; 
$error_msg = ''; 

// ========================================== 
// Handle Form Submissions (POST Requests) 
// ========================================== 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {          
    // 1. Handle "Save Changes" (Profile Update)     
    if (isset($_POST['save_profile'])) {         
        $new_username = trim($_POST['user_name']);         
        $new_email = trim($_POST['email']);                  
        
        if (empty($new_username) || empty($new_email)) {             
            $error_msg = "Username and Email cannot be empty.";         
        } else {             
            try {                 
                $updateQuery = "UPDATE user SET user_name = ?, email = ? WHERE user_id = ? AND role = 'admin'";                 
                $updateStmt = mysqli_prepare($conn, $updateQuery);                 
                mysqli_stmt_bind_param($updateStmt, "ssi", $new_username, $new_email, $currentUserId);                                  
                
                if (mysqli_stmt_execute($updateStmt)) {                     
                    $success_msg = "Profile updated successfully!";                 
                } else {                     
                    $error_msg = "Failed to update profile.";                 
                }                 
                mysqli_stmt_close($updateStmt);             
            } catch (Exception $e) {                 
                $error_msg = "Database error: " . $e->getMessage();             
            }         
        }     
    }     

    // 2. Handle Password Update with Dual Auth     
    if (isset($_POST['update_password'])) {         
        $current_pw = $_POST['current_password'] ?? '';         
        $security_ans = $_POST['security_answer_input'] ?? '';         
        $new_pw = $_POST['new_password'];         
        $confirm_pw = $_POST['confirm_password'];                  
        $use_security_question = isset($_POST['use_security_question']) && $_POST['use_security_question'] === 'yes';                  
        
        if (empty($new_pw) || empty($confirm_pw)) {             
            $error_msg = "Please fill in the new password fields.";         
        } elseif ($new_pw !== $confirm_pw) {             
            $error_msg = "New passwords do not match.";         
        } else {             
            try {                 
                $authQuery = "SELECT pass_hash, security_answer FROM user WHERE user_id = ?";                 
                $authStmt = mysqli_prepare($conn, $authQuery);                 
                mysqli_stmt_bind_param($authStmt, "i", $currentUserId);                 
                mysqli_stmt_execute($authStmt);                 
                $authResult = mysqli_stmt_get_result($authStmt);                 
                $user = mysqli_fetch_assoc($authResult);                 
                mysqli_stmt_close($authStmt);                                  
                
                $is_authorized = false;                                  
                if ($user) {                     
                    if ($use_security_question) {                         
                        if (empty($security_ans)) {                             
                            $error_msg = "Please provide your security answer.";                         
                        } elseif (password_verify($security_ans, $user['security_answer'])) {                             
                            $is_authorized = true;                         
                        } else {                             
                            $error_msg = "Incorrect security answer.";                         
                        }                     
                    } else {                         
                        if (empty($current_pw)) {                             
                            $error_msg = "Please enter your current password.";                         
                        } elseif (password_verify($current_pw, $user['pass_hash'])) {                             
                            $is_authorized = true;                         
                        } else {                             
                            $error_msg = "Incorrect current password.";                         
                        }                     
                    }                 
                }                                  
                
                if ($is_authorized) {                     
                    $new_hash = password_hash($new_pw, PASSWORD_DEFAULT);                     
                    $updatePwQuery = "UPDATE user SET pass_hash = ? WHERE user_id = ?";                     
                    $updatePwStmt = mysqli_prepare($conn, $updatePwQuery);                     
                    mysqli_stmt_bind_param($updatePwStmt, "si", $new_hash, $currentUserId);                                          
                    if (mysqli_stmt_execute($updatePwStmt)) {                         
                        $success_msg = "Password changed successfully!";                     
                    } else {                         
                        $error_msg = "Failed to change password.";                     
                    }                     
                    mysqli_stmt_close($updatePwStmt);                 
                }             
            } catch(Exception $e) {                 
                $error_msg = "Database error: " . $e->getMessage();             
            }         
        }     
    } 
}

// Fetch Latest Data for Display
try {     
    $query = "SELECT user_name, email, role, security_question FROM user WHERE user_id = ? AND role = 'admin'";     
    $stmt = mysqli_prepare($conn, $query);     
    mysqli_stmt_bind_param($stmt, "s", $currentUserId);     
    mysqli_stmt_execute($stmt);     
    $result = mysqli_stmt_get_result($stmt);     
    $admin_data = mysqli_fetch_assoc($result);     
    mysqli_stmt_close($stmt);     
    if(!$admin_data) {         
        die("Admin profile record not found.");     
    } 
} catch(Exception $e){     
    die("Database error: " . $e->getMessage()); 
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
    <link rel="stylesheet" href="../../assets/css/adminProfile.css">     
    <script src="../../assets/js/adminProfile.js" defer></script>     
    <script type="module" src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.esm.js"></script>     
    <script nomodule src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.js"></script>     
    <title>Account Settings - Campus Food Rescue</title> 
</head> 
<body>     
    <div class="dashboard-container">         
        <?php include '../../includes/sidebar.php'; ?>         
        <div class="main-content">             
            <div class="topbar-container">                 
                <?php include '../../includes/topbar.php'; ?>             
            </div>             
            <div class="content-container">                                  
                <!-- Status Notifications -->                 
                <?php if($success_msg): ?>                     
                    <div class="alert-banner alert-success">                         
                        <ion-icon name="checkmark-circle-outline"></ion-icon>
                        <span><?= $success_msg; ?></span>                     
                    </div>                 
                <?php endif; ?>                 
                <?php if($error_msg): ?>                     
                    <div class="alert-banner alert-danger">                         
                        <ion-icon name="alert-circle-outline"></ion-icon>
                        <span><?= $error_msg; ?></span>                     
                    </div>                 
                <?php endif; ?>                 

                <div class="user-page-header">                     
                    <div class="user-page-header-left">                         
                        <h1 class="page-title">Account Settings</h1>                         
                        <p class="page-subtitle">Manage your administrator profile, credential security, and notification preferences.</p>                     
                    </div>                     
                    <div class="user-page-header-right">                         
                        <button type="button" class="export-button" id="edit-profile-toggle">Edit Profile</button>                         
                        <button type="submit" form="profile-form" name="save_profile" class="add-user-button">Save Changes</button>                     
                    </div>                 
                </div>                 

                <div class="charts-section">                     
                    <!-- Left Column: Forms -->
                    <div class="charts-column">                         
                        <!-- Profile Card -->
                        <div class="chart-card">                             
                            <h2 class="profile-title">Profile Information</h2>                                                          
                            <form id="profile-form" method="POST" action="">                                 
                                <div class="profile-info-container">                                     
                                    <div class="profile-info-item">                                         
                                        <label class="info-label">Username</label>                                         
                                        <input type="text" name="user_name" class="info-value" value="<?= htmlspecialchars($admin_data['user_name']); ?>" readonly>                                     
                                    </div>                                     
                                    <div class="profile-info-item">                                         
                                        <label class="info-label">Role</label>                                         
                                        <div class="role-badge-display">Administrator</div>                                     
                                    </div>                                                                          
                                    <div class="profile-info-item">                                         
                                        <label class="info-label">Email Address</label>                                         
                                        <input type="email" name="email" class="info-value" value="<?= htmlspecialchars($admin_data['email']); ?>" readonly>                                     
                                    </div>                                 
                                </div>                             
                            </form>                         
                        </div>                         

                        <!-- Security & Password Card -->
                        <div class="chart-card">                             
                            <h2 class="profile-title">Change Password</h2>                                                                          
                            <form method="POST" action="">                                 
                                <input type="hidden" name="use_security_question" id="use_security_question" value="no">                                 
                                
                                <div class="password-form-grid">                                                                                  
                                    <!-- Mode 1: Current Password -->                                     
                                    <div class="profile-info-item" id="current-password-group">                                         
                                        <div class="form-label-row">                                             
                                            <label class="info-label">Current Password</label>                                             
                                            <a href="#" id="toggle-security-btn" class="text-link">Forgot Password?</a>                                         
                                        </div>                                         
                                        <input type="password" name="current_password" id="current_password" class="info-value" placeholder="Enter current password">                                     
                                    </div>                                     

                                    <!-- Mode 2: Security Question Reset -->                                     
                                    <div class="profile-info-item" id="security-question-group" style="display: none;">                                         
                                        <div class="form-label-row">                                             
                                            <label class="info-label text-danger">Question: <?= htmlspecialchars($admin_data['security_question']); ?></label>                                             
                                            <a href="#" id="toggle-password-btn" class="text-link">Use Password</a>                                         
                                        </div>                                         
                                        <input type="text" name="security_answer_input" id="security_answer_input" class="info-value" placeholder="Enter answer to reset">                                     
                                    </div>                                     

                                    <div class="profile-info-item">                                         
                                        <label class="info-label">New Password</label>                                         
                                        <input type="password" name="new_password" class="info-value" placeholder="Enter new password">                                     
                                    </div>                                                                          
                                    
                                    <div class="profile-info-item">                                         
                                        <label class="info-label">Confirm New Password</label>                                         
                                        <input type="password" name="confirm_password" class="info-value" placeholder="Confirm new password">                                     
                                    </div>                                 
                                </div>                                                                  
                                
                                <button type="submit" name="update_password" class="update-btn">Update Password</button>                             
                            </form>                         
                        </div>                     
                    </div>                     

                    <!-- Right Column: Alerts & Active Session -->
                    <div class="side-column">                         
                        <div class="side-panel-card">                             
                            <div class="side-panel-header">
                                <span class="panel-title">System Alerts</span>                             
                            </div>
                            <p class="panel-desc">Manage notification frequency for high-priority security triggers.</p>                                                          
                            
                            <div class="alert-toggle-list">                                 
                                <label class="alert-toggle-row">                                     
                                    <span>Instant alert on 3+ flagged listings</span>
                                    <input type="checkbox" class="modern-toggle" checked>
                                </label>                                 
                                <label class="alert-toggle-row">                                     
                                    <span>New provider verification requests</span>
                                    <input type="checkbox" class="modern-toggle" checked>
                                </label>                                 
                                <label class="alert-toggle-row">                                     
                                    <span>Daily system health report</span>
                                    <input type="checkbox" class="modern-toggle">
                                </label>                             
                            </div>                         
                        </div>                         

                        <div class="side-panel-card">                             
                            <div class="side-panel-header flex-between">
                                <span class="panel-title">Active Session</span>
                                <span class="badge-dot-live">Current</span>
                            </div>
                            <div class="session-info-box">
                                <div class="session-device-row">
                                    <ion-icon name="desktop-outline"></ion-icon>
                                    <div>
                                        <div class="session-title">Admin Console (Web)</div>
                                        <div class="session-meta">IP: <?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') ?> &bull; Active Now</div>
                                    </div>
                                </div>
                            </div>                         
                        </div>                     
                    </div>                 
                </div>             
            </div>         
        </div>     
    </div> 
</body> 
</html>