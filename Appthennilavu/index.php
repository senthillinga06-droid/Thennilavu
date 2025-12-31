<?php
session_start();

// Database connection and initialization
$host = "localhost";
$user = "thennilavu_matrimonial";
$pass = "OYVuiEKfS@FQ";
$db   = "thennilavu_thennilavu"; 

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ensure search log table exists
$conn->query("CREATE TABLE IF NOT EXISTS search_queries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  looking_for VARCHAR(20) NULL,
  age_range VARCHAR(20) NULL,
  country VARCHAR(100) NULL,
  city VARCHAR(100) NULL,
  religion VARCHAR(50) NULL,
  user_ip VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Function to sanitize input data
function sanitizeInput($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

// Fetch latest reviews
$reviews_sql = "SELECT name, profession, country, comment, rating, photo 
                FROM reviews 
                ORDER BY review_date DESC 
                LIMIT 10";
$reviews_result = $conn->query($reviews_sql);

// Fetch packages
$packages_sql = "SELECT * FROM packages WHERE status = 'active' ORDER BY price ASC";
$packages_result = $conn->query($packages_sql);

// Fetch success stories
$stories_sql = "SELECT blog_id, title, author_name, author_photo, content, publish_date 
                FROM blog 
                WHERE status='published' 
                ORDER BY publish_date DESC LIMIT 8";
$stories_result = $conn->query($stories_sql);

// Process search if form submitted
$search_results = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ThenNilavu Matrimony - Find Your Perfect Match</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Modern Mobile-First Variables */
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

        /* Base Mobile Styles */
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
            padding-top: 60px; /* Space for header */
            padding-bottom: 70px; /* Space for bottom nav */
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
            background: rgba(var(--bg-primary-rgb), 0.9);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-logo {
            height: 36px;
            width: auto;
        }

        .header-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            display: none; /* Hidden on mobile, visible on larger screens */
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
            background: rgba(var(--bg-primary-rgb), 0.95);
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
            background: rgba(var(--primary-color-rgb), 0.1);
        }

        .nav-item:hover {
            color: var(--primary-color);
        }

        /* Hero Banner */
        .hero-banner {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .hero-banner::before {
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
            margin: 0 auto 20px;
        }

     /* Quick Actions */
.quick-actions {
    display: flex;
    gap: 12px;
    margin: 20px 16px;
    position: relative;   /* IMPORTANT */
    z-index: 1000;         /* IMPORTANT - brings buttons to top */
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
    text-decoration: none;

    position: relative;    /* IMPORTANT */
    z-index: 1001;          /* IMPORTANT */
    pointer-events: auto;   /* IMPORTANT */
}

.action-btn.primary {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    border: none;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--elevated-shadow);
}

.hero-banner::before {
  content: "";
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,0.5);
}


        /* Search Form - Mobile Optimized */
        .search-section {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin: 0 16px 24px;
            border: 1px solid var(--border-color);
        }

        .search-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-title i {
            color: var(--primary-color);
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .form-select, .form-input {
            width: 100%;
            padding: 14px 16px;
            background: var(--bg-primary);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            color: var(--text-primary);
            transition: var(--transition);
            appearance: none;
            -webkit-appearance: none;
        }

        .form-select:focus, .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
        }

        .search-btn {
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
            margin-top: 10px;
        }

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(233, 30, 99, 0.3);
        }

        /* Section Cards */
        .section-card {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin: 0 16px 20px;
            border: 1px solid var(--border-color);
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--primary-color);
        }

        .view-all {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Package Cards */
        .packages-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        @media (min-width: 480px) {
            .packages-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .package-card {
            background: var(--bg-primary);
            border-radius: var(--radius-md);
            padding: 20px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .package-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--elevated-shadow);
        }

        .package-header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
        }

        .package-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .package-price {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary-color);
        }

        .package-features {
            list-style: none;
            margin-bottom: 20px;
        }

        .package-features li {
            padding: 8px 0;
            color: var(--text-secondary);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
        }

        .package-features li::before {
            content: '✓';
            color: var(--primary-color);
            font-weight: bold;
            margin-right: 10px;
            width: 16px;
        }

        .package-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: block;
            text-align: center;
        }

        .package-btn:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
        }

        /* Success Stories */
        .stories-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        @media (min-width: 480px) {
            .stories-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .story-card {
            background: var(--bg-primary);
            border-radius: var(--radius-md);
            overflow: hidden;
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .story-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--elevated-shadow);
        }

        .story-image {
            height: 180px;
            overflow: hidden;
            position: relative;
        }

        .story-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }

        .story-card:hover .story-image img {
            transform: scale(1.05);
        }

        .story-date {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
            z-index: 1;
        }

        .story-content {
            padding: 16px;
        }

        .story-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .story-excerpt {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .story-link {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Testimonials */
        .testimonials-slider {
            position: relative;
            overflow: hidden;
            margin-bottom: 16px;
        }

        .testimonial-card {
            background: var(--bg-primary);
            border-radius: var(--radius-md);
            padding: 20px;
            border: 1px solid var(--border-color);
            margin-bottom: 12px;
        }

        .testimonial-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
        }

        .testimonial-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid var(--primary-light);
        }

        .testimonial-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .testimonial-info h4 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .testimonial-info p {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .testimonial-text {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 15px;
            font-style: italic;
        }

        .star-rating {
            display: flex;
            gap: 4px;
        }

        .star-rating i {
            color: #ffc107;
            font-size: 0.9rem;
        }

        /* About Section */
        .about-card {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin: 0 16px 24px;
            border: 1px solid var(--border-color);
        }

        .about-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 20px;
            border: 4px solid var(--primary-color);
            box-shadow: 0 8px 25px rgba(233, 30, 99, 0.2);
        }

        .about-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .about-description {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 25px;
            text-align: center;
        }

        .about-features {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
        }

        @media (min-width: 480px) {
            .about-features {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .about-feature {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .feature-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .feature-icon i {
            color: white;
            font-size: 0.9rem;
        }

        .feature-content h4 {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .feature-content p {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin: 0;
            line-height: 1.4;
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

        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive Adjustments */
        @media (min-width: 768px) {
            body {
                max-width: 480px;
                margin: 0 auto;
                border-left: 1px solid var(--border-color);
                border-right: 1px solid var(--border-color);
            }
            
            .header-title {
                display: block;
            }
            
            .bottom-nav .nav-item {
                font-size: 0.75rem;
            }
        }

        @media (max-width: 380px) {
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
            
            .action-btn {
                font-size: 0.8rem;
                padding: 12px 8px;
            }
        }
    </style>
</head>
<body>
    <!-- Theme Variables Script -->
    <script>
        // Convert CSS variables to RGB for backdrop filter support
        document.documentElement.style.setProperty('--bg-primary-rgb', '255, 255, 255');
        document.documentElement.style.setProperty('--primary-color-rgb', '233, 30, 99');
        
        // Update for dark mode
        if (document.documentElement.getAttribute('data-theme') === 'dark') {
            document.documentElement.style.setProperty('--bg-primary-rgb', '18, 18, 18');
        }
    </script>

    <!-- App Header -->
    <header class="app-header">
        <div class="header-left">
            <img src="img/LogoBG1.png" alt="TheanNilavu Logo" class="header-logo">
            <h1 class="header-title">ThenNilavu</h1>
        </div>
        <button class="theme-toggle" id="themeToggle">
            <i class="fas fa-moon"></i>
        </button>
    </header>

    <!-- Main Content -->
    <main>
        <!-- Hero Banner -->
        <section class="hero-banner">
            <div class="hero-icon">
                <i class="fas fa-heart"></i>
            </div>
            <h1 class="hero-title">Find Your Perfect Match</h1>
            <p class="hero-subtitle">Join thousands of successful couples who found love with us</p>
            <div class="quick-actions">
                <a href="login.php" class="action-btn primary">
                    <i class="fas fa-user-plus"></i>
                    Join Free
                </a>
                <a href="mem.php" class="action-btn">
                    <i class="fas fa-search"></i>
                    Browse Profiles
                </a>
            </div>
        </section>

      

        <!-- About Us -->
        <section class="about-card fade-in">
            <div class="about-image">
                <img src="img/112024-house-on-the-cloud.jpeg" alt="Happy Couple">
            </div>
            <h2 class="section-title">
                <i class="fas fa-info-circle"></i>
                About Us
            </h2>
            <p class="about-description">
                ThenNilavu is a trusted marriage platform dedicated to helping individuals find 
                their perfect life partners with a seamless and secure matchmaking experience.
            </p>
            <div class="about-features">
                <div class="about-feature">
                    <div class="feature-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="feature-content">
                        <h4>Verified Profiles</h4>
                        <p>Connect with genuine, verified members</p>
                    </div>
                </div>
                <div class="about-feature">
                    <div class="feature-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <div class="feature-content">
                        <h4>Perfect Match</h4>
                        <p>Advanced algorithms for better matches</p>
                    </div>
                </div>
                <div class="about-feature">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="feature-content">
                        <h4>100% Secure</h4>
                        <p>Your privacy is our top priority</p>
                    </div>
                </div>
                <div class="about-feature">
                    <div class="feature-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="feature-content">
                        <h4>Trusted Agency</h4>
                        <p>Thousands of successful matches</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Packages -->
        <section class="section-card fade-in">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-gem"></i>
                    Our Packages
                </h2>
                <a href="package.php" class="view-all">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <div class="packages-grid">
                <?php if (!empty($packages_result) && $packages_result->num_rows > 0): ?>
                    <?php 
                    $package_count = 0;
                    while ($package = $packages_result->fetch_assoc()): 
                        $package_count++;
                        if ($package_count > 2) break; // Show only 2 on mobile
                    ?>
                        <div class="package-card">
                            <div class="package-header">
                                <h3 class="package-name"><?= htmlspecialchars($package['name']) ?></h3>
                                <div class="package-price">Rs <?= number_format($package['price'], 0) ?></div>
                            </div>
                            <ul class="package-features">
                                <li><?= htmlspecialchars($package['duration_days']) ?> Days</li>
                                <li><?= htmlspecialchars($package['profile_views_limit']) ?> Profile Views</li>
                                <li><?= htmlspecialchars($package['interest_limit']) ?> Interests</li>
                                <li><?= htmlspecialchars($package['search_access']) ?> Search Access</li>
                            </ul>
                            <a href="package.php" class="package-btn">View Details</a>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="package-card">
                        <div class="package-header">
                            <h3 class="package-name">No Packages Available</h3>
                        </div>
                        <p style="color: var(--text-secondary); text-align: center; padding: 20px 0;">
                            Check back soon for our membership plans.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Success Stories -->
        <section class="section-card fade-in">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-heart-circle-check"></i>
                    Success Stories
                </h2>
                <a href="story.php" class="view-all">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <div class="stories-grid">
                <?php if ($stories_result && $stories_result->num_rows > 0): ?>
                    <?php 
                    $story_count = 0;
                    // Reset pointer
                    $stories_result->data_seek(0);
                    while ($story = $stories_result->fetch_assoc()): 
                        $story_count++;
                        if ($story_count > 2) break; // Show only 4 on mobile
                        $date = !empty($story['publish_date']) ? date('M Y', strtotime($story['publish_date'])) : 'Dec 2024';
                    ?>
                        <div class="story-card">
                            <div class="story-image">
                                <div class="story-date"><?= $date ?></div>
                                <img src="<?= $story['author_photo'] ? 'https://administration.thennilavu.lk/' . htmlspecialchars($story['author_photo']) : 'img/wedding-rings.jpg' ?>" 
                                     alt="<?= htmlspecialchars($story['title']) ?>">
                            </div>
                            <div class="story-content">
                                <h3 class="story-title"><?= htmlspecialchars($story['title']) ?></h3>
                                <p class="story-excerpt"><?= htmlspecialchars(mb_strimwidth(strip_tags($story['content']), 0, 80, '...')) ?></p>
                                <a href="story.php?blog_id=<?= $story['blog_id'] ?>" class="story-link">
                                    Read Story <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="story-card">
                        <div class="story-content">
                            <h3 class="story-title">No Stories Available</h3>
                            <p class="story-excerpt">Check back soon for inspiring love stories from our community.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Testimonials -->
        <section class="section-card fade-in">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-star"></i>
                    Client Reviews
                </h2>
            </div>
            
            <div class="testimonials-slider">
                <?php if ($reviews_result && $reviews_result->num_rows > 0): ?>
                    <?php 
                    $review_count = 0;
                    // Reset pointer
                    $reviews_result->data_seek(0);
                    while ($review = $reviews_result->fetch_assoc()): 
                        $review_count++;
                        if ($review_count > 3) break; // Show only 3 on mobile
                        $photo_path = !empty($review['photo']) ? 'uploads/' . $review['photo'] : 'img/default-avatar.jpg';
                    ?>
                        <div class="testimonial-card">
                            <div class="testimonial-header">
                                <div class="testimonial-avatar">
                                    <img src="<?= $photo_path ?>" alt="<?= htmlspecialchars($review['name']) ?>">
                                </div>
                                <div class="testimonial-info">
                                    <h4><?= htmlspecialchars($review['name']) ?></h4>
                                    <p><?= htmlspecialchars($review['profession'] . ', ' . $review['country']) ?></p>
                                </div>
                            </div>
                            <p class="testimonial-text">"<?= htmlspecialchars($review['comment']) ?>"</p>
                            <div class="star-rating">
                                <?php for ($i = 0; $i < 5; $i++): ?>
                                    <i class="fas fa-star<?= $i < $review['rating'] ? '' : '-half-alt' ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="testimonial-card">
                        <p class="testimonial-text">"ThenNilavu helped me find my perfect match. Highly recommended!"</p>
                        <div class="testimonial-header">
                            <div class="testimonial-info">
                                <h4>Happy Client</h4>
                                <p>Member</p>
                            </div>
                        </div>
                        <div class="star-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

   
     <!-- Bottom Navigation with ALL Items -->
        <nav class="bottom-nav">
            <a href="index.php" class="nav-item active">
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
            
            <a href="contact.php" class="nav-item">
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

    <script>
        // Theme Management
        const themeToggle = document.getElementById('themeToggle');
        const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');
        
        // Check for saved theme or preferred scheme
        const currentTheme = localStorage.getItem('theme') || 
                           (prefersDarkScheme.matches ? 'dark' : 'light');
        
        // Set initial theme
        document.documentElement.setAttribute('data-theme', currentTheme);
        updateThemeIcon(currentTheme);
        updateThemeColors(currentTheme);
        
        // Toggle theme
        themeToggle.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
            updateThemeColors(newTheme);
        });
        
        function updateThemeIcon(theme) {
            const icon = themeToggle.querySelector('i');
            icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
        
        function updateThemeColors(theme) {
            if (theme === 'dark') {
                document.documentElement.style.setProperty('--bg-primary-rgb', '18, 18, 18');
            } else {
                document.documentElement.style.setProperty('--bg-primary-rgb', '255, 255, 255');
            }
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
        
        // Simple testimonial slider
        let testimonialIndex = 0;
        const testimonialCards = document.querySelectorAll('.testimonial-card');
        
        if (testimonialCards.length > 1) {
            setInterval(() => {
                testimonialCards[testimonialIndex].style.display = 'none';
                testimonialIndex = (testimonialIndex + 1) % testimonialCards.length;
                testimonialCards[testimonialIndex].style.display = 'block';
            }, 5000);
        }
    </script>
</body>
</html>