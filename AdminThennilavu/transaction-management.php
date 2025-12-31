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

// Database connection
$host = "localhost";
$user = "thennilavu_matrimonial";
$pass = "OYVuiEKfS@FQ";
$db = "thennilavu_thennilavu";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: ".$conn->connect_error);

// Handle AJAX requests for package actions
if(isset($_POST['action'])){
    $action = $_POST['action'];
    
    if($action === 'accept'){
        $id = intval($_POST['package_id']);
        $start_date = date('Y-m-d H:i:s');
        
        // Get duration for calculating end date
        $res = $conn->query("SELECT duration FROM userpackage WHERE id=$id");
        if($res->num_rows > 0){
            $package = $res->fetch_assoc();
            $duration = $package['duration'];
            $end_date = date('Y-m-d H:i:s', strtotime($start_date . " + $duration days"));
            
            $stmt = $conn->prepare("UPDATE userpackage SET requestPackage='accept', start_date=?, end_date=? WHERE id=?");
            $stmt->bind_param("ssi", $start_date, $end_date, $id);
            echo $stmt->execute() ? json_encode(['success'=>true]) : json_encode(['success'=>false,'error'=>$stmt->error]);
            $stmt->close();
        } else {
            echo json_encode(['success'=>false,'error'=>'Package not found']);
        }
        exit;
    }
    elseif($action === 'reject'){
        $id = intval($_POST['package_id']);
        $start_date = date('Y-m-d H:i:s'); // Store rejection date as start_date
        
        $stmt = $conn->prepare("UPDATE userpackage SET requestPackage='reject', start_date=? WHERE id=?");
        $stmt->bind_param("si", $start_date, $id);
        echo $stmt->execute() ? json_encode(['success'=>true]) : json_encode(['success'=>false,'error'=>$stmt->error]);
        $stmt->close();
        exit;
    }
}

