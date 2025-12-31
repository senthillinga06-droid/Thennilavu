<?php
ob_start();
session_start();
if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

$name = $_SESSION['name'];
$type = ucfirst($_SESSION['role']); // Admin/Staff
$isAdmin = ($_SESSION['role'] === 'admin');

// DB connection
$host = "localhost";
$user = "thennilavu_matrimonial";
$pass = "OYVuiEKfS@FQ";
$db = "thennilavu_thennilavu";
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get statistics from database
$total_members = 0;
$premium_members = 0;
$revenue = 0;
$successful_matches = 0;
$recent_members = [];
$recent_transactions = [];

// Total Members
$res1 = $conn->query("SELECT COUNT(*) as cnt FROM members");
if ($res1) $total_members = $res1->fetch_assoc()['cnt'];

// Premium Members
$res2 = $conn->query("SELECT COUNT(*) as cnt FROM members WHERE membership_type = 'premium'");
if ($res2) $premium_members = $res2->fetch_assoc()['cnt'];

// Revenue (this month)
$res3 = $conn->query("SELECT SUM(amount) as total FROM transactions WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
if ($res3) {
    $revenue_data = $res3->fetch_assoc();
    $revenue = $revenue_data['total'] ? $revenue_data['total'] : 0;
}

// Successful Matches (estimated)
$res4 = $conn->query("SELECT COUNT(*) as cnt FROM matches WHERE status = 'successful'");
if ($res4) $successful_matches = $res4->fetch_assoc()['cnt'];

// Recent Members
$res5 = $conn->query("SELECT name, created_at FROM members ORDER BY created_at DESC LIMIT 5");
if ($res5) {
    while ($row = $res5->fetch_assoc()) {
        $recent_members[] = $row;
    }
}

// Recent Transactions
$res6 = $conn->query("SELECT t.amount, p.name as package_name, m.name as member_name, t.created_at 
                     FROM transactions t 
                     JOIN packages p ON t.package_id = p.id 
                     JOIN members m ON t.member_id = m.id 
                     ORDER BY t.created_at DESC LIMIT 5");
if ($res6) {
    while ($row = $res6->fetch_assoc()) {
        $recent_transactions[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard - Matrimony Admin Panel</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --primary: #4f46e5;
      --primary-dark: #4338ca;
      --secondary: #ec4899;
      --success: #10b981;
      --warning: #f59e0b;
      --danger: #ef4444;
      --light: #f8fafc;
      --dark: #1e293b;
      --gray: #64748b;
      --gray-light: #cbd5e1;
      --sidebar-width: 260px;
      --header-height: 70px;
      --border-radius: 12px;
      --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
      --transition: all 0.3s ease;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', sans-serif;
      background-color: #f1f5f9;
      color: var(--dark);
      line-height: 1.6;
    }

    /* Top Navigation */
    .top-nav {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      height: var(--header-height);
      background: white;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 2rem;
      box-shadow: var(--shadow);
      z-index: 100;
    }

    .logo-container {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .logo-circle {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: bold;
      font-size: 18px;
    }

    .matrimony-name-top {
      font-weight: 700;
      color: var(--primary);
      font-size: 1.25rem;
    }

    .nav-right {
      display: flex;
      align-items: center;
      gap: 1.5rem;
    }

    .main-menu {
      display: flex;
      gap: 1.5rem;
    }

    .logout {
      color: var(--danger);
      text-decoration: none;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transition: var(--transition);
    }

    .logout:hover {
      color: var(--primary);
    }

    .user-info {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
    }

    .user-type {
      background: rgba(79, 70, 229, 0.1);
      color: var(--primary);
      padding: 0.25rem 0.75rem;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
    }

    .username {
      font-weight: 600;
      color: var(--dark);
    }

    /* Dashboard Layout */
    .dashboard-layout {
      display: flex;
      margin-top: var(--header-height);
      min-height: calc(100vh - var(--header-height));
    }

    /* Sidebar */
    .sidebar {
      width: var(--sidebar-width);
      background: white;
      height: calc(100vh - var(--header-height));
      position: fixed;
      left: 0;
      top: var(--header-height);
      overflow-y: auto;
      padding: 1.5rem 0;
      box-shadow: var(--shadow);
      transition: var(--transition);
      z-index: 90;
    }

    .matrimony-name {
      padding: 0 1.5rem 1.5rem;
      font-weight: 700;
      color: var(--primary);
      font-size: 1.1rem;
      border-bottom: 1px solid var(--gray-light);
      margin-bottom: 1rem;
    }

    .sidebar-menu {
      list-style: none;
      padding: 0 1rem;
    }

    .sidebar-link {
      display: flex;
      align-items: center;
      padding: 0.875rem 1rem;
      color: var(--gray);
      text-decoration: none;
      border-radius: var(--border-radius);
      margin-bottom: 0.5rem;
      transition: var(--transition);
      font-weight: 500;
    }

    .sidebar-link:hover {
      background-color: #f1f5f9;
      color: var(--primary);
    }

    .sidebar-link.active {
      background-color: var(--primary);
      color: white;
    }

    .sidebar-link i {
      margin-right: 0.75rem;
      font-size: 1.1rem;
      width: 24px;
      text-align: center;
    }

    /* Main Content */
    .main-content {
      flex: 1;
      padding: 2rem;
      margin-left: var(--sidebar-width);
    }

    /* Dashboard Overview */
    .dashboard-overview {
      margin-bottom: 2rem;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }

    .stat-card {
      background: white;
      border-radius: var(--border-radius);
      padding: 1.5rem;
      box-shadow: var(--shadow);
      display: flex;
      position: relative;
      overflow: hidden;
    }

    .stat-icon {
      width: 60px;
      height: 60px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 1rem;
      background: rgba(79, 70, 229, 0.1);
      color: var(--primary);
      font-size: 1.5rem;
    }

    .stat-content {
      flex: 1;
    }

    .stat-content h3 {
      font-size: 0.9rem;
      font-weight: 500;
      color: var(--gray);
      margin-bottom: 0.5rem;
    }

    .stat-number {
      font-size: 1.75rem;
      font-weight: 700;
      color: var(--dark);
      margin-bottom: 0.5rem;
    }

    .stat-change {
      font-size: 0.8rem;
      font-weight: 500;
    }

    .stat-change.positive {
      color: var(--success);
    }

    .revenue-stat .stat-icon {
      background: rgba(16, 185, 129, 0.1);
      color: var(--success);
    }

    /* Quick Actions */
    .quick-actions-section {
      background: white;
      border-radius: var(--border-radius);
      padding: 1.5rem;
      box-shadow: var(--shadow);
      margin-bottom: 2rem;
    }

    .quick-actions-section h2 {
      margin-bottom: 1.5rem;
      font-size: 1.25rem;
      color: var(--dark);
    }

    .top-actions {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 1rem;
    }

    .action-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      padding: 0.875rem 1rem;
      background: var(--primary);
      color: white;
      border: none;
      border-radius: var(--border-radius);
      font-weight: 500;
      cursor: pointer;
      transition: var(--transition);
    }

    .action-btn:hover {
      background: var(--primary-dark);
    }

    /* Search Section */
    .search-section {
      background: white;
      border-radius: var(--border-radius);
      padding: 1.5rem;
      box-shadow: var(--shadow);
      margin-bottom: 2rem;
    }

    .search-bar {
      position: relative;
      max-width: 500px;
    }

    .search-bar i {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--gray);
    }

    .search-bar input {
      width: 100%;
      padding: 0.875rem 1rem 0.875rem 2.5rem;
      border: 1px solid var(--gray-light);
      border-radius: var(--border-radius);
      font-size: 1rem;
    }

    .search-bar input:focus {
      outline: none;
      border-color: var(--primary);
    }

    /* Recent Activity */
    .recent-activity-section {
      background: white;
      border-radius: var(--border-radius);
      padding: 1.5rem;
      box-shadow: var(--shadow);
      margin-bottom: 2rem;
    }

    .recent-activity-section h2 {
      margin-bottom: 1.5rem;
      font-size: 1.25rem;
      color: var(--dark);
    }

    .activity-tables-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
      gap: 1.5rem;
    }

    .activity-table-section {
      background: #f8fafc;
      border-radius: var(--border-radius);
      padding: 1.5rem;
    }

    .activity-table-section h3 {
      margin-bottom: 1rem;
      font-size: 1.1rem;
      color: var(--dark);
    }

    /* Tables */
    .data-table {
      width: 100%;
      border-collapse: collapse;
    }

    .data-table th {
      background: #f1f5f9;
      padding: 0.875rem;
      text-align: left;
      font-weight: 600;
      color: var(--dark);
    }

    .data-table td {
      padding: 0.875rem;
      border-bottom: 1px solid var(--gray-light);
    }

    .data-table tr:last-child td {
      border-bottom: none;
    }

    /* Buttons */
    .popup-btn, .publish-btn, .delete-btn {
      padding: 0.5rem 0.875rem;
      border: none;
      border-radius: 6px;
      font-size: 0.875rem;
      font-weight: 500;
      cursor: pointer;
    }

    .popup-btn {
      background: var(--primary);
      color: white;
    }

    .publish-btn {
      background: var(--success);
      color: white;
      margin-right: 0.5rem;
    }

    .delete-btn {
      background: var(--danger);
      color: white;
    }

    /* Modals */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
      background-color: white;
      margin: 5% auto;
      border-radius: var(--border-radius);
      width: 90%;
      max-width: 1000px;
      max-height: 80vh;
      overflow-y: auto;
      box-shadow: var(--shadow);
    }

    .modal-header {
      padding: 1.5rem;
      border-bottom: 1px solid var(--gray-light);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .modal-header h2 {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .close {
      color: var(--gray);
      font-size: 1.5rem;
      font-weight: bold;
      cursor: pointer;
    }

    .close:hover {
      color: var(--dark);
    }

    .modal-body {
      padding: 1.5rem;
    }

    /* Responsive Design */
    @media (max-width: 992px) {
      .sidebar {
        transform: translateX(-100%);
      }
      
      .sidebar.active {
        transform: translateX(0);
      }
      
      .main-content {
        margin-left: 0;
      }
      
      .menu-toggle {
        display: block;
      }
    }

    @media (max-width: 768px) {
      .top-nav {
        padding: 0 1rem;
      }
      
      .main-content {
        padding: 1rem;
      }
      
      .stats-grid, .top-actions, .activity-tables-grid {
        grid-template-columns: 1fr;
      }
      
      .matrimony-name-top {
        display: none;
      }
    }

    /* Menu Toggle (for mobile) */
    .menu-toggle {
      display: none;
      background: none;
      border: none;
      font-size: 1.5rem;
      color: var(--dark);
      cursor: pointer;
    }

    @media (max-width: 992px) {
      .menu-toggle {
        display: block;
      }
    }

    /* Hide staff management for non-admin users */
    <?php if (!$isAdmin) : ?>
    #staffLink {
      display: none;
    }
    <?php endif; ?>
  </style>
</head>
<body>
  <!-- Top Navigation -->
  <header class="top-nav">
    <div class="logo-container">
      <button class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
      </button>
      <div class="logo-circle">M</div>
      <div class="matrimony-name-top">Matrimony Admin</div>
    </div>
    
    <div class="nav-right">
      <nav class="main-menu">
        <a href="logout.php" class="logout">
          <i class="fas fa-sign-out-alt"></i> Logout
        </a>
      </nav>
      <div class="user-info">
        <span class="user-type"><?= htmlspecialchars($type) ?></span>
        <span class="username"><?= htmlspecialchars($name) ?></span>
      </div>
    </div>
  </header>

  <div class="dashboard-layout">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
      <div class="matrimony-name">Matrimony Admin Panel</div>
      <ul class="sidebar-menu">
        <li><a href="index.php" class="sidebar-link active"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="members.php" class="sidebar-link"><i class="fas fa-users"></i> Manage Members</a></li>
        <li><a href="call-management.php" class="sidebar-link"><i class="fas fa-phone"></i> Call Management</a></li>
        <li><a href="user-message-management.php" class="sidebar-link"><i class="fas fa-comments"></i> User Messages</a></li>
        <li><a href="review-management.php" class="sidebar-link"><i class="fas fa-star"></i> Review Management</a></li>
        <li><a href="transaction-management.php" class="sidebar-link"><i class="fas fa-receipt"></i> Transactions</a></li>
        <li><a href="packages-management.php" class="sidebar-link"><i class="fas fa-box"></i> Packages</a></li>
        <li><a href="blog-management.php" class="sidebar-link"><i class="fas fa-blog"></i> Blog Management</a></li>
        <li><a href="total-earnings.php" class="sidebar-link"><i class="fas fa-chart-line"></i> Total Earnings</a></li>
        <li><a id="staffLink" href="staff.php" class="sidebar-link"><i class="fas fa-user-shield"></i> Staff Management</a></li>
      </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
      <!-- Dashboard Overview Section -->
      <div class="dashboard-overview">
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-icon">
              <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
              <h3>Total Members</h3>
              <p class="stat-number"><?= $total_members ?></p>
              <span class="stat-change positive">+12% this month</span>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-icon">
              <i class="fas fa-crown"></i>
            </div>
            <div class="stat-content">
              <h3>Premium Members</h3>
              <p class="stat-number"><?= $premium_members ?></p>
              <span class="stat-change positive">+8% this month</span>
            </div>
          </div>
          <div class="stat-card revenue-stat">
            <div class="stat-icon">
              <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-content">
              <h3>Revenue</h3>
              <p class="stat-number">$<?= number_format($revenue, 2) ?></p>
              <span class="stat-change positive">+15% this month</span>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-icon">
              <i class="fas fa-heart"></i>
            </div>
            <div class="stat-content">
              <h3>Successful Matches</h3>
              <p class="stat-number"><?= $successful_matches ?></p>
              <span class="stat-change positive">+5% this month</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Actions Section -->
      <div class="quick-actions-section">
        <h2>Quick Actions</h2>
        <div class="top-actions">
          <button class="action-btn" id="newMemberBtn"><i class="fas fa-plus"></i> New Member</button>
          <button class="action-btn" id="freeMemberBtn"><i class="fas fa-user"></i> Free Member</button>
          <button class="action-btn" id="paidMemberBtn"><i class="fas fa-crown"></i> Paid Member</button>
          <button class="action-btn" id="viewReportsBtn"><i class="fas fa-chart-bar"></i> View Reports</button>
          <button class="action-btn" id="settingsBtn"><i class="fas fa-cog"></i> Settings</button>
        </div>
      </div>

      <!-- Search Section -->
      <div class="search-section">
        <div class="search-bar">
          <i class="fas fa-search"></i>
          <input type="text" placeholder="Search member"/>
        </div>
      </div>

      <!-- Recent Activity Section -->
      <div class="recent-activity-section">
        <h2>Recent Activity</h2>
        <div class="activity-tables-grid">
          <div class="activity-table-section">
            <h3>Recent Members</h3>
            <table class="data-table activity-table">
              <thead>
                <tr>
                  <th>Member Name</th>
                  <th>Join Date</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($recent_members)): ?>
                  <?php foreach ($recent_members as $member): ?>
                    <tr>
                      <td><?= htmlspecialchars($member['name']) ?></td>
                      <td><?= date('M j, Y', strtotime($member['created_at'])) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="2" style="text-align: center;">No recent members</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          
          <div class="activity-table-section">
            <h3>Recent Transactions</h3>
            <table class="data-table activity-table">
              <thead>
                <tr>
                  <th>Member</th>
                  <th>Package</th>
                  <th>Amount</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($recent_transactions)): ?>
                  <?php foreach ($recent_transactions as $transaction): ?>
                    <tr>
                      <td><?= htmlspecialchars($transaction['member_name']) ?></td>
                      <td><?= htmlspecialchars($transaction['package_name']) ?></td>
                      <td>$<?= number_format($transaction['amount'], 2) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="3" style="text-align: center;">No recent transactions</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

   

      
    </main>
  </div>

  <!-- Modals would go here (same as in your original code) -->

  <script>
    // Toggle sidebar on mobile
    document.getElementById('menuToggle').addEventListener('click', function() {
      document.getElementById('sidebar').classList.toggle('active');
    });

    // Check authentication
    document.addEventListener('DOMContentLoaded', function() {
      // Use PHP session status for authentication check
      const isLoggedIn = <?= isset($_SESSION['staff_id']) ? 'true' : 'false' ?>;
      
      if (!isLoggedIn) {
        window.location.href = 'login.php';
      }
      
      // Hide staff management for non-admin users
      const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
      if (!isAdmin) {
        document.getElementById('staffLink').style.display = 'none';
      }

      // Setup button functionality
      setupModalButtons();
    });

    function setupModalButtons() {
      // Setup all modal buttons here
      // This would include the same JavaScript from your original code
      // for handling the modals for New Member, Free Member, etc.
      
      console.log('Modal buttons setup would go here');
    }
  </script>
</body>
</html>