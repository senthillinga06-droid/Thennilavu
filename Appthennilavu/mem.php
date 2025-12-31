<?php
$user_display = 'sample@gmail.com';
session_start();
if (isset($_SESSION['user_id'])) {
    $conn = new mysqli('localhost', 'thennilavu_matrimonial', 'OYVuiEKfS@FQ', 'thennilavu_thennilavu');
    if (!$conn->connect_error) {
        $uid = $_SESSION['user_id'];
        $userQ = $conn->prepare('SELECT username, email FROM users WHERE id=?');
        if ($userQ) {
            $userQ->bind_param('i', $uid);
            $userQ->execute();
            $userQ->bind_result($uname, $uemail);
            if ($userQ->fetch()) {
                $user_display = htmlspecialchars($uname ?: $uemail);
            }
            $userQ->close();
        }
    }
}

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

// Ensure user_interest_events table exists (used to record additional repeated likes)
// This prevents fatal errors when mem.php runs before any repeated-like event has been recorded.
$conn->query("CREATE TABLE IF NOT EXISTS user_interest_events (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    target_member_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Check if profile_hidden column exists, if not create it
$columnCheck = $conn->query("SHOW COLUMNS FROM members LIKE 'profile_hidden'");
if ($columnCheck->num_rows == 0) {
    $conn->query("ALTER TABLE members ADD COLUMN profile_hidden TINYINT(1) DEFAULT 0");
}

// Handle WhatsApp number request
if (isset($_GET['get_whatsapp'])) {
    $sql = "SELECT whatsapp_number FROM company_details LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode(["whatsapp_number" => $row['whatsapp_number']]);
    } else {
        echo json_encode(["whatsapp_number" => null]);
    }
    exit;
}
        
// ------------------------------------------------------------
// 1. DEFAULT PACKAGE VALUES
// ------------------------------------------------------------
$user_type = 'Free User';
$search_access = 'Basic';
$matchmaker_enabled = 'No';
$interest_limit = '5';
$profile_views_limit = '10';
$profile_view_enabled = 'No';
$profile_hidden = 'No';
$package_name = '';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    // ------------------------------------------------------------
    // 2. FETCH USER PACKAGE
    // ------------------------------------------------------------
    $stmt = $conn->prepare("
        SELECT status, requestPackage, end_date 
        FROM userpackage 
        WHERE user_id = ? 
        ORDER BY start_date DESC 
        LIMIT 1
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($status, $requestPackage, $end_date);

    if ($stmt->fetch()) {
        if ($requestPackage === 'accept' && strtotime($end_date) > time()) {
            $package_name = $status;
            $user_type = $status;
        }
    }
    $stmt->close();

    // FETCH PACKAGE DETAILS
    if (!empty($package_name)) {
        $stmt = $conn->prepare("
            SELECT search_access, matchmaker_enabled, interest_limit, profile_views_limit, profile_view_enabled 
            FROM packages 
            WHERE name = ? AND status = 'active' 
            LIMIT 1
        ");
        $stmt->bind_param('s', $package_name);
        $stmt->execute();
        $stmt->bind_result($search_access, $matchmaker_enabled, $interest_limit, $profile_views_limit, $profile_view_enabled);

        if (!$stmt->fetch()) {
            $search_access = 'Basic';
            $matchmaker_enabled = 'No';
            $interest_limit = '5';
            $profile_views_limit = '10';
            $profile_view_enabled = 'No';
        }
        $stmt->close();
    }

    // ------------------------------------------------------------
    // 3. FETCH LOGGED-IN MEMBER + LOOKING_FOR
    // ------------------------------------------------------------
    $member_stmt = $conn->prepare("
        SELECT id, looking_for
        FROM members 
        WHERE user_id = ? 
        LIMIT 1
    ");
    $member_stmt->bind_param('i', $user_id);
    $member_stmt->execute();
    $member_row = $member_stmt->get_result()->fetch_assoc();
    $member_stmt->close();

    if ($member_row) {
        $logged_member_id = $member_row['id'];
        $looking_for = $member_row['looking_for'];

        // Get logged-in user's nakshatra
        $logged_user_nakshatra = null;
        $nakshatra_stmt = $conn->prepare("SELECT nakshatra FROM horoscope WHERE member_id = ?");
        $nakshatra_stmt->bind_param('i', $logged_member_id);
        $nakshatra_stmt->execute();
        $nakshatra_result = $nakshatra_stmt->get_result()->fetch_assoc();
        if ($nakshatra_result) {
            $logged_user_nakshatra = $nakshatra_result['nakshatra'];
        }
        $nakshatra_stmt->close();
        
        // ------------------------------------------------------------
        // 4. FETCH PARTNER EXPECTATIONS
        // ------------------------------------------------------------
        $expect_stmt = $conn->prepare("
            SELECT religion, min_age, max_age, min_height, max_height, smoking, drinking, preferred_country, marital_status
            FROM partner_expectations 
            WHERE member_id = ? 
            LIMIT 1
        ");
        $expect_stmt->bind_param("i", $logged_member_id);
        $expect_stmt->execute();
        $expect = $expect_stmt->get_result()->fetch_assoc();
        $expect_stmt->close();

        // If no expectations, set default values to avoid errors
        $exp_religion = $expect['religion'] ?? '';
        $exp_min_age = $expect['min_age'] ?? 18;
        $exp_max_age = $expect['max_age'] ?? 60;
        $exp_min_height = $expect['min_height'] ?? 120;
        $exp_max_height = $expect['max_height'] ?? 220;
        $smoking = $expect['smoking'] ?? 'no';
        $drinking = $expect['drinking'] ?? 'no';
        $exp_preferred_country = $expect['preferred_country'] ?? '';
        $exp_marital_status = $expect['marital_status'] ?? '';



        // ------------------------------------------------------------
        // 5. FINAL PERFECT MATCH QUERY
        // ------------------------------------------------------------
        $perfect_match_sql = "
        SELECT 
            members.*, 
            physical_info.height_cm, 
            physical_info.weight_kg,
            (COUNT(ui.id) + COALESCE(uie.extra_likes, 0)) AS likes_received,
            CASE 
                WHEN up.requestPackage = 'accept' AND up.end_date > NOW() THEN p.name
                ELSE 'Free User'
            END AS package_name
        FROM members
        LEFT JOIN physical_info 
            ON members.id = physical_info.member_id

        LEFT JOIN user_interests ui 
            ON members.id = ui.target_member_id

        LEFT JOIN (
            SELECT target_member_id, COUNT(*) AS extra_likes
            FROM user_interest_events
            GROUP BY target_member_id
        ) uie ON members.id = uie.target_member_id

        LEFT JOIN userpackage up 
            ON members.user_id = up.user_id 
            AND up.requestPackage = 'accept'

        LEFT JOIN packages p 
            ON p.name = up.status

        WHERE 
            members.id != ?
            AND members.gender = ?
            AND members.religion = ?
            AND physical_info.height_cm BETWEEN ? AND ?
            AND members.smoking = ?
            AND members.drinking = ?
            AND (TIMESTAMPDIFF(YEAR, members.dob, CURDATE())) BETWEEN ? AND ?
            AND members.marital_status NOT IN ('Married','Deactivated','deactive')
            AND COALESCE(members.profile_hidden, 0) = 0

        GROUP BY members.id
        ORDER BY members.created_at DESC
        LIMIT 4
        ";

        // ------------------------------------------------------------
        // 6. EXECUTE FINAL QUERY
        // ------------------------------------------------------------
        $perfect_match_stmt = $conn->prepare($perfect_match_sql);
        $perfect_match_stmt->bind_param(
            "issddssii",
            $logged_member_id,
            $looking_for,
            $exp_religion,
            $exp_min_height,
            $exp_max_height,
            $smoking,
            $drinking,
            $exp_min_age,
            $exp_max_age
        );
        $perfect_match_stmt->execute();
        $perfect_match_result = $perfect_match_stmt->get_result();

        // ------------------------------------------------------------
        // 7. COLLECT RESULTS
        // ------------------------------------------------------------
        $perfect_matches = [];
        if ($perfect_match_result && $perfect_match_result->num_rows > 0) {
            while ($row = $perfect_match_result->fetch_assoc()) {
                $perfect_matches[] = $row;
            }
        }

        $perfect_match_stmt->close();
    }
}


// Define allowed filters based on search access
$allowed_filters = [];
if ($search_access === 'Basic') {
    // Free users get basic search - only country and education
    $allowed_filters = ['looking_for', 'marital_status', 'country', 'education'];
} elseif ($search_access === 'Limited') {
    // Limited package users get specific filters
    $allowed_filters = ['looking_for', 'marital_status', 'country', 'education', 'profession', 'age', 'religion'];
} elseif ($search_access === 'Unlimited') {
    // Unlimited package users get all filters
    $allowed_filters = ['looking_for', 'marital_status', 'religion', 'country', 'profession', 'age', 'education', 'income', 'height', 'weight'];
} else {
    // Default to basic for any other case
    $allowed_filters = ['looking_for', 'marital_status', 'country', 'education'];
}

// Determine horoscope access: Only allow if matchmaker is enabled AND not a free user
$horoscope_access = ($matchmaker_enabled === 'Yes' && $search_access !== 'Basic');

// Fetch unique professions from database for dropdown
$professions = [];
$profession_stmt = $conn->prepare("SELECT DISTINCT profession FROM members WHERE profession IS NOT NULL AND profession != '' ORDER BY profession ASC");
$profession_stmt->execute();
$profession_result = $profession_stmt->get_result();
while ($row = $profession_result->fetch_assoc()) {
    $professions[] = $row['profession'];
}
$profession_stmt->close();

// Fetch unique education degrees from database for dropdown
$education_degrees = [];
$education_stmt = $conn->prepare("SELECT DISTINCT stream_or_degree FROM education WHERE stream_or_degree IS NOT NULL AND stream_or_degree != '' ORDER BY stream_or_degree ASC");
$education_stmt->execute();
$education_result = $education_stmt->get_result();
while ($row = $education_result->fetch_assoc()) {
    $education_degrees[] = $row['stream_or_degree'];
}
$education_stmt->close();

// Handle AJAX request for member details
if (isset($_GET['action']) && $_GET['action'] === 'get_member' && isset($_GET['id'])) {
    $member_id = (int)$_GET['id'];
    
    // Get member details
    $stmt = $conn->prepare("SELECT * FROM members WHERE id = ? ");
    $stmt->bind_param('i', $member_id);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    
    if ($member) {
        // Get physical info
        $stmt = $conn->prepare("SELECT * FROM physical_info WHERE member_id = ?");
        $stmt->bind_param('i', $member_id);
        $stmt->execute();
        $physical = $stmt->get_result()->fetch_assoc();
        
        // Get education
        $stmt = $conn->prepare("SELECT * FROM education WHERE member_id = ?");
        $stmt->bind_param('i', $member_id);
        $stmt->execute();
        $education = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Get family info
        $stmt = $conn->prepare("SELECT * FROM family WHERE member_id = ?");
        $stmt->bind_param('i', $member_id);
        $stmt->execute();
        $family = $stmt->get_result()->fetch_assoc();
        
        // Get partner expectations
        $stmt = $conn->prepare("SELECT * FROM partner_expectations WHERE member_id = ?");
        $stmt->bind_param('i', $member_id);
        $stmt->execute();
        $partner = $stmt->get_result()->fetch_assoc();
        
        // Get horoscope - only if user has access
        $horoscope = null;
        if ($horoscope_access) {
            $stmt = $conn->prepare("SELECT * FROM horoscope WHERE member_id = ?");
            $stmt->bind_param('i', $member_id);
            $stmt->execute();
            $horoscope = $stmt->get_result()->fetch_assoc();
        }
        
        // Get package information
        $package_info = 'Free User';
        $stmt = $conn->prepare("
            SELECT p.name, up.end_date 
            FROM userpackage up 
            JOIN packages p ON p.name = up.status 
            WHERE up.user_id = (SELECT user_id FROM members WHERE id = ?) 
            AND up.requestPackage = 'accept' 
            ORDER BY up.start_date DESC 
            LIMIT 1
        ");
        $stmt->bind_param('i', $member_id);
        $stmt->execute();
        $package_result = $stmt->get_result()->fetch_assoc();
        
        if ($package_result && strtotime($package_result['end_date']) > time()) {
            $package_info = $package_result['name'];
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'member' => $member,
            'physical' => $physical,
            'education' => $education,
            'family' => $family,
            'partner' => $partner,
            'horoscope' => $horoscope,
            'horoscope_access' => $horoscope_access,
            'package' => $package_info
        ]);
        exit;
    }
}

// Build filter query - only allow filters based on search access
$where_conditions = [];
$params = [];
$types = '';

// Always exclude inactive profiles and hidden profiles
$where_conditions[] = "marital_status != ?";
$params[] = 'deactive';
$types .= 's';


if (!empty($_GET['looking_for']) && in_array('looking_for', $allowed_filters)) {
    $where_conditions[] = "looking_for = ?";
    $params[] = $_GET['looking_for'];
    $types .= 's';
}

if (!empty($_GET['marital_status']) && in_array('marital_status', $allowed_filters)) {
    $where_conditions[] = "marital_status = ?";
    $params[] = $_GET['marital_status'];
    $types .= 's';
}

if (!empty($_GET['religion']) && in_array('religion', $allowed_filters)) {
    $where_conditions[] = "religion = ?";
    $params[] = $_GET['religion'];
    $types .= 's';
}

if (!empty($_GET['country']) && in_array('country', $allowed_filters)) {
    $where_conditions[] = "country = ?";
    $params[] = $_GET['country'];
    $types .= 's';
}

if (!empty($_GET['profession']) && in_array('profession', $allowed_filters)) {
    $where_conditions[] = "profession LIKE ?";
    $params[] = '%' . $_GET['profession'] . '%';
    $types .= 's';
}

if (!empty($_GET['age']) && in_array('age', $allowed_filters)) {
    $ageRange = explode('-', $_GET['age']);
    if (count($ageRange) === 2) {
        $where_conditions[] = "TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN ? AND ?";
        $params[] = $ageRange[0];
        $params[] = $ageRange[1];
        $types .= 'ii';
    } elseif ($ageRange[0] === '60+') {
        $where_conditions[] = "TIMESTAMPDIFF(YEAR, dob, CURDATE()) > ?";
        $params[] = 60;
        $types .= 'i';
    }
}

if (!empty($_GET['height']) && in_array('height', $allowed_filters)) {
    $heightValue = $_GET['height'];
    if ($heightValue === '<150') {
        $where_conditions[] = "physical_info.height_cm < ?";
        $params[] = 150;
        $types .= 'i';
    } elseif ($heightValue === '>190') {
        $where_conditions[] = "physical_info.height_cm > ?";
        $params[] = 190;
        $types .= 'i';
    } elseif (strpos($heightValue, '-') !== false) {
        $heightRange = explode('-', $heightValue);
        if (count($heightRange) === 2 && is_numeric($heightRange[0]) && is_numeric($heightRange[1])) {
            $where_conditions[] = "physical_info.height_cm BETWEEN ? AND ?";
            $params[] = (int)$heightRange[0];
            $params[] = (int)$heightRange[1];
            $types .= 'ii';
        }
    }
}

if (!empty($_GET['weight']) && in_array('weight', $allowed_filters)) {
    $weightValue = $_GET['weight'];
    if ($weightValue === '<50') {
        $where_conditions[] = "physical_info.weight_kg < ?";
        $params[] = 50;
        $types .= 'i';
    } elseif ($weightValue === '>90') {
        $where_conditions[] = "physical_info.weight_kg > ?";
        $params[] = 90;
        $types .= 'i';
    } elseif (strpos($weightValue, '-') !== false) {
        $weightRange = explode('-', $weightValue);
        if (count($weightRange) === 2 && is_numeric($weightRange[0]) && is_numeric($weightRange[1])) {
            $where_conditions[] = "physical_info.weight_kg BETWEEN ? AND ?";
            $params[] = (int)$weightRange[0];
            $params[] = (int)$weightRange[1];
            $types .= 'ii';
        }
    }
}

if (!empty($_GET['education']) && in_array('education', $allowed_filters)) {
    $where_conditions[] = "education.stream_or_degree = ?";
    $params[] = $_GET['education'];
    $types .= 's';
}

if (!empty($_GET['income']) && in_array('income', $allowed_filters)) {
    $incomeValue = $_GET['income'];
    if ($incomeValue === '<50000') {
        $where_conditions[] = "members.income < ?";
        $params[] = 50000;
        $types .= 'i';
    } elseif ($incomeValue === '>200000') {
        $where_conditions[] = "members.income > ?";
        $params[] = 200000;
        $types .= 'i';
    } elseif (strpos($incomeValue, '-') !== false) {
        $incomeRange = explode('-', $incomeValue);
        if (count($incomeRange) === 2 && is_numeric($incomeRange[0]) && is_numeric($incomeRange[1])) {
            $where_conditions[] = "members.income BETWEEN ? AND ?";
            $params[] = (int)$incomeRange[0];
            $params[] = (int)$incomeRange[1];
            $types .= 'ii';
        }
    }
}

// Exclude members with marital_status 'Married' or 'Deactivated'
$where_conditions[] = "members.marital_status NOT IN (?, ?)";
$params[] = 'Married';
$params[] = 'Deactivated';
$types .= 'ss';

$sql = "SELECT members.*, physical_info.height_cm, physical_info.weight_kg,
    (COUNT(ui.id) + COALESCE(uie.extra_likes,0)) as likes_received,
        CASE 
            WHEN up.requestPackage = 'accept' AND up.end_date > NOW() THEN p.name
            ELSE 'Free User'
        END as package_name,
        COALESCE(members.profile_hidden, 0) as profile_hidden,
        CASE 
            WHEN up.requestPackage = 'accept' AND up.end_date > NOW() THEN COALESCE(p.profile_hide_enabled, 'No')
            ELSE 'No'
        END as profile_hide_enabled
        FROM members 
        LEFT JOIN physical_info ON members.id = physical_info.member_id
        LEFT JOIN user_interests ui ON members.id = ui.target_member_id
        LEFT JOIN (
            SELECT target_member_id, COUNT(*) as extra_likes
            FROM user_interest_events
            GROUP BY target_member_id
        ) uie ON members.id = uie.target_member_id
        LEFT JOIN userpackage up ON members.user_id = up.user_id AND up.requestPackage = 'accept'
        LEFT JOIN packages p ON p.name = up.status
        LEFT JOIN education ON members.id = education.member_id";
if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(' AND ', $where_conditions);
}

// Pagination setup
$records_per_page = 12;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $records_per_page;

// First, get total count for pagination
$count_sql = "SELECT COUNT(DISTINCT members.id) as total
        FROM members 
        LEFT JOIN physical_info ON members.id = physical_info.member_id
        LEFT JOIN user_interests ui ON members.id = ui.target_member_id
        LEFT JOIN userpackage up ON members.user_id = up.user_id AND up.requestPackage = 'accept'
        LEFT JOIN packages p ON p.name = up.status
        LEFT JOIN education ON members.id = education.member_id";
if (!empty($where_conditions)) {
    $count_sql .= " WHERE " . implode(' AND ', $where_conditions);
}

$total_records = 0;
if (!empty($params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    if ($count_row = $count_result->fetch_assoc()) {
        $total_records = $count_row['total'];
    }
    $count_stmt->close();
} else {
    $count_result = $conn->query($count_sql);
    if ($count_row = $count_result->fetch_assoc()) {
        $total_records = $count_row['total'];
    }
}

$total_pages = ceil($total_records / $records_per_page);

// Add sorting options with pagination
$sort_by = $_GET['sort'] ?? 'newest';
if ($sort_by === 'most_liked') {
    $sql .= " GROUP BY members.id ORDER BY likes_received DESC, members.created_at DESC LIMIT ? OFFSET ?";
} else {
    $sql .= " GROUP BY members.id ORDER BY members.created_at DESC LIMIT ? OFFSET ?";
}

// Debug: Add a hidden field to show current sort mode (only in development)
$debug_mode = isset($_GET['debug']) && $_GET['debug'] === '1';
$current_sort_debug = $debug_mode ? "<!-- Current Sort: " . htmlspecialchars($sort_by) . " -->" : "";

// Add limit and offset parameters
$params[] = $records_per_page;
$params[] = $offset;
$types .= 'ii';

$members = [];
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Members - Thennilavu Matrimony</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Modern Mobile App Variables */
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
            --card-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            --elevated-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
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
            --card-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
            --elevated-shadow: 0 4px 16px rgba(0, 0, 0, 0.35);
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
            overflow-x: hidden;
            padding-top: 60px;
            padding-bottom: 80px;
            min-height: 100vh;
        }

        /* App Container */
        .app-container {
            max-width: 100%;
            min-height: 100vh;
        }

        /* App Header - Mobile App Style */
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
            background: rgba(var(--bg-primary-rgb), 0.95);
            height: 60px;
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
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-sm);
            transition: var(--transition);
        }

        .back-button:hover {
            background: var(--bg-secondary);
        }

        .header-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .header-action-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.1rem;
            padding: 8px;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-sm);
            transition: var(--transition);
        }

        .header-action-btn:hover {
            background: var(--bg-secondary);
            color: var(--primary-color);
        }

        /* Bottom Navigation - Mobile App Style */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--bg-primary);
            border-top: 1px solid var(--border-color);
            padding: 8px 12px;
            display: flex;
            justify-content: space-around;
            z-index: 1000;
            backdrop-filter: blur(10px);
            background: rgba(var(--bg-primary-rgb), 0.95);
            height: 70px;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 0.7rem;
            gap: 4px;
            transition: var(--transition);
            padding: 6px 8px;
            border-radius: var(--radius-sm);
            min-width: 56px;
            flex: 1;
            height: 54px;
        }

        .nav-item i {
            font-size: 1.1rem;
            transition: var(--transition);
        }

        .nav-item.active {
            color: var(--primary-color);
            background: rgba(var(--primary-color-rgb), 0.1);
        }

        .nav-item.active i {
            transform: translateY(-2px);
        }

        .nav-item:hover {
            color: var(--primary-color);
        }

        /* Main Content Area */
        .content-container {
            padding: 16px;
            max-width: 100%;
            overflow-x: hidden;
        }

        /* Hero Section */
        .hero-section {
            padding: 20px 16px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
            border-radius: var(--radius-lg);
            margin-bottom: 20px;
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
            font-size: 2.2rem;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .hero-title {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 8px;
            line-height: 1.2;
        }

        .hero-subtitle {
            font-size: 0.9rem;
            opacity: 0.9;
            max-width: 280px;
            margin: 0 auto;
        }

        /* Perfect Match Section */
        .perfect-matches-section {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 20px 16px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
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
            flex-shrink: 0;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
            flex: 1;
        }

        /* Filter Card - Mobile Optimized */
        .filter-card {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .filter-card:hover {
            box-shadow: var(--elevated-shadow);
        }

        .filter-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .filter-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-badge {
            background: var(--primary-color);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        /* Form Elements - Mobile Optimized */
        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 0.85rem;
        }

        .form-select, .form-control {
            width: 100%;
            padding: 14px 16px;
            background: var(--bg-primary);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            color: var(--text-primary);
            transition: var(--transition);
            appearance: none;
            -webkit-appearance: none;
        }

        .form-select:focus, .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
        }

        /* Buttons - Mobile Optimized */
        .btn-primary {
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(233, 30, 99, 0.3);
        }

        .btn-secondary {
            width: 100%;
            padding: 16px;
            background: var(--bg-secondary);
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        /* Large, easy-to-tap navigation buttons */
#prevImageBtn,
#nextImageBtn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 60px; /* Larger touch target */
    height: 60px;
    border-radius: 50%;
    background-color: rgba(0, 0, 0, 0.7);
    border: 2px solid rgba(255, 255, 255, 0.5);
    color: white;
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    opacity: 0.9;
    transition: all 0.3s ease;
}
#prevImageBtn { left: 15px; }
#nextImageBtn { right: 15px; }

/* Make buttons even more prominent on very small screens */
@media (max-width: 576px) {
    #prevImageBtn,
    #nextImageBtn {
        width: 50px;
        height: 50px;
        font-size: 1.3rem;
    }
    #prevImageBtn { left: 10px; }
    #nextImageBtn { right: 10px; }
}

/* Hover and active states for better feedback */
#prevImageBtn:hover,
#nextImageBtn:hover,
#prevImageBtn:active,
#nextImageBtn:active {
    background-color: rgba(233, 30, 99, 0.9); /* Your theme color */
    border-color: white;
    opacity: 1;
    transform: translateY(-50%) scale(1.05);
}

/* Reposition auto-play button */
#autoPlayToggle {
    position: absolute;
    bottom: 80px; /* Position above the indicator dots */
    right: 20px;
    z-index: 1000;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background-color: rgba(0, 0, 0, 0.7);
    border: 2px solid rgba(255, 255, 255, 0.5);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    opacity: 0.9;
}

/* Image counter styling */
#imageCounter {
    position: absolute;
    top: 20px;
    left: 20px;
    background-color: rgba(0, 0, 0, 0.7);
    color: white;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    z-index: 1000;
}

/* Image indicator dots - make them larger for mobile */
#imageIndicators {
    position: absolute;
    bottom: 20px;
    left: 0;
    right: 0;
    display: flex;
    justify-content: center;
    gap: 10px;
    z-index: 1000;
    padding: 10px;
}
#imageIndicators button {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    padding: 0;
    border: 2px solid rgba(255, 255, 255, 0.7);
    background-color: transparent;
    transition: all 0.3s ease;
}
#imageIndicators button.btn-primary {
    background-color: white;
    border-color: white;
}

/* Swipe hint for mobile users */
@media (max-width: 768px) {
    #imageCarousel::after {
        content: '← Swipe →';
        position: absolute;
        bottom: 60px;
        left: 0;
        right: 0;
        text-align: center;
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.8rem;
        letter-spacing: 2px;
        z-index: 999;
        animation: pulseHint 2s infinite;
    }
    
    @keyframes pulseHint {
        0%, 100% { opacity: 0.5; }
        50% { opacity: 1; }
    }
}

        .btn-secondary:hover {
            background: var(--bg-tertiary);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        /* Member Cards Grid - Mobile Optimized */
        .members-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        @media (max-width: 380px) {
            .members-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 12px;
            }
        }

        /* Member Card - Mobile App Style */
        .member-card {
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid var(--border-color);
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .member-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--elevated-shadow);
        }

        .member-badges {
            position: absolute;
            top: 8px;
            left: 8px;
            right: 8px;
            display: flex;
            justify-content: space-between;
            z-index: 2;
        }

        .badge-id {
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            backdrop-filter: blur(5px);
        }

        .badge-package {
            background: var(--success-color);
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-perfect {
            background: linear-gradient(135deg, #ff9800, #ff5722);
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.6rem;
            font-weight: 600;
        }

        .badge-hidden {
            background: var(--warning-color);
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.6rem;
            font-weight: 600;
        }

        .member-image {
            width: 100%;
            height: 160px;
            overflow: hidden;
            position: relative;
            background: var(--bg-tertiary);
        }

        .member-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }

        .member-card:hover .member-image img {
            transform: scale(1.05);
        }

        .profile-initial {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: bold;
            color: white;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        .member-info {
            padding: 12px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .member-name {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
            line-height: 1.2;
        }

        .member-details {
            flex: 1;
        }

        .member-detail {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
            font-size: 0.8rem;
        }

        .detail-label {
            color: var(--text-secondary);
            font-weight: 500;
            min-width: 70px;
        }

        .detail-value {
            color: var(--text-primary);
            font-weight: 600;
            text-align: right;
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .member-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-top: 1px solid var(--border-color);
            margin-top: 8px;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.1rem;
            padding: 6px;
            cursor: pointer;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: var(--transition);
            position: relative;
        }

        .action-btn:hover {
            background: var(--bg-secondary);
            color: var(--primary-color);
        }

        .action-btn.whatsapp:hover {
            color: #25D366;
            background: rgba(37, 211, 102, 0.1);
        }

        .action-btn.interest.active {
            color: var(--primary-color);
        }

        .interest-count {
            position: absolute;
            top: -6px;
            right: -6px;
            background: var(--primary-color);
            color: white;
            font-size: 0.65rem;
            font-weight: 700;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--bg-primary);
        }

        .view-profile-btn {
            padding: 8px 16px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }

        .view-profile-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(233, 30, 99, 0.3);
        }

        /* Pagination - Mobile Optimized */
        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 24px;
            flex-wrap: wrap;
        }

        .page-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-sm);
            background: var(--bg-secondary);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .page-btn:hover {
            background: var(--bg-tertiary);
            color: var(--primary-color);
        }

        .page-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .page-info {
            font-size: 0.85rem;
            color: var(--text-muted);
            text-align: center;
            width: 100%;
            margin-top: 8px;
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
            pointer-events: none;
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
        
        
        /* Enhanced Profile Modal Styles for Mobile App */
.profile-modal {
    border-radius: var(--radius-lg);
    overflow: hidden;
    border: none;
    box-shadow: var(--elevated-shadow);
    background: var(--bg-primary);
}

.profile-modal .modal-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    border-bottom: none;
    padding: 25px 20px;
    position: relative;
    overflow: hidden;
    min-height: 160px;
}

