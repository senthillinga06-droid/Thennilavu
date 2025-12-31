<?php
session_start();
$conn = new mysqli('localhost', 'thennilavu_matrimonial', 'OYVuiEKfS@FQ', 'thennilavu_thennilavu');
if ($conn->connect_error) {
    die('DB error: ' . $conn->connect_error);
}

$err = '';
$blocked = false; // will be true if user exists but role is 'block'
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
                    // If the account is blocked, do not create session — show modal informing user to contact admin
                    if ($role === 'block') {
                        $blocked = true;
                    } else {
                        // Normal login flow
                        $_SESSION['user_id'] = $uid;
                        $_SESSION['username'] = $username;
                        $_SESSION['role'] = $role;

                        if ($role === 'admin') {
                            header('Location: ../admin/dashboard.html');
                            exit;
                        }

                        // Check if profile exists
                        $profile = $conn->prepare('SELECT id FROM members WHERE user_id=?');
                        if ($profile) {
                            $profile->bind_param('i', $uid);
                            $profile->execute();
                            $profile->store_result();
                            if ($profile->num_rows == 0) {
                                // No profile, go to members.php
                                header('Location: members.php');
                                exit;
                            } else {
                                // Profile exists, check package
                                $pkg = $conn->prepare('SELECT package FROM members WHERE user_id=?');
                                if ($pkg) {
                                    $pkg->bind_param('i', $uid);
                                    $pkg->execute();
                                    $pkg->bind_result($package);
                                    $pkg->fetch();
                                    if (!$package || $package === '' || is_null($package)) {
                                        header('Location: package.php');
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
    <title>Login - Thennilavu Matrimony</title>
    <link rel="stylesheet" href="bootstrap.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #fd2c79;
            --secondary-color: #2c3e50;
            --accent-color: #ed0cbd;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --success-color: #28a745;
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

        @media (max-width: 768px) {
            body {
                background-attachment: scroll;
                background-position: center top;
                background-size: cover !important;
                animation: none !important;
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
            transform: scale(1.05);
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
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
            position: relative;
            z-index: 10;
        }

        .login-card {
            background: rgba(0, 0, 0, 0.75);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
            width: 100%;
            max-width: 450px;
        }

        .login-card::before {
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

        .login-card > * {
            position: relative;
            z-index: 2;
        }

        .login-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5), 
                        0 0 40px rgba(253, 44, 121, 0.9), 
                        0 0 80px rgba(253, 44, 121, 0.6),
                        0 0 120px rgba(253, 44, 121, 0.3);
            border: 2px solid rgba(253, 44, 121, 1);
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h2 {
            font-size: 2.2rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 10px;
            position: relative;
            padding-bottom: 15px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .login-header h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
            border-radius: 2px;
        }

        .login-header p {
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
            border-color: var(--primary-color);
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
            color: var(--primary-color);
        }

        .btn-login {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(253, 44, 121, 0.3);
            position: relative;
            overflow: hidden;
            font-size: 1.05rem;
            letter-spacing: 0.5px;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(253, 44, 121, 0.4);
            background: linear-gradient(135deg, var(--accent-color), var(--primary-color));
            color: white;
        }

        .alert-danger {
            border-radius: 12px;
            padding: 15px 20px;
            font-size: 0.9rem;
            border: 1px solid rgba(220, 53, 69, 0.3);
            background: rgba(220, 53, 69, 0.1);
            color: #ff6b6b;
            backdrop-filter: blur(10px);
            margin-bottom: 25px;
        }

        .form-check-label, .form-acc {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
        }

        .form-acc a {
            color: var(--primary-color);
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
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
            transition: width 0.3s ease;
        }

        .form-acc a:hover {
            color: var(--accent-color);
            text-shadow: 0 0 15px rgba(237, 12, 189, 0.5);
        }

        .form-acc a:hover::after {
            width: 100%;
        }

        .text-end a {
            color: var(--primary-color);
            font-size: 0.9rem;
            text-decoration: none;
            transition: color 0.3s ease;
            position: relative;
            text-shadow: 0 0 10px rgba(253, 44, 121, 0.3);
        }

        .text-end a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 1px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
            transition: width 0.3s ease;
        }

        .text-end a:hover {
            color: var(--accent-color);
            text-shadow: 0 0 15px rgba(237, 12, 189, 0.5);
        }

        .text-end a:hover::after {
            width: 100%;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            box-shadow: 0 0 10px rgba(253, 44, 121, 0.5);
        }

        .form-check-input {
            background-color: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .user-display {
            color: var(--light-color);
            font-weight: 500;
            padding: 5px 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            backdrop-filter: blur(5px);
        }

        .form-footer {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        @media (max-width: 768px) {
            .login-card {
                padding: 30px;
            }
            
            .login-header h2 {
                font-size: 1.9rem;
            }
            
            .navbar .btn {
                padding: 6px 15px;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 576px) {
            .login-card {
                padding: 25px 20px;
            }
            
            .login-header h2 {
                font-size: 1.7rem;
            }
            
            .login-header p {
                font-size: 0.9rem;
            }
            
            .form-container {
                padding: 15px;
            }
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

        /* Modal styles for blocked account */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 20px;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 450px;
            width: 100%;
            text-align: center;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
            animation: modalFadeIn 0.3s ease-out;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-title {
            color: #b71c1c;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .modal-text {
            color: #333;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .modal-btn {
            background: #7f0808;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s;
        }

        .modal-btn:hover {
            background: #9a0b0b;
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

        <div class="form-container">
            <div class="login-card">
                <div class="login-header">
                    <h2>Welcome Back</h2>
                    <p>Sign in to continue your journey with Thennilavu</p>
                </div>
                
                <?php if ($err): ?>
                    <div class="alert alert-danger"><?php echo $err; ?></div>
                <?php endif; ?>
                
                <form method="post">
                    <div class="form-group mb-4">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="position-relative">
                            <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                            <span class="password-toggle" onclick="togglePassword('password')">
                                <i id="password-icon" class="fas fa-eye"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div class="form-group row mb-4 align-items-center">
                        <div class="col-6 text-start">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="rememberMe" name="rememberMe">
                                <label class="form-check-label" for="rememberMe">Remember Me</label>
                            </div>
                        </div>
                        <div class="col-6 text-end">
                            <a href="forgotPassword.php" class="form-acc">Forgot Password?</a>
                        </div>
                    </div>
                    
                    <div class="form-group d-grid mb-4">
                        <button type="submit" class="btn btn-login">Sign In</button>
                    </div>
                </form>
                
                <div class="form-footer text-center">
                    <label class="form-acc"><b>Don't have an account?</b> <a href="signup.php"><b>Create Account</b></a></label>
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
        
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
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
                
                // Add validation styling
                input.addEventListener('input', function() {
                    if (this.value) {
                        this.classList.add('has-value');
                    } else {
                        this.classList.remove('has-value');
                    }
                });
            });
        });
    </script>
    
    <?php if ($blocked): ?>
    <div class="modal-overlay">
        <div class="modal-content">
            <h3 class="modal-title">Account Blocked</h3>
            <p class="modal-text">Your account has been temporarily suspended. Please contact the administrator for assistance.</p>
            <button class="modal-btn" onclick="this.closest('.modal-overlay').style.display='none'">OK</button>
        </div>
    </div>
    <?php endif; ?>
</body>

</html>