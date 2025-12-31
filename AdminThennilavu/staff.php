<?php
// Temporarily enable error display to diagnose HTTP 500 issues.
// Remove or set to 0 in production after debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Database connection (moved up so token-based email handlers can run without an admin session)
$host = "localhost";
$user = "thennilavu_matrimonial";
$pass = "OYVuiEKfS@FQ";
$db = "thennilavu_thennilavu";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: ".$conn->connect_error);

// Token-based approve/reject via email (allow approving from email link without admin session)
if (isset($_GET['action']) && ($_GET['action'] === 'approve_request' || $_GET['action'] === 'reject_request') && isset($_GET['request_id']) && isset($_GET['token'])) {
  $email_action = $_GET['action'];
  $request_id = (int)$_GET['request_id'];
  $token = $_GET['token'];

  // Fetch the request and verify token
  $rq = $conn->prepare("SELECT id, staff_id, ip, status FROM staff_login_requests WHERE id = ? AND token = ? LIMIT 1");
  if ($rq) {
    $rq->bind_param('is', $request_id, $token);
    $rq->execute();
    $rres = $rq->get_result();
    $rrow = $rres->fetch_assoc();
    $rq->close();
  } else {
    $rrow = null;
  }

  if (!$rrow) {
    http_response_code(404);
    echo "<h2>Invalid or expired approval link.</h2>";
    exit;
  }

  // Only allow action on pending requests
  if ($rrow['status'] !== 'pending') {
    echo "<h2>This request has already been processed.</h2>";
    exit;
  }

  // Approve flow
  if ($email_action === 'approve_request') {
    $ip_to_approve = $rrow['ip'] ?? null;
    // If IP blocked, mark blocked
    $is_blocked = false;
    if ($ip_to_approve) {
      $chk = $conn->prepare("SELECT id FROM blocked_ips WHERE ip = ? LIMIT 1");
      if ($chk) {
        $chk->bind_param('s', $ip_to_approve);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) $is_blocked = true;
        $chk->close();
      }
    }

    if ($is_blocked) {
      $u = $conn->prepare("UPDATE staff_login_requests SET status='blocked', approved_by=NULL, approved_at=NOW() WHERE id = ?");
      if ($u) { $u->bind_param('i', $request_id); $u->execute(); $u->close(); }
      // log
      $logDir = __DIR__ . DIRECTORY_SEPARATOR . 'logs'; if (!is_dir($logDir)) @mkdir($logDir,0755,true);
      $event = ['type'=>'approve_via_email_blocked','request_id'=>$request_id,'ip'=>$ip_to_approve,'time'=>date('c')];
      @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'staff_events.log', json_encode($event)."\n", FILE_APPEND | LOCK_EX);
      echo "<h2>IP is blocked. Request marked as blocked.</h2>";
      exit;
    }

    // Mark approved
    $stmt = $conn->prepare("UPDATE staff_login_requests SET status='approved', approved_by=NULL, approved_at=NOW() WHERE id = ?");
    if ($stmt) {
      $stmt->bind_param('i', $request_id);
      $stmt->execute();
      $stmt->close();
    }

    // Add to approved_ips if IP present
    if ($ip_to_approve) {
      $ip_stmt = $conn->prepare("INSERT INTO approved_ips (ip, created_by) VALUES (?, NULL) ON DUPLICATE KEY UPDATE ip=ip");
      if ($ip_stmt) { $ip_stmt->bind_param('s', $ip_to_approve); $ip_stmt->execute(); $ip_stmt->close(); }
    }

    // log
    $logDir = __DIR__ . DIRECTORY_SEPARATOR . 'logs'; if (!is_dir($logDir)) @mkdir($logDir,0755,true);
    $event = ['type'=>'approve_via_email','request_id'=>$request_id,'ip'=>$ip_to_approve,'time'=>date('c')];
    @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'staff_events.log', json_encode($event)."\n", FILE_APPEND | LOCK_EX);

    echo "<h2>Request approved. The staff login has been allowed from this IP.</h2>";
    exit;
  }

  // Reject flow
  if ($email_action === 'reject_request') {
    $stmt = $conn->prepare("UPDATE staff_login_requests SET status='rejected', approved_by=NULL, approved_at=NOW() WHERE id = ?");
    if ($stmt) { $stmt->bind_param('i', $request_id); $stmt->execute(); $stmt->close(); }
    // log
    $logDir = __DIR__ . DIRECTORY_SEPARATOR . 'logs'; if (!is_dir($logDir)) @mkdir($logDir,0755,true);
    $event = ['type'=>'reject_via_email','request_id'=>$request_id,'time'=>date('c')];
    @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'staff_events.log', json_encode($event)."\n", FILE_APPEND | LOCK_EX);
    echo "<h2>Request rejected. The staff will not be allowed to login from this IP.</h2>";
    exit;
  }
}

require_once 'verify_session.php';

// Check if user is logged in
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

// Ensure approved_ips table exists so admin UI and queries work even if login.php hasn't run
$conn->query("CREATE TABLE IF NOT EXISTS approved_ips (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) UNIQUE,
  created_by INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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

// Helper to sanitize POST safely
function get_post($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : null;
}

// Produce a friendly device name from a user agent string
function friendly_device_from_ua($ua) {
  if (!$ua) return null;
  $s = strtolower($ua);
  if (strpos($s, 'samsung') !== false || preg_match('/\bsm-[a-z0-9-]+\b/i', $ua)) {
    if (preg_match('/(SM-[A-Z0-9-]+)/i', $ua, $m)) return 'Samsung ' . $m[1];
    if (preg_match('/samsung[\s\/\-]?([a-z0-9 ]+)/i', $ua, $m)) return 'Samsung ' . trim($m[1]);
    return 'Samsung device';
  }
  if (strpos($s, 'iphone') !== false) return 'iPhone';
  if (strpos($s, 'ipad') !== false) return 'iPad';
  if (strpos($s, 'pixel') !== false) return 'Google Pixel';
  if (strpos($s, 'huawei') !== false) return 'Huawei device';
  if (strpos($s, 'oneplus') !== false) return 'OnePlus device';
  if (strpos($s, 'mi ' ) !== false || strpos($s, 'xiaomi') !== false) return 'Xiaomi device';
  if (strpos($s, 'windows') !== false) {
    if (preg_match('/\b(hp|hewlett-packard)\b/i', $ua, $m)) return 'Desktop (HP)';
    if (preg_match('/\b(dell)\b/i', $ua, $m)) return 'Desktop (Dell)';
    if (preg_match('/\b(lenovo)\b/i', $ua, $m)) return 'Desktop (Lenovo)';
    return 'Desktop (Windows)';
  }
  if (strpos($s, 'macintosh') !== false || strpos($s, 'mac os x') !== false) return 'Desktop (Mac)';
  if (strpos($s, 'linux') !== false && strpos($s, 'android') === false) return 'Desktop (Linux)';
  $ua = trim($ua);
  if (strlen($ua) > 60) return substr($ua, 0, 60) . '...';
  return $ua;
}

// Lightweight user-agent parser to extract browser, version, engine, device type and model
function parse_user_agent($ua) {
  $out = ['browser'=>'','version'=>'','engine'=>'','device_type'=>'','device_model'=>'','device_name'=>''];
  if (!$ua) return $out;
  $s = $ua;
  // Browser & version
  if (preg_match('/SamsungBrowser\/([\d\.]+)/i', $s, $m)) { $out['browser']='Samsung Internet'; $out['version']=$m[1]; }
  elseif (preg_match('/OPR\/([\d\.]+)/i', $s, $m)) { $out['browser']='Opera'; $out['version']=$m[1]; }
  elseif (preg_match('/Edg\/([\d\.]+)/i', $s, $m)) { $out['browser']='Edge'; $out['version']=$m[1]; }
  elseif (preg_match('/Chrome\/([\d\.]+)/i', $s, $m)) { $out['browser']='Chrome'; $out['version']=$m[1]; }
  elseif (preg_match('/Firefox\/([\d\.]+)/i', $s, $m)) { $out['browser']='Firefox'; $out['version']=$m[1]; }
  elseif (preg_match('/Version\/([\d\.]+).*Safari\//i', $s, $m)) { $out['browser']='Safari'; $out['version']=$m[1]; }
  // Engine
  if (stripos($s,'AppleWebKit') !== false) $out['engine']='Blink / WebKit';
  elseif (stripos($s,'Gecko') !== false) $out['engine']='Gecko';
  elseif (stripos($s,'Trident') !== false) $out['engine']='Trident';
  // Device type
  if (stripos($s,'Mobile') !== false || stripos($s,'Android') !== false || stripos($s,'iPhone') !== false) $out['device_type']='Mobile'; else $out['device_type']='Desktop';
  // Device model / friendly name
  $fd = friendly_device_from_ua($ua);
  if ($fd) $out['device_name'] = $fd;
  // Try to extract a model token
  if (preg_match('/(SM-[A-Z0-9\-]+)/i', $s, $m)) { $out['device_model'] = strtoupper($m[1]); }
  else {
    if (preg_match('/\(([^)]+)\)/', $s, $m)) {
      $parts = preg_split('/[;\/,:]+/', $m[1]);
      foreach ($parts as $p) {
        $t = trim($p);
        if (preg_match('/^[A-Za-z0-9\- ]{2,40}$/', $t)) { $out['device_model']=$t; break; }
      }
    }
  }
  return $out;
}

// Token-based approve/reject via email (allow approving from email link without admin session)
if (isset($_GET['action']) && ($_GET['action'] === 'approve_request' || $_GET['action'] === 'reject_request') && isset($_GET['request_id']) && isset($_GET['token'])) {
  $email_action = $_GET['action'];
  $request_id = (int)$_GET['request_id'];
  $token = $_GET['token'];

  // Fetch the request and verify token
  $rq = $conn->prepare("SELECT id, staff_id, ip, status FROM staff_login_requests WHERE id = ? AND token = ? LIMIT 1");
  if ($rq) {
    $rq->bind_param('is', $request_id, $token);
    $rq->execute();
    $rres = $rq->get_result();
    $rrow = $rres->fetch_assoc();
    $rq->close();
  } else {
    $rrow = null;
  }

  if (!$rrow) {
    http_response_code(404);
    echo "<h2>Invalid or expired approval link.</h2>";
    exit;
  }

  // Only allow action on pending requests
  if ($rrow['status'] !== 'pending') {
    echo "<h2>This request has already been processed.</h2>";
    exit;
  }

  // Approve flow
  if ($email_action === 'approve_request') {
    $ip_to_approve = $rrow['ip'] ?? null;
    // If IP blocked, mark blocked
    $is_blocked = false;
    if ($ip_to_approve) {
      $chk = $conn->prepare("SELECT id FROM blocked_ips WHERE ip = ? LIMIT 1");
      if ($chk) {
        $chk->bind_param('s', $ip_to_approve);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) $is_blocked = true;
        $chk->close();
      }
    }

    if ($is_blocked) {
      $u = $conn->prepare("UPDATE staff_login_requests SET status='blocked', approved_by=NULL, approved_at=NOW() WHERE id = ?");
      if ($u) { $u->bind_param('i', $request_id); $u->execute(); $u->close(); }
      // log
      $logDir = __DIR__ . DIRECTORY_SEPARATOR . 'logs'; if (!is_dir($logDir)) @mkdir($logDir,0755,true);
      $event = ['type'=>'approve_via_email_blocked','request_id'=>$request_id,'ip'=>$ip_to_approve,'time'=>date('c')];
      @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'staff_events.log', json_encode($event)."\n", FILE_APPEND | LOCK_EX);
      echo "<h2>IP is blocked. Request marked as blocked.</h2>";
      exit;
    }

    // Mark approved
    $stmt = $conn->prepare("UPDATE staff_login_requests SET status='approved', approved_by=NULL, approved_at=NOW() WHERE id = ?");
    if ($stmt) {
      $stmt->bind_param('i', $request_id);
      $stmt->execute();
      $stmt->close();
    }

    // Add to approved_ips if IP present
    if ($ip_to_approve) {
      $ip_stmt = $conn->prepare("INSERT INTO approved_ips (ip, created_by) VALUES (?, NULL) ON DUPLICATE KEY UPDATE ip=ip");
      if ($ip_stmt) { $ip_stmt->bind_param('s', $ip_to_approve); $ip_stmt->execute(); $ip_stmt->close(); }
    }

    // log
    $logDir = __DIR__ . DIRECTORY_SEPARATOR . 'logs'; if (!is_dir($logDir)) @mkdir($logDir,0755,true);
    $event = ['type'=>'approve_via_email','request_id'=>$request_id,'ip'=>$ip_to_approve,'time'=>date('c')];
    @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'staff_events.log', json_encode($event)."\n", FILE_APPEND | LOCK_EX);

    echo "<h2>Request approved. The staff login has been allowed from this IP.</h2>";
    exit;
  }

  // Reject flow
  if ($email_action === 'reject_request') {
    $stmt = $conn->prepare("UPDATE staff_login_requests SET status='rejected', approved_by=NULL, approved_at=NOW() WHERE id = ?");
    if ($stmt) { $stmt->bind_param('i', $request_id); $stmt->execute(); $stmt->close(); }
    // log
    $logDir = __DIR__ . DIRECTORY_SEPARATOR . 'logs'; if (!is_dir($logDir)) @mkdir($logDir,0755,true);
    $event = ['type'=>'reject_via_email','request_id'=>$request_id,'time'=>date('c')];
    @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'staff_events.log', json_encode($event)."\n", FILE_APPEND | LOCK_EX);
    echo "<h2>Request rejected. The staff will not be allowed to login from this IP.</h2>";
    exit;
  }
}

