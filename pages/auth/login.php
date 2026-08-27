<?php
// Load configuration, session management, and database connection[cite: 1]
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';

// ============================================================================
// AJAX Endpoint: Fetch Security Question for a Given Email
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'get_security_question') {
  header('Content-Type: application/json');
  $email = trim($_GET['email'] ?? '');

  if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email address.']);
    exit();
  }

  $query = "SELECT security_question FROM `user` WHERE `email` = ? LIMIT 1";
  $stmt  = mysqli_prepare($conn, $query);

  if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user   = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($user && !empty($user['security_question'])) {
      echo json_encode([
        'status'   => 'success',
        'question' => $user['security_question']
      ]);
    } else {
      echo json_encode([
        'status'  => 'error',
        'message' => 'No security question found for this email account.'
      ]);
    }
  } else {
    echo json_encode(['status' => 'error', 'message' => 'Database query failed.']);
  }
  exit();
}

// Redirect already logged-in users away from the auth screen on standard GET visits[cite: 1]
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isLoggedIn()) {
  $role = getRole();
  $roleFolders = [
    'admin'    => 'admin',
    'provider' => 'food_provider',
    'student'  => 'student'
  ];
  $folder = $roleFolders[$role] ?? 'student';
  header("Location: " . BASE_URL . "/pages/$folder/dashboard.php");
  exit();
}

$error_msg   = '';
$success_msg = '';
$active_tab  = 'login'; // Controls visible UI section ('login' or 'forgot')

// Flash notifications passed via query parameters[cite: 1]
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  if (isset($_GET['registered'])) {
    $success_msg = 'Account created! Please log in.';
  } elseif (isset($_GET['pending'])) {
    $success_msg = 'Application submitted! You can log in now, but you\'ll need admin approval before you can post listings.';
  }
}

