<nav class="navbar">
  <div class="nav-logo">
    <a href="<?= BASE_URL ?>/index.php">Campus Food Rescue</a>
  </div>

  <div class="nav-links">
    <a href="#">How It Works</a>
    <a href="#">Impact</a>
    <a href="#">About</a>
    <a href="#">FAQ</a>
  </div>

  <div class="nav-actions">
    <a class="nav-login" href="<?= BASE_URL ?>/pages/auth/login.php">Login</a>
    <a class="nav-signup" href="<?= BASE_URL ?>/pages/auth/register.php">Sign Up</a>
  </div>

  <button class="mobile-menu-btn" id="mobileMenuBtn">
        &#9776;
    </button>
</nav>

<nav class="mobile-menu" id="mobileMenu">

    <a href="#">How It Works</a>
    <a href="#">Impact</a>
    <a href="#">About</a>
    <a href="#">FAQ</a>

    <a href="<?= BASE_URL ?>/pages/auth/login.php">
        Login
    </a>

    <a href="<?= BASE_URL ?>/pages/auth/register.php">
        Sign Up
    </a>

</nav>