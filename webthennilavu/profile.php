<?php
session_start();

// Database connection
$conn = new mysqli('localhost', 'thennilavu_matrimonial', 'OYVuiEKfS@FQ', 'thennilavu_thennilavu');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle deactivate action: set marital_status = 'Deactivated'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_member'])) {
    try {
        $conn->begin_transaction();
        // find member id for this user
        $stmt = $conn->prepare("SELECT id FROM members WHERE user_id = ?");
        if (!$stmt) throw new Exception('Prepare failed (select member): ' . $conn->error);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        if (!$res) throw new Exception('Member record not found');
        $mid = $res['id'];

        $stmt = $conn->prepare("UPDATE members SET marital_status = ? WHERE id = ?");
        if (!$stmt) throw new Exception('Prepare failed (update marital_status): ' . $conn->error);
        $new_status = 'Deactivated';
        $stmt->bind_param('si', $new_status, $mid);
        $stmt->execute();
        $conn->commit();
        $flash_success = 'Your profile has been deactivated (marital status set to Deactivated).';
        // Refresh page data below by continuing to normal flow
    } catch (Exception $e) {
        if ($conn->errno) $conn->rollback();
        $flash_error = 'Failed to deactivate profile: ' . $e->getMessage();
    }
}

// Handle reactivate action: set marital_status back to original value (default 'Single')
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reactivate_member'])) {
    try {
        $conn->begin_transaction();
        // find member id for this user
        $stmt = $conn->prepare("SELECT id FROM members WHERE user_id = ?");
        if (!$stmt) throw new Exception('Prepare failed (select member): ' . $conn->error);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        if (!$res) throw new Exception('Member record not found');
        $mid = $res['id'];

        $stmt = $conn->prepare("UPDATE members SET marital_status = ? WHERE id = ?");
        if (!$stmt) throw new Exception('Prepare failed (update marital_status): ' . $conn->error);
        $new_status = 'Single'; // Default status when reactivating
        $stmt->bind_param('si', $new_status, $mid);
        $stmt->execute();
        $conn->commit();
        $flash_success = 'Your profile has been reactivated (marital status set to Single).';
        // Refresh page data below by continuing to normal flow
    } catch (Exception $e) {
        if ($conn->errno) $conn->rollback();
        $flash_error = 'Failed to reactivate profile: ' . $e->getMessage();
    }
}

// Handle hide action: set profile_hidden = 1
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hide_member'])) {
    try {
        $conn->begin_transaction();
        
        // Check if user has permission to hide profile
        $checkStmt = $conn->prepare("
            SELECT p.profile_hide_enabled 
            FROM members m 
            LEFT JOIN userpackage up ON m.user_id = up.user_id 
            LEFT JOIN packages p ON up.status = p.name 
            WHERE m.user_id = ? 
            AND up.requestPackage = 'accept' 
            AND up.end_date >= CURDATE()
            ORDER BY up.end_date DESC 
            LIMIT 1
        ");
        $checkStmt->bind_param('i', $user_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result()->fetch_assoc();
        
        if (!$checkResult || $checkResult['profile_hide_enabled'] !== 'Yes') {
            throw new Exception('Profile hide feature is not available with your current package');
        }
        
        // Update profile_hidden status
        $stmt = $conn->prepare("UPDATE members SET profile_hidden = 1 WHERE user_id = ?");
        if (!$stmt) throw new Exception('Prepare failed (update profile_hidden): ' . $conn->error);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $conn->commit();
        $flash_success = 'Your profile has been hidden from other members.';
    } catch (Exception $e) {
        if ($conn->errno) $conn->rollback();
        $flash_error = 'Failed to hide profile: ' . $e->getMessage();
    }
}

// Handle show action: set profile_hidden = 0
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['show_member'])) {
    try {
        $conn->begin_transaction();
        $stmt = $conn->prepare("UPDATE members SET profile_hidden = 0 WHERE user_id = ?");
        if (!$stmt) throw new Exception('Prepare failed (update profile_hidden): ' . $conn->error);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $conn->commit();
        $flash_success = 'Your profile is now visible to other members.';
    } catch (Exception $e) {
        if ($conn->errno) $conn->rollback();
        $flash_error = 'Failed to show profile: ' . $e->getMessage();
    }
}

