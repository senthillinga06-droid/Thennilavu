<?php
session_start();

if (!isset($_SESSION['staff_id'])) { 
    header('Location: login.php'); 
    exit; 
}
$staff_name = $_SESSION['name'] ?? 'Admin';
$staff_type = ucfirst($_SESSION['role'] ?? 'staff');
// Backwards compatibility for template fragments still using $name / $type
if (!isset($name)) $name = $staff_name;
if (!isset($type)) $type = $staff_type;

/* DB config - change as needed */
$host = "localhost";
$user = "thennilavu_matrimonial";
$pass = "OYVuiEKfS@FQ";
$db = "thennilavu_thennilavu";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) { 
    http_response_code(500); 
    error_log('DB connect error: '.$conn->connect_error); 
    die("Database connection failed"); 
}

/* ---- helpers ---- */
function json_out($data){
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}
function parse_dt_local($s){
    if (!$s) return date('Y-m-d H:i:s');
    $s = str_replace('T',' ',$s);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/',$s)) $s .= ' 00:00:00';
    return $s;
}

/* ---- API routing ---- */
$action = $_GET['action'] ?? null;

/* Add call */
if ($action === 'add_call' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $member_name = trim($_POST['member_name'] ?? '');
    $call_date = parse_dt_local($_POST['call_date'] ?? '');
    $duration_raw = $_POST['duration'] ?? '';
    $duration = ($duration_raw === '' ? 0 : (int)$duration_raw);
    $notes = trim($_POST['notes'] ?? '');

    // Server-side: prevent past scheduling (allow small 30s skew tolerance)
    $call_ts = strtotime($call_date);
    if ($call_ts !== false && $call_ts < (time() - 30)) {
        json_out(['status'=>'error','message'=>'Call Date & Time must be now or future']);
    }

    if ($phone === '') json_out(['status'=>'error','message'=>'Phone required']);
    // Always start as Scheduled regardless of duration; we now STORE the entered (planned) duration value.
    // The call is only Completed when the complete_call action is invoked.
    $status = 'Scheduled';

    $sql = "INSERT INTO calls (caller_id, member_id, member_name, call_phone, call_date, duration, status, notes)
      VALUES (NULL, NULL, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) json_out(['status'=>'error','message'=>'DB prepare failed: '.$conn->error]);
    // params: member_name (s), phone (s), call_date (s), duration (i), status (s), notes (s)
    $stmt->bind_param('sssiss', $member_name, $phone, $call_date, $duration, $status, $notes);
    if ($stmt->execute()) {
        $id = $stmt->insert_id;
        $stmt->close();

        // Compose email to staff about the scheduled call (no reminder fields)
        $subject = "New Scheduled Call (#{$id}) — {$phone}";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $call_date_display = $call_date ?: date('Y-m-d H:i:s');
        $html = "<p>A new call has been scheduled.</p>
         <p><strong>Call ID:</strong> {$id}<br>
         <strong>Phone:</strong> ".htmlspecialchars($phone)."<br>
         <strong>Member name:</strong> ".htmlspecialchars($member_name ?: '—')."<br>
         <strong>Call date:</strong> {$call_date_display}<br>
         <strong>Planned Duration:</strong> ".($duration>0?intval($duration).' min':'—')."<br>
         <strong>Notes:</strong> ".nl2br(htmlspecialchars($notes ?: '—'))."</p>
         <p>View it in the admin panel: <a href='{$scheme}://{$host}/admin/call-management.php'>Call Management</a></p>
         <p>— Matrimony Admin</p>";

        $mail_error = null;
        // keep existing helper (if present elsewhere)
        $mail_ok = function_exists('send_email_to_staff') ? send_email_to_staff($conn, $subject, $html, $mail_error) : false;

        if ($mail_ok) {
            json_out(['status'=>'success','message'=>'Call saved as Scheduled and staff notified by email','call_id'=>$id,'status_label'=>$status]);
        }
        // Email failed; degrade gracefully without scaring end-user (omit explicit failure unless debug requested)
        $debug = isset($_GET['debug_mail']);
        json_out([
          'status'=>'success',
          'message'=>'Call saved as Scheduled' . ($debug ? ' (email failed)' : ''),
          'call_id'=>$id,
          'status_label'=>$status,
          'mail_error'=>$debug ? $mail_error : null
        ]);
    } else {
        $err = $stmt->error; $stmt->close(); json_out(['status'=>'error','message'=>$err]);
    }
}

