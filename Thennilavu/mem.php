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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members - Thennilavu Matrimony</title>
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
            --gradient-bg: var(--primary-gradient);
            --card-shadow: 0 10px 30px rgba(0,0,0,0.08);
            --hover-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('img/1.jpg') center/cover no-repeat fixed;
            color: #333;
            overflow-x: hidden;
            min-height: 100vh;
        }
        
        /* Enhanced Navbar Styling */
        .navbar {
            background-color: rgba(0, 0, 0, 0.7);
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
            margin-top: -16px;
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
            
            .navbar-toggler-icon {
                width: 30px;
                height: 30px;
                background-size: 30px 30px;
            }
        }

        @media (max-width: 576px) {
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

        @media (max-width: 400px) {
            .navbar-brand img {
                max-width: 240px;
                margin-top: -28px;
                height: 134px;
                width: 200px;
            }
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

        .hero-section {
            padding: 60px 0 30px;
            color: white;
            text-align: center;
            margin-top: 102px;
        }
        
        /* INNOVATIVE CSS STARTS HERE */
        
        .section-title {
            position: relative;
            margin-bottom: 40px;
            font-weight: 700;
            color: white;
            text-align: center;
            font-size: 2.5rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .section-title:after {
            content: '';
            display: block;
            width: 80px;
            height: 5px;
            background: linear-gradient(90deg, transparent, #ffd700, transparent);
            margin: 20px auto;
            border-radius: 3px;
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0%, 100% { opacity: 0.7; }
            50% { opacity: 1; }
        }
        
        .filter-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }
        
        .filter-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }
        
        .filter-card h5 {
            color: var(--primary-color);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(127, 8, 8, 0.1);
            font-size: 1.4rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        /* Package Limits Styling */
        .package-limits {
            background: rgba(127, 8, 8, 0.02);
            border-radius: 12px;
            padding: 15px;
            border: 1px solid rgba(127, 8, 8, 0.1);
        }
        
        .limit-card {
            background: white;
            padding: 12px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid #f0f0f0;
            transition: all 0.3s ease;
        }
        
        .limit-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(127, 8, 8, 0.1);
            border-color: rgba(127, 8, 8, 0.2);
        }
        
        .limit-card small {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .limit-card strong {
            font-size: 0.9rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            margin-top: 4px;
        }
        
        .limit-card i {
            font-size: 1rem;
        }
        
        .form-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-select, .form-control {
            border-radius: 12px;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s ease;
            font-size: 15px;
        }
        
        .form-select:focus, .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(127, 8, 8, 0.25);
            transform: translateY(-2px);
        }
        
        .member-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: var(--card-shadow);
            height: 100%;
            margin-bottom: 15px;
            position: relative;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            max-width: 280px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .member-card:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-bg);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .package-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 10;
        }
        
        .package-badge .badge {
            font-size: 11px;
            font-weight: 600;
            padding: 5px 8px;
            border-radius: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        
        .member-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: var(--hover-shadow);
        }
        
        .member-card:hover:before {
            transform: scaleX(1);
        }
        
        .member-img {
            height: 150px;
            overflow: hidden;
            position: relative;
            background: linear-gradient(45deg, #f0f0f0, #e0e0e0);
        }
        
        .member-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.5s ease;
            filter: brightness(0.95);
        }
        
        .member-card:hover .member-img img {
            transform: scale(1.1);
            filter: brightness(1.05);
        }

        /* Story-like hover overlay for member images */
        .member-img::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(225,29,72,0.75), rgba(225,29,72,0.2), transparent);
            opacity: 0;
            transition: opacity 0.45s ease;
            z-index: 1;
        }

        .member-img::after {
            content: "❤";
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(4px);
            border-radius: 50%;
            padding: 0.45rem;
            font-size: 0.95rem;
            transform: translateX(40px);
            transition: transform 0.45s ease, opacity 0.45s ease;
            opacity: 0;
            z-index: 2;
        }

        .member-card:hover .member-img::before {
            opacity: 1;
        }

        .member-card:hover .member-img::after {
            transform: translateX(0);
            opacity: 1;
        }
        
        /* Profile Hidden Initial Display */
        .profile-initial {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #7f0808, #a01010);
            color: white;
            font-size: 4rem;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            border-radius: 8px;
        }
        
        .profile-initial:hover {
            background: linear-gradient(135deg, #a01010, #c01515);
            transform: scale(1.05);
            transition: all 0.3s ease;
        }
        
        .member-id {
            position: absolute;
            top: 15px;
            left: 15px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .member-info {
            padding: 10px;
            position: relative;
        }
        
        .member-name {
            font-weight: 800;
            color: #222;
            margin-bottom: 8px;
            font-size: 1.1rem;
            position: relative;
            display: inline-block;
        }
        
        .member-name:after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 40px;
            height: 3px;
            background: var(--gradient-bg);
            border-radius: 2px;
        }
        
        .member-details {
            color: #666;
            font-size: 0.8rem;
            margin-bottom: 15px;
            line-height: 1.4;
        }
        
        .member-details div {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .member-details strong {
            color: #444;
            min-width: 70px;
            font-size: 0.75rem;
        }
        
        .member-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid rgba(0,0,0,0.1);
        }
        
        .action-icon {
            font-size: 1.4rem;
            color: #aaa;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 8px;
            border-radius: 50%;
            background: rgba(0,0,0,0.03);
        }
        
        .action-icon:hover {
            color: var(--primary-color);
            transform: scale(1.2) translateY(-3px);
            background: rgba(127, 8, 8, 0.1);
        }
        
        .whatsapp-icon {
            font-size: 1.4rem;
            color: #25D366;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 8px;
            border-radius: 50%;
            background: rgba(37, 211, 102, 0.1);
            margin-left: 10px;
        }
        
        .whatsapp-icon:hover {
            color: #128C7E;
            transform: scale(1.2) translateY(-3px);
            background: rgba(37, 211, 102, 0.2);
        }
        
        .favorite.active {
            color: #e74c3c;
        }
        
        .interest-btn {
            position: relative;
        }
        
        .interest-btn.interested {
            color: #e74c3c !important;
            animation: heartBeat 0.6s ease-in-out;
        }
        
        .interest-btn.loading {
            color: #ffc107 !important;
            animation: pulse 1s infinite;
        }
        
        @keyframes heartBeat {
            0% { transform: scale(1); }
            14% { transform: scale(1.3); }
            28% { transform: scale(1); }
            42% { transform: scale(1.3); }
            70% { transform: scale(1); }
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
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
        
        .btn-view {
            background: var(--gradient-bg);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(127, 8, 8, 0.3);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-view:hover {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(127, 8, 8, 0.4);
        }
        
        .btn-view:active {
            transform: translateY(-1px);
        }
        
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
        
        /* Enhanced Modal Styles */
        .modal-content {
            border-radius: 25px;
            overflow: hidden;
            border: none;
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        }
        
        .modal-header {
            background: var(--gradient-bg);
            color: white;
            border-bottom: none;
            padding: 25px 30px;
            position: relative;
            overflow: hidden;
        }
        
        .modal-header:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path d="M0,0 L100,0 L100,100 Z" fill="rgba(255,255,255,0.1)"/></svg>');
            background-size: cover;
        }
        
        .modal-title {
            font-weight: 800;
            font-size: 1.8rem;
            position: relative;
            z-index: 1;
        }
        
        .btn-close {
            filter: invert(1);
            opacity: 0.8;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }
        
        .btn-close:hover {
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
        
        .modal-body {
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
            content: '•';
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
            background: var(--gradient-bg);
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
            background: var(--gradient-bg);
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
            content: '•';
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
            content: '🎓';
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
            content: '📚';
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

        
        .modal-footer {
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
            background: var(--gradient-bg);
            border-radius: 10px;
        }

        .modal-tab-content::-webkit-scrollbar-thumb:hover {
            background: #a00;
        }

        /* Responsive adjustments */
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

        
        .nav-pills .nav-link {
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 10px 15px;
            margin: 0 2px;
            transition: all 0.3s ease;
        }
        
        .nav-pills .nav-link.active {
            background: var(--gradient-bg);
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
        
        .profile-basic {
            text-align: center;
            padding: 25px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .profile-basic h4 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-weight: 800;
        }
        
        .profile-basic p {
            color: #666;
            margin-bottom: 8px;
            font-size: 1rem;
        }
        
        .badge-custom {
            background: var(--gradient-bg);
            color: white;
            padding: 8px 15px;
            border-radius: 50px;
            font-size: 0.9rem;
            margin: 3px;
            box-shadow: 0 3px 10px rgba(127, 8, 8, 0.2);
        }
        
        /* Pagination Styles */
        .pagination {
            margin-top: 40px;
        }
        
        .page-link {
            border-radius: 10px;
            margin: 0 5px;
            color: var(--primary-color);
            font-weight: 600;
            border: 2px solid #e0e0e0;
            padding: 10px 18px;
            transition: all 0.3s ease;
        }
        
        .page-link:hover {
            background: var(--gradient-bg);
            color: white;
            border-color: var(--primary-color);
            transform: translateY(-3px);
        }
        
        .pagination .active .page-link {
            background: var(--gradient-bg);
            border-color: var(--primary-color);
            transform: scale(1.1);
        }
        
        /* No Results Styling */
        .alert-info {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            border: none;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }
        
        .alert-info h4 {
            color: #0c5460;
            margin-bottom: 15px;
        }
        
        /* Loading Animation */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .loading {
            animation: pulse 1.5s ease-in-out infinite;
        }
        
        /* Scrollbar Styling */
        .modal-body::-webkit-scrollbar {
            width: 8px;
        }
        
        .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .modal-body::-webkit-scrollbar-thumb {
            background: var(--gradient-bg);
            border-radius: 10px;
        }
        
        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #a00;
        }
        
        footer {
            background: #1a1a1a;
            margin-top: 80px;
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
        
        /* Upgrade Notification Styles */
        .upgrade-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1), 0 5px 15px rgba(0,0,0,0.08);
            border: 1px solid rgba(127, 8, 8, 0.1);
            transform: translateX(450px);
            transition: transform 0.3s ease, opacity 0.3s ease;
            opacity: 0;
        }
        
        .upgrade-notification.show {
            transform: translateX(0);
            opacity: 1;
        }
        
        .upgrade-notification-content {
            padding: 20px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        
        .upgrade-icon {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .upgrade-text {
            flex: 1;
        }
        
        .upgrade-text h5 {
            margin: 0 0 8px 0;
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .upgrade-text p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .upgrade-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: flex-end;
        }
        
        .upgrade-actions .btn-sm {
            font-size: 0.8rem;
            padding: 6px 12px;
            border-radius: 8px;
            font-weight: 600;
        }
        
        .upgrade-actions .btn-primary {
            background: var(--primary-gradient);
            border: none;
            box-shadow: 0 3px 10px rgba(253, 44, 121, 0.3);
        }
        
        .upgrade-actions .btn-primary:hover {
            background: linear-gradient(50deg, #ed0cbd, #fd2c79);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(253, 44, 121, 0.4);
        }
        
        .upgrade-actions .btn-outline-secondary {
            color: #6c757d;
            border-color: #6c757d;
            width: 30px;
            height: 30px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Floating Action Button */
        .fab {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: var(--gradient-bg);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 10px 25px rgba(127, 8, 8, 0.3);
            z-index: 1000;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
        }
        
        .fab:hover {
            transform: scale(1.1) rotate(15deg);
            box-shadow: 0 15px 35px rgba(127, 8, 8, 0.4);
        }

        /* Animation for tab content */
        .tab-pane {
            animation: fadeIn 0.5s ease-in-out;
        }

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

        /* Active tab content highlight */
        .tab-pane.active {
            animation: slideIn 0.4s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Enhanced value styling */
        .tab-content span:empty::before {
            content: "Not specified";
            color: #999;
            font-style: italic;
            font-weight: 400;
        }

        /* Special highlighting for important information */
        .tab-content .col-6:nth-child(odd) {
            background: linear-gradient(135deg, rgba(127, 8, 8, 0.03) 0%, rgba(127, 8, 8, 0.01) 100%);
        }

        .tab-content .col-6:nth-child(even) {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(248, 249, 250, 0.9) 100%);
        }
        
        /* Profile Stats Icon Styling */
        #profileStatsIcon {
            transition: all 0.3s ease;
        }
        
        #profileStatsIcon:hover {
            color: #a00 !important;
            transform: rotate(90deg) scale(1.2);
        }
        
        /* Profile Stats Modal Styling */
        #profileStatsModal .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }
        
        #profileStatsModal .modal-header {
            background: var(--primary-gradient);
            color: white;
            border-radius: 20px 20px 0 0;
            border-bottom: none;
        }
        
        #profileStatsModal .modal-body {
            padding: 25px;
        }
        
        #profileStatsModal .modal-footer {
            border-top: 1px solid #e9ecef;
            border-radius: 0 0 20px 20px;
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
            background: var(--gradient-bg);
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

        /* Clickable Image Styles */
        .profile-image-modal {
            transition: all 0.3s ease;
            border-radius: 10px;
        }

        .profile-image-modal:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            filter: brightness(1.1);
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

        /* ====================== */
        /* MOBILE RESPONSIVENESS */
        /* ====================== */

        /* Mobile Navbar Improvements */
        @media (max-width: 991px) {
            .navbar {
                height: auto;
                /* match members.php */
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
            
            /* larger menu icon */
            .navbar-toggler-icon {
                width: 30px;
                height: 30px;
                background-size: 30px 30px;
            }
        }

        /* Mobile Hero Section */
        @media (max-width: 768px) {
            .hero-section {
                margin-top: 100px;
                padding: 40px 0 20px;
            }
            
            .heading1 {
                font-size: 2.2rem;
            }
            
            .hero-section .lead {
                font-size: 1rem;
            }
        }

        /* Mobile Filter Section */
        @media (max-width: 768px) {
            .filter-card {
                padding: 20px 15px;
                margin-bottom: 20px;
            }
            
            .filter-card h5 {
                font-size: 1.2rem;
                margin-bottom: 15px;
            }
            
            .form-label {
                font-size: 0.9rem;
            }
            
            .form-select, .form-control {
                padding: 10px 12px;
                font-size: 0.9rem;
            }
            
            .btn-primary {
                padding: 10px 20px;
                font-size: 0.9rem;
            }
        }

        /* Mobile Member Cards */
        @media (max-width: 768px) {
            .member-card {
                max-width: 100%;
                margin-bottom: 20px;
            }
            
            .member-img {
                height: 180px;
            }
            
            .member-info {
                padding: 15px;
            }
            
            .member-name {
                font-size: 1.2rem;
            }
            
            .member-details {
                font-size: 1rem;
                font-weight: 510;
            }
            
            .member-details div {
                /* Keep label and value on the same row for mobile so "Looking for", "Age" and "Location"
                   appear inline instead of stacking into two lines. Allow wrapping if the value is long. */
                flex-direction: row;
                align-items: center;
                gap: 6px;
                flex-wrap: nowrap;
            }

            .member-details strong {
                min-width: 70px;
                font-size: 1rem;
                flex: 0 0 auto;
            }

            .member-details div > *:not(strong) {
                /* Ensure the value text truncates cleanly if it's very long */
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            
            .member-actions {
                flex-direction: column;
                gap: 10px;
                align-items: stretch;
            }
            
            .btn-view {
                width: 100%;
                justify-content: center;
            }
        }

        /* Mobile Modal */
        @media (max-width: 768px) {
            .modal-dialog {
                margin: 10px;
            }
            
            .modal-content {
                border-radius: 15px;
            }
            
            .modal-header {
                padding: 20px;
            }
            
            .modal-title {
                font-size: 1.4rem;
            }
            
            .modal-body {
                padding: 15px;
                max-height: 60vh;
            }
            
            .nav-pills .nav-link {
                font-size: 0.8rem;
                padding: 8px 10px;
                margin: 0 1px;
            }
            
            .profile-image-container {
                position: static;
                margin-bottom: 20px;
            }
            
            .profile-image-modal {
                max-width: 250px;
            }
        }

        /* Mobile Tab Content */
        @media (max-width: 768px) {
            .tab-content .row {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .tab-content .col-6 {
                padding: 10px;
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

        /* Mobile Pagination */
        @media (max-width: 768px) {
            .pagination {
                margin-top: 30px;
            }
            
            .page-link {
                padding: 8px 12px;
                font-size: 0.9rem;
                margin: 0 2px;
            }
            
            .pagination .active .page-link {
                transform: scale(1.05);
            }
        }

        /* Mobile Footer */
        @media (max-width: 768px) {
            footer {
                margin-top: 50px;
            }
            
            footer .col-md-3 {
                margin-bottom: 30px;
                text-align: center;
            }
            
            footer .row {
                text-align: center;
            }
            
            footer ul.list-unstyled {
                display: inline-block;
                text-align: left;
            }
        }

        /* Tablet view navbar height parity with members.php */
        @media (max-width: 768px) and (min-width: 577px) {
            .navbar {
                max-height: 73px;
            }
        }

        /* Small Mobile Devices - match members.php navbar height */
        @media (max-width: 576px) {
            .navbar {
                max-height: 85px;
            }
            
            .navbar-brand img {
                max-width: 160px;
                height: 132px;
                width: auto;
            }
            
            .heading1 {
                font-size: 1.8rem;
            }
            
            .section-title {
                font-size: 1.8rem;
            }
            
            .filter-card h5 {
                font-size: 1.1rem;
            }
            
            .custom-btn {
                min-width: 100px;
                padding: 8px 15px;
                font-size: 0.85rem;
            }
            
            .member-card {
                margin-bottom: 15px;
            }
            
            .modal-dialog {
                margin: 5px;
            }
        }

        /* Extra Small Devices - match members.php navbar height */
        @media (max-width: 480px) {
            .navbar {
                max-height: 90px;
            }
        }

        /* Very small devices */
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
            
            .heading1 {
                font-size: 1.6rem;
            }
            
            .section-title {
                font-size: 1.6rem;
            }
            
            .filter-card {
                padding: 15px 10px;
            }
            
            .member-img {
                height: 160px;
            }
            
            .modal-title {
                font-size: 1.2rem;
            }
        }

        /* Mobile Grid Layout */
        @media (max-width: 768px) {
            #cardsRoot .col-lg-4 {
                width: 50%;
            }
        }

        @media (max-width: 576px) {
            #cardsRoot .col-lg-4 {
                width: 100%;
            }
        }

        /* Mobile Package Badge */
        @media (max-width: 768px) {
            .package-badge .badge {
                font-size: 10px;
                padding: 4px 6px;
            }
        }

        /* Mobile Interest Count */
        @media (max-width: 768px) {
            .interest-count {
                min-width: 18px;
                height: 18px;
                font-size: 9px;
                top: -6px;
                right: -6px;
            }
        }

        /* Mobile Form Layout */
        @media (max-width: 768px) {
            .filter-card .row {
                margin-left: -5px;
                margin-right: -5px;
            }
            
            .filter-card .row > [class*="col-"] {
                padding-left: 5px;
                padding-right: 5px;
            }
        }

        /* Mobile Notification */
        @media (max-width: 768px) {
            .upgrade-notification {
                left: 10px;
                right: 10px;
                max-width: none;
                transform: translateY(-100px);
            }
            
            .upgrade-notification.show {
                transform: translateY(0);
            }
            
            .upgrade-notification-content {
                padding: 15px;
                gap: 12px;
            }
            
            .upgrade-actions {
                flex-direction: row;
                align-items: center;
            }
            /* Make upgrade notification stack vertically on small screens so the message
               has full width and wraps naturally instead of squeezing into a tiny column
               which can cause odd per-character/word wrapping. */
            .upgrade-notification-content {
                flex-direction: column;
                align-items: stretch;
            }

            .upgrade-text p {
                white-space: normal !important;
                overflow-wrap: anywhere !important;
                word-break: break-word !important;
                margin-bottom: 8px;
            }

            .upgrade-actions {
                justify-content: flex-end;
                width: 100%;
                gap: 8px;
            }

            .upgrade-actions .btn-sm {
                width: auto;
            }
        }

        /* Mobile Stats Modal */
        @media (max-width: 576px) {
            #profileStatsModal .modal-body {
                padding: 20px 15px;
            }
            
            .package-limits {
                padding: 12px;
            }
            
            .limit-card {
                padding: 10px;
            }
            
            .limit-card small {
                font-size: 0.7rem;
            }
            
            .limit-card strong {
                font-size: 0.8rem;
            }
        }

        /* Mobile Action Icons */
        @media (max-width: 768px) {
            .action-icon {
                font-size: 1.2rem;
                padding: 6px;
            }
        }

        /* Mobile Heart Animation */
        @media (max-width: 768px) {
            .heart {
                font-size: 20px;
            }
        }

        /* Mobile Profile Initial */
        @media (max-width: 768px) {
            .profile-initial {
                font-size: 3rem;
            }
        }

        /* Mobile Upgrade Modal */
        @media (max-width: 576px) {
            .upgrade-modal {
                margin: 10px;
            }
            
            .upgrade-package-card {
                margin-bottom: 10px;
            }
        }

        /* Mobile Image Modal */
        @media (max-width: 768px) {
            #imageViewModal .modal-dialog {
                margin: 10px;
            }
            
            #fullSizeImage {
                max-height: 70vh;
            }
        }

        /* Mobile Profile View Upgrade Modal */
        @media (max-width: 576px) {
            #profileViewUpgradeModal .modal-body {
                padding: 20px 15px;
            }
            
            #profileViewUpgradeModal .display-1 {
                font-size: 3rem;
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .info-item {
                flex-direction: column;
                text-align: left;
                gap: 5px;
            }
            
            .info-value {
                text-align: left;
            }
            
            .modal-body {
                padding: 20px;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .member-img {
                height: 220px;
            }
            
            .profile-basic {
                padding: 20px;
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
                    <li class="nav-item"><a class="nav-link active" href="mem.php">Members</a></li>
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
    <section class="hero-section">  
        <div class="container">
            <h1 class="display-4 fw-bold heading1">Find Your Perfect Match</h1>
            <p class="lead">Browse through our verified members to find your life partner</p>
        </div>
    </section>

    <!-- Perfect Match Section -->
    <?php if (!empty($perfect_matches)): ?>
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold text-primary mb-3">💎 Perfect Matches for You</h2>
                <p class="text-muted">Based on your partner expectations and preferences</p>
            </div>
            <div class="row g-4">
                <?php foreach ($perfect_matches as $match): ?>
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="member-card">
                        <div class="package-badge">
                            <span class="badge bg-success">Perfect Match ✨</span>
                        </div>
                        <div class="member-img">
                            <span class="member-id">ID: <?php echo htmlspecialchars($match['id']); ?></span>
                            <?php
                            // Check if profile should be hidden
                            // Only hide photo if profile_hidden = 1 in members table
                            $profileHidden = intval($match['profile_hidden'] ?? 0);
                            $shouldHideProfile = ($profileHidden === 1);
                            
                            if ($shouldHideProfile) {
                                // Add hidden profile badge
                                ?>
                                <span class="badge bg-warning position-absolute" style="top:15px;right:15px;font-size:0.7rem;">
                                    <i class="bi bi-eye-slash"></i> Hidden
                                </span>
                                <?php
                            }
                            
                            // Now display the image based on hide status
                            if ($shouldHideProfile) {
                                // Display first letter of name in a styled background
                                $firstLetter = strtoupper(substr($match['name'] ?? 'U', 0, 1));
                                ?>
                                <div class="profile-initial">
                                    <?php echo $firstLetter; ?>
                                </div>
                                <?php
                            } else {
                                // Display normal image
                                $photoField = $match['photo'] ?? '';
                                if (!empty($photoField)) {
                                    $photoField = ltrim($photoField, '/');
                                    if (strpos($photoField, 'uploads/') === 0) {
                                        $imgPath = $photoField;
                                        $fileCheck = __DIR__ . '/' . $photoField;
                                    } else {
                                        $imgPath = 'uploads/' . $photoField;
                                        $fileCheck = __DIR__ . '/uploads/' . $photoField;
                                    }
                                    if (!file_exists($fileCheck)) {
                                        $imgPath = 'img/d.webp';
                                    }
                                } else {
                                    $imgPath = 'img/d.webp';
                                }
                                ?>
                                <img src="<?php echo $imgPath; ?>" alt="<?php echo htmlspecialchars($match['name']); ?>" style="object-fit:cover;width:100%;height:100%;">
                                <?php
                            }
                            ?>
                        </div>
                        <div class="member-info">
                            <h4 class="member-name"><?php echo htmlspecialchars($match['name']); ?></h4>
                            <div class="member-details">
                                <div><strong>Looking for:</strong> <?php echo htmlspecialchars($match['looking_for'] ?? '-'); ?></div>
                                <div><strong>Age:</strong> <?php
                                    if (!empty($match['dob']) && $match['dob'] !== '0000-00-00') {
                                        $dob = new DateTime($match['dob']);
                                        $now = new DateTime();
                                        $age = $now->diff($dob)->y;
                                        echo $age;
                                    } else {
                                        echo '-';
                                    }
                                ?></div>
                                <div><strong>Religion:</strong> <?php echo htmlspecialchars($match['religion'] ?? '-'); ?></div>
                                <div><strong>Profession:</strong> <?php echo htmlspecialchars($match['profession'] ?? '-'); ?></div>
                                <div><strong>Location:</strong> <?php echo htmlspecialchars($match['country'] ?? '-'); ?></div>
                            </div>
                        </div>
                        <div class="member-actions">
                            <div>
                                <div class="interest-action-container">
                                    <i class="bi bi-heart action-icon interest-btn" 
                                       data-member-id="<?php echo htmlspecialchars($match['id']); ?>" 
                                       onclick="toggleInterest(this, <?php echo htmlspecialchars($match['id']); ?>)" 
                                       title="Express Interest">
                                    </i>
                                    <span class="interest-count" id="interest-count-<?php echo htmlspecialchars($match['id']); ?>">
                                        <?php echo htmlspecialchars($match['likes_received'] ?? '0'); ?>
                                    </span>
                                </div>
                                <i class="bi bi-whatsapp whatsapp-icon" 
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
                                </i>
                            </div>
                            <button class="btn-view" data-member-id="<?php echo htmlspecialchars($match['id']); ?>" 
                                    onclick="openDetails(<?php echo htmlspecialchars($match['id']); ?>)">
                                <i class="bi bi-eye-fill me-1"></i>View Profile
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Main Content -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <!-- Filter Section -->
                <div class="col-lg-4 col-md-5">
                    <div class="filter-card">
                        <i class="bi bi-gear-fill ms-2" id="profileStatsIcon" data-bs-toggle="modal" data-bs-target="#profileStatsModal" style="cursor: pointer; color: var(--primary-color); font-size: 1.1rem;" title="View Profile Stats"></i>
                        <h5>Member Filter 
                            <span class="badge bg-secondary" style="font-size: 0.8rem;">
                                <?php echo htmlspecialchars($user_type); ?> 
                                (<?php echo htmlspecialchars($search_access); ?>)
                            </span>
                        </h5>
                        
                        <!-- Sort Options -->
                        <div class="mb-3">
                            <label class="form-label">📊 Sort By</label>
                            <select class="form-select" id="f_sort">
                                <option value="newest" <?php echo ($_GET['sort'] ?? 'newest') === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="most_liked" <?php echo ($_GET['sort'] ?? '') === 'most_liked' ? 'selected' : ''; ?>>Most Liked ❤️</option>
                            </select>
                            <?php echo $current_sort_debug; ?>
                        </div>
                        
                        <?php if (in_array('looking_for', $allowed_filters)): ?>
                        <div class="mb-3">
                            <label class="form-label">👤 Looking for</label>
                            <select class="form-select" id="f_looking">
                                <option value="">All</option>
                                <option value="Male" <?php echo ($_GET['looking_for'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($_GET['looking_for'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($_GET['looking_for'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (in_array('marital_status', $allowed_filters)): ?>
                        <div class="mb-3">
                            <label class="form-label">💍 Marital Status</label>
                            <select class="form-select" id="f_marital">
                                <option value="">All</option>
                                <option value="Single" <?php echo ($_GET['marital_status'] ?? '') === 'Single' ? 'selected' : ''; ?>>Single</option>
                                <option value="Divorced" <?php echo ($_GET['marital_status'] ?? '') === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                <option value="Widowed" <?php echo ($_GET['marital_status'] ?? '') === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (in_array('religion', $allowed_filters)): ?>
                        <div class="mb-3">
                            <label class="form-label">🕍 Religion</label>
                            <select class="form-select" id="f_religion">
                                <option value="">All</option>
                                <option value="Hindu" <?php echo ($_GET['religion'] ?? '') === 'Hindu' ? 'selected' : ''; ?>>Hindu</option>
                                <option value="Christian" <?php echo ($_GET['religion'] ?? '') === 'Christian' ? 'selected' : ''; ?>>Christian</option>
                                <option value="Islam" <?php echo ($_GET['religion'] ?? '') === 'Islam' ? 'selected' : ''; ?>>Islam</option>
                                <option value="Buddhist" <?php echo ($_GET['religion'] ?? '') === 'Buddhist' ? 'selected' : ''; ?>>Buddhist</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (in_array('country', $allowed_filters)): ?>
                        <div class="mb-3">
                            <label class="form-label">🌍 Country</label>
                            <select class="form-select" id="f_country">
                                <option value="">All</option>
                                <option value="Sri Lanka" <?php echo ($_GET['country'] ?? '') === 'Sri Lanka' ? 'selected' : ''; ?>>Sri Lanka</option>
                                <option value="India" <?php echo ($_GET['country'] ?? '') === 'India' ? 'selected' : ''; ?>>India</option>
                                <option value="Other" <?php echo ($_GET['country'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (in_array('profession', $allowed_filters)): ?>
                        <div class="mb-3">
                            <label class="form-label">💼 Profession</label>
                            <select class="form-select" id="f_profession">
                                <option value="">All</option>
                                <?php foreach ($professions as $profession): ?>
                                    <option value="<?php echo htmlspecialchars($profession); ?>" <?php echo ($_GET['profession'] ?? '') === $profession ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($profession); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if (in_array('age', $allowed_filters)): ?>
                        <div class="mb-3">
                            <label class="form-label">🎂 Age</label>
                            <select class="form-select" id="f_age">
                                <option value="">All</option>
                                <option value="18-25" <?php echo ($_GET['age'] ?? '') === '18-25' ? 'selected' : ''; ?>>18-25</option>
                                <option value="26-35" <?php echo ($_GET['age'] ?? '') === '26-35' ? 'selected' : ''; ?>>26-35</option>
                                <option value="36-45" <?php echo ($_GET['age'] ?? '') === '36-45' ? 'selected' : ''; ?>>36-45</option>
                                <option value="46-60" <?php echo ($_GET['age'] ?? '') === '46-60' ? 'selected' : ''; ?>>46-60</option>
                                <option value="60+" <?php echo ($_GET['age'] ?? '') === '60+' ? 'selected' : ''; ?>>60+</option>
                            </select>
                        </div>
                        <?php endif; ?> 

                        <?php if (in_array('education', $allowed_filters)): ?>
                        <div class="mb-3">
                            <label class="form-label">🎓 Education</label>
                            <select class="form-select" id="f_education">
                                <option value="">All</option>
                                <?php foreach ($education_degrees as $degree): ?>
                                    <option value="<?php echo htmlspecialchars($degree); ?>" <?php echo (isset($_GET['education']) && $_GET['education'] === $degree) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($degree); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select> 
                        </div>
                        <?php endif; ?>

                        <?php if (in_array('income', $allowed_filters)): ?>
                        <div class="mb-3">
                            <label class="form-label">💰 Income</label>
                            <select class="form-select" id="f_income">
                                <option value="">All</option>
                                <option value="<50000" <?php echo ($_GET['income'] ?? '') === '<50000' ? 'selected' : ''; ?>>Below 50,000</option>
                                <option value="50000-100000" <?php echo ($_GET['income'] ?? '') === '50000-100000' ? 'selected' : ''; ?>>50,000-100,000</option>
                                <option value="100000-200000" <?php echo ($_GET['income'] ?? '') === '100000-200000' ? 'selected' : ''; ?>>100,000-200,000</option>
                                <option value=">200000" <?php echo ($_GET['income'] ?? '') === '>200000' ? 'selected' : ''; ?>>Above 200,000</option>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if (in_array('height', $allowed_filters)): ?>
                        <div class="mb-3">
                            <label class="form-label">📏 Height</label>
                            <select class="form-select" id="f_height">
                                <option value="">All</option>
                                <option value="<150" <?php echo ($_GET['height'] ?? '') === '<150' ? 'selected' : ''; ?>>Below 150 cm</option>
                                <option value="150-170" <?php echo ($_GET['height'] ?? '') === '150-170' ? 'selected' : ''; ?>>150-170 cm</option>
                                <option value="170-190" <?php echo ($_GET['height'] ?? '') === '170-190' ? 'selected' : ''; ?>>170-190 cm</option>
                                <option value=">190" <?php echo ($_GET['height'] ?? '') === '>190' ? 'selected' : ''; ?>>Above 190 cm</option>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if (in_array('weight', $allowed_filters)): ?>
                        <div class="mb-3">
                            <label class="form-label">⚖️ Weight</label>
                            <select class="form-select" id="f_weight">
                                <option value="">All</option>
                                <option value="<50" <?php echo ($_GET['weight'] ?? '') === '<50' ? 'selected' : ''; ?>>Below 50 kg</option>
                                <option value="50-70" <?php echo ($_GET['weight'] ?? '') === '50-70' ? 'selected' : ''; ?>>50-70 kg</option>
                                <option value="70-90" <?php echo ($_GET['weight'] ?? '') === '70-90' ? 'selected' : ''; ?>>70-90 kg</option>
                                <option value=">90" <?php echo ($_GET['weight'] ?? '') === '>90' ? 'selected' : ''; ?>>Above 90 kg</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    
                        <button class="btn btn-primary w-100 mb-2" id="btnApply">
                            <i class="bi bi-funnel-fill me-2"></i>Apply Filters
                        </button>
                        <button class="btn btn-outline-secondary w-100" onclick="window.location.href='mem.php'">
                            <i class="bi bi-arrow-clockwise me-2"></i>Clear Filters
                        </button>
                    </div>
                </div>
            
                <!-- Members Section -->
                <div class="col-lg-8 col-md-7">
                <div class="row g-3" id="cardsRoot">
                    <?php foreach ($members as $member): ?>
                      <div class="col-lg-4 col-md-6 col-sm-6">
                        <div class="member-card">
                            <div class="package-badge">
                                <span class="badge <?php echo ($member['package_name'] === 'Free User') ? 'bg-secondary' : 'bg-primary'; ?>">
                                    <?php echo htmlspecialchars($member['package_name'] ?? 'Free User'); ?>
                                </span>
                            </div>
                            <div class="member-img">
                                <span class="member-id">ID: <?php echo htmlspecialchars($member['id']); ?></span>
                                <?php
                                // Check if profile should be hidden
                                // Only hide photo if profile_hidden = 1 in members table
                                $profileHidden = intval($member['profile_hidden'] ?? 0);
                                $shouldHideProfile = ($profileHidden === 1);
                                
                                // Debug comment (visible in page source)
                                echo "<!-- Member ID: " . $member['id'] . " | Profile Hidden: " . $profileHidden . " | Should Hide: " . ($shouldHideProfile ? 'YES' : 'NO') . " -->";
                                
                                if ($shouldHideProfile) {
                                    // Add hidden profile badge
                                    ?>
                                    <span class="badge bg-warning position-absolute" style="top:15px;right:15px;font-size:0.7rem;">
                                        <i class="bi bi-eye-slash"></i> Hidden
                                    </span>
                                    <?php
                                }
                                
                                // Now display the image based on hide status
                                if ($shouldHideProfile) {
                                    // Display first letter of name in a styled background
                                    $firstLetter = strtoupper(substr($member['name'] ?? 'U', 0, 1));
                                    ?>
                                    <div class="profile-initial">
                                        <?php echo $firstLetter; ?>
                                    </div>
                                    <?php
                                } else {
                                    // Display normal image
                                    $photoField = $member['photo'] ?? '';
                                    if (!empty($photoField)) {
                                        $photoField = ltrim($photoField, '/');
                                        if (strpos($photoField, 'uploads/') === 0) {
                                            $imgPath = $photoField;
                                            $fileCheck = __DIR__ . '/' . $photoField;
                                        } else {
                                            $imgPath = 'uploads/' . $photoField;
                                            $fileCheck = __DIR__ . '/uploads/' . $photoField;
                                        }
                                        if (!file_exists($fileCheck)) {
                                            $imgPath = 'img/d.webp';
                                        }
                                    } else {
                                        $imgPath = 'img/d.webp';
                                    }
                                    ?>
                                    <img src="<?php echo $imgPath; ?>" alt="<?php echo htmlspecialchars($member['photo']); ?>" style="object-fit:cover;width:100%;height:100%;">
                                    <?php
                                }
                                ?>
                            </div>
                            <div class="member-info">
                                <h4 class="member-name"><?php echo htmlspecialchars($member['name']); ?></h4>
                                <div class="member-details">
                                    <div><strong>Looking for:</strong> <?php echo htmlspecialchars($member['looking_for'] ?? '-'); ?></div>
                                    <div><strong>Age:</strong> <?php
                                        if (!empty($member['dob']) && $member['dob'] !== '0000-00-00') {
                                            $dob = new DateTime($member['dob']);
                                            $now = new DateTime();
                                            $age = $now->diff($dob)->y;
                                            echo $age;
                                        } else {
                                            echo '-';
                                        }
                                    ?></div>
                                    <div><strong>Religion:</strong> <?php echo htmlspecialchars($member['religion'] ?? '-'); ?></div>
                                    <div><strong>Profession:</strong> <?php echo htmlspecialchars($member['profession'] ?? '-'); ?></div>
                                    <div><strong>Location:</strong> <?php echo htmlspecialchars($member['country'] ?? '-'); ?></div>
                                    
                                </div>
                                <div class="member-actions">
                                    <div>
                                        <div class="interest-action-container">
                                            <i class="bi bi-heart action-icon interest-btn" 
                                               data-member-id="<?php echo htmlspecialchars($member['id']); ?>" 
                                               onclick="toggleInterest(this, <?php echo htmlspecialchars($member['id']); ?>)" 
                                               title="Express Interest">
                                            </i>
                                            <span class="interest-count" id="interest-count-<?php echo htmlspecialchars($member['id']); ?>">
                                                <?php echo htmlspecialchars($member['likes_received'] ?? '0'); ?>
                                            </span>
                                        </div>
                                        <i class="bi bi-whatsapp whatsapp-icon" 
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
                                        </i>
                                    </div>
                                    <button class="btn-view" data-member-id="<?php echo htmlspecialchars($member['id']); ?>" onclick="openDetails(<?php echo htmlspecialchars($member['id']); ?>)">
                                        <i class="bi bi-eye-fill me-1"></i>View Profile
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($members)): ?>
                    <div class="col-12 text-center py-5">
                        <div class="alert alert-info">
                            <i class="bi bi-search display-4 text-info mb-3"></i>
                            <h4>No members found</h4>
                            <p>Please try different filters or check back later for new members.</p>
                            <button class="btn btn-primary mt-2" onclick="window.location.href='mem.php'">
                                <i class="bi bi-arrow-clockwise me-2"></i>Reset Filters
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php 
                        // Build query string for pagination links
                        $query_params = $_GET;
                        unset($query_params['page']); // Remove page parameter
                        $query_string = !empty($query_params) ? '&' . http_build_query($query_params) : '';
                        ?>
                        
                        <!-- Previous Button -->
                        <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo ($current_page <= 1) ? '#' : '?page=' . ($current_page - 1) . $query_string; ?>" 
                               <?php echo ($current_page <= 1) ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>
                                <i class="bi bi-chevron-left me-1"></i>Previous
                            </a>
                        </li>
                        
                        <?php
                        // Calculate page numbers to show
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        // Show first page if we're not starting from 1
                        if ($start_page > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?page=1' . $query_string . '">1</a></li>';
                            if ($start_page > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }
                        
                        // Show page numbers
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            $active = ($i == $current_page) ? 'active' : '';
                            echo '<li class="page-item ' . $active . '"><a class="page-link" href="?page=' . $i . $query_string . '">' . $i . '</a></li>';
                        }
                        
                        // Show last page if we're not ending at the last page
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . $query_string . '">' . $total_pages . '</a></li>';
                        }
                        ?>
                        
                        <!-- Next Button -->
                        <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo ($current_page >= $total_pages) ? '#' : '?page=' . ($current_page + 1) . $query_string; ?>"
                               <?php echo ($current_page >= $total_pages) ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>
                                Next<i class="bi bi-chevron-right ms-1"></i>
                            </a>
                        </li>
                    </ul>
                    
                    <!-- Page Info -->
                    <div class="text-center mt-3">
                        <small class="text-white">
                            Showing <?php echo (($current_page - 1) * $records_per_page + 1); ?> to 
                            <?php echo min($current_page * $records_per_page, $total_records); ?> of 
                            <?php echo $total_records; ?> members
                        </small>
                    </div>
                </nav>
                <?php endif; ?>
            </div>
        </div> 
    </section>

    <!-- Enhanced Member Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailsModalLabel">
                        <i class="bi bi-person-badge me-2"></i>Profile Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row">
                        <!-- Left Side - Profile Image -->
                        <div class="col-md-4">
                            <div class="profile-image-container">
                                <img id="modalPhoto" src="img/d.webp" alt="Member Photo" class="profile-image-modal">
                                <div class="profile-basic-info">
                                    <h5 id="modalName" class="profile-name">Member Name</h5>
                                    <div class="profile-meta">
                                        <span class="badge bg-primary">ID: <span id="modalId">-</span></span>
                                        <span class="badge bg-success">Age: <span id="modalAge">-</span></span>
                                        <span class="badge bg-info">Package: <span id="modalPackage">-</span></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Side - Tabs and Content -->
                        <div class="col-md-8">
                            <!-- Tabs Navigation -->
                            <ul class="nav nav-pills nav-justified mb-3" id="profileTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" role="tab">Basic</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="physical-tab" data-bs-toggle="tab" data-bs-target="#physical" type="button" role="tab">Physical</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="family-tab" data-bs-toggle="tab" data-bs-target="#family" type="button" role="tab">Family</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="education-tab" data-bs-toggle="tab" data-bs-target="#education" type="button" role="tab">Education</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="partner-tab" data-bs-toggle="tab" data-bs-target="#partner" type="button" role="tab">Partner</button>
                                </li>
                                <?php if ($horoscope_access): ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="horoscope-tab" data-bs-toggle="tab" data-bs-target="#horoscope" type="button" role="tab">Horoscope</button>
                                </li>
                                <?php else: ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link disabled" id="horoscope-tab-disabled" type="button" role="tab" title="Upgrade to access Horoscope information">
                                        Horoscope <i class="bi bi-lock-fill"></i>
                                    </button>
                                </li>
                                <?php endif; ?>
                            </ul>
                            
                            <!-- Tab Content -->
                            <div class="tab-content modal-tab-content" id="profileTabContent">
                        <!-- Basic Information Tab -->
                        <div class="tab-pane fade show active" id="basic" role="tabpanel">
                            <div class="row g-2">
                                <div class="col-6"><small><strong>Name:</strong> <span id="nameVal">-</span></small></div>
                                <div class="col-6"><small><strong>Gender:</strong> <span id="genderVal">-</span></small></div>
                                <div class="col-6"><small><strong>DOB:</strong> <span id="dobVal">-</span></small></div>
                                <div class="col-6"><small><strong>Religion:</strong> <span id="religionVal">-</span></small></div>
                                <div class="col-6"><small><strong>Marital:</strong> <span id="maritalVal">-</span></small></div>
                                <div class="col-6"><small><strong>Language:</strong> <span id="languageVal">-</span></small></div>
                                <div class="col-6"><small><strong>Profession:</strong> <span id="professionVal">-</span></small></div>
                                <div class="col-6"><small><strong>Country:</strong> <span id="countryVal">-</span></small></div>   
                                
                             
                                
                                <div class="col-6" style="display:none;">
                                        <small><strong>Phone:</strong> <span id="phoneVal">-</span></small>
                                    </div>

                                
                                
                                
                                <div class="col-6"><small><strong>Smoking:</strong> <span id="smokingVal">-</span></small></div>
                                <div class="col-6"><small><strong>Drinking:</strong> <span id="drinkingVal">-</span></small></div>
                                <div class="col-6"><small><strong>Income:</strong> <span id="incomeVal">-</span></small></div>
                            </div>
                        </div>
                        
                        <!-- Physical Information Tab -->
                        <div class="tab-pane fade" id="physical" role="tabpanel">
                            <div class="row g-2">
                                <div class="col-6"><small><strong>Complexion:</strong> <span id="complexionVal">-</span></small></div>
                                <div class="col-6"><small><strong>Height:</strong> <span id="heightVal">-</span></small></div>
                                <div class="col-6"><small><strong>Weight:</strong> <span id="weightVal">-</span></small></div>
                                <div class="col-6"><small><strong>Blood Group:</strong> <span id="bloodVal">-</span></small></div>
                                <div class="col-6"><small><strong>Eye Color:</strong> <span id="eyeVal">-</span></small></div>
                                <div class="col-6"><small><strong>Hair Color:</strong> <span id="hairVal">-</span></small></div>
                                <div class="col-12"><small><strong>Disability:</strong> <span id="disabilityVal">-</span></small></div>
                            </div>
                        </div>
                        
                        <!-- Family Information Tab -->
                        <div class="tab-pane fade" id="family" role="tabpanel">
                            <div class="row g-2">
                                <div class="col-6"><small><strong>Father:</strong> <span id="fatherVal">-</span></small></div>
                                <div class="col-6"><small><strong>Father Profession:</strong> <span id="fatherProfessionVal">-</span></small></div>
                                
                              <div class="col-6" style="display:none;">
                                <small><strong>Father Contact:</strong> <span id="fatherContactVal">-</span></small>
                            </div>

                                
                                <div class="col-6"><small><strong>Mother:</strong> <span id="motherVal">-</span></small></div>
                                <div class="col-6"><small><strong>Mother Profession:</strong> <span id="motherProfessionVal">-</span></small></div>
                                
                               <div class="col-6" style="display:none;">
    <small><strong>Mother Contact:</strong> <span id="motherContactVal">-</span></small>
</div>

                                
                                
                                <div class="col-6"><small><strong>Brothers:</strong> <span id="brothersVal">-</span></small></div>
                                <div class="col-6"><small><strong>Sisters:</strong> <span id="sistersVal">-</span></small></div>
                            </div>
                        </div>
                        
                        <!-- Education Information Tab -->
                        <div class="tab-pane fade" id="education" role="tabpanel">
                            <div id="educationContainer">
                                <!-- Education items will be dynamically added here -->
                            </div>
                        </div>
                        
                        <!-- Partner Expectations Tab -->
                        <div class="tab-pane fade" id="partner" role="tabpanel">
                            <div class="row g-2">
                                <div class="col-6"><small><strong>Country:</strong> <span id="prefCountryVal">-</span></small></div>
                                <div class="col-6"><small><strong>Age Range:</strong> <span id="ageRangeVal">-</span></small></div>
                                <div class="col-6"><small><strong>Height Range:</strong> <span id="heightRangeVal">-</span></small></div>
                                <div class="col-6"><small><strong>Marital:</strong> <span id="prefMaritalVal">-</span></small></div>
                                <div class="col-6"><small><strong>Religion:</strong> <span id="prefReligionVal">-</span></small></div>
                                <div class="col-6"><small><strong>Smoking:</strong> <span id="prefSmokingVal">-</span></small></div>
                                <div class="col-6"><small><strong>Drinking:</strong> <span id="prefDrinkingVal">-</span></small></div>
                            </div>
                        </div>
                        
                        <!-- Horoscope Information Tab -->
                        <div class="tab-pane fade" id="horoscope" role="tabpanel">
                            <?php if ($horoscope_access): ?>
                            <div class="row g-2 mb-3">
                                <div class="col-6"><small><strong>Birth Date:</strong> <span id="horoscopeBirthDate">-</span></small></div>
                                <div class="col-6"><small><strong>Birth Time:</strong> <span id="horoscopeBirthTime">-</span></small></div>
                                <div class="col-6"><small><strong>Zodiac:</strong> <span id="horoscopeZodiac">-</span></small></div>
                                <div class="col-6"><small><strong>Nakshatra:</strong> <span id="horoscopeNakshatra">-</span></small></div>
                                <div class="col-12"><small><strong>Karmic Debt:</strong> <span id="horoscopeKarmic">-</span></small></div>
                            </div>
                            <div style="text-align: center; width:100%;  margin-top: 10px; margin-bottom: 20px; ">
                                    <button 
                                      id="compatibility_btn"
                                      onclick="showNakshatraComparison()"
                                      style="
                                        padding: 10px 20px;
                                        background-color: #FF746C;
                                        color: white;
                                        border: none;
                                        border-radius: 6px;
                                        cursor: pointer;
                                        font-size: 16px;
                                    ">
                                        Marriage compatibility
                                    </button>
                                </div>
                                
                                <div id="nakshatra_display" style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 8px; display: none;">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <div style="text-align: center; flex: 1;">
                                                <strong>Your Details:</strong><br>
                                                <span id="logged_user_nakshatra" style="color: #7f0808; font-weight: bold;">-</span><br>
                                                <small>(<span id="logged_user_gender" style="color: #666;">-</span>)</small>
                                            </div>
                                            <div style="text-align: center; flex: 1;">
                                                <strong>Their Details:</strong><br>
                                                <span id="member_nakshatra" style="color: #7f0808; font-weight: bold;">-</span><br>
                                                <small>(<span id="member_gender" style="color: #666;">-</span>)</small>
                                            </div>
                                        </div>
                                    </div>


                            <div id="horoscopeImages"></div>
                            <?php else: ?>
                            <div class="text-center py-5">
                                <div class="mb-4">
                                    <i class="bi bi-lock-fill" style="font-size: 4rem; color: #dc3545;"></i>
                                </div>
                                <h5 class="mb-3">Horoscope Access Restricted</h5>
                                <p class="text-muted mb-4">
                                    <?php if ($search_access === 'Basic'): ?>
                                        Free users do not have access to horoscope information. 
                                    <?php else: ?>
                                        Your current package does not include matchmaker services.
                                    <?php endif ?>
                                    Upgrade to a package with matchmaker features to view detailed horoscope information.
                                </p>
                                <div class="alert alert-info">
                                    <i class="bi bi-star"></i> 
                                    <strong>Upgrade Required:</strong> 
                                    <?php if ($search_access === 'Basic'): ?>
                                        Purchase a premium package with matchmaker services to access horoscope details.
                                    <?php else: ?>
                                        Switch to a package that includes matchmaker services (like Platinum) to unlock horoscope access.
                                    <?php endif ?>
                                </div>
                                <button class="btn btn-primary">
                                    <i class="bi bi-arrow-up-circle me-2"></i>Upgrade Package
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-3 mb-4">
                    <h5 class="mb-3"> Thennilavu </h5>
                    <p>Connecting hearts, building relationships since 2010. Your journey to finding the perfect life partner begins here.</p>
                </div>
                
                <div class="col-md-3 mb-4">
                    <h5 class="mb-3">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="index.php" class="text-white text-decoration-none">Home</a></li>
                       
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

    <style>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Custom notification function
        function showCustomNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'success' ? 'success' : type === 'error' ? 'danger' : type === 'warning' ? 'warning' : 'info'} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            // Add to page
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification && notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

        // Heart animation
        document.addEventListener("mousemove", function(e) {
            if (Math.random() > 0.95) {
                const heart = document.createElement("div");
                heart.className = "heart";
                heart.innerHTML = "❤️";
                
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
        
        // Toggle favorite
        function toggleFavorite(el) {
            el.classList.toggle('active');
            if (el.classList.contains('active')) {
                el.classList.remove('bi-heart');
                el.classList.add('bi-heart-fill');
                // Add animation effect
                el.style.animation = 'pulse 0.6s ease';
                setTimeout(() => {
                    el.style.animation = '';
                }, 600);
            } else {
                el.classList.remove('bi-heart-fill');
                el.classList.add('bi-heart');
            }
        }
        
        // Interest functionality
        function toggleInterest(element, memberId) {
            // Check if user is logged in
            <?php if (!isset($_SESSION['user_id'])): ?>
            showCustomNotification('Please login to express interest', 'warning');
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
                    element.classList.remove('bi-heart');
                    element.classList.add('bi-heart-fill');
                    element.title = 'Express Interest';
                    element.style.cursor = 'pointer';
                    // small pulse animation to show the action
                    element.style.animation = 'pulse 0.6s ease';
                    setTimeout(() => { element.style.animation = ''; }, 700);

                    showCustomNotification(`Interest expressed! (${data.current_count}/${data.limit === 'Unlimited' ? '∞' : data.limit})`, 'success');
                    updateInterestCounter(data.current_count, data.limit);

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
                        showCustomNotification(data.error || 'Failed to process request', 'error');
                    }
                }
            })
            .catch(error => {
                element.classList.remove('loading');
                console.error('Error:', error);
                showCustomNotification('Network error. Please try again.', 'error');
            });
        }
        
        // Function to update likes count in real-time
        function updateLikesCount(memberId) {
            // Update the main likes display
            const likesElement = document.getElementById(`likes-count-${memberId}`);
            if (likesElement) {
                let currentCount = parseInt(likesElement.textContent) || 0;
                currentCount += 1;
                likesElement.textContent = currentCount;
                
                // Add a small animation to highlight the change
                likesElement.classList.add('updated');
                setTimeout(() => {
                    likesElement.classList.remove('updated');
                }, 1000);
            }

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
        
       
        // WhatsApp contact function
        function contactWhatsApp(memberId, memberName, age, profession) {

    // Step 1: Fetch WhatsApp number from backend
    fetch('mem.php?get_whatsapp=1')
        .then(response => response.json())
        .then(data => {
            const phoneNumber = data.whatsapp_number;

            if (!phoneNumber) {
                alert("WhatsApp number not found!");
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
        .catch(error => console.error('Error fetching WhatsApp number:', error));
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
                        button.classList.add('interested');
                        button.classList.remove('bi-heart');
                        button.classList.add('bi-heart-fill');
                        button.title = 'Add Interest';
                    }
                })
                .catch(error => {
                    console.error('Error checking interest status:', error);
                });
            });
            <?php endif; ?>
        }
        
        // Call checkInterestStatus when page loads
        document.addEventListener('DOMContentLoaded', function() {
            checkInterestStatus();
            loadCurrentInterestCount();
            loadCurrentProfileViewsCount();
            
            // Add event listeners for profile stats icon and modal
            const profileStatsIcon = document.getElementById('profileStatsIcon');
            const profileStatsModal = document.getElementById('profileStatsModal');
            
            if (profileStatsIcon) {
                // Show modal on hover
                profileStatsIcon.addEventListener('mouseenter', function() {
                    const modal = new bootstrap.Modal(profileStatsModal);
                    modal.show();
                });
            }
            
            if (profileStatsModal) {
                profileStatsModal.addEventListener('show.bs.modal', function() {
                    // Refresh data when modal is opened
                    loadCurrentInterestCount();
                    loadCurrentProfileViewsCount();
                });
            }
        });
        
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
                                    <h5 class="text-success mb-0">₹${priceText}</h5>
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
                            <i class="bi bi-info-circle me-2"></i>
                            Check out our premium packages for unlimited interests!
                        </div>
                    </div>
                `;
            }
            
            modal.show();
        }
        
        // Filter functionality
        document.getElementById('btnApply').addEventListener('click', function() {
            const params = new URLSearchParams();
            
            // Helper function to safely get element value
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
            const height = getElementValue('f_height');
            const weight = getElementValue('f_weight');
            const education = getElementValue('f_education');
            const income = getElementValue('f_income');
            
            // Get allowed filters from PHP
            const allowedFilters = <?php echo json_encode($allowed_filters); ?>;
            
            if (sortBy) params.append('sort', sortBy);
            if (lookingFor && allowedFilters.includes('looking_for')) params.append('looking_for', lookingFor);
            if (maritalStatus && allowedFilters.includes('marital_status')) params.append('marital_status', maritalStatus);
            if (religion && allowedFilters.includes('religion')) params.append('religion', religion);
            if (country && allowedFilters.includes('country')) params.append('country', country);
            if (profession && allowedFilters.includes('profession')) params.append('profession', profession);
            if (age && allowedFilters.includes('age')) params.append('age', age);
            if (height && allowedFilters.includes('height')) params.append('height', height);
            if (weight && allowedFilters.includes('weight')) params.append('weight', weight);
            if (education && allowedFilters.includes('education')) params.append('education', education);
            if (income && allowedFilters.includes('income')) params.append('income', income);
            
            window.location.href = '?' + params.toString();
        });
        
        // Auto-apply sorting when sort dropdown changes
        const sortElement = document.getElementById('f_sort');
        if (sortElement) {
            sortElement.addEventListener('change', function() {
                const params = new URLSearchParams(window.location.search);
                params.set('sort', this.value);
                window.location.href = '?' + params.toString();
            });
        }
        
        // Scroll to top function
        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }
        
        // Show/hide floating action button
        window.addEventListener('scroll', function() {
            const fab = document.querySelector('.fab');
            if (window.scrollY > 300) {
                fab.style.display = 'flex';
            } else {
                fab.style.display = 'none';
            }
        });
        
        // Handle disabled horoscope tab clicks
        document.addEventListener('click', function(e) {
            if (e.target && e.target.id === 'horoscope-tab-disabled') {
                e.preventDefault();
                showUpgradeNotification();
                return false;
            }
        });
        
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
                title = '💎 Upgrade to Premium';
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
                        <i class="bi bi-lock-fill"></i>
                    </div>
                    <div class="upgrade-text">
                        <h5>${title}</h5>
                        <p>${message}</p>
                        <small class="text-muted">Current: <?php echo htmlspecialchars($user_type); ?> | Access: ${userAccess}</small>
                    </div>
                    <div class="upgrade-actions">
                        ${upgradeType === 'interest_limit' ? 
                        `<a href="package.php" class="btn btn-primary btn-sm me-2">
                            <i class="bi bi-arrow-up-circle"></i> Upgrade Now
                        </a>` : ''}
                        <button class="btn btn-outline-secondary btn-sm" onclick="this.closest('.upgrade-notification').remove()" title="Close">
                            <i class="bi bi-x"></i>
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
        
        function getCurrentModalMemberId() {
            const modal = document.getElementById('detailsModal');
            if (modal && modal.classList.contains('show')) {
                const viewBtn = modal.querySelector('.btn-view[data-member-id]');
                return viewBtn ? viewBtn.getAttribute('data-member-id') : null;
            }
            return null;
        }
        
        // Image viewing functionality with profile view restrictions
        function handleImageClick(imageSrc, imageAlt = 'Profile Image') {
            <?php if (isset($_SESSION['user_id'])): ?>
            // Check if user has profile view enabled in their package
            const profileViewEnabled = '<?php echo $profile_view_enabled; ?>';
            
            if (profileViewEnabled === 'Yes') {
                // User can view images - show full size image modal
                const memberId = getCurrentModalMemberId();
                showFullSizeImage(imageSrc, imageAlt, memberId);
            } else {
                // User cannot view images - show upgrade modal
                showProfileViewUpgradeModal();
            }
            <?php else: ?>
            // Non-logged in users get upgrade prompt
            showProfileViewUpgradeModal();
            <?php endif; ?>
        }
        
        // Image carousel variables
        let currentImageIndex = 0;
        let imageCarouselData = [];
        let autoPlayInterval = null;
        let isAutoPlaying = true;
        let currentMemberId = null; // Store current member ID
        
        function showFullSizeImage(imageSrc, imageAlt, memberId = null) {
            // Fetch all images for this member
            if (memberId) {
                fetchMemberImages(memberId, imageSrc, imageAlt);
            } else {
                // Single image mode
                imageCarouselData = [{src: imageSrc, alt: imageAlt}];
                initializeImageCarousel();
            }
            
            const imageModal = new bootstrap.Modal(document.getElementById('imageViewModal'));
            imageModal.show();
        }
        
        function fetchMemberImages(memberId, primarySrc, primaryAlt) {
            fetch(`api_member_photos.php?member_id=${memberId}`)
                .then(response => response.json())
                .then(data => {
                    imageCarouselData = [{src: primarySrc, alt: primaryAlt}]; // Start with primary image
                    if (data.success && data.photos) {
                        data.photos.forEach(photo => {
                            imageCarouselData.push({src: photo.photo_path, alt: primaryAlt + ' - Additional Photo'});
                        });
                    }
                    initializeImageCarousel();
                })
                .catch(error => {
                    console.error('Error fetching member images:', error);
                    imageCarouselData = [{src: primarySrc, alt: primaryAlt}];
                    initializeImageCarousel();
                });
        }
        
        function initializeImageCarousel() {
            currentImageIndex = 0;
            updateImageDisplay();
            createImageIndicators();
            startAutoPlay();
            
            // Show/hide navigation buttons
            const prevBtn = document.getElementById('prevImageBtn');
            const nextBtn = document.getElementById('nextImageBtn');
            const autoPlayBtn = document.getElementById('autoPlayToggle');
            
            if (imageCarouselData.length > 1) {
                prevBtn.style.display = 'block';
                nextBtn.style.display = 'block';
                autoPlayBtn.style.display = 'block';
            } else {
                prevBtn.style.display = 'none';
                nextBtn.style.display = 'none';
                autoPlayBtn.style.display = 'none';
            }
        }
        
        function updateImageDisplay() {
            const fullSizeImage = document.getElementById('fullSizeImage');
            const modalLabel = document.getElementById('imageViewModalLabel');
            const imageCounter = document.getElementById('imageCounter');
            
            if (imageCarouselData.length > 0) {
                const currentImage = imageCarouselData[currentImageIndex];
                fullSizeImage.src = currentImage.src;
                fullSizeImage.alt = currentImage.alt;
                modalLabel.innerHTML = `<i class="bi bi-image me-2"></i>Profile Images`;
                imageCounter.textContent = `${currentImageIndex + 1} / ${imageCarouselData.length}`;
                
                // Update indicators
                updateImageIndicators();
            }
        }
        
        function createImageIndicators() {
            const indicators = document.getElementById('imageIndicators');
            indicators.innerHTML = '';
            
            if (imageCarouselData.length > 1) {
                imageCarouselData.forEach((_, index) => {
                    const dot = document.createElement('button');
                    dot.className = `btn btn-sm rounded-circle mx-1 ${index === 0 ? 'btn-primary' : 'btn-outline-light'}`;
                    dot.style.width = '12px';
                    dot.style.height = '12px';
                    dot.onclick = () => goToImage(index);
                    indicators.appendChild(dot);
                });
            }
        }
        
        function updateImageIndicators() {
            const indicators = document.getElementById('imageIndicators');
            const dots = indicators.querySelectorAll('button');
            dots.forEach((dot, index) => {
                dot.className = `btn btn-sm rounded-circle mx-1 ${index === currentImageIndex ? 'btn-primary' : 'btn-outline-light'}`;
            });
        }
        
        function nextImage() {
            if (imageCarouselData.length > 1) {
                currentImageIndex = (currentImageIndex + 1) % imageCarouselData.length;
                updateImageDisplay();
            }
        }
        
        function prevImage() {
            if (imageCarouselData.length > 1) {
                currentImageIndex = currentImageIndex === 0 ? imageCarouselData.length - 1 : currentImageIndex - 1;
                updateImageDisplay();
            }
        }
        
        function goToImage(index) {
            if (index >= 0 && index < imageCarouselData.length) {
                currentImageIndex = index;
                updateImageDisplay();
            }
        }
        
        function startAutoPlay() {
            if (imageCarouselData.length > 1 && isAutoPlaying) {
                autoPlayInterval = setInterval(nextImage, 10000); // 10 seconds
            }
        }
        
        function stopAutoPlay() {
            if (autoPlayInterval) {
                clearInterval(autoPlayInterval);
                autoPlayInterval = null;
            }
        }
        
        function toggleAutoPlay() {
            const autoPlayBtn = document.getElementById('autoPlayToggle');
            if (isAutoPlaying) {
                stopAutoPlay();
                isAutoPlaying = false;
                autoPlayBtn.innerHTML = '<i class="bi bi-play-fill"></i>';
            } else {
                startAutoPlay();
                isAutoPlaying = true;
                autoPlayBtn.innerHTML = '<i class="bi bi-pause-fill"></i>';
            }
        }
        
        // Event listeners for navigation
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('nextImageBtn').onclick = nextImage;
            document.getElementById('prevImageBtn').onclick = prevImage;
            document.getElementById('autoPlayToggle').onclick = toggleAutoPlay;
            
            // Stop auto-play when modal is closed
            document.getElementById('imageViewModal').addEventListener('hidden.bs.modal', function() {
                stopAutoPlay();
            });
            
            // Keyboard navigation
            document.getElementById('imageViewModal').addEventListener('keydown', function(e) {
                if (e.key === 'ArrowRight') nextImage();
                if (e.key === 'ArrowLeft') prevImage();
                if (e.key === ' ') {
                    e.preventDefault();
                    toggleAutoPlay();
                }
            });
        });
        
        function showProfileViewUpgradeModal() {
            const upgradeModal = new bootstrap.Modal(document.getElementById('profileViewUpgradeModal'));
            upgradeModal.show();
        }
        
        // Add click handlers to profile images when modal content is loaded
        function addImageClickHandlers() {
            // Add click handler to main profile image in modal
            const modalPhoto = document.getElementById('modalPhoto');
            if (modalPhoto) {
                // Only make image clickable if profile is not hidden
                if (!window.currentProfileHidden) {
                    modalPhoto.style.cursor = 'pointer';
                    modalPhoto.title = 'Click to view full size gallery';
                    modalPhoto.onclick = function() {
                        console.log('Modal photo clicked, currentMemberId:', currentMemberId);
                        showImageCarousel(currentMemberId);
                    };
                } else {
                    modalPhoto.style.cursor = 'default';
                    modalPhoto.title = 'Image not available for hidden profiles';
                    modalPhoto.onclick = null;
                }
            }
            
            // Add click handlers to any additional images in the modal content
            const modalImages = document.querySelectorAll('#detailsModal .modal-body img');
            modalImages.forEach(img => {
                if (img.id !== 'modalPhoto') { // Skip main profile image as it's already handled
                    if (!window.currentProfileHidden) {
                        img.style.cursor = 'pointer';
                        img.title = 'Click to view full size gallery';
                        img.onclick = function() {
                            console.log('Additional image clicked, currentMemberId:', currentMemberId);
                            showImageCarousel(currentMemberId);
                        };
                    } else {
                        img.style.cursor = 'default';
                        img.title = 'Image not available for hidden profiles';
                        img.onclick = null;
                    }
                }
            });
        }
        
        // Modal functionality
        const detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));
        
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
            currentMemberId = memberId;
            
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
                        alert('Error: ' + viewData.error);
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
                alert('Error checking profile views limit. Please try again.');
            });
            <?php else: ?>
            // For non-logged-in users, open directly
            openProfileModal(memberId);
            <?php endif; ?>
        }
        
        function openProfileModal(memberId) {
            <?php if (isset($_SESSION['user_id'])): ?>
            // Check if user has profile view access
            const profileViewEnabled = '<?php echo $profile_view_enabled; ?>';
            
            if (profileViewEnabled !== 'Yes') {
                // User doesn't have profile view enabled - show upgrade modal instead
                showProfileViewUpgradeModal();
                return;
            }
            <?php endif; ?>
            
            // Store original modal content
            const modalBody = document.querySelector('.modal-body');
            const originalContent = modalBody.innerHTML;
            
            // Show loading state
            modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-3">Loading profile details...</p></div>';
            detailsModal.show();
            
            fetch('?action=get_member&id=' + memberId)
                .then(res => {
                    if (!res.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return res.json();
                })
                .then(data => {
                    // Restore original modal content first
                    modalBody.innerHTML = originalContent;
                    
                    if (!data || !data.member) {
                        throw new Error('Invalid data received');
                    }
                    const m = data.member || {};
                    const p = data.physical || {};
                    const f = data.family || {};
                    const partner = data.partner || {};
                    const education = data.education || [];
                    const horoscope = data.horoscope || {};
                    
                    window.currentMemberData = data;

                    // Handle photo path
                    let photoPath = 'img/d.webp';
                    if (m.photo) {
                        let photo = m.photo.replace(/^\/+/, '');
                        photoPath = photo.startsWith('uploads/') ? photo : 'uploads/' + photo;
                    }
                    
                    // Basic Information
                    document.getElementById('modalName').textContent = m.name || '-';
                    document.getElementById('modalId').textContent = m.id || '-';
                    document.getElementById('modalAge').textContent = calculateAge(m.dob);
                    document.getElementById('modalPackage').textContent = data.package || 'Free User';
                    
                    // Store profile_hidden status globally for image click handling
                    window.currentProfileHidden = m.profile_hidden == 1;
                    
                    if (m.profile_hidden == 1) {
                        document.getElementById('modalPhoto').src ='/img/defaultBG.png'
                    } else {
                        document.getElementById('modalPhoto').src = photoPath;

                    }

                    // Basic Information
                    document.getElementById('nameVal').textContent = m.name || '-';
                    document.getElementById('genderVal').textContent = m.gender || '-';
                    document.getElementById('dobVal').textContent = m.dob || '-';
                    document.getElementById('religionVal').textContent = m.religion || '-';
                    document.getElementById('maritalVal').textContent = m.marital_status || '-';
                    document.getElementById('languageVal').textContent = m.language || '-';
                    document.getElementById('professionVal').textContent = m.profession || '-';
                    document.getElementById('countryVal').textContent = m.country || '-';
                    document.getElementById('phoneVal').textContent = m.phone || '-';
                    document.getElementById('smokingVal').textContent = m.smoking || '-';
                    document.getElementById('drinkingVal').textContent = m.drinking || '-';
                    document.getElementById('incomeVal').textContent = m.income || '-';


                    // Physical Information
                    document.getElementById('complexionVal').textContent = p.complexion || '-';
                    document.getElementById('heightVal').textContent = p.height_cm ? p.height_cm + ' cm' : '-';
                    document.getElementById('weightVal').textContent = p.weight_kg ? p.weight_kg + ' kg' : '-';
                    document.getElementById('bloodVal').textContent = p.blood_group || '-';
                    document.getElementById('eyeVal').textContent = p.eye_color || '-';
                    document.getElementById('hairVal').textContent = p.hair_color || '-';
                    document.getElementById('disabilityVal').textContent = p.disability || '-';

                    // Family Information
                    document.getElementById('fatherVal').textContent = f.father_name || '-';
                    document.getElementById('fatherProfessionVal').textContent = f.father_profession || '-';
                    document.getElementById('fatherContactVal').textContent = f.father_contact || '-';
                    document.getElementById('motherVal').textContent = f.mother_name || '-';
                    document.getElementById('motherProfessionVal').textContent = f.mother_profession || '-';
                    document.getElementById('motherContactVal').textContent = f.mother_contact || '-';
                    document.getElementById('brothersVal').textContent = f.brothers_count !== null ? f.brothers_count : '-';
                    document.getElementById('sistersVal').textContent = f.sisters_count !== null ? f.sisters_count : '-';

                    // Education Information
                    const educationContainer = document.getElementById('educationContainer');
                    educationContainer.innerHTML = '';
                    
                    if (education.length > 0) {
                        education.forEach(edu => {
                            const eduItem = document.createElement('div');
                            eduItem.className = 'education-item';
                            eduItem.innerHTML = `
                                <h6><strong>${edu.level || 'Education'}</strong></h6>
                                <p><strong>Institute:</strong> ${edu.school_or_institute || '-'}</p>
                                <p><strong>Degree/Stream:</strong> ${edu.stream_or_degree || '-'}</p>
                                <p><strong>Field:</strong> ${edu.field || '-'}</p>
                                <p><strong>Registration No:</strong> ${edu.reg_number || '-'}</p>
                                <p><strong>Period:</strong> ${edu.start_year || ''} - ${edu.end_year || ''}</p>
                                <p><strong>Result:</strong> ${edu.result || '-'}</p>
                            `;
                            educationContainer.appendChild(eduItem);
                        });
                    } else {
                        educationContainer.innerHTML = '<p class="text-muted">No education information available.</p>';
                    }

                    // Partner Expectations
                    document.getElementById('prefCountryVal').textContent = partner.preferred_country || '-';
                    let ageRange = '-';
                    if (partner.min_age && partner.max_age) {
                        ageRange = partner.min_age + ' - ' + partner.max_age + ' years';
                    } else if (partner.min_age) {
                        ageRange = 'Above ' + partner.min_age + ' years';
                    } else if (partner.max_age) {
                        ageRange = 'Below ' + partner.max_age + ' years';
                    }
                    document.getElementById('ageRangeVal').textContent = ageRange;
                    
                    let heightRange = '-';
                    if (partner.min_height && partner.max_height) {
                        heightRange = partner.min_height + ' - ' + partner.max_height + ' cm';
                    } else if (partner.min_height) {
                        heightRange = 'Above ' + partner.min_height + ' cm';
                    } else if (partner.max_height) {
                        heightRange = 'Below ' + partner.max_height + ' cm';
                    }
                    document.getElementById('heightRangeVal').textContent = heightRange;
                    document.getElementById('prefMaritalVal').textContent = partner.marital_status || '-';
                    document.getElementById('prefReligionVal').textContent = partner.religion || '-';
                    document.getElementById('prefSmokingVal').textContent = partner.smoking || '-';
                    document.getElementById('prefDrinkingVal').textContent = partner.drinking || '-';

                    // Horoscope Information - Only populate if user has access
                    <?php if ($horoscope_access): ?>
                    document.getElementById('horoscopeBirthDate').textContent = horoscope.birth_date || '-';
                    document.getElementById('horoscopeBirthTime').textContent = formatTime(horoscope.birth_time);
                    document.getElementById('horoscopeZodiac').textContent = horoscope.zodiac || '-';
                    const nakshatraNames = {
    "1001": "அஸ்வினி",
    "1002": "பரணி",
    "1003": "கார்த்திகை 1ம் பாதம்",
    "1004": "கார்த்திகை 2ம் பாதம்",
    "1005": "கார்த்திகை 3ம் பாதம்",
    "1006": "கார்த்திகை 4ம் பாதம்",
    "1007": "ரோகிணி",
    "1008": "மிருகசீரிடம் 1ம் பாதம்",
    "1009": "மிருகசீரிடம் 2ம் பாதம்",
    "1010": "மிருகசீரிடம் 3ம் பாதம்",
    "1011": "மிருகசீரிடம் 4ம் பாதம்",
    "1012": "திருவாதிரை",
    "1013": "புனர்பூசம் 1ம் பாதம்",
    "1014": "புனர்பூசம் 2ம் பாதம்",
    "1015": "புனர்பூசம் 3ம் பாதம்",
    "1016": "புனர்பூசம் 4ம் பாதம்",
    "1017": "பூசம்",
    "1018": "ஆயிலியம்",
    "1019": "மகம்",
    "1020": "பூரம்",
    "1021": "உத்தரம் 1ம் பாதம்",
    "1022": "உத்தரம் 2ம் பாதம்",
    "1023": "உத்தரம் 3ம் பாதம்",
    "1024": "உத்தரம் 4ம் பாதம்",
    "1025": "அஸ்தம்",
    "1026": "சித்திரை 1ம் பாதம்",
    "1027": "சித்திரை 2ம் பாதம்",
    "1028": "சித்திரை 3ம் பாதம்",
    "1029": "சித்திரை 4ம் பாதம்",
    "1030": "சுவாதி",
    "1031": "விசாகம் 1ம் பாதம்",
    "1032": "விசாகம் 2ம் பாதம்",
    "1033": "விசாகம் 3ம் பாதம்",
    "1034": "விசாகம் 4ம் பாதம்",
    "1035": "அனுஷம்",
    "1036": "கேட்டை",
    "1037": "மூலம்",
    "1038": "பூராடம்",
    "1039": "உத்திராடம் 1ம் பாதம்",
    "1040": "உத்திராடம் 2ம் பாதம்",
    "1041": "உத்திராடம் 3ம் பாதம்",
    "1042": "உத்திராடம் 4ம் பாதம்",
    "1043": "திருவோணம்",
    "1044": "அவிட்டம் 1 ம் பாதம்",
    "1045": "அவிட்டம் 2ம் பாதம்",
    "1046": "அவிட்டம் 3ம் பாதம்",
    "1047": "அவிட்டம் 4ம் பாதம்",
    "1048": "சதயம்",
    "1049": "பூரட்டாதி 1ம் பாதம்",
    "1050": "பூரட்டாதி 2ம் பாதம்",
    "1051": "பூரட்டாதி 3ம் பாதம்",
    "1052": "பூரட்டாதி 4ம் பாதம்",
    "1053": "உத்திரட்டாதி",
    "1054": "ரேவதி"
};

document.getElementById('horoscopeNakshatra').textContent =
    nakshatraNames[horoscope.nakshatra] || '-';

                    document.getElementById('horoscopeKarmic').textContent = horoscope.karmic_debt || '-';
                    
                    // Horoscope Images
                    const horoscopeImages = document.getElementById('horoscopeImages');
                    horoscopeImages.innerHTML = '';
                    
                    if (horoscope.planet_image) {
                        const planetImg = document.createElement('div');
                        planetImg.className = 'horoscope-img';
                        planetImg.innerHTML = `<strong>Planet Image</strong><br><img src="${horoscope.planet_image}" alt="Planet Image">`;
                        horoscopeImages.appendChild(planetImg);
                    }
                    
                    if (horoscope.kundali_image) {
                        const kundaliImg = document.createElement('div');
                        kundaliImg.className = 'horoscope-img';
                        kundaliImg.innerHTML = `<strong>Kundali Image</strong><br><img src="${horoscope.kundali_image}" alt="Kundali Image">`;
                        horoscopeImages.appendChild(kundaliImg);
                    }
                    
                    if (horoscope.navamsha_image) {
                        const navamshaImg = document.createElement('div');
                        navamshaImg.className = 'horoscope-img';
                        navamshaImg.innerHTML = `<strong>Navamsha Image</strong><br><img src="${horoscope.navamsha_image}" alt="Navamsha Image">`;
                        horoscopeImages.appendChild(navamshaImg);
                    }
                    
                    if (!horoscope.planet_image && !horoscope.kundali_image && !horoscope.navamsha_image) {
                        horoscopeImages.innerHTML = '<p class="text-muted">No horoscope images available.</p>';
                    }
                    <?php else: ?>
                    // User doesn't have horoscope access - data is already restricted in the PHP template
                    console.log('Horoscope access restricted for current user package');
                    <?php endif; ?>

                    // Reset to Basic tab after content is loaded
                    document.querySelector('#basic-tab').click();
                    
                    // Add image click handlers with profile view restrictions
                    addImageClickHandlers();
                    
                    detailsModal.show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Restore original modal content
                    modalBody.innerHTML = originalContent;
                    
                    // Show error message in modal instead of alert
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'alert alert-danger text-center';
                    errorDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i>Unable to load member details. Please try again later.';
                    modalBody.insertBefore(errorDiv, modalBody.firstChild);
                    
                    // Auto-hide error after 3 seconds
                    setTimeout(() => {
                        if (errorDiv.parentNode) {
                            errorDiv.remove();
                        }
                    }, 3000);
                });
        }
        
        function showNakshatraComparison() {
            const loggedUserNakshatra = '<?php echo $logged_user_nakshatra ?? ''; ?>';
            const memberData = window.currentMemberData;
            
            if (!memberData || !memberData.horoscope) {
                alert('Horoscope information not available for this member.');
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
                const toast = document.createElement('div');
                toast.className = 'alert alert-warning alert-dismissible fade show position-fixed';
                toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 350px; border-radius: 15px; box-shadow: 0 10px 30px rgba(255, 193, 7, 0.3); border: none; background: linear-gradient(135deg, #fff3cd, #ffeaa7);';
                toast.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="background: linear-gradient(135deg, #f39c12, #e67e22); color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                        </div>
                        <div style="flex: 1;">
                            <strong style="color: #856404; font-size: 1.1rem;">Compatibility Check</strong><br>
                            <span style="color: #664d03; font-size: 0.95rem;">Opposite gender is possible to compatibility</span>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" style="filter: none; opacity: 0.7;"></button>
                    </div>
                `;
                
                document.body.appendChild(toast);
                
                // Auto remove after 6 seconds
                setTimeout(() => {
                    if (toast && toast.parentNode) {
                        toast.remove();
                    }
                }, 6000);
                
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
            
            

            
                window.open(
    'Nakshatra.php?boy_id=' + maleNakshatra + '&girl_id=' + femaleNakshatra,
    '_blank'  // opens in a new tab
);


        }

        // Image Carousel Variables and Functions
        let currentImages = [];

        function showImageCarousel(memberId) {
            console.log('showImageCarousel called with memberId:', memberId);
            
            // Show the modal first to provide immediate feedback
            const imageModal = new bootstrap.Modal(document.getElementById('imageViewModal'));
            imageModal.show();
            
            // Show loading in the image
            const fullSizeImage = document.getElementById('fullSizeImage');
            fullSizeImage.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="200" height="100"><text y="50%" x="50%" text-anchor="middle" dy=".35em">Loading...</text></svg>';
            
            // Fetch images from API and show in carousel
            fetch('api_member_photos.php?member_id=' + memberId)
                .then(response => {
                    console.log('API response received');
                    return response.json();
                })
                .then(data => {
                    console.log('API data:', data);
                    if (data.success && data.photos && data.photos.length > 0) {
                        // Convert to the format expected by existing carousel
                        imageCarouselData = data.photos.map(photo => ({
                            src: photo.photo_path,
                            alt: photo.alt || 'Member Photo'
                        }));
                        currentImageIndex = 0;
                        initializeImageCarousel();
                    } else {
                        // Show no images message with debug info
                        console.log('No photos found. Debug info:', data.debug || 'No debug info');
                        fullSizeImage.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="300" height="100"><text y="40%" x="50%" text-anchor="middle" dy=".35em" font-size="14">No additional images found</text><text y="60%" x="50%" text-anchor="middle" dy=".35em" font-size="12">' + (data.error || 'Check console for details') + '</text></svg>';
                        document.getElementById('imageCounter').textContent = '0 / 0';
                        
                        // Hide navigation buttons
                        document.getElementById('prevImageBtn').style.display = 'none';
                        document.getElementById('nextImageBtn').style.display = 'none';
                        document.getElementById('autoPlayToggle').style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error loading images:', error);
                    fullSizeImage.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="200" height="100"><text y="50%" x="50%" text-anchor="middle" dy=".35em">Error loading images</text></svg>';
                });
        }
    </script>

    <!-- Upgrade Modal -->
    <div class="modal fade" id="upgradeModal" tabindex="-1" aria-labelledby="upgradeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(127, 8, 8, 0.3);">
                <div class="modal-header" style="background: linear-gradient(135deg, #7f0808 0%, #a00 100%); color: white; border-radius: 15px 15px 0 0;">
                    <h5 class="modal-title" id="upgradeModalLabel">
                        <i class="bi bi-arrow-up-circle-fill me-2"></i>Upgrade Required
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center" style="padding: 30px;">
                    <div class="mb-4">
                        <i class="bi bi-heart-fill" style="font-size: 3rem; color: #e74c3c;"></i>
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
                        <a href="package.php" class="btn btn-primary btn-lg" style="background: var(--primary-gradient); border: none; border-radius: 10px;">
                            <i class="bi bi-arrow-up-circle me-2"></i>View All Packages
                        </a>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            Maybe Later
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Viewing Modal -->
    <div class="modal fade" id="imageViewModal" tabindex="-1" aria-labelledby="imageViewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-dark">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-white" id="imageViewModalLabel">
                        <i class="bi bi-image me-2"></i>Profile Images
                    </h5>
                    <div class="d-flex align-items-center">
                        <span class="text-white me-3" id="imageCounter">1 / 1</span>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body text-center p-0 position-relative">
                    <div id="imageCarousel" class="position-relative">
                        <img id="fullSizeImage" src="" alt="Profile Image" class="img-fluid" style="max-height: 80vh; width: auto; transition: opacity 0.3s ease;">
                        
                        <!-- Navigation buttons -->
                        <button type="button" id="prevImageBtn" class="btn btn-primary position-absolute top-50 start-0 translate-middle-y ms-3" style="z-index: 10; opacity: 0.8;">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <button type="button" id="nextImageBtn" class="btn btn-primary position-absolute top-50 end-0 translate-middle-y me-3" style="z-index: 10; opacity: 0.8;">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                        
                        <!-- Auto-play toggle -->
                        <button type="button" id="autoPlayToggle" class="btn btn-secondary position-absolute bottom-0 end-0 m-3" style="z-index: 10; opacity: 0.8;">
                            <i class="bi bi-pause-fill"></i>
                        </button>
                    </div>
                    
                    <!-- Image dots indicator -->
                    <div id="imageIndicators" class="d-flex justify-content-center mt-3 pb-3"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile View Upgrade Modal -->
    <div class="modal fade" id="profileViewUpgradeModal" tabindex="-1" aria-labelledby="profileViewUpgradeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="profileViewUpgradeModalLabel">
                        <i class="bi bi-lock-fill me-2 text-warning"></i>Premium Feature
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="mb-4">
                        <i class="bi bi-image display-1 text-muted"></i>
                    </div>
                    <h4 class="mb-3">View Full Profile Images</h4>
                    <p class="text-muted mb-4">
                        Upgrade to a premium package to view full-size profile images and unlock additional features!
                    </p>
                    <div class="d-grid gap-2">
                        <a href="package.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-arrow-up-circle me-2"></i>Upgrade Now
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
                        <i class="bi bi-person-badge me-2"></i><?php echo htmlspecialchars($user_type); ?> Profile Stats
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
                                            '<i class="bi bi-infinity"></i> Unlimited' : 
                                            '<i class="bi bi-eye"></i> ' . htmlspecialchars($profile_views_limit);
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
                                            '<i class="bi bi-infinity"></i> Unlimited' : 
                                            '<i class="bi bi-heart"></i> ' . htmlspecialchars($interest_limit);
                                        ?>
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Today's Interest Usage -->
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="alert alert-info" style="padding: 12px; font-size: 0.9rem; margin-bottom: 15px;" id="modalInterestUsageAlert">
                        <i class="bi bi-heart-fill me-2"></i>
                        <span id="modalInterestUsageText">Today's Interests: <span id="modalCurrentInterestCount">0</span>/<?php echo $interest_limit === 'Unlimited' ? '∞' : htmlspecialchars($interest_limit); ?></span>
                        <?php if ($interest_limit !== 'Unlimited'): ?>
                        <div class="progress mt-2" style="height: 6px;">
                            <div class="progress-bar bg-danger" role="progressbar" style="width: 0%" id="modalInterestProgressBar"></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Today's Profile Views Usage -->
                    <div class="alert alert-warning" style="padding: 12px; font-size: 0.9rem; margin-bottom: 15px;" id="modalProfileViewsUsageAlert">
                        <i class="bi bi-eye-fill me-2"></i>
                        <span id="modalProfileViewsUsageText">Today's Profile Views: <span id="modalCurrentProfileViewsCount">0</span>/<?php echo $profile_views_limit === 'Unlimited' ? '∞' : htmlspecialchars($profile_views_limit); ?></span>
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
                        <i class="bi bi-x-circle me-2"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>