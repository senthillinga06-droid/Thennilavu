<?php
session_start();

$host = "localhost";
$user = "thennilavu_matrimonial";
$pass = "OYVuiEKfS@FQ";
$db   = "thennilavu_thennilavu";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Handle review form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name       = $_POST['name'] ?? '';
    $profession = $_POST['profession'] ?? '';
    $country    = $_POST['country'] ?? '';
    $message    = $_POST['message'] ?? '';
    $rating     = $_POST['rating'] ?? 0;
    $photo_name = null;

    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        $allowed_types = ['image/jpeg','image/png','image/gif'];
        $mime_type = mime_content_type($_FILES["photo"]["tmp_name"]);
        if (in_array($mime_type, $allowed_types)) {
            $photo_name = time().'_'.basename($_FILES["photo"]["name"]);
            move_uploaded_file($_FILES["photo"]["tmp_name"], $target_dir.$photo_name);
        }
    }

    $stmt = $conn->prepare("INSERT INTO reviews (name, profession, country, comment, rating, photo) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssis", $name, $profession, $country, $message, $rating, $photo_name);
    $stmt->execute();
    $stmt->close();
}

// Pagination setup
$stories_per_page = 6;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, $current_page);

// Get total count of published stories
$count_result = $conn->query("SELECT COUNT(*) as total FROM blog WHERE status='published'");
$total_stories = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_stories / $stories_per_page);

// Calculate offset for pagination
$offset = ($current_page - 1) * $stories_per_page;

