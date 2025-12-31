<?php
ob_start();
session_start();
if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

$name = $_SESSION['name'];
$type = ucfirst($_SESSION['role']);
$isAdmin = ($_SESSION['role'] === 'admin');

// Database connection
$host = "localhost";
$user = "thennilavu_matrimonial";
$pass = "OYVuiEKfS@FQ";
$db = "thennilavu_thennilavu";
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize message variables
$message = '';
$message_type = '';

// Process approval action
if (isset($_GET['approve']) && is_numeric($_GET['approve'])) {
    $id = intval($_GET['approve']);
    
    // Verify the request exists and is pending
    $check_sql = "SELECT id FROM package_requests WHERE id = ? AND status = 'pending'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Update the request status
        $update_sql = "UPDATE package_requests SET status='approved', approved_at=NOW() WHERE id=?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $id);
        
        if ($update_stmt->execute()) {
            $message = "Package request approved successfully!";
            $message_type = "success";
        } else {
            $message = "Error approving request: " . $update_stmt->error;
            $message_type = "error";
        }
        $update_stmt->close();
    } else {
        $message = "Invalid request or request already processed";
        $message_type = "error";
    }
    $check_stmt->close();
    
    // Refresh the page to show updated data
    header('Location: package-requests.php?message=' . urlencode($message) . '&type=' . $message_type);
    exit;
}

// Process rejection action
if (isset($_GET['reject']) && is_numeric($_GET['reject'])) {
    $id = intval($_GET['reject']);
    
    // Verify the request exists and is pending
    $check_sql = "SELECT id FROM package_requests WHERE id = ? AND status = 'pending'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Update the request status
        $update_sql = "UPDATE package_requests SET status='rejected', approved_at=NOW() WHERE id=?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $id);
        
        if ($update_stmt->execute()) {
            $message = "Package request rejected successfully!";
            $message_type = "success";
        } else {
            $message = "Error rejecting request: " . $update_stmt->error;
            $message_type = "error";
        }
        $update_stmt->close();
    } else {
        $message = "Invalid request or request already processed";
        $message_type = "error";
    }
    $check_stmt->close();
    
    // Refresh the page to show updated data
    header('Location: package-requests.php?message=' . urlencode($message) . '&type=' . $message_type);
    exit;
}

// Check for message in URL parameters
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = urldecode($_GET['message']);
    $message_type = $_GET['type'];
}

// Fetch all package requests
$sql = "SELECT pr.id, m.name as user_name, p.name as package_name, p.price, p.duration_days, 
               pr.status, pr.requested_at, pr.approved_at 
        FROM package_requests pr 
        JOIN members m ON pr.user_id = m.user_id 
        JOIN packages p ON pr.package_id = p.package_id 
        ORDER BY pr.requested_at DESC";
$result = $conn->query($sql);

if (!$result) {
    die("SQL Error: " . $conn->error);
}

// Get statistics
$total_requests = 0;
$pending_requests = 0;
$approved_requests = 0;

$stats_sql = "SELECT status, COUNT(*) as count FROM package_requests GROUP BY status";
$stats_result = $conn->query($stats_sql);

