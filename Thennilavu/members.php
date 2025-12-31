<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$conn = new mysqli('localhost', 'thennilavu_matrimonial', 'OYVuiEKfS@FQ', 'thennilavu_thennilavu');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);  
}

// Helper: check login
function require_login() {
    if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
        // Detect AJAX (fetch/XHR) request
        $isAjax = false;
        if (
            (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
            (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
        ) {
            $isAjax = true;
        }
        if ($isAjax) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['error' => 'Not logged in']);
            exit;
        } else {
            header('Location: login.php');
            exit;
        }
    }
}

// Check if user already has member details
$existing_member = false;
if (isset($_SESSION['user_id'])) {
    $check_stmt = $conn->prepare("SELECT id FROM members WHERE user_id = ?");
    $check_stmt->bind_param("i", $_SESSION['user_id']);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    if ($result->num_rows > 0) {
        $existing_member = true;
    }
    $check_stmt->close();
}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Log what was posted
    error_log("POST data received: " . print_r($_POST, true));
    error_log("FILES data received: " . print_r($_FILES, true));
    
    // 1) Member Details form
    if (isset($_POST['name'])) {
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $name = $_POST['name'] ?? '';
        $looking_for = $_POST['looking_for'] ?? '';
        $dob = $_POST['dob'] ?? '';
        $religion = $_POST['religion'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $marital_status = $_POST['marital_status'] ?? '';
        $language = $_POST['language'] ?? '';
        $profession = $_POST['profession'] ?? '';
        $country = $_POST['country'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $smoking = $_POST['smoking'] ?? '';
        $drinking = $_POST['drinking'] ?? '';
        $income = $_POST['income'] ?? ''; // New income field

        $photo = '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
            $photo = 'uploads/' . time() . '_' . $_FILES['photo']['name'];
            move_uploaded_file($_FILES['photo']['tmp_name'], $photo);
        }

        $stmt = $conn->prepare("INSERT INTO members 
            (user_id, name, photo, looking_for, dob, religion, gender, marital_status, language, profession, country, phone, smoking, drinking, income) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            die('Prepare failed: ' . $conn->error);
        }
        $stmt->bind_param(
            "issssssssssssss",
            $user_id,
            $name,
            $photo,
            $looking_for,
            $dob,
            $religion,
            $gender,
            $marital_status,
            $language,
            $profession,
            $country,
            $phone,
            $smoking,
            $drinking,
            $income
        );
        if ($stmt->execute()) {
            $_SESSION['member_id'] = $stmt->insert_id;
            $member_id = $_SESSION['member_id'];
            
            // Handle additional photos
            if (isset($_FILES['additional_photos']) && is_array($_FILES['additional_photos']['name'])) {
                $upload_dir = 'uploads/additional_photos/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $photo_count = 0;
                for ($i = 0; $i < count($_FILES['additional_photos']['name']) && $photo_count < 5; $i++) {
                    if ($_FILES['additional_photos']['error'][$i] === 0 && !empty($_FILES['additional_photos']['name'][$i])) {
                        $photo_name = time() . '_' . $i . '_' . $_FILES['additional_photos']['name'][$i];
                        $photo_path = $upload_dir . $photo_name;
                        
                        if (move_uploaded_file($_FILES['additional_photos']['tmp_name'][$i], $photo_path)) {
                            // Insert into additional_photos table
                            $photo_stmt = $conn->prepare("INSERT INTO additional_photos (member_id, photo_path, upload_order) VALUES (?, ?, ?)");
                            if ($photo_stmt) {
                                $upload_order = $i + 1;
                                $photo_stmt->bind_param("isi", $member_id, $photo_path, $upload_order);
                                $photo_stmt->execute();
                                $photo_stmt->close();
                                $photo_count++;
                            }
                        }
                    }
                }
            }
            
            $success_message = "Member saved. Continue next steps.";
        } else {
            $error_message = "Error saving member details: " . $stmt->error;
        }
    }

    // 2) Physical Info form
    if (isset($_POST['complexion']) && isset($_SESSION['member_id'])) {
        $member_id = (int)$_SESSION['member_id'];
        $complexion = $_POST['complexion'] ?? null;
        $height = $_POST['height'] ?? null;
        $weight = $_POST['weight'] ?? null;
        $blood_group = $_POST['blood_group'] ?? null;
        $eye_color = $_POST['eye_color'] ?? null;
        $hair_color = $_POST['hair_color'] ?? null;
        $disability = $_POST['disability'] ?? null;

        $stmt2 = $conn->prepare("INSERT INTO physical_info (member_id, complexion, height_cm, weight_kg, blood_group, eye_color, hair_color, disability) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt2) {
            die('Prepare failed: ' . $conn->error);
        }
        $stmt2->bind_param("isddssss", $member_id, $complexion, $height, $weight, $blood_group, $eye_color, $hair_color, $disability);
        if ($stmt2->execute()) {
            $success_message = "Physical info saved.";
        } else {
            $error_message = "Error saving physical info: " . $stmt2->error;
        }
    }

    // 3) Education form
    // 3) Education form
    if (!empty($_POST['institute']) && is_array($_POST['institute']) && isset($_SESSION['member_id'])) {
            $member_id = (int)$_SESSION['member_id'];
            for ($i = 0; $i < count($_POST['institute']); $i++) {
                $institute = $_POST['institute'][$i] ?? '';
                $degree = $_POST['degree'][$i] ?? '';
                $field = $_POST['field'][$i] ?? '';
                $regnum = $_POST['regnum'][$i] ?? '';
                $startyear = (int)($_POST['startyear'][$i] ?? 0);
                $endyear = (int)($_POST['endyear'][$i] ?? 0);

                $stmt3 = $conn->prepare("INSERT INTO education (member_id, level, school_or_institute, stream_or_degree, field, reg_number, start_year, end_year) VALUES (?, 'Higher', ?, ?, ?, ?, ?, ?)");
                if (!$stmt3) {
                    die('Prepare failed: ' . $conn->error);
                }
                $stmt3->bind_param("issssii", $member_id, $institute, $degree, $field, $regnum, $startyear, $endyear);
                if (!$stmt3->execute()) {
                    $error_message = "Error saving education info: " . $stmt3->error;
                    break;
                }
            }
            if (!isset($error_message)) {
                $success_message = "Education saved.";
            }
        }

    // 4) Family form
    if (isset($_POST['father_name']) && isset($_SESSION['member_id'])) {
        $member_id = (int)$_SESSION['member_id'];
        $father_name = $_POST['father_name'] ?? '';
        $father_profession = $_POST['father_profession'] ?? '';
        $father_contact = $_POST['father_contact'] ?? '';
        $mother_name = $_POST['mother_name'] ?? '';
        $mother_profession = $_POST['mother_profession'] ?? '';
        $mother_contact = $_POST['mother_contact'] ?? '';
        $brothers = (int)($_POST['brothers'] ?? 0);
        $sisters = (int)($_POST['sisters'] ?? 0);

        $stmt4 = $conn->prepare("INSERT INTO family (member_id, father_name, father_profession, father_contact, mother_name, mother_profession, mother_contact, brothers_count, sisters_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt4) {
            die('Prepare failed: ' . $conn->error);
        }
        $stmt4->bind_param("issssssii", $member_id, $father_name, $father_profession, $father_contact, $mother_name, $mother_profession, $mother_contact, $brothers, $sisters);
        if ($stmt4->execute()) {
            $success_message = "Family info saved.";
        } else {
            $error_message = "Error saving family info: " . $stmt4->error;
        }
    }

    // 5) Partner Expectations form
    if (isset($_POST['partner_country']) && isset($_SESSION['member_id'])) {
        $member_id = (int)$_SESSION['member_id'];
        $partner_country = $_POST['partner_country'] ?? '';
        $min_age = (int)($_POST['min_age'] ?? 0);
        $max_age = (int)($_POST['max_age'] ?? 0);
        $min_height = (int)($_POST['min_height'] ?? 0);
        $max_height = (int)($_POST['max_height'] ?? 0);
        $partner_marital_status = $_POST['partner_marital_status'] ?? '';
        $partner_religion = $_POST['partner_religion'] ?? '';
        $partner_smoking = $_POST['partner_smoking'] ?? '';
        $partner_drinking = $_POST['partner_drinking'] ?? '';

        $stmt5 = $conn->prepare("INSERT INTO partner_expectations (member_id, preferred_country, min_age, max_age, min_height, max_height, marital_status, religion, smoking, drinking) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt5) {
            die('Prepare failed: ' . $conn->error);
        }
        $stmt5->bind_param("isiiiissss", $member_id, $partner_country, $min_age, $max_age, $min_height, $max_height, $partner_marital_status, $partner_religion, $partner_smoking, $partner_drinking);
        if ($stmt5->execute()) {
            $success_message = "Partner expectations saved.";
        } else {
            $error_message = "Error saving partner expectations: " . $stmt5->error;
        }
    }

    // 6) Horoscope form
    if (isset($_POST['birth_date']) && isset($_SESSION['member_id'])) {
        $member_id = (int)$_SESSION['member_id'];
        $birth_date = $_POST['birth_date'] ?? '';
        $birth_time = $_POST['birth_time'] ?? '';
        $zodiac = $_POST['zodiac'] ?? '';
        $nakshatra = $_POST['nakshatra'] ?? '';
        $karmic_debt = $_POST['karmic_debt'] ?? '';

        $planet_img = '';
        if (isset($_FILES['planet_image']) && $_FILES['planet_image']['error'] === 0) {
            $planet_img = 'uploads/' . time() . '_' . $_FILES['planet_image']['name'];
            move_uploaded_file($_FILES['planet_image']['tmp_name'], $planet_img);
        }

        $navamsha_img = '';
        if (isset($_FILES['navamsha_image']) && $_FILES['navamsha_image']['error'] === 0) {
            $navamsha_img = 'uploads/' . time() . '_' . $_FILES['navamsha_image']['name'];
            move_uploaded_file($_FILES['navamsha_image']['tmp_name'], $navamsha_img);
        }

        $stmt6 = $conn->prepare("INSERT INTO horoscope (member_id, birth_date, birth_time, zodiac, nakshatra, karmic_debt, planet_image, navamsha_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt6) {
            die('Prepare failed: ' . $conn->error);
        }
        $stmt6->bind_param("isssssss", $member_id, $birth_date, $birth_time, $zodiac, $nakshatra, $karmic_debt, $planet_img, $navamsha_img);
        if ($stmt6->execute()) {
            $success_message = "Horoscope saved. Registration complete.";
        } else {
            $error_message = "Error saving horoscope info: " . $stmt6->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership - Thennilavu Matrimony</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/css/intlTelInput.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/intlTelInput.min.js"></script>

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

        .hero-section {
            padding: 60px 0 30px;
            color: white;
            text-align: center;
            margin-top: 120px;
        }

        .section-title {
            position: relative;
            margin-bottom: 40px;
            font-weight: 700;
        }
        
        .section-title:after {
            content: '';
            display: block;
            width: 60px;
            height: 4px;
            background: var(--gradient-bg);
            margin: 15px auto;
            border-radius: 2px;
        }
        
        .form-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: none;
            backdrop-filter: blur(10px);
        }
        
        .form-section.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            padding: 12px 15px;
            border: 1px solid #e1e1e1;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(253, 44, 121, 0.15);
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(50deg, #ed0cbd, #fd2c79);
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-3px);
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
        
        .carousel-container {
            perspective: 1000px;
            height: 300px;
            margin: 40px 0;
        }
        
        .carousel {
            width: 200px;
            height: 250px;
            position: relative;
            transform-style: preserve-3d;
            animation: gallery 50s linear infinite;
            margin: 0 auto;
        }
        
        .carousel span {
            position: absolute;
            width: 200px;
            height: 250px;
            transform-style: preserve-3d;
            transform: rotateY(calc(var(--i)*45deg)) translateZ(350px);
            -webkit-box-reflect: below 3.5px linear-gradient(transparent, transparent, rgba(3, 3, 3, 0.2));
        }
        
        .carousel span img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border: 6px ridge;
            border-radius: 10px;
        }
        
        @keyframes gallery {
            0% {
                transform: perspective(1000px) rotateY(0deg);
            }
            100% {
                transform: perspective(1000px) rotateY(-360deg);
            }
        }
        
        footer {
            background: #2b2b2b;
        }
        
        /* Success message styling */
        .alert-success {
            background: rgba(40, 167, 69, 0.9);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        /* Enhanced Flow Design */
        .flow-container {
            position: relative;
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .flow-progress {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
            counter-reset: step;
            padding: 0 10px;
        }
        
        .flow-progress::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            width: 100%;
            height: 4px;
            background: #e0e0e0;
            z-index: 1;
        }
        
        .flow-progress-bar {
            position: absolute;
            top: 15px;
            left: 0;
            height: 4px;
            background: var(--primary-gradient);
            z-index: 2;
            transition: width 0.5s ease;
            border-radius: 2px;
        }
        
        .flow-step {
            position: relative;
            z-index: 3;
            text-align: center;
            width: 100%;
            padding: 0 5px;
            cursor: default;
        }
        
        .flow-step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: 600;
            color: #777;
            transition: all 0.3s ease;
            position: relative;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border: 2px solid transparent;
        }
        
        .flow-step.active .flow-step-circle {
            background: var(--primary-gradient);
            color: white;
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(253, 44, 121, 0.3);
            border-color: #fff;
        }
        
        .flow-step.completed .flow-step-circle {
            background: #28a745;
            color: white;
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
            border-color: #fff;
        }
        
        .flow-step.completed .flow-step-circle::after {
            content: '✔';
            font-size: 18px;
        }
        
        .flow-step-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* top/bottom mobile labels (hidden by default) */
        .flow-step-top,
        .flow-step-bottom {
            display: none;
            font-size: 0.85rem;
            color: white;
            font-weight: 600;
            text-align: center;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
            margin: 2px 0;
        }
        
        .flow-step.active .flow-step-label {
            color: white;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.7);
        }
        
        .flow-step.completed .flow-step-label {
            color: white;
        }
        
        .form-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            gap: 15px;
        }
        
        .form-section-title {
            text-align: center;
            margin-bottom: 30px;
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.8rem;
            position: relative;
            padding-bottom: 15px;
        }
        
        .form-section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: var(--primary-gradient);
            border-radius: 2px;
        }
        
        /* Enhanced form styling */
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-color);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .form-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .form-card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
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
            .form-section {
                padding: 20px 15px;
            }
            
            .carousel {
                width: 150px;
                height: 200px;
            }
            
            .carousel span {
                width: 150px;
                height: 200px;
                transform: rotateY(calc(var(--i)*45deg)) translateZ(250px);
            }
            
            .flow-progress {
                flex-wrap: wrap;
                margin-bottom: 30px;
            }
            
            .flow-progress {
                justify-content: flex-start;
                gap: 10px;
                margin-bottom: 16px;
                padding: 0 10px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                flex-wrap: nowrap;
            }

            /* hide the connecting line and progress bar on small screens */
            .flow-progress::before {
                display: none;
            }

            .flow-progress-bar {
                display: none;
            }

            .flow-step {
                width: auto;
                flex: 0 0 auto;
                padding: 0 6px;
                margin-bottom: 0;
                text-align: center;
            }

            .flow-step-circle {
                width: 36px;
                height: 36px;
                font-size: 0.95rem;
                margin: 0 auto 0;
            }

            /* remove label text on mobile to keep the flow compact */
            .flow-step-label {
                display: none;
            }

            /* show split labels above and below the circle on mobile */
            .flow-step-top,
            .flow-step-bottom {
                display: block;
                font-size: 0.75rem;
                color: white;
                margin: 2px 0;
            }
            
            .form-section-title {
                font-size: 1.5rem;
            }
            
            .form-card {
                padding: 20px 15px;
            }
            
            .form-navigation {
                flex-direction: column;
            }
            
            .form-navigation button {
                width: 100%;
            }
        }
        
        @media (max-width: 576px) {
            .flow-progress {
                gap: 8px;
                padding: 0 8px;
                flex-wrap: nowrap;
            }

            .flow-step {
                width: auto;
                flex: 0 0 auto;
                padding: 0 4px;
            }

            .flow-step-circle {
                width: 34px;
                height: 34px;
                font-size: 0.85rem;
            }

            .flow-step-label {
                display: none;
            }
            .flow-step-top,
            .flow-step-bottom {
                display: block;
                font-size: 0.72rem;
                color: white;
                margin: 1px 0;
            }
            
            .form-section-title {
                font-size: 1.3rem;
            }
            
            .form-card-title {
                font-size: 1.1rem;
            }
        }
        
        @media (max-width: 400px) {
            .flow-progress {
                gap: 8px;
                padding: 0 6px;
                flex-wrap: nowrap;
            }

            .flow-step {
                width: auto;
                flex: 0 0 auto;
                padding: 0 4px;
            }

            .flow-step-circle {
                width: 32px;
                height: 32px;
                font-size: 0.8rem;
            }

            .flow-step-label {
                display: none;
            }
            .flow-step-top,
            .flow-step-bottom {
                display: block;
                font-size: 0.7rem;
                color: white;
                margin: 1px 0;
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
        
        /* Enhanced form field styling */
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .form-group label {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .form-group label i {
            margin-right: 8px;
            color: var(--primary-color);
        }
        
        .required::after {
            content: " *";
            color: #dc3545;
        }
        
        /* Step navigation buttons */
        .step-nav-btn {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
        }
        
        .btn-prev, .btn-next {
            min-width: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-prev i, .btn-next i {
            margin: 0 5px;
        }
        
        /* Success checkmark animation */
        .checkmark {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: block;
            stroke-width: 2;
            stroke: #fff;
            stroke-miterlimit: 10;
            margin: 0 auto;
            box-shadow: inset 0px 0px 0px #7ac142;
            animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both;
        }
        
        .checkmark-circle {
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            stroke-width: 2;
            stroke-miterlimit: 10;
            stroke: #7ac142;
            fill: none;
            animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
        }
        
        .checkmark-check {
            transform-origin: 50% 50%;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
        }
        
        @keyframes stroke {
            100% {
                stroke-dashoffset: 0;
            }
        }
        
        @keyframes scale {
            0%, 100% {
                transform: none;
            }
            50% {
                transform: scale3d(1.1, 1.1, 1);
            }
        }
        
        @keyframes fill {
            100% {
                box-shadow: inset 0px 0px 0px 30px #7ac142;
            }
        }
        
        /* Loading spinner */
        .spinner {
            border: 2px solid rgba(255, 255, 255, 0.3);
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border-left-color: #ffffff;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 8px;
        }
        
        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }
        
        /* Invalid field styling */
        .form-control.is-invalid,
        .form-select.is-invalid {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25) !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        
        /* Button loading state */
        .btn[data-locked="true"] {
            pointer-events: none;
            opacity: 0.8;
        }

        .heading1 {
            text-align:center;
            color:transparent;
            -webkit-text-stroke:1px #fff;
            background:url("./img/back.png");
            -webkit-background-clip:text;
            background-clip:text;
            animation:back 20s linear infinite;
        }

        @keyframes back{
            100%{
                background-position:2000px 0;
            }
        }

        /* ====================== */
        /* MOBILE RESPONSIVENESS */
        /* ====================== */

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
                /* slightly larger logo on mobile */
                max-width: 220px;
                height: 144px;
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

        /* Tablet view navbar height parity with members.php */
        @media (max-width: 768px) and (min-width: 577px) {
            .navbar {
                max-height: 73px;
            }
        }

        /* Mobile Hero Section */
        @media (max-width: 768px) {
            .new-hero-section {
                min-height: auto;
                padding: 110px 0 50px;
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
                margin-top: 25px;
                padding-top: 15px;
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
            .navbar {
                max-height: 89px;
            }
            
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
            .navbar {
                min-height: 47px;
                padding: 0;
            }
            
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
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link active" href="members.php">Membership</a></li>
                    <li class="nav-item"><a class="nav-link" href="mem.php">Members</a></li>
                    <li class="nav-item"><a class="nav-link" href="package.php">Packages</a></li>
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
    <section class="hero-section" style="margin-top: 10px;">
        <div class="container">
            <h1 class="display-4 fw-bold heading1">Become a Member</h1>
            <p class="lead">Join our community to find your perfect life partner</p>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container py-3">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success text-center">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger text-center">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Enhanced Flow Progress -->
        <div class="flow-container">
            <div class="flow-progress">
                <div class="flow-progress-bar" id="progressBar"></div>
                <div class="flow-step active" data-step="1">
                    <div class="flow-step-top">Member</div>
                    <div class="flow-step-circle">1</div>
                    <div class="flow-step-bottom">Details</div>
                    <div class="flow-step-label">Member Details</div>
                </div>
                <div class="flow-step" data-step="2">
                    <div class="flow-step-top">Physical</div>
                    <div class="flow-step-circle">2</div>
                    <div class="flow-step-bottom">Info</div>
                    <div class="flow-step-label">Physical Info</div>
                </div>
                <div class="flow-step" data-step="3">
                    <div class="flow-step-top">Education</div>
                    <div class="flow-step-circle">3</div>
                    <div class="flow-step-bottom">Info</div>
                    <div class="flow-step-label">Education Info</div>
                </div>
                <div class="flow-step" data-step="4">
                    <div class="flow-step-top">Family</div>
                    <div class="flow-step-circle">4</div>
                    <div class="flow-step-bottom">Info</div>
                    <div class="flow-step-label">Family Info</div>
                </div>
                <div class="flow-step" data-step="5">
                    <div class="flow-step-top">Partner</div>
                    <div class="flow-step-circle">5</div>
                    <div class="flow-step-bottom">Expectation</div>
                    <div class="flow-step-label">Partner Expectation</div>
                </div>
                <div class="flow-step" data-step="6">
                    <div class="flow-step-top">Horoscope</div>
                    <div class="flow-step-circle">6</div>
                    <div class="flow-step-bottom">Info</div>
                    <div class="flow-step-label">Horoscope Info</div>
                </div>
            </div>

            <!-- Member Details Form -->
            <section id="member-details" class="form-section active" <?php echo $existing_member ? 'style="pointer-events: none; opacity: 0.6;"' : ''; ?>>
                <h2 class="form-section-title">Member Details</h2>
                <form id="memberDetailsForm" enctype="multipart/form-data">
                    <div class="form-card">
                        <h3 class="form-card-title"><i class="bi bi-person-circle"></i> Personal Information / தனிப்பட்ட தகவல்</h3>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required"><i class="bi bi-person"></i> Name / <span style="font-weight: normal; padding-left: 4px;"> பெயர்</span></label>
                                    <input type="text" class="form-control" name="name" placeholder="Your full name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required"><i class="bi bi-image"></i> Upload Your Photo / <span style="font-weight: normal; padding-left: 4px;"> உங்கள் புகைப்படம்</span></label>
                                    <input type="file" class="form-control" name="photo" accept="image/*" required>
                                    <small class="text-muted">Supported formats: JPG, PNG. Max size: 2MB.</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label class="form-label"><i class="bi bi-images"></i> Additional Photos / <span style="font-weight: normal; padding-left: 4px;"> மேலதிக புகைப்படம்</span></label>
                                    <div id="additional-photos-container">
                                        <div class="additional-photo-item mb-3">
                                            <div class="row">
                                                <div class="col-md-10">
                                                    <input type="file" class="form-control" name="additional_photos[]" accept="image/*">
                                                </div>
                                                <div class="col-md-2">
                                                    <button type="button" class="btn btn-success w-100" onclick="addPhotoField()" title="Add Photo"><i class="bi bi-plus"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <small class="text-muted">Upload up to 5 additional photos. Supported formats: JPG, PNG. Max size: 2MB each.</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required"><i class="bi bi-search"></i> Looking For / <span style="font-weight: normal; padding-left: 4px;"> தேடுகிறவர் பாலினம்</span></label>
                                    <select class="form-select" name="looking_for" required>
                                        <option value="">Select option</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required"><i class="bi bi-calendar-event"></i> Date of Birth / <span style="font-weight: normal; padding-left: 4px;"> பிறந்த தேதி</span></label>
                                    <input type="date" class="form-control" name="dob" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required"><i class="bi bi-heart"></i> Religion / <span style="font-weight: normal; padding-left: 4px;"> மதம்</span></label>
                                    <select class="form-select" name="religion" required>
                                        <option value="">Select option</option>
                                        <option value="Hindu">Hindu</option>
                                        <option value="Christian">Christian</option>
                                        <option value="Islam">Islam</option>
                                        <option value="Buddhist">Buddhist</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required"><i class="bi bi-gender-ambiguous"></i> Gender / <span style="font-weight: normal; padding-left: 4px;"> பாலினம்</span></label>
                                    <select class="form-select" name="gender" required>
                                        <option value="">Select option</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-card">
                        <h3 class="form-card-title"><i class="bi bi-info-circle"></i> Additional Information / கூடுதல் தகவல்
</h3>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required"><i class="bi bi-people"></i> Marital Status / <span style="font-weight: normal; padding-left: 4px;"> திருமண நிலை</span></label>
                                    <select class="form-select" name="marital_status" required>
                                        <option value="">Select option</option>
                                        <option value="Single">Single</option>
                                        <option value="Divorced">Divorced</option>
                                        <option value="Widowed">Widowed</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required"><i class="bi bi-translate"></i> Language / <span style="font-weight: normal; padding-left: 4px;"> மொழி</span></label>
                                    <select class="form-control" name="language" required>
                                        <option value="" disabled selected>Select Language</option>
                                        <option value="Tamil">Tamil</option>
                                        <option value="English">English</option>
                                        <option value="Sinhala">Sinhala</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required"><i class="bi bi-briefcase"></i> Profession / <span style="font-weight: normal; padding-left: 4px;"> தொழில்</span></label>
                                    <input type="text" class="form-control" name="profession" placeholder="Eg: Teacher, Engineer" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required"><i class="bi bi-globe"></i> Location / <span style="font-weight: normal; padding-left: 4px;"> முகவரி</span></label>
                                    <input type="text" class="form-control" name="country" placeholder="Eg: Jaffna, Sri Lanka" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label required"><i class="bi bi-telephone"></i> Phone Number / <span style="font-weight: normal; padding-left: 4px;"> தொலைபேசி எண்</span></label>
                            <input type="tel" class="form-control" name="phone"  id="member_phone" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label"><i class="bi bi-cloud-fog"></i> Smoking Habit / <span style="font-weight: normal; padding-left: 4px;"> புகைபிடிக்கும் பழக்கம்</span></label>
                                    <select class="form-select" name="smoking">
                                        <option value="No">No</option>
                                        <option value="Yes">Yes</option>
                                        <option value="Occasionally">Occasionally</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label"><i class="bi bi-cup-straw"></i> Drinking Habit / <span style="font-weight: normal; padding-left: 4px;"> மது அருந்தும் பழக்கம்<span></label>
                                    <select class="form-select" name="drinking">
                                        <option value="No">No</option>
                                        <option value="Yes">Yes</option>
                                        <option value="Occasionally">Occasionally</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="form-group"> 
                                    <label class="form-label">💸 Income / <span style="font-weight: normal; padding-left: 4px;"> வருமானம்</span></label>
                                    <input type="text" class="form-control" id="income" name="income" placeholder="Enter your income" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-navigation">
                        <button type="button" class="btn btn-primary w-100" onclick="validateAndNext('memberDetailsForm', 2, this)">Next <i class="bi bi-arrow-right"></i></button>
                    </div>
                </form>
            </section>

            <!-- Physical Information Form -->
            <section id="physical-info" class="form-section">
                <h2 class="form-section-title">Physical Information</h2>
                <form id="physicalInfoForm">
                    <div class="form-card">
                        <h3 class="form-card-title"><i class="bi bi-person-bounding-box"></i> Physical Attributes / உடல் பண்புகள்</h3>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required"><i class="bi bi-palette"></i> Complexion / <span style="font-weight: normal; padding-left: 4px;">தோல் நிறம்</span></label>
                                    <select class="form-select" name="complexion" required>
                                        <option value="">Select option</option>
                                        <option value="Fair">Fair</option>
                                        <option value="Wheatish">Wheatish</option>
                                        <option value="Dark">Dark</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required"><i class="bi bi-arrows-expand"></i> Height (cm) / <span style="font-weight: normal; padding-left: 4px;">உயரம் (செ.மீ.)</span></label>
                                    <input type="number" class="form-control" max="300" name="height" placeholder="Eg: 170" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required"><i class="bi bi-speedometer"></i> Weight (kg) / <span style="font-weight: normal; padding-left: 4px;">எடை (கி.கி.)</span></label>
                                    <input type="number" class="form-control" max="300" name="weight" placeholder="Eg: 65" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required"><i class="bi bi-droplet"></i> Blood Group / <span style="font-weight: normal; padding-left: 4px;">இரத்த வகை</span></label>
                                    <select class="form-select" name="blood_group" required>
                                        <option value="">Select option</option>
                                        <option value="A+">A+</option>
                                        <option value="A-">A-</option>
                                        <option value="B+">B+</option>
                                        <option value="B-">B-</option>
                                        <option value="O+">O+</option>
                                        <option value="O-">O-</option>
                                        <option value="AB+">AB+</option>
                                        <option value="AB-">AB-</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required"><i class="bi bi-eye"></i> Eye Color / <span style="font-weight: normal; padding-left: 4px;">கண் நிறம்</span></label>
                                    <input type="text" class="form-control" name="eye_color" placeholder="Eg: Brown" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required"><i class="bi bi-scissors"></i> Hair Color / <span style="font-weight: normal; padding-left: 4px;">முடி நிறம்</span></label>
                                    <input type="text" class="form-control" name="hair_color" placeholder="Eg: Black" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label required"><i class="bi bi-person-check"></i> Any Disability / <span style="font-weight: normal; padding-left: 4px;">மாற்றுத்திறன் உள்ளதா</span></label>
                            <select class="form-select" name="disability" required>
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-navigation">
                        <button type="button" class="btn btn-primary" onclick="validateAndNext('physicalInfoForm', 3, this)">Next <i class="bi bi-arrow-right"></i></button>
                    </div>
                </form>
            </section>

            <!-- Education Information Form -->
            <section id="education-info" class="form-section">
                <h2 class="form-section-title">Educational Information</h2>
                <form id="educationInfoForm">
                    <div class="form-card">
                        <h3 class="form-card-title"><i class="bi bi-book"></i> Educational Background / கல்வி பின்னணி</h3>
                        <div id="higher-edu-container">
                            <div class="education-entry">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label"><i class="bi bi-building"></i> Institute / <span style="font-weight: normal; padding-left: 4px;">நிறுவனம்</span></label>
                                            <input type="text" class="form-control" name="institute[]" placeholder="Institute Name">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label"><i class="bi bi-award"></i> Degree / <span style="font-weight: normal; padding-left: 4px;">பட்டம்</span></label>
                                            <input type="text" class="form-control" name="degree[]" placeholder="Degree Name">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label"><i class="bi bi-journal-bookmark"></i> Field of Study / <span style="font-weight: normal; padding-left: 4px;">ஆய்வுக் களம்</span></label>
                                            <input type="text" class="form-control" name="field[]" placeholder="Field of Study">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label"><i class="bi bi-card-checklist"></i> Reg. Number / <span style="font-weight: normal; padding-left: 4px;">பதிவு எண்</span></label>
                                            <input type="text" class="form-control" name="regnum[]" placeholder="Registration Number">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label"><i class="bi bi-calendar-plus"></i> Start Year / <span style="font-weight: normal; padding-left: 4px;">தொடக்க ஆண்டு</span></label>
                                            <input type="number" class="form-control" name="startyear[]" placeholder="Start Year">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label"><i class="bi bi-calendar-check"></i> End Year / <span style="font-weight: normal; padding-left: 4px;">இறுதி ஆண்டு</span></label>
                                            <input type="number" class="form-control" name="endyear[]" placeholder="End Year">
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-12 text-end">
                                        <button type="button" class="btn btn-outline-danger btn-sm remove-education-btn" onclick="removeEducation(this)" style="display: none;">
                                            <i class="bi bi-trash"></i> Remove Education
                                        </button>
                                    </div>
                                </div>
                                <hr>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="button" class="btn btn-success" onclick="addHigherEducation()"><i class="bi bi-plus-circle"></i> Add New Education</button>
                        </div>
                    </div>

                    <div class="form-navigation">
                        <button type="button" class="btn btn-primary" onclick="validateAndNext('educationInfoForm', 4, this)">Next <i class="bi bi-arrow-right"></i></button>
                    </div>
                </form>
            </section>
            
            <script>
                function initIntlTel(id) {
                    const input = document.querySelector(id);
                
                    return window.intlTelInput(input, {
                        initialCountry: "auto",
                        nationalMode: false,
                        separateDialCode: true,
                        geoIpLookup: callback => {
                            fetch("https://ipapi.co/json/")
                                .then(resp => resp.json())
                                .then(data => callback(data.country_code))
                                .catch(() => callback("LK"));
                        },
                        utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js"
                    });
                }
                
                // Initialize for each field
                const phoneIti = initIntlTel("#member_phone");
            </script>


            <!-- Family Information Form -->
            <section id="family-info" class="form-section">
                <h2 class="form-section-title">Family Information</h2>
                <form id="familyInfoForm">
                    <div class="form-card">
                        <h3 class="form-card-title"><i class="bi bi-people-fill"></i> Family Details / குடும்ப விவரங்கள்</h3>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required"><i class="bi bi-person"></i> Father's Name / <span style="font-weight: normal; padding-left: 4px;">தந்தையின் பெயர்</span></label>
                                    <input type="text" class="form-control" name="father_name" placeholder="Enter father's name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label"><i class="bi bi-briefcase"></i> Father's Profession / <span style="font-weight: normal; padding-left: 4px;">தந்தையின் தொழில்</span></label>
                                    <input type="text" class="form-control" name="father_profession" placeholder="Enter father's profession">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label"><i class="bi bi-telephone"></i> Father's Contact / <span style="font-weight: normal; padding-left: 4px;">தந்தையின் தொடர்பு எண்</span></label>
                                    <input type="text" class="form-control" name="father_contact" placeholder="Phone number">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required"><i class="bi bi-person"></i> Mother's Name / <span style="font-weight: normal; padding-left: 4px;">தாயின் பெயர்</span></label>
                                    <input type="text" class="form-control" name="mother_name" placeholder="Enter mother's name" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label"><i class="bi bi-briefcase"></i> Mother's Profession / <span style="font-weight: normal; padding-left: 4px;">தாயின் தொழில்</span></label>
                                    <input type="text" class="form-control" name="mother_profession" placeholder="Enter mother's profession">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label"><i class="bi bi-telephone"></i> Mother's Contact / <span style="font-weight: normal; padding-left: 4px;">அம்மாவின் தொடர்பு எண்</span></label>
                                    <input type="text" class="form-control" name="mother_contact" placeholder="Phone number">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label"><i class="bi bi-gender-male"></i> Total Brothers / <span style="font-weight: normal; padding-left: 4px;">மொத்த சகோதரர்கள்</span></label>
                                    <input type="number" class="form-control" name="brothers" placeholder="Eg: 2">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label"><i class="bi bi-gender-female"></i> Total Sisters / <span style="font-weight: normal; padding-left: 4px;">மொத்த சகோதரிகள்</span></label>
                                    <input type="number" class="form-control" name="sisters" placeholder="Eg: 1" >
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-navigation">
                        <button type="button" class="btn btn-primary" onclick="validateAndNext('familyInfoForm', 5, this)">Next <i class="bi bi-arrow-right"></i></button>
                    </div>
                </form>
            </section>

            <!-- Partner Expectations Form -->
            <section id="partner-info" class="form-section">
                <h2 class="form-section-title">Partner Expectations</h2>
                <form id="partnerInfoForm">
                    <div class="form-card">
                        <h3 class="form-card-title"><i class="bi bi-heart"></i> Partner Preferences / கூட்டாளர் விருப்பத்தேர்வுகள்</h3>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required"><i class="bi bi-globe"></i> Preferred Country / <span style="font-weight: normal; padding-left: 4px;">விருப்பமான நாடு</span></label>
                                    <input type="text" class="form-control" name="partner_country" placeholder="Enter country" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required"><i class="bi bi-heart"></i> Religion / <span style="font-weight: normal; padding-left: 4px;">மதம்</span></label>
                                    <select class="form-select" name="partner_religion" required>
                                        <option value="">Select option</option>
                                        <option value="Hindu">Hindu</option>
                                        <option value="Christian">Christian</option>
                                        <option value="Islam">Islam</option>
                                        <option value="Buddhist">Buddhist</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required"><i class="bi bi-calendar-minus"></i> Minimum Age / <span style="font-weight: normal; padding-left: 4px;">குறைந்தபட்ச வயது</span></label>
                                    <input type="number" class="form-control" name="min_age" placeholder="Eg: 25" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required"><i class="bi bi-calendar-plus"></i> Maximum Age / <span style="font-weight: normal; padding-left: 4px;">அதிகபட்ச வயது</span></label>
                                    <input type="number" class="form-control" name="max_age" placeholder="Eg: 35" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required"><i class="bi bi-arrows-collapse"></i> Minimum Height (cm) / <span style="font-weight: normal; padding-left: 4px;">குறைந்தபட்ச உயரம்</span></label>
                                    <input type="number" class="form-control" name="min_height" placeholder="Eg: 150" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required"><i class="bi bi-arrows-expand"></i> Maximum Height (cm) / <span style="font-weight: normal; padding-left: 4px;">அதிகபட்ச உயரம்</span></label>
                                    <input type="number" class="form-control" name="max_height" placeholder="Eg: 180" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required"><i class="bi bi-cloud-fog"></i> Smoking Habit / <span style="font-weight: normal; padding-left: 4px;">புகைபிடிக்கும் பழக்கம்</span></label>
                                    <select class="form-select" name="partner_smoking" required>
                                        <option value="" selected disabled>Select option</option>
                                        <option value="Yes">Yes</option>
                                        <option value="No">No</option>
                                        <option value="Occasionally">Occasionally</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required"><i class="bi bi-cup-straw"></i> Drinking Habit / <span style="font-weight: normal; padding-left: 4px;">குடிப்பழக்கம்</span></label>
                                    <select class="form-select" name="partner_drinking" required>
                                        <option value="" selected disabled>Select option</option>
                                        <option value="Yes">Yes</option>
                                        <option value="No">No</option>
                                        <option value="Occasionally">Occasionally</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required"><i class="bi bi-people"></i> Marital Status / <span style="font-weight: normal; padding-left: 4px;">திருமண நிலை</span></label>
                                    <select class="form-select" name="partner_marital_status" required>
                                        <option value="" selected disabled>Select status</option>
                                        <option value="Never Married">Never Married</option>
                                        <option value="Divorced">Divorced</option>
                                        <option value="Widowed">Widowed</option>
                                        <option value="Separated">Separated</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-navigation">
                        <button type="button" class="btn btn-primary" onclick="validateAndNext('partnerInfoForm', 6, this)">Next <i class="bi bi-arrow-right"></i></button>
                    </div>
                </form>
            </section>

            <!-- Horoscope Information Form -->
            <section id="horoscope-info" class="form-section">
                <h2 class="form-section-title">Horoscope Details</h2>
                <form id="horoscopeInfoForm" enctype="multipart/form-data">
                    <div class="form-card">
                        <h3 class="form-card-title"><i class="bi bi-stars"></i> Astrological Information / ஜோதிட தகவல்</h3>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label"><i class="bi bi-calendar-date"></i> Birth Date / <span style="font-weight: normal; padding-left: 4px;">பிறந்த தேதி</span></label>
                                    <input type="date" class="form-control" name="birth_date">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label"><i class="bi bi-clock"></i> Birth Time / <span style="font-weight: normal; padding-left: 4px;">பிறந்த நேரம்</span></label>
                                    <input type="time" class="form-control" name="birth_time">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label"><i class="bi bi-sun"></i> Zodiac Sign / <span style="font-weight: normal; padding-left: 4px;">இராசி அடையாளம்</span></label>
                                    <input type="text" class="form-control" name="zodiac" placeholder="Eg: Leo, Virgo">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label"><i class="bi bi-moon-stars"></i> Nakshatra / <span style="font-weight: normal; padding-left: 4px;">நட்சத்திரம்</span></label>
                                    <select class="form-control" name="nakshatra">
                                      <option value="1001">
                                                                                    அஸ்வினி
                                                                                </option>
                                                                                                                                                            <option value="1002">
                                                                                    பரணி
                                                                                </option>
                                                                                                                                                            <option value="1003">
                                                                                    கார்த்திகை 1ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1004">
                                                                                    கார்த்திகை 2ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1005">
                                                                                    கார்த்திகை 3ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1006">
                                                                                    கார்த்திகை 4ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1007">
                                                                                    ரோகிணி
                                                                                </option>
                                                                                                                                                            <option value="1008">
                                                                                    மிருகசீரிடம் 1ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1009">
                                                                                    மிருகசீரிடம் 2ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1010">
                                                                                    மிருகசீரிடம் 3ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1011">
                                                                                    மிருகசீரிடம் 4ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1012">
                                                                                    திருவாதிரை
                                                                                </option>
                                                                                                                                                            <option value="1013">
                                                                                    புனர்பூசம் 1ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1014">
                                                                                    புனர்பூசம் 2ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1015">
                                                                                    புனர்பூசம் 3ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1016">
                                                                                    புனர்பூசம் 4ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1017">
                                                                                    பூசம்
                                                                                </option>
                                                                                                                                                            <option value="1018">
                                                                                    ஆயிலியம்
                                                                                </option>
                                                                                                                                                            <option value="1019">
                                                                                    மகம்
                                                                                </option>
                                                                                                                                                            <option value="1020">
                                                                                    பூரம்
                                                                                </option>
                                                                                                                                                            <option value="1021">
                                                                                    உத்தரம் 1ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1022">
                                                                                    உத்தரம் 2ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1023">
                                                                                    உத்தரம் 3ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1024">
                                                                                    உத்தரம் 4ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1025">
                                                                                    அஸ்தம்
                                                                                </option>
                                                                                                                                                            <option value="1026">
                                                                                    சித்திரை 1ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1027">
                                                                                    சித்திரை 2ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1028">
                                                                                    சித்திரை 3ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1029">
                                                                                    சித்திரை 4ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1030">
                                                                                    சுவாதி
                                                                                </option>
                                                                                                                                                            <option value="1031">
                                                                                    விசாகம் 1ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1032">
                                                                                    விசாகம் 2ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1033">
                                                                                    விசாகம் 3ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1034">
                                                                                    விசாகம் 4ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1035">
                                                                                    அனுஷம்
                                                                                </option>
                                                                                                                                                            <option value="1036">
                                                                                    கேட்டை
                                                                                </option>
                                                                                                                                                            <option value="1037">
                                                                                    மூலம்
                                                                                </option>
                                                                                                                                                            <option value="1038">
                                                                                    பூராடம்
                                                                                </option>
                                                                                                                                                            <option value="1039">
                                                                                    உத்திராடம் 1ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1040">
                                                                                    உத்திராடம் 2ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1041">
                                                                                    உத்திராடம் 3ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1042">
                                                                                    உத்திராடம் 4ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1043">
                                                                                    திருவோணம்
                                                                                </option>
                                                                                                                                                            <option value="1044">
                                                                                    அவிட்டம் 1 ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1045">
                                                                                    அவிட்டம் 2ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1046">
                                                                                    அவிட்டம் 3ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1047">
                                                                                    அவிட்டம் 4ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1048">
                                                                                    சதயம்
                                                                                </option>
                                                                                                                                                            <option value="1049">
                                                                                    பூரட்டாதி 1ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1050">
                                                                                    பூரட்டாதி 2ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1051">
                                                                                    பூரட்டாதி 3ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1052">
                                                                                    பூரட்டாதி 4ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1053">
                                                                                    உத்திரட்டாதி
                                                                                </option>
                                                                                                                                                            <option value="1054">
                                                                                    ரேவதி
                                                                                </option>
                                    </select>

                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="bi bi-arrow-repeat"></i> Karmic Debt / <span style="font-weight: normal; padding-left: 4px;">கர்மக் கடன்</span></label>
                            <input type="text" class="form-control" name="karmic_debt" placeholder="Eg: Yes/No or Specific Value">
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label"><i class="bi bi-image"></i> Planet Position Image / <span style="font-weight: normal; padding-left: 4px;">கிரக நிலை படம்</span></label>
                                    <input type="file" class="form-control" name="planet_image" accept="image/*">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label"><i class="bi bi-image-alt"></i> Navamsha Position Image / <span style="font-weight: normal; padding-left: 4px;">நவாம்ச நிலை படம்</span></label>
                                    <input type="file" class="form-control" name="navamsha_image" accept="image/*">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-navigation">
                        <button type="button" class="btn btn-success" onclick="finishRegistration()"><i class="bi bi-check-lg"></i> Finish Registration</button>
                    </div>
                </form>
            </section>
        </div>
    </div>
  
    <div class="spacer"></div>

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
                        <li class="mb-2"><a href="mem.php" class="text-white text-decoration-none">Members</a></li>
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
            
            <div class="text-center">Thennilavu Matrimony. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
        
        // Flow navigation
        let currentStep = 1;
        const totalSteps = 6;
        
        function updateProgressBar() {
            const progressBar = document.getElementById('progressBar');
            const progressPercentage = ((currentStep - 1) / (totalSteps - 1)) * 100;
            progressBar.style.width = `${progressPercentage}%`;
        }
        
        function updateStepIndicators() {
            document.querySelectorAll('.flow-step').forEach((step, index) => {
                const stepNumber = index + 1;
                
                if (stepNumber < currentStep) {
                    step.classList.add('completed');
                    step.classList.remove('active');
                } else if (stepNumber === currentStep) {
                    step.classList.add('active');
                    step.classList.remove('completed');
                } else {
                    step.classList.remove('active', 'completed');
                }
            });
            
            updateProgressBar();
        }
        
        function showStep(stepNumber) {
            document.querySelectorAll('.form-section').forEach(section => {
                section.classList.remove('active');
            });
            
            const sections = [
                'member-details',
                'physical-info',
                'education-info',
                'family-info',
                'partner-info',
                'horoscope-info'
            ];
            
            if (stepNumber >= 1 && stepNumber <= sections.length) {
                document.getElementById(sections[stepNumber - 1]).classList.add('active');
                currentStep = stepNumber;
                updateStepIndicators();
                
                // Scroll to the top of the form
                document.querySelector('.flow-container').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
        
        function goToStep(stepNumber) {
            showStep(stepNumber);
        }
        
        // Add education field
        // Add photo field function
        function addPhotoField() {
            const container = document.getElementById('additional-photos-container');
            const photoItems = container.querySelectorAll('.additional-photo-item');
            
            if (photoItems.length >= 5) {
                alert('Maximum 5 additional photos allowed.');
                return;
            }
            
            const newPhotoItem = document.createElement('div');
            newPhotoItem.className = 'additional-photo-item mb-3';
            newPhotoItem.innerHTML = `
                <div class="row">
                    <div class="col-md-10">
                        <input type="file" class="form-control" name="additional_photos[]" accept="image/*">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-danger w-100" onclick="removePhotoField(this)" title="Remove Photo"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            `;
            container.appendChild(newPhotoItem);
        }
        
        function removePhotoField(button) {
            const container = document.getElementById('additional-photos-container');
            const photoItems = container.querySelectorAll('.additional-photo-item');
            
            if (photoItems.length > 1) {
                button.closest('.additional-photo-item').remove();
            } else {
                alert('At least one photo field must remain.');
            }
        }

        function addHigherEducation() {
            const container = document.getElementById('higher-edu-container');
            const newEntry = document.createElement('div');
            newEntry.className = 'education-entry';
            newEntry.innerHTML = `
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label"><i class="bi bi-building"></i> Institute / <span style="font-weight: normal; padding-left: 4px;">நிறுவனம்</span></label>
                            <input type="text" class="form-control" name="institute[]" placeholder="Institute Name">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label"><i class="bi bi-award"></i> Degree / <span style="font-weight: normal; padding-left: 4px;">பட்டம்</span></label>
                            <input type="text" class="form-control" name="degree[]" placeholder="Degree Name">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label"><i class="bi bi-journal-bookmark"></i> Field of Study / <span style="font-weight: normal; padding-left: 4px;">ஆய்வுக் களம்</span></label>
                            <input type="text" class="form-control" name="field[]" placeholder="Field of Study">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label"><i class="bi bi-card-checklist"></i> Reg. Number / <span style="font-weight: normal; padding-left: 4px;">பதிவு எண்</span></label>
                            <input type="text" class="form-control" name="regnum[]" placeholder="Registration Number">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label"><i class="bi bi-calendar-plus"></i> Start Year / <span style="font-weight: normal; padding-left: 4px;">தொடக்க ஆண்டு</span></label>
                            <input type="number" class="form-control" name="startyear[]" placeholder="Start Year">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label"><i class="bi bi-calendar-check"></i> End Year / <span style="font-weight: normal; padding-left: 4px;">இறுதி ஆண்டு</span></label>
                            <input type="number" class="form-control" name="endyear[]" placeholder="End Year">
                        </div>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-12 text-end">
                        <button type="button" class="btn btn-outline-danger btn-sm remove-education-btn" onclick="removeEducation(this)">
                            <i class="bi bi-trash"></i> Remove Education
                        </button>
                    </div>
                </div>
                <hr>
            `;
            container.appendChild(newEntry);
            updateRemoveButtonsVisibility();
        }

        function removeEducation(button) {
            const container = document.getElementById('higher-edu-container');
            const educationEntries = container.querySelectorAll('.education-entry');
            
            // Don't allow removal if only one entry exists
            if (educationEntries.length <= 1) {
                alert('At least one education entry is required.');
                return;
            }
            
            // Remove the education entry
            const entryToRemove = button.closest('.education-entry');
            entryToRemove.remove();
            
            // Update remove button visibility
            updateRemoveButtonsVisibility();
        }
        
        function updateRemoveButtonsVisibility() {
            const container = document.getElementById('higher-edu-container');
            const educationEntries = container.querySelectorAll('.education-entry');
            const removeButtons = container.querySelectorAll('.remove-education-btn');
            
            // Show remove buttons only if there are more than 1 entries
            removeButtons.forEach(button => {
                button.style.display = educationEntries.length > 1 ? 'inline-block' : 'none';
            });
        }
        
        // Initialize remove button visibility on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateRemoveButtonsVisibility();
        });
        
        function validateAndNext(formId, nextStep, btn) {
            const form = document.getElementById(formId);
            const button = btn || document.activeElement || null;
            let originalBtnHtml = null;
            
            // Function to restore button state
            function restoreButton() {
                if (button) {
                    button.disabled = false;
                    button.dataset.locked = 'false';
                    if (originalBtnHtml) {
                        button.innerHTML = originalBtnHtml;
                    }
                }
            }
            
            // Check if button is already being processed
            if (button && button.dataset.locked === 'true') {
                return false; // Prevent multiple clicks
            }
            
            // Validate required fields FIRST (except for horoscope section)
            if (formId !== 'horoscopeInfoForm') {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                let firstInvalidField = null;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.style.borderColor = '#dc3545';
                        field.classList.add('is-invalid');
                        if (!firstInvalidField) {
                            firstInvalidField = field;
                        }
                        isValid = false;
                    } else {
                        field.style.borderColor = '#e1e1e1';
                        field.classList.remove('is-invalid');
                    }
                });
                
                // If validation fails, don't proceed and don't show loading
                if (!isValid) {
                    showAlert('Please fill in all required fields before proceeding.', 'danger');
                    if (firstInvalidField) {
                        firstInvalidField.focus();
                        firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return false;
                }
            }
            
            // Only set loading state AFTER validation passes
            if (button) {
                button.dataset.locked = 'true';
                originalBtnHtml = button.innerHTML;
                button.disabled = true;
                button.innerHTML = '<div class="spinner"></div> Processing...';
            }
            
            // Submit current form data
            const formData = new FormData(form);
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Move to next step
                showStep(nextStep);
                // Reset button state for future use
                restoreButton();
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error saving data. Please try again.', 'danger');
                restoreButton();
            });
            
            return true;
        }
        
        function finishRegistration() {
            const form = document.getElementById('horoscopeInfoForm');
            const formData = new FormData(form);
            
            // Show loading state
            const finishBtn = document.querySelector('#horoscope-info button[onclick="finishRegistration()"]');
            const originalText = finishBtn.innerHTML;
            finishBtn.innerHTML = '<div class="spinner"></div>';
            finishBtn.disabled = true;
            
            // Submit horoscope data
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                showAlert('Registration completed successfully!', 'success');
                setTimeout(() => {
                    window.location.href = 'package.php';
                }, 1500);
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Network error. Please try again.', 'danger');
                finishBtn.innerHTML = originalText;
                finishBtn.disabled = false;
            });
        }

        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            setTimeout(() => alertDiv.remove(), 5000);
        }
        
        // Initialize the form
        updateStepIndicators();
        
        // Click functionality to flow steps removed - navigation disabled
        
        // Function to navigate to specific step
        function navigateToStep(stepNumber) {
            // Hide all sections
            document.querySelectorAll('.form-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Show target section
            const targetSections = {
                1: 'member-details',
                2: 'physical-info', 
                3: 'education-info',
                4: 'family-info',
                5: 'partner-info',
                6: 'horoscope-info'
            };
            
            const targetSection = document.getElementById(targetSections[stepNumber]);
            if (targetSection) {
                targetSection.classList.add('active');
                
                // Scroll to section smoothly
                targetSection.scrollIntoView({ 
                    behavior: 'smooth',
                    block: 'start'
                });
                
                // Update step indicators
                currentStep = stepNumber;
                updateStepIndicators();
            }
        }
    </script> 
</body>
</html>