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
        $upload_dir = '/home10/thennilavu/public_html/uploads/';
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
    <title>My Profile - Thennilavu Matrimony</title>
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

        /* Profile Header Card */
        .profile-card {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            box-shadow: var(--card-shadow);
        }

        .profile-image-container {
            text-align: center;
            margin-bottom: 20px;
        }

        .profile-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .profile-name {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 5px;
            text-align: center;
        }

        .profile-details {
            color: var(--text-secondary);
            font-size: 0.9rem;
            text-align: center;
            margin-bottom: 20px;
        }

        .status-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            margin-bottom: 20px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .status-visible {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .status-hidden {
            background-color: #fff3e0;
            color: #ef6c00;
        }

        .status-package {
            background-color: #e3f2fd;
            color: #1565c0;
        }

        .profile-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
        }

        .action-btn {
            padding: 10px 16px;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(233, 30, 99, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-warning {
            background: var(--warning-color);
            color: white;
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        /* Tab Navigation */
        .tab-navigation {
            display: flex;
            overflow-x: auto;
            margin-bottom: 20px;
            padding: 10px 0;
            scrollbar-width: none;
            -ms-overflow-style: none;
            gap: 8px;
        }

        .tab-navigation::-webkit-scrollbar {
            display: none;
        }

        .tab-button {
            flex: 0 0 auto;
            padding: 10px 16px;
            border: none;
            background: var(--bg-secondary);
            color: var(--text-secondary);
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .tab-button:hover {
            background: var(--bg-tertiary);
        }

        .tab-button.active {
            background: var(--primary-color);
            color: white;
        }

        /* Tab Content */
        .tab-content {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
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

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
        }

        .info-card {
            background: var(--bg-primary);
            border-radius: var(--radius-md);
            padding: 16px;
            border: 1px solid var(--border-color);
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .info-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: var(--text-primary);
            flex: 1;
        }

        .info-value {
            color: var(--text-secondary);
            flex: 1;
            text-align: right;
        }

        /* Quick Actions Panel */
        .quick-actions-panel {
            background: var(--bg-primary);
            border-radius: var(--radius-md);
            padding: 20px;
            margin-top: 20px;
            border: 1px solid var(--border-color);
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
        }

        .action-card {
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            padding: 16px;
            text-align: center;
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .action-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-shadow);
        }

        .action-icon {
            font-size: 1.5rem;
            margin-bottom: 8px;
            color: var(--primary-color);
        }

        .action-text {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        /* Photos Grid */
        .photos-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .photo-card {
            position: relative;
            border-radius: var(--radius-md);
            overflow: hidden;
            aspect-ratio: 1;
        }

        .photo-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.7));
            padding: 8px;
            display: flex;
            justify-content: space-between;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .photo-card:hover .photo-overlay {
            opacity: 1;
        }

        .photo-badge {
            background: var(--primary-color);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
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

        .toast.warning {
            background: var(--warning-color);
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

        .php-message.warning {
            background: var(--warning-color);
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

        .form-input {
            width: 100%;
            padding: 14px 16px;
            background: var(--bg-primary);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            color: var(--text-primary);
            transition: var(--transition);
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            padding: 24px;
            max-width: 400px;
            width: 100%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: var(--elevated-shadow);
        }

        .modal-header {
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .modal-body {
            margin-bottom: 20px;
            color: var(--text-secondary);
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        /* Responsive Adjustments */
        @media (min-width: 768px) {
            .app-container {
                max-width: 480px;
                margin: 0 auto;
                border-left: 1px solid var(--border-color);
                border-right: 1px solid var(--border-color);
            }
            
            .photos-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 380px) {
            .profile-image {
                width: 100px;
                height: 100px;
            }
            
            .hero-title {
                font-size: 1.5rem;
            }
            
            .profile-name {
                font-size: 1.2rem;
            }
            
            .tab-button {
                padding: 8px 12px;
                font-size: 0.85rem;
            }
            
            .photos-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 320px) {
            .hero-title {
                font-size: 1.3rem;
            }
            
            .header-title {
                font-size: 1.1rem;
            }
            
            .profile-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .action-btn {
                width: 100%;
            }
        }

        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.5s ease forwards;
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
        <?php if (isset($flash_success)): ?>
            <div class="php-message">
                <?php echo $flash_success; ?>
            </div>
            <script>
                setTimeout(() => {
                    document.querySelector('.php-message').style.display = 'none';
                }, 5000);
            </script>
        <?php endif; ?>
        
        <?php if (isset($flash_error)): ?>
            <div class="php-message error">
                <?php echo $flash_error; ?>
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
                <h1 class="header-title">My Profile</h1>
            </div>
            <button class="theme-toggle" id="themeToggle">
                <i class="fas fa-moon"></i>
            </button>
        </header>

        <!-- Hero Section -->
        <?php if (!$member): ?>
            <section class="hero-section">
                <div class="hero-icon">
                    <i class="fas fa-user-circle"></i>
                </div>
                <h1 class="hero-title">Complete Your Profile</h1>
                <p class="hero-subtitle">Please complete your registration to view your profile</p>
                <div style="margin-top: 20px;">
                    <a href="members.php" class="action-btn btn-primary" style="display: inline-block;">
                        <i class="fas fa-user-plus"></i>
                        Complete Registration
                    </a>
                </div>
            </section>
        <?php else: ?>
            <section class="hero-section">
                <div class="hero-icon">
                    <i class="fas fa-user-circle"></i>
                </div>
                <h1 class="hero-title"><?php echo htmlspecialchars($member['name']); ?></h1>
                <p class="hero-subtitle"><?php echo htmlspecialchars($member['profession'] . ' • ' . $member['city'] . ', ' . $member['country']); ?></p>
            </section>

            <!-- Profile Header -->
            <div class="content-container">
                <div class="profile-card fade-in">
                    <div class="profile-image-container">
                        <img src="http://thennilavu.lk/<?php echo htmlspecialchars($member['photo'] ?: 'img/default-avatar.png'); ?>" 
                             alt="Profile Photo" class="profile-image">
                    </div>
                    
                    <h2 class="profile-name"><?php echo htmlspecialchars($member['name']); ?></h2>
                    
                    <div class="profile-details">
                        <?php echo htmlspecialchars($member['gender'] . ' • ' . $member['marital_status']); ?>
                    </div>
                    
                    <div class="status-badges">
                        <span class="status-badge <?php echo (isset($member['profile_hidden']) && $member['profile_hidden']) ? 'status-hidden' : 'status-visible'; ?>">
                            <i class="fas <?php echo (isset($member['profile_hidden']) && $member['profile_hidden']) ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                            <?php echo (isset($member['profile_hidden']) && $member['profile_hidden']) ? 'Hidden' : 'Visible'; ?>
                        </span>
                        
                        <span class="status-badge status-package">
                            <i class="fas fa-crown"></i>
                            <?php if ($package_active): ?>
                                <?php echo htmlspecialchars($package_name); ?>
                            <?php else: ?>
                                Free User
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <div class="profile-actions">
                        <a href="edit_profile.php" class="action-btn btn-outline">
                            <i class="fas fa-edit"></i>
                            Edit
                        </a>
                        
                        <?php if ($profile_hide_enabled): ?>
                            <?php if (isset($member['profile_hidden']) && $member['profile_hidden']): ?>
                                <form method="POST" id="showForm" style="display:inline;">
                                    <input type="hidden" name="show_member" value="1">
                                    <button type="submit" class="action-btn btn-success">
                                        <i class="fas fa-eye"></i>
                                        Show
                                    </button>
                                </form>
                            <?php else: ?>
                                <button type="button" class="action-btn btn-warning" onclick="showHideModal()">
                                    <i class="fas fa-eye-slash"></i>
                                    Hide
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <button type="button" class="action-btn btn-warning" onclick="showUpgradeMessage()">
                                <i class="fas fa-eye-slash"></i>
                                Hide
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($member['marital_status'] === 'Deactivated'): ?>
                            <button type="button" class="action-btn btn-success" onclick="showReactivateModal()">
                                <i class="fas fa-user-check"></i>
                                Reactivate
                            </button>
                        <?php else: ?>
                            <button type="button" class="action-btn btn-danger" onclick="showDeactivateModal()">
                                <i class="fas fa-user-slash"></i>
                                Deactivate
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tab Navigation -->
                <div class="tab-navigation">
                    <button class="tab-button active" data-tab="physical">
                        <i class="fas fa-user"></i>
                        Physical
                    </button>
                    <button class="tab-button" data-tab="partner">
                        <i class="fas fa-heart"></i>
                        Partner
                    </button>
                    <button class="tab-button" data-tab="education">
                        <i class="fas fa-graduation-cap"></i>
                        Education
                    </button>
                    <button class="tab-button" data-tab="family">
                        <i class="fas fa-users"></i>
                        Family
                    </button>
                    <button class="tab-button" data-tab="horoscope">
                        <i class="fas fa-star"></i>
                        Horoscope
                    </button>
                    <button class="tab-button" data-tab="photos">
                        <i class="fas fa-images"></i>
                        Photos
                    </button>
                    <button class="tab-button" data-tab="actions">
                        <i class="fas fa-cog"></i>
                        Actions
                    </button>
                </div>

                <!-- Tab Content -->
                <div id="physical" class="tab-content active">
                    <h3 class="section-title">
                        <i class="fas fa-user"></i>
                        Physical Information
                    </h3>
                    
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
                        <p class="text-center" style="color: var(--text-muted); padding: 40px 0;">
                            No physical information available
                        </p>
                    <?php endif; ?>
                    
                    <div class="quick-actions-panel">
                        <div class="actions-grid">
                            <a href="edit_profile.php" class="action-card">
                                <div class="action-icon">
                                    <i class="fas fa-edit"></i>
                                </div>
                                <div class="action-text">Edit Profile</div>
                            </a>
                            <a href="package.php" class="action-card">
                                <div class="action-icon">
                                    <i class="fas fa-crown"></i>
                                </div>
                                <div class="action-text">Upgrade Package</div>
                            </a>
                            <a href="index.php" class="action-card">
                                <div class="action-icon">
                                    <i class="fas fa-home"></i>
                                </div>
                                <div class="action-text">Back to Home</div>
                            </a>
                        </div>
                    </div>
                </div>

                <div id="partner" class="tab-content">
                    <h3 class="section-title">
                        <i class="fas fa-heart"></i>
                        Partner Expectations
                    </h3>
                    
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
                        <p class="text-center" style="color: var(--text-muted); padding: 40px 0;">
                            No partner expectations available
                        </p>
                    <?php endif; ?>
                    
                    <div class="quick-actions-panel">
                        <div class="actions-grid">
                            <a href="edit_profile.php" class="action-card">
                                <div class="action-icon">
                                    <i class="fas fa-edit"></i>
                                </div>
                                <div class="action-text">Edit Profile</div>
                            </a>
                            <a href="package.php" class="action-card">
                                <div class="action-icon">
                                    <i class="fas fa-crown"></i>
                                </div>
                                <div class="action-text">Upgrade Package</div>
                            </a>
                            <a href="index.php" class="action-card">
                                <div class="action-icon">
                                    <i class="fas fa-home"></i>
                                </div>
                                <div class="action-text">Back to Home</div>
                            </a>
                        </div>
                    </div>
                </div>

                <div id="education" class="tab-content">
                    <h3 class="section-title">
                        <i class="fas fa-graduation-cap"></i>
                        Education
                    </h3>
                    
                    <?php if ($education): ?>
                        <div class="info-grid">
                            <?php foreach ($education as $edu): ?>
                                <div class="info-card">
                                    <div class="info-item">
                                        <span class="info-label">Level:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($edu['level']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Institute:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($edu['school_or_institute']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Degree:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($edu['stream_or_degree']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Field:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($edu['field']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Reg. Number:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($edu['reg_number']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Duration:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($edu['start_year'] . ' - ' . $edu['end_year']); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center" style="color: var(--text-muted); padding: 40px 0;">
                            No education information available
                        </p>
                    <?php endif; ?>
                    
                    <div class="quick-actions-panel">
                        <div class="actions-grid">
                            <a href="edit_profile.php" class="action-card">
                                <div class="action-icon">
                                    <i class="fas fa-edit"></i>
                                </div>
                                <div class="action-text">Edit Profile</div>
                            </a>
                            <a href="package.php" class="action-card">
                                <div class="action-icon">
                                    <i class="fas fa-crown"></i>
                                </div>
                                <div class="action-text">Upgrade Package</div>
                            </a>
                            <a href="index.php" class="action-card">
                                <div class="action-icon">
                                    <i class="fas fa-home"></i>
                                </div>
                                <div class="action-text">Back to Home</div>
                            </a>
                        </div>
                    </div>
                </div>

                <div id="family" class="tab-content">
                    <h3 class="section-title">
                        <i class="fas fa-users"></i>
                        Family Information
                    </h3>
                    
                    <?php if ($family): ?>
                        <div class="info-grid">
                            <div class="info-card">
                                <h4 style="color: var(--primary-color); margin-bottom: 15px; font-size: 1rem;">Father's Information</h4>
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
                                <h4 style="color: var(--primary-color); margin-bottom: 15px; font-size: 1rem;">Mother's Information</h4>
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
                                <h4 style="color: var(--primary-color); margin-bottom: 15px; font-size: 1rem;">Siblings</h4>
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
                        <p class="text-center" style="color: var(--text-muted); padding: 40px 0;">
                            No family information available
                        </p>
                    <?php endif; ?>
                    
                    <div class="quick-actions-panel">
                        <div class="actions-grid">
                            <a href="edit_profile.php" class="action-card">
                                <div class="action-icon">
                                    <i class="fas fa-edit"></i>
                                </div>
                                <div class="action-text">Edit Profile</div>
                            </a>
                            <a href="package.php" class="action-card">
                                <div class="action-icon">
                                    <i class="fas fa-crown"></i>
                                </div>
                                <div class="action-text">Upgrade Package</div>
                            </a>
                            <a href="index.php" class="action-card">
                                <div class="action-icon">
                                    <i class="fas fa-home"></i>
                                </div>
                                <div class="action-text">Back to Home</div>
                            </a>
                        </div>
                    </div>
                </div>

                <div id="horoscope" class="tab-content">
                    <h3 class="section-title">
                        <i class="fas fa-star"></i>
                        Horoscope
                    </h3>
                    
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
                            <div style="margin-top: 20px;">
                                <h4 style="color: var(--primary-color); margin-bottom: 15px; font-size: 1rem;">Charts</h4>
                                <div style="display: flex; flex-wrap: wrap; gap: 15px; justify-content: center;">
                                    <?php if ($horoscope['planet_image']): ?>
                                        <div style="flex: 1; min-width: 200px;">
                                            <h5 style="font-size: 0.9rem; margin-bottom: 8px; color: var(--text-secondary);">Planet Chart</h5>
                                            <img src="http://thennilavu.lk/<?php echo htmlspecialchars($horoscope['planet_image']); ?>" 
                                                 alt="Planet Chart" style="width: 100%; height: 150px; object-fit: cover; border-radius: var(--radius-md);">
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($horoscope['navamsha_image']): ?>
                                        <div style="flex: 1; min-width: 200px;">
                                            <h5 style="font-size: 0.9rem; margin-bottom: 8px; color: var(--text-secondary);">Navamsha Chart</h5>
                                            <img src="http://thennilavu.lk/<?php echo htmlspecialchars($horoscope['navamsha_image']); ?>" 
                                                 alt="Navamsha Chart" style="width: 100%; height: 150px; object-fit: cover; border-radius: var(--radius-md);">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-center" style="color: var(--text-muted); padding: 40px 0;">
                            No horoscope information available
                        </p>
                    <?php endif; ?>
                    
                    <div class="quick-actions-panel">
                        <div class="actions-grid">
                            <a href="edit_profile.php" class="action-card">
                                <div class="action-icon">
                                    <i class="fas fa-edit"></i>
                                </div>
                                <div class="action-text">Edit Profile</div>
                            </a>
                            <a href="package.php" class="action-card">
                                <div class="action-icon">
                                    <i class="fas fa-crown"></i>
                                </div>
                                <div class="action-text">Upgrade Package</div>
                            </a>
                            <a href="index.php" class="action-card">
                                <div class="action-icon">
                                    <i class="fas fa-home"></i>
                                </div>
                                <div class="action-text">Back to Home</div>
                            </a>
                        </div>
                    </div>
                </div>

                <div id="photos" class="tab-content">
                    <h3 class="section-title">
                        <i class="fas fa-images"></i>
                        Additional Photos
                        <button type="button" class="action-btn btn-primary" style="margin-left: auto; padding: 6px 12px; font-size: 0.8rem;" onclick="openEditPhotosModal()">
                            <i class="fas fa-camera"></i>
                            Manage
                        </button>
                    </h3>
                    
                    <div id="photosContent" style="min-height: 200px;">
                        <div style="text-align: center; padding: 40px 0; color: var(--text-muted);">
                            <i class="fas fa-image" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                            <p>Loading photos...</p>
                        </div>
                    </div>
                    
                    <div class="quick-actions-panel">
                        <div class="actions-grid">
                            <a href="index.php" class="action-card">
                                <div class="action-icon">
                                    <i class="fas fa-home"></i>
                                </div>
                                <div class="action-text">Back to Home</div>
                            </a>
                        </div>
                    </div>
                </div>

                <div id="actions" class="tab-content">
                    <h3 class="section-title">
                        <i class="fas fa-cog"></i>
                        Profile Actions
                    </h3>
                    
                    <div class="info-grid">
                        <div class="info-card">
                            <h4 style="color: var(--primary-color); margin-bottom: 15px; font-size: 1rem;">Profile Visibility</h4>
                            <div class="info-item">
                                <span class="info-label">Status:</span>
                                <span class="info-value">
                                    <?php if (isset($member['profile_hidden']) && $member['profile_hidden']): ?>
                                        <span class="status-badge status-hidden" style="padding: 4px 10px;">
                                            <i class="fas fa-eye-slash"></i> Hidden
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-visible" style="padding: 4px 10px;">
                                            <i class="fas fa-eye"></i> Visible
                                        </span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                                <?php if ($profile_hide_enabled): ?>
                                    <?php if (isset($member['profile_hidden']) && $member['profile_hidden']): ?>
                                        <form method="POST" id="showForm2" style="display:inline;">
                                            <input type="hidden" name="show_member" value="1">
                                            <button type="submit" class="action-btn btn-success">
                                                <i class="fas fa-eye"></i>
                                                Show Profile
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="action-btn btn-warning" onclick="showHideModal()">
                                            <i class="fas fa-eye-slash"></i>
                                            Hide Profile
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button type="button" class="action-btn btn-warning" onclick="showUpgradeMessage()">
                                        <i class="fas fa-eye-slash"></i>
                                        Hide Profile
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="info-card">
                            <h4 style="color: var(--primary-color); margin-bottom: 15px; font-size: 1rem;">Account Management</h4>
                            <div class="info-item">
                                <span class="info-label">Package:</span>
                                <span class="info-value">
                                    <?php if ($package_active): ?>
                                        <?php echo htmlspecialchars($package_name); ?>
                                    <?php else: ?>
                                        Free User
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <?php if ($package_active && $package_expires): ?>
                                <div class="info-item">
                                    <span class="info-label">Expires:</span>
                                    <span class="info-value"><?php echo date('Y-m-d', strtotime($package_expires)); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                                <a href="edit_profile.php" class="action-btn btn-outline">
                                    <i class="fas fa-edit"></i>
                                    Edit Profile
                                </a>
                                
                                <?php if ($member['marital_status'] === 'Deactivated'): ?>
                                    <button type="button" class="action-btn btn-success" onclick="showReactivateModal()">
                                        <i class="fas fa-user-check"></i>
                                        Reactivate
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="action-btn btn-danger" onclick="showDeactivateModal()">
                                        <i class="fas fa-user-slash"></i>
                                        Deactivate
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="quick-actions-panel">
                        <h4 style="color: var(--primary-color); margin-bottom: 15px; font-size: 1rem;">Quick Links</h4>
                        <div class="actions-grid">
                            <a href="index.php" class="action-card">
                                <div class="action-icon">
                                    <i class="fas fa-home"></i>
                                </div>
                                <div class="action-text">Home</div>
                            </a>
                            <a href="package.php" class="action-card">
                                <div class="action-icon">
                                    <i class="fas fa-crown"></i>
                                </div>
                                <div class="action-text">Upgrade</div>
                            </a>
                            <a href="members.php" class="action-card">
                                <div class="action-icon">
                                    <i class="fas fa-search"></i>
                                </div>
                                <div class="action-text">Browse</div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

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
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="profile.php" class="nav-item active">
                    <i class="fas fa-user-circle"></i>
                    <span>Profile</span>
                </a>
            <?php else: ?>
                <a href="login.php" class="nav-item">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Login</span>
                </a>
            <?php endif; ?>
        </nav>

        <!-- Toast Notification -->
        <div class="toast" id="toast"></div>

        <!-- Hide Profile Modal -->
        <div class="modal-overlay" id="hideModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Confirm Hide Profile</h3>
                </div>
                <div class="modal-body">
                    Are you sure you want to hide your profile? Your profile will not be visible in search results.
                </div>
                <div class="modal-footer">
                    <button type="button" class="action-btn btn-outline" onclick="closeModal('hideModal')">
                        Cancel
                    </button>
                    <form method="POST" id="hideForm" style="display:inline;">
                        <input type="hidden" name="hide_member" value="1">
                        <button type="submit" class="action-btn btn-warning">
                            <i class="fas fa-eye-slash"></i>
                            Hide Profile
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Deactivate Modal -->
        <div class="modal-overlay" id="deactivateModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Confirm Deactivation</h3>
                </div>
                <div class="modal-body">
                    Are you sure you want to deactivate your profile? This will set your marital status to Deactivated.
                </div>
                <div class="modal-footer">
                    <button type="button" class="action-btn btn-outline" onclick="closeModal('deactivateModal')">
                        Cancel
                    </button>
                    <form method="POST" id="deactivateForm" style="display:inline;">
                        <input type="hidden" name="deactivate_member" value="1">
                        <button type="submit" class="action-btn btn-danger">
                            <i class="fas fa-user-slash"></i>
                            Deactivate
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Reactivate Modal -->
        <div class="modal-overlay" id="reactivateModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Confirm Reactivation</h3>
                </div>
                <div class="modal-body">
                    Are you sure you want to reactivate your profile? This will set your marital status to Single and make your profile active again.
                </div>
                <div class="modal-footer">
                    <button type="button" class="action-btn btn-outline" onclick="closeModal('reactivateModal')">
                        Cancel
                    </button>
                    <form method="POST" id="reactivateForm" style="display:inline;">
                        <input type="hidden" name="reactivate_member" value="1">
                        <button type="submit" class="action-btn btn-success">
                            <i class="fas fa-user-check"></i>
                            Reactivate
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Photos Modal -->
        <div class="modal-overlay" id="editPhotosModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">
                        <i class="fas fa-images"></i>
                        Manage Photos
                    </h3>
                    <button type="button" class="back-button" onclick="closeModal('editPhotosModal')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="editPhotosAlert"></div>

                    <div class="form-group">
                        <label class="form-label">Add new photos</label>
                        <input type="file" id="newPhotosInput" name="additional_photos[]" multiple accept="image/*" class="form-input">
                        <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 5px;">
                            You can select multiple images. Max 5MB each.
                        </div>
                    </div>

                    <button id="uploadNewPhotosBtn" class="action-btn btn-primary" style="width: 100%; margin-bottom: 20px;">
                        <i class="fas fa-upload"></i>
                        Upload Selected Photos
                    </button>

                    <hr style="margin: 20px 0; border-color: var(--border-color);">

                    <h4 style="margin-bottom: 15px; color: var(--text-primary);">Existing Photos</h4>
                    <div id="existingPhotosList" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;"></div>
                </div>
            </div>
        </div>
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
                window.location.href = 'index.php';
            }
        }
        
        // Tab Navigation
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', () => {
                // Remove active class from all tabs and buttons
                document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                
                // Add active class to clicked button and corresponding content
                button.classList.add('active');
                const tabId = button.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
            });
        });
        
        // Modal Functions
        function showHideModal() {
            document.getElementById('hideModal').classList.add('active');
        }
        
        function showDeactivateModal() {
            document.getElementById('deactivateModal').classList.add('active');
        }
        
        function showReactivateModal() {
            document.getElementById('reactivateModal').classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        // Close modals when clicking outside
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('active');
                }
            });
        });
        
        // Function to show upgrade message for profile hide feature
        function showUpgradeMessage() {
            const toast = document.getElementById('toast');
            toast.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-crown" style="font-size: 1.2rem;"></i>
                    <div>
                        <strong>Upgrade Required!</strong><br>
                        Profile hide feature is available only for premium users.
                    </div>
                </div>
                <div style="margin-top: 10px;">
                    <a href="package.php" class="action-btn btn-primary" style="font-size: 0.9rem; padding: 8px 16px;">
                        <i class="fas fa-arrow-up"></i> Upgrade Now
                    </a>
                </div>
            `;
            toast.classList.add('show', 'warning');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 5000);
        }
        
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

        // Photo Loading Functions
        function normalizeClientPath(photo) {
            if (!photo) return '';
            photo = photo.replace(/^\/+/, '');
            if (/^https?:\/\//i.test(photo)) return photo;
            return photo.startsWith('uploads/') ? photo : 'uploads/' + photo;
        }

        async function loadProfilePhotos(memberId) {
            const container = document.getElementById('photosContent');
            if (!memberId || memberId <= 0) {
                container.innerHTML = `<div style="text-align: center; padding: 40px 0; color: var(--text-muted);">No member selected.</div>`;
                return;
            }

            try {
                const res = await fetch('profile.php?get_member_photos=1&member_id=' + encodeURIComponent(memberId), { credentials: 'same-origin' });
                if (!res.ok) throw new Error('Network response was not ok');
                const data = await res.json();
                if (!data.success) throw new Error(data.error || 'API error');

                const photos = data.photos || [];
                if (photos.length === 0) {
                    container.innerHTML = `
                        <div style="text-align: center; padding: 40px 0; color: var(--text-muted);">
                            <i class="fas fa-image" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                            <p>No Additional Photos Yet</p>
                            <p style="font-size: 0.9rem; margin-top: 5px;">Add photos to make your profile more attractive!</p>
                        </div>
                    `;
                    return;
                }

                const baseUrl = "https://thennilavu.lk/";

                // Build responsive grid
                let html = '<div class="photos-grid">';
                photos.forEach((p, idx) => {
                    const raw = p.photo_path || '';
                    const src = normalizeClientPath(raw);
                    const title = p.is_main ? 'Profile Photo' : ('Photo ' + (p.upload_order ?? (idx+1)));
                    html += `
                        <div class="photo-card">
                            <img src="${baseUrl}${src}" alt="${title}" loading="lazy">
                            <div class="photo-overlay">
                                <span class="photo-badge">
                                    <i class="fas fa-image"></i> ${p.is_main ? 'Main' : ('#' + (p.upload_order ?? (idx+1)))}
                                </span>
                                <button class="action-btn" style="padding: 4px 10px; font-size: 0.7rem; background: white; color: var(--text-primary);" onclick="viewPhotoModal('${src.replace(/'/g, "\\'")}', '${title.replace(/'/g, "\\'")}')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';

                // Summary
                html += `<div style="background: var(--success-color); color: white; padding: 12px; border-radius: var(--radius-md); margin-top: 15px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-check-circle" style="font-size: 1.2rem;"></i>
                    <span>You have ${photos.length} photo(s)</span>
                </div>`;

                container.innerHTML = html;

            } catch (err) {
                console.error('Failed to load photos', err);
                container.innerHTML = `<div style="background: var(--danger-color); color: white; padding: 12px; border-radius: var(--radius-md); text-align: center;">Unable to load photos. Please try again later.</div>`;
            }
        }

        // View Photo Modal
        function viewPhotoModal(photoPath, photoTitle) {
            const modalHtml = `
                <div class="modal-overlay active" id="photoModal">
                    <div class="modal-content" style="max-width: 90%; background: transparent; box-shadow: none;">
                        <div style="position: relative;">
                            <button class="action-btn" onclick="closeModal('photoModal')" style="position: absolute; top: 10px; right: 10px; z-index: 10; background: rgba(0,0,0,0.5); color: white;">
                                <i class="fas fa-times"></i>
                            </button>
                            <img src="${photoPath}" alt="${photoTitle}" style="width: 100%; height: auto; border-radius: var(--radius-md);">
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
        }

        // Edit Photos Modal Functions
        function openEditPhotosModal() {
            const memberId = <?php echo isset($member_id) ? intval($member_id) : 0; ?>;
            if (!memberId || memberId <= 0) {
                showToast('No member available to edit photos.', 'error');
                return;
            }
            
            document.getElementById('newPhotosInput').value = null;
            document.getElementById('existingPhotosList').innerHTML = '';
            document.getElementById('editPhotosAlert').innerHTML = '';
            loadExistingPhotos();
            document.getElementById('editPhotosModal').classList.add('active');
        }

        function showAlert(container, type, message) {
            container.innerHTML = `
                <div style="background: ${type === 'success' ? 'var(--success-color)' : type === 'error' ? 'var(--danger-color)' : 'var(--warning-color)'}; 
                            color: white; padding: 12px; border-radius: var(--radius-md); margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                    <span>${message}</span>
                    <button onclick="this.parentElement.style.display='none'" style="background: none; border: none; color: white; cursor: pointer;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        }

        async function loadExistingPhotos() {
            const list = document.getElementById('existingPhotosList');
            const memberId = <?php echo isset($member_id) ? intval($member_id) : 0; ?>;
            
            list.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 20px; color: var(--text-muted);">Loading...</div>';
            
            try {
                const res = await fetch('profile.php?get_member_photos=1&member_id=' + encodeURIComponent(memberId), { credentials: 'same-origin' });
                if (!res.ok) throw new Error('Network error');
                const data = await res.json();
                if (!data.success) throw new Error(data.error || 'API returned failure');
                const photos = data.photos || [];
                
                if (photos.length === 0) {
                    list.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 20px; color: var(--text-muted);">No photos uploaded yet.</div>';
                    return;
                }
                
                let html = '';
                photos.forEach(p => {
                    const src = normalizeClientPath(p.photo_path);
                    const id = p.id || '';
                    const deleteButton = (!p.is_main && id) ? `
                        <button class="action-btn" style="padding: 4px 10px; font-size: 0.7rem; background: var(--danger-color); color: white; width: 100%; margin-top: 5px;" 
                                data-photo-id="${id}" data-photo-src="${src}">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    ` : '';
                    
                    const photoLabel = p.is_main ? '<span style="position: absolute; top: 5px; left: 5px; background: var(--primary-color); color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 600;">Main</span>' : '';

                    const baseUrl = "https://thennilavu.lk/";

                    html += `
                        <div style="position: relative;">
                            <img src="${baseUrl}${src}" style="width: 100%; height: 120px; object-fit: cover; border-radius: var(--radius-md);">
                            ${photoLabel}
                            <div style="margin-top: 5px;">
                                <button class="action-btn" style="padding: 4px 10px; font-size: 0.7rem; background: var(--primary-color); color: white; width: 100%;" 
                                        onclick="viewPhotoModal('${src.replace(/'/g, "\\'")}','Photo')">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                ${deleteButton}
                            </div>
                        </div>
                    `;
                });
                
                list.innerHTML = html;
                
                // Attach delete handlers
                list.querySelectorAll('button[data-photo-id]').forEach(btn => {
                    btn.addEventListener('click', async function(){
                        const pid = this.getAttribute('data-photo-id');
                        if (!confirm('Are you sure you want to delete this photo?')) return;
                        
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
                                loadProfilePhotos(memberId);
                                showAlert(document.getElementById('editPhotosAlert'), 'success', 'Photo deleted successfully');
                            } else {
                                showAlert(document.getElementById('editPhotosAlert'), 'error', j.error || 'Failed to delete photo');
                            }
                        } catch (err) {
                            console.error(err);
                            showAlert(document.getElementById('editPhotosAlert'), 'error', 'Network or server error');
                        }
                    });
                });
                
            } catch (err) {
                console.error(err);
                list.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 20px; color: var(--text-muted);">Failed to load photos.</div>';
            }
        }

        // Upload handler for new photos
        const uploadBtn = document.getElementById('uploadNewPhotosBtn');
        if (uploadBtn) {
            uploadBtn.addEventListener('click', async function(e){
                e.preventDefault();
                
                if (uploadBtn.disabled) return;
                
                const input = document.getElementById('newPhotosInput');
                if (!input || !input.files || input.files.length === 0) {
                    showAlert(document.getElementById('editPhotosAlert'), 'warning', 'Please select at least one file');
                    return;
                }
                
                // Set loading state
                uploadBtn.disabled = true;
                const originalText = uploadBtn.innerHTML;
                uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
                
                const fd = new FormData();
                for (let i=0; i<input.files.length; i++) fd.append('additional_photos[]', input.files[i]);
                fd.append('member_id', <?php echo isset($member_id) ? intval($member_id) : 0; ?>);

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
                        loadProfilePhotos(<?php echo isset($member_id) ? intval($member_id) : 0; ?>);
                        document.getElementById('newPhotosInput').value = null;
                    } else {
                        showAlert(document.getElementById('editPhotosAlert'), 'error', j.error || 'Upload failed');
                    }
                } catch (err) {
                    console.error(err);
                    showAlert(document.getElementById('editPhotosAlert'), 'error', 'Network or server error');
                } finally {
                    // Reset button state
                    uploadBtn.disabled = false;
                    uploadBtn.innerHTML = originalText;
                }
            });
        }

        // Load photos on page load
        document.addEventListener('DOMContentLoaded', function() {
            const memberId = <?php echo isset($member_id) ? intval($member_id) : 0; ?>;
            if (memberId) loadProfilePhotos(memberId);
        });
    </script>
</body>
</html>