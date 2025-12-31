<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

// Get session variables
$name = $_SESSION['name'] ?? 'Unknown';
$type = ucfirst($_SESSION['role'] ?? 'staff');

// Database connection
$host = "localhost";
$user = "thennilavu_matrimonial";
$pass = "OYVuiEKfS@FQ";
$db = "thennilavu_thennilavu";

$conn = new mysqli($host, $user, $pass, $db);
   
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$message = '';
$message_type = '';
  
// Process form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // Add new package
  if (isset($_POST['add_package'])) {
    $name = $_POST['name'];
    $price = $_POST['price'];
    $duration_days = $_POST['duration_days'];
    $status = $_POST['status'];
    $description = $_POST['description'];
    $profile_views_limit = $_POST['profile_views_limit'];
    $interest_limit = $_POST['interest_limit'];
    $search_access = $_POST['search_access'];
    $profile_view_enabled = $_POST['profile_view_enabled'];
    $profile_hide_enabled = $_POST['profile_hide_enabled'];
    $matchmaker_enabled = $_POST['matchmaker_enabled'];

    $sql = "INSERT INTO packages (name, price, duration_days, status, description, profile_views_limit, interest_limit, search_access, profile_view_enabled, profile_hide_enabled, matchmaker_enabled)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sdsssssssss", $name, $price, $duration_days, $status, $description, $profile_views_limit, $interest_limit, $search_access, $profile_view_enabled, $profile_hide_enabled, $matchmaker_enabled);
    if ($stmt->execute()) {
      $message = "New package added successfully!";
      $message_type = "success";
    } else {
      $message = "Error: " . $stmt->error;
      $message_type = "error";
    }
    $stmt->close();
  }
    
  // Update package
  if (isset($_POST['update_package'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $price = $_POST['price'];
    $duration_days = $_POST['duration_days'];
    $status = $_POST['status'];
    $description = $_POST['description'];
    $profile_views_limit = $_POST['profile_views_limit'];
    $interest_limit = $_POST['interest_limit'];
    $search_access = $_POST['search_access'];
    $profile_view_enabled = $_POST['profile_view_enabled'];
    $profile_hide_enabled = $_POST['profile_hide_enabled'];
    $matchmaker_enabled = $_POST['matchmaker_enabled'];

    $sql = "UPDATE packages SET name=?, price=?, duration_days=?, status=?, description=?, profile_views_limit=?, interest_limit=?, search_access=?, profile_view_enabled=?, profile_hide_enabled=?, matchmaker_enabled=? WHERE package_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sdsssssssssi", $name, $price, $duration_days, $status, $description, $profile_views_limit, $interest_limit, $search_access, $profile_view_enabled, $profile_hide_enabled, $matchmaker_enabled, $id);
    if ($stmt->execute()) {
      $message = "Package updated successfully!";
      $message_type = "success";
    } else {
      $message = "Error: " . $stmt->error;
      $message_type = "error";
    }
    $stmt->close();
  }
    
  // Soft-deactivate package instead of deleting
  if (isset($_POST['delete_package'])) {
    $id = $_POST['id'];

    $sql = "UPDATE packages SET status='inactive' WHERE package_id=?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
      $message = "Package deactivated successfully!";
      $message_type = "success";
    } else {
      $message = "Error: " . $stmt->error;
      $message_type = "error";
    }

    $stmt->close();
  }

  // Activate package (set status back to active)
  if (isset($_POST['activate_package'])) {
    $id = $_POST['id'];

    $sql = "UPDATE packages SET status='active' WHERE package_id=?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
      $message = "Package activated successfully!";
      $message_type = "success";
    } else {
      $message = "Error: " . $stmt->error;
      $message_type = "error";
    }

    $stmt->close();
  }
}

// Get all packages
$packages = [];
$sql = "SELECT * FROM packages ORDER BY package_id DESC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $packages[] = $row;
    }
}

