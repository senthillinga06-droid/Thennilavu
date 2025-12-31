<?php
// header.php
require_once 'config.php';
checkAuth();

$name = $_SESSION['name'];
$type = ucfirst($_SESSION['role']); // Admin/Staff
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard | Matrimony Services</title>
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

    /* Hide staff management for non-admin users */
    <?php if ($_SESSION['role'] !== 'admin'): ?>
    #staffLink {
      display: none;
    }
    <?php endif; ?>

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

    /* Optional overlay for mobile */
    .overlay {
      display: none;
      position: fixed;
      top: var(--header-height);
      left: 0;
      width: 100%;
      height: calc(100vh - var(--header-height));
      background: rgba(0, 0, 0, 0.3);
      z-index: 80;
    }

    .overlay.active {
      display: block;
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

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="matrimony-name">Matrimony Admin Panel</div>
    <ul class="sidebar-menu">
      <li><a href="index.php" class="sidebar-link"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="members.php" class="sidebar-link"><i class="fas fa-users"></i> Manage Members</a></li>
      <li><a href="call-management.php" class="sidebar-link"><i class="fas fa-phone"></i> Call Management</a></li>
      <li><a href="user-message-management.php" class="sidebar-link"><i class="fas fa-comments"></i> User Messages</a></li>
      <li><a href="review-management.php" class="sidebar-link"><i class="fas fa-star"></i> Review</a></li>
      <li><a href="transaction-management.php" class="sidebar-link"><i class="fas fa-receipt"></i> Transactions</a></li>
      <li><a href="packages-management.php" class="sidebar-link"><i class="fas fa-box"></i> Packages</a></li>
      <li><a href="blog-management.php" class="sidebar-link"><i class="fas fa-blog"></i> Blog Management</a></li>
      <li><a href="total-earnings.php" class="sidebar-link"><i class="fas fa-chart-line"></i> Total Earnings</a></li>
      <li><a href="staff.php" class="sidebar-link" id="staffLink"><i class="fas fa-user-shield"></i> Staff Management</a></li>
    </ul>
  </aside>

  <!-- Overlay for mobile -->
  <div class="overlay" id="overlay"></div>

  <!-- Main Content -->
  <main class="main-content">
    <!-- Your main content starts here -->


    <!-- JS for Menu Toggle -->
  <script>
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');

    // Toggle sidebar visibility on button click
    menuToggle.addEventListener('click', () => {
      sidebar.classList.toggle('active');
      overlay.classList.toggle('active');
    });

    // Close sidebar when clicking on overlay
    overlay.addEventListener('click', () => {
      sidebar.classList.remove('active');
      overlay.classList.remove('active');
    });
  </script>
</body>
</html>