.profile-modal .modal-header:before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at 30% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
}

.profile-modal .header-content {
    display: flex;
    align-items: center;
    gap: 20px;
    position: relative;
    z-index: 1;
}

.profile-modal .profile-avatar {
    position: relative;
    flex-shrink: 0;
}

.profile-modal .profile-image-modal {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    border: 4px solid rgba(255, 255, 255, 0.3);
    object-fit: cover;
    cursor: pointer;
    transition: var(--transition);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
}

.profile-modal .profile-image-modal:hover {
    transform: scale(1.05);
    border-color: rgba(255, 255, 255, 0.6);
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.3);
}

.profile-modal .avatar-badge {
    position: absolute;
    bottom: 0;
    right: 0;
    background: linear-gradient(135deg, var(--warning-color) 0%, #ff5722 100%);
    color: white;
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 11px;
    font-weight: 600;
    border: 2px solid white;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.profile-modal .profile-header-info {
    flex: 1;
}

.profile-modal .modal-title {
    font-size: 1.5rem;
    font-weight: 800;
    margin-bottom: 8px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.profile-modal .profile-meta {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.profile-modal .badge {
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.profile-modal .profile-subtitle {
    display: flex;
    gap: 15px;
    margin-top: 8px;
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.9rem;
}

.profile-modal .profile-subtitle span {
    display: flex;
    align-items: center;
    gap: 6px;
}

.profile-modal .btn-close {
    filter: invert(1);
    opacity: 0.8;
    transition: var(--transition);
    position: absolute;
    top: 15px;
    right: 15px;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 50%;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.profile-modal .btn-close:hover {
    opacity: 1;
    background: rgba(0, 0, 0, 0.3);
    transform: rotate(90deg);
}

/* Tabs Container - Mobile Optimized */
        .profile-modal .tabs-container {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            padding: 0 8px;
            position: sticky;
            top: 0;
            z-index: 20;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .profile-modal .tabs-scroll {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding: 10px 0 6px 0;
            scrollbar-width: thin;
        }

        .profile-modal .tabs-wrapper {
            display: flex;
            gap: 4px;
            min-width: max-content;
        }

        .profile-modal .tab-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            padding: 10px 10px 6px 10px;
            background: var(--bg-primary);
            border: none;
            border-radius: var(--radius-md);
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.95rem;
            transition: var(--transition);
            min-width: 80px;
            cursor: pointer;
            border: 2px solid transparent;
        }

.profile-modal .tab-btn i {
    font-size: 1rem;
    margin-bottom: 4px;
}

.profile-modal .tab-btn:hover {
    background: var(--bg-secondary);
    color: var(--primary-color);
    transform: translateY(-2px);
}

.profile-modal .tab-btn.active {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    border-color: rgba(255, 255, 255, 0.3);
    box-shadow: 0 5px 20px rgba(var(--primary-color-rgb), 0.3);
}

.profile-modal .tab-btn.disabled {
    opacity: 0.6;
    cursor: not-allowed;
    background: var(--bg-primary);
    color: var(--text-muted);
}

/* Tab Content */
        .profile-modal .modal-body {
            padding: 18px 8px 18px 8px;
            max-height: 70vh;
            min-height: 350px;
            overflow-y: auto;
            background: var(--bg-primary);
        }

        .profile-modal .tab-content-wrapper {
            margin-top: 10px;
        }

       

/* Section Cards */
        .profile-modal .section-card {
            background: var(--bg-primary);
            border-radius: var(--radius-md);
            padding: 16px 10px 16px 10px;
            margin-bottom: 16px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }

.profile-modal .section-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--elevated-shadow);
}

.profile-modal .section-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 15px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Info Grid */
.profile-modal .info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
}

.profile-modal .info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: var(--bg-secondary);
    border-radius: var(--radius-sm);
    transition: var(--transition);
}

.profile-modal .info-item:hover {
    background: var(--bg-tertiary);
    transform: translateX(5px);
}

        .profile-modal .info-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 1.05rem;
        }

.profile-modal .info-label i {
    color: var(--primary-color);
    font-size: 1rem;
    width: 20px;
    text-align: center;
}

        .profile-modal .info-value {
            color: var(--text-primary);
            font-weight: 500;
            text-align: right;
            flex: 1;
            padding-left: 8px;
            font-size: 1.05rem;
            word-break: break-word;
        }

/* Family Section */
.profile-modal .family-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 15px;
}

.profile-modal .family-member {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px;
    background: var(--bg-secondary);
    border-radius: var(--radius-sm);
    transition: var(--transition);
}

.profile-modal .family-member:hover {
    background: var(--bg-tertiary);
    transform: translateY(-2px);
}

.profile-modal .family-icon {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.3rem;
    flex-shrink: 0;
}

.profile-modal .family-info h6 {
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 4px;
    font-size: 1rem;
}

.profile-modal .family-details {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 0.85rem;
}

/* Ensure the main image scales correctly on all screens */
#fullSizeImage {
    max-width: 100%;
    max-height: 85vh; /* Leave space for controls */
    width: auto;
    height: auto;
    object-fit: contain; /* This scales the image to fit without cropping[citation:4] */
    display: block;
    margin: 0 auto;
}

/* Education Section */
.profile-modal #educationContainer {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.profile-modal .education-item {
    background: var(--bg-primary);
    padding: 20px;
    border-radius: var(--radius-sm);
    border-left: 4px solid var(--primary-color);
    box-shadow: var(--card-shadow);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.profile-modal .education-item:hover {
    transform: translateY(-3px);
    box-shadow: var(--elevated-shadow);
    border-left-color: var(--secondary-color);
}

.profile-modal .education-item h6 {
    color: var(--primary-color);
    font-weight: 700;
    margin-bottom: 12px;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.profile-modal .education-item p {
    margin-bottom: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    border-bottom: 1px solid var(--border-color);
    font-size: 0.85rem;
}

.profile-modal .education-item p strong {
    color: var(--text-secondary);
    min-width: 120px;
    font-weight: 600;
}

/* Expectations Grid */
.profile-modal .expectations-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
}

.profile-modal .expectation-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px;
    background: var(--bg-secondary);
    border-radius: var(--radius-sm);
    transition: var(--transition);
}

.profile-modal .expectation-item:hover {
    background: var(--bg-tertiary);
    transform: translateY(-2px);
}

