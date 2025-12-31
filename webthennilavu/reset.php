<?php
session_start();
$conn = new mysqli('localhost', 'thennilavu_matrimonial', 'OYVuiEKfS@FQ', 'thennilavu_thennilavu');
if ($conn->connect_error) { die('DB error: ' . $conn->connect_error); }

$email = $_GET['email'] ?? '';
$token = $_GET['token'] ?? '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $token = $_POST['token'];
    $newpass = $_POST['password'];
    $confpass = $_POST['confirm_password'];

    if ($newpass === $confpass) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email=? AND password_reset_token=? AND password_reset_expires > NOW()");
        $stmt->bind_param('ss', $email, $token);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $hash = password_hash($newpass, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE users SET password=?, password_reset_token=NULL, password_reset_expires=NULL WHERE email=?");
            $upd->bind_param('ss', $hash, $email);
            $upd->execute();
            $msg = '<div class="alert alert-success">Password reset successful. <a href="login.php">Login</a></div>';
        } else {
            $msg = '<div class="alert alert-danger">Invalid or expired token.</div>';
        }
    } else {
        $msg = '<div class="alert alert-danger">Passwords do not match.</div>';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Reset Password</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-5">
  <div class="container">
    <h2>Reset Password</h2>
    <?php echo $msg; ?>
    <form method="post">
      <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
      <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
      <div class="mb-2">
        <input type="password" name="password" class="form-control" placeholder="New Password" required>
      </div>
      <div class="mb-2">
        <input type="password" name="confirm_password" class="form-control" placeholder="Confirm Password" required>
      </div>
      <button type="submit" class="btn btn-success">Reset Password</button>
    </form>
  </div>
</body>
</html>
