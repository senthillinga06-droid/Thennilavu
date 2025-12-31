<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Admin Dashboard | Matrimony Services</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <!-- Top Navigation -->
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
                <span class="user-type"><?= htmlspecialchars(ucfirst($_SESSION['role'])) ?></span>
                <span class="username"><?= htmlspecialchars($_SESSION['name']) ?></span>
            </div>
        </div>
    </header>
    
    <!-- Start Dashboard Layout -->
    <div class="dashboard-layout">