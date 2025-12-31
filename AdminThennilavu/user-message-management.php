<?php
session_start();
if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

// Set these variables needed by header
$name = $_SESSION['name'];
$type = ucfirst($_SESSION['role']); // Admin/Staff

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

// --- Ensure messages table has new columns (message_type, reply_text, reply_sent_time) ---
// This prevents fatal errors if the DB wasn't manually migrated yet.
function ensure_messages_schema(mysqli $conn) {
  $ddlParts = [];
  // message_type
  $res = $conn->query("SHOW COLUMNS FROM messages LIKE 'message_type'");
  if ($res && $res->num_rows === 0) {
    $ddlParts[] = "ADD COLUMN message_type ENUM('user','admin') NOT NULL DEFAULT 'user' AFTER sent_time";
  }
  // reply_text
  $res = $conn->query("SHOW COLUMNS FROM messages LIKE 'reply_text'");
  if ($res && $res->num_rows === 0) {
    $ddlParts[] = "ADD COLUMN reply_text TEXT NULL AFTER message_text";
  }
  // reply_sent_time
  $res = $conn->query("SHOW COLUMNS FROM messages LIKE 'reply_sent_time'");
  if ($res && $res->num_rows === 0) {
    $ddlParts[] = "ADD COLUMN reply_sent_time DATETIME NULL AFTER reply_text";
  }
  if (!empty($ddlParts)) {
    // Attempt a single ALTER statement (ignore errors quietly)
    $alter = "ALTER TABLE messages " . implode(', ', $ddlParts);
    try { $conn->query($alter); } catch (Throwable $e) { /* silently ignore */ }
  }
}

ensure_messages_schema($conn);

/* --- Email (PHPMailer) bootstrap --- */
$mailer_ready = false; $mailer_error = '';

// Only include ONE Composer autoload to avoid duplicate ComposerAutoloaderInit class redeclare
$autoloadCandidates = [
  __DIR__ . '/vendor/autoload.php',                // local vendor
  __DIR__ . '/../vendor/autoload.php',             // one level up
  dirname(__DIR__) . '/user/vendor/autoload.php',  // same pattern used by packaged installs
  dirname(__DIR__) . '/../user/vendor/autoload.php'
  // NOTE: We intentionally skip adding more after we successfully include one
];

$autoloadUsed = null;
foreach ($autoloadCandidates as $auto) {
  if (is_file($auto)) { require_once $auto; $autoloadUsed = $auto; break; }
}

// If PHPMailer not available after first autoload, fallback to manual include from known full install
if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
  $manualBases = [
    dirname(__DIR__) . '/user/vendor/phpmailer/phpmailer/src/',
    dirname(__DIR__) . '/../user/vendor/phpmailer/phpmailer/src/' // safety variant
  ];
  $needed = ['Exception.php','PHPMailer.php','SMTP.php'];
  foreach ($manualBases as $base) {
    $ok = true;
    foreach ($needed as $f) { if (!is_file($base.$f)) { $ok = false; break; } }
    if ($ok) {
      foreach ($needed as $f) { require_once $base.$f; }
      if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) { break; }
    }
  }
}

if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
  $mailer_ready = true;
} else {
  $mailer_error = 'PHPMailer not loaded (autoloadUsed=' . ($autoloadUsed ? basename(dirname($autoloadUsed)) : 'none') . ')';

  // Final fallback: try legacy PHPMailer autoloader (older installs)
  $legacyAutoload = __DIR__ . '/phpmailer/PHPMailerAutoload.php';
  if (is_file($legacyAutoload)) {
    require_once $legacyAutoload;
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
      $mailer_ready = true; $mailer_error = '';
    }
  }
}

