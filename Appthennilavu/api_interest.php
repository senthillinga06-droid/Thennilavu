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
if (in_array($action, ['express_interest', 'check_status']) && empty($member_id)) {
    echo json_encode(['success' => false, 'error' => 'Member ID is required for this action']);
    exit;
}

try {
    if ($action === 'express_interest') {
        // Get user's package information
        $package_query = "SELECT p.name, p.interest_limit 
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
        $interest_limit = '5'; // Default 5 interests for free users
        
        if ($package_result->num_rows > 0) {
            // User has an active package
            $package = $package_result->fetch_assoc();
            $package_name = $package['name'];
            $interest_limit = $package['interest_limit'];
        }
        
        // Check if interest limit is reached (if not unlimited)
        if ($interest_limit !== 'Unlimited') {
            $count_query = "SELECT likes_count FROM user_daily_interest_counts 
                           WHERE user_id = ? AND interest_date = CURDATE()";
            $stmt = $conn->prepare($count_query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $count_result = $stmt->get_result();
            
            $current_count = 0;
            if ($count_result->num_rows > 0) {
                $current_count = $count_result->fetch_assoc()['likes_count'];
            }
            
            if ($current_count >= $interest_limit) {
                $upgrade_message = $package_name === 'Free User' ? 
                    "You've used all 5 free daily interests! Upgrade to Premium for unlimited interests." :
                    "Daily interest limit reached. Upgrade to Premium for unlimited interests!";
                    
                echo json_encode([
                    'success' => false,
                    'code' => 'LIMIT_REACHED',
                    'error' => "Daily interest limit reached ({$current_count}/{$interest_limit}). " . $upgrade_message,
                    'show_upgrade' => true,
                    'upgrade_message' => $upgrade_message,
                    'current_count' => $current_count,
                    'limit' => $interest_limit,
                    'package_name' => $package_name
                ]);
                exit;
            }
        }
        
        // NOTE: allow repeated interests from same user (multiple likes allowed)
        // Previously we prevented duplicates; remove that check so each click inserts a new record
        
        // Add interest - try inserting into the unique table first
        $insert_query = "INSERT INTO user_interests (user_id, target_member_id, interest_date) VALUES (?, ?, CURDATE())";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("ii", $user_id, $member_id);

        $executed = false;
        try {
            $stmt->execute();
            $executed = true;
        } catch (mysqli_sql_exception $e) {
            // Duplicate entry for unique_interest -> allow repeated likes by recording events
            if ($e->getCode() == 1062) {
                // Ensure events table exists
                $conn->query("CREATE TABLE IF NOT EXISTS user_interest_events (
                    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    target_member_id INT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

                try {
                    $evtInsert = $conn->prepare("INSERT INTO user_interest_events (user_id, target_member_id) VALUES (?, ?)");
                    $evtInsert->bind_param("ii", $user_id, $member_id);
                    $evtInsert->execute();
                    $executed = true;
                    $evtInsert->close();
                } catch (Exception $e2) {
                    // If event insert also fails, leave executed=false and fall through
                }
            } else {
                // rethrow unexpected DB exception so the outer try/catch can handle it
                throw $e;
            }
        }

        if ($executed) {
            // Update daily count
            $update_count = "INSERT INTO user_daily_interest_counts (user_id, interest_date, likes_count) 
                            VALUES (?, CURDATE(), 1) 
                            ON DUPLICATE KEY UPDATE likes_count = likes_count + 1";
            $stmt = $conn->prepare($update_count);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            // Get updated count
            $count_query = "SELECT likes_count FROM user_daily_interest_counts 
                           WHERE user_id = ? AND interest_date = CURDATE()";
            $stmt = $conn->prepare($count_query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $new_count = $stmt->get_result()->fetch_assoc()['likes_count'] ?? 1;
            
            echo json_encode([
                'success' => true, 
                'message' => 'Interest expressed successfully',
                'current_count' => $new_count,
                'limit' => $interest_limit
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to express interest']);
        }
        
    } elseif ($action === 'remove_interest') {
        // Remove interest is disabled
        echo json_encode(['success' => false, 'error' => 'Removing interest is not allowed']);
        
    } elseif ($action === 'check_status') {
        // Check if interest exists
        $check_query = "SELECT id FROM user_interests WHERE user_id = ? AND target_member_id = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("ii", $user_id, $member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        echo json_encode([
            'success' => true,
            'has_interest' => $result->num_rows > 0
        ]);
        
    } elseif ($action === 'get_daily_count') {
        // Get current daily interest count for the user
        
        // First get user's package info to get the limit
        $package_query = "SELECT p.name, p.interest_limit 
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
        $interest_limit = '5';
        
        if ($package_result->num_rows > 0) {
            $package = $package_result->fetch_assoc();
            $package_name = $package['name'];
            $interest_limit = $package['interest_limit'];
        }
        
        // Get current daily count
        $count_query = "SELECT likes_count FROM user_daily_interest_counts 
                       WHERE user_id = ? AND interest_date = CURDATE()";
        $stmt = $conn->prepare($count_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $count_result = $stmt->get_result();
        
        $current_count = 0;
        if ($count_result->num_rows > 0) {
            $current_count = $count_result->fetch_assoc()['likes_count'];
        }
        
        echo json_encode([
            'success' => true,
            'current_count' => $current_count,
            'limit' => $interest_limit,
            'package_name' => $package_name
        ]);
        
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
