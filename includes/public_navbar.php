<nav class="navbar">
  <div class="nav-logo">
    <a href="<?= BASE_URL ?>/index.php">Campus Food Rescue</a>
  </div>

  <!-- Desktop Navigation Links -->
  <div class="nav-links">
    <a href="<?= BASE_URL ?>/index.php#how-it-works">How It Works</a>
    <a href="<?= BASE_URL ?>/index.php#impact">Impact</a>
    <a href="<?= BASE_URL ?>/index.php#about">About</a>
    <a href="<?= BASE_URL ?>/index.php#faq">FAQ</a>
    <a href="<?= BASE_URL ?>/pages/auth/support.php">Support</a>
  </div>

  <!-- Auth Actions -->
  <div class="nav-actions">
    <a class="nav-login" href="<?= BASE_URL ?>/pages/auth/login.php">Login</a>
    <a class="nav-signup" href="<?= BASE_URL ?>/pages/auth/register.php">Sign Up</a>
  </div>

  <!-- Mobile Menu Trigger -->
  <button class="mobile-menu-btn" id="mobileMenuBtn">
    &#9776;
  </button>
</nav>

<!-- Mobile Navigation Drawer -->
<nav class="mobile-menu" id="mobileMenu">
  <a href="<?= BASE_URL ?>/index.php#how-it-works">How It Works</a>
  <a href="<?= BASE_URL ?>/index.php#impact">Impact</a>
  <a href="<?= BASE_URL ?>/index.php#about">About</a>
  <a href="<?= BASE_URL ?>/index.php#faq">FAQ</a>
  <a href="<?= BASE_URL ?>/pages/auth/support.php">Support</a>

  <a href="<?= BASE_URL ?>/pages/auth/login.php">Login</a>
  <a href="<?= BASE_URL ?>/pages/auth/register.php">Sign Up</a>
</nav>