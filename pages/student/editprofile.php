<?php
session_start();
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
requireRole('student');

$studentId = (int) ($_SESSION['user_id'] ?? 0);
$profileError = '';
$passwordError = '';
$profileSuccess = '';
$passwordSuccess = '';

$userStmt = mysqli_prepare($conn, "SELECT user_name, email FROM user WHERE user_id = ? AND role = 'student' LIMIT 1");
mysqli_stmt_bind_param($userStmt, 'i', $studentId);
mysqli_stmt_execute($userStmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($userStmt));

if (!$user) {
    die('Student account could not be found.');
}

$userName = $user['user_name'];
$email = $user['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $passwordError = 'Please fill in your current password, new password, and confirmation.';
        } elseif (strlen($newPassword) < 6) {
            $passwordError = 'New password must be at least 6 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $passwordError = 'New passwords do not match.';
        } else {
            $passwordStmt = mysqli_prepare($conn, "SELECT pass_hash FROM user WHERE user_id = ? AND role = 'student' LIMIT 1");
            mysqli_stmt_bind_param($passwordStmt, 'i', $studentId);
            mysqli_stmt_execute($passwordStmt);
            $passwordUser = mysqli_fetch_assoc(mysqli_stmt_get_result($passwordStmt));
            mysqli_stmt_close($passwordStmt);

            if (!$passwordUser || !password_verify($currentPassword, $passwordUser['pass_hash'])) {
                $passwordError = 'Incorrect current password.';
            } else {
                $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $updatePasswordStmt = mysqli_prepare($conn, "UPDATE user SET pass_hash = ? WHERE user_id = ? AND role = 'student'");
                mysqli_stmt_bind_param($updatePasswordStmt, 'si', $newPasswordHash, $studentId);

                if (mysqli_stmt_execute($updatePasswordStmt)) {
                    $passwordSuccess = 'Password changed successfully.';
                } else {
                    $passwordError = 'Unable to change your password. Please try again.';
                }
                mysqli_stmt_close($updatePasswordStmt);
            }
        }
    } else {
        $userName = trim($_POST['user_name'] ?? '');
        $email = $user['email'];

        if ($userName === '' || strlen($userName) > 30) {
            $profileError = 'Please enter a name between 1 and 30 characters.';
        } else {
            $updateStmt = mysqli_prepare($conn, "UPDATE user SET user_name = ? WHERE user_id = ? AND role = 'student'");
            mysqli_stmt_bind_param($updateStmt, 'si', $userName, $studentId);
            mysqli_stmt_execute($updateStmt);
            $_SESSION['user_name'] = $userName;
            header('Location: studentprofile.php?updated=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/topbar.css">
    <link rel="stylesheet" href="../../assets/css/student/studentprofile.css">
    <title>Edit Profile | Campus Food Rescue</title>
</head>
<body>
<div class="dashboard-container">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="topbar-container">
            <?php include '../../includes/topbar.php'; ?>
        </div>

        <div class="content-container profile-page">
            <a href="studentprofile.php" class="back-link">&larr; Back to Settings</a>
            <h1 class="page-title">Edit Profile</h1>
            <p class="page-subtitle">Update your account details.</p>

            <section class="profile-card edit-profile-card">
                <?php if ($profileError): ?>
                    <div class="edit-profile-error"><?= htmlspecialchars($profileError) ?></div>
                <?php endif; ?>
                <?php if ($profileSuccess): ?>
                    <div class="edit-profile-success"><?= htmlspecialchars($profileSuccess) ?></div>
                <?php endif; ?>

                <form method="POST" class="edit-profile-form">
                    <label for="user_name">Full name</label>
                    <input id="user_name" name="user_name" type="text" maxlength="30" value="<?= htmlspecialchars($userName) ?>" required>

                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" maxlength="40" value="<?= htmlspecialchars($email) ?>" readonly>

                    <div class="edit-profile-actions">
                        <button type="submit" class="claim-button">Save Changes</button>
                        <a href="studentprofile.php" class="edit-profile-cancel">Cancel</a>
                    </div>
                </form>
            </section>

            <section class="profile-card edit-profile-card">
                <h2 class="section-heading-title">Change Password</h2>
                <?php if ($passwordError): ?>
                    <div class="edit-profile-error"><?= htmlspecialchars($passwordError) ?></div>
                <?php endif; ?>
                <?php if ($passwordSuccess): ?>
                    <div class="edit-profile-success"><?= htmlspecialchars($passwordSuccess) ?></div>
                <?php endif; ?>
                <form method="POST" class="edit-profile-form">
                    <label for="current_password">Current password</label>
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>

                    <label for="new_password">New password</label>
                    <input id="new_password" name="new_password" type="password" minlength="6" autocomplete="new-password" required>

                    <label for="confirm_password">Confirm new password</label>
                    <input id="confirm_password" name="confirm_password" type="password" minlength="6" autocomplete="new-password" required>

                    <div class="edit-profile-actions">
                        <button type="submit" name="update_password" class="claim-button">Change Password</button>
                    </div>
                </form>
            </section>
        </div>
    </main>
</div>
</body>
</html>
