<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus Food Rescue</title>
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/public_navbar_footer.css">
    <link rel="stylesheet" href="assets/css/landing.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'includes/public_navbar.php'; ?>

    <main>
        <!-- Hero Section -->
        <section class="hero" id="hero">
            <div class="container hero-container">
                <div>
                    <h1 class="hero-title">
                        Rescue Food.<br>
                        Reduce Waste.<br>
                        <span>Help Students.</span>
                    </h1>
                    <p class="hero-desc">
                        Connect directly with campus dining halls and cafes to claim perfectly good surplus food before it goes to waste. Good for the planet, good for your wallet.
                    </p>
                    <div class="hero-actions">
                        <button class="btn btn-primary">Get Started</button>
                        <button class="btn btn-outline">Learn More</button>
                    </div>
                </div>
                <div class="hero-image-wrapper">
                    <img src="assets/images/landingpage1.png" alt="Students sharing food" class="hero-image">
                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section class="how-it-works" id="how-it-works">
            <div class="container">
                <h2 class="section-title">How It Works</h2>
                <p class="section-subtitle">Three simple steps to fight food waste on campus.</p>
                
                <div class="steps-grid">
                    <!-- Step 1: Browse -->
                    <div class="step-card">
                        <div class="step-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <h3 class="step-title">Browse</h3>
                        <p class="step-desc">Check real-time listings of available surplus food from participating dining locations across campus.</p>
                    </div>

                    <!-- Step 2: Claim -->
                    <div class="step-card">
                        <div class="step-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"></path></svg>
                        </div>
                        <h3 class="step-title">Claim</h3>
                        <p class="step-desc">Reserve the items you want with a single tap. Reservations are held for a specific pickup window.</p>
                    </div>

                    <!-- Step 3: Pick Up -->
                    <div class="step-card">
                        <div class="step-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                        </div>
                        <h3 class="step-title">Pick Up</h3>
                        <p class="step-desc">Head to the designated location, show your digital confirmation, and enjoy your rescued meal.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Impact Section -->
        <section class="impact" id="impact">
            <div class="container impact-container">
                <div class="impact-text-area">
                    <h2 class="section-title">Measurable Impact</h2>
                    <p>Every meal rescued is a step towards a more sustainable campus. Together, we are significantly reducing our carbon footprint and conserving vital resources.</p>
                </div>
                
                <div class="impact-stats-area">
                    <!-- Total Rescued Stat -->
                    <div class="stat-card large">
                        <div class="stat-icon">
                            <svg fill="currentColor" viewBox="0 0 24 24"><path d="M11 9H9V2H7v7H5V2H3v7c0 2.12 1.66 3.84 3.75 3.97V22h2.5v-9.03C11.34 12.84 13 11.12 13 9V2h-2v7zm5-3v8h2.5v8H21V2c-2.76 0-5 2.24-5 4z"/></svg>
                        </div>
                        <div class="stat-value">2,450</div>
                        <div class="stat-label">Meals Rescued</div>
                    </div>
                    
                    <!-- Environmental Stats Row -->
                    <div class="stats-row">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"></path></svg>
                            </div>
                            <div class="stat-value">820 kg</div>
                            <div class="stat-label">CO2 Reduced</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            </div>
                            <div class="stat-value">18,000 L</div>
                            <div class="stat-label">Water Saved</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- About / Why Us Section -->
        <section class="why-use" id="about">
            <div class="container why-container">
                <div>
                    <img src="assets/images/landingpage2.png" alt="App on mobile phone" class="app-mockup">
                </div>

                <div>
                    <h2 class="section-title">Why use Campus Food Rescue?</h2>
                    
                    <div class="features-list">
                        <!-- Feature 1 -->
                        <div class="feature-item">
                            <div class="feature-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                            </div>
                            <div>
                                <h4 class="feature-title">Save Money</h4>
                                <p class="feature-desc">Access high-quality, nutritious meals for free or at a drastically reduced cost.</p>
                            </div>
                        </div>

                        <!-- Feature 2 -->
                        <div class="feature-item">
                            <div class="feature-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                            </div>
                            <div>
                                <h4 class="feature-title">Fight Climate Change</h4>
                                <p class="feature-desc">Directly contribute to lowering campus greenhouse gas emissions by preventing food rot in landfills.</p>
                            </div>
                        </div>

                        <!-- Feature 3 -->
                        <div class="feature-item">
                            <div class="feature-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                            </div>
                            <div>
                                <h4 class="feature-title">Build Community</h4>
                                <p class="feature-desc">Join thousands of students and staff working together towards a zero-waste campus.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section class="faq" id="faq">
            <div class="container">
                <h2 class="section-title">Frequently Asked Questions</h2>
                <p class="section-subtitle">Find answers to common questions about food rescue on campus.</p>
                
                <div class="faq-list">
                    <!-- FAQ Item 1 -->
                    <div class="faq-item">
                        <h4 class="faq-question">Who is eligible to claim food?</h4>
                        <p class="faq-answer">All registered university students, staff, and faculty members with an active campus account can participate.</p>
                    </div>

                    <!-- FAQ Item 2 -->
                    <div class="faq-item">
                        <h4 class="faq-question">Is the food safe to eat?</h4>
                        <p class="faq-answer">Yes, all surplus meals are packaged and handled according to food safety guidelines right after service hours.</p>
                    </div>

                    <!-- FAQ Item 3 -->
                    <div class="faq-item">
                        <h4 class="faq-question">How does pickup work?</h4>
                        <p class="faq-answer">Reserve your meal on the platform, visit the pickup counter during the designated time slot, and show your digital confirmation.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <?php include 'includes/public_footer.php'; ?>

</body>
</html>