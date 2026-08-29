<?php
$userName = $_SESSION['user_name'] ?? 'student';
$roleLower = strtolower($_SESSION['role'] ?? 'student');
$email = $_SESSION['email'] ?? 'student@gmail.com';
$userId = $_SESSION['user_id'] ?? 1;

$words = explode(" ", $userName);
$initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));

// Route the avatar/profile link to the correct settings page per role
$topbar_profile_pages = [
    'admin'    => 'adminprofile.php',
    'provider' => 'profile.php',
    'student'  => 'studentprofile.php',
];
$topbar_profile_page = $topbar_profile_pages[$roleLower] ?? 'profile.php';
?>

<header class="topbar">
    <div class="topbar-left">
        <span><img src="../../assets/images/logo.png" alt="Campus Food Rescue Logo" width="40" style="margin-left: 6px;"></span>
        <span class="topbar-title">Campus Food Rescue</span>
    </div>

    <div class="topbar-right">
        <a class="profile-pic" href="<?= htmlspecialchars($topbar_profile_page) ?>" style="gap: 12px;">
            <div class="avatar avatar-<?= $roleLower ?>"><?= $initials ?></div>
            <div class="user-info" style="display: flex; flex-direction: column; text-align: left; line-height: 1.2;">
                <div class="user-name" style="font-weight: 600; font-size: 14px; color: #111827; margin-bottom: 2px;">
                    <?= htmlspecialchars($userName) ?>
                </div>
            </div>
        </a>

        <button class="logout-btn" onclick="window.location.href='../../pages/auth/logout.php'">Logout</button>
    </div>
</header>