// Handle actions only if admin
if ($is_admin_verified) {
    $action = strtolower(get_post('action') ?? ($_GET['action'] ?? ''));

    if ($action === 'add') {
    // required fields
    $name = get_post('name');
    $email = get_post('email');
    $password = get_post('password');
    $role = get_post('role');

    if (!$name || !$email || !$password || !$role) {
        $_SESSION['error'] = "Please fill required fields.";
        header("Location: staff.php");
        exit;
    }

    // additional optional fields
    $age = get_post('age') ? (int)get_post('age') : null;
    $gender = get_post('gender') ?: null;
    $address = get_post('address') ?: null;
    $branch_id = get_post('branch_id') ? (int)get_post('branch_id') : null;
    $phone = get_post('phone') ?: null;
    $access_level = get_post('access_level') ?: 'restricted';

    // Check duplicate email
    $stmt = $conn->prepare("SELECT staff_id FROM staff WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $_SESSION['error'] = "Email already exists.";
        $stmt->close();
        header("Location: staff.php");
        exit;
    }
    $stmt->close();

    // Hash password
    $pw_hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO staff (name, email, password, role, phone, age, gender, address, branch_id, access_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssissssi", $name, $email, $pw_hash, $role, $phone, $age, $gender, $address, $branch_id, $access_level);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Staff added.";
    } else {
        $_SESSION['error'] = "DB error: " . $stmt->error;
    }
    $stmt->close();
    header("Location: staff.php");
    exit;
}

if ($action === 'edit') {
    $staff_id = (int)get_post('staff_id');
    if (!$staff_id) {
        $_SESSION['error'] = "Invalid staff id.";
        header("Location: staff.php");
        exit;
    }

    $name = get_post('name');
    $email = get_post('email');
    $password = get_post('password'); // optional: only update if provided
    $role = get_post('role');
    $age = get_post('age') ? (int)get_post('age') : null;
    $gender = get_post('gender') ?: null;
    $address = get_post('address') ?: null;
    $branch_id = get_post('branch_id') ? (int)get_post('branch_id') : null;
    $phone = get_post('phone') ?: null;
    $access_level = get_post('access_level') ?: 'restricted';

    // simple validation
    if (!$name || !$email || !$role) {
        $_SESSION['error'] = "Please fill required fields.";
        header("Location: staff.php");
        exit;
    }

    // If email changed - ensure no duplicate (optional)
    $stmt = $conn->prepare("SELECT staff_id FROM staff WHERE email = ? AND staff_id <> ?");
    $stmt->bind_param("si", $email, $staff_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $_SESSION['error'] = "Another user already uses that email.";
        $stmt->close();
        header("Location: staff.php");
        exit;
    }
    $stmt->close();

    // Build update query; include password only if non-empty
    if (!empty($password)) {
        $pw_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE staff SET name=?, email=?, password=?, role=?, phone=?, age=?, gender=?, address=?, branch_id=?, access_level=?, updated_at=NOW() WHERE staff_id=?");
        $stmt->bind_param("ssssissssii", $name, $email, $pw_hash, $role, $phone, $age, $gender, $address, $branch_id, $access_level, $staff_id);
    } else {
        $stmt = $conn->prepare("UPDATE staff SET name=?, email=?, role=?, phone=?, age=?, gender=?, address=?, branch_id=?, access_level=?, updated_at=NOW() WHERE staff_id=?");
        $stmt->bind_param("ssssisssii", $name, $email, $role, $phone, $age, $gender, $address, $branch_id, $access_level, $staff_id);
    }

    if ($stmt->execute()) {
        $_SESSION['success'] = "Staff updated.";
    } else {
        $_SESSION['error'] = "DB error: " . $stmt->error;
    }
    $stmt->close();
    header("Location: staff.php");
    exit;
}

if ($action === 'delete') {
    $staff_id = (int)get_post('staff_id');
    if (!$staff_id) {
        $_SESSION['error'] = "Invalid staff id.";
        header("Location: staff.php");
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM staff WHERE staff_id = ?");
    $stmt->bind_param("i", $staff_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Staff deleted.";
    } else {
        $_SESSION['error'] = "DB error: " . $stmt->error;
    }
    $stmt->close();
    header("Location: staff.php");
    exit;
}

if ($action === 'add_branch') {
    $branch_name = get_post('branch_name');
    $branch_address = get_post('branch_address');
    $branch_phone = get_post('branch_phone');
    $status = get_post('status') ?: 'active';

    if (!$branch_name || !$branch_address) {
        $_SESSION['error'] = "Branch name and address are required.";
        header("Location: staff.php");
        exit;
    }

    // Check for duplicate branch name (case-insensitive)
    $stmt_check = $conn->prepare("SELECT id FROM branches WHERE LOWER(branch_name) = LOWER(?)");
    $stmt_check->bind_param("s", $branch_name);
    $stmt_check->execute();
    $stmt_check->store_result();
    
    if ($stmt_check->num_rows > 0) {
        $_SESSION['error'] = "Branch name '" . htmlspecialchars($branch_name) . "' already exists. Please use a different name.";
        $stmt_check->close();
        header("Location: staff.php");
        exit;
    }
    $stmt_check->close();

    $stmt = $conn->prepare("INSERT INTO branches (branch_name, branch_address, branch_phone, status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $branch_name, $branch_address, $branch_phone, $status);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Branch '" . htmlspecialchars($branch_name) . "' added successfully.";
    } else {
        if (strpos($stmt->error, 'Duplicate') !== false || strpos($stmt->error, 'unique') !== false) {
            $_SESSION['error'] = "Branch name '" . htmlspecialchars($branch_name) . "' already exists. Please use a different name.";
        } else {
            $_SESSION['error'] = "Error adding branch: " . $stmt->error;
        }
    }
    $stmt->close();
    header("Location: staff.php");
    exit;
}

if ($action === 'toggle_branch') {
    $branch_id = (int)get_post('branch_id');
    if (!$branch_id) {
        $_SESSION['error'] = "Invalid branch id.";
        header("Location: staff.php");
        exit;
    }

    // Get current status
    $stmt = $conn->prepare("SELECT status FROM branches WHERE id = ?");
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $branch = $result->fetch_assoc();
    $stmt->close();

    if (!$branch) {
        $_SESSION['error'] = "Branch not found.";
        header("Location: staff.php");
        exit;
    }

    // Toggle status
    $new_status = ($branch['status'] === 'active') ? 'deactivate' : 'active';
    
    $stmt = $conn->prepare("UPDATE branches SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $branch_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Branch status updated.";
    } else {
        $_SESSION['error'] = "DB error: " . $stmt->error;
    }
    $stmt->close();
    header("Location: staff.php");
    exit;
}

if ($action === 'get_branch') {
    $branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;
    
    if ($branch_id > 0) {
        $stmt = $conn->prepare("SELECT * FROM branches WHERE id = ?");
        $stmt->bind_param("i", $branch_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $branch = $result->fetch_assoc();
        $stmt->close();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'branch' => $branch
        ]);
        exit;
    }
}

if ($action === 'edit_branch') {
    $branch_id = (int)get_post('branch_id');
    $branch_name = get_post('branch_name');
    $branch_address = get_post('branch_address');
    $branch_phone = get_post('branch_phone');
    $status = get_post('status') ?: 'active';

    if (!$branch_id || !$branch_name || !$branch_address) {
        $_SESSION['error'] = "Invalid branch data.";
        header("Location: staff.php");
        exit;
    }

    // Get current branch name to check if it's being changed
    $stmt_current = $conn->prepare("SELECT branch_name FROM branches WHERE id = ?");
    $stmt_current->bind_param("i", $branch_id);
    $stmt_current->execute();
    $current_result = $stmt_current->get_result();
    $current_branch = $current_result->fetch_assoc();
    $stmt_current->close();
    
    if (!$current_branch) {
        $_SESSION['error'] = "Branch not found.";
        header("Location: staff.php");
        exit;
    }

    // Check for duplicate branch name only if name is being changed (case-insensitive)
    if (strtolower($branch_name) !== strtolower($current_branch['branch_name'])) {
        $stmt_check = $conn->prepare("SELECT id FROM branches WHERE LOWER(branch_name) = LOWER(?) AND id != ?");
        $stmt_check->bind_param("si", $branch_name, $branch_id);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows > 0) {
            $_SESSION['error'] = "Branch name '" . htmlspecialchars($branch_name) . "' already exists. Please use a different name.";
            $stmt_check->close();
            header("Location: staff.php");
            exit;
        }
        $stmt_check->close();
    }

    $stmt = $conn->prepare("UPDATE branches SET branch_name = ?, branch_address = ?, branch_phone = ?, status = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $branch_name, $branch_address, $branch_phone, $status, $branch_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Branch '" . htmlspecialchars($branch_name) . "' updated successfully.";
    } else {
        if (strpos($stmt->error, 'Duplicate') !== false || strpos($stmt->error, 'unique') !== false) {
            $_SESSION['error'] = "Branch name '" . htmlspecialchars($branch_name) . "' already exists. Please use a different name.";
        } else {
            $_SESSION['error'] = "Error updating branch: " . $stmt->error;
        }
    }
    $stmt->close();
    header("Location: staff.php");
    exit;
}

