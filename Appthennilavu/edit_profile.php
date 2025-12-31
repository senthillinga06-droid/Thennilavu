<?php
session_start();

$conn = new mysqli('localhost', 'thennilavu_matrimonial', 'OYVuiEKfS@FQ', 'thennilavu_thennilavu');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');   
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch existing data
$member = null; $physical = null; $education = []; $family = null; $partner = null; $horoscope = null;

$stmt = $conn->prepare("SELECT id, name, photo, looking_for, dob, religion, gender, marital_status, language, profession, country, phone, smoking, drinking, present_address, city, zip, permanent_address, permanent_city FROM members WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();

if ($member) {
    $member_id = $member['id'];
    
    $stmt = $conn->prepare("SELECT complexion, height_cm, weight_kg, blood_group, eye_color, hair_color, disability FROM physical_info WHERE member_id = ?");
    $stmt->bind_param('i', $member_id);
    $stmt->execute();
    $physical = $stmt->get_result()->fetch_assoc();
    
    $stmt = $conn->prepare("SELECT level, school_or_institute, stream_or_degree, field, reg_number, start_year, end_year FROM education WHERE member_id = ?");
    $stmt->bind_param('i', $member_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { 
        $education[] = $r; 
    }
    
    $stmt = $conn->prepare("SELECT father_name, father_profession, father_contact, mother_name, mother_profession, mother_contact, brothers_count, sisters_count FROM family WHERE member_id = ?");
    $stmt->bind_param('i', $member_id);
    $stmt->execute();
    $family = $stmt->get_result()->fetch_assoc();
    
    $stmt = $conn->prepare("SELECT preferred_country, min_age, max_age, min_height, max_height, marital_status, religion, smoking, drinking FROM partner_expectations WHERE member_id = ?");
    $stmt->bind_param('i', $member_id);
    $stmt->execute();
    $partner = $stmt->get_result()->fetch_assoc();
    
    $stmt = $conn->prepare("SELECT birth_date, birth_time, zodiac, nakshatra, karmic_debt, planet_image, navamsha_image FROM horoscope WHERE member_id = ?");
    $stmt->bind_param('i', $member_id);
    $stmt->execute();
    $horoscope = $stmt->get_result()->fetch_assoc();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        // Whitelist of editable fields - ignore any extra inputs
        $allowed = [
        'name','looking_for','dob','religion','gender','marital_status','language','profession','country','phone','smoking','drinking',
            // physical
            'complexion','height','weight','blood_group','eye_color','hair_color','disability',
            // education arrays
            'institute','degree','field','regnum','startyear','endyear',
            // family
            'father_name','father_profession','father_contact','mother_name','mother_profession','mother_contact','brothers','sisters',
            // partner
            'partner_country','partner_marital_status','partner_religion','partner_smoking','partner_drinking','min_age','max_age','min_height','max_height',
            // horoscope
            'birth_date','birth_time','zodiac','nakshatra','karmic_debt'
        ];

        // Sanitize inputs: only keep allowed keys
        $input = [];
        foreach ($allowed as $key) {
            if (isset($_POST[$key])) $input[$key] = is_array($_POST[$key]) ? $_POST[$key] : trim($_POST[$key]);
        }

        $conn->begin_transaction();

        $upload_dir = '/home10/thennilavu/public_html/';
        // Ensure uploads directory exists
        if (!is_dir($upload_dir . '/uploads')) {
            mkdir($upload_dir . '/uploads', 0755, true);
        }

        // Handle file uploads robustly
        $photo = $member['photo'] ?? '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['photo']['name']);
            $dest = $upload_dir . '/uploads/' . $safeName;
            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                throw new Exception('Failed to upload photo');
            }
            $photo = 'uploads/' . $safeName;
        }

        $planet_img = ($horoscope['planet_image'] ?? '');
        if (isset($_FILES['planet_image']) && $_FILES['planet_image']['error'] === UPLOAD_ERR_OK) {
            $safeName = time() . '_planet_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['planet_image']['name']);
            $dest = $upload_dir . '/uploads/' . $safeName;
            if (!move_uploaded_file($_FILES['planet_image']['tmp_name'], $dest)) {
                throw new Exception('Failed to upload planet image');
            }
            $planet_img = 'uploads/' . $safeName;
        }

        $navamsha_img = ($horoscope['navamsha_image'] ?? '');
        if (isset($_FILES['navamsha_image']) && $_FILES['navamsha_image']['error'] === UPLOAD_ERR_OK) {
            $safeName = time() . '_navamsha_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['navamsha_image']['name']);
            $dest = $upload_dir . '/uploads/' . $safeName;
            if (!move_uploaded_file($_FILES['navamsha_image']['tmp_name'], $dest)) {
                throw new Exception('Failed to upload navamsha image');
            }
            $navamsha_img = 'uploads/' . $safeName;
        }

        // Normalize boolean-like fields
        $smoking = ($input['smoking'] ?? 'No') ?: 'No';
        $drinking = ($input['drinking'] ?? 'No') ?: 'No';

        // Prepare member update - only fields we expect
        $name = $input['name'] ?? $member['name'] ?? '';
        $looking_for = $input['looking_for'] ?? $member['looking_for'] ?? '';
        $dob = $input['dob'] ?? $member['dob'] ?? null;
        $religion = $input['religion'] ?? $member['religion'] ?? '';
        $gender = $input['gender'] ?? $member['gender'] ?? '';
        $marital_status = $input['marital_status'] ?? $member['marital_status'] ?? '';
        $language = $input['language'] ?? $member['language'] ?? '';
        $profession = $input['profession'] ?? $member['profession'] ?? '';
        $country = $input['country'] ?? $member['country'] ?? '';
        $phone = $input['phone'] ?? $member['phone'] ?? '';
    // We no longer collect or update address/city/zip fields from the edit UI. The DB columns remain DEFAULT NULL.
    $stmt = $conn->prepare("UPDATE members SET name=?, photo=?, looking_for=?, dob=?, religion=?, gender=?, marital_status=?, language=?, profession=?, country=?, phone=?, smoking=?, drinking=? WHERE user_id=?");
    if (!$stmt) throw new Exception('Prepare failed (members): ' . $conn->error);
    // types: 13 strings (s) and 1 int (i)
    $stmt->bind_param('sssssssssssssi', $name, $photo, $looking_for, $dob, $religion, $gender, $marital_status, $language, $profession, $country, $phone, $smoking, $drinking, $user_id);
        $stmt->execute();

        // Physical info: coerce numeric values; if empty use 0 to avoid binding issues
        $complexion = $input['complexion'] ?? ($physical['complexion'] ?? '');
        $height = isset($input['height']) && $input['height'] !== '' ? (float)$input['height'] : 0.0;
        $weight = isset($input['weight']) && $input['weight'] !== '' ? (float)$input['weight'] : 0.0;
        $blood_group = $input['blood_group'] ?? ($physical['blood_group'] ?? '');
        $eye_color = $input['eye_color'] ?? ($physical['eye_color'] ?? '');
        $hair_color = $input['hair_color'] ?? ($physical['hair_color'] ?? '');
        $disability = $input['disability'] ?? ($physical['disability'] ?? '');

        if ($physical) {
            $stmt = $conn->prepare("UPDATE physical_info SET complexion=?, height_cm=?, weight_kg=?, blood_group=?, eye_color=?, hair_color=?, disability=? WHERE member_id=?");
            if (!$stmt) throw new Exception('Prepare failed (physical update): ' . $conn->error);
            $stmt->bind_param('sddssssi', $complexion, $height, $weight, $blood_group, $eye_color, $hair_color, $disability, $member_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO physical_info (member_id, complexion, height_cm, weight_kg, blood_group, eye_color, hair_color, disability) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) throw new Exception('Prepare failed (physical insert): ' . $conn->error);
            $stmt->bind_param('isddssss', $member_id, $complexion, $height, $weight, $blood_group, $eye_color, $hair_color, $disability);
        }
        $stmt->execute();

        // Education: delete and reinsert only when arrays provided
        if (isset($input['institute']) && is_array($input['institute'])) {
            $stmt = $conn->prepare("DELETE FROM education WHERE member_id = ?");
            if (!$stmt) throw new Exception('Prepare failed (education delete): ' . $conn->error);
            $stmt->bind_param('i', $member_id);
            $stmt->execute();

            $stmt = $conn->prepare("INSERT INTO education (member_id, level, school_or_institute, stream_or_degree, field, reg_number, start_year, end_year) VALUES (?, 'Higher', ?, ?, ?, ?, ?, ?)");
            if (!$stmt) throw new Exception('Prepare failed (education insert): ' . $conn->error);
            for ($i = 0; $i < count($input['institute']); $i++) {
                $institute = $input['institute'][$i] ?? '';
                if (trim($institute) === '') continue;
                $degree = $input['degree'][$i] ?? '';
                $field = $input['field'][$i] ?? '';
                $regnum = $input['regnum'][$i] ?? '';
                $start = !empty($input['startyear'][$i]) ? (int)$input['startyear'][$i] : 0;
                $end = !empty($input['endyear'][$i]) ? (int)$input['endyear'][$i] : 0;
                $stmt->bind_param('issssii', $member_id, $institute, $degree, $field, $regnum, $start, $end);
                $stmt->execute();
            }
        }

        // Family info
        if (!empty($input['father_name']) || !empty($input['mother_name'])) {
            $brothers = !empty($input['brothers']) ? (int)$input['brothers'] : 0;
            $sisters = !empty($input['sisters']) ? (int)$input['sisters'] : 0;
            if ($family) {
                $stmt = $conn->prepare("UPDATE family SET father_name=?, father_profession=?, father_contact=?, mother_name=?, mother_profession=?, mother_contact=?, brothers_count=?, sisters_count=? WHERE member_id=?");
                if (!$stmt) throw new Exception('Prepare failed (family update): ' . $conn->error);
                $f_name = $input['father_name'] ?? $family['father_name'];
                $f_prof = $input['father_profession'] ?? $family['father_profession'];
                $f_contact = $input['father_contact'] ?? $family['father_contact'];
                $m_name = $input['mother_name'] ?? $family['mother_name'];
                $m_prof = $input['mother_profession'] ?? $family['mother_profession'];
                $m_contact = $input['mother_contact'] ?? $family['mother_contact'];
                $stmt->bind_param('ssssssiii', $f_name, $f_prof, $f_contact, $m_name, $m_prof, $m_contact, $brothers, $sisters, $member_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO family (member_id, father_name, father_profession, father_contact, mother_name, mother_profession, mother_contact, brothers_count, sisters_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt) throw new Exception('Prepare failed (family insert): ' . $conn->error);
                $f_name = $input['father_name'] ?? '';
                $f_prof = $input['father_profession'] ?? '';
                $f_contact = $input['father_contact'] ?? '';
                $m_name = $input['mother_name'] ?? '';
                $m_prof = $input['mother_profession'] ?? '';
                $m_contact = $input['mother_contact'] ?? '';
                $stmt->bind_param('isssssiii', $member_id, $f_name, $f_prof, $f_contact, $m_name, $m_prof, $m_contact, $brothers, $sisters);
            }
            $stmt->execute();
        }

        // Partner expectations
        if (!empty($input['partner_country']) || !empty($input['partner_religion'])) {
            $min_age = !empty($input['min_age']) ? (int)$input['min_age'] : 0;
            $max_age = !empty($input['max_age']) ? (int)$input['max_age'] : 0;
            $min_height = !empty($input['min_height']) ? (int)$input['min_height'] : 0;
            $max_height = !empty($input['max_height']) ? (int)$input['max_height'] : 0;
            if ($partner) {
                $stmt = $conn->prepare("UPDATE partner_expectations SET preferred_country=?, min_age=?, max_age=?, min_height=?, max_height=?, marital_status=?, religion=?, smoking=?, drinking=? WHERE member_id=?");
                if (!$stmt) throw new Exception('Prepare failed (partner update): ' . $conn->error);
                $p_country = $input['partner_country'] ?? $partner['preferred_country'];
                $p_marital = $input['partner_marital_status'] ?? $partner['marital_status'];
                $p_religion = $input['partner_religion'] ?? $partner['religion'];
                $p_smoking = $input['partner_smoking'] ?? $partner['smoking'];
                $p_drinking = $input['partner_drinking'] ?? $partner['drinking'];
                $stmt->bind_param('siiiissssi', $p_country, $min_age, $max_age, $min_height, $max_height, $p_marital, $p_religion, $p_smoking, $p_drinking, $member_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO partner_expectations (member_id, preferred_country, min_age, max_age, min_height, max_height, marital_status, religion, smoking, drinking) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt) throw new Exception('Prepare failed (partner insert): ' . $conn->error);
                $p_country = $input['partner_country'] ?? '';
                $p_marital = $input['partner_marital_status'] ?? '';
                $p_religion = $input['partner_religion'] ?? '';
                $p_smoking = $input['partner_smoking'] ?? '';
                $p_drinking = $input['partner_drinking'] ?? '';
                $stmt->bind_param('isiiiissss', $member_id, $p_country, $min_age, $max_age, $min_height, $max_height, $p_marital, $p_religion, $p_smoking, $p_drinking);
            }
            $stmt->execute();
        }

        // Horoscope
        if (!empty($input['birth_date']) || !empty($input['zodiac']) || $planet_img || $navamsha_img) {
            if ($horoscope) {
                $stmt = $conn->prepare("UPDATE horoscope SET birth_date=?, birth_time=?, zodiac=?, nakshatra=?, karmic_debt=?, planet_image=?, navamsha_image=? WHERE member_id=?");
                if (!$stmt) throw new Exception('Prepare failed (horoscope update): ' . $conn->error);
                $b_date = $input['birth_date'] ?? $horoscope['birth_date'];
                $b_time = $input['birth_time'] ?? $horoscope['birth_time'];
                $b_zodiac = $input['zodiac'] ?? $horoscope['zodiac'];
                $b_nak = $input['nakshatra'] ?? $horoscope['nakshatra'];
                $b_karmic = $input['karmic_debt'] ?? $horoscope['karmic_debt'];
                $stmt->bind_param('sssssssi', $b_date, $b_time, $b_zodiac, $b_nak, $b_karmic, $planet_img, $navamsha_img, $member_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO horoscope (member_id, birth_date, birth_time, zodiac, nakshatra, karmic_debt, planet_image, navamsha_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt) throw new Exception('Prepare failed (horoscope insert): ' . $conn->error);
                $b_date = $input['birth_date'] ?? null;
                $b_time = $input['birth_time'] ?? null;
                $b_zodiac = $input['zodiac'] ?? null;
                $b_nak = $input['nakshatra'] ?? null;
                $b_karmic = $input['karmic_debt'] ?? null;
                $stmt->bind_param('isssssss', $member_id, $b_date, $b_time, $b_zodiac, $b_nak, $b_karmic, $planet_img, $navamsha_img);
            }
            $stmt->execute();
        }

        $conn->commit();
        $success_message = "Profile updated successfully!";
        header('Location: profile.php');
        exit;
    } catch (Exception $e) {
        if ($conn->errno) $conn->rollback();
        $error_message = 'Update failed: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Thennilavu Matrimony</title>
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

        /* Content Container */
        .content-container {
            padding: 20px 16px;
        }

        /* Form Container */
        .form-container {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }

        .form-header {
            margin-bottom: 25px;
            text-align: center;
        }

        .form-icon {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--primary-color);
        }

        .form-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .form-subtitle {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        /* Form Sections */
        .form-section {
            background: var(--bg-primary);
            border-radius: var(--radius-md);
            padding: 20px;
            margin-bottom: 16px;
            border: 1px solid var(--border-color);
            box-shadow: var(--card-shadow);
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Form Elements */
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

        .form-input {
            width: 100%;
            padding: 12px 14px;
            background: var(--bg-secondary);
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

        .form-select {
            width: 100%;
            padding: 12px 14px;
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            color: var(--text-primary);
            transition: var(--transition);
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
        }

        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 20px;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            width: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(233, 30, 99, 0.3);
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            width: 100%;
        }

        .btn-secondary:hover {
            background: var(--bg-tertiary);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        /* Education Entry */
        .education-entry {
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            padding: 16px;
            margin-bottom: 12px;
            border: 1px solid var(--border-color);
            position: relative;
        }

        .remove-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--danger-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.8rem;
        }

        .add-btn {
            background: var(--success-color);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            padding: 12px 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            margin-top: 10px;
            cursor: pointer;
        }

        /* Image Preview */
        .image-preview-container {
            margin-top: 10px;
            text-align: center;
        }

        .image-preview {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid var(--primary-color);
            padding: 2px;
            background: white;
        }

        .current-image-label {
            display: block;
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 5px;
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

        /* PHP Messages */
        .php-message {
            background: var(--success-color);
            color: white;
            padding: 14px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
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
        }

        @media (max-width: 380px) {
            .form-title {
                font-size: 1.2rem;
            }
            
            .header-title {
                font-size: 1.1rem;
            }
            
            .section-title {
                font-size: 1rem;
            }
            
            .form-input, .form-select {
                padding: 10px 12px;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 320px) {
            .form-title {
                font-size: 1.1rem;
            }
            
            .header-title {
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
    </style>
</head>
<body>
    <!-- Theme Variables Script -->
    <script>
        document.documentElement.style.setProperty('--bg-primary-rgb', '255, 255, 255');
        document.documentElement.style.setProperty('--primary-color-rgb', '233, 30, 99');
        
        // Update for dark mode
        if (document.documentElement.getAttribute('data-theme') === 'dark') {
            document.documentElement.style.setProperty('--bg-primary-rgb', '18, 18, 18');
        }
    </script>

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
                <h1 class="header-title">Edit Profile</h1>
            </div>
            <button class="theme-toggle" id="themeToggle">
                <i class="fas fa-moon"></i>
            </button>
        </header>

        <!-- Main Content -->
        <main class="content-container">
            <div class="form-container fade-in">
                <div class="form-header">
                    <div class="form-icon">
                        <i class="fas fa-user-edit"></i>
                    </div>
                    <h2 class="form-title">Edit Your Profile</h2>
                    <p class="form-subtitle">Update your information to find your perfect match</p>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <!-- Personal Information -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-user-circle"></i>
                            Personal Information
                        </h3>
                        
                        <div class="form-group">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-input" name="name" value="<?php echo htmlspecialchars($member['name'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Photo</label>
                            <input type="file" class="form-input" name="photo" accept="image/*">
                            <?php if ($member['photo']): ?>
                                <div class="image-preview-container">
                                    <img src="https://thennilavu.lk/<?php echo htmlspecialchars($member['photo']); ?>" class="image-preview" alt="Current Photo">
                                    <span class="current-image-label">Current Photo</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Looking For</label>
                            <select class="form-select" name="looking_for" required>
                                <option value="Male" <?php echo ($member['looking_for'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($member['looking_for'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" class="form-input" name="dob" value="<?php echo htmlspecialchars($member['dob'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Religion</label>
                            <input type="text" class="form-input" name="religion" value="<?php echo htmlspecialchars($member['religion'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="gender" required>
                                <option value="Male" <?php echo ($member['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($member['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Marital Status</label>
                            <select class="form-select" name="marital_status" required>
                                <option value="Single" <?php echo ($member['marital_status'] ?? '') == 'Single' ? 'selected' : ''; ?>>Single</option>
                                <option value="Divorced" <?php echo ($member['marital_status'] ?? '') == 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                <option value="Widowed" <?php echo ($member['marital_status'] ?? '') == 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Language</label>
                            <input type="text" class="form-input" name="language" value="<?php echo htmlspecialchars($member['language'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Profession</label>
                            <input type="text" class="form-input" name="profession" value="<?php echo htmlspecialchars($member['profession'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Country</label>
                            <input type="text" class="form-input" name="country" value="<?php echo htmlspecialchars($member['country'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-input" name="phone" value="<?php echo htmlspecialchars($member['phone'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <!-- Physical Information -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-user"></i>
                            Physical Information
                        </h3>
                        
                        <div class="form-group">
                            <label class="form-label">Complexion</label>
                            <select class="form-select" name="complexion" required>
                                <option value="Fair" <?php echo ($physical['complexion'] ?? '') == 'Fair' ? 'selected' : ''; ?>>Fair</option>
                                <option value="Wheatish" <?php echo ($physical['complexion'] ?? '') == 'Wheatish' ? 'selected' : ''; ?>>Wheatish</option>
                                <option value="Dark" <?php echo ($physical['complexion'] ?? '') == 'Dark' ? 'selected' : ''; ?>>Dark</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Height (cm)</label>
                            <input type="number" class="form-input" name="height" value="<?php echo htmlspecialchars($physical['height_cm'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Weight (kg)</label>
                            <input type="number" class="form-input" name="weight" value="<?php echo htmlspecialchars($physical['weight_kg'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Blood Group</label>
                            <select class="form-select" name="blood_group" required>
                                <option value="A+" <?php echo ($physical['blood_group'] ?? '') == 'A+' ? 'selected' : ''; ?>>A+</option>
                                <option value="A-" <?php echo ($physical['blood_group'] ?? '') == 'A-' ? 'selected' : ''; ?>>A-</option>
                                <option value="B+" <?php echo ($physical['blood_group'] ?? '') == 'B+' ? 'selected' : ''; ?>>B+</option>
                                <option value="B-" <?php echo ($physical['blood_group'] ?? '') == 'B-' ? 'selected' : ''; ?>>B-</option>
                                <option value="O+" <?php echo ($physical['blood_group'] ?? '') == 'O+' ? 'selected' : ''; ?>>O+</option>
                                <option value="O-" <?php echo ($physical['blood_group'] ?? '') == 'O-' ? 'selected' : ''; ?>>O-</option>
                                <option value="AB+" <?php echo ($physical['blood_group'] ?? '') == 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                <option value="AB-" <?php echo ($physical['blood_group'] ?? '') == 'AB-' ? 'selected' : ''; ?>>AB-</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Eye Color</label>
                            <input type="text" class="form-input" name="eye_color" value="<?php echo htmlspecialchars($physical['eye_color'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Hair Color</label>
                            <input type="text" class="form-input" name="hair_color" value="<?php echo htmlspecialchars($physical['hair_color'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Disability</label>
                            <select class="form-select" name="disability" required>
                                <option value="No" <?php echo ($physical['disability'] ?? '') == 'No' ? 'selected' : ''; ?>>No</option>
                                <option value="Yes" <?php echo ($physical['disability'] ?? '') == 'Yes' ? 'selected' : ''; ?>>Yes</option>
                            </select>
                        </div>
                    </div>

                    <!-- Education -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-graduation-cap"></i>
                            Education
                        </h3>
                        
                        <div id="education-container">
                            <?php if ($education): ?>
                                <?php foreach ($education as $index => $edu): ?>
                                    <div class="education-entry">
                                        <button type="button" class="remove-btn" onclick="this.parentElement.remove()">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Institute</label>
                                            <input type="text" class="form-input" name="institute[]" value="<?php echo htmlspecialchars($edu['school_or_institute']); ?>">
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">Degree</label>
                                            <input type="text" class="form-input" name="degree[]" value="<?php echo htmlspecialchars($edu['stream_or_degree']); ?>">
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">Field</label>
                                            <input type="text" class="form-input" name="field[]" value="<?php echo htmlspecialchars($edu['field']); ?>">
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">Registration Number</label>
                                            <input type="text" class="form-input" name="regnum[]" value="<?php echo htmlspecialchars($edu['reg_number']); ?>">
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">Start Year</label>
                                            <input type="number" class="form-input" name="startyear[]" value="<?php echo htmlspecialchars($edu['start_year']); ?>">
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">End Year</label>
                                            <input type="number" class="form-input" name="endyear[]" value="<?php echo htmlspecialchars($edu['end_year']); ?>">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="education-entry">
                                    <button type="button" class="remove-btn" onclick="this.parentElement.remove()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Institute</label>
                                        <input type="text" class="form-input" name="institute[]">
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">Degree</label>
                                        <input type="text" class="form-input" name="degree[]">
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">Field</label>
                                        <input type="text" class="form-input" name="field[]">
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">Registration Number</label>
                                        <input type="text" class="form-input" name="regnum[]">
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">Start Year</label>
                                        <input type="number" class="form-input" name="startyear[]">
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">End Year</label>
                                        <input type="number" class="form-input" name="endyear[]">
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <button type="button" class="add-btn" onclick="addEducation()">
                            <i class="fas fa-plus"></i>
                            Add Education
                        </button>
                    </div>

                    <!-- Family Information -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-users"></i>
                            Family Information
                        </h3>
                        
                        <div class="form-group">
                            <label class="form-label">Father's Name</label>
                            <input type="text" class="form-input" name="father_name" value="<?php echo htmlspecialchars($family['father_name'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Father's Profession</label>
                            <input type="text" class="form-input" name="father_profession" value="<?php echo htmlspecialchars($family['father_profession'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Father's Contact</label>
                            <input type="text" class="form-input" name="father_contact" value="<?php echo htmlspecialchars($family['father_contact'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Mother's Name</label>
                            <input type="text" class="form-input" name="mother_name" value="<?php echo htmlspecialchars($family['mother_name'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Mother's Profession</label>
                            <input type="text" class="form-input" name="mother_profession" value="<?php echo htmlspecialchars($family['mother_profession'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Mother's Contact</label>
                            <input type="text" class="form-input" name="mother_contact" value="<?php echo htmlspecialchars($family['mother_contact'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Brothers Count</label>
                            <input type="number" class="form-input" name="brothers" value="<?php echo htmlspecialchars($family['brothers_count'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Sisters Count</label>
                            <input type="number" class="form-input" name="sisters" value="<?php echo htmlspecialchars($family['sisters_count'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- Partner Expectations -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-heart"></i>
                            Partner Expectations
                        </h3>
                        
                        <div class="form-group">
                            <label class="form-label">Preferred Country</label>
                            <input type="text" class="form-input" name="partner_country" value="<?php echo htmlspecialchars($partner['preferred_country'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Partner Religion</label>
                            <input type="text" class="form-input" name="partner_religion" value="<?php echo htmlspecialchars($partner['religion'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Min Age</label>
                            <input type="number" class="form-input" name="min_age" value="<?php echo htmlspecialchars($partner['min_age'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Max Age</label>
                            <input type="number" class="form-input" name="max_age" value="<?php echo htmlspecialchars($partner['max_age'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Min Height (cm)</label>
                            <input type="number" class="form-input" name="min_height" value="<?php echo htmlspecialchars($partner['min_height'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Max Height (cm)</label>
                            <input type="number" class="form-input" name="max_height" value="<?php echo htmlspecialchars($partner['max_height'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Marital Status</label>
                            <select class="form-select" name="partner_marital_status" required>
                                <option value="Never Married" <?php echo ($partner['marital_status'] ?? '') == 'Never Married' ? 'selected' : ''; ?>>Never Married</option>
                                <option value="Divorced" <?php echo ($partner['marital_status'] ?? '') == 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                <option value="Widowed" <?php echo ($partner['marital_status'] ?? '') == 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Smoking</label>
                            <select class="form-select" name="partner_smoking" required>
                                <option value="No" <?php echo ($partner['smoking'] ?? '') == 'No' ? 'selected' : ''; ?>>No</option>
                                <option value="Yes" <?php echo ($partner['smoking'] ?? '') == 'Yes' ? 'selected' : ''; ?>>Yes</option>
                                <option value="Occasionally" <?php echo ($partner['smoking'] ?? '') == 'Occasionally' ? 'selected' : ''; ?>>Occasionally</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Drinking</label>
                            <select class="form-select" name="partner_drinking" required>
                                <option value="No" <?php echo ($partner['drinking'] ?? '') == 'No' ? 'selected' : ''; ?>>No</option>
                                <option value="Yes" <?php echo ($partner['drinking'] ?? '') == 'Yes' ? 'selected' : ''; ?>>Yes</option>
                                <option value="Occasionally" <?php echo ($partner['drinking'] ?? '') == 'Occasionally' ? 'selected' : ''; ?>>Occasionally</option>
                            </select>
                        </div>
                    </div>

                    <!-- Horoscope Information -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-star"></i>
                            Horoscope Information
                        </h3>
                        
                        <div class="form-group">
                            <label class="form-label">Birth Date</label>
                            <input type="date" class="form-input" name="birth_date" value="<?php echo htmlspecialchars($horoscope['birth_date'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Birth Time</label>
                            <input type="time" class="form-input" name="birth_time" value="<?php echo htmlspecialchars($horoscope['birth_time'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Zodiac Sign</label>
                            <input type="text" class="form-input" name="zodiac" value="<?php echo htmlspecialchars($horoscope['zodiac'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Nakshatra</label>
                            <input type="text" class="form-input" name="nakshatra" value="<?php echo htmlspecialchars($horoscope['nakshatra'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Karmic Debt</label>
                            <input type="text" class="form-input" name="karmic_debt" value="<?php echo htmlspecialchars($horoscope['karmic_debt'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Planet Chart Image</label>
                            <input type="file" class="form-input" name="planet_image" accept="image/*">
                            <?php if ($horoscope && !empty($horoscope['planet_image'])): ?>
                                <div class="image-preview-container">
                                    <img src="https://thennilavu.lk/<?php echo htmlspecialchars($horoscope['planet_image']); ?>" class="image-preview" alt="Current Planet Chart">
                                    <span class="current-image-label">Current Planet Chart</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Navamsha Chart Image</label>
                            <input type="file" class="form-input" name="navamsha_image" accept="image/*">
                            <?php if ($horoscope && !empty($horoscope['navamsha_image'])): ?>
                                <div class="image-preview-container">
                                    <img src="https://thennilavu.lk/<?php echo htmlspecialchars($horoscope['navamsha_image']); ?>" class="image-preview" alt="Current Navamsha Chart">
                                    <span class="current-image-label">Current Navamsha Chart</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div style="display: flex; gap: 12px; margin-top: 30px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check-circle"></i>
                            Update Profile
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='profile.php'">
                            <i class="fas fa-times-circle"></i>
                            Cancel
                        </button>
                    </div>
                </form>
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
            <a href="contact.php" class="nav-item">
                <i class="fas fa-envelope"></i>
                <span>Contact</span>
            </a>
            <a href="story.php" class="nav-item">
                <i class="fas fa-heart"></i>
                <span>Stories</span>
            </a>
            <a href="profile.php" class="nav-item active">
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
                window.location.href = 'profile.php';
            }
        }
        
        // Add Education Entry Function
        function addEducation() {
            const container = document.getElementById('education-container');
            const newEntry = document.createElement('div');
            newEntry.className = 'education-entry';
            newEntry.innerHTML = `
                <button type="button" class="remove-btn" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
                
                <div class="form-group">
                    <label class="form-label">Institute</label>
                    <input type="text" class="form-input" name="institute[]">
                </div>

                <div class="form-group">
                    <label class="form-label">Degree</label>
                    <input type="text" class="form-input" name="degree[]">
                </div>

                <div class="form-group">
                    <label class="form-label">Field</label>
                    <input type="text" class="form-input" name="field[]">
                </div>

                <div class="form-group">
                    <label class="form-label">Registration Number</label>
                    <input type="text" class="form-input" name="regnum[]">
                </div>

                <div class="form-group">
                    <label class="form-label">Start Year</label>
                    <input type="number" class="form-input" name="startyear[]">
                </div>

                <div class="form-group">
                    <label class="form-label">End Year</label>
                    <input type="number" class="form-input" name="endyear[]">
                </div>
            `;
            container.appendChild(newEntry);
            
            // Scroll to the new entry
            newEntry.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        
        // Initialize animations
        document.addEventListener('DOMContentLoaded', () => {
            const fadeElements = document.querySelectorAll('.fade-in');
            
            fadeElements.forEach(el => {
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
            });
            
            // Auto-hide PHP messages after 5 seconds
            const phpMessages = document.querySelectorAll('.php-message');
            phpMessages.forEach(toast => {
                setTimeout(() => {
                    toast.style.display = 'none';
                }, 5000);
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
        
        // File input preview
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        // Find the preview container
                        const container = input.parentElement.querySelector('.image-preview-container');
                        if (container) {
                            container.innerHTML = `
                                <img src="${e.target.result}" class="image-preview" alt="New Image">
                                <span class="current-image-label">New Image</span>
                            `;
                        }
                    }
                    reader.readAsDataURL(this.files[0]);
                }
            });
        });
    </script>
</body>
</html>