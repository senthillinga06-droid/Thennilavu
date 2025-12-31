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

// Get user_id from session
$user_id = $_SESSION['user_id'] ?? null;

// Handle package request with payment slip
$request_message = '';
if (isset($_POST['submit_package_request']) && $user_id) {
    $package_id = intval($_POST['package_id']);
    $package_name = $_POST['package_name'];
    $package_duration = intval($_POST['package_duration']);
    
    // Handle file upload
    $slip_url = '';
    if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] == 0) {
        $upload_dir = 'uploads/payment_slips/';
        if (!file_exists($upload_dir)) {  
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['payment_slip']['name'], PATHINFO_EXTENSION);
        $file_name = 'slip_' . $user_id . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['payment_slip']['tmp_name'], $upload_path)) {
            $slip_url = $upload_path;
        }
    }
    
    // Insert into userpackage table
    $stmt = $conn->prepare("INSERT INTO userpackage (user_id, status, duration, requestPackage, slip) VALUES (?, ?, ?, 'yes', ?)");
    $stmt->bind_param("isis", $user_id, $package_name, $package_duration, $slip_url);
    
    if ($stmt->execute()) {
        $request_message = '<div class="alert alert-success">Package request submitted successfully! Please wait for admin approval.</div>';
    } else {
        $request_message = '<div class="alert alert-danger">Error submitting request. Please try again.</div>';
    }
    $stmt->close();
}