if ($action === 'get') {
    $staff_id = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : 0;
    
    if ($staff_id > 0) {
        $stmt = $conn->prepare("SELECT * FROM staff WHERE staff_id = ?");
        $stmt->bind_param("i", $staff_id);
    } else {
        $stmt = $conn->prepare("SELECT * FROM staff");
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $staff = $result->fetch_all(MYSQLI_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'staff' => $staff
    ]);
    exit;
}

if ($action === 'get_branches') {
    $result = $conn->query("SELECT id, branch_name FROM branches WHERE status = 'active' ORDER BY branch_name ASC");
    $branches = $result->fetch_all(MYSQLI_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'branches' => $branches
    ]);
    exit;
}

if ($action === 'add_company_details') {
    $mobile_number = get_post('mobile_number');
    $land_number = get_post('land_number');
    $whatsapp_number = get_post('whatsapp_number');
    $location = get_post('location');
    $email = get_post('email_company');
    $bank_name = get_post('bank_name');
    $account_number = get_post('account_number');
    $account_name = get_post('account_name');
    $branch = get_post('branch');

    // Check if details already exist
    $check_stmt = $conn->prepare("SELECT id FROM company_details LIMIT 1");
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        // Update existing details
        $update_stmt = $conn->prepare("UPDATE company_details SET mobile_number = ?, land_number = ?, whatsapp_number = ?, location = ?, email = ?, bank_name = ?, account_number = ?, account_name = ?, branch = ? LIMIT 1");
        $update_stmt->bind_param("sssssssss", $mobile_number, $land_number, $whatsapp_number, $location, $email, $bank_name, $account_number, $account_name, $branch);
        
        if ($update_stmt->execute()) {
            $_SESSION['success'] = "Company details updated.";
        } else {
            $_SESSION['error'] = "DB error: " . $update_stmt->error;
        }
        $update_stmt->close();
    } else {
        // Insert new details
        $insert_stmt = $conn->prepare("INSERT INTO company_details (mobile_number, land_number, whatsapp_number, location, email, bank_name, account_number, account_name, branch) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("sssssssss", $mobile_number, $land_number, $whatsapp_number, $location, $email, $bank_name, $account_number, $account_name, $branch);
        
        if ($insert_stmt->execute()) {
            $_SESSION['success'] = "Company details added.";
        } else {
            $_SESSION['error'] = "DB error: " . $insert_stmt->error;
        }
        $insert_stmt->close();
    }
    $check_stmt->close();
    header("Location: staff.php");
    exit;
}

if ($action === 'get_company_details') {
    $stmt = $conn->prepare("SELECT * FROM company_details LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $details = $result->fetch_assoc();
    $stmt->close();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'details' => $details ?: []
    ]);
    exit;
}

