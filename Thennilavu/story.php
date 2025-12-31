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
$current_page = max(1, $current_page); // Ensure page is at least 1

// Get total count of published stories
$count_result = $conn->query("SELECT COUNT(*) as total FROM blog WHERE status='published'");
$total_stories = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_stories / $stories_per_page);

// Calculate offset for pagination
$offset = ($current_page - 1) * $stories_per_page;

// Fetch success stories with pagination (only published)
$stories = $conn->query("SELECT * FROM blog WHERE status='published' ORDER BY publish_date DESC LIMIT $stories_per_page OFFSET $offset");

// Fetch popular posts
$popular = $conn->query("SELECT * FROM blog WHERE status='published' ORDER BY RAND() LIMIT 4");

// Fetch latest posts
$latest = $conn->query("SELECT * FROM blog WHERE status='published' ORDER BY created_at DESC LIMIT 4");

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Success Stories - Thennilavu Matrimony</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
    --primary-color: #e91e63;
    --primary-light: #fce4ec;
    --secondary-color: #2c3e50;
    --accent-color: #ff4081;
    --light-color: #f8f9fa;
    --dark-color: #343a40;
    --primary-gradient: linear-gradient(50deg, #e91e63, #ff4081);
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    /* match members.php overlay strength for consistent look */
    background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('img/1.jpg') center/cover no-repeat fixed;
    color: #333;
    overflow-x: hidden;
    padding-top: 90px; /* Prevent content from being hidden under fixed navbar */ 
}

     .navbar {
            background-color: rgba(0, 0, 0, 0.7); /* Black with 60% opacity */
            padding: 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            height: 90px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
        }

        
        /* Premium Button Styles with Icons */
.ms-3.d-flex.gap-2 {
    padding: 10px;
    align-items: center;
    gap: 15px;
}

.custom-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 12px 25px;
    border-radius: 12px;
    text-decoration: none;
    transition: all 0.4s ease;
    border: none;
    font-weight: 600;
    font-size: 0.95rem;
    min-width: 130px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    position: relative;
    overflow: hidden;
    gap: 8px;
}

/* Dashboard Button */
.dashboard-btn {
    background: linear-gradient(135deg, #e91e63, #ec407a, #f06292);
    color: white;
    border: 2px solid rgba(255, 255, 255, 0.25);
}

.dashboard-btn:hover {
    background: linear-gradient(135deg, #c2185b, #e91e63, #ec407a);
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 12px 30px rgba(233, 30, 99, 0.35);
    border-color: rgba(255, 255, 255, 0.4);
}

/* Logout Button */
.logout-btn {
    background: linear-gradient(135deg, #ffffff, #f8f9fa, #e9ecef);
    color: #2c3e50;
    border: 2px solid rgba(255, 255, 255, 0.4);
}

.logout-btn:hover {
    background: linear-gradient(135deg, #c2185b, #e91e63, #ec407a);
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 12px 30px rgba(233, 30, 99, 0.35);
    border-color: rgba(255, 255, 255, 0.4);
    color: white;
}

/* Button Icons */
.btn-icon {
    font-size: 1.1rem;
    transition: transform 0.3s ease;
}

.dashboard-btn:hover .btn-icon {
    transform: scale(1.2) rotate(5deg);
}

.logout-btn:hover .btn-icon {
    transform: scale(1.2) translateX(2px);
}

/* Text styles */
.btn-text {
    color: inherit;
    font-weight: inherit;
    font-size: inherit;
}

.navbar-brand {
    font-weight: 700;
    font-size: 1.8rem;
    color: white !important;
    display: flex;
    align-items: center;
}

.navbar-brand img {
    margin-right: 10px;
    transition: transform 0.3s ease;
    /* Constrain logo height so it doesn't overflow the navbar and push content */
    height: 144px;
    display: block;
}

.navbar-brand:hover img {
    transform: rotate(10deg);
}

.navbar-nav .nav-link {
    color: rgba(255, 255, 255, 0.85) !important;
    font-weight: 500;
    margin: 0 8px;
    padding: 8px 16px !important;
    border-radius: 20px;
    transition: all 0.3s ease;
    position: relative;
}
.story-date {
  position: absolute;
  top: 12px;
  left: 12px;
  background: rgba(0, 0, 0, 0.7);
  color: #fff;
  padding: 6px 14px;
  border-radius: 50px; /* ellipse shape */
  font-size: 14px;
  font-weight: 500;
  letter-spacing: 0.5px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.2);
  z-index: 2;
}

.navbar-nav .nav-link:hover,
.navbar-nav .nav-link.active {
    color: white !important;
    transform: translateY(-2px);
}

.navbar-nav .nav-link::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    width: 0;
    height: 2px;
    background: white;
    transition: all 0.3s ease;
    transform: translateX(-50%);
}

.navbar-nav .nav-link:hover::after,
.navbar-nav .nav-link.active::after {
    width: 70%;
}

.navbar-toggler {
    border: 1px solid rgba(255, 255, 255, 0.3);
    padding: 4px 8px;
    margin-top: -16px;
}

.navbar-toggler:focus {
    box-shadow: none;
}

.navbar-toggler-icon {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.8%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
}


       /* Button container */
        .ms-3.d-flex.gap-2 {
            padding: 10px;
            align-items: center;
        }

        /* Base button styles */
        .custom-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            min-width: 100px;
        }

.hero-section {
    padding: 12px 0 60px;
    color: white;
    text-align: center;
    margin-top: 76px;
    position: relative;
}

.hero-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
}