// Get package by ID for editing
$edit_package = null;
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $sql = "SELECT * FROM packages WHERE package_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $edit_package = $result->fetch_assoc();
    }
    
    $stmt->close();
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Packages Management - Matrimony Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
  <style>
    :root {
      --primary: #7e3af2;
      --primary-dark: #6c2bd9;
      --secondary: #ff5e92;
      --success: #10b981;
      --warning: #f59e0b;
      --danger: #ef4444;
      --light: #f8fafc;
      --dark: #1e293b;
      --gray: #64748b;
      --gray-light: #e5e7eb;
      --sidebar-width: 280px;
      --header-height: 70px;
      --border-radius: 12px;
      --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
      --transition: all 0.3s ease;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Inter', sans-serif;
    }
    
    body {
      background-color: #f5f7f9;
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
      background: rgba(126, 58, 242, 0.1);
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
    
    /* Top Action Buttons */
    .top-actions {
      display: flex;
      gap: 1rem;
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
    }
    
    .action-btn {
      padding: 0.75rem 1.5rem;
      border: none;
      border-radius: var(--border-radius);
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .btn-primary {
      background: var(--primary);
      color: white;
    }
    
    .btn-primary:hover {
      background: var(--primary-dark);
    }
    
    .btn-success {
      background: var(--success);
      color: white;
    }
    
    .btn-success:hover {
      background: #059669;
    }
    
    .btn-warning {
      background: var(--warning);
      color: white;
    }
    
    .btn-warning:hover {
      background: #d97706;
    }
    
    .btn-danger {
      background: var(--danger);
      color: white;
    }
    
    .btn-danger:hover {
      background: #dc2626;
    }
    
    /* Content Section */
    .content-section {
      background: white;
      border-radius: var(--border-radius);
      padding: 1.5rem;
      box-shadow: var(--shadow);
      margin-bottom: 1.5rem;
    }
    
    .section-title {
      font-size: 1.25rem;
      font-weight: 600;
      color: var(--dark);
      margin-bottom: 1rem;
    }
    
    /* Table Styles */
    .data-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 1rem;
    }
    
    .data-table th,
    .data-table td {
      padding: 0.75rem;
      text-align: left;
      border-bottom: 1px solid var(--gray-light);
    }
    
    .data-table th {
      background-color: var(--light);
      font-weight: 600;
      color: var(--dark);
    }
    
    .data-table tr:hover {
      background-color: #f8fafc;
    }
    
    .status-badge {
      padding: 0.25rem 0.75rem;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    
    .status-active {
      background: #d1fae5;
      color: #065f46;
    }
    
    .status-inactive {
      background: #fee2e2;
      color: #b91c1c;
    }
    
    .status-draft {
      background: #fef3c7;
      color: #92400e;
    }
    
    /* Form Styles */
    .form-group {
      margin-bottom: 1rem;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 600;
      color: var(--dark);
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 0.75rem;
      border: 1px solid var(--gray-light);
      border-radius: var(--border-radius);
      font-size: 1rem;
    }
    
    .form-group textarea {
      resize: vertical;
      min-height: 100px;
    }
    
    /* Modal Styles */
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
      padding: 2rem;
      border-radius: var(--border-radius);
      width: 90%;
      max-width: 600px;
      max-height: 80vh;
      overflow-y: auto;
    }
    
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
    }
    
    .modal-title {
      font-size: 1.5rem;
      font-weight: 600;
      color: var(--dark);
    }
    
    .close {
      font-size: 2rem;
      font-weight: bold;
      cursor: pointer;
      color: var(--gray);
    }
    
    .close:hover {
      color: var(--dark);
    }
    
    .form-actions {
      display: flex;
      gap: 1rem;
      justify-content: flex-end;
      margin-top: 1.5rem;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
      .sidebar {
        transform: translateX(-100%);
      }
      
      .main-content {
        margin-left: 0;
      }
      
      .top-actions {
        flex-direction: column;
      }
    }
    
    .action-btn {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.75rem 1.5rem;
      border: none;
      border-radius: var(--border-radius);
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
    }
    
    .action-btn.primary {
      background-color: var(--primary);
      color: white;
    }
    
    .action-btn.secondary {
      background-color: white;
      color: var(--dark);
      border: 1px solid var(--gray-light);
    }
    
    .action-btn:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow);
    }
    
    .action-btn.primary:hover {
      background-color: var(--primary-dark);
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
    
    /* Content Section */
    .content-section {
      background-color: white;
      border-radius: var(--border-radius);
      padding: 1.5rem;
      margin-bottom: 1.5rem;
      box-shadow: var(--shadow);
    }
    
    .content-section h2 {
      margin-bottom: 1.5rem;
      color: var(--dark);
      font-size: 1.5rem;
    }
    
    /* Stats Grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1.5rem;
      margin-bottom: 1.5rem;
    }
    
    .stat-card {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1.5rem;
      background-color: #f9fafb;
      border-radius: var(--border-radius);
      transition: var(--transition);
    }
    
    .stat-card:hover {
      transform: translateY(-3px);
      box-shadow: var(--shadow);
    }
    
    .stat-icon {
      width: 60px;
      height: 60px;
      border-radius: 12px;
      background-color: var(--primary);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
    }
    
    .stat-info h3 {
      font-size: 0.875rem;
      color: var(--gray);
      margin-bottom: 0.5rem;
    }
    
    .stat-number {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--dark);
      margin-bottom: 0.25rem;
    }
    
    .stat-change {
      font-size: 0.875rem;
      display: flex;
      align-items: center;
      gap: 0.25rem;
    }
    
    .stat-change.positive {
      color: var(--success);
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
    
    .status-badge.active {
      background-color: #def7ec;
      color: #03543f;
    }
    
    .status-badge.inactive {
      background-color: #fde8e8;
      color: #9b1c1c;
    }
    
    .status-badge.draft {
      background-color: #fef3c7;
      color: #92400e;
    }
    
    /* Action buttons */
    .action-buttons {
      display: flex;
      gap: 0.5rem;
    }
    
    .edit-btn, .delete-btn {
      width: 32px;
      height: 32px;
      border-radius: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      border: none;
      cursor: pointer;
      transition: var(--transition);
    }
    
    .edit-btn {
      background-color: var(--success);
      color: white;
    }
    
    .delete-btn {
      background-color: var(--danger);
      color: white;
    }
    
    .edit-btn:hover, .delete-btn:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow);
    }
    
    /* Modal */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      align-items: center;
      justify-content: center;
    }
    
    .modal-content {
      background-color: white;
      border-radius: var(--border-radius);
      width: 90%;
      max-width: 700px;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: var(--shadow);
    }
    
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1.5rem;
      border-bottom: 1px solid var(--gray-light);
    }
    
    .modal-header h2 {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: var(--dark);
    }
    
    .close {
      font-size: 1.5rem;
      font-weight: bold;
      cursor: pointer;
      color: var(--gray);
    }
    
    .close:hover {
      color: var(--dark);
    }
    
    .modal-body {
      padding: 1.5rem;
    }
    
    /* Form Styles */
    .form-row {
      display: flex;
      gap: 1rem;
      margin-bottom: 1rem;
      flex-wrap: wrap;
    }
    
    .form-column {
      flex: 1;
      min-width: 200px;
    }
    
    .form-group {
      margin-bottom: 1rem;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 600;
      color: var(--dark);
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 0.75rem;
      border: 1px solid var(--gray-light);
      border-radius: var(--border-radius);
      font-size: 1rem;
      transition: var(--transition);
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(126, 58, 242, 0.1);
    }
    
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
        <li><a href="blog-management.php" class="sidebar-link"><i class="fas fa-blog"></i> Blog Management</a></li>
        <li><a href="total-earnings.php" class="sidebar-link"><i class="fas fa-chart-line"></i> Total Earnings</a></li>
        <li id="staffLink"><a href="staff.php" class="sidebar-link"><i class="fas fa-user-shield"></i> Staff Management</a></li>
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

      <!-- Top Action Buttons -->
      <div class="top-actions">
        <button id="addPackageBtn" class="action-btn primary">
          <i class="fas fa-plus"></i>
          Add New Package
        </button>
        <button id="exportPdfBtn" class="action-btn secondary">
          <i class="fas fa-download"></i>
          Export Packages PDF
        </button>
      </div>

      <!-- Search Bar -->
      <div class="search-bar">
        <i class="fas fa-search"></i>
        <input type="text" id="searchInput" placeholder="Search packages by name, features, or status"/>
      </div>

      <!-- Package Statistics -->
      <div class="content-section">
        <h2>Package Overview</h2>
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-icon">
              <i class="fas fa-box"></i>
            </div>
            <div class="stat-info">
              <h3>Total Packages</h3>
              <p class="stat-number" id="totalPackages"><?php echo count($packages); ?></p>
              <span class="stat-change positive">
                <i class="fas fa-arrow-up"></i>
                <?php echo count($packages); ?> total
              </span>
            </div>
          </div>
          
          <div class="stat-card">
            <div class="stat-icon">
              <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
              <h3>Active Packages</h3>
              <?php
                $active_count = 0;
                foreach ($packages as $pkg) {
                  if ($pkg['status'] === 'active') $active_count++;
                }
                $active_percentage = count($packages) > 0 ? round(($active_count / count($packages)) * 100) : 0;
              ?>
              <p class="stat-number" id="activePackages"><?php echo $active_count; ?></p>
              <span class="stat-change positive">
                <i class="fas fa-arrow-up"></i>
                <?php echo $active_percentage; ?>%
              </span>
            </div>
          </div>
          
          <div class="stat-card">
            <div class="stat-icon">
              <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
              <h3>Total Subscribers</h3>
              <p class="stat-number">1,247</p>
              <span class="stat-change positive">
                <i class="fas fa-arrow-up"></i>
                12.5%
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Packages Table -->
      <div class="content-section">
        <h2>Package List</h2>
        <?php if (count($packages) === 0): ?>
          <div class="loading">
            <i class="fas fa-box-open"></i>
            <p>No packages found. Add your first package to get started.</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="data-table" id="packagesTable">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Package Name</th>
                  <th>Price</th>
                  <th>Duration</th>
                  <th>Profile Views</th>
                  <th>Interest</th>
                  <th>Search</th>
                  <th>Profile View</th>
                  <th>Profile Hide</th>
                  <th>Matchmaker</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="packagesTableBody">
                <?php foreach ($packages as $pkg): ?>
                  <tr>
                    <td><?php echo $pkg['package_id']; ?></td>
                    <td><?php echo htmlspecialchars($pkg['name']); ?></td>
                    <td>$<?php echo number_format($pkg['price'], 2); ?></td>
                    <td><?php echo $pkg['duration_days']; ?> days</td>
                    <td><?php echo htmlspecialchars($pkg['profile_views_limit']); ?></td>
                    <td><?php echo htmlspecialchars($pkg['interest_limit']); ?></td>
                    <td><?php echo htmlspecialchars($pkg['search_access']); ?></td>
                    <td><?php echo htmlspecialchars($pkg['profile_view_enabled']); ?></td>
                    <td><?php echo htmlspecialchars($pkg['profile_hide_enabled']); ?></td>
                    <td><?php echo htmlspecialchars($pkg['matchmaker_enabled']); ?></td>
                    <td><span class="status-badge <?php echo htmlspecialchars($pkg['status']); ?>"><?php echo ucfirst($pkg['status']); ?></span></td>
                    <td>
                      <div class="action-buttons">
                        <button class="edit-btn" onclick="openUpdateModal(<?php echo $pkg['package_id']; ?>)" title="Edit">
                          <i class="fas fa-edit"></i>
                        </button>
                        <?php if ($pkg['status'] === 'active'): ?>
                          <form method="POST" style="display: inline;">
                            <input type="hidden" name="id" value="<?php echo $pkg['package_id']; ?>">
                            <button type="submit" name="delete_package" class="delete-btn" title="Deactivate" onclick="return confirm('Are you sure you want to deactivate this package?')">
                              <i class="fas fa-user-slash"></i>
                            </button>
                          </form>
                        <?php else: ?>
                          <form method="POST" style="display: inline;">
                            <input type="hidden" name="id" value="<?php echo $pkg['package_id']; ?>">
                            <button type="submit" name="activate_package" class="delete-btn" title="Activate" onclick="return confirm('Are you sure you want to activate this package?')" style="background-color: #2b8a3e;">
                              <i class="fas fa-check"></i>
                            </button>
                          </form>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- Add Package Modal -->
      <div id="addPackageModal" class="modal">
        <div class="modal-content">
          <div class="modal-header">
            <h2><i class="fas fa-box"></i> Add New Package</h2>
            <span class="close" id="closeAddPackageModal">&times;</span>
          </div>
          <div class="modal-body">
            <form method="POST" id="addPackageForm">
              <!-- Row 1: Package Name, Price -->
              <div class="form-row">
                <div class="form-column">
                  <div class="form-group">
                    <label for="pkgName">Package Name</label>
                    <input id="pkgName" name="name" type="text" placeholder="e.g., Premium Plan" required />
                  </div>
                </div>
                <div class="form-column">
                  <div class="form-group">
                    <label for="pkgPrice">Price (USD)</label>
                    <input id="pkgPrice" name="price" type="number" step="0.01" min="0" placeholder="e.g., 79.99" required />
                  </div>
                </div>
              </div>

              <!-- Row 2: Duration, Features, Status -->
              <div class="form-row">
                <div class="form-column">
                  <div class="form-group">
                    <label for="pkgDuration">Duration (Days)</label>
                    <select id="pkgDuration" name="duration_days" required>
                      <option value="">Select duration</option>
                      <option value="7">7 Days</option>
                      <option value="30">1 Month</option>
                      <option value="90">3 Months</option>
                      <option value="180">6 Months</option>
                      <option value="365">12 Months</option>
                    </select>
                  </div>
                </div>
                <div class="form-column">
                  <div class="form-group">
                    <label for="pkgProfileViews">Profile Views</label>
                    <input id="pkgProfileViews" name="profile_views_limit" type="text" placeholder="e.g., 100" />
                  </div>
                </div>
                <div class="form-column">
                  <div class="form-group">
                    <label for="pkgInterest">Interest</label>
                    <input id="pkgInterest" name="interest_limit" type="text" placeholder="e.g., 50" />
                  </div>
                </div>
                <div class="form-column">
                  <div class="form-group">
                    <label for="pkgSearch">Search</label>
                    <select id="pkgSearch" name="search_access">
                      <option value="Limited">Limited</option>
                      <option value="Unlimited">Unlimited</option>
                    </select>
                  </div>
                </div>
                <div class="form-column">
                  <div class="form-group">
                    <label for="pkgProfileView">Profile View</label>
                    <select id="pkgProfileView" name="profile_view_enabled">
                      <option value="Yes">Yes</option>
                      <option value="No">No</option>
                    </select>
                  </div>
                </div>
                <div class="form-column">
                  <div class="form-group">
                    <label for="pkgProfileHide">Profile Hide</label>
                    <select id="pkgProfileHide" name="profile_hide_enabled">
                      <option value="Yes">Yes</option>
                      
                    </select>
                  </div>
                </div>
                <div class="form-column">
                  <div class="form-group">
                    <label for="pkgMatchmaker">Matchmaker</label>
                    <select id="pkgMatchmaker" name="matchmaker_enabled">
                      <option value="Yes">Yes</option>
                      <option value="No">No</option>
                    </select>
                  </div>
                </div>
                <div class="form-column">
                  <div class="form-group">
                    <label for="pkgStatus">Status</label>
                    <select id="pkgStatus" name="status" required>
                      <option value="active">Active</option>
                      <option value="inactive">Inactive</option>
                      <option value="draft">Draft</option>
                    </select>
                  </div>
                </div>
              </div>

              <div class="form-group">
                <label for="pkgDesc">Description</label>
                <textarea id="pkgDesc" name="description" rows="3" placeholder="Short description..."></textarea>
              </div>

              <div class="form-actions">
                <button type="submit" name="add_package" class="btn-primary"><i class="fas fa-save"></i> Save Package</button>
                <button type="button" id="cancelAddPackage" class="btn-secondary">Cancel</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Update Package Modal -->
      <div id="updatePackageModal" class="modal">
        <div class="modal-content">
          <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Update Package</h2>
            <span class="close" id="closeUpdatePackageModal">&times;</span>
          </div>
          <div class="modal-body">
            <?php if ($edit_package): ?>
            <form method="POST" id="updatePackageForm">
              <input type="hidden" name="id" value="<?php echo $edit_package['package_id']; ?>">
              
              <!-- Row 1: Package Name, Price -->
              <div class="form-row">
                <div class="form-column">
                  <div class="form-group">
                    <label for="updatePkgName">Package Name</label>
                    <input id="updatePkgName" name="name" type="text" value="<?php echo htmlspecialchars($edit_package['name']); ?>" placeholder="e.g., Premium Plan" required />
                  </div>
                </div>
                <div class="form-column">
                  <div class="form-group">
                    <label for="updatePkgPrice">Price (USD)</label>
                    <input id="updatePkgPrice" name="price" type="number" step="0.01" min="0" value="<?php echo $edit_package['price']; ?>" placeholder="e.g., 79.99" required />
                  </div>
                </div>
              </div>

              <!-- Row 2: Duration, Features, Status -->
              <div class="form-row">
                <div class="form-column">
                  <div class="form-group">
                    <label for="updatePkgDuration">Duration (Days)</label>
                    <select id="updatePkgDuration" name="duration_days" required>
                      <option value="">Select duration</option>
                      <option value="7" <?php if ($edit_package['duration_days'] == 7) echo 'selected'; ?>>7 Days</option>
                      <option value="30" <?php if ($edit_package['duration_days'] == 30) echo 'selected'; ?>>1 Month</option>
                      <option value="90" <?php if ($edit_package['duration_days'] == 90) echo 'selected'; ?>>3 Months</option>
                      <option value="180" <?php if ($edit_package['duration_days'] == 180) echo 'selected'; ?>>6 Months</option>
                      <option value="365" <?php if ($edit_package['duration_days'] == 365) echo 'selected'; ?>>12 Months</option>
                    </select>
                  </div>
                </div>
                <div class="form-column">
                  <div class="form-group">
                    <label for="updatePkgProfileViews">Profile Views</label>
                    <input id="updatePkgProfileViews" name="profile_views_limit" type="text" value="<?php echo htmlspecialchars($edit_package['profile_views_limit']); ?>" placeholder="e.g., 100" />
                  </div>
                </div>
                <div class="form-column">
                  <div class="form-group">
                    <label for="updatePkgInterest">Interest</label>
                    <input id="updatePkgInterest" name="interest_limit" type="text" value="<?php echo htmlspecialchars($edit_package['interest_limit']); ?>" placeholder="e.g., 50" />
                  </div>
                </div>
                <div class="form-column">
                  <div class="form-group">
                    <label for="updatePkgSearch">Search</label>
                    <select id="updatePkgSearch" name="search_access">
                      <option value="Limited" <?php if ($edit_package['search_access'] == 'Limited') echo 'selected'; ?>>Limited</option>
                      <option value="Unlimited" <?php if ($edit_package['search_access'] == 'Unlimited') echo 'selected'; ?>>Unlimited</option>
                    </select>
                  </div>
                </div>
                <div class="form-column">
                  <div class="form-group">
                    <label for="updatePkgProfileView">Profile View</label>
                    <select id="updatePkgProfileView" name="profile_view_enabled">
                      <option value="Yes" <?php if ($edit_package['profile_view_enabled'] == 'Yes') echo 'selected'; ?>>Yes</option>
                      <option value="No" <?php if ($edit_package['profile_view_enabled'] == 'No') echo 'selected'; ?>>No</option>
                    </select>
                  </div>
                </div>
                <div class="form-column">
                  <div class="form-group">
                    <label for="updatePkgProfileHide">Profile Hide</label>
                    <select id="updatePkgProfileHide" name="profile_hide_enabled">
                      <option value="Yes" <?php if ($edit_package['profile_hide_enabled'] == 'Yes') echo 'selected'; ?>>Yes</option>
                     
                    </select>
                  </div>
                </div>
                <div class="form-column">
                  <div class="form-group">
                    <label for="updatePkgMatchmaker">Matchmaker</label>
                    <select id="updatePkgMatchmaker" name="matchmaker_enabled">
                      <option value="Yes" <?php if ($edit_package['matchmaker_enabled'] == 'Yes') echo 'selected'; ?>>Yes</option>
                      <option value="No" <?php if ($edit_package['matchmaker_enabled'] == 'No') echo 'selected'; ?>>No</option>
                    </select>
                  </div>
                </div>
                <div class="form-column">
                  <div class="form-group">
                    <label for="updatePkgStatus">Status</label>
                    <select id="updatePkgStatus" name="status" required>
                      <option value="active" <?php if ($edit_package['status'] == 'active') echo 'selected'; ?>>Active</option>
                      <option value="inactive" <?php if ($edit_package['status'] == 'inactive') echo 'selected'; ?>>Inactive</option>
                      <option value="draft" <?php if ($edit_package['status'] == 'draft') echo 'selected'; ?>>Draft</option>
                    </select>
                  </div>
                </div>
              </div>

              <div class="form-group">
                <label for="updatePkgDesc">Description</label>
                <textarea id="updatePkgDesc" name="description" rows="3" placeholder="Short description..."><?php echo htmlspecialchars($edit_package['description']); ?></textarea>
              </div>

              <div class="form-actions">
                <button type="submit" name="update_package" class="btn-primary"><i class="fas fa-save"></i> Update Package</button>
                <button type="button" id="cancelUpdatePackage" class="btn-secondary">Cancel</button>
              </div>
            </form>
            <?php else: ?>
              <div class="loading">Package not found</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script>
    // DOM elements
    const addPackageBtn = document.getElementById('addPackageBtn');
    const addPackageModal = document.getElementById('addPackageModal');
    const closeAddPackageModal = document.getElementById('closeAddPackageModal');
    const cancelAddPackage = document.getElementById('cancelAddPackage');
    
    const updatePackageModal = document.getElementById('updatePackageModal');
    const closeUpdatePackageModal = document.getElementById('closeUpdatePackageModal');
    const cancelUpdatePackage = document.getElementById('cancelUpdatePackage');
    
    const searchInput = document.getElementById('searchInput');
    const exportPdfBtn = document.getElementById('exportPdfBtn');
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');

    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
      // Show modals based on URL parameters
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('edit_id')) {
        updatePackageModal.style.display = 'flex';
      }
      
      // Event listeners for modals
      if (addPackageBtn) addPackageBtn.addEventListener('click', () => addPackageModal.style.display = 'flex');
      if (closeAddPackageModal) closeAddPackageModal.addEventListener('click', () => addPackageModal.style.display = 'none');
      if (cancelAddPackage) cancelAddPackage.addEventListener('click', () => addPackageModal.style.display = 'none');
      
      if (closeUpdatePackageModal) {
        closeUpdatePackageModal.addEventListener('click', () => {
          updatePackageModal.style.display = 'none';
          const url = new URL(window.location);
          url.searchParams.delete('edit_id');
          window.history.replaceState({}, '', url);
        });
      }
      
      if (cancelUpdatePackage) {
        cancelUpdatePackage.addEventListener('click', () => {
          updatePackageModal.style.display = 'none';
          const url = new URL(window.location);
          url.searchParams.delete('edit_id');
          window.history.replaceState({}, '', url);
        });
      }
      
      // Close modals when clicking outside
      window.addEventListener('click', (e) => {
        if (addPackageModal && e.target === addPackageModal) addPackageModal.style.display = 'none';
        if (updatePackageModal && e.target === updatePackageModal) {
          updatePackageModal.style.display = 'none';
          const url = new URL(window.location);
          url.searchParams.delete('edit_id');
          window.history.replaceState({}, '', url);
        }
      });
      
      // Search functionality
      if (searchInput) searchInput.addEventListener('input', filterPackages);
      
      // PDF export
      if (exportPdfBtn) {
        exportPdfBtn.addEventListener('click', exportToPDF);
      }
      
      // Mobile menu toggle
      if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', () => {
          sidebar.classList.toggle('active');
        });
      }
    });

    // Filter packages based on search input
    function filterPackages() {
      const searchTerm = searchInput.value.toLowerCase();
      const rows = document.querySelectorAll('#packagesTableBody tr');
      
      for (let row of rows) {
        const name = row.cells[1].textContent.toLowerCase();
        // Combine all new fields for search
        const profileViews = row.cells[4].textContent.toLowerCase();
        const interest = row.cells[5].textContent.toLowerCase();
        const search = row.cells[6].textContent.toLowerCase();
        const profileView = row.cells[7].textContent.toLowerCase();
        const profileHide = row.cells[8].textContent.toLowerCase();
        const matchmaker = row.cells[9].textContent.toLowerCase();
        const status = row.cells[10].textContent.toLowerCase();
        if (
          name.includes(searchTerm) ||
          profileViews.includes(searchTerm) ||
          interest.includes(searchTerm) ||
          search.includes(searchTerm) ||
          profileView.includes(searchTerm) ||
          profileHide.includes(searchTerm) ||
          matchmaker.includes(searchTerm) ||
          status.includes(searchTerm)
        ) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      }
    }
    
    // Open update modal
    function openUpdateModal(id) {
      // Add edit_id to URL
      const url = new URL(window.location);
      url.searchParams.set('edit_id', id);
      window.history.replaceState({}, '', url);
      
      // Reload page to load the package data
      window.location.reload();
    }
    
    // Pass PHP package data to JS
    const packagesData = <?php echo json_encode($packages); ?>;
    const totalPackages = packagesData.length;
    const activePackages = packagesData.filter(pkg => pkg.status === 'active').length;

    // Export to PDF
    function exportToPDF() {
      try {
        if (!window.jspdf) {
          alert('PDF library not loaded. Please refresh the page and try again.');
          return;
        }
        
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        
        doc.setFontSize(20);
        doc.text('Packages Report', 20, 20);
        
        doc.setFontSize(12);
        doc.text('Generated on: ' + new Date().toLocaleDateString(), 20, 35);
        
        const tableData = packagesData.map(pkg => [
          pkg.package_id,
          pkg.name,
          '$' + parseFloat(pkg.price).toFixed(2),
          pkg.duration_days + ' days',
          pkg.status.charAt(0).toUpperCase() + pkg.status.slice(1)
        ]);
        
        doc.autoTable({
          head: [['ID', 'Name', 'Price', 'Duration', 'Status']],
          body: tableData,
          startY: 50,
          theme: 'grid',
          headStyles: {
            fillColor: [126, 58, 242],
            textColor: 255
          }
        });
        
        doc.save('packages-report.pdf');
      } catch (error) {
        console.error('PDF Export Error:', error);
        alert('Error generating PDF. Please try again.');
      }
    }
  </script>
</body>
</html>