<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$conn = new mysqli('localhost', 'thennilavu_matrimonial', 'OYVuiEKfS@FQ', 'thennilavu_thennilavu');
if ($conn->connect_error) {
  http_response_code(500);
  echo json_encode(['error' => 'DB connection failed']);
  exit;
}

$member_id = (int)($_GET['member_id'] ?? 0);
if ($member_id <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'member_id required']);
  exit;
}

// Basic member
$sql_m = "SELECT id, name, photo, looking_for, dob, religion, gender, marital_status, language, profession, country, phone, present_address, city, zip, permanent_address, permanent_city FROM members WHERE id = ?";
$stmt_m = $conn->prepare($sql_m);
if (!$stmt_m) { http_response_code(500); echo json_encode(['error' => $conn->error]); exit; }
$stmt_m->bind_param('i', $member_id);
$stmt_m->execute();
$member = $stmt_m->get_result()->fetch_assoc();
if (!$member) { http_response_code(404); echo json_encode(['error' => 'not found']); exit; }

// Physical
$sql_p = "SELECT complexion, height_cm, weight_kg, blood_group, eye_color, hair_color, disability FROM physical_info WHERE member_id = ? ORDER BY id DESC LIMIT 1";
$stmt_p = $conn->prepare($sql_p);
$stmt_p->bind_param('i', $member_id);
$stmt_p->execute();
$physical = $stmt_p->get_result()->fetch_assoc();

// Education (all)
$sql_e = "SELECT level, school_or_institute, stream_or_degree, field, reg_number, start_year, end_year FROM education WHERE member_id = ? ORDER BY id DESC";
$stmt_e = $conn->prepare($sql_e);
$stmt_e->bind_param('i', $member_id);
$stmt_e->execute();
$edu_res = $stmt_e->get_result();
$education = [];
while ($row = $edu_res->fetch_assoc()) { $education[] = $row; }

// Family
$sql_f = "SELECT father_name, father_profession, father_contact, mother_name, mother_profession, mother_contact, brothers_count, sisters_count FROM family WHERE member_id = ? ORDER BY id DESC LIMIT 1";
$stmt_f = $conn->prepare($sql_f);
$stmt_f->bind_param('i', $member_id);
$stmt_f->execute();
$family = $stmt_f->get_result()->fetch_assoc();

// Partner
$sql_pe = "SELECT preferred_country, min_age, max_age, min_height, max_height, marital_status, religion, smoking, drinking FROM partner_expectations WHERE member_id = ? ORDER BY id DESC LIMIT 1";
$stmt_pe = $conn->prepare($sql_pe);
$stmt_pe->bind_param('i', $member_id);
$stmt_pe->execute();
$partner = $stmt_pe->get_result()->fetch_assoc();

// Horoscope
$sql_h = "SELECT birth_date, birth_time, zodiac, nakshatra, karmic_debt, planet_image, navamsha_image FROM horoscope WHERE member_id = ? ORDER BY id DESC LIMIT 1";
$stmt_h = $conn->prepare($sql_h);
$stmt_h->bind_param('i', $member_id);
$stmt_h->execute();
$horoscope = $stmt_h->get_result()->fetch_assoc();

echo json_encode([
  'member' => $member,
  'physical' => $physical,
  'education' => $education,
  'family' => $family,
  'partner' => $partner,
  'horoscope' => $horoscope
]);
?>