.hero-section h1 {
    font-size: 4rem;
}

.hero-section .lead {
    font-size: 1.3rem;
    margin-bottom: 2rem;
    opacity: 0.9;
    font-weight: 300;
}

.section-title {
    position: relative;
    margin-bottom: 50px;
    font-weight: 700;
    color: white;
    font-size: 2.5rem;
    text-align: center;
    font-family: 'Playfair Display', serif;
}

.section-title:after {
    content: '';
    display: block;
    width: 80px;
    height: 4px;
    background: var(--primary-gradient);
    margin: 20px auto;
    border-radius: 2px;
}

/* Success Stories Section (copied/adapted from home.php) */
#stories {
    /* transparent background per request (no yellow) */
    background: transparent;
    position: relative;
    overflow: hidden;
}

#stories::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-image: 
        radial-gradient(circle at 20% 30%, rgba(233, 30, 99, 0.03) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(233, 30, 99, 0.03) 0%, transparent 50%);
    pointer-events: none;
}

.story-card {
    background: #ffffff;
    border-radius: 12px; /* match story.html ~0.75rem */
    overflow: hidden;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    height: 100%;
    position: relative;
    border: 1px solid #e5e7eb;
}

.story-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.story-image {
    height: 300px; /* match story.html */
    overflow: hidden;
    position: relative;
    background-color: #e5e7eb; /* match story.html placeholder */
    transition: transform 0.7s ease;
}

.story-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.7s ease;
    z-index: 0;
}

/* Gradient overlay on hover (matching story.html) */
.story-image::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(225,29,72,0.8), rgba(225,29,72,0.2), transparent);
    opacity: 0;
    transition: opacity 0.5s;
    z-index: 1;
}

/* Heart icon on hover (matching story.html) */
.story-image::after {
    content: "❤";
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: rgba(255,255,255,0.9);
    backdrop-filter: blur(5px);
    border-radius: 50%;
    padding: 0.5rem;
    font-size: 1rem;
    transform: translateX(50px);
    transition: transform 0.5s, opacity 0.5s;
    opacity: 0;
    z-index: 2;
}

.story-card:hover .story-image img {
    transform: scale(1.1);
}

.story-card:hover .story-image::before {
    opacity: 1;
}

.story-card:hover .story-image::after {
    transform: translateX(0);
    opacity: 1;
}

/* Remove full overlay - matching story.html (no full pink overlay on hover) */

/* Story content section below image (matching story.html) */
.story-content {
    padding: 1.5rem;
    background: white;
}

.story-names {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.story-names h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1a1a1a;
    margin: 0;
}

.story-names i {
    color: #e11d48;
    font-size: 1rem;
}

.story-date-text {
    color: #6b7280;
    margin-bottom: 1rem;
    font-size: 0.875rem;
    font-weight: 500;
}

.story-text {
    color: #6b7280;
    line-height: 1.6;
    font-size: 0.95rem;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

.read-full-story {
    all: unset;
    display: inline-block;
    margin-top: 1rem;
    color: #e11d48;
    font-size: 1.0rem;
    font-weight: 500;
    text-decoration: none;
}

.read-full-story:hover {
    color: #e11d48;
    text-decoration: none;
}

/* Story Modal Styles */
.story-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    animation: fadeIn 0.3s ease;
}

.story-modal.active {
    display: block;
}

.story-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px);
}

.story-modal-content {
    position: relative;
    /* reduce modal width and keep it readable — narrower but taller image */
    width: min(92%, 720px);
    max-width: 720px;
    max-height: 95vh;
    margin: 5vh auto;
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
    animation: slideUp 0.4s ease;
}

.story-modal-close {
    position: absolute;
    top: 20px;
    right: 20px;
    width: 40px;
    height: 40px;
    background: white;
    border: none;
    border-radius: 50%;
    font-size: 28px;
    line-height: 1;
    cursor: pointer;
    z-index: 10;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transition: all 0.3s ease;
    color: #6b7280;
}

.story-modal-close:hover {
    background: #e11d48;
    color: white;
    transform: rotate(90deg);
}

.story-modal-body {
    display: flex;
    flex-direction: column;
    max-height: 90vh;
    overflow-y: auto;
}

.story-modal-image {
    width: 100%;
    height: clamp(610px, 60vh, 1090px);
    background: #f3f4f6;
}

