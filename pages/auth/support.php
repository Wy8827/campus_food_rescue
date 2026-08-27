<?php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support & Contact - Campus Food Rescue</title>
    <!-- Core & Layout Stylesheets -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/public_navbar_footer.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/landing.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Custom styling specific to Support Page -->
    <style>
        .support-hero {
            background-color: var(--bg-gray);
            padding: 72px 0 48px;
            text-align: center;
        }

        .support-hero .hero-title {
            font-size: 40px;
            font-weight: 800;
            margin-bottom: 16px;
            color: var(--text-dark);
        }

        .support-hero .hero-desc {
            font-size: 16px;
            color: var(--text-gray);
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .support-section {
            padding: 64px 0 96px;
        }

        /* Contact Cards Grid */
        .contact-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
            margin-bottom: 64px;
        }

        @media (min-width: 768px) {
            .contact-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 992px) {
            .contact-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        .contact-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-md);
            padding: 32px 24px;
            text-align: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.04);
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .contact-card:hover {
            transform: translateY(-4px);
            border-color: var(--primary-color);
            box-shadow: 0 12px 20px -3px rgba(0, 0, 0, 0.08);
        }

        .contact-icon {
            width: 56px;
            height: 56px;
            background-color: var(--primary-light);
            color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .contact-icon svg {
            width: 28px;
            height: 28px;
        }

        .contact-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .contact-subtitle {
            font-size: 13px;
            color: var(--text-light);
            margin-bottom: 16px;
        }

        .contact-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--primary-color);
            word-break: break-all;
            margin-bottom: 8px;
        }

        .contact-btn {
            margin-top: auto;
            display: inline-block;
            padding: 8px 18px;
            font-size: 13px;
            font-weight: 600;
            border-radius: var(--border-radius-sm);
            background-color: var(--primary-light);
            color: var(--primary-color);
            transition: all 0.2s ease;
        }

        .contact-btn:hover {
            background-color: var(--primary-color);
            color: #ffffff;
        }

        /* Guidance Guide Card */
        .guidance-box {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-md);
            padding: 36px;
            max-width: 860px;
            margin: 0 auto;
        }

        .guidance-box h3 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 16px;
            color: var(--text-dark);
        }

        .guidance-box p {
            color: var(--text-gray);
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .guidance-steps {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .guidance-steps li {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            font-size: 14px;
            color: var(--text-gray);
            line-height: 1.5;
        }

        .step-num {
            flex-shrink: 0;
            width: 26px;
            height: 26px;
            background-color: var(--primary-color);
            color: #ffffff;
            font-weight: 700;
            font-size: 12px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>

    <!-- Public Navigation Bar -->
    <?php include __DIR__ . '/../../includes/public_navbar.php'; ?>

    <main>
        <!-- Support Header / Banner -->
        <section class="support-hero">
            <div class="container">
                <h1 class="hero-title">Support & Contact Admin</h1>
                <p class="hero-desc">
                    Need help with food listings, account verification, or platform issues? Reach out directly to the Campus Food Rescue administration team.
                </p>
            </div>
        </section>

        <!-- Main Support Content -->
        <section class="support-section">
            <div class="container">
                
                <!-- Contact Channels Grid -->
                <div class="contact-grid">
                    
                    <!-- Email Contact Card -->
                    <div class="contact-card">
                        <div class="contact-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <h3 class="contact-title">Email Administration</h3>
                        <p class="contact-subtitle">Official Admin Mailbox</p>
                        <div class="contact-value">admin@apu.edu.my</div>
                        <a href="mailto:admin@apu.edu.my?subject=Campus%20Food%20Rescue%20Support%20Request" class="contact-btn">
                            Send Email
                        </a>
                    </div>

                    <!-- Phone / Hotline Card -->
                    <div class="contact-card">
                        <div class="contact-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                        </div>
                        <h3 class="contact-title">Helpdesk Hotline</h3>
                        <p class="contact-subtitle">Direct Phone Assistance</p>
                        <div class="contact-value">+60 3-8996 1000</div>
                        <a href="tel:+60389961000" class="contact-btn">
                            Call Now
                        </a>
                    </div>

                    <!-- Admin Office Location Card -->
                    <div class="contact-card">
                        <div class="contact-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <h3 class="contact-title">Campus Admin Desk</h3>
                        <p class="contact-subtitle">Student Services & Operations</p>
                        <div class="contact-value">Admin System Hub</div>
                        <span class="contact-btn" style="background:#f3f4f6; color:#555; cursor:default;">
                            Mon - Fri: 9AM - 5PM
                        </span>
                    </div>

                </div>

                <!-- User Guidance Section -->
                <div class="guidance-box">
                    <h3>How to Get Quick Assistance</h3>
                    <p>When reaching out to the administrator, please include the following details so we can resolve your issue promptly:</p>
                    
                    <ul class="guidance-steps">
                        <li>
                            <span class="step-num">1</span>
                            <div><strong>Student/Staff ID & Registered Email:</strong> Clearly state your university ID and registered account email address.</div>
                        </li>
                        <li>
                            <span class="step-num">2</span>
                            <div><strong>Detailed Description:</strong> Specify if your issue relates to food claiming, expired listings, or account login errors.</div>
                        </li>
                        <li>
                            <span class="step-num">3</span>
                            <div><strong>Attach Screenshots:</strong> For system bugs or claim errors, attach relevant screenshots for faster troubleshooting.</div>
                        </li>
                    </ul>
                </div>

            </div>
        </section>
    </main>

    <!-- Public Footer -->
    <?php include __DIR__ . '/../../includes/public_footer.php'; ?>

</body>
</html>