/* Complete call: mark Completed (no prompts). Optional: duration & talking_content if provided. */
if ($action === 'complete_call' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $call_id = (int)($_POST['call_id'] ?? 0);
    if ($call_id <= 0) json_out(['status'=>'error','message'=>'Invalid call id']);

    $has_duration = array_key_exists('duration', $_POST) && $_POST['duration'] !== '';
    $duration = $has_duration ? (int)$_POST['duration'] : null;
    $talk = isset($_POST['talking_content']) ? trim($_POST['talking_content']) : '';

    if (!$has_duration && $talk === '') {
        $stmt = $conn->prepare("UPDATE calls SET status='Completed' WHERE call_id=?");
        if (!$stmt) json_out(['status'=>'error','message'=>'DB prepare failed']);
        $stmt->bind_param('i',$call_id);
        if ($stmt->execute()) { $stmt->close(); json_out(['status'=>'success','message'=>'Marked Completed']); }
        $err = $stmt->error; $stmt->close(); json_out(['status'=>'error','message'=>$err]);
    } else {
        $parts = []; $types=''; $vals=[];
        if ($has_duration) { $parts[] = "duration=?"; $types .= 'i'; $vals[] = $duration; }
        if ($talk !== '') { $parts[] = "notes = CONCAT(COALESCE(notes,''), ?)"; $types .= 's'; $vals[] = "\n[Talk] ".$talk; }
        $parts[] = "status='Completed'";
        $sql = "UPDATE calls SET ".implode(', ',$parts)." WHERE call_id=?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) json_out(['status'=>'error','message'=>'DB prepare failed']);
        if ($types !== '') {
            $types .= 'i'; $vals[] = $call_id;
            $stmt->bind_param($types, ...$vals);
        } else {
            $stmt->bind_param('i', $call_id);
        }
        if ($stmt->execute()) { $stmt->close(); json_out(['status'=>'success','message'=>'Updated and marked Completed']); }
        $err = $stmt->error; $stmt->close(); json_out(['status'=>'error','message'=>$err]);
    }
}

/* Get calls (for table) */
if ($action === 'get_calls') {
    $q = trim($_GET['q'] ?? '');
    $sql = "SELECT call_id, call_date, duration, call_phone, 
             CASE WHEN status='Scheduled' AND call_date < (NOW() - INTERVAL 2 HOUR) THEN 'Missed' ELSE status END AS status,
             member_name
        FROM calls";
    $params = [];
    $types = '';
    
    if ($q !== '') {
        $like = '%' . $q . '%';
        $sql .= " WHERE (COALESCE(member_name,'') LIKE ? OR call_phone LIKE ?)";
        $types = 'ss';
        $params = [$like, $like];
    }
    $sql .= " ORDER BY FIELD(status,'Scheduled','Missed','Completed'), call_date DESC, call_id DESC LIMIT 500";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) json_out(['status'=>'error','message'=>'DB prepare failed']);
    
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) $out[] = $r;
    $stmt->close();
    json_out($out);
}

/* Get history by phone */
if ($action === 'get_history') {
    $phone = trim($_GET['phone'] ?? '');
    if ($phone === '') json_out(['status'=>'error','message'=>'Phone required']);
    $stmt = $conn->prepare("SELECT call_id, call_date, duration, status, notes FROM calls WHERE call_phone=? ORDER BY call_date DESC, call_id DESC LIMIT 500");
    if (!$stmt) json_out(['status'=>'error','message'=>'DB prepare failed']);
    $stmt->bind_param('s',$phone); $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        // Only mark Missed if still Scheduled and past 1 hour; do NOT auto-complete based on duration
        if ($row['status']==='Scheduled' && strtotime($row['call_date']) < time()-3600) {
          $row['status'] = 'Missed';
        }
        $out[] = $row;
    }
    $stmt->close(); json_out($out);
}

/* Export CSV for phone history */
if ($action === 'export_csv') {
    $phone = trim($_GET['phone'] ?? '');
    if ($phone === '') { header('Content-Type:text/plain'); echo 'Phone required'; exit; }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.preg_replace('/[^a-z0-9_]+/i','_', $phone).'_calls.csv"');
    $out = fopen('php://output','w');
    fputcsv($out, ['call_id','call_date','duration','status','phone','notes']);
    $stmt = $conn->prepare("SELECT call_id, call_date, duration, status, notes FROM calls WHERE call_phone=? ORDER BY call_date DESC");
    $stmt->bind_param('s',$phone); $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) fputcsv($out, [$row['call_id'],$row['call_date'],$row['duration'],$row['status'],$phone,$row['notes']]);
    fclose($out); $stmt->close(); exit;
}

