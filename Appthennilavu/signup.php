<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Database connection
$conn = new mysqli('localhost', 'thennilavu_matrimonial', 'OYVuiEKfS@FQ', 'thennilavu_thennilavu');
if ($conn->connect_error) {
    die('DB error: ' . $conn->connect_error);
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($username && $email && $password && $confirm_password) {
        if ($password !== $confirm_password) {
            $err = "Passwords do not match";
        } else {
            // Check if email exists
            $exists = $conn->prepare('SELECT id FROM users WHERE email=?');
            $exists->bind_param('s', $email);
            $exists->execute();
            $exists->store_result();

            if ($exists->num_rows > 0) {
                $err = 'Email already exists';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $conn->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
                $ins->bind_param('sss', $username, $email, $hash);
                if ($ins->execute()) {
                  $_SESSION['user_id'] = $conn->insert_id;
                   header('Location: login.php?registered=1');
                   exit;
                } else {
                    $err = 'Registration failed (execute error): ' . $ins->error;
                }
            }
        }
    } else {
        $err = 'All fields required';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - ThenNilavu Matrimony</title>
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

    /* Register Card */
    .register-card {
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
        margin-bottom: 18px;
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

    /* Terms Checkbox */
    .terms-group {
        margin: 20px 0;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 10px;
        border: 1px solid #e0e0e0;
    }

    .checkbox-container {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        cursor: pointer;
    }

    .checkbox {
        width: 18px;
        height: 18px;
        border: 2px solid #ddd;
        border-radius: 4px;
        background: white;
        position: relative;
        flex-shrink: 0;
        margin-top: 2px;
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
        font-size: 11px;
        font-weight: bold;
    }

    .checkbox-label {
        font-size: 14px;
        color: #555;
        line-height: 1.4;
    }

    .terms-link {
        color: #e91e63;
        text-decoration: none;
        font-weight: 500;
    }

    .terms-link:hover {
        text-decoration: underline;
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
        margin-bottom: 20px;
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

    /* Login Link */
    .login-section {
        text-align: center;
        padding-top: 20px;
        border-top: 1px solid #eee;
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
        
        .register-card {
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
       
        <!-- Register Card -->
        <div class="register-card">
            <h2 class="card-title">Create Your Account</h2>
            <p class="card-subtitle">Join our community to begin</p>

            <?php if ($err): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($err); ?>
                </div>
            <?php endif; ?>

            <!-- Registration Form - Exact same as original -->
            <form method="POST" action="" id="registerForm">
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <div class="input-container">
                        <input type="text" 
                               id="username" 
                               class="form-input" 
                               placeholder="Sam" 
                               name="username" 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                               required>
                        <div class="input-icon">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <div class="input-container">
                        <input type="email" 
                               id="email" 
                               class="form-input" 
                               placeholder="sample@gmail.com" 
                               name="email" 
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
                               id="password" 
                               class="form-input" 
                               placeholder="**********" 
                               name="password" 
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
                               class="form-input" 
                               placeholder="**********" 
                               name="confirm_password" 
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
                
                <!-- Terms Agreement - Same as original -->
                <div class="terms-group">
                    <div class="checkbox-container" onclick="toggleTerms()">
                        <div class="checkbox" id="termsCheckbox"></div>
                        <span class="checkbox-label">
                            I agree to the <a href="terms.php" class="terms-link" target="_blank">Terms of Service</a> 
                            and <a href="privacy.php" class="terms-link" target="_blank">Privacy Policy</a>
                        </span>
                        <input type="checkbox" 
                               id="terms" 
                               name="terms" 
                               style="display: none;"
                               required>
                    </div>
                </div>
                
                <button type="submit" class="submit-btn" id="submitBtn">
                    <i class="fas fa-user-plus"></i>
                    <span>Register</span>
                </button>
            </form>

            <div class="login-section">
                <p class="login-text">Already have an account?</p>
                <a href="login.php" class="login-link">Log in</a>
            </div>
        </div>
    </div>

    <script>
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
        
        // Toggle Terms Checkbox
        function toggleTerms() {
            const checkbox = document.getElementById('termsCheckbox');
            const hiddenCheckbox = document.getElementById('terms');
            
            checkbox.classList.toggle('checked');
            hiddenCheckbox.checked = checkbox.classList.contains('checked');
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
        function validateForm() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const termsChecked = document.getElementById('terms').checked;
            
            if (password !== confirmPassword) {
                alert('Passwords do not match');
                return false;
            }
            
            if (!termsChecked) {
                alert('Please agree to the terms and conditions');
                return false;
            }
            
            return true;
        }
        
        // Add form validation on submit
        document.getElementById('registerForm').onsubmit = function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Registering...</span>';
            submitBtn.disabled = true;
            
            return true;
        };
        
        // Add focus effects for better UX
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.form-input');
            
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
                    document.getElementById('registerForm').submit();
                }
            }
        });
    </script>
</body>
</html>