// Fetch packages from database
$sql = "SELECT * FROM packages WHERE status = 'active' ORDER BY price ASC";
$result = $conn->query($sql);
$packages = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $packages[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Packages - Thennilavu Matrimony</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
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
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('img/holding-hand.jpg') center/cover no-repeat fixed;
            color: #333;
            overflow-x: hidden;
        }
        
        /* Enhanced Navbar Styling */
        .navbar {
            background-color: var(--primary-color);
            padding: 15px 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
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

        /* Dashboard button */
        .dashboard-btn {
            background: linear-gradient(135deg, rgba(253, 44, 121, 0.3), rgba(237, 12, 189, 0.1));
            backdrop-filter: blur(5px);
        }

        .dashboard-btn:hover {
            background: linear-gradient(135deg, rgba(253, 44, 121, 0.4), rgba(237, 12, 189, 0.2));
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }

        /* Log Out button */
        .logout-btn {
            background: linear-gradient(135deg, rgba(253, 44, 121, 0.3), rgba(237, 12, 189, 0.1));
        }

        .logout-btn:hover {
            background: linear-gradient(135deg, rgba(253, 44, 121, 0.4), rgba(237, 12, 189, 0.2));
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }

        /* Text styles */
        .btn-text {
            color: #ffffff;
            font-weight: 600;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
        
        /* Enhanced Content Sections */
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

        .hero-section {
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('img/holding-hand.jpg') center/cover no-repeat;
            padding: 120px 0 80px;
            color: white;
            text-align: center;
            margin-top: 76px;
        }
        
        .section-title {
            position: relative;
            margin-bottom: 40px;
            font-weight: 700;
            color: white;
            font-size: 2.5rem;
        }
        
        .section-title:after {
            content: '';
            display: block;
            width: 80px;
            height: 4px;
            background: var(--primary-gradient);
            margin: 20px auto;
            border-radius: 2px;
        }
        
        .section-title.dark {
            color: #333;
        }
        
        .section-title.dark:after {
            background: var(--primary-gradient);
        }
        
        /* Enhanced Package Cards */
        .package-card {
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.4s ease;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            height: 100%;
            background: #ffffff;
            border: 1px solid #f0f0f0;
            position: relative;
        }
        
        .package-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .package-header {
            background: var(--primary-gradient);
            color: white;
            padding: 35px 25px 25px;
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
            border-left: 20px solid transparent;
            border-right: 20px solid transparent;
            border-top: 20px solid var(--primary-color);
        }
        
        .package-name {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .package-price {
            font-size: 3.2rem;
            font-weight: 700;
            margin: 20px 0 5px;
            color: white;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .package-duration {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 0;
            font-weight: 500;
        }
        
        .package-features {
            padding: 35px 25px 30px;
            background: white;
        }
        
        .feature-item {
            padding: 14px 0;
            display: flex;
            align-items: center;
            color: #555;
            font-size: 1rem;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .feature-item:last-child {
            border-bottom: none;
        }
        
        .feature-icon {
            color: var(--primary-color);
            margin-right: 15px;
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }
        
        .popular-package {
            position: relative;
            transform: scale(1.05);
            z-index: 1;
            border: 3px solid var(--primary-color);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        
        .popular-package .package-header {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
        }
        
        .popular-badge {
            position: absolute;
            top: -12px;
            right: 25px;
            background: var(--primary-gradient);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            z-index: 2;
        }
        
        .btn-package {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 16px 35px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 25px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(253, 44, 121, 0.3);
        }
        
        .btn-package:hover {
            background: linear-gradient(50deg, #ed0cbd, #fd2c79);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(253, 44, 121, 0.4);
            color: white;
        }
        
        /* Gold Package Styling */
        .package-card.gold-package .package-header {
            background: linear-gradient(135deg, #FFD700, #FFA500);
        }
        
        .package-card.gold-package .feature-icon {
            color: #FFA500;
        }
        
        .package-card.gold-package .btn-package {
            background: linear-gradient(135deg, #FFD700, #FFA500);
        }
        
        .package-card.gold-package .btn-package:hover {
            background: linear-gradient(135deg, #FFA500, #FF8C00);
        }
        
        /* Diamond Package Styling */
        .package-card.diamond-package .package-header {
            background: linear-gradient(135deg, #B9F2FF, #00BFFF);
        }
        
        .package-card.diamond-package .feature-icon {
            color: #00BFFF;
        }
        
        .package-card.diamond-package .btn-package {
            background: linear-gradient(135deg, #B9F2FF, #00BFFF);
        }
        
        .package-card.diamond-package .btn-package:hover {
            background: linear-gradient(135deg, #00BFFF, #1E90FF);
        }
        
        /* Enhanced Counter Boxes */
        .counter-box {
            text-align: center;
            padding: 40px 25px;
            background: linear-gradient(135deg, #ffffff 0%, #f9f9f9 100%);
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            transition: all 0.4s ease;
            border: 1px solid rgba(253, 44, 121, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .counter-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--primary-gradient);
        }
        
        .counter-box:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.12);
        }
        
        .counter-box h2 {
            font-size: 3rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 15px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .counter-box p {
            font-size: 1.2rem;
            color: #555;
            margin: 0;
            font-weight: 500;
        }
        
        /* Enhanced Table Styling */
        .table-responsive {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            background: white;
        }
        
        .table-dark {
            background: var(--primary-gradient) !important;
            color: white;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(253, 44, 121, 0.05);
            transform: scale(1.01);
            transition: all 0.3s ease;
        }
        
        .table th {
            border: none;
            padding: 20px 15px;
            font-weight: 600;
            text-align: center;
        }
        
        .table td {
            border: none;
            padding: 18px 15px;
            text-align: center;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0;
        }
        
        /* Enhanced FAQ Section */
        .faq-accordion .accordion-item {
            border: 1px solid #e1e1e1;
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .faq-accordion .accordion-item:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .faq-accordion .accordion-button {
            background-color: #f9f9f9;
            color: var(--secondary-color);
            font-weight: 600;
            padding: 22px 25px;
            border-radius: 12px !important;
            transition: all 0.3s ease;
            font-size: 1.1rem;
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
            padding: 22px 25px;
            color: #555;
            line-height: 1.7;
            background-color: white;
            font-size: 1rem;
        }
        
        /* Enhanced Modal Styling */
        .package-modal .modal-content {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            border: none;
        }
        
        .package-modal .modal-header {
            background: var(--primary-gradient);
            color: white;
            padding: 25px 30px;
            border-bottom: none;
        }
        
        .package-modal .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .package-modal .modal-body {
            padding: 30px;
            background: #fdfdfd;
        }
        
        .package-modal .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #f0f0f0;
            background: white;
        }
        
        .package-details {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            border-left: 4px solid var(--primary-color);
        }
        
        .payment-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-left: 4px solid #28a745;
        }
        
        .bank-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid #17a2b8;
        }
        
        .bank-info strong {
            color: var(--secondary-color);
            font-size: 1.1rem;
        }
        
        /* Enhanced Form Styling */
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
        
        /* Enhanced Button Styling */
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
        
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            border-radius: 10px;
            padding: 14px 35px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-success:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.4);
            background: linear-gradient(135deg, #20c997, #28a745);
            color: white;
        }
        
        /* Alert Styling */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 18px 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .alert-success {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.95), rgba(32, 201, 151, 0.95));
            color: white;
            border-left: 4px solid #28a745;
        }
        
        .alert-info {
            background: linear-gradient(135deg, rgba(23, 162, 184, 0.95), rgba(32, 201, 201, 0.95));
            color: white;
            border-left: 4px solid #17a2b8;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.95), rgba(255, 99, 132, 0.95));
            color: white;
            border-left: 4px solid #dc3545;
        }
        
        /* Heart Animation */
        .heart {
            position: absolute;
            color: var(--primary-color);
            font-size: 22px;
            pointer-events: none;
            animation: floatUp 3s ease-out forwards;
            opacity: 1;
            z-index: 9999;
        }
        
        @keyframes floatUp {
            0% {
                transform: translate(0, 0) scale(1);
                opacity: 1;
            }
            100% {
                transform: translate(-20px, -200px) scale(1.5);
                opacity: 0;
            }
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
        
        /* Enhanced responsive adjustments */
        @media (max-width: 992px) {
            .content-section {
                margin: 30px 20px;
                padding: 40px 20px;
            }
            
            .popular-package {
                transform: scale(1);
            }
            
            .package-price {
                font-size: 2.8rem;
            }
            
            .package-name {
                font-size: 1.4rem;
            }
            
            .package-header {
                padding: 30px 20px 20px;
            }
            
            .package-features {
                padding: 30px 20px 25px;
            }
        }

        @media (max-width: 768px) {
            .package-card {
                margin-bottom: 30px;
            }
            
            .counter-box {
                margin-bottom: 20px;
            }
            
            .package-price {
                font-size: 2.5rem;
            }
            
            .package-name {
                font-size: 1.3rem;
            }
            
            .btn-package {
                padding: 14px 30px;
                font-size: 1rem;
            }
            
            .hero-section {
                padding: 100px 0 60px;
            }
            
            .section-title {
                font-size: 2.2rem;
            }
        }
        
        @media (max-width: 576px) {
            .package-card {
                margin-bottom: 25px;
            }
            
            .package-price {
                font-size: 2.2rem;
            }
            
            .package-name {
                font-size: 1.2rem;
            }
            
            .btn-package {
                padding: 12px 25px;
                font-size: 0.95rem;
            }
            
            .hero-section {
                padding: 80px 0 50px;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .content-section {
                margin: 20px 15px;
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Enhanced Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="logo.png" alt="Thennilavu Logo" width="40" height="40">
               Thennilavu
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="home.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="members.php">Membership</a></li>
                    <li class="nav-item"><a class="nav-link" href="mem.php">Members</a></li>
                    <li class="nav-item"><a class="nav-link active" href="package.php">Packages</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact Us</a></li>
                    <li class="nav-item"><a class="nav-link" href="story.php">Stories</a></li>
                </ul>
                <div class="ms-3 d-flex gap-2">
                    <a href="profile.php" class="btn custom-btn dashboard-btn">
                        <span class="btn-text">Dashboard</span>
                    </a>
                    <a href="logout.php" class="btn custom-btn logout-btn">
                        <span class="btn-text">Log Out</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section" style="margin-top: 102px;">
        <div class="container">
            <h1 class="display-4 fw-bold">Our Membership Packages</h1>
            <p class="lead">Choose the plan that's right for your journey to finding your perfect match</p>
        </div>
    </section>

    <!-- Packages Section -->
    <section class="content-section" style="background: transparent; box-shadow: none; margin: 40px 0;">
        <div class="container"> 
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center mb-5">
                    <h2 class="section-title" style="color: white; font-size: 2.5rem;">Choose Your Perfect Plan</h2>
                    <p class="lead" style="color: #f0f0f0;">Select the package that best fits your needs and start your journey to finding your perfect match.</p>
                </div>
            </div>
            
            <?php if (!empty($request_message)): ?>
                <?php echo $request_message; ?>
            <?php endif; ?>
            <div id="requestMessage"></div>
            
            <div class="row g-4 justify-content-center">
                <?php if (empty($packages)): ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center">
                            <h4>No packages available at the moment</h4>
                            <p>Please check back later for our membership packages.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($packages as $index => $package): ?>
                        <?php 
                            $features = !empty($package['features']) ? explode(',', $package['features']) : [];
                            $isPopular = $index == 1; // Make second package popular
                            
                            // Assign package class based on name or index
                            $packageClass = '';
                            $packageName = strtolower($package['name']);
                            if (strpos($packageName, 'gold') !== false || $index == 0) {
                                $packageClass = 'gold-package';
                            } elseif (strpos($packageName, 'diamond') !== false || $index == 1) {
                                $packageClass = 'diamond-package';
                            }
                        ?>
                        <div class="col-md-4">
                            <div class="package-card <?php echo $packageClass; ?> <?php echo $isPopular ? 'popular-package' : ''; ?>">
                                <?php if ($isPopular): ?>
                                    <div class="popular-badge">MOST POPULAR</div>
                                <?php endif; ?>
                                <div class="package-header">
                                    <h3 class="package-name"><?php echo htmlspecialchars($package['name']); ?></h3>
                                    <div class="package-price">$<?php echo number_format($package['price'], 2); ?></div>
                                    <div class="package-duration">Duration (<?php echo $package['duration_days']; ?>)</div>
                                </div>
                                
                                <div class="package-features">
                                    <div class="feature-item">
                                        <i class="bi bi-check feature-icon"></i>
                                        <span>Contact View (<?php echo htmlspecialchars($package['profile_views_limit']); ?>)</span>
                                    </div>
                                    <div class="feature-item">
                                        <i class="bi bi-check feature-icon"></i>
                                        <span>Interest Express (<?php echo htmlspecialchars($package['interest_limit']); ?>)</span>
                                    </div>
                                    <div class="feature-item">
                                        <i class="bi bi-check feature-icon"></i>
                                        <span>Image Upload (<?php echo htmlspecialchars($package['search_access']); ?>)</span>
                                    </div>

                                    <button type="button" class="btn-package" onclick="openPackageModal(<?php echo $package['package_id']; ?>, '<?php echo htmlspecialchars($package['name']); ?>', <?php echo $package['price']; ?>, <?php echo $package['duration_days']; ?>, '<?php echo htmlspecialchars($package['description'] ?? ''); ?>')">Buy Now</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Comparison Section -->
    <section class="content-section">
        <div class="container">
            <h2 class="text-center section-title dark">Package Comparison</h2>
            <?php if (!empty($packages)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Feature</th>
                                <?php foreach ($packages as $package): ?>
                                    <th class="text-center"><?php echo htmlspecialchars($package['name']); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Cost</strong></td>
                                <?php foreach ($packages as $package): ?>
                                    <td class="text-center">Rs. <?php echo htmlspecialchars(number_format($package['price'], 2)); ?></td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <td><strong>Duration</strong></td>
                                <?php foreach ($packages as $package): ?>
                                    <td class="text-center"><?php echo htmlspecialchars($package['duration_days']); ?> days</td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <td><strong>Profile Views count</strong></td>
                                <?php foreach ($packages as $package): ?>
                                    <td class="text-center"><?php echo htmlspecialchars($package['profile_views_limit']); ?></td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <td><strong>Interest</strong></td>
                                <?php foreach ($packages as $package): ?>
                                    <td class="text-center"><?php echo htmlspecialchars($package['interest_limit']); ?></td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <td><strong>Search</strong></td>
                                <?php foreach ($packages as $package): ?>
                                    <td class="text-center"><?php echo htmlspecialchars($package['search_access']); ?></td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <td><strong>Profile View</strong></td>
                                <?php foreach ($packages as $package): ?>
                                    <td class="text-center">
                                        <?php if (strtolower($package['profile_view_enabled']) === 'yes'): ?>
                                            <i class="bi bi-check-circle-fill text-success" style="font-size: 1.3rem;"></i>
                                        <?php else: ?>
                                            <i class="bi bi-x-circle-fill text-danger" style="font-size: 1.3rem;"></i>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <td><strong>Profile Hide</strong></td>
                                <?php foreach ($packages as $package): ?>
                                    <td class="text-center">
                                        <?php if (strtolower($package['profile_hide_enabled']) === 'yes'): ?>
                                            <i class="bi bi-check-circle-fill text-success" style="font-size: 1.3rem;"></i>
                                        <?php else: ?>
                                            <i class="bi bi-x-circle-fill text-danger" style="font-size: 1.3rem;"></i>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <td><strong>Matchmaker</strong></td>
                                <?php foreach ($packages as $package): ?>
                                    <td class="text-center">
                                        <?php if (strtolower($package['matchmaker_enabled']) === 'yes'): ?>
                                            <i class="bi bi-check-circle-fill text-success" style="font-size: 1.3rem;"></i>
                                        <?php else: ?>
                                            <i class="bi bi-x-circle-fill text-danger" style="font-size: 1.3rem;"></i>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info text-center">
                    <p>No packages available for comparison.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="content-section">
        <div class="container">
            <h2 class="text-center section-title dark">Why Choose Thennilavu?</h2>
            
            <div class="row text-center g-4">
                <div class="col-md-3">
                    <div class="counter-box">
                        <h2 class="counter" data-count="25000">0</h2>
                        <p>Total Members</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="counter-box">
                        <h2 class="counter" data-count="8700">0</h2>
                        <p>Success Stories</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="counter-box">
                        <h2 class="counter" data-count="1200">0</h2>
                        <p>Premium Members</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="counter-box">
                        <h2 class="counter" data-count="10">0</h2>
                        <p>Years of Service</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="content-section">
        <div class="container">
            <h2 class="text-center section-title dark">Frequently Asked Questions</h2>
            
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

    <!-- Package Modal -->
    <div class="modal fade package-modal" id="packageModal" tabindex="-1" aria-labelledby="packageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="packageModalLabel">Package Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="packageDetails">
                        <!-- Package details will be loaded here -->
                    </div>
                    
                    <div id="paymentSection" style="display: none;">
                        <div class="payment-section">
                            <h5 class="mb-4">Payment Information</h5>
                            <div class="bank-info mb-4">
                                <strong>Bank Details:</strong><br>
                                Bank: Commercial Bank<br>
                                Account: 1234567890<br>
                                Name: Thennilavu Matrimony
                            </div>
                            
                            <form id="paymentForm" enctype="multipart/form-data">
                                <input type="hidden" id="modalPackageId" name="package_id">
                                <input type="hidden" id="modalPackageName" name="package_name">
                                <input type="hidden" id="modalPackageDuration" name="package_duration">
                                
                                <div class="mb-4">
                                    <label for="paymentSlip" class="form-label">Upload Payment Slip *</label>
                                    <input type="file" class="form-control" id="paymentSlip" name="payment_slip" accept="image/*" required>
                                    <div class="form-text">Please upload a clear image of your payment receipt (JPG, PNG, or PDF)</div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success btn-lg">Submit Request</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="chooseBtn" onclick="showPaymentSection()">Choose This Package</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white pt-5 pb-3">
        <div class="container">
            <div class="row">
                <div class="col-md-3 mb-4">
                    <h5 class="mb-3"> Thennilavu </h5>
                    <p>Connecting hearts, building relationships since 2010. Your journey to finding the perfect life partner begins here.</p>
                </div>
                
                <div class="col-md-3 mb-4">
                    <h5 class="mb-3">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="home.php" class="text-white text-decoration-none">Home</a></li>
                        <li class="mb-2"><a href="members.php" class="text-white text-decoration-none">Membership</a></li>
                        <li class="mb-2"><a href="package.php" class="text-white text-decoration-none">Packages</a></li>
                        <li class="mb-2"><a href="contact.php" class="text-white text-decoration-none">Contact Us</a></li>
                    </ul>
                </div>
                
                <div class="col-md-3 mb-4">
                    <h5 class="mb-3">Legal</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-white text-decoration-none">Privacy Policy</a></li>
                        <li class="mb-2"><a href="#" class="text-white text-decoration-none">Terms of Service</a></li>
                        <li class="mb-2"><a href="#" class="text-white text-decoration-none">Refund Policy</a></li>
                    </ul>
                </div>
                
                <div class="col-md-3 mb-4">
                    <h5 class="mb-3">Contact Info</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="bi bi-geo-alt me-2"></i> Colombo, Sri Lanka</li>
                        <li class="mb-2"><i class="bi bi-envelope me-2"></i> info@Thennilavu.com</li>
                        <li class="mb-2"><i class="bi bi-telephone me-2"></i> +94 77 123 4567</li>
                    </ul>
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="text-center">
                <p class="mb-0">&copy; 2025  Thennilavu Matrimony. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Expose PHP packages array to JS for modal details
    window.packagesData = <?php echo json_encode($packages); ?>;
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
        
        // Counter animation
        function animateCounter(counter) {
            const target = +counter.getAttribute('data-count');
            let count = 0;
            const speed = 20;
            const increment = Math.ceil(target / 100);

            const updateCount = () => {
                count += increment;
                if (count >= target) {
                    counter.innerText = target.toLocaleString();
                } else {
                    counter.innerText = count.toLocaleString();
                    requestAnimationFrame(updateCount);
                }
            };
            updateCount();
        }

        function initCountersOnScroll() {
            const counters = document.querySelectorAll('.counter');
            const observer = new IntersectionObserver((entries, obs) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        animateCounter(entry.target);
                        obs.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.7 });

            counters.forEach(counter => observer.observe(counter));
        }

        document.addEventListener('DOMContentLoaded', initCountersOnScroll);
        
        // Package modal functions
        function openPackageModal(packageId, packageName, packagePrice, packageDuration, packageDescription) {
            document.getElementById('modalPackageId').value = packageId;
            document.getElementById('modalPackageName').value = packageName;
            document.getElementById('modalPackageDuration').value = packageDuration;
            
            // Find the package object from the packages array
            const pkg = (window.packagesData || []).find(p => p.package_id == packageId);
            let detailsHtml = `<div class="package-details">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="text-primary mb-2">${packageName}</h4>
                        <p class="text-muted mb-0">${packageDescription || 'Premium matrimony package with enhanced features'}</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <h3 class="text-primary mb-1">$${packagePrice}</h3>
                        <p class="text-muted mb-0">For ${packageDuration} days</p>
                    </div>
                </div>`;
            if (pkg) {
                detailsHtml += `<hr class="my-4">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="mb-3">Package Features:</h6>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Profile Views: ${pkg.profile_views_limit ?? 'Unlimited'}</li>
                            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Interest Express: ${pkg.interest_limit ?? 'Unlimited'}</li>
                            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Search Access: ${pkg.search_access ?? 'Full'}</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6 class="mb-3">Additional Features:</h6>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="bi ${pkg.profile_view_enabled === 'yes' ? 'bi-check-circle text-success' : 'bi-x-circle text-secondary'} me-2"></i> Profile View</li>
                            <li class="mb-2"><i class="bi ${pkg.profile_hide_enabled === 'yes' ? 'bi-check-circle text-success' : 'bi-x-circle text-secondary'} me-2"></i> Profile Hide</li>
                            <li class="mb-2"><i class="bi ${pkg.matchmaker_enabled === 'yes' ? 'bi-check-circle text-success' : 'bi-x-circle text-secondary'} me-2"></i> Matchmaker</li>
                        </ul>
                    </div>
                </div>`;
            }
            detailsHtml += `</div>`;
            
            document.getElementById('packageDetails').innerHTML = detailsHtml;
            document.getElementById('paymentSection').style.display = 'none';
            document.getElementById('chooseBtn').style.display = 'block';
            
            const modal = new bootstrap.Modal(document.getElementById('packageModal'));
            modal.show();
        }
        
        function showPaymentSection() {
            document.getElementById('paymentSection').style.display = 'block';
            document.getElementById('chooseBtn').style.display = 'none';
        }
        
        // Handle payment form submission
        document.addEventListener('DOMContentLoaded', function() {
            const paymentForm = document.getElementById('paymentForm');
            if (paymentForm) {
                paymentForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    formData.append('submit_package_request', '1');
                    
                    fetch('package.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(data => {
                        location.reload();
                    })
                    .catch(error => {
                        alert('Error submitting request. Please try again.');
                    });
                });
            }
        });
    </script>
</body>
</html>