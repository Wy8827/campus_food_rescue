<?php 
    $current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <span><img src="../../assets/images/logo.png" alt="Campus Food Rescue Logo" width="50" style="margin-left: 6px;"></span>
        <span class="brand-text">Campus Food Rescue <br> <span class="brand-subtext">Admin Portal</span></span>
    </div>
        
    <div class="sidebar-profile">
        <span><ion-icon name="person-circle-outline" class="profile-icon"></ion-icon></span>
        <span class="profile-text"><?php echo $_SESSION['username']; ?></span>
    </div>

    <div class="sidebar-navigation-container">
        <ul class="sidebar-navigation">
            <li class="nav-container <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                <a href="dashboard.php">
                    <span class="nav-icon"><img src="../../assets/images/overview.png" alt="System Overview Logo" width="18" ></span>
                    <span class="nav-text">System Overview</span>
                </a>
            </li>

            <li class="nav-container <?php echo ($current_page == 'moderation.php') ? 'active' : ''; ?>">
                <a href="moderation.php">
                    <img src="../../assets/images/moderation.png" alt="List Moderation Logo" width="18" class="nav-icon">
                    <span class="nav-text">List Moderation</span>
                </a>
            </li>

            <li class="nav-container <?php echo ($current_page == 'userManagement.php') ? 'active' : ''; ?>">
                <a href="userManagement.php">
                    <img src="../../assets/images/usermanagement.png" alt="User Management Logo" width="18" class="nav-icon">
                    <span class="nav-text">User Management</span>
                </a>

            </li>

            <li class="nav-container <?php echo ($current_page == 'impactAnalytics.php') ? 'active' : ''; ?>">
                <a href="impactAnalytics.php">
                    <img src="../../assets/images/impact.png" alt="Impact Analytics Logo" width="18" class="nav-icon">
                    <span class="nav-text">Impact Analytics</span>
                </a>

            </li>
    
            <li class="nav-container <?php echo ($current_page == 'auditLog.php') ? 'active' : ''; ?>">
                <a href="auditLog.php">
                    <img src="../../assets/images/auditlog.png" alt="Audit Log Logo" width="18" class="nav-icon">
                    <span class="nav-text">Audit Log</span>
                </a>
            </li>
        </ul>
    </div>

    <footer class="sidebar-footer">
        <ul class="sidebar-navigation">
            <li class="footer-nav-container <?php echo ($current_page == 'adminprofile.php') ? 'active' : ''; ?>">
                <a href="adminprofile.php">
                    <img src="../../assets/images/setting.png" alt="Settings Logo" width="18" class="nav-icon">
                    <span class="nav-text">Settings</span>
                </a>
            </li>
            <li class="footer-nav-container">
                <a href="support.php">
                    <img src="../../assets/images/support.png" alt="Support Logo" width="18" class="nav-icon">
                    <span class="nav-text">Support</span>
                </a>
            </li>
        </ul>
    </footer>
</aside> 