/* If no API match, render UI below */
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Call Management — Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<link rel="stylesheet" href="call.css">
<style>
/* minimal inline styles for modal & badges if your CSS missing */
.modal { display:none; position:fixed; left:0; top:0; right:0; bottom:0; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:9999; }
.modal-content { background:#fff; padding:1rem; width:90%; max-width:900px; border-radius:6px; box-shadow:0 6px 24px rgba(0,0,0,.2); }
.status-badge { padding:.25rem .5rem; border-radius:4px; font-weight:600; font-size:.85rem; display:inline-block; }
.status-completed { background:#e6ffed; color:#0a7a2b; }
.status-missed { background:#ffecec; color:#9a1c1c; }
.status-scheduled { background:#eef6ff; color:#0b4d8b; }
.btn { padding:.35rem .6rem; border-radius:6px; border:1px solid rgba(0,0,0,.08); background:#fff; cursor:pointer; }
.btn-sm { font-size:.85rem; padding:.25rem .4rem; }
.btn-primary { background:#0b6efd; color:#fff; border:0; }
.btn-secondary { background:#f3f4f6; }
.btn-success { background:#16a34a; color:#fff; }
.table { width:100%; border-collapse:collapse; }
.table th, .table td { padding:.5rem .6rem; border-bottom:1px solid #eee; text-align:left; }
.table-container { overflow:auto; max-height: 420px; }
.card { background:#fff; padding:1rem; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,.05); margin-bottom:1rem; }
.form-grid { display:grid; grid-template-columns: repeat(3, 1fr); gap:.75rem; }
.form-group { display:flex; flex-direction:column; }
.form-actions { margin-top:.75rem; display:flex; gap:.5rem; }
@media (max-width:900px){ .form-grid { grid-template-columns: 1fr; } }
</style>

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
      background: var(--primary-light);
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

    .welcome-section {
      background: white;
      border-radius: var(--border-radius);
      padding: 2rem;
      box-shadow: var(--shadow);
      margin-bottom: 2rem;
    }

    .welcome-section h1 {
      font-size: 1.75rem;
      margin-bottom: 0.5rem;
      color: var(--dark);
    }

    .welcome-section p {
      color: var(--gray);
      margin-bottom: 2rem;
    }

    /* Quick Stats */
    .quick-stats {
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
      flex-direction: column;
    }

    .stat-card h3 {
      font-size: 0.9rem;
      font-weight: 500;
      color: var(--gray);
      margin-bottom: 0.5rem;
    }

    .stat-card p {
      font-size: 2rem;
      font-weight: 700;
      color: var(--primary);
      margin: 0;
    }

    .stat-icon {
      align-self: flex-end;
      margin-top: 1rem;
      width: 50px;
      height: 50px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: rgba(79, 70, 229, 0.1);
      color: var(--primary);
      font-size: 1.5rem;
    }

    /* Activity Section */
    .activity-section {
      background: white;
      border-radius: var(--border-radius);
      padding: 1.5rem;
      box-shadow: var(--shadow);
    }

    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
    }

    .section-title {
      font-size: 1.25rem;
      font-weight: 600;
      color: var(--dark);
    }

    .view-all {
      color: var(--primary);
      text-decoration: none;
      font-weight: 500;
      font-size: 0.875rem;
    }

    .activity-list {
      list-style: none;
    }

    .activity-item {
      display: flex;
      align-items: center;
      padding: 1rem 0;
      border-bottom: 1px solid var(--gray-light);
    }

    .activity-item:last-child {
      border-bottom: none;
    }

    .activity-icon {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 1rem;
      background-color: rgba(79, 70, 229, 0.1);
      color: var(--primary);
    }

    .activity-content {
      flex: 1;
    }

    .activity-title {
      font-weight: 500;
      margin-bottom: 0.25rem;
    }

    .activity-time {
      font-size: 0.75rem;
      color: var(--gray);
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
      
      .quick-stats {
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
    <?php if ($_SESSION['role'] !== 'admin') : ?>
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

  <div class="dashboard-layout" style="display:flex; gap:1rem; padding:1rem;">
     <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="matrimony-name">Matrimony Admin Panel</div>
    <ul class="sidebar-menu">
      <li><a href="index.php" class="sidebar-link"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="members.php" class="sidebar-link"><i class="fas fa-users"></i> Manage Members</a></li>
      <li><a href="call-management.php" class="sidebar-link active"><i class="fas fa-phone"></i> Call Management</a></li>
      <li><a href="user-message-management.php" class="sidebar-link"><i class="fas fa-comments"></i> User Messages</a></li>
      <li><a href="review-management.php" class="sidebar-link"><i class="fas fa-star"></i> Review Management</a></li>
      <li><a href="transaction-management.php" class="sidebar-link"><i class="fas fa-receipt"></i> Transactions</a></li>
      <li><a href="packages-management.php" class="sidebar-link"><i class="fas fa-box"></i> Packages</a></li>
      <li><a href="blog-management.php" class="sidebar-link"><i class="fas fa-blog"></i> Blog Management</a></li>
      <li><a href="total-earnings.php" class="sidebar-link"><i class="fas fa-chart-line"></i> Total Earnings</a></li>
      <li><a href="staff.php" class="sidebar-link" id="staffLink"><i class="fas fa-user-shield"></i> Staff Management</a></li>
    </ul>
  </aside>

    <!-- Main Content -->
    <main class="page-container" style="flex:1;">
      <div class="page-header">
        <h1 class="page-title">Call Management</h1>
      </div>

      <!-- Add Call Form -->
      <div class="card">
        <h2 class="card-title">Add New Call</h2>
        <form id="callForm">
          <div class="form-grid">
            <div class="form-group">
              <label for="phone">Phone Number *</label>
              <input type="text" id="phone" class="form-control" placeholder="Enter phone number" required>
            </div>
            <div class="form-group">
              <label for="member_name">Member Name</label>
              <input type="text" id="member_name" class="form-control" placeholder="Enter member name">
            </div>
            <div class="form-group">
              <label for="call_date">Call Date & Time</label>
              <input type="datetime-local" id="call_date" class="form-control" required>
            </div>
            <div class="form-group">
              <label for="duration">Duration (minutes)</label>
              <input type="number" id="duration" class="form-control" placeholder="Duration in minutes" min="0">
            </div>
            <div class="form-group" style="grid-column: 1 / -1;">
              <label for="notes">Notes</label>
              <textarea id="notes" class="form-control" placeholder="Additional notes" rows="3"></textarea>
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save"></i> Save Call
            </button>
            <button type="button" id="resetBtn" class="btn btn-secondary">
              <i class="fas fa-redo"></i> Reset Form
            </button>
          </div>
        </form>
      </div>

      <!-- Search and Controls -->
      <div class="card">
        <div class="search-container" style="display:flex; gap:1rem; align-items:end;">
          <div class="form-group search-input" style="flex:1;">
            <label for="q">Search Calls</label>
            <input type="text" id="q" class="form-control" placeholder="Search by phone number or member name">
          </div>
        </div>
      </div>

      <!-- Calls Table -->
      <div class="card">
        <h2 class="card-title">Call History</h2>
        <div class="table-container">
          <table class="table" id="callsTable">
            <thead>
              <tr>
                <th>ID</th>
                <th>Member</th>
                <th>Phone</th>
                <th>Date & Time</th>
                <th>Duration</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </main>
  </div>

  <!-- History Modal -->
  <div id="historyModal" class="modal">
    <div class="modal-content">
      <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center;">
        <h3 class="modal-title" id="histTitle">Call History</h3>
        <button id="closeHist" class="btn btn-secondary btn-sm">
          <i class="fas fa-times"></i> Close
        </button>
      </div>
      <div class="modal-body">
        <div class="table-container">
          <table class="table" id="histTable">
            <thead>
              <tr>
                <th>Call ID</th>
                <th>Date & Time</th>
                <th>Duration</th>
                <th>Status</th>
                <th>Notes</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer" style="margin-top:.75rem;">
        <button id="exportHist" class="btn btn-primary">
          <i class="fas fa-download"></i> Export CSV
        </button>
      </div>
    </div>
  </div>

<script>
/* JavaScript for functionality (improved JSON error debugging) */
const $ = sel => document.querySelector(sel);
const callsTbody = document.querySelector('#callsTable tbody');
const histModal = document.getElementById('historyModal');
const histTableBody = document.querySelector('#histTable tbody');
const histTitle = document.getElementById('histTitle');

// Format minutes display
function fmtMin(n){ 
  return (!n || n==0) ? '—' : (n + ' min'); 
}

// Get status badge class
function statusClass(s){ 
  if(!s) return ''; 
  s=s.toLowerCase(); 
  if(s==='completed') return 'status-completed'; 
  if(s==='missed') return 'status-missed'; 
  if(s==='scheduled') return 'status-scheduled';
  return ''; 
}

// Format date for display
function formatDate(dt) {
  if (!dt) return '—';
  const date = new Date(dt);
  return date.toLocaleString();
}

// Load calls data
async function loadCalls(q=''){
  try {
    const res = await fetch('?action=get_calls' + (q ? '&q='+encodeURIComponent(q) : ''));
    const data = await res.json();
    callsTbody.innerHTML = '';
    
    if (data.length === 0) {
      callsTbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 2rem;">No calls found</td></tr>';
      return;
    }
    
    data.forEach(r => {
      const tr = document.createElement('tr');
      const status = r.status || '—';
      
      tr.innerHTML = `
        <td>${r.call_id}</td>
        <td>${r.member_name || '—'}</td>
        <td>${r.call_phone}</td>
        <td>${formatDate(r.call_date)}</td>
        <td>${fmtMin(r.duration)}</td>
        <td><span class="status-badge ${statusClass(status)}">${status}</span></td>
        <td class="actions-cell">
          <button class="btn btn-secondary btn-sm btn-history" data-phone="${r.call_phone}" data-name="${r.member_name||''}">
            <i class="fas fa-history"></i> History
          </button>
          <button class="btn btn-secondary btn-sm btn-export" data-phone="${r.call_phone}">
            <i class="fas fa-file-csv"></i> CSV
          </button>
          ${status !== 'Completed' ? `
            <button class="btn btn-success btn-sm btn-complete" data-id="${r.call_id}">
              <i class="fas fa-check"></i> Complete
            </button>
          ` : ''}
        </td>`;
      callsTbody.appendChild(tr);
    });
  } catch (error) {
    console.error('Error loading calls:', error);
    callsTbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 2rem; color: var(--danger);">Error loading data</td></tr>';
  }
}

// Save new call (improved: raw response reading + JSON parse fallback)
document.getElementById('callForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const phone = document.getElementById('phone').value.trim();
  if (!phone) {
    alert('Phone number is required');
    return;
  }
  // Client-side validation for future or current datetime
  const cdInput = document.getElementById('call_date');
  if (cdInput.value) {
    const sel = new Date(cdInput.value);
    const now = new Date();
    if (sel.getTime() < now.getTime() - 30000) { // 30s grace
      alert('Call Date & Time must be now or future');
      return;
    }
  }
  
  const submitBtn = e.target.querySelector('button[type="submit"]');
  const originalText = submitBtn.innerHTML;
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
  submitBtn.disabled = true;
  
  try {
    const fd = new FormData();
    fd.append('phone', phone);
    fd.append('member_name', document.getElementById('member_name').value.trim());
    fd.append('call_date', document.getElementById('call_date').value);
    fd.append('duration', document.getElementById('duration').value);
    fd.append('notes', document.getElementById('notes').value.trim());
    
    const r = await fetch('?action=add_call', { method: 'POST', body: fd });
    const text = await r.text();
    let j;
    try {
      j = JSON.parse(text);
    } catch (parseErr) {
      console.error('Server returned non-JSON response:', text);
      alert('Server error — check console or server logs. Response preview:\n\n' + text.slice(0,1000));
      return;
    }
    
    if (j.status === 'success') {
      document.getElementById('callForm').reset();
      // Reset the call date to current time
      const now = new Date();
      now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
      document.getElementById('call_date').value = now.toISOString().slice(0,16);
      loadCalls();
    }
    
    alert(j.message || (j.status === 'success' ? 'Call saved successfully' : 'Error saving call'));
  } catch (error) {
    console.error('Error saving call:', error);
    alert('Error saving call. Please try again.');
  } finally {
    submitBtn.innerHTML = originalText;
    submitBtn.disabled = false;
  }
});

// Reset form
document.getElementById('resetBtn').addEventListener('click', () => {
  document.getElementById('callForm').reset();
  setCallDateNow();
});

function setCallDateNow(){
  const input = document.getElementById('call_date');
  if (!input) return;
  const now = new Date();
  now.setSeconds(0,0);
  // Adjust timezone for datetime-local (expects local)
  const local = new Date(now.getTime() - now.getTimezoneOffset()*60000);
  const iso = local.toISOString().slice(0,16);
  input.min = iso; // enforce no past selection
  if (!input.value || input.value < iso) input.value = iso;
}

// Search functionality
document.getElementById('q').addEventListener('input', e => {
  loadCalls(e.target.value.trim());
});

// Delegated table actions (complete + history + export)
// Improved to parse non-JSON for complete_call as well.
document.addEventListener('click', async (ev) => {
  const h = ev.target.closest('.btn-history');
  const ex = ev.target.closest('.btn-export');
  const cm = ev.target.closest('.btn-complete');

  if (h) {
    const phone = h.dataset.phone;
    const name = h.dataset.name || phone;
    openHistory(phone, name);
  }

  if (ex) {
    const phone = ex.dataset.phone;
    window.location = '?action=export_csv&phone=' + encodeURIComponent(phone);
  }

  if (cm) {
    const id = cm.dataset.id;
    if (!confirm('Mark this call as Completed?')) return;
    
    const btn = ev.target.closest('.btn-complete');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;
    
    try {
      const fd = new FormData(); 
      fd.append('call_id', id);
      const res = await fetch('?action=complete_call', { method:'POST', body: fd });
      const text = await res.text();
      let j;
      try {
        j = JSON.parse(text);
      } catch (parseErr) {
        console.error('Server returned non-JSON response for complete_call:', text);
        alert('Server error — check console or server logs. Response preview:\n\n' + text.slice(0,1000));
        return;
      }
      
      if (j.status === 'success') {
        // Update row in DOM without full reload
        const row = btn.closest('tr');
        if (row) {
          // Update status cell
          const statusCell = row.querySelector('.status-badge');
          if (statusCell) { 
            statusCell.textContent = 'Completed'; 
            statusCell.className = 'status-badge status-completed';
          }
          // Remove Complete button
          btn.remove();
        }
      }
      
      alert(j.message || (j.status === 'success' ? 'Call marked as completed' : 'Error updating call'));
    } catch (error) {
      console.error('Error completing call:', error);
      alert('Error completing call. Please try again.');
    } finally {
      // note: if we removed the button above, originalText replace won't find btn
      try { btn.innerHTML = originalText; btn.disabled = false; } catch(e){}
    }
  }
});

// Open history modal and populate table
async function openHistory(phone, name) {
  histTitle.textContent = `${name} — ${phone}`;
  histTableBody.innerHTML = '';
  
  try {
    const res = await fetch('?action=get_history&phone=' + encodeURIComponent(phone));
    const data = await res.json();
    
    if (data.length === 0) {
      histTableBody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 2rem;">No call history found</td></tr>';
    } else {
      data.forEach(r => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${r.call_id}</td>
          <td>${formatDate(r.call_date)}</td>
          <td>${fmtMin(r.duration)}</td>
          <td><span class="status-badge ${statusClass(r.status)}">${r.status}</span></td>
          <td>${(r.notes||'')}</td>`;
        histTableBody.appendChild(tr);
      });
    }
    
    document.getElementById('exportHist').onclick = () => window.location = '?action=export_csv&phone=' + encodeURIComponent(phone);
    histModal.style.display = 'flex';
  } catch (error) {
    console.error('Error loading history:', error);
    histTableBody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 2rem; color: var(--danger);">Error loading history</td></tr>';
    histModal.style.display = 'flex';
  }
}

// Close history modal
document.getElementById('closeHist').addEventListener('click', () => {
  histModal.style.display = 'none';
});

// Close modal when clicking outside
histModal.addEventListener('click', (e) => {
  if (e.target === histModal) {
    histModal.style.display = 'none';
  }
});

// Mobile menu toggle
document.getElementById('menuToggle').addEventListener('click', () => {
  document.getElementById('sidebar').classList.toggle('active');
});

// Initialize page
(function init(){
  setCallDateNow();
  // Refresh min every minute in case page left open
  setInterval(setCallDateNow, 60000);
  loadCalls();
})();
</script>
</body>
</html>