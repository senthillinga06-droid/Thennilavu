<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$conn = new mysqli('localhost', 'thennilavu_matrimonial', 'OYVuiEKfS@FQ', 'thennilavu_thennilavu');
if ($conn->connect_error) {
  http_response_code(500);
  echo json_encode(['error' => 'DB connection failed']);
  exit;
}

$status = $_GET['status'] ?? 'active';
$sql = "SELECT package_id, name, price, duration_days, status, description, features, created_at
        FROM packages
        WHERE (? = '' OR status = ?)
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) { http_response_code(500); echo json_encode(['error' => $conn->error]); exit; }
$stmt->bind_param('ss', $status, $status);
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);

echo json_encode(['data' => $rows]);
?>