if ($action === 'get_all_company_details') {
    $stmt = $conn->prepare("SELECT * FROM company_details LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $company_details = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'company_details' => $company_details
    ]);
    exit;
}

  // Approve / Reject login requests and block IPs
  if ($action === 'approve_request') {
    $request_id = (int)get_post('request_id');
    if (!$request_id) {
      $_SESSION['error'] = 'Invalid request id.';
      header('Location: staff.php'); exit;
    }
    // Get the IP from the request first
    $req_stmt = $conn->prepare("SELECT ip FROM staff_login_requests WHERE id = ?");
    $req_stmt->bind_param('i', $request_id);
    $req_stmt->execute();
    $req_res = $req_stmt->get_result();
    $req_row = $req_res->fetch_assoc();
    $req_stmt->close();
    $ip_to_approve = $req_row['ip'] ?? null;
    
    // If there's an IP to approve, check whether it's currently blocked first
    if ($ip_to_approve) {
      $chk = $conn->prepare("SELECT id FROM blocked_ips WHERE ip = ? LIMIT 1");
      if ($chk) {
        $chk->bind_param('s', $ip_to_approve);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
          // IP is blocked — mark the request as 'blocked' and do NOT approve or add to approved_ips
          $chk->close();
          $u = $conn->prepare("UPDATE staff_login_requests SET status='blocked', approved_by=?, approved_at=NOW() WHERE id = ?");
          $u->bind_param('ii', $staff_id, $request_id);
          $u->execute();
          $u->close();
          $_SESSION['error'] = 'This IP is blocked; request marked as blocked and not approved.';
          // log
          $logDir = __DIR__ . DIRECTORY_SEPARATOR . 'logs'; if (!is_dir($logDir)) @mkdir($logDir,0755,true);
          $event = ['type'=>'approve_login_blocked','request_id'=>$request_id,'ip'=>$ip_to_approve,'attempted_by'=>$staff_id,'time'=>date('c')];
          @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'staff_events.log', json_encode($event)."\n", FILE_APPEND | LOCK_EX);
          header('Location: staff.php'); exit;
        }
        $chk->close();
      }
    }

    // Safe to approve
    $stmt = $conn->prepare("UPDATE staff_login_requests SET status='approved', approved_by=?, approved_at=NOW() WHERE id = ?");
    $stmt->bind_param('ii', $staff_id, $request_id);
    if ($stmt->execute()) {
      // Also add this IP to approved_ips so future logins from this IP bypass approval
      if ($ip_to_approve) {
        $ip_stmt = $conn->prepare("INSERT INTO approved_ips (ip, created_by) VALUES (?, ?) ON DUPLICATE KEY UPDATE created_by=VALUES(created_by)");
        $ip_stmt->bind_param('si', $ip_to_approve, $staff_id);
        $ip_stmt->execute();
        $ip_stmt->close();
      }
      $_SESSION['success'] = 'Login request approved.';
      // log
      $logDir = __DIR__ . DIRECTORY_SEPARATOR . 'logs'; if (!is_dir($logDir)) @mkdir($logDir,0755,true);
      $event = ['type'=>'approve_login','request_id'=>$request_id,'approved_by'=>$staff_id,'time'=>date('c')];
      @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'staff_events.log', json_encode($event)."\n", FILE_APPEND | LOCK_EX);
    } else {
      $_SESSION['error'] = 'DB error: '.$stmt->error;
    }
    $stmt->close();
    header('Location: staff.php'); exit;
  }

  if ($action === 'reject_request') {
    $request_id = (int)get_post('request_id');
    if (!$request_id) { $_SESSION['error'] = 'Invalid request id.'; header('Location: staff.php'); exit; }
    $stmt = $conn->prepare("UPDATE staff_login_requests SET status='rejected' WHERE id = ?");
    $stmt->bind_param('i', $request_id);
    if ($stmt->execute()) {
      $_SESSION['success'] = 'Login request rejected.';
    } else { $_SESSION['error'] = 'DB error: '.$stmt->error; }
    $stmt->close();
    header('Location: staff.php'); exit;
  }

  if ($action === 'block_ip') {
    $ip = get_post('ip');
    $reason = get_post('reason') ?: 'Blocked by admin';
    if (!$ip) { $_SESSION['error'] = 'IP required.'; header('Location: staff.php'); exit; }
    $stmt = $conn->prepare("INSERT INTO blocked_ips (ip,reason,created_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE reason=VALUES(reason), created_by=VALUES(created_by)");
    $stmt->bind_param('ssi', $ip, $reason, $staff_id);
    if ($stmt->execute()) {
      $_SESSION['success'] = 'IP blocked: '.htmlspecialchars($ip);
    } else { $_SESSION['error'] = 'DB error: '.$stmt->error; }
    $stmt->close();
    header('Location: staff.php'); exit;
  }

  if ($action === 'unblock_ip') {
    $ip = get_post('ip');
    if (!$ip) { $_SESSION['error'] = 'IP required.'; header('Location: staff.php'); exit; }
    $stmt = $conn->prepare("DELETE FROM blocked_ips WHERE ip = ?");
    $stmt->bind_param('s', $ip);
    if ($stmt->execute()) {
      $_SESSION['success'] = 'IP unblocked: ' . htmlspecialchars($ip);
    } else { $_SESSION['error'] = 'DB error: ' . $stmt->error; }
    $stmt->close();
    header('Location: staff.php'); exit;
  }

  if ($action === 'approve_ip') {
    $ip = get_post('ip');
    $request_id = (int)get_post('request_id');
    if (!$ip) { $_SESSION['error'] = 'IP required.'; header('Location: staff.php'); exit; }
    // Prevent approving an IP that is currently blocked
    $bchk = $conn->prepare("SELECT id FROM blocked_ips WHERE ip = ? LIMIT 1");
    if ($bchk) {
      $bchk->bind_param('s', $ip);
      $bchk->execute();
      $bchk->store_result();
      if ($bchk->num_rows > 0) {
        $bchk->close();
        $_SESSION['error'] = 'Cannot approve an IP that is currently blocked.';
        header('Location: staff.php'); exit;
      }
      $bchk->close();
    }
    $stmt = $conn->prepare("INSERT INTO approved_ips (ip, created_by) VALUES (?, ?) ON DUPLICATE KEY UPDATE created_by=VALUES(created_by)");
    $stmt->bind_param('si', $ip, $staff_id);
    if ($stmt->execute()) {
      // mark request approved if provided
      if ($request_id) {
        $u = $conn->prepare("UPDATE staff_login_requests SET status='approved', approved_by=?, approved_at=NOW() WHERE id = ?");
        $u->bind_param('ii', $staff_id, $request_id);
        $u->execute();
        $u->close();
      }
      $_SESSION['success'] = 'IP approved for auto-login: '.htmlspecialchars($ip);
      // log
      $logDir = __DIR__ . DIRECTORY_SEPARATOR . 'logs'; if (!is_dir($logDir)) @mkdir($logDir,0755,true);
      $event = ['type'=>'approve_ip','ip'=>$ip,'approved_by'=>$staff_id,'time'=>date('c')];
      @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'staff_events.log', json_encode($event)."\n", FILE_APPEND | LOCK_EX);
    } else { $_SESSION['error'] = 'DB error: '.$stmt->error; }
    $stmt->close();
    header('Location: staff.php'); exit;
  }

  if ($action === 'unapprove_ip') {
    $ip = get_post('ip');
    if (!$ip) { $_SESSION['error'] = 'IP required.'; header('Location: staff.php'); exit; }
    $stmt = $conn->prepare("DELETE FROM approved_ips WHERE ip = ?");
    $stmt->bind_param('s', $ip);
    if ($stmt->execute()) {
      $_SESSION['success'] = 'IP removed from approved list: '.htmlspecialchars($ip);
      // Log the action
      $logDir = __DIR__ . DIRECTORY_SEPARATOR . 'logs'; if (!is_dir($logDir)) @mkdir($logDir,0755,true);
      $event = ['type'=>'unapprove_ip','ip'=>$ip,'unapproved_by'=>$staff_id,'time'=>date('c')];
      @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'staff_events.log', json_encode($event)."\n", FILE_APPEND | LOCK_EX);
    } else { $_SESSION['error'] = 'DB error: '.$stmt->error; }
    $stmt->close();
    
    // Invalidate any active approved sessions that used this IP
    try {
      $s = $conn->prepare("SELECT session_id, staff_id FROM approved_sessions WHERE ip = ?");
      if ($s) {
        $s->bind_param('s', $ip);
        $s->execute();
        $res = $s->get_result();
        $removed = [];
        while ($row = $res->fetch_assoc()) {
          $removed[] = $row;
        }
        $s->close();

        // Delete those session records from DB
        $d = $conn->prepare("DELETE FROM approved_sessions WHERE ip = ?");
        if ($d) { $d->bind_param('s', $ip); $d->execute(); $d->close(); }

        // Attempt to remove session files for immediate invalidation (if file-based sessions)
        foreach ($removed as $rrow) {
          $sessId = $rrow['session_id'];
          $sidStaff = $rrow['staff_id'];
          // Log forced logout per session
          $logDir = __DIR__ . DIRECTORY_SEPARATOR . 'logs'; if (!is_dir($logDir)) @mkdir($logDir,0755,true);
          $ev = ['type'=>'force_logout','staff_id'=>$sidStaff,'ip'=>$ip,'session'=>$sessId,'time'=>date('c')];
          @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'staff_events.log', json_encode($ev)."\n", FILE_APPEND | LOCK_EX);

          // Try to remove session file (best-effort)
          $savePath = ini_get('session.save_path') ?: sys_get_temp_dir();
          $sessFile = rtrim($savePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sess_' . $sessId;
          if (is_file($sessFile) && is_writable($sessFile)) {
            @unlink($sessFile);
          }
        }
      }
    } catch (Exception $e) { /* ignore */ }
    
    // Also, if the current admin action removed the IP that matches this session, log them out immediately
    $current_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
    if (isset($_SESSION['login_ip']) && $_SESSION['login_ip'] === $ip && $current_ip === $ip) {
      session_destroy();
      header('Location: login.php?msg=ip_removed');
      exit;
    }
    
    header('Location: staff.php'); exit;
  }

    // If unknown action
    if (!empty($action)) {
        $_SESSION['error'] = "Unknown action.";
        header("Location: staff.php");
        exit;
    }
}

// Fetch all staff for display
$result = $conn->query("SELECT * FROM staff ORDER BY created_at DESC");
$all_staff = $result->fetch_all(MYSQLI_ASSOC);

// Fetch pending login requests and blocked IPs for admin view
$pending_requests = [];
$pr = $conn->query("SELECT r.*, s.name AS staff_name, s.branch_id, b.branch_name FROM staff_login_requests r LEFT JOIN staff s ON r.staff_id = s.staff_id LEFT JOIN branches b ON s.branch_id = b.id WHERE r.status = 'pending' ORDER BY r.created_at DESC");
$pr = $conn->query("SELECT r.*, s.name AS staff_name, s.branch_id, b.branch_name FROM staff_login_requests r LEFT JOIN staff s ON r.staff_id = s.staff_id LEFT JOIN branches b ON s.branch_id = b.id LEFT JOIN approved_ips a ON r.ip = a.ip WHERE r.status = 'pending' AND a.ip IS NULL ORDER BY r.created_at DESC");
if ($pr) $pending_requests = $pr->fetch_all(MYSQLI_ASSOC);

// Fetch blocked IPs with a recent associated staff name and branch (if any)
$blocked_ips = [];
$bi = $conn->query(
  "SELECT b.*, 
     (SELECT s.name FROM staff_login_requests r JOIN staff s ON r.staff_id = s.staff_id WHERE r.ip = b.ip ORDER BY r.created_at DESC LIMIT 1) AS staff_name, 
     (SELECT br.branch_name FROM staff_login_requests r JOIN staff s ON r.staff_id = s.staff_id JOIN branches br ON s.branch_id = br.id WHERE r.ip = b.ip ORDER BY r.created_at DESC LIMIT 1) AS branch_name 
   FROM blocked_ips b ORDER BY created_at DESC"
);
if ($bi) $blocked_ips = $bi->fetch_all(MYSQLI_ASSOC);

// Approved IPs for auto-allowing staff logins
// Approved IPs with recent staff name and branch (if available)
$approved_ips = [];
$ai = $conn->query(
  "SELECT a.*, 
     (SELECT s.name FROM staff_login_requests r JOIN staff s ON r.staff_id = s.staff_id WHERE r.ip = a.ip ORDER BY r.created_at DESC LIMIT 1) AS staff_name, 
     (SELECT br.branch_name FROM staff_login_requests r JOIN staff s ON r.staff_id = s.staff_id JOIN branches br ON s.branch_id = br.id WHERE r.ip = a.ip ORDER BY r.created_at DESC LIMIT 1) AS branch_name 
   FROM approved_ips a ORDER BY created_at DESC"
);
if ($ai) $approved_ips = $ai->fetch_all(MYSQLI_ASSOC);

