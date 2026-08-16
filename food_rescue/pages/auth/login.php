<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/public_navbar_footer.css">
  <link rel="stylesheet" href="../../assets/css/auth.css">


</head>
<body>
  <?php include '../../includes/public_navbar.php'; ?>

  <div class="auth-page">
    <div class="auth-card">
      <div class="auth-icon">
        <img src="../../assets/images/auth-icon.png" alt="">
      </div>

      <h1 class="auth-title">Welcome Back</h1>
      <p class="auth-sub">Sign in to manage institutional logistics and sustainability tracking.</p>

      <form action="" method="POST">
        <!-- Email -->
        <div class="form-group">
          <label for="email">Email Address</label>
          <div class="input-wrap">
            <img src="../../assets/images/email-icon.png" alt="">
              <input type="email" id="txtEmail" name="txtEmail" placeholder="admin@institution.edu">
          </div>
        </div>

          <!-- Password -->
        <div class="form-group">
          <div class="label-row">
            <label for="txtPassword">Password</label>
            <a href="#" class="forgot-link">Forgot password?</a>
          </div> 
          <div class="input-wrap">
            <img src="../../assets/images/password-icon.png" alt="">
              <input type="password" id="txtPassword" name="txtPassword" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
          </div>
        </div>

        <button type="submit" class="btn-submit">Sign In</button>
      </form>

      <p class="auth-switch">
                Don't have an account? <a href="register.php">Sign Up</a>
      </p>

    </div>
  </div>

  <script src="../../assets/js/navbar.js"></script>
</body>
</html>