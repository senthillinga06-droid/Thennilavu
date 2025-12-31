<?php
session_start();
header('Content-Type: application/json');

// Database connection
$host = "localhost";
$user = "thennilavu_matrimonial";
$pass = "OYVuiEKfS@FQ";
$db = "thennilavu_thennilavu";

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Check if user is logged in and is admin
if (!isset($_SESSION['isLoggedIn']) || $_SESSION['userType'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $author_id = (int)($_POST['author_id'] ?? 1); // Default to 1 if not provided
    $category = trim($_POST['category'] ?? '');
    $status = trim($_POST['status'] ?? 'draft');
    $publish_date = $_POST['publishDate'] ?? date('Y-m-d');
    
    // Validation
    if (empty($title)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Blog title is required']);
        exit;
    }
    
    if (empty($content)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Blog content is required']);
        exit;
    }
    
    // Prepare and execute the insert statement
    $sql = "INSERT INTO blog (title, content, author_id, category, status, publish_date, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("ssisss", $title, $content, $author_id, $category, $status, $publish_date);
    
    if ($stmt->execute()) {
        $blog_id = $conn->insert_id;
        echo json_encode([
            'success' => true, 
            'message' => 'Blog created successfully',
            'blog_id' => $blog_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create blog: ' . $stmt->error]);
    }
    
    $stmt->close();
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

$conn->close();
?> 