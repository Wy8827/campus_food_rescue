<?php
session_start();
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
requireRole('student');

$studentId = (int) ($_SESSION['user_id'] ?? 0);
$error = '';

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
    $userName = trim($_POST['user_name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($userName === '' || strlen($userName) > 30) {
        $error = 'Please enter a name between 1 and 30 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 40) {
        $error = 'Please enter a valid email address.';
    } else {
        $duplicateStmt = mysqli_prepare($conn, "SELECT user_id FROM user WHERE email = ? AND user_id <> ? LIMIT 1");
        mysqli_stmt_bind_param($duplicateStmt, 'si', $email, $studentId);
        mysqli_stmt_execute($duplicateStmt);

        if (mysqli_num_rows(mysqli_stmt_get_result($duplicateStmt)) > 0) {
            $error = 'That email address is already in use.';
        } else {
            $updateStmt = mysqli_prepare($conn, "UPDATE user SET user_name = ?, email = ? WHERE user_id = ? AND role = 'student'");
            mysqli_stmt_bind_param($updateStmt, 'ssi', $userName, $email, $studentId);
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
                <?php if ($error): ?>
                    <div class="edit-profile-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" class="edit-profile-form">
                    <label for="user_name">Full name</label>
                    <input id="user_name" name="user_name" type="text" maxlength="30" value="<?= htmlspecialchars($userName) ?>" required>

                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" maxlength="40" value="<?= htmlspecialchars($email) ?>" required>

                    <div class="edit-profile-actions">
                        <button type="submit" class="claim-button">Save Changes</button>
                        <a href="studentprofile.php" class="edit-profile-cancel">Cancel</a>
                    </div>
                </form>
            </section>
        </div>
    </main>
</div>
</body>
</html>
