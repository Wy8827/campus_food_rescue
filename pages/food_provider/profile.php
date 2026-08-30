<?php
session_start();
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/_provider_helpers.php';

requireRole('provider');

$userId = (int)($_SESSION['user_id'] ?? 0);
$providerId = getProviderId($conn, $userId);
if (!$providerId) {
    die("No provider profile is linked to this account yet. Please contact support.");
}


// Gate: block all provider functionality until an admin approves the account
requireApprovedProvider($conn, $providerId);
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---------------- 1. Save store profile ----------------
    if (isset($_POST['save_profile'])) {
        $provider_name   = trim($_POST['provider_name'] ?? '');
        $location        = trim($_POST['location'] ?? '');
        $operating_hours = trim($_POST['operating_hours'] ?? '');
        $contact_number  = trim($_POST['contact_number'] ?? '');
        // Cuisine Category, Pickup Instructions, Primary Contact Person, and
        // Notification Preferences were removed — the `provider` table has
        // no matching columns for any of them, so this UPDATE previously
        // failed with an "unknown column" error every time it ran.

        if ($provider_name === '' || $location === '' || $contact_number === '') {
            $error_msg = "Store name, location, and contact number are required.";
        } else {
            $query = "UPDATE provider SET
                        provider_name = ?, location = ?, operating_hours = ?, contact_number = ?
                      WHERE provider_id = ? AND user_id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param(
                $stmt, "ssssii",
                $provider_name, $location, $operating_hours, $contact_number,
                $providerId, $userId
            );
            if (mysqli_stmt_execute($stmt)) {
                $success_msg = "Store profile updated successfully!";
            } else {
                $error_msg = "Failed to update store profile.";
            }
            mysqli_stmt_close($stmt);
        }
    }

    // ---------------- 2. Change password ----------------
    if (isset($_POST['update_password'])) {
        $current_pw = $_POST['current_password'] ?? '';
        $security_ans = $_POST['security_answer_input'] ?? '';
        $new_pw = $_POST['new_password'] ?? '';
        $confirm_pw = $_POST['confirm_password'] ?? '';
        $use_security_question = isset($_POST['use_security_question']) && $_POST['use_security_question'] === 'yes';

        if (empty($new_pw) || empty($confirm_pw)) {
            $error_msg = "Please fill in the new password fields.";
        } elseif (strlen($new_pw) < 6) {
            $error_msg = "New password must be at least 6 characters.";
        } elseif ($new_pw !== $confirm_pw) {
            $error_msg = "New passwords do not match.";
        } else {
            $authQuery = "SELECT pass_hash, security_answer FROM user WHERE user_id = ?";
            $authStmt = mysqli_prepare($conn, $authQuery);
            mysqli_stmt_bind_param($authStmt, "i", $userId);
            mysqli_stmt_execute($authStmt);
            $user = mysqli_fetch_assoc(mysqli_stmt_get_result($authStmt));
            mysqli_stmt_close($authStmt);

            $is_authorized = false;
            if ($user) {
                if ($use_security_question) {
                    if (empty($security_ans)) {
                        $error_msg = "Please provide your security answer.";
                    } elseif (password_verify(strtolower($security_ans), $user['security_answer'])) {
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
                $updatePwStmt = mysqli_prepare($conn, "UPDATE user SET pass_hash = ? WHERE user_id = ?");
                mysqli_stmt_bind_param($updatePwStmt, "si", $new_hash, $userId);
                if (mysqli_stmt_execute($updatePwStmt)) {
                    $success_msg = "Password changed successfully!";
                } else {
                    $error_msg = "Failed to change password.";
                }
                mysqli_stmt_close($updatePwStmt);
            }
        }
    }
}

// ---------------- Fetch latest data ----------------
$stmt = mysqli_prepare($conn, "SELECT * FROM provider WHERE provider_id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $providerId, $userId);
mysqli_stmt_execute($stmt);
$provider = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT user_name, email, security_question FROM user WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$userRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$provider) {
    die("Provider data not found.");
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
    <link rel="stylesheet" href="../../assets/css/provider/provider.css">
    <link rel="stylesheet" href="../../assets/css/provider/profile.css">
    <script src="../../assets/js/provider/profile.js" defer></script>
    <script type="module" src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.js"></script>
    <title>Manage Store Details</title>
</head>
<body>
    <div class="dashboard-container">
        <?php $provider_pending_claims_badge = getPendingClaimsCount($conn, $providerId); include '../../includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="topbar-container">
                <?php include '../../includes/topbar.php'; ?>
            </div>

            <div class="content-container">
                <?php if ($success_msg): ?><div class="alert-banner alert-success"><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
                <?php if ($error_msg): ?><div class="alert-banner alert-error"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

                <div class="user-page-header">
                    <div class="user-page-header-left">
                        <h1 class="page-title">Manage Store Details</h1>
                        <p class="page-subtitle">Update your public profile and store details.</p>
                    </div>
                    <div class="user-page-header-right">
                        <button type="button" class="export-button">Edit Profile</button>
                        <button type="submit" form="store-form" name="save_profile" class="add-user-button">Save Profile Changes</button>
                    </div>
                </div>

                <form id="store-form" method="POST" action="">
                    <div class="charts-section">
                        <div class="charts-column">
                            <div class="chart-card">
                                <h2 class="profile-title">Store Identity</h2>
                                <div class="profile-info-container">
                                    <div class="profile-info-item">
                                        <span class="info-label">Store Name</span>
                                        <input type="text" name="provider_name" class="info-value" value="<?= htmlspecialchars($provider['provider_name']) ?>" readonly>
                                    </div>
                                </div>
                                <span class="status-pill <?= $provider['provider_status'] === 'active' ? 'badge-active' : 'badge-pending' ?>">
                                    <?= $provider['provider_status'] === 'active' ? '✓ Verified Campus Vendor' : ucfirst(str_replace('_',' ', $provider['provider_status'])) ?>
                                </span>
                            </div>

                            <div class="chart-card">
                                <h2 class="profile-title">Location &amp; Pickup</h2>
                                <div class="profile-info-container" style="flex-direction: column; gap: 16px;">
                                    <div class="profile-info-item">
                                        <span class="info-label">Campus Location</span>
                                        <input type="text" name="location" class="info-value" value="<?= htmlspecialchars($provider['location']) ?>" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="side-column">
                            <div class="quick-links-container profile-hours-card">
                                <span class="quick-link-header" style="text-align:left; margin-top:0;">Hours &amp; Contact</span>
                                <div class="profile-info-container" style="flex-direction: column; gap: 16px;">
                                    <div class="profile-info-item">
                                        <span class="info-label">Operating Hours</span>
                                        <input type="text" name="operating_hours" class="info-value" value="<?= htmlspecialchars($provider['operating_hours'] ?? '') ?>" placeholder="e.g., Mon-Sun 7:00am-8:00pm" readonly>
                                    </div>
                                    <div class="profile-info-item">
                                        <span class="info-label">Contact Phone Number</span>
                                        <input type="text" name="contact_number" class="info-value" value="<?= htmlspecialchars($provider['contact_number']) ?>" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-card" style="margin-top:4px;">
                        <label style="display:flex; gap:10px; align-items:flex-start; font-size:13px; color:#344054;">
                            <input type="checkbox" required style="margin-top:3px;">
                            I certify that all surplus food donated through Campus Food Rescue complies with university food safety and hygienic guidelines. I understand my responsibilities as a verified vendor.
                        </label>
                    </div>
                </form>

                <div class="chart-card" style="margin-top:24px;">
                    <h3 class="profile-title" style="margin-top: 0;">Change Password</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="use_security_question" id="use_security_question" value="no">
                        <div class="profile-info-container" style="flex-direction: column; gap: 16px;">
                            <div class="profile-info-item" id="current-password-group">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <span class="info-label" style="margin: 0;">Current Password:</span>
                                    <a href="#" id="toggle-security-btn" style="font-size: 13px; color: #275300; font-weight: 600; text-decoration: none;">Forgot Password?</a>
                                </div>
                                <input type="password" name="current_password" id="current_password" class="info-value" placeholder="Enter current password">
                            </div>

                            <div class="profile-info-item" id="security-question-group" style="display: none;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <span class="info-label" style="margin: 0; color: #B42318;">Security Question: <?= htmlspecialchars($userRow['security_question'] ?? '') ?></span>
                                    <a href="#" id="toggle-password-btn" style="font-size: 13px; color: #275300; font-weight: 600; text-decoration: none;">I remember my password</a>
                                </div>
                                <input type="text" name="security_answer_input" id="security_answer_input" class="info-value" placeholder="Enter your answer to reset">
                            </div>

                            <div class="profile-info-item">
                                <span class="info-label">New Password:</span>
                                <input type="password" name="new_password" class="info-value" placeholder="Enter new password">
                            </div>
                            <div class="profile-info-item">
                                <span class="info-label">Confirm New Password:</span>
                                <input type="password" name="confirm_password" class="info-value" placeholder="Confirm new password">
                            </div>
                        </div>
                        <button type="submit" name="update_password" class="update-btn">Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</body>
</html>