// Handle delete additional photo action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_POST['photo_id'])) {
    header('Content-Type: application/json');
    
    try {
        $photo_id = intval($_POST['photo_id']);
        
        // First, get the member_id for the logged-in user
        $stmt = $conn->prepare("SELECT id FROM members WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $member_result = $stmt->get_result()->fetch_assoc();
        
        if (!$member_result) {
            echo json_encode(['success' => false, 'error' => 'Member not found']);
            exit;
        }
        
        $member_id = $member_result['id'];
        
        // Get photo details before deletion (for file cleanup)
        $stmt = $conn->prepare("SELECT photo_path FROM additional_photos WHERE id = ? AND member_id = ?");
        $stmt->bind_param('ii', $photo_id, $member_id);
        $stmt->execute();
        $photo_result = $stmt->get_result()->fetch_assoc();
        
        if (!$photo_result) {
            echo json_encode(['success' => false, 'error' => 'Photo not found or unauthorized']);
            exit;
        }
        
        // Delete from database
        $stmt = $conn->prepare("DELETE FROM additional_photos WHERE id = ? AND member_id = ?");
        $stmt->bind_param('ii', $photo_id, $member_id);
        
        if ($stmt->execute()) {
            // Try to delete the physical file (optional - won't fail if file doesn't exist)
            $file_path = $photo_result['photo_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            echo json_encode(['success' => true, 'message' => 'Photo deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to delete photo from database']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle get_member_photos request
if (isset($_GET['get_member_photos']) && $_GET['get_member_photos'] == '1') {
    header('Content-Type: application/json');
    
    $member_id = isset($_GET['member_id']) ? intval($_GET['member_id']) : 0;
    
    if ($member_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid member ID']);
        exit;
    }
    
    try {
        // Fetch main profile photo first
        $stmt = $conn->prepare("SELECT photo FROM members WHERE id = ?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $member = $result->fetch_assoc();
        $stmt->close();
        
        $photos = [];
        
        // Add main profile photo as first image
        if ($member && !empty($member['photo'])) {
            $photos[] = [
                'photo_path' => $member['photo'],
                'upload_order' => 0,
                'is_main' => true
            ];
        }
        
        // Fetch additional photos for the member
        $stmt = $conn->prepare("SELECT id, photo_path, upload_order FROM additional_photos WHERE member_id = ? ORDER BY upload_order ASC");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $row['is_main'] = false;
            $photos[] = $row;
        }
        
        $stmt->close();
        
        echo json_encode([
            'success' => true, 
            'photos' => $photos
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle upload action for additional photos
if (isset($_GET['action']) && $_GET['action'] == 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'Not authenticated']);
            exit;
        }
        
        // Get member ID for this user
        $stmt = $conn->prepare("SELECT id FROM members WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $member_result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$member_result) {
            echo json_encode(['success' => false, 'error' => 'Member not found']);
            exit;
        }
        
        $member_id = $member_result['id'];
        
        // Check if files were uploaded
        if (!isset($_FILES['additional_photos']) || empty($_FILES['additional_photos']['name'][0])) {
            echo json_encode(['success' => false, 'error' => 'No files uploaded']);
            exit;
        }
        
        // Create uploads directory if it doesn't exist
        $upload_dir = __DIR__ . '/uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Get current photo count to determine upload order
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM additional_photos WHERE member_id = ?");
        $stmt->bind_param('i', $member_id);
        $stmt->execute();
        $count_result = $stmt->get_result()->fetch_assoc();
        $current_count = $count_result['count'];
        $stmt->close();
        
        $uploaded_files = 0;
        $errors = [];
        
        // Process each uploaded file
        for ($i = 0; $i < count($_FILES['additional_photos']['name']); $i++) {
            if ($_FILES['additional_photos']['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }
            
            // Check if we're at the 5 photo limit
            if ($current_count + $uploaded_files >= 5) {
                $errors[] = "Maximum 5 additional photos allowed";
                break;
            }
            
            $file_name = $_FILES['additional_photos']['name'][$i];
            $file_tmp = $_FILES['additional_photos']['tmp_name'][$i];
            $file_size = $_FILES['additional_photos']['size'][$i];
            
            // Validate file
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $file_type = mime_content_type($file_tmp);
            
            if (!in_array($file_type, $allowed_types)) {
                $errors[] = "File $file_name: Invalid file type. Only JPEG, PNG, and GIF allowed.";
                continue;
            }
            
            if ($file_size > 5 * 1024 * 1024) { // 5MB limit
                $errors[] = "File $file_name: File size too large. Maximum 5MB allowed.";
                continue;
            }
            
            // Generate unique filename with desired format
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $timestamp = time();
            $order = $uploaded_files + 1;
            $new_filename = $timestamp . '_additional_' . $order . '_' . $member_id . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            // Move uploaded file
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Insert into database with full path including uploads/
                $stmt = $conn->prepare("INSERT INTO additional_photos (member_id, photo_path, upload_order) VALUES (?, ?, ?)");
                $upload_order = $current_count + $uploaded_files + 1;
                $photo_path = 'uploads/' . $new_filename;
                $stmt->bind_param('isi', $member_id, $photo_path, $upload_order);
                
                if ($stmt->execute()) {
                    $uploaded_files++;
                } else {
                    $errors[] = "Failed to save $file_name to database";
                    unlink($upload_path); // Remove the uploaded file
                }
                $stmt->close();
            } else {
                $errors[] = "Failed to upload $file_name";
            }
        }
        
        if ($uploaded_files > 0) {
            $message = "$uploaded_files photo(s) uploaded successfully";
            if (!empty($errors)) {
                $message .= ". Some files had errors: " . implode(', ', $errors);
            }
            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No files uploaded. ' . implode(', ', $errors)]);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Upload error: ' . $e->getMessage()]);
    }
    exit;
}

// Fetch user details from all tables
$member = null; $physical = null; $education = []; $family = null; $partner = null; $horoscope = null;

// Check if profile_hidden column exists, if not create it
$columnCheck = $conn->query("SHOW COLUMNS FROM members LIKE 'profile_hidden'");
if ($columnCheck->num_rows == 0) {
    $conn->query("ALTER TABLE members ADD COLUMN profile_hidden TINYINT(1) DEFAULT 0");
}

// Get member details and member_id
$stmt = $conn->prepare("SELECT id, name, photo, looking_for, dob, religion, gender, marital_status, language, profession, country, phone, smoking, drinking, present_address, city, zip, permanent_address, permanent_city, COALESCE(profile_hidden, 0) as profile_hidden FROM members WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();

if (!$member) {
    // User hasn't completed registration
    $member_id = null;
} else {
    $member_id = $member['id'];
    
    // Get physical info
    $stmt = $conn->prepare("SELECT complexion, height_cm, weight_kg, blood_group, eye_color, hair_color, disability FROM physical_info WHERE member_id = ?");
    $stmt->bind_param('i', $member_id);
    $stmt->execute();
    $physical = $stmt->get_result()->fetch_assoc();
    
    // Get education
    $stmt = $conn->prepare("SELECT level, school_or_institute, stream_or_degree, field, reg_number, start_year, end_year FROM education WHERE member_id = ?");
    $stmt->bind_param('i', $member_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { 
        $education[] = $r; 
    }
    
    // Get family info
    $stmt = $conn->prepare("SELECT father_name, father_profession, father_contact, mother_name, mother_profession, mother_contact, brothers_count, sisters_count FROM family WHERE member_id = ?");
    $stmt->bind_param('i', $member_id);
    $stmt->execute();
    $family = $stmt->get_result()->fetch_assoc();
    
    // Get partner expectations
    $stmt = $conn->prepare("SELECT preferred_country, min_age, max_age, min_height, max_height, marital_status, religion, smoking, drinking FROM partner_expectations WHERE member_id = ?");
    $stmt->bind_param('i', $member_id);
    $stmt->execute();
    $partner = $stmt->get_result()->fetch_assoc();
    
    // Get horoscope
    $stmt = $conn->prepare("SELECT birth_date, birth_time, zodiac, nakshatra, karmic_debt, planet_image, navamsha_image FROM horoscope WHERE member_id = ?");
    $stmt->bind_param('i', $member_id);
    $stmt->execute();
    $horoscope = $stmt->get_result()->fetch_assoc();
    
    // Get additional photos
    $additional_photos = [];
    $stmt = $conn->prepare("SELECT id, photo_path, upload_order FROM additional_photos WHERE member_id = ? ORDER BY upload_order ASC");
    $stmt->bind_param('i', $member_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $additional_photos[] = $r;
    }

    // Helper: normalize stored photo paths so they are web-accessible
    function normalize_photo_path($path) {
        if (!$path) return '';
        // Convert backslashes to forward slashes (Windows paths)
        $p = str_replace('\\', '/', trim($path));

        // If it's already an absolute URL, return as-is
        if (preg_match('#^https?://#i', $p)) {
            return $p;
        }

        // If path contains 'uploads/' return substring starting there (handles absolute server paths)
        $pos = stripos($p, 'uploads/');
        if ($pos !== false) {
            return substr($p, $pos);
        }

        // If path starts with a leading slash, treat as root-relative and strip leading slashes
        if (strpos($p, '/') === 0) {
            $p = ltrim($p, '/');
            // ensure uploads/ prefix if not present
            return strpos($p, 'uploads/') === 0 ? $p : 'uploads/' . $p;
        }

        // If the path already begins with uploads/, return as-is
        if (stripos($p, 'uploads/') === 0) {
            return $p;
        }

        // Fallback: prepend 'uploads/' so it matches the mem.php display logic
        return 'uploads/' . $p;
    }
}

// Determine current active package for this user (considering expiry and accept flag)
$package_name = '';
$package_expires = null;
$package_active = false;
$profile_hide_enabled = false;
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $stmt = $conn->prepare("
        SELECT up.status, up.requestPackage, up.end_date, p.profile_hide_enabled 
        FROM userpackage up 
        LEFT JOIN packages p ON up.status = p.name 
        WHERE up.user_id = ? 
        ORDER BY up.start_date DESC 
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $stmt->bind_result($up_status, $up_requestPackage, $up_end_date, $profile_hide_enabled_db);
        if ($stmt->fetch()) {
            if ($up_requestPackage === 'accept' && strtotime($up_end_date) > time()) {
                $package_name = $up_status;
                $package_expires = $up_end_date;
                $package_active = true;
                $profile_hide_enabled = ($profile_hide_enabled_db === 'Yes');
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - TheanNilavu Matrimony</title>
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
            background: linear-gradient(135deg, #f5f7fa 0%, #e91e63 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-dark);
            min-height: 100vh;
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

        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .modal-backdrop {
            z-index: 1040 !important;
        }
        
        .modal {
            z-index: 1050 !important;
            position: fixed !important; /* ensures modal ignores parent stacking context */
        }


        .profile-header {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .profile-img-container {
            padding: 20px;
            text-align: center;
            background: linear-gradient(135deg, var(--primary-color) 0%, #ad1457 100%);
        }

        .profile-img {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .profile-info {
            padding: 25px;
        }

        .profile-name {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 5px;
        }

        .profile-profession {
            color: var(--text-light);
            font-size: 1.1rem;
            margin-bottom: 20px;
        }

        .profile-stats {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
            text-align: center;
        }

        .stat-item {
            padding: 10px;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-light);
        }

        .nav-tabs-custom {
            background: white;
            border-radius: 15px;
            padding: 0 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            border-bottom: none;
        }

        .nav-tabs-custom .nav-link {
            border: none;
            color: var(--text-light);
            font-weight: 600;
            padding: 15px 20px;
            position: relative;
            transition: all 0.3s ease;
        }

        .nav-tabs-custom .nav-link:hover {
            color: var(--primary-color);
            background: transparent;
        }

        .nav-tabs-custom .nav-link.active {
            color: var(--primary-color);
            background: transparent;
            border: none;
        }

        .nav-tabs-custom .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 15px;
            right: 15px;
            height: 3px;
            background: var(--primary-color);
            border-radius: 3px 3px 0 0;
        }

        .tab-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            min-height: 500px;
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
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

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .info-card {
            background: var(--primary-light);
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .info-item {
            margin-bottom: 15px;
            display: flex;
        }

        .info-label {
            font-weight: 600;
            color: var(--text-dark);
            min-width: 150px;
        }

        .info-value {
            color: var(--text-light);
            flex: 1;
        }

        .table-custom {
            width: 100%;
            border-collapse: collapse;
        }

        .table-custom th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            padding: 12px 15px;
        }

        .table-custom td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .table-custom tbody tr:hover {
            background-color: rgba(233, 30, 99, 0.05);
        }

        .horoscope-img {
            max-width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            margin-bottom: 15px;
        }

        .horoscope-img:hover {
            transform: scale(1.03);
        }

        .btn-custom {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            font-weight: 600;
            padding: 10px 25px;
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
            padding: 10px 25px;
            border-radius: 30px;
            transition: all 0.3s ease;
        }

        .btn-outline-custom:hover {
            background-color: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .action-btn {
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: 600;
            margin: 5px;
        }

        .alert-custom {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: none;
            border-left: 4px solid var(--primary-color);
        }

        .badge-custom {
            background-color: var(--primary-color);
            color: white;
        }

        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
        }

        .status-visible {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .status-hidden {
            background-color: #fff3e0;
            color: #ef6c00;
        }

        @media (max-width: 768px) {
            .profile-img {
                width: 150px;
                height: 150px;
            }
            
            .info-label {
                min-width: 120px;
            }
            
            .back-btn {
                top: 10px;
                left: 10px;
                width: 40px;
                height: 40px;
            }
            
            .nav-tabs-custom .nav-link {
                padding: 10px 15px;
                font-size: 0.9rem;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Enhanced styling for Add/Edit Photos button */
        .btn-photos-edit {
            background: linear-gradient(135deg, #e91e63, #ff4081) !important;
            border: 2px solid #e91e63 !important;
            color: white !important;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 20px;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(233, 30, 99, 0.2);
        }
        
        .btn-photos-edit:hover {
            background: linear-gradient(135deg, #d81b60, #e91e63) !important;
            border-color: #d81b60 !important;
            color: white !important;
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.3);
        }
        
        .btn-photos-edit:active {
            transform: translateY(0);
        }
        
        .btn-photos-edit i {
            font-size: 1rem;
        }
    </style>
</head>
<body>
    <!-- Back Button -->
    <a href="index.php" class="back-btn" title="Back to Home">
        <i class="bi bi-arrow-left-short" style="font-size: 1.5rem;"></i>
    </a>

    <div class="container py-4 profile-container">
        <?php if (isset($flash_success)): ?>
            <div class="alert alert-success alert-custom text-center mb-4"><?php echo $flash_success; ?></div>
        <?php endif; ?>
        <?php if (isset($flash_error)): ?>
            <div class="alert alert-danger alert-custom text-center mb-4"><?php echo $flash_error; ?></div>
        <?php endif; ?>

        <?php if (!$member): ?>
            <div class="alert alert-warning text-center alert-custom mt-5">
                <h4 class="fw-bold">Profile Not Found</h4>
                <p class="mb-3">Please complete your registration first.</p>
                <a href="members.php" class="btn btn-custom">Complete Registration</a>
            </div>
        <?php else: ?>
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="row g-0">
                    <div class="col-md-4 profile-img-container">
                        <img src="<?php echo htmlspecialchars($member['photo'] ?: 'img/default-avatar.png'); ?>" 
                             alt="Profile Photo" class="profile-img mb-3">
                        <h3 class="text-white profile-name"><?php echo htmlspecialchars($member['name']); ?></h3>
                        <p class="text-white profile-profession"><?php echo htmlspecialchars($member['profession']); ?></p>
                        

                    </div>
                    <div class="col-md-8 profile-info">
                        <div class="d-flex justify-content-between align-items-start flex-wrap">
                            <div>
                                <h2 class="profile-name mb-1"><?php echo htmlspecialchars($member['name']); ?></h2>
                                <p class="text-muted mb-3"><?php echo htmlspecialchars($member['profession'] . ' • ' . $member['city'] . ', ' . $member['country']); ?></p>
                                
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <span class="status-badge <?php echo (isset($member['profile_hidden']) && $member['profile_hidden']) ? 'status-hidden' : 'status-visible'; ?>">
                                        <i class="bi <?php echo (isset($member['profile_hidden']) && $member['profile_hidden']) ? 'bi-eye-slash' : 'bi-eye'; ?> me-1"></i>
                                        <?php echo (isset($member['profile_hidden']) && $member['profile_hidden']) ? 'Hidden' : 'Visible'; ?>
                                    </span>
                                    
                                    <span class="status-badge" style="background-color: #e3f2fd; color: #1565c0;">
                                        <i class="bi bi-award me-1"></i>
                                        <?php if ($package_active): ?>
                                            <?php echo htmlspecialchars($package_name); ?>
                                        <?php else: ?>
                                            Free User
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="edit_profile.php" class="btn btn-outline-custom action-btn">
                                    <i class="bi bi-pencil-square me-1"></i>Edit Profile
                                </a>
                                
                                <?php if ($profile_hide_enabled): ?>
                                    <?php if (isset($member['profile_hidden']) && $member['profile_hidden']): ?>
                                        <form method="POST" id="showForm" style="display:inline;">
                                            <input type="hidden" name="show_member" value="1">
                                            <button type="submit" class="btn btn-outline-success action-btn" 
                                                    onclick="return confirm('Are you sure you want to make your profile visible to other members?')">
                                                <i class="bi bi-eye me-1"></i>Show Profile
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" id="hideForm" style="display:inline;">
                                            <input type="hidden" name="hide_member" value="1">
                                            <button type="button" class="btn btn-outline-custom action-btn" data-bs-toggle="modal" data-bs-target="#hideModal">
                                                <i class="bi bi-eye-slash me-1"></i>Hide Profile
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button type="button" class="btn btn-outline-custom action-btn" onclick="showUpgradeMessage()">
                                        <i class="bi bi-eye-slash me-1"></i>Hide Profile
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($member['marital_status'] === 'Deactivated'): ?>
                                    <form method="POST" id="reactivateForm" style="display:inline;">
                                        <input type="hidden" name="reactivate_member" value="1">
                                        <button type="button" class="btn btn-outline-success action-btn" data-bs-toggle="modal" data-bs-target="#reactivateModal">
                                            <i class="bi bi-person-check me-1"></i>Reactivate
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" id="deactivateForm" style="display:inline;">
                                        <input type="hidden" name="deactivate_member" value="1">
                                        <button type="button" class="btn btn-outline-custom action-btn" data-bs-toggle="modal" data-bs-target="#deactivateModal">
                                            <i class="bi bi-person-x me-1"></i>Deactivate
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="info-item">
                                    <span class="info-label">Gender:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($member['gender']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Date of Birth:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($member['dob']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Religion:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($member['religion']); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-item">
                                    <span class="info-label">Marital Status:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($member['marital_status']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Language:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($member['language']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Phone:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($member['phone']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <ul class="nav nav-tabs nav-tabs-custom" id="profileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="physical-tab" data-bs-toggle="tab" data-bs-target="#physical" type="button" role="tab" aria-controls="physical" aria-selected="true">
                        <i class="bi bi-person-bounding-box me-2"></i>Physical Info
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="partner-tab" data-bs-toggle="tab" data-bs-target="#partner" type="button" role="tab" aria-controls="partner" aria-selected="false">
                        <i class="bi bi-heart me-2"></i>Partner Expectations
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="education-tab" data-bs-toggle="tab" data-bs-target="#education" type="button" role="tab" aria-controls="education" aria-selected="false">
                        <i class="bi bi-book me-2"></i>Education
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="family-tab" data-bs-toggle="tab" data-bs-target="#family" type="button" role="tab" aria-controls="family" aria-selected="false">
                        <i class="bi bi-people-fill me-2"></i>Family
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="horoscope-tab" data-bs-toggle="tab" data-bs-target="#horoscope" type="button" role="tab" aria-controls="horoscope" aria-selected="false">
                        <i class="bi bi-stars me-2"></i>Horoscope
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="photos-tab" data-bs-toggle="tab" data-bs-target="#photos" type="button" role="tab" aria-controls="photos" aria-selected="false">
                        <i class="bi bi-images me-2"></i>Additional Photos
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="actions-tab" data-bs-toggle="tab" data-bs-target="#actions" type="button" role="tab" aria-controls="actions" aria-selected="false">
                        <i class="bi bi-gear me-2"></i>Actions
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="profileTabContent">
                <!-- Physical Information Tab -->
                <div class="tab-pane fade show active" id="physical" role="tabpanel" aria-labelledby="physical-tab">
                    <h4 class="section-title"><i class="bi bi-person-bounding-box"></i> Physical Information</h4>
                    <?php if ($physical): ?>
                        <div class="info-grid">
                            <div class="info-card">
                                <div class="info-item">
                                    <span class="info-label">Complexion:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($physical['complexion']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Height:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($physical['height_cm']); ?> cm</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Weight:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($physical['weight_kg']); ?> kg</span>
                                </div>
                            </div>
                            
                            <div class="info-card">
                                <div class="info-item">
                                    <span class="info-label">Blood Group:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($physical['blood_group']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Eye Color:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($physical['eye_color']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Hair Color:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($physical['hair_color']); ?></span>
                                </div>
                            </div>
                            
                            <div class="info-card">
                                <div class="info-item">
                                    <span class="info-label">Disability:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($physical['disability'] ?: 'None'); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-5">No physical information available</p>
                    <?php endif; ?>
                    
                    <!-- Quick Actions -->
                    <div class="info-card mt-4">
                        <h5 class="fw-bold text-primary mb-3">Quick Actions</h5>
                        <div class="d-flex flex-wrap gap-3">
                            <a href="edit_profile.php" class="btn btn-custom action-btn">
                                <i class="bi bi-pencil-square me-1"></i>Edit Profile
                            </a>
                            <a href="package.php" class="btn btn-outline-custom action-btn">
                                <i class="bi bi-gem me-1"></i>Upgrade Package
                            </a>
                            <a href="index.php" class="btn btn-outline-custom action-btn">
                                <i class="bi bi-house me-1"></i>Back to Home
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Partner Expectations Tab -->
                <div class="tab-pane fade" id="partner" role="tabpanel" aria-labelledby="partner-tab">
                    <h4 class="section-title"><i class="bi bi-heart"></i> Partner Expectations</h4>
                    <?php if ($partner): ?>
                        <div class="info-grid">
                            <div class="info-card">
                                <div class="info-item">
                                    <span class="info-label">Preferred Country:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($partner['preferred_country']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Age Range:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($partner['min_age'] . ' - ' . $partner['max_age']); ?> years</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Height Range:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($partner['min_height'] . ' - ' . $partner['max_height']); ?> cm</span>
                                </div>
                            </div>
                            
                            <div class="info-card">
                                <div class="info-item">
                                    <span class="info-label">Marital Status:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($partner['marital_status']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Religion:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($partner['religion']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Smoking:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($partner['smoking']); ?></span>
                                </div>
                            </div>
                            
                            <div class="info-card">
                                <div class="info-item">
                                    <span class="info-label">Drinking:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($partner['drinking']); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-5">No partner expectations available</p>
                    <?php endif; ?>
                    
                    <!-- Quick Actions -->
                    <div class="info-card mt-4">
                        <h5 class="fw-bold text-primary mb-3">Quick Actions</h5>
                        <div class="d-flex flex-wrap gap-3">
                            <a href="edit_profile.php" class="btn btn-custom action-btn">
                                <i class="bi bi-pencil-square me-1"></i>Edit Profile
                            </a>
                            <a href="package.php" class="btn btn-outline-custom action-btn">
                                <i class="bi bi-gem me-1"></i>Upgrade Package
                            </a>
                            <a href="index.php" class="btn btn-outline-custom action-btn">
                                <i class="bi bi-house me-1"></i>Back to Home
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Education Tab -->
                <div class="tab-pane fade" id="education" role="tabpanel" aria-labelledby="education-tab">
                    <h4 class="section-title"><i class="bi bi-book"></i> Education</h4>
                    <?php if ($education): ?>
                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>Level</th>
                                        <th>Institute</th>
                                        <th>Degree</th>
                                        <th>Field</th>
                                        <th>Reg. Number</th>
                                        <th>Duration</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($education as $edu): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($edu['level']); ?></td>
                                            <td><?php echo htmlspecialchars($edu['school_or_institute']); ?></td>
                                            <td><?php echo htmlspecialchars($edu['stream_or_degree']); ?></td>
                                            <td><?php echo htmlspecialchars($edu['field']); ?></td>
                                            <td><?php echo htmlspecialchars($edu['reg_number']); ?></td>
                                            <td><?php echo htmlspecialchars($edu['start_year'] . ' - ' . $edu['end_year']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-5">No education information available</p>
                    <?php endif; ?>
                    
                    <!-- Quick Actions -->
                    <div class="info-card mt-4">
                        <h5 class="fw-bold text-primary mb-3">Quick Actions</h5>
                        <div class="d-flex flex-wrap gap-3">
                            <a href="edit_profile.php" class="btn btn-custom action-btn">
                                <i class="bi bi-pencil-square me-1"></i>Edit Profile
                            </a>
                            <a href="package.php" class="btn btn-outline-custom action-btn">
                                <i class="bi bi-gem me-1"></i>Upgrade Package
                            </a>
                            <a href="index.php" class="btn btn-outline-custom action-btn">
                                <i class="bi bi-house me-1"></i>Back to Home
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Family Information Tab -->
                <div class="tab-pane fade" id="family" role="tabpanel" aria-labelledby="family-tab">
                    <h4 class="section-title"><i class="bi bi-people-fill"></i> Family Information</h4>
                    <?php if ($family): ?>
                        <div class="info-grid">
                            <div class="info-card">
                                <h6 class="fw-bold text-primary mb-3">Father's Information</h6>
                                <div class="info-item">
                                    <span class="info-label">Name:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($family['father_name']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Profession:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($family['father_profession']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Contact:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($family['father_contact']); ?></span>
                                </div>
                            </div>
                            
                            <div class="info-card">
                                <h6 class="fw-bold text-primary mb-3">Mother's Information</h6>
                                <div class="info-item">
                                    <span class="info-label">Name:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($family['mother_name']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Profession:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($family['mother_profession']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Contact:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($family['mother_contact']); ?></span>
                                </div>
                            </div>
                            
                            <div class="info-card">
                                <h6 class="fw-bold text-primary mb-3">Siblings</h6>
                                <div class="info-item">
                                    <span class="info-label">Brothers:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($family['brothers_count']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Sisters:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($family['sisters_count']); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-5">No family information available</p>
                    <?php endif; ?>
                    
                    <!-- Quick Actions -->
                    <div class="info-card mt-4">
                        <h5 class="fw-bold text-primary mb-3">Quick Actions</h5>
                        <div class="d-flex flex-wrap gap-3">
                            <a href="edit_profile.php" class="btn btn-custom action-btn">
                                <i class="bi bi-pencil-square me-1"></i>Edit Profile
                            </a>
                            <a href="package.php" class="btn btn-outline-custom action-btn">
                                <i class="bi bi-gem me-1"></i>Upgrade Package
                            </a>
                            <a href="index.php" class="btn btn-outline-custom action-btn">
                                <i class="bi bi-house me-1"></i>Back to Home
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Horoscope Tab -->
                <div class="tab-pane fade" id="horoscope" role="tabpanel" aria-labelledby="horoscope-tab">
                    <h4 class="section-title"><i class="bi bi-stars"></i> Horoscope</h4>
                    <?php if ($horoscope): ?>
                        <div class="info-grid">
                            <div class="info-card">
                                <div class="info-item">
                                    <span class="info-label">Birth Date:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($horoscope['birth_date']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Birth Time:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($horoscope['birth_time']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Zodiac Sign:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($horoscope['zodiac']); ?></span>
                                </div>
                            </div>
                            
                            <div class="info-card">
                                <div class="info-item">
                                    <span class="info-label">Nakshatra:</span>
                                    <span class="info-value"><?php 
        if ($horoscope['nakshatra'] == "1001") echo "அஸ்வினி";
        elseif ($horoscope['nakshatra'] == "1002") echo "பரணி";
        elseif ($horoscope['nakshatra'] == "1003") echo "கார்த்திகை 1ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1004") echo "கார்த்திகை 2ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1005") echo "கார்த்திகை 3ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1006") echo "கார்த்திகை 4ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1007") echo "ரோகிணி";
        elseif ($horoscope['nakshatra'] == "1008") echo "மிருகசீரிடம் 1ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1009") echo "மிருகசீரிடம் 2ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1010") echo "மிருகசீரிடம் 3ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1011") echo "மிருகசீரிடம் 4ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1012") echo "திருவாதிரை";
        elseif ($horoscope['nakshatra'] == "1013") echo "புனர்பூசம் 1ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1014") echo "புனர்பூசம் 2ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1015") echo "புனர்பூசம் 3ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1016") echo "புனர்பூசம் 4ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1017") echo "பூசம்";
        elseif ($horoscope['nakshatra'] == "1018") echo "ஆயிலியம்";
        elseif ($horoscope['nakshatra'] == "1019") echo "மகம்";
        elseif ($horoscope['nakshatra'] == "1020") echo "பூரம்";
        elseif ($horoscope['nakshatra'] == "1021") echo "உத்தரம் 1ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1022") echo "உத்தரம் 2ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1023") echo "உத்தரம் 3ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1024") echo "உத்தரம் 4ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1025") echo "அஸ்தம்";
        elseif ($horoscope['nakshatra'] == "1026") echo "சித்திரை 1ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1027") echo "சித்திரை 2ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1028") echo "சித்திரை 3ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1029") echo "சித்திரை 4ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1030") echo "சுவாதி";
        elseif ($horoscope['nakshatra'] == "1031") echo "விசாகம் 1ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1032") echo "விசாகம் 2ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1033") echo "விசாகம் 3ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1034") echo "விசாகம் 4ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1035") echo "அனுஷம்";
        elseif ($horoscope['nakshatra'] == "1036") echo "கேட்டை";
        elseif ($horoscope['nakshatra'] == "1037") echo "மூலம்";
        elseif ($horoscope['nakshatra'] == "1038") echo "பூராடம்";
        elseif ($horoscope['nakshatra'] == "1039") echo "உத்திராடம் 1ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1040") echo "உத்திராடம் 2ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1041") echo "உத்திராடம் 3ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1042") echo "உத்திராடம் 4ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1043") echo "திருவோணம்";
        elseif ($horoscope['nakshatra'] == "1044") echo "அவிட்டம் 1 ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1045") echo "அவிட்டம் 2ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1046") echo "அவிட்டம் 3ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1047") echo "அவிட்டம் 4ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1048") echo "சதயம்";
        elseif ($horoscope['nakshatra'] == "1049") echo "பூரட்டாதி 1ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1050") echo "பூரட்டாதி 2ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1051") echo "பூரட்டாதி 3ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1052") echo "பூரட்டாதி 4ம் பாதம்";
        elseif ($horoscope['nakshatra'] == "1053") echo "உத்திரட்டாதி";
        elseif ($horoscope['nakshatra'] == "1054") echo "ரேவதி";
    ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Karmic Debt:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($horoscope['karmic_debt']); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($horoscope['planet_image'] || $horoscope['navamsha_image']): ?>
                            <div class="row mt-4">
                                <div class="col-12">
                                    <h5 class="fw-bold text-primary mb-3">Charts</h5>
                                </div>
                                <?php if ($horoscope['planet_image']): ?>
                                    <div class="col-md-6 mb-4">
                                        <h6 class="fw-bold text-primary mb-2">Planet Chart</h6>
                                        <img src="<?php echo htmlspecialchars($horoscope['planet_image']); ?>" 
                                             alt="Planet Chart" class="horoscope-img w-100">
                                    </div>
                                <?php endif; ?>
                                <?php if ($horoscope['navamsha_image']): ?>
                                    <div class="col-md-6 mb-4">
                                        <h6 class="fw-bold text-primary mb-2">Navamsha Chart</h6>
                                        <img src="<?php echo htmlspecialchars($horoscope['navamsha_image']); ?>" 
                                             alt="Navamsha Chart" class="horoscope-img w-100">
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-muted text-center py-5">No horoscope information available</p>
                    <?php endif; ?>
                    
                    <!-- Quick Actions -->
                    <div class="info-card mt-4">
                        <h5 class="fw-bold text-primary mb-3">Quick Actions</h5>
                        <div class="d-flex flex-wrap gap-3">
                            <a href="edit_profile.php" class="btn btn-custom action-btn">
                                <i class="bi bi-pencil-square me-1"></i>Edit Profile
                            </a>
                            <a href="package.php" class="btn btn-outline-custom action-btn">
                                <i class="bi bi-gem me-1"></i>Upgrade Package
                            </a>
                            <a href="index.php" class="btn btn-outline-custom action-btn">
                                <i class="bi bi-house me-1"></i>Back to Home
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Additional Photos Tab -->
                <div class="tab-pane fade" id="photos" role="tabpanel" aria-labelledby="photos-tab">
                    <h4 class="section-title"><i class="bi bi-images"></i> Additional Photos
                        <button id="openEditPhotosBtn" type="button" class="btn btn-sm btn-photos-edit ms-3" style="vertical-align: middle;" title="Add / Edit Photos">
                            <i class="bi bi-camera-fill me-2"></i> Add/Edit Photos
                        </button>
                    </h4>

                    <!-- Photos container: populated by client-side fetch to match mem.php behavior -->
                    <div id="photosContent">
                        <div class="alert alert-info alert-custom text-center py-5" style="background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%); border: 2px dashed #90caf9;">
                            <i class="bi bi-image" style="font-size: 3rem; color: #1976d2;"></i>
                            <p class="mt-3 mb-1" style="font-size: 1.1rem; color: #1565c0;"><strong>Loading photos…</strong></p>
                            <p class="text-muted mb-0">If photos exist they will appear here shortly.</p>
                        </div>
                    </div>
                    
                    <script>
                        // Normalize path similar to mem.php client logic
                        function normalizeClientPath(photo) {
                            if (!photo) return '';
                            photo = photo.replace(/^\/+/, '');
                            if (/^https?:\/\//i.test(photo)) return photo;
                            return photo.startsWith('uploads/') ? photo : 'uploads/' + photo;
                        }

                        async function loadProfilePhotos(memberId) {
                            const container = document.getElementById('photosContent');
                            if (!memberId || memberId <= 0) {
                                container.innerHTML = `<div class="alert alert-info">No member selected.</div>`;
                                return;
                            }

                            try {
                                const res = await fetch('profile.php?get_member_photos=1&member_id=' + encodeURIComponent(memberId), { credentials: 'same-origin' });
                                if (!res.ok) throw new Error('Network response was not ok');
                                const data = await res.json();
                                if (!data.success) throw new Error(data.error || 'API error');

                                const photos = data.photos || [];
                                if (photos.length === 0) {
                                    container.innerHTML = `<div class="alert alert-info alert-custom text-center py-5"><i class="bi bi-image" style="font-size: 3rem;"></i><p class="mt-3 mb-1"><strong>No Additional Photos Yet</strong></p><p class="text-muted">Add photos to your profile to make it more attractive!</p></div>`;
                                    return;
                                }

                                // Build responsive grid
                                let html = '<div class="row">';
                                photos.forEach((p, idx) => {
                                    const raw = p.photo_path || '';
                                    const src = normalizeClientPath(raw);
                                    const title = p.is_main ? 'Profile Photo' : ('Photo ' + (p.upload_order ?? (idx+1)));
                                    html += `
                                        <div class="col-lg-4 col-md-6 mb-4">
                                            <div class="photo-card-wrapper" style="height: 100%;">
                                                <div style="position: relative; overflow: hidden; border-radius: 12px; box-shadow: 0 4px 15px rgba(233, 30, 99, 0.1); height: 300px; transition: all 0.3s ease;">
                                                    <img src="${src}" alt="${title}" style="width:100%; height:100%; object-fit:cover; display:block;">
                                                    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.3); opacity: 0; transition: opacity 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 10px;">
                                                        <button class="btn btn-light btn-sm" onclick="viewPhotoModal('${src.replace(/'/g, "\\'")}', '${title.replace(/'/g, "\\'")}')"><i class="bi bi-zoom-in"></i> <span class="d-none d-sm-inline">View</span></button>
                                                        
                                                    </div>
                                                    <div style="position: absolute; top: 10px; right: 10px; background: rgba(233, 30, 99, 0.9); color: white; padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                                                        <i class="bi bi-image"></i> ${p.is_main ? 'Main' : ('#' + (p.upload_order ?? (idx+1)))}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    `;
                                });
                                html += '</div>';

                                // Summary
                                html += `<div class="alert alert-success alert-custom mt-4" style="background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 100%); border-left: 4px solid #4caf50;"><div class="d-flex justify-content-between align-items-center"><div><i class="bi bi-check-circle me-2" style="color: #4caf50; font-size: 1.2rem;"></i><strong style="color: #2e7d32;">You have ${photos.length} photo(s)</strong></div></div></div>`;

                                container.innerHTML = html;

                            } catch (err) {
                                console.error('Failed to load photos', err);
                                container.innerHTML = `<div class="alert alert-danger">Unable to load photos. Please try again later.</div>`;
                            }
                        }

                        document.addEventListener('DOMContentLoaded', function() {
                            const memberId = <?php echo isset($member_id) ? intval($member_id) : 0; ?>;
                            if (memberId) loadProfilePhotos(memberId);
                        });
                    </script>
                    
                    
                    <!-- Quick Actions -->
                    <div class="info-card mt-4" style="background: linear-gradient(135deg, var(--primary-light) 0%, #fff3e0 100%); border-left: 4px solid var(--primary-color);">
                        <h5 class="fw-bold text-primary mb-3"><i class="bi bi-lightning-charge me-2"></i>Quick Actions</h5>
                        <p class="text-muted mb-3">Manage your photo gallery to showcase yourself better</p>
                        <div class="d-flex flex-wrap gap-3">
                            <a href="index.php" class="btn btn-outline-custom action-btn">
                                <i class="bi bi-house me-1"></i>Back to Home
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Actions Tab -->
                <div class="tab-pane fade" id="actions" role="tabpanel" aria-labelledby="actions-tab">
                    <h4 class="section-title"><i class="bi bi-gear"></i> Profile Actions</h4>
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="info-card h-100">
                                <h5 class="fw-bold text-primary mb-3">Profile Visibility</h5>
                                <p class="mb-3">Control who can see your profile in search results.</p>
                                
                                <div class="mb-3">
                                    <span class="info-label">Current Status:</span>
                                    <span class="info-value">
                                        <?php if (isset($member['profile_hidden']) && $member['profile_hidden']): ?>
                                            <span class="status-badge status-hidden">
                                                <i class="bi bi-eye-slash me-1"></i>Hidden
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-visible">
                                                <i class="bi bi-eye me-1"></i>Visible
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <div class="d-flex flex-wrap gap-2 mt-3">
                                    <?php if ($profile_hide_enabled): ?>
                                        <?php if (isset($member['profile_hidden']) && $member['profile_hidden']): ?>
                                            <form method="POST" id="showForm2" style="display:inline;">
                                                <input type="hidden" name="show_member" value="1">
                                                <button type="submit" class="btn btn-outline-success action-btn" 
                                                        onclick="return confirm('Are you sure you want to make your profile visible to other members?')">
                                                    <i class="bi bi-eye me-1"></i>Show Profile
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" id="hideForm2" style="display:inline;">
                                                <input type="hidden" name="hide_member" value="1">
                                                <button type="button" class="btn btn-outline-custom action-btn" data-bs-toggle="modal" data-bs-target="#hideModal">
                                                    <i class="bi bi-eye-slash me-1"></i>Hide Profile
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-outline-custom action-btn" onclick="showUpgradeMessage()">
                                            <i class="bi bi-eye-slash me-1"></i>Hide Profile
                                        </button>
                                        <small class="text-muted d-block mt-2">Upgrade to premium to hide your profile</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <div class="info-card h-100">
                                <h5 class="fw-bold text-primary mb-3">Account Management</h5>
                                <p class="mb-3">Manage your account settings and status.</p>
                                
                                <div class="mb-3">
                                    <span class="info-label">Package:</span>
                                    <span class="info-value">
                                        <?php if ($package_active): ?>
                                            <?php echo htmlspecialchars($package_name); ?> (expires <?php echo date('Y-m-d', strtotime($package_expires)); ?>)
                                        <?php else: ?>
                                            Free User
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <div class="d-flex flex-wrap gap-2 mt-3">
                                    <a href="edit_profile.php" class="btn btn-custom action-btn">
                                        <i class="bi bi-pencil-square me-1"></i>Edit Profile
                                    </a>
                                    
                                    <?php if ($member['marital_status'] === 'Deactivated'): ?>
                                        <form method="POST" id="reactivateForm2" style="display:inline;">
                                            <input type="hidden" name="reactivate_member" value="1">
                                            <button type="button" class="btn btn-outline-success action-btn" data-bs-toggle="modal" data-bs-target="#reactivateModal">
                                                <i class="bi bi-person-check me-1"></i>Reactivate
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" id="deactivateForm2" style="display:inline;">
                                            <input type="hidden" name="deactivate_member" value="1">
                                            <button type="button" class="btn btn-outline-custom action-btn" data-bs-toggle="modal" data-bs-target="#deactivateModal">
                                                <i class="bi bi-person-x me-1"></i>Deactivate
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <h5 class="fw-bold text-primary mb-3">Quick Links</h5>
                        <div class="d-flex flex-wrap gap-3">
                            <a href="index.php" class="btn btn-outline-custom action-btn">
                                <i class="bi bi-house me-1"></i>Back to Home
                            </a>
                            <a href="package.php" class="btn btn-outline-custom action-btn">
                                <i class="bi bi-gem me-1"></i>Upgrade Package
                            </a>
                            <a href="members.php" class="btn btn-outline-custom action-btn">
                                <i class="bi bi-search me-1"></i>Browse Members
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Hide confirmation modal -->
    <div class="modal fade" id="hideModal" tabindex="-1" aria-labelledby="hideModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="hideModalLabel">Confirm Hide Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to hide your profile? Your profile will not be visible in search results.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" onclick="document.getElementById('hideForm').submit();">
                        <i class="bi bi-eye-slash me-1"></i>Hide Profile
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Deactivate confirmation modal -->
    <div class="modal fade" id="deactivateModal" tabindex="-1" aria-labelledby="deactivateModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deactivateModalLabel">Confirm Deactivation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to deactivate your profile? This will set your marital status to Deactivated.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="if(document.getElementById('deactivateForm')) document.getElementById('deactivateForm').submit(); if(document.getElementById('deactivateForm2')) document.getElementById('deactivateForm2').submit();">
                        <i class="bi bi-person-x me-1"></i>Deactivate
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reactivate confirmation modal -->
    <div class="modal fade" id="reactivateModal" tabindex="-1" aria-labelledby="reactivateModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reactivateModalLabel">Confirm Reactivation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to reactivate your profile? This will set your marital status to Single and make your profile active again.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="if(document.getElementById('reactivateForm')) document.getElementById('reactivateForm').submit(); if(document.getElementById('reactivateForm2')) document.getElementById('reactivateForm2').submit();">
                        <i class="bi bi-person-check me-1"></i>Reactivate
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Initialize Bootstrap tabs
            var triggerTabList = [].slice.call(document.querySelectorAll('#profileTabs button'))
            triggerTabList.forEach(function (triggerEl) {
                var tabTrigger = new bootstrap.Tab(triggerEl)
                
                triggerEl.addEventListener('click', function (event) {
                    event.preventDefault()
                    tabTrigger.show()
                })
            })
            
            // Clean up any stray backdrops
            document.querySelectorAll('.modal-backdrop').forEach(function(backdrop) {
                backdrop.parentNode && backdrop.parentNode.removeChild(backdrop);
            });
            
            // Ensure modals are properly initialized and clickable
            var deactivateModal = document.getElementById('deactivateModal');
            if(deactivateModal) {
                new bootstrap.Modal(deactivateModal);
            }
            
            var reactivateModal = document.getElementById('reactivateModal');
            if(reactivateModal) {
                new bootstrap.Modal(reactivateModal);
            }
            
            // Remove any modal-open class that might be stuck
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            
            // Make sure modals are above other content
            if(deactivateModal) deactivateModal.style.zIndex = '1050';
            if(reactivateModal) reactivateModal.style.zIndex = '1050';
        });

        // Function to show upgrade message for profile hide feature
        function showUpgradeMessage() {
            // Create and show a Bootstrap toast
            const toastHtml = `
                <div class="toast align-items-center text-white bg-warning border-0 position-fixed top-0 end-0 m-3" 
                     role="alert" aria-live="assertive" aria-atomic="true" id="upgradeToast" 
                     style="z-index: 1055;">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="bi bi-crown-fill me-2"></i>
                            <strong>Upgrade Required!</strong><br>
                            Profile hide feature is available only for premium users. Please upgrade your package to access this feature.
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" 
                                data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body pt-0">
                        <a href="package.php" class="btn btn-light btn-sm">
                            <i class="bi bi-arrow-up me-1"></i>Upgrade Now
                        </a>
                    </div>
                </div>
            `;
            
            // Remove any existing upgrade toast
            const existingToast = document.getElementById('upgradeToast');
            if (existingToast) {
                existingToast.remove();
            }
            
            // Add the toast to the body
            document.body.insertAdjacentHTML('beforeend', toastHtml);
            
            // Show the toast
            const toastElement = document.getElementById('upgradeToast');
            const toast = new bootstrap.Toast(toastElement, {
                autohide: true,
                delay: 5000
            });
            toast.show();
            
            // Remove the toast element after it's hidden
            toastElement.addEventListener('hidden.bs.toast', function() {
                this.remove();
            });
        }

        // Function to view photo in a modal
        function viewPhotoModal(photoPath, photoTitle) {
            // Create modal HTML
            const modalHtml = `
                <div class="modal fade" id="photoModal" tabindex="-1" aria-labelledby="photoModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content" style="border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
                            <div class="modal-header" style="border: none;">
                                <h5 class="modal-title" id="photoModalLabel">${photoTitle}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-center" style="padding: 0; margin-bottom:20px;">
                                <img src="${photoPath}" alt="${photoTitle}" style="max-width: 100%; height: auto; max-height: 600px;">
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove any existing modal
            const existingModal = document.getElementById('photoModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add new modal to page
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show the modal
            const photoModal = new bootstrap.Modal(document.getElementById('photoModal'));
            photoModal.show();
            
            // Remove modal after it's hidden
            document.getElementById('photoModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }
    </script>

    <!-- Add/Edit Photos Modal -->
    <div class="modal fade" id="editPhotosModal" tabindex="-1" aria-labelledby="editPhotosModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPhotosModalLabel"><i class="bi bi-images me-2"></i>Manage Photos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="editPhotosAlert"></div>

                    <div class="mb-3">
                        <label for="newPhotosInput" class="form-label">Add new photos</label>
                        <input type="file" id="newPhotosInput" name="additional_photos[]" multiple accept="image/*" class="form-control">
                        <div class="form-text">You can select multiple images. Max 5MB each.</div>
                    </div>

                    <div class="mb-3">
                        <button id="uploadNewPhotosBtn" class="btn btn-primary">Upload Selected Photos</button>
                    </div>

                    <hr>

                    <h6 class="mb-2">Existing Photos</h6>
                    <div id="existingPhotosList" class="row gy-3"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function(){
            const memberId = <?php echo isset($member_id) ? intval($member_id) : 0; ?>;
            const editPhotosBtn = document.getElementById('openEditPhotosBtn');
            const editPhotosModalEl = document.getElementById('editPhotosModal');
            const editPhotosModal = editPhotosModalEl ? new bootstrap.Modal(editPhotosModalEl) : null;

            function showAlert(container, type, message) {
                container.innerHTML = `<div class="alert alert-${type} alert-dismissible">${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
            }

            async function loadExistingPhotos() {
                const list = document.getElementById('existingPhotosList');
                list.innerHTML = '<div class="text-center p-3">Loading...</div>';
                try {
                    const res = await fetch('profile.php?get_member_photos=1&member_id=' + encodeURIComponent(memberId), { credentials: 'same-origin' });
                    if (!res.ok) throw new Error('Network error');
                    const data = await res.json();
                    if (!data.success) throw new Error(data.error || 'API returned failure');
                    const photos = data.photos || [];
                    if (photos.length === 0) {
                        list.innerHTML = '<div class="col-12"><div class="alert alert-info">No photos uploaded yet.</div></div>';
                        return;
                    }
                    let html = '';
                    photos.forEach(p => {
                        const src = (function(photo){ photo = photo ? photo.replace(/^\/+/, '') : ''; if (/^https?:\/\//i.test(photo)) return photo; return photo.startsWith('uploads/') ? photo : 'uploads/' + photo; })(p.photo_path);
                        const id = p.id || '';
                        // Only show delete button for additional photos (not main profile photo)
                        const deleteButton = (!p.is_main && id) ? `<button class="btn btn-sm btn-danger me-2" data-photo-id="${id}" data-photo-src="${src}">Delete</button>` : '';
                        const photoLabel = p.is_main ? '<span class="badge bg-primary position-absolute top-0 start-0 m-2">Main Photo</span>' : '';
                        
                        html += `
                            <div class="col-md-4">
                                <div class="card position-relative">
                                    <img src="${src}" class="card-img-top" style="height:160px;object-fit:cover;" />
                                    ${photoLabel}
                                    <div class="card-body text-center">
                                        ${deleteButton}
                                        <button class="btn btn-sm btn-outline-secondary" onclick="viewPhotoModal('${src.replace(/'/g, "\\'")}','Photo')">View</button>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    list.innerHTML = html;
                    // attach delete handlers
                    list.querySelectorAll('button[data-photo-id]').forEach(btn => {
                        btn.addEventListener('click', async function(){
                            const pid = this.getAttribute('data-photo-id');
                            if (!confirm('Delete this photo?')) return;
                            try {
                                const resp = await fetch('profile.php?action=delete', {
                                    method: 'POST',
                                    credentials: 'same-origin',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: 'photo_id=' + encodeURIComponent(pid)
                                });
                                const j = await resp.json();
                                if (j.success) {
                                    loadExistingPhotos();
                                    // refresh main photos area
                                    if (typeof loadProfilePhotos === 'function') loadProfilePhotos(memberId);
                                    showAlert(document.getElementById('editPhotosAlert'), 'success', 'Photo deleted');
                                } else {
                                    showAlert(document.getElementById('editPhotosAlert'), 'danger', j.error || 'Delete failed');
                                }
                            } catch (err) {
                                console.error(err);
                                showAlert(document.getElementById('editPhotosAlert'), 'danger', 'Network or server error');
                            }
                        });
                    });
                } catch (err) {
                    console.error(err);
                    list.innerHTML = '<div class="col-12"><div class="alert alert-danger">Failed to load photos.</div></div>';
                }
            }

            if (editPhotosBtn && editPhotosModal) {
                editPhotosBtn.addEventListener('click', function(){
                    if (!memberId || memberId <= 0) {
                        alert('No member available to edit photos.');
                        return;
                    }
                    document.getElementById('newPhotosInput').value = null;
                    document.getElementById('existingPhotosList').innerHTML = '';
                    document.getElementById('editPhotosAlert').innerHTML = '';
                    loadExistingPhotos();
                    editPhotosModal.show();
                });
            }

            // Upload handler
            const uploadBtn = document.getElementById('uploadNewPhotosBtn');
            if (uploadBtn) {
                uploadBtn.addEventListener('click', async function(e){
                    e.preventDefault();
                    
                    // Prevent multiple clicks by checking if already uploading
                    if (uploadBtn.disabled) return;
                    
                    const input = document.getElementById('newPhotosInput');
                    if (!input || !input.files || input.files.length === 0) {
                        showAlert(document.getElementById('editPhotosAlert'), 'warning', 'Please select at least one file');
                        return;
                    }
                    
                    // Set loading state
                    uploadBtn.disabled = true;
                    const originalText = uploadBtn.innerHTML;
                    uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Uploading...';
                    
                    const fd = new FormData();
                    for (let i=0;i<input.files.length;i++) fd.append('additional_photos[]', input.files[i]);
                    fd.append('member_id', memberId);

                    try {
                        const resp = await fetch('profile.php?action=upload', {
                            method: 'POST',
                            credentials: 'same-origin',
                            body: fd
                        });
                        const j = await resp.json();
                        if (j.success) {
                            showAlert(document.getElementById('editPhotosAlert'), 'success', 'Uploaded successfully');
                            loadExistingPhotos();
                            if (typeof loadProfilePhotos === 'function') loadProfilePhotos(memberId);
                            // clear selection
                            document.getElementById('newPhotosInput').value = null;
                        } else {
                            showAlert(document.getElementById('editPhotosAlert'), 'danger', j.error || 'Upload failed');
                        }
                    } catch (err) {
                        console.error(err);
                        showAlert(document.getElementById('editPhotosAlert'), 'danger', 'Network or server error');
                    } finally {
                        // Reset button state
                        uploadBtn.disabled = false;
                        uploadBtn.innerHTML = originalText;
                    }
                });
            }
        })();
    </script>
</body>
</html>