.profile-modal .expectation-icon {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.profile-modal .expectation-value {
    font-weight: 600;
    color: var(--text-primary);
    margin-top: 4px;
    font-size: 0.95rem;
}

/* Modal Footer Actions */
.profile-modal .modal-footer {
    background: var(--bg-secondary);
    border-top: 1px solid var(--border-color);
    padding: 20px;
    border-radius: 0 0 var(--radius-lg) var(--radius-lg);
}

.profile-modal .footer-actions {
    display: flex;
    gap: 12px;
    width: 100%;
    justify-content: center;
}

.profile-modal .btn-action {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    border-radius: 25px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: var(--transition);
    min-width: 160px;
    justify-content: center;
    font-size: 0.9rem;
}

.profile-modal .interest-btn {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
}

.profile-modal .whatsapp-btn {
    background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
    color: white;
}

.profile-modal .close-btn {
    background: var(--text-muted);
    color: white;
}

.profile-modal .btn-action:hover {
    transform: translateY(-2px);
    box-shadow: var(--elevated-shadow);
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .profile-modal .modal-header {
        padding: 20px;
        flex-direction: column;
        text-align: center;
    }
    
    .profile-modal .header-content {
        flex-direction: column;
        text-align: center;
        gap: 15px;
    }
    
    .profile-modal .profile-subtitle {
        flex-direction: column;
        gap: 8px;
    }
    
    .profile-modal .info-grid {
        grid-template-columns: 1fr;
    }
    
    .profile-modal .expectations-grid {
        grid-template-columns: 1fr;
    }
    
    .profile-modal .footer-actions {
        flex-direction: column;
    }
    
    .profile-modal .btn-action {
        width: 100%;
        @media (max-width: 768px) {
            .profile-modal .modal-header {
                padding: 14px 6px 10px 6px;
                flex-direction: column;
                text-align: center;
            }
            .profile-modal .header-content {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            .profile-modal .profile-subtitle {
                flex-direction: column;
                gap: 6px;
                font-size: 0.98rem;
            }
            .profile-modal .tabs-container {
                padding: 0 2px;
                top: 0;
            }
            .profile-modal .tabs-scroll {
                padding: 6px 0 2px 0;
            }
            .profile-modal .tab-btn {
                min-width: 70px;
                padding: 8px 6px 4px 6px;
                font-size: 1.05rem;
            }
            .profile-modal .info-grid {
                grid-template-columns: 1fr;
                gap: 6px;
            }
            .profile-modal .section-card {
                padding: 10px 4px 10px 4px;
            }
            .profile-modal .footer-actions {
                flex-direction: column;
                gap: 6px;
            }
            .profile-modal .btn-action {
                width: 100%;
            }
            .profile-modal .modal-body {
                padding: 10px 2px 10px 2px;
                max-height: 70vh;
                min-height: 320px;
            }
            .profile-modal .tab-content {
                padding: 4px 0 4px 0;
            }
        }
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .modal-body {
            padding: 20px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .btn-close {
            filter: invert(0.5);
        }

        [data-theme="dark"] .btn-close {
            filter: invert(0.8);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }

        .empty-icon {
            font-size: 3rem;
            color: var(--text-muted);
            margin-bottom: 16px;
        }

        .empty-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .empty-text {
            color: var(--text-secondary);
            margin-bottom: 20px;
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-secondary);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Utility Classes */
        .d-none {
            display: none !important;
        }

        .text-center {
            text-align: center;
        }

        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
        .mt-4 { margin-top: 16px; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 12px; }
        .mb-4 { margin-bottom: 16px; }
        .mx-auto { margin-left: auto; margin-right: auto; }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .content-container {
                padding: 12px;
            }
            
            .hero-section {
                padding: 16px;
                margin-bottom: 16px;
            }
            
            .hero-title {
                font-size: 1.3rem;
            }
            
            .members-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 12px;
            }
        }

        @media (max-width: 480px) {
            .app-header {
                padding: 10px 12px;
            }
            
            .header-title {
                font-size: 1.1rem;
            }
            
            .bottom-nav {
                padding: 6px 8px;
                height: 65px;
            }
            
            .nav-item {
                font-size: 0.65rem;
                padding: 4px 6px;
                min-width: 50px;
            }
            
            .nav-item i {
                font-size: 1rem;
            }
            
            .members-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            }
            
            .member-image {
                height: 140px;
            }
        }
        
        /* Force the modal to be edge-to-edge on mobile */
@media (max-width: 768px) {
    #imageViewModal .modal-dialog.modal-fullscreen {
        margin: 0;
        max-width: 100vw;
    }
    #imageViewModal .modal-content {
        border-radius: 0;
        height: 100vh;
        background-color: #000; /* Pure black background for image viewing */
    }
    #imageViewModal .modal-body {
        padding: 0;
        display: flex;
        align-items: center; /* Vertically center the image */
        justify-content: center;
    }
}

        @media (max-width: 360px) {
            .members-grid {
                grid-template-columns: 1fr;
            }
            
            .hero-title {
                font-size: 1.2rem;
            }
        }

        /* Theme Variables for JavaScript */
        :root {
            --bg-primary-rgb: 255, 255, 255;
            --primary-color-rgb: 233, 30, 99;
        }

        [data-theme="dark"] {
            --bg-primary-rgb: 18, 18, 18;
        }

        /* ALL YOUR ORIGINAL MODAL STYLES ARE PRESERVED BELOW */
        /* I'm including the critical styles but keeping your original modal structure */

        /* Enhanced Modal Styles from Original */
        .modal-xl .modal-content {
            border-radius: 25px;
            overflow: hidden;
            border: none;
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        }
        
        .modal-xl .modal-header {
            background: linear-gradient(50deg, #fd2c79, #ed0cbd);
            color: white;
            border-bottom: none;
            padding: 25px 30px;
            position: relative;
            overflow: hidden;
        }
        
        .modal-xl .modal-header:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path d="M0,0 L100,0 L100,100 Z" fill="rgba(255,255,255,0.1)"/></svg>');
            background-size: cover;
        }
        
        .modal-xl .modal-title {
            font-weight: 800;
            font-size: 1.8rem;
            position: relative;
            z-index: 1;
        }
        
        .modal-xl .btn-close {
            filter: invert(1);
            opacity: 0.8;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }
        
        .modal-xl .btn-close:hover {
            opacity: 1;
            transform: rotate(90deg);
        }
        
        /* Modal backdrop blur effect */
        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.8) !important;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        
        .modal-backdrop.show {
            opacity: 1 !important;
        }
        
        .modal-xl .modal-body {
            padding: 15px;
            max-height: 65vh;
            overflow-y: auto;
            background: linear-gradient(135deg, #fafafa 0%, #ffffff 100%);
        }
        
        .nav-tabs .nav-link {
            font-size: 0.85rem;
            padding: 8px 12px;
        }
        
        /* Tab Content Grid Layout */
        .tab-content .row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        }

        .tab-content .col-6 {
            background: rgba(255, 255, 255, 0.8);
            padding: 15px;
            border-radius: 12px;
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
            backdrop-filter: blur(5px);
            margin-bottom: 0;
        }

        .tab-content .col-6:hover {
            background: rgba(255, 255, 255, 0.95);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(127, 8, 8, 0.1);
            border-left-color: #a00;
        }

        .tab-content .col-6 small {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            font-size: 0.85rem;
        }

        .tab-content .col-6 strong {
            color: var(--primary-color);
            font-weight: 700;
            min-width: 120px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-content .col-6 strong::before {
            content: 'тАв';
            color: var(--primary-color);
            font-size: 1.5rem;
            line-height: 1;
        }

        .tab-content .col-6 span {
            color: #2c3e50;
            font-weight: 500;
            text-align: right;
            flex: 1;
            padding-left: 10px;
        }
        
        .profile-section {
            background: white;
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 5px solid var(--primary-color);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .profile-section:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(50deg, #fd2c79, #ed0cbd);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .profile-section:hover:before {
            transform: scaleX(1);
        }
        
        .profile-section:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .section-header {
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.1rem;
        }
        
        .section-header i {
            font-size: 1.5rem;
            background: linear-gradient(50deg, #fd2c79, #ed0cbd);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 10px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 10px;
            border-bottom: 1px solid #f5f5f5;
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        
        .info-item:hover {
            background: rgba(127, 8, 8, 0.03);
            transform: translateX(5px);
        }
        
        .info-label {
            font-weight: 700;
            color: #555;
            min-width: 180px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-label:before {
            content: 'тАв';
            color: var(--primary-color);
            font-size: 1.5rem;
        }
        
        .info-value {
            color: #333;
            text-align: right;
            flex: 1;
            font-weight: 500;
        }
        
        .info-value:empty::before {
            content: "-";
            color: #999;
            font-style: italic;
        }
        
        /* Education Items Styling */
        #educationContainer {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .education-item {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 0;
            border-left: 4px solid var(--primary-color);
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .education-item::before {
            content: '';
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 1.5rem;
            opacity: 0.1;
        }

        .education-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-left-color: #a00;
        }

        .education-item h6 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 12px;
            font-size: 1.1rem;
            border-bottom: 2px solid rgba(127, 8, 8, 0.1);
            padding-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .education-item h6::before {
            content: '';
            font-size: 1.2rem;
        }

        .education-item p {
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .education-item p strong {
            color: #555;
            min-width: 140px;
            font-weight: 600;
        }

        .education-item p:last-child {
            border-bottom: none;
        }
        
        /* Horoscope Images Grid */
        .horoscope-images {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .horoscope-img {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: 1px solid rgba(127, 8, 8, 0.1);
        }

        .horoscope-img:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .horoscope-img strong {
            display: block;
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }

        .horoscope-img img {
            width: 100%;
            max-width: 200px;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .horoscope-img:hover img {
            transform: scale(1.05);
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        /* Full-width items for better spacing */
        .tab-content .col-12 {
            grid-column: 1 / -1;
            background: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid var(--primary-color);
            margin: 10px 0;
        }

        .tab-content .col-12:hover {
            background: rgba(255, 255, 255, 0.95);
            transform: translateY(-2px);
        }

        /* Empty state styling */
        .tab-content .text-muted {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d !important;
            font-style: italic;
            background: rgba(248, 249, 250, 0.8);
            border-radius: 12px;
            border: 2px dashed #dee2e6;
        }
        
        .modal-xl .modal-footer {
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            border-top: 1px solid #e9ecef;
            padding: 25px 30px;
            border-radius: 0 0 25px 25px;
        }
        
        .profile-image-container {
            position: sticky;
            top: 20px;
            text-align: center;
        }
        
        .profile-image-modal {
            width: 100%;
            max-width: 320px;
            height: auto;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            border: 4px solid white;
            margin-bottom: 20px;
        }
        
        .profile-image-modal:hover {
            transform: scale(1.02);
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
        }
        
        .profile-basic-info {
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .profile-name {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 15px;
            font-size: 1.3rem;
        }
        
        .profile-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .profile-meta .badge {
            font-size: 0.9rem;
            padding: 8px 12px;
            border-radius: 8px;
        }
        
        /* Scrollbar styling for tab content */
        .modal-tab-content::-webkit-scrollbar {
            width: 8px;
        }

        .modal-tab-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
            margin: 5px;
        }

        .modal-tab-content::-webkit-scrollbar-thumb {
            background: linear-gradient(50deg, #fd2c79, #ed0cbd);
            border-radius: 10px;
        }

        .modal-tab-content::-webkit-scrollbar-thumb:hover {
            background: #a00;
        }

        /* Responsive adjustments for modal */
        @media (max-width: 768px) {
            .modal-tab-content {
                padding: 15px;
                min-height: 300px;
                max-height: 300px;
            }
            
            .tab-content .row {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .tab-content .col-6 {
                padding: 12px;
            }
            
            .tab-content .col-6 small {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }
            
            .tab-content .col-6 span {
                text-align: left;
                padding-left: 0;
                margin-top: 5px;
            }
            
            .education-item {
                padding: 15px;
            }
            
            .education-item p {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .horoscope-images {
                grid-template-columns: 1fr;
            }
        }

        /* Nav Pills Styles */
        .nav-pills .nav-link {
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 10px 15px;
            margin: 0 2px;
            transition: all 0.3s ease;
        }
        
        .nav-pills .nav-link.active {
            background: linear-gradient(50deg, #fd2c79, #ed0cbd);
            color: white;
        }
        
        .nav-pills .nav-link:not(.active) {
            color: var(--primary-color);
            background: rgba(127, 8, 8, 0.1);
        }
        
        .nav-pills .nav-link:not(.active):hover {
            background: rgba(127, 8, 8, 0.2);
            transform: translateY(-2px);
        }
        
        /* Disabled tab styling */
        .nav-link.disabled {
            color: #6c757d !important;
            background-color: #f8f9fa;
            cursor: not-allowed !important;
            pointer-events: auto !important;
            opacity: 0.6;
        }
        
        .nav-link.disabled:hover {
            color: #6c757d !important;
            background-color: #f8f9fa !important;
            transform: none !important;
        }
        
        .nav-link.disabled i {
            color: #dc3545;
        }

        /* Image viewing modal styles */
        #imageViewModal .modal-content {
            background: rgba(0,0,0,0.9);
            border: none;
        }

        #imageViewModal .modal-body {
            background: transparent;
        }

        /* Clickable images in modal content */
        #detailsModal .modal-body img[style*="cursor: pointer"] {
            transition: all 0.3s ease;
            border-radius: 8px;
        }

        #detailsModal .modal-body img[style*="cursor: pointer"]:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            filter: brightness(1.1);
        }

        /* Profile view upgrade modal styles */
        #profileViewUpgradeModal .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        #profileViewUpgradeModal .modal-header {
            background: linear-gradient(135deg, #ffc107 0%, #ff8f00 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            border-bottom: none;
        }

        #profileViewUpgradeModal .display-1 {
            font-size: 4rem;
            opacity: 0.7;
        }

        /* Upgrade Modal Styles */
        .upgrade-modal {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(127, 8, 8, 0.3);
        }
        
        .upgrade-package-card {
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
            border-radius: 12px;
            border: 2px solid #e9ecef;
            overflow: hidden;
        }
        
        .upgrade-package-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-5px) scale(1.03);
            box-shadow: 0 10px 25px rgba(127, 8, 8, 0.15);
        }
        
        .upgrade-package-card.selected {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, rgba(127, 8, 8, 0.05) 0%, rgba(127, 8, 8, 0.02) 100%);
        }
        
        .upgrade-package-header {
            background: linear-gradient(50deg, #fd2c79, #ed0cbd);
            color: white;
            padding: 15px;
            text-align: center;
            position: relative;
        }
        
        .upgrade-package-header::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 10px solid transparent;
            border-right: 10px solid transparent;
            border-top: 10px solid #a00;
        }
        
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        /* Heart animation from original */
        .heart {
            position: absolute;
            color: #e74c3c;
            font-size: 24px;
            pointer-events: none;
            animation: floatUp 3s ease-out forwards;
            opacity: 1;
            z-index: 9999;
            text-shadow: 0 0 10px rgba(231, 76, 60, 0.5);
        }
        
        @keyframes floatUp {
            0% {
                transform: translate(0, 0) scale(0.8);
                opacity: 1;
            }
            50% {
                opacity: 0.8;
            }
            100% {
                transform: translate(var(--drift-x, 0px), -200px) scale(1.5);
                opacity: 0;
            }
        }

        /* Interest Action Container Styles */
        .interest-action-container {
            position: relative;
            display: inline-block;
        }

        .interest-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            border-radius: 50%;
            min-width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
            border: 2px solid white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
            z-index: 10;
        }

        .interest-count:empty {
            display: none;
        }

        .interest-action-container:hover .interest-count {
            transform: scale(1.1);
            background: linear-gradient(135deg, #ee5a24, #ff6b6b);
        }

        /* Animation for count updates */
        .interest-count.updated {
            animation: countUpdate 0.6s ease-in-out;
        }

        @keyframes countUpdate {
            0% { 
                transform: scale(1); 
                background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            }
            50% { 
                transform: scale(1.3); 
                background: linear-gradient(135deg, #00d2d3, #54a0ff);
            }
            100% { 
                transform: scale(1); 
                background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            }
        }

        /* Disabled interest button styling */
        .interest-btn.interested {
            color: #e91e63 !important;
            cursor: pointer !important;
            opacity: 0.8;
        }
        
        .interest-btn.interested:hover {
            color: #e91e63 !important;
            transform: none !important;
        }
        
        /* Likes count styling */
        .likes-count {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #eee;
        }
        
        .likes-count .badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            transition: all 0.3s ease;
        }
        
        .likes-count .badge.updated {
            transform: scale(1.2);
            background-color: #198754 !important;
            box-shadow: 0 0 10px rgba(25, 135, 84, 0.5);
        }
        
        .likes-count i {
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <!-- App Container -->
    <div class="app-container">
        <!-- App Header -->
        <header class="app-header">
            <div class="header-left">
                <button class="back-button" onclick="goBack()">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <h1 class="header-title">Find Matches</h1>
            </div>
            <div class="header-actions">
                <button class="header-action-btn" id="themeToggle" title="Toggle Theme">
                    <i class="fas fa-moon"></i>
                </button>
                <button class="header-action-btn" onclick="window.location.href='profile.php'" title="Profile">
                    <i class="fas fa-user"></i>
                </button>
            </div>
        </header>

        <!-- Main Content -->
        <main class="content-container">
            <!-- Hero Section -->
            <section class="hero-section">
                <div class="hero-icon">
                    <i class="fas fa-heart"></i>
                </div>
                <h1 class="hero-title">Find Your Perfect Match</h1>
                <p class="hero-subtitle">Browse through verified members to find your life partner</p>
            </section>

            <!-- Perfect Matches Section -->
            <?php if (!empty($perfect_matches)): ?>
            <section class="perfect-matches-section fade-in">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <h2 class="section-title">Perfect Matches for You</h2>
                </div>
                <div class="members-grid">
                    <?php foreach ($perfect_matches as $match): ?>
                    <div class="member-card">
                        <div class="member-badges">
                            <span class="badge-id">ID: <?php echo htmlspecialchars($match['id']); ?></span>
                            <span class="badge-perfect">Perfect Match</span>
                        </div>
                        <div class="member-image">
                            <?php
                            $profileHidden = intval($match['profile_hidden'] ?? 0);
                            $shouldHideProfile = ($profileHidden === 1);
                            
                            if ($shouldHideProfile) {
                                $firstLetter = strtoupper(substr($match['name'] ?? 'U', 0, 1));
                                ?>
                                <div class="profile-initial">
                                    <?php echo $firstLetter; ?>
                                </div>
                                <div class="member-badges" style="top: auto; bottom: 8px;">
                                    <span class="badge-hidden">Hidden Profile</span>
                                </div>
                                <?php
                            } else {
                                $photoField = $match['photo'] ?? '';
                                $imgPath = 'img/d.webp';
                                
                                if (!empty($photoField)) {
                                    $photoField = ltrim($photoField, '/');
                                    
                                    if (strpos($photoField, 'uploads/') === 0) {
                                        $imgPath = $photoField;
                                        $fileCheck = 'https://thennilavu.lk/' . $photoField;
                                    } else {
                                        $imgPath = 'uploads/' . $photoField;
                                        $fileCheck = __DIR__ . '/uploads/' . $photoField;
                                    }
                                    
                                   
                                } else {
                                    $imgPath = 'img/d.webp';
                                }
                                ?>
                                <img src="https://thennilavu.lk/<?php echo $imgPath; ?>" alt="<?php echo htmlspecialchars($match['name']); ?>" onerror="console.log('Image failed to load: <?php echo $imgPath; ?>')">
                                <?php
                            }
                            ?>
                        </div>
                        <div class="member-info">
                            <h3 class="member-name"><?php echo htmlspecialchars($match['name']); ?></h3>
                            <div class="member-details">
                                <div class="member-detail">
                                    <span class="detail-label">Looking for:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($match['looking_for'] ?? '-'); ?></span>
                                </div>
                                <div class="member-detail">
                                    <span class="detail-label">Age:</span>
                                    <span class="detail-value">
                                        <?php
                                        if (!empty($match['dob']) && $match['dob'] !== '0000-00-00') {
                                            $dob = new DateTime($match['dob']);
                                            $now = new DateTime();
                                            echo $now->diff($dob)->y;
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="member-detail">
                                    <span class="detail-label">Religion:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($match['religion'] ?? '-'); ?></span>
                                </div>
                                <div class="member-detail">
                                    <span class="detail-label">Profession:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($match['profession'] ?? '-'); ?></span>
                                </div>
                                <div class="member-detail">
                                    <span class="detail-label">Location:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($match['country'] ?? '-'); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="member-actions">
                            <div class="action-buttons">
                                <button class="action-btn interest" 
                                        data-member-id="<?php echo htmlspecialchars($match['id']); ?>"
                                        onclick="toggleInterest(this, <?php echo htmlspecialchars($match['id']); ?>)"
                                        title="Express Interest">
                                    <i class="fas fa-heart"></i>
                                    <span class="interest-count" id="interest-count-<?php echo htmlspecialchars($match['id']); ?>">
                                        <?php echo htmlspecialchars($match['likes_received'] ?? '0'); ?>
                                    </span>
                                </button>
                                <button class="action-btn whatsapp"
                                        onclick="contactWhatsApp(<?php echo htmlspecialchars($match['id']); ?>, '<?php echo htmlspecialchars(addslashes($match['name'])); ?>', <?php 
                                            if (!empty($match['dob']) && $match['dob'] !== '0000-00-00') {
                                                $dob = new DateTime($match['dob']);
                                                $now = new DateTime();
                                                echo $now->diff($dob)->y;
                                            } else {
                                                echo "'-'";
                                            }
                                        ?>, '<?php echo htmlspecialchars(addslashes($match['profession'] ?? '-')); ?>')"
                                        title="Contact on WhatsApp">
                                    <i class="fab fa-whatsapp"></i>
                                </button>
                            </div>
                            <button class="view-profile-btn"
                                    data-member-id="<?php echo htmlspecialchars($match['id']); ?>"
                                    onclick="openDetails(<?php echo htmlspecialchars($match['id']); ?>)">
                                <i class="fas fa-eye"></i>
                                View
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Filter Section -->
            <div class="filter-card fade-in">
                <div class="filter-header">
                    <div class="filter-title">
                        <i class="fas fa-filter"></i>
                        Filter Members
                        <span class="filter-badge"><?php echo htmlspecialchars($user_type); ?></span>
                    </div>
                    <button class="header-action-btn" onclick="toggleFilters()" title="Toggle Filters">
                        <i class="fas fa-sliders-h"></i>
                    </button>
                </div>
                
                <div id="filterContent">
                    <!-- Sort Options -->
                    <div class="form-group">
                        <label class="form-label">Sort By</label>
                        <select class="form-select" id="f_sort">
                            <option value="newest" <?php echo ($_GET['sort'] ?? 'newest') === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="most_liked" <?php echo ($_GET['sort'] ?? '') === 'most_liked' ? 'selected' : ''; ?>>Most Liked</option>
                        </select>
                    </div>
                    
                    <!-- Filter Fields -->
                    <div class="row g-2">
                        <?php if (in_array('looking_for', $allowed_filters)): ?>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Looking for</label>
                                <select class="form-select" id="f_looking">
                                    <option value="">All</option>
                                    <option value="Male" <?php echo ($_GET['looking_for'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo ($_GET['looking_for'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo ($_GET['looking_for'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (in_array('marital_status', $allowed_filters)): ?>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Marital Status</label>
                                <select class="form-select" id="f_marital">
                                    <option value="">All</option>
                                    <option value="Single" <?php echo ($_GET['marital_status'] ?? '') === 'Single' ? 'selected' : ''; ?>>Single</option>
                                    <option value="Divorced" <?php echo ($_GET['marital_status'] ?? '') === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                    <option value="Widowed" <?php echo ($_GET['marital_status'] ?? '') === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (in_array('religion', $allowed_filters)): ?>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Religion</label>
                                <select class="form-select" id="f_religion">
                                    <option value="">All</option>
                                    <option value="Hindu" <?php echo ($_GET['religion'] ?? '') === 'Hindu' ? 'selected' : ''; ?>>Hindu</option>
                                    <option value="Christian" <?php echo ($_GET['religion'] ?? '') === 'Christian' ? 'selected' : ''; ?>>Christian</option>
                                    <option value="Islam" <?php echo ($_GET['religion'] ?? '') === 'Islam' ? 'selected' : ''; ?>>Islam</option>
                                    <option value="Buddhist" <?php echo ($_GET['religion'] ?? '') === 'Buddhist' ? 'selected' : ''; ?>>Buddhist</option>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (in_array('country', $allowed_filters)): ?>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Country</label>
                                <select class="form-select" id="f_country">
                                    <option value="">All</option>
                                    <option value="Sri Lanka" <?php echo ($_GET['country'] ?? '') === 'Sri Lanka' ? 'selected' : ''; ?>>Sri Lanka</option>
                                    <option value="India" <?php echo ($_GET['country'] ?? '') === 'India' ? 'selected' : ''; ?>>India</option>
                                    <option value="Other" <?php echo ($_GET['country'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (in_array('profession', $allowed_filters)): ?>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Profession</label>
                                <select class="form-select" id="f_profession">
                                    <option value="">All</option>
                                    <?php foreach ($professions as $profession): ?>
                                        <option value="<?php echo htmlspecialchars($profession); ?>" <?php echo ($_GET['profession'] ?? '') === $profession ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($profession); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (in_array('age', $allowed_filters)): ?>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Age</label>
                                <select class="form-select" id="f_age">
                                    <option value="">All</option>
                                    <option value="18-25" <?php echo ($_GET['age'] ?? '') === '18-25' ? 'selected' : ''; ?>>18-25</option>
                                    <option value="26-35" <?php echo ($_GET['age'] ?? '') === '26-35' ? 'selected' : ''; ?>>26-35</option>
                                    <option value="36-45" <?php echo ($_GET['age'] ?? '') === '36-45' ? 'selected' : ''; ?>>36-45</option>
                                    <option value="46-60" <?php echo ($_GET['age'] ?? '') === '46-60' ? 'selected' : ''; ?>>46-60</option>
                                    <option value="60+" <?php echo ($_GET['age'] ?? '') === '60+' ? 'selected' : ''; ?>>60+</option>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Additional filters that might be hidden on mobile -->
                    <div id="moreFilters" class="d-none">
                        <div class="row g-2">
                            <?php if (in_array('education', $allowed_filters)): ?>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Education</label>
                                    <select class="form-select" id="f_education">
                                        <option value="">All</option>
                                        <?php foreach ($education_degrees as $degree): ?>
                                            <option value="<?php echo htmlspecialchars($degree); ?>" <?php echo (isset($_GET['education']) && $_GET['education'] === $degree) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($degree); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select> 
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (in_array('income', $allowed_filters)): ?>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Income</label>
                                    <select class="form-select" id="f_income">
                                        <option value="">All</option>
                                        <option value="<50000" <?php echo ($_GET['income'] ?? '') === '<50000' ? 'selected' : ''; ?>>Below 50,000</option>
                                        <option value="50000-100000" <?php echo ($_GET['income'] ?? '') === '50000-100000' ? 'selected' : ''; ?>>50,000-100,000</option>
                                        <option value="100000-200000" <?php echo ($_GET['income'] ?? '') === '100000-200000' ? 'selected' : ''; ?>>100,000-200,000</option>
                                        <option value=">200000" <?php echo ($_GET['income'] ?? '') === '>200000' ? 'selected' : ''; ?>>Above 200,000</option>
                                    </select>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (in_array('height', $allowed_filters)): ?>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Height</label>
                                    <select class="form-select" id="f_height">
                                        <option value="">All</option>
                                        <option value="<150" <?php echo ($_GET['height'] ?? '') === '<150' ? 'selected' : ''; ?>>Below 150 cm</option>
                                        <option value="150-170" <?php echo ($_GET['height'] ?? '') === '150-170' ? 'selected' : ''; ?>>150-170 cm</option>
                                        <option value="170-190" <?php echo ($_GET['height'] ?? '') === '170-190' ? 'selected' : ''; ?>>170-190 cm</option>
                                        <option value=">190" <?php echo ($_GET['height'] ?? '') === '>190' ? 'selected' : ''; ?>>Above 190 cm</option>
                                    </select>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (in_array('weight', $allowed_filters)): ?>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Weight</label>
                                    <select class="form-select" id="f_weight">
                                        <option value="">All</option>
                                        <option value="<50" <?php echo ($_GET['weight'] ?? '') === '<50' ? 'selected' : ''; ?>>Below 50 kg</option>
                                        <option value="50-70" <?php echo ($_GET['weight'] ?? '') === '50-70' ? 'selected' : ''; ?>>50-70 kg</option>
                                        <option value="70-90" <?php echo ($_GET['weight'] ?? '') === '70-90' ? 'selected' : ''; ?>>70-90 kg</option>
                                        <option value=">90" <?php echo ($_GET['weight'] ?? '') === '>90' ? 'selected' : ''; ?>>Above 90 kg</option>
                                    </select>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Show more filters button -->
                    <div class="text-center mb-3">
                        <button class="btn-secondary btn-sm" onclick="toggleMoreFilters()" id="moreFiltersBtn">
                            <i class="fas fa-chevron-down"></i>
                            More Filters
                        </button>
                    </div>
                    
                    <div class="mt-4">
                        <button class="btn-primary mb-2" id="btnApply">
                            <i class="fas fa-filter"></i>
                            Apply Filters
                        </button>
                        <button class="btn-secondary" onclick="window.location.href='mem.php'">
                            <i class="fas fa-redo"></i>
                            Clear Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Members Section -->
            <div class="section-header mb-3">
                <div class="section-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h2 class="section-title">All Members</h2>
            </div>
            
            <div class="members-grid" id="cardsRoot">
                <?php foreach ($members as $member): ?>
                <div class="member-card fade-in">
                    <div class="member-badges">
                        <span class="badge-id">ID: <?php echo htmlspecialchars($member['id']); ?></span>
                        <span class="badge-package"><?php echo htmlspecialchars($member['package_name'] ?? 'Free'); ?></span>
                    </div>
                    <div class="member-image">
                        <?php
                        $profileHidden = intval($member['profile_hidden'] ?? 0);
                        $shouldHideProfile = ($profileHidden === 1);
                        
                        if ($shouldHideProfile) {
                            $firstLetter = strtoupper(substr($member['name'] ?? 'U', 0, 1));
                            ?>
                            <div class="profile-initial">
                                <?php echo $firstLetter; ?>
                            </div>
                            <div class="member-badges" style="top: auto; bottom: 8px;">
                                <span class="badge-hidden">Hidden Profile</span>
                            </div>
                            <?php
                        } else {
                            $photoField = $member['photo'] ?? '';
                            $imgPath = 'img/d.webp';
                            error_log('🖼️ [PERFECT MATCH] Processing member: ' . ($member['id'] ?? 'unknown') . ', name: ' . ($member['name'] ?? 'unknown'));
                            error_log('🖼️ [PERFECT MATCH] Photo field: ' . $photoField);
                            
                            if (!empty($photoField)) {
                                $photoField = ltrim($photoField, '/');
                                error_log('🖼️ [PERFECT MATCH] After trim: ' . $photoField);
                                
                                if (strpos($photoField, 'uploads/') === 0) {
                                    $imgPath = $photoField;
                                    $fileCheck = __DIR__ . '/' . $photoField;
                                    error_log('🖼️ [PERFECT MATCH] Path type: already has uploads/, img: ' . $imgPath);
                                } else {
                                    $imgPath = 'uploads/' . $photoField;
                                    $fileCheck = __DIR__ . '/uploads/' . $photoField;
                                    error_log('🖼️ [PERFECT MATCH] Path type: no uploads/ prefix, img: ' . $imgPath);
                                }
                                
                                error_log('🖼️ [PERFECT MATCH] File check path: ' . $fileCheck);
                                error_log('🖼️ [PERFECT MATCH] File exists: ' . (file_exists($fileCheck) ? 'YES' : 'NO'));
                                
                               
                            } else {
                                error_log('❌ [PERFECT MATCH] Photo field is empty, using default image');
                                $imgPath = 'img/sdfsafsdf.webp';
                            }
                            ?>
                            <img src="http://thennilavu.lk/<?php echo $imgPath; ?>" alt="http://thennilavu.lk/<?php echo $imgPath; ?>" onerror="console.log('Image failed to load: <?php echo $imgPath; ?>')">
                            <?php
                        }
                        ?>
                    </div>
                    <div class="member-info">
                        <h3 class="member-name"><?php echo htmlspecialchars($member['name']); ?></h3>
                        <div class="member-details">
                            <div class="member-detail">
                                <span class="detail-label">Looking for:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($member['looking_for'] ?? '-'); ?></span>
                            </div>
                            <div class="member-detail">
                                <span class="detail-label">Age:</span>
                                <span class="detail-value">
                                    <?php
                                    if (!empty($member['dob']) && $member['dob'] !== '0000-00-00') {
                                        $dob = new DateTime($member['dob']);
                                        $now = new DateTime();
                                        echo $now->diff($dob)->y;
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="member-detail">
                                <span class="detail-label">Religion:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($member['religion'] ?? '-'); ?></span>
                            </div>
                            <div class="member-detail">
                                <span class="detail-label">Profession:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($member['profession'] ?? '-'); ?></span>
                            </div>
                            <div class="member-detail">
                                <span class="detail-label">Location:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($member['country'] ?? '-'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="member-actions">
                        <div class="action-buttons">
                            <button class="action-btn interest" 
                                    data-member-id="<?php echo htmlspecialchars($member['id']); ?>"
                                    onclick="toggleInterest(this, <?php echo htmlspecialchars($member['id']); ?>)"
                                    title="Express Interest">
                                <i class="fas fa-heart"></i>
                                <span class="interest-count" id="interest-count-<?php echo htmlspecialchars($member['id']); ?>">
                                    <?php echo htmlspecialchars($member['likes_received'] ?? '0'); ?>
                                </span>
                            </button>
                            <button class="action-btn whatsapp"
                                    onclick="contactWhatsApp(<?php echo htmlspecialchars($member['id']); ?>, '<?php echo htmlspecialchars(addslashes($member['name'])); ?>', <?php 
                                        if (!empty($member['dob']) && $member['dob'] !== '0000-00-00') {
                                            $dob = new DateTime($member['dob']);
                                            $now = new DateTime();
                                            echo $now->diff($dob)->y;
                                        } else {
                                            echo '-';
                                        }
                                    ?>, '<?php echo htmlspecialchars(addslashes($member['profession'] ?? '-')); ?>')"
                                    title="Contact on WhatsApp">
                                <i class="fab fa-whatsapp"></i>
                            </button>
                        </div>
                        <button class="view-profile-btn"
                                data-member-id="<?php echo htmlspecialchars($member['id']); ?>"
                                onclick="openDetails(<?php echo htmlspecialchars($member['id']); ?>)">
                            <i class="fas fa-eye"></i>
                            View
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($members)): ?>
                <div class="empty-state fade-in">
                    <div class="empty-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3 class="empty-title">No Members Found</h3>
                    <p class="empty-text">Try adjusting your filters or check back later for new members.</p>
                    <button class="btn-primary" onclick="window.location.href='mem.php'">
                        <i class="fas fa-redo"></i>
                        Reset Filters
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-container fade-in">
                <?php 
                $query_params = $_GET;
                unset($query_params['page']);
                $query_string = !empty($query_params) ? '&' . http_build_query($query_params) : '';
                ?>
                
                <!-- Previous Button -->
                <button class="page-btn <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>"
                        onclick="<?php echo ($current_page <= 1) ? 'return false;' : 'window.location.href=\'?page=' . ($current_page - 1) . $query_string . '\''; ?>">
                    <i class="fas fa-chevron-left"></i>
                </button>
                
                <?php
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    $active = ($i == $current_page) ? 'active' : '';
                    echo '<button class="page-btn ' . $active . '" onclick="window.location.href=\'?page=' . $i . $query_string . '\'">' . $i . '</button>';
                }
                ?>
                
                <!-- Next Button -->
                <button class="page-btn <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>"
                        onclick="<?php echo ($current_page >= $total_pages) ? 'return false;' : 'window.location.href=\'?page=' . ($current_page + 1) . $query_string . '\''; ?>">
                    <i class="fas fa-chevron-right"></i>
                </button>
                
                <div class="page-info">
                    Showing <?php echo (($current_page - 1) * $records_per_page + 1); ?> to 
                    <?php echo min($current_page * $records_per_page, $total_records); ?> of 
                    <?php echo $total_records; ?> members
                </div>
            </div>
            <?php endif; ?>
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
            <a href="mem.php" class="nav-item active">
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

    <!-- ALL YOUR ORIGINAL MODALS - PRESERVED EXACTLY AS IN YOUR CODE -->
    






<!-- Enhanced Member Details Modal - Mobile Optimized -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-fullscreen">
        <div class="modal-content" style="border-radius: 0;">
            <!-- Mobile Header -->
            <div class="modal-header" style="
                background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
                color: white;
                border-bottom: none;
                padding: 15px;
                position: sticky;
                top: 0;
                z-index: 100;
            ">
                <div class="d-flex align-items-center w-100">
                    <!-- Back Button -->
                    <button type="button" class="btn btn-light btn-sm me-3" data-bs-dismiss="modal" style="
                        width: 40px;
                        height: 40px;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    ">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    
                    <!-- Title -->
                    <div class="flex-grow-1">
                        <h6 class="modal-title mb-0" id="detailsModalLabel" style="
                            font-weight: 700;
                            font-size: 1.1rem;
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                        ">Profile Details</h6>
                        <small id="modalSubtitle" style="opacity: 0.9;">ID: - • Age: -</small>
                    </div>
                </div>
            </div>
            
            <!-- Profile Header Section -->
            <div class="profile-header-section" style="
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                padding: 20px 15px;
                border-bottom: 1px solid #dee2e6;
            ">
                <div class="d-flex align-items-center">
                    <!-- Profile Image -->
                    <div class="position-relative me-3">
                        <img id="modalPhoto" src="img/d.webp" alt="Member Photo" 
                             class="profile-image-mobile" 
                             onclick="handleImageClick(this.src, this.alt)"
                             style="
                                width: 80px;
                                height: 80px;
                                border-radius: 50%;
                                border: 3px solid white;
                                box-shadow: 0 3px 10px rgba(0,0,0,0.1);
                                object-fit: cover;
                             ">
                        <span class="badge" id="modalPackageBadge" style="
                            position: absolute;
                            bottom: 0;
                            right: 0;
                            background: #ff9800;
                            color: white;
                            font-size: 0.65rem;
                            padding: 2px 6px;
                        ">Free</span>
                    </div>
                    
                    <!-- Basic Info -->
                    <div class="flex-grow-1">
                        <h5 id="modalName" class="mb-1" style="
                            font-weight: 700;
                            font-size: 1.2rem;
                            color: #333;
                        ">Member Name</h5>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <span class="badge" style="
                                background: rgba(233, 30, 99, 0.1);
                                color: #e91e63;
                                font-weight: 500;
                                padding: 4px 8px;
                                border-radius: 12px;
                                font-size: 0.75rem;
                            ">
                                <i class="fas fa-briefcase me-1"></i>
                                <span id="modalProfession">-</span>
                            </span>
                        </div>
                        <div style="font-size: 0.85rem; color: #666;">
                            <span class="me-3">
                                <i class="fas fa-venus-mars me-1"></i>
                                <span id="modalGender">-</span>
                            </span>
                            <span>
                                <i class="fas fa-ring me-1"></i>
                                <span id="modalMarital">-</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Mobile Tabs - FIXED WITH ICONS -->
            <div class="tabs-scroll-container" style="
                position: sticky;
                top: 56px;
                background: white;
                z-index: 90;
                border-bottom: 1px solid #dee2e6;
                padding: 10px 15px 0;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                white-space: nowrap;
            ">
                <div class="d-flex gap-2" style="min-width: max-content; padding-bottom: 5px;">
                    <!-- Fixed tab buttons with icons -->
                    <button class="mobile-tab-btn active" data-tab="basic">
                        <div class="mobile-tab-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <span class="mobile-tab-text">Basic</span>
                    </button>
                    
                    <button class="mobile-tab-btn" data-tab="physical">
                        <div class="mobile-tab-icon">
                            <i class="fas fa-heartbeat"></i>
                        </div>
                        <span class="mobile-tab-text">Physical</span>
                    </button>
                    
                    <button class="mobile-tab-btn" data-tab="family">
                        <div class="mobile-tab-icon">
                            <i class="fas fa-home"></i>
                        </div>
                        <span class="mobile-tab-text">Family</span>
                    </button>
                    
                    <button class="mobile-tab-btn" data-tab="education">
                        <div class="mobile-tab-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <span class="mobile-tab-text">Education</span>
                    </button>
                    
                    <button class="mobile-tab-btn" data-tab="partner">
                        <div class="mobile-tab-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <span class="mobile-tab-text">Partner</span>
                    </button>
                    
                    <?php if ($horoscope_access): ?>
                    <button class="mobile-tab-btn" data-tab="horoscope">
                        <div class="mobile-tab-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <span class="mobile-tab-text">Horoscope</span>
                    </button>
                    <?php else: ?>
                    <button class="mobile-tab-btn locked" onclick="showToast('Upgrade to view horoscope', 'warning')">
                        <div class="mobile-tab-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <span class="mobile-tab-text">Horoscope</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Tab Content - SCROLLABLE AREA -->
            <div class="modal-body" style="padding: 0; overflow-y: auto; height: calc(100vh - 250px);">
                <!-- Basic Information -->
                <div id="basicContent" class="mobile-tab-content active">
                    <!-- Personal Info Card -->
                    <div class="mobile-info-card">
                        <div class="mobile-card-header">
                            <div class="mobile-card-icon" style="background: #e91e63;">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <div>
                                <h6 class="mobile-card-title">Personal Information</h6>
                                <small class="mobile-card-subtitle">Basic details about the member</small>
                            </div>
                        </div>
                        <div class="mobile-card-body">
                            <div class="mobile-info-item">
                                <div class="mobile-info-label">
                                    <i class="fas fa-church me-2"></i>
                                    Religion
                                </div>
                                <div class="mobile-info-value" id="religionVal">-</div>
                            </div>
                            <div class="mobile-info-item">
                                <div class="mobile-info-label">
                                    <i class="fas fa-language me-2"></i>
                                    Language
                                </div>
                                <div class="mobile-info-value" id="languageVal">-</div>
                            </div>
                            <div class="mobile-info-item">
                                <div class="mobile-info-label">
                                    <i class="fas fa-calendar-alt me-2"></i>
                                    Date of Birth
                                </div>
                                <div class="mobile-info-value" id="dobVal">-</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Professional Info Card -->
                    <div class="mobile-info-card">
                        <div class="mobile-card-header">
                            <div class="mobile-card-icon" style="background: #2196f3;">
                                <i class="fas fa-briefcase"></i>
                            </div>
                            <div>
                                <h6 class="mobile-card-title">Professional Information</h6>
                                <small class="mobile-card-subtitle">Career and financial details</small>
                            </div>
                        </div>
                        <div class="mobile-card-body">
                            <div class="mobile-info-item">
                                <div class="mobile-info-label">
                                    <i class="fas fa-money-bill-wave me-2"></i>
                                    Income
                                </div>
                                <div class="mobile-info-value" id="incomeVal">-</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Lifestyle Card -->
                    <div class="mobile-info-card">
                        <div class="mobile-card-header">
                            <div class="mobile-card-icon" style="background: #4caf50;">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <div>
                                <h6 class="mobile-card-title">Lifestyle</h6>
                                <small class="mobile-card-subtitle">Habits and preferences</small>
                            </div>
                        </div>
                        <div class="mobile-card-body">
                            <div class="mobile-info-item">
                                <div class="mobile-info-label">
                                    <i class="fas fa-smoking me-2"></i>
                                    Smoking
                                </div>
                                <div class="mobile-info-value" id="smokingVal">-</div>
                            </div>
                            <div class="mobile-info-item">
                                <div class="mobile-info-label">
                                    <i class="fas fa-glass-whiskey me-2"></i>
                                    Drinking
                                </div>
                                <div class="mobile-info-value" id="drinkingVal">-</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Physical Information -->
                <div id="physicalContent" class="mobile-tab-content">
                    <div class="mobile-info-card">
                        <div class="mobile-card-header">
                            <div class="mobile-card-icon" style="background: #ff5722;">
                                <i class="fas fa-heartbeat"></i>
                            </div>
                            <div>
                                <h6 class="mobile-card-title">Physical Details</h6>
                                <small class="mobile-card-subtitle">Body measurements and features</small>
                            </div>
                        </div>
                        <div class="mobile-card-body">
                            <div class="mobile-info-grid">
                                <div class="mobile-grid-item">
                                    <div class="mobile-grid-icon" style="background: #e91e63;">
                                        <i class="fas fa-ruler-vertical"></i>
                                    </div>
                                    <div class="mobile-grid-content">
                                        <small>Height</small>
                                        <div class="mobile-grid-value" id="heightVal">-</div>
                                    </div>
                                </div>
                                <div class="mobile-grid-item">
                                    <div class="mobile-grid-icon" style="background: #2196f3;">
                                        <i class="fas fa-weight"></i>
                                    </div>
                                    <div class="mobile-grid-content">
                                        <small>Weight</small>
                                        <div class="mobile-grid-value" id="weightVal">-</div>
                                    </div>
                                </div>
                                <div class="mobile-grid-item">
                                    <div class="mobile-grid-icon" style="background: #9c27b0;">
                                        <i class="fas fa-palette"></i>
                                    </div>
                                    <div class="mobile-grid-content">
                                        <small>Complexion</small>
                                        <div class="mobile-grid-value" id="complexionVal">-</div>
                                    </div>
                                </div>
                                <div class="mobile-grid-item">
                                    <div class="mobile-grid-icon" style="background: #ff9800;">
                                        <i class="fas fa-tint"></i>
                                    </div>
                                    <div class="mobile-grid-content">
                                        <small>Blood Group</small>
                                        <div class="mobile-grid-value" id="bloodVal">-</div>
                                    </div>
                                </div>
                                <div class="mobile-grid-item">
                                    <div class="mobile-grid-icon" style="background: #4caf50;">
                                        <i class="fas fa-eye"></i>
                                    </div>
                                    <div class="mobile-grid-content">
                                        <small>Eye Color</small>
                                        <div class="mobile-grid-value" id="eyeVal">-</div>
                                    </div>
                                </div>
                                <div class="mobile-grid-item">
                                    <div class="mobile-grid-icon" style="background: #795548;">
                                        <i class="fas fa-cut"></i>
                                    </div>
                                    <div class="mobile-grid-content">
                                        <small>Hair Color</small>
                                        <div class="mobile-grid-value" id="hairVal">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Family Information -->
                <div id="familyContent" class="mobile-tab-content">
                    <div class="mobile-info-card">
                        <div class="mobile-card-header">
                            <div class="mobile-card-icon" style="background: #9c27b0;">
                                <i class="fas fa-users"></i>
                            </div>
                            <div>
                                <h6 class="mobile-card-title">Family Background</h6>
                                <small class="mobile-card-subtitle">Family members and details</small>
                            </div>
                        </div>
                        <div class="mobile-card-body">
                            <!-- Father -->
                            <div class="mobile-family-member">
                                <div class="mobile-family-avatar" style="background: #2196f3;">
                                    <i class="fas fa-male"></i>
                                </div>
                                <div class="mobile-family-details">
                                    <h6>Father</h6>
                                    <div class="mobile-family-info">
                                        <span id="fatherVal">-</span>
                                        <small>Profession: <span id="fatherProfessionVal">-</span></small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Mother -->
                            <div class="mobile-family-member">
                                <div class="mobile-family-avatar" style="background: #e91e63;">
                                    <i class="fas fa-female"></i>
                                </div>
                                <div class="mobile-family-details">
                                    <h6>Mother</h6>
                                    <div class="mobile-family-info">
                                        <span id="motherVal">-</span>
                                        <small>Profession: <span id="motherProfessionVal">-</span></small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Siblings -->
                            <div class="mobile-siblings-section">
                                <div class="mobile-sibling-card">
                                    <div class="mobile-sibling-icon" style="background: #2196f3;">
                                        <i class="fas fa-male"></i>
                                    </div>
                                    <div class="mobile-sibling-info">
                                        <div class="mobile-sibling-count" id="brothersVal">-</div>
                                        <small>Brothers</small>
                                    </div>
                                </div>
                                <div class="mobile-sibling-card">
                                    <div class="mobile-sibling-icon" style="background: #e91e63;">
                                        <i class="fas fa-female"></i>
                                    </div>
                                    <div class="mobile-sibling-info">
                                        <div class="mobile-sibling-count" id="sistersVal">-</div>
                                        <small>Sisters</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Education Information -->
                <div id="educationContent" class="mobile-tab-content">
                    <div id="educationContainerMobile">
                        <!-- Education items will be dynamically added here -->
                    </div>
                    <div id="educationEmptyMobile" class="mobile-empty-state" style="display: none;">
                        <div class="mobile-empty-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <h6>No Education Information</h6>
                        <p>This member hasn't added education details yet</p>
                    </div>
                </div>
                
                <!-- Partner Expectations -->
                <div id="partnerContent" class="mobile-tab-content">
                    <div class="mobile-info-card">
                        <div class="mobile-card-header">
                            <div class="mobile-card-icon" style="background: #e91e63;">
                                <i class="fas fa-heart"></i>
                            </div>
                            <div>
                                <h6 class="mobile-card-title">Partner Expectations</h6>
                                <small class="mobile-card-subtitle">What they're looking for</small>
                            </div>
                        </div>
                        <div class="mobile-card-body">
                            <div class="mobile-expectations-grid">
                                <div class="mobile-expectation-item">
                                    <div class="mobile-expectation-icon" style="background: #2196f3;">
                                        <i class="fas fa-globe"></i>
                                    </div>
                                    <div class="mobile-expectation-content">
                                        <small>Country</small>
                                        <div class="mobile-expectation-value" id="prefCountryVal">-</div>
                                    </div>
                                </div>
                                <div class="mobile-expectation-item">
                                    <div class="mobile-expectation-icon" style="background: #ff9800;">
                                        <i class="fas fa-user-clock"></i>
                                    </div>
                                    <div class="mobile-expectation-content">
                                        <small>Age Range</small>
                                        <div class="mobile-expectation-value" id="ageRangeVal">-</div>
                                    </div>
                                </div>
                                <div class="mobile-expectation-item">
                                    <div class="mobile-expectation-icon" style="background: #4caf50;">
                                        <i class="fas fa-ruler-vertical"></i>
                                    </div>
                                    <div class="mobile-expectation-content">
                                        <small>Height Range</small>
                                        <div class="mobile-expectation-value" id="heightRangeVal">-</div>
                                    </div>
                                </div>
                                <div class="mobile-expectation-item">
                                    <div class="mobile-expectation-icon" style="background: #9c27b0;">
                                        <i class="fas fa-ring"></i>
                                    </div>
                                    <div class="mobile-expectation-content">
                                        <small>Marital Status</small>
                                        <div class="mobile-expectation-value" id="prefMaritalVal">-</div>
                                    </div>
                                </div>
                                <div class="mobile-expectation-item">
                                    <div class="mobile-expectation-icon" style="background: #795548;">
                                        <i class="fas fa-church"></i>
                                    </div>
                                    <div class="mobile-expectation-content">
                                        <small>Religion</small>
                                        <div class="mobile-expectation-value" id="prefReligionVal">-</div>
                                    </div>
                                </div>
                                <div class="mobile-expectation-item">
                                    <div class="mobile-expectation-icon" style="background: #ff5722;">
                                        <i class="fas fa-smoking"></i>
                                    </div>
                                    <div class="mobile-expectation-content">
                                        <small>Smoking</small>
                                        <div class="mobile-expectation-value" id="prefSmokingVal">-</div>
                                    </div>
                                </div>
                                <div class="mobile-expectation-item">
                                    <div class="mobile-expectation-icon" style="background: #2196f3;">
                                        <i class="fas fa-glass-whiskey"></i>
                                    </div>
                                    <div class="mobile-expectation-content">
                                        <small>Drinking</small>
                                        <div class="mobile-expectation-value" id="prefDrinkingVal">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Horoscope Information -->
                <div id="horoscopeContent" class="mobile-tab-content">
                    <?php if ($horoscope_access): ?>
                    <div class="mobile-info-card">
                        <div class="mobile-card-header">
                            <div class="mobile-card-icon" style="background: #ff9800;">
                                <i class="fas fa-star"></i>
                            </div>
                            <div>
                                <h6 class="mobile-card-title">Horoscope Details</h6>
                                <small class="mobile-card-subtitle">Astrological information</small>
                            </div>
                        </div>
                        <div class="mobile-card-body">
                            <div class="mobile-horoscope-grid">
                                <div class="mobile-horoscope-item">
                                    <div class="mobile-horoscope-icon" style="background: #ff5722;">
                                        <i class="fas fa-calendar-day"></i>
                                    </div>
                                    <div class="mobile-horoscope-content">
                                        <small>Birth Date</small>
                                        <div class="mobile-horoscope-value" id="horoscopeBirthDate">-</div>
                                    </div>
                                </div>
                                <div class="mobile-horoscope-item">
                                    <div class="mobile-horoscope-icon" style="background: #2196f3;">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="mobile-horoscope-content">
                                        <small>Birth Time</small>
                                        <div class="mobile-horoscope-value" id="horoscopeBirthTime">-</div>
                                    </div>
                                </div>
                                <div class="mobile-horoscope-item">
                                    <div class="mobile-horoscope-icon" style="background: #ff9800;">
                                        <i class="fas fa-sun"></i>
                                    </div>
                                    <div class="mobile-horoscope-content">
                                        <small>Zodiac Sign</small>
                                        <div class="mobile-horoscope-value" id="horoscopeZodiac">-</div>
                                    </div>
                                </div>
                                <div class="mobile-horoscope-item">
                                    <div class="mobile-horoscope-icon" style="background: #9c27b0;">
                                        <i class="fas fa-star-and-crescent"></i>
                                    </div>
                                    <div class="mobile-horoscope-content">
                                        <small>Nakshatra</small>
                                        <div class="mobile-horoscope-value" id="horoscopeNakshatra">-</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Compatibility Button -->
                            <div class="mobile-compatibility-section">
                                <button class="mobile-compatibility-btn" onclick="showNakshatraComparison()">
                                    <i class="fas fa-heart me-2"></i>
                                    Check Marriage Compatibility
                                </button>
                                
                                <div id="nakshatraDisplayMobile" class="mobile-nakshatra-comparison" style="display: none;">
                                    <div class="mobile-comparison-grid">
                                        <div class="mobile-comparison-item">
                                            <small>Your Nakshatra</small>
                                            <div class="mobile-comparison-value" id="loggedUserNakshatra">-</div>
                                        </div>
                                        <div class="mobile-comparison-item">
                                            <small>Partner's Nakshatra</small>
                                            <div class="mobile-comparison-value" id="memberNakshatra">-</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="mobile-upgrade-prompt">
                        <div class="mobile-upgrade-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <h5>Horoscope Access Restricted</h5>
                        <p>
                            <?php if ($search_access === 'Basic'): ?>
                                Free users do not have access to horoscope information.
                            <?php else: ?>
                                Your current package does not include matchmaker services.
                            <?php endif ?>
                        </p>
                        <a href="package.php" class="mobile-upgrade-btn">
                            <i class="fas fa-arrow-up-circle me-2"></i>
                            Upgrade Package
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Hidden Desktop Education Container for JavaScript -->
            <div id="educationContainer" style="display: none;">
                <!-- Education items will be dynamically added here by JavaScript -->
            </div>
            
            <!-- Fixed Bottom Actions -->
            <div class="mobile-bottom-actions">
                <button class="mobile-action-btn interest-btn" onclick="toggleInterestModal()">
                    <i class="fas fa-heart me-2"></i>
                    Express Interest
                </button>
                <button class="mobile-action-btn whatsapp-btn" onclick="contactWhatsAppModal()">
                    <i class="fab fa-whatsapp me-2"></i>
                    WhatsApp
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Enhanced Profile Modal Styles */
.profile-modal {
    border-radius: 20px;
    overflow: hidden;
    border: none;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
}

.profile-modal .modal-header {
    background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
    color: white;
    border-bottom: none;
    padding: 30px;
    position: relative;
    overflow: hidden;
    min-height: 180px;
}

.profile-modal .modal-header:before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at 30% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
}

.profile-modal .header-content {
    display: flex;
    align-items: center;
    gap: 25px;
    position: relative;
    z-index: 1;
}

.profile-modal .profile-avatar {
    position: relative;
    flex-shrink: 0;
}

.profile-modal .profile-image-modal {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    border: 4px solid rgba(255, 255, 255, 0.3);
    object-fit: cover;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.profile-modal .profile-image-modal:hover {
    transform: scale(1.05);
    border-color: rgba(255, 255, 255, 0.6);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
}

.profile-modal .avatar-badge {
    position: absolute;
    bottom: 0;
    right: 0;
    background: linear-gradient(135deg, #ff9800 0%, #ff5722 100%);
    color: white;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    border: 2px solid white;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.profile-modal .profile-header-info {
    flex: 1;
}

.profile-modal .modal-title {
    font-size: 1.8rem;
    font-weight: 800;
    margin-bottom: 10px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.profile-modal .profile-meta {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.profile-modal .badge {
    padding: 8px 15px;
    border-radius: 15px;
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.profile-modal .profile-subtitle {
    display: flex;
    gap: 20px;
    margin-top: 10px;
    color: rgba(255, 255, 255, 0.9);
    font-size: 1rem;
}

.profile-modal .profile-subtitle span {
    display: flex;
    align-items: center;
    gap: 8px;
}

.profile-modal .btn-close {
    filter: invert(1);
    opacity: 0.8;
    transition: all 0.3s ease;
    position: absolute;
    top: 20px;
    right: 20px;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.profile-modal .btn-close:hover {
    opacity: 1;
    background: rgba(0, 0, 0, 0.3);
    transform: rotate(90deg);
}

/* Tabs Container */
.profile-modal .tabs-container {
    background: white;
    border-bottom: 1px solid #e9ecef;
    padding: 0 30px;
    position: sticky;
    top: 0;
    z-index: 10;
}

.profile-modal .tabs-scroll {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    padding: 20px 0;
}

.profile-modal .tabs-scroll::-webkit-scrollbar {
    height: 4px;
}

.profile-modal .tabs-scroll::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 2px;
}

.profile-modal .tabs-scroll::-webkit-scrollbar-thumb {
    background: #e91e63;
    border-radius: 2px;
}

.profile-modal .tabs-wrapper {
    display: flex;
    gap: 5px;
    min-width: max-content;
}

.profile-modal .tab-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 15px 20px;
    background: #f8f9fa;
    border: none;
    border-radius: 15px;
    color: #6c757d;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    min-width: 100px;
    cursor: pointer;
    border: 2px solid transparent;
}

.profile-modal .tab-btn i {
    font-size: 1.2rem;
    margin-bottom: 5px;
}

.profile-modal .tab-btn:hover {
    background: #e9ecef;
    color: #495057;
    transform: translateY(-2px);
}

.profile-modal .tab-btn.active {
    background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
    color: white;
    border-color: rgba(255, 255, 255, 0.3);
    box-shadow: 0 5px 20px rgba(233, 30, 99, 0.3);
}

.profile-modal .tab-btn.disabled {
    opacity: 0.6;
    cursor: not-allowed;
    background: #f8f9fa;
    color: #6c757d;
}

.profile-modal .tab-btn.disabled:hover {
    transform: none;
    background: #f8f9fa;
}


/* Tab Content Styling */
.profile-modal .tab-content-wrapper {
    margin-top: 10px;
}

.profile-modal .tab-content {
    display: none;
    animation: fadeIn 0.5s ease;
    padding: 8px 2px 8px 2px;
    opacity: 0;
    transition: opacity 0.3s ease;
    position: relative;
}

.profile-modal .tab-content.active {
    display: block;
    opacity: 1;
    z-index: 1;
}

/* Ensure all tabs are properly positioned */
.profile-modal .tab-content.tab-pane {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
}

.profile-modal .tab-content.tab-pane.active {
    position: relative;
}



/* Section Cards */
.profile-modal .section-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
    border: 1px solid #e9ecef;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.profile-modal .section-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
}

.profile-modal .section-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: #e91e63;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Info Grid */
.profile-modal .info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.profile-modal .info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
    transition: all 0.3s ease;
}

.profile-modal .info-item:hover {
    background: #e9ecef;
    transform: translateX(5px);
}

.profile-modal .info-item.full-width {
    grid-column: 1 / -1;
}

.profile-modal .info-label {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #495057;
    font-weight: 600;
}

.profile-modal .info-label i {
    color: #e91e63;
    font-size: 1.1rem;
    width: 24px;
    text-align: center;
}

.profile-modal .info-value {
    color: #212529;
    font-weight: 500;
    text-align: right;
    flex: 1;
    padding-left: 10px;
    word-break: break-word;
}

/* Family Section */
.profile-modal .family-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.profile-modal .family-member {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 10px;
    transition: all 0.3s ease;
}

.profile-modal .family-member:hover {
    background: #e9ecef;
    transform: translateY(-3px);
}

.profile-modal .family-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
}

.profile-modal .family-info h6 {
    font-weight: 700;
    color: #495057;
    margin-bottom: 5px;
}

.profile-modal .family-details {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.profile-modal .siblings-info {
    display: flex;
    gap: 20px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 10px;
    grid-column: 1 / -1;
}

.profile-modal .sibling-count {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px 20px;
    background: white;
    border-radius: 10px;
    flex: 1;
}

.profile-modal .sibling-count i {
    font-size: 2rem;
    color: #e91e63;
}

.profile-modal .sibling-count div {
    display: flex;
    flex-direction: column;
}

.profile-modal .sibling-count span {
    font-size: 1.5rem;
    font-weight: 700;
    color: #212529;
}

/* Education Section */
.profile-modal #educationContainer {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.profile-modal .education-item {
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    padding: 25px;
    border-radius: 15px;
    border-left: 5px solid #e91e63;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.profile-modal .education-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
    border-left-color: #9c27b0;
}

.profile-modal .education-item h6 {
    color: #e91e63;
    font-weight: 700;
    margin-bottom: 15px;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.profile-modal .education-item p {
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.profile-modal .education-item p strong {
    color: #495057;
    min-width: 150px;
    font-weight: 600;
}

/* Expectations Grid */
.profile-modal .expectations-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.profile-modal .expectation-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 10px;
    transition: all 0.3s ease;
}

.profile-modal .expectation-item:hover {
    background: #e9ecef;
    transform: translateY(-3px);
}

.profile-modal .expectation-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
}

.profile-modal .expectation-value {
    font-weight: 600;
    color: #212529;
    margin-top: 5px;
}

/* Horoscope Section */
.profile-modal .horoscope-basic {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.profile-modal .horoscope-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
}

.profile-modal .horoscope-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #ff9800 0%, #ff5722 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.1rem;
}

.profile-modal .horoscope-value {
    font-weight: 600;
    color: #212529;
    margin-top: 5px;
}

/* Compatibility Section */
.profile-modal .compatibility-section {
    background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
    padding: 25px;
    border-radius: 15px;
    margin: 25px 0;
    text-align: center;
    border: 2px dashed #ff9800;
}

.profile-modal .compatibility-btn {
    background: linear-gradient(135deg, #ff9800 0%, #ff5722 100%);
    color: white;
    border: none;
    padding: 15px 30px;
    border-radius: 25px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 5px 20px rgba(255, 152, 0, 0.3);
}

.profile-modal .compatibility-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(255, 152, 0, 0.4);
}

.profile-modal .nakshatra-comparison {
    margin-top: 20px;
    padding: 20px;
    background: white;
    border-radius: 10px;
    border: 1px solid #e9ecef;
}

.profile-modal .comparison-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.profile-modal .comparison-item {
    text-align: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
}

.profile-modal .comparison-label {
    font-size: 0.9rem;
    color: #6c757d;
    margin-bottom: 5px;
}

.profile-modal .comparison-value {
    font-size: 1.3rem;
    font-weight: 700;
    color: #e91e63;
    margin-bottom: 5px;
}

/* Make existing info-card-mobile match other tab styling */
.info-card-mobile {
    background: white;
    border-radius: 12px;
    margin-bottom: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    border: 1px solid #eee;
    overflow: hidden;
}

.info-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px;
    background: #fafafa;
    border-bottom: 1px solid #eee;
}

.info-card-header i.fa-graduation-cap {
    color: #2196f3;
    font-size: 1.2rem;
}

.mobile-card-title {
    font-weight: 700;
    color: #333;
    margin: 0;
    font-size: 1rem;
}

.mobile-card-subtitle {
    color: #999;
    font-size: 0.8rem;
}

.info-list {
    padding: 15px;
}

.info-item-mobile {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f5f5f5;
}

.info-item-mobile:last-child {
    border-bottom: none;
}

.info-label {
    color: #666;
    font-weight: 500;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
}

.info-value {
    color: #333;
    font-weight: 600;
    font-size: 0.9rem;
    text-align: right;
    max-width: 60%;
    word-break: break-word;
}

/* Horoscope Images */
.profile-modal .horoscope-images-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
    margin-top: 30px;
}

.profile-modal .horoscope-img-card {
    background: white;
    padding: 20px;
    border-radius: 15px;
    text-align: center;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.profile-modal .horoscope-img-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
}

.profile-modal .horoscope-img-card img {
    width: 100%;
    max-width: 250px;
    height: auto;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.profile-modal .horoscope-img-card img:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
}

/* Upgrade Prompt */
.profile-modal .upgrade-prompt {
    text-align: center;
    padding: 50px 30px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 15px;
    border: 2px dashed #6c757d;
}

.profile-modal .upgrade-icon {
    font-size: 4rem;
    color: #dc3545;
    margin-bottom: 20px;
}

.profile-modal .upgrade-prompt h5 {
    color: #212529;
    margin-bottom: 15px;
    font-weight: 700;
}

.profile-modal .upgrade-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
    color: white;
    padding: 12px 30px;
    border-radius: 25px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    margin-top: 20px;
}

.profile-modal .upgrade-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(233, 30, 99, 0.3);
    color: white;
}

/* Modal Footer */
.profile-modal .modal-footer {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-top: 1px solid #dee2e6;
    padding: 25px 30px;
    border-radius: 0 0 20px 20px;
}

.profile-modal .footer-actions {
    display: flex;
    gap: 15px;
    width: 100%;
    justify-content: center;
}

.profile-modal .btn-action {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 25px;
    border-radius: 25px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 180px;
    justify-content: center;
}

.profile-modal .interest-btn {
    background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
    color: white;
}

.profile-modal .whatsapp-btn {
    background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
    color: white;
}

.profile-modal .close-btn {
    background: #6c757d;
    color: white;
}

.profile-modal .btn-action:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
}

/* Empty State */
.profile-modal .empty-state {
    text-align: center;
    padding: 50px 20px;
    color: #6c757d;
}

.profile-modal .empty-state i {
    font-size: 3rem;
    margin-bottom: 15px;
    opacity: 0.5;
}

/* Animations */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}


/* Mobile-optimized styles */
@media (max-width: 768px) {
    /* Fullscreen modal */
    .modal-dialog.modal-fullscreen {
        margin: 0;
        max-width: 100%;
        height: 100vh;
    }
    
    .modal-content {
        height: 100vh;
        border-radius: 0 !important;
    }
    
    /* Mobile Tab Buttons - FIXED WITH ICONS */
    .mobile-tab-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 8px 12px;
        background: #f8f9fa;
        border: none;
        border-radius: 12px;
        color: #666;
        font-size: 0.75rem;
        font-weight: 500;
        min-width: 65px;
        transition: all 0.3s ease;
        white-space: nowrap;
        gap: 5px;
    }
    
    .mobile-tab-btn.active {
        background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(233, 30, 99, 0.3);
    }
    
    .mobile-tab-btn.locked {
        opacity: 0.6;
    }
    
    .mobile-tab-icon {
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
    }
    
    .mobile-tab-text {
        font-size: 0.7rem;
        font-weight: 500;
    }
    
    /* Tab Content - Scrollable */
   .mobile-tab-content {
    display: none;
    padding: 15px;
    animation: fadeIn 0.3s ease;
    max-height: calc(100vh - 250px);
    overflow-y: auto;
    margin-bottom:50px;
}

    
    .mobile-tab-content.active {
        display: block;
    }
    
    /* Info Cards */
    .mobile-info-card {
        background: white;
        border-radius: 12px;
        margin-bottom: 15px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        border: 1px solid #eee;
        overflow: hidden;
    }
    
    .mobile-card-header {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 15px;
        background: #fafafa;
        border-bottom: 1px solid #eee;
    }
    
    .mobile-card-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.1rem;
    }
    
    .mobile-card-title {
        font-weight: 700;
        color: #333;
        margin: 0;
        font-size: 1rem;
    }
    
    .mobile-card-subtitle {
        color: #999;
        font-size: 0.8rem;
    }
    
    .mobile-card-body {
        padding: 15px;
    }
    
    /* Info Items */
    .mobile-info-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid #f5f5f5;
    }
    
    .mobile-info-item:last-child {
        border-bottom: none;
    }
    
    .mobile-info-label {
        color: #666;
        font-weight: 500;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
    }
    
    .mobile-info-value {
        color: #333;
        font-weight: 600;
        font-size: 0.9rem;
        text-align: right;
        max-width: 60%;
        word-break: break-word;
    }
    
    /* Grid Layout for Physical Details */
    .mobile-info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .mobile-grid-item {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 12px;
        text-align: center;
    }
    
    .mobile-grid-icon {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        margin: 0 auto 8px;
        font-size: 1rem;
    }
    
    .mobile-grid-content small {
        color: #666;
        font-size: 0.8rem;
        display: block;
        margin-bottom: 4px;
    }
    
    .mobile-grid-value {
        font-weight: 600;
        color: #333;
        font-size: 0.9rem;
    }
    
    /* Family Members */
    .mobile-family-member {
        display: flex;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #f5f5f5;
    }
    
    .mobile-family-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.1rem;
        margin-right: 12px;
    }
    
    .mobile-family-details h6 {
        font-weight: 700;
        color: #333;
        margin: 0 0 4px;
        font-size: 0.95rem;
    }
    
    .mobile-family-info {
        font-size: 0.85rem;
        color: #666;
    }
    
    .mobile-family-info span {
        display: block;
        margin-bottom: 2px;
    }
    
    /* Siblings Section */
    .mobile-siblings-section {
        display: flex;
        justify-content: space-around;
        padding: 15px 0;
    }
    
    .mobile-sibling-card {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .mobile-sibling-icon {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }
    
    .mobile-sibling-info {
        display: flex;
        flex-direction: column;
    }
    
    .mobile-sibling-count {
        font-weight: 700;
        font-size: 1.2rem;
        color: #333;
    }
    
    .mobile-sibling-info small {
        font-size: 0.8rem;
        color: #999;
    }
    
    /* Expectations Grid */
    .mobile-expectations-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .mobile-expectation-item {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 12px;
        text-align: center;
    }
    
    .mobile-expectation-icon {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        margin: 0 auto 8px;
        font-size: 1rem;
    }
    
    .mobile-expectation-content small {
        color: #666;
        font-size: 0.8rem;
        display: block;
        margin-bottom: 4px;
    }
    
    .mobile-expectation-value {
        font-weight: 600;
        color: #333;
        font-size: 0.9rem;
    }
    
    /* Horoscope Grid */
    .mobile-horoscope-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        margin-bottom: 20px;
    }
    
    .mobile-horoscope-item {
        background: #fff8e1;
        border-radius: 10px;
        padding: 12px;
        text-align: center;
    }
    
    .mobile-horoscope-icon {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        margin: 0 auto 8px;
        font-size: 1rem;
    }
    
    .mobile-horoscope-content small {
        color: #666;
        font-size: 0.8rem;
        display: block;
        margin-bottom: 4px;
    }
    
    .mobile-horoscope-value {
        font-weight: 600;
        color: #333;
        font-size: 0.9rem;
    }
    
    /* Compatibility Section */
    .mobile-compatibility-section {
        text-align: center;
        padding: 20px 0;
        background: #fff3e0;
        border-radius: 10px;
        margin-top: 20px;
    }
    
    .mobile-compatibility-btn {
        background: linear-gradient(135deg, #ff9800 0%, #ff5722 100%);
        color: white;
        border: none;
        padding: 12px 20px;
        border-radius: 25px;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
    }
    
    /* Upgrade Prompt */
    .mobile-upgrade-prompt {
        text-align: center;
        padding: 40px 20px;
        background: #f8f9fa;
        border-radius: 12px;
        margin: 20px;
    }
    
    .mobile-upgrade-icon {
        font-size: 3rem;
        color: #dc3545;
        margin-bottom: 15px;
    }
    
    .mobile-upgrade-prompt h5 {
        color: #333;
        margin-bottom: 10px;
        font-weight: 700;
    }
    
    .mobile-upgrade-prompt p {
        color: #666;
        margin-bottom: 20px;
        font-size: 0.9rem;
    }
    
    .mobile-upgrade-btn {
        display: inline-flex;
        align-items: center;
        background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
        color: white;
        padding: 10px 20px;
        border-radius: 25px;
        font-weight: 600;
        text-decoration: none;
        font-size: 0.9rem;
    }
    
    /* Empty State */
    .mobile-empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #999;
    }
    
    .mobile-empty-icon {
        font-size: 2rem;
        color: #ddd;
        margin-bottom: 10px;
    }
    
    /* Bottom Actions */
    .mobile-bottom-actions {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: white;
        border-top: 1px solid #dee2e6;
        padding: 10px 15px;
        display: flex;
        gap: 10px;
        z-index: 1000;
    }
    
    .mobile-action-btn {
        flex: 1;
        padding: 12px;
        border: none;
        border-radius: 25px;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 0.9rem;
    }
    
    .mobile-action-btn.interest-btn {
        background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
        color: white;
    }
    
    .mobile-action-btn.whatsapp-btn {
        background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
        color: white;
    }
    
    /* Scrollbar for tabs */
    .tabs-scroll-container::-webkit-scrollbar {
        display: none;
    }
    
    /* Animation */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
}

/* Desktop fallback */
@media (min-width: 769px) {
    .modal-dialog.modal-fullscreen {
        max-width: 800px;
        margin: 1.75rem auto;
    }
    
    .modal-content {
        border-radius: 20px !important;
    }
    
    .mobile-bottom-actions {
        position: static;
        border-top: 1px solid #dee2e6;
        margin-top: 20px;
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .profile-modal .modal-header {
        padding: 20px;
        flex-direction: column;
        text-align: center;
    }
    
    .profile-modal .header-content {
        flex-direction: column;
        text-align: center;
        gap: 20px;
    }
    
    .profile-modal .profile-subtitle {
        flex-direction: column;
        gap: 10px;
    }
    
    .profile-modal .tabs-container {
        padding: 0 15px;
    }
    
    .profile-modal .tab-btn {
        min-width: 80px;
        padding: 12px 15px;
        font-size: 0.8rem;
    }
    
    .profile-modal .info-grid {
        grid-template-columns: 1fr;
    }
    
    .profile-modal .family-grid,
    .profile-modal .expectations-grid,
    .profile-modal .horoscope-basic {
        grid-template-columns: 1fr;
    }
    
    .profile-modal .footer-actions {
        flex-direction: column;
    }
    
    .profile-modal .btn-action {
        width: 100%;
        min-width: auto;
    }
    
    .profile-modal .modal-body {
        padding: 20px;
        max-height: 50vh;
    }
}

@media (max-width: 576px) {
    .profile-modal .modal-dialog {
        margin: 10px;
    }
    
    .profile-modal .profile-image-modal {
        width: 100px;
        height: 100px;
    }
    
    .profile-modal .modal-title {
        font-size: 1.5rem;
    }
    
    .profile-modal .section-card {
        padding: 12px 8px;
        margin-top: 0;
        margin-bottom: 12px;
    }
    /* Reduce space for the first section-card in modal */
    .profile-modal .tab-pane .section-card:first-of-type {
        padding-top: 6px !important;
        padding-bottom: 10px !important;
        margin-top: 0 !important;
    }
}


/* Add to your existing CSS */

/* Improve modal for mobile */
@media (max-width: 768px) {
    .profile-modal .modal-dialog {
        margin: 10px;
        max-width: 100%;
    }
    
    .profile-modal .header-content {
        flex-direction: column;
        text-align: center;
        gap: 15px;
    }
    
    .profile-modal .profile-subtitle {
        flex-direction: column;
        gap: 8px;
        font-size: 0.9rem;
    }
    
    .profile-modal .tabs-wrapper {
        justify-content: flex-start;
    }
    
    .profile-modal .tab-btn {
        min-width: 80px;
        padding: 10px 12px;
        font-size: 0.8rem;
    }
    
    .profile-modal .info-grid {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    
    .profile-modal .section-card {
        padding: 15px;
    }
    
    .profile-modal .footer-actions {
        flex-direction: column;
        gap: 8px;
    }
    
    .profile-modal .btn-action {
        width: 100%;
    }
}

/* Improve member cards for mobile */
@media (max-width: 480px) {
    .members-grid {
        grid-template-columns: 1fr !important;
    }
    
    .member-card {
        max-width: 100%;
    }
    
    .member-details {
        font-size: 0.85rem;
    }
    
    .view-profile-btn {
        padding: 6px 12px;
        font-size: 0.8rem;
    }
}

/* Better touch targets */
.action-btn,
.nav-item,
.tab-btn {
    min-height: 44px; /* Apple's minimum touch target size */
}

/* Improve readability */
.member-name {
    font-size: 1.1rem;
    line-height: 1.3;
}

.member-detail {
    font-size: 0.9rem;
    line-height: 1.4;
}



@media (max-width: 768px) {
    /* Modal adjustments */
    .modal-dialog.modal-fullscreen {
        margin: 0;
        max-width: 100%;
        height: 100vh;
    }
    
    .modal-content {
        height: 100vh;
        border-radius: 0 !important;
    }
    
    /* Mobile Tab Buttons */
    .tab-btn-mobile {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 8px 12px;
        background: #f8f9fa;
        border: none;
        border-radius: 12px;
        color: #666;
        font-size: 0.75rem;
        font-weight: 500;
        min-width: 70px;
        transition: all 0.3s ease;
        white-space: nowrap;
    }
    
    .tab-btn-mobile i {
        font-size: 1rem;
        margin-bottom: 4px;
    }
    
    .tab-btn-mobile.active {
        background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(233, 30, 99, 0.3);
    }
    
    /* Tab Content */
    .tab-content-mobile {
        display: none;
        padding: 15px;
        animation: fadeIn 0.3s ease;
    }
    
    .tab-content-mobile.active {
        display: block;
    }
    
    /* Info Cards */
    .info-card-mobile {
        background: white;
        border-radius: 12px;
        margin-bottom: 15px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        border: 1px solid #eee;
        overflow: hidden;
    }
    
    .info-card-header {
        padding: 15px;
        font-weight: 700;
        font-size: 1rem;
        color: #333;
        border-bottom: 1px solid #eee;
        background: #fafafa;
        display: flex;
        align-items: center;
    }
    
    .info-list {
        padding: 0;
    }
    
    .info-item-mobile {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 15px;
        border-bottom: 1px solid #f5f5f5;
    }
    
    .info-item-mobile:last-child {
        border-bottom: none;
    }
    
    .info-label {
        color: #666;
        font-weight: 500;
        font-size: 0.9rem;
    }
    
    .info-value {
        color: #333;
        font-weight: 600;
        font-size: 0.9rem;
        text-align: right;
        max-width: 60%;
        word-break: break-word;
    }
    
    /* Family Members */
    .family-member-mobile {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        border-bottom: 1px solid #f5f5f5;
    }
    
    .family-icon-mobile {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.1rem;
        margin-right: 12px;
    }
    
    .family-info-mobile h6 {
        font-weight: 700;
        color: #333;
        margin-bottom: 4px;
        font-size: 0.95rem;
    }
    
    .family-details-mobile {
        font-size: 0.85rem;
        color: #666;
    }
    
    /* Siblings Section */
    .siblings-section-mobile {
        display: flex;
        justify-content: space-around;
        padding: 15px;
    }
    
    .sibling-count-mobile {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .sibling-icon {
        width: 35px;
        height: 35px;
        background: #f0f0f0;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #666;
    }
    
    .sibling-info {
        display: flex;
        flex-direction: column;
    }
    
    .sibling-info span {
        font-weight: 700;
        font-size: 1.2rem;
        color: #333;
    }
    
    .sibling-info small {
        font-size: 0.8rem;
        color: #999;
    }
    
    /* Expectations Grid */
    .expectation-grid-mobile {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        padding: 15px;
    }
    
    .expectation-item-mobile {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 12px;
        text-align: center;
    }
    
    .expectation-icon-mobile {
        width: 35px;
        height: 35px;
        background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        margin: 0 auto 8px;
        font-size: 1rem;
    }
    
    .expectation-info small {
        color: #666;
        font-size: 0.8rem;
    }
    
    .expectation-value {
        font-weight: 600;
        color: #333;
        margin-top: 4px;
        font-size: 0.9rem;
    }
    
    /* Horoscope Grid */
    .horoscope-grid-mobile {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        padding: 15px;
    }
    
    .horoscope-item-mobile {
        background: #fff8e1;
        border-radius: 10px;
        padding: 12px;
        text-align: center;
    }
    
    .horoscope-icon-mobile {
        width: 35px;
        height: 35px;
        background: linear-gradient(135deg, #ff9800 0%, #ff5722 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        margin: 0 auto 8px;
        font-size: 1rem;
    }
    
    /* Compatibility Section */
    .compatibility-section-mobile {
        padding: 15px;
        text-align: center;
    }
    
    .compatibility-btn-mobile {
        background: linear-gradient(135deg, #ff9800 0%, #ff5722 100%);
        color: white;
        border: none;
        padding: 12px 20px;
        border-radius: 25px;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
    }
    
    /* Upgrade Prompt */
    .upgrade-prompt-mobile {
        text-align: center;
        padding: 40px 20px;
        background: #f8f9fa;
        border-radius: 12px;
        margin: 20px;
    }
    
    .upgrade-icon-mobile {
        font-size: 3rem;
        color: #dc3545;
        margin-bottom: 15px;
    }
    
    .upgrade-prompt-mobile h5 {
        color: #333;
        margin-bottom: 10px;
        font-weight: 700;
    }
    
    .upgrade-prompt-mobile p {
        color: #666;
        margin-bottom: 20px;
    }
    
    .upgrade-btn-mobile {
        display: inline-flex;
        align-items: center;
        background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%);
        color: white;
        padding: 10px 20px;
        border-radius: 25px;
        font-weight: 600;
        text-decoration: none;
        font-size: 0.9rem;
    }
    
    /* Animation */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* Scrollbar for tabs */
    .tabs-scroll-container::-webkit-scrollbar {
        display: none;
    }
    
    .tabs-scroll-container {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
}

/* Desktop fallback */
@media (min-width: 769px) {
    .modal-dialog.modal-fullscreen {
        max-width: 800px;
        margin: 1.75rem auto;
    }
    
    .modal-content {
        border-radius: 20px !important;
    }
    
    .fixed-bottom {
        position: static !important;
    }
}
</style>



<script>
// =============================================
// 1. SAFE GETTERS FOR BOOTSTRAP MODALS
// Use getters to avoid duplicate/temporal-dead-zone issues
// =============================================
function getDetailsModal() {
    if (!window._detailsModal) window._detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));
    return window._detailsModal;
}

function getUpgradeModal() {
    if (!window._upgradeModal) window._upgradeModal = new bootstrap.Modal(document.getElementById('upgradeModal'));
    return window._upgradeModal;
}

function getImageViewModal() {
    if (!window._imageViewModal) window._imageViewModal = new bootstrap.Modal(document.getElementById('imageViewModal'));
    return window._imageViewModal;
}

function getProfileViewUpgradeModal() {
    if (!window._profileViewUpgradeModal) window._profileViewUpgradeModal = new bootstrap.Modal(document.getElementById('profileViewUpgradeModal'));
    return window._profileViewUpgradeModal;
}

function getProfileStatsModal() {
    if (!window._profileStatsModal) window._profileStatsModal = new bootstrap.Modal(document.getElementById('profileStatsModal'));
    return window._profileStatsModal;
}

// =============================================
// 2. THEN DEFINE ALL FUNCTIONS
// =============================================

// Initialize theme
const themeToggle = document.getElementById('themeToggle');
const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');

const currentTheme = localStorage.getItem('theme') || 
                   (prefersDarkScheme.matches ? 'dark' : 'light');

document.documentElement.setAttribute('data-theme', currentTheme);
updateThemeIcon(currentTheme);

if (themeToggle) {
    themeToggle.addEventListener('click', () => {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        updateThemeIcon(newTheme);
    });
}

function updateThemeIcon(theme) {
    const icon = themeToggle.querySelector('i');
    icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
}

// Back button function
function goBack() {
    window.history.back();
}

// Toggle filters
function toggleFilters() {
    const filterContent = document.getElementById('filterContent');
    filterContent.classList.toggle('d-none');
}

// Toggle more filters
function toggleMoreFilters() {
    const moreFilters = document.getElementById('moreFilters');
    const moreFiltersBtn = document.getElementById('moreFiltersBtn');
    
    moreFilters.classList.toggle('d-none');
    
    if (moreFilters.classList.contains('d-none')) {
        moreFiltersBtn.innerHTML = '<i class="fas fa-chevron-down"></i> More Filters';
    } else {
        moreFiltersBtn.innerHTML = '<i class="fas fa-chevron-up"></i> Less Filters';
    }
}

// Toast notification
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast';
    toast.classList.add(type);
    toast.classList.add('show');
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 4000);
}

// Filter functionality
const _btnApply = document.getElementById('btnApply');
if (_btnApply) {
    _btnApply.addEventListener('click', function() {
    const params = new URLSearchParams();
    
    function getElementValue(id) {
        const element = document.getElementById(id);
        return element ? element.value : '';
    }
    
    const sortBy = getElementValue('f_sort');
    const lookingFor = getElementValue('f_looking');
    const maritalStatus = getElementValue('f_marital');
    const religion = getElementValue('f_religion');
    const country = getElementValue('f_country');
    const profession = getElementValue('f_profession');
    const age = getElementValue('f_age');
    const education = getElementValue('f_education');
    const income = getElementValue('f_income');
    const height = getElementValue('f_height');
    const weight = getElementValue('f_weight');
    
    const allowedFilters = <?php echo json_encode($allowed_filters); ?>;
    
    if (sortBy) params.append('sort', sortBy);
    if (lookingFor && allowedFilters.includes('looking_for')) params.append('looking_for', lookingFor);
    if (maritalStatus && allowedFilters.includes('marital_status')) params.append('marital_status', maritalStatus);
    if (religion && allowedFilters.includes('religion')) params.append('religion', religion);
    if (country && allowedFilters.includes('country')) params.append('country', country);
    if (profession && allowedFilters.includes('profession')) params.append('profession', profession);
    if (age && allowedFilters.includes('age')) params.append('age', age);
    if (education && allowedFilters.includes('education')) params.append('education', education);
    if (income && allowedFilters.includes('income')) params.append('income', income);
    if (height && allowedFilters.includes('height')) params.append('height', height);
    if (weight && allowedFilters.includes('weight')) params.append('weight', weight);
    
    window.location.href = '?' + params.toString();
    });
}

// Auto-apply sorting
const sortElement = document.getElementById('f_sort');
if (sortElement) {
    sortElement.addEventListener('change', function() {
        const params = new URLSearchParams(window.location.search);
        params.set('sort', this.value);
        window.location.href = '?' + params.toString();
    });
}




function initializeMobileTabs() {
    const tabButtons = document.querySelectorAll('.mobile-tab-btn:not(.locked)');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab') + 'Content';
            
            // Remove active class from all buttons
            document.querySelectorAll('.mobile-tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Add active class to clicked button
            this.classList.add('active');
            
            // Hide all tab contents
            document.querySelectorAll('.mobile-tab-content').forEach(content => {
                content.classList.remove('active');
                content.style.display = 'none';
            });
            
            // Show selected tab content
            const tabContent = document.getElementById(tabId);
            if (tabContent) {
                tabContent.classList.add('active');
                tabContent.style.display = 'block';
                
                // Scroll to top of content
                tabContent.scrollTop = 0;
            }
        });
    });
}

