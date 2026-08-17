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

            <div class="role-tabs">
                <button type="button" class="role-tab active" id="tabStudent" onclick="switchRole('student')">
                    <div class="active-check">
                        <svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <div class="tab-icon">&#x1F393;</div>
                    <div class="tab-label">Student</div>
                    <div class="tab-desc">Claim surplus food<br>from campus providers</div>
                </button>

                <button type="button" class="role-tab" id="tabProvider" onclick="switchRole('provider')">
                    <div class="active-check">
                        <svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <div class="tab-icon">&#x1F371;</div>
                    <div class="tab-label">Food Provider</div>
                    <div class="tab-desc">List surplus food<br>for students to claim</div>
                </button>
            </div>

            <form action="" method="POST" id="registerForm" novalidate>
                <input type="hidden" name="role" id="roleInput" value="student">

                <div class="form-group">
                    <label for="user_name">Username <span class="req">*</span></label>
                    <div class="input-wrap">
                        <input type="text" id="user_name" name="user_name" placeholder="Enter your username">
                    </div>
                    <span class="form-error" id="user_name_error"></span>
                </div>

                <div class="form-group">
                    <label for="email">Email Address <span class="req">*</span></label>
                    <div class="input-wrap">
                        <input type="email" id="email" name="email" placeholder="you@example.com">
                    </div>
                    <span class="form-error" id="email_error"></span>
                </div>

                <div class="form-2col">
                    <div class="form-group">
                        <label for="password">Password <span class="req">*</span></label>
                        <div class="input-wrap">
                            <input type="password" id="password" name="password" placeholder="Min. 6 characters">
                        </div>
                        <span class="form-error" id="password_error"></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm">Confirm Password <span class="req">*</span></label>
                        <div class="input-wrap">
                            <input type="password" id="confirm" name="confirm" placeholder="Repeat password">
                        </div>
                        <span class="form-error" id="confirm_error"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="security_question">Security Question <span class="req">*</span></label>
                    <div class="input-wrap">
                        <select id="security_question" name="security_question">
                            <option value="">Select a question</option>
                            <option>What is your favourite food?</option>
                            <option>What was your first pet's name?</option>
                            <option>What city were you born in?</option>
                            <option>What is your mother's maiden name?</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="security_answer">Your Answer <span class="req">*</span></label>
                    <div class="input-wrap">
                        <input type="text" id="security_answer" name="security_answer" placeholder="Enter your answer (case insensitive)">
                    </div>
                    <span class="form-error" id="answer_error"></span>
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
                            <input type="text" id="provider_name" name="provider_name" placeholder="e.g. Mamak Stall APU">
                        </div>
                    </div>

                    <div class="form-2col">
                        <div class="form-group">
                            <label for="contact">Contact Number <span class="req">*</span></label>
                            <div class="input-wrap">
                                <input type="text" id="contact" name="contact" placeholder="e.g. 012-3456789">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="location">Campus Location <span class="req">*</span></label>
                            <div class="input-wrap">
                                <input type="text" id="location" name="location" placeholder="e.g. Block A, GF">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="request_note">Why do you want to join? (Optional)</label>
                        <div class="input-wrap">
                            <textarea id="request_note" name="request_note" rows="3" style="resize:vertical" placeholder="Tell admin about your stall and the food you plan to share…"></textarea>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    Create Student Account
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
</body>
</html>