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
                  $_SESSION['user_id'] = $conn->insert_id; // Add this line
                   header('Location: login.php?registered=1');
                   exit;
                  }
                else {
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
  <title>Register</title>
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
        background-color: var(--primary-color);
        padding: 18px 0;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
        transition: all 0.3s ease;
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
    }

    .navbar .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    .navbar .btn a {
        text-decoration: none;
        color: white;
        font-weight: 500;
    }

    .form-container {
        background-size: cover;
        background-position: center;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px 0;
        position: relative;
        z-index: 10;
    }

    .register-form {
        background: rgba(0, 0, 0, 0.75);
        border-radius: 20px;
        padding: 40px;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease, border 0.3s ease;
        position: relative;
        overflow: hidden;
        width: 100%;
        max-width: 500px;
    }

    .register-form::before {
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

    .register-form > * {
        position: relative;
        z-index: 2;
    }

    .register-form:hover {
        transform: translateY(-8px);
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5), 
                    0 0 40px rgba(253, 44, 121, 0.9), 
                    0 0 80px rgba(253, 44, 121, 0.6),
                    0 0 120px rgba(253, 44, 121, 0.3);
        border: 2px solid rgba(253, 44, 121, 1);
    }

    .register-header {
        text-align: center;
        margin-bottom: 25px;
    }

    .register-header h2 {
        font-size: 2rem;
        font-weight: 700;
        color: #ffffff;
        margin-bottom: 10px;
        position: relative;
        padding-bottom: 15px;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .register-header h2::after {
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

    .register-header p {
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

    .btn-register {
        background: linear-gradient(135deg, #fd2c79, #ed0cbd);
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

    .btn-register::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.5s;
    }

    .btn-register:hover::before {
        left: 100%;
    }

    .btn-register:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(253, 44, 121, 0.4);
        background: linear-gradient(135deg, #ed0cbd, #fd2c79);
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

    .text-end a {
        color: #fd2c79;
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
        background: linear-gradient(90deg, #fd2c79, #ed0cbd);
        transition: width 0.3s ease;
    }

    .text-end a:hover {
        color: #ed0cbd;
        text-shadow: 0 0 15px rgba(237, 12, 189, 0.5);
    }

    .text-end a:hover::after {
        width: 100%;
    }

    .form-check-input:checked {
        background-color: #fd2c79;
        border-color: #fd2c79;
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

    @media (max-width: 768px) {
        .register-form {
            padding: 25px;
        }
        
        .register-header h2 {
            font-size: 1.7rem;
        }
        
        .navbar .btn {
            padding: 6px 15px;
            font-size: 0.9rem;
        }
    }

    @media (max-width: 576px) {
        .register-form {
            padding: 20px;
        }
         
        .register-header h2 {
            font-size: 1.5rem;
        }
        
        .register-header p {
            font-size: 0.9rem;
        }
        
        .form-container {
            padding: 10px;
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
    .form-group:nth-child(5) { animation-delay: 0.5s; }
    .form-group:nth-child(6) { animation-delay: 0.6s; }

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

    <div class="form-container">
        <div class="register-form">
            <div class="register-header">
              <h2>Create Your Account</h2>
              <p>Join our community to begin your journey</p>
            </div>
            
            <?php if ($err): ?>
              <div class="alert alert-danger mb-4"><?php echo $err; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
              <div class="form-group mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" id="username" class="form-control" placeholder="Sam" name="username" required>
              </div>
              
              <div class="form-group mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" class="form-control" placeholder="sample@gmail.com" name="email" required>
              </div>
              
              <div class="form-group mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="position-relative">
                  <input type="password" id="password" class="form-control" placeholder="**********" name="password" required>
                  <span class="password-toggle" onclick="togglePassword('password')">
                    <i id="password-icon" class="fas fa-eye"></i>
                  </span>
                </div>
              </div>
              
              <div class="form-group mb-4">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <div class="position-relative">
                  <input type="password" id="confirm_password" class="form-control" placeholder="**********" name="confirm_password" required>
                  <span class="password-toggle" onclick="togglePassword('confirm_password')">
                    <i id="confirm_password-icon" class="fas fa-eye"></i>
                  </span>
                </div>
              </div>
              
              <div class="form-group d-grid mb-3">
                <button class="btn btn-register" type="submit">Register</button>
              </div>
              
              <div class="text-center mt-4">
                <label class="form-acc"><b>Already have an account?</b> <a href="login.php"><b>Log in</b></a></label>
              </div>
            </form>
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
    });
});
</script>
</body>
</html>