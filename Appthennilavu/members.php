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

// Check if user already has member details
$existing_member = false;
$member_id_existing = null;
if (isset($_SESSION['user_id'])) {
    $check_stmt = $conn->prepare("SELECT id FROM members WHERE user_id = ?");
    $check_stmt->bind_param("i", $_SESSION['user_id']);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    if ($result->num_rows > 0) {
        $existing_member = true;
        $row = $result->fetch_assoc();
        $member_id_existing = $row['id'];
        $_SESSION['member_id'] = $member_id_existing;
    }
    $check_stmt->close();
}

// Prevent existing members from re-submitting
if ($existing_member && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Location: profile.php'); // Redirect to profile/edit page
    exit;
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
        $income = $_POST['income'] ?? '';

        $photo = '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
            $photo = 'uploads/' . time() . '_' . $_FILES['photo']['name'];
            move_uploaded_file($_FILES['photo']['tmp_name'], $photo);
        }

        // Check if this is an update or insert
        if ($existing_member && isset($_SESSION['member_id'])) {
            // UPDATE existing member
            $member_id = $_SESSION['member_id'];
            if ($photo) {
                // Update with new photo
                $stmt = $conn->prepare("UPDATE members SET name=?, photo=?, looking_for=?, dob=?, religion=?, gender=?, marital_status=?, language=?, profession=?, country=?, phone=?, smoking=?, drinking=?, income=? WHERE id=?");
                $stmt->bind_param("sssssssssssssi", $name, $photo, $looking_for, $dob, $religion, $gender, $marital_status, $language, $profession, $country, $phone, $smoking, $drinking, $member_id);
            } else {
                // Update without changing photo
                $stmt = $conn->prepare("UPDATE members SET name=?, looking_for=?, dob=?, religion=?, gender=?, marital_status=?, language=?, profession=?, country=?, phone=?, smoking=?, drinking=?, income=? WHERE id=?");
                $stmt->bind_param("ssssssssssssi", $name, $looking_for, $dob, $religion, $gender, $marital_status, $language, $profession, $country, $phone, $smoking, $drinking, $income, $member_id);
            }
            
            if ($stmt->execute()) {
                $success_message = "Member information updated successfully.";
            } else {
                $error_message = "Error updating member information: " . $stmt->error;
            }
            $stmt->close();
        } else {
            // INSERT new member
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
                $success_message = "Member information saved successfully.";
                
                // Handle additional photos
                if (isset($_FILES['additional_photos']) && is_array($_FILES['additional_photos']['name'])) {
                    $upload_dir = 'uploads/additional_photos/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $photo_count = 0;
                    $total_photos = count($_FILES['additional_photos']['name']);
                    for ($i = 0; $i < $total_photos && $photo_count < 5; $i++) {
                        if (isset($_FILES['additional_photos']['error'][$i]) && $_FILES['additional_photos']['error'][$i] === 0 && !empty($_FILES['additional_photos']['name'][$i])) {
                            $photo_name = time() . '_' . $i . '_' . basename($_FILES['additional_photos']['name'][$i]);
                            $photo_path = $upload_dir . $photo_name;

                            if (move_uploaded_file($_FILES['additional_photos']['tmp_name'][$i], $photo_path)) {
                                $photo_stmt = $conn->prepare("INSERT INTO additional_photos (member_id, photo_path, upload_order) VALUES (?, ?, ?)");
                                if ($photo_stmt) {
                                    $upload_order = $photo_count + 1;
                                    $photo_stmt->bind_param("isi", $member_id, $photo_path, $upload_order);
                                    $photo_stmt->execute();
                                    $photo_stmt->close();
                                    $photo_count++;
                                }
                            }
                        }
                    }
                }
            }
            
            $success_message = "Member saved. Continue next steps.";
        }
        $stmt->close();
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Mobile App Design Variables */
        :root {
            --primary-color: #e91e63;
            --primary-dark: #c2185b;
            --secondary-color: #2c3e50;
            --accent-color: #ff4081;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
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
            --gradient-primary: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        [data-theme="dark"] {
            --primary-color: #e91e63;
            --primary-dark: #c2185b;
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: var(--bg-primary, #ffffff);
            color: var(--text-primary, #1a1a2e);
            line-height: 1.6;
            transition: var(--transition);
            padding-top: 60px;
            padding-bottom: 80px;
        }

        /* App Header */
        .app-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: var(--bg-primary, #ffffff);
            border-bottom: 1px solid var(--border-color);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 1000;
            backdrop-filter: blur(10px);
            background: rgba(var(--bg-primary-rgb, 255, 255, 255), 0.95);
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
            background: rgba(var(--bg-primary-rgb, 255, 255, 255), 0.95);
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
            background: rgba(var(--primary-color-rgb, 233, 30, 99), 0.1);
        }

        .nav-item:hover {
            color: var(--primary-color);
        }

        /* Hero Section */
        .hero-section {
            padding: 24px 20px;
            background: var(--gradient-primary);
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
            margin-top: 0;
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

        /* Main Content */
        .content-container {
            padding: 20px 16px;
        }

        /* Progress Steps */
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
            padding: 0 10px;
        }

        .progress-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--border-color);
            z-index: 1;
        }

        .progress-bar {
            position: absolute;
            top: 20px;
            left: 0;
            height: 3px;
            background: var(--primary-color);
            z-index: 2;
            transition: width 0.5s ease;
            border-radius: 2px;
        }

        .step {
            position: relative;
            z-index: 3;
            text-align: center;
            width: 100%;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: 600;
            color: var(--text-secondary);
            transition: var(--transition);
            position: relative;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border: 2px solid transparent;
        }

        .step.active .step-circle {
            background: var(--primary-color);
            color: white;
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(233, 30, 99, 0.3);
            border-color: #fff;
        }

        .step.completed .step-circle {
            background: var(--success-color);
            color: white;
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
            border-color: #fff;
        }

        .step.completed .step-circle::after {
            content: '✓';
            font-size: 18px;
        }

        .step-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            transition: var(--transition);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .step.active .step-label {
            color: var(--primary-color);
        }

        /* Form Sections */
        .form-section {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .form-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Form Cards */
        .form-card {
            background: var(--bg-secondary, #f8f9fa);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .form-card:hover {
            box-shadow: var(--elevated-shadow);
        }

        .form-card-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(233, 30, 99, 0.1);
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-label i {
            color: var(--primary-color);
            font-size: 1rem;
        }

        .required::after {
            content: " *";
            color: var(--danger-color);
        }

        .form-input,
        .form-select {
            width: 100%;
            padding: 14px 16px;
            background: var(--bg-primary);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            color: var(--text-primary);
            transition: var(--transition);
            font-family: inherit;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
        }

        textarea.form-input {
            min-height: 120px;
            resize: vertical;
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 12px;
        }

        /* Form Navigation */
        .form-navigation {
            display: flex;
            justify-content: center;
            margin-top: 30px;
            gap: 15px;
        }

        .btn {
            padding: 16px 24px;
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
            width: 100%;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(233, 30, 99, 0.3);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.3);
        }

        /* Toast Notification */
        .toast {
            position: fixed;
            bottom: 100px;
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

        .toast.info {
            background: var(--warning-color);
        }

        /* Loading Spinner */
        .spinner {
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

        /* Alert Messages */
        .alert {
            padding: 15px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            font-weight: 500;
            text-align: center;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.9);
            color: white;
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.9);
            color: white;
        }

        /* Additional Photos */
        .additional-photo-item {
            margin-bottom: 15px;
        }

        .photo-action-btn {
            width: 100%;
            padding: 12px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: 2px dashed var(--border-color);
            background: var(--bg-primary);
            color: var(--text-secondary);
        }

        .photo-action-btn:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        /* Education Entry */
        .education-entry {
            background: var(--bg-primary);
            border-radius: var(--radius-md);
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid var(--border-color);
        }

        .remove-btn {
            background: var(--danger-color);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            padding: 8px 16px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .remove-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        /* Responsive Design */
        @media (min-width: 768px) {
            body {
                max-width: 480px;
                margin: 0 auto;
                border-left: 1px solid var(--border-color);
                border-right: 1px solid var(--border-color);
            }
            
            .nav-item {
                font-size: 0.75rem;
            }
            
            .form-navigation {
                justify-content: space-between;
            }
            
            .btn {
                width: auto;
                min-width: 140px;
            }
        }

        @media (max-width: 380px) {
            .hero-title {
                font-size: 1.5rem;
            }
            
            .step-circle {
                width: 36px;
                height: 36px;
                font-size: 0.9rem;
            }
            
            .step-label {
                font-size: 0.7rem;
            }
            
            .nav-item {
                font-size: 0.65rem;
                padding: 6px 4px;
                min-width: 50px;
            }
            
            .nav-item i {
                font-size: 1rem;
            }
        }

        /* Row Grid for Forms */
        .form-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }

        @media (min-width: 576px) {
            .form-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Section Title */
        .section-title {
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(233, 30, 99, 0.1);
        }

        /* Helper Text */
        .form-text {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 4px;
            display: block;
        }

        /* Tamil Text */
        .tamil-text {
            font-family: 'Noto Sans Tamil', sans-serif;
            font-weight: 400;
            font-size: 0.9em;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <!-- Theme Variables Script -->
    <script>
        document.documentElement.style.setProperty('--bg-primary-rgb', '255, 255, 255');
        document.documentElement.style.setProperty('--primary-color-rgb', '233, 30, 99');
    </script>

    <!-- App Header -->
    <header class="app-header">
        <div class="header-left">
            <button class="back-button" onclick="goBack()">
                <i class="fas fa-arrow-left"></i>
            </button>
            <h1 class="header-title">Become a Member</h1>
        </div>
        <button class="theme-toggle" id="themeToggle">
            <i class="fas fa-moon"></i>
        </button>
    </header>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-icon">
            <i class="fas fa-heart"></i>
        </div>
        <h1 class="hero-title">Complete Your Profile</h1>
        <p class="hero-subtitle">Join our community to find your perfect life partner</p>
    </section>

    <!-- Main Content -->
    <main class="content-container">
        <?php if ($existing_member): ?>
            <!-- Existing Member Message -->
            <div style="text-align: center; margin-top: 40px; padding: 30px; border-radius: 12px; background: rgba(76, 175, 80, 0.1); border: 2px solid var(--success-color);">
                <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 15px; color: var(--success-color);"></i>
                <h3 style="margin: 15px 0; color: var(--text-primary);">Member Details Already Submitted</h3>
                <p style="margin: 10px 0; font-size: 0.95rem; color: var(--text-secondary);">
                    Your profile has been registered. You can now edit your information or proceed to browse members.
                </p>
                <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                    <a href="profile.php" class="btn btn-primary" style="text-decoration: none; width: auto; min-width: 140px;">
                        <i class="fas fa-edit"></i> Edit Profile
                    </a>
                    <a href="mem.php" class="btn btn-success" style="text-decoration: none; width: auto; min-width: 140px;">
                        <i class="fas fa-users"></i> Browse Members
                    </a>
                </div>
            </div>
        <?php else: ?>
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                </div>
            <?php endif; ?>

        <!-- Progress Steps -->
        <div class="progress-steps">
            <div class="progress-bar" id="progressBar"></div>
            <div class="step active" data-step="1">
                <div class="step-circle">1</div>
                <div class="step-label">Personal</div>
            </div>
            <div class="step" data-step="2">
                <div class="step-circle">2</div>
                <div class="step-label">Physical</div>
            </div>
            <div class="step" data-step="3">
                <div class="step-circle">3</div>
                <div class="step-label">Education</div>
            </div>
            <div class="step" data-step="4">
                <div class="step-circle">4</div>
                <div class="step-label">Family</div>
            </div>
            <div class="step" data-step="5">
                <div class="step-circle">5</div>
                <div class="step-label">Partner</div>
            </div>
            <div class="step" data-step="6">
                <div class="step-circle">6</div>
                <div class="step-label">Horoscope</div>
            </div>
        </div>

        <!-- Member Details Form -->
        <section id="member-details" class="form-section active">
            <h2 class="section-title">Personal Information</h2>
            <form id="memberDetailsForm" enctype="multipart/form-data">
                <div class="form-card">
                    <h3 class="form-card-title"><i class="fas fa-user"></i> Basic Details</h3>
                    
                    <div class="form-group">
                        <label class="form-label required"><i class="fas fa-signature"></i> Full Name</label>
                        <input type="text" class="form-input" name="name" placeholder="Enter your full name" required>
                        <span class="form-text tamil-text">முழு பெயர்</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required"><i class="fas fa-search"></i> Looking For</label>
                            <select class="form-select" name="looking_for" required>
                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                            <span class="form-text tamil-text">தேடுகிறவர் பாலினம்</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label required"><i class="fas fa-calendar"></i> Date of Birth</label>
                            <input type="date" class="form-input" name="dob" required>
                            <span class="form-text tamil-text">பிறந்த தேதி</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required"><i class="fas fa-pray"></i> Religion</label>
                            <select class="form-select" name="religion" required>
                                <option value="">Select</option>
                                <option value="Hindu">Hindu</option>
                                <option value="Christian">Christian</option>
                                <option value="Islam">Islam</option>
                                <option value="Buddhist">Buddhist</option>
                                <option value="Other">Other</option>
                            </select>
                            <span class="form-text tamil-text">மதம்</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label required"><i class="fas fa-venus-mars"></i> Gender</label>
                            <select class="form-select" name="gender" required>
                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                            <span class="form-text tamil-text">பாலினம்</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label required"><i class="fas fa-image"></i> Profile Photo</label>
                        <input type="file" class="form-input" name="photo" accept="image/*" required>
                        <span class="form-text">JPG, PNG up to 2MB</span>
                        <span class="form-text tamil-text">புகைப்படம்</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-images"></i> Additional Photos</label>
                        <div id="additional-photos-container">
                            <div class="additional-photo-item">
                                <input type="file" class="form-input" name="additional_photos[]" accept="image/*">
                            </div>
                        </div>
                        <button type="button" class="photo-action-btn" onclick="addPhotoField()">
                            <i class="fas fa-plus"></i> Add More Photos
                        </button>
                        <span class="form-text">Up to 5 photos, 2MB each</span>
                        <span class="form-text tamil-text">மேலதிக புகைப்படங்கள்</span>
                    </div>
                </div>

                <div class="form-card">
                    <h3 class="form-card-title"><i class="fas fa-info-circle"></i> Additional Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required"><i class="fas fa-heart"></i> Marital Status</label>
                            <select class="form-select" name="marital_status" required>
                                <option value="">Select</option>
                                <option value="Single">Single</option>
                                <option value="Divorced">Divorced</option>
                                <option value="Widowed">Widowed</option>
                            </select>
                            <span class="form-text tamil-text">திருமண நிலை</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label required"><i class="fas fa-language"></i> Language</label>
                            <select class="form-select" name="language" required>
                                <option value="">Select</option>
                                <option value="Tamil">Tamil</option>
                                <option value="English">English</option>
                                <option value="Sinhala">Sinhala</option>
                            </select>
                            <span class="form-text tamil-text">மொழி</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required"><i class="fas fa-briefcase"></i> Profession</label>
                            <input type="text" class="form-input" name="profession" placeholder="e.g., Teacher, Engineer" required>
                            <span class="form-text tamil-text">தொழில்</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label required"><i class="fas fa-globe"></i> Location</label>
                            <input type="text" class="form-input" name="country" placeholder="e.g., Jaffna, Sri Lanka" required>
                            <span class="form-text tamil-text">முகவரி</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label required"><i class="fas fa-phone"></i> Phone Number</label>
                        <input type="tel" class="form-input" name="phone" placeholder="Enter phone number" required>
                        <span class="form-text tamil-text">தொலைபேசி எண்</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-smoking"></i> Smoking Habit</label>
                            <select class="form-select" name="smoking">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                                <option value="Occasionally">Occasionally</option>
                            </select>
                            <span class="form-text tamil-text">புகைபிடிக்கும் பழக்கம்</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-wine-glass"></i> Drinking Habit</label>
                            <select class="form-select" name="drinking">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                                <option value="Occasionally">Occasionally</option>
                            </select>
                            <span class="form-text tamil-text">மது அருந்தும் பழக்கம்</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-money-bill-wave"></i> Income</label>
                        <input type="text" class="form-input" name="income" placeholder="Enter your income">
                        <span class="form-text tamil-text">வருமானம்</span>
                    </div>
                </div>

                <div class="form-navigation">
                    <button type="button" class="btn btn-primary" onclick="validateAndNext('memberDetailsForm', 2, this)">
                        Next <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </form>
        </section>

        <!-- Physical Information Form -->
        <section id="physical-info" class="form-section">
            <h2 class="section-title">Physical Information</h2>
            <form id="physicalInfoForm">
                <div class="form-card">
                    <h3 class="form-card-title"><i class="fas fa-user-circle"></i> Physical Attributes</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required"><i class="fas fa-palette"></i> Complexion</label>
                            <select class="form-select" name="complexion" required>
                                <option value="">Select</option>
                                <option value="Fair">Fair</option>
                                <option value="Wheatish">Wheatish</option>
                                <option value="Dark">Dark</option>
                            </select>
                            <span class="form-text tamil-text">தோல் நிறம்</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label required"><i class="fas fa-ruler-vertical"></i> Height (cm)</label>
                            <input type="number" class="form-input" name="height" placeholder="e.g., 170" required>
                            <span class="form-text tamil-text">உயரம்</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required"><i class="fas fa-weight"></i> Weight (kg)</label>
                            <input type="number" class="form-input" name="weight" placeholder="e.g., 65" required>
                            <span class="form-text tamil-text">எடை</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label required"><i class="fas fa-tint"></i> Blood Group</label>
                            <select class="form-select" name="blood_group" required>
                                <option value="">Select</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                            </select>
                            <span class="form-text tamil-text">இரத்த வகை</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required"><i class="fas fa-eye"></i> Eye Color</label>
                            <input type="text" class="form-input" name="eye_color" placeholder="e.g., Brown" required>
                            <span class="form-text tamil-text">கண் நிறம்</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label required"><i class="fas fa-cut"></i> Hair Color</label>
                            <input type="text" class="form-input" name="hair_color" placeholder="e.g., Black" required>
                            <span class="form-text tamil-text">முடி நிறம்</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label required"><i class="fas fa-wheelchair"></i> Disability</label>
                        <select class="form-select" name="disability" required>
                            <option value="No">No</option>
                            <option value="Yes">Yes</option>
                        </select>
                        <span class="form-text tamil-text">மாற்றுத்திறன்</span>
                    </div>
                </div>

                <div class="form-navigation">
                    <button type="button" class="btn btn-primary" onclick="validateAndNext('physicalInfoForm', 3, this)">
                        Next <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </form>
        </section>

        <!-- Education Information Form -->
        <section id="education-info" class="form-section">
            <h2 class="section-title">Education Information</h2>
            <form id="educationInfoForm">
                <div class="form-card">
                    <h3 class="form-card-title"><i class="fas fa-graduation-cap"></i> Educational Background</h3>
                    
                    <div id="education-container">
                        <div class="education-entry">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label"><i class="fas fa-university"></i> Institute</label>
                                    <input type="text" class="form-input" name="institute[]" placeholder="Institute Name">
                                    <span class="form-text tamil-text">நிறுவனம்</span>
                                </div>

                                <div class="form-group">
                                    <label class="form-label"><i class="fas fa-award"></i> Degree</label>
                                    <input type="text" class="form-input" name="degree[]" placeholder="Degree Name">
                                    <span class="form-text tamil-text">பட்டம்</span>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label"><i class="fas fa-book"></i> Field of Study</label>
                                    <input type="text" class="form-input" name="field[]" placeholder="Field of Study">
                                    <span class="form-text tamil-text">ஆய்வுக் களம்</span>
                                </div>

                                <div class="form-group">
                                    <label class="form-label"><i class="fas fa-id-card"></i> Registration Number</label>
                                    <input type="text" class="form-input" name="regnum[]" placeholder="Registration Number">
                                    <span class="form-text tamil-text">பதிவு எண்</span>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label"><i class="fas fa-calendar-alt"></i> Start Year</label>
                                    <input type="number" class="form-input" name="startyear[]" placeholder="Start Year">
                                    <span class="form-text tamil-text">தொடக்க ஆண்டு</span>
                                </div>

                                <div class="form-group">
                                    <label class="form-label"><i class="fas fa-calendar-check"></i> End Year</label>
                                    <input type="number" class="form-input" name="endyear[]" placeholder="End Year">
                                    <span class="form-text tamil-text">இறுதி ஆண்டு</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <button type="button" class="photo-action-btn" onclick="addEducationField()">
                            <i class="fas fa-plus"></i> Add Another Education
                        </button>
                    </div>
                </div>

                <div class="form-navigation">
                    <button type="button" class="btn btn-primary" onclick="validateAndNext('educationInfoForm', 4, this)">
                        Next <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </form>
        </section>

        <!-- Family Information Form -->
        <section id="family-info" class="form-section">
            <h2 class="section-title">Family Information</h2>
            <form id="familyInfoForm">
                <div class="form-card">
                    <h3 class="form-card-title"><i class="fas fa-users"></i> Family Details</h3>
                    
                    <div class="form-group">
                        <label class="form-label required"><i class="fas fa-male"></i> Father's Name</label>
                        <input type="text" class="form-input" name="father_name" placeholder="Father's name" required>
                        <span class="form-text tamil-text">தந்தையின் பெயர்</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-briefcase"></i> Father's Profession</label>
                            <input type="text" class="form-input" name="father_profession" placeholder="Father's profession">
                            <span class="form-text tamil-text">தந்தையின் தொழில்</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-phone"></i> Father's Contact</label>
                            <input type="text" class="form-input" name="father_contact" placeholder="Phone number">
                            <span class="form-text tamil-text">தந்தையின் தொடர்பு எண்</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label required"><i class="fas fa-female"></i> Mother's Name</label>
                        <input type="text" class="form-input" name="mother_name" placeholder="Mother's name" required>
                        <span class="form-text tamil-text">தாயின் பெயர்</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-briefcase"></i> Mother's Profession</label>
                            <input type="text" class="form-input" name="mother_profession" placeholder="Mother's profession">
                            <span class="form-text tamil-text">தாயின் தொழில்</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-phone"></i> Mother's Contact</label>
                            <input type="text" class="form-input" name="mother_contact" placeholder="Phone number">
                            <span class="form-text tamil-text">அம்மாவின் தொடர்பு எண்</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-male"></i> Brothers</label>
                            <input type="number" class="form-input" name="brothers" placeholder="Number of brothers">
                            <span class="form-text tamil-text">மொத்த சகோதரர்கள்</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-female"></i> Sisters</label>
                            <input type="number" class="form-input" name="sisters" placeholder="Number of sisters">
                            <span class="form-text tamil-text">மொத்த சகோதரிகள்</span>
                        </div>
                    </div>
                </div>

                <div class="form-navigation">
                    <button type="button" class="btn btn-primary" onclick="validateAndNext('familyInfoForm', 5, this)">
                        Next <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </form>
        </section>

        <!-- Partner Expectations Form -->
        <section id="partner-info" class="form-section">
            <h2 class="section-title">Partner Expectations</h2>
            <form id="partnerInfoForm">
                <div class="form-card">
                    <h3 class="form-card-title"><i class="fas fa-heart"></i> Partner Preferences</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required"><i class="fas fa-globe"></i> Preferred Country</label>
                            <input type="text" class="form-input" name="partner_country" placeholder="e.g., Sri Lanka" required>
                            <span class="form-text tamil-text">விருப்பமான நாடு</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label required"><i class="fas fa-pray"></i> Religion</label>
                            <select class="form-select" name="partner_religion" required>
                                <option value="">Select</option>
                                <option value="Hindu">Hindu</option>
                                <option value="Christian">Christian</option>
                                <option value="Islam">Islam</option>
                                <option value="Buddhist">Buddhist</option>
                                <option value="Other">Other</option>
                            </select>
                            <span class="form-text tamil-text">மதம்</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required"><i class="fas fa-birthday-cake"></i> Minimum Age</label>
                            <input type="number" class="form-input" name="min_age" placeholder="e.g., 25" required>
                            <span class="form-text tamil-text">குறைந்தபட்ச வயது</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label required"><i class="fas fa-birthday-cake"></i> Maximum Age</label>
                            <input type="number" class="form-input" name="max_age" placeholder="e.g., 35" required>
                            <span class="form-text tamil-text">அதிகபட்ச வயது</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required"><i class="fas fa-ruler-vertical"></i> Minimum Height (cm)</label>
                            <input type="number" class="form-input" name="min_height" placeholder="e.g., 150" required>
                            <span class="form-text tamil-text">குறைந்தபட்ச உயரம்</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label required"><i class="fas fa-ruler-vertical"></i> Maximum Height (cm)</label>
                            <input type="number" class="form-input" name="max_height" placeholder="e.g., 180" required>
                            <span class="form-text tamil-text">அதிகபட்ச உயரம்</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required"><i class="fas fa-smoking"></i> Smoking Habit</label>
                            <select class="form-select" name="partner_smoking" required>
                                <option value="">Select</option>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                                <option value="Occasionally">Occasionally</option>
                            </select>
                            <span class="form-text tamil-text">புகைபிடிக்கும் பழக்கம்</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label required"><i class="fas fa-wine-glass"></i> Drinking Habit</label>
                            <select class="form-select" name="partner_drinking" required>
                                <option value="">Select</option>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                                <option value="Occasionally">Occasionally</option>
                            </select>
                            <span class="form-text tamil-text">குடிப்பழக்கம்</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label required"><i class="fas fa-heart"></i> Marital Status</label>
                        <select class="form-select" name="partner_marital_status" required>
                            <option value="">Select</option>
                            <option value="Never Married">Never Married</option>
                            <option value="Divorced">Divorced</option>
                            <option value="Widowed">Widowed</option>
                            <option value="Separated">Separated</option>
                        </select>
                        <span class="form-text tamil-text">திருமண நிலை</span>
                    </div>
                </div>

                <div class="form-navigation">
                    <button type="button" class="btn btn-primary" onclick="validateAndNext('partnerInfoForm', 6, this)">
                        Next <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </form>
        </section>

        <!-- Horoscope Information Form -->
        <section id="horoscope-info" class="form-section">
            <h2 class="section-title">Horoscope Details</h2>
            <form id="horoscopeInfoForm" enctype="multipart/form-data">
                <div class="form-card">
                    <h3 class="form-card-title"><i class="fas fa-star"></i> Astrological Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-calendar"></i> Birth Date</label>
                            <input type="date" class="form-input" name="birth_date">
                            <span class="form-text tamil-text">பிறந்த தேதி</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-clock"></i> Birth Time</label>
                            <input type="time" class="form-input" name="birth_time">
                            <span class="form-text tamil-text">பிறந்த நேரம்</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-sun"></i> Zodiac Sign</label>
                            <input type="text" class="form-input" name="zodiac" placeholder="e.g., Leo">
                            <span class="form-text tamil-text">இராசி அடையாளம்</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-moon"></i> Nakshatra</label>
                            <select class="form-select" name="nakshatra">
                                <option value="">Select Nakshatra</option>
                                <option value="1001">அஸ்வினி</option>
                                <option value="1002">பரணி</option>
                                <!-- Add more nakshatra options as needed -->
                            </select>
                            <span class="form-text tamil-text">நட்சத்திரம்</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-balance-scale"></i> Karmic Debt</label>
                        <input type="text" class="form-input" name="karmic_debt" placeholder="Yes/No or Specific Value">
                        <span class="form-text tamil-text">கர்மக் கடன்</span>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-image"></i> Planet Position Image</label>
                            <input type="file" class="form-input" name="planet_image" accept="image/*">
                            <span class="form-text tamil-text">கிரக நிலை படம்</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-image"></i> Navamsha Position Image</label>
                            <input type="file" class="form-input" name="navamsha_image" accept="image/*">
                            <span class="form-text tamil-text">நவாம்ச நிலை படம்</span>
                        </div>
                    </div>
                </div>

                <div class="form-navigation">
                    <button type="button" class="btn btn-success" onclick="finishRegistration(this)">
                        <i class="fas fa-check"></i> Complete Registration
                    </button>
                </div>
            </form>
        </section>
        <?php endif; ?> <!-- End: Only show form if NOT existing member -->
    </main>

    <!-- Toast Notification -->
    <div class="toast" id="toast"></div>

     <!-- Bottom Navigation -->
        <nav class="bottom-nav">
            <a href="index.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="members.php" class="nav-item active">
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
            if (currentStep > 1) {
                goToStep(currentStep - 1);
            } else {
                window.history.back();
            }
        }
        
        // Step Management
        let currentStep = 1;
        const totalSteps = 6;
        
        function updateProgressBar() {
            const progressBar = document.getElementById('progressBar');
            const progressPercentage = ((currentStep - 1) / (totalSteps - 1)) * 100;
            progressBar.style.width = `${progressPercentage}%`;
        }
        
        function updateStepIndicators() {
            document.querySelectorAll('.step').forEach((step, index) => {
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
                
                // Scroll to top
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }
        
        function goToStep(stepNumber) {
            showStep(stepNumber);
        }
        
        // Photo Field Management
        function addPhotoField() {
            const container = document.getElementById('additional-photos-container');
            const photoItems = container.querySelectorAll('.additional-photo-item');
            
            if (photoItems.length >= 5) {
                showToast('Maximum 5 additional photos allowed', 'info');
                return;
            }
            
            const newPhotoItem = document.createElement('div');
            newPhotoItem.className = 'additional-photo-item';
            newPhotoItem.innerHTML = `
                <input type="file" class="form-input" name="additional_photos[]" accept="image/*">
            `;
            container.appendChild(newPhotoItem);
        }
        
        // Education Field Management
        function addEducationField() {
            const container = document.getElementById('education-container');
            const newEntry = document.createElement('div');
            newEntry.className = 'education-entry';
            newEntry.innerHTML = `
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-university"></i> Institute</label>
                        <input type="text" class="form-input" name="institute[]" placeholder="Institute Name">
                        <span class="form-text tamil-text">நிறுவனம்</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-award"></i> Degree</label>
                        <input type="text" class="form-input" name="degree[]" placeholder="Degree Name">
                        <span class="form-text tamil-text">பட்டம்</span>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-book"></i> Field of Study</label>
                        <input type="text" class="form-input" name="field[]" placeholder="Field of Study">
                        <span class="form-text tamil-text">ஆய்வுக் களம்</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-id-card"></i> Registration Number</label>
                        <input type="text" class="form-input" name="regnum[]" placeholder="Registration Number">
                        <span class="form-text tamil-text">பதிவு எண்</span>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-calendar-alt"></i> Start Year</label>
                        <input type="number" class="form-input" name="startyear[]" placeholder="Start Year">
                        <span class="form-text tamil-text">தொடக்க ஆண்டு</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-calendar-check"></i> End Year</label>
                        <input type="number" class="form-input" name="endyear[]" placeholder="End Year">
                        <span class="form-text tamil-text">இறுதி ஆண்டு</span>
                    </div>
                </div>
                <div class="text-right">
                    <button type="button" class="remove-btn" onclick="removeEducationField(this)">
                        <i class="fas fa-trash"></i> Remove
                    </button>
                </div>
            `;
            container.appendChild(newEntry);
        }
        
        function removeEducationField(button) {
            const container = document.getElementById('education-container');
            const educationEntries = container.querySelectorAll('.education-entry');
            
            if (educationEntries.length > 1) {
                button.closest('.education-entry').remove();
            } else {
                showToast('At least one education entry is required', 'info');
            }
        }
        
        // Form Validation and Submission
        function validateAndNext(formId, nextStep, button) {
            const form = document.getElementById(formId);
            
            // Lock button to prevent multiple clicks
            if (button.dataset.locked === 'true') {
                return false;
            }
            
            button.dataset.locked = 'true';
            const originalText = button.innerHTML;
            button.innerHTML = '<div class="spinner"></div> Saving...';
            button.disabled = true;
            
            // Validate required fields (except horoscope)
            if (formId !== 'horoscopeInfoForm') {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                let firstInvalidField = null;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.style.borderColor = 'var(--danger-color)';
                        if (!firstInvalidField) {
                            firstInvalidField = field;
                        }
                        isValid = false;
                    } else {
                        field.style.borderColor = '';
                    }
                });
                
                if (!isValid) {
                    showToast('Please fill in all required fields', 'error');
                    if (firstInvalidField) {
                        firstInvalidField.focus();
                    }
                    
                    // Restore button state
                    button.dataset.locked = 'false';
                    button.innerHTML = originalText;
                    button.disabled = false;
                    return false;
                }
            }
            
            // Submit form data
            const formData = new FormData(form);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Move to next step
                showStep(nextStep);
                showToast('Information saved successfully', 'success');
                
                // Restore button state
                button.dataset.locked = 'false';
                button.innerHTML = originalText;
                button.disabled = false;
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error saving data. Please try again.', 'error');
                
                // Restore button state
                button.dataset.locked = 'false';
                button.innerHTML = originalText;
                button.disabled = false;
            });
            
            return false;
        }
        
        function finishRegistration(button) {
            const form = document.getElementById('horoscopeInfoForm');
            
            // Lock button
            button.dataset.locked = 'true';
            const originalText = button.innerHTML;
            button.innerHTML = '<div class="spinner"></div> Completing...';
            button.disabled = true;
            
            // Submit form data
            const formData = new FormData(form);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                showToast('Registration completed successfully!', 'success');
                
                // Redirect to packages page after delay
                setTimeout(() => {
                    window.location.href = 'package.php';
                }, 1500);
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error completing registration. Please try again.', 'error');
                
                // Restore button state
                button.dataset.locked = 'false';
                button.innerHTML = originalText;
                button.disabled = false;
            });
            
            return false;
        }
        
        // Toast Notification Function
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast ' + type;
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 4000);
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', () => {
            updateStepIndicators();
            
            <?php if ($existing_member): ?>
                showToast('You have already submitted member details. You can now edit your profile.', 'info');
            <?php endif; ?>
        });
    </script>
</body>
</html>