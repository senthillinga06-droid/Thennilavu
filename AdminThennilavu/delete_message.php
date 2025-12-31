<?php
session_start();
if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

$host = "localhost";
$user = "thennilavu_matrimonial";
$pass = "OYVuiEKfS@FQ";
$db = "thennilavu_thennilavu";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('DB connection failed');
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header('Location: user-message-management.php?msg=invalid');
    exit;
}

$stmt = $conn->prepare('DELETE FROM messages WHERE message_id=? LIMIT 1');
if (!$stmt) {
    header('Location: user-message-management.php?msg=prep_failed');
    exit;
}
$stmt->bind_param('i', $id);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

$param = $affected === 1 ? 'deleted' : 'not_found';
header('Location: user-message-management.php?msg=' . $param);
exit;