.story-modal-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.story-modal-details {
    padding: 2.5rem;
}

.story-modal-header {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid #f3f4f6;
}

.story-modal-header h2 {
    color: #1f2937;
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 1rem;
    line-height: 1.3;
}

.story-modal-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    font-size: 0.95rem;
    color: #6b7280;
}

.story-modal-meta span {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.story-modal-meta i {
    color: #e11d48;
    font-size: 1rem;
}

.story-modal-author span,
.story-modal-date span,
.story-modal-category span {
    font-weight: 500;
    color: #374151;
}

.story-modal-text {
    color: #4b5563;
    font-size: 1.1rem;
    line-height: 1.8;
    text-align: justify;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 768px) {
    .story-modal-content {
        margin: 0;
        max-height: 100vh;
        border-radius: 0;
        width: 100%;
        height: 100vh;
    }
    
    .story-modal-image {
        /* keep image prominent on phones — increased height */
    /* increased by +60px for mobile */
    height: clamp(470px, 50vh, 890px);
    }
    
    .story-modal-details {
        padding: 1.5rem;
    }
    
    .story-modal-header h2 {
        font-size: 1.5rem;
    }
    
    .story-modal-meta {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .story-modal-text {
        font-size: 1rem;
    }
}

/* Match story.html container width for stories */
#stories .container { max-width: 1200px; }

.read-more-btn:hover {
    color: var(--accent-color);
}

.read-more-btn i {
    margin-left: 8px;
    transition: transform 0.3s ease;
}

.read-more-btn:hover i {
    transform: translateX(6px);
}

.read-more-btn::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 0;
    height: 2px;
    background: var(--primary-color);
    transition: width 0.3s ease;
}

.read-more-btn:hover::after {
    width: 100%;
}

.story-badge {
    position: absolute;
    top: 20px;
    right: 20px;
    background: var(--primary-gradient);
    color: white;
    padding: 8px 16px;
    border-radius: 25px;
    font-size: 0.85rem;
    font-weight: 600;
    z-index: 10;
    box-shadow: 0 4px 15px rgba(233, 30, 99, 0.3);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Story Modal */
.modal-content {
    border-radius: 20px;
    overflow: hidden;
    border: none;
    box-shadow: 0 30px 80px rgba(0, 0, 0, 0.3);
    background: rgba(255, 255, 255, 0.98);
    border: 1px solid rgba(233, 30, 99, 0.1);
}

.modal-header {
    background: var(--primary-gradient);
    color: white;
    border-bottom: none;
    padding: 1.5rem 2rem;
    position: relative;
}

.modal-header::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 5%;
    width: 90%;
    height: 1px;
    background: rgba(255, 255, 255, 0.3);
}

.modal-title {
    font-weight: 600;
    font-size: 1.5rem;
    font-family: 'Playfair Display', serif;
}

.modal-body {
    padding: 2rem;
    color: #333;
    font-family: 'Poppins', sans-serif;
}

.modal-img {
    border-radius: 15px;
    margin-bottom: 2rem;
    width: 100%;
    height: 350px;
    object-fit: cover;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    border: 3px solid var(--primary-light);
}

.modal-body p.lead {
    color: #444;
    line-height: 1.8;
    font-weight: 400;
    letter-spacing: 0.3px;
    text-align: justify;
    margin-bottom: 1.5rem;
    font-family: 'Poppins', sans-serif;
}

/* (Reverted) Stories grid remains default Bootstrap columns */

/* Why Choose (Features) section styles adapted from story.html */
.features {
    padding: 5rem 1rem;
}

.features .section-header {
    text-align: center;
    margin-bottom: 4rem;
}

.features .section-title {
    font-size: 2.25rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: #ffffff;
}

/* Remove decorative underline inherited from global .section-title */
.features .section-title:after { display: none; }

@media (min-width: 768px) {
    .features .section-title { font-size: 3rem; }
}

.features .section-description {
    font-size: 1.125rem;
    color: rgba(255, 255, 255, 0.6); /* white at 10% opacity */
    max-width: 42rem;
    margin: 0 auto;
}

.features-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
}