// Load login/logout events from server-side log file (JSON lines) without changing database
$login_rows = [];
$logFile = __DIR__ . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'staff_events.log';
if (file_exists($logFile)) {
  $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  $pending = []; // pending login events per staff_id
  $sessions = [];

  foreach ($lines as $line) {
    $e = json_decode($line, true);
    if (!$e || !isset($e['type'])) continue;
    if ($e['type'] === 'login') {
      $sid = (int)($e['staff_id'] ?? 0);
      // If structured UA info is not present in the log entry, try to parse it from the raw UA string
      $ua_raw = $e['device'] ?? $e['ua'] ?? $e['user_agent'] ?? '';
      $parsed = parse_user_agent($ua_raw);
      $pending[$sid][] = [
        'staff_id' => $sid,
        'ip_address' => $e['ip'] ?? '',
        'location' => $e['location_text'] ?? $e['location'] ?? '',
        'browser' => $e['browser'] ?? $parsed['browser'] ?? '',
        'version' => $e['version'] ?? $parsed['version'] ?? '',
        'engine' => $e['engine'] ?? $parsed['engine'] ?? '',
        'device_type' => $e['device_type'] ?? $parsed['device_type'] ?? '',
        'device_model' => $e['device_model'] ?? $parsed['device_model'] ?? '',
        'device' => $ua_raw,
        'device_name' => $e['device_name'] ?? $parsed['device_name'] ?? null,
        'login_time' => $e['time'] ?? null,
        'logout_time' => null
      ];
    } elseif ($e['type'] === 'logout') {
      $sid = (int)($e['staff_id'] ?? 0);
      if (!empty($pending[$sid])) {
        $last = array_pop($pending[$sid]);
        $last['logout_time'] = $e['time'] ?? null;
        $sessions[] = $last;
      } else {
        // logout without prior login: record as a session with null login_time
        $sessions[] = [
          'staff_id' => $sid,
          'ip_address' => '',
          'location' => '',
          'browser' => '',
          'version' => '',
          'engine' => '',
          'device_type' => '',
          'device_model' => '',
          'device' => '',
          'device_name' => null,
          'login_time' => null,
          'logout_time' => $e['time'] ?? null
        ];
      }
    }
  }

  // Remaining pending logins without logout
  foreach ($pending as $arr) {
    foreach ($arr as $s) {
      $sessions[] = $s;
    }
  }

  // Enrich with staff name and branch name
  $login_rows = [];
  if (!empty($sessions)) {
    // collect unique staff ids
    $staff_ids = array_unique(array_map(function($x){return (int)$x['staff_id'];}, $sessions));
    $staff_map = [];
    if (!empty($staff_ids)) {
      $in = implode(',', array_map('intval', $staff_ids));
      $res = $conn->query("SELECT staff_id, name, branch_id FROM staff WHERE staff_id IN ($in)");
      if ($res) {
        while ($r = $res->fetch_assoc()) {
          $staff_map[(int)$r['staff_id']] = $r;
        }
      }
    }

    // load branch names
    $branch_map = [];
    $branch_ids = [];
    foreach ($staff_map as $s) if (!empty($s['branch_id'])) $branch_ids[] = (int)$s['branch_id'];
    $branch_ids = array_unique($branch_ids);
    if (!empty($branch_ids)) {
      $inb = implode(',', $branch_ids);
      $bres = $conn->query("SELECT id, branch_name FROM branches WHERE id IN ($inb)");
      if ($bres) {
        while ($b = $bres->fetch_assoc()) $branch_map[(int)$b['id']] = $b['branch_name'];
      }
    }

    foreach ($sessions as $s) {
      $sid = (int)$s['staff_id'];
      // fields from log
      $browser = $s['browser'] ?? '';
      $version = $s['version'] ?? '';
      $engine = $s['engine'] ?? '';
      $device_type = $s['device_type'] ?? '';
      $device_model = $s['device_model'] ?? '';
      $dev_raw = $s['device'] ?? '';
      $dev_name = $s['device_name'] ?? null;
      if (empty($dev_name) && !empty($dev_raw) && function_exists('friendly_device_from_ua')) {
        try { $dev_name = friendly_device_from_ua($dev_raw); } catch (Exception $e) { $dev_name = null; }
      }
      $login_rows[] = [
        'staff_name' => $staff_map[$sid]['name'] ?? 'Unknown',
        'branch_name' => $branch_map[$staff_map[$sid]['branch_id']] ?? '',
        'ip_address' => $s['ip_address'] ?? '',
        'browser' => $browser,
        'version' => $version,
        'engine' => $engine,
        'device_type' => $device_type,
        'device_model' => $device_model,
        'device' => $dev_raw,
        'device_name' => $dev_name ?? '',
        'location' => $s['location'] ?? '',
        'login_time' => $s['login_time'] ?? '',
        'logout_time' => $s['logout_time'] ?? null
      ];
    }
    // sort by login_time desc (nulls last)
    usort($login_rows, function($a,$b){
      $ta = $a['login_time'] ? strtotime($a['login_time']) : 0;
      $tb = $b['login_time'] ? strtotime($b['login_time']) : 0;
      return $tb <=> $ta;
    });
    // limit
    $login_rows = array_slice($login_rows, 0, 500);
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Staff - Admin Panel</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"/>
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
  overflow-x: hidden;
}

/* ===== HEADER STYLES (Exactly like header.php) ===== */
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
  z-index: 1000;
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

/* Menu Toggle */
.menu-toggle {
  display: none;
  background: none;
  border: none;
  font-size: 1.5rem;
  color: var(--dark);
  cursor: pointer;
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
  z-index: 999;
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
  transition: var(--transition);
  width: calc(100% - var(--sidebar-width));
}

/* Content Section */
.content-section {
  background-color: #fff;
  border-radius: var(--border-radius);
  box-shadow: var(--shadow);
  padding: 2rem;
  width: 100%;
}

/* Top Actions */
.top-actions {
  display: flex;
  gap: 1rem;
  margin-bottom: 2rem;
  flex-wrap: wrap;
}

.action-btn {
  padding: 0.7rem 1.5rem;
  background-color: var(--primary);
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  transition: var(--transition);
}

.action-btn:hover {
  background-color: var(--primary-dark);
  transform: translateY(-1px);
}

/* Staff Form */
.staff-form {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
  padding: 1.5rem;
  background-color: #f9fafc;
  border-radius: var(--border-radius);
}

.staff-form input, .staff-form select {
  padding: 0.8rem;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-family: 'Inter', sans-serif;
  transition: var(--transition);
}

.staff-form input:focus, .staff-form select:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.staff-form button {
  grid-column: 1 / -1;
}

/* Data Table */
.data-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 1.5rem;
}

.data-table th,
.data-table td {
  padding: 1rem;
  text-align: left;
  border-bottom: 1px solid #eee;
}

.data-table th {
  background-color: #f9fafc;
  font-weight: 600;
  color: var(--dark);
}

.data-table tr:hover {
  background-color: #f9fafc;
}

.action-cell {
  display: flex;
  gap: 0.5rem;
}

.edit-btn, .delete-btn {
  padding: 0.4rem 0.8rem;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.9rem;
  transition: var(--transition);
}

.edit-btn {
  background-color: #e9f5ff;
  color: #2a8bf2;
}

.edit-btn:hover {
  background-color: #d1e9ff;
}

.delete-btn {
  background-color: #ffe9e9;
  color: #ff2a2a;
}

.delete-btn:hover {
  background-color: #ffd1d1;
}

/* Modal */
.modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  z-index: 1100;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.modal-content {
  background-color: #fff;
  padding: 2rem;
  border-radius: var(--border-radius);
  width: 100%;
  max-width: 500px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.close-btn {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: #777;
  transition: var(--transition);
}

.close-btn:hover {
  color: var(--dark);
}

/* Message alerts */
.alert {
  padding: 1rem;
  border-radius: 6px;
  margin-bottom: 1.5rem;
}

.alert-success {
  background-color: #e9ffe9;
  color: #2a8b2a;
  border: 1px solid #2a8b2a;
}

.alert-error {
  background-color: #ffe9e9;
  color: #ff2a2a;
  border: 1px solid #ff2a2a;
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
  border-radius: var(--border-radius);
  padding: 40px;
  text-align: center;
  box-shadow: var(--shadow);
  max-width: 500px;
  width: 100%;
  border: 1px solid #e9ecef;
}

.access-denied-icon {
  font-size: 64px;
  color: var(--danger);
  margin-bottom: 20px;
}

.access-denied-card h2 {
  color: var(--danger);
  margin-bottom: 15px;
  font-size: 28px;
  font-weight: 600;
}

.access-denied-card p {
  color: var(--gray);
  margin-bottom: 15px;
  line-height: 1.6;
}

.user-info-display {
  background: #f8f9fa;
  border-radius: 8px;
  padding: 20px;
  margin: 20px 0;
  border-left: 4px solid var(--danger);
}

.user-info-display p {
  margin-bottom: 8px;
  color: var(--dark);
  font-size: 14px;
}

.user-info-display p:last-child {
  margin-bottom: 0;
}

.back-btn {
  background: var(--gray);
  color: white;
  border: none;
  padding: 12px 24px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 16px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: var(--transition);
  margin-top: 20px;
}

.back-btn:hover {
  background: #5a6268;
  transform: translateY(-1px);
}

/* Login Management Sections */
.admin-section {
  margin: 24px 0;
  padding: 24px;
  background: white;
  border-radius: var(--border-radius);
  box-shadow: var(--shadow);
}

.admin-section h3 {
  color: var(--dark);
  margin-bottom: 20px;
  padding-bottom: 12px;
  border-bottom: 2px solid var(--primary);
  font-size: 20px;
  font-weight: 600;
}

.admin-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 16px;
  font-size: 14px;
}

.admin-table th {
  background-color: #f8fafc;
  padding: 12px 16px;
  text-align: left;
  font-weight: 600;
  color: var(--dark);
  border-bottom: 2px solid #e5e7eb;
}

.admin-table td {
  padding: 12px 16px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}

.admin-table tr:hover {
  background-color: #f8fafc;
}

.admin-table .actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.admin-table button {
  padding: 6px 12px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
  transition: var(--transition);
  white-space: nowrap;
}

.approve-btn {
  background-color: #e9ffe9;
  color: #10b981;
  border: 1px solid #10b981;
}

.approve-btn:hover {
  background-color: #d1ffd1;
  transform: translateY(-1px);
}

.reject-btn {
  background-color: #ffe9e9;
  color: #ef4444;
  border: 1px solid #ef4444;
}

.reject-btn:hover {
  background-color: #ffd1d1;
  transform: translateY(-1px);
}

.block-btn {
  background-color: #fff7e9;
  color: #f59e0b;
  border: 1px solid #f59e0b;
}

.block-btn:hover {
  background-color: #ffeed1;
  transform: translateY(-1px);
}

.unblock-btn {
  background-color: #e9f5ff;
  color: #2a8bf2;
  border: 1px solid #2a8bf2;
}

.unblock-btn:hover {
  background-color: #d1e9ff;
  transform: translateY(-1px);
}

