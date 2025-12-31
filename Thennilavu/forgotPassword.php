<?php
session_start();

// Try to load PHPMailer
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    die('PHPMailer not found. Please run: composer install');
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$conn = new mysqli('localhost', 'thennilavu_matrimonial', 'OYVuiEKfS@FQ', 'thennilavu_thennilavu');
if ($conn->connect_error) {
    die('DB error: ' . $conn->connect_error);
}

$msg = '';
$err = '';
$step = $_POST['step'] ?? 'email';
$email = $_SESSION['reset_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 'email') {
        $email = trim($_POST['email'] ?? '');
        
        if ($email) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();
                
                if ($stmt->num_rows > 0) {
                    // Generate OTP
                    $otp = sprintf("%06d", mt_rand(100000, 999999));
                    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                    
                    // Store OTP in database
                    $update = $conn->prepare("UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE email = ?");
                    if ($update) {
                        $update->bind_param("sss", $otp, $expires, $email);
                        if ($update->execute()) {
                            // Send OTP via email
                            $mail = new PHPMailer(true);
                            try {
                                $mail->isSMTP();
                                $mail->Host = 'mail.thennilavu.lk';
                                $mail->SMTPAuth = true;
                                $mail->Username = 'noreply@thennilavu.lk';
                                $mail->Password = 'ThennilavuMatrimony';
                                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                $mail->Port = 587;
                                
                                $mail->setFrom('noreply@thennilavu.lk', 'Thennilavu Matrimony');
                                $mail->addAddress($email);
                                
                                $mail->Subject = 'Password Reset OTP';
                                $mail->Body = "Your password reset OTP is: " . $otp . "\n\nThis OTP will expire in 10 minutes.";
                                
                                $mail->send();
                                $_SESSION['reset_email'] = $email;
                                $step = 'otp';
                                $msg = "OTP has been sent to your email.";
                            } catch (Exception $e) {
                                $err = "Failed to send OTP. Please try again.";
                            }
                        } else {
                            $err = "Failed to generate OTP.";
                        }
                        $update->close();
                    } else {
                        $err = "Database error.";
                    }
                } else {
                    $err = "Email not found.";
                }
                $stmt->close();
            } else {
                $err = "Database error.";
            }
        } else {
            $err = "Email is required.";
        }
    } elseif ($step === 'verify_otp') {
        $otp = trim($_POST['otp'] ?? '');
        $email = $_SESSION['reset_email'] ?? '';
        
        if ($otp && $email) {
            // First check if record exists and get the stored OTP
            $stmt = $conn->prepare("SELECT password_reset_token, password_reset_expires FROM users WHERE email = ?");
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->bind_result($stored_otp, $expires);
                
                if ($stmt->fetch()) {
                    $stmt->close();
                    
                    // Check if OTP matches and hasn't expired
                    if ($stored_otp === $otp && strtotime($expires) > time()) {
                        $step = 'reset';
                        $msg = "OTP verified. Enter your new password.";
                    } else {
                        if ($stored_otp !== $otp) {
                            $err = "Invalid OTP.";
                        } else {
                            $err = "OTP has expired.";
                        }
                        $step = 'otp';
                    }
                } else {
                    $stmt->close();
                    $err = "Email not found.";
                    $step = 'otp';
                }
            } else {
                $err = "Database error.";
            }
        } else {
            $err = "OTP is required.";
            $step = 'otp';
        }
    } elseif ($step === 'reset_password') {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $email = $_SESSION['reset_email'] ?? '';
        
        if ($password && $confirm_password && $email) {
            if ($password === $confirm_password) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE users SET password = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE email = ?");
                if ($update) {
                    $update->bind_param("ss", $hash, $email);
                    if ($update->execute()) {
                        unset($_SESSION['reset_email']);
                        $msg = "Password reset successfully. You can now login.";
                        $step = 'complete';
                    } else {
                        $err = "Failed to reset password.";
                    }
                    $update->close();
                } else {
                    $err = "Database error.";
                }
            } else {
                $err = "Passwords do not match.";
                $step = 'reset';
            }
        } else {
            $err = "All fields are required.";
            $step = 'reset';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    :root {
        --primary-color: #7f0808;
        --secondary-color: #2c3e50;
        --accent-color: #007bff;
        --light-color: #f8f9fa;
        --dark-color: #343a40;
    }
    

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), 
                        url('img/1.jpg') center/cover no-repeat fixed;
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
            animation: zoomBg 10s ease-in-out infinite alternate;
        }

        @keyframes zoomBg {
            from {
                background-size: 100% auto;
            }
            to {
                background-size: 120% auto;
            }
        }

        /* Moving Background Logos */
        .moving-logo {
            position: fixed;
            opacity: 0.4;
            pointer-events: none;
            z-index: 1;
        }

        .moving-logo img {
            width: 200px;
            height: auto;
            filter: brightness(2) drop-shadow(0 0 30px rgba(255, 255, 255, 0.5));
        }

        .logo-1 {
            top: 10%;
            left: 5%;
            animation: float1 25s ease-in-out infinite;
        }

        .logo-2 {
            top: 20%;
            right: 8%;
            animation: float2 30s ease-in-out infinite;
        }

        .logo-3 {
            bottom: 15%;
            left: 10%;
            animation: float3 28s ease-in-out infinite;
        }

        .logo-4 {
            bottom: 25%;
            right: 12%;
            animation: float4 32s ease-in-out infinite;
        }

        .logo-5 {
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation: float5 27s ease-in-out infinite;
        }

        @keyframes float1 {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(30px, -30px) rotate(5deg); }
            50% { transform: translate(-20px, -50px) rotate(-3deg); }
            75% { transform: translate(40px, -20px) rotate(4deg); }
        }

        @keyframes float2 {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(-35px, 25px) rotate(-4deg); }
            50% { transform: translate(25px, 45px) rotate(3deg); }
            75% { transform: translate(-30px, 15px) rotate(-5deg); }
        }

        @keyframes float3 {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(25px, 30px) rotate(3deg); }
            50% { transform: translate(-30px, 50px) rotate(-4deg); }
            75% { transform: translate(35px, 25px) rotate(5deg); }
        }

        @keyframes float4 {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(-25px, -35px) rotate(-3deg); }
            50% { transform: translate(30px, -45px) rotate(4deg); }
            75% { transform: translate(-35px, -25px) rotate(-5deg); }
        }

        @keyframes float5 {
            0%, 100% { transform: translate(-50%, -50%) rotate(0deg); }
            25% { transform: translate(calc(-50% + 20px), calc(-50% - 25px)) rotate(3deg); }
            50% { transform: translate(calc(-50% - 25px), calc(-50% + 30px)) rotate(-3deg); }
            75% { transform: translate(calc(-50% + 30px), calc(-50% - 20px)) rotate(4deg); }
        }


    .navbar {
        background-color: rgba(0, 0, 0, 0.1);
        padding: 18px 0;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
    }

    .navbar-brand img {
        transition: transform 0.3s ease;
    }

    .navbar-brand img:hover {
        transform: scale(1.1);
    }

    .navbar .btn {
        border-radius: 20px;
        padding: 8px 20px;
        transition: all 0.3s ease;
        font-weight: 500;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .navbar .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        background: rgba(255, 255, 255, 0.15);
    }

    .navbar .btn a {
        text-decoration: none;
        color: white;
        font-weight: 500;
    }

    .form-container {
        background-size: cover;
        background-position: center;
        /* Account for the fixed nav spacer (80px) so the form truly centers in the visible area */
        min-height: calc(100vh - 80px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 30px 15px;
        position: relative;
        z-index: 10;
    }

    .auth-form {
        background: rgba(0, 0, 0, 0.75);
        border-radius: 20px;
        padding: 40px;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease, border 0.3s ease;
        width: 100%;
        max-width: 450px;
        position: relative;
        overflow: hidden;
    }

    .auth-form::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%);
        border-radius: 20px;
        z-index: 1;
    }

    .auth-form > * {
        position: relative;
        z-index: 2;
    }

    .auth-form:hover {
        transform: translateY(-8px);
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5), 
                    0 0 40px rgba(253, 44, 121, 0.9), 
                    0 0 80px rgba(253, 44, 121, 0.6),
                    0 0 120px rgba(253, 44, 121, 0.3);
        border: 2px solid rgba(253, 44, 121, 1);
    }

    .auth-header {
        text-align: center;
        margin-bottom: 30px;
    }

    .auth-header h2 {
        font-size: 2.2rem;
        font-weight: 700;
        color: #ffffff;
        margin-bottom: 10px;
        position: relative;
        padding-bottom: 15px;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .auth-header h2::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 80px;
        height: 3px;
        background: linear-gradient(90deg, #fd2c79, #ed0cbd);
        border-radius: 2px;
    }

    .auth-header p {
        font-size: 1rem;
        color: rgba(255, 255, 255, 0.8);
        line-height: 1.5;
        margin: 15px 0 0;
    }

    .form-label {
        color: rgba(255, 255, 255, 0.9);
        font-weight: 600;
        margin-bottom: 8px;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        font-size: 0.95rem;
    }

    .form-control {
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        background: rgba(255, 255, 255, 0.1);
        color: #ffffff;
        padding: 14px 18px;
        transition: all 0.3s ease;
        font-size: 1rem;
        backdrop-filter: blur(10px);
    }

    .form-control::placeholder {
        color: rgba(255, 255, 255, 0.5);
    }

    .form-control:focus {
        border-color: #fd2c79;
        background: rgba(255, 255, 255, 0.15);
        box-shadow: 0 0 0 0.2rem rgba(253, 44, 121, 0.25), 0 0 20px rgba(253, 44, 121, 0.1);
        outline: none;
        color: #ffffff;
    }

    .password-toggle {
        position: absolute;
        right: 18px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: rgba(255, 255, 255, 0.6);
        transition: color 0.3s ease;
        z-index: 5;
    }

    .password-toggle:hover {
        color: #fd2c79;
    }

    .btn-primary {
        background: linear-gradient(135deg, #fd2c79, #ed0cbd);
        border: none;
        border-radius: 12px;
        padding: 14px;
        font-weight: 600;
        color: white;
        transition: all 0.3s ease;
        box-shadow: 0 6px 20px rgba(253, 44, 121, 0.3);
        width: 100%;
        position: relative;
        overflow: hidden;
        font-size: 1.05rem;
        letter-spacing: 0.5px;
    }

    .btn-primary::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.5s;
    }

    .btn-primary:hover::before {
        left: 100%;
    }

    .btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(253, 44, 121, 0.4);
        background: linear-gradient(135deg, #ed0cbd, #fd2c79);
        color: white;
    }

    .alert {
        border-radius: 12px;
        padding: 15px 20px;
        font-size: 0.9rem;
        backdrop-filter: blur(10px);
        margin-bottom: 25px;
    }

    .alert-success {
        background: rgba(40, 167, 69, 0.1);
        color: #5cb85c;
        border: 1px solid rgba(40, 167, 69, 0.3);
    }

    .alert-danger {
        background: rgba(220, 53, 69, 0.1);
        color: #ff6b6b;
        border: 1px solid rgba(220, 53, 69, 0.3);
    }

    .form-acc a {
        color: #fd2c79;
        font-weight: 500;
        text-decoration: none;
        transition: color 0.3s ease;
        position: relative;
        text-shadow: 0 0 10px rgba(253, 44, 121, 0.3);
    }

    .form-acc a::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 0;
        height: 2px;
        background: linear-gradient(90deg, #fd2c79, #ed0cbd);
        transition: width 0.3s ease;
    }

    .form-acc a:hover {
        color: #ed0cbd;
        text-shadow: 0 0 15px rgba(237, 12, 189, 0.5);
    }

    .form-acc a:hover::after {
        width: 100%;
    }

    .step-indicator {
        display: flex;
        justify-content: center;
        margin-bottom: 25px;
        position: relative;
    }

    .step {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        background: #e0e0e0;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 15px;
        font-weight: 600;
        color: #777;
        position: relative;
        z-index: 2;
        transition: all 0.3s ease;
    }

    .step.active {
        background: var(--primary-color);
        color: white;
        transform: scale(1.1);
    }

    .step.completed {
        background: #28a745;
        color: white;
    }

    .step-line {
        position: absolute;
        top: 50%;
        left: 0;
        right: 0;
        height: 3px;
        background: #e0e0e0;
        z-index: 1;
    }

    .step-line-progress {
        position: absolute;
        top: 0;
        left: 0;
        height: 100%;
        background: #28a745;
        transition: width 0.5s ease;
    }

    .success-icon {
        font-size: 60px;
        color: #28a745;
        margin-bottom: 20px;
    }

    .text-center h4 {
        color: #ffffff;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .text-center p {
        color: rgba(255, 255, 255, 0.9);
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
    }

    /* Animation for form elements */
    .form-group {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
        transform: translateY(20px);
    }

    @keyframes fadeInUp {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Stagger animation for form elements */
    .form-group:nth-child(1) { animation-delay: 0.1s; }
    .form-group:nth-child(2) { animation-delay: 0.2s; }
    .form-group:nth-child(3) { animation-delay: 0.3s; }
    .form-group:nth-child(4) { animation-delay: 0.4s; }

    @media (max-width: 768px) {
        .auth-form {
            padding: 25px;
        }
        
        .auth-header h2 {
            font-size: 1.7rem;
        }
        
        .navbar .btn {
            padding: 6px 15px;
            font-size: 0.9rem;
        }
        
        .step {
            width: 30px;
            height: 30px;
            margin: 0 10px;
        }
    }

    @media (max-width: 576px) {
        .auth-form {
            padding: 20px;
        }
        
        .auth-header h2 {
            font-size: 1.5rem;
        }
        
        .auth-header p {
            font-size: 0.9rem;
        }
        
        .form-container {
            padding: 10px;
        }
        
        .step {
            width: 25px;
            height: 25px;
            margin: 0 8px;
            font-size: 0.8rem;
        }
    }

     @media (max-width: 768px) {
            body {
                background-attachment: scroll;
                background-position: center top;
                background-size: cover !important;
                animation: none !important;
            }
        }
    </style>
</head>
<body>
    <!-- Moving Background Logos -->
    <div class="moving-logo logo-1">
        <img src="img/LogoBG1.png" alt="Logo">
    </div>
    <div class="moving-logo logo-2">
        <img src="img/LogoBG1.png" alt="Logo">
    </div>
    <div class="moving-logo logo-3">
        <img src="img/LogoBG1.png" alt="Logo">
    </div>
    <div class="moving-logo logo-4">
        <img src="img/LogoBG1.png" alt="Logo">
    </div>
    <div class="moving-logo logo-5">
        <img src="img/LogoBG1.png" alt="Logo">
    </div>

<div class="container-fluid p-0">

    <!-- Spacing for fixed nav -->
    <div style="height: 80px;"></div>

    <div class="row">
        <div class="col-12">
            <div class="row form-container">
                <div class="col-10 col-sm-8 col-md-6 col-lg-4">
                    <div class="auth-form">
                        <div class="auth-header">
                            <h2>Reset Your Password</h2>
                            <p>
                                <?php 
                                    if ($step === 'email') echo 'Enter your email to receive OTP';
                                    elseif ($step === 'otp') echo 'Enter the OTP sent to your email';
                                    elseif ($step === 'reset') echo 'Enter your new password';
                                    else echo 'Password reset completed';
                                ?>
                            </p>
                        </div>
                        
                        <!-- Step Indicator -->
                        <div class="step-indicator">
                            <div class="step-line">
                                <div class="step-line-progress" id="step-progress"></div>
                            </div>
                            <div class="step <?php echo ($step === 'email') ? 'active' : (in_array($step, ['otp', 'reset', 'complete']) ? 'completed' : ''); ?>">1</div>
                            <div class="step <?php echo ($step === 'otp') ? 'active' : (in_array($step, ['reset', 'complete']) ? 'completed' : ''); ?>">2</div>
                            <div class="step <?php echo ($step === 'reset') ? 'active' : ($step === 'complete' ? 'completed' : ''); ?>">3</div>
                        </div>
                        
                        <?php if ($msg): ?>
                            <div class="alert alert-success mb-4"><?php echo $msg; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($err): ?>
                            <div class="alert alert-danger mb-4"><?php echo $err; ?></div>
                        <?php endif; ?>

                        <?php if ($step === 'email'): ?>
                        <form method="POST">
                            <input type="hidden" name="step" value="email">
                            <div class="form-group mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required>
                            </div>
                            <div class="form-group d-grid">
                                <button type="submit" class="btn btn-primary">Send OTP</button>
                            </div>
                        </form>
                        <?php elseif ($step === 'otp'): ?>
                        <form method="POST">
                            <input type="hidden" name="step" value="verify_otp">
                            <div class="form-group mb-3">
                                <label for="otp" class="form-label">Verification Code</label>
                                <input type="text" id="otp" name="otp" class="form-control" placeholder="Enter 6-digit OTP" maxlength="6" required>
                                <div class="form-text mt-2">Check your email for the OTP code</div>
                            </div>
                            <div class="form-group d-grid">
                                <button type="submit" class="btn btn-primary">Verify OTP</button>
                            </div>
                        </form>
                        <?php elseif ($step === 'reset'): ?>
                        <form method="POST">
                            <input type="hidden" name="step" value="reset_password">
                            <div class="form-group mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <div class="position-relative">
                                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter new password" required>
                                    <span class="password-toggle" onclick="togglePassword('password')">
                                        <i id="password-icon" class="fas fa-eye"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group mb-4">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <div class="position-relative">
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirm new password" required>
                                    <span class="password-toggle" onclick="togglePassword('confirm_password')">
                                        <i id="confirm_password-icon" class="fas fa-eye"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group d-grid">
                                <button type="submit" class="btn btn-primary">Reset Password</button>
                            </div>
                        </form>
                        <?php elseif ($step === 'complete'): ?>
                        <div class="text-center py-3">
                            <div class="success-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h4 class="mt-3 mb-4">Password Reset Successful</h4>
                            <p class="mb-4">Your password has been reset successfully. You can now log in with your new password.</p>
                            <div class="form-group d-grid">
                                <a href="login.php" class="btn btn-primary">Go to Login</a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="text-center mt-4">
                            <label class="form-acc" style="color: #e0e0e0;"><b>Remember your password?</b> <a href="login.php"><b>Log in</b></a></label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '-icon');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Update step progress indicator
document.addEventListener('DOMContentLoaded', function() {
    const stepProgress = document.getElementById('step-progress');
    const step = '<?php echo $step; ?>';
    
    if (step === 'email') {
        stepProgress.style.width = '0%';
    } else if (step === 'otp') {
        stepProgress.style.width = '50%';
    } else if (step === 'reset' || step === 'complete') {
        stepProgress.style.width = '100%';
    }
    
    // Add some interactive effects
    const inputs = document.querySelectorAll('.form-control');
    
    inputs.forEach(input => {
        // Add focus effect
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            if (this.value === '') {
                this.parentElement.classList.remove('focused');
            }
        });
    });
});
</script>
</body>
</html>