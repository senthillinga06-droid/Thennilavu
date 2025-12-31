<?php
// Temporarily enable error display to diagnose HTTP 500 issues.
// Remove or set to 0 in production after debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ob_start();
session_start();

/* =======================
   DATABASE CONNECTION
======================= */
$conn = new mysqli(
  "localhost",
  "thennilavu_matrimonial",
  "OYVuiEKfS@FQ",
  "thennilavu_thennilavu"
);
if ($conn->connect_error) die("DB Connection Failed");

// Ensure tables for login approval workflow
$conn->query("CREATE TABLE IF NOT EXISTS staff_login_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  staff_id INT NOT NULL,
  name VARCHAR(255),
  branch_id INT DEFAULT NULL,
  ip VARCHAR(45),
  ua TEXT,
  token VARCHAR(128),
  status ENUM('pending','approved','rejected','blocked') DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  approved_by INT DEFAULT NULL,
  approved_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS blocked_ips (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) UNIQUE,
  reason VARCHAR(255),
  created_by INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS approved_ips (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) UNIQUE,
  created_by INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS approved_sessions (
  session_id VARCHAR(128) PRIMARY KEY,
  staff_id INT NOT NULL,
  ip VARCHAR(45),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* =======================
   REVERSE GEOCODE
======================= */
function reverse_geocode($lat, $lon) {
  if (!$lat || !$lon) return null;
  $url = "https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=$lat&lon=$lon";
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 5,
    CURLOPT_USERAGENT => "ThennilavuAdmin/1.0"
  ]);
  $res = curl_exec($ch);
  curl_close($ch);
  $data = json_decode($res, true);
  return $data['display_name'] ?? null;
}

/* =======================
   USER-AGENT PARSER
======================= */
function parse_user_agent($ua) {
  $out = [
    'browser' => null,
    'version' => null,
    'engine' => null,
    'device_type' => 'Desktop',
    'device_model' => null,
    'friendly_device' => null
  ];

  if (!$ua) return $out;

  // Browser
  if (preg_match('/SamsungBrowser\/([\d\.]+)/i', $ua, $m)) {
    $out['browser'] = 'Samsung Internet';
    $out['version'] = $m[1];
  } elseif (preg_match('/Chrome\/([\d\.]+)/i', $ua, $m)) {
    $out['browser'] = 'Chrome';
    $out['version'] = $m[1];
  } elseif (preg_match('/Firefox\/([\d\.]+)/i', $ua, $m)) {
    $out['browser'] = 'Firefox';
    $out['version'] = $m[1];
  } elseif (preg_match('/Safari\/([\d\.]+)/i', $ua)) {
    $out['browser'] = 'Safari';
  }

  // Engine
  if (stripos($ua, 'AppleWebKit') !== false) {
    $out['engine'] = 'Blink / WebKit';
  } elseif (stripos($ua, 'Gecko') !== false) {
    $out['engine'] = 'Gecko';
  }

  // Device type
  if (stripos($ua, 'Mobile') !== false || stripos($ua, 'Android') !== false) {
    $out['device_type'] = 'Mobile';
  }

  // Samsung model - extract and format to friendly name (SM-A217F → A21)
  if (preg_match('/(SM-[A-Z0-9]+)/i', $ua, $m)) {
    $raw_model = strtoupper($m[1]); // SM-A217F
    $out['device_model'] = $raw_model;
    $formatted = preg_replace('/^SM-/i', '', $raw_model); // A217F
    $formatted = preg_replace('/[FUB]$/i', '', $formatted); // A217
    // Shorten: A217 → A21
    if (strlen($formatted) > 3 && is_numeric($formatted[strlen($formatted)-1])) {
      $formatted = substr($formatted, 0, -1);
    }
    $out['friendly_device'] = 'Samsung ' . $formatted;
  } else {
    // fallback: try to extract model token from the UA parenthesis block
    if (preg_match('/\(([^\)]+)\)/', $ua, $para)) {
      $parts = preg_split('/[;\/,]+/', $para[1]);
      foreach ($parts as $p) {
        $t = trim($p);
        // match tokens like SM-A217F or A217F or A21s
        if (preg_match('/^(SM-[A-Z0-9-]+)$/i', $t, $mm)) {
          $raw_model = strtoupper($mm[1]);
          $out['device_model'] = $raw_model;
          $out['friendly_device'] = 'Samsung ' . preg_replace('/^SM-/i', '', $raw_model);
          break;
        }
        if (preg_match('/^[A-Z][0-9]{2,4}[A-Z0-9]?$/i', $t)) {
          $raw_model = strtoupper($t);
          $out['device_model'] = $raw_model;
          if (stripos($ua, 'samsung') !== false || stripos($ua, 'SM-') !== false) {
            $out['friendly_device'] = 'Samsung ' . $raw_model;
          } else {
            $out['friendly_device'] = $raw_model;
          }
          break;
        }
      }
    }
  }
  if (empty($out['friendly_device']) && stripos($ua, 'iPhone') !== false) {
    $out['friendly_device'] = 'iPhone';
  }
  if (empty($out['friendly_device']) && stripos($ua, 'iPad') !== false) {
    $out['friendly_device'] = 'iPad';
  }
  if (empty($out['friendly_device']) && stripos($ua, 'Windows') !== false) {
    $out['friendly_device'] = 'Windows Desktop';
  }
  if (empty($out['friendly_device']) && stripos($ua, 'Macintosh') !== false) {
    $out['friendly_device'] = 'Mac Desktop';
  }
  if (empty($out['friendly_device']) && stripos($ua, 'Linux') !== false && stripos($ua, 'Android') === false) {
    $out['friendly_device'] = 'Linux Desktop';
  }

  return $out;
}

