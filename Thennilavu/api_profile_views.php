<?php
session_start();

// Database connection
$host = "localhost";
$user = "thennilavu_matrimonial"; 
$pass = "OYVuiEKfS@FQ";     
$db   = "thennilavu_thennilavu";

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
}

header('Content-Type: application/json');

// Get input data
$input = json_decode(file_get_contents('php://input'), true);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $input['action'] ?? '';
$member_id = $input['member_id'] ?? '';

// Validate parameters based on action
if (empty($action)) {
    echo json_encode(['success' => false, 'error' => 'Action is required']);
    exit;
}

// Only require member_id for certain actions
if (in_array($action, ['track_view']) && empty($member_id)) {
    echo json_encode(['success' => false, 'error' => 'Member ID is required for this action']);
    exit;
}

try {
    if ($action === 'track_view') {
        // Get user's package information
        $package_query = "SELECT p.name, p.profile_views_limit 
                         FROM userpackage up 
                         JOIN packages p ON up.status = p.name 
                         WHERE up.user_id = ? AND up.requestPackage = 'accept' AND up.end_date > NOW()
                         ORDER BY up.start_date DESC LIMIT 1";
        $stmt = $conn->prepare($package_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $package_result = $stmt->get_result();
        
        // Set default values for free users
        $package_name = 'Free User';
        $views_limit = '10';
        
        if ($package_result->num_rows > 0) {
            $package = $package_result->fetch_assoc();
            $package_name = $package['name'];
            $views_limit = $package['profile_views_limit'];
        }
        
        // Check if limit is unlimited
        if ($views_limit === 'Unlimited') {
            // Track the view without limit check
            $insert_view_query = "INSERT INTO user_profile_views (viewer_id, viewed_member_id) VALUES (?, ?)";
            $stmt = $conn->prepare($insert_view_query);
            $stmt->bind_param("ii", $user_id, $member_id);
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Profile view tracked',
                'limit_status' => 'unlimited'
            ]);
            exit;
        }
        
        // Get current daily views count
        $count_query = "SELECT views_count FROM user_daily_profile_views 
                       WHERE user_id = ? AND view_date = CURDATE()";
        $stmt = $conn->prepare($count_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $count_result = $stmt->get_result();
        
        $current_views = 0;
        if ($count_result->num_rows > 0) {
            $current_views = $count_result->fetch_assoc()['views_count'];
        }
        
        // Check if user has reached the limit
        if ($current_views >= intval($views_limit)) {
            // Get upgrade options
            $upgrade_query = "SELECT name, price, profile_views_limit FROM packages 
                             WHERE status = 'active' AND profile_views_limit > ? 
                             ORDER BY price ASC LIMIT 3";
            $stmt = $conn->prepare($upgrade_query);
            $stmt->bind_param("s", $views_limit);
            $stmt->execute();
            $upgrade_result = $stmt->get_result();
            
            $upgrade_options = [];
            while ($row = $upgrade_result->fetch_assoc()) {
                $upgrade_options[] = $row;
            }
            
            echo json_encode([
                'success' => false,
                'error' => "You've reached your daily profile views limit of {$views_limit}. Upgrade to view more profiles!",
                'limit_reached' => true,
                'current_count' => $current_views,
                'limit' => $views_limit,
                'package_name' => $package_name,
                'upgrade_options' => $upgrade_options
            ]);
            exit;
        }
        
        // Track the profile view
        $insert_view_query = "INSERT INTO user_profile_views (viewer_id, viewed_member_id) VALUES (?, ?)";
        $stmt = $conn->prepare($insert_view_query);
        $stmt->bind_param("ii", $user_id, $member_id);
        $stmt->execute();
        
        // Update daily views count
        $update_count_query = "INSERT INTO user_daily_profile_views (user_id, view_date, views_count) 
                              VALUES (?, CURDATE(), 1) 
                              ON DUPLICATE KEY UPDATE 
                              views_count = views_count + 1, 
                              updated_at = CURRENT_TIMESTAMP";
        $stmt = $conn->prepare($update_count_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        $new_count = $current_views + 1;
        
        echo json_encode([
            'success' => true,
            'message' => 'Profile view tracked successfully',
            'current_count' => $new_count,
            'limit' => $views_limit,
            'package_name' => $package_name
        ]);
        
    } elseif ($action === 'get_daily_views_count') {
        // Get user's package info to get the limit
        $package_query = "SELECT p.name, p.profile_views_limit 
                         FROM userpackage up 
                         JOIN packages p ON up.status = p.name 
                         WHERE up.user_id = ? AND up.requestPackage = 'accept' AND up.end_date > NOW()
                         ORDER BY up.start_date DESC LIMIT 1";
        $stmt = $conn->prepare($package_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $package_result = $stmt->get_result();
        
        // Set default values for free users
        $package_name = 'Free User';
        $views_limit = '10';
        
        if ($package_result->num_rows > 0) {
            $package = $package_result->fetch_assoc();
            $package_name = $package['name'];
            $views_limit = $package['profile_views_limit'];
        }
        
        // Get current daily views count
        $count_query = "SELECT views_count FROM user_daily_profile_views 
                       WHERE user_id = ? AND view_date = CURDATE()";
        $stmt = $conn->prepare($count_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $count_result = $stmt->get_result();
        
        $current_views = 0;
        if ($count_result->num_rows > 0) {
            $current_views = $count_result->fetch_assoc()['views_count'];
        }
        
        echo json_encode([
            'success' => true,
            'current_count' => $current_views,
            'limit' => $views_limit,
            'package_name' => $package_name
        ]);
        
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>