// Call this when modal opens
document.addEventListener('DOMContentLoaded', function() {
    initializeMobileTabs();
});

// Mobile-optimized populate function
function populateMobileModal(data) {
    const m = data.member || {};
    const p = data.physical || {};
    const f = data.family || {};
    const partner = data.partner || {};
    const education = data.education || [];
    const horoscope = data.horoscope || {};
    
    // Update header
    document.getElementById('detailsModalLabel').textContent = m.name || 'Profile Details';
    document.getElementById('modalSubtitle').textContent = `ID: ${m.id || '-'} • Age: ${calculateAge(m.dob)}`;
    
    // Profile header
    document.getElementById('modalName').textContent = m.name || '-';
    document.getElementById('modalProfession').textContent = m.profession || '-';
    document.getElementById('modalGender').textContent = m.gender || '-';
    document.getElementById('modalMarital').textContent = m.marital_status || '-';
    document.getElementById('modalPackageBadge').textContent = data.package || 'Free';
    
    // Handle profile photo
    let photoPath = 'img/d.webp';
    if (m.photo) {
        let photo = m.photo.replace(/^\/+/, '');
        photoPath = photo.startsWith('uploads/') ? 'https://thennilavu.lk/'+ photo : 'https://thennilavu.lk/uploads/' + photo;
    }
    const modalPhoto = document.getElementById('modalPhoto');
    modalPhoto.src = m.profile_hidden == 1 ? 'img/defaultBG.png' : photoPath;

    
    // Store member ID for actions
    window.currentMemberId = m.id;
    window.currentProfileHidden = m.profile_hidden == 1;
    
    // Populate basic info
    document.getElementById('religionVal').textContent = m.religion || '-';
    document.getElementById('languageVal').textContent = m.language || '-';
    document.getElementById('dobVal').textContent = m.dob || '-';
    document.getElementById('incomeVal').textContent = m.income ? formatNumber(m.income) : '-';
    document.getElementById('smokingVal').textContent = m.smoking || '-';
    document.getElementById('drinkingVal').textContent = m.drinking || '-';
    
    // Populate physical info
    document.getElementById('heightVal').textContent = p.height_cm ? p.height_cm + ' cm' : '-';
    document.getElementById('weightVal').textContent = p.weight_kg ? p.weight_kg + ' kg' : '-';
    document.getElementById('complexionVal').textContent = p.complexion || '-';
    document.getElementById('bloodVal').textContent = p.blood_group || '-';
    document.getElementById('eyeVal').textContent = p.eye_color || '-';
    document.getElementById('hairVal').textContent = p.hair_color || '-';
    
    // Populate family info
    document.getElementById('fatherVal').textContent = f.father_name || '-';
    document.getElementById('fatherProfessionVal').textContent = f.father_profession || '-';
    document.getElementById('motherVal').textContent = f.mother_name || '-';
    document.getElementById('motherProfessionVal').textContent = f.mother_profession || '-';
    document.getElementById('brothersVal').textContent = f.brothers_count !== null ? f.brothers_count : '-';
    document.getElementById('sistersVal').textContent = f.sisters_count !== null ? f.sisters_count : '-';
    
    // Populate education
    const educationContainer = document.getElementById('educationContainerMobile');
    const educationEmpty = document.getElementById('educationEmptyMobile');
    
    if (education.length > 0) {
        educationEmpty.style.display = 'none';
        educationContainer.innerHTML = '';
        
        education.forEach(edu => {
            const eduCard = document.createElement('div');
            eduCard.className = 'info-card-mobile';
            eduCard.innerHTML = `
                <div class="info-card-header">
                    <i class="fas fa-graduation-cap me-2" style="color: #2196f3;"></i>
                    ${edu.level || 'Education'}
                </div>
                <div class="info-list">
                    <div class="info-item-mobile">
                        <span class="info-label">Institute</span>
                        <span class="info-value">${edu.school_or_institute || '-'}</span>
                    </div>
                    <div class="info-item-mobile">
                        <span class="info-label">Degree/Stream</span>
                        <span class="info-value">${edu.stream_or_degree || '-'}</span>
                    </div>
                    <div class="info-item-mobile">
                        <span class="info-label">Period</span>
                        <span class="info-value">${edu.start_year || ''} - ${edu.end_year || ''}</span>
                    </div>
                    
                </div>
            `;
            educationContainer.appendChild(eduCard);
        });
    } else {
        educationContainer.innerHTML = '';
        educationEmpty.style.display = 'block';
    }
    
    // Populate partner expectations
    let ageRange = '-';
    if (partner.min_age && partner.max_age) {
        ageRange = partner.min_age + ' - ' + partner.max_age + ' years';
    }
    document.getElementById('ageRangeVal').textContent = ageRange;
    
    let heightRange = '-';
    if (partner.min_height && partner.max_height) {
        heightRange = partner.min_height + ' - ' + partner.max_height + ' cm';
    }
    document.getElementById('heightRangeVal').textContent = heightRange;
    
    document.getElementById('prefCountryVal').textContent = partner.preferred_country || '-';
    document.getElementById('prefMaritalVal').textContent = partner.marital_status || '-';
    document.getElementById('prefReligionVal').textContent = partner.religion || '-';
    document.getElementById('prefSmokingVal').textContent = partner.smoking || '-';
    document.getElementById('prefDrinkingVal').textContent = partner.drinking || '-';
    
    // Populate horoscope (if access)
    <?php if ($horoscope_access): ?>
    if (horoscope) {
        document.getElementById('horoscopeBirthDate').textContent = horoscope.birth_date || '-';
        document.getElementById('horoscopeBirthTime').textContent = formatTime(horoscope.birth_time);
        document.getElementById('horoscopeZodiac').textContent = horoscope.zodiac || '-';
        document.getElementById('horoscopeNakshatra').textContent = horoscope.nakshatra || '-';
    }
    <?php endif; ?>
    
    // Initialize mobile tabs
    setTimeout(() => {
        initializeMobileTabs();
    }, 100);
}

