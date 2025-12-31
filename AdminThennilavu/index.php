<?php
session_start();
require_once 'verify_session.php';  // Add this line
// index.php
require_once 'header.php';

// Get dashboard statistics
$stats = getDashboardStats($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Matrimony Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fb;
            color: #333;
            line-height: 1.6;
        }

        /* Main Content Area */
        main {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        /* Welcome Section */
        .welcome-section {
            margin-top: 80px;
            margin-bottom: 30px;
        }

        .welcome-section h1 {
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .welcome-section p {
            color: #7f8c8d;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }

        /* Quick Stats */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            position: relative;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-card h3 {
            font-size: 1rem;
            color: #7f8c8d;
            margin-bottom: 10px;
            font-weight: 500;
        }

        .stat-card p {
            font-size: 2.2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0;
        }

        .stat-icon {
            position: absolute;
            top: 25px;
            right: 25px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
            font-size: 1.5rem;
        }

        /* Activity Section */
        .activity-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 1.4rem;
            color: #2c3e50;
            font-weight: 600;
        }

        .view-all {
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .view-all:hover {
            color: #2980b9;
        }

        /* Activity List */
        .activity-list {
            list-style: none;
        }

        .activity-item {
            display: flex;
            padding: 15px 0;
            border-bottom: 1px solid #ecf0f1;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .activity-content {
            flex-grow: 1;
        }

        .activity-title {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .activity-time {
            font-size: 0.85rem;
            color: #95a5a6;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            main {
                margin-left: 0;
                padding: 15px;
            }
            
            .welcome-section {
                margin-top: 70px;
            }
            
            .quick-stats {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .welcome-section h1 {
                font-size: 1.7rem;
            }
            
            .quick-stats {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .stat-card p {
                font-size: 1.8rem;
            }
            
            .activity-section {
                padding: 20px;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .view-all {
                margin-top: 10px;
            }
        }

        @media (max-width: 576px) {
            .welcome-section h1 {
                font-size: 1.5rem;
            }
            
            .welcome-section p {
                font-size: 1rem;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
            }
            
            .activity-item {
                flex-direction: column;
            }
            
            .activity-icon {
                margin-bottom: 10px;
                margin-right: 0;
            }
        }
    </style>
</head>
<body>

<div class="welcome-section">
    <h1>Welcome back, <?= htmlspecialchars($name) ?>!</h1>
    <p>Here's what's happening with your matrimony platform today.</p>
    
    <div class="quick-stats">
        <div class="stat-card">
            <h3>Total Members</h3>
            <p><?= $stats['total_members'] ?></p>
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
        </div>
        
        <div class="stat-card">
            <h3>Active Packages</h3>
            <p><?= $stats['active_packages'] ?></p>
            <div class="stat-icon">
                <i class="fas fa-gift"></i>
            </div>
        </div>
        
        <div class="stat-card">
            <h3>Today's Transactions</h3>
            <p><?= $stats['recent_transactions'] ?></p>
            <div class="stat-icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
        </div>
    </div>
</div>

<div class="activity-section">
    <div class="section-header">
        <h2 class="section-title">Recent Member Registrations</h2>
        <a href="members.php" class="view-all">View All</a>
    </div>
    
    <ul class="activity-list">
        <?php if (!empty($stats['recent_members'])): ?>
            <?php foreach ($stats['recent_members'] as $member): ?>
                <li class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">New member registered: <?= htmlspecialchars($member['name']) ?></div>
                        <div class="activity-time"><?= date('M j, Y g:i A', strtotime($member['created_at'])) ?></div>
                    </div>
                </li>
            <?php endforeach; ?>
        <?php else: ?>
            <li class="activity-item">
                <div class="activity-content">
                    <div class="activity-title">No recent member registrations</div>
                </div>
            </li>
        <?php endif; ?>
    </ul>
</div>

</main>

<script>
    // Toggle sidebar on mobile
    document.getElementById('menuToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
    });

    // Check authentication
    document.addEventListener('DOMContentLoaded', function() {
        // Check if user is logged in
        const isLoggedIn = <?= isset($_SESSION['staff_id']) ? 'true' : 'false' ?>;
        
        if (!isLoggedIn) {
            window.location.href = 'login.php';
        }
        
        // Hide staff management for non-admin users
        const userRole = "<?= $_SESSION['role'] ?>";
        if (userRole !== 'admin') {
            document.getElementById('staffLink').style.display = 'none';
        }
    });
</script>

</body>
</html>