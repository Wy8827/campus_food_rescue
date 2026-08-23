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
                // Update username and email using mysqli[cite: 8]
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

    // 2. Handle "Update Password" with Dual Authentication
    if (isset($_POST['update_password'])) {
        $current_pw = $_POST['current_password'] ?? '';
        $security_ans = $_POST['security_answer_input'] ?? '';
        $new_pw = $_POST['new_password'];
        $confirm_pw = $_POST['confirm_password'];
        
        // Determine which verification method the user is using
        $use_security_question = isset($_POST['use_security_question']) && $_POST['use_security_question'] === 'yes';
        
        if (empty($new_pw) || empty($confirm_pw)) {
            $error_msg = "Please fill in the new password fields.";
        } elseif ($new_pw !== $confirm_pw) {
            $error_msg = "New passwords do not match.";
        } else {
            try {
                // Fetch the current password hash and security answer hash from the database[cite: 7, 8]
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
                        // Verification Method A: Security Question[cite: 7]
                        if (empty($security_ans)) {
                            $error_msg = "Please provide your security answer.";
                        } elseif (password_verify($security_ans, $user['security_answer'])) {
                            $is_authorized = true;
                        } else {
                            $error_msg = "Incorrect security answer.";
                        }
                    } else {
                        // Verification Method B: Current Password[cite: 7]
                        if (empty($current_pw)) {
                            $error_msg = "Please enter your current password.";
                        } elseif (password_verify($current_pw, $user['pass_hash'])) {
                            $is_authorized = true;
                        } else {
                            $error_msg = "Incorrect current password.";
                        }
                    }
                }
                
                // If authorized, hash the new password and update the database[cite: 7, 8]
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

// ==========================================
// Fetch Latest Data for Display
// ==========================================
try {
    $query = "SELECT user_name, email, role, security_question FROM user WHERE user_id = ? AND role = 'admin'";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $currentUserId);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $admin_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if(!$admin_data) {
        die("Admin data not found.");
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
                
                <!-- Display Success or Error Messages -->
                <?php if($success_msg): ?>
                    <div style="color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 500;">
                        <?php echo $success_msg; ?>
                    </div>
                <?php endif; ?>
                <?php if($error_msg): ?>
                    <div style="color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 500;">
                        <?php echo $error_msg; ?>
                    </div>
                <?php endif; ?>

                <div class="user-page-header">
                    <div class="user-page-header-left">
                        <h1 class="page-title">Account Settings</h1>
                        <p class="page-subtitle">Manage your administrator profile, security preferences, and system alerts.</p>
                    </div>

                    <div class="user-page-header-right">
                        <!-- Button type prevents accidental form submission -->
                        <button type="button" class="export-button">Edit Profile</button>
                        <!-- Form attribute targets the profile-form below -->
                        <button type="submit" form="profile-form" name="save_profile" class="add-user-button">Save Changes</button>
                    </div>
                </div>

                <div class="charts-section">
                    <div class="charts-column">
                        <div class="chart-card">
                            <h2 class="profile-title">Profile Information</h2>
                            
                            <!-- Form wrapper for profile updates -->
                            <form id="profile-form" method="POST" action="">
                                <div class="profile-info-container">
                                    <div class="profile-info-item">
                                        <span class="info-label">Username:</span> <br>
                                        <input type="text" name="user_name" class="info-value" value="<?php echo htmlspecialchars($admin_data['user_name']); ?>" readonly>
                                    </div>

                                    <div class="profile-info-item">
                                        <span class="info-label">Role:</span> <br>
                                        <div class="role-value"><span type="text" class="" value="<?php echo htmlspecialchars($admin_data['role']); ?>" readonly>Administrator</span></div>
                                    </div>
                                    
                                    <div class="profile-info-item">
                                        <span class="info-label">Email:</span> <br>
                                        <input type="email" name="email" class="info-value" value="<?php echo htmlspecialchars($admin_data['email']); ?>" readonly>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="chart-card">
                            <div class="security-settings-container">
                                <div class="security-setting-item">
                                    <h3 class="profile-title" style="margin-top: 0;">Change Password</h3>
                                    
                                    <!-- Form wrapper for password updates -->
                                    <form method="POST" action="">
                                        <!-- Hidden input to track verification method -->
                                        <input type="hidden" name="use_security_question" id="use_security_question" value="no">

                                        <div class="profile-info-container" style="flex-direction: column; gap: 16px;">
                                            
                                            <!-- Mode 1: Current Password Group -->
                                            <div class="profile-info-item" id="current-password-group">
                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                                    <span class="info-label" style="margin: 0;">Current Password:</span>
                                                    <a href="#" id="toggle-security-btn" style="font-size: 13px; color: #275300; font-weight: 600; text-decoration: none;">Forgot Password?</a>
                                                </div>
                                                <input type="password" name="current_password" id="current_password" class="info-value" placeholder="Enter current password" readonly>
                                            </div>

                                            <!-- Mode 2: Security Question Group (Hidden by default) -->
                                            <div class="profile-info-item" id="security-question-group" style="display: none;">
                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                                    <span class="info-label" style="margin: 0; color: #B42318;">Security Question: <?php echo htmlspecialchars($admin_data['security_question']); ?></span>
                                                    <a href="#" id="toggle-password-btn" style="font-size: 13px; color: #275300; font-weight: 600; text-decoration: none;">I remember my password</a>
                                                </div>
                                                <input type="text" name="security_answer_input" id="security_answer_input" class="info-value" placeholder="Enter your answer to reset" readonly>
                                            </div>

                                            <!-- New Password Fields -->
                                            <div class="profile-info-item">
                                                <span class="info-label">New Password:</span>
                                                <input type="password" name="new_password" class="info-value" placeholder="Enter new password" readonly>
                                            </div>
                                            
                                            <div class="profile-info-item">
                                                <span class="info-label">Confirm New Password:</span>
                                                <input type="password" name="confirm_password" class="info-value" placeholder="Confirm new password" readonly>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" name="update_password" class="update-btn">Update Password</button>
                                    </form>
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
    
</body>
</html>