@media (min-width: 768px) {
    .features-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (min-width: 1024px) {
    .features-grid { grid-template-columns: repeat(3, 1fr); }
}

.feature-card {
    padding: 2rem;
    border-radius: 1rem;
    background: #ffffff;
    border: 1px solid #e5e7eb; /* border */
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
    transition: all 0.5s ease;
}

.feature-card:hover {
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
}

.feature-icon {
    width: 3.5rem;
    height: 3.5rem;
    border-radius: 50%;
    background-color: rgba(225, 29, 72, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
    transition: transform 0.5s ease;
}

.feature-card:hover .feature-icon { transform: scale(1.1); }

.feature-icon i { color: #e11d48; font-size: 1.75rem; }

.feature-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
    transition: color 0.3s ease;
    color: #111827;
}

.feature-card:hover .feature-title { color: #e11d48; }

.feature-description { color: #6b7280; line-height: 1.7; }

.modal-body h6 {
    color: var(--primary-color);
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.modal-body .text-muted {
    color: #888 !important;
    font-size: 0.9rem;
}

.avatar-placeholder {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--primary-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2rem;
    font-weight: bold;
    box-shadow: 0 8px 25px rgba(233, 30, 99, 0.3);
    border: 3px solid white;
}

.btn-close {
    filter: invert(1);
    opacity: 0.8;
}

.btn-close:hover {
    opacity: 1;
}

/* Review Section */
.review-section {
    padding: 100px 0;
    position: relative;
    margin: 60px 0;
}

.review-section::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100" opacity="0.02"><defs><pattern id="hearts" width="100" height="100" patternUnits="userSpaceOnUse"><path d="M50,30 60,40 70,30 65,45 80,50 65,55 70,70 60,60 50,70 40,60 30,70 35,55 20,50 35,45 30,30 40,40 Z" fill="%23e91e63" /></pattern></defs><rect width="100" height="100" fill="url(%23hearts)" /></svg>');
}

.review-section .card {
    border: none;
    border-radius: 25px;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.15);
    background: rgba(255, 255, 255, 0.98);
    border: 1px solid rgba(233, 30, 99, 0.1);
    overflow: hidden;
}

.review-section .card-body {
    padding: 3rem;
}

.form-control, .form-select {
    border-radius: 12px;
    padding: 1rem 1.2rem;
    border: 2px solid #e8f0fe;
    transition: all 0.3s ease;
    font-size: 1rem;
    background: rgba(255, 255, 255, 0.9);
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.3rem rgba(233, 30, 99, 0.15);
    background: white;
}

.btn-primary {
    background: var(--primary-gradient);
    border: none;
    border-radius: 12px;
    padding: 1rem 2.5rem;
    font-weight: 600;
    font-size: 1.1rem;
    transition: all 0.3s ease;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(233, 30, 99, 0.3);
    background: linear-gradient(50deg, #ff4081, #e91e63);
}

/* Stats Section */
.counter-box {
    text-align: center;
    padding: 40px 30px;
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
    transition: all 0.4s ease;
    border: 1px solid rgba(233, 30, 99, 0.1);
    position: relative;
    overflow: hidden;
}

.counter-box::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--primary-gradient);
}

.counter-box:hover {
    transform: translateY(-10px) scale(1.05);
    box-shadow: 0 25px 50px rgba(233, 30, 99, 0.15);
}

.counter-box h2 {
    font-size: 3.5rem;
    font-weight: bold;
    color: var(--primary-color);
    margin-bottom: 15px;
    font-family: 'Playfair Display', serif;
}

.counter-box p {
    font-size: 1.2rem;
    color: #666;
    margin: 0;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Footer */
footer {
    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
    color: white;
    padding: 80px 0 40px;
    margin-top: 80px;
    position: relative;
}

footer::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--primary-gradient);
}

footer h5 {
    color: white;
    margin-bottom: 1.5rem;
    position: relative;
    padding-bottom: 0.8rem;
    font-family: 'Playfair Display', serif;
    font-size: 1.3rem;
}

footer h5::after {
    content: "";
    position: absolute;
    bottom: 0;
    left: 0;
    width: 50px;
    height: 3px;
    background: var(--primary-color);
    border-radius: 2px;
}

footer a {
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    transition: all 0.3s ease;
    display: inline-block;
    margin-bottom: 0.5rem;
}

footer a:hover {
    color: var(--primary-color);
    padding-left: 8px;
    transform: translateX(5px);
}

.social-icons {
    margin-top: 1.5rem;
}

.social-icons a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    margin-right: 12px;
    transition: all 0.3s ease;
    text-decoration: none;
    padding: 0;
    transform: none;
}

.social-icons a:hover {
    background: var(--primary-color);
    transform: translateY(-5px) scale(1.1);
    box-shadow: 0 10px 25px rgba(233, 30, 99, 0.3);
}

.heart {
    position: absolute;
    color: var(--primary-color);
    font-size: 24px;
    pointer-events: none;
    animation: floatUp 3s ease-out forwards;
    opacity: 1;
    z-index: 9999;
    text-shadow: 0 2px 10px rgba(0,0,0,0.3);
}

@keyframes floatUp {
    0% { transform: translate(0, 0) scale(1); opacity: 1; }
    100% { transform: translate(-30px, -250px) scale(1.8); opacity: 0; }
}

.rating i {
    color: #ffc107;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.testimonial-img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid white;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.avatar-placeholder {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: var(--primary-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.8rem;
    font-weight: bold;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
}

/* Form Labels */
.form-label {
    font-weight: 600;
    color: #444;
    margin-bottom: 0.8rem;
    font-size: 1rem;
}