// Fetch package requests by status with package prices
$pending_result = $conn->query("
    SELECT up.*, u.username, u.email, p.price 
    FROM userpackage up 
    JOIN users u ON up.user_id = u.id 
    LEFT JOIN packages p ON up.status = p.name
    WHERE up.requestPackage = 'yes'
    ORDER BY up.id DESC
");

$accepted_result = $conn->query("
    SELECT up.*, u.username, u.email, p.price 
    FROM userpackage up 
    JOIN users u ON up.user_id = u.id 
    LEFT JOIN packages p ON up.status = p.name
    WHERE up.requestPackage = 'accept'
    ORDER BY up.id DESC
");

$rejected_result = $conn->query("
    SELECT up.*, u.username, u.email, p.price 
    FROM userpackage up 
    JOIN users u ON up.user_id = u.id 
    LEFT JOIN packages p ON up.status = p.name
    WHERE up.requestPackage = 'reject'
    ORDER BY up.id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard | Matrimony Services</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="header.css"/>

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

    <main class="main-content">
      <!-- Pending Requests Table -->
      <div class="content-section">
        <h2><i class="fas fa-clock"></i> Pending Package Requests</h2>
        
        
        <div class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>User</th>
                <th>Package</th>
                <th>Price</th>
                <th>Duration</th>
                <th>Payment Slip</th>
                <th>Request Status</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
<?php 
if($pending_result->num_rows > 0) {
    while($package = $pending_result->fetch_assoc()): 
?>
              <tr>
                <td><?= $package['id'] ?></td>
                <td>
                  <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($package['username']) ?></div>
                    <div class="user-email"><?= htmlspecialchars($package['email']) ?></div>
                  </div>
                </td>
                <td>
                  <span class="package-badge <?= strtolower($package['status']) ?>"><?= htmlspecialchars($package['status']) ?></span>
                </td>
                <td>
                  <span class="price-badge">$<?= $package['price'] ? number_format($package['price'], 2) : '0.00' ?></span>
                </td>
                <td><?= $package['duration'] ?> days</td>
                <td>
                  <?php if($package['slip']): ?>
                    <?php
if (!empty($package['slip'])) {
    $slipPath = $package['slip'];

    // If it's not already a full URL, use your server path
    if (strpos($slipPath, 'http') !== 0) {
        $slipPath = 'https://thennilavu.lk/uploads/payment_slips/' . basename($slipPath);
    }
    ?>
    
    <?php
}
?>

                    <button onclick="viewSlip('<?= htmlspecialchars($slipPath) ?>')" class="slip-link">
                      <i class="fas fa-file-image"></i> View Slip
                    </button>
                  <?php else: ?>
                    <span class="no-slip">No slip</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="status-badge pending">Pending</span>
                </td>
                <td>
                  <?= $package['start_date'] != '0000-00-00 00:00:00' ? date('Y-m-d H:i', strtotime($package['start_date'])) : 'Not set' ?>
                </td>
                <td>
                  <?= $package['end_date'] != '0000-00-00 00:00:00' ? date('Y-m-d H:i', strtotime($package['end_date'])) : 'Not set' ?>
                </td>
                <td class="action-buttons">
                  <button class="accept-btn" onclick="handlePackage(<?= $package['id'] ?>, 'accept')" title="Accept Request">
                    <i class="fas fa-check"></i> Accept
                  </button>
                  <button class="reject-btn" onclick="handlePackage(<?= $package['id'] ?>, 'reject')" title="Reject Request">
                    <i class="fas fa-times"></i> Reject
                  </button>
                </td>
              </tr>
<?php 
    endwhile; 
} else {
    echo '<tr><td colspan="9" class="text-center">No pending package requests found</td></tr>';
}
?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Accepted Requests Table -->
      <div class="content-section">
        <h2><i class="fas fa-check-circle"></i> Accepted Package Requests</h2>
       
        
        <div class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>User</th>
                <th>Package</th>
                <th>Price</th>
                <th>Duration</th>
                <th>Payment Slip</th>
                <th>Request Status</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
<?php 
if($accepted_result->num_rows > 0) {
    while($package = $accepted_result->fetch_assoc()): 
?>
              <tr>
                <td><?= $package['id'] ?></td>
                <td>
                  <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($package['username']) ?></div>
                    <div class="user-email"><?= htmlspecialchars($package['email']) ?></div>
                  </div>
                </td>
                <td>
                  <span class="package-badge <?= strtolower($package['status']) ?>"><?= htmlspecialchars($package['status']) ?></span>
                </td>
                <td>
                  <span class="price-badge">$<?= $package['price'] ? number_format($package['price'], 2) : '0.00' ?></span>
                </td>
                <td><?= $package['duration'] ?> days</td>
                <td>
                  <?php if($package['slip']): ?>
                    <?php 
                    // Adjust slip path - if it doesn't start with ../, add the correct path
                    $slipPath = $package['slip'];
                    if (!str_starts_with($slipPath, 'http')) {
                        $slipPath = 'https://thennilavu.lk/uploads/payment_slips/' . basename($slipPath);
                    }
                    ?>
                    <button onclick="viewSlip('<?= htmlspecialchars($slipPath) ?>')" class="slip-link">
                      <i class="fas fa-file-image"></i> View Slip
                    </button>
                  <?php else: ?>
                    <span class="no-slip">No slip</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="status-badge accepted">Accepted</span>
                </td>
                <td>
                  <?= $package['start_date'] != '0000-00-00 00:00:00' ? date('Y-m-d H:i', strtotime($package['start_date'])) : 'Not set' ?>
                </td>
                <td>
                  <?= $package['end_date'] != '0000-00-00 00:00:00' ? date('Y-m-d H:i', strtotime($package['end_date'])) : 'Not set' ?>
                </td>
                <td class="action-buttons">
                  <span class="status-text accepted"><i class="fas fa-check-circle"></i> Accepted</span>
                </td>
              </tr>
<?php 
    endwhile; 
} else {
    echo '<tr><td colspan="9" class="text-center">No accepted package requests found</td></tr>';
}
?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Rejected Requests Table -->
      <div class="content-section">
        <h2><i class="fas fa-times-circle"></i> Rejected Package Requests</h2>
      
        
        <div class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>User</th>
                <th>Package</th>
                <th>Price</th>
                <th>Duration</th>
                <th>Payment Slip</th>
                <th>Request Status</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
<?php 
if($rejected_result->num_rows > 0) {
    while($package = $rejected_result->fetch_assoc()): 
?>
              <tr>
                <td><?= $package['id'] ?></td>
                <td>
                  <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($package['username']) ?></div>
                    <div class="user-email"><?= htmlspecialchars($package['email']) ?></div>
                  </div>
                </td>
                <td>
                  <span class="package-badge <?= strtolower($package['status']) ?>"><?= htmlspecialchars($package['status']) ?></span>
                </td>
                <td>
                  <span class="price-badge">$<?= $package['price'] ? number_format($package['price'], 2) : '0.00' ?></span>
                </td>
                <td><?= $package['duration'] ?> days</td>
                <td>
                  <?php if($package['slip']): ?>
                    <?php 
                    // Adjust slip path - if it doesn't start with ../, add the correct path
                    $slipPath = $package['slip'];
                    
                         if (!str_starts_with($slipPath, 'http')) {
                        $slipPath = 'https://thennilavu.lk/uploads/payment_slips/' . basename($slipPath);
                    }
                    ?>
                    <button onclick="viewSlip('<?= htmlspecialchars($slipPath) ?>')" class="slip-link">
                      <i class="fas fa-file-image"></i> View Slip
                    </button>
                  <?php else: ?>
                    <span class="no-slip">No slip</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="status-badge rejected">Rejected</span>
                </td>
                <td>
                  <?= $package['start_date'] != '0000-00-00 00:00:00' ? date('Y-m-d H:i', strtotime($package['start_date'])) : 'Not set' ?>
                </td>
                <td>
                  <?= $package['end_date'] != '0000-00-00 00:00:00' ? date('Y-m-d H:i', strtotime($package['end_date'])) : 'Not set' ?>
                </td>
                <td class="action-buttons">
                  <span class="status-text rejected"><i class="fas fa-times-circle"></i> Rejected</span>
                </td>
              </tr>
<?php 
    endwhile; 
} else {
    echo '<tr><td colspan="10" class="text-center">No rejected package requests found</td></tr>';
}
?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>

  <style>
/* Main Content Styles */
.main-content {
  flex: 1;
  padding: 20px;
  background: #f5f7f9;
  margin-left: 250px;
}

.content-section {
  background: #fff;
  border-radius: 8px;
  padding: 20px;
  margin-bottom: 20px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.content-section h2 {
  margin-bottom: 10px;
  color: #333;
  font-size: 22px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.content-section p {
  margin-bottom: 20px;
  color: #666;
}

/* Table Styles */
.table-container {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 20px;
  background-color: #fff;
  box-shadow: 0 4px 8px rgba(0,0,0,0.05);
  border-radius: 8px;
  overflow: hidden;
}

.data-table th, .data-table td {
  padding: 15px 12px;
  text-align: left;
  vertical-align: middle;
}

.data-table thead th {
  background-color: #f8f9fa;
  font-weight: 600;
  color: #333;
  text-transform: uppercase;
  font-size: 12px;
  border-bottom: 2px solid #e9ecef;
}

.data-table tbody tr {
  border-bottom: 1px solid #e9ecef;
  transition: background-color 0.2s ease;
}

.data-table tbody tr:last-child {
  border-bottom: none;
}

.data-table tbody tr:hover {
  background-color: #f1f3f5;
}

/* User Info Styles */
.user-info .user-name {
  font-weight: 600;
  color: #333;
  margin-bottom: 2px;
}

.user-info .user-email {
  font-size: 12px;
  color: #666;
}

/* Package Badge Styles */
.package-badge {
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
}

.package-badge.gold {
  background: #fff3cd;
  color: #856404;
}

.package-badge.silver {
  background: #e2e3e5;
  color: #383d41;
}

.package-badge.premium {
  background: #d4edda;
  color: #155724;
}

/* Price Badge Styles */
.price-badge {
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
  background: #e7f3ff;
  color: #0066cc;
  border: 1px solid #b3d9ff;
}

/* Status Badge Styles */
.status-badge {
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  text-transform: capitalize;
}

.status-badge.pending {
  background: #fff3e0;
  color: #ef6c00;
}

.status-badge.accepted {
  background: #e8f5e9;
  color: #2e7d32;
}

.status-badge.rejected {
  background: #ffebee;
  color: #c62828;
}

/* Status Text Styles */
.status-text {
  padding: 6px 12px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 4px;
}

.status-text.accepted {
  background: #e8f5e9;
  color: #2e7d32;
}

.status-text.rejected {
  background: #ffebee;
  color: #c62828;
}

/* Payment Slip Link */
.slip-link {
  background: none;
  border: none;
  color: #4a90e2;
  text-decoration: none;
  font-size: 12px;
  display: flex;
  align-items: center;
  gap: 5px;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 4px;
  transition: background-color 0.2s;
}

.slip-link:hover {
  background-color: #f0f8ff;
  text-decoration: underline;
}

.no-slip {
  color: #999;
  font-size: 12px;
  font-style: italic;
}

/* Action Buttons */
.action-buttons {
  display: flex;
  gap: 8px;
}

.accept-btn, .reject-btn {
  padding: 6px 12px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 11px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 4px;
  transition: all 0.2s;
}

.accept-btn {
  background: #e8f5e9;
  color: #2e7d32;
}

.accept-btn:hover {
  background: #c8e6c9;
}

.reject-btn {
  background: #ffebee;
  color: #c62828;
}

.reject-btn:hover {
  background: #ffcdd2;
}

.text-center {
  text-align: center;
  color: #666;
  font-style: italic;
  padding: 40px 20px;
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
  overflow: auto;
  background-color: rgba(0,0,0,0.4);
}

.modal-content {
  background-color: #fefefe;
  margin: 5% auto;
  padding: 0;
  border: 1px solid #888;
  width: 80%;
  max-width: 800px;
  border-radius: 8px;
  box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  border-bottom: 1px solid #e0e0e0;
  background-color: #f8f9fa;
  border-radius: 8px 8px 0 0;
}

.modal-header h2 {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 10px;
  color: #333;
}

.modal-body {
  padding: 20px;
  text-align: center;
}

.close {
  color: #aaa;
  font-size: 28px;
  font-weight: bold;
  cursor: pointer;
}

.close:hover {
  color: #000;
}
  </style>

  <script>
// View payment slip in modal
function viewSlip(slipUrl) {
    const modal = document.getElementById('slipModal');
    const slipImage = document.getElementById('slipImage');
    const closeBtn = document.getElementById('closeSlipModal');
    
    slipImage.src = slipUrl;
    modal.style.display = 'block';
    
    // Close modal when clicking the X
    closeBtn.onclick = function() {
        modal.style.display = 'none';
    }
    
    // Close modal when clicking outside of it
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
}

// Handle package accept/reject actions
async function handlePackage(packageId, action) {
    const actionText = action === 'accept' ? 'accept' : 'reject';
    
    if (!confirm(`Are you sure you want to ${actionText} this package request?`)) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('package_id', packageId);
        
        const response = await fetch('', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(`Package request ${actionText}ed successfully!`);
            location.reload();
        } else {
            alert('Error: ' + (data.error || `Failed to ${actionText} package request`));
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

// Menu toggle functionality
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');

menuToggle.addEventListener('click', () => {
    sidebar.classList.toggle('collapsed');
});
  </script>

  <!-- Payment Slip Modal -->
  <div id="slipModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2><i class="fas fa-file-image"></i> Payment Slip</h2>
        <span class="close" id="closeSlipModal">&times;</span>
      </div>
      <div class="modal-body">
        <img id="slipImage" src="" alt="Payment Slip" style="max-width: 100%; height: auto;">
      </div>
    </div>
  </div>

</body>
</html>