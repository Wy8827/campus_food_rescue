<header class="topbar">
    <div class="topbar-left">
        <span><img src="../../assets/images/logo.png" alt="Campus Food Rescue Logo" width="40" style="margin-left: 6px;"></span>
        <span class="topbar-title">Campus Food Rescue</span>
    </div>

    <div class="topbar-right">
        <a class="profile-pic" href="adminprofile.php">
            <span><ion-icon name="person-circle-outline"></ion-icon></span>
            <span class="profile-text"><?php echo $_SESSION['user_name']; ?></span>
        </a>

        <button class="logout-btn" onclick="window.location.href='../../pages/auth/logout.php'">Logout</button>
    </div>

</header>