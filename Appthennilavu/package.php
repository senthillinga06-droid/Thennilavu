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
        
        $upload_dir = '/home10/thennilavu/public_html/uploads/payment_slips/';
        if (!file_exists($upload_dir)) {  
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['payment_slip']['name'], PATHINFO_EXTENSION);
        $file_name = 'slip_' . $user_id . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['payment_slip']['tmp_name'], $upload_path)) {
            $slip_url = '/uploads/payment_slips/' . $file_name;
        }
    }
    
    // Insert into userpackage table
    $stmt = $conn->prepare("INSERT INTO userpackage (user_id, status, duration, requestPackage, slip) VALUES (?, ?, ?, 'yes', ?)");
    $stmt->bind_param("isis", $user_id, $package_name, $package_duration, $slip_url);
    
    if ($stmt->execute()) {
        $request_message = '<div class="toast show success" id="successToast">Package request submitted successfully! Please wait for admin approval.</div>';
    } else {
        $request_message = '<div class="toast show error" id="errorToast">Error submitting request. Please try again.</div>';
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

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Packages - TheanNilavu Matrimony</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Mobile Design Pattern - Matching story.php mobile version */
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
            background: rgba(var(--bg-primary-rgb), 0.9);
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

        /* Packages Grid */
        .packages-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        @media (min-width: 768px) {
            .packages-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1024px) {
            .packages-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        /* Package Card */
        .package-card {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid var(--border-color);
            position: relative;
        }

        .package-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--elevated-shadow);
        }

        .package-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 25px 20px 15px;
            text-align: center;
            position: relative;
        }

        .package-name {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .package-price {
            font-size: 2.5rem;
            font-weight: 800;
            margin: 10px 0 5px;
            color: white;
        }

        .package-duration {
            font-size: 0.85rem;
            opacity: 0.9;
            margin-bottom: 0;
        }

        .popular-badge {
            position: absolute;
            top: -8px;
            right: 20px;
            background: linear-gradient(135deg, #ff4081, #e91e63);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            z-index: 2;
        }

        /* Package Features */
        .package-features {
            padding: 25px 20px;
            background: var(--bg-primary);
        }

        .feature-item {
            padding: 10px 0;
            display: flex;
            align-items: center;
            color: var(--text-secondary);
            font-size: 0.9rem;
            border-bottom: 1px solid var(--border-color);
        }

        .feature-item:last-child {
            border-bottom: none;
        }

        .feature-icon {
            color: var(--primary-color);
            margin-right: 12px;
            font-size: 1rem;
            width: 20px;
        }

        .buy-now-btn {
            display: block;
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
            margin-top: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .buy-now-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(233, 30, 99, 0.3);
        }

        /* Comparison Table */
        .comparison-section {
            margin: 32px 0;
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

        .table-container {
            overflow-x: auto;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            background: var(--bg-primary);
        }

        .comparison-table {
            width: 100%;
            min-width: 600px;
            border-collapse: collapse;
        }

        .comparison-table th {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 16px 12px;
            font-weight: 600;
            text-align: center;
            border-bottom: 2px solid var(--border-color);
        }

        .comparison-table td {
            padding: 14px 12px;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
            background: var(--bg-primary);
        }

        .comparison-table tr:last-child td {
            border-bottom: none;
        }

        .comparison-table .feature-label {
            text-align: left;
            font-weight: 600;
            color: var(--text-primary);
            background: var(--bg-secondary);
        }

        /* Stats Section */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin: 32px 0;
        }

        @media (min-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        .stat-card {
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            padding: 20px;
            text-align: center;
            transition: var(--transition);
            border: 1px solid var(--border-color);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-shadow);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin: 0;
        }

        /* FAQ Section */
        .faq-section {
            margin: 32px 0;
        }

        .faq-item {
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            margin-bottom: 12px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .faq-question {
            padding: 16px 20px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition);
        }

        .faq-question:hover {
            background: var(--bg-tertiary);
        }

        .faq-answer {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: var(--bg-primary);
        }

        .faq-answer.active {
            padding: 16px 20px;
            max-height: 500px;
        }

        .faq-toggle {
            color: var(--primary-color);
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }

        .faq-toggle.active {
            transform: rotate(45deg);
        }

        /* Package Modal */
        .package-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 2000;
            display: none;
        }

        .package-modal.active {
            display: block;
        }

        .modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--elevated-shadow);
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .modal-title {
            font-size: 1.2rem;
            font-weight: 700;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 4px;
        }

        .modal-body {
            padding: 20px;
            overflow-y: auto;
            max-height: 60vh;
        }

        .package-details {
            margin-bottom: 25px;
        }

        .package-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .package-info-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .package-info-price {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary-color);
        }

        .feature-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin-bottom: 25px;
        }

        @media (min-width: 480px) {
            .feature-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .feature-category h6 {
            color: var(--secondary-color);
            font-weight: 600;
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 2px solid var(--primary-color);
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
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .feature-list li i {
            margin-right: 8px;
            font-size: 1rem;
            width: 20px;
            text-align: center;
        }

        .feature-list .fa-check-circle {
            color: var(--success-color);
        }

        .feature-list .fa-times-circle {
            color: var(--danger-color);
        }

        /* Payment Section */
        .payment-section {
            display: none;
        }

        .payment-section.active {
            display: block;
        }

        .bank-info {
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid var(--success-color);
        }

        .bank-info h6 {
            color: var(--secondary-color);
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .bank-info h6 i {
            color: var(--success-color);
        }

        .bank-details {
            color: var(--text-secondary);
            line-height: 1.6;
            font-size: 0.9rem;
        }

        .bank-details span {
            font-weight: 600;
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

        .file-upload-wrapper {
            position: relative;
            overflow: hidden;
            display: block;
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
            padding: 14px 16px;
            background: var(--bg-secondary);
            border: 2px dashed var(--border-color);
            border-radius: var(--radius-md);
            text-align: center;
            color: var(--text-muted);
            transition: var(--transition);
            cursor: pointer;
            font-size: 0.9rem;
        }

        .file-upload-label:hover {
            border-color: var(--primary-color);
            background: rgba(var(--primary-color-rgb), 0.05);
            color: var(--primary-color);
        }

        .file-upload-label i {
            margin-right: 8px;
            font-size: 1.1rem;
        }

        .form-text {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 5px;
        }

        /* Buttons */
        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .btn {
            flex: 1;
            padding: 14px 20px;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            text-align: center;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(233, 30, 99, 0.3);
        }

        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-secondary);
        }

        .btn-secondary:hover {
            background: var(--border-color);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color), #20c997);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.3);
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

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .empty-icon {
            font-size: 3rem;
            color: var(--text-muted);
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.3rem;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .empty-state p {
            font-size: 0.95rem;
            max-width: 300px;
            margin: 0 auto;
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
            .packages-grid {
                grid-template-columns: 1fr;
            }
            
            .hero-title {
                font-size: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .bottom-nav .nav-item {
                font-size: 0.65rem;
                padding: 6px 4px;
                min-width: 50px;
            }
            
            .bottom-nav .nav-item i {
                font-size: 1rem;
            }
        }

        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Heart animation */
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
            0% { transform: translate(0, 0) scale(1); opacity: 1; }
            100% { transform: translate(-20px, -200px) scale(1.5); opacity: 0; }
        }
    </style>
</head>
<body>
    <!-- Theme Variables Script -->
    <script>
        document.documentElement.style.setProperty('--bg-primary-rgb', '255, 255, 255');
        document.documentElement.style.setProperty('--primary-color-rgb', '233, 30, 99');
        
        if (document.documentElement.getAttribute('data-theme') === 'dark') {
            document.documentElement.style.setProperty('--bg-primary-rgb', '18, 18, 18');
        }
    </script>

    <!-- App Container -->
    <div class="app-container">
        <!-- App Header -->
        <header class="app-header">
            <div class="header-left">
                <button class="back-button" onclick="goBack()">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <h1 class="header-title">Packages</h1>
            </div>
            <button class="theme-toggle" id="themeToggle">
                <i class="fas fa-moon"></i>
            </button>
        </header>

        <!-- Hero Section -->
        <section class="hero-section">
            <div class="hero-icon">
                <i class="fas fa-crown"></i>
            </div>
            <h1 class="hero-title">Choose Your Plan</h1>
            <p class="hero-subtitle">Find the perfect package for your journey to love</p>
        </section>

        <!-- Main Content -->
        <main class="content-container">
            <?php if (!empty($request_message)): ?>
                <?php echo $request_message; ?>
            <?php endif; ?>

            <?php if (empty($packages)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <h3>No Packages Available</h3>
                    <p>Please check back later for our membership packages.</p>
                </div>
            <?php else: ?>
                <!-- Packages Grid -->
                <div class="packages-grid">
                    <?php foreach ($packages as $index => $package): ?>
                        <?php 
                            $isPopular = $index == 1;
                            $features = !empty($package['features']) ? explode(',', $package['features']) : [];
                        ?>
                        <div class="package-card fade-in">
                            <?php if ($isPopular): ?>
                                <div class="popular-badge">MOST POPULAR</div>
                            <?php endif; ?>
                            
                            <div class="package-header">
                                <h3 class="package-name"><?php echo htmlspecialchars($package['name']); ?></h3>
                                <div class="package-price">$<?php echo number_format($package['price'], 2); ?></div>
                                <div class="package-duration"><?php echo htmlspecialchars($package['duration_days']); ?> days</div>
                            </div>
                            
                            <div class="package-features">
                                <div class="feature-item">
                                    <i class="fas fa-eye feature-icon"></i>
                                    <span>Views: <?php echo htmlspecialchars($package['profile_views_limit']); ?></span>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-heart feature-icon"></i>
                                    <span>Interest: <?php echo htmlspecialchars($package['interest_limit']); ?></span>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-search feature-icon"></i>
                                    <span>Search: <?php echo htmlspecialchars($package['search_access']); ?></span>
                                </div>
                                
                                <button class="buy-now-btn" 
                                    onclick="openPackageModal(<?php echo $package['package_id']; ?>, '<?php echo htmlspecialchars($package['name']); ?>', <?php echo $package['price']; ?>, <?php echo $package['duration_days']; ?>)">
                                    Choose Package
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Comparison Section -->
                <div class="comparison-section fade-in">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h2 class="section-title">Package Comparison</h2>
                    </div>
                    
                    <div class="table-container">
                        <table class="comparison-table">
                            <thead>
                                <tr>
                                    <th>Feature</th>
                                    <?php foreach ($packages as $package): ?>
                                        <th><?php echo htmlspecialchars($package['name']); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="feature-label">Cost</td>
                                    <?php foreach ($packages as $package): ?>
                                        <td>$<?php echo htmlspecialchars(number_format($package['price'], 2)); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <td class="feature-label">Duration</td>
                                    <?php foreach ($packages as $package): ?>
                                        <td><?php echo htmlspecialchars($package['duration_days']); ?> days</td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <td class="feature-label">Profile Views</td>
                                    <?php foreach ($packages as $package): ?>
                                        <td><?php echo htmlspecialchars($package['profile_views_limit']); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <td class="feature-label">Interest Limit</td>
                                    <?php foreach ($packages as $package): ?>
                                        <td><?php echo htmlspecialchars($package['interest_limit']); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <td class="feature-label">Search Access</td>
                                    <?php foreach ($packages as $package): ?>
                                        <td><?php echo htmlspecialchars($package['search_access']); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <td class="feature-label">Profile View</td>
                                    <?php foreach ($packages as $package): ?>
                                        <td>
                                            <?php if (strtolower($package['profile_view_enabled']) === 'yes'): ?>
                                                <i class="fas fa-check-circle text-success"></i>
                                            <?php else: ?>
                                                <i class="fas fa-times-circle text-danger"></i>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <td class="feature-label">Profile Hide</td>
                                    <?php foreach ($packages as $package): ?>
                                        <td>
                                            <?php if (strtolower($package['profile_hide_enabled']) === 'yes'): ?>
                                                <i class="fas fa-check-circle text-success"></i>
                                            <?php else: ?>
                                                <i class="fas fa-times-circle text-danger"></i>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <td class="feature-label">Matchmaker</td>
                                    <?php foreach ($packages as $package): ?>
                                        <td>
                                            <?php if (strtolower($package['matchmaker_enabled']) === 'yes'): ?>
                                                <i class="fas fa-check-circle text-success"></i>
                                            <?php else: ?>
                                                <i class="fas fa-times-circle text-danger"></i>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Stats Section -->
                <div class="stats-grid fade-in">
                    <div class="stat-card">
                        <div class="stat-value" data-count="25000">0</div>
                        <p class="stat-label">Total Members</p>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" data-count="8700">0</div>
                        <p class="stat-label">Success Stories</p>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" data-count="1200">0</div>
                        <p class="stat-label">Premium Members</p>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" data-count="10">0</div>
                        <p class="stat-label">Years of Service</p>
                    </div>
                </div>

                <!-- FAQ Section -->
                <div class="faq-section fade-in">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-question-circle"></i>
                        </div>
                        <h2 class="section-title">Frequently Asked Questions</h2>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(1)">
                            <span>How do I create a profile?</span>
                            <i class="fas fa-plus faq-toggle"></i>
                        </div>
                        <div class="faq-answer" id="faq-answer-1">
                            You can create a profile by filling in the registration form with your personal, educational, and family details. After submitting, your profile will be reviewed and published.
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(2)">
                            <span>Is my data safe and private?</span>
                            <i class="fas fa-plus faq-toggle"></i>
                        </div>
                        <div class="faq-answer" id="faq-answer-2">
                            Yes, we take your privacy seriously. Your personal information is secured and only accessible by verified users.
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(3)">
                            <span>How do I upgrade to a premium plan?</span>
                            <i class="fas fa-plus faq-toggle"></i>
                        </div>
                        <div class="faq-answer" id="faq-answer-3">
                            You can upgrade any time by selecting a package and completing the payment process. Choose your preferred package and follow the payment instructions.
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(4)">
                            <span>Can I contact other members directly?</span>
                            <i class="fas fa-plus faq-toggle"></i>
                        </div>
                        <div class="faq-answer" id="faq-answer-4">
                            Contacting other members depends on your membership level. Premium members have full access to messaging features.
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>

        <!-- Package Modal -->
        <div class="package-modal" id="packageModal">
            <div class="modal-overlay" onclick="closePackageModal()"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Package Details</h3>
                    <button class="modal-close" onclick="closePackageModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="packageDetails">
                        <!-- Package details loaded here -->
                    </div>
                    
                    <div class="payment-section" id="paymentSection">
                        <div class="bank-info">
                            <h6><i class="fas fa-university"></i> Bank Transfer Details</h6>
                            <div class="bank-details">
                                <span>Bank:</span> <?php echo htmlspecialchars($bank_name); ?><br>
                                <span>Account Number:</span> <?php echo htmlspecialchars($account_number); ?><br>
                                <span>Account Name:</span> <?php echo htmlspecialchars($account_name); ?><br>
                                <span>Branch:</span> <?php echo htmlspecialchars($branch); ?>
                            </div>
                        </div>
                        
                        <form id="paymentForm" enctype="multipart/form-data">
                            <input type="hidden" id="modalPackageId" name="package_id">
                            <input type="hidden" id="modalPackageName" name="package_name">
                            <input type="hidden" id="modalPackageDuration" name="package_duration">
                            
                            <div class="form-group">
                                <label class="form-label">Upload Payment Slip *</label>
                                <div class="file-upload-wrapper">
                                    <input type="file" id="paymentSlip" name="payment_slip" accept="image/*,.pdf" required>
                                    <label for="paymentSlip" class="file-upload-label">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        Click to upload payment receipt
                                    </label>
                                </div>
                                <div class="form-text">Supported: JPG, PNG, PDF (Max: 5MB)</div>
                            </div>
                            
                            <div class="modal-actions">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Request
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="modal-actions" id="chooseActions">
                        <button class="btn btn-secondary" onclick="closePackageModal()">Close</button>
                        <button class="btn btn-primary" onclick="showPaymentSection()">
                            <i class="fas fa-check-circle me-2"></i>Choose Package
                        </button>
                    </div>
                </div>
            </div>
        </div>

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
            <a href="contact.php" class="nav-item">
                <i class="fas fa-envelope"></i>
                <span>Contact</span>
            </a>
            <a href="story.php" class="nav-item">
                <i class="fas fa-heart"></i>
                <span>Stories</span>
            </a>
            <a href="profile.php" class="nav-item">
                <i class="fas fa-user-circle"></i>
                <span>Profile</span>
            </a>
        </nav>

        <!-- Toast Notification -->
        <div class="toast" id="toast"></div>
    </div>

    <script>
        // Theme Management
        const themeToggle = document.getElementById('themeToggle');
        const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');
        
        const currentTheme = localStorage.getItem('theme') || 
                           (prefersDarkScheme.matches ? 'dark' : 'light');
        
        document.documentElement.setAttribute('data-theme', currentTheme);
        updateThemeIcon(currentTheme);
        updateThemeColors(currentTheme);
        
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
        
        // Back Button Function
        function goBack() {
            window.history.back();
        }
        
        // Package Modal Functions
        const packageModal = document.getElementById('packageModal');
        let currentPackageData = null;
        
        function openPackageModal(packageId, packageName, packagePrice, packageDuration) {
            // Find the package object from the packages array
            const pkg = <?php echo json_encode($packages); ?>.find(p => p.package_id == packageId);
            
            if (!pkg) return;
            
            currentPackageData = pkg;
            
            // Populate hidden fields
            document.getElementById('modalPackageId').value = packageId;
            document.getElementById('modalPackageName').value = packageName;
            document.getElementById('modalPackageDuration').value = packageDuration;
            
            // Build package details HTML
            let detailsHtml = `<div class="package-details">
                <div class="package-info">
                    <div class="package-info-name">${packageName}</div>
                    <div class="package-info-price">$${packagePrice}</div>
                </div>
                <div class="feature-grid">`;
            
            // Core Features
            detailsHtml += `<div class="feature-category">
                <h6>Core Features</h6>
                <ul class="feature-list">
                    <li><i class="fas fa-check-circle"></i> Profile Views: ${pkg.profile_views_limit || 'Unlimited'}</li>
                    <li><i class="fas fa-check-circle"></i> Interest Express: ${pkg.interest_limit || 'Unlimited'}</li>
                    <li><i class="fas fa-check-circle"></i> Search Access: ${pkg.search_access || 'Full Access'}</li>
                </ul>
            </div>`;
            
            // Additional Features
            const isEnabled = (val) => String(val).toLowerCase().trim() === 'yes';
            
            detailsHtml += `<div class="feature-category">
                <h6>Additional Features</h6>
                <ul class="feature-list">
                    <li>${isEnabled(pkg.profile_view_enabled) ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-times-circle"></i>'} Profile View Access</li>
                    <li>${isEnabled(pkg.profile_hide_enabled) ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-times-circle"></i>'} Profile Hide Option</li>
                    <li>${isEnabled(pkg.matchmaker_enabled) ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-times-circle"></i>'} Matchmaker Service</li>
                </ul>
            </div>`;
            
            detailsHtml += `</div></div>`;
            
            document.getElementById('packageDetails').innerHTML = detailsHtml;
            document.getElementById('paymentSection').classList.remove('active');
            document.getElementById('chooseActions').style.display = 'flex';
            
            packageModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function showPaymentSection() {
            document.getElementById('paymentSection').classList.add('active');
            document.getElementById('chooseActions').style.display = 'none';
            
            // Scroll to payment section
            document.getElementById('paymentSection').scrollIntoView({ 
                behavior: 'smooth',
                block: 'start'
            });
        }
        
        function closePackageModal() {
            packageModal.classList.remove('active');
            document.body.style.overflow = '';
            document.getElementById('paymentForm').reset();
        }
        
        // Close modal on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && packageModal.classList.contains('active')) {
                closePackageModal();
            }
        });
        
        // FAQ Toggle
        function toggleFAQ(id) {
            const answer = document.getElementById(`faq-answer-${id}`);
            const toggle = document.querySelector(`#faq-answer-${id}`).previousElementSibling.querySelector('.faq-toggle');
            
            if (answer.classList.contains('active')) {
                answer.classList.remove('active');
                toggle.classList.remove('active');
            } else {
                // Close all other FAQs
                document.querySelectorAll('.faq-answer.active').forEach(item => {
                    item.classList.remove('active');
                    item.previousElementSibling.querySelector('.faq-toggle').classList.remove('active');
                });
                
                answer.classList.add('active');
                toggle.classList.add('active');
            }
        }
        
        // Counter Animation
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
            const counters = document.querySelectorAll('.stat-value');
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
        
        // Heart animation
        document.addEventListener("mousemove", function(e) {
            if (Math.random() > 0.97) {
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
        
        // Form Submission
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.innerHTML = '<span class="loading-spinner"></span> Processing...';
            submitBtn.disabled = true;
            
            const formData = new FormData(this);
            formData.append('submit_package_request', '1');
            
            fetch('package.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                if (data.includes('success') || data.includes('submitted')) {
                    showToast('Package request submitted successfully! We\'ll review and contact you soon.', 'success');
                    setTimeout(() => {
                        closePackageModal();
                        location.reload();
                    }, 2000);
                } else {
                    showToast('Error submitting request. Please try again.', 'error');
                }
            })
            .catch(error => {
                showToast('Network error. Please check your connection and try again.', 'error');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
        
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
        
        // File upload display
        document.getElementById('paymentSlip').addEventListener('change', function() {
            const label = document.querySelector('.file-upload-label');
            if (this.files && this.files[0]) {
                const fileName = this.files[0].name;
                label.innerHTML = `<i class="fas fa-file-check"></i> ${fileName}`;
                label.style.borderColor = '#4caf50';
                label.style.background = 'rgba(76, 175, 80, 0.1)';
                label.style.color = '#4caf50';
            }
        });
        
        // Initialize animations
        document.addEventListener('DOMContentLoaded', () => {
            const fadeElements = document.querySelectorAll('.fade-in');
            
            fadeElements.forEach(el => {
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
            });
            
            initCountersOnScroll();
            
            // Auto-hide toast messages
            const toasts = document.querySelectorAll('.toast.show');
            toasts.forEach(toast => {
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 5000);
            });
        });
    </script>
</body>
</html>