.remove-btn {
  background-color: #f1f5f9;
  color: var(--gray);
  border: 1px solid var(--gray-light);
}

.remove-btn:hover {
  background-color: #e5e7eb;
  transform: translateY(-1px);
}

.no-data {
  text-align: center;
  padding: 32px !important;
  color: var(--gray);
  font-style: italic;
}

/* Login History Section */
.login-history-section {
    width: 80%;
     margin-left: 300px;
     margin-right: 6px;
 
  padding: 24px;
  background: white;
  border-radius: var(--border-radius);
  box-shadow: var(--shadow);
}

.login-history-section h2 {
  color: var(--dark);
  margin-bottom: 20px;
  padding-bottom: 12px;
  border-bottom: 2px solid var(--primary);
  font-size: 20px;
  font-weight: 600;
}

.login-history-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 16px;
  font-size: 14px;
}

.login-history-table th {
  background-color: #f8fafc;
  padding: 12px 10px;
  text-align: left;
  font-weight: 600;
  color: var(--dark);
  border-bottom: 2px solid #e5e7eb;
  white-space: nowrap;
}

.login-history-table td {
  padding: 12px 10px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: top;
}

.login-history-table tr:hover {
  background-color: #f8fafc;
}

.device-name-cell {
  max-width: 150px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.device-name-cell:hover {
  overflow: visible;
  white-space: normal;
  background-color: white;
  box-shadow: 0 0 10px rgba(0,0,0,0.1);
  z-index: 10;
  position: relative;
}

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 500;
}