// Mobile-optimized modal opening
function openDetails(memberId) {
    window.currentMemberId = memberId;
    
    <?php if (isset($_SESSION['user_id'])): ?>
    // Check profile views limit
    fetch('api_profile_views.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'track_view',
            member_id: memberId
        })
    })
    .then(response => response.json())
    .then(viewData => {
        if (!viewData.success && viewData.limit_reached) {
            showProfileViewsUpgradeModal(viewData);
            return;
        }
        
        // Load member data
        loadMemberDetailsForMobile(memberId);
    })
    .catch(error => {
        console.error('Error:', error);
        loadMemberDetailsForMobile(memberId);
    });
    <?php else: ?>
    loadMemberDetailsForMobile(memberId);
    <?php endif; ?>
}

function loadMemberDetailsForMobile(memberId) {
    // Show loading
    const modalBody = document.querySelector('#detailsModal .modal-body');
    modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div><p class="mt-3">Loading...</p></div>';
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    modal.show();
    
    // Fetch data
    fetch('?action=get_member&id=' + memberId)
        .then(response => response.json())
        .then(data => {
            if (!data || !data.member) {
                throw new Error('Invalid data');
            }
            
            // Restore modal structure
            modalBody.innerHTML = `
                <div id="basicContent" class="tab-content-mobile active">
                    <!-- Basic content will be populated -->
                </div>
                <div id="physicalContent" class="tab-content-mobile">
                    <!-- Physical content -->
                </div>
                <div id="familyContent" class="tab-content-mobile">
                    <!-- Family content -->
                </div>
                <div id="educationContent" class="tab-content-mobile">
                    <!-- Education content -->
                </div>
                <div id="partnerContent" class="tab-content-mobile">
                    <!-- Partner content -->
                </div>
                <div id="horoscopeContent" class="tab-content-mobile">
                    <!-- Horoscope content -->
                </div>
            `;
            
            // Populate data
            populateMobileModal(data);
            
            // Scroll to top
            window.scrollTo(0, 0);
        })
        .catch(error => {
            console.error('Error:', error);
            modalBody.innerHTML = `
                <div class="text-center py-5">
                    <i class="fas fa-exclamation-triangle fa-2x text-danger mb-3"></i>
                    <p>Failed to load profile details</p>
                    <button class="btn btn-primary mt-2" onclick="loadMemberDetailsForMobile(${memberId})">
                        <i class="fas fa-redo"></i> Try Again
                    </button>
                </div>
            `;
        });
}

// Mobile action functions
function toggleInterestModal() {
    if (!window.currentMemberId) return;
    
    <?php if (!isset($_SESSION['user_id'])): ?>
    showToast('Please login to express interest', 'warning');
    return;
    <?php endif; ?>
    
    const element = document.querySelector(`[data-member-id="${window.currentMemberId}"]`);
    if (element) {
        toggleInterest(element, window.currentMemberId);
    }
}

function contactWhatsAppModal() {
    if (!window.currentMemberId || !window.currentMemberData) {
        showToast('Member information not loaded', 'error');
        return;
    }
    
    const m = window.currentMemberData.member || {};
    contactWhatsApp(window.currentMemberId, m.name, calculateAge(m.dob), m.profession);
}

// Helper functions
function calculateAge(dob) {
    if (!dob || dob === '0000-00-00') return '-';
    const birthDate = new Date(dob);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    return age;
}

function formatTime(timeString) {
    if (!timeString) return '-';
    return timeString.substring(0, 5);
}

function formatNumber(num) {
    if (!num || num === '0') return '-';
    return '₹' + parseInt(num).toLocaleString('en-IN');
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Add click handler to profile image for image gallery
    document.addEventListener('click', function(e) {
        if (e.target && e.target.id === 'modalPhoto') {
            if (!window.currentProfileHidden) {
                showImageCarousel(window.currentMemberId);
            }
        }
    });
    
    // Initialize tabs
    initializeMobileTabs();
});

// Tab functionality for modal
function initializeTabNavigation() {
    const tabButtons = document.querySelectorAll('#detailsModal .tab-btn:not(.disabled)');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // Remove active class from all buttons
            tabButtons.forEach(btn => btn.classList.remove('active'));
            
            // Add active class to clicked button
            this.classList.add('active');
            
            // Hide all tab contents
            document.querySelectorAll('#detailsModal .tab-content').forEach(content => {
                content.classList.remove('active', 'show');
            });

            // Show selected tab content (ids are now simple names like 'basic')
            const tabContent = document.getElementById(tabId);
            if (tabContent) {
                tabContent.classList.add('active', 'show');
            }
        });
    });
    
    // Disabled tab click handler
    document.querySelectorAll('#detailsModal .tab-btn.disabled').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            showToast('Upgrade to premium to access this feature', 'warning');
        });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    initializeTabNavigation();
});

// Helper functions
function calculateAge(dob) {
    if (!dob || dob === '0000-00-00') return '-';
    const birthDate = new Date(dob);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    return age;
}

function formatTime(timeString) {
    if (!timeString) return '-';
    return timeString.substring(0, 5); // Format as HH:MM
}

function formatNumber(num) {
    if (!num || num === '0') return '-';
    return '₹' + parseInt(num).toLocaleString('en-IN');
}

// Main function to open member details
function openDetails(memberId) {
    // Store the member ID globally
    window.currentMemberId = memberId;
    
    <?php if (isset($_SESSION['user_id'])): ?>
    // First check if user can view more profiles
    fetch('api_profile_views.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'track_view',
            member_id: memberId
        })
    })
    .then(response => response.json())
    .then(viewData => {
        if (!viewData.success) {
            if (viewData.limit_reached) {
                // Show upgrade modal for profile views limit
                showProfileViewsUpgradeModal(viewData);
                return;
            } else {
                showToast('Error: ' + viewData.error, 'error');
                return;
            }
        }
        
        // Update profile views counter
        updateProfileViewsCounter(viewData.current_count, viewData.limit);
        
        // Now proceed to open the profile details
        fetchMemberDetails(memberId);
    })
    .catch(error => {
        console.error('Error checking profile views limit:', error);
        showToast('Error checking profile views limit. Please try again.', 'error');
    });
    <?php else: ?>
    // For non-logged-in users, open directly
    fetchMemberDetails(memberId);
    <?php endif; ?>
}

// Fetch member details from server
function fetchMemberDetails(memberId) {
    console.error('🔍 [fetchMemberDetails] Starting fetch for member ID:', memberId);
    
    // Get modal body and SAVE original HTML structure (CRITICAL FIX!)
    const modalBody = document.querySelector('#detailsModal .modal-body');
    if (!modalBody) {
        console.error('❌ [fetchMemberDetails] Modal body element not found!');
        alert('Error: Modal body element missing');
        return;
    }
    
    // **KEY FIX:** Store original HTML (tabs, content structure) before replacing it!
    if (!window._modalBodyOriginalHTML) {
        window._modalBodyOriginalHTML = modalBody.innerHTML;
        console.log('✅ [fetchMemberDetails] Original modal HTML structure SAVED');
    }
    
    // Show loading overlay
    const loadingHTML = '<div class="text-center py-5" style="min-height: 400px; display: flex; align-items: center; justify-content: center;"><div><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-3">Loading profile details...</p></div></div>';
    modalBody.innerHTML = loadingHTML;
    console.log('✓ [fetchMemberDetails] Loading spinner displayed');
    
    // Show the modal
    try {
        const detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));
        detailsModal.show();
        console.log('✓ [fetchMemberDetails] Modal shown');
    } catch (err) {
        console.error('❌ [fetchMemberDetails] Error showing modal:', err);
    }
    
    // Fetch member data with timeout and robust error handling
    const controller = new AbortController();
    const signal = controller.signal;
    const timeout = setTimeout(() => {
        controller.abort();
    }, 20000); // 20s timeout

    // Use explicit pathname to avoid issues with relative '?' resolution
    const url = window.location.pathname + '?action=get_member&id=' + encodeURIComponent(memberId);
    console.log('Fetching member details from', url);

    fetch(url, { signal })
        .then(response => {
            clearTimeout(timeout);
            console.error('🔍 [FETCH] Response received:', response.status, response.statusText);
            
            if (!response.ok) {
                throw new Error('HTTP ' + response.status + ': ' + response.statusText);
            }
            return response.json().then(json => {
                console.error('✅ [FETCH] JSON parsed, keys:', Object.keys(json));
                return json;
            });
        })
        .then(data => {
            console.error('📊 [FETCH] Data received - member:', data.member ? data.member.id : 'MISSING');
            
            if (!data || !data.member) {
                console.error('❌ [FETCH] Invalid data structure:', data);
                throw new Error('Invalid data received: member object missing');
            }

            // Store data globally
            window.currentMemberData = data;
            console.log('✅ [FETCH] Data stored globally');

            // **CRITICAL:** Restore original modal HTML structure before populating!
            const modalBodyRestore = document.querySelector('#detailsModal .modal-body');
            if (window._modalBodyOriginalHTML && modalBodyRestore) {
                modalBodyRestore.innerHTML = window._modalBodyOriginalHTML;
                console.log('✅ [FETCH] Original modal HTML restored - tabs and content divs restored!');
            }

            // Populate the modal with data
            try {
                console.log('➡️ [FETCH] Calling populateModal...');
                populateModal(data);
                console.log('✅ [FETCH] populateModal completed');
            } catch (popErr) {
                console.error('❌ [FETCH] populateModal error:', popErr);
                throw popErr;
            }
            
            // Reinitialize tab navigation after modal content is loaded
            setTimeout(() => {
                try {
                    console.log('➡️ [FETCH] Calling initializeTabNavigation...');
                    initializeTabNavigation();
                    console.log('✅ [FETCH] Tabs initialized');
                } catch (tabErr) {
                    console.error('❌ [FETCH] Tab init error:', tabErr);
                }
            }, 100);
        })
        .catch(error => {
            clearTimeout(timeout);
            console.error('❌ [FETCH] Error caught:', error.name, error.message);
            
            if (error.name === 'AbortError') {
                console.error('❌ [FETCH] Request timeout/abort for member', memberId);
                showToast('Request timed out. Please try again.', 'error');
            } else {
                console.error('❌ [FETCH] Network/parse error:', error.message);
                showToast('Unable to load member details. Please try again later.', 'error');
            }

            // Update modal with error message
            const modalBodyErr = document.querySelector('#detailsModal .modal-body');
            if (modalBodyErr) {
                modalBodyErr.innerHTML = `
                    <div class="error-state" style="text-align: center; padding: 50px 20px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: var(--danger-color); margin-bottom: 15px;"></i>
                        <h5 style="color: var(--danger-color); margin-bottom: 12px;">Unable to Load Profile</h5>
                        <p style="color: var(--text-muted);">The profile details could not be loaded.</p>
                        <p style="color: #dc3545; font-weight: 600; margin-top: 15px; font-size: 0.9rem; word-break: break-word;">${error.name}: ${error.message}</p>
                        <p style="color: #666; font-size: 0.75rem; margin-top: 15px;">📋 Check browser console (F12) for detailed logs to help fix this issue.</p>
                        <button class="btn btn-primary mt-3" onclick="fetchMemberDetails(${memberId})" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); border: none; padding: 10px 20px; border-radius: var(--radius-sm); color: white;">
                            <i class="fas fa-redo"></i> Try Again
                        </button>
                    </div>
                `;
                console.log('✅ [FETCH] Error message displayed in modal');
            }
        });
}

