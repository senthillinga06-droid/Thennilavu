<?php
session_start();

// Database connection
$host = "localhost";
$user = "thennilavu_matrimonial"; // change if needed
$pass = "OYVuiEKfS@FQ";     // change if needed
$db   = "thennilavu_thennilavu";

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch company details from database
$company_sql = "SELECT location, mobile_number, land_number, email FROM company_details LIMIT 1";
$company_result = $conn->query($company_sql);
$company_data = $company_result->fetch_assoc();

// Set values from database or defaults
$address = $company_data['location'] ?? '123 Main Street, Colombo, Sri Lanka';
$phone1 = $company_data['mobile_number'] ?? '+94 77 123 4567';
$phone2 = $company_data['land_number'] ?? '+94 11 234 5678';
$email1 = $company_data['email'] ?? 'info@Thennilavu.com';
$email2 = 'support@Thennilavu.com'; // Static second email

// Fetch latest reviews
$sql = "SELECT name, profession, country, comment, rating, photo 
        FROM reviews 
        ORDER BY review_date DESC 
        LIMIT 10";
$result = $conn->query($sql);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name    = $conn->real_escape_string($_POST['name']);
    $email   = $conn->real_escape_string($_POST['email']);
    $subject = $conn->real_escape_string($_POST['subject']);
    $message = $conn->real_escape_string($_POST['message']);

    $sql = "INSERT INTO messages (sender_name, sender_email, subject, message_text) 
            VALUES ('$name', '$email', '$subject', '$message')";

    if ($conn->query($sql) === TRUE) {
        $success_message = "Your message has been sent successfully!";
    } else {
        $error_message = "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Thennilavu Matrimony</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Modern Color Variables */
        :root {
            /* Light Theme */
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --bg-tertiary: #e9ecef;
            --text-primary: #1a1a2e;
            --text-secondary: #495057;
            --text-muted: #6c757d;
            --primary-color: #e91e63;
            --primary-dark: #c2185b;
            --primary-light: #f8bbd9;
            --secondary-color: #7b1fa2;
            --accent-color: #ff4081;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --danger-color: #f44336;
            --border-color: #dee2e6;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --elevated-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 24px;
            --transition: all 0.3s ease;
        }

        /* Dark Theme */
        [data-theme="dark"] {
            --bg-primary: #121212;
            --bg-secondary: #1e1e1e;
            --bg-tertiary: #2d2d2d;
            --text-primary: #f8f9fa;
            --text-secondary: #adb5bd;
            --text-muted: #6c757d;
            --border-color: #404040;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
            --elevated-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
        }

        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            transition: var(--transition);
            -webkit-tap-highlight-color: transparent;
        }

        /* App Container */
        .app-container {
            max-width: 100%;
            min-height: 100vh;
            padding-top: 60px; /* Space for header */
            padding-bottom: 80px; /* Space for bottom nav */
        }

        /* App Header */
        .app-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-color);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 1000;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }

        [data-theme="dark"] .app-header {
            background: rgba(18, 18, 18, 0.95);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .back-button {
            background: none;
            border: none;
            color: var(--primary-color);
            font-size: 1.2rem;
            padding: 8px;
            cursor: pointer;
        }

        .header-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .theme-toggle {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.2rem;
            padding: 8px;
            cursor: pointer;
            transition: var(--transition);
        }

        .theme-toggle:hover {
            color: var(--primary-color);
        }

        /* Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--bg-primary);
            border-top: 1px solid var(--border-color);
            padding: 8px 16px;
            display: flex;
            justify-content: space-around;
            z-index: 1000;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }

        [data-theme="dark"] .bottom-nav {
            background: rgba(18, 18, 18, 0.95);
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 0.7rem;
            gap: 4px;
            transition: var(--transition);
            padding: 6px 8px;
            border-radius: var(--radius-sm);
            min-width: 56px;
        }

        .nav-item i {
            font-size: 1.1rem;
        }

        .nav-item.active {
            color: var(--primary-color);
            background: rgba(233, 30, 99, 0.1);
        }

        .nav-item:hover {
            color: var(--primary-color);
        }

        /* Hero Section */
        .hero-section {
            padding: 24px 20px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
        }

        .hero-icon {
            font-size: 2.5rem;
            margin-bottom: 12px;
            opacity: 0.9;
        }

        .hero-title {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 8px;
            line-height: 1.2;
        }

        .hero-subtitle {
            font-size: 0.95rem;
            opacity: 0.9;
            max-width: 300px;
            margin: 0 auto;
        }

        /* Content Container */
        .content-container {
            padding: 20px 16px;
        }

        /* Contact Cards Grid */
        .contact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .contact-card {
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            padding: 20px;
            text-align: center;
            transition: var(--transition);
            border: 1px solid var(--border-color);
        }

        .contact-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--elevated-shadow);
        }

        .contact-card-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            color: white;
            font-size: 1.2rem;
        }

        .contact-card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .contact-card-text {
            font-size: 0.85rem;
            color: var(--text-secondary);
            line-height: 1.4;
        }

        /* Form Section */
        .form-section {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }

        .section-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            background: var(--bg-primary);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            color: var(--text-primary);
            transition: var(--transition);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
        }

        textarea.form-input {
            min-height: 120px;
            resize: vertical;
            font-family: inherit;
        }

        /* Submit Button */
        .submit-button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .submit-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(233, 30, 99, 0.3);
        }

        /* Toast Notification */
        .toast {
            position: fixed;
            bottom: 80px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: var(--success-color);
            color: white;
            padding: 14px 20px;
            border-radius: var(--radius-md);
            box-shadow: var(--elevated-shadow);
            opacity: 0;
            transition: var(--transition);
            z-index: 1100;
            max-width: 90%;
            text-align: center;
            font-weight: 500;
        }

        .toast.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }

        .toast.error {
            background: var(--danger-color);
        }

        /* Loading Spinner */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 12px;
            margin: 20px 0;
        }

        .action-btn {
            flex: 1;
            padding: 14px 12px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            color: var(--text-primary);
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .action-btn:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        /* FAQ Section */
        .faq-section {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 24px;
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
        }

        .faq-item {
            margin-bottom: 16px;
        }

        .faq-question {
            padding: 16px;
            background: var(--bg-primary);
            border-radius: var(--radius-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: var(--transition);
            border: 1px solid var(--border-color);
        }

        .faq-question:hover {
            border-color: var(--primary-color);
        }

        .faq-question-text {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.95rem;
            flex: 1;
        }

        .faq-toggle {
            color: var(--primary-color);
            transition: transform 0.3s ease;
            margin-left: 12px;
        }

        .faq-answer {
            padding: 0;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .faq-answer.active {
            padding: 16px;
            max-height: 200px;
        }

        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* PHP Toast Messages */
        .php-message {
            position: fixed;
            bottom: 80px;
            left: 50%;
            transform: translateX(-50%) translateY(0);
            background: var(--success-color);
            color: white;
            padding: 14px 20px;
            border-radius: var(--radius-md);
            box-shadow: var(--elevated-shadow);
            z-index: 1100;
            max-width: 90%;
            text-align: center;
            font-weight: 500;
        }

        .php-message.error {
            background: var(--danger-color);
        }

        /* Responsive Adjustments */
        @media (min-width: 768px) {
            .app-container {
                max-width: 480px;
                margin: 0 auto;
                border-left: 1px solid var(--border-color);
                border-right: 1px solid var(--border-color);
            }
            
            .bottom-nav .nav-item {
                font-size: 0.75rem;
            }
        }

        @media (max-width: 380px) {
            .contact-grid {
                grid-template-columns: 1fr;
            }
            
            .hero-title {
                font-size: 1.5rem;
            }
            
            .bottom-nav .nav-item {
                font-size: 0.65rem;
                padding: 6px 4px;
                min-width: 50px;
            }
            
            .bottom-nav .nav-item i {
                font-size: 1rem;
            }
            
            .quick-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 320px) {
            .hero-title {
                font-size: 1.3rem;
            }
            
            .header-title {
                font-size: 1.1rem;
            }
            
            .contact-card {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <!-- App Container -->
    <div class="app-container">
        <!-- PHP Messages Display -->
        <?php if (isset($success_message)): ?>
            <div class="php-message">
                <?php echo $success_message; ?>
            </div>
            <script>
                setTimeout(() => {
                    document.querySelector('.php-message').style.display = 'none';
                }, 5000);
            </script>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="php-message error">
                <?php echo $error_message; ?>
            </div>
            <script>
                setTimeout(() => {
                    document.querySelector('.php-message').style.display = 'none';
                }, 5000);
            </script>
        <?php endif; ?>

        <!-- App Header -->
        <header class="app-header">
            <div class="header-left">
                <button class="back-button" onclick="goBack()">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <h1 class="header-title">Contact Us</h1>
            </div>
            <button class="theme-toggle" id="themeToggle">
                <i class="fas fa-moon"></i>
            </button>
        </header>

        <!-- Hero Section -->
        <section class="hero-section">
            <div class="hero-icon">
                <i class="fas fa-comments"></i>
            </div>
            <h1 class="hero-title">We're Here to Help</h1>
            <p class="hero-subtitle">Get in touch with our team for any questions or assistance</p>
        </section>

        <!-- Main Content -->
        <main class="content-container">
            <!-- Quick Action Buttons -->
            <div class="quick-actions">
                <button class="action-btn" onclick="window.location.href='tel:<?php echo preg_replace('/[^0-9+]/', '', $phone1); ?>'">
                    <i class="fas fa-phone"></i>
                    Call Now
                </button>
                <button class="action-btn" onclick="window.location.href='mailto:<?php echo $email1; ?>'">
                    <i class="fas fa-envelope"></i>
                    Email Us
                </button>
                <button class="action-btn" onclick="window.open('https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $phone1); ?>', '_blank')">
                    <i class="fab fa-whatsapp"></i>
                    WhatsApp
                </button>
            </div>

            <!-- Contact Information - Data from Database -->
            <div class="contact-grid">
                <div class="contact-card fade-in" style="animation-delay: 0.1s">
                    <div class="contact-card-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h3 class="contact-card-title">Our Location</h3>
                    <p class="contact-card-text"><?php echo htmlspecialchars($address); ?></p>
                </div>

                <div class="contact-card fade-in" style="animation-delay: 0.2s">
                    <div class="contact-card-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <h3 class="contact-card-title">Call Us</h3>
                    <p class="contact-card-text">
                        <?php echo htmlspecialchars($phone1); ?><br>
                        <?php echo htmlspecialchars($phone2); ?>
                    </p>
                </div>

                <div class="contact-card fade-in" style="animation-delay: 0.3s">
                    <div class="contact-card-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h3 class="contact-card-title">Email Us</h3>
                    <p class="contact-card-text">
                        <?php echo htmlspecialchars($email1); ?><br>
                        <?php echo htmlspecialchars($email2); ?>
                    </p>
                </div>
            </div>

            <!-- Contact Form -->
            <div class="form-section fade-in">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                    <h2 class="section-title">Send Your Message</h2>
                </div>

                <!-- Form will submit to PHP -->
                <form id="contactForm" method="POST" action="" onsubmit="return validateForm()">
                    <div class="form-group">
                        <label class="form-label" for="name">Full Name</label>
                        <input type="text" class="form-input" id="name" name="name" required 
                               placeholder="Enter your full name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" class="form-input" id="email" name="email" required 
                               placeholder="Enter your email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="subject">Subject</label>
                        <input type="text" class="form-input" id="subject" name="subject" required 
                               placeholder="What is this regarding?" value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="message">Your Message</label>
                        <textarea class="form-input" id="message" name="message" required 
                                  placeholder="Type your message here..."><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                    </div>

                    <button type="submit" class="submit-button" id="submitBtn">
                        <i class="fas fa-paper-plane"></i>
                        <span id="submitText">Send Message</span>
                    </button>
                </form>
            </div>

            <!-- FAQ Section -->
            <div class="faq-section fade-in">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <h2 class="section-title">Frequently Asked Questions</h2>
                </div>

                <div class="faq-container">
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <span class="faq-question-text">How do I create a profile?</span>
                            <i class="fas fa-plus faq-toggle"></i>
                        </div>
                        <div class="faq-answer">
                            You can create a profile by filling in the registration form with your personal, educational, and family details. After submitting, your profile will be reviewed and published.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <span class="faq-question-text">Is my data safe and private?</span>
                            <i class="fas fa-plus faq-toggle"></i>
                        </div>
                        <div class="faq-answer">
                            Yes, we take your privacy seriously. Your personal information is secured and only accessible by verified users.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <span class="faq-question-text">How do I upgrade my plan?</span>
                            <i class="fas fa-plus faq-toggle"></i>
                        </div>
                        <div class="faq-answer">
                            You can upgrade any time by visiting the "Our Packages" section and selecting the desired plan. Payment can be made via card, bank transfer, or mobile payment.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <span class="faq-question-text">Can I contact other members directly?</span>
                            <i class="fas fa-plus faq-toggle"></i>
                        </div>
                        <div class="faq-answer">
                            Contacting other members depends on your membership level. Premium and Elite members have full access to messaging features.
                        </div>
                    </div>
                </div>
            </div>

           
        </main>

        <!-- Bottom Navigation -->
        <nav class="bottom-nav">
            <a href="index.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="members.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Membership</span>
            </a>
            <a href="mem.php" class="nav-item">
                <i class="fas fa-user-friends"></i>
                <span>Members</span>
            </a>
            <a href="contact.php" class="nav-item active">
                <i class="fas fa-envelope"></i>
                <span>Contact</span>
            </a>
            <a href="story.php" class="nav-item">
                <i class="fas fa-heart"></i>
                <span>Stories</span>
            </a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user-circle"></i>
                    <span>Profile</span>
                </a>
            <?php else: ?>
                <a href="login.php" class="nav-item">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Login</span>
                </a>
            <?php endif; ?>
        </nav>

        <!-- Toast Notification -->
        <div class="toast" id="toast"></div>
    </div>

    <script>
        // Theme Management
        const themeToggle = document.getElementById('themeToggle');
        
        // Check for saved theme or preferred scheme
        const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');
        const currentTheme = localStorage.getItem('theme') || 
                           (prefersDarkScheme.matches ? 'dark' : 'light');
        
        // Set initial theme
        document.documentElement.setAttribute('data-theme', currentTheme);
        updateThemeIcon(currentTheme);
        
        // Toggle theme
        themeToggle.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });
        
        function updateThemeIcon(theme) {
            const icon = themeToggle.querySelector('i');
            icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
        
        // Back Button Function
        function goBack() {
            if (document.referrer && document.referrer.includes(window.location.host)) {
                window.history.back();
            } else {
                window.location.href = 'index.php';
            }
        }
        
        // FAQ Toggle Function
        function toggleFAQ(element) {
            const answer = element.nextElementSibling;
            const toggleIcon = element.querySelector('.faq-toggle');
            
            answer.classList.toggle('active');
            toggleIcon.classList.toggle('fa-plus');
            toggleIcon.classList.toggle('fa-minus');
            
            // Close other open FAQs
            const allFAQs = document.querySelectorAll('.faq-answer');
            allFAQs.forEach(faq => {
                if (faq !== answer && faq.classList.contains('active')) {
                    faq.classList.remove('active');
                    const otherToggle = faq.previousElementSibling.querySelector('.faq-toggle');
                    otherToggle.classList.remove('fa-minus');
                    otherToggle.classList.add('fa-plus');
                }
            });
        }
        
        // Form Validation and Submission
        function validateForm() {
            const form = document.getElementById('contactForm');
            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');
            
            // Basic validation
            const name = form.name.value.trim();
            const email = form.email.value.trim();
            const subject = form.subject.value.trim();
            const message = form.message.value.trim();
            
            if (!name || !email || !subject || !message) {
                showToast('Please fill in all fields', 'error');
                return false;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showToast('Please enter a valid email address', 'error');
                return false;
            }
            
            // Show loading state
            submitBtn.disabled = true;
            const originalHTML = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="loading-spinner"></span> Sending...';
            
            // Form is valid, allow submission
            // Re-enable button after 3 seconds if submission hangs
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;
            }, 3000);
            
            return true;
        }
        
        // Toast Function
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast ' + (type === 'error' ? 'error' : '');
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 4000);
        }
        
        // Initialize animations
        document.addEventListener('DOMContentLoaded', () => {
            const fadeElements = document.querySelectorAll('.fade-in');
            
            fadeElements.forEach(el => {
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
            });
        });

        // Handle quick action buttons for mobile
        document.addEventListener('DOMContentLoaded', function() {
            // Format phone numbers for tel: links
            const phoneLinks = document.querySelectorAll('[onclick*="tel:"]');
            phoneLinks.forEach(link => {
                const onclick = link.getAttribute('onclick');
                const cleanNumber = onclick.match(/tel:([^']+)/)[1];
                link.setAttribute('href', 'tel:' + cleanNumber);
            });
        });
    </script>
</body>
</html>