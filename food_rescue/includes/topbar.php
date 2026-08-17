<header class="topbar">
    
    <div class="search-bar-container">
        <input type="text" placeholder="Search Console..." class="search-bar">
    </div>

    
    <button class="export-rpt">Export Report</button>
    
    <span style="color: #C2C9B7;">|</span>

    <ion-icon name="notifications-outline"></ion-icon>


    <a class="profile-pic" href="adminprofile.php">
        <span><ion-icon name="person-circle-outline"></ion-icon></span>
        <span class="profile-text"><?php echo $_SESSION['username']; ?></span>
    </a>

    <button class="logout-btn" onclick="window.location.href='../../logout.php'">Logout</button>
    

</header>