// Populate modal with member data
function populateModal(data) {
    const m = data.member || {};
    const p = data.physical || {};
    const f = data.family || {};
    const partner = data.partner || {};
    const education = data.education || [];
    const horoscope = data.horoscope || {};
    
    // Update header information
    // Safe setter to avoid errors when elements are not present
    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    setText('modalName', m.name || '-');
    setText('modalId', m.id || '-');
    setText('modalAge', calculateAge(m.dob));
    setText('modalMarital', m.marital_status || '-');
    setText('modalProfession', m.profession || '-');
    setText('modalLocation', m.country || '-');
    
    // Update package badge safely
    const packageBadge = document.getElementById('modalPackageBadge');
    if (packageBadge) packageBadge.textContent = data.package || 'Free';

    // Update profile photo safely
    let photoPath = 'img/d.webp';
    if (m.photo) {
        let photo = m.photo.replace(/^\/+/, '');
        photoPath = photo.startsWith('uploads/') ? 'https://thennilavu.lk/'+ photo : 'https://thennilavu.lk/uploads/' + photo;
    }
    const modalPhoto = document.getElementById('modalPhoto');
    if (modalPhoto) modalPhoto.src = m.profile_hidden == 1 ? '/img/defaultBG.png' : photoPath;
    
    // Make image clickable if not hidden
    window.currentProfileHidden = m.profile_hidden == 1;
    if (modalPhoto) {
        if (!window.currentProfileHidden) {
            modalPhoto.style.cursor = 'pointer';
            modalPhoto.onclick = function() {
                showImageCarousel(window.currentMemberId);
            };
        } else {
            modalPhoto.style.cursor = 'default';
            modalPhoto.onclick = null;
        }
    }
    
    // Basic Information Tab
    setText('genderVal', m.gender || '-');
    setText('dobVal', m.dob || '-');
    setText('religionVal', m.religion || '-');
    setText('maritalVal', m.marital_status || '-');
    setText('languageVal', m.language || '-');
    setText('professionVal', m.profession || '-');
    setText('countryVal', m.country || '-');
    setText('incomeVal', m.income ? formatNumber(m.income) : '-');
    setText('smokingVal', m.smoking || '-');
    setText('drinkingVal', m.drinking || '-');
    
    // Physical Information Tab
    setText('heightVal', p.height_cm ? p.height_cm + ' cm' : '-');
    setText('weightVal', p.weight_kg ? p.weight_kg + ' kg' : '-');
    setText('complexionVal', p.complexion || '-');
    setText('bloodVal', p.blood_group || '-');
    setText('eyeVal', p.eye_color || '-');
    setText('hairVal', p.hair_color || '-');
    
    // Family Information Tab
    setText('fatherVal', f.father_name || '-');
    setText('fatherProfessionVal', f.father_profession || '-');
    setText('motherVal', f.mother_name || '-');
    setText('motherProfessionVal', f.mother_profession || '-');
    setText('brothersVal', f.brothers_count !== null ? f.brothers_count : '-');
    setText('sistersVal', f.sisters_count !== null ? f.sisters_count : '-');
    
    
    
    
    
    
    
    
    
    
    // Education Information Tab
    console.log('📚 [EDUCATION] Starting education population...');
    console.log('📚 [EDUCATION] Education data received:', education);
    console.log('📚 [EDUCATION] Education array length:', education ? education.length : 'undefined');
    
    const educationContainer = document.getElementById('educationContainer');
    const educationContainerMobile = document.getElementById('educationContainerMobile');
    const educationEmpty = document.getElementById('educationEmpty');
    const educationEmptyMobile = document.getElementById('educationEmptyMobile');
    
    console.log('📚 [EDUCATION] Container (desktop) found:', !!educationContainer);
    console.log('📚 [EDUCATION] Container (mobile) found:', !!educationContainerMobile);
    console.log('📚 [EDUCATION] Empty state (desktop) found:', !!educationEmpty);
    console.log('📚 [EDUCATION] Empty state (mobile) found:', !!educationEmptyMobile);
    
    if (education && education.length > 0) {
        console.log('📚 [EDUCATION] Has education data, processing', education.length, 'items');
        
        // Clear desktop container
        if (educationEmpty) {
            educationEmpty.style.display = 'none';
            console.log('📚 [EDUCATION] Desktop empty state hidden');
        }
        if (educationContainer) {
            educationContainer.innerHTML = '';
            console.log('📚 [EDUCATION] Desktop container cleared');
        }
        
        // Clear mobile container
        if (educationEmptyMobile) {
            educationEmptyMobile.style.display = 'none';
            console.log('📚 [EDUCATION] Mobile empty state hidden');
        }
        if (educationContainerMobile) {
            educationContainerMobile.innerHTML = '';
            console.log('📚 [EDUCATION] Mobile container cleared');
        }
        
        // Populate education items
        education.forEach((edu, index) => {
            console.log(`📚 [EDUCATION] Processing item ${index + 1}:`, edu);
            
            // Desktop version
            if (educationContainer) {
                const eduItem = document.createElement('div');
                eduItem.className = 'education-item';
                eduItem.innerHTML = `
                    <h6><i class="fas fa-graduation-cap"></i> ${edu.level || 'Education'}</h6>
                    <p><strong>Institute:</strong> ${edu.school_or_institute || '-'}</p>
                    <p><strong>Degree/Stream:</strong> ${edu.stream_or_degree || '-'}</p>
                    <p><strong>Field:</strong> ${edu.field || '-'}</p>
                    <p><strong>Period:</strong> ${edu.start_year || ''} - ${edu.end_year || ''}</p>
                    
                `;
                educationContainer.appendChild(eduItem);
                console.log(`📚 [EDUCATION] Desktop item ${index + 1} added`);
            }
            
            // Mobile version
            if (educationContainerMobile) {
                const eduCard = document.createElement('div');
                eduCard.className = 'info-card-mobile';
                eduCard.innerHTML = `
                    <div class="info-card-header">
                        <i class="fas fa-graduation-cap me-2" style="color: #2196f3;"></i>
                        ${edu.level || 'Education'}
                    </div>
                    <div class="info-list">
                        <div class="info-item-mobile">
                            <span class="info-label">Institute</span>
                            <span class="info-value">${edu.school_or_institute || '-'}</span>
                        </div>
                        <div class="info-item-mobile">
                            <span class="info-label">Degree/Stream</span>
                            <span class="info-value">${edu.stream_or_degree || '-'}</span>
                        </div>
                        <div class="info-item-mobile">
                            <span class="info-label">Field</span>
                            <span class="info-value">${edu.field || '-'}</span>
                        </div>
                        <div class="info-item-mobile">
                            <span class="info-label">Period</span>
                            <span class="info-value">${edu.start_year || ''} - ${edu.end_year || ''}</span>
                        </div>
                       
                    </div>
                `;
                educationContainerMobile.appendChild(eduCard);
                console.log(`📚 [EDUCATION] Mobile item ${index + 1} added`);
            }
        });
        
        console.log('✅ [EDUCATION] All education items populated successfully');
    } else {
        console.log('📚 [EDUCATION] No education data found');
        
        // Show empty state for desktop
        if (educationContainer) {
            educationContainer.innerHTML = '';
            console.log('📚 [EDUCATION] Desktop container cleared');
        }
        if (educationEmpty) {
            educationEmpty.style.display = 'block';
            console.log('📚 [EDUCATION] Desktop empty state shown');
        }
        
        // Show empty state for mobile
        if (educationContainerMobile) {
            educationContainerMobile.innerHTML = '';
            console.log('📚 [EDUCATION] Mobile container cleared');
        }
        if (educationEmptyMobile) {
            educationEmptyMobile.style.display = 'block';
            console.log('📚 [EDUCATION] Mobile empty state shown');
        }
    }
    
    // Partner Expectations Tab
    setText('prefCountryVal', partner.preferred_country || '-');
    
    let ageRange = '-';
    if (partner.min_age && partner.max_age) {
        ageRange = partner.min_age + ' - ' + partner.max_age + ' years';
    } else if (partner.min_age) {
        ageRange = 'Above ' + partner.min_age + ' years';
    } else if (partner.max_age) {
        ageRange = 'Below ' + partner.max_age + ' years';
    }
    setText('ageRangeVal', ageRange);
    
    let heightRange = '-';
    if (partner.min_height && partner.max_height) {
        heightRange = partner.min_height + ' - ' + partner.max_height + ' cm';
    } else if (partner.min_height) {
        heightRange = 'Above ' + partner.min_height + ' cm';
    } else if (partner.max_height) {
        heightRange = 'Below ' + partner.max_height + ' cm';
    }
    setText('heightRangeVal', heightRange);
    
    setText('prefMaritalVal', partner.marital_status || '-');
    setText('prefReligionVal', partner.religion || '-');
    setText('prefSmokingVal', partner.smoking || '-');
    setText('prefDrinkingVal', partner.drinking || '-');
    
    // Horoscope Information Tab
    <?php if ($horoscope_access): ?>
    if (horoscope) {
        setText('horoscopeBirthDate', horoscope.birth_date || '-');
        setText('horoscopeBirthTime', formatTime(horoscope.birth_time));
        setText('horoscopeZodiac', horoscope.zodiac || '-');
        setText('horoscopeKarmic', horoscope.karmic_debt || '-');
        
        // Nakshatra mapping
        const nakshatraNames = {
            "1001": "அஸ்வினி",
            "1002": "பரணி",
            // ... your nakshatra names
            "1054": "ரேவதி"
        };
        
        setText('horoscopeNakshatra', nakshatraNames[horoscope.nakshatra] || horoscope.nakshatra || '-');

        // Horoscope Images
        const horoscopeImages = document.getElementById('horoscopeImages');
        if (horoscopeImages) horoscopeImages.innerHTML = '';
        
        const images = [
            { key: 'planet_image', label: 'Planet Image' },
            { key: 'kundali_image', label: 'Kundali Image' },
            { key: 'navamsha_image', label: 'Navamsha Image' }
        ];
        
        let hasImages = false;
        images.forEach(img => {
            if (horoscope[img.key]) {
                hasImages = true;
                const imgCard = document.createElement('div');
                imgCard.className = 'horoscope-img-card';
                imgCard.innerHTML = `
                    <h6><i class="fas fa-image"></i> ${img.label}</h6>
                    <img src="${horoscope[img.key]}" alt="${img.label}" 
                         onclick="handleImageClick('${horoscope[img.key]}', '${img.label}')">
                `;
                if (horoscopeImages) horoscopeImages.appendChild(imgCard);
            }
        });

        if (!hasImages && horoscopeImages) {
            horoscopeImages.innerHTML = '<p style="text-align: center; color: var(--text-muted); grid-column: 1 / -1;">No horoscope images available.</p>';
        }
    }
    <?php endif; ?>
    
    // Reset to first tab and ensure it's active (use Bootstrap Tab API)
    const firstTab = document.getElementById('basic-tab');
    const firstTabContent = document.getElementById('basic');

    if (firstTab && firstTabContent) {
        // Remove active/show from all tabs and contents first
        document.querySelectorAll('#detailsModal .tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('#detailsModal .tab-content').forEach(content => content.classList.remove('active', 'show'));

        // Activate first tab using Bootstrap Tab API (ensures proper aria and classes)
        try {
            firstTab.classList.add('active');
            const bsTab = new bootstrap.Tab(firstTab);
            bsTab.show();
        } catch (err) {
            // Fallback: toggle classes manually
            firstTab.classList.add('active');
            firstTabContent.classList.add('active');
        }
    }
    
    // Update modal footer buttons
    updateModalButtons(window.currentMemberId, m.name, calculateAge(m.dob), m.profession);
}

// Update modal action buttons
function updateModalButtons(memberId, memberName, age, profession) {
    // Update interest button
    const interestBtn = document.querySelector('#detailsModal .interest-btn');
    if (interestBtn) {
        interestBtn.onclick = function() {
            toggleInterestModal(memberId);
        };
    }
    
    // Update WhatsApp button
    const whatsappBtn = document.querySelector('#detailsModal .whatsapp-btn');
    if (whatsappBtn) {
        whatsappBtn.onclick = function() {
            contactWhatsApp(memberId, memberName, age, profession);
        };
    }
}

// Toggle interest from modal
function toggleInterestModal(memberId) {
    // Find the interest button in the card and trigger it
    const cardBtn = document.querySelector(`[data-member-id="${memberId}"]`);
    if (cardBtn) {
        toggleInterest(cardBtn.querySelector('i'), memberId);
    } else {
        showToast('Cannot express interest at this moment', 'warning');
    }
}

// WhatsApp function
function contactWhatsApp(memberId, memberName, age, profession) {
    // Step 1: Fetch WhatsApp number from backend
    fetch('mem.php?get_whatsapp=1')
        .then(response => response.json())
        .then(data => {
            const phoneNumber = data.whatsapp_number;

            if (!phoneNumber) {
                showToast("WhatsApp number not found!", 'error');
                return;
            }

            // Step 2: Prepare message
            const message = `Member name: ${memberName}
Age: ${age}
ID: ${memberId}
Profession: ${profession}

I want to know more details about this member`;

            const encodedMessage = encodeURIComponent(message);

            // Step 3: Open WhatsApp
            const whatsappUrl = `https://wa.me/${phoneNumber}?text=${encodedMessage}`;
            window.open(whatsappUrl, '_blank');
        })
        .catch(error => {
            console.error('Error fetching WhatsApp number:', error);
            showToast('Error connecting to WhatsApp', 'error');
        });
}

// Interest functionality
function toggleInterest(element, memberId) {
    // Check if user is logged in
    <?php if (!isset($_SESSION['user_id'])): ?>
    showToast('Please login to express interest', 'warning');
    return;
    <?php endif; ?>
    
    // Prevent multiple clicks
    if (element.classList.contains('loading')) {
        return;
    }
    
    element.classList.add('loading');
    
    fetch('api_interest.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            member_id: memberId,
            action: 'express_interest'
        })
    })
    .then(response => response.json())
    .then(data => {
        element.classList.remove('loading');
        
        if (data.success) {
            // Update heart icon
            if (element.classList.contains('fa-heart')) {
                element.classList.remove('fa-heart');
                element.classList.add('fa-heart');
            }
            element.classList.add('active');
            element.style.animation = 'pulse 0.6s ease';
            setTimeout(() => { element.style.animation = ''; }, 700);

            showToast(`Interest expressed! (${data.current_count}/${data.limit === 'Unlimited' ? '∞' : data.limit})`, 'success');

            // Update the likes count
            updateLikesCount(memberId);
        } else {
            if (data.code === 'LIMIT_REACHED') {
                // Show upgrade modal
                showUpgradeModal(data);
            } else {
                showToast(data.error || 'Failed to process request', 'error');
            }
        }
    })
    .catch(error => {
        element.classList.remove('loading');
        console.error('Error:', error);
        showToast('Network error. Please try again.', 'error');
    });
}

// Function to update likes count
function updateLikesCount(memberId) {
    const interestCountElement = document.getElementById(`interest-count-${memberId}`);
    if (interestCountElement) {
        let currentCount = parseInt(interestCountElement.textContent) || 0;
        currentCount += 1;
        interestCountElement.textContent = currentCount;
        
        // Add animation
        interestCountElement.classList.add('updated');
        setTimeout(() => {
            interestCountElement.classList.remove('updated');
        }, 600);
    }
}

// Check interest status on page load
function checkInterestStatus() {
    <?php if (isset($_SESSION['user_id'])): ?>
    const interestButtons = document.querySelectorAll('.interest-btn');
    interestButtons.forEach(button => {
        const memberId = button.getAttribute('data-member-id');
        fetch('api_interest.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                member_id: memberId,
                action: 'check_status'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.has_interest) {
                button.classList.add('active');
                button.querySelector('i').classList.add('fa-heart');
            }
        })
        .catch(error => {
            console.error('Error checking interest status:', error);
        });
    });
    <?php endif; ?>
}

// Load current interest count
function loadCurrentInterestCount() {
    <?php if (isset($_SESSION['user_id'])): ?>
    fetch('api_interest.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'get_daily_count'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateInterestCounter(data.current_count, data.limit);
        }
    })
    .catch(error => {
        console.error('Error loading interest count:', error);
    });
    <?php endif; ?>
}

// Load current profile views count
function loadCurrentProfileViewsCount() {
    <?php if (isset($_SESSION['user_id'])): ?>
    fetch('api_profile_views.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'get_daily_views_count'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateProfileViewsCounter(data.current_count, data.limit);
        }
    })
    .catch(error => {
        console.error('Error loading profile views count:', error);
    });
    <?php endif; ?>
}

// Update interest counter display
function updateInterestCounter(currentCount, limit) {
    const modalCountElement = document.getElementById('modalCurrentInterestCount');
    const modalProgressBar = document.getElementById('modalInterestProgressBar');
    const modalUsageAlert = document.getElementById('modalInterestUsageAlert');
    
    if (modalCountElement) {
        modalCountElement.textContent = currentCount;
        
        if (modalProgressBar && limit !== 'Unlimited') {
            const percentage = Math.min((currentCount / parseInt(limit)) * 100, 100);
            modalProgressBar.style.width = percentage + '%';
            
            if (percentage >= 90) {
                modalProgressBar.className = 'progress-bar bg-danger';
                modalUsageAlert.className = 'alert alert-warning';
            } else if (percentage >= 70) {
                modalProgressBar.className = 'progress-bar bg-warning';
                modalUsageAlert.className = 'alert alert-warning';
            } else {
                modalProgressBar.className = 'progress-bar bg-success';
                modalUsageAlert.className = 'alert alert-info';
            }
        }
    }
}

// Update profile views counter display
function updateProfileViewsCounter(currentCount, limit) {
    const modalCountElement = document.getElementById('modalCurrentProfileViewsCount');
    const modalProgressBar = document.getElementById('modalProfileViewsProgressBar');
    const modalUsageAlert = document.getElementById('modalProfileViewsUsageAlert');
    
    if (modalCountElement) {
        modalCountElement.textContent = currentCount;
        
        if (modalProgressBar && limit !== 'Unlimited') {
            const percentage = Math.min((currentCount / parseInt(limit)) * 100, 100);
            modalProgressBar.style.width = percentage + '%';
            
            if (percentage >= 90) {
                modalProgressBar.className = 'progress-bar bg-danger';
                modalUsageAlert.className = 'alert alert-danger';
            } else if (percentage >= 70) {
                modalProgressBar.className = 'progress-bar bg-warning';
                modalUsageAlert.className = 'alert alert-warning';
            } else {
                modalProgressBar.className = 'progress-bar bg-primary';
                modalUsageAlert.className = 'alert alert-warning';
            }
        }
    }
}

// Show profile views upgrade modal
function showProfileViewsUpgradeModal(data) {
    const messageElement = document.getElementById('upgradeMessage');
    const optionsContainer = document.getElementById('packageOptions');
    
    // Update message
    messageElement.textContent = data.error || 'You\'ve reached your daily profile views limit. Upgrade to view more profiles!';
    
    // Clear previous options
    optionsContainer.innerHTML = '';
    
    // Add package options
    if (data.upgrade_options && data.upgrade_options.length > 0) {
        data.upgrade_options.slice(0, 2).forEach(pkg => {
            const packageCard = document.createElement('div');
            packageCard.className = 'col-6';
            
            const limitText = pkg.profile_views_limit === 'Unlimited' ? '∞' : pkg.profile_views_limit;
            const priceText = parseFloat(pkg.price).toLocaleString();
            
            packageCard.innerHTML = `
                <div class="card h-100" style="border: 2px solid #e0e0e0; border-radius: 10px; transition: all 0.3s ease;">
                    <div class="card-body text-center p-3">
                        <h6 class="card-title text-primary">${pkg.name}</h6>
                        <div class="mb-2">
                            <span class="badge bg-info">${limitText} Profile Views</span>
                        </div>
                        <div class="mb-2">
                            <h5 class="text-success mb-0">Rs.${priceText}</h5>
                        </div>
                        <button class="btn btn-primary btn-sm" onclick="window.location.href='package.php'">
                            Choose Plan
                        </button>
                    </div>
                </div>
            `;
            
            optionsContainer.appendChild(packageCard);
        });
    } else {
        optionsContainer.innerHTML = '<div class="col-12 text-center"><p class="text-muted">No upgrade options available at the moment.</p></div>';
    }
    
    upgradeModal.show();
}

// Show upgrade modal
function showUpgradeModal(data) {
    const messageElement = document.getElementById('upgradeMessage');
    const optionsContainer = document.getElementById('packageOptions');
    
    // Update message
    messageElement.textContent = data.error || 'You\'ve reached your daily interest limit. Upgrade to send more interests!';
    
    // Clear previous options
    optionsContainer.innerHTML = '';
    
    // Add package options
    if (data.upgrade_options && data.upgrade_options.length > 0) {
        data.upgrade_options.slice(0, 2).forEach(pkg => {
            const packageCard = document.createElement('div');
            packageCard.className = 'col-6';
            
            const limitText = pkg.interest_limit === 'Unlimited' ? '∞' : pkg.interest_limit;
            const priceText = parseFloat(pkg.price).toLocaleString();
            
            packageCard.innerHTML = `
                <div class="card h-100" style="border: 2px solid #e0e0e0; border-radius: 10px; transition: all 0.3s ease;">
                    <div class="card-body text-center p-3">
                        <h6 class="card-title text-primary">${pkg.name}</h6>
                        <div class="mb-2">
                            <span class="badge bg-success">${limitText} Interests</span>
                        </div>
                        <div class="fw-bold text-dark">Rs.${priceText}</div>
                    </div>
                </div>
            `;
            
            optionsContainer.appendChild(packageCard);
        });
    } else {
        optionsContainer.innerHTML = `
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Check out our premium packages for unlimited interests!
                </div>
            </div>
        `;
    }
    
    upgradeModal.show();
}

// Nakshatra comparison function
function showNakshatraComparison() {
    const loggedUserNakshatra = '<?php echo $logged_user_nakshatra ?? ''; ?>';
    const memberData = window.currentMemberData;
    
    if (!memberData || !memberData.horoscope) {
        showToast('Horoscope information not available for this member.', 'warning');
        return;
    }
    
    const memberNakshatra = memberData.horoscope.nakshatra || '';
    const memberGender = memberData.member.gender || '';
    
    // Get logged user gender
    <?php if (isset($member_row)): ?>
    let loggedUserGender = '<?php echo $member_row['looking_for'] === 'Male' ? 'Female' : ($member_row['looking_for'] === 'Female' ? 'Male' : 'Other'); ?>';
    <?php else: ?>
    let loggedUserGender = 'Not available';
    <?php endif; ?>
    
    if (loggedUserGender === memberGender && loggedUserGender !== 'Not available' && memberGender !== '') {
        showToast('Opposite gender is possible for compatibility check', 'warning');
        return;
    }
    
    // Display the values
    document.getElementById('logged_user_nakshatra').textContent = loggedUserNakshatra || 'Not available';
    document.getElementById('member_nakshatra').textContent = memberNakshatra || 'Not available';
    document.getElementById('logged_user_gender').textContent = loggedUserGender;
    document.getElementById('member_gender').textContent = memberGender || 'Not available';
    
    // Show the nakshatra display
    document.getElementById('nakshatra_display').style.display = 'block';
    
    // Determine male and female nakshatras
    let maleNakshatra, femaleNakshatra;
    if (loggedUserGender === 'Male') {
        maleNakshatra = loggedUserNakshatra;
        femaleNakshatra = memberNakshatra;
    } else if (memberGender === 'Male') {
        maleNakshatra = memberNakshatra;
        femaleNakshatra = loggedUserNakshatra;
    } else {
        maleNakshatra = loggedUserNakshatra;
        femaleNakshatra = memberNakshatra;
    }
    
    // Open nakshatra compatibility page
    window.open(
        'Nakshatra.php?boy_id=' + maleNakshatra + '&girl_id=' + femaleNakshatra,
        '_blank'
    );
}

// Image viewing functionality
function handleImageClick(imageSrc, imageAlt = 'Profile Image') {
    <?php if (isset($_SESSION['user_id'])): ?>
    const profileViewEnabled = '<?php echo $profile_view_enabled; ?>';
    
    if (profileViewEnabled === 'Yes') {
        showFullSizeImage(imageSrc, imageAlt, window.currentMemberId);
    } else {
        getProfileViewUpgradeModal().show();
    }
    <?php else: ?>
    getProfileViewUpgradeModal().show();
    <?php endif; ?>
}

// =====================================================
// IMAGE CAROUSEL - FIXED VERSION
// =====================================================
let currentImageIndex = 0;
let imageCarouselData = [];
let autoPlayInterval = null;
let isAutoPlaying = true;
let imageViewModalInstance = null;

// Initialize image modal (call on DOM ready)
function initializeImageModal() {
    const imageViewModalEl = document.getElementById('imageViewModal');
    if (imageViewModalEl && !imageViewModalInstance) {
        imageViewModalInstance = new bootstrap.Modal(imageViewModalEl);
        
        // Stop autoplay when modal closes
        imageViewModalEl.addEventListener('hidden.bs.modal', function() {
            stopAutoPlay();
            isAutoPlaying = true; // Reset for next opening
        });
        
        console.log('✅ [IMAGE] Modal initialized');
    }
}

// Main function: Show full-size image
function showFullSizeImage(imageSrc, imageAlt, memberId = null) {
    console.log('🖼️ [IMAGE] showFullSizeImage:', {imageSrc, imageAlt, memberId});
    
    // Reset carousel
    currentImageIndex = 0;
    imageCarouselData = [];
    isAutoPlaying = true;
    stopAutoPlay();
    
    // Load images
    if (memberId && window.currentMemberData && window.currentMemberData.member) {
        fetchMemberImages(memberId, imageSrc, imageAlt);
    } else {
        imageCarouselData = [{src: imageSrc, alt: imageAlt}];
        console.log('🖼️ [IMAGE] Using single image');
        initializeImageCarousel();
    }
    
    // Show modal
    if (!imageViewModalInstance) {
        initializeImageModal();
    }
    
    if (imageViewModalInstance) {
        imageViewModalInstance.show();
        console.log('✅ [IMAGE] Modal shown');
    } else {
        console.error('❌ [IMAGE] Failed to show modal');
    }
}

// Fetch all member images
function fetchMemberImages(memberId, primarySrc, primaryAlt) {
    console.log('🖼️ [IMAGE] fetchMemberImages for member:', memberId);
    
    const memberData = window.currentMemberData?.member;
    if (!memberData) {
        console.log('⚠️ [IMAGE] No member data, using primary image');
        imageCarouselData = [{src: primarySrc, alt: primaryAlt}];
        initializeImageCarousel();
        return;
    }
    
    imageCarouselData = [];
    
    // Add primary profile photo
    if (memberData.photo && memberData.photo !== 'img/d.webp') {
        let photoPath = memberData.photo;
        if (!photoPath.startsWith('http') && !photoPath.startsWith('/')) {
            photoPath = 'uploads/' + photoPath;
        }
        imageCarouselData.push({src: photoPath, alt: memberData.name || 'Profile'});
        console.log('🖼️ [IMAGE] Added profile photo:', photoPath);
    }
    
    // Add additional photos
    if (Array.isArray(memberData.additional_photos) && memberData.additional_photos.length > 0) {
        memberData.additional_photos.forEach((photo, idx) => {
            if (photo && photo.photo_path) {
                let photoPath = photo.photo_path;
                if (!photoPath.startsWith('http') && !photoPath.startsWith('/')) {
                    photoPath = 'uploads/' + photoPath;
                }
                imageCarouselData.push({src: photoPath, alt: `Photo ${idx + 1}`});
                console.log('🖼️ [IMAGE] Added additional photo:', photoPath);
            }
        });
        console.log('🖼️ [IMAGE] Total additional photos:', memberData.additional_photos.length);
    }
    
    // Fallback to primary image if no gallery
    if (imageCarouselData.length === 0 && primarySrc) {
        imageCarouselData.push({src: primarySrc, alt: primaryAlt});
        console.log('🖼️ [IMAGE] Using fallback primary image');
    }
    
    // If still no images, use placeholder
    if (imageCarouselData.length === 0) {
        imageCarouselData.push({src: 'img/d.webp', alt: 'No image available'});
        console.log('🖼️ [IMAGE] Using placeholder image');
    }
    
    console.log('🖼️ [IMAGE] Total images loaded:', imageCarouselData.length);
    initializeImageCarousel();
}


// Initialize carousel display and controls
function initializeImageCarousel() {
    if (imageCarouselData.length === 0) {
        console.error('❌ [IMAGE] No images to display!');
        return;
    }
    
    console.log('🖼️ [IMAGE] initializeImageCarousel - images:', imageCarouselData.length);
    
    // Update display
    updateImageDisplay();
    createImageIndicators();
    
    // Show/hide navigation based on image count
    const prevBtn = document.getElementById('prevImageBtn');
    const nextBtn = document.getElementById('nextImageBtn');
    const autoPlayBtn = document.getElementById('autoPlayToggle');
    
    if (imageCarouselData.length > 1) {
        if (prevBtn) prevBtn.style.display = 'block';
        if (nextBtn) nextBtn.style.display = 'block';
        if (autoPlayBtn) {
            autoPlayBtn.style.display = 'block';
            autoPlayBtn.innerHTML = '<i class="fas fa-pause"></i>';
        }
        startAutoPlay();
    } else {
        if (prevBtn) prevBtn.style.display = 'none';
        if (nextBtn) nextBtn.style.display = 'none';
        if (autoPlayBtn) autoPlayBtn.style.display = 'none';
        stopAutoPlay();
    }
}

// Update image display in modal
function updateImageDisplay() {
    if (!imageCarouselData || imageCarouselData.length === 0) return;
    
    const fullSizeImage = document.getElementById('fullSizeImage');
    const imageCounter = document.getElementById('imageCounter');
    
    if (!fullSizeImage) {
        console.error('❌ [IMAGE] fullSizeImage element not found!');
        return;
    }
    
    const currentImage = imageCarouselData[currentImageIndex];
    console.log('🖼️ [IMAGE] Displaying image:', currentImage.src);
    
    fullSizeImage.src = currentImage.src;
    fullSizeImage.alt = currentImage.alt;
    
    if (imageCounter) {
        imageCounter.textContent = `${currentImageIndex + 1} / ${imageCarouselData.length}`;
    }
    
    updateImageIndicators();
}

// Create indicator dots
function createImageIndicators() {
    const indicators = document.getElementById('imageIndicators');
    if (!indicators) {
        console.error('❌ [IMAGE] imageIndicators element not found!');
        return;
    }
    
    indicators.innerHTML = '';
    
    if (imageCarouselData.length <= 1) return;
    
    imageCarouselData.forEach((_, index) => {
        const dot = document.createElement('button');
        dot.type = 'button';
        dot.className = `btn btn-sm rounded-circle mx-1 ${index === 0 ? 'btn-primary' : 'btn-outline-light'}`;
        dot.style.width = '10px';
        dot.style.height = '10px';
        dot.style.padding = '0';
        dot.onclick = () => goToImage(index);
        dot.title = `Image ${index + 1}`;
        indicators.appendChild(dot);
    });
    
    console.log('🖼️ [IMAGE] Created', imageCarouselData.length, 'indicator dots');
}

// Update indicator dots styling
function updateImageIndicators() {
    const indicators = document.getElementById('imageIndicators');
    if (!indicators) return;
    
    const dots = indicators.querySelectorAll('button');
    dots.forEach((dot, index) => {
        if (index === currentImageIndex) {
            dot.className = 'btn btn-sm rounded-circle mx-1 btn-primary';
        } else {
            dot.className = 'btn btn-sm rounded-circle mx-1 btn-outline-light';
        }
        dot.style.width = '10px';
        dot.style.height = '10px';
        dot.style.padding = '0';
    });
}

// Navigation: Next image
function nextImage() {
    if (imageCarouselData.length <= 1) return;
    
    currentImageIndex = (currentImageIndex + 1) % imageCarouselData.length;
    console.log('🖼️ [IMAGE] Next image - index:', currentImageIndex);
    updateImageDisplay();
}

