<?php 
    $current_page = basename($_SERVER['PHP_SELF']);
    $role = $_SESSION['role'] ?? 'student';

    $portal_titles = [
        'admin'    => 'Admin Portal',
        'provider' => 'Provider Portal',
        'student'  => 'Student Portal',
    ];

    $portal_title = $portal_titles[$role] ?? 'Portal';

    $menus = [
        'admin' => [
            ['url' => 'dashboard.php',       'title' => 'System Overview',  'icon' => '../../assets/images/overview.png'],
            ['url' => 'moderation.php',      'title' => 'List Moderation',  'icon' => '../../assets/images/moderation.png'],
            ['url' => 'userManagement.php',  'title' => 'User Management',  'icon' => '../../assets/images/usermanagement.png'],
            ['url' => 'impactAnalytics.php', 'title' => 'Impact Analytics', 'icon' => '../../assets/images/impact.png'],
            ['url' => 'auditLog.php',        'title' => 'Audit Log',        'icon' => '../../assets/images/auditlog.png'],
        ],
        'provider' => [
            ['url' => 'dashboard.php',       'title' => 'Provider Overview','icon' => '../../assets/images/overview.png'],
            ['url' => 'manageListings.php',  'title' => 'My Listings',      'icon' => '../../assets/images/moderation.png'],
            ['url' => 'claimVerification.php','title' => 'Verify Claims',   'icon' => '../../assets/images/auditlog.png'],
            ['url' => 'impactReport.php',    'title' => 'Donation Impact',  'icon' => '../../assets/images/impact.png'],
        ],
        'student' => [
            ['url' => 'dashboard.php',       'title' => 'Browse Food',      'icon' => '../../assets/images/overview.png'],
            ['url' => 'myClaims.php',        'title' => 'My Claims',        'icon' => '../../assets/images/auditlog.png'],
            ['url' => 'impactSummary.php',   'title' => 'My Impact',        'icon' => '../../assets/images/impact.png'],
        ],
    ];

    $current_menu = $menus[$role] ?? $menus['student'];

    $profile_pages = [
        'admin'    => 'adminprofile.php',
        'provider' => 'providerProfile.php',
        'student'  => 'studentProfile.php',
    ];
    
    $profile_page = $profile_pages[$role] ?? 'profile.php';
?>

<aside class="sidebar">
    <div class="sidebar-header">
            <span><img src="../../assets/images/logo.png" alt="Campus Food Rescue Logo" width="50" style="margin-left: 6px;"></span>
            <span class="brand-text">Campus Food Rescue <br> <span class="brand-subtext"><?= htmlspecialchars($portal_title) ?></span></span>
    </div>

    <div class="sidebar-navigation-container">
        <ul class="sidebar-navigation">
            <?php foreach ($current_menu as $item): ?>
                <li class="nav-container <?= ($current_page == $item['url']) ? 'active' : '' ?>">
                    <a href="<?= htmlspecialchars($item['url']) ?>">
                        <span class="nav-icon"><img src="<?= htmlspecialchars($item['icon']) ?>" alt="<?= htmlspecialchars($item['title']) ?>"></span>
                        <span class="nav-text"><?= htmlspecialchars($item['title']) ?></span>
                    </a>
                </li>
            <?php endforeach;?>
        </ul>
    </div>

    <footer class="sidebar-footer">
        <ul class="sidebar-navigation">
            <li class="footer-nav-container <?= ($current_page == $profile_page) ? 'active' : ''; ?>">
                <a href="<?= htmlspecialchars($profile_page) ?>">
                    <img src="../../assets/images/setting.png" alt="Settings Logo" width="18" class="nav-icon">
                    <span class="nav-text">Settings</span>
                </a>
            </li>
            <li class="footer-nav-container <?= ($current_page == 'support.php') ? 'active' : ''; ?>">
                <a href="support.php">
                    <img src="../../assets/images/support.png" alt="Support Logo" width="18" class="nav-icon">
                    <span class="nav-text">Support</span>
                </a>
            </li>
        </ul>
    </footer>
</aside> 