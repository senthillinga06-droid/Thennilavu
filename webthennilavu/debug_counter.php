<!DOCTYPE html>
<html>
<head>
    <title>Interest Counter Debug</title>
</head>
<body>
    <h1>Interest Counter Debug Test</h1>
    
    <div>
        <p>Session User ID: <?php session_start(); echo $_SESSION['user_id'] ?? 'Not logged in'; ?></p>
        <p>Today's Interests: <span id="currentInterestCount">0</span>/5</p>
        <div class="progress" style="height: 20px;">
            <div class="progress-bar bg-success" id="interestProgressBar" style="width: 0%"></div>
        </div>
    </div>
    
    <button onclick="testAPI()">Test API Call</button>
    <div id="results"></div>
    
    <script>
        function testAPI() {
            console.log('Testing API call...');
            document.getElementById('results').innerHTML = 'Loading...';
            
            fetch('api_interest.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_daily_count'
                })
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text();
            })
            .then(text => {
                console.log('Raw response:', text);
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed data:', data);
                    document.getElementById('results').innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                    
                    if (data.success) {
                        document.getElementById('currentInterestCount').textContent = data.current_count;
                        const percentage = (data.current_count / parseInt(data.limit)) * 100;
                        document.getElementById('interestProgressBar').style.width = percentage + '%';
                    }
                } catch (e) {
                    console.error('JSON parse error:', e);
                    document.getElementById('results').innerHTML = 'Error: ' + text;
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                document.getElementById('results').innerHTML = 'Network error: ' + error.message;
            });
        }
        
        // Auto-test on page load
        window.onload = testAPI;
    </script>
</body>
</html>