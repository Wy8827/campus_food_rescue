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
            ['url' => 'dashboard.php',       'title' => 'Dashboard',        'icon' => '../../assets/images/overview.png'],
            ['url' => 'createListing.php',   'title' => 'Create Listing',   'icon' => '../../assets/images/adduser.png'],
            ['url' => 'manageListings.php',  'title' => 'Manage Listings',  'icon' => '../../assets/images/moderation.png'],
            ['url' => 'claimTracker.php',    'title' => 'Claim Tracker',    'icon' => '../../assets/images/usermanagement.png', 'badge_var' => 'provider_pending_claims_badge'],
            ['url' => 'qrScanner.php',       'title' => 'QR Scanner',       'icon' => '../../assets/images/auditlog.png'],
            ['url' => 'impact.php',          'title' => 'My Impact',        'icon' => '../../assets/images/impact.png'],
        ],
        'student' => [
            ['url' => 'dashboard.php',       'title' => 'Browse Food',      'icon' => '../../assets/images/overview.png'],
            ['url' => 'claims.php',          'title' => 'My Claims',        'icon' => '../../assets/images/auditlog.png'],
            ['url' => 'leaderboard.php',     'title' => 'Leaderboard',      'icon' => '../../assets/images/impact.png'],
        ],
    ];

    $current_menu = $menus[$role] ?? $menus['student'];

    $profile_pages = [
        'admin'    => 'adminprofile.php',
        'provider' => 'profile.php',
        'student'  => 'studentprofile.php',
    ];
    
    $profile_page = $profile_pages[$role] ?? 'profile.php';

    // Every nav/profile URL above is a bare relative filename (e.g.
    // 'dashboard.php'), which only resolves correctly when the CURRENT
    // page happens to physically live inside that role's own folder
    // (pages/food_provider/, pages/student/, pages/admin/). That's true
    // for every page except pages/auth/support.php — the one shared
    // support page every role links to from here. Prefixing every link
    // with the role's real folder makes the sidebar work correctly no
    // matter which folder the including page actually lives in.
    $role_folders = [
        'admin'    => 'admin',
        'provider' => 'food_provider',
        'student'  => 'student',
    ];
    $role_base = BASE_URL . '/pages/' . ($role_folders[$role] ?? 'student') . '/';
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <span><img src="../../assets/images/logo.png" alt="Campus Food Rescue Logo" width="50" style="margin-left: 6px;"></span>
        <span class="brand-text">Campus Food Rescue <br> <span class="brand-subtext"><?= htmlspecialchars($portal_title) ?></span></span>
    </div>

    <div class="sidebar-navigation-container">
        <ul class="sidebar-navigation">
            <?php foreach ($current_menu as $item):
                $badgeCount = null;
                if (isset($item['badge_var']) && isset($GLOBALS[$item['badge_var']])) {
                    $badgeCount = (int)$GLOBALS[$item['badge_var']];
                }
            ?>
                <li class="nav-container <?= ($current_page == $item['url']) ? 'active' : '' ?>">
                    <a href="<?= $role_base . htmlspecialchars($item['url']) ?>">
                        <span class="nav-icon"><img src="<?= htmlspecialchars($item['icon']) ?>" alt="<?= htmlspecialchars($item['title']) ?>"></span>
                        <span class="nav-text"><?= htmlspecialchars($item['title']) ?></span>
                        <?php if ($badgeCount !== null && $badgeCount > 0): ?>
                            <span class="sb-badge"><?= $badgeCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach;?>
        </ul>
    </div>

    <footer class="sidebar-footer">
        <ul class="sidebar-navigation">
            <li class="footer-nav-container <?= ($current_page == $profile_page) ? 'active' : ''; ?>">
                <a href="<?= $role_base . htmlspecialchars($profile_page) ?>">
                    <img src="../../assets/images/setting.png" alt="Settings Logo" width="18" class="nav-icon">
                    <span class="nav-text">Settings</span>
                </a>
            </li>
            <li class="footer-nav-container <?= ($current_page == 'support.php') ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>/pages/auth/support.php">
                    <img src="../../assets/images/support.png" alt="Support Logo" width="18" class="nav-icon">
                    <span class="nav-text">Support</span>
                </a>
            </li>
        </ul>
    </footer>
</aside>