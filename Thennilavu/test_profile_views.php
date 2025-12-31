<!DOCTYPE html>
<html>
<head>
    <title>Profile Views System Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .test-section { background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 8px; }
        .result { background: #d4edda; padding: 10px; margin: 10px 0; border-radius: 5px; }
        button { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin: 5px; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Profile Views System Test</h1>
        
        <?php
        session_start();
        if (!isset($_SESSION['user_id'])) {
            echo "<p style='color: red;'>Please log in to test the profile views system.</p>";
            echo "<a href='login.php'>Login Here</a>";
            exit;
        }
        
      $host = "localhost";
$user = "thennilavu_matrimonial";
$pass = "OYVuiEKfS@FQ";
$db = "thennilavu_thennilavu";

        $conn = new mysqli($host, $user, $pass, $db);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        
        echo "<p><strong>Logged in as User ID:</strong> " . $_SESSION['user_id'] . "</p>";
        
        // Get user's package info
        $package_query = "SELECT p.name, p.profile_views_limit 
                         FROM userpackage up 
                         JOIN packages p ON up.status = p.name 
                         WHERE up.user_id = ? AND up.requestPackage = 'accept' AND up.end_date > NOW()
                         ORDER BY up.end_date DESC LIMIT 1";
        $stmt = $conn->prepare($package_query);
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $package = $result->fetch_assoc();
            echo "<p><strong>Package:</strong> " . $package['name'] . " (Limit: " . $package['profile_views_limit'] . " views/day)</p>";
        } else {
            echo "<p><strong>Package:</strong> Free User (Limit: 10 views/day)</p>";
        }
        
        // Get current daily count
        $count_query = "SELECT views_count FROM user_daily_profile_views 
                       WHERE user_id = ? AND view_date = CURDATE()";
        $stmt = $conn->prepare($count_query);
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $count_result = $stmt->get_result();
        
        $current_views = 0;
        if ($count_result->num_rows > 0) {
            $current_views = $count_result->fetch_assoc()['views_count'];
        }
        
        echo "<p><strong>Today's Profile Views:</strong> " . $current_views . "</p>";
        ?>
        
        <div class="test-section">
            <h3>Test API Endpoints</h3>
            
            <button onclick="testGetCount()">Get Daily Views Count</button>
            <button onclick="testTrackView()">Test Track View (Member ID: 1)</button>
            <button onclick="testTrackView(2)">Test Track View (Member ID: 2)</button>
            <button onclick="simulateMultipleViews()">Simulate Multiple Views</button>
            
            <div id="results"></div>
        </div>
        
        <div class="test-section">
            <h3>Database Tables Check</h3>
            <?php
            // Check if tables exist
            $tables = ['user_daily_profile_views', 'user_profile_views'];
            foreach ($tables as $table) {
                $result = $conn->query("SHOW TABLES LIKE '$table'");
                if ($result->num_rows > 0) {
                    echo "<p style='color: green;'>✅ Table '$table' exists</p>";
                    
                    // Show table structure
                    $result = $conn->query("DESCRIBE $table");
                    echo "<details><summary>Table structure</summary><pre>";
                    while ($row = $result->fetch_assoc()) {
                        echo $row['Field'] . " (" . $row['Type'] . ")\n";
                    }
                    echo "</pre></details>";
                } else {
                    echo "<p style='color: red;'>❌ Table '$table' not found</p>";
                }
            }
            ?>
        </div>
    </div>

    <script>
        function testGetCount() {
            showResult('Testing get daily views count...');
            
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
                showResult('Get Count Result: ' + JSON.stringify(data, null, 2));
            })
            .catch(error => {
                showResult('Error: ' + error.message);
            });
        }
        
        function testTrackView(memberId = 1) {
            showResult('Testing track view for member ' + memberId + '...');
            
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
            .then(data => {
                showResult('Track View Result: ' + JSON.stringify(data, null, 2));
                if (data.success) {
                    // Refresh page to update count display
                    setTimeout(() => window.location.reload(), 1000);
                }
            })
            .catch(error => {
                showResult('Error: ' + error.message);
            });
        }
        
        function simulateMultipleViews() {
            showResult('Simulating multiple profile views...');
            
            for (let i = 1; i <= 5; i++) {
                setTimeout(() => {
                    testTrackView(i);
                }, i * 500);
            }
        }
        
        function showResult(message) {
            const results = document.getElementById('results');
            const div = document.createElement('div');
            div.className = 'result';
            div.innerHTML = '<pre>' + message + '</pre>';
            results.appendChild(div);
            results.scrollTop = results.scrollHeight;
        }
    </script>
</body>
</html>