// Navigation: Previous image
function prevImage() {
    if (imageCarouselData.length <= 1) return;
    
    currentImageIndex = currentImageIndex === 0 ? imageCarouselData.length - 1 : currentImageIndex - 1;
    console.log('🖼️ [IMAGE] Previous image - index:', currentImageIndex);
    updateImageDisplay();
}

// Navigation: Go to specific image
function goToImage(index) {
    if (index >= 0 && index < imageCarouselData.length) {
        currentImageIndex = index;
        console.log('🖼️ [IMAGE] Go to image index:', currentImageIndex);
        updateImageDisplay();
    }
}

// Auto-play: Start
function startAutoPlay() {
    if (imageCarouselData.length <= 1) return;
    
    stopAutoPlay(); // Clear any existing interval
    autoPlayInterval = setInterval(nextImage, 5000); // Change every 5 seconds
    console.log('🖼️ [IMAGE] Auto-play started');
}

// Auto-play: Stop
function stopAutoPlay() {
    if (autoPlayInterval) {
        clearInterval(autoPlayInterval);
        autoPlayInterval = null;
        console.log('🖼️ [IMAGE] Auto-play stopped');
    }
}

// Auto-play: Toggle
function toggleAutoPlay() {
    const autoPlayBtn = document.getElementById('autoPlayToggle');
    if (!autoPlayBtn) return;
    
    if (isAutoPlaying) {
        stopAutoPlay();
        isAutoPlaying = false;
        autoPlayBtn.innerHTML = '<i class="fas fa-play"></i>';
        console.log('🖼️ [IMAGE] Auto-play disabled');
    } else {
        startAutoPlay();
        isAutoPlaying = true;
        autoPlayBtn.innerHTML = '<i class="fas fa-pause"></i>';
        console.log('🖼️ [IMAGE] Auto-play enabled');
    }
}

function showImageCarousel(memberId) {
    console.log('showImageCarousel called with memberId:', memberId);
    
    // Initialize modal if needed
    if (!imageViewModalInstance) {
        initializeImageModal();
    }
    
    if (imageViewModalInstance) {
        imageViewModalInstance.show();
    }
    
    // Fetch and display images
    const fullSizeImage = document.getElementById('fullSizeImage');
    if (fullSizeImage) {
        fullSizeImage.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="200" height="100"><text y="50%" x="50%" text-anchor="middle" dy=".35em">Loading...</text></svg>';
    }
    
    fetch('api_member_photos.php?member_id=' + memberId)
        .then(response => {
            console.log('API response received');
            return response.json();
        })
        .then(data => {
            console.log('API data:', data);
            if (data.success && data.photos && data.photos.length > 0) {
                imageCarouselData = data.photos.map(photo => ({
                    src: 'https://thennilavu.lk/' + photo.photo_path,
                    alt: photo.alt || 'Member Photo'
                }));
                currentImageIndex = 0;
                initializeImageCarousel();
            } else {
                console.log('No photos found. Debug info:', data.debug || 'No debug info');
                if (fullSizeImage) {
                    fullSizeImage.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="300" height="100"><text y="40%" x="50%" text-anchor="middle" dy=".35em" font-size="14">No additional images found</text></svg>';
                }
                document.getElementById('imageCounter').textContent = '0 / 0';
                
                document.getElementById('prevImageBtn').style.display = 'none';
                document.getElementById('nextImageBtn').style.display = 'none';
                document.getElementById('autoPlayToggle').style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error loading images:', error);
            if (fullSizeImage) {
                fullSizeImage.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="200" height="100"><text y="50%" x="50%" text-anchor="middle" dy=".35em">Error loading images</text></svg>';
            }
        });
}

// Initialize everything on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ [DOM] DOMContentLoaded fired');
    
    // Initialize image modal first
    initializeImageModal();
    console.log('✅ [DOM] Image modal initialized');
    
    // Setup image carousel event listeners
    const nextImageBtn = document.getElementById('nextImageBtn');
    const prevImageBtn = document.getElementById('prevImageBtn');
    const autoPlayToggle = document.getElementById('autoPlayToggle');
    
    if (nextImageBtn) {
        nextImageBtn.onclick = nextImage;
        console.log('✅ [DOM] Next button listener attached');
    }
    if (prevImageBtn) {
        prevImageBtn.onclick = prevImage;
        console.log('✅ [DOM] Prev button listener attached');
    }
    if (autoPlayToggle) {
        autoPlayToggle.onclick = toggleAutoPlay;
        console.log('✅ [DOM] AutoPlay toggle listener attached');
    }
    
    // Add keyboard navigation for image carousel
    document.addEventListener('keydown', function(e) {
        if (imageViewModalInstance && document.getElementById('imageViewModal').classList.contains('show')) {
            if (e.key === 'ArrowRight') {
                nextImage();
            } else if (e.key === 'ArrowLeft') {
                prevImage();
            } else if (e.key === ' ') {
                e.preventDefault();
                toggleAutoPlay();
            }
        }
    });
    console.log('✅ [DOM] Keyboard navigation attached');
    
    // Initialize functions
    checkInterestStatus();
    loadCurrentInterestCount();
    loadCurrentProfileViewsCount();
    
    // Add fade-in animations
    const fadeElements = document.querySelectorAll('.fade-in');
    fadeElements.forEach(el => {
        el.style.opacity = '1';
        el.style.transform = 'translateY(0)';
    });
    
    console.log('✅ [DOM] All initializations complete');
});
</script>
    
    

    <!-- Upgrade Modal -->
    <div class="modal fade" id="upgradeModal" tabindex="-1" aria-labelledby="upgradeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(127, 8, 8, 0.3);">
                <div class="modal-header" style="background: linear-gradient(135deg, #7f0808 0%, #a00 100%); color: white; border-radius: 15px 15px 0 0;">
                    <h5 class="modal-title" id="upgradeModalLabel">
                        <i class="fas fa-arrow-up-circle me-2"></i>Upgrade Required
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center" style="padding: 30px;">
                    <div class="mb-4">
                        <i class="fas fa-heart" style="font-size: 3rem; color: #e74c3c;"></i>
                    </div>
                    <h4 class="mb-3" style="color: #333;">Interest Limit Reached!</h4>
                    <p class="text-muted mb-4" id="upgradeMessage">
                        You've reached your daily interest limit. Upgrade to send more interests and connect with more people!
                    </p>
                    
                    <!-- Package Options -->
                    <div id="packageOptions" class="row g-3 mb-4">
                        <!-- Package cards will be dynamically inserted here -->
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="package.php" class="btn btn-primary btn-lg" style="background: linear-gradient(50deg, #fd2c79, #ed0cbd); border: none; border-radius: 10px;">
                            <i class="fas fa-arrow-up-circle me-2"></i>View All Packages
                        </a>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            Maybe Later
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

   <!-- Floating Image Modal with Navigation -->
<div class="modal fade" id="imageViewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 bg-transparent">
            <!-- Close Button -->
            <div class="position-absolute top-0 end-0 z-3 p-3">
                <button type="button" class="btn-close btn-close-white bg-dark bg-opacity-75 border-0 shadow"
                    data-bs-dismiss="modal" aria-label="Close"
                    style="width: 40px; height: 40px; border-radius: 50%; padding: 10px;">
                </button>
            </div>
            
            <!-- Image Container -->
            <div class="modal-body p-0 position-relative">
                <!-- Main Image -->
                <img id="fullSizeImage" src="" alt="Profile Image" 
                    class="img-fluid rounded-3 shadow-lg"
                    style="max-height: 85vh; width: auto; object-fit: contain;">
                
                <!-- Navigation Arrows (Always Visible) -->
                <button type="button" id="prevImageBtn" 
                    class="btn position-absolute top-50 start-0 translate-middle-y"
                    style="width: 50px; height: 50px; border-radius: 50%; 
                           background: rgba(0,0,0,0.7); border: 2px solid rgba(255,255,255,0.3);
                           color: white; margin-left: -25px; display: none;
                           transform: translateY(-50%); transition: all 0.3s ease;">
                    <i class="fas fa-chevron-left"></i>
                </button>
                
                <button type="button" id="nextImageBtn" 
                    class="btn position-absolute top-50 end-0 translate-middle-y"
                    style="width: 50px; height: 50px; border-radius: 50%; 
                           background: rgba(0,0,0,0.7); border: 2px solid rgba(255,255,255,0.3);
                           color: white; margin-right: -25px; display: none;
                           transform: translateY(-50%); transition: all 0.3s ease;">
                    <i class="fas fa-chevron-right"></i>
                </button>
                
                <!-- Image Counter -->
                <div id="imageCounter" class="position-absolute top-0 start-0 m-3 bg-dark bg-opacity-75 text-white rounded-pill px-3 py-2 shadow"
                    style="font-size: 0.9rem; font-weight: 600; display: none;">
                    <i class="fas fa-image me-1"></i>
                    <span id="currentImageNum">1</span>/<span id="totalImages">1</span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Make modal backdrop transparent */
#imageViewModal.modal {
    background-color: rgba(0, 0, 0, 0.9);
}

#imageViewModal .modal-dialog {
    max-width: 95vw;
    max-height: 95vh;
    margin: auto;
}

#imageViewModal .modal-content {
    box-shadow: none !important;
    background: transparent !important;
}

/* Custom close button */
#imageViewModal .btn-close {
    filter: invert(1);
    opacity: 0.9;
    transition: all 0.3s ease;
}

#imageViewModal .btn-close:hover {
    opacity: 1;
    background-color: rgba(233, 30, 99, 0.9) !important;
    transform: rotate(90deg);
    box-shadow: 0 0 15px rgba(233, 30, 99, 0.5);
}

/* Navigation arrows styling */
#prevImageBtn:hover, #nextImageBtn:hover {
    background: rgba(233, 30, 99, 0.9) !important;
    border-color: white !important;
    transform: translateY(-50%) scale(1.1) !important;
    box-shadow: 0 0 20px rgba(233, 30, 99, 0.7);
}

#prevImageBtn:active, #nextImageBtn:active {
    transform: translateY(-50%) scale(0.95) !important;
}

/* Image animation */
#fullSizeImage {
    opacity: 0;
    transform: scale(0.95);
    animation: imagePopup 0.4s ease forwards;
}

@keyframes imagePopup {
    0% {
        opacity: 0;
        transform: scale(0.95);
    }
    100% {
        opacity: 1;
        transform: scale(1);
    }
}

/* Mobile responsive */
@media (max-width: 768px) {
    #imageViewModal .modal-dialog {
        max-width: 100vw;
        max-height: 100vh;
        margin: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
    }
    
    #fullSizeImage {
        max-height: 90vh;
        max-width: 100vw;
    }
    
    #prevImageBtn, #nextImageBtn {
        width: 44px !important;
        height: 44px !important;
        margin-left: -22px !important;
        margin-right: -22px !important;
    }
    
    #imageCounter {
        padding: 6px 12px !important;
        font-size: 0.8rem !important;
    }
}

/* Very small screens */
@media (max-width: 480px) {
    #prevImageBtn, #nextImageBtn {
        width: 40px !important;
        height: 40px !important;
        margin-left: -20px !important;
        margin-right: -20px !important;
    }
    
    #imageViewModal .btn-close {
        width: 36px !important;
        height: 36px !important;
    }
}

/* Keyboard navigation highlight */
#prevImageBtn:focus, #nextImageBtn:focus,
#imageViewModal .btn-close:focus {
    outline: 2px solid rgba(233, 30, 99, 0.8);
    outline-offset: 2px;
}
</style>

<script>
// Navigation functions
function nextImage() {
    if (imageCarouselData.length <= 1) return;
    
    const img = document.getElementById('fullSizeImage');
    img.style.animation = 'none';
    
    setTimeout(() => {
        currentImageIndex = (currentImageIndex + 1) % imageCarouselData.length;
        updateImageDisplay();
        img.style.animation = 'imagePopup 0.4s ease forwards';
    }, 100);
}

function prevImage() {
    if (imageCarouselData.length <= 1) return;
    
    const img = document.getElementById('fullSizeImage');
    img.style.animation = 'none';
    
    setTimeout(() => {
        currentImageIndex = currentImageIndex === 0 ? imageCarouselData.length - 1 : currentImageIndex - 1;
        updateImageDisplay();
        img.style.animation = 'imagePopup 0.4s ease forwards';
    }, 100);
}

// Update image display
function updateImageDisplay() {
    if (!imageCarouselData || imageCarouselData.length === 0) return;
    
    const currentImage = imageCarouselData[currentImageIndex];
    const img = document.getElementById('fullSizeImage');
    const prevBtn = document.getElementById('prevImageBtn');
    const nextBtn = document.getElementById('nextImageBtn');
    const counter = document.getElementById('imageCounter');
    const currentNum = document.getElementById('currentImageNum');
    const totalImages = document.getElementById('totalImages');
    
    // Set image
    img.src = currentImage.src;
    img.alt = currentImage.alt;
    
    // Update counter
    currentNum.textContent = currentImageIndex + 1;
    totalImages.textContent = imageCarouselData.length;
    
    // Show/hide navigation based on image count
    if (imageCarouselData.length > 1) {
        prevBtn.style.display = 'flex';
        nextBtn.style.display = 'flex';
        counter.style.display = 'flex';
        
        // Add pulse animation to arrows
        prevBtn.style.animation = 'pulse 0.6s ease';
        nextBtn.style.animation = 'pulse 0.6s ease';
        setTimeout(() => {
            prevBtn.style.animation = '';
            nextBtn.style.animation = '';
        }, 600);
    } else {
        prevBtn.style.display = 'none';
        nextBtn.style.display = 'none';
        counter.style.display = 'none';
    }
}

// Initialize image carousel
function initializeImageCarousel() {
    if (imageCarouselData.length === 0) return;
    
    updateImageDisplay();
    
    // Set up event listeners for navigation buttons
    document.getElementById('prevImageBtn').onclick = prevImage;
    document.getElementById('nextImageBtn').onclick = nextImage;
    
    // Add keyboard navigation
    document.addEventListener('keydown', function handleKeyboard(e) {
        if (document.getElementById('imageViewModal').classList.contains('show')) {
            if (e.key === 'ArrowRight') {
                nextImage();
                e.preventDefault();
            } else if (e.key === 'ArrowLeft') {
                prevImage();
                e.preventDefault();
            }
        }
    });
}

// Touch swipe functionality
let touchStartX = 0;
const minSwipeDistance = 50;

function handleTouchStart(e) {
    touchStartX = e.touches[0].clientX;
}

function handleTouchMove(e) {
    e.preventDefault();
}

function handleTouchEnd(e) {
    const touchEndX = e.changedTouches[0].clientX;
    const diffX = touchEndX - touchStartX;
    
    if (Math.abs(diffX) > minSwipeDistance) {
        if (diffX > 0) {
            // Swipe right - previous image
            prevImage();
            
            // Visual feedback
            const img = document.getElementById('fullSizeImage');
            img.style.transform = 'translateX(20px)';
            setTimeout(() => {
                img.style.transform = 'translateX(0)';
            }, 200);
        } else {
            // Swipe left - next image
            nextImage();
            
            // Visual feedback
            const img = document.getElementById('fullSizeImage');
            img.style.transform = 'translateX(-20px)';
            setTimeout(() => {
                img.style.transform = 'translateX(0)';
            }, 200);
        }
    }
}

// Show floating image modal
function showFloatingImage(imageSrc, imageAlt, memberId = null) {
    // Reset carousel
    currentImageIndex = 0;
    imageCarouselData = [];
    
    // Load images
    if (memberId && window.currentMemberData && window.currentMemberData.member) {
        fetchMemberImages(memberId, imageSrc, imageAlt);
    } else {
        imageCarouselData = [{src: imageSrc, alt: imageAlt}];
        initializeImageCarousel();
    }
    
    // Show modal
    const modalElement = document.getElementById('imageViewModal');
    const modal = new bootstrap.Modal(modalElement);
    modal.show();
    
    // Add touch events
    modalElement.addEventListener('touchstart', handleTouchStart, {passive: false});
    modalElement.addEventListener('touchmove', handleTouchMove, {passive: false});
    modalElement.addEventListener('touchend', handleTouchEnd, {passive: false});
}

// Update your existing image click handler
function handleImageClick(imageSrc, imageAlt = 'Profile Image') {
    <?php if (isset($_SESSION['user_id'])): ?>
    const profileViewEnabled = '<?php echo $profile_view_enabled; ?>';
    
    if (profileViewEnabled === 'Yes') {
        showFloatingImage(imageSrc, imageAlt, window.currentMemberId);
    } else {
        // Show upgrade modal if needed
        const upgradeModal = new bootstrap.Modal(document.getElementById('profileViewUpgradeModal'));
        upgradeModal.show();
    }
    <?php else: ?>
    const upgradeModal = new bootstrap.Modal(document.getElementById('profileViewUpgradeModal'));
    upgradeModal.show();
    <?php endif; ?>
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    // Make sure modal backdrop is dark
    const modalElement = document.getElementById('imageViewModal');
    if (modalElement) {
        modalElement.addEventListener('show.bs.modal', function() {
            document.querySelector('.modal-backdrop').style.backgroundColor = 'rgba(0, 0, 0, 0.9)';
        });
        
        // Clean up touch events when modal closes
        modalElement.addEventListener('hidden.bs.modal', function() {
            modalElement.removeEventListener('touchstart', handleTouchStart);
            modalElement.removeEventListener('touchmove', handleTouchMove);
            modalElement.removeEventListener('touchend', handleTouchEnd);
        });
    }
    
    // Add pulse animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes pulse {
            0% { transform: translateY(-50%) scale(1); }
            50% { transform: translateY(-50%) scale(1.15); }
            100% { transform: translateY(-50%) scale(1); }
        }
    `;
    document.head.appendChild(style);
});
</script>



<style>
/* Mobile-optimized Image Modal Styles */
#imageViewModal .modal-content {
    border-radius: 0;
    height: 100vh;
}

#imageViewModal .modal-body {
    touch-action: pan-x pan-y;
    user-select: none;
    -webkit-user-select: none;
}

/* Image indicators - larger for mobile */
#imageIndicators button {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    padding: 0;
    border: 2px solid rgba(255,255,255,0.5);
    background: transparent;
    transition: all 0.3s ease;
}

#imageIndicators button.active {
    background: white;
    border-color: white;
    transform: scale(1.2);
}

/* Button hover effects */
#prevImageBtn:hover, #nextImageBtn:hover,
#autoPlayToggle:hover {
    background: rgba(233, 30, 99, 0.8) !important;
    border-color: white !important;
    transform: scale(1.05);
}

/* Zoom animation */
@keyframes zoomIn {
    from { transform: scale(1); }
    to { transform: scale(1.2); }
}

@keyframes zoomOut {
    from { transform: scale(1.2); }
    to { transform: scale(1); }
}

.zoom-in {
    animation: zoomIn 0.3s ease forwards;
}

.zoom-out {
    animation: zoomOut 0.3s ease forwards;
}

/* Swipe animation */
.swipe-left {
    animation: swipeLeft 0.3s ease;
}

.swipe-right {
    animation: swipeRight 0.3s ease;
}

@keyframes swipeLeft {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(-100%); opacity: 0; }
}

@keyframes swipeRight {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
}

/* Responsive adjustments */
@media (max-width: 576px) {
    #prevImageBtn, #nextImageBtn {
        width: 50px !important;
        height: 50px !important;
        font-size: 1rem !important;
    }
    
    #autoPlayToggle {
        width: 40px !important;
        height: 40px !important;
    }
    
    .modal-footer .btn-sm {
        padding: 8px 12px;
        font-size: 0.85rem;
    }
}
</style>

<script>
// Touch swipe functionality
let touchStartX = 0;
let touchEndX = 0;
const swipeThreshold = 50; // minimum swipe distance

function handleTouchStart(e) {
    touchStartX = e.changedTouches[0].screenX;
}

function handleTouchMove(e) {
    e.preventDefault(); // Prevent scrolling while swiping
}

function handleTouchEnd(e) {
    touchEndX = e.changedTouches[0].screenX;
    handleSwipe();
}

function handleSwipe() {
    const swipeDistance = touchEndX - touchStartX;
    
    if (Math.abs(swipeDistance) > swipeThreshold) {
        if (swipeDistance > 0) {
            // Swipe right - previous image
            prevImage();
            document.getElementById('fullSizeImage').classList.add('swipe-right');
            setTimeout(() => {
                document.getElementById('fullSizeImage').classList.remove('swipe-right');
            }, 300);
        } else {
            // Swipe left - next image
            nextImage();
            document.getElementById('fullSizeImage').classList.add('swipe-left');
            setTimeout(() => {
                document.getElementById('fullSizeImage').classList.remove('swipe-left');
            }, 300);
        }
    }
}

// Zoom functionality
let currentZoom = 1;

function zoomImage(factor) {
    const img = document.getElementById('fullSizeImage');
    currentZoom *= factor;
    currentZoom = Math.max(0.5, Math.min(3, currentZoom)); // Limit zoom between 0.5x and 3x
    
    img.style.transform = `scale(${currentZoom})`;
    img.style.transition = 'transform 0.3s ease';
    
    // Add visual feedback
    img.classList.add(factor > 1 ? 'zoom-in' : 'zoom-out');
    setTimeout(() => {
        img.classList.remove('zoom-in', 'zoom-out');
    }, 300);
}

function resetZoom() {
    const img = document.getElementById('fullSizeImage');
    currentZoom = 1;
    img.style.transform = 'scale(1)';
    img.style.transition = 'transform 0.3s ease';
}

// Download current image
function downloadCurrentImage() {
    const img = document.getElementById('fullSizeImage');
    const link = document.createElement('a');
    link.href = img.src;
    link.download = `profile-image-${window.currentMemberId || 'unknown'}.jpg`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showToast('Image download started', 'success');
}

// Auto-play functionality
let isAutoPlaying = true;
let autoPlayInterval;

function toggleAutoPlay() {
    const icon = document.getElementById('autoPlayIcon');
    
    if (isAutoPlaying) {
        stopAutoPlay();
        icon.className = 'fas fa-play';
        showToast('Auto-play paused', 'info');
    } else {
        startAutoPlay();
        icon.className = 'fas fa-pause';
        showToast('Auto-play started', 'success');
    }
}

function startAutoPlay() {
    stopAutoPlay();
    isAutoPlaying = true;
    autoPlayInterval = setInterval(nextImage, 4000); // 4 seconds
}

function stopAutoPlay() {
    isAutoPlaying = false;
    if (autoPlayInterval) {
        clearInterval(autoPlayInterval);
        autoPlayInterval = null;
    }
}

// Update existing navigation functions to work with new modal
document.addEventListener('DOMContentLoaded', function() {
    // Set up auto-play toggle
    const autoPlayBtn = document.getElementById('autoPlayToggle');
    if (autoPlayBtn) {
        autoPlayBtn.onclick = toggleAutoPlay;
    }
    
    // Start auto-play when modal opens
    const imageModal = document.getElementById('imageViewModal');
    if (imageModal) {
        imageModal.addEventListener('shown.bs.modal', function() {
            if (imageCarouselData.length > 1) {
                startAutoPlay();
            }
        });
        
        imageModal.addEventListener('hidden.bs.modal', function() {
            stopAutoPlay();
            resetZoom();
        });
    }
    
    // Close modal when tapping outside image
    const modalBody = document.querySelector('#imageViewModal .modal-body');
    if (modalBody) {
        modalBody.addEventListener('click', function(e) {
            if (e.target.id === 'imageCarousel' || e.target.classList.contains('modal-body')) {
                bootstrap.Modal.getInstance(document.getElementById('imageViewModal')).hide();
            }
        });
    }
});

// Toast function (if not already defined)
function showToast(message, type = 'info') {
    // Your existing toast implementation
    console.log(`${type}: ${message}`);
}
</script>

    <!-- Profile View Upgrade Modal -->
    <div class="modal fade" id="profileViewUpgradeModal" tabindex="-1" aria-labelledby="profileViewUpgradeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="profileViewUpgradeModalLabel">
                        <i class="fas fa-lock me-2 text-warning"></i>Premium Feature
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="mb-4">
                        <i class="fas fa-image display-1 text-muted"></i>
                    </div>
                    <h4 class="mb-3">View Full Profile Images</h4>
                    <p class="text-muted mb-4">
                        Upgrade to a premium package to view full-size profile images and unlock additional features!
                    </p>
                    <div class="d-grid gap-2">
                        <a href="package.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-arrow-up-circle me-2"></i>Upgrade Now
                        </a>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            Maybe Later
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Stats Modal -->
    <div class="modal fade" id="profileStatsModal" tabindex="-1" aria-labelledby="profileStatsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="profileStatsModalLabel">
                        <i class="fas fa-user-circle me-2"></i><?php echo htmlspecialchars($user_type); ?> Profile Stats
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Package Limits Display -->
                    <div class="package-limits mb-3">
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="limit-card">
                                    <small class="text-muted d-block">Profile Views</small>
                                    <strong class="text-primary">
                                        <?php 
                                        echo $profile_views_limit === 'Unlimited' ? 
                                            '<i class="fas fa-infinity"></i> Unlimited' : 
                                            '<i class="fas fa-eye"></i> ' . htmlspecialchars($profile_views_limit);
                                        ?>
                                    </strong>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="limit-card">
                                    <small class="text-muted d-block">Interest Limit</small>
                                    <strong class="text-success">
                                        <?php 
                                        echo $interest_limit === 'Unlimited' ? 
                                            '<i class="fas fa-infinity"></i> Unlimited' : 
                                            '<i class="fas fa-heart"></i> ' . htmlspecialchars($interest_limit);
                                        ?>
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Today's Interest Usage -->
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="alert alert-info" style="padding: 12px; font-size: 0.9rem; margin-bottom: 15px;" id="modalInterestUsageAlert">
                        <i class="fas fa-heart me-2"></i>
                        <span id="modalInterestUsageText">Today's Interests: <span id="modalCurrentInterestCount">0</span>/<?php echo $interest_limit === 'Unlimited' ? 'тИЮ' : htmlspecialchars($interest_limit); ?></span>
                        <?php if ($interest_limit !== 'Unlimited'): ?>
                        <div class="progress mt-2" style="height: 6px;">
                            <div class="progress-bar bg-danger" role="progressbar" style="width: 0%" id="modalInterestProgressBar"></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Today's Profile Views Usage -->
                    <div class="alert alert-warning" style="padding: 12px; font-size: 0.9rem; margin-bottom: 15px;" id="modalProfileViewsUsageAlert">
                        <i class="fas fa-eye me-2"></i>
                        <span id="modalProfileViewsUsageText">Today's Profile Views: <span id="modalCurrentProfileViewsCount">0</span>/<?php echo $profile_views_limit === 'Unlimited' ? 'тИЮ' : htmlspecialchars($profile_views_limit); ?></span>
                        <?php if ($profile_views_limit !== 'Unlimited'): ?>
                        <div class="progress mt-2" style="height: 6px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: 0%" id="modalProfileViewsProgressBar"></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times-circle me-2"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Footer initialization (guarded to avoid duplicate declarations)
        if (!window._memFooterInit) {
            window._memFooterInit = true;

            const themeToggleEl = document.getElementById('themeToggle');
            const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');

            const currentTheme = localStorage.getItem('theme') || 
                               (prefersDarkScheme.matches ? 'dark' : 'light');

            document.documentElement.setAttribute('data-theme', currentTheme);
            if (typeof updateThemeIcon === 'function') updateThemeIcon(currentTheme);

            if (themeToggleEl) {
                themeToggleEl.addEventListener('click', () => {
                    const currentTheme = document.documentElement.getAttribute('data-theme');
                    const newTheme = currentTheme === 'light' ? 'dark' : 'light';

                    document.documentElement.setAttribute('data-theme', newTheme);
                    localStorage.setItem('theme', newTheme);
                    if (typeof updateThemeIcon === 'function') updateThemeIcon(newTheme);
                });
            }
        }
        
        // Back button function
        function goBack() {
            window.history.back();
        }
        
        // Toggle filters
        function toggleFilters() {
            const filterContent = document.getElementById('filterContent');
            filterContent.classList.toggle('d-none');
        }
        
        // Toggle more filters
        function toggleMoreFilters() {
            const moreFilters = document.getElementById('moreFilters');
            const moreFiltersBtn = document.getElementById('moreFiltersBtn');
            const icon = moreFiltersBtn.querySelector('i');
            
            moreFilters.classList.toggle('d-none');
            
            if (moreFilters.classList.contains('d-none')) {
                moreFiltersBtn.innerHTML = '<i class="fas fa-chevron-down"></i> More Filters';
            } else {
                moreFiltersBtn.innerHTML = '<i class="fas fa-chevron-up"></i> Less Filters';
            }
        }
        
        // Toast notification
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast';
            toast.classList.add(type);
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 4000);
        }
        
        // Filter functionality
        const _btnApply2 = document.getElementById('btnApply');
        if (_btnApply2) {
            _btnApply2.addEventListener('click', function() {
            const params = new URLSearchParams();
            
            function getElementValue(id) {
                const element = document.getElementById(id);
                return element ? element.value : '';
            }
            
            const sortBy = getElementValue('f_sort');
            const lookingFor = getElementValue('f_looking');
            const maritalStatus = getElementValue('f_marital');
            const religion = getElementValue('f_religion');
            const country = getElementValue('f_country');
            const profession = getElementValue('f_profession');
            const age = getElementValue('f_age');
            const education = getElementValue('f_education');
            const income = getElementValue('f_income');
            const height = getElementValue('f_height');
            const weight = getElementValue('f_weight');
            
            const allowedFilters = <?php echo json_encode($allowed_filters); ?>;
            
            if (sortBy) params.append('sort', sortBy);
            if (lookingFor && allowedFilters.includes('looking_for')) params.append('looking_for', lookingFor);
            if (maritalStatus && allowedFilters.includes('marital_status')) params.append('marital_status', maritalStatus);
            if (religion && allowedFilters.includes('religion')) params.append('religion', religion);
            if (country && allowedFilters.includes('country')) params.append('country', country);
            if (profession && allowedFilters.includes('profession')) params.append('profession', profession);
            if (age && allowedFilters.includes('age')) params.append('age', age);
            if (education && allowedFilters.includes('education')) params.append('education', education);
            if (income && allowedFilters.includes('income')) params.append('income', income);
            if (height && allowedFilters.includes('height')) params.append('height', height);
            if (weight && allowedFilters.includes('weight')) params.append('weight', weight);
            
            window.location.href = '?' + params.toString();
            });
        }
        
        // Auto-apply sorting (already handled above - skip duplicate declaration)
        
        // Heart animation from original
        document.addEventListener("mousemove", function(e) {
            if (Math.random() > 0.95) {
                const heart = document.createElement("div");
                heart.className = "heart";
                heart.innerHTML = "тЭдя╕П";
                
                const driftX = Math.random() * 40 - 20;
                const scale = 0.8 + Math.random() * 0.7;
                const duration = 2 + Math.random() * 2;
                
                heart.style.left = e.pageX + "px";
                heart.style.top = e.pageY + "px";
                heart.style.setProperty('--drift-x', driftX + 'px');
                heart.style.transform = `translate(0, 0) scale(${scale})`;
                heart.style.animationDuration = `${duration}s`;
                
                document.body.appendChild(heart);
                
                setTimeout(() => {
                    heart.remove();
                }, duration * 1000);
            }
        });
        
        // Interest functionality (PRESERVED FROM ORIGINAL)
        function toggleInterest(element, memberId) {
            // Check if user is logged in
            <?php if (!isset($_SESSION['user_id'])): ?>
            showToast('Please login to express interest', 'warning');
            return;
            <?php endif; ?>
            
            // Prevent multiple clicks
            if (element.classList.contains('loading')) {
                return;
            }
            
            // Allow repeated interests: don't block when user already expressed interest.
            // Each click will attempt to record an interest and increment the counters.
            
            element.classList.add('loading');
            
            // Only allow express_interest action
            const action = 'express_interest';
            
            fetch('api_interest.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    member_id: memberId,
                    action: action
                })
            })
            .then(response => response.json())
            .then(data => {
                element.classList.remove('loading');
                
                if (data.success) {
                    // Animate the heart and ensure it is visually filled, but allow further clicks
                    element.classList.remove('fa-heart');
                    element.classList.add('fa-heart');
                    element.classList.add('active');
                    element.title = 'Express Interest';
                    element.style.cursor = 'pointer';
                    // small pulse animation to show the action
                    element.style.animation = 'pulse 0.6s ease';
                    setTimeout(() => { element.style.animation = ''; }, 700);

                    showToast(`Interest expressed! (${data.current_count}/${data.limit === 'Unlimited' ? 'тИЮ' : data.limit})`, 'success');

                    // Update the likes count in real-time
                    updateLikesCount(memberId);
                } else {
                    if (data.code === 'LIMIT_REACHED') {
                        // Show upgrade modal with package options
                        if (data.upgrade_required && data.upgrade_options) {
                            showUpgradeModal(data);
                        } else {
                            setTimeout(() => {
                                showUpgradeNotification('interest_limit');
                            }, 500);
                        }
                    } else {
                        showToast(data.error || 'Failed to process request', 'error');
                    }
                }
            })
            .catch(error => {
                element.classList.remove('loading');
                console.error('Error:', error);
                showToast('Network error. Please try again.', 'error');
            });
        }
        
        // Function to update likes count in real-time
        function updateLikesCount(memberId) {
            // Update the interest count near the heart icon
            const interestCountElement = document.getElementById(`interest-count-${memberId}`);
            if (interestCountElement) {
                let currentCount = parseInt(interestCountElement.textContent) || 0;
                currentCount += 1;
                interestCountElement.textContent = currentCount;
                
                // Add animation to the interest count
                interestCountElement.classList.add('updated');
                setTimeout(() => {
                    interestCountElement.classList.remove('updated');
                }, 600);
            }
        }
        
        // WhatsApp contact function (PRESERVED FROM ORIGINAL)
        function contactWhatsApp(memberId, memberName, age, profession) {
            // Step 1: Fetch WhatsApp number from backend
            fetch('mem.php?get_whatsapp=1')
                .then(response => response.json())
                .then(data => {
                    const phoneNumber = data.whatsapp_number;

                    if (!phoneNumber) {
                        showToast("WhatsApp number not found!", 'error');
                        return;
                    }

                    // Step 2: Prepare message
                    const message = `Member name: ${memberName}
