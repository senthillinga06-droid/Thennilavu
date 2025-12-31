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
    <meta name="google-site-verification" content="_VcFNF1Nsp5SPabY-5Pxj6b4GQiCHQ5XJ9AKzv-6xu0" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thennilavu Matrimony - Find Your Perfect Match</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Existing CSS code remains unchanged */
        :root {
            --primary-color: #fd2c79;
            --secondary-color: #2c3e50;
            --accent-color: #ed0cbd;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --primary-gradient: linear-gradient(50deg, #fd2c79, #ed0cbd);
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            overflow-x: hidden;
            /* Match the navbar height so the collapse aligns flush under the navbar */
            padding-top: 90px; /* was 80px; navbar height is 90px */
        }

        /* Enhanced Navbar Styling */
        .navbar {
            background-color: rgba(0, 0, 0, 0.7); /* Black with 60% opacity */
            padding: 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            height: 90px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.8rem;
            color: white !important;
            display: flex;
            align-items: center;
        }

        .navbar-brand img {
            margin-right: 10px;
            transition: transform 0.3s ease;
            /* Constrain logo height so it doesn't overflow the navbar and push content */
            height: 144px;
            display: block;
        }
        
        .navbar-brand:hover img {
            transform: rotate(10deg);
        }

        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.85) !important;
            font-weight: 500;
            margin: 0 8px;
            padding: 8px 16px !important;
            border-radius: 20px;
            transition: all 0.3s ease;
            position: relative;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: white !important;
            transform: translateY(-2px);
        }

        .navbar-nav .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: white;
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .navbar-nav .nav-link:hover::after,
        .navbar-nav .nav-link.active::after {
            width: 70%;
        }

        .navbar-toggler {
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 4px 8px;
            margin-top: -16px;
        }

        .navbar-toggler:focus {
            box-shadow: none;
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.8%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        /* Premium Button Styles with Icons */
        .ms-3.d-flex.gap-2 {
            padding: 10px;
            align-items: center;
            gap: 15px;
        }

        .custom-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 25px;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.4s ease;
            border: none;
            font-weight: 600;
            font-size: 0.95rem;
            min-width: 130px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
            position: relative;
            overflow: hidden;
            gap: 8px;
        }

        /* Dashboard Button */
        .dashboard-btn {
            background: linear-gradient(135deg, #e91e63, #ec407a, #f06292);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.25);
        }

        .dashboard-btn:hover {
            background: linear-gradient(135deg, #c2185b, #e91e63, #ec407a);
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 12px 30px rgba(233, 30, 99, 0.35);
            border-color: rgba(255, 255, 255, 0.4);
        }

        /* Logout Button */
        .logout-btn {
            background: linear-gradient(135deg, #ffffff, #f8f9fa, #e9ecef);
            color: #2c3e50;
            border: 2px solid rgba(255, 255, 255, 0.4);
        }

        .logout-btn:hover {
            background: linear-gradient(135deg, #c2185b, #e91e63, #ec407a);
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 12px 30px rgba(233, 30, 99, 0.35);
            border-color: rgba(255, 255, 255, 0.4);
            color: white;
        }

        /* Button Icons */
        .btn-icon {
            font-size: 1.1rem;
            transition: transform 0.3s ease;
        }

        .dashboard-btn:hover .btn-icon {
            transform: scale(1.2) rotate(5deg);
        }

        .logout-btn:hover .btn-icon {
            transform: scale(1.2) translateX(2px);
        }

        /* Text styles */
        .btn-text {
            color: inherit;
            font-weight: inherit;
            font-size: inherit;
        }

        /* New Hero Section with Slider */
        .new-hero-section {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            overflow: hidden;
            /* Keep hero aligned with navbar — match the values above */
            margin-top: -90px; /* Compensate for body padding (now 90px) */
            padding-top: 90px; /* Add padding to prevent content overlap */
        }
        
        /* Background Slider */
        .hero-slider {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }
        
        .hero-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
            opacity: 0;
            transition: opacity 1.5s ease-in-out;
        }
        
        .hero-slide.active {
            opacity: 1;
        }
        
        .hero-slide::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3));
            z-index: 2;
        }
        
        /* Hero Content */
        .hero-content {
            position: relative;
            z-index: 10;
            width: 100%;
        }
        
        .hero-left {
            color: white;
            padding-right: 2rem;
            position: relative;
            z-index: 11;
        }
        
        .hero-welcome {
            color: #e91e63;
            font-size: 1.6rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }
        
        .hero-welcome i {
            font-size: 1.5rem;
            filter: drop-shadow(2px 2px 4px rgba(0,0,0,0.5));
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 2rem;
            color: #e91e63;
            text-shadow: 3px 3px 6px rgba(0,0,0,0.5);
        }
        
        .hero-buttons {
            display: flex;
            gap: 20px;
            margin-top: 2rem;
        }
        
        .hero-btn {
            padding: 8px 23px;
            border-radius: 15px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 2px solid #e91e63;
            font-size: 1.0rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .hero-btn.primary {
            background: #e91e63;
            color: white;
        }
        
        .hero-btn.primary:hover {
            background: #ad1457;
            border-color: #ad1457;
            transform: translateY(-3px);
            color: white;
            box-shadow: 0 8px 25px rgba(233, 30, 99, 0.4);
        }
        
        /* Search Form */
        .search-form-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            margin-left: 2rem;
            position: relative;
            z-index: 20;
        }
        
        .search-form-title {
            color: #e91e63;
            font-size: 1.8rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .search-form-container .form-label {
            color: #e91e63;
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
            font-size: 0.9rem;
        }
        
        .search-form-container .form-select, 
        .search-form-container .form-control {
            border: 2px solid #f0f0f0;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #fafafa;
        }
        
        .search-form-container .form-select:focus, 
        .search-form-container .form-control:focus {
            border-color: #e91e63;
            box-shadow: 0 0 0 0.2rem rgba(233, 30, 99, 0.25);
            background: white;
            outline: none;
        }
        
        .search-btn {
            background: linear-gradient(135deg, #e91e63, #f06292);
            color: white;
            border: none;
            border-radius: 15px;
            padding: 8px;
            font-weight: 600;
            font-size: 1.1rem;
            width: 100%;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .search-btn:hover {
            background: linear-gradient(135deg, #ad1457, #e91e63);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(233, 30, 99, 0.3);
        }
        
        /* Success Stories Section Styling */
        #happy-stories {
            background: #F8E9C0;
            position: relative;
            overflow: hidden;
        }
        
        #happy-stories::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(233, 30, 99, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(233, 30, 99, 0.03) 0%, transparent 50%);
            pointer-events: none;
        }
        
        .story-card {
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            height: 100%;
            position: relative;
            border: 1px solid #f0f0f0;
        }
        
        .story-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }
        
        .story-image {
            height: 250px;
            overflow: hidden;
            position: relative;
            background: linear-gradient(45deg, #f0f0f0, #e0e0e0);
        }
        
        .story-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.3s ease;
        }
        
        .story-card:hover .story-image img {
            transform: scale(1.05);
        }
        
        /* Pink Hover Overlay */
        .story-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, #e91e63, #f06292);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 30px;
            text-align: center;
            opacity: 0;
            transition: all 0.4s ease;
            z-index: 10;
        }
        
        .story-card:hover .story-overlay {
            opacity: 1;
        }
        
        .story-overlay h3 {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .story-overlay p {
            color: rgba(255,255,255,0.95);
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 20px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .story-overlay .read-more-btn {
            background: white;
            color: #e91e63;
            padding: 10px 25px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .story-overlay .read-more-btn:hover {
            background: rgba(255,255,255,0.9);
            color: #e91e63;
            transform: translateY(-2px);
        }
        
        .story-date {
            position: absolute;
            top: 15px;
            left: 15px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
            z-index: 5;
        }
        
        .story-heart {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            z-index: 5;
        }
        
        .story-heart i {
            color: #e91e63;
            font-size: 1.2rem;
        }
        
        .story-card:hover .story-heart {
            transform: scale(1.1);
            background: #e91e63;
        }
        
        .story-card:hover .story-heart i {
            color: white;
        }
        
        .story-author {
            color: #e91e63;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .story-label {
            color: #999;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .read-more-btn {
            background: linear-gradient(135deg, #e91e63, #f06292);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .read-more-btn:hover {
            background: linear-gradient(135deg, #ad1457, #e91e63);
            color: white;
            transform: translateX(5px);
        }

        /* Modern Package Cards Styling */
        .packages-section {
            background: #F8E9C0;
            position: relative;
        }
        
        .home-package-card {
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            height: 100%;
            position: relative;
            border: 1px solid #f0f0f0;
        }
        
        .home-package-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        
        .package-header {
            background: linear-gradient(135deg, #e91e63, #f06292);
            color: white;
            padding: 20px;
            text-align: center;
            position: relative;
        }
        
        .package-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 15px solid transparent;
            border-right: 15px solid transparent;
            border-top: 15px solid #e91e63;
        }
        
        .package-name {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .package-content {
            padding: 30px 20px 20px;
            text-align: center;
        }
        
        .package-price {
            font-size: 2.5rem;
            font-weight: 700;
            color: #e91e63;
            margin-bottom: 15px;
        }
        
        .package-features {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }
        
        .package-features li {
            padding: 8px 0;
            color: #666;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: flex-start;
        }
        
        .package-features li::before {
            content: '✓';
            color: #e91e63;
            font-weight: bold;
            margin-right: 10px;
            width: 16px;
        }
        
        .package-buy-btn {
            background: linear-gradient(135deg, #e91e63, #f06292);
            color: white;
            border: none;
            padding: 10px 28px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            width: 100%;
            text-decoration: none;
            display: inline-block;
        }
        
        .package-buy-btn:hover {
            background: linear-gradient(135deg, #ad1457, #e91e63);
            color: white;
            transform: translateY(-2px);
        }
        
        .see-more-btn {
            background: linear-gradient(135deg, #e91e63, #f06292);
            color: white;
            border: none;
            padding: 12px 70px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-top: 30px;
        }
        
        .see-more-btn:hover {
            background: linear-gradient(135deg, #ad1457, #e91e63);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(233, 30, 99, 0.3);
        }

        /* About Us Section Styling */
        .about-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #fff5f8 100%);
            position: relative;
            overflow: hidden;
        }
        
        .about-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 80%, rgba(233, 30, 99, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(233, 30, 99, 0.03) 0%, transparent 50%);
            pointer-events: none;
        }
        
        .about-image-container {
            position: relative;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .about-image-circle {
            width: 500px;
            height: 500px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto;
            position: relative;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            border: 8px solid #ffffff;
        }
        
        .about-image-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .about-content {
            padding: 30px 0;
        }
        
        .about-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 25px;
        }
        
        .about-description {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #666;
            margin-bottom: 40px;
        }
        
        .about-features {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        
        .about-feature {
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        
        .feature-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #e91e63, #f06292);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 5px;
            box-shadow: 0 6px 20px rgba(233, 30, 99, 0.25);
            transition: all 0.3s ease;
        }
        
        .feature-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(233, 30, 99, 0.35);
        }
        
        .feature-icon i {
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .feature-content h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .feature-content p {
            font-size: 0.95rem;
            color: #666;
            margin: 0;
            line-height: 1.5;
        }

        /* Enhanced Testimonials Section - Exact CSS from testimonials.html */
        .testimonials-section {
            --rose: 330 85% 55%;
            --rose-light: 340 80% 65%;
            --rose-glow: 335 90% 70%;
            background: #edcfdbff;
            --foreground: 222.2 84% 4.9%;
            --card: 0 0% 100%;
            --card-foreground: 222.2 84% 4.9%;
            --muted: 210 40% 96.1%;
            --muted-foreground: 215.4 16.3% 46.9%;
            --border: 214.3 31.8% 91.4%;
            padding: 5rem 1rem;
            background-color: hsl(var(--background));
        }

        .testimonials-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .testimonials-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .testimonials-title {
            font-size: 2.25rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        @media (min-width: 768px) {
            .testimonials-title {
                font-size: 3rem;
            }
        }

        .testimonials-subtitle {
            font-size: 1.125rem;
            color: black/hsl(var(--muted-foreground));
            max-width: 48rem;
            margin: 0 auto;
        }

        .testimonials-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
            transition: opacity 0.5s ease;
        }

        @media (min-width: 768px) {
            .testimonials-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        .testimonial-card {
            background-color: hsl(var(--card));
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            transition: box-shadow 0.3s ease;
        }

        .testimonial-card:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .profile-container {
            display: flex;
            justify-content: center;
            margin-bottom: 1.5rem;
        }

        .profile-wrapper {
            position: relative;
            width: 8rem;
            height: 8rem;
        }

        .rotating-border {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: linear-gradient(to right, 
                hsl(var(--rose)), 
                hsl(var(--rose-light)), 
                hsl(var(--rose-glow)));
            animation: rotate-border 3s linear infinite;
        }

        .profile-image {
            position: absolute;
            inset: 0.25rem;
            border-radius: 50%;
            background-color: hsl(var(--background));
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .profile-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .testimonial-text {
            color: hsl(var(--muted-foreground));
            text-align: center;
            margin-bottom: 1.5rem;
            min-height: 120px;
        }

        .testimonial-info {
            text-align: center;
            margin-bottom: 1rem;
        }

        .testimonial-name {
            font-weight: 700;
            font-size: 1.125rem;
            color: hsl(var(--foreground));
        }

        .testimonial-role {
            font-size: 0.875rem;
            color: hsl(var(--muted-foreground));
        }

        .star-rating {
            display: flex;
            justify-content: center;
            gap: 0.25rem;
        }

        .star {
            width: 1.25rem;
            height: 1.25rem;
        }

        .star.filled {
            fill: hsl(var(--rose));
            color: hsl(var(--rose));
        }

        .star.empty {
            fill: hsl(var(--muted));
            color: hsl(var(--muted));
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
        }

        .dot {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            background-color: hsl(var(--muted));
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
        }

        .dot:hover {
            background-color: hsl(var(--muted-foreground));
        }

        .dot.active {
            background-color: hsl(var(--rose));
            width: 2rem;
        }

        @keyframes rotate-border {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        @keyframes fade-in {
            0% {
                opacity: 0;
                transform: translateY(10px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fade-in 0.5s ease-out;
        }

        .opacity-0 {
            opacity: 0;
        }

        .opacity-100 {
            opacity: 1;
        }

        /* Hero section styling */
        .hero-section {
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('img/holding-hand.jpg') center/cover no-repeat;
            padding: 100px 0;
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        .section-title {
            position: relative;
            text-align: center;
            font-weight: 700;
            margin-bottom: 30px;
        }

        .section-title:after {
            content: '';
            display: block;
            width: 80px;
            height: 4px;
            background: var(--primary-gradient);
            margin: 15px auto;
            border-radius: 2px;
        }

        .card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .story-img {
            height: 300px;
            object-fit: cover;
            width: 100%;
            border-radius: 12px;
        }

        footer {
            background: var(--dark-color);
            color: #fff;
        }

        footer a {
            color: blue;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        footer a:hover {
            color: var(--accent-color);
        }

        .user-display {
            color: var(--light-color);
            font-weight: 500;
            padding: 5px 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            backdrop-filter: blur(5px);
        }

        /* Heart animation */
        .heart {
            position: absolute;
            color: var(--primary-color);
            font-size: 22px;
            pointer-events: none;
            animation: floatUp 2.5s ease-out forwards;
        }

        @keyframes floatUp {
            0% { transform: translate(0, 0) scale(1); opacity: 1; }
            100% { transform: translate(-30px, -150px) scale(1.2); opacity: 0; }
        }

        /* ====================== */
        /* MOBILE RESPONSIVENESS */
        /* ====================== */

        /* Mobile Navbar Improvements */
        @media (max-width: 991px) {
            .navbar {
                height: auto;
                /* match members.php */
                min-height: 93px;
                padding: 1px 0;
            }
            
            .navbar-collapse {
                background: linear-gradient(135deg, rgba(0, 0, 0, 0.95), rgba(20, 20, 20, 0.95));
                margin-top: -28px;
                padding: 20px 15px;
                border-radius: 12px;                
                margin-left: -10px;
                margin-right: -10px;
                box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
                border: 1px solid rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(10px);
            }
            
            .navbar-nav {
                text-align: center;
                margin-bottom: 15px;
                gap: 0px;
                display: flex;
                flex-direction: column;
            }
            
            .navbar-nav .nav-link {
                margin: 4px 0;
                padding: 12px 20px !important;
                display: block;
                border-radius: 8px;
                background: rgba(255, 255, 255, 0.08);
                font-size: 0.9rem;
                font-weight: 500;
                color: rgba(255, 255, 255, 0.9) !important;
                border: 1px solid rgba(255, 255, 255, 0.1);
                transition: all 0.3s ease;
                text-decoration: none;
            }
            
            .navbar-nav .nav-link:hover,
            .navbar-nav .nav-link.active {
                background: linear-gradient(135deg, #e91e63, #ec407a);
                color: white !important;
                transform: translateY(-2px);
                box-shadow: 0 8px 20px rgba(233, 30, 99, 0.3);
                border-color: rgba(255, 255, 255, 0.3);
            }
            
            .ms-3.d-flex.gap-2 {
                justify-content: center;
                flex-wrap: wrap;
                gap: 12px !important;
                padding: 15px 0 10px 0;
                border-top: 1px solid rgba(255, 255, 255, 0.1);
                margin-top: 10px;
            }
            
            .custom-btn {
                min-width: 120px;
                padding: 10px 20px;
                font-size: 0.85rem;
                font-weight: 600;
                border-radius: 8px;
                transition: all 0.3s ease;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .navbar-brand img {
                /* slightly larger logo on mobile */
                max-width: 220px;
                height: 140px;
                width: auto;
                display: block;
            }
            
            .navbar-toggler {
                padding: 6px 9px;
                font-size: 1.1rem;
                border: none;
                background: rgba(255, 255, 255, 0.15);
                border-radius: 6px;
            }
            
            .navbar-toggler:hover {
                background: rgba(255, 255, 255, 0.25);
            }
            
            /* larger menu icon */
            .navbar-toggler-icon {
                width: 30px;
                height: 30px;
                background-size: 30px 30px;
            }
            
            /* Fix hero section top spacing for mobile; push welcome down a bit */
            .new-hero-section {
                padding-top: 110px;
            }

            /* Move welcome text slightly down if needed */
            .hero-welcome {
                margin-top: 8px;
            }

            /* Hide Success Stories button on mobile */
            .hero-buttons .hero-btn.primary {
                display: none !important;
            }
        }

        /* Mobile Hero Section */
        @media (max-width: 768px) {
            .new-hero-section {
                min-height: auto;
                padding: 100px 0 50px;
            }
            
            .hero-left {
                padding-right: 0;
                text-align: center;
                margin-bottom: 3rem;
            }
            
            .hero-title {
                font-size: 2.2rem;
            }
            
            .hero-welcome {
                font-size: 1.3rem;
                justify-content: center;
            }
            
            .search-form-container {
                margin-left: 0;
                padding: 25px 20px;
            }
            
            .hero-buttons {
                justify-content: center;
                flex-direction: column;
                align-items: center;
                gap: 15px;
            }
            
            .hero-btn {
                width: 100%;
                max-width: 280px;
            }
        }

        /* Tablet view navbar height parity with members.php */
        @media (max-width: 768px) and (min-width: 577px) {
            .navbar {
                max-height: 73px;
            }
        }

        /* Small Mobile Devices - match members.php navbar height */
        @media (max-width: 576px) {
            .navbar {
                max-height: 85px;
            }
        }

        /* Extra Small Devices - match members.php navbar height */
        @media (max-width: 480px) {
            .navbar {
                max-height: 90px;
            }
        }

        /* Very small devices */
        @media (max-width: 400px) {
            .navbar {
                max-height: 47px;
            }
        }

        /* Mobile About Section */
        @media (max-width: 768px) {
            .about-image-circle {
                width: 300px;
                height: 300px;
            }
            
            .about-title {
                font-size: 2rem;
                text-align: center;
            }
            
            .about-features {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .about-content {
                padding: 20px 0;
                text-align: center;
            }
            
            .about-feature {
                justify-content: center;
                text-align: left;
            }
        }

        /* Mobile Packages Section */
        @media (max-width: 768px) {
            .home-package-card {
                margin-bottom: 20px;
            }
            
            .package-price {
                font-size: 2rem;
            }
            
            .package-name {
                font-size: 1.2rem;
            }
            
            .package-content {
                padding: 25px 15px 15px;
            }
            
            .see-more-btn {
                padding: 12px 40px;
                width: 100%;
                max-width: 280px;
            }
        }

        /* Mobile Success Stories */
        @media (max-width: 768px) {
            #happy-stories .col-lg-3 {
                margin-bottom: 30px;
            }
            
            .story-image {
                height: 220px;
            }
            
            .story-overlay {
                padding: 20px;
            }
            
            .story-overlay h3 {
                font-size: 1.3rem;
            }
            
            .story-overlay p {
                font-size: 0.9rem;
            }
        }

        /* Mobile Testimonials */
        @media (max-width: 768px) {
            .testimonials-section {
                padding: 3rem 1rem;
            }
            
            .testimonials-title {
                font-size: 1.8rem;
            }
            
            .testimonials-subtitle {
                font-size: 1rem;
            }
            
            .testimonial-card {
                padding: 1.5rem;
            }
            
            .profile-wrapper {
                width: 6rem;
                height: 6rem;
            }
        }

        /* Mobile Footer */
        @media (max-width: 768px) {
            footer .col-md-3 {
                margin-bottom: 30px;
                text-align: center;
            }
            
            footer .row {
                text-align: center;
            }
        }

        /* Small Mobile Devices */
        @media (max-width: 576px) {
            .navbar-brand img {
                max-width: 160px;
                height: 132px;
                width: auto;
            }
            
            .hero-title {
                font-size: 1.8rem;
            }
            
            .hero-welcome {
                font-size: 1.1rem;
            }
            
            .search-form-title {
                font-size: 1.5rem;
            }
            
            .about-image-circle {
                width: 250px;
                height: 250px;
            }
            
            .about-title {
                font-size: 1.7rem;
            }
            
            .section-title {
                font-size: 1.8rem;
            }
            
            .custom-btn {
                min-width: 100px;
                padding: 8px 15px;
                font-size: 0.85rem;
            }
            
            .testimonials-title {
                font-size: 1.6rem;
            }
        }

        /* Extra Small Devices */
        @media (max-width: 400px) {
            .navbar-brand img {
                max-width: 240px;
                margin-top: -28px;
                height: 134px;
                width: 200px;
            }
            
            .hero-title {
                font-size: 1.6rem;
            }
            
            .about-image-circle {
                width: 220px;
                height: 220px;
            }
            
            .about-title {
                font-size: 1.5rem;
            }
            
            .section-title {
                font-size: 1.6rem;
            }
            
            .package-name {
                font-size: 1.1rem;
            }
            
            .package-price {
                font-size: 1.8rem;
            }
        }

        /* Button container */
        .ms-3.d-flex.gap-2 {
            padding: 10px;
            align-items: center;
        }

        /* Base button styles */
        .custom-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            min-width: 100px;
        }

        /* Responsive adjustments */
        @media (max-width: 576px) {
            .custom-btn {
                padding: 8px 15px;
                min-width: 80px;
            }
            
            .btn-text {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <!-- Enhanced Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="img/LogoBG1.png" alt="Thennilavu Logo" width="240" height="144">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link active" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="members.php">Membership</a></li>
                    <li class="nav-item"><a class="nav-link" href="mem.php">Members</a></li>
                    <li class="nav-item"><a class="nav-link" href="package.php">Packages</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact Us</a></li>
                    <li class="nav-item"><a class="nav-link" href="story.php">Stories</a></li>
                </ul>
                <div class="ms-3 d-flex gap-2">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <!-- Logged in user buttons -->
                        <a href="profile.php" class="btn custom-btn dashboard-btn">
                            <span class="btn-text">Dashboard</span>
                        </a>
                        <a href="logout.php" class="btn custom-btn logout-btn">
                            <span class="btn-text">Log Out</span>
                        </a>
                    <?php else: ?>
                        <!-- Not logged in - show login and register buttons with existing styles -->
                        <a href="login.php" class="btn custom-btn dashboard-btn">
                            <span class="btn-text">Login</span>
                        </a>
                        <a href="signup.php" class="btn custom-btn logout-btn">
                            <span class="btn-text">Register</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- New Hero Section with Slider Background -->
    <section class="new-hero-section">
        <!-- Background Slider -->
        <div class="hero-slider">
            <div class="hero-slide active" style="background-image: url('img/1.jpg')"></div>
            <div class="hero-slide" style="background-image: url('img/bg1.jpg')"></div>
            <div class="hero-slide" style="background-image: url('img/2.jpg')"></div>
        </div>
        
        <!-- Hero Content -->
        <div class="container hero-content">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-left" data-aos="fade-right">
                        <div class="hero-welcome">
                            <i class="bi bi-heart-fill"></i>
                            Welcome <span style="color: white;"> To Thennilavu</span>
                        </div>
                        <h1 class="hero-title">
                            Find Your Perfect Life<br>
                            Partner With Us
                        </h1>
                        <div class="hero-buttons">
                            <a href="story.php" class="hero-btn primary">Success Stories</a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="search-form-container" data-aos="fade-left">
                        <h3 class="search-form-title">Find Your Partner</h3>
                        <form action="mem.php" method="POST">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label class="form-label">Country</label>
                                    <select class="form-select" name="country">
                                        <option value="">Select One</option>
                                        <option value="India">India</option>
                                        <option value="USA">USA</option>
                                        <option value="UK">UK</option>
                                        <option value="Canada">Canada</option>
                                        <option value="Australia">Australia</option>
                                        <option value="Germany">Germany</option>
                                        <option value="France">France</option>
                                        <option value="Sri Lanka">Sri Lanka</option>
                                        <option value="Malaysia">Malaysia</option>
                                        <option value="Singapore">Singapore</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">City</label>
                                    <input type="text" class="form-control" name="city" placeholder="Enter city">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Profession</label>
                                    <input type="text" class="form-control" name="profession" placeholder="Enter profession">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Marital Status</label>
                                    <select class="form-select" name="marital_status">
                                        <option value="">Select One</option>
                                        <option value="Never Married">Never Married</option>
                                        <option value="Divorced">Divorced</option>
                                        <option value="Widowed">Widowed</option>
                                        <option value="Separated">Separated</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Looking For</label>
                                    <select class="form-select" name="looking_for">
                                        <option value="">Select One</option>
                                        <option value="Bride">Bride</option>
                                        <option value="Groom">Groom</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Smoking Habits</label>
                                    <select class="form-select" name="smoking">
                                        <option value="">Select One</option>
                                        <option value="Non-Smoker">Non-Smoker</option>
                                        <option value="Social Smoker">Social Smoker</option>
                                        <option value="Regular Smoker">Regular Smoker</option>
                                        <option value="Trying to Quit">Trying to Quit</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Drinking Status</label>
                                    <select class="form-select" name="drinking">
                                        <option value="">Select One</option>
                                        <option value="Non-Drinker">Non-Drinker</option>
                                        <option value="Social Drinker">Social Drinker</option>
                                        <option value="Regular Drinker">Regular Drinker</option>
                                        <option value="Occasionally">Occasionally</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="search-btn">Search</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Us Section -->
    <section class="py-5 about-section" id="about">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="zoom-in" data-aos-duration="1000">
                    <div class="about-image-container">
                        <div class="about-image-circle">
                            <img src="img/112024-house-on-the-cloud.jpeg" alt="Happy Couple - Thennilavu Success Story">
                        </div>
                    </div>
                </div>
                <div class="col-lg-6" data-aos="fade-left" data-aos-duration="1000">
                    <div class="about-content">
                        <h2 class="about-title">About Us</h2>
                        <p class="about-description">
                            Thennilavu is a trusted marriage platform dedicated to helping individuals find 
                            their perfect life partners. We offer tailored matrimonial packages, a growing 
                            community of verified members, and inspiring success stories. We aim to 
                            connect hearts and build lifelong relationships with a seamless and secure 
                            matchmaking experience.
                        </p>
                        <div class="about-features">
                            <div class="about-feature">
                                <div class="feature-icon">
                                    <i class="bi bi-person-check-fill"></i>
                                </div>
                                <div class="feature-content">
                                    <h4>Contact genuine profiles</h4>
                                    <p>Connect with verified and authentic members in our trusted community</p>
                                </div>
                            </div>
                            <div class="about-feature">
                                <div class="feature-icon">
                                    <i class="bi bi-heart-fill"></i>
                                </div>
                                <div class="feature-content">
                                    <h4>Find perfect match quite easily</h4>
                                    <p>Advanced search and matching algorithms to find your ideal partner</p>
                                </div>
                            </div>
                            <div class="about-feature">
                                <div class="feature-icon">
                                    <i class="bi bi-shield-fill-check"></i>
                                </div>
                                <div class="feature-content">
                                    <h4>100% security for data and Profile</h4>
                                    <p>Your privacy and data security are our top priorities</p>
                                </div>
                            </div>
                            <div class="about-feature">
                                <div class="feature-icon">
                                    <i class="bi bi-star-fill"></i>
                                </div>
                                <div class="feature-content">
                                    <h4>Trusted Matrimonial agency in the world</h4>
                                    <p>Thousands of successful matches and happy couples worldwide</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Packages Section -->
    <section class="py-5 packages-section">
        <div class="container">
            <h2 class="section-title text-center mb-5">Our Packages</h2>
            <p class="text-center mb-5 text-muted">Choose the perfect plan for your journey to finding love</p>
            <div class="row g-4">
                <?php if (!empty($packages_result) && $packages_result->num_rows > 0): ?>
                    <?php while ($package = $packages_result->fetch_assoc()): ?>
                        <div class="col-lg-3 col-md-6" data-aos="fade-up">
                            <div class="home-package-card">
                                <div class="package-header">
                                    <h3 class="package-name"><?= htmlspecialchars($package['name']) ?></h3>
                                </div>
                                <div class="package-content">
                                    <div class="package-price">Rs <?= number_format($package['price'], 2) ?></div>
                                    <ul class="package-features">
                                        <li>Duration (<?= htmlspecialchars($package['duration_days']) ?> days)</li>
                                        <li>Profile Views (<?= htmlspecialchars($package['profile_views_limit']) ?>)</li>
                                        <li>Interest Express (<?= htmlspecialchars($package['interest_limit']) ?>)</li>
                                        <li>Search Access (<?= htmlspecialchars($package['search_access']) ?>)</li>
                                    </ul>
                                    <a href="package.php" class="package-buy-btn">Buy Now</a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center">
                            <h4>No packages available at the moment</h4>
                            <p>Check back soon for our membership plans.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="text-center">
                <a href="package.php" class="see-more-btn">See More</a>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="py-5 text-white" style="background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('img/holding-hand.jpg') center/cover fixed;">
        <div class="container">
            <h2 class="section-title">How It Works</h2>
            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="card h-100 bg-dark bg-opacity-75">
                        <div class="card-body text-center p-4">
                            <div class="display-4 text-warning mb-3">1</div>
                            <h3 class="card-title" style="color:blue;">Sign Up</h3>
                            <p style="color: #ccc;">Create your profile with ease by providing your basic details and preferences.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="card h-100 bg-dark bg-opacity-75">
                        <div class="card-body text-center p-4">
                            <div class="display-4 text-warning mb-3">2</div>
                            <h3 class="card-title" style="color:blue;">Choose a Package</h3>
                            <p style="color: #ccc;">Select a plan that fits your needs to access premium features and matches.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="card h-100 bg-dark bg-opacity-75">
                        <div class="card-body text-center p-4">
                            <div class="display-4 text-warning mb-3">3</div>
                            <h3 class="card-title" style="color:blue;">Connect</h3>
                            <p style="color: #ccc;">Explore verified profiles, send interests, and start meaningful conversations.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

     <!-- Happy Stories Section -->
    <section class="py-5" id="happy-stories">
        <div class="container">
            <h2 class="section-title text-center mb-3">Success Stories</h2>
            <p class="text-center text-muted mb-5" style="font-size: 1.1rem; max-width: 600px; margin: 0 auto 3rem;">
                Our successful stories are too verse. These are awesome, romantic, like a dream.
            </p>
            <div class="row g-4">
                <?php if ($stories_result && $stories_result->num_rows > 0): ?>
                    <?php 
                    $story_count = 0;
                    while ($story = $stories_result->fetch_assoc()): 
                        $story_count++;
                        // Add class to hide stories 5-8 on mobile
                        $mobile_class = ($story_count > 4) ? 'd-none d-md-block' : '';
                    ?>
                        <div class="col-lg-3 col-md-6 <?= $mobile_class ?>" data-aos="fade-up">
                            <div class="story-card">
                                <div class="story-image">
                                    <?php
                                    // Format the date
                                    $date = '';
                                    if (!empty($story['publish_date'])) {
                                        $date = date('F Y', strtotime($story['publish_date']));
                                    } else {
                                        $date = 'December 2024';
                                    }
                                    ?>
                                    <div class="story-date"><?= $date ?></div>
                                    <div class="story-heart">
                                        <i class="bi bi-heart-fill"></i>
                                    </div>
                                    <img src="<?= $story['author_photo'] ? 'https://administration.thennilavu.lk/' . htmlspecialchars($story['author_photo']) : 'img/wedding-rings.jpg' ?>"
                                        alt="<?= htmlspecialchars($story['title']) ?>">
                                    
                                    <!-- Pink Hover Overlay -->
                                    <div class="story-overlay">
                                        <h3><?= htmlspecialchars($story['title']) ?></h3>
                                        <p><?= htmlspecialchars(mb_strimwidth(strip_tags($story['content']), 0, 150, '...')) ?></p>
                                        <a href="story.php?blog_id=<?= $story['blog_id'] ?>" class="read-more-btn">
                                            Read More <i class="bi bi-arrow-right"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center">
                            <h4>No happy stories available at the moment</h4>
                            <p>Check back soon for inspiring love stories from our community.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Enhanced Testimonials Section -->
    <section class="py-5 testimonials-section" id="testimonials">
        <div class="testimonials-container">
            <!-- Header -->
            <div class="testimonials-header fade-in">
                <h2 class="testimonials-title">What Our Clients Say</h2>
                <p class="testimonials-subtitle">
                    Discover why thousands have found their perfect match through Thennilavu
                </p>
            </div>

            <!-- Testimonials Grid -->
            <div class="testimonials-grid opacity-100" id="testimonialsGrid">
                <!-- Testimonial cards will be populated by JavaScript -->
            </div>

            <!-- Pagination Dots -->
            <div class="pagination" id="pagination">
                <!-- Dots will be populated by JavaScript -->
            </div>
        </div>
    </section>

    <!-- Footer Section -->
    <footer class="bg-dark text-white pt-5 pb-3">
        <div class="container">
            <div class="row">
                <div class="col-md-3 mb-4">
                    <h5>Thennilavu Matrimony</h5>
                    <p>Thennilavu Matrimony brings hearts together. Find your perfect match in a trusted community. Begin your beautiful journey with us.</p>
                </div>
                <div class="col-md-3 mb-4">
                    <h5>About Us</h5>
                    <p>We are dedicated to helping you find your perfect match with our trusted matrimonial services.</p>
                </div>
                <div class="col-md-3 mb-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="members.php">Membership</a></li>
                        <li><a href="contact.php">Contact</a></li>
                        <li><a href="#">Login</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4">
                    <h5>Contact</h5>
                    <p>Email: info@Thennilavu.com</p>
                    <p>Location: Sri Lanka</p>
                    <p>Feel free to reach out to us for any support, Our team is here to help you find a perfect match with a smooth experience.</p>
                </div>
            </div>
            <div class="text-center pt-3 border-top">&copy; 2025 Thennilavu Matrimony. All rights reserved.</div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
    <script>
        AOS.init();
        
        // Testimonials JavaScript - Exact same as testimonials.html but with PHP data
        document.addEventListener('DOMContentLoaded', function() {
            // Convert PHP reviews data to JavaScript array
            const testimonials = [
                <?php if ($reviews_result && $reviews_result->num_rows > 0): ?>
                    <?php
                    // Reset the result pointer
                    $reviews_result->data_seek(0);
                    $testimonial_items = [];
                    while ($review = $reviews_result->fetch_assoc()) {
                    $photo_path = !empty($review['photo']) ? 'uploads/' . $review['photo'] : 'img/default-avatar.jpg';
                        $name = json_encode(htmlspecialchars($review['name']));
                        $role = json_encode(htmlspecialchars($review['profession'] . ', ' . $review['country']));
                        $image = json_encode($photo_path);
                        $text = json_encode(htmlspecialchars($review['comment']));
                        $rating = intval($review['rating']);
                        
                        $testimonial_items[] = '{
                            id: ' . (count($testimonial_items) + 1) . ',
                            name: ' . $name . ',
                            role: ' . $role . ',
                            image: ' . $image . ',
                            rating: ' . $rating . ',
                            text: ' . $text . '
                        }';
                    }
                    echo implode(',', $testimonial_items);
                    ?>
                <?php else: ?>
                    {
                        id: 1,
                        name: "Happy Client",
                        role: "Member",
                        image: "img/1.jpg",
                        rating: 5,
                        text: "Thennilavu helped me find my perfect match. Highly recommended!"
                    }
                <?php endif; ?>
            ];

            // State variables
            let currentIndex = 0;
            let isAnimating = false;

            // DOM elements
            const testimonialsGrid = document.getElementById('testimonialsGrid');
            const pagination = document.getElementById('pagination');

            // Get visible testimonials based on current index
            function getVisibleTestimonials() {
                const visible = [];
                for (let i = 0; i < 3; i++) {
                    const index = (currentIndex + i) % testimonials.length;
                    visible.push(testimonials[index]);
                }
                return visible;
            }

            // Generate star rating HTML
            function generateStars(rating) {
                let starsHTML = '';
                for (let i = 0; i < 5; i++) {
                    const starClass = i < rating ? 'star filled' : 'star empty';
                    starsHTML += `
                        <svg class="${starClass}" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                    `;
                }
                return starsHTML;
            }

            // Render testimonials
            function renderTestimonials() {
                const visibleTestimonials = getVisibleTestimonials();
                
                testimonialsGrid.innerHTML = visibleTestimonials.map(testimonial => `
                    <div class="testimonial-card">
                        <div class="profile-container">
                            <div class="profile-wrapper">
                                <div class="rotating-border"></div>
                                <div class="profile-image">
                                    <img src="${testimonial.image}" alt="${testimonial.name}">
                                </div>
                            </div>
                        </div>
                        <p class="testimonial-text">${testimonial.text}</p>
                        <div class="testimonial-info">
                            <h3 class="testimonial-name">${testimonial.name}</h3>
                            <p class="testimonial-role">${testimonial.role}</p>
                        </div>
                        <div class="star-rating">
                            ${generateStars(testimonial.rating)}
                        </div>
                    </div>
                `).join('');
            }

            // Render pagination dots
            function renderPagination() {
                pagination.innerHTML = testimonials.map((_, i) => `
                    <button class="dot ${i === currentIndex ? 'active' : ''}" 
                            data-index="${i}" 
                            aria-label="Go to testimonial set ${i + 1}">
                    </button>
                `).join('');
                
                // Add event listeners to dots
                document.querySelectorAll('.dot').forEach(dot => {
                    dot.addEventListener('click', () => {
                        const index = parseInt(dot.getAttribute('data-index'));
                        if (!isAnimating && index !== currentIndex) {
                            isAnimating = true;
                            testimonialsGrid.classList.add('opacity-0');
                            
                            setTimeout(() => {
                                currentIndex = index;
                                renderTestimonials();
                                renderPagination();
                                testimonialsGrid.classList.remove('opacity-0');
                                isAnimating = false;
                            }, 500);
                        }
                    });
                });
            }

            // Initialize testimonials
            function initTestimonials() {
                if (testimonials.length > 0) {
                    renderTestimonials();
                    renderPagination();
                    
                    // Auto-rotate testimonials every 4 seconds
                    setInterval(() => {
                        if (!isAnimating) {
                            isAnimating = true;
                            testimonialsGrid.classList.add('opacity-0');
                            
                            setTimeout(() => {
                                currentIndex = (currentIndex + 1) % testimonials.length;
                                renderTestimonials();
                                renderPagination();
                                testimonialsGrid.classList.remove('opacity-0');
                                isAnimating = false;
                            }, 500);
                        }
                    }, 4000);
                }
            }

            // Start testimonials
            initTestimonials();
        });
        
        // Hero Background Slider - Fixed Implementation
        document.addEventListener('DOMContentLoaded', function() {
            let currentSlide = 0;
            const slides = document.querySelectorAll('.hero-slide');
            const totalSlides = slides.length;
            
            console.log('Found', totalSlides, 'slides');
            
            function showSlide(index) {
                // Hide all slides
                slides.forEach(slide => {
                    slide.classList.remove('active');
                });
                
                // Show the current slide
                if (slides[index]) {
                    slides[index].classList.add('active');
                    console.log('Showing slide', index);
                }
            }
            
            function nextSlide() {
                currentSlide = (currentSlide + 1) % totalSlides;
                showSlide(currentSlide);
            }
            
            // Initialize first slide
            if (totalSlides > 0) {
                showSlide(0);
                // Auto-advance slides every 5 seconds
                setInterval(nextSlide, 5000);
            }
        });
        
        document.addEventListener("mousemove", function(e) {
            if (Math.random() > 0.8) {
                const heart = document.createElement("div");
                heart.className = "heart";
                heart.innerHTML = "❤️";
                heart.style.left = e.pageX + "px";
                heart.style.top = e.pageY + "px";
                document.body.appendChild(heart);
                setTimeout(() => { heart.remove(); }, 2500);
            }
        });
    </script>
</body>
</html>