// Fetch success stories with pagination (only published)
$stories = $conn->query("SELECT * FROM blog WHERE status='published' ORDER BY publish_date DESC LIMIT $stories_per_page OFFSET $offset");

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Success Stories - TheanNilavu Matrimony</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Modern Color Variables */
        :root {
            /* Light Theme */
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --bg-tertiary: #e9ecef;
            --text-primary: #1a1a2e;
            --text-secondary: #495057;
            --text-muted: #6c757d;
            --primary-color: #e91e63;
            --primary-dark: #c2185b;
            --primary-light: #f8bbd9;
            --secondary-color: #7b1fa2;
            --accent-color: #ff4081;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --danger-color: #f44336;
            --border-color: #dee2e6;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --elevated-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 24px;
            --transition: all 0.3s ease;
        }

        /* Dark Theme */
        [data-theme="dark"] {
            --bg-primary: #121212;
            --bg-secondary: #1e1e1e;
            --bg-tertiary: #2d2d2d;
            --text-primary: #f8f9fa;
            --text-secondary: #adb5bd;
            --text-muted: #6c757d;
            --border-color: #404040;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
            --elevated-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
        }

        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            transition: var(--transition);
            -webkit-tap-highlight-color: transparent;
        }

        /* App Container */
        .app-container {
            max-width: 100%;
            min-height: 100vh;
            padding-top: 60px; /* Space for header */
            padding-bottom: 80px; /* Space for bottom nav */
        }

        /* App Header */
        .app-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-color);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 1000;
            backdrop-filter: blur(10px);
            background: rgba(var(--bg-primary-rgb), 0.9);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .back-button {
            background: none;
            border: none;
            color: var(--primary-color);
            font-size: 1.2rem;
            padding: 8px;
            cursor: pointer;
        }

        .header-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .theme-toggle {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.2rem;
            padding: 8px;
            cursor: pointer;
            transition: var(--transition);
        }

        .theme-toggle:hover {
            color: var(--primary-color);
        }

        /* Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--bg-primary);
            border-top: 1px solid var(--border-color);
            padding: 8px 16px;
            display: flex;
            justify-content: space-around;
            z-index: 1000;
            backdrop-filter: blur(10px);
            background: rgba(var(--bg-primary-rgb), 0.95);
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 0.7rem;
            gap: 4px;
            transition: var(--transition);
            padding: 6px 8px;
            border-radius: var(--radius-sm);
            min-width: 56px;
        }

        .nav-item i {
            font-size: 1.1rem;
        }

        .nav-item.active {
            color: var(--primary-color);
            background: rgba(var(--primary-color-rgb), 0.1);
        }

        .nav-item:hover {
            color: var(--primary-color);
        }

        /* Hero Section */
        .hero-section {
            padding: 24px 20px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
        }

        .hero-icon {
            font-size: 2.5rem;
            margin-bottom: 12px;
            opacity: 0.9;
        }

        .hero-title {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 8px;
            line-height: 1.2;
        }

        .hero-subtitle {
            font-size: 0.95rem;
            opacity: 0.9;
            max-width: 300px;
            margin: 0 auto;
        }

        /* Content Container */
        .content-container {
            padding: 20px 16px;
        }

        /* Stories Grid */
        .stories-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        @media (min-width: 768px) {
            .stories-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1024px) {
            .stories-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        /* Story Card */
        .story-card {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid var(--border-color);
        }

        .story-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--elevated-shadow);
        }

        .story-image {
            height: 200px;
            overflow: hidden;
            position: relative;
        }

        .story-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .story-card:hover .story-image img {
            transform: scale(1.05);
        }

        .story-date-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            z-index: 2;
        }

        .story-content {
            padding: 20px;
        }

        .story-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .story-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }

        .story-heart {
            color: var(--primary-color);
            font-size: 0.9rem;
        }

        .story-date {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 12px;
            display: block;
        }

        .story-excerpt {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 16px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .read-story-btn {
            display: inline-block;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 8px 0;
            border: none;
            background: none;
            cursor: pointer;
            width: 100%;
            text-align: left;
            border-top: 1px solid var(--border-color);
            padding-top: 16px;
            margin-top: 8px;
        }

        .read-story-btn:hover {
            color: var(--primary-dark);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin: 32px 0;
            flex-wrap: wrap;
        }

        .page-btn {
            padding: 10px 16px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            color: var(--text-primary);
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
            min-width: 44px;
            text-align: center;
        }

        .page-btn:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .page-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .page-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        .page-info {
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-top: 12px;
            width: 100%;
        }

        /* Review Form */
        .review-section {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 24px;
            margin: 24px 0;
            border: 1px solid var(--border-color);
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }

        .section-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            background: var(--bg-primary);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            color: var(--text-primary);
            transition: var(--transition);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
        }

        textarea.form-input {
            min-height: 120px;
            resize: vertical;
            font-family: inherit;
        }

        .form-select {
            width: 100%;
            padding: 14px 16px;
            background: var(--bg-primary);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            color: var(--text-primary);
            transition: var(--transition);
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 12px;
        }

        .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
        }

        /* Submit Button */
        .submit-button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .submit-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(233, 30, 99, 0.3);
        }

        /* Star Rating */
        .rating-stars {
            display: flex;
            gap: 4px;
            margin: 8px 0;
        }

        .rating-stars i {
            color: #ddd;
            font-size: 1.2rem;
        }

        .rating-stars i.filled {
            color: #ffc107;
        }

        /* Toast Notification */
        .toast {
            position: fixed;
            bottom: 80px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: var(--success-color);
            color: white;
            padding: 14px 20px;
            border-radius: var(--radius-md);
            box-shadow: var(--elevated-shadow);
            opacity: 0;
            transition: var(--transition);
            z-index: 1100;
            max-width: 90%;
            text-align: center;
            font-weight: 500;
        }

        .toast.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }

        .toast.error {
            background: var(--danger-color);
        }

        /* Loading Spinner */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .empty-icon {
            font-size: 3rem;
            color: var(--text-muted);
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.3rem;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .empty-state p {
            font-size: 0.95rem;
            max-width: 300px;
            margin: 0 auto;
        }

        /* Story Modal */
        .story-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 2000;
            display: none;
        }

        .story-modal.active {
            display: block;
        }

        .modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--elevated-shadow);
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 4px;
        }

        .modal-body {
            padding: 20px;
            overflow-y: auto;
            max-height: 60vh;
        }

        .modal-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
        }

        .modal-author {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .modal-date {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 16px;
        }

        .modal-text {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* Responsive Adjustments */
        @media (min-width: 768px) {
            .app-container {
                max-width: 480px;
                margin: 0 auto;
                border-left: 1px solid var(--border-color);
                border-right: 1px solid var(--border-color);
            }
            
            .bottom-nav .nav-item {
                font-size: 0.75rem;
            }
        }

        @media (max-width: 380px) {
            .stories-grid {
                grid-template-columns: 1fr;
            }
            
            .hero-title {
                font-size: 1.5rem;
            }
            
            .bottom-nav .nav-item {
                font-size: 0.65rem;
                padding: 6px 4px;
                min-width: 50px;
            }
            
            .bottom-nav .nav-item i {
                font-size: 1rem;
            }
        }

        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <!-- Theme Variables Script -->
    <script>
        document.documentElement.style.setProperty('--bg-primary-rgb', '255, 255, 255');
        document.documentElement.style.setProperty('--primary-color-rgb', '233, 30, 99');
        
        if (document.documentElement.getAttribute('data-theme') === 'dark') {
            document.documentElement.style.setProperty('--bg-primary-rgb', '18, 18, 18');
        }
    </script>

    <!-- App Container -->
    <div class="app-container">
        <!-- App Header -->
        <header class="app-header">
            <div class="header-left">
                <button class="back-button" onclick="goBack()">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <h1 class="header-title">Success Stories</h1>
            </div>
            <button class="theme-toggle" id="themeToggle">
                <i class="fas fa-moon"></i>
            </button>
        </header>

        <!-- Hero Section -->
        <section class="hero-section">
            <div class="hero-icon">
                <i class="fas fa-heart"></i>
            </div>
            <h1 class="hero-title">Real Love Stories</h1>
            <p class="hero-subtitle">Inspiring stories of love and companionship from our community</p>
        </section>

        <!-- Main Content -->
        <main class="content-container">
            <?php if ($stories && $stories->num_rows > 0): ?>
                <div class="stories-grid">
                    <?php while ($story = $stories->fetch_assoc()): 
                        $imgPath = 'img/default.jpg';
                        if (!empty($story['author_photo'])) {
                            $photo = $story['author_photo'];
                            $imgPath = 'https://administration.thennilavu.lk/' . $photo;
                        }
                        $date = !empty($story['publish_date']) ? date('F Y', strtotime($story['publish_date'])) : 'December 2024';
                        $excerpt = htmlspecialchars(mb_strimwidth(strip_tags($story['content']), 0, 150, '...'));
                    ?>
                        <div class="story-card fade-in">
                            <div class="story-image">
                                <img src="<?= htmlspecialchars($imgPath) ?>" alt="<?= htmlspecialchars($story['title']) ?>">
                                <div class="story-date-badge"><?= $date ?></div>
                            </div>
                            <div class="story-content">
                                <div class="story-header">
                                    <h3 class="story-title"><?= htmlspecialchars($story['author_name']) ?></h3>
                                    <i class="fas fa-heart story-heart"></i>
                                </div>
                                <span class="story-date"><?= $date ?></span>
                                <p class="story-excerpt"><?= $excerpt ?></p>
                                <button class="read-story-btn" 
                                    data-story-title="<?= htmlspecialchars($story['title']) ?>"
                                    data-story-content="<?= htmlspecialchars($story['content']) ?>"
                                    data-story-author="<?= htmlspecialchars($story['author_name']) ?>"
                                    data-story-date="<?= !empty($story['publish_date']) ? date('F j, Y', strtotime($story['publish_date'])) : 'December 2024' ?>"
                                    data-story-image="<?= htmlspecialchars($imgPath) ?>">
                                    Read Full Story <i class="fas fa-arrow-right" style="margin-left: 8px;"></i>
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($current_page > 1): ?>
                            <a href="?page=<?= $current_page - 1 ?>" class="page-btn">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-btn disabled">
                                <i class="fas fa-chevron-left"></i>
                            </span>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == 1 || $i == $total_pages || ($i >= $current_page - 1 && $i <= $current_page + 1)): ?>
                                <a href="?page=<?= $i ?>" class="page-btn <?= $i == $current_page ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php elseif ($i == $current_page - 2 || $i == $current_page + 2): ?>
                                <span class="page-btn disabled">...</span>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?= $current_page + 1 ?>" class="page-btn">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-btn disabled">
                                <i class="fas fa-chevron-right"></i>
                            </span>
                        <?php endif; ?>

                        <div class="page-info">
                            Showing <?= (($current_page - 1) * $stories_per_page) + 1 ?> to 
                            <?= min($current_page * $stories_per_page, $total_stories) ?> of 
                            <?= $total_stories ?> stories
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-heart-broken"></i>
                    </div>
                    <h3>No Stories Yet</h3>
                    <p>Check back soon for inspiring love stories from our community.</p>
                </div>
            <?php endif; ?>

            <!-- Share Your Story Section -->
            <div class="review-section fade-in">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <h2 class="section-title">Share Your Story</h2>
                </div>

                <form method="POST" enctype="multipart/form-data" onsubmit="return validateReviewForm()">
                    <div class="form-group">
                        <label class="form-label" for="name">Your Name *</label>
                        <input type="text" class="form-input" id="name" name="name" required 
                               placeholder="Enter your full name">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="profession">Profession</label>
                        <input type="text" class="form-input" id="profession" name="profession" 
                               placeholder="Your profession">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="country">Country</label>
                        <input type="text" class="form-input" id="country" name="country" 
                               placeholder="Your country">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="message">Your Experience *</label>
                        <textarea class="form-input" id="message" name="message" required 
                                  placeholder="Share your inspiring experience..."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Rating *</label>
                        <select name="rating" class="form-select" required>
                            <option value="" disabled selected>Select your rating</option>
                            <option value="5">★★★★★ Excellent</option>
                            <option value="4">★★★★ Very Good</option>
                            <option value="3">★★★ Good</option>
                            <option value="2">★★ Fair</option>
                            <option value="1">★ Poor</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="photo">Your Photo (Optional)</label>
                        <input type="file" class="form-input" id="photo" name="photo" 
                               accept="image/jpeg,image/png,image/gif">
                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">
                            JPEG, PNG or GIF files accepted
                        </div>
                    </div>

                    <button type="submit" class="submit-button" id="submitBtn">
                        <i class="fas fa-paper-plane"></i>
                        <span id="submitText">Share Your Story</span>
                    </button>
                </form>
            </div>
        </main>

        <!-- Story Modal -->
        <div class="story-modal" id="storyModal">
            <div class="modal-overlay" onclick="closeStoryModal()"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title" id="modalStoryTitle"></h3>
                    <button class="modal-close" onclick="closeStoryModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <img id="modalStoryImage" class="modal-image" src="" alt="Story Image">
                    <div class="modal-author" id="modalStoryAuthor"></div>
                    <div class="modal-date" id="modalStoryDate"></div>
                    <div class="modal-text" id="modalStoryContent"></div>
                </div>
            </div>
        </div>

        <!-- Bottom Navigation -->
        <nav class="bottom-nav">
            <a href="index.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="members.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Membership</span>
            </a>
            <a href="mem.php" class="nav-item">
                <i class="fas fa-user-friends"></i>
                <span>Members</span>
            </a>
            <a href="contact.php" class="nav-item">
                <i class="fas fa-envelope"></i>
                <span>Contact</span>
            </a>
            <a href="story.php" class="nav-item active">
                <i class="fas fa-heart"></i>
                <span>Stories</span>
            </a>
            <a href="profile.php" class="nav-item">
                <i class="fas fa-user-circle"></i>
                <span>Profile</span>
            </a>
        </nav>

        <!-- Toast Notification -->
        <div class="toast" id="toast"></div>
    </div>

    <script>
        // Theme Management
        const themeToggle = document.getElementById('themeToggle');
        const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');
        
        const currentTheme = localStorage.getItem('theme') || 
                           (prefersDarkScheme.matches ? 'dark' : 'light');
        
        document.documentElement.setAttribute('data-theme', currentTheme);
        updateThemeIcon(currentTheme);
        updateThemeColors(currentTheme);
        
        themeToggle.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
            updateThemeColors(newTheme);
        });
        
        function updateThemeIcon(theme) {
            const icon = themeToggle.querySelector('i');
            icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
        
        function updateThemeColors(theme) {
            if (theme === 'dark') {
                document.documentElement.style.setProperty('--bg-primary-rgb', '18, 18, 18');
            } else {
                document.documentElement.style.setProperty('--bg-primary-rgb', '255, 255, 255');
            }
        }
        
        // Back Button Function
        function goBack() {
            window.history.back();
        }
        
        // Story Modal Functions
        const storyModal = document.getElementById('storyModal');
        
        document.querySelectorAll('.read-story-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const title = this.getAttribute('data-story-title');
                const content = this.getAttribute('data-story-content');
                const author = this.getAttribute('data-story-author');
                const date = this.getAttribute('data-story-date');
                const image = this.getAttribute('data-story-image');
                
                document.getElementById('modalStoryTitle').textContent = title;
                document.getElementById('modalStoryContent').textContent = content;
                document.getElementById('modalStoryAuthor').textContent = author;
                document.getElementById('modalStoryDate').textContent = date;
                document.getElementById('modalStoryImage').src = image;
                
                storyModal.classList.add('active');
                document.body.style.overflow = 'hidden';
            });
        });
        
        function closeStoryModal() {
            storyModal.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        // Close modal on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && storyModal.classList.contains('active')) {
                closeStoryModal();
            }
        });
        
        // Form Validation
        function validateReviewForm() {
            const form = document.querySelector('form');
            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');
            
            const name = form.name.value.trim();
            const message = form.message.value.trim();
            const rating = form.rating.value;
            
            if (!name || !message || !rating) {
                showToast('Please fill in all required fields', 'error');
                return false;
            }
            
            // Show loading state
            submitBtn.disabled = true;
            const originalHTML = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="loading-spinner"></span> Sharing...';
            
            // Form is valid, allow submission to PHP
            return true;
        }
        
        // Toast Function
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast ' + (type === 'error' ? 'error' : '');
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 4000);
        }
        
        // Initialize animations
        document.addEventListener('DOMContentLoaded', () => {
            const fadeElements = document.querySelectorAll('.fade-in');
            
            fadeElements.forEach(el => {
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
            });
            
            // Auto-hide PHP messages after 5 seconds
            const phpMessages = document.querySelectorAll('.toast.show');
            phpMessages.forEach(toast => {
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 5000);
            });
        });
    </script>
</body>
</html>