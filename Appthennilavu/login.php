<?php
session_start();
$conn = new mysqli('localhost', 'thennilavu_matrimonial', 'OYVuiEKfS@FQ', 'thennilavu_thennilavu');
if ($conn->connect_error) {
    die('DB error: ' . $conn->connect_error);
}

$err = '';
$blocked = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
  
            if ($stmt->num_rows > 0) {
                $stmt->bind_result($uid, $username, $hash, $role);
                $stmt->fetch();

                if (password_verify($password, $hash)) {
                    if ($role === 'block') {
                        $blocked = true;
                    } else {
                        $_SESSION['user_id'] = $uid;
                        $_SESSION['username'] = $username;
                        $_SESSION['role'] = $role;

                        if ($role === 'admin') {
                            header('Location: ../admin/dashboard.html');
                            exit;
                        }

                        $profile = $conn->prepare('SELECT id FROM members WHERE user_id=?');
                        if ($profile) {
                            $profile->bind_param('i', $uid);
                            $profile->execute();
                            $profile->store_result();
                            if ($profile->num_rows == 0) {
                                header('Location: members.php');
                                exit;
                            } else {
                                $pkg = $conn->prepare('SELECT package FROM members WHERE user_id=?');
                                if ($pkg) {
                                    $pkg->bind_param('i', $uid);
                                    $pkg->execute();
                                    $pkg->bind_result($package);
                                    $pkg->fetch();
                                    if (!$package || $package === '' || is_null($package)) {
                                        header('Location: index.php');
                                        exit;
                                    } else {
                                        header('Location: index.php');
                                        exit;
                                    }
                                } else {
                                    $err = "Database error: " . $conn->error;
                                }
                            }
                        } else {
                            $err = "Database error: " . $conn->error;
                        }
                    }
                } else {
                    $err = "Invalid password";
                }
            } else {
                $err = "User not found";
            }
            $stmt->close();
        } else {
            $err = "Database error: " . $conn->error;
        }
    } else {
        $err = "All fields are required";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ThenNilavu Matrimony</title>
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
            margin-bottom: 40px;
        }

        .logo {
            width: 120px;
            height: auto;
            margin-bottom: 15px;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));
        }

        .app-name {
            color: white;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .app-tagline {
            color: rgba(255,255,255,0.9);
            font-size: 14px;
            font-weight: 400;
        }

        /* Login Card */
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 30px 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .card-title {
            font-size: 22px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }

        .card-subtitle {
            color: #666;
            font-size: 14px;
            text-align: center;
            margin-bottom: 30px;
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
            margin-bottom: 8px;
        }

        .input-container {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 15px 45px 15px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
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

        /* Remember & Forgot */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox {
            width: 18px;
            height: 18px;
            border: 2px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            position: relative;
        }

        .checkbox.checked {
            background: #e91e63;
            border-color: #e91e63;
        }

        .checkbox.checked::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 12px;
            font-weight: bold;
        }

        .checkbox-label {
            font-size: 14px;
            color: #555;
            cursor: pointer;
        }

        .forgot-link {
            color: #e91e63;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        /* Submit Button */
        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #e91e63, #7b1fa2);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 25px;
            transition: transform 0.3s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
        }

        /* Register Link */
        .register-section {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .register-text {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .register-link {
            color: #e91e63;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
        }

        .register-link:hover {
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

        /* Blocked Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 1000;
        }

        .modal {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 350px;
            width: 100%;
            text-align: center;
        }

        .modal-icon {
            font-size: 48px;
            color: #dc3545;
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
        }

        .modal-text {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 25px;
        }

        .modal-btn {
            padding: 12px 30px;
            background: #e91e63;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            width: 100%;
        }

        /* Responsive */
        @media (max-width: 480px) {
            body {
                padding: 15px;
            }
            
            .login-card {
                padding: 25px 20px;
            }
            
            .logo {
                width: 100px;
            }
            
            .app-name {
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
     

        <!-- Login Card -->
        <div class="login-card">
            <h2 class="card-title">Welcome Back</h2>
            <p class="card-subtitle">Sign in to continue your journey</p>

            <?php if ($err): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($err); ?>
                </div>
            <?php endif; ?>

            <!-- Login Form - Exact same as original -->
            <form method="post" id="loginForm" action="">
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <div class="input-container">
                        <input type="email" 
                               class="form-input" 
                               id="email" 
                               name="email" 
                               placeholder="Enter your email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                               required>
                        <div class="input-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-container">
                        <input type="password" 
                               class="form-input" 
                               id="password" 
                               name="password" 
                               placeholder="Enter your password" 
                               required>
                        <button type="button" 
                                class="password-toggle" 
                                onclick="togglePassword()">
                            <i id="passwordIcon" class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <div class="remember-me">
                        <div class="checkbox" id="rememberCheckbox" onclick="toggleRemember()"></div>
                        <label class="checkbox-label" onclick="toggleRemember()">Remember me</label>
                        <input type="checkbox" 
                               class="form-check-input" 
                               id="rememberMe" 
                               name="rememberMe"
                               style="display: none;">
                    </div>
                    <a href="forgotPassword.php" class="forgot-link">Forgot Password?</a>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Sign In</span>
                </button>
            </form>

            <div class="register-section">
                <p class="register-text">Don't have an account?</p>
                <a href="signup.php" class="register-link">Create Account</a>
            </div>
        </div>
    </div>

    <!-- Blocked Account Modal - Same as original -->
    <?php if ($blocked): ?>
    <div class="modal-overlay">
        <div class="modal">
            <div class="modal-icon">
                <i class="fas fa-ban"></i>
            </div>
            <h3 class="modal-title">Account Blocked</h3>
            <p class="modal-text">Your account has been temporarily suspended. Please contact the administrator for assistance.</p>
            <button class="modal-btn" onclick="this.closest('.modal-overlay').style.display='none'">
                OK
            </button>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Toggle Password Visibility - Same as original
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const passwordIcon = document.getElementById('passwordIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                passwordIcon.classList.remove('fa-eye');
                passwordIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                passwordIcon.classList.remove('fa-eye-slash');
                passwordIcon.classList.add('fa-eye');
            }
        }
        
        // Toggle Remember Me - Mobile friendly
        function toggleRemember() {
            const checkbox = document.getElementById('rememberCheckbox');
            const hiddenCheckbox = document.getElementById('rememberMe');
            
            checkbox.classList.toggle('checked');
            hiddenCheckbox.checked = checkbox.classList.contains('checked');
        }
        
        // Add focus effects for better UX
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.form-input');
            
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.borderColor = '#e91e63';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.borderColor = '#e0e0e0';
                });
            });
            
            // Auto-hide error message after 5 seconds
            const errorMessage = document.querySelector('.error-message');
            if (errorMessage) {
                setTimeout(() => {
                    errorMessage.style.opacity = '0';
                    errorMessage.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => {
                        errorMessage.style.display = 'none';
                    }, 500);
                }, 5000);
            }
        });
        
        // Handle Enter key to submit form
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const activeElement = document.activeElement;
                if (activeElement.tagName === 'INPUT' && !activeElement.type === 'submit') {
                    document.getElementById('loginForm').submit();
                }
            }
        });
    </script>
</body>
</html>