Age: ${age}
ID: ${memberId}
Profession: ${profession}

I want to know more details about this member`;

                    const encodedMessage = encodeURIComponent(message);

                    // Step 3: Open WhatsApp
                    const whatsappUrl = `https://wa.me/${phoneNumber}?text=${encodedMessage}`;
                    window.open(whatsappUrl, '_blank');
                })
                .catch(error => {
                    console.error('Error fetching WhatsApp number:', error);
                    showToast('Error connecting to WhatsApp', 'error');
                });
        }
        
        // Check interest status for all members when page loads
        function checkInterestStatus() {
            <?php if (isset($_SESSION['user_id'])): ?>
            const interestButtons = document.querySelectorAll('.interest-btn');
            interestButtons.forEach(button => {
                const memberId = button.getAttribute('data-member-id');
                fetch('api_interest.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        member_id: memberId,
                        action: 'check_status'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.has_interest) {
                        button.classList.add('active');
                        button.classList.add('fa-heart');
                        button.title = 'Add Interest';
                    }
                })
                .catch(error => {
                    console.error('Error checking interest status:', error);
                });
            });
            <?php endif; ?>
        }
        
        // Load current interest count
        function loadCurrentInterestCount() {
            <?php if (isset($_SESSION['user_id'])): ?>
            console.log('Loading current interest count...');
            fetch('api_interest.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_daily_count'
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('API Response:', data);
                if (data.success) {
                    console.log('Updating counter with:', data.current_count, data.limit);
                    updateInterestCounter(data.current_count, data.limit);
                } else {
                    console.error('API Error:', data.error);
                }
            })
            .catch(error => {
                console.error('Error loading interest count:', error);
            });
            <?php else: ?>
            console.log('User not logged in, skipping interest count load');
            <?php endif; ?>
        }
        
        // Load current profile views count
        function loadCurrentProfileViewsCount() {
            <?php if (isset($_SESSION['user_id'])): ?>
            console.log('Loading current profile views count...');
            fetch('api_profile_views.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_daily_views_count'
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Profile Views API Response:', data);
                if (data.success) {
                    console.log('Updating profile views counter with:', data.current_count, data.limit);
                    updateProfileViewsCounter(data.current_count, data.limit);
                } else {
                    console.error('Profile Views API Error:', data.error);
                }
            })
            .catch(error => {
                console.error('Error loading profile views count:', error);
            });
            <?php else: ?>
            console.log('User not logged in, skipping profile views count load');
            <?php endif; ?>
        }
        
        // Update interest counter display
        function updateInterestCounter(currentCount, limit) {
            console.log('updateInterestCounter called with:', currentCount, limit);
            
            // Update modal elements
            const modalCountElement = document.getElementById('modalCurrentInterestCount');
            const modalProgressBar = document.getElementById('modalInterestProgressBar');
            const modalUsageAlert = document.getElementById('modalInterestUsageAlert');
            
            if (modalCountElement) {
                modalCountElement.textContent = currentCount;
                console.log('Updated modal count display to:', currentCount);
                
                // Update progress bar if limit is not unlimited
                if (modalProgressBar && limit !== 'Unlimited') {
                    const percentage = Math.min((currentCount / parseInt(limit)) * 100, 100);
                    modalProgressBar.style.width = percentage + '%';
                    console.log('Updated modal progress bar to:', percentage + '%');
                    
                    // Change color based on usage
                    if (percentage >= 90) {
                        modalProgressBar.className = 'progress-bar bg-danger';
                        modalUsageAlert.className = 'alert alert-warning';
                    } else if (percentage >= 70) {
                        modalProgressBar.className = 'progress-bar bg-warning';
                        modalUsageAlert.className = 'alert alert-warning';
                    } else {
                        modalProgressBar.className = 'progress-bar bg-success';
                        modalUsageAlert.className = 'alert alert-info';
                    }
                }
            } else {
                console.error('modalCurrentInterestCount element not found!');
            }
        }
        
        // Update profile views counter display
        function updateProfileViewsCounter(currentCount, limit) {
            console.log('updateProfileViewsCounter called with:', currentCount, limit);
            
            // Update modal elements
            const modalCountElement = document.getElementById('modalCurrentProfileViewsCount');
            const modalProgressBar = document.getElementById('modalProfileViewsProgressBar');
            const modalUsageAlert = document.getElementById('modalProfileViewsUsageAlert');
            
            console.log('Modal profile views elements found:', {
                modalCountElement: !!modalCountElement,
                modalProgressBar: !!modalProgressBar,
                modalUsageAlert: !!modalUsageAlert
            });
            
            if (modalCountElement) {
                modalCountElement.textContent = currentCount;
                console.log('Updated modal profile views count display to:', currentCount);
                
                // Update progress bar if limit is not unlimited
                if (modalProgressBar && limit !== 'Unlimited') {
                    const percentage = Math.min((currentCount / parseInt(limit)) * 100, 100);
                    modalProgressBar.style.width = percentage + '%';
                    console.log('Updated modal profile views progress bar to:', percentage + '%');
                    
                    // Change color based on usage
                    if (percentage >= 90) {
                        modalProgressBar.className = 'progress-bar bg-danger';
                        modalUsageAlert.className = 'alert alert-danger';
                    } else if (percentage >= 70) {
                        modalProgressBar.className = 'progress-bar bg-warning';
                        modalUsageAlert.className = 'alert alert-warning';
                    } else {
                        modalProgressBar.className = 'progress-bar bg-primary';
                        modalUsageAlert.className = 'alert alert-warning';
                    }
                }
            } else {
                console.error('modalCurrentProfileViewsCount element not found!');
            }
        }
        
        // Show upgrade modal with package options for profile views
        function showProfileViewsUpgradeModal(data) {
            const modal = new bootstrap.Modal(document.getElementById('upgradeModal'));
            const messageElement = document.getElementById('upgradeMessage');
            const optionsContainer = document.getElementById('packageOptions');
            
            // Update message
            messageElement.textContent = data.error || 'You\'ve reached your daily profile views limit. Upgrade to view more profiles!';
            
            // Clear previous options
            optionsContainer.innerHTML = '';
            
            // Add package options
            if (data.upgrade_options && data.upgrade_options.length > 0) {
                data.upgrade_options.slice(0, 2).forEach(pkg => { // Show max 2 options
                    const packageCard = document.createElement('div');
                    packageCard.className = 'col-6';
                    
                    const limitText = pkg.profile_views_limit === 'Unlimited' ? 'тИЮ' : pkg.profile_views_limit;
                    const priceText = parseFloat(pkg.price).toLocaleString();
                    
                    packageCard.innerHTML = `
                        <div class="card h-100" style="border: 2px solid #e0e0e0; border-radius: 10px; transition: all 0.3s ease;">
                            <div class="card-body text-center p-3">
                                <h6 class="card-title text-primary">${pkg.name}</h6>
                                <div class="mb-2">
                                    <span class="badge bg-info">${limitText} Profile Views</span>
                                </div>
                                <div class="mb-2">
                                    <h5 class="text-success mb-0">Rs.${priceText}</h5>
                                </div>
                                <button class="btn btn-primary btn-sm" onclick="window.location.href='package.php'">
                                    Choose Plan
                                </button>
                            </div>
                        </div>
                    `;
                    
                    // Add hover effect
                    packageCard.addEventListener('mouseenter', function() {
                        this.querySelector('.card').style.borderColor = '#007bff';
                        this.querySelector('.card').style.transform = 'translateY(-2px)';
                    });
                    
                    packageCard.addEventListener('mouseleave', function() {
                        this.querySelector('.card').style.borderColor = '#e0e0e0';
                        this.querySelector('.card').style.transform = 'translateY(0)';
                    });
                    
                    optionsContainer.appendChild(packageCard);
                });
            } else {
                optionsContainer.innerHTML = '<div class="col-12 text-center"><p class="text-muted">No upgrade options available at the moment.</p></div>';
            }
            
            modal.show();
        }
        
        // Show upgrade modal with package options
        function showUpgradeModal(data) {
            const modal = new bootstrap.Modal(document.getElementById('upgradeModal'));
            const messageElement = document.getElementById('upgradeMessage');
            const optionsContainer = document.getElementById('packageOptions');
            
            // Update message
            messageElement.textContent = data.error || 'You\'ve reached your daily interest limit. Upgrade to send more interests!';
            
            // Clear previous options
            optionsContainer.innerHTML = '';
            
            // Add package options
            if (data.upgrade_options && data.upgrade_options.length > 0) {
                data.upgrade_options.slice(0, 2).forEach(pkg => { // Show max 2 options
                    const packageCard = document.createElement('div');
                    packageCard.className = 'col-6';
                    
                    const limitText = pkg.interest_limit === 'Unlimited' ? 'тИЮ' : pkg.interest_limit;
                    const priceText = parseFloat(pkg.price).toLocaleString();
                    
                    packageCard.innerHTML = `
                        <div class="card h-100" style="border: 2px solid #e0e0e0; border-radius: 10px; transition: all 0.3s ease;">
                            <div class="card-body text-center p-3">
                                <h6 class="card-title text-primary">${pkg.name}</h6>
                                <div class="mb-2">
                                    <span class="badge bg-success">${limitText} Interests</span>
                                </div>
                                <div class="fw-bold text-dark">Rs.${priceText}</div>
                            </div>
                        </div>
                    `;
                    
                    // Add hover effect
                    const card = packageCard.querySelector('.card');
                    card.addEventListener('mouseenter', function() {
                        this.style.borderColor = '#7f0808';
                        this.style.transform = 'translateY(-2px)';
                        this.style.boxShadow = '0 5px 15px rgba(127, 8, 8, 0.2)';
                    });
                    card.addEventListener('mouseleave', function() {
                        this.style.borderColor = '#e0e0e0';
                        this.style.transform = 'translateY(0)';
                        this.style.boxShadow = 'none';
                    });
                    
                    optionsContainer.appendChild(packageCard);
                });
            } else {
                optionsContainer.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Check out our premium packages for unlimited interests!
                        </div>
                    </div>
                `;
            }
            
            modal.show();
        }
        
        // Function to show upgrade notification
        function showUpgradeNotification(upgradeType = 'general') {
            // Remove any existing notification
            const existingNotification = document.querySelector('.upgrade-notification');
            if (existingNotification) {
                existingNotification.remove();
            }
            
            // Get user access level from PHP
            const userAccess = '<?php echo $search_access; ?>';
            const matchmakerEnabled = '<?php echo $matchmaker_enabled; ?>';
            
            let title, message;
            
            if (upgradeType === 'interest_limit') {
                title = 'тнР Upgrade to Premium';
                message = 'You\'ve reached your daily interest limit! Upgrade to Premium for unlimited daily interests and find your perfect match faster.';
            } else if (userAccess === 'Basic') {
                title = 'Premium Feature Locked';
                message = 'Horoscope information is available only to premium subscribers. Upgrade your account to access detailed astrological insights.';
            } else {
                title = 'Matchmaker Service Required';
                message = 'Your current package doesn\'t include matchmaker services. Upgrade to a package with horoscope access to view detailed birth charts.';
            }
            
            // Create notification element
            const notification = document.createElement('div');
            notification.className = 'upgrade-notification';
            notification.innerHTML = `
                <div class="upgrade-notification-content">
                    <div class="upgrade-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="upgrade-text">
                        <h5>${title}</h5>
                        <p>${message}</p>
                        <small class="text-muted">Current: <?php echo htmlspecialchars($user_type); ?> | Access: ${userAccess}</small>
                    </div>
                    <div class="upgrade-actions">
                        ${upgradeType === 'interest_limit' ? 
                        `<a href="package.php" class="btn btn-primary btn-sm me-2">
                            <i class="fas fa-arrow-up-circle"></i> Upgrade Now
                        </a>` : ''}
                        <button class="btn btn-outline-secondary btn-sm" onclick="this.closest('.upgrade-notification').remove()" title="Close">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
            
            // Add to body
            document.body.appendChild(notification);
            
            // Show with animation
            setTimeout(() => {
                notification.classList.add('show');
            }, 10);
            
            // Auto remove after 6 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.classList.remove('show');
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.remove();
                        }
                    }, 300);
                }
            }, 6000);
        }
        
        // Handle disabled horoscope tab clicks
        document.addEventListener('click', function(e) {
            if (e.target && e.target.id === 'horoscope-tab-disabled') {
                e.preventDefault();
                showUpgradeNotification();
                return false;
            }
        });
        
        // Open details modal (PRESERVED FROM ORIGINAL)
        // detailsModal instance is provided by getDetailsModal() to avoid duplicate declarations
        
        function calculateAge(dob) {
            if (!dob) return '-';
            const birthDate = new Date(dob);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            return age;
        }
        
        function formatTime(timeString) {
            if (!timeString) return '-';
            return timeString.substring(0, 5); // Format as HH:MM
        }
        
        function openDetails(memberId) {
            // Store the member ID globally for image carousel access
            window.currentMemberId = memberId;
            
            <?php if (isset($_SESSION['user_id'])): ?>
            // First check if user can view more profiles
            fetch('api_profile_views.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'track_view',
                    member_id: memberId
                })
            })
            .then(response => response.json())
            .then(viewData => {
                if (!viewData.success) {
                    if (viewData.limit_reached) {
                        // Show upgrade modal for profile views limit
                        showProfileViewsUpgradeModal(viewData);
                        return;
                    } else {
                        showToast('Error: ' + viewData.error, 'error');
                        return;
                    }
                }
                
                // Update profile views counter
                updateProfileViewsCounter(viewData.current_count, viewData.limit);
                
                // Now proceed to open the profile details
                openProfileModal(memberId);
            })
            .catch(error => {
                console.error('Error checking profile views limit:', error);
                showToast('Error checking profile views limit. Please try again.', 'error');
            });
            <?php else: ?>
            // For non-logged-in users, open directly
            openProfileModal(memberId);
            <?php endif; ?>
        }
        
        function openProfileModal(memberId) {
            window.currentMemberId = memberId;
            
            <?php if (isset($_SESSION['user_id'])): ?>
            fetch('api_profile_views.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'track_view', member_id: memberId})
            })
            .then(response => response.json())
            .then(viewData => {
                if (!viewData.success && viewData.limit_reached) {
                    showProfileViewsUpgradeModal(viewData);
                    return;
                }
                loadMemberDetailsForModal(memberId);
            })
            .catch(error => {
                console.error('Error:', error);
                loadMemberDetailsForModal(memberId);
            });
            <?php else: ?>
            loadMemberDetailsForModal(memberId);
            <?php endif; ?>
        }

        function loadMemberDetailsForModal(memberId) {
            const modalBody = document.querySelector('#detailsModal .modal-body');
            if (!modalBody) return;
            
            if (!window._modalBodyOriginalHTML) {
                window._modalBodyOriginalHTML = modalBody.innerHTML;
            }
            
            modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div><p class="mt-3">Loading profile details...</p></div>';
            getDetailsModal().show();
            
            fetch('?action=get_member&id=' + memberId)
                .then(res => {
                    if (!res.ok) throw new Error('Network response was not ok');
                    return res.json();
                })
                .then(data => {
                    if (!data || !data.member) throw new Error('Invalid data received');
                    
                    const modalBodyRestore = document.querySelector('#detailsModal .modal-body');
                    if (window._modalBodyOriginalHTML && modalBodyRestore) {
                        modalBodyRestore.innerHTML = window._modalBodyOriginalHTML;
                    }
                    
                    window.currentMemberData = data;
                    populateModalData(data);
                    
                    setTimeout(() => {
                        initializeTabNavigation();
                    }, 100);
                })
                .catch(error => {
                    console.error('Error:', error);
                    const modalBodyError = document.querySelector('#detailsModal .modal-body');
                    if (modalBodyError) {
                        modalBodyError.innerHTML = '<div class="text-center py-5"><i class="fas fa-exclamation-triangle fa-2x text-danger mb-3"></i><p>Unable to load member details. Please try again later.</p></div>';
                    }
                });
        }

        function setModalElement(id, value) {
            const el = document.getElementById(id);
            if (el) el.textContent = value;
        }

        function populateModalData(data) {
            const m = data.member || {};
            const p = data.physical || {};
            const f = data.family || {};
            const partner = data.partner || {};
            const education = data.education || [];
            const horoscope = data.horoscope || {};
            
            setModalElement('modalName', m.name || '-');
            setModalElement('modalAge', calculateAge(m.dob));
            // Header fields
            setModalElement('modalProfession', m.profession || '-');
            setModalElement('modalGender', m.gender || '-');
            setModalElement('modalMarital', m.marital_status || '-');
            setModalElement('modalPackage', data.package || 'Free User');
            setModalElement('modalSubtitle', `ID: ${m.id || '-'} • Age: ${calculateAge(m.dob)}`);
            
            let photoPath = 'img/d.webp';
            if (m.photo) {
                let photo = m.photo.replace(/^\/+/, '');
                photoPath = photo.startsWith('uploads/') ? 'https://thennilavu.lk/' + photo : 'https://thennilavu.lk/uploads/' + photo;
            }
            
            const modalPhoto = document.getElementById('modalPhoto');
            if (modalPhoto) {
                modalPhoto.src = m.profile_hidden == 1 ? '/img/defaultBG.png' : photoPath;
            }
            
            window.currentProfileHidden = m.profile_hidden == 1;
            
            const packageBadge = document.getElementById('modalPackageBadge');
            if (packageBadge) packageBadge.textContent = data.package || 'Free';
            
            setModalElement('dobVal', m.dob || '-');
            setModalElement('religionVal', m.religion || '-');
            setModalElement('languageVal', m.language || '-');
            setModalElement('smokingVal', m.smoking || '-');
            setModalElement('drinkingVal', m.drinking || '-');
            setModalElement('incomeVal', m.income || '-');
            
            setModalElement('complexionVal', p.complexion || '-');
            setModalElement('heightVal', p.height_cm ? p.height_cm + ' cm' : '-');
            setModalElement('weightVal', p.weight_kg ? p.weight_kg + ' kg' : '-');
            setModalElement('bloodVal', p.blood_group || '-');
            setModalElement('eyeVal', p.eye_color || '-');
            setModalElement('hairVal', p.hair_color || '-');
            
            setModalElement('fatherVal', f.father_name || '-');
            setModalElement('fatherProfessionVal', f.father_profession || '-');
            setModalElement('motherVal', f.mother_name || '-');
            setModalElement('motherProfessionVal', f.mother_profession || '-');
            setModalElement('brothersVal', f.brothers_count !== null ? f.brothers_count : '-');
            setModalElement('sistersVal', f.sisters_count !== null ? f.sisters_count : '-');
            
            const educationContainer = document.getElementById('educationContainerMobile') || document.getElementById('educationContainer');
            const educationEmpty = document.getElementById('educationEmptyMobile') || document.getElementById('educationEmpty');
            
            if (educationContainer) {
                educationContainer.innerHTML = '';
                
                if (education && education.length > 0) {
                    if (educationEmpty) educationEmpty.style.display = 'none';
                    
                    education.forEach(edu => {
                        const eduItem = document.createElement('div');
                        eduItem.className = 'education-item';
                        eduItem.innerHTML = `
                            <h6><strong>${edu.level || 'Education'}</strong></h6>
                            <p><strong>Institute:</strong> ${edu.school_or_institute || '-'}</p>
                            <p><strong>Degree/Stream:</strong> ${edu.stream_or_degree || '-'}</p>
                            <p><strong>Field:</strong> ${edu.field || '-'}</p>
                            <p><strong>Period:</strong> ${edu.start_year || ''} - ${edu.end_year || ''}</p>
                            
                        `;
                        educationContainer.appendChild(eduItem);
                    });
                } else {
                    if (educationEmpty) educationEmpty.style.display = 'block';
                }
            }
            
            setModalElement('prefCountryVal', partner.preferred_country || '-');
            
            let ageRange = '-';
            if (partner.min_age && partner.max_age) {
                ageRange = partner.min_age + ' - ' + partner.max_age + ' years';
            } else if (partner.min_age) {
                ageRange = 'Above ' + partner.min_age + ' years';
            } else if (partner.max_age) {
                ageRange = 'Below ' + partner.max_age + ' years';
            }
            setModalElement('ageRangeVal', ageRange);
            
            let heightRange = '-';
            if (partner.min_height && partner.max_height) {
                heightRange = partner.min_height + ' - ' + partner.max_height + ' cm';
            } else if (partner.min_height) {
                heightRange = 'Above ' + partner.min_height + ' cm';
            } else if (partner.max_height) {
                heightRange = 'Below ' + partner.max_height + ' cm';
            }
            setModalElement('heightRangeVal', heightRange);
            
            setModalElement('prefMaritalVal', partner.marital_status || '-');
            setModalElement('prefReligionVal', partner.religion || '-');
            setModalElement('prefSmokingVal', partner.smoking || '-');
            setModalElement('prefDrinkingVal', partner.drinking || '-');
            
            <?php if ($horoscope_access): ?>
            if (horoscope) {
                setModalElement('horoscopeBirthDate', horoscope.birth_date || '-');
                setModalElement('horoscopeBirthTime', formatTime(horoscope.birth_time));
                setModalElement('horoscopeZodiac', horoscope.zodiac || '-');
            }
            <?php endif; ?>
        }
        
        // Nakshatra comparison function (PRESERVED FROM ORIGINAL)
        function showNakshatraComparison() {
            const loggedUserNakshatra = '<?php echo $logged_user_nakshatra ?? ''; ?>';
            const memberData = window.currentMemberData;
            
            if (!memberData || !memberData.horoscope) {
                showToast('Horoscope information not available for this member.', 'warning');
                return;
            }
            
            const memberNakshatra = memberData.horoscope.nakshatra || '';
            const memberGender = memberData.member.gender || '';
            
            // Get logged user gender from member data
            <?php if (isset($member_row)): ?>
            let loggedUserGender = '<?php echo $member_row['looking_for'] === 'Male' ? 'Female' : ($member_row['looking_for'] === 'Female' ? 'Male' : 'Other'); ?>';
            <?php else: ?>
            let loggedUserGender = 'Not available';
            <?php endif; ?>
            
            if (loggedUserGender === memberGender && loggedUserGender !== 'Not available' && memberGender !== '') {
                // Show stylish toast message for same gender
                showToast('Opposite gender is possible for compatibility check', 'warning');
                return;
            }
            
            // Display the values
            document.getElementById('logged_user_nakshatra').textContent = loggedUserNakshatra || 'Not available';
            document.getElementById('member_nakshatra').textContent = memberNakshatra || 'Not available';
            document.getElementById('logged_user_gender').textContent = loggedUserGender;
            document.getElementById('member_gender').textContent = memberGender || 'Not available';
            
            // Determine male and female nakshatras
            let maleNakshatra, femaleNakshatra;
            if (loggedUserGender === 'Male') {
                maleNakshatra = loggedUserNakshatra;
                femaleNakshatra = memberNakshatra;
            } else if (memberGender === 'Male') {
                maleNakshatra = memberNakshatra;
                femaleNakshatra = loggedUserNakshatra;
            } else {
                // Default assignment if genders are unclear
                maleNakshatra = loggedUserNakshatra;
                femaleNakshatra = memberNakshatra;
            }
            
            // Show the nakshatra display
            document.getElementById('nakshatra_display').style.display = 'block';
            
            // Open nakshatra compatibility page
            window.open(
                'Nakshatra.php?boy_id=' + maleNakshatra + '&girl_id=' + femaleNakshatra,
                '_blank'
            );
        }
    </script>
</body>
</html>