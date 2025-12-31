<?php
// config.php
ob_start();
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'thennilavu_matrimonial');
define('DB_PASS', 'OYVuiEKfS@FQ');
define('DB_NAME', 'thennilavu_thennilavu');


// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Authentication check
function checkAuth() {
    if (!isset($_SESSION['staff_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Get dashboard statistics
function getDashboardStats($conn) {
    $stats = [];
    
    // Total Members
    $res1 = $conn->query("SELECT COUNT(*) as cnt FROM members");
    $stats['total_members'] = $res1->fetch_assoc()['cnt'];
    
    // Active Packages
    $res2 = $conn->query("SELECT COUNT(*) as cnt FROM packages WHERE status='active'");
    $stats['active_packages'] = $res2->fetch_assoc()['cnt'];
    
    // Today's Transactions
    $res3 = $conn->query("SELECT COUNT(*) as cnt FROM userpackage WHERE DATE(start_date) = CURDATE() AND requestPackage = 'accept'");
    $stats['recent_transactions'] = $res3 ? $res3->fetch_assoc()['cnt'] : 0;
    
    // Recent Members
    $res4 = $conn->query("SELECT name, created_at FROM members ORDER BY created_at DESC LIMIT 5");
    $stats['recent_members'] = [];
    while ($row = $res4->fetch_assoc()) {
        $stats['recent_members'][] = $row;
    }
    
    return $stats;
}
?>