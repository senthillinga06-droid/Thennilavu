<?php
// API to fetch additional photos for a member
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated', 'debug' => 'No session user_id']);
    exit;
}

$conn = new mysqli('localhost', 'thennilavu_matrimonial', 'OYVuiEKfS@FQ', 'thennilavu_thennilavu');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$member_id = isset($_GET['member_id']) ? intval($_GET['member_id']) : 0;

if ($member_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid member ID', 'debug' => 'member_id: ' . $member_id]);
    exit;
}

// Fetch main profile photo first
$stmt = $conn->prepare("SELECT photo FROM members WHERE id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();
$member = $result->fetch_assoc();
$stmt->close();

$photos = [];

// Add main profile photo as first image
if ($member && !empty($member['photo'])) {
    $photos[] = [
        'photo_path' => $member['photo'],
        'upload_order' => 0,
        'is_main' => true
    ];
}

// Fetch additional photos for the member
$stmt = $conn->prepare("SELECT photo_path, upload_order FROM additional_photos WHERE member_id = ? ORDER BY upload_order ASC");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $row['is_main'] = false;
    $photos[] = $row;
}

$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode([
    'success' => true, 
    'photos' => $photos,
    'debug' => [
        'member_id' => $member_id,
        'total_photos' => count($photos),
        'session_user_id' => $_SESSION['user_id']
    ]
]);
?>