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
    <title>Forgot Password - TheanNilavu Matrimony</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    /* Mobile App Style */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: linear-gradient(135deg, #e91e63 0%, #7b1fa2 100%);
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 20px;
    }

    .app-container {
        width: 100%;
        max-width: 400px;
    }

    /* Logo */
    .logo-container {
        text-align: center;
        margin-bottom: 30px;
    }

    .logo {
        width: 100px;
        height: auto;
        margin-bottom: 15px;
        filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));
    }

    .app-name {
        color: white;
        font-size: 22px;
        font-weight: 700;
        margin-bottom: 5px;
        text-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    .app-tagline {
        color: rgba(255,255,255,0.9);
        font-size: 14px;
        font-weight: 400;
    }

    /* Step Indicator */
    .step-indicator {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        position: relative;
        padding: 0 20px;
    }

    .step-indicator::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50px;
        right: 50px;
        height: 3px;
        background: #ddd;
        z-index: 1;
        transform: translateY(-50%);
    }

    .step-progress {
        position: absolute;
        top: 50%;
        left: 50px;
        height: 3px;
        background: #e91e63;
        z-index: 2;
        transform: translateY(-50%);
        transition: width 0.5s ease;
    }

    .step {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        background: white;
        border: 3px solid #ddd;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        color: #777;
        position: relative;
        z-index: 3;
        transition: all 0.3s ease;
    }

    .step.active {
        background: #e91e63;
        border-color: #e91e63;
        color: white;
        transform: scale(1.1);
    }

    .step.completed {
        background: #28a745;
        border-color: #28a745;
        color: white;
    }

    /* Auth Card */
    .auth-card {
        background: white;
        border-radius: 20px;
        padding: 25px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    }

    .card-title {
        font-size: 22px;
        font-weight: 700;
        color: #333;
        margin-bottom: 8px;
        text-align: center;
    }

    .card-subtitle {
        color: #666;
        font-size: 14px;
        text-align: center;
        margin-bottom: 25px;
    }

    /* Form */
    .form-group {
        margin-bottom: 20px;
    }

    .form-label {
        display: block;
        color: #555;
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 6px;
    }

    .input-container {
        position: relative;
    }

    .form-input {
        width: 100%;
        padding: 14px 45px 14px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        font-size: 16px;
        color: #333;
        background: #f8f9fa;
        transition: all 0.3s ease;
    }

    .form-input:focus {
        outline: none;
        border-color: #e91e63;
        background: white;
        box-shadow: 0 0 0 3px rgba(233,30,99,0.1);
    }

    .input-icon {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #999;
        font-size: 18px;
    }

    .password-toggle {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #999;
        font-size: 18px;
        cursor: pointer;
        padding: 5px;
    }

    /* OTP Input */
    .otp-container {
        display: flex;
        gap: 10px;
        justify-content: center;
        margin: 20px 0;
    }

    .otp-input {
        width: 45px;
        height: 55px;
        text-align: center;
        font-size: 24px;
        font-weight: 600;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        background: #f8f9fa;
        color: #333;
        transition: all 0.3s ease;
    }

    .otp-input:focus {
        border-color: #e91e63;
        outline: none;
        background: white;
        box-shadow: 0 0 0 3px rgba(233,30,99,0.1);
    }

    /* Timer */
    .timer-container {
        text-align: center;
        margin: 15px 0;
        color: #666;
        font-size: 14px;
    }

    .timer {
        font-weight: 600;
        color: #e91e63;
    }

    /* Resend Link */
    .resend-container {
        text-align: center;
        margin: 20px 0;
    }

    .resend-link {
        color: #e91e63;
        font-size: 14px;
        font-weight: 500;
        text-decoration: none;
        cursor: pointer;
    }

    .resend-link:hover {
        text-decoration: underline;
    }

    .resend-link.disabled {
        color: #999;
        cursor: not-allowed;
    }

    /* Submit Button */
    .submit-btn {
        width: 100%;
        padding: 16px;
        background: linear-gradient(135deg, #e91e63, #7b1fa2);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        margin: 20px 0;
        transition: transform 0.3s ease;
    }

    .submit-btn:hover {
        transform: translateY(-2px);
    }

    .submit-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    /* Success Screen */
    .success-screen {
        text-align: center;
        padding: 20px 0;
    }

    .success-icon {
        font-size: 60px;
        color: #28a745;
        margin-bottom: 20px;
    }

    .success-title {
        font-size: 22px;
        font-weight: 700;
        color: #333;
        margin-bottom: 10px;
    }

    .success-message {
        color: #666;
        font-size: 14px;
        line-height: 1.5;
        margin-bottom: 25px;
    }

    /* Login Link */
    .login-section {
        text-align: center;
        padding-top: 20px;
        border-top: 1px solid #eee;
        margin-top: 20px;
    }

    .login-text {
        color: #666;
        font-size: 14px;
        margin-bottom: 8px;
    }

    .login-link {
        color: #e91e63;
        font-size: 15px;
        font-weight: 600;
        text-decoration: none;
    }

    .login-link:hover {
        text-decoration: underline;
    }

    /* Error Message */
    .error-message {
        background: rgba(220,53,69,0.1);
        color: #dc3545;
        padding: 12px 15px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-size: 14px;
        border: 1px solid rgba(220,53,69,0.2);
        text-align: center;
    }

    /* Success Message */
    .success-message-box {
        background: rgba(40,167,69,0.1);
        color: #28a745;
        padding: 12px 15px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-size: 14px;
        border: 1px solid rgba(40,167,69,0.2);
        text-align: center;
    }

    /* Password Match Feedback */
    .match-feedback {
        font-size: 12px;
        margin-top: 4px;
        padding: 0 5px;
    }

    .match-feedback.valid {
        color: #28a745;
    }

    .match-feedback.invalid {
        color: #dc3545;
    }

    /* Responsive */
    @media (max-width: 480px) {
        body {
            padding: 15px;
        }
        
        .auth-card {
            padding: 20px;
        }
        
        .logo {
            width: 90px;
        }
        
        .app-name {
            font-size: 20px;
        }
        
        .card-title {
            font-size: 20px;
        }
        
        .step {
            width: 30px;
            height: 30px;
            font-size: 14px;
        }
        
        .otp-input {
            width: 40px;
            height: 50px;
            font-size: 20px;
        }
    }

    /* Animations */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .fade-in {
        animation: fadeIn 0.5s ease forwards;
    }
    </style>
</head>
<body>
    <div class="app-container fade-in">
       

        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step-progress" id="stepProgress"></div>
            <div class="step <?php echo ($step === 'email') ? 'active' : (in_array($step, ['otp', 'reset', 'complete']) ? 'completed' : ''); ?>">1</div>
            <div class="step <?php echo ($step === 'otp') ? 'active' : (in_array($step, ['reset', 'complete']) ? 'completed' : ''); ?>">2</div>
            <div class="step <?php echo ($step === 'reset') ? 'active' : ($step === 'complete' ? 'completed' : ''); ?>">3</div>
        </div>

        <!-- Auth Card -->
        <div class="auth-card">
            <?php if ($err): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($err); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($msg): ?>
                <div class="success-message-box">
                    <?php echo htmlspecialchars($msg); ?>
                </div>
            <?php endif; ?>

            <!-- Step 1: Email -->
            <?php if ($step === 'email'): ?>
            <div class="card-header">
                <h2 class="card-title">Reset Your Password</h2>
                <p class="card-subtitle">Enter your email to receive OTP</p>
            </div>

            <form method="POST" id="emailForm">
                <input type="hidden" name="step" value="email">
                
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <div class="input-container">
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-input" 
                               placeholder="Enter your email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                               required>
                        <div class="input-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                    </div>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-paper-plane"></i>
                    <span>Send OTP</span>
                </button>
            </form>

            <!-- Step 2: OTP Verification -->
            <?php elseif ($step === 'otp'): ?>
            <div class="card-header">
                <h2 class="card-title">Verify OTP</h2>
                <p class="card-subtitle">Enter the 6-digit code sent to your email</p>
            </div>

            <form method="POST" id="otpForm">
                <input type="hidden" name="step" value="verify_otp">
                
                <div class="form-group">
                    <div class="otp-container">
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                        <input type="text" 
                               class="otp-input" 
                               name="otp[]" 
                               maxlength="1" 
                               data-index="<?php echo $i - 1; ?>"
                               oninput="moveToNext(this)" 
                               onkeydown="handleOtpKeyDown(event, this)"
                               required>
                        <?php endfor; ?>
                        <input type="hidden" id="otpCode" name="otp">
                    </div>

                    <!-- Timer -->
                    <div class="timer-container">
                        <span>OTP expires in: </span>
                        <span class="timer" id="timer">10:00</span>
                    </div>

                    <!-- Resend Link -->
                    <div class="resend-container">
                        <a href="javascript:void(0)" class="resend-link disabled" id="resendLink" onclick="resendOTP()">
                            Resend OTP
                        </a>
                    </div>
                </div>

                <button type="submit" class="submit-btn" id="verifyBtn" disabled>
                    <i class="fas fa-check-circle"></i>
                    <span>Verify OTP</span>
                </button>
            </form>

            <!-- Step 3: Reset Password -->
            <?php elseif ($step === 'reset'): ?>
            <div class="card-header">
                <h2 class="card-title">New Password</h2>
                <p class="card-subtitle">Create a new password for your account</p>
            </div>

            <form method="POST" id="resetForm">
                <input type="hidden" name="step" value="reset_password">
                
                <div class="form-group">
                    <label class="form-label" for="password">New Password</label>
                    <div class="input-container">
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-input" 
                               placeholder="Enter new password" 
                               required>
                        <button type="button" 
                                class="password-toggle" 
                                onclick="togglePassword('password')">
                            <i id="password-icon" class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password</label>
                    <div class="input-container">
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               class="form-input" 
                               placeholder="Confirm new password" 
                               required
                               oninput="checkPasswordMatch()">
                        <button type="button" 
                                class="password-toggle" 
                                onclick="togglePassword('confirm_password')">
                            <i id="confirm_password-icon" class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div id="match-feedback" class="match-feedback">
                        <!-- Will be filled by JavaScript -->
                    </div>
                </div>

                <button type="submit" class="submit-btn" id="resetBtn">
                    <i class="fas fa-key"></i>
                    <span>Reset Password</span>
                </button>
            </form>

            <!-- Step 4: Success -->
            <?php elseif ($step === 'complete'): ?>
            <div class="success-screen">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2 class="success-title">Password Reset Successful!</h2>
                <p class="success-message">Your password has been reset successfully. You can now log in with your new password.</p>
                
                <a href="login.php" class="submit-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Go to Login</span>
                </a>
            </div>
            <?php endif; ?>

            <!-- Login Link -->
            <?php if ($step !== 'complete'): ?>
            <div class="login-section">
                <p class="login-text">Remember your password?</p>
                <a href="login.php" class="login-link">Back to Login</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Update Step Progress
        document.addEventListener('DOMContentLoaded', function() {
            const stepProgress = document.getElementById('stepProgress');
            const step = '<?php echo $step; ?>';
            
            if (step === 'email') {
                stepProgress.style.width = '0%';
            } else if (step === 'otp') {
                stepProgress.style.width = '50%';
            } else if (step === 'reset') {
                stepProgress.style.width = '100%';
            } else if (step === 'complete') {
                stepProgress.style.width = '100%';
            }
            
            // Auto-hide messages after 5 seconds
            const errorMessage = document.querySelector('.error-message');
            const successMessage = document.querySelector('.success-message-box');
            
            if (errorMessage) {
                setTimeout(() => {
                    errorMessage.style.opacity = '0';
                    errorMessage.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => {
                        errorMessage.style.display = 'none';
                    }, 500);
                }, 5000);
            }
            
            if (successMessage) {
                setTimeout(() => {
                    successMessage.style.opacity = '0';
                    successMessage.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => {
                        successMessage.style.display = 'none';
                    }, 500);
                }, 5000);
            }
            
            // OTP Timer
            if ('<?php echo $step; ?>' === 'otp') {
                startTimer();
            }
            
            // Check password match
            if ('<?php echo $step; ?>' === 'reset') {
                checkPasswordMatch();
            }
        });
        
        // Toggle Password Visibility - Same as original
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
        
        // OTP Functions - Same as original concept
        function moveToNext(input) {
            const index = parseInt(input.dataset.index);
            const value = input.value;
            
            // Auto move to next input
            if (value.length === 1 && index < 5) {
                document.querySelector(`[data-index="${index + 1}"]`).focus();
            }
            
            // Update hidden OTP field
            updateOTPCode();
        }
        
        function handleOtpKeyDown(event, input) {
            const index = parseInt(input.dataset.index);
            
            // Handle backspace
            if (event.key === 'Backspace' && input.value === '' && index > 0) {
                document.querySelector(`[data-index="${index - 1}"]`).focus();
            }
        }
        
        function updateOTPCode() {
            const otpInputs = document.querySelectorAll('.otp-input');
            let otpCode = '';
            
            otpInputs.forEach(input => {
                otpCode += input.value;
            });
            
            document.getElementById('otpCode').value = otpCode;
            
            // Enable/disable verify button
            const verifyBtn = document.getElementById('verifyBtn');
            verifyBtn.disabled = otpCode.length !== 6;
        }
        
        // Timer Function - Same as original concept
        function startTimer() {
            let timeLeft = 600; // 10 minutes in seconds
            const timerElement = document.getElementById('timer');
            const resendLink = document.getElementById('resendLink');
            
            const timer = setInterval(() => {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                
                timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                
                if (timeLeft <= 0) {
                    clearInterval(timer);
                    resendLink.classList.remove('disabled');
                    resendLink.textContent = 'Resend OTP';
                } else {
                    timeLeft--;
                }
            }, 1000);
        }
        
        function resendOTP() {
            const resendLink = document.getElementById('resendLink');
            if (!resendLink.classList.contains('disabled')) {
                // Submit form to resend OTP - same as original
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="step" value="email">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Check Password Match - Simple validation like original
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const feedback = document.getElementById('match-feedback');
            
            if (confirmPassword === '') {
                feedback.textContent = '';
                feedback.className = 'match-feedback';
            } else if (password !== confirmPassword) {
                feedback.textContent = 'Passwords do not match';
                feedback.className = 'match-feedback invalid';
            } else {
                feedback.textContent = 'Passwords match';
                feedback.className = 'match-feedback valid';
            }
        }
        
        // Simple form validation - Same as original PHP validation
        function validateResetForm() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                alert('Passwords do not match');
                return false;
            }
            
            return true;
        }
        
        // Add form validation on submit
        document.getElementById('resetForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match');
                return false;
            }
            
            // Show loading state
            const resetBtn = document.getElementById('resetBtn');
            resetBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Resetting...</span>';
            resetBtn.disabled = true;
            
            return true;
        });
        
        // Add focus effects for better UX
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.form-input, .otp-input');
            
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.style.borderColor = '#e91e63';
                    this.style.backgroundColor = 'white';
                });
                
                input.addEventListener('blur', function() {
                    this.style.borderColor = '#e0e0e0';
                    this.style.backgroundColor = '#f8f9fa';
                });
            });
        });
        
        // Handle Enter key to submit form
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const activeElement = document.activeElement;
                if (activeElement.tagName === 'INPUT' && !activeElement.type === 'submit') {
                    const form = document.querySelector('form');
                    if (form) form.submit();
                }
            }
        });
    </script>
</body>
</html>