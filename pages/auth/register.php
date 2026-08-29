<?php
session_start();
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';

if (isLoggedIn()) {
  $role = getRole();
  $roleFolders = ['admin' => 'admin', 'provider' => 'food_provider', 'student' => 'student'];
  $folder = $roleFolders[$role] ?? 'student';
  header("Location: " . BASE_URL . "/pages/$folder/dashboard.php");
  exit();
}

$errors = [];
$fieldErrors = [];

// Keep whatever the person typed so a failed submit doesn't wipe the form
$role              = 'student';
$user_name         = '';
$email             = '';
$security_question = '';
$security_answer   = '';
$provider_name     = '';
$contact           = '';
$operating_hours   = '';
$location          = '';
$request_note      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role              = ($_POST['role'] ?? 'student') === 'provider' ? 'provider' : 'student';
    $user_name         = trim($_POST['user_name'] ?? '');
    $email             = trim($_POST['email'] ?? '');
    $password          = $_POST['password'] ?? '';
    $confirm           = $_POST['confirm'] ?? '';
    $security_question = trim($_POST['security_question'] ?? '');
    $security_answer   = trim($_POST['security_answer'] ?? '');

    if ($role === 'provider') {
        $provider_name = trim($_POST['provider_name'] ?? '');
        $contact       = trim($_POST['contact'] ?? '');
        $operating_hours = trim($_POST['operating_hours'] ?? '');
        $location      = trim($_POST['location'] ?? '');
        $request_note  = trim($_POST['request_note'] ?? '');
    }

    // ---------------- Server-side validation ----------------
    // (the page also validates client-side via register.js, but that
    // can never be trusted on its own — this is the authoritative check)
    if ($user_name === '' || strlen($user_name) > 30) {
        $fieldErrors['user_name'] = 'Username is required (max 30 characters).';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fieldErrors['email'] = 'Enter a valid email address.';
    }
    if (strlen($password) < 6) {
        $fieldErrors['password'] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm) {
        $fieldErrors['confirm'] = 'Passwords do not match.';
    }
    if ($security_question === '') {
        $errors[] = 'Please select a security question.';
    }
    if ($security_answer === '') {
        $fieldErrors['answer'] = 'Security answer is required.';
    }

    if ($role === 'provider') {
        if ($provider_name === '' || strlen($provider_name) > 100) {
            $errors[] = 'Please provide your stall / organisation name.';
        }
        if ($contact === '' || strlen($contact) > 20) {
            $errors[] = 'Please provide a contact number.';
        }
        if ($location === '' || strlen($location) > 200) {
            $errors[] = 'Please provide your campus location.';
        }
        if ($operating_hours === '') {
            $errors[] = 'Please provide your operating hours.';
        }
    }

    // Duplicate email check (only bother querying if nothing else failed yet)
    if (empty($fieldErrors) && empty($errors)) {
        $stmt = mysqli_prepare($conn, "SELECT user_id FROM user WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($exists) {
            $fieldErrors['email'] = 'An account with this email already exists.';
        }
    }

    // ---------------- Create the account ----------------
    if (empty($fieldErrors) && empty($errors)) {
        mysqli_begin_transaction($conn);
        $ok = true;

        $passHash = password_hash($password, PASSWORD_DEFAULT);
        $answerHash = password_hash(strtolower($security_answer), PASSWORD_DEFAULT);

        $stmt = mysqli_prepare($conn, "
            INSERT INTO user (user_name, email, role, pass_hash, account_status, security_question, security_answer)
            VALUES (?, ?, ?, ?, 'active', ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, "ssssss", $user_name, $email, $role, $passHash, $security_question, $answerHash);
        if (!mysqli_stmt_execute($stmt)) {
            $ok = false;
        }
        $newUserId = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        if ($ok && $role === 'provider') {
            // provider_status defaults to 'pending_approval' per the schema —
            // this is the exact status admin/moderation.php already filters
            // on and can Approve/Suspend, so no admin-side changes needed.
            $stmt = mysqli_prepare($conn, "
                INSERT INTO provider (user_id, provider_name, contact_number, location, operating_hours, request_note)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            mysqli_stmt_bind_param($stmt, "isssss", $newUserId, $provider_name, $contact, $location, $operating_hours, $request_note);
            if (!mysqli_stmt_execute($stmt)) {
                $ok = false;
            }
            mysqli_stmt_close($stmt);
        }

        if ($ok) {
            mysqli_commit($conn);
            $redirectFlag = $role === 'provider' ? 'pending' : 'registered';
            header("Location: " . BASE_URL . "/pages/auth/login.php?$redirectFlag=1");
            exit();
        } else {
            mysqli_rollback($conn);
            $errors[] = 'Something went wrong creating your account. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Campus Food Rescue</title>
    
    <!-- Link the CSS files in hierarchical order -->
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/public_navbar_footer.css">
    <link rel="stylesheet" href="../../assets/css/auth.css">
</head>
<body>

    <?php include '../../includes/public_navbar.php'; ?>

    <div class="auth-page">
        <div class="auth-card register-card">
            
            <h1 class="auth-title text-left">Create an Account</h1>
            <p class="auth-sub text-left">Join Campus Food Rescue and help make a difference.</p>

            <?php if (!empty($errors)): ?>
                <div style="color: #d9534f; background-color: #fdf7f7; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; text-align: center; border: 1px solid #ebccd1;">
                    <?php foreach ($errors as $e): ?>
                        <div><?= htmlspecialchars($e) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="role-tabs">
                <button type="button" class="role-tab <?= $role === 'student' ? 'active' : '' ?>" id="tabStudent" onclick="switchRole('student')">
                    <div class="active-check">
                        <svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <div class="tab-icon">&#x1F393;</div>
                    <div class="tab-label">Student</div>
                    <div class="tab-desc">Claim surplus food<br>from campus providers</div>
                </button>

                <button type="button" class="role-tab <?= $role === 'provider' ? 'active' : '' ?>" id="tabProvider" onclick="switchRole('provider')">
                    <div class="active-check">
                        <svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <div class="tab-icon">&#x1F371;</div>
                    <div class="tab-label">Food Provider</div>
                    <div class="tab-desc">List surplus food<br>for students to claim</div>
                </button>
            </div>

            <form action="" method="POST" id="registerForm" novalidate>
                <input type="hidden" name="role" id="roleInput" value="<?= htmlspecialchars($role) ?>">

                <div class="form-group">
                    <label for="user_name">Username <span class="req">*</span></label>
                    <div class="input-wrap">
                        <input type="text" id="user_name" name="user_name" placeholder="Enter your username" value="<?= htmlspecialchars($user_name) ?>">
                    </div>
                    <span class="form-error" id="user_name_error"><?= htmlspecialchars($fieldErrors['user_name'] ?? '') ?></span>
                </div>

                <div class="form-group">
                    <label for="email">Email Address <span class="req">*</span></label>
                    <div class="input-wrap">
                        <input type="email" id="email" name="email" placeholder="you@example.com" value="<?= htmlspecialchars($email) ?>">
                    </div>
                    <span class="form-error" id="email_error"><?= htmlspecialchars($fieldErrors['email'] ?? '') ?></span>
                </div>

                <div class="form-2col">
                    <div class="form-group">
                        <label for="password">Password <span class="req">*</span></label>
                        <div class="input-wrap">
                            <input type="password" id="password" name="password" placeholder="Min. 6 characters">
                        </div>
                        <span class="form-error" id="password_error"><?= htmlspecialchars($fieldErrors['password'] ?? '') ?></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm">Confirm Password <span class="req">*</span></label>
                        <div class="input-wrap">
                            <input type="password" id="confirm" name="confirm" placeholder="Repeat password">
                        </div>
                        <span class="form-error" id="confirm_error"><?= htmlspecialchars($fieldErrors['confirm'] ?? '') ?></span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="security_question">Security Question <span class="req">*</span></label>
                    <div class="input-wrap">
                        <select id="security_question" name="security_question">
                            <option value="">Select a question</option>
                            <?php foreach ([
                                'What is your favourite food?',
                                "What was your first pet's name?",
                                'What city were you born in?',
                                "What is your mother's maiden name?",
                            ] as $q): ?>
                                <option <?= $security_question === $q ? 'selected' : '' ?>><?= htmlspecialchars($q) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="security_answer">Your Answer <span class="req">*</span></label>
                    <div class="input-wrap">
                        <input type="text" id="security_answer" name="security_answer" placeholder="Enter your answer (case insensitive)" value="<?= htmlspecialchars($security_answer) ?>">
                    </div>
                    <span class="form-error" id="answer_error"><?= htmlspecialchars($fieldErrors['answer'] ?? '') ?></span>
                </div>

                <!-- Provider Fields (Hidden by Default) -->
                <div id="providerFields">
                    <div class="divider">Provider details</div>

                    <div class="provider-notice">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        <div>Your provider account needs admin approval before you can post listings. You will be notified once approved.</div>
                    </div>

                    <div class="form-group">
                        <label for="provider_name">Stall / Organisation Name <span class="req">*</span></label>
                        <div class="input-wrap">
                            <input type="text" id="provider_name" name="provider_name" placeholder="e.g. Mamak Stall APU" value="<?= htmlspecialchars($provider_name) ?>">
                        </div>
                    </div>

                    <div class="form-2col">
                        <div class="form-group">
                            <label for="contact">Contact Number <span class="req">*</span></label>
                            <div class="input-wrap">
                                <input type="text" id="contact" name="contact" placeholder="e.g. 012-3456789" value="<?= htmlspecialchars($contact) ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="location">Campus Location <span class="req">*</span></label>
                            <div class="input-wrap">
                                <input type="text" id="location" name="location" placeholder="e.g. Block A, GF" value="<?= htmlspecialchars($location) ?>">
                            </div>
                        </div>
                    </div>

                    <!-- NEW FIELD: Operating Hours -->
                    <div class="form-group">
                        <label for="operating_hours">Operating Hours <span class="req">*</span></label>
                        <div class="input-wrap">
                            <input type="text" id="operating_hours" name="operating_hours" 
                                placeholder="e.g. Mon-Fri 8:00am-5:00pm" 
                                value="<?= htmlspecialchars($operating_hours) ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="request_note">Why do you want to join? (Optional)</label>
                        <div class="input-wrap">
                            <textarea id="request_note" name="request_note" rows="3" style="resize:vertical" placeholder="Tell admin about your stall and the food you plan to share…"><?= htmlspecialchars($request_note) ?></textarea>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    <?= $role === 'provider' ? 'Submit Provider Application' : 'Create Student Account' ?>
                </button>

            </form>

            <div class="auth-switch">
                Already have an account? <a href="login.php">Login here</a>
            </div>

        </div>
    </div>

    <?php include '../../includes/public_footer.php'; ?>

    <script src="../../assets/js/register.js"></script>
    <script src="../../assets/js/navbar.js"></script>
    <?php if ($role === 'provider'): ?>
        <script>
            // Re-apply the provider tab's UI state after a server-side
            // validation error re-renders this page with role=provider
            // already selected (switchRole() lives in register.js).
            document.addEventListener('DOMContentLoaded', function () {
                switchRole('provider');
            });
        </script>
    <?php endif; ?>
</body>
</html>