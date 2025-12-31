<?php
session_start();
$conn = new mysqli('localhost', 'thennilavu_matrimonial', 'OYVuiEKfS@FQ', 'thennilavu_thennilavu');
if ($conn->connect_error) { die('DB error'); }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username && $email && $password) {
        $exists = $conn->prepare('SELECT id FROM users WHERE username=? OR email=?');
        $exists->bind_param('ss', $username, $email);
        $exists->execute();
        $exists->store_result();
        if ($exists->num_rows > 0) {
            $err = 'Username or email already exists'; 
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $conn->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
            $ins->bind_param('sss', $username, $email, $hash);
            if ($ins->execute()) {
                header('Location: login.php?registered=1');
                exit;
            } else {
                $err = 'Registration failed';
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
    <link rel="stylesheet" href="bootstrap.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card p-4">
                <h3 class="mb-3">Create a New Account</h3>
                <?php if ($err): ?>
                    <div class="alert alert-danger"><?php echo $err; ?></div>
                <?php endif; ?>
                <form method="post">
                    <div class="mb-3">
                        <label for="username" class="form-label">User Name</label>
                        <input type="text" name="username" id="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" name="email" id="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Register</button>
                </form>
                <div class="mt-3 text-center">
                    Already have an account? <a href="login.php">Log in</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