/* No Stories State */
.no-stories {
    padding: 80px 20px;
    text-align: center;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 20px;
}

.no-stories i {
    font-size: 4rem;
    margin-bottom: 2rem;
    opacity: 0.7;
}

.no-stories h3 {
    color: rgba(255, 255, 255, 0.8);
    margin-bottom: 1rem;
    font-weight: 300;
}

.no-stories p {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1.1rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .hero-section {
        padding: 100px 0 40px;
        margin-top: 70px;
    }
    
    .hero-section h1 {
        font-size: 2.5rem;
    }
    
    .hero-section .lead {
        font-size: 1.1rem;
    }
    
    .section-title {
        font-size: 2rem;
    }
    
    .navbar-nav {
        background: #000000;
        border-radius: 15px;
    }
    
    .counter-box h2 {
        font-size: 2.8rem;
    }
    
    .review-section .card-body {
        padding: 2rem;
    }
    
    .story-card .card-body {
        padding: 1.5rem;
    }
    
    .modal-body {
        padding: 1.5rem;
    }
}

@media (max-width: 576px) {
    .hero-section h1 {
        font-size: 2rem;
    }
    
    .section-title {
        font-size: 1.8rem;
    }
    
    .counter-box {
        padding: 30px 20px;
    }
    
    .counter-box h2 {
        font-size: 2.2rem;
    }
}

        .heading1 {
            text-align:center;
            color:transparent;
            -webkit-text-stroke:1px #fff;
            background:url("./img/back.png");
            background-clip:text; /* standard property for compatibility */
            -webkit-background-clip:text;
            animation:back 20s linear infinite;
        }

        @keyframes back{
            100%{
                background-position:2000px 0;
            }
        }

        /* Mobile Navbar Improvements */
        @media (max-width: 991px) {
            .navbar {
                height: auto;
                /* match members.php */
                min-height: 93px;
                padding: 1px 0;
            }
            
            .navbar-collapse {
                background: linear-gradient(135deg, rgba(0, 0, 0, 0.95), rgba(20, 20, 20, 0.95));
                margin-top: -28px;
                padding: 20px 15px;
                border-radius: 12px;                
                margin-left: -10px;
                margin-right: -10px;
                box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
                border: 1px solid rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(10px);
            }
            
            .navbar-nav {
                text-align: center;
                margin-bottom: 15px;
                gap: 0px;
                display: flex;
                flex-direction: column;
            }
            
            .navbar-nav .nav-link {
                margin: 4px 0;
                padding: 12px 20px !important;
                display: block;
                border-radius: 8px;
                background: rgba(255, 255, 255, 0.08);
                font-size: 0.9rem;
                font-weight: 500;
                color: rgba(255, 255, 255, 0.9) !important;
                border: 1px solid rgba(255, 255, 255, 0.1);
                transition: all 0.3s ease;
                text-decoration: none;
            }
            
            .navbar-nav .nav-link:hover,
            .navbar-nav .nav-link.active {
                background: linear-gradient(135deg, #e91e63, #ec407a);
                color: white !important;
                transform: translateY(-2px);
                box-shadow: 0 8px 20px rgba(233, 30, 99, 0.3);
                border-color: rgba(255, 255, 255, 0.3);
            }
            
            .ms-3.d-flex.gap-2 {
                justify-content: center;
                flex-wrap: wrap;
                gap: 12px !important;
                padding: 15px 0 10px 0;
                border-top: 1px solid rgba(255, 255, 255, 0.1);
                margin-top: 10px;
            }
            
            .custom-btn {
                min-width: 120px;
                padding: 10px 20px;
                font-size: 0.85rem;
                font-weight: 600;
                border-radius: 8px;
                transition: all 0.3s ease;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .navbar-brand img {
                /* slightly larger logo on mobile */
                max-width: 220px;
                height: 140px;
                width: auto;
                display: block;
            }
            
            .navbar-toggler {
                padding: 6px 9px;
                font-size: 1.1rem;
                border: none;
                background: rgba(255, 255, 255, 0.15);
                border-radius: 6px;
            }
            
            .navbar-toggler:hover {
                background: rgba(255, 255, 255, 0.25);
            }
            
            /* larger menu icon */
            .navbar-toggler-icon {
                width: 30px;
                height: 30px;
                background-size: 30px 30px;
            }
            
            /* Fix hero section top spacing for mobile; push welcome down a bit */
            .new-hero-section {
                padding-top: 110px;
            }

            /* Move welcome text slightly down if needed */
            .hero-welcome {
                margin-top: 8px;
            }

            /* Hide Success Stories button on mobile */
            .hero-buttons .hero-btn.primary {
                display: none !important;
            }
        }

        @media (max-width: 768px) and (min-width: 577px) {
            .navbar {
                max-height: 73px;
            }
        }

        @media (max-width: 576px) {
            .navbar {
                max-height: 85px;
            }
            
            .navbar-brand img {
                max-width: 160px;
                height: 132px;
                width: auto;
            }
            
            .custom-btn {
                min-width: 100px;
                padding: 8px 15px;
                font-size: 0.85rem;
            }
        }

        @media (max-width: 480px) {
            .navbar {
                max-height: 90px;
            }
        }

        @media (max-width: 400px) {
            .navbar {
                max-height: 47px;
            }
            
            .navbar-brand img {
                max-width: 240px;
                margin-top: -28px;
                height: 134px;
                width: 200px;
            }
        }

        /* Pagination Styles */
        .pagination {
            margin: 0;
        }
        
        .pagination .page-link {
            background-color: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(233, 30, 99, 0.2);
            color: var(--primary-color);
            padding: 12px 18px;
            margin: 0 4px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .pagination .page-link:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(233, 30, 99, 0.3);
        }
        
        .pagination .page-item.active .page-link {
            background: var(--primary-gradient);
            border-color: var(--primary-color);
            color: white;
            box-shadow: 0 4px 12px rgba(233, 30, 99, 0.4);
        }
        
        .pagination .page-item.disabled .page-link {
            background-color: rgba(255, 255, 255, 0.5);
            border-color: rgba(108, 117, 125, 0.2);
            color: #6c757d;
            opacity: 0.6;
        }
        
        .pagination .page-item.disabled .page-link:hover {
            background-color: rgba(255, 255, 255, 0.5);
            transform: none;
            box-shadow: none;
        }

</style>
</head>
<body>
<!-- Enhanced Navbar -->
<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
        <a class="navbar-brand" href="#">
            <img src="img/LogoBG1.png" alt="Thennilavu Logo" width="240" height="140">
        </a>     
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="members.php">Membership</a></li>
                <li class="nav-item"><a class="nav-link" href="mem.php">Members</a></li>
                <li class="nav-item"><a class="nav-link" href="package.php">Packages</a></li>
                <li class="nav-item"><a class="nav-link" href="contact.php">Contact Us</a></li>
                <li class="nav-item"><a class="nav-link active" href="story.php">Stories</a></li>
            </ul>
            <div class="ms-3 d-flex gap-2">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <!-- Logged in user buttons -->
                        <a href="profile.php" class="btn custom-btn dashboard-btn">
                            <span class="btn-text">Dashboard</span>
                        </a>
                        <a href="logout.php" class="btn custom-btn logout-btn">
                            <span class="btn-text">Log Out</span>
                        </a>
                    <?php else: ?>
                        <!-- Not logged in - show login and register buttons with existing styles -->
                        <a href="login.php" class="btn custom-btn dashboard-btn">
                            <span class="btn-text">Login</span>
                        </a>
                        <a href="signup.php" class="btn custom-btn logout-btn">
                            <span class="btn-text">Register</span>
                        </a>
                    <?php endif; ?>
                </div>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <h1 class="display-4 fw-bold heading1">Success Stories</h1>
        <p class="lead">Real stories of love and companionship that began with Thennilavu Matrimony</p>
    </div>
</section>

<!-- Success Stories Section -->
<section id="stories" class="py-5">
    <div class="container">
        <!-- Section title intentionally removed per request -->
        <div class="row g-4">
            <?php if ($stories && $stories->num_rows > 0): ?>
                <?php 
                while ($story = $stories->fetch_assoc()): 
                    $imgPath = 'img/default.jpg';
                    if (!empty($story['author_photo'])) {
                        $photo = $story['author_photo'];
                        $imgPath = 'https://administration.thennilavu.lk/' . $photo;
                    }
                ?>
                    <div class="col-lg-4 col-md-6" data-aos="fade-up">
                        <div class="story-card">
                            <div class="story-image position-relative">
                                <img src="<?= htmlspecialchars($imgPath) ?>" alt="<?= htmlspecialchars($story['title']) ?>">
                            </div>
                            <div class="story-content">
                                <div class="story-names">
                                    <h3><?= htmlspecialchars(string: $story['author_name']) ?></h3>
                                    <i class="fas fa-heart"></i>
                                </div>
                                <div class="story-date-text">
                                    <?= !empty($story['publish_date']) ? date('F Y', strtotime($story['publish_date'])) : 'December 2024' ?>
                                </div>
                                <p class="story-text" style="font-weight: bold; color: gray;">
                                    <?= htmlspecialchars(mb_strimwidth(strip_tags($story['title']), 0, 150, '...')) ?>
                                </p>
                                <p class="story-text">
                                    <?= htmlspecialchars(mb_strimwidth(strip_tags($story['content']), 0, 150, '...')) ?>
                                </p>
                                <button class="read-full-story" 
                                    data-story-id="<?= $story['blog_id'] ?>"
                                    data-story-title="<?= htmlspecialchars($story['title']) ?>"
                                    data-story-content="<?= htmlspecialchars($story['content']) ?>"
                                    data-story-author="<?= htmlspecialchars($story['author_name']) ?>"
                                    data-story-date="<?= !empty($story['publish_date']) ? date('F j, Y', strtotime($story['publish_date'])) : 'December 2024' ?>"
                                    data-story-image="<?= htmlspecialchars($imgPath) ?>"
                                    data-story-category="<?= htmlspecialchars($story['category'] ?? '') ?>">
                                    Read Full Story
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <h4>No happy stories available at the moment</h4>
                        <p>Check back soon for inspiring love stories from our community.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="row mt-5">
            <div class="col-12">
                <nav aria-label="Success Stories Pagination">
                    <ul class="pagination justify-content-center">
                        <!-- Previous Button -->
                        <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo ($current_page <= 1) ? '#' : '?page=' . ($current_page - 1); ?>" 
                               <?php echo ($current_page <= 1) ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>
                                <i class="bi bi-chevron-left me-1"></i>Previous
                            </a>
                        </li>
                        
                        <?php
                        // Show page numbers
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        // Always show first page
                        if ($start_page > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                            if ($start_page > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }
                        
                        // Show page range
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            $active = ($i == $current_page) ? 'active' : '';
                            echo '<li class="page-item ' . $active . '"><a class="page-link" href="?page=' . $i . '">' . $i . '</a></li>';
                        }
                        
                        // Always show last page
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '">' . $total_pages . '</a></li>';
                        }
                        ?>
                        
                        <!-- Next Button -->
                        <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo ($current_page >= $total_pages) ? '#' : '?page=' . ($current_page + 1); ?>"
                               <?php echo ($current_page >= $total_pages) ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>
                                Next<i class="bi bi-chevron-right ms-1"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                
                <!-- Pagination Info -->
                <div class="text-center mt-3">
                    <small style="color: white; ">
                        Showing <?php echo (($current_page - 1) * $stories_per_page) + 1; ?> to 
                        <?php echo min($current_page * $stories_per_page, $total_stories); ?> of 
                        <?php echo $total_stories; ?> success stories
                    </small>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Story Modal -->
<div id="storyModal" class="story-modal">
    <div class="story-modal-overlay"></div>
    <div class="story-modal-content">
        <button class="story-modal-close">&times;</button>
        <div class="story-modal-body">
            <div class="story-modal-image">
                <img id="modalStoryImage" src="" alt="Story Image">
            </div>
            <div class="story-modal-details">
                <div class="story-modal-header">
                    <h2 id="modalStoryTitle"></h2>
                    <div class="story-modal-meta">
                        <span class="story-modal-author"><i class="fas fa-user"></i> <span id="modalStoryAuthor"></span></span>
                        <span class="story-modal-date"><i class="fas fa-calendar"></i> <span id="modalStoryDate"></span></span>
                        <span class="story-modal-category"><i class="fas fa-tag"></i> <span id="modalStoryCategory"></span></span>
                    </div>
                </div>
                <div class="story-modal-text" id="modalStoryContent"></div>
            </div>
        </div>
    </div>
</div>

<!-- Review Section -->
<section class="review-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card border-0 shadow-lg">
                    <div class="card-body p-5">
                        <div class="text-center mb-5">
                            <h2 class="fw-bold" style="color: #e11d48">Share Your Experience</h2>
                            <p class="text-muted">Tell us how Thennilavu helped you find your perfect match</p>
                        </div>
                        
                        <div class="row">
                            <div class="col-lg-12">
                                <form action="" method="POST" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Your Name</label>
                                            <input type="text" name="name" class="form-control" placeholder="Full Name" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Profession</label>
                                            <input type="text" name="profession" class="form-control" placeholder="Your Profession">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Country</label>
                                        <input type="text" name="country" class="form-control" placeholder="Your Country">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Your Experience</label>
                                        <textarea name="message" class="form-control" rows="4" placeholder="Share your inspiring experience..." required></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Rating</label>
                                        <select name="rating" class="form-select" required>
                                            <option value="5">★★★★★ Excellent</option>
                                            <option value="4">★★★★ Very Good</option>
                                            <option value="3">★★★ Good</option>
                                            <option value="2">★★ Fair</option>
                                            <option value="1">★ Poor</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label class="form-label">Your Photo (Optional)</label>
                                        <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/gif">
                                        <div class="form-text">JPEG, PNG or GIF files accepted</div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary w-100 py-2">
                                        <i class="fas fa-paper-plane me-2"></i> Share Your Story
                                    </button>
                                </form>
                            </div>
                            
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Why Choose Thennilavu? Section (features) -->
<section class="features">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Why Choose Thennilavu?</h2>
            <p class="section-description">
                Your trusted partner in finding the perfect life companion with genuine profiles and proven success
            </p>
        </div>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3 class="feature-title">Verified Profiles</h3>
                <p class="feature-description">
                    Every profile is carefully verified to ensure authenticity and safety, giving you peace of mind in your search for the perfect match.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3 class="feature-title">Lakhs of Members</h3>
                <p class="feature-description">
                    Join our growing community of lakhs of genuine members actively seeking their life partner across diverse backgrounds and preferences.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h3 class="feature-title">Advanced Matching</h3>
                <p class="feature-description">
                    Our intelligent matching system uses detailed preferences to connect you with compatible partners who share your values and aspirations.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3 class="feature-title">Proven Success</h3>
                <p class="feature-description">
                    Thousands of successful marriages and countless happy couples trust Thennilavu as their preferred matrimony platform.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-bolt"></i>
                </div>
                <h3 class="feature-title">Quick Response</h3>
                <p class="feature-description">
                    Connect instantly with interested matches and get quick responses to move forward in your journey to find your soulmate.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-star"></i>
                </div>
                <h3 class="feature-title">Premium Experience</h3>
                <p class="feature-description">
                    Enjoy a seamless user experience with intuitive features, personalized recommendations, and dedicated customer support.
                </p>
            </div>
        </div>
    </div>
    
</section>

<!-- Footer -->
    <footer class="bg-dark text-white pt-5 pb-3">
        <div class="container">
            <div class="row">
                <div class="col-md-3 mb-4">
                    <h5>Thennilavu Matrimony</h5>
                    <p>Thennilavu Matrimony brings hearts together. Find your perfect match in a trusted community. Begin your beautiful journey with us.</p>
                </div>
                <div class="col-md-3 mb-4">
                    <h5>About Us</h5>
                    <p>We are dedicated to helping you find your perfect match with our trusted matrimonial services.</p>
                </div>
                <div class="col-md-3 mb-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="members.php">Membership</a></li>
                        <li><a href="contact.php">Contact</a></li>
                        <li><a href="#">Login</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4">
                    <h5>Contact</h5>
                    <p>Email: info@Thennilavu.com</p>
                    <p>Location: Sri Lanka</p>
                    <p>Feel free to reach out to us for any support, Our team is here to help you find a perfect match with a smooth experience.</p>
                </div>
            </div>
            <div class="text-center pt-3 border-top">&copy; 2025 Thennilavu Matrimony. All rights reserved.</div>
        </div>
    </footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Counter animation
function animateCounter(counter) {
    const target = +counter.getAttribute('data-count');
    let count = 0;
    const speed = 20;
    const increment = Math.ceil(target / 100);

    const updateCount = () => {
        count += increment;
        if (count >= target) {
            counter.innerText = target.toLocaleString();
        } else {
            counter.innerText = count.toLocaleString();
            requestAnimationFrame(updateCount);
        }
    };
    updateCount();
}

function initCountersOnScroll() {
    const counters = document.querySelectorAll('.counter');
    const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                obs.unobserve(entry.target);
            }
        });
    }, { threshold: 0.7 });

    counters.forEach(counter => observer.observe(counter));
}