.status-active {
  background-color: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.status-inactive {
  background-color: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

/* Overlay for mobile */
.overlay {
  display: none;
  position: fixed;
  top: var(--header-height);
  left: 0;
  width: 100%;
  height: calc(100vh - var(--header-height));
  background: rgba(0, 0, 0, 0.3);
  z-index: 998;
}

.overlay.active {
  display: block;
}

/* Hide staff management for non-admin users */
<?php if ($_SESSION['role'] !== 'admin'): ?>
#staffLink {
  display: none;
}
<?php endif; ?>

/* ===== RESPONSIVE STYLES (Exactly like header.php) ===== */
@media (max-width: 992px) {
  .sidebar {
    transform: translateX(-100%);
  }

  .sidebar.active {
    transform: translateX(0);
  }

  .main-content {
    margin-left: 0;
    width: 100%;
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

  /* Mobile-specific content adjustments */
  .content-section {
    padding: 1.5rem;
  }

  .staff-form {
    grid-template-columns: 1fr;
    padding: 1rem;
  }

  .top-actions {
    flex-direction: column;
  }

  .action-btn {
    width: 100%;
  }

  /* Table responsive */
  .data-table-container,
  .admin-table-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }

  .data-table {
    min-width: 600px;
  }

  .admin-table {
    min-width: 800px;
  }

  .login-history-table {
    min-width: 1200px;
  }

  .action-cell {
    flex-direction: column;
    gap: 0.25rem;
  }

  .admin-table .actions {
    flex-direction: column;
  }

  .edit-btn, .delete-btn {
    width: 100%;
    text-align: center;
  }

  /* Modal mobile */
  .modal-content {
    padding: 1.5rem;
    margin: 1rem;
  }

  /* Admin sections mobile */
  .admin-section,
  .login-history-section {
    padding: 1rem;
    margin: 16px 0;
  }

  .admin-section h3,
  .login-history-section h2 {
    font-size: 18px;
    padding-bottom: 8px;
  }

  /* Access denied mobile */
  .access-denied-card {
    padding: 30px 20px;
  }

  .access-denied-icon {
    font-size: 48px;
  }

  .access-denied-card h2 {
    font-size: 24px;
  }
}

@media (max-width: 480px) {
  .nav-right {
    gap: 1rem;
  }

  .user-info {
    display: none;
  }

  .main-content {
    padding: 0.75rem;
  }

  .content-section {
    padding: 1rem;
  }

  .admin-section,
  .login-history-section {
    padding: 0.75rem;
  }

  .admin-table button {
    padding: 4px 8px;
    font-size: 12px;
  }
}
  </style>
</head>
<body>
  <!-- Top Navigation (Exactly like header.php) -->
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

  <!-- Dashboard Layout -->
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

    <!-- Overlay for mobile -->
    <div class="overlay" id="overlay"></div>

    <!-- Main Content -->
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
            <p>Please contact your administrator if you need access to staff management.</p>
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
      <div class="content-section">
        <div id="messageArea">
          <?php
          if (isset($_SESSION['success'])) {
              echo '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
              unset($_SESSION['success']);
          }
          if (isset($_SESSION['error'])) {
              echo '<div class="alert alert-error">' . $_SESSION['error'] . '</div>';
              unset($_SESSION['error']);
          }
          ?>
        </div>
        
        <div class="top-actions">
          <button class="action-btn" id="addStaffBtn">Add New Staff</button>
        </div>
        
        <div class="data-table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Branch</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Role</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="staffTableBody">
              <?php foreach ($all_staff as $staff): ?>
              <?php
                // Get branch name if branch_id exists
                $branch_name = 'N/A';
                if (!empty($staff['branch_id'])) {
                    $branch_stmt = $conn->prepare("SELECT branch_name FROM branches WHERE id = ?");
                    $branch_stmt->bind_param("i", $staff['branch_id']);
                    $branch_stmt->execute();
                    $branch_result = $branch_stmt->get_result();
                    $branch_data = $branch_result->fetch_assoc();
                    if ($branch_data) {
                        $branch_name = htmlspecialchars($branch_data['branch_name']);
                    }
                    $branch_stmt->close();
                }
              ?>
              <tr>
                <td><?= $staff['staff_id'] ?></td>
                <td><?= htmlspecialchars($staff['name']) ?></td>
                <td><?= $branch_name ?></td>
                <td><?= htmlspecialchars($staff['email']) ?></td>
                <td><?= htmlspecialchars($staff['phone'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($staff['role']) ?></td>
                <td class="action-cell">
                  <button class="edit-btn" data-id="<?= $staff['staff_id'] ?>">Edit</button>
                  <button class="delete-btn" data-id="<?= $staff['staff_id'] ?>">Delete</button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Add Branch Button -->
        <button onclick="document.getElementById('branchForm').style.display='block'" class="action-btn" style="margin-top: 20px;">Add Branch</button>

        <!-- Branch Form (hidden by default) -->
        <div id="branchForm" style="display:none; margin-top:20px; padding: 1.5rem; background-color: #f9fafc; border-radius: 12px;">
            <form method="POST" action="staff.php" class="staff-form">
                <input type="hidden" name="action" value="add_branch">
                <input type="text" name="branch_name" placeholder="Branch Name" required>
                <input type="text" name="branch_address" placeholder="Branch Address" required>
                <input type="text" name="branch_phone" placeholder="Branch Phone Number">
                <select name="status">
                    <option value="active">Activate</option>
                    <option value="deactivate">Deactivate</option>
                </select>
                <button type="submit" class="action-btn">Save Branch</button>
                <button type="button" onclick="document.getElementById('branchForm').style.display='none'" class="action-btn" style="background-color: var(--gray); grid-column: auto;">Cancel</button>
            </form>
        </div>

        <!-- Manage Branch Table -->
        <h2 style="margin-top: 2rem;">Manage Branch</h2>
        <div class="data-table-container" style="margin-top: 1.5rem;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Address</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetch branches from the same database connection
                    $branch_result = $conn->query("SELECT id, branch_name, branch_address, branch_phone, status FROM branches ORDER BY id DESC");
                    if ($branch_result && $branch_result->num_rows > 0) {
                    ?>
                    <!-- Pending Login Approvals moved below (after Company Details) -->
                    <?php
                        while($branch = $branch_result->fetch_assoc()) {
                            $status_badge_bg = ($branch['status'] == 'active') ? '#e9f5ff' : '#ffe9e9';
                            $status_badge_color = ($branch['status'] == 'active') ? '#2a8bf2' : '#ff2a2a';
                            $status_text = ($branch['status'] == 'active') ? 'Deactivate' : 'Activate';
                            
                            echo "<tr>
                                <td>".htmlspecialchars($branch['branch_name'])."</td>
                                <td>".htmlspecialchars($branch['branch_address'])."</td>
                                <td>".htmlspecialchars($branch['branch_phone'] ?? 'N/A')."</td>
                                <td>
                                    <form method='POST' action='staff.php' style='display:inline;'>
                                        <input type='hidden' name='action' value='toggle_branch'>
                                        <input type='hidden' name='branch_id' value='{$branch['id']}'>
                                        <button type='submit' class='action-btn' style='background-color: $status_badge_bg; color: $status_badge_color; padding: 0.4rem 0.8rem; font-size: 0.9rem;'>$status_text</button>
                                    </form>
                                </td>
                                <td class='action-cell'>
                                    <button class='edit-btn' onclick=\"editBranch({$branch['id']})\">Edit</button>
                                </td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' style='text-align: center; padding: 20px; color: var(--gray);'>No branches found</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Manage Company Details Section -->
        <h2 style="margin-top: 2rem;">Company Details</h2>
        <div class="top-actions" style="margin-top: 1.5rem;">
          <button class="action-btn" id="addCompanyDetailsBtn" style="background-color: #10b981; display: none;">Add Company Details</button>
        </div>
        
        <div class="data-table-container" style="margin-top: 1.5rem;">
          <table class="data-table">
            <thead>
              <tr>
                <th>Mobile</th>
                <th>Land Number</th>
                <th>WhatsApp</th>
                <th>Email</th>
                <th>Bank Name</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="companyDetailsBody">
              <tr id="noCompanyDetailsRow">
                <td colspan="6" style="text-align: center; padding: 20px; color: var(--gray);">No company details found. Click "Add Company Details" to create one.</td>
              </tr>
            </tbody>
          </table>
        </div>
        
     
       
        
    <!-- Pending Login Approvals (Admin) -->
<?php if ($is_admin_verified): ?>
<div class="admin-section">
  <h3>Pending Login Approvals</h3>
  <?php if (!empty($pending_requests)): ?>
  <div class="admin-table-container">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Branch</th>
          <th>IP</th>
          <th>Time</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($pending_requests as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['staff_name'] ?: $r['name']) ?></td>
          <td><?= htmlspecialchars($r['branch_name'] ?: '') ?></td>
          <td><?= htmlspecialchars($r['ip']) ?></td>
          <td><?= htmlspecialchars($r['created_at']) ?></td>
          <td class="actions">
            <form method="post" style="display:inline-block;margin-right:6px">
              <input type="hidden" name="action" value="approve_request">
              <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
              <button type="submit" class="approve-btn">Approve</button>
            </form>
            <form method="post" style="display:inline-block;margin-right:6px">
              <input type="hidden" name="action" value="reject_request">
              <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
              <button type="submit" class="reject-btn">Reject</button>
            </form>
            <form method="post" style="display:inline-block;margin-right:6px">
              <input type="hidden" name="action" value="block_ip">
              <input type="hidden" name="ip" value="<?= htmlspecialchars($r['ip']) ?>">
              <input type="hidden" name="reason" value="Blocked from pending login">
              <button type="submit" class="block-btn">Block IP</button>
            </form>
            <form method="post" style="display:inline-block">
              <input type="hidden" name="action" value="unblock_ip">
              <input type="hidden" name="ip" value="<?= htmlspecialchars($r['ip']) ?>">
              <button type="submit" class="unblock-btn">Unblock (if blocked)</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <p style="color:var(--gray);">No pending login requests.</p>
  <?php endif; ?>
</div>


       
        
    <!-- Blocked IPs (Admin) -->
<?php if ($is_admin_verified): ?>
<div class="admin-section">
  <h3>Blocked IPs</h3>
  <?php if (!empty($blocked_ips)): ?>
  <div class="admin-table-container">
    <table class="admin-table">
      <thead>
        <tr>
          <th>IP</th>
          <th>Name</th>
          <th>Branch</th>
          <th>Reason</th>
          <th>Blocked At</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($blocked_ips as $bi): ?>
          <tr>
            <td><?= htmlspecialchars($bi['ip']) ?></td>
            <td><?= htmlspecialchars($bi['staff_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($bi['branch_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($bi['reason'] ?? '') ?></td>
            <td><?= htmlspecialchars($bi['created_at'] ?? '') ?></td>
            <td class="actions">
              <form method="post" style="display:inline-block; margin-right:6px;">
                <input type="hidden" name="action" value="unblock_ip">
                <input type="hidden" name="ip" value="<?= htmlspecialchars($bi['ip']) ?>">
                <button type="submit" class="unblock-btn">Unblock</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <p style="color:var(--gray);">No blocked IPs.</p>
  <?php endif; ?>
</div>
<?php endif; ?>
        
        <!-- Approved IPs (Admin) -->
        
        <?php endif; ?>
      
      
      <!-- Approved IPs (Admin) -->
<?php if ($is_admin_verified): ?>
<div class="admin-section">
  <h3>Approved IPs (auto-allow staff login)</h3>
  <?php if (!empty($approved_ips)): ?>
  <div class="admin-table-container">
    <table class="admin-table">
      <thead>
        <tr>
          <th>IP</th>
          <th>Name</th>
          <th>Branch</th>
          <th>Approved At</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($approved_ips as $ap): ?>
          <tr>
            <td><?= htmlspecialchars($ap['ip']) ?></td>
            <td><?= htmlspecialchars($ap['staff_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($ap['branch_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($ap['created_at'] ?? '') ?></td>
            <td class="actions">
              <form method="post" style="display:inline-block; margin-right:6px;">
                <input type="hidden" name="action" value="unapprove_ip">
                <input type="hidden" name="ip" value="<?= htmlspecialchars($ap['ip']) ?>">
                <button type="submit" class="remove-btn">Remove</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <p style="color:var(--gray);">No approved IPs.</p>
  <?php endif; ?>
</div>
<?php endif; ?>
</div>

      <?php endif; ?>
      
      
  
    </main>
  </div>

  <!-- Add/Edit Staff Modal -->
  <div class="modal" id="staffModal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 id="modalTitle">Add Staff Member</h2>
        <button class="close-btn" id="closeModal">&times;</button>
      </div>
      <form class="staff-form" id="staffForm" method="POST" action="staff.php">
        <input type="hidden" id="staffId" name="staff_id">
        <input type="hidden" name="action" id="formAction" value="add">
        <input type="text" id="name" name="name" placeholder="Employee name" required>
        <input type="email" id="email" name="email" placeholder="Email address" required>
        <input type="password" id="password" name="password" placeholder="Password" required>
        <input type="number" id="age" name="age" placeholder="Age" min="18" max="65">
        <select id="gender" name="gender" required>
          <option value="">Select Gender</option>
          <option value="Male">Male</option>
          <option value="Female">Female</option>
          <option value="Other">Other</option>
        </select>
        <input type="text" id="address" name="address" placeholder="Address">
        <select id="branch_id" name="branch_id" required>
          <option value="">Select Branch</option>
        </select>
        <input type="tel" id="phone" name="phone" placeholder="Phone number">
        <select id="role" name="role" required>
          <option value="">Select Role</option>
          <option value="admin">Admin</option>
          <option value="staff">Staff</option>
        </select>
        <button class="action-btn" type="submit">Save Staff</button>
      </form>
    </div>
  </div>

  <!-- Add/Edit Branch Modal -->
  <div class="modal" id="branchModal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 id="branchModalTitle">Edit Branch</h2>
        <button class="close-btn" id="closeBranchModal">&times;</button>
      </div>
      <form class="staff-form" id="branchForm2" method="POST" action="staff.php">
        <input type="hidden" name="action" value="edit_branch">
        <input type="hidden" id="branchId" name="branch_id">
        <input type="text" id="branchName" name="branch_name" placeholder="Branch Name" required>
        <input type="text" id="branchAddress" name="branch_address" placeholder="Branch Address" required>
        <input type="text" id="branchPhone" name="branch_phone" placeholder="Branch Phone Number">
        <select id="branchStatus" name="status">
            <option value="active">Activate</option>
            <option value="deactivate">Deactivate</option>
        </select>
        <button class="action-btn" type="submit">Save Branch</button>
      </form>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div class="modal" id="deleteModal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Confirm Deletion</h2>
        <button class="close-btn" id="closeDeleteModal">&times;</button>
      </div>
      <p>Are you sure you want to delete this staff member? This action cannot be undone.</p>
      <div style="display: flex; gap: 1rem; margin-top: 1.5rem; flex-wrap: wrap;">
        <form id="deleteForm" method="POST" action="staff.php">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="staff_id" id="deleteStaffId">
          <button class="action-btn" type="submit" style="background-color: var(--danger);">Delete</button>
        </form>
        <button class="action-btn" id="cancelDelete" style="background-color: var(--gray);">Cancel</button>
      </div>
    </div>
  </div>

  <!-- Company Details Modal -->
  <div class="modal" id="companyDetailsModal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 id="companyDetailsModalTitle">Add Company Details</h2>
        <button class="close-btn" id="closeCompanyDetailsModal">&times;</button>
      </div>
      <form class="staff-form" id="companyDetailsForm" method="POST" action="staff.php">
        <input type="hidden" name="action" value="add_company_details">
        
        <input type="tel" id="comp_mobile_number" name="mobile_number" placeholder="Mobile Number">
        <input type="tel" id="comp_land_number" name="land_number" placeholder="Land Number">
        <input type="tel" id="comp_whatsapp_number" name="whatsapp_number" placeholder="WhatsApp Number">

        <input type="text" id="comp_location" name="location" placeholder="Location">
        <input type="email" id="comp_email" name="email_company" placeholder="Email">
        <input type="text" id="comp_bank_name" name="bank_name" placeholder="Bank Name">
        <input type="text" id="comp_account_number" name="account_number" placeholder="Account Number">
        <input type="text" id="comp_account_name" name="account_name" placeholder="Account Name">
        <input type="text" id="comp_branch" name="branch" placeholder="Branch">
        
        <button class="action-btn" type="submit">Save Details</button>
      </form>
    </div>
  </div>

  <script>
  
  const input = document.getElementById('comp_whatsapp_number');

// Set default value
input.value = '+94 ';

// Prevent removing +94
input.addEventListener('input', function () {
    if (!input.value.startsWith('+94')) {
        input.value = '+94';
    }
});

    // DOM Elements
    const staffTableBody = document.getElementById('staffTableBody');
    const staffModal = document.getElementById('staffModal');
    const branchModal = document.getElementById('branchModal');
    const deleteModal = document.getElementById('deleteModal');
    const companyDetailsModal = document.getElementById('companyDetailsModal');
    const companyDetailsForm = document.getElementById('companyDetailsForm');
    const companyDetailsBody = document.getElementById('companyDetailsBody');
    const staffForm = document.getElementById('staffForm');
    const branchForm2 = document.getElementById('branchForm2');
    const modalTitle = document.getElementById('modalTitle');
    const branchModalTitle = document.getElementById('branchModalTitle');
    const companyDetailsModalTitle = document.getElementById('companyDetailsModalTitle');
    const addStaffBtn = document.getElementById('addStaffBtn');
    const addCompanyDetailsBtn = document.getElementById('addCompanyDetailsBtn');
    const closeModal = document.getElementById('closeModal');
    const closeBranchModal = document.getElementById('closeBranchModal');
    const closeDeleteModal = document.getElementById('closeDeleteModal');
    const closeCompanyDetailsModal = document.getElementById('closeCompanyDetailsModal');
    const cancelDelete = document.getElementById('cancelDelete');
    const messageArea = document.getElementById('messageArea');
    const deleteForm = document.getElementById('deleteForm');
    const formAction = document.getElementById('formAction');
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');

    // Mobile menu toggle (Exactly like header.php)
    menuToggle.addEventListener('click', () => {
      sidebar.classList.toggle('active');
      overlay.classList.toggle('active');
    });

    // Close sidebar when clicking on overlay
    overlay.addEventListener('click', () => {
      sidebar.classList.remove('active');
      overlay.classList.remove('active');
    });

    // Load branches for dropdown
    function loadBranches() {
      const branchSelect = document.getElementById('branch_id');
      fetch('staff.php?action=get_branches')
        .then(response => response.json())
        .then(data => {
          if (data.success && data.branches) {
            branchSelect.innerHTML = '<option value="">Select Branch</option>';
            data.branches.forEach(branch => {
              const option = document.createElement('option');
              option.value = branch.id;
              option.textContent = branch.branch_name;
              branchSelect.appendChild(option);
            });
          }
        })
        .catch(error => console.error('Error loading branches:', error));
    }

    // Show modal for adding new staff
    addStaffBtn.addEventListener('click', () => {
      modalTitle.textContent = 'Add Staff Member';
      staffForm.reset();
      document.getElementById('staffId').value = '';
      formAction.value = 'add';
      document.getElementById('password').required = true;
      document.getElementById('password').placeholder = 'Password';
      loadBranches();
      staffModal.style.display = 'flex';
      document.body.style.overflow = 'hidden';
    });

    // Close modal
    closeModal.addEventListener('click', () => {
      staffModal.style.display = 'none';
      document.body.style.overflow = '';
    });

    // Close branch modal
    closeBranchModal.addEventListener('click', () => {
      branchModal.style.display = 'none';
      document.body.style.overflow = '';
    });

    // Close delete modal
    closeDeleteModal.addEventListener('click', () => {
      deleteModal.style.display = 'none';
      document.body.style.overflow = '';
    });

    cancelDelete.addEventListener('click', () => {
      deleteModal.style.display = 'none';
      document.body.style.overflow = '';
    });

    // Edit staff member
    function editStaff(id) {
      fetch(`staff.php?action=get&staff_id=${id}`)
        .then(response => response.json())
        .then(data => {
          if (data.success && data.staff.length > 0) {
            const staff = data.staff[0];
            modalTitle.textContent = 'Edit Staff Member';
            
            // Safely set form values with null checks
            const setFieldValue = (id, value) => {
              const element = document.getElementById(id);
              if (element) {
                element.value = value || '';
              }
            };
            
            setFieldValue('staffId', staff.staff_id);
            setFieldValue('name', staff.name);
            setFieldValue('email', staff.email);
            setFieldValue('password', ''); // Don't fill password for security
            setFieldValue('age', staff.age);
            setFieldValue('gender', staff.gender);
            setFieldValue('address', staff.address);
            setFieldValue('branch_id', staff.branch_id);
            setFieldValue('phone', staff.phone);
            setFieldValue('role', staff.role);
            loadBranches();
            
            // Set password as not required for edit
            const passwordField = document.getElementById('password');
            if (passwordField) {
              passwordField.required = false;
              passwordField.placeholder = 'Leave blank to keep current password';
            }
            
            formAction.value = 'edit';
            staffModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
          } else {
            showMessage('Error loading staff data', 'error');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showMessage('Error loading staff data', 'error');
        });
    }

    // Edit branch member
    function editBranch(id) {
      fetch(`staff.php?action=get_branch&branch_id=${id}`)
        .then(response => response.json())
        .then(data => {
          if (data.success && data.branch) {
            const branch = data.branch;
            branchModalTitle.textContent = 'Edit Branch';
            
            document.getElementById('branchId').value = branch.id;
            document.getElementById('branchName').value = branch.branch_name || '';
            document.getElementById('branchAddress').value = branch.branch_address || '';
            document.getElementById('branchPhone').value = branch.branch_phone || '';
            document.getElementById('branchStatus').value = branch.status || 'active';
            
            branchModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
          } else {
            showMessage('Error loading branch data', 'error');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showMessage('Error loading branch data', 'error');
        });
    }

    // Show delete confirmation modal
    function showDeleteModal(id) {
      document.getElementById('deleteStaffId').value = id;
      deleteModal.style.display = 'flex';
      document.body.style.overflow = 'hidden';
    }

    // Show message
    function showMessage(message, type) {
      messageArea.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
      
      // Auto hide after 5 seconds
      setTimeout(() => {
        messageArea.innerHTML = '';
      }, 5000);
    }

    // Go back function for access denied
    function goBack() {
      window.history.back();
    }

    // Load company details
    function loadCompanyDetails() {
      fetch('staff.php?action=get_all_company_details')
        .then(response => response.json())
        .then(data => {
          if (data.success && data.company_details && data.company_details.length > 0) {
            companyDetailsBody.innerHTML = '';
            addCompanyDetailsBtn.style.display = 'none'; // Hide Add button if details exist
            data.company_details.forEach(detail => {
              const row = document.createElement('tr');
              row.innerHTML = `
                <td>${detail.mobile_number || 'N/A'}</td>
                <td>${detail.land_number || 'N/A'}</td>
                <td>${detail.whatsapp_number || 'N/A'}</td>
                <td>${detail.email || 'N/A'}</td>
                <td>${detail.bank_name || 'N/A'}</td>
                <td class="action-cell">
                  <button class="edit-btn" onclick="editCompanyDetails()">Edit</button>
                </td>
              `;
              companyDetailsBody.appendChild(row);
            });
          } else {
            // Show "No details found" message
            addCompanyDetailsBtn.style.display = 'block'; // Show Add button if no details
            companyDetailsBody.innerHTML = '<tr id="noCompanyDetailsRow"><td colspan="6" style="text-align: center; padding: 20px; color: var(--gray);">No company details found. Click "Add Company Details" to create one.</td></tr>';
          }
        })
        .catch(error => console.error('Error loading company details:', error));
    }

    // Show add company details modal
    addCompanyDetailsBtn.addEventListener('click', () => {
      document.getElementById('companyDetailsModalTitle').textContent = 'Add Company Details';
      companyDetailsForm.reset();
      document.getElementById('comp_mobile_number').value = '';
      document.getElementById('comp_land_number').value = '';
      document.getElementById('comp_whatsapp_number').value = '';
      document.getElementById('comp_location').value = '';
      document.getElementById('comp_email').value = '';
      document.getElementById('comp_bank_name').value = '';
      document.getElementById('comp_account_number').value = '';
      document.getElementById('comp_account_name').value = '';
      document.getElementById('comp_branch').value = '';
      companyDetailsModal.style.display = 'flex';
      document.body.style.overflow = 'hidden';
    });

    // Close company details modal
    closeCompanyDetailsModal.addEventListener('click', () => {
      companyDetailsModal.style.display = 'none';
      document.body.style.overflow = '';
    });

    // Edit company details
    function editCompanyDetails() {
      fetch(`staff.php?action=get_company_details`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const details = data.details;
            document.getElementById('companyDetailsModalTitle').textContent = 'Edit Company Details';
            
            document.getElementById('comp_mobile_number').value = details?.mobile_number || '';
            document.getElementById('comp_land_number').value = details?.land_number || '';
            document.getElementById('comp_whatsapp_number').value = details?.whatsapp_number || '';
            document.getElementById('comp_location').value = details?.location || '';
            document.getElementById('comp_email').value = details?.email || '';
            document.getElementById('comp_bank_name').value = details?.bank_name || '';
            document.getElementById('comp_account_number').value = details?.account_number || '';
            document.getElementById('comp_account_name').value = details?.account_name || '';
            document.getElementById('comp_branch').value = details?.branch || '';
            
            companyDetailsModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
          } else {
            showMessage('Error loading company details', 'error');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showMessage('Error loading company details', 'error');
        });
    }

    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
      // Load company details table on page load
      loadCompanyDetails();
      
      // Add event listeners to edit and delete buttons
      document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
          const id = parseInt(e.target.getAttribute('data-id'));
          if (id) {
            editStaff(id);
          }
        });
      });
      
      document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
          const id = parseInt(e.target.getAttribute('data-id'));
          if (id) {
            showDeleteModal(id);
          }
        });
      });
      
      // Close modals if clicked outside
      window.addEventListener('click', (e) => {
        if (e.target === staffModal) {
          staffModal.style.display = 'none';
          document.body.style.overflow = '';
        }
        if (e.target === branchModal) {
          branchModal.style.display = 'none';
          document.body.style.overflow = '';
        }
        if (e.target === deleteModal) {
          deleteModal.style.display = 'none';
          document.body.style.overflow = '';
        }
        if (e.target === companyDetailsModal) {
          companyDetailsModal.style.display = 'none';
          document.body.style.overflow = '';
        }
      });

      // Close sidebar when window is resized to desktop
      window.addEventListener('resize', () => {
        if (window.innerWidth > 992) {
          sidebar.classList.remove('active');
          overlay.classList.remove('active');
        }
      });
    });
  </script>
 
  
  
  <!-- Staff Login / Logout History -->
<div class="login-history-section">
  <h2>Staff Login / Logout History</h2>
  <div class="admin-table-container">
    <table class="login-history-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Branch</th>
          <th>IP Address</th>
          <th>Browser</th>
          
          
          <th>Device Type</th>
          
          
          
          <th>Login Time</th>
          <th>Logout Time</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($login_rows)): ?>
          <?php foreach ($login_rows as $idx => $r): ?>
            <tr>
              <td><?= ($idx+1) ?></td>
              <td><?= htmlspecialchars($r['staff_name'] ?? 'Unknown') ?></td>
              <td><?= htmlspecialchars($r['branch_name'] ?? '') ?></td>
              <td><?= htmlspecialchars($r['ip_address'] ?? '') ?></td>
              <td><?= htmlspecialchars($r['browser'] ?? '') ?></td>
              
              
              <td><?= htmlspecialchars($r['device_type'] ?? '') ?></td>
              
              
              
              <td><?= htmlspecialchars($r['login_time'] ?? '') ?></td>
              <td><?= htmlspecialchars($r['logout_time'] ?? '-') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="13" style="padding:12px;color:#6b7280;">No login records found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>