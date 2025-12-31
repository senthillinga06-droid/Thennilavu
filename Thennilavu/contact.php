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

// Fetch company details
$company_sql = "SELECT location, mobile_number, land_number, email FROM company_details LIMIT 1";
$company_result = $conn->query($company_sql);
$company_data = $company_result->fetch_assoc();

// Set default values if not found
$address = $company_data['location'] ?? '';
$phone1 = $company_data['mobile_number'] ?? '';
$phone2 = $company_data['land_number'] ?? '';
$email1 = $company_data['email'] ?? '';
$email2 = 'info@Thennilavu.com'; // Static second email as DB has only one email field

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('img/1.jpg') center/cover no-repeat fixed;
            overflow-x: hidden;
            line-height: 1.6;
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

        /* Heading effect (same as members.php) */
        .heading1 {
            text-align: center;
            color: transparent;
            -webkit-text-stroke: 1px #fff;
            background: url("./img/back.png");
            -webkit-background-clip: text;
            background-clip: text;
            animation: back 20s linear infinite;
            /* responsive size: small screens -> 1.6rem, scales with viewport, max 3.6rem */
            font-size: clamp(1.6rem, 6vw, 3.6rem);
            line-height: 1.05;
        }

        @keyframes back {
            100% {
                background-position: 2000px 0;
            }
        }

        /* Reduce stroke and size for very narrow phones (<= 375px) so heading doesn't look oversized */
        @media (max-width: 375px) {
            .heading1 {
                -webkit-text-stroke: 0.6px #fff;
                /* smaller clamp for narrow devices (375x667 etc.) */
                font-size: clamp(1.2rem, 4.5vw, 1.6rem);
            }
        }

        /* Slight adjustment for slightly wider small devices (376px - 400px) */
        @media (min-width: 376px) and (max-width: 400px) {
            .heading1 {
                -webkit-text-stroke: 0.8px #fff;
                font-size: clamp(1.4rem, 5.5vw, 2.0rem);
            }
        }

        /* Enhanced content sections */
        .content-section {
            background-color: white;
            color: #333;
            margin: 40px 50px;
            border-radius: 15px;
            padding: 50px 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
        }

        .content-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: var(--primary-gradient);
        }

        /* Contact Information Cards */
        .contact-info-card {
            background: linear-gradient(135deg, #ffffff 0%, #f9f9f9 100%);
            border-radius: 15px;
            padding: 35px 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            transition: all 0.4s ease;
            height: 100%;
            text-align: center;
            border: 1px solid rgba(253, 44, 121, 0.1);
            position: relative;
            overflow: hidden;
        }

        .contact-info-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--primary-gradient);
        }

        .contact-info-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.12);
        }

        .contact-icon {
            font-size: 2.8rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
            display: inline-block;
            transition: transform 0.3s ease;
        }

        .contact-info-card:hover .contact-icon {
            transform: scale(1.1);
        }

        .contact-info-card h4 {
            color: var(--secondary-color);
            margin-bottom: 15px;
            font-weight: 600;
        }

        .contact-info-card p {
            color: #555;
            margin-bottom: 5px;
        }

        /* About & Map Section */
        .about-content {
            padding-right: 30px;
        }

        .about-content h3 {
            color: var(--secondary-color);
            margin-bottom: 25px;
            font-weight: 700;
            position: relative;
            display: inline-block;
        }

        .about-content h3::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 50px;
            height: 3px;
            background: var(--primary-gradient);
            border-radius: 2px;
        }

        .about-content p {
            color: #555;
            margin-bottom: 20px;
            line-height: 1.7;
        }

        .social-icons {
            margin-top: 25px;
        }

        .social-icon {
            width: 45px;
            height: 45px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            border-radius: 50%;
            margin-right: 12px;
            transition: all 0.3s ease;
            font-size: 1.1rem;
        }

        .social-icon:hover {
            transform: translateY(-5px) scale(1.1);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
        }

        .map-container {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            height: 100%;
            min-height: 350px;
        }

        /* Contact Form Section */
        .contact-form-card {
            background: linear-gradient(135deg, #ffffff 0%, #f9f9f9 100%);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(253, 44, 121, 0.1);
            overflow: hidden;
        }

        .contact-form-header {
            background: var(--primary-gradient);
            color: white;
            padding: 25px 30px;
            text-align: center;
        }

        .contact-form-header h3 {
            margin: 0;
            font-weight: 600;
        }

        .contact-form-body {
            padding: 40px;
        }

        .form-control {
            border-radius: 10px;
            border: 1px solid #e1e1e1;
            padding: 14px 18px;
            transition: all 0.3s ease;
            font-size: 1rem;
            background-color: #fdfdfd;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(253, 44, 121, 0.15);
            outline: none;
            background-color: white;
        }

        .form-label {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 8px;
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: 10px;
            padding: 14px 35px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(253, 44, 121, 0.3);
            font-size: 1.1rem;
            letter-spacing: 0.5px;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(253, 44, 121, 0.4);
            background: linear-gradient(50deg, #ed0cbd, #fd2c79);
            color: white;
        }

        /* FAQ Section */
        .faq-accordion .accordion-item {
            border: 1px solid #e1e1e1;
            border-radius: 10px;
            margin-bottom: 15px;
            overflow: hidden;
        }

        .faq-accordion .accordion-button {
            background-color: #f9f9f9;
            color: var(--secondary-color);
            font-weight: 600;
            padding: 20px 25px;
            border-radius: 10px !important;
            transition: all 0.3s ease;
        }

        .faq-accordion .accordion-button:not(.collapsed) {
            background-color: rgba(253, 44, 121, 0.1);
            color: var(--primary-color);
            box-shadow: none;
        }

        .faq-accordion .accordion-button:focus {
            box-shadow: 0 0 0 0.2rem rgba(253, 44, 121, 0.15);
            border-color: var(--primary-color);
        }

        .faq-accordion .accordion-body {
            padding: 20px 25px;
            color: #555;
            line-height: 1.7;
            background-color: white;
        }

        /* Section Titles */
        .section-title {
            position: relative;
            text-align: center;
            font-weight: 700;
            margin-bottom: 50px;
            color: var(--secondary-color);
            font-size: 2.2rem;
        }

        .section-title::after {
            content: '';
            display: block;
            width: 80px;
            height: 4px;
            background: var(--primary-gradient);
            margin: 20px auto;
            border-radius: 2px;
        }

        /* Background Spacer */
        .bg-spacer {
            height: 80px;
        }

        /* Alert Styling */
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
        }

        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        /* Testimonials Section */
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
            color: #ffffff;
        }

        @media (min-width: 768px) {
            .testimonials-title {
                font-size: 3rem;
            }
        }

        .testimonials-subtitle {
            font-size: 1.125rem;
            color: #ffffff;
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
        }

        .profile-wrapper {
            display: inline-block;
            width: 64px;
            height: 64px;
            position: relative;
            margin-right: 1rem;
        }

        .rotating-border {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            padding: 4px;
            background: conic-gradient(from 0deg at 50% 50%, rgba(233,30,99,0.15), rgba(253,44,121,0.15));
            -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 6px), black calc(100% - 6px));
            mask: radial-gradient(farthest-side, transparent calc(100% - 6px), black calc(100% - 6px));
        }

        .profile-image {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            overflow: hidden;
            position: relative;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .testimonial-text {
            margin-top: 1rem;
            font-size: 0.95rem;
            color: #333;
            line-height: 1.6;
        }

        .testimonial-name {
            font-weight: 700;
            margin: 0;
        }

        .testimonial-role {
            color: #777;
            margin: 0;
        }

        .star-rating {
            margin-top: 1rem;
            display: flex;
            gap: 4px;
        }

        .star {
            width: 18px;
            height: 18px;
            fill: #ffd166;
        }

        /* Enhanced Navbar Styling */
        .navbar {
            background-color: rgba(0, 0, 0, 0.7); /* Black with 60% opacity */
            padding: 15px 0;
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
        }

        .navbar-toggler:focus {
            box-shadow: none;
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.8%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
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

        /* Hero Section */
        .hero-section {
            padding: 50px 0 30px;
            color: #fff;
            align-items: center;
            margin-top: 102px;
        }

        .hero-section h1 {
            font-size: 4rem;
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

        /* Footer */
        footer {
            background: var(--dark-color);
            color: #fff;
        }

        footer a {
            color: #ffe0e6;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        footer a:hover {
            color: var(--accent-color);
        }

        /* Mobile Navbar Improvements */
        @media (max-width: 991px) {
            .navbar {
                height: auto;
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
            
            .navbar-toggler-icon {
                width: 30px;
                height: 30px;
                background-size: 30px 30px;
            }
            
            .hero-section {
                padding: 120px 0 60px;
                min-height: auto;
            }
            
            .display-3 {
                font-size: 2.2rem;
            }
            
            .content-section {
                margin: 30px 20px;
                padding: 40px 20px;
            }
            
            .about-content {
                padding-right: 0;
                margin-bottom: 30px;
            }
        }

        @media (max-width: 768px) and (min-width: 577px) {
            .navbar {
                max-height: 73px;
            }
        }

        @media (max-width: 768px) {
            .hero-section {
                padding: 100px 0 50px;
                text-align: center;
            }
            
            .display-3 {
                font-size: 2rem;
            }
            
            .lead {
                font-size: 1.1rem;
            }
            
            .section-title {
                font-size: 1.8rem;
            }
            
            .content-section {
                margin: 20px 15px;
                padding: 30px 15px;
            }
            
            .contact-form-body {
                padding: 25px;
            }
            
            .bg-spacer {
                height: 50px;
            }
        }

        @media (max-width: 576px) {
            .navbar {
                max-height: 85px;
            }
            
            .navbar-brand img {
                max-width: 160px;
                height: 132px;
                width: auto;
            }
            
            .container {
                padding-left: 15px;
                padding-right: 15px;
            }
            
            .hero-section {
                padding: 80px 0 40px;
            }
            
            .display-3 {
                font-size: 1.8rem;
            }
            
            .lead {
                font-size: 1rem;
            }
            
            .section-title {
                font-size: 1.6rem;
            }
            
            .content-section {
                margin: 15px 10px;
                padding: 25px 15px;
            }
            
            .contact-form-body {
                padding: 20px;
            }
            
            .custom-btn {
                min-width: 100px;
                padding: 8px 15px;
                font-size: 0.85rem;
            }
            
            .btn-text {
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            .navbar {
                max-height: 90px;
            }
        }

        @media (max-width: 400px) {
            .navbar {
                max-height: 47px;
            }
            
            .navbar-brand img {
                max-width: 240px;
                margin-top: -28px;
                height: 134px;
                width: 200px;
            }
        }

        .heading1 {
            text-align:center;
            color:transparent;
            -webkit-text-stroke:1px #fff;
            background:url("./img/back.png");
            -webkit-background-clip:text;
            animation:back 20s linear infinite;
        }

@media (max-width: 768px) {
  .hero-section {
    padding: 80px 15px;
    background-position: center;
  }
}

@media (max-width: 576px) {
  .hero-section {
    padding: 60px 10px;
  }

  .hero-section h1 {
    font-size: 1.8rem;
  }

  .hero-section p {
    font-size: 1rem;
  }
}


        @keyframes back{
            100%{
                background-position:2000px 0;
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
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="members.php">Membership</a></li>
                    <li class="nav-item"><a class="nav-link" href="mem.php">Members</a></li>
                    <li class="nav-item"><a class="nav-link" href="package.php">Packages</a></li>
                    <li class="nav-item"><a class="nav-link active" href="contact.php">Contact Us</a></li>
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

    <!-- Hero Section -->
        <section class="hero-section">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-8 mx-auto text-center" data-aos="fade-up" data-aos-duration="1000">
                        <h1 class="display-4 fw-bold heading1">Get in Touch With Us</h1>
                        <p class="lead mt-3">We're here to help you find your perfect match. Reach out with any questions!</p>
                    </div>
                </div>
            </div>
        </section>

    <!-- Background Spacer -->
    <div class="bg-spacer"></div>

    <!-- Contact Information Section -->
    <section class="content-section" id="contact-info-section">
        <div class="container">
            <h2 class="section-title">Contact Information</h2>
            
            <div class="row g-4">
                <!-- Address Card -->
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="contact-info-card">
                        <div class="contact-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h4>Our Location</h4>
                        <p><?php echo htmlspecialchars($address); ?></p>
                    </div>
                </div>
                
                <!-- Contact Card -->
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="contact-info-card">
                        <div class="contact-icon">
                            <i class="fas fa-phone-alt"></i>
                        </div>
                        <h4>Phone Number</h4>
                        <p><?php echo htmlspecialchars($phone1); ?></p>
                        <p><?php echo htmlspecialchars($phone2); ?></p>
                    </div>
                </div>
                
                <!-- Email Card -->
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="contact-info-card">
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h4>Email Address</h4>
                        <p><?php echo htmlspecialchars($email1); ?></p>
                        <p><?php echo htmlspecialchars($email2); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Background Spacer -->
    <div class="bg-spacer"></div>

    <!-- About & Map Section -->
    <section class="content-section" id="about-map-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 about-content" data-aos="fade-right">
                    <h3>About Thennilavu </h3>
                    <p>Thennilavu Matrimony is a trusted and inclusive matrimonial platform designed to help individuals find their life partners with honesty, respect, and safety. While our roots are inspired by Tamil culture and traditions, we proudly welcome people from all backgrounds, communities, and cultures.</p>
                    <p>This is not just a Tamil-only site — it's a place for everyone seeking meaningful relationships built on mutual understanding and shared values.</p>
                    
                    
                </div>
                
                <div class="col-md-6" data-aos="fade-left">
                    <div class="map-container">
                        <iframe width="100%" height="350" id="gmap_canvas" src="https://maps.google.com/maps?q=colombo&t=&z=13&ie=UTF8&iwloc=&output=embed" frameborder="0" scrolling="no"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Background Spacer -->
    <div class="bg-spacer"></div>

    <!-- Contact Form Section -->
    <section class="content-section" id="contact-form-section">
        <div class="container">
            <h2 class="section-title">Send Us a Message</h2>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <div class="contact-form-card">
                        <div class="contact-form-header">
                            <h3>We'd Love to Hear From You</h3>
                        </div>
                        <div class="contact-form-body">
                            <form action="" method="POST">
                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <label for="name" class="form-label">Your Name</label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                    <div class="col-md-6 mb-4">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="subject" class="form-label">Subject</label>
                                    <input type="text" class="form-control" id="subject" name="subject" required>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="message" class="form-label">Message</label>
                                    <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                                </div>
                                
                                <div class="text-center mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg px-5">Send Message</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Background Spacer -->
    <div class="bg-spacer"></div>

    <!-- Testimonials Section -->
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
                <!-- populated by JS -->
            </div>

            <!-- Pagination Dots -->
            <div class="pagination" id="pagination"></div>
        </div>
    </section>

    <!-- Background Spacer -->
    <div class="bg-spacer"></div>

    <!-- FAQ Section -->
    <section class="content-section" id="faq-section">
        <div class="container">
            <h2 class="section-title">Frequently Asked Questions</h2>
            
            <div class="accordion faq-accordion" id="faqAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1" aria-expanded="true" aria-controls="faq1">
                            How do I create a profile?
                        </button>
                    </h2>
                    <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            You can create a profile by filling in the registration form with your personal, educational, and family details. After submitting, your profile will be reviewed and published.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2" aria-expanded="false" aria-controls="faq2">
                            Is my data safe and private?
                        </button>
                    </h2>
                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Yes, we take your privacy seriously. Your personal information is secured and only accessible by verified users.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3" aria-expanded="false" aria-controls="faq3">
                            How do I upgrade to a premium plan?
                        </button>
                    </h2>
                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            You can upgrade any time by visiting the "Our Packages" section and selecting the desired plan. Payment can be made via card, bank transfer, or mobile payment.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4" aria-expanded="false" aria-controls="faq4">
                            Can I contact other members directly?
                        </button>
                    </h2>
                    <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Contacting other members depends on your membership level. Premium and Elite members have full access to messaging features.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer Section -->
    <div id="footer-section">
        <footer class="bg-dark text-white pt-5 pb-3">
            <div class="container">
                <div class="row">
                    <div class="col-md-3 mb-4">
                        <h5 class="mb-3">Thennilavu</h5>
                        <p>Connecting hearts, building relationships since 2010. Your journey to finding the perfect life partner begins here.</p>
                    </div>
                    
                    <div class="col-md-3 mb-4">
                        <h5 class="mb-3">Quick Links</h5>
                        <ul class="list-unstyled">
                            <li class="mb-2"><a href="index.php">Home</a></li>
                            <li class="mb-2"><a href="members.php">Membership</a></li>
                            <li class="mb-2"><a href="package.php">Packages</a></li>
                            <li class="mb-2"><a href="contact.php">Contact Us</a></li>
                        </ul>
                    </div>
                    
                    <div class="col-md-3 mb-4">
                        <h5 class="mb-3">Legal</h5>
                        <ul class="list-unstyled">
                            <li class="mb-2"><a href="#">Privacy Policy</a></li>
                            <li class="mb-2"><a href="#">Terms of Service</a></li>
                            <li class="mb-2"><a href="#">Refund Policy</a></li>
                        </ul>
                    </div>
                    
                    <div class="col-md-3 mb-4">
                    <h5>Contact</h5>
                    <p>Email: info@Thennilavu.com</p>
                    <p>Location: Sri Lanka</p>
                    <p>Feel free to reach out to us for any support, Our team is here to help you find a perfect match with a smooth experience.</p>
                </div>
                </div>
                
                <hr class="my-4">
                
                <div class="text-center">
                    <p class="mb-0">&copy; 2025 Thennilavu Matrimony. All rights reserved.</p>
                </div>
            </div>
        </footer>
    </div>

    <script>
    // Testimonials JavaScript - adapted from home.php
    document.addEventListener('DOMContentLoaded', function() {
        // Build testimonials array from PHP and emit as JSON for safe JS parsing
        const testimonials = <?php
            $js_items = [];
            if ($result && $result->num_rows > 0) {
                $result->data_seek(0);
                $idx = 1;
                while ($row = $result->fetch_assoc()) {
                    $photo_path = !empty($row['photo']) ? 'uploads/' . $row['photo'] : 'img/default-avatar.jpg';
                    $js_items[] = [
                        'id' => $idx++,
                        'name' => htmlspecialchars($row['name']),
                        'role' => htmlspecialchars($row['profession'] . ', ' . $row['country']),
                        'image' => $photo_path,
                        'rating' => intval($row['rating']),
                        'text' => htmlspecialchars($row['comment'])
                    ];
                }
            } else {
                $js_items[] = [
                    'id' => 1,
                    'name' => 'Happy Client',
                    'role' => 'Member',
                    'image' => 'img/default-avatar.jpg',
                    'rating' => 5,
                    'text' => 'Thennilavu helped me find my perfect match. Highly recommended!',
                ];
            }
            echo json_encode($js_items, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        ?>;

        let currentIndex = 0;
        let isAnimating = false;

        const testimonialsGrid = document.getElementById('testimonialsGrid');
        const pagination = document.getElementById('pagination');

        function getVisibleTestimonials() {
            const visible = [];
            for (let i = 0; i < 3; i++) {
                const index = (currentIndex + i) % testimonials.length;
                visible.push(testimonials[index]);
            }
            return visible;
        }

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
                    <div class="star-rating">${generateStars(testimonial.rating)}</div>
                </div>
            `).join('');
        }

        function renderPagination() {
            pagination.innerHTML = testimonials.map((_, i) => `
                <button class="dot ${i === currentIndex ? 'active' : ''}" data-index="${i}" aria-label="Go to testimonial set ${i + 1}"></button>
            `).join('');

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

        function initTestimonials() {
            if (testimonials.length > 0) {
                renderTestimonials();
                renderPagination();
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

        initTestimonials();
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
    <script>
        // Initialize AOS animations
        AOS.init({
            duration: 800,
            once: true
        });
        
        // Heart animation
        document.addEventListener("mousemove", function(e) {
            if (Math.random() > 0.95) {
                const heart = document.createElement("div");
                heart.className = "heart";
                heart.innerHTML = "❤️";
                
                const driftX = Math.random() * 40 - 20;
                const scale = 1 + Math.random();
                const duration = 2 + Math.random() * 2;
                
                heart.style.left = e.pageX + "px";
                heart.style.top = e.pageY + "px";
                heart.style.transform = `translate(0, 0) scale(${scale})`;
                heart.style.animationDuration = `${duration}s`;
                
                document.body.appendChild(heart);
                
                setTimeout(() => {
                    heart.remove();
                }, duration * 1000);
            }
        });
    </script>
</body>
</html>