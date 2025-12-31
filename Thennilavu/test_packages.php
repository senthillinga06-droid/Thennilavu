<?php
// Test script to verify package-based search access implementation

// Database connection
$host = "localhost";
$user = "thennilavu_matrimonial";
$pass = "OYVuiEKfS@FQ";
$db = "thennilavu_thennilavu";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Package-Based Search Access Test</h2>";

// Test 1: Check if packages table exists and has data
echo "<h3>1. Packages Table Test</h3>";
$result = $conn->query("SELECT * FROM packages WHERE status = 'active'");
if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Package ID</th><th>Name</th><th>Price</th><th>Duration (days)</th><th>Search Access</th><th>Matchmaker</th><th>Profile Views</th><th>Interest Limit</th><th>Status</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['package_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['price']) . "</td>";
        echo "<td>" . htmlspecialchars($row['duration_days']) . "</td>";
        echo "<td><strong>" . htmlspecialchars($row['search_access']) . "</strong></td>";
        echo "<td><strong>" . htmlspecialchars($row['matchmaker_enabled']) . "</strong></td>";
        echo "<td><strong>" . htmlspecialchars($row['profile_views_limit']) . "</strong></td>";
        echo "<td><strong>" . htmlspecialchars($row['interest_limit']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>No active packages found. You may need to insert some test data:</p>";
    echo "<pre>";
    echo "INSERT INTO packages (name, price, duration_days, status, search_access, description) VALUES\n";
    echo "('Free User', 0.00, 30, 'active', 'Basic', 'Basic search with limited filters'),\n";
    echo "('Premium', 29.99, 30, 'active', 'Limited', 'Enhanced search with more filters'),\n";
    echo "('Gold', 49.99, 60, 'active', 'Unlimited', 'Full access to all search filters'),\n";
    echo "('Platinum', 99.99, 90, 'active', 'Unlimited', 'Premium package with unlimited access');";
    echo "</pre>";
}

// Test 2: Test search access logic for different package types
echo "<h3>2. Search Access Logic Test</h3>";

$test_packages = [
    ['name' => 'Free User', 'expected_access' => 'Basic'],
    ['name' => 'Premium', 'expected_access' => 'Limited'],
    ['name' => 'Gold', 'expected_access' => 'Unlimited'],
    ['name' => 'Platinum', 'expected_access' => 'Unlimited']
];

foreach ($test_packages as $test) {
    $package_name = $test['name'];
    $expected = $test['expected_access'];
    
    // Simulate the search access logic
    $search_access = 'Basic'; // default
    $matchmaker_enabled = 'No'; // default
    $interest_limit = '5'; // default
    $profile_views_limit = '10'; // default
    
    $stmt = $conn->prepare("SELECT search_access, matchmaker_enabled, interest_limit, profile_views_limit FROM packages WHERE name = ? AND status = 'active' LIMIT 1");
    $stmt->bind_param('s', $package_name);
    $stmt->execute();
    $stmt->bind_result($search_access, $matchmaker_enabled, $interest_limit, $profile_views_limit);
    if (!$stmt->fetch()) {
        $search_access = 'Basic';
        $matchmaker_enabled = 'No';
        $interest_limit = '5';
        $profile_views_limit = '10';
    }
    $stmt->close();
    
    // Define allowed filters based on search access
    $allowed_filters = [];
    if ($search_access === 'Basic') {
        $allowed_filters = ['looking_for', 'marital_status', 'country', 'education'];
    } elseif ($search_access === 'Limited') {
        $allowed_filters = ['looking_for', 'marital_status', 'country', 'education', 'profession', 'caste', 'age', 'religion'];
    } elseif ($search_access === 'Unlimited') {
        $allowed_filters = ['looking_for', 'marital_status', 'religion', 'country', 'profession', 'age', 'education', 'income', 'height', 'weight', 'city', 'caste'];
    }
    
    // Determine horoscope access
    $horoscope_access = ($matchmaker_enabled === 'Yes' && $search_access !== 'Basic');
    
    $status = ($search_access === $expected) ? "✅ PASS" : "❌ FAIL";
    echo "<p><strong>Package:</strong> $package_name | <strong>Access:</strong> $search_access | <strong>Expected:</strong> $expected | <strong>Status:</strong> $status</p>";
    echo "<p><strong>Matchmaker:</strong> $matchmaker_enabled | <strong>Horoscope Access:</strong> " . ($horoscope_access ? 'Yes' : 'No') . "</p>";
    echo "<p><strong>Profile Views:</strong> $profile_views_limit | <strong>Interest Limit:</strong> $interest_limit</p>";
    echo "<p><strong>Allowed Filters:</strong> " . implode(', ', $allowed_filters) . "</p>";
    echo "<hr>";
}

// Test 3: Show how the system would work for a sample user
echo "<h3>3. User Package Test (Simulation)</h3>";
echo "<p>This shows how the system would determine search access for different user scenarios:</p>";

$user_scenarios = [
    ['package' => 'Free User', 'description' => 'User with no active package'],
    ['package' => 'Premium', 'description' => 'User with Premium package'],
    ['package' => 'Gold', 'description' => 'User with Gold package'],
    ['package' => 'Platinum', 'description' => 'User with Platinum package']
];

foreach ($user_scenarios as $scenario) {
    $package_name = $scenario['package'];
    $description = $scenario['description'];
    
    // Get search access
    $search_access = 'Basic';
    $matchmaker_enabled = 'No';
    $interest_limit = '5';
    $profile_views_limit = '10';
    $stmt = $conn->prepare("SELECT search_access, matchmaker_enabled, interest_limit, profile_views_limit FROM packages WHERE name = ? AND status = 'active' LIMIT 1");
    $stmt->bind_param('s', $package_name);
    $stmt->execute();
    $stmt->bind_result($search_access, $matchmaker_enabled, $interest_limit, $profile_views_limit);
    if (!$stmt->fetch()) {
        $search_access = 'Basic';
        $matchmaker_enabled = 'No';
        $interest_limit = '5';
        $profile_views_limit = '10';
    }
    $stmt->close();
    
    // Get allowed filters
    $allowed_filters = [];
    if ($search_access === 'Basic') {
        $allowed_filters = ['looking_for', 'marital_status', 'country', 'education'];
    } elseif ($search_access === 'Limited') {
        $allowed_filters = ['looking_for', 'marital_status', 'country', 'education', 'profession', 'caste', 'age', 'religion'];
    } elseif ($search_access === 'Unlimited') {
        $allowed_filters = ['looking_for', 'marital_status', 'religion', 'country', 'profession', 'age', 'education', 'income', 'height', 'weight', 'city', 'caste'];
    }
    
    // Determine horoscope access
    $horoscope_access = ($matchmaker_enabled === 'Yes' && $search_access !== 'Basic');
    
    echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h4>$description</h4>";
    echo "<p><strong>Package:</strong> $package_name</p>";
    echo "<p><strong>Search Access:</strong> $search_access</p>";
    echo "<p><strong>Matchmaker Enabled:</strong> $matchmaker_enabled</p>";
    echo "<p><strong>Horoscope Access:</strong> " . ($horoscope_access ? '<span style="color: green;">✅ Yes</span>' : '<span style="color: red;">❌ No</span>') . "</p>";
    echo "<div style='background: #f8f9fa; padding: 8px; border-radius: 4px; margin: 8px 0;'>";
    echo "<strong>Package Limits:</strong><br>";
    echo "📧 <strong>Profile Views:</strong> " . ($profile_views_limit === 'Unlimited' ? '♾️ Unlimited' : $profile_views_limit) . " | ";
    echo "💝 <strong>Interest Limit:</strong> " . ($interest_limit === 'Unlimited' ? '♾️ Unlimited' : $interest_limit);
    echo "</div>";
    echo "<p><strong>Available Filters:</strong> " . implode(', ', $allowed_filters) . " (" . count($allowed_filters) . " filters)</p>";
    echo "</div>";
}

// Test 4: Interest Tracking System Test
echo "<h3>4. Interest Tracking System Test</h3>";

// Check if interest tracking tables exist
$interest_tables = ['user_interests', 'user_daily_interest_counts'];
$tables_exist = true;

foreach ($interest_tables as $table) {
    $check_table = $conn->query("SHOW TABLES LIKE '$table'");
    if (!$check_table || $check_table->num_rows === 0) {
        echo "<p style='color: red;'>❌ Table '$table' not found. Please run setup_interests.sql</p>";
        $tables_exist = false;
    } else {
        echo "<p style='color: green;'>✅ Table '$table' exists</p>";
    }
}

if ($tables_exist) {
    // Test interest limits for different packages
    echo "<h4>Interest Limit Testing with Upgrade Scenarios</h4>";
    
    $interest_test_scenarios = [
        ['package' => 'Free User', 'current_interests' => 5, 'limit' => '5', 'test_case' => 'At limit - should show upgrade'],
        ['package' => 'Premium', 'current_interests' => 10, 'limit' => '10', 'test_case' => 'At limit - should show upgrade'],
        ['package' => 'Gold', 'current_interests' => 20, 'limit' => '25', 'test_case' => 'Under limit - should allow'],
        ['package' => 'Platinum', 'current_interests' => 50, 'limit' => 'Unlimited', 'test_case' => 'Unlimited - should always allow']
    ];
    
    foreach ($interest_test_scenarios as $scenario) {
        $package_name = $scenario['package'];
        $current_count = $scenario['current_interests'];
        $limit = $scenario['limit'];
        $test_case = $scenario['test_case'];
        
        // Determine if user can express more interests
        $can_express_interest = true;
        $limit_status = "";
        $upgrade_needed = false;
        
        if ($limit === 'Unlimited') {
            $limit_status = "✅ No limit - Can express unlimited interests";
        } else {
            $limit_value = (int)$limit;
            if ($current_count >= $limit_value) {
                $can_express_interest = false;
                $limit_status = "❌ Limit reached ($current_count/$limit_value) - UPGRADE REQUIRED";
                $upgrade_needed = true;
            } else {
                $limit_status = "✅ Can express more interests ($current_count/$limit_value)";
            }
        }
        
        // Get upgrade options for this package
        $upgrade_suggestions = "";
        if ($upgrade_needed) {
            $upgrade_query = "SELECT name, price, interest_limit FROM packages 
                             WHERE status = 'active' AND name != '$package_name'
                             ORDER BY CASE 
                                 WHEN interest_limit = 'Unlimited' THEN 999 
                                 ELSE CAST(interest_limit AS UNSIGNED) 
                             END ASC, price ASC LIMIT 2";
            $upgrade_result = $conn->query($upgrade_query);
            
            if ($upgrade_result && $upgrade_result->num_rows > 0) {
                $upgrade_suggestions = "<br><strong>🎯 Suggested Upgrades:</strong> ";
                while ($upgrade = $upgrade_result->fetch_assoc()) {
                    $limitText = $upgrade['interest_limit'] === 'Unlimited' ? '∞' : $upgrade['interest_limit'];
                    $upgrade_suggestions .= "<span style='color: #007bff;'>{$upgrade['name']} ({$limitText} interests - Rs.{$upgrade['price']})</span> ";
                }
            }
        }
        
        echo "<div style='border: 1px solid #ccc; padding: 15px; margin: 10px 0; border-radius: 8px; background: " . 
             ($upgrade_needed ? "#fff5f5" : "#f8f9fa") . ";'>";
        echo "<h6><strong>Package:</strong> $package_name | <strong>Test Case:</strong> $test_case</h6>";
        echo "<p><strong>Current Interests:</strong> $current_count | <strong>Limit:</strong> $limit</p>";
        echo "<p><strong>Status:</strong> $limit_status</p>";
        if ($upgrade_needed) {
            echo "<div style='background: #ffe6e6; padding: 10px; border-radius: 5px; border-left: 4px solid #dc3545;'>";
            echo "<strong>💡 Monetization Opportunity:</strong> User hits limit → Show upgrade modal → Drive premium sales!";
            echo $upgrade_suggestions;
            echo "</div>";
        }
        echo "</div>";
    }
    
    // Show current interest activity (if any)
    echo "<h4>Current Interest Activity</h4>";
    $today_interests = $conn->query("
        SELECT uic.user_id, uic.likes_count, u.username 
        FROM user_daily_interest_counts uic 
        LEFT JOIN users u ON uic.user_id = u.id 
        WHERE uic.interest_date = CURDATE() 
        ORDER BY uic.likes_count DESC 
        LIMIT 10
    ");
    
    if ($today_interests && $today_interests->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>User ID</th><th>Username</th><th>Today's Interests</th></tr>";
        while ($row = $today_interests->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['user_id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['username'] ?? 'N/A') . "</td>";
            echo "<td><strong>" . htmlspecialchars($row['likes_count']) . "</strong></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No interest activity recorded for today. This is normal for a new installation.</p>";
    }
    
    // Show total interests in the system
    $total_interests = $conn->query("SELECT COUNT(*) as total FROM user_interests");
    if ($total_interests) {
        $total_count = $total_interests->fetch_assoc()['total'];
        echo "<p><strong>Total interests recorded in system:</strong> $total_count</p>";
    }
}

$conn->close();
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    pre { background-color: #f4f4f4; padding: 10px; border-radius: 5px; }
</style>