document.addEventListener('DOMContentLoaded', initCountersOnScroll);

// Story Modal Functionality
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('storyModal');
    const modalOverlay = document.querySelector('.story-modal-overlay');
    const closeBtn = document.querySelector('.story-modal-close');
    const readFullStoryBtns = document.querySelectorAll('.read-full-story');
    
    // Open modal when clicking "Read Full Story"
    readFullStoryBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Get data from button attributes
            const title = this.getAttribute('data-story-title');
            const content = this.getAttribute('data-story-content');
            const author = this.getAttribute('data-story-author');
            const date = this.getAttribute('data-story-date');
            const image = this.getAttribute('data-story-image');
            const category = this.getAttribute('data-story-category') || 'Success Story';
            
            // Populate modal
            document.getElementById('modalStoryTitle').textContent = title;
            document.getElementById('modalStoryContent').textContent = content;
            document.getElementById('modalStoryAuthor').textContent = author;
            document.getElementById('modalStoryDate').textContent = date;
            document.getElementById('modalStoryCategory').textContent = category;
            document.getElementById('modalStoryImage').src = image;
            document.getElementById('modalStoryImage').alt = title;
            
            // Show modal
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    });
    
    // Close modal functions
    function closeModal() {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    closeBtn.addEventListener('click', closeModal);
    modalOverlay.addEventListener('click', closeModal);
    
    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('active')) {
            closeModal();
        }
    });
});

document.addEventListener("mousemove", function(e) {
    if (Math.random() < 0.97) return; // Reduce frequency
    
    const heart = document.createElement("div");
    heart.className = "heart";
    heart.innerHTML = "❤️";
    const scale = 0.7 + Math.random() * 0.8;
    const duration = 2 + Math.random() * 2;
    heart.style.left = e.pageX + "px";
    heart.style.top = e.pageY + "px";
    heart.style.transform = `translate(0, 0) scale(${scale})`;
    heart.style.animationDuration = `${duration}s`;
    document.body.appendChild(heart);
    setTimeout(() => { heart.remove(); }, duration * 1000);
});
</script>
</body>
</html>