if ($stats_result) {
    while ($row = $stats_result->fetch_assoc()) {
        $total_requests += $row['count'];
        if ($row['status'] == 'pending') $pending_requests = $row['count'];
        if ($row['status'] == 'approved') $approved_requests = $row['count'];
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Package Requests - Matrimony Admin Panel</title>
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

    /* Content Section */
    .content-section {
      background: white;
      border-radius: var(--border-radius);
      padding: 1.5rem;
      margin-bottom: 2rem;
      box-shadow: var(--shadow);
    }

    .content-section h2 {
      margin-bottom: 1.5rem;
      font-size: 1.5rem;
      color: var(--dark);
    }

    /* Stats Grid */
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
      font-size: 1.5rem;
    }

    .stat-icon.requests {
      background: rgba(79, 70, 229, 0.1);
      color: var(--primary);
    }

    .stat-icon.pending {
      background: rgba(245, 158, 11, 0.1);
      color: var(--warning);
    }

    .stat-icon.approved {
      background: rgba(16, 185, 129, 0.1);
      color: var(--success);
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

    /* Alert styles */
    .alert {
      padding: 12px 16px;
      margin-bottom: 16px;
      border-radius: 8px;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .alert-success {
      background-color: #D1FAE5;
      color: #065F46;
      border: 1px solid #A7F3D0;
    }

    .alert-error {
      background-color: #FEE2E2;
      color: #B91C1C;
      border: 1px solid #FECACA;
    }

    /* Search Bar */
    .search-bar {
      position: relative;
      margin-bottom: 1.5rem;
      max-width: 400px;
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
      padding: 0.75rem 1rem 0.75rem 2.5rem;
      border: 1px solid var(--gray-light);
      border-radius: var(--border-radius);
      font-size: 1rem;
    }

    .search-bar input:focus {
      outline: none;
      border-color: var(--primary);
    }

    /* Data Table */
    .data-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 1rem;
    }

    .data-table th,
    .data-table td {
      padding: 0.875rem 1rem;
      text-align: left;
    }

    .data-table thead {
      background-color: #f9fafb;
    }

    .data-table thead th {
      font-weight: 600;
      color: var(--dark);
      border-bottom: 1px solid var(--gray-light);
    }

    .data-table tbody tr {
      border-bottom: 1px solid var(--gray-light);
    }

    .data-table tbody tr:hover {
      background-color: #f9fafb;
    }

    /* Status badges */
    .status-badge {
      padding: 0.35rem 0.75rem;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      display: inline-block;
    }

    .status-badge.pending {
      background-color: #fef3c7;
      color: #92400e;
    }

    .status-badge.approved {
      background-color: #def7ec;
      color: #03543f;
    }

    .status-badge.rejected {
      background-color: #fde8e8;
      color: #9b1c1c;
    }

    /* Action buttons */
    .action-buttons {
      display: flex;
      gap: 0.5rem;
    }

    .approve-btn, .reject-btn {
      padding: 0.5rem 0.875rem;
      border-radius: 6px;
      font-size: 0.875rem;
      font-weight: 500;
      cursor: pointer;
      border: none;
      transition: var(--transition);
    }

    .approve-btn {
      background-color: var(--success);
      color: white;
    }

    .approve-btn:hover {
      background-color: #059669;
      transform: translateY(-1px);
    }

    .reject-btn {
      background-color: var(--danger);
      color: white;
    }

    .reject-btn:hover {
      background-color: #dc2626;
      transform: translateY(-1px);
    }

    /* Empty state */
    .empty-state {
      text-align: center;
      padding: 3rem;
      color: var(--gray);
    }

    .empty-state i {
      font-size: 3rem;
      margin-bottom: 1rem;
      color: var(--gray-light);
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
        padding: 1rem;
      }
      
      .menu-toggle {
        display: block;
      }
    }

    @media (max-width: 768px) {
      .top-nav {
        padding: 0 1rem;
      }
      
      .stats-grid {
        grid-template-columns: 1fr;
      }
      
      .matrimony-name-top {
        display: none;
      }
      
      .data-table {
        display: block;
        overflow-x: auto;
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
        <li><a href="index.php" class="sidebar-link"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="members.php" class="sidebar-link"><i class="fas fa-users"></i> Manage Members</a></li>
        <li><a href="call-management.php" class="sidebar-link"><i class="fas fa-phone"></i> Call Management</a></li>
        <li><a href="user-message-management.php" class="sidebar-link"><i class="fas fa-comments"></i> User Messages</a></li>
        <li><a href="review-management.php" class="sidebar-link"><i class="fas fa-star"></i> Review Management</a></li>
        <li><a href="transaction-management.php" class="sidebar-link"><i class="fas fa-receipt"></i> Transactions</a></li>
        <li><a href="packages-management.php" class="sidebar-link"><i class="fas fa-box"></i> Packages</a></li>
        <li><a href="package-requests.php" class="sidebar-link active"><i class="fas fa-clipboard-list"></i> Package Requests</a></li>
        <li><a href="blog-management.php" class="sidebar-link"><i class="fas fa-blog"></i> Blog Management</a></li>
        <li><a href="total-earnings.php" class="sidebar-link"><i class="fas fa-chart-line"></i> Total Earnings</a></li>
        <li><a id="staffLink" href="staff.php" class="sidebar-link"><i class="fas fa-user-shield"></i> Staff Management</a></li>
      </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
      <!-- Display success/error messages -->
      <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
          <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
          <?php echo $message; ?>
        </div>
      <?php endif; ?>

      <!-- Page Header -->
      <div class="content-section">
        <h2><i class="fas fa-clipboard-list"></i> Package Requests Management</h2>
        <p>Manage member requests for package upgrades and subscriptions.</p>
      </div>

      <!-- Statistics -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon requests">
            <i class="fas fa-clipboard-list"></i>
          </div>
          <div class="stat-content">
            <h3>Total Requests</h3>
            <p class="stat-number"><?= $total_requests ?></p>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="stat-icon pending">
            <i class="fas fa-clock"></i>
          </div>
          <div class="stat-content">
            <h3>Pending Requests</h3>
            <p class="stat-number"><?= $pending_requests ?></p>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="stat-icon approved">
            <i class="fas fa-check-circle"></i>
          </div>
          <div class="stat-content">
            <h3>Approved Requests</h3>
            <p class="stat-number"><?= $approved_requests ?></p>
          </div>
        </div>
      </div>

      <!-- Search Bar -->
      <div class="content-section">
        <div class="search-bar">
          <i class="fas fa-search"></i>
          <input type="text" id="searchInput" placeholder="Search requests by user or package..."/>
        </div>
      </div>

      <!-- Requests Table -->
      <div class="content-section">
        <h2>Package Requests</h2>
        
        <?php if ($result->num_rows === 0): ?>
          <div class="empty-state">
            <i class="fas fa-clipboard-list"></i>
            <h3>No Package Requests</h3>
            <p>There are no package requests to display at this time.</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="data-table" id="requestsTable">
              <thead>
                <tr>
                  <th>User Name</th>
                  <th>Package</th>
                  <th>Price</th>
                  <th>Duration</th>
                  <th>Status</th>
                  <th>Requested At</th>
                  <th>Approved At</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($row['user_name']) ?></td>
                  <td><?= htmlspecialchars($row['package_name']) ?></td>
                  <td>$<?= number_format($row['price'], 2) ?></td>
                  <td><?= $row['duration_days'] ?> days</td>
                  <td><span class="status-badge <?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
                  <td><?= date('M j, Y g:i A', strtotime($row['requested_at'])) ?></td>
                  <td><?= $row['approved_at'] ? date('M j, Y g:i A', strtotime($row['approved_at'])) : '-' ?></td>
                  <td>
                    <div class="action-buttons">
                      <?php if($row['status'] === 'pending'): ?>
                        <a href="?approve=<?= $row['id'] ?>" class="approve-btn" onclick="return confirm('Are you sure you want to approve this request?')">
                          <i class="fas fa-check"></i> Approve
                        </a>
                        <a href="?reject=<?= $row['id'] ?>" class="reject-btn" onclick="return confirm('Are you sure you want to reject this request?')">
                          <i class="fas fa-times"></i> Reject
                        </a>
                      <?php else: ?>
                        <span class="text-muted">Processed</span>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>

  <script>
    // Toggle sidebar on mobile
    document.getElementById('menuToggle').addEventListener('click', function() {
      document.getElementById('sidebar').classList.toggle('active');
    });

    // Search functionality
    document.getElementById('searchInput').addEventListener('input', function() {
      const searchTerm = this.value.toLowerCase();
      const rows = document.querySelectorAll('#requestsTable tbody tr');
      
      rows.forEach(row => {
        const userName = row.cells[0].textContent.toLowerCase();
        const packageName = row.cells[1].textContent.toLowerCase();
        const status = row.cells[4].textContent.toLowerCase();
        
        if (userName.includes(searchTerm) || packageName.includes(searchTerm) || status.includes(searchTerm)) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => {
        alert.style.opacity = '0';
        alert.style.transition = 'opacity 0.5s ease';
        setTimeout(() => alert.remove(), 500);
      });
    }, 5000);
  </script>
</body>
</html>