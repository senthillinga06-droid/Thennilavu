<?php
session_start();
require '../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require '../vendor/phpmailer/phpmailer/src/SMTP.php';
require '../vendor/phpmailer/phpmailer/src/Exception.php';
use PHPMailer\PHPMailer\PHPMailer;

$conn = new mysqli('localhost', 'thennilavu_matrimonial', 'OYVuiEKfS@FQ', 'thennilavu_thennilavu');
if ($conn->connect_error) { die('DB error: ' . $conn->connect_error); }

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email) {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email=?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 1800); // 30 min
            $upd = $conn->prepare('UPDATE users SET password_reset_token=?, password_reset_expires=? WHERE email=?');
            $upd->bind_param('sss', $token, $expires, $email);
            $upd->execute();

            // Send email
            $resetLink = 'http://localhost/thennilavu-main/user/reset.php?email=' . urlencode($email) . '&token=' . $token;

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'your_gmail@gmail.com';
            $mail->Password = 'your_gmail_app_password';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('your_gmail@gmail.com', 'Thennilavu');
            $mail->addAddress($email);
            $mail->Subject = 'Password Reset Link';
            $mail->Body = "Click this link to reset your password: $resetLink";

            if ($mail->send()) {
                $msg = '<div class="alert alert-success">Reset link sent to your email!</div>';
            } else {
                $msg = '<div class="alert alert-danger">Mail not sent.</div>';
            }
        } else {
            $msg = '<div class="alert alert-danger">Email not found!</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Forgot Password</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-5">
  <div class="container">
    <h2>Forgot Password</h2>
    <?php echo $msg; ?>
    <form method="post">
      <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
      <button type="submit" class="btn btn-primary mt-2">Send Reset Link</button>
    </form>
  </div>
</body>
</html>
