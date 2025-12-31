<?php
session_start();
// Database connection
$host = "localhost";
$user = "thennilavu_matrimonial"; // change if needed
$pass = "OYVuiEKfS@FQ";     // change if needed
$db   = "thennilavu_thennilavu";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

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

// Fetch company bank details
$company_sql = "SELECT bank_name, account_number, account_name, branch FROM company_details LIMIT 1";
$company_result = $conn->query($company_sql);
$company_data = $company_result->fetch_assoc();

// Set default values if not found
$bank_name = $company_data['bank_name'] ?? 'Commercial Bank';
$account_number = $company_data['account_number'] ?? '1234567890';
$account_name = $company_data['account_name'] ?? 'Thennilavu Matrimony';
$branch = $company_data['branch'] ?? 'Colombo Main Branch';
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
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('img/1.jpg') center/cover no-repeat fixed;
            color: #333;
            overflow-x: hidden;    
        }
        
        /* Make comparison tables horizontally scrollable on small screens
           - Ensures touch-friendly scrolling and avoids table squishing.
           - Adjust min-width as needed if you have many package columns. */
        @media (max-width: 992px) {
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch; /* smooth momentum scrolling on iOS */
                -ms-overflow-style: auto; /* IE/Edge */
                scrollbar-width: auto; /* Firefox */
            }

            /* Prevent cells from wrapping so the table can be scrolled horizontally */
            .table-responsive table {
                min-width: 640px; /* tweak this value if you have many columns */
            }

            .table-responsive th,
            .table-responsive td {
                white-space: nowrap;
            }
        }
        
        /* Enhanced Navbar Styling */
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

        /* Button container */
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

        
        
        .hero-section {
            padding: 60px 0 30px;
            color: white;
            text-align: center;
            margin-top: 76px;
        }
        
        .section-title {
            position: relative;
            margin-bottom: 40px;
            font-weight: 700;
            color: white;
            text-align: center;
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
        
        .package-card {
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            height: 100%;
            background: #ffffff;
            border: 1px solid #f0f0f0;
            position: relative;
        }
        
        .package-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }
        
        .package-header {
            background: var(--primary-gradient);
            color: white;
            padding: 30px 25px 20px;
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
            border-top: 15px solid var(--primary-color);
        }
        
        .package-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .package-price {
            font-size: 2.8rem;
            font-weight: 700;
            margin: 15px 0 5px;
            color: white;
        }
        
        .package-duration {
            font-size: 0.95rem;
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        .package-features {
            padding: 30px 25px 25px;
            background: white;
        }
        
        .feature-item {
            padding: 12px 0;
            display: flex;
            align-items: center;
            color: #555;
            font-size: 0.95rem;
        }
        
        .feature-icon {
            color: var(--primary-color);
            margin-right: 12px;
            font-size: 1.1rem;
            width: 20px;
        }
        
        .popular-package {
            position: relative;
            transform: scale(1.05);
            z-index: 1;
            border: 2px solid #e91e63;
        }
        
        .popular-package .package-header {
            background: linear-gradient(135deg, #e91e63, #ad1457);
        }
        
        .popular-badge {
            position: absolute;
            top: -10px;
            right: 20px;
            background: var(--primary-gradient);
            color: white;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-package {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 25px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-package:hover {
            background: linear-gradient(50deg, #ed0cbd, #fd2c79);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(253, 44, 121, 0.3);
            color: white;
        }
        
        /* Gold Package Styling */
        .package-card.gold-package .package-header {
            background: var(--primary-gradient);
        }
        
        .package-card.gold-package .feature-icon {
            color: var(--primary-color);
        }
        
        .package-card.gold-package .btn-package {
            background: var(--primary-gradient);
        }
        
        .package-card.gold-package .btn-package:hover {
            background: linear-gradient(50deg, #ed0cbd, #fd2c79);
        }
        
        /* Diamond Package Styling */
        .package-card.diamond-package .package-header {
            background: var(--primary-gradient);
        }
        
        .package-card.diamond-package .feature-icon {
            color: var(--primary-color);
        }
        
        .package-card.diamond-package .btn-package {
            background: var(--primary-gradient);
        }
        
        .package-card.diamond-package .btn-package:hover {
            background: linear-gradient(50deg, #ed0cbd, #fd2c79);
        }
        
        .counter-box {
            text-align: center;
            padding: 30px 20px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .counter-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .counter-box h2 {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .counter-box p {
            font-size: 1.1rem;
            color: #555;
            margin: 0;
        }
        
        .heart {
            position: absolute;
            color: red;
            font-size: 20px;
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
        
        .accordion-button:not(.collapsed) {
            background-color: rgba(127, 8, 8, 0.1);
            color: #7f0808;
        }
        
        .accordion-button:focus {
            border-color: #7f0808;
            box-shadow: 0 0 0 0.25rem rgba(127, 8, 8, 0.15);
        }
        
        footer {
            background: #2b2b2b;
        }
        
        /* Enhanced form styling */
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border-left: 4px solid #7f0808;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .form-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .form-card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #7f0808;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .form-card-title i {
            margin-right: 10px;
            font-size: 1.4rem;
        }
        
        /* Enhanced responsive adjustments */
        @media (max-width: 768px) {
            .package-card {
                margin-bottom: 30px;
            }
            
            .popular-package {
                transform: scale(1);
            }
            
            .counter-box {
                margin-bottom: 20px;
            }
            
            .package-price {
                font-size: 2.2rem;
            }
            
            .package-name {
                font-size: 1.3rem;
            }
            
            .package-header {
                padding: 25px 20px 15px;
            }
            
            .package-features {
                padding: 25px 20px 20px;
            }
        }
        
        @media (max-width: 576px) {
            .package-card {
                margin-bottom: 25px;
            }
            
            .package-price {
                font-size: 2rem;
            }
            
            .package-name {
                font-size: 1.2rem;
            }
            
            .btn-package {
                padding: 12px 25px;
                font-size: 0.9rem;
            }
            
            .hero-section {
                padding: 60px 0 40px;
            }
            
            .section-title {
                font-size: 2rem;
            }
        }
        
        .spacer {
            height: 200px; /* default for large screens */
        }

        @media (max-width: 768px) {
            .spacer {
                height: 100px; /* tablets and small screens */
            }
        }

        @media (max-width: 480px) {
            .spacer {
                height: 60px; /* mobile phones */
            }
        }
        
        /* Enhanced Table Styling (copied from temp.php)
           Use .table-wrapper as the visual container (rounded corners, shadow)
           and keep .table-responsive as the scrollable element. */
        .table-wrapper {
            border-radius: 15px;
            overflow: hidden; /* clip background and keep rounded corners */
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            background: white;
            margin-bottom: 1rem;
        }

        .table-responsive {
            border-radius: 0;
            background: transparent;
            overflow-x: auto; /* allow horizontal scrolling when needed */
            -webkit-overflow-scrolling: touch;
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
        
        /* Enhanced content sections - identical to contact.php */
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
        
        /* Background Spacer */
        .bg-spacer {
            height: 80px;
        }
        
        /* FAQ Section Title Responsive Styling - identical to contact.php */
        @media (max-width: 768px) {
            .section-title {
                font-size: 1.8rem;
            }
            
            .content-section {
                margin: 20px 15px;
                padding: 30px 15px;
            }
            
            .bg-spacer {
                height: 50px;
            }
        }
        
        @media (max-width: 576px) {
            .section-title {
                font-size: 1.6rem;
            }
            
            .content-section {
                margin: 15px 10px;
                padding: 25px 15px;
            }
        }
        
        /* Alert styling */
        .alert {
            border-radius: 8px;
            border: none;
            padding: 15px;
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.9);
            color: white;
        }
        
        .alert-info {
            background: rgba(23, 162, 184, 0.9);
            color: white;
        }
        
        .alert-danger {
            background: rgba(220, 53, 69, 0.9);
            color: white;
        }
        
        /* Enhanced Modal Styling */
        .package-modal .modal-content {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            border: none;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        }
        
        .package-modal .modal-header {
            background: var(--primary-gradient);
            color: white;
            padding: 25px 30px;
            border-bottom: none;
            position: relative;
        }
        
        .package-modal .modal-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: rgba(255,255,255,0.3);
        }
        
        .package-modal .modal-title {
            font-size: 1.6rem;
            font-weight: 700;
            margin: 0;
        }
        
        .package-modal .btn-close {
            filter: invert(1);
            opacity: 0.8;
            transition: all 0.3s ease;
        }
        
        .package-modal .btn-close:hover {
            opacity: 1;
            transform: rotate(90deg);
        }
        
        .package-modal .modal-body {
            padding: 30px;
            background: #fdfdfd;
        }
        
        .package-modal .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #e9ecef;
            background: white;
            border-radius: 0 0 20px 20px;
        }
        
        /* Package Details Section */
        .package-details-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .package-details-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.12);
        }
        
        .package-header-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .package-title-section h4 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .package-title-section .package-description {
            color: #6c757d;
            font-size: 1rem;
            margin: 0;
        }
        
        .package-price-section {
            text-align: right;
        }
        
        .package-price-section .price {
            color: var(--primary-color);
            font-size: 2.2rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 5px;
        }
        
        .package-price-section .duration {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0;
        }
        
        .package-features-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .feature-category h6 {
            color: var(--secondary-color);
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--primary-color);
            display: inline-block;
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .feature-list li {
            padding: 8px 0;
            display: flex;
            align-items: center;
            color: #555;
            font-size: 0.95rem;
        }
        
        .feature-list li i {
            margin-right: 10px;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }
        
        .feature-list .bi-check-circle-fill {
            color: #28a745;
        }
        
        .feature-list .bi-x-circle-fill {
            color: #dc3545;
        }
        
        /* Payment Section */
        .payment-section-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border-left: 4px solid #28a745;
            transition: all 0.3s ease;
        }
        
        .payment-section-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.12);
        }
        
        .payment-section-card h5 {
            color: #28a745;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .payment-section-card h5 i {
            margin-right: 10px;
            font-size: 1.3rem;
        }
        
        .bank-info-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 20px;
            border-left: 4px solid #17a2b8;
            margin-bottom: 25px;
        }
        
        .bank-info-card strong {
            color: var(--secondary-color);
            font-size: 1.1rem;
            margin-bottom: 10px;
            display: block;
        }
        
        .bank-details {
            color: #495057;
            line-height: 1.6;
            margin: 0;
        }
        
        .bank-details span {
            font-weight: 600;
            color: var(--secondary-color);
        }
        
        /* Enhanced Form Styling */
        .payment-form .form-label {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 8px;
        }
        
        .payment-form .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
            font-size: 1rem;
            background: #fdfdfd;
        }
        
        .payment-form .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(253, 44, 121, 0.15);
            outline: none;
            background: white;
        }
        
        .payment-form .form-text {
            color: #6c757d;
            font-size: 0.85rem;
            margin-top: 5px;
        }
        
        /* Enhanced Button Styling */
        .modal-btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(253, 44, 121, 0.3);
        }
        
        .modal-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(253, 44, 121, 0.4);
            background: linear-gradient(50deg, #ed0cbd, #fd2c79);
            color: white;
        }
        
        .modal-btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .modal-btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
            background: linear-gradient(135deg, #20c997, #28a745);
            color: white;
        }
        
        .modal-btn-secondary {
            background: #6c757d;
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .modal-btn-secondary:hover {
            background: #5a6268;
            color: white;
            transform: translateY(-2px);
        }
        
        /* File Upload Styling */
        .file-upload-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        
        .file-upload-wrapper input[type=file] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-upload-label {
            display: block;
            padding: 12px 15px;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            text-align: center;
            color: #6c757d;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .file-upload-label:hover {
            border-color: var(--primary-color);
            background: rgba(253, 44, 121, 0.05);
            color: var(--primary-color);
        }
        
        .file-upload-label i {
            margin-right: 8px;
            font-size: 1.2rem;
        }
        
        /* Responsive Modal Adjustments */
        @media (max-width: 768px) {
            .package-modal .modal-dialog {
                margin: 20px;
            }
            
            .package-features-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .package-header-info {
                flex-direction: column;
                text-align: center;
            }
            
            .package-price-section {
                text-align: center;
                margin-top: 15px;
            }
            
            .package-modal .modal-body {
                padding: 20px;
            }
            
            .package-modal .modal-header,
            .package-modal .modal-footer {
                padding: 20px;
            }
        }
        
        @media (max-width: 576px) {
            .package-modal .modal-dialog {
                margin: 6px;
            }

            .package-modal .modal-content {
                border-radius: 10px;
            }

            .package-details-card,
            .payment-section-card {
                padding: 14px;
            }

            .package-header-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .package-title-section h4 {
                font-size: 1.05rem;
            }

            .package-title-section .package-description {
                font-size: 0.95rem;
                color: #6c757d;
            }

            .package-price-section {
                text-align: left;
            }

            .package-price-section .price {
                font-size: 1.6rem;
            }

            .package-price-section .duration {
                font-size: 0.85rem;
            }

            .package-features-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .feature-list li {
                font-size: 0.95rem;
                padding: 6px 0;
            }

            .feature-list li i {
                font-size: 1rem;
                margin-right: 8px;
            }

            .bank-info-card strong {
                font-size: 1rem;
            }

            .bank-details {
                font-size: 0.95rem;
            }

            .payment-form .form-control {
                font-size: 0.95rem;
                padding: 10px 12px;
            }

            .modal-btn-primary,
            .modal-btn-success,
            .modal-btn-secondary {
                width: 100%;
                padding: 10px 12px;
                font-size: 1rem;
            }

            /* Make file upload label more tappable */
            .file-upload-label {
                padding: 10px 12px;
                border-radius: 8px;
                font-size: 0.95rem;
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

        @keyframes back{
            100%{
                background-position:2000px 0;
            }
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
        }

        @media (max-width: 768px) and (min-width: 577px) {
            .navbar {
                max-height: 73px;
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
            
            .custom-btn {
                min-width: 100px;
                padding: 8px 15px;
                font-size: 0.85rem;
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
            <h1 class="display-4 fw-bold heading1">Our Membership Packages</h1>
            <p class="lead">Choose the plan that's right for your journey to finding your perfect match</p>
        </div>
    </section>

    <!-- Packages Section -->
    <section class="py-5" style="background: transparent;">
        <div class="container"> 
            
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
    <section class="py-5">
        <div class="container">
            <h2 class="text-center section-title" style="font-size: 40px;">Package Comparison</h2>
            <?php if (!empty($packages)): ?>
                <div class="table-wrapper">
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
                                            <i class="bi bi-check-circle-fill text-success"></i>
                                        <?php else: ?>
                                            <i class="bi bi-x-circle-fill text-danger"></i>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <td><strong>Profile Hide</strong></td>
                                <?php foreach ($packages as $package): ?>
                                    <td class="text-center">
                                        <?php if (strtolower($package['profile_hide_enabled']) === 'yes'): ?>
                                            <i class="bi bi-check-circle-fill text-success"></i>
                                        <?php else: ?>
                                            <i class="bi bi-x-circle-fill text-danger"></i>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <td><strong>Matchmaker</strong></td>
                                <?php foreach ($packages as $package): ?>
                                    <td class="text-center">
                                        <?php if (strtolower($package['matchmaker_enabled']) === 'yes'): ?>
                                            <i class="bi bi-check-circle-fill text-success"></i>
                                        <?php else: ?>
                                            <i class="bi bi-x-circle-fill text-danger"></i>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info text-center">
                    <p>No packages available for comparison.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center section-title">Why Choose Thennilavu?</h2>
            
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

    <!-- Background Spacer -->
    <div class="bg-spacer"></div>

    <!-- FAQ Section -->
    <section class="content-section" id="faq-section">
        <div class="container">
            <h2 class="section-title" style="color: #000000;">Frequently Asked Questions</h2>
            
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
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="packageDetails">
                        <!-- Package details will be loaded here -->
                    </div>
                    
                    <div id="paymentSection" style="display: none;">
                        <div class="payment-section-card">
                            <h5><i class="bi bi-credit-card"></i> Payment Information</h5>
                            
                            <div class="bank-info-card">
                                <strong>Bank Transfer Details</strong>
                                <p class="bank-details">
                                    <span>Bank:</span> <?php echo htmlspecialchars($bank_name); ?><br>
                                    <span>Account Number:</span> <?php echo htmlspecialchars($account_number); ?><br>
                                    <span>Account Name:</span> <?php echo htmlspecialchars($account_name); ?><br>
                                    <span>Branch:</span> <?php echo htmlspecialchars($branch); ?>
                                </p>
                            </div>
                            
                            <form id="paymentForm" enctype="multipart/form-data" class="payment-form">
                                <input type="hidden" id="modalPackageId" name="package_id">
                                <input type="hidden" id="modalPackageName" name="package_name">
                                <input type="hidden" id="modalPackageDuration" name="package_duration">
                                
                                <div class="mb-4">
                                    <label for="paymentSlip" class="form-label">Upload Payment Slip *</label>
                                    <div class="file-upload-wrapper">
                                        <input type="file" class="form-control" id="paymentSlip" name="payment_slip" accept="image/*,.pdf" required>
                                        <label for="paymentSlip" class="file-upload-label">
                                            <i class="bi bi-cloud-upload"></i>
                                            Click to upload payment receipt
                                        </label>
                                    </div>
                                    <div class="form-text">Supported formats: JPG, PNG, PDF (Max: 5MB)</div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="modal-btn-success">
                                        <i class="bi bi-send-check me-2"></i>Submit Package Request
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="modal-btn-primary" id="chooseBtn" onclick="showPaymentSection()">
                        <i class="bi bi-check-circle me-2"></i>Choose This Package
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
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
                        <li class="mb-2"><a href="index.php" class="text-white text-decoration-none">Home</a></li>
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

    <!-- Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div id="successToast" class="toast" role="alert">
            <div class="toast-header bg-success text-white">
                <i class="bi bi-check-circle-fill me-2"></i>
                <strong class="me-auto">Success!</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                Package request submitted successfully! We'll review and contact you soon.
            </div>
        </div>
    </div>

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
        
        // Enhanced Package Modal Functions
        function openPackageModal(packageId, packageName, packagePrice, packageDuration, packageDescription) {
            document.getElementById('modalPackageId').value = packageId;
            document.getElementById('modalPackageName').value = packageName;
            document.getElementById('modalPackageDuration').value = packageDuration;
            
            // Find the package object from the packages array
            const pkg = (window.packagesData || []).find(p => p.package_id == packageId);
            
            let detailsHtml = `<div class="package-details-card">
                <div class="package-header-info">
                    <div class="package-title-section">
                        <h4>${packageName}</h4>
                        <p class="package-description">${packageDescription || 'Premium matrimony package with enhanced features to help you find your perfect match.'}</p>
                    </div>
                    <div class="package-price-section">
                        <div class="price">$${packagePrice}</div>
                        <p class="duration">${packageDuration} days access</p>
                    </div>
                </div>`;
            
                if (pkg) {
                    // Flexible check: accept 'yes' (any case), '1', true, or 'true'
                    const isEnabled = (val) => {
                        if (val === null || val === undefined) return false;
                        const s = String(val).toLowerCase().trim();
                        return s === 'yes' || s === '1' || s === 'true' || s === 'on';
                    };

                    const iconFor = (val) => isEnabled(val) ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-danger"></i>';

                    detailsHtml += `<div class="package-features-grid">
                        <div class="feature-category">
                            <h6>Core Features</h6>
                            <ul class="feature-list">
                                <li>${iconFor(pkg.profile_views_limit ? 'yes' : 'no')} Profile Views: ${pkg.profile_views_limit ?? 'Unlimited'}</li>
                                <li>${iconFor(pkg.interest_limit ? 'yes' : 'no')} Interest Express: ${pkg.interest_limit ?? 'Unlimited'}</li>
                                <li>${iconFor(pkg.search_access ? 'yes' : 'no')} Search Access: ${pkg.search_access ?? 'Full Access'}</li>
                            </ul>
                        </div>
                        <div class="feature-category">
                            <h6>Additional Features</h6>
                            <ul class="feature-list">
                                <li>${iconFor(pkg.profile_view_enabled)} Profile View Access</li>
                                <li>${iconFor(pkg.profile_hide_enabled)} Profile Hide Option</li>
                                <li>${iconFor(pkg.matchmaker_enabled)} Matchmaker Service</li>
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
            
            // Scroll to payment section
            document.getElementById('paymentSection').scrollIntoView({ 
                behavior: 'smooth',
                block: 'start'
            });
        }
        
        // Enhanced file upload display
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('paymentSlip');
            const fileLabel = document.querySelector('.file-upload-label');
            
            if (fileInput && fileLabel) {
                fileInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const fileName = this.files[0].name;
                        fileLabel.innerHTML = `<i class="bi bi-file-earmark-check"></i> ${fileName}`;
                        fileLabel.style.borderColor = '#28a745';
                        fileLabel.style.background = 'rgba(40, 167, 69, 0.1)';
                        fileLabel.style.color = '#28a745';
                    }
                });
            }
            
            // Handle payment form submission
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
                        if (data.includes('success') || data.includes('submitted')) {
                            const toast = new bootstrap.Toast(document.getElementById('successToast'));
                            toast.show();
                            bootstrap.Modal.getInstance(document.getElementById('packageModal')).hide();
                            paymentForm.reset();
                        } else {
                            location.reload();
                        }
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