function send_app_email($fromEmail, $fromName, $toEmail, $subject, $htmlBody, &$err = null) {
  $err = null;
  // Validate basic email structure
  if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) { $err = 'Invalid recipient email'; return false; }
  if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) { $err = 'Invalid sender email'; return false; }
  try {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    // Provided credentials (consider moving to env vars in production)
    $mail->Username = 'kamalanathanthananchayan@gmail.com';
    $mail->Password = 'smfyhmyngvlcuhst';
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';
    // If running in a dev/local environment, relax SSL verification for TLS to avoid
    // 'unable to get local issuer certificate' errors. Only enable for localhost.
    if (!empty($_SERVER['SERVER_NAME']) && in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1'])) {
      $mail->SMTPOptions = [
        'ssl' => [
          'verify_peer' => false,
          'verify_peer_name' => false,
          'allow_self_signed' => true
        ]
      ];
    }
    // Important: set From to the SMTP account so Gmail doesn't reject the message.
    // Use the provided admin name as display or fallback to 'Admin'. Also add Reply-To
    $smtpFrom = 'kamalanathanthananchayan@gmail.com'; // must be same as $mail->Username for Gmail
    try {
      $mail->setFrom($smtpFrom, $fromName ?: 'Admin');
    } catch (Throwable $e) {
      // If setFrom fails for any reason, set a sensible default
      $mail->setFrom($mail->Username, $fromName ?: 'Admin');
    }
    // Preserve the original sender as Reply-To for reply purposes
    if (filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
      $mail->addReplyTo($fromEmail, $fromName ?: 'Admin');
    }
    $mail->addAddress($toEmail);
    $mail->Subject = $subject ?: '(no subject)';
    $mail->isHTML(true);
    $safeBody = nl2br(htmlspecialchars($htmlBody ?: '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    $mail->Body = '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.4">' . $safeBody . '</div>';
    $mail->AltBody = $htmlBody ?: '';
    // Collect SMTP debug output and set a level (this may help debugging connection problems)
    $debugOutput = '';
    $mail->SMTPDebug = 2; // 0=off, 1=client, 2=client+server
    $mail->Debugoutput = function($str, $level) use (&$debugOutput) { $debugOutput .= "[$level] $str\n"; };
    $mail->send();
    return true;
  } catch (Throwable $e) {
    // Include exception message plus collected SMTP debug output
    $errMsg = $e->getMessage();
    if (!empty($debugOutput)) {
      $errMsg .= "\nSMTP debug:\n" . $debugOutput;
    }
    // Avoid logging sensitive credential data (do not include $mail->Password)
    $err = $errMsg;
    return false;
  }
}

// If PHPMailer isn't available, provide a safe fallback using PHP mail(). This will
// be less reliable and doesn't support SMTP, but it avoids the entire subsystem
// completely failing in situations where PHPMailer isn't installed.
function send_app_email_fallback($fromEmail, $fromName, $toEmail, $subject, $htmlBody, &$err = null) {
  $err = null;
  if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) { $err = 'Invalid recipient email'; return false; }
  if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) { $err = 'Invalid sender email'; return false; }
  $headers = [];
  // For PHP mail fallback, set From to the provided sender and include Reply-To
  $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
  $headers[] = 'Reply-To: ' . $fromName . ' <' . $fromEmail . '>';
  $headers[] = 'MIME-Version: 1.0';
  $headers[] = 'Content-type: text/html; charset=UTF-8';
  $headerString = implode("\r\n", $headers);
  $ok = @mail($toEmail, $subject, $htmlBody, $headerString);
  if ($ok) return true;
  $err = 'mail() function returned false';
  return false;
}

// Helper to log non-sensitive email errors for debugging
function log_email_error($context, $err) {
  try {
    $logPath = __DIR__ . '/email_errors.log';
    $time = date('Y-m-d H:i:s');
    $entry = sprintf("[%s] %s - %s\n\n", $time, $context, $err);
    // Avoid writing if file not writable or disabled
    if (is_writable(dirname($logPath)) || (!file_exists($logPath) && is_writable(dirname($logPath)))) {
      file_put_contents($logPath, $entry, FILE_APPEND | LOCK_EX);
    }
  } catch (Throwable $e) {
    // Intentionally ignore logging failures
  }
}

// Handle reply form submission (one-time reply to a user message)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
  $sender_name       = trim($_POST['sender_name'] ?? ''); // admin name
  $sender_email      = trim($_POST['sender_email'] ?? ''); // admin email
  $recipient_email   = trim($_POST['recipient_email'] ?? ''); // original user sender email
  $subject           = trim($_POST['subject'] ?? '');
  $reply_text        = trim($_POST['message_text'] ?? '');
  $original_message_id = intval($_POST['original_message_id'] ?? 0);

  if ($sender_name && $sender_email && $recipient_email && $subject && $reply_text && $original_message_id > 0) {
    // Ensure original message exists and not already replied
    $chk = $conn->prepare("SELECT message_id, reply_text FROM messages WHERE message_id=? LIMIT 1");
    if ($chk) {
      $chk->bind_param('i', $original_message_id);
      $chk->execute();
      $res = $chk->get_result();
      if ($row = $res->fetch_assoc()) {
        if (!empty($row['reply_text'])) {
          $error_message = 'This message already has a reply.';
        } else {
          $upd = $conn->prepare("UPDATE messages SET reply_text=?, reply_sent_time=NOW() WHERE message_id=? AND reply_text IS NULL");
          if ($upd) {
            $upd->bind_param('si', $reply_text, $original_message_id);
            if ($upd->execute() && $upd->affected_rows === 1) {
              // Attempt to send email reply
              if ($mailer_ready) {
                $mailErr = null;
                $sentOk = send_app_email($sender_email, $sender_name, $recipient_email, $subject, $reply_text, $mailErr);
                if ($sentOk) {
                  $success_message = 'Reply sent & stored successfully!';
                } else {
                  $success_message = 'Reply stored. Email failed.';
                  $error_message = 'Email error: '.htmlspecialchars($mailErr ?? '');
                  log_email_error('reply_message PHPMailer', $mailErr ?? 'empty');
                }
              } else {
                // try fallback send through PHP mail() if available
                $mailErr = null;
                $sentOk = false;
                if (function_exists('send_app_email_fallback')) {
                  $sentOk = send_app_email_fallback($sender_email, $sender_name, $recipient_email, $subject, $reply_text, $mailErr);
                }
                if ($sentOk) {
                  $success_message = 'Reply sent & stored (fallback) successfully!';
                } else {
                  $success_message = 'Reply stored (email subsystem not available)';
                  if ($mailer_error) $error_message = 'Mailer bootstrap: '.htmlspecialchars($mailer_error);
                  if ($mailErr) { $error_message .= ' Fallback email error: '.htmlspecialchars($mailErr); log_email_error('reply_message fallback', $mailErr); }
                }
              }
            } else {
              $error_message = 'Failed to update original message (maybe already replied).';
            }
            $upd->close();
          } else {
            $error_message = 'DB prepare failed for reply update.';
          }
        }
      } else {
        $error_message = 'Original message not found.';
      }
      $chk->close();
    } else {
      $error_message = 'DB prepare failed for original message check.';
    }
  } else {
    $error_message = 'All reply fields required.';
  }
}

