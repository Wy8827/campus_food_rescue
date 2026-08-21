<?php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';

if (isLoggedIn()) {
  $role = getRole();
  header("Location: " . BASE_URL . "/pages/$role/dashboard.php");
  exit();
}

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['txtEmail'] ?? '');
  $password = $_POST['txtPassword'] ?? '';

  if(empty($email) || empty($password)) {
    $error_msg = 'Please fill in all fields.';
  } else {
    // Replace PDO with mysqli_prepare and use the ? placeholder[cite: 3]
    $query = "SELECT * FROM `user` WHERE `email` = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    
    // Bind the email parameter as a string ("s")[cite: 3]
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    
    // Fetch the associative array result[cite: 3]
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    // The password verification and routing logic remains unchanged[cite: 3]
    if ($user && password_verify($password, $user['pass_hash'])) {
      if($user['account_status'] === 'banned') {
        $error_msg = 'Your account has been banned. Please contact support.';
      } else {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user_name'] = $user['user_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['email'] = $user['email'];
      
        if($user['role'] === 'admin') {
          header("Location: " . BASE_URL . "/pages/admin/dashboard.php");
        } elseif ($user['role'] === '') {
          header("Location: " . BASE_URL . "/pages/provider/dashboard.php");
        } else {
          header("Location: " . BASE_URL . "/pages/student/dashboard.php");
        }
        exit();
      } 
    } else {
      $error_msg = 'Invalid email or password.';
    }
  }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login Page</title>
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

      <?php if (!empty($error_msg)): ?>
        <div style="color: #d9534f; background-color: #fdf7f7; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; text-align: center; border: 1px solid #ebccd1;">
          <?= htmlspecialchars($error_msg) ?>
        </div>
      <?php endif; ?>

      <form action="" method="POST">
        <!-- Email -->
        <div class="form-group">
          <label for="txtEmail">Email Address</label>
          <div class="input-wrap">
            <img src="../../assets/images/email-icon.png" alt="">
              <input type="email" id="txtEmail" name="txtEmail" placeholder="admin@institution.edu" required>
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
              <input type="password" id="txtPassword" name="txtPassword" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" required>
          </div>
        </div>

        <button type="submit" class="btn-submit">Sign In</button>
      </form>

      <p class="auth-switch">
                Don't have an account? <a href="register.php">Sign Up</a>
      </p>

    </div>
  </div>

  <?php include '../../includes/public_footer.php'; ?>

  <script src="../../assets/js/navbar.js"></script>
</body>
</html>