/* =======================
   LOGIN HANDLER
======================= */
$error = '';
$msg = '';

// Check for auto-logout message
if (isset($_GET['msg'])) {
  $msg_type = $_GET['msg'];
  if ($msg_type === 'ip_removed') {
    $msg = 'Your IP has been removed from approved list. Please contact administrator.';
  }
}

// AJAX/poll endpoints for login approval flow
if (isset($_GET['action'])) {
  $act = $_GET['action'];
  if ($act === 'status') {
    $req = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;
    $token = $_GET['token'] ?? '';
    header('Content-Type: application/json');
    if ($req) {
      $st = $conn->prepare("SELECT status FROM staff_login_requests WHERE id = ? AND token = ?");
      $st->bind_param('is', $req, $token);
      $st->execute();
      $res = $st->get_result();
      $row = $res->fetch_assoc();
      $st->close();
      echo json_encode(['status' => $row['status'] ?? 'unknown']);
      exit;
    }
    echo json_encode(['status' => 'unknown']);
    exit;
  }
  if ($act === 'complete') {
    $req = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;
    $token = $_GET['token'] ?? '';
    if ($req) {
      $st = $conn->prepare("SELECT staff_id,status FROM staff_login_requests WHERE id = ? AND token = ?");
      $st->bind_param('is', $req, $token);
      $st->execute();
      $res = $st->get_result();
      $row = $res->fetch_assoc();
      $st->close();
      if ($row && $row['status'] === 'approved') {
        // create session now
        $sid = (int)$row['staff_id'];
        $sstmt = $conn->prepare("SELECT staff_id, name, role FROM staff WHERE staff_id = ?");
        $sstmt->bind_param('i', $sid);
        $sstmt->execute();
        $sstmt->bind_result($id,$name,$r);
        $sstmt->fetch();
        $sstmt->close();
        if ($id) {
          $_SESSION['staff_id'] = $id;
          $_SESSION['name'] = $name;
          $_SESSION['role'] = $r;
          // Set login_method to track this came from pending approval, NOT approved IP
          $_SESSION['login_method'] = 'pending_approval';
          $_SESSION['login_ip'] = null;  // Don't verify IP for pending approval flow
          // log final login
          $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
          $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
          $logDir = __DIR__ . "/logs";
          if (!is_dir($logDir)) mkdir($logDir, 0755, true);
          $log = ['type'=>'login','time'=>date('Y-m-d H:i:s'),'staff_id'=>$id,'name'=>$name,'ip'=>$ip,'device'=>$ua,'status'=>'approved'];
          file_put_contents("$logDir/staff_events.log", json_encode($log).PHP_EOL, FILE_APPEND);
          header('Location: index.php');
          exit;
        }
      }
    }
    // default fallback
    header('Location: login.php');
    exit;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $role = $_POST['userType'] ?? '';
  $email = $_POST['username'] ?? '';
  $pass = $_POST['password'] ?? '';

  if ($role && $email && $pass) {

    $stmt = $conn->prepare(
      "SELECT staff_id, name, role, access_level, password
       FROM staff WHERE email=? AND role=?"
    );
    $stmt->bind_param("ss", $email, $role);
    $stmt->execute();
    $stmt->bind_result($id, $name, $r, $access, $hash);
    $stmt->fetch();
    // Close the select statement early to avoid "Commands out of sync" when running
    // additional queries/prepared statements in the same request.
    $stmt->close();

    if ($id && password_verify($pass, $hash)) {
      // check blocked IPs for both admin and staff
      $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
      $bq = $conn->prepare("SELECT id FROM blocked_ips WHERE ip = ? LIMIT 1");
      $bq->bind_param('s', $ip);
      $bq->execute();
      $bq->store_result();
      if ($bq->num_rows > 0) {
        $error = 'Your IP is blocked. Contact administrator.';
        $bq->close();
      } else {
        $bq->close();
        // If user is admin, allow immediate login
        if (strtolower($r) === 'admin') {
          $_SESSION['staff_id'] = $id;
          $_SESSION['name'] = $name;
          $_SESSION['role'] = $r;
          // log immediate admin login
          $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
          $logDir = __DIR__ . "/logs";
          if (!is_dir($logDir)) mkdir($logDir, 0755, true);
          $log = ['type'=>'login','time'=>date('Y-m-d H:i:s'),'staff_id'=>$id,'name'=>$name,'ip'=>$ip,'device'=>$ua,'status'=>'approved','role'=>'admin'];
          file_put_contents("$logDir/staff_events.log", json_encode($log).PHP_EOL, FILE_APPEND);
          header('Location: index.php');
          exit;
        }

        // If this IP is approved for this system, allow staff immediate login
        $aq = $conn->prepare("SELECT id FROM approved_ips WHERE ip = ? LIMIT 1");
        $aq->bind_param('s', $ip);
        $aq->execute();
        $aq->store_result();
        if ($aq->num_rows > 0) {
          $aq->close();
          // create session now for staff
          $sstmt = $conn->prepare("SELECT staff_id, name, role FROM staff WHERE staff_id = ?");
          $sstmt->bind_param('i', $id);
          $sstmt->execute();
          $sstmt->bind_result($sid,$sname,$sr);
          $sstmt->fetch();
          $sstmt->close();
          if ($sid) {
            $_SESSION['staff_id'] = $sid;
            $_SESSION['name'] = $sname;
            $_SESSION['role'] = $sr;
            $_SESSION['login_ip'] = $ip;  // Store the IP so we can auto-logout if removed
            $_SESSION['login_method'] = 'approved_ip';  // Track that they logged in from approved IP, verify on every request
            // Track this session in DB so we can invalidate it when IP is removed
            $sess_id = session_id();
            try {
              $ins = $conn->prepare("REPLACE INTO approved_sessions (session_id, staff_id, ip, created_at) VALUES (?, ?, ?, NOW())");
              if ($ins) {
                $ins->bind_param('sis', $sess_id, $sid, $ip);
                $ins->execute();
                $ins->close();
              }
            } catch (Exception $e) { /* ignore */ }
            // log final login
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $logDir = __DIR__ . "/logs";
            if (!is_dir($logDir)) mkdir($logDir, 0755, true);
            $log = ['type'=>'login','time'=>date('Y-m-d H:i:s'),'staff_id'=>$sid,'name'=>$sname,'ip'=>$ip,'device'=>$ua,'status'=>'approved','auto_ip'=>true];
            file_put_contents("$logDir/staff_events.log", json_encode($log).PHP_EOL, FILE_APPEND);
            header('Location: index.php');
            exit;
          }
        }

        // create pending request for staff accounts
        $token = bin2hex(random_bytes(16));
        // get branch if available
        $branch_id = null;
        $bs = $conn->prepare("SELECT branch_id FROM staff WHERE staff_id = ? LIMIT 1");
        $bs->bind_param('i', $id);
        $bs->execute();
        $bres = $bs->get_result();
        $brow = $bres->fetch_assoc();
        $bs->close();
        if (!empty($brow['branch_id'])) $branch_id = (int)$brow['branch_id'];

        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        // Insert without token (we'll update token after insert)
        $stmt = $conn->prepare("INSERT INTO staff_login_requests (staff_id,name,branch_id,ip,ua,status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param('isiss', $id, $name, $branch_id, $ip, $ua);
        $stmt->execute();
        $req_id = $conn->insert_id;
        $stmt->close();
        if ($req_id) {
          $u = $conn->prepare("UPDATE staff_login_requests SET token = ? WHERE id = ?");
          $u->bind_param('si', $token, $req_id);
          $u->execute();
          $u->close();
        }

        // write to log for admin visibility
        $logDir = __DIR__ . "/logs";
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        $log = ['type'=>'login_request','time'=>date('Y-m-d H:i:s'),'staff_id'=>$id,'name'=>$name,'ip'=>$ip,'device'=>$ua,'request_id'=>$req_id,'status'=>'pending'];
        file_put_contents("$logDir/staff_events.log", json_encode($log).PHP_EOL, FILE_APPEND);

        // Send approval email to admin (use PHPMailer if available, otherwise fallback to mail())
        try {
          $admin_email = null;
          $cq = $conn->prepare("SELECT email FROM company_details LIMIT 1");
          if ($cq) {
            $cq->execute();
            $cres = $cq->get_result();
            $crow = $cres->fetch_assoc();
            $cq->close();
            if (!empty($crow['email'])) $admin_email = $crow['email'];
          }
          if (!$admin_email) $admin_email = 'admin@localhost';

          $approveUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . dirname($_SERVER['REQUEST_URI']) . '/staff.php?action=approve_request&request_id=' . urlencode($req_id) . '&token=' . urlencode($token);
          $rejectUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . dirname($_SERVER['REQUEST_URI']) . '/staff.php?action=reject_request&request_id=' . urlencode($req_id) . '&token=' . urlencode($token);

          $subject = "[Approval] Login request from {$name} ({$ip})";
          $body = '<html><body>';
          $body .= '<h3>Login approval request</h3>';
          $body .= '<p><strong>Staff:</strong> ' . htmlspecialchars($name) . '</p>';
          $body .= '<p><strong>IP:</strong> ' . htmlspecialchars($ip) . '<br>';
          $body .= '<strong>Device:</strong> ' . htmlspecialchars(substr($ua,0,200)) . '</p>';
          $body .= '<p>Please choose an action:</p>';
          $body .= '<p><a href="' . htmlspecialchars($approveUrl) . '" style="display:inline-block;padding:10px 14px;background:#28a745;color:#fff;text-decoration:none;border-radius:6px;margin-right:8px;">Approve</a>';
          $body .= '<a href="' . htmlspecialchars($rejectUrl) . '" style="display:inline-block;padding:10px 14px;background:#dc3545;color:#fff;text-decoration:none;border-radius:6px;">Reject</a></p>';
          $body .= '<p>Request ID: ' . intval($req_id) . '</p>';
          $body .= '</body></html>';

          // Use PHPMailer if available
          if (file_exists(__DIR__ . '/vendor/autoload.php')) {
            require_once __DIR__ . '/vendor/autoload.php';
            try {
              $mail = new PHPMailer\PHPMailer\PHPMailer(true);
              $mail->isSMTP();
              $mail->Host = 'mail.thennilavu.lk';
              $mail->SMTPAuth = true;
              $mail->Username = 'request@administration.thennilavu.lk';
              $mail->Password = 'Admin@1515';
              $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
              $mail->Port = 587;
              $mail->setFrom('request@administration.thennilavu.lk', 'Thennilavu Request');
              $mail->addAddress($admin_email);
              $mail->isHTML(true);
              $mail->Subject = $subject;
              $mail->Body = $body;
              $mail->send();
              // log email sent
              $elog = ['type'=>'approval_email','time'=>date('Y-m-d H:i:s'),'to'=>$admin_email,'request_id'=>$req_id,'method'=>'phpmailer','status'=>'sent'];
              file_put_contents("$logDir/staff_events.log", json_encode($elog).PHP_EOL, FILE_APPEND);
            } catch (Exception $e) {
              error_log('PHPMailer error: ' . $e->getMessage());
              $elog = ['type'=>'approval_email','time'=>date('Y-m-d H:i:s'),'to'=>$admin_email,'request_id'=>$req_id,'method'=>'phpmailer','status'=>'error','error'=>$e->getMessage()];
              file_put_contents("$logDir/staff_events.log", json_encode($elog).PHP_EOL, FILE_APPEND);
            }
          } else {
            // Fallback: use mail() with HTML headers
            $headers = "From: request@administration.thennilavu.lk\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            @mail($admin_email, $subject, $body, $headers);
            $elog = ['type'=>'approval_email','time'=>date('Y-m-d H:i:s'),'to'=>$admin_email,'request_id'=>$req_id,'method'=>'mail','status'=>'sent'];
            file_put_contents("$logDir/staff_events.log", json_encode($elog).PHP_EOL, FILE_APPEND);
          }
        } catch (Exception $e) {
          error_log('Approval email error: ' . $e->getMessage());
        }

        // show waiting page with poll
        ?>
        <!doctype html>
        <html><head><meta charset="utf-8"><title>Waiting for approval</title></head><body>
        <div style="max-width:480px;margin:80px auto;padding:20px;border:1px solid #ddd;border-radius:8px;font-family:Arial,Helvetica,sans-serif;">
          <h3>Login request submitted</h3>
          <p>Please wait while an administrator approves your login.</p>
          <p><strong>Name:</strong> <?= htmlspecialchars($name) ?><br>
          <strong>IP:</strong> <?= htmlspecialchars($ip) ?></p>
          <p id="status">Status: pending</p>
        </div>
        <script>
        (function(){
          var rid = <?= json_encode($req_id) ?>;
          var token = <?= json_encode($token) ?>;
          function check(){
            fetch('login.php?action=status&request_id='+rid+'&token='+token).then(r=>r.json()).then(function(j){
              document.getElementById('status').textContent = 'Status: '+j.status;
              if (j.status === 'approved') {
                window.location = 'login.php?action=complete&request_id='+rid+'&token='+token;
              } else if (j.status === 'rejected' || j.status === 'blocked') {
                // stop and show message
              } else setTimeout(check,3000);
            }).catch(function(){ setTimeout(check,3000); });
          }
          setTimeout(check,2000);
        })();
        </script>
        </body></html>
        <?php
        exit;
      }

    } else {
      $error = "Invalid login credentials";
    }
  } else {
    $error = "All fields required";
  }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Matrimony Admin Login</title>
<style>
body { background:#6b6bd6; font-family:Arial; display:flex; height:100vh; justify-content:center; align-items:center; }
.box { background:#fff; padding:30px; width:360px; border-radius:10px; }
input,select,button { width:100%; padding:10px; margin-top:10px; }
button { background:#4f46e5; color:#fff; border:none; cursor:pointer; }
.error { color:red; margin-top:10px; }
</style>
</head>
<body>

<div class="box">
<h2>Admin Login</h2>
<form method="post">
<select name="userType" required>
  <option value="">Select Role</option>
  <option value="admin">Admin</option>
  <option value="staff">Staff</option>
</select>
<input type="email" name="username" placeholder="Email" required>
<input type="password" name="password" placeholder="Password" required>

<input type="hidden" name="location" id="location">

<button type="submit">Login</button>

<?php if ($error): ?>
<div class="error"><?= $error ?></div>
<?php endif; ?>
<?php if ($msg): ?>
<div style="color:#ff9800; margin-top:10px; padding:10px; background:#fff3cd; border:1px solid #ffc107; border-radius:4px;"><?= $msg ?></div>
<?php endif; ?>
</form>
</div>

<style>
/* Simple full-screen overlay shown when location permission is required */
.loc-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.6); display:flex; align-items:center; justify-content:center; z-index:9999; }
.loc-card { background:#fff; padding:24px; border-radius:10px; max-width:420px; text-align:center; }
.loc-card button { margin-top:12px; padding:8px 14px; }
</style>
<div id="locOverlay" class="loc-overlay" style="display:none;">
  <div class="loc-card">
    <h3>Please allow Location</h3>
    <p>This site requires your location to continue. Please click "Allow" in the browser prompt.</p>
    <div>
      <button id="locRetryBtn">Retry</button>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  var locationInput = document.getElementById('location');
  var loginForm = document.querySelector('form');
  var submitBtn = loginForm.querySelector('button[type=submit]');
  var overlay = document.getElementById('locOverlay');
  var retry = document.getElementById('locRetryBtn');

  function requireLocation() {
    // disable submit until location is captured
    if (submitBtn) submitBtn.disabled = true;
    overlay.style.display = 'flex';
    attemptGeolocation();
  }

  function attemptGeolocation() {
    if (!navigator.geolocation) {
      alert('Geolocation not supported by your browser');
      return;
    }
    navigator.geolocation.getCurrentPosition(function(p){
      if (locationInput) locationInput.value = p.coords.latitude + ',' + p.coords.longitude;
      if (submitBtn) submitBtn.disabled = false;
      overlay.style.display = 'none';
    }, function(err){
      // Permission denied or timeout
      console.warn('Geolocation error:', err.message);
      overlay.style.display = 'flex';
      if (submitBtn) submitBtn.disabled = true;
    }, { enableHighAccuracy:false, timeout:15000, maximumAge:0 });
  }

  retry.addEventListener('click', function(){ attemptGeolocation(); });

  // Start flow: require location before allowing login
  requireLocation();
});
</script>

</body>
</html>
