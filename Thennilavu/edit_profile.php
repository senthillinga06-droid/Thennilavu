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

        // Ensure uploads directory exists
        if (!is_dir(__DIR__ . '/uploads')) {
            mkdir(__DIR__ . '/uploads', 0755, true);
        }

        // Handle file uploads robustly
        $photo = $member['photo'] ?? '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['photo']['name']);
            $dest = __DIR__ . '/uploads/' . $safeName;
            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                throw new Exception('Failed to upload photo');
            }
            $photo = 'uploads/' . $safeName;
        }

        $planet_img = ($horoscope['planet_image'] ?? '');
        if (isset($_FILES['planet_image']) && $_FILES['planet_image']['error'] === UPLOAD_ERR_OK) {
            $safeName = time() . '_planet_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['planet_image']['name']);
            $dest = __DIR__ . '/uploads/' . $safeName;
            if (!move_uploaded_file($_FILES['planet_image']['tmp_name'], $dest)) {
                throw new Exception('Failed to upload planet image');
            }
            $planet_img = 'uploads/' . $safeName;
        }

        $navamsha_img = ($horoscope['navamsha_image'] ?? '');
        if (isset($_FILES['navamsha_image']) && $_FILES['navamsha_image']['error'] === UPLOAD_ERR_OK) {
            $safeName = time() . '_navamsha_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['navamsha_image']['name']);
            $dest = __DIR__ . '/uploads/' . $safeName;
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #e91e63;
            --primary-light: #fce4ec;
            --secondary-color: #f8f9fa;
            --accent-color: #ff4081;
            --text-dark: #333;
            --text-light: #6c757d;
            --border-color: #e0e0e0;
        }

        body {
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('img/holding-hand.jpg') center/cover no-repeat fixed;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-dark);
            min-height: 100vh;
            padding-top: 20px;
        }
        
        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 10;
            background: white;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(233, 30, 99, 0.2);
            transition: all 0.3s ease;
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .back-btn:hover {
            background: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(233, 30, 99, 0.3);
            color: var(--primary-color);
        }

        .edit-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .form-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary-color);
        }

        .section-title {
            color: var(--primary-color);
            font-weight: 700;
            border-bottom: 2px solid var(--primary-light);
            padding-bottom: 10px;
            margin-bottom: 25px;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 10px;
            font-size: 1.6rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid var(--border-color);
            padding: 12px 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(233, 30, 99, 0.1);
        }

        .btn-custom {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            font-weight: 600;
            padding: 12px 30px;
            border-radius: 30px;
            transition: all 0.3s ease;
        }

        .btn-custom:hover {
            background-color: #d81b60;
            border-color: #d81b60;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            color: white;
        }

        .btn-outline-custom {
            border-color: var(--primary-color);
            color: var(--primary-color);
            font-weight: 600;
            padding: 12px 30px;
            border-radius: 30px;
            transition: all 0.3s ease;
        }

        .btn-outline-custom:hover {
            background-color: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .education-entry {
            background: var(--primary-light);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 3px solid var(--primary-color);
        }

        .remove-btn {
            background: #dc3545;
            border: none;
            color: white;
            border-radius: 20px;
            padding: 8px 16px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .remove-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .add-btn {
            background: #28a745;
            border: none;
            color: white;
            border-radius: 20px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .add-btn:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .current-image {
            border: 2px solid var(--primary-color);
            border-radius: 8px;
            padding: 5px;
            background: white;
        }

        .alert-custom {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: none;
            border-left: 4px solid var(--primary-color);
        }

        .tab-quick-links {
            background: rgba(255,255,255,0.92);
            border-radius: 10px;
            padding: 16px;
            border-left: 4px solid var(--primary-color);
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: flex-start;
        }

        .quick-link-btn {
            background: transparent;
            border: 1px solid rgba(0,0,0,0.06);
            color: var(--primary-color);
            padding: 8px 14px;
            border-radius: 20px;
            font-weight: 600;
            transition: all 0.18s ease;
            text-decoration: none;
        }

        .quick-link-btn:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0,0,0,0.12);
        }

        @media (max-width: 768px) {
            .back-btn {
                top: 10px;
                left: 10px;
                width: 40px;
                height: 40px;
            }
            
            .form-section {
                padding: 20px;
                margin-bottom: 15px;
            }
            
            .section-title {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Back Button -->
    <a href="profile.php" class="back-btn" title="Back to Profile">
        <i class="bi bi-arrow-left-short" style="font-size: 1.5rem;"></i>
    </a>

    <div class="container py-4 edit-container">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-custom text-center mb-4"><?php echo $success_message; ?></div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-custom text-center mb-4"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="update_profile" value="1">
            
            <!-- Member Details -->
            <div class="form-section">
                <h2 class="section-title"><i class="bi bi-person-circle"></i> Personal Information</h2>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($member['name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Photo</label>
                        <input type="file" class="form-control" name="photo" accept="image/*">
                        <?php if ($member['photo']): ?>
                            <div class="mt-2">
                                <small class="text-muted">Current: </small>
                                <img src="<?php echo htmlspecialchars($member['photo']); ?>" width="50" height="50" class="rounded current-image">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Looking For</label>
                        <select class="form-select" name="looking_for" required>
                            <option value="Male" <?php echo ($member['looking_for'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($member['looking_for'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" class="form-control" name="dob" value="<?php echo htmlspecialchars($member['dob'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Religion</label>
                        <input type="text" class="form-control" name="religion" value="<?php echo htmlspecialchars($member['religion'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Gender</label>
                        <select class="form-select" name="gender" required>
                            <option value="Male" <?php echo ($member['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($member['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Marital Status</label>
                        <select class="form-select" name="marital_status" required>
                            <option value="Single" <?php echo ($member['marital_status'] ?? '') == 'Single' ? 'selected' : ''; ?>>Single</option>
                            <option value="Divorced" <?php echo ($member['marital_status'] ?? '') == 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                            <option value="Widowed" <?php echo ($member['marital_status'] ?? '') == 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Language</label>
                        <input type="text" class="form-control" name="language" value="<?php echo htmlspecialchars($member['language'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Profession</label>
                        <input type="text" class="form-control" name="profession" value="<?php echo htmlspecialchars($member['profession'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Country</label>
                        <input type="text" class="form-control" name="country" value="<?php echo htmlspecialchars($member['country'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($member['phone'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>

            <!-- Physical Info -->
            <div class="form-section">
                <h2 class="section-title"><i class="bi bi-person-bounding-box"></i> Physical Information</h2>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Complexion</label>
                        <select class="form-select" name="complexion" required>
                            <option value="Fair" <?php echo ($physical['complexion'] ?? '') == 'Fair' ? 'selected' : ''; ?>>Fair</option>
                            <option value="Wheatish" <?php echo ($physical['complexion'] ?? '') == 'Wheatish' ? 'selected' : ''; ?>>Wheatish</option>
                            <option value="Dark" <?php echo ($physical['complexion'] ?? '') == 'Dark' ? 'selected' : ''; ?>>Dark</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Height (cm)</label>
                        <input type="number" class="form-control" name="height" value="<?php echo htmlspecialchars($physical['height_cm'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Weight (kg)</label>
                        <input type="number" class="form-control" name="weight" value="<?php echo htmlspecialchars($physical['weight_kg'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
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
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Eye Color</label>
                        <input type="text" class="form-control" name="eye_color" value="<?php echo htmlspecialchars($physical['eye_color'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Hair Color</label>
                        <input type="text" class="form-control" name="hair_color" value="<?php echo htmlspecialchars($physical['hair_color'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Disability</label>
                        <select class="form-select" name="disability" required>
                            <option value="No" <?php echo ($physical['disability'] ?? '') == 'No' ? 'selected' : ''; ?>>No</option>
                            <option value="Yes" <?php echo ($physical['disability'] ?? '') == 'Yes' ? 'selected' : ''; ?>>Yes</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Education -->
            <div class="form-section">
                <h2 class="section-title"><i class="bi bi-book"></i> Education</h2>
                <div id="education-container">
                    <?php if ($education): ?>
                        <?php foreach ($education as $index => $edu): ?>
                            <div class="education-entry">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Institute</label>
                                        <input type="text" class="form-control" name="institute[]" value="<?php echo htmlspecialchars($edu['school_or_institute']); ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Degree</label>
                                        <input type="text" class="form-control" name="degree[]" value="<?php echo htmlspecialchars($edu['stream_or_degree']); ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Field</label>
                                        <input type="text" class="form-control" name="field[]" value="<?php echo htmlspecialchars($edu['field']); ?>">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Reg. Number</label>
                                        <input type="text" class="form-control" name="regnum[]" value="<?php echo htmlspecialchars($edu['reg_number']); ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Start Year</label>
                                        <input type="number" class="form-control" name="startyear[]" value="<?php echo htmlspecialchars($edu['start_year']); ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">End Year</label>
                                        <input type="number" class="form-control" name="endyear[]" value="<?php echo htmlspecialchars($edu['end_year']); ?>">
                                    </div>
                                </div>
                                <button type="button" class="remove-btn" onclick="this.parentElement.remove()">
                                    <i class="bi bi-trash me-1"></i>Remove
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="education-entry">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Institute</label>
                                    <input type="text" class="form-control" name="institute[]">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Degree</label>
                                    <input type="text" class="form-control" name="degree[]">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Field</label>
                                    <input type="text" class="form-control" name="field[]">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Reg. Number</label>
                                    <input type="text" class="form-control" name="regnum[]">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Start Year</label>
                                    <input type="number" class="form-control" name="startyear[]">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">End Year</label>
                                    <input type="number" class="form-control" name="endyear[]">
                                </div>
                            </div>
                            <button type="button" class="remove-btn" onclick="this.parentElement.remove()">
                                <i class="bi bi-trash me-1"></i>Remove
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="add-btn" onclick="addEducation()">
                    <i class="bi bi-plus-circle me-1"></i>Add Education
                </button>
            </div>

            <!-- Family Info -->
            <div class="form-section">
                <h2 class="section-title"><i class="bi bi-people-fill"></i> Family Information</h2>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Father's Name</label>
                        <input type="text" class="form-control" name="father_name" value="<?php echo htmlspecialchars($family['father_name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Father's Profession</label>
                        <input type="text" class="form-control" name="father_profession" value="<?php echo htmlspecialchars($family['father_profession'] ?? ''); ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Father's Contact</label>
                        <input type="text" class="form-control" name="father_contact" value="<?php echo htmlspecialchars($family['father_contact'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Mother's Name</label>
                        <input type="text" class="form-control" name="mother_name" value="<?php echo htmlspecialchars($family['mother_name'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Mother's Profession</label>
                        <input type="text" class="form-control" name="mother_profession" value="<?php echo htmlspecialchars($family['mother_profession'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Mother's Contact</label>
                        <input type="text" class="form-control" name="mother_contact" value="<?php echo htmlspecialchars($family['mother_contact'] ?? ''); ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Brothers Count</label>
                        <input type="number" class="form-control" name="brothers" value="<?php echo htmlspecialchars($family['brothers_count'] ?? ''); ?>" >
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Sisters Count</label>
                        <input type="number" class="form-control" name="sisters" value="<?php echo htmlspecialchars($family['sisters_count'] ?? ''); ?>" >
                    </div>
                </div>
            </div>

            <!-- Partner Expectations -->
            <div class="form-section">
                <h2 class="section-title"><i class="bi bi-heart"></i> Partner Expectations</h2>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Preferred Country</label>
                        <input type="text" class="form-control" name="partner_country" value="<?php echo htmlspecialchars($partner['preferred_country'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Partner Religion</label>
                        <input type="text" class="form-control" name="partner_religion" value="<?php echo htmlspecialchars($partner['religion'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Min Age</label>
                        <input type="number" class="form-control" name="min_age" value="<?php echo htmlspecialchars($partner['min_age'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Max Age</label>
                        <input type="number" class="form-control" name="max_age" value="<?php echo htmlspecialchars($partner['max_age'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Min Height (cm)</label>
                        <input type="number" class="form-control" name="min_height" value="<?php echo htmlspecialchars($partner['min_height'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Max Height (cm)</label>
                        <input type="number" class="form-control" name="max_height" value="<?php echo htmlspecialchars($partner['max_height'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Marital Status</label>
                        <select class="form-select" name="partner_marital_status" required>
                            <option value="Never Married" <?php echo ($partner['marital_status'] ?? '') == 'Never Married' ? 'selected' : ''; ?>>Never Married</option>
                            <option value="Divorced" <?php echo ($partner['marital_status'] ?? '') == 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                            <option value="Widowed" <?php echo ($partner['marital_status'] ?? '') == 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Smoking</label>
                        <select class="form-select" name="partner_smoking" required>
                            <option value="No" <?php echo ($partner['smoking'] ?? '') == 'No' ? 'selected' : ''; ?>>No</option>
                            <option value="Yes" <?php echo ($partner['smoking'] ?? '') == 'Yes' ? 'selected' : ''; ?>>Yes</option>
                            <option value="Occasionally" <?php echo ($partner['smoking'] ?? '') == 'Occasionally' ? 'selected' : ''; ?>>Occasionally</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Drinking</label>
                        <select class="form-select" name="partner_drinking" required>
                            <option value="No" <?php echo ($partner['drinking'] ?? '') == 'No' ? 'selected' : ''; ?>>No</option>
                            <option value="Yes" <?php echo ($partner['drinking'] ?? '') == 'Yes' ? 'selected' : ''; ?>>Yes</option>
                            <option value="Occasionally" <?php echo ($partner['drinking'] ?? '') == 'Occasionally' ? 'selected' : ''; ?>>Occasionally</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Horoscope -->
            <div class="form-section">
                <h2 class="section-title"><i class="bi bi-stars"></i> Horoscope Information</h2>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Birth Date</label>
                        <input type="date" class="form-control" name="birth_date" value="<?php echo htmlspecialchars($horoscope['birth_date'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Birth Time</label>
                        <input type="time" class="form-control" name="birth_time" value="<?php echo htmlspecialchars($horoscope['birth_time'] ?? ''); ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Zodiac Sign</label>
                        <input type="text" class="form-control" name="zodiac" value="<?php echo htmlspecialchars($horoscope['zodiac'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nakshatra</label>
                        <input type="text" class="form-control" name="nakshatra" value="<?php echo htmlspecialchars($horoscope['nakshatra'] ?? ''); ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Karmic Debt</label>
                        <input type="text" class="form-control" name="karmic_debt" value="<?php echo htmlspecialchars($horoscope['karmic_debt'] ?? ''); ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Planet Chart Image</label>
                        <input type="file" class="form-control" name="planet_image" accept="image/*">
                        <?php if ($horoscope && !empty($horoscope['planet_image'])): ?>
                            <div class="mt-2">
                                <small class="text-muted">Current: </small>
                                <img src="<?php echo htmlspecialchars($horoscope['planet_image']); ?>" width="50" height="50" class="rounded current-image">
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Navamsha Chart Image</label>
                        <input type="file" class="form-control" name="navamsha_image" accept="image/*">
                        <?php if ($horoscope && !empty($horoscope['navamsha_image'])): ?>
                            <div class="mt-2">
                                <small class="text-muted">Current: </small>
                                <img src="<?php echo htmlspecialchars($horoscope['navamsha_image']); ?>" width="50" height="50" class="rounded current-image">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="tab-quick-links">
                <h5 class="fw-bold text-primary mb-3">Quick Actions</h5>
                <div class="d-flex flex-wrap gap-3">
                    <a href="profile.php" class="quick-link-btn">
                        <i class="bi bi-person-circle me-1"></i>View Profile
                    </a>
                    <a href="home.php" class="quick-link-btn">
                        <i class="bi bi-house me-1"></i>Back to Home
                    </a>
                    <a href="package.php" class="quick-link-btn">
                        <i class="bi bi-gem me-1"></i>Upgrade Package
                    </a>
                </div>
            </div>

            <div class="text-center mt-4">
                <button type="submit" class="btn btn-custom btn-lg me-3">
                    <i class="bi bi-check-circle me-1"></i>Update Profile
                </button>
                <a href="profile.php" class="btn btn-outline-custom btn-lg">
                    <i class="bi bi-x-circle me-1"></i>Cancel
                </a>
            </div>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function addEducation() {
            const container = document.getElementById('education-container');
            const newEntry = document.createElement('div');
            newEntry.className = 'education-entry';
            newEntry.innerHTML = `
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Institute</label>
                        <input type="text" class="form-control" name="institute[]">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Degree</label>
                        <input type="text" class="form-control" name="degree[]">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Field</label>
                        <input type="text" class="form-control" name="field[]">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Reg. Number</label>
                        <input type="text" class="form-control" name="regnum[]">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Start Year</label>
                        <input type="number" class="form-control" name="startyear[]">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">End Year</label>
                        <input type="number" class="form-control" name="endyear[]">
                    </div>
                </div>
                <button type="button" class="remove-btn" onclick="this.parentElement.remove()">
                    <i class="bi bi-trash me-1"></i>Remove
                </button>
            `;
            container.appendChild(newEntry);
        }
    </script>
</body>
</html>