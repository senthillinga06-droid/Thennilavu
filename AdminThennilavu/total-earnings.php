<?php
// total-earnings.php
require_once 'header.php';

// Check if user is admin (using session data)
$isAdmin = ($_SESSION['role'] === 'admin');

// Verify admin role from database
$staff_id = $_SESSION['staff_id'];
$role_check = $conn->prepare("SELECT role FROM staff WHERE staff_id = ?");
$role_check->bind_param("i", $staff_id);
$role_check->execute();
$role_result = $role_check->get_result();
$staff_data = $role_result->fetch_assoc();
$role_check->close();

// Check if user is admin
$is_admin_verified = ($staff_data && $staff_data['role'] === 'admin');
$access_denied = !$is_admin_verified;

// Get date range for filtering
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Build date condition for queries
$date_condition = "";
if($start_date && $end_date) {
    $date_condition = " AND up.start_date BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";
}

$current_accepted_result = $conn->query("
    SELECT up.*, u.username, u.email, p.price, p.name AS package_name
    FROM userpackage up
    JOIN users u ON up.user_id = u.id
    LEFT JOIN packages p ON up.status = p.name
    JOIN (
        SELECT user_id, MAX(start_date) AS latest_start
        FROM userpackage
        WHERE requestPackage = 'accept' 
        AND end_date > NOW()
        $date_condition
        GROUP BY user_id
    ) AS latest ON latest.user_id = up.user_id AND latest.latest_start = up.start_date
    WHERE up.requestPackage = 'accept'
    AND up.end_date > NOW()
    $date_condition
    ORDER BY up.start_date DESC
");


// Fetch past/expired accepted package requests
$past_accepted_result = $conn->query("
    SELECT up.*, u.username, u.email, p.price, p.name as package_name
    FROM userpackage up 
    JOIN users u ON up.user_id = u.id 
    LEFT JOIN packages p ON up.status = p.name
    WHERE up.requestPackage = 'accept' 
    AND (
        up.end_date <= NOW() 
        OR up.start_date < (
            SELECT MAX(start_date)
            FROM userpackage
            WHERE user_id = up.user_id
            AND requestPackage = 'accept'
        )
    )    
    $date_condition
    ORDER BY up.end_date DESC
");

// Get package statistics
$package_stats = $conn->query("
    SELECT 
        p.name as package_name,
        COUNT(up.id) as total_activated,
        SUM(p.price) as total_revenue,
        p.price as unit_price
    FROM userpackage up 
    JOIN packages p ON up.status = p.name
    WHERE up.requestPackage = 'accept'
    $date_condition
    GROUP BY p.name, p.price
    ORDER BY total_activated DESC
");

// Calculate total earnings
$total_earnings_result = $conn->query("
    SELECT SUM(p.price) as total_earnings
    FROM userpackage up 
    JOIN packages p ON up.status = p.name
    WHERE up.requestPackage = 'accept'
    $date_condition
");
$total_earnings = $total_earnings_result->fetch_assoc()['total_earnings'] ?? 0;

// Get monthly earnings for chart
$monthly_earnings = $conn->query("
    SELECT 
        DATE_FORMAT(up.start_date, '%Y-%m') as month,
        SUM(p.price) as earnings
    FROM userpackage up 
    JOIN packages p ON up.status = p.name
    WHERE up.requestPackage = 'accept'
    $date_condition
    GROUP BY DATE_FORMAT(up.start_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
");
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* Main Content Styles */
.main-content {
  flex: 1;
  padding: 20px;
  background: #f5f7f9;
  margin-top:30px;
  margin-left: 130px;
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

/* Access Denied Styles */
.access-denied-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 60vh;
  padding: 20px;
}

.access-denied-card {
  background: #fff;
  border-radius: 12px;
  padding: 40px;
  text-align: center;
  box-shadow: 0 8px 24px rgba(0,0,0,0.1);
  max-width: 500px;
  width: 100%;
  border: 1px solid #e9ecef;
}

.access-denied-icon {
  font-size: 64px;
  color: #dc3545;
  margin-bottom: 20px;
}

.access-denied-card h2 {
  color: #dc3545;
  margin-bottom: 15px;
  font-size: 28px;
  font-weight: 600;
}

.access-denied-card p {
  color: #666;
  margin-bottom: 15px;
  line-height: 1.6;
}

.user-info-display {
  background: #f8f9fa;
  border-radius: 8px;
  padding: 20px;
  margin: 20px 0;
  border-left: 4px solid #dc3545;
}

.user-info-display p {
  margin-bottom: 8px;
  color: #333;
  font-size: 14px;
}

.user-info-display p:last-child {
  margin-bottom: 0;
}

.back-btn {
  background: #6c757d;
  color: white;
  border: none;
  padding: 12px 24px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 16px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s;
  margin-top: 20px;
}

.back-btn:hover {
  background: #5a6268;
  transform: translateY(-1px);
}

.back-btn i {
  font-size: 14px;
}

/* Earnings Summary Cards */
.earnings-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 20px;
  margin-bottom: 20px;
}

.summary-card {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  display: flex;
  align-items: center;
  gap: 20px;
}

.summary-card.total-earnings {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.summary-card.active-packages {
  background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
  color: white;
}

.card-icon {
  font-size: 48px;
  opacity: 0.8;
}

.card-content h3 {
  margin: 0 0 8px 0;
  font-size: 16px;
  font-weight: 500;
  opacity: 0.9;
}

.card-content .amount {
  font-size: 32px;
  font-weight: 700;
  margin-bottom: 4px;
}

.card-content p {
  margin: 0;
  font-size: 14px;
  opacity: 0.8;
}

/* Filter Form */
.filter-form {
  background: #f8f9fa;
  padding: 20px;
  border-radius: 8px;
  margin-bottom: 20px;
}

.date-inputs {
  display: flex;
  gap: 15px;
  align-items: end;
  flex-wrap: wrap;
}

.input-group {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.input-group label {
  font-weight: 500;
  color: #333;
  font-size: 14px;
}

.input-group input {
  padding: 8px 12px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
}

.filter-btn, .clear-btn {
  padding: 8px 16px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: 5px;
}

.filter-btn {
  background: #007bff;
  color: white;
}

.filter-btn:hover {
  background: #0056b3;
}

.clear-btn {
  background: #6c757d;
  color: white;
}

.clear-btn:hover {
  background: #545b62;
}

/* Download Section */
.download-section {
  border-top: 1px solid #eee;
  padding-top: 20px;
  margin-top: 20px;
}

.download-section h3 {
  margin-bottom: 15px;
  color: #333;
}

.download-buttons {
  display: flex;
  gap: 15px;
}

.download-btn {
  padding: 10px 20px;
  border-radius: 6px;
  text-decoration: none;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s;
}

.download-btn.pdf {
  background: #dc3545;
  color: white;
}

.download-btn.pdf:hover {
  background: #c82333;
}

.download-btn.excel {
  background: #28a745;
  color: white;
}

.download-btn.excel:hover {
  background: #218838;
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 20px;
}

.stat-card {
  background: #fff;
  border: 1px solid #e9ecef;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.stat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}

.stat-header h4 {
  margin: 0;
  color: #333;
  font-size: 18px;
}

.stat-badge {
  background: #e7f3ff;
  color: #0066cc;
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}

.stat-details {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.stat-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.stat-item .label {
  color: #666;
  font-size: 14px;
}

.stat-item .value {
  font-weight: 600;
  color: #333;
}

.no-data {
  text-align: center;
  color: #666;
  font-style: italic;
  grid-column: 1 / -1;
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

.status-badge.active {
  background: #e8f5e9;
  color: #2e7d32;
}

.status-badge.expired {
  background: #ffebee;
  color: #c62828;
}

/* Days Remaining/Expired Styles */
.days-remaining {
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 4px;
}

.days-remaining.normal {
  background: #e8f5e9;
  color: #2e7d32;
}

.days-remaining.warning {
  background: #fff3e0;
  color: #ef6c00;
  animation: pulse 2s infinite;
}

.days-expired {
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  background: #ffebee;
  color: #c62828;
}

@keyframes pulse {
  0% { opacity: 1; }
  50% { opacity: 0.7; }
  100% { opacity: 1; }
}

.text-center {
  text-align: center;
  color: #666;
  font-style: italic;
  padding: 40px 20px;
}

/* Chart Container */
.chart-container {
  position: relative;
  height: 400px;
  margin-top: 20px;
}

/* Sidebar Active Link */
.sidebar-link.active {
  background: #4a90e2;
  color: white;
}

.sidebar-link.active i {
  color: white;
}

/* Responsive Design */
@media (max-width: 992px) {
  .main-content {
    margin-left: 0;
  }
}

@media (max-width: 768px) {
  .main-content {
    padding: 15px;
  }
  
  .date-inputs {
    flex-direction: column;
    align-items: stretch;
  }
  
  .download-buttons {
    flex-direction: column;
  }
  
  .earnings-summary {
    grid-template-columns: 1fr;
  }
  
  .summary-card {
    padding: 20px;
  }
  
  .card-icon {
    font-size: 36px;
  }
  
  .card-content .amount {
    font-size: 24px;
  }
  
  .data-table {
    font-size: 14px;
  }
  
  .data-table th, 
  .data-table td {
    padding: 10px 8px;
  }
  
  .stats-grid {
    grid-template-columns: 1fr;
  }
  
  .access-denied-card {
    padding: 30px 20px;
  }
  
  .access-denied-icon {
    font-size: 48px;
  }
}

@media (max-width: 576px) {
  .main-content {
    padding: 10px;
  }
  
  .content-section {
    padding: 15px;
  }
  
  .summary-card {
    flex-direction: column;
    text-align: center;
    gap: 15px;
  }
  
  .filter-form {
    padding: 15px;
  }
  
  .chart-container {
    height: 300px;
  }
  
  .data-table {
    font-size: 12px;
  }
  
  .data-table th, 
  .data-table td {
    padding: 8px 6px;
  }
}
</style>

<main class="main-content">
  <?php if ($access_denied): ?>
    <!-- Access Denied Content -->
    <div class="access-denied-container">
      <div class="access-denied-card">
        <div class="access-denied-icon">
          <i class="fas fa-shield-alt"></i>
        </div>
        <h2>Access Restricted</h2>
        <p>This page is only accessible to administrators.</p>
        <p>Please contact your administrator if you need access to earnings reports.</p>
        <div class="user-info-display">
          <p><strong>Current User:</strong> <?= htmlspecialchars($name) ?></p>
          <p><strong>Role:</strong> <?= htmlspecialchars($type) ?></p>
        </div>
        <button onclick="goBack()" class="back-btn">
          <i class="fas fa-arrow-left"></i> Go Back
        </button>
      </div>
    </div>
  <?php else: ?>
    <!-- Earnings Summary Cards -->
    <div class="earnings-summary">
      <div class="summary-card total-earnings">
        <div class="card-icon">
          <i class="fas fa-dollar-sign"></i>
        </div>
        <div class="card-content">
          <h3>Total Earnings</h3>
          <div class="amount">$<?= number_format($total_earnings, 2) ?></div>
          <p><?= $start_date && $end_date ? "From $start_date to $end_date" : "All time" ?></p>
        </div>
      </div>
      
      <div class="summary-card active-packages">
        <div class="card-icon">
          <i class="fas fa-box-open"></i>
        </div>
        <div class="card-content">
          <h3>Active Packages</h3>
          <div class="amount"><?= $current_accepted_result->num_rows ?></div>
          <p>Currently accepted packages</p>
        </div>
      </div>
    </div>

    <!-- Date Filter Section -->
    <div class="content-section">
      <h2><i class="fas fa-filter"></i> Filter Reports</h2>
      <form method="GET" class="filter-form">
        <div class="date-inputs">
          <div class="input-group">
            <label for="start_date">Start Date:</label>
            <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
          </div>
          <div class="input-group">
            <label for="end_date">End Date:</label>
            <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
          </div>
          <button type="submit" class="filter-btn">
            <i class="fas fa-search"></i> Filter
          </button>
          <a href="total-earnings.php" class="clear-btn">
            <i class="fas fa-times"></i> Clear
          </a>
        </div>
      </form>
      
      <!-- Download Buttons -->
      <div class="download-section">
        <h3>Download Reports</h3>
        <div class="download-buttons">
          <a href="download_report.php?format=pdf&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" target="_blank" class="download-btn pdf">
            <i class="fas fa-file-pdf"></i> Download PDF
          </a>
          <a href="download_report.php?format=excel&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="download-btn excel">
            <i class="fas fa-file-excel"></i> Download Excel
          </a>
        </div>
      </div>
    </div>

    <!-- Package Statistics -->
    <div class="content-section">
      <h2><i class="fas fa-chart-bar"></i> Package Statistics</h2>
      <div class="stats-grid">
        <?php if($package_stats->num_rows > 0): ?>
          <?php while($stat = $package_stats->fetch_assoc()): ?>
            <div class="stat-card">
              <div class="stat-header">
                <h4><?= htmlspecialchars($stat['package_name']) ?></h4>
                <span class="stat-badge"><?= $stat['total_activated'] ?> Active</span>
              </div>
              <div class="stat-details">
                <div class="stat-item">
                  <span class="label">Unit Price:</span>
                  <span class="value">$<?= number_format($stat['unit_price'], 2) ?></span>
                </div>
                <div class="stat-item">
                  <span class="label">Total Revenue:</span>
                  <span class="value">$<?= number_format($stat['total_revenue'], 2) ?></span>
                </div>
              </div>
            </div>
          <?php endwhile; ?>
        <?php else: ?>
          <p class="no-data">No package statistics available for the selected period.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Current/Active Accepted Package Requests Table -->
    <div class="content-section">
      <h2><i class="fas fa-check-circle"></i> Active Accepted Packages</h2>
      <p>Currently active package subscriptions (end date is in the future)</p>
      
      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>User</th>
              <th>Package</th>
              <th>Price</th>
              <th>Duration</th>
              <th>Start Date</th>
              <th>End Date</th>
              <th>Status</th>
              <th>Days Remaining</th>
            </tr>
          </thead>
          <tbody>
<?php 
if($current_accepted_result->num_rows > 0) {
    while($package = $current_accepted_result->fetch_assoc()): 
        $end_date = strtotime($package['end_date']);
        $current_date = time();
        $days_remaining = ceil(($end_date - $current_date) / (60 * 60 * 24));
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
                <span class="package-badge <?= strtolower($package['status']) ?>"><?= htmlspecialchars($package['package_name']) ?></span>
              </td>
              <td>
                <span class="price-badge">$<?= $package['price'] ? number_format($package['price'], 2) : '0.00' ?></span>
              </td>
              <td><?= $package['duration'] ?> days</td>
              <td>
                <?= $package['start_date'] != '0000-00-00 00:00:00' ? date('Y-m-d H:i', strtotime($package['start_date'])) : 'Not set' ?>
              </td>
              <td>
                <?= $package['end_date'] != '0000-00-00 00:00:00' ? date('Y-m-d H:i', strtotime($package['end_date'])) : 'Not set' ?>
              </td>
              <td>
                <span class="status-badge active">
                  <i class="fas fa-check-circle"></i> Active
                </span>
              </td>
              <td>
                <span class="days-remaining <?= $days_remaining <= 7 ? 'warning' : 'normal' ?>">
                  <?= $days_remaining ?> days
                </span>
              </td>
            </tr>
<?php 
    endwhile; 
} else {
    echo '<tr><td colspan="9" class="text-center">No active accepted packages found for the selected period</td></tr>';
}
?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Past/Expired Accepted Package Requests Table -->
    <div class="content-section">
      <h2><i class="fas fa-history"></i> Past Accepted Packages</h2>
      <p>Expired package subscriptions (end date has passed)</p>
      
      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>User</th>
              <th>Package</th>
              <th>Price</th>
              <th>Duration</th>
              <th>Start Date</th>
              <th>End Date</th>
              <th>Status</th>
              <th>Expired Days</th>
            </tr>
          </thead>
          <tbody>
<?php 
if($past_accepted_result->num_rows > 0) {
    while($package = $past_accepted_result->fetch_assoc()): 
        $end_date = strtotime($package['end_date']);
        $current_date = time();
        $expired_days = ceil(($current_date - $end_date) / (60 * 60 * 24));
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
                <span class="package-badge <?= strtolower($package['status']) ?>"><?= htmlspecialchars($package['package_name']) ?></span>
              </td>
              <td>
                <span class="price-badge">$<?= $package['price'] ? number_format($package['price'], 2) : '0.00' ?></span>
              </td>
              <td><?= $package['duration'] ?> days</td>
              <td>
                <?= $package['start_date'] != '0000-00-00 00:00:00' ? date('Y-m-d H:i', strtotime($package['start_date'])) : 'Not set' ?>
              </td>
              <td>
                <?= $package['end_date'] != '0000-00-00 00:00:00' ? date('Y-m-d H:i', strtotime($package['end_date'])) : 'Not set' ?>
              </td>
              <td>
                <span class="status-badge expired">
                  <i class="fas fa-times-circle"></i> Expired
                </span>
              </td>
              <td>
                <span class="days-expired">
                  <?= $expired_days ?> days ago
                </span>
              </td>
            </tr>
<?php 
    endwhile; 
} else {
    echo '<tr><td colspan="9" class="text-center">No expired packages found for the selected period</td></tr>';
}
?>
          </tbody>
        </table>
      </div>
    </div>

   
  <?php endif; ?>
</main>

<script>
// Chart.js for Monthly Earnings
const ctx = document.getElementById('earningsChart').getContext('2d');

// Get monthly data from PHP
const monthlyData = [
<?php 
$months = [];
$earnings = [];
if($monthly_earnings->num_rows > 0) {
    while($row = $monthly_earnings->fetch_assoc()) {
        $months[] = "'" . date('M Y', strtotime($row['month'] . '-01')) . "'";
        $earnings[] = $row['earnings'] ?? 0;
    }
}
echo implode(',', $months);
?>
];

const earningsData = [<?= implode(',', array_reverse($earnings)) ?>];

const earningsChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: monthlyData.reverse(),
        datasets: [{
            label: 'Monthly Earnings ($)',
            data: earningsData,
            borderColor: '#4a90e2',
            backgroundColor: 'rgba(74, 144, 226, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#4a90e2',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            }
        },
        elements: {
            point: {
                hoverRadius: 8
            }
        }
    }
});

// Go back function for access denied
function goBack() {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        window.location.href = 'index.php';
    }
}

// Menu toggle functionality
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');

if (menuToggle && sidebar) {
    menuToggle.addEventListener('click', () => {
        sidebar.classList.toggle('active');
    });
}
</script>

</body>
</html>