// ============================================================================
// Form Handling (POST Requests)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $form_type = $_POST['form_type'] ?? 'login';

  // --------------------------------------------------------------------------
  // 1. Standard Login Authentication
  // --------------------------------------------------------------------------
  if ($form_type === 'login') {
    $email    = trim($_POST['txtEmail'] ?? '');
    $password = $_POST['txtPassword'] ?? '';

    if (empty($email) || empty($password)) {
      $error_msg = 'Please fill in all fields.';
    } else {
      $query = "SELECT * FROM `user` WHERE `email` = ? LIMIT 1";
      $stmt  = mysqli_prepare($conn, $query);

      if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($user && password_verify($password, $user['pass_hash'])) {
          if ($user['account_status'] === 'banned') {
            $error_msg = 'Your account has been banned. Please contact support.';
          } elseif ($user['account_status'] === 'throttled') {
            // Compare throttled_until against MySQL's own NOW(), not PHP's
            // date()/time() — the value was written with MySQL's
            // DATE_ADD(NOW(), INTERVAL 3 DAY) in claimTracker.php, so it
            // has to be checked against that same clock or the two can
            // silently disagree (PHP/MySQL timezone drift).
            $throttleCheck = mysqli_prepare($conn, "SELECT (throttled_until IS NOT NULL AND throttled_until > NOW()) AS still_throttled, throttled_until FROM `user` WHERE user_id = ?");
            mysqli_stmt_bind_param($throttleCheck, "i", $user['user_id']);
            mysqli_stmt_execute($throttleCheck);
            $throttleRow = mysqli_fetch_assoc(mysqli_stmt_get_result($throttleCheck));
            mysqli_stmt_close($throttleCheck);

            if ($throttleRow && (int)$throttleRow['still_throttled'] === 1) {
              // Still within the throttle window — block the login.
              $untilText = date('M j, Y g:i A', strtotime($throttleRow['throttled_until']));
              $error_msg = "Your account is temporarily throttled due to repeated no-shows. You can log in again after $untilText.";
            } else {
              // Throttle period has elapsed — clear it and let the login
              // proceed normally below.
              $clear = mysqli_prepare($conn, "UPDATE `user` SET account_status = 'active', throttled_until = NULL WHERE user_id = ?");
              mysqli_stmt_bind_param($clear, "i", $user['user_id']);
              mysqli_stmt_execute($clear);
              mysqli_stmt_close($clear);

              session_regenerate_id(true);
              $_SESSION['user_id']   = $user['user_id'];
              $_SESSION['user_name'] = $user['user_name'];
              $_SESSION['role']      = $user['role'];
              $_SESSION['email']     = $user['email'];

              if ($user['role'] === 'admin') {
                header("Location: " . BASE_URL . "/pages/admin/dashboard.php");
              } elseif ($user['role'] === 'provider') {
                header("Location: " . BASE_URL . "/pages/food_provider/dashboard.php");
              } else {
                header("Location: " . BASE_URL . "/pages/student/dashboard.php");
              }
              exit();
            }
          } else {
            // Mitigate session fixation by regenerating session token[cite: 1]
            session_regenerate_id(true);

            // Populate session attributes[cite: 1]
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['user_name'] = $user['user_name'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['email']     = $user['email'];

            // Route user to their corresponding portal[cite: 1]
            if ($user['role'] === 'admin') {
              header("Location: " . BASE_URL . "/pages/admin/dashboard.php");
            } elseif ($user['role'] === 'provider') {
              header("Location: " . BASE_URL . "/pages/food_provider/dashboard.php");
            } else {
              header("Location: " . BASE_URL . "/pages/student/dashboard.php");
            }
            exit();
          }
        } else {
          $error_msg = 'Invalid email or password.';
        }
      } else {
        $error_msg = 'An internal database error occurred. Please try again.';
      }
    }
  }

  // --------------------------------------------------------------------------
  // 2. Forgot Password / Security Question Reset
  // --------------------------------------------------------------------------
  elseif ($form_type === 'reset_password') {
    $active_tab   = 'forgot';
    $reset_email  = trim($_POST['reset_email'] ?? '');
    $security_ans = trim($_POST['security_answer'] ?? '');
    $new_pw       = $_POST['new_password'] ?? '';
    $confirm_pw   = $_POST['confirm_password'] ?? '';

    // Validation matching register requirements
    if (empty($reset_email) || !filter_var($reset_email, FILTER_VALIDATE_EMAIL)) {
      $error_msg = 'Please enter a valid email address.';
    } elseif (empty($security_ans)) {
      $error_msg = 'Security answer is required.';
    } elseif (strlen($new_pw) < 6) {
      $error_msg = 'Password must be at least 6 characters.';
    } elseif ($new_pw !== $confirm_pw) {
      $error_msg = 'New passwords do not match.';
    } else {
      try {
        // Retrieve current pass_hash and security_answer
        $query = "SELECT user_id, pass_hash, security_answer FROM `user` WHERE `email` = ? LIMIT 1";
        $stmt  = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $reset_email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($user && !empty($user['security_answer'])) {
          // Verify security answer (case-insensitive and strict checks)
          $isAnswerValid = password_verify(strtolower($security_ans), $user['security_answer']) 
                        || password_verify($security_ans, $user['security_answer']);

          if (!$isAnswerValid) {
            $error_msg = 'Incorrect security answer.';
          } elseif (password_verify($new_pw, $user['pass_hash'])) {
            // Restriction: Ensure the new password is not identical to the old password
            $error_msg = 'New password cannot be the same as your old password.';
          } else {
            // Hash the new password and update user record
            $new_hash    = password_hash($new_pw, PASSWORD_DEFAULT);
            $updateQuery = "UPDATE `user` SET `pass_hash` = ? WHERE `user_id` = ?";
            $updateStmt  = mysqli_prepare($conn, $updateQuery);
            mysqli_stmt_bind_param($updateStmt, "si", $new_hash, $user['user_id']);

            if (mysqli_stmt_execute($updateStmt)) {
              $success_msg = 'Password updated successfully! You can now log in with your new password.';
              $active_tab  = 'login'; // Switch back to login form upon success
            } else {
              $error_msg = 'Failed to reset password. Please try again.';
            }
            mysqli_stmt_close($updateStmt);
          }
        } else {
          $error_msg = 'No matching account found or no security question configured.';
        }
      } catch (Exception $e) {
        $error_msg = 'Database error: ' . $e->getMessage();
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account Access - Campus Food Rescue</title>
  
  <!-- Core Stylesheets -->
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/public_navbar_footer.css">
  <link rel="stylesheet" href="../../assets/css/auth.css">

  <style>
    /* Security Question & View Switcher Styles */
    .security-box {
      background-color: #F8FAFC;
      border: 1px solid #E2E8F0;
      border-radius: 8px;
      padding: 12px;
      margin-bottom: 16px;
      font-size: 13px;
    }
    .security-box-label {
      font-size: 11px;
      color: #64748B;
      text-transform: uppercase;
      font-weight: 700;
    }
    .security-question-text {
      color: #0F172A;
      font-weight: 600;
      margin-top: 4px;
    }
    .btn-find-question {
      background-color: transparent;
      border: 1px solid #275300;
      color: #275300;
      padding: 0 14px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      font-size: 12px;
      transition: all 0.2s ease;
      white-space: nowrap;
    }
    .btn-find-question:hover {
      background-color: #275300;
      color: #FFFFFF;
    }
  </style>
</head>
<body>
  <?php include '../../includes/public_navbar.php'; ?>

  <div class="auth-page">
    <div class="auth-card">
      <div class="auth-icon">
        <img src="../../assets/images/auth-icon.png" alt="Authentication Icon">
      </div>

      <!-- Flash Notifications -->
      <?php if (!empty($success_msg)): ?>
        <div style="color: #155724; background-color: #d4edda; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; text-align: center; border: 1px solid #c3e6cb;">
          <?= htmlspecialchars($success_msg) ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($error_msg)): ?>
        <div style="color: #d9534f; background-color: #fdf7f7; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; text-align: center; border: 1px solid #ebccd1;">
          <?= htmlspecialchars($error_msg) ?>
        </div>
      <?php endif; ?>

      <!-- ================================================================== -->
      <!-- VIEW 1: Login Form                                                 -->
      <!-- ================================================================== -->
      <div id="login-section" style="<?= $active_tab === 'forgot' ? 'display: none;' : 'display: block;' ?>">
        <h1 class="auth-title">Welcome Back</h1>
        <p class="auth-sub">Sign in to manage institutional logistics and sustainability tracking.</p>

        <form action="" method="POST">
          <input type="hidden" name="form_type" value="login">

          <!-- Email Field -->
          <div class="form-group">
            <label for="txtEmail">Email Address</label>
            <div class="input-wrap">
              <img src="../../assets/images/email-icon.png" alt="Email Icon">
              <input type="email" id="txtEmail" name="txtEmail" placeholder="admin@institution.edu" value="<?= htmlspecialchars($_POST['txtEmail'] ?? '') ?>" required>
            </div>
          </div>

          <!-- Password Field -->
          <div class="form-group">
            <div class="label-row">
              <label for="txtPassword">Password</label>
              <a href="#" id="to-forgot-btn" class="forgot-link">Forgot password?</a>
            </div> 
            <div class="input-wrap">
              <img src="../../assets/images/password-icon.png" alt="Password Icon">
              <input type="password" id="txtPassword" name="txtPassword" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" required>
            </div>
          </div>

          <button type="submit" class="btn-submit">Sign In</button>
        </form>

        <p class="auth-switch">
          Don't have an account? <a href="register.php">Sign Up</a>
        </p>
      </div>

      <!-- ================================================================== -->
      <!-- VIEW 2: Forgot Password / Security Question Form                   -->
      <!-- ================================================================== -->
      <div id="forgot-section" style="<?= $active_tab === 'forgot' ? 'display: block;' : 'display: none;' ?>">
        <h1 class="auth-title">Reset Password</h1>
        <p class="auth-sub">Answer your registered security question to set a new password.</p>

        <form action="" method="POST" id="reset-password-form">
          <input type="hidden" name="form_type" value="reset_password">

          <!-- Email Input & Fetch Button -->
          <div class="form-group">
            <label for="reset_email">Registered Email Address</label>
            <div style="display: flex; gap: 8px;">
              <div class="input-wrap" style="flex: 1;">
                <img src="../../assets/images/email-icon.png" alt="Email Icon">
                <input type="email" id="reset_email" name="reset_email" placeholder="Enter your account email" value="<?= htmlspecialchars($_POST['reset_email'] ?? '') ?>" required>
              </div>
              <button type="button" id="btn-fetch-question" class="btn-find-question">Find Question</button>
            </div>
          </div>

          <!-- Question Display Panel -->
          <div class="security-box" id="question-box" style="display: none;">
            <div class="security-box-label">Security Question</div>
            <div class="security-question-text" id="question-text">Loading question...</div>
          </div>

          <!-- Answer Input -->
          <div class="form-group" id="answer-group" style="display: none;">
            <label for="security_answer">Your Answer</label>
            <div class="input-wrap">
              <img src="../../assets/images/auth-icon.png" alt="Answer Icon" style="opacity: 0.5;">
              <input type="text" id="security_answer" name="security_answer" placeholder="Enter your answer (case insensitive)">
            </div>
          </div>

          <!-- New Password Input Fields -->
          <div id="password-reset-fields" style="display: none;">
            <div class="form-group">
              <label for="new_password">New Password</label>
              <div class="input-wrap">
                <img src="../../assets/images/password-icon.png" alt="Password Icon">
                <input type="password" id="new_password" name="new_password" placeholder="Min. 6 characters" minlength="6">
              </div>
            </div>

            <div class="form-group">
              <label for="confirm_password">Confirm New Password</label>
              <div class="input-wrap">
                <img src="../../assets/images/password-icon.png" alt="Password Icon">
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat password" minlength="6">
              </div>
            </div>

            <button type="submit" class="btn-submit">Reset Password</button>
          </div>
        </form>

        <p class="auth-switch" style="margin-top: 18px;">
          Remembered your password? <a href="#" id="to-login-btn">Back to Sign In</a>
        </p>
      </div>

    </div>
  </div>

  <?php include '../../includes/public_footer.php'; ?>

  <script src="../../assets/js/navbar.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const loginSection      = document.getElementById('login-section');
      const forgotSection     = document.getElementById('forgot-section');
      const toForgotBtn       = document.getElementById('to-forgot-btn');
      const toLoginBtn        = document.getElementById('to-login-btn');

      const resetEmailInput   = document.getElementById('reset_email');
      const btnFetchQuestion  = document.getElementById('btn-fetch-question');
      const questionBox       = document.getElementById('question-box');
      const questionText      = document.getElementById('question-text');
      const answerGroup       = document.getElementById('answer-group');
      const passwordFields    = document.getElementById('password-reset-fields');
      const securityAnswer    = document.getElementById('security_answer');
      const newPassword       = document.getElementById('new_password');
      const confirmPassword   = document.getElementById('confirm_password');
      const resetPasswordForm = document.getElementById('reset-password-form');

      // 1. Toggle between Login view and Reset Password view
      toForgotBtn.addEventListener('click', (e) => {
        e.preventDefault();
        loginSection.style.display = 'none';
        forgotSection.style.display = 'block';

        const typedEmail = document.getElementById('txtEmail').value.trim();
        if (typedEmail && !resetEmailInput.value) {
          resetEmailInput.value = typedEmail;
          fetchSecurityQuestion();
        }
      });

      toLoginBtn.addEventListener('click', (e) => {
        e.preventDefault();
        forgotSection.style.display = 'none';
        loginSection.style.display = 'block';
      });

      // 2. Fetch Security Question via AJAX
      async function fetchSecurityQuestion() {
        const email = resetEmailInput.value.trim();
        if (!email) {
          alert('Please enter your registered email address.');
          resetEmailInput.focus();
          return;
        }

        btnFetchQuestion.disabled = true;
        btnFetchQuestion.textContent = 'Searching...';

        try {
          const response = await fetch(`login.php?action=get_security_question&email=${encodeURIComponent(email)}`);
          const data = await response.json();

          if (data.status === 'success') {
            questionText.textContent = data.question;
            questionBox.style.display = 'block';
            answerGroup.style.display = 'block';
            passwordFields.style.display = 'block';

            securityAnswer.required = true;
            newPassword.required = true;
            confirmPassword.required = true;
            securityAnswer.focus();
          } else {
            alert(data.message || 'No security question found for this account.');
            questionBox.style.display = 'none';
            answerGroup.style.display = 'none';
            passwordFields.style.display = 'none';
          }
        } catch (error) {
          console.error('Error fetching question:', error);
          alert('Unable to load security question. Please check connection.');
        } finally {
          btnFetchQuestion.disabled = false;
          btnFetchQuestion.textContent = 'Find Question';
        }
      }

      btnFetchQuestion.addEventListener('click', fetchSecurityQuestion);

      // Trigger question lookup when Enter key is pressed in the email field
      resetEmailInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          fetchSecurityQuestion();
        }
      });

      // 3. Client-side form validation before submit
      resetPasswordForm.addEventListener('submit', (e) => {
        if (passwordFields.style.display !== 'none') {
          if (newPassword.value.length < 6) {
            alert('Password must be at least 6 characters.');
            newPassword.focus();
            e.preventDefault();
            return;
          }
          if (newPassword.value !== confirmPassword.value) {
            alert('Passwords do not match.');
            confirmPassword.focus();
            e.preventDefault();
            return;
          }
        }
      });

      // Re-trigger question lookup if page reloaded on error in forgot tab
      if ('<?= $active_tab ?>' === 'forgot' && resetEmailInput.value.trim() !== '') {
        fetchSecurityQuestion();
      }
    });
  </script>
</body>
</html>