// Handle new message submission (send email + store) - treated as admin-originated
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
  $sender_name     = trim($_POST['sender_name'] ?? '');
  $sender_email    = trim($_POST['sender_email'] ?? '');
  $recipient_email = trim($_POST['recipient_email'] ?? '');
  $subject         = trim($_POST['subject'] ?? '');
  $message_text    = trim($_POST['message_text'] ?? '');
  $priority        = trim($_POST['priority'] ?? 'medium');

  if ($sender_name && $sender_email && $recipient_email && $subject && $message_text) {
    $stmt = $conn->prepare("INSERT INTO messages (sender_name, sender_email, subject, message_text, sent_time, message_type) VALUES (?, ?, ?, ?, NOW(), 'admin')");
    if ($stmt) {
      $stmt->bind_param("ssss", $sender_name, $sender_email, $subject, $message_text);
            if ($stmt->execute()) {
        if ($mailer_ready) {
          $mailErr = null;
          $sentOk = send_app_email($sender_email, $sender_name, $recipient_email, $subject, $message_text, $mailErr);
          if ($sentOk) {
            $success_message = "Message stored & emailed successfully!";
          } else {
            $success_message = "Message stored. Email failed.";
                  $error_message = 'Email error: '.htmlspecialchars($mailErr ?? '');
          }
        } else {
                // Attempt a simpler fallback (PHP mail()) if PHPMailer isn't available
                $mailErr = null;
                $sentOk = false;
                if (function_exists('send_app_email_fallback')) {
                  $sentOk = send_app_email_fallback($sender_email, $sender_name, $recipient_email, $subject, $message_text, $mailErr);
                }
                if ($sentOk) {
                  $success_message = "Message stored & emailed (fallback) successfully!";
                } else {
                  $success_message = "Message stored (email subsystem not available)";
                  if ($mailer_error) $error_message = 'Mailer bootstrap: '.htmlspecialchars($mailer_error);
                  if ($mailErr) { $error_message .= ' Fallback email error: '.htmlspecialchars($mailErr); log_email_error('send_message fallback', $mailErr); }
                }
        }
      } else {
        $error_message = "DB insert failed.";
      }
      $stmt->close();
    } else {
      $error_message = "DB prepare failed.";
    }
  } else {
    $error_message = "All fields are required.";
  }
}

