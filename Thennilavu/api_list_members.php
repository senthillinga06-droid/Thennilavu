<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$conn = new mysqli('localhost', 'thennilavu_matrimonial', 'OYVuiEKfS@FQ', 'thennilavu_thennilavu');
if ($conn->connect_error) {
  http_response_code(500);
  echo json_encode(['error' => 'DB connection failed']);
  exit;
}

// Filters
$looking_for = $_GET['looking_for'] ?? '';
$marital_status = $_GET['marital_status'] ?? '';
$religion = $_GET['religion'] ?? '';
$country = $_GET['country'] ?? '';
$profession = $_GET['profession'] ?? '';
$city = $_GET['city'] ?? '';

$clauses = [];
$params = [];
$types = '';

if ($looking_for !== '') { $clauses[] = 'm.looking_for = ?'; $params[] = $looking_for; $types .= 's'; }
if ($marital_status !== '') { $clauses[] = 'm.marital_status = ?'; $params[] = $marital_status; $types .= 's'; }
if ($religion !== '') { $clauses[] = 'm.religion = ?'; $params[] = $religion; $types .= 's'; }
if ($country !== '') { $clauses[] = 'm.country = ?'; $params[] = $country; $types .= 's'; }
if ($profession !== '') { $clauses[] = 'm.profession LIKE ?'; $params[] = "%$profession%"; $types .= 's'; }
if ($city !== '') { $clauses[] = 'm.city LIKE ?'; $params[] = "%$city%"; $types .= 's'; }

$where = count($clauses) ? ('WHERE ' . implode(' AND ', $clauses)) : '';

$sql = "SELECT m.id, m.name, m.photo, m.looking_for, m.marital_status, m.religion, m.country, m.profession, m.city,
               TIMESTAMPDIFF(YEAR, m.dob, CURDATE()) AS age, m.language
        FROM members m $where
        ORDER BY m.created_at DESC
        LIMIT 100";

$stmt = $conn->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['error' => $conn->error]);
  exit;
}
if ($types !== '') {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);

echo json_encode(['data' => $rows]);
?>