// Fetch separated message groups
$adminMessages = $conn->query("SELECT * FROM messages WHERE message_type='admin' ORDER BY sent_time DESC");
$userMessages  = $conn->query("SELECT * FROM messages WHERE message_type='user' ORDER BY sent_time DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>User Message Management</title>
  
  <link rel="stylesheet" href="maincontent.css"/>
  <link rel="stylesheet" href="tables.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="header.css"/>
  <style>
    /* Reset and base styles */
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    
    body {
      font-family: 'Inter', sans-serif;
      line-height: 1.6;
      color: #333;
      background-color: #f5f7f9;
      overflow-x: hidden;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
      /* Mobile adjustments */
      .dashboard-layout {
        flex-direction: column;
      }
      
      .sidebar {
        position: fixed;
        top: 0;
        left: -250px;
        width: 250px;
        height: 100%;
        z-index: 1001;
        transition: left 0.3s ease;
        overflow-y: auto;
      }
      
      .sidebar.active {
        left: 0;
      }
      
      .main-content {
        margin-left: 0 !important;
        width: 100%;
        padding: 15px;
        min-height: calc(100vh - 70px);
      }
      
      .top-nav {
        padding: 10px 15px;
        position: sticky;
        top: 0;
        z-index: 999;
      }
      
      .logo-circle {
        width: 35px;
        height: 35px;
        font-size: 14px;
      }
      
      .matrimony-name-top {
        font-size: 16px;
      }
      
      .nav-right {
        flex-direction: column;
        align-items: flex-end;
        gap: 5px;
      }
      
      .user-info {
        font-size: 14px;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
      }
      
      .content-section {
        padding: 15px;
        margin-bottom: 15px;
        width: 100%;
        overflow: hidden;
      }
      
      .form-row {
        flex-direction: column;
        gap: 0;
      }
      
      .form-column {
        width: 100%;
      }
      
      .data-table-container {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }
      
      .data-table {
        min-width: 800px; /* Ensure table has enough width */
      }
      
      .data-table th, 
      .data-table td {
        padding: 8px 10px;
        font-size: 14px;
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }
      
      .action-btn {
        padding: 8px 15px;
        font-size: 14px;
      }
      
      .modal-content {
        width: 95%;
        margin: 10% auto;
        padding: 15px;
        max-height: 90vh;
        overflow-y: auto;
      }
      
      .tab-buttons {
        flex-wrap: wrap;
      }
      
      .tab-btn {
        flex: 1;
        min-width: 80px;
        font-size: 14px;
      }
      
      /* Mobile-specific table adjustments */
      .mobile-table-container {
        width: 100%;
        overflow-x: auto;
      }
      
      .search-bar {
        margin: 15px 0;
        width: 100%;
      }
      
      .search-bar input {
        width: 100%;
      }
      
      /* Improve button spacing on mobile */
      .data-table td .reply-btn, 
      .data-table td .delete-btn {
        margin-bottom: 5px;
        display: block;
        width: 100%;
        text-align: center;
      }
    }
    
    @media (min-width: 769px) and (max-width: 1024px) {
      /* Tablet adjustments */
      .sidebar {
        width: 200px;
      }
      
      .main-content {
        margin-left: 200px !important;
        padding: 20px;
      }
      
      .data-table th, 
      .data-table td {
        padding: 10px 12px;
      }
      
      .modal-content {
        width: 90%;
        max-width: 700px;
      }
      
      .content-section {
        padding: 20px;
      }
    }
    
    @media (min-width: 1025px) {
      /* Desktop adjustments */
      .sidebar {
        width: 250px;
      }
      
      .main-content {
        margin-left: 250px !important;
        padding: 25px;
      }
      
      .content-section {
        padding: 25px;
      }
    }
    
    /* Common responsive styles */
    .menu-toggle {
      display: none;
      background: none;
      border: none;
      font-size: 20px;
      cursor: pointer;
      margin-right: 10px;
      color: #333;
    }
    
    @media (max-width: 768px) {
      .menu-toggle {
        display: block;
      }
    }
    
    .overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      z-index: 1000;
    }
    
    .overlay.active {
      display: block;
    }
    
    /* Increase delete button width and prevent font overflow */
    .data-table td .delete-btn {
      min-width: 70px;
      white-space: nowrap;
      text-align: center;
    }
    
    /* Align reply and delete buttons horizontally with spacing */
    .data-table td .reply-btn, .data-table td .delete-btn {
      margin-right: 5px;
      vertical-align: middle;
      display: inline-block;
    }
    
    @media (max-width: 768px) {
      .data-table td .reply-btn, .data-table td .delete-btn {
        margin-right: 0;
        margin-bottom: 5px;
        display: block;
      }
    }
    
    .data-table td {
      white-space: nowrap;
      max-width: 200px;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    
    /* Make all text inside reply modal white */
    #replyModal .modal-content, #replyModal .modal-content * {
      color: #1a0303ff !important;
    }
    
    /* Improve action button alignment in modal */
    #replyModal .form-actions {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
    }
    
    .modal {
      display: none;
      position: fixed;
      z-index: 1100;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background-color: rgba(0,0,0,0.4);
    }
    
    .modal-content {
      background-color: #9353ccff;
      margin: 5% auto;
      padding: 20px;
      border: 1px solid #888;
      width: 80%;
      max-width: 600px;
      border-radius: 8px;
      position: relative;
    }
    
    .close {
      color: #aaa;
      float: right;
      font-size: 28px;
      font-weight: bold;
      cursor: pointer;
      line-height: 1;
    }
    
    .close:hover,
    .close:focus {
      color: black;
      text-decoration: none;
    }
    
    .alert {
      padding: 10px;
      margin: 10px 0;
      border-radius: 4px;
      width: 100%;
    }
    
    .alert-success {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    
    .alert-error {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    
    /* Basic styling for elements that might not be defined in other CSS files */
    .top-nav {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 15px 20px;
      background-color: #fff;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      width: 100%;
    }
    
    .logo-container {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .logo-circle {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background-color: #4a90e2;
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
    }
    
    .dashboard-layout {
      display: flex;
      min-height: calc(100vh - 70px);
      width: 100%;
    }
    
    .main-content {
      flex: 1;
      padding: 20px;
      background-color: #f5f7f9;
      width: 100%;
      overflow-x: hidden;
    }
    
    .content-section {
      background: white;
      border-radius: 8px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
      width: 100%;
      overflow: hidden;
    }
    
    .form-row {
      display: flex;
      gap: 20px;
      margin-bottom: 15px;
      width: 100%;
    }
    
    .form-column {
      flex: 1;
      min-width: 0; /* Prevent flex items from overflowing */
    }
    
    .form-group {
      margin-bottom: 15px;
      width: 100%;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 5px;
      font-weight: 600;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-family: inherit;
    }
    
    .form-actions {
      display: flex;
      gap: 10px;
      width: 100%;
    }
    
    .action-btn {
      padding: 10px 20px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-weight: 600;
      font-family: inherit;
    }
    
    .action-btn.primary {
      background-color: #4a90e2;
      color: white;
    }
    
    .action-btn.secondary {
      background-color: #f1f1f1;
      color: #333;
    }
    
    .data-table-container {
      width: 100%;
      overflow-x: auto;
    }
    
    .data-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 800px; /* Ensure table has minimum width */
    }
    
    .data-table th,
    .data-table td {
      padding: 12px 15px;
      text-align: left;
      border-bottom: 1px solid #ddd;
    }
    
    .data-table th {
      background-color: #f8f9fa;
      font-weight: 600;
      position: sticky;
      top: 0;
    }
    
    .popup-btn, .reply-btn, .delete-btn {
      padding: 6px 12px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-family: inherit;
    }
    
    .reply-btn {
      background-color: #4a90e2;
      color: white;
    }
    
    .delete-btn {
      background-color: #e24a4a;
      color: white;
    }
    
    .tab-buttons {
      display: flex;
      gap: 10px;
      margin-bottom: 15px;
      width: 100%;
    }
    
    .tab-btn {
      padding: 8px 16px;
      border: 1px solid #ddd;
      background: white;
      border-radius: 4px;
      cursor: pointer;
      font-family: inherit;
    }
    
    .tab-btn.active {
      background-color: #4a90e2;
      color: white;
      border-color: #4a90e2;
    }
    
    .search-bar {
      position: relative;
      margin: 20px 0;
      width: 100%;
    }
    
    .search-bar i {
      position: absolute;
      left: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: #999;
    }
    
    .search-bar input {
      width: 100%;
      padding: 10px 10px 10px 40px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-family: inherit;
    }
    
    /* Ensure all content is visible at 100% zoom */
    html {
      -webkit-text-size-adjust: 100%;
    }
    
    .nav-right {
      display: flex;
      align-items: center;
      gap: 15px;
    }
    
    .main-menu {
      display: flex;
      align-items: center;
    }
    
    .logout {
      display: flex;
      align-items: center;
      gap: 5px;
      text-decoration: none;
      color: #c02525ff;
    }
    
    /* Make sure tables don't overflow their containers */
    .data-table-container {
      -webkit-overflow-scrolling: touch;
      border: 1px solid #e0e0e0;
      border-radius: 4px;
    }
    
    /* Responsive text sizes */
    @media (max-width: 480px) {
      .content-section h2 {
        font-size: 1.3rem;
      }
      
      .data-table th, 
      .data-table td {
        font-size: 12px;
        padding: 6px 8px;
      }
      
      .popup-btn, .reply-btn, .delete-btn {
        padding: 4px 8px;
        font-size: 12px;
      }
    }
  </style>
</head>
<body>
  <!-- Overlay for mobile sidebar -->
  <div class="overlay" id="overlay"></div>
  
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
        <li><a href="members.php" class="sidebar-link"><i class="fas fa-users"></i> ManageMembers</a></li>
        <li><a href="call-management.php" class="sidebar-link"><i class="fas fa-phone"></i> Call Management</a></li>
        <li><a href="user-message-management.php" class="sidebar-link"><i class="fas fa-comments"></i> User Messages</a></li>
        <li><a href="review-management.php" class="sidebar-link"><i class="fas fa-star"></i> Review</a></li>
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
      <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
      <?php endif; ?>
      
      <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
      <?php endif; ?>

      <?php
        if (!isset($success_message) && !isset($error_message) && isset($_GET['msg'])) {
          $msgMap = [
            'deleted'    => ['type' => 'success', 'text' => 'Message deleted successfully.'],
            'not_found'  => ['type' => 'error',   'text' => 'Message not found or already deleted.'],
            'invalid'    => ['type' => 'error',   'text' => 'Invalid message id.'],
            'prep_failed'=> ['type' => 'error',   'text' => 'Delete prepare failed.']
          ];
          $code = $_GET['msg'];
          if (isset($msgMap[$code])) {
            $m = $msgMap[$code];
            echo '<div class="alert alert-' . ($m['type']==='success'?'success':'error') . '">' . htmlspecialchars($m['text']) . '</div>';
          }
        }
      ?>

      <!-- Message Form -->
      <div class="content-section">
        <h2>Send Message</h2>
        <form class="message-form" method="POST" action="">
          <div class="form-row">
            <div class="form-column">
              <div class="form-group">
                <label>From (Sender Name)</label>
                <input type="text" name="sender_name" placeholder="Enter sender name" required/>
              </div>
              <div class="form-group">
                <label>From (Sender Email)</label>
                <input type="email" name="sender_email" placeholder="Enter sender email" required/>
              </div>
            </div>
            <div class="form-column">
              <div class="form-group">
                <label>To (Recipient Email)</label>
                <input type="email" name="recipient_email" placeholder="Enter recipient email" required/>
              </div>
              <div class="form-group">
                <label>Priority</label>
                <select name="priority">
                  <option value="low">Low</option>
                  <option value="medium">Medium</option>
                  <option value="high">High</option>
                </select>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Subject</label>
            <input type="text" name="subject" placeholder="Enter message subject" required/>
          </div>
          <div class="form-group">
            <label>Message Content</label>
            <textarea name="message_text" placeholder="Enter your message here..." rows="6" required></textarea>
          </div>
          <div class="form-actions">
            <button type="submit" name="send_message" class="action-btn primary">Send Message</button>
            <button type="button" class="action-btn secondary">Save Draft</button>
          </div>
        </form>
      </div>

      <!-- Search Bar -->
      <div class="search-bar">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Search messages" id="searchInput"/>
      </div>

      <!-- User Messages (Incoming) -->
      <div class="content-section">
        <h2>User Messages (Inbox)</h2>
        <div class="data-table-container">
          <table class="data-table">
            <thead>
              <tr>
                
                <th>User Name</th>
                <th>User Email</th>
                <th>Subject</th>
                <th>Message</th>
                <th>Reply Content</th>
                
                <th>Reply / Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php if ($userMessages && $userMessages->num_rows > 0) { while ($row = $userMessages->fetch_assoc()) { ?>
              <tr>
               
                <td><?= htmlspecialchars($row['sender_name']); ?></td>
                
                <td style="white-space: nowrap; overflow: visible; text-overflow: clip;">
    <?= htmlspecialchars($row['sender_email']); ?> <br>
    <?= htmlspecialchars($row['sent_time']); ?>
   </td>
                <td><?= htmlspecialchars($row['subject']); ?></td>
                <td><button class="popup-btn" onclick="openViewModal('Message','<?= addslashes($row['message_text']); ?>','User: <?= addslashes($row['sender_name']); ?> | Email: <?= addslashes($row['sender_email']); ?>')">View</button></td>
                <td>
                  <?php if (!empty($row['reply_text'])): ?>
                    <button class="popup-btn" onclick="openViewModal('Reply','<?= addslashes($row['reply_text']); ?>','Replied At: <?= addslashes($row['reply_sent_time'] ?? '') ?>')">View Reply</button>
                  <?php else: ?>
                    <span style="color:#888;">—</span>
                  <?php endif; ?>
                </td>
                
                <td>
                  <?php if (!empty($row['reply_text'])): ?>
                    <span style="color:green;font-weight:600;">Replied</span>
                  <?php else: ?>
                    <button class="reply-btn" onclick="openReplyModal('<?= addslashes($row['sender_email']); ?>','<?= addslashes($row['subject']); ?>','<?= addslashes($row['message_text']); ?>','<?= addslashes($row['sender_name']); ?>', <?= (int)$row['message_id']; ?>)">Reply</button>
                  <?php endif; ?>
                  <a href="delete_message.php?id=<?= $row['message_id']; ?>" onclick="return confirm('Are you sure?')"><button class="delete-btn">Delete</button></a>
                </td>
              </tr>
            <?php } } else { ?>
              <tr><td colspan="8" style="text-align:center;">No user messages</td></tr>
            <?php } ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Admin Sent Messages -->
      <div class="content-section">
        <h2>Admin Sent Messages (Outbox)</h2>
        <div class="data-table-container">
          <table class="data-table">
            <thead>
              <tr>

                <th>From Name</th>
                <th>From Email</th>
                <th>Subject</th>
                <th>Message</th>
                <th>Reply Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php if ($adminMessages && $adminMessages->num_rows > 0) { while ($row = $adminMessages->fetch_assoc()) { ?>
              <tr>
                
                <td><?= htmlspecialchars($row['sender_name']); ?></td>


                <td style="white-space: nowrap; overflow: visible; text-overflow: clip;">
    <?= htmlspecialchars($row['sender_email']); ?> <br>
    <?= htmlspecialchars($row['sent_time']); ?>
   </td>

                <td style="white-space: normal; word-wrap: break-word;">
    <?= nl2br(htmlspecialchars($row['subject'])); ?>
</td>

                <td><button class="popup-btn" onclick="openViewModal('Message','<?= addslashes($row['message_text']); ?>','Admin: <?= addslashes($row['sender_name']); ?> | Email: <?= addslashes($row['sender_email']); ?>')">View</button></td>
                
                <td><?= !empty($row['reply_text']) ? '<span style="color:green;font-weight:600;">User Replied</span>' : '<span style="color:#888;">—</span>'; ?></td>
                <td><a href="delete_message.php?id=<?= $row['message_id']; ?>" onclick="return confirm('Are you sure?')"><button class="delete-btn">Delete</button></a></td>
              </tr>
            <?php } } else { ?>
              <tr><td colspan="8" style="text-align:center;">No admin messages</td></tr>
            <?php } ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Reply Modal -->
      <div id="replyModal" class="modal">
        <div class="modal-content">
          <span class="close" onclick="closeModal()">&times;</span>
          <h2>Reply to Message</h2>
          <form class="message-form" method="POST" action="">
            <input type="hidden" name="recipient_email" id="reply_recipient">
            <input type="hidden" name="original_message_id" id="original_message_id">
            <div class="form-group">
              <label>From (Sender Name)</label>
              <input type="text" name="sender_name" id="reply_sender_name" placeholder="Your name" required>
            </div>
            <div class="form-group">
              <label>From (Sender Email)</label>
              <input type="email" name="sender_email" id="reply_sender_email" placeholder="Your email" required>
            </div>
            <div class="form-group">
              <label>Subject</label>
              <input type="text" name="subject" id="reply_subject" required>
            </div>
            <div class="form-group">
              <label>Priority</label>
              <select name="priority">
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
              </select>
            </div>
            <div class="form-group">
              <label>Original Message</label>
              <textarea id="original_message" readonly rows="3"></textarea>
            </div>
            <div class="form-group">
              <label>Your Reply</label>
              <textarea name="message_text" placeholder="Type your reply here..." rows="6" required></textarea>
            </div>
            <div class="form-actions">
              <button type="submit" name="reply_message" class="action-btn primary">Send Reply</button>
              <button type="button" class="action-btn secondary" onclick="closeModal()">Cancel</button>
            </div>
          </form>
        </div>
      </div>

      <!-- View Modal -->
      <div id="viewModal" class="modal">
        <div class="modal-content">
          <span class="close" onclick="closeViewModal()">&times;</span>
          <h2 id="viewModalTitle">View</h2>
          <div class="form-group">
            <label id="viewMetaLabel" style="font-weight:600;display:block;margin-bottom:6px;"></label>
            <div id="viewBody" style="white-space:pre-wrap;background:#fff;padding:12px;border:1px solid #ccc;border-radius:4px;max-height:300px;overflow:auto;"></div>
          </div>
          <div class="form-actions" style="justify-content:flex-end;">
            <button type="button" class="action-btn secondary" onclick="closeViewModal()">Close</button>
          </div>
        </div>
      </div>

      <!-- Inbox/Outbox Tabs -->
      <div class="content-section">
       
        <div class="message-list">
          <!-- Message items will be displayed here -->
        </div>
      </div>
    </main>
  </div>
  
  <script>
    // Mobile sidebar toggle
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    
    if (menuToggle && sidebar && overlay) {
      menuToggle.addEventListener('click', function() {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
      });
      
      overlay.addEventListener('click', function() {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
      });
      
      // Close sidebar when clicking on a link (mobile)
      const sidebarLinks = document.querySelectorAll('.sidebar-link');
      sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
          sidebar.classList.remove('active');
          overlay.classList.remove('active');
          document.body.style.overflow = '';
        });
      });
    }
    
    // Get the modal
    var modal = document.getElementById("replyModal");
    
    // Function to open the reply modal
    function openReplyModal(email, subject, originalMessage, senderName, messageId) {
      document.getElementById('reply_recipient').value = email;
      document.getElementById('reply_subject').value = 'Re: ' + subject;
      document.getElementById('original_message').value = originalMessage;
      document.getElementById('reply_sender_name').value = 'Admin';
      document.getElementById('reply_sender_email').value = 'admin@matrimony.com';
      document.getElementById('original_message_id').value = messageId;
      modal.style.display = 'block';
      document.body.style.overflow = 'hidden';
    }
    
    // Function to close the modal
    function closeModal() {
      modal.style.display = "none";
      document.body.style.overflow = '';
    }
    
    // Close the modal when clicking outside of it
    window.onclick = function(event) {
      if (event.target === modal) { 
        closeModal(); 
      }
      if (event.target === viewModal) { 
        closeViewModal(); 
      }
    }
    
    // View modal logic
    var viewModal = document.getElementById('viewModal');
    function openViewModal(title, body, meta) {
      document.getElementById('viewModalTitle').textContent = title || 'View';
      document.getElementById('viewBody').textContent = body || '';
      document.getElementById('viewMetaLabel').textContent = meta || '';
      viewModal.style.display = 'block';
      document.body.style.overflow = 'hidden';
    }
    function closeViewModal() { 
      viewModal.style.display = 'none'; 
      document.body.style.overflow = '';
    }

    // Check authentication on page load
    document.addEventListener('DOMContentLoaded', function() {
      const isLoggedIn = localStorage.getItem('isLoggedIn');
      const userType = localStorage.getItem('userType');
      const username = localStorage.getItem('username');
      
      if (!isLoggedIn) {
        window.location.href = 'login.php';
        return;
      }
      
      // Display user info
      if (username && userType) {
        console.log(`Welcome ${username} (${userType})`);
        const userDisplay = document.getElementById('userDisplay');
        if (userDisplay) {
          userDisplay.textContent = `${userType.toUpperCase()}`;
        }
      }

      const staffLink = document.getElementById('staffLink');
      if (staffLink && userType !== 'admin') {
        staffLink.style.display = 'none';
      }
      
      // Initialize client-side search for messages
      setupMessageSearch();
      
      // Ensure content fits on screen
      adjustLayoutForZoom();
    });

    // Setup message search to filter Inbox and Outbox tables
    function setupMessageSearch() {
      const searchInput = document.getElementById('searchInput');
      if (!searchInput) return;

      function filterTable(table) {
        if (!table) return;
        const rows = table.querySelectorAll('tbody tr');
        const term = searchInput.value.toLowerCase().trim();
        rows.forEach(row => {
          const cells = row.querySelectorAll('td');
          if (!cells || cells.length === 0) return;

          // Collect searchable text from key columns
          const id = (cells[0] && cells[0].textContent) ? cells[0].textContent.toLowerCase() : '';
          const name = (cells[1] && cells[1].textContent) ? cells[1].textContent.toLowerCase() : '';
          const email = (cells[2] && cells[2].textContent) ? cells[2].textContent.toLowerCase() : '';
          const subject = (cells[3] && cells[3].textContent) ? cells[3].textContent.toLowerCase() : '';
          const msgPreview = (cells[4] && cells[4].textContent) ? cells[4].textContent.toLowerCase() : '';
          const sent = (cells[6] && cells[6].textContent) ? cells[6].textContent.toLowerCase() : '';

          const hay = [id, name, email, subject, msgPreview, sent].join(' ');
          const match = term === '' || hay.indexOf(term) !== -1;
          row.style.display = match ? '' : 'none';
        });
      }

      // Locate tables by their section headings to be robust
      let inboxTable = null, outboxTable = null;
      document.querySelectorAll('.content-section').forEach(section => {
        const heading = section.querySelector('h2');
        if (!heading) return;
        const title = heading.textContent.toLowerCase();
        const tbl = section.querySelector('.data-table');
        if (!tbl) return;
        if (title.includes('user messages') || title.includes('inbox')) {
          inboxTable = tbl;
        } else if (title.includes('admin sent') || title.includes('outbox')) {
          outboxTable = tbl;
        }
      });

      searchInput.addEventListener('input', function() {
        filterTable(inboxTable);
        filterTable(outboxTable);
      });
    }
    
    // Adjust layout for different zoom levels
    function adjustLayoutForZoom() {
      const width = window.innerWidth;
      const height = window.innerHeight;
      
      // Adjust table container widths if needed
      const tableContainers = document.querySelectorAll('.data-table-container');
      tableContainers.forEach(container => {
        const parentWidth = container.parentElement.clientWidth;
        container.style.width = `${parentWidth}px`;
      });
      
      // Adjust modal positioning if needed
      if (width < 768) {
        document.querySelectorAll('.modal-content').forEach(modal => {
          modal.style.marginTop = '5%';
        });
      }
    }
    
    // Listen for window resize
    window.addEventListener('resize', adjustLayoutForZoom);
  </script>
</body>
</html>