<?php
session_start();
require_once 'verify_session.php';  // Add this line
// Enable runtime error reporting temporarily to debug HTTP 500
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

 
$name = $_SESSION['name'];
$type = ucfirst($_SESSION['role']); // Admin/Staff

// Database connectionPresent Address
$host = "localhost";
$user = "thennilavu_matrimonial";
$pass = "OYVuiEKfS@FQ";
$db = "thennilavu_thennilavu";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// AJAX: robust delete handler — placed early so it catches delete requests before other handlers
if (isset($_POST['action']) && $_POST['action'] === 'delete_member') {
  header('Content-Type: application/json');
  $memberId = intval($_POST['member_id'] ?? 0);
  if ($memberId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid member id']);
    exit;
  }

  try {
    // fetch linked user_id if any
    $userId = null;
    if ($stmt = $conn->prepare("SELECT user_id FROM members WHERE id=? LIMIT 1")) {
      $stmt->bind_param('i', $memberId);
      $stmt->execute();
      $stmt->bind_result($uid);
      if ($stmt->fetch()) { $userId = $uid; }
      $stmt->close();
    }

    $conn->begin_transaction();

    // delete rows that reference member_id
    $memberTables = ['physical_info','family','partner_expectations','horoscope','education'];
    foreach ($memberTables as $t) {
      if ($stmt = $conn->prepare("DELETE FROM {$t} WHERE member_id=?")) {
        $stmt->bind_param('i', $memberId);
        $stmt->execute();
        $stmt->close();
      }
    }

    // If we have a linked user, remove userpackage rows by user_id
    if ($userId) {
      if ($stmt = $conn->prepare("DELETE FROM userpackage WHERE user_id=?")) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
      }
    }

    // finally delete the member row
    if ($stmt = $conn->prepare("DELETE FROM members WHERE id=? LIMIT 1")) {
      $stmt->bind_param('i', $memberId);
      $stmt->execute();
      $affected = $stmt->affected_rows;
      $stmt->close();
    } else {
      throw new Exception('Failed to prepare members delete');
    }

    $conn->commit();
    echo json_encode(['success' => ($affected > 0), 'member_id' => $memberId, 'message' => ($affected > 0) ? 'Member deleted' : 'Member not found']);
  } catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
  }
  exit;
}

// AJAX: Get branches for dropdown
if (isset($_GET['action']) && $_GET['action'] === 'get_branches') {
  header('Content-Type: application/json');
  $result = $conn->query("SELECT id, branch_name FROM branches WHERE status = 'active' ORDER BY branch_name ASC");
  $branches = [];
  if ($result) {
    while ($row = $result->fetch_assoc()) {
      $branches[] = $row;
    }
  }
  echo json_encode(['success' => true, 'branches' => $branches]);
  exit;
}

// Helper: fetch all rows from a prepared statement in environments
// where mysqli_stmt::get_result() may not be available (mysqlnd missing).
function fetch_stmt_all($stmt) {
  $rows = [];
  if (method_exists($stmt, 'get_result')) {
    $res = $stmt->get_result();
    if ($res) {
      while ($r = $res->fetch_assoc()) $rows[] = $r;
    }
    return $rows;
  }

  $meta = $stmt->result_metadata();
  if (!$meta) return $rows;
  $fields = [];
  $row = [];
  while ($field = $meta->fetch_field()) {
    $fields[] = $field->name;
    $row[$field->name] = null;
  }
  $meta->free();

  $bindVars = [];
  foreach ($fields as $f) $bindVars[] = &$row[$f];
  call_user_func_array([$stmt, 'bind_result'], $bindVars);

  while ($stmt->fetch()) {
    $copy = [];
    foreach ($fields as $f) $copy[$f] = $row[$f];
    $rows[] = $copy;
  }
  return $rows;
}

// Server-side: sanitize string 'null' values in the members array so json_encode doesn't emit the literal 'null' strings
if (!function_exists('sanitize_null_strings_recursive')) {
function sanitize_null_strings_recursive(&$arr) {
  if (!is_array($arr)) return;
  foreach ($arr as $k => &$v) {
    if (is_array($v)) sanitize_null_strings_recursive($v);
    else if (is_string($v) && strtolower(trim($v)) === 'null') $v = null;
  }
  unset($v);
}
}

// Fetch all members with their details including package information
$sql = "SELECT m.*, p.height_cm, p.weight_kg, p.complexion, p.blood_group,
        COALESCE(up.status, 'Free User') as package_name,
        CASE 
            WHEN up.requestPackage = 'accept' AND up.end_date > NOW() THEN 'active'
            WHEN up.requestPackage = 'accept' AND up.end_date <= NOW() THEN 'expired'
            WHEN up.requestPackage = 'pending' THEN 'pending'
            WHEN up.status IS NULL THEN 'free'
            ELSE 'free'
        END as package_status,
        up.end_date,
        up.requestPackage,
        CASE 
            WHEN u.role = 'block' THEN 'Deactivated'
            ELSE 'Active'
        END as profile_status,
        b.branch_name
        FROM members m 
        LEFT JOIN physical_info p ON m.id = p.member_id 
        LEFT JOIN users u ON m.user_id = u.id
        LEFT JOIN userpackage up ON u.id = up.user_id AND up.end_date = (
          SELECT MAX(end_date) 
          FROM userpackage 
          WHERE user_id = u.id
        )
        LEFT JOIN branches b ON m.branch_id = b.id
        ORDER BY m.created_at DESC";
$result = $conn->query($sql);
$members = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Debug: Log photo data for first few members
        if (count($members) < 3) {
            error_log("Member photo debug - ID: " . $row['id'] . ", Name: " . $row['name'] . ", Photo: " . ($row['photo'] ?? 'NULL'));
        }
        $members[] = $row;
    }
}

// Enrich each member with related data from other tables: family, education, physical_info, horoscope, partner_expectations, users
foreach ($members as $idx => $m) {
  $mid = intval($m['id']);

  // Family (single row)
  $members[$idx]['family'] = null;
  if ($stmt = $conn->prepare("SELECT father_name, father_profession, father_contact, mother_name, mother_profession, mother_contact, brothers_count, sisters_count FROM family WHERE member_id = ? LIMIT 1")) {
    $stmt->bind_param('i', $mid);
    $stmt->execute();
      $rows = fetch_stmt_all($stmt);
      if (count($rows) > 0) {
        $members[$idx]['family'] = $rows[0];
      }
    $stmt->close();
  }

  // Education (multiple rows)
  $members[$idx]['education'] = [];
  if ($stmt = $conn->prepare("SELECT level, school_or_institute, stream_or_degree, field, reg_number, start_year, end_year, result FROM education WHERE member_id = ? ORDER BY start_year DESC")) {
    $stmt->bind_param('i', $mid);
    $stmt->execute();
      $rows = fetch_stmt_all($stmt);
      foreach ($rows as $er) {
        $members[$idx]['education'][] = $er;
      }
    $stmt->close();
  }

  // Physical info (may already be partially joined). Ensure eye_color, hair_color, disability and other fields are present.
  $needsPhysical = false;
  $checkFields = ['complexion','height_cm','weight_kg','blood_group','eye_color','hair_color','disability'];
  foreach ($checkFields as $f) {
    if (!isset($members[$idx][$f]) || $members[$idx][$f] === null) { $needsPhysical = true; break; }
  }

  if ($needsPhysical) {
    if ($stmt = $conn->prepare("SELECT complexion, height_cm, weight_kg, blood_group, eye_color, hair_color, disability FROM physical_info WHERE member_id = ? LIMIT 1")) {
      $stmt->bind_param('i', $mid);
      $stmt->execute();
      $rows = fetch_stmt_all($stmt);
      if (count($rows) > 0) {
        $p = $rows[0];
        // merge fetched physical info into member record (preserve existing values)
        foreach ($p as $k => $v) {
          if (!isset($members[$idx][$k]) || $members[$idx][$k] === null || $members[$idx][$k] === '') {
            $members[$idx][$k] = $v;
          }
        }
      }
      $stmt->close();
    }
  }

  // Horoscope
  $members[$idx]['horoscope'] = null;
  if ($stmt = $conn->prepare("SELECT birth_date, birth_time, zodiac, nakshatra, karmic_debt, planet_image, navamsha_image FROM horoscope WHERE member_id = ? LIMIT 1")) {
    $stmt->bind_param('i', $mid);
    $stmt->execute();
      $rows = fetch_stmt_all($stmt);
      if (count($rows) > 0) {
        $members[$idx]['horoscope'] = $rows[0];
      }
    $stmt->close();
  }

  // Partner expectations
  $members[$idx]['partner'] = null;
  if ($stmt = $conn->prepare("SELECT preferred_country, min_age, max_age, min_height, max_height, marital_status, religion, smoking, drinking FROM partner_expectations WHERE member_id = ? LIMIT 1")) {
    $stmt->bind_param('i', $mid);
    $stmt->execute();
      $rows = fetch_stmt_all($stmt);
      if (count($rows) > 0) {
        $members[$idx]['partner'] = $rows[0];
      }
    $stmt->close();
  }

  // User info: last_login and role (if linked)
  $members[$idx]['last_login'] = $members[$idx]['last_login'] ?? null;
  if (!empty($members[$idx]['user_id'])) {
    if ($stmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1")) {
      $uid = intval($members[$idx]['user_id']);
      $stmt->bind_param('i', $uid);
      $stmt->execute();
      $rows = fetch_stmt_all($stmt);
      if (count($rows) > 0) {
        $u = $rows[0];
        $members[$idx]['user_role'] = $u['role'];
      }
      $stmt->close();
    }
  }

  // Additional Photos
  $members[$idx]['additional_photos'] = [];
  if ($stmt = $conn->prepare("SELECT id, photo_path, upload_order, uploaded_at FROM additional_photos WHERE member_id = ? ORDER BY upload_order ASC, uploaded_at ASC")) {
    $stmt->bind_param('i', $mid);
    $stmt->execute();
    $rows = fetch_stmt_all($stmt);
    foreach ($rows as $photo) {
      $members[$idx]['additional_photos'][] = $photo;
    }
    $stmt->close();
  }
}

// Debug output - remove this after testing
// Normalize any string 'null' values to NULL before casting to JSON
foreach ($members as &$m) sanitize_null_strings_recursive($m);
unset($m);
if (isset($_GET['debug_photos']) && count($members) > 0) {
    echo "<pre>Debug Photo Data:\n";
    for ($i = 0; $i < min(3, count($members)); $i++) {
        echo "Member " . ($i+1) . ":\n";
        echo "  ID: " . $members[$i]['id'] . "\n";
        echo "  Name: " . $members[$i]['name'] . "\n";
        echo "  Photo field: '" . ($members[$i]['photo'] ?? 'NULL') . "'\n";
        echo "  Photo length: " . strlen($members[$i]['photo'] ?? '') . "\n\n";
    }
    echo "</pre>";
    exit;
}

// Add or Edit member form submit
if (isset($_POST['add_member'])) {
    $edit_id = !empty($_POST['edit_member_id']) ? intval($_POST['edit_member_id']) : 0;

    $name              = $_POST['name'] ?? '';
    $gender            = $_POST['gender'] ?? '';
    $dob               = $_POST['dob'] ?? '';
    $religion          = $_POST['religion'] ?? '';
    $marital_status    = $_POST['marital_status'] ?? '';
    $language          = $_POST['language'] ?? '';
    $profession        = $_POST['profession'] ?? '';
    $country           = $_POST['country'] ?? '';
    $phone             = $_POST['phone'] ?? '';
    $smoking           = $_POST['smoking'] ?? '';
    $drinking          = $_POST['drinking'] ?? '';
    $present_address   = $_POST['present_address'] ?? '';
    $city              = $_POST['city'] ?? '';
    $zip               = $_POST['zip'] ?? '';
    $permanent_address = $_POST['permanent_address'] ?? '';
    $permanent_city    = $_POST['permanent_city'] ?? '';
    $looking_for       = isset($_POST['looking_for']) ? $_POST['looking_for'] : '';
    $branch_id         = !empty($_POST['branch_id']) ? intval($_POST['branch_id']) : null;

    // Photo upload (for both add and edit) - save files under the main site's public `uploads/` folder
    $photoName = "";
    // Public base URL to reference uploaded files on the main domain
    $publicBaseUrl = 'https://thennilavu.lk/uploads/';
    // Preferred filesystem path for the main site's document root (cPanel example you provided)
    $preferredFsPath = '/home10/thennilavu/public_html/uploads/';
    
    $baseup ='uploads/';

    // Decide final target directory: prefer the known public_html path if it exists (or can be created),
    // otherwise fall back to the current vhost document root `/uploads/`.
    if (is_dir($preferredFsPath) || @mkdir($preferredFsPath, 0755, true)) {
      $targetDir = rtrim($preferredFsPath, '/\\') . '/';
    } else {
      $targetDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/uploads/';
      // try to ensure it exists
      if (!is_dir($targetDir)) { @mkdir($targetDir, 0755, true); }
    }
    if (!is_dir($targetDir)) {
        if (!@mkdir($targetDir, 0755, true)) {
            error_log("Failed to create upload directory: $targetDir");
        }
    }
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
      // sanitize original filename and prepend timestamp to avoid collisions
      $orig = basename($_FILES['photo']['name']);
      $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $orig);
      $uploadedBase = time() . "_" . $safe;
      $targetFile = $targetDir . $uploadedBase;
      $moved = move_uploaded_file($_FILES['photo']['tmp_name'], $targetFile);
      if ($moved) {
        // store full public URL so admin (on subdomain) and public site both can render directly
        $photoName = $baseup . $uploadedBase;
      } else {
        error_log("Failed to move uploaded photo to $targetFile");
        $photoName = '';
      }
    }
    
    // Handle additional photos upload
    if (isset($_FILES['additional_photos']) && is_array($_FILES['additional_photos']['name'])) {
      for ($i = 0; $i < count($_FILES['additional_photos']['name']); $i++) {
        if (!empty($_FILES['additional_photos']['name'][$i]) && $_FILES['additional_photos']['error'][$i] == 0) {
          $orig = basename($_FILES['additional_photos']['name'][$i]);
          $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $orig);
          $uploadedBase = time() . "_additional_" . $i . "_" . $safe;
          $targetFile = $targetDir . $uploadedBase;
          if (move_uploaded_file($_FILES['additional_photos']['tmp_name'][$i], $targetFile)) {
            $additionalPhotos[] = $baseup . $uploadedBase;
          } else {
            error_log("Failed to move additional photo " . $i . " to $targetFile");
          }
        }
      }
    }

    if ($edit_id > 0) {
      // --- UPDATE existing member ---
      // If no new photo uploaded, preserve existing (and normalize stored value)
      if ($photoName === '') {
        if ($stmt = $conn->prepare("SELECT photo FROM members WHERE id=? LIMIT 1")) {
          $stmt->bind_param('i', $edit_id);
          $stmt->execute();
          $rows = fetch_stmt_all($stmt);
          if (count($rows) > 0) {
            $existingPhoto = $rows[0]['photo'] ?? '';
            // Normalize possible stored formats. Prefer preserving full URLs so
            // admin (subdomain) and public site render correctly.
            if (stripos($existingPhoto, 'http://') === 0 || stripos($existingPhoto, 'https://') === 0) {
              // keep full URL as-is
              $photoName = $existingPhoto;
            } else {
              // Strip any leading ../ or ./ or / segments and keep as relative path
              $photoName = preg_replace('#^(?:\.\./|\./|/)+#', '', $existingPhoto);
              // If the stored value looks like 'uploads/...' convert to full public URL
              if (strpos($photoName, 'uploads/') === 0) {
                $photoName = $baseup . ltrim(str_replace('uploads/', '', $photoName), '/');
              }
            }
          }
          $stmt->close();
        }
      }

      $sql = "UPDATE members SET name=?, photo=?, gender=?, dob=?, religion=?, marital_status=?, language=?, profession=?, country=?, phone=?, smoking=?, drinking=?, present_address=?, city=?, zip=?, permanent_address=?, permanent_city=?, looking_for=?, branch_id=? WHERE id=? LIMIT 1";
      if ($stmt = $conn->prepare($sql)) {
        $types = str_repeat('s', 18) . 'ii';
        $stmt->bind_param($types,
          $name, $photoName, $gender, $dob, $religion, $marital_status,
          $language, $profession, $country, $phone, $smoking, $drinking,
          $present_address, $city, $zip, $permanent_address, $permanent_city,
          $looking_for, $branch_id, $edit_id
        );
        if ($stmt->execute()) {
          $member_id = $edit_id;
        } else {
          echo "Error updating member: " . $stmt->error; exit;
        }
        $stmt->close();
      }

      // --- Physical info ---
      $complexion = $_POST['complexion'] ?? null;
      $height = $_POST['height'] ?? null;
      $weight = $_POST['weight'] ?? null;
      $blood_group = $_POST['blood_group'] ?? null;
      $eye_color = $_POST['eye_color'] ?? null;
      $hair_color = $_POST['hair_color'] ?? null;
      $disability = $_POST['disability'] ?? null;

      if ($stmt = $conn->prepare("SELECT id FROM physical_info WHERE member_id=? LIMIT 1")) {
        $stmt->bind_param('i', $member_id);
        $stmt->execute();
        $rows = fetch_stmt_all($stmt);
        $stmt->close();
        if (count($rows) > 0) {
          // update
          if ($stmt2 = $conn->prepare("UPDATE physical_info SET complexion=?, height_cm=?, weight_kg=?, blood_group=?, eye_color=?, hair_color=?, disability=? WHERE member_id=?")) {
            $stmt2->bind_param('sddssssi', $complexion, $height, $weight, $blood_group, $eye_color, $hair_color, $disability, $member_id);
            $stmt2->execute(); $stmt2->close();
          }
        } else {
          // insert
          if ($stmt2 = $conn->prepare("INSERT INTO physical_info (member_id, complexion, height_cm, weight_kg, blood_group, eye_color, hair_color, disability) VALUES (?, ?, ?, ?, ?, ?, ?, ?)") ) {
            $stmt2->bind_param('isddssss', $member_id, $complexion, $height, $weight, $blood_group, $eye_color, $hair_color, $disability);
            $stmt2->execute(); $stmt2->close();
          }
        }
      }

      // --- Family ---
      $father_name = $_POST['father_name'] ?? '';
      $father_profession = $_POST['father_profession'] ?? '';
      $father_contact = $_POST['father_contact'] ?? '';
      $mother_name = $_POST['mother_name'] ?? '';
      $mother_profession = $_POST['mother_profession'] ?? '';
      $mother_contact = $_POST['mother_contact'] ?? '';
      $brothers = isset($_POST['brothers']) ? (int)$_POST['brothers'] : 0;
      $sisters = isset($_POST['sisters']) ? (int)$_POST['sisters'] : 0;

      if ($stmt = $conn->prepare("SELECT id FROM family WHERE member_id=? LIMIT 1")) {
        $stmt->bind_param('i', $member_id);
        $stmt->execute(); $rows = fetch_stmt_all($stmt); $stmt->close();
        if (count($rows) > 0) {
          if ($stmt4 = $conn->prepare("UPDATE family SET father_name=?, father_profession=?, father_contact=?, mother_name=?, mother_profession=?, mother_contact=?, brothers_count=?, sisters_count=? WHERE member_id=?")) {
            $stmt4->bind_param('ssssssiii', $father_name, $father_profession, $father_contact, $mother_name, $mother_profession, $mother_contact, $brothers, $sisters, $member_id);
            $stmt4->execute(); $stmt4->close();
          }
        } else {
          if ($stmt4 = $conn->prepare("INSERT INTO family (member_id, father_name, father_profession, father_contact, mother_name, mother_profession, mother_contact, brothers_count, sisters_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)") ) {
            $stmt4->bind_param('issssssii', $member_id, $father_name, $father_profession, $father_contact, $mother_name, $mother_profession, $mother_contact, $brothers, $sisters);
            $stmt4->execute(); $stmt4->close();
          }
        }
      }

      // --- Partner expectations ---
      $partner_country = $_POST['partner_country'] ?? '';
      $min_age = isset($_POST['min_age']) ? (int)$_POST['min_age'] : null;
      $max_age = isset($_POST['max_age']) ? (int)$_POST['max_age'] : null;
      $min_height = isset($_POST['min_height']) ? (int)$_POST['min_height'] : null;
      $max_height = isset($_POST['max_height']) ? (int)$_POST['max_height'] : null;
      $partner_marital_status = isset($_POST['partner_marital_status']) ? trim($_POST['partner_marital_status']) : '';
      // Normalize partner_marital_status to one of enum values (case-insensitive), otherwise set to empty
      $allowed_partner_marital = ['Never Married','Divorced','Widowed','Separated'];
      if ($partner_marital_status !== '') {
        $found = null;
        foreach ($allowed_partner_marital as $a) {
          if (strcasecmp($a, $partner_marital_status) === 0) { $found = $a; break; }
        }
        if (!$found) {
          // Try partial match: e.g., 'never' => 'Never Married'
          foreach ($allowed_partner_marital as $a) {
            if (stripos($a, $partner_marital_status) !== false) { $found = $a; break; }
          }
        }
        if ($found) $partner_marital_status = $found; else $partner_marital_status = '';
      }
      $partner_religion = $_POST['partner_religion'] ?? '';
      $partner_smoking = $_POST['partner_smoking'] ?? '';
      $partner_drinking = $_POST['partner_drinking'] ?? '';

      error_log('DEBUG: partner_marital_status on edit: ' . var_export($partner_marital_status, true));
      if ($stmt = $conn->prepare("SELECT id FROM partner_expectations WHERE member_id=? LIMIT 1")) {
        $stmt->bind_param('i', $member_id);
        $stmt->execute(); $rows = fetch_stmt_all($stmt); $stmt->close();
        if (count($rows) > 0) {
          // Build dynamic update only for non-empty values to avoid overwriting fields with empty values
          $updateParts = array();
          $params = array();
          $typesStr = '';
          if ($partner_country !== '') { $updateParts[] = 'preferred_country=?'; $typesStr .= 's'; $params[] = $partner_country; }
          if ($min_age !== null) { $updateParts[] = 'min_age=?'; $typesStr .= 'i'; $params[] = $min_age; }
          if ($max_age !== null) { $updateParts[] = 'max_age=?'; $typesStr .= 'i'; $params[] = $max_age; }
          if ($min_height !== null) { $updateParts[] = 'min_height=?'; $typesStr .= 'i'; $params[] = $min_height; }
          if ($max_height !== null) { $updateParts[] = 'max_height=?'; $typesStr .= 'i'; $params[] = $max_height; }
          if ($partner_marital_status !== '') { $updateParts[] = 'marital_status=?'; $typesStr .= 's'; $params[] = $partner_marital_status; }
          if ($partner_religion !== '') { $updateParts[] = 'religion=?'; $typesStr .= 's'; $params[] = $partner_religion; }
          if ($partner_smoking !== '') { $updateParts[] = 'smoking=?'; $typesStr .= 's'; $params[] = $partner_smoking; }
          if ($partner_drinking !== '') { $updateParts[] = 'drinking=?'; $typesStr .= 's'; $params[] = $partner_drinking; }

          if (count($updateParts) > 0) {
            $sqlUpdate = "UPDATE partner_expectations SET " . implode(', ', $updateParts) . " WHERE member_id=?";
            $stmt5 = $conn->prepare($sqlUpdate);
            if ($stmt5) {
              // bind params dynamically
              $typesStr .= 'i'; // for member_id
              $params[] = $member_id;
              $bindParams = array_merge(array($typesStr), $params);
              $tmp = array();
              foreach ($bindParams as $k => $v) $tmp[$k] = &$bindParams[$k];
              call_user_func_array(array($stmt5, 'bind_param'), $tmp);
              if (!$stmt5->execute()) {
                error_log('Partner dynamic update error: ' . $stmt5->error);
              } else {
                error_log('Partner dynamic update affected rows: ' . $stmt5->affected_rows . ' (sql: ' . $sqlUpdate . ', params: ' . json_encode($params) . ')');
              }
              $stmt5->close();
            } else {
              error_log('Partner dynamic update prepare failed: ' . $conn->error . ' (sql: ' . $sqlUpdate . ')');
            }
          } else {
            error_log('Partner dynamic update: no fields to update for member ' . $member_id);
          }
        } else {
          error_log('DEBUG: partner_marital_status on insert: ' . var_export($partner_marital_status, true));
          if ($stmt5 = $conn->prepare("INSERT INTO partner_expectations (member_id, preferred_country, min_age, max_age, min_height, max_height, marital_status, religion, smoking, drinking) VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?, ?, ?)") ) {
            $stmt5->bind_param('isiiiissss', $member_id, $partner_country, $min_age, $max_age, $min_height, $max_height, $partner_marital_status, $partner_religion, $partner_smoking, $partner_drinking);
            if (!$stmt5->execute()) {
              error_log('Partner insert error: ' . $stmt5->error);
            } else {
              error_log('Partner insert affected rows: ' . $stmt5->affected_rows);
            }
            $stmt5->close();
          }
        }
      }

      // --- Horoscope ---
      $birth_date = $_POST['birth_date'] ?? '';
      $birth_time = $_POST['birth_time'] ?? '';
      $zodiac = $_POST['zodiac'] ?? '';
      $nakshatra = $_POST['nakshatra'] ?? '';
      $karmic_debt = $_POST['karmic_debt'] ?? '';

      $planet_img = '';
      $navamsha_img = '';
      if (isset($_FILES['planet_image']) && $_FILES['planet_image']['error'] == 0) {
        $origP = basename($_FILES['planet_image']['name']);
        $safeP = preg_replace('/[^A-Za-z0-9._-]/', '_', $origP);
        $planetName = time() . "_planet_" . $safeP;
        $planetPath = $targetDir . $planetName;
        if (move_uploaded_file($_FILES['planet_image']['tmp_name'], $planetPath)) {
          $planet_img = $publicBaseUrl . $planetName;
        } else {
          error_log("Failed to move planet image to $planetPath");
        }
      }
      if (isset($_FILES['navamsha_image']) && $_FILES['navamsha_image']['error'] == 0) {
        $origN = basename($_FILES['navamsha_image']['name']);
        $safeN = preg_replace('/[^A-Za-z0-9._-]/', '_', $origN);
        $navName = time() . "_nav_" . $safeN;
        $navPath = $targetDir . $navName;
        if (move_uploaded_file($_FILES['navamsha_image']['tmp_name'], $navPath)) {
          $navamsha_img = $publicBaseUrl . $navName;
        } else {
          error_log("Failed to move navamsha image to $navPath");
        }
      }

      // If no new horoscope images uploaded, preserve existing ones from DB
      if ($planet_img === '' || $navamsha_img === '') {
        $existingPlanet = '';
        $existingNav = '';
        if ($stmt = $conn->prepare("SELECT planet_image, navamsha_image FROM horoscope WHERE member_id=? LIMIT 1")) {
          $stmt->bind_param('i', $member_id);
          $stmt->execute();
          $rows = fetch_stmt_all($stmt);
          if (count($rows) > 0) {
            $existingPlanet = $rows[0]['planet_image'] ?? '';
            $existingNav = $rows[0]['navamsha_image'] ?? '';
          }
          $stmt->close();
        }
        if ($planet_img === '' && $existingPlanet) $planet_img = $existingPlanet;
        if ($navamsha_img === '' && $existingNav) $navamsha_img = $existingNav;
      }

      if ($stmt = $conn->prepare("SELECT id FROM horoscope WHERE member_id=? LIMIT 1")) {
        $stmt->bind_param('i', $member_id);
        $stmt->execute(); $rows = fetch_stmt_all($stmt); $stmt->close();
        if (count($rows) > 0) {
          if ($stmt6 = $conn->prepare("UPDATE horoscope SET birth_date=?, birth_time=?, zodiac=?, nakshatra=?, karmic_debt=?, planet_image=?, navamsha_image=? WHERE member_id=?")) {
            $stmt6->bind_param('sssssssi', $birth_date, $birth_time, $zodiac, $nakshatra, $karmic_debt, $planet_img, $navamsha_img, $member_id);
            $stmt6->execute(); $stmt6->close();
          }
        } else {
          if ($stmt6 = $conn->prepare("INSERT INTO horoscope (member_id, birth_date, birth_time, zodiac, nakshatra, karmic_debt, planet_image, navamsha_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)") ) {
            $stmt6->bind_param('isssssss', $member_id, $birth_date, $birth_time, $zodiac, $nakshatra, $karmic_debt, $planet_img, $navamsha_img);
            $stmt6->execute(); $stmt6->close();
          }
        }
      }

      // --- Education: easiest is to delete existing and re-insert ---
      if ($stmt = $conn->prepare("DELETE FROM education WHERE member_id=?")) { $stmt->bind_param('i',$member_id); $stmt->execute(); $stmt->close(); }
      if (!empty($_POST['institute']) && is_array($_POST['institute'])) {
        for ($i = 0; $i < count($_POST['institute']); $i++) {
          $institute = $_POST['institute'][$i] ?? '';
          $degree = $_POST['degree'][$i] ?? '';
          $field = $_POST['field'][$i] ?? '';
          $regnum = $_POST['regnum'][$i] ?? '';
          $startyear = !empty($_POST['startyear'][$i]) ? (int)$_POST['startyear'][$i] : 0;
          $endyear = !empty($_POST['endyear'][$i]) ? (int)$_POST['endyear'][$i] : 0;
          if ($institute || $degree || $field || $regnum || $startyear || $endyear) {
            if ($stmt3 = $conn->prepare("INSERT INTO education (member_id, level, school_or_institute, stream_or_degree, field, reg_number, start_year, end_year) VALUES (?, 'Higher', ?, ?, ?, ?, ?, ?)") ) {
              $stmt3->bind_param('issssii', $member_id, $institute, $degree, $field, $regnum, $startyear, $endyear);
              $stmt3->execute(); $stmt3->close();
            }
          }
        }
      }

      // Save additional photos if any were uploaded (for edit, replace old ones)
      // Handle photo deletions from edit form
      $deletePhotoIds = isset($_POST['delete_additional_photos']) && !empty($_POST['delete_additional_photos']) 
        ? array_filter(array_map('intval', explode(',', $_POST['delete_additional_photos'])))
        : [];
      
      if (!empty($deletePhotoIds)) {
        // Delete selected photos from database
        $placeholders = implode(',', array_fill(0, count($deletePhotoIds), '?'));
        if ($stmt = $conn->prepare("DELETE FROM additional_photos WHERE id IN ($placeholders) AND member_id = ?")) {
          $params = array_merge($deletePhotoIds, [$member_id]);
          $types = str_repeat('i', count($deletePhotoIds)) . 'i';
          $tmp = array_merge(array($types), $params);
          $bindParams = array();
          foreach ($tmp as $k => $v) $bindParams[$k] = &$tmp[$k];
          call_user_func_array(array($stmt, 'bind_param'), $bindParams);
          $stmt->execute();
          $stmt->close();
        }
      }
      
      // Insert new additional photos (only if new ones were uploaded)
      if (!empty($additionalPhotos)) {
        // Get the highest existing upload_order for this member
        $maxOrder = 0;
        if ($stmt = $conn->prepare("SELECT MAX(upload_order) as max_order FROM additional_photos WHERE member_id = ?")) {
          $stmt->bind_param('i', $member_id);
          $stmt->execute();
          $result = $stmt->get_result();
          if ($row = $result->fetch_assoc()) {
            $maxOrder = ($row['max_order'] ?? 0) + 1;
          }
          $stmt->close();
        }
        
        // Insert new ones
        $uploadOrder = $maxOrder;
        foreach ($additionalPhotos as $photoPath) {
          if ($stmt = $conn->prepare("INSERT INTO additional_photos (member_id, photo_path, upload_order, uploaded_at) VALUES (?, ?, ?, NOW())")) {
            $stmt->bind_param('isi', $member_id, $photoPath, $uploadOrder);
            $stmt->execute();
            $stmt->close();
            $uploadOrder++;
          }
        }
      }

      echo "<script>alert('Member updated successfully!'); window.location.href='members.php';</script>";
      exit;

    } else {
      // --- INSERT new member (existing behavior) ---
      $sql = "INSERT INTO members 
      (name, photo, gender, dob, religion, marital_status, language, profession, country, phone, smoking, drinking, present_address, city, zip, permanent_address, permanent_city, looking_for, branch_id, created_at)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())";

      $stmt = $conn->prepare($sql);
      $stmt->bind_param("ssssssssssssssssssi",
          $name, $photoName, $gender, $dob, $religion, $marital_status,
          $language, $profession, $country, $phone, $smoking, $drinking,
          $present_address, $city, $zip, $permanent_address, $permanent_city,
          $looking_for, $branch_id
      );

      if ($stmt->execute()) {
        // Get newly inserted member id
        $member_id = $stmt->insert_id;

        // --- Physical info (optional) ---
        $complexion = $_POST['complexion'] ?? null;
        $height = $_POST['height'] ?? null;
        $weight = $_POST['weight'] ?? null;
        $blood_group = $_POST['blood_group'] ?? null;
        $eye_color = $_POST['eye_color'] ?? null;
        $hair_color = $_POST['hair_color'] ?? null;
        $disability = $_POST['disability'] ?? null;

        if ($complexion || $height || $weight || $blood_group || $eye_color || $hair_color || $disability) {
          $stmt2 = $conn->prepare("INSERT INTO physical_info (member_id, complexion, height_cm, weight_kg, blood_group, eye_color, hair_color, disability) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
          if ($stmt2) {
            $stmt2->bind_param("isddssss", $member_id, $complexion, $height, $weight, $blood_group, $eye_color, $hair_color, $disability);
            $stmt2->execute();
            $stmt2->close();
          }
        }

        // --- Family (optional) ---
        $father_name = $_POST['father_name'] ?? '';
        $father_profession = $_POST['father_profession'] ?? '';
        $father_contact = $_POST['father_contact'] ?? '';
        $mother_name = $_POST['mother_name'] ?? '';
        $mother_profession = $_POST['mother_profession'] ?? '';
        $mother_contact = $_POST['mother_contact'] ?? '';
        $brothers = isset($_POST['brothers']) ? (int)$_POST['brothers'] : 0;
        $sisters = isset($_POST['sisters']) ? (int)$_POST['sisters'] : 0;

        if ($father_name || $mother_name || $brothers || $sisters) {
          $stmt4 = $conn->prepare("INSERT INTO family (member_id, father_name, father_profession, father_contact, mother_name, mother_profession, mother_contact, brothers_count, sisters_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
          if ($stmt4) {
            $stmt4->bind_param("issssssii", $member_id, $father_name, $father_profession, $father_contact, $mother_name, $mother_profession, $mother_contact, $brothers, $sisters);
            $stmt4->execute();
            $stmt4->close();
          }
        }

        // --- Partner expectations (optional) ---
        $partner_country = $_POST['partner_country'] ?? '';
        $min_age = isset($_POST['min_age']) ? (int)$_POST['min_age'] : 0;
        $max_age = isset($_POST['max_age']) ? (int)$_POST['max_age'] : 0;
        $min_height = isset($_POST['min_height']) ? (int)$_POST['min_height'] : 0;
        $max_height = isset($_POST['max_height']) ? (int)$_POST['max_height'] : 0;
        $partner_marital_status = isset($_POST['partner_marital_status']) ? trim($_POST['partner_marital_status']) : '';
        $allowed_partner_marital = ['Never Married','Divorced','Widowed','Separated'];
        if ($partner_marital_status !== '') {
          $found = null;
          foreach ($allowed_partner_marital as $a) {
            if (strcasecmp($a, $partner_marital_status) === 0) { $found = $a; break; }
          }
          if (!$found) {
            foreach ($allowed_partner_marital as $a) {
              if (stripos($a, $partner_marital_status) !== false) { $found = $a; break; }
            }
          }
          if ($found) $partner_marital_status = $found; else $partner_marital_status = '';
        }
        $partner_religion = $_POST['partner_religion'] ?? '';
        $partner_smoking = $_POST['partner_smoking'] ?? '';
        $partner_drinking = $_POST['partner_drinking'] ?? '';

        if ($partner_country || $min_age || $max_age || $min_height || $max_height || $partner_marital_status || $partner_religion) {
          $stmt5 = $conn->prepare("INSERT INTO partner_expectations (member_id, preferred_country, min_age, max_age, min_height, max_height, marital_status, religion, smoking, drinking) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
          if ($stmt5) {
            $stmt5->bind_param("isiiiissss", $member_id, $partner_country, $min_age, $max_age, $min_height, $max_height, $partner_marital_status, $partner_religion, $partner_smoking, $partner_drinking);
            $stmt5->execute();
            $stmt5->close();
          }
        }

        // --- Horoscope (optional) ---
        $birth_date = $_POST['birth_date'] ?? '';
        $birth_time = $_POST['birth_time'] ?? '';
        $zodiac = $_POST['zodiac'] ?? '';
        $nakshatra = $_POST['nakshatra'] ?? '';
        $karmic_debt = $_POST['karmic_debt'] ?? '';

        $planet_img = '';
        $navamsha_img = '';
        if (isset($_FILES['planet_image']) && $_FILES['planet_image']['error'] == 0) {
          $origP = basename($_FILES['planet_image']['name']);
          $safeP = preg_replace('/[^A-Za-z0-9._-]/', '_', $origP);
          $planetName = time() . "_planet_" . $safeP;
          $planetPath = $targetDir . $planetName;
          if (move_uploaded_file($_FILES['planet_image']['tmp_name'], $planetPath)) {
            $planet_img = $publicBaseUrl . $planetName;
          } else {
            error_log("Failed to move uploaded planet image to $planetPath");
          }
        }
        if (isset($_FILES['navamsha_image']) && $_FILES['navamsha_image']['error'] == 0) {
          $origN = basename($_FILES['navamsha_image']['name']);
          $safeN = preg_replace('/[^A-Za-z0-9._-]/', '_', $origN);
          $navName = time() . "_nav_" . $safeN;
          $navPath = $targetDir . $navName;
          if (move_uploaded_file($_FILES['navamsha_image']['tmp_name'], $navPath)) {
            $navamsha_img = $publicBaseUrl . $navName;
          } else {
            error_log("Failed to move uploaded navamsha image to $navPath");
          }
        }

        if ($birth_date || $birth_time || $zodiac || $nakshatra || $karmic_debt || $planet_img || $navamsha_img) {
          $stmt6 = $conn->prepare("INSERT INTO horoscope (member_id, birth_date, birth_time, zodiac, nakshatra, karmic_debt, planet_image, navamsha_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
          if ($stmt6) {
            $stmt6->bind_param("isssssss", $member_id, $birth_date, $birth_time, $zodiac, $nakshatra, $karmic_debt, $planet_img, $navamsha_img);
            $stmt6->execute();
            $stmt6->close();
          }
        }

        // --- Education (optional, arrays) ---
        if (!empty($_POST['institute']) && is_array($_POST['institute'])) {
          for ($i = 0; $i < count($_POST['institute']); $i++) {
            $institute = $_POST['institute'][$i] ?? '';
            $degree = $_POST['degree'][$i] ?? '';
            $field = $_POST['field'][$i] ?? '';
            $regnum = $_POST['regnum'][$i] ?? '';
            $startyear = !empty($_POST['startyear'][$i]) ? (int)$_POST['startyear'][$i] : 0;
            $endyear = !empty($_POST['endyear'][$i]) ? (int)$_POST['endyear'][$i] : 0;

            if ($institute || $degree || $field || $regnum || $startyear || $endyear) {
              $stmt3 = $conn->prepare("INSERT INTO education (member_id, level, school_or_institute, stream_or_degree, field, reg_number, start_year, end_year) VALUES (?, 'Higher', ?, ?, ?, ?, ?, ?)");
              if ($stmt3) {
                $stmt3->bind_param("issssii", $member_id, $institute, $degree, $field, $regnum, $startyear, $endyear);
                $stmt3->execute();
                $stmt3->close();
              }
            }
          }
        }

        // After inserting related rows, redirect back to members list
        
        // Save additional photos if any were uploaded
        if (!empty($additionalPhotos)) {
          $uploadOrder = 1;
          foreach ($additionalPhotos as $photoPath) {
            if ($stmt = $conn->prepare("INSERT INTO additional_photos (member_id, photo_path, upload_order, uploaded_at) VALUES (?, ?, ?, NOW())")) {
              $stmt->bind_param('isi', $member_id, $photoPath, $uploadOrder);
              $stmt->execute();
              $stmt->close();
              $uploadOrder++;
            }
          }
        }
        
        echo "<script>alert('New member added successfully with related details!'); window.location.href='members.php';</script>";
      } else {
        echo "Error: " . $stmt->error;
      }
    }
}

    // AJAX: Block member (set users.role = 'block')
    if (isset($_POST['action']) && $_POST['action'] === 'block_member') {
      header('Content-Type: application/json');
      $memberId = intval($_POST['member_id'] ?? 0);
      if ($memberId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid member id']);
        exit;
      }
      $error = '';
      $userId = null;
      try {
        // Find linked user id via members.user_id
        if ($stmt = $conn->prepare("SELECT user_id FROM members WHERE id=? LIMIT 1")) {
          $stmt->bind_param('i', $memberId);
          $stmt->execute();
          $stmt->bind_result($uid);
          if ($stmt->fetch()) { $userId = $uid; }
          $stmt->close();
        }
        if (!$userId) {
          echo json_encode(['success'=>false,'message'=>'No linked user_id in members table']);
          exit;
        }
        // Ensure users.role enum contains block
        $roleCol = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
        if ($roleCol && $roleCol->num_rows === 1) {
          $colData = $roleCol->fetch_assoc();
          if (isset($colData['Type']) && stripos($colData['Type'], "'block'") === false) {
            // Attempt to modify enum to include block
            try { $conn->query("ALTER TABLE users MODIFY role ENUM('admin','user','block') NOT NULL DEFAULT 'user'"); } catch (Throwable $ie) { /* ignore */ }
          }
        }
        // Perform update
        $updated = false;
        if ($stmt = $conn->prepare("UPDATE users SET role='block' WHERE id=? LIMIT 1")) {
          $stmt->bind_param('i', $userId);
          $stmt->execute();
          if ($stmt->affected_rows === 1) { $updated = true; }
          $stmt->close();
        }
        echo json_encode([
          'success'=>$updated,
          'member_id'=>$memberId,
          'user_id'=>$userId,
          'message'=>$updated? 'Member blocked' : 'User role not updated (already blocked or user missing)'
        ]);
      } catch (Throwable $e) {
        echo json_encode(['success'=>false,'message'=>'Exception: '.$e->getMessage()]);
      }
      exit;
    }

    // AJAX: Unblock member (set users.role = 'user')
    if (isset($_POST['action']) && $_POST['action'] === 'unblock_member') {
      header('Content-Type: application/json');
      $memberId = intval($_POST['member_id'] ?? 0);
      if ($memberId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid member id']);
        exit;
      }
      $userId = null;
      try {
        if ($stmt = $conn->prepare("SELECT user_id FROM members WHERE id=? LIMIT 1")) {
          $stmt->bind_param('i', $memberId);
          $stmt->execute();
          $stmt->bind_result($uid);
          if ($stmt->fetch()) { $userId = $uid; }
          $stmt->close();
        }
        if (!$userId) {
          echo json_encode(['success'=>false,'message'=>'No linked user_id in members table']);
          exit;
        }

        // Perform update to set role back to 'user'
        $updated = false;
        if ($stmt = $conn->prepare("UPDATE users SET role='user' WHERE id=? LIMIT 1")) {
          $stmt->bind_param('i', $userId);
          $stmt->execute();
          if ($stmt->affected_rows === 1) { $updated = true; }
          $stmt->close();
        }

        echo json_encode([
          'success'=>$updated,
          'member_id'=>$memberId,
          'user_id'=>$userId,
          'message'=>$updated? 'Member unblocked' : 'User role not updated (may already be active)'
        ]);
      } catch (Throwable $e) {
        echo json_encode(['success'=>false,'message'=>'Exception: '.$e->getMessage()]);
      }
      exit;
    }

    // AJAX: Toggle profile hidden status
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_profile_hidden') {
      header('Content-Type: application/json');
      $memberId = intval($_POST['member_id'] ?? 0);
      if ($memberId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid member id']);
        exit;
      }
      
      try {
        // Get current profile_hidden status
        $currentStatus = 0;
        if ($stmt = $conn->prepare("SELECT profile_hidden FROM members WHERE id=? LIMIT 1")) {
          $stmt->bind_param('i', $memberId);
          $stmt->execute();
          $stmt->bind_result($hidden);
          if ($stmt->fetch()) { $currentStatus = intval($hidden); }
          $stmt->close();
        }
        
        // Toggle the status
        $newStatus = $currentStatus === 1 ? 0 : 1;
        $updated = false;
        
        if ($stmt = $conn->prepare("UPDATE members SET profile_hidden=? WHERE id=? LIMIT 1")) {
          $stmt->bind_param('ii', $newStatus, $memberId);
          $stmt->execute();
          if ($stmt->affected_rows >= 0) { $updated = true; }
          $stmt->close();
        }
        
        echo json_encode([
          'success' => $updated,
          'member_id' => $memberId,
          'profile_hidden' => $newStatus,
          'message' => $updated ? ($newStatus === 1 ? 'Profile hidden' : 'Profile visible') : 'Failed to update profile status'
        ]);
      } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
      }
      exit;
    }

    // AJAX: Delete member and related data (when no linked user exists)
    if (isset($_POST['action']) && $_POST['action'] === 'delete_member') {
      header('Content-Type: application/json');
      $memberId = intval($_POST['member_id'] ?? 0);
      if ($memberId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid member id']);
        exit;
      }

      try {
        $conn->begin_transaction();

        // Delete related rows in known tables (if present)
        $tables = ['physical_info','family','partner_expectations','horoscope','education','userpackage'];
        foreach ($tables as $t) {
          if ($stmt = $conn->prepare("DELETE FROM $t WHERE member_id=?")) {
            $stmt->bind_param('i', $memberId);
            $stmt->execute();
            $stmt->close();
          }
        }

        // Finally delete members row
        if ($stmt = $conn->prepare("DELETE FROM members WHERE id=? LIMIT 1")) {
          $stmt->bind_param('i', $memberId);
          $stmt->execute();
          $affected = $stmt->affected_rows;
          $stmt->close();
        } else {
          throw new Exception('Failed to prepare members delete');
        }

        $conn->commit();

        echo json_encode(['success' => ($affected > 0), 'member_id' => $memberId, 'message' => ($affected > 0) ? 'Member deleted' : 'Member not found']);
      } catch (Throwable $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
      }
      exit;
    }

    // AJAX: Get user status (role) for a member id
    if (isset($_GET['action']) && $_GET['action'] === 'get_user_status') {
      header('Content-Type: application/json');
      $memberId = intval($_GET['member_id'] ?? 0);
      if ($memberId <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid member id']); exit; }
      $role = null; $found = false; $userId = null;
      try {
        if ($stmt = $conn->prepare("SELECT user_id FROM members WHERE id=? LIMIT 1")) {
          $stmt->bind_param('i', $memberId);
          $stmt->execute();
          $stmt->bind_result($uid); if ($stmt->fetch()) { $userId = $uid; }
          $stmt->close();
        }
        if ($userId) {
          if ($stmt = $conn->prepare("SELECT role FROM users WHERE id=? LIMIT 1")) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->bind_result($r); if ($stmt->fetch()) { $role = $r; $found = true; }
            $stmt->close();
          }
        }
      } catch (Throwable $e) {
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        exit;
      }
      echo json_encode(['success'=>true,'found'=>$found,'role'=>$role,'user_id'=>$userId]);
      exit;
    }

    // AJAX: Delete additional photo
    if (isset($_POST['action']) && $_POST['action'] === 'delete_additional_photo') {
      header('Content-Type: application/json');
      $photoId = intval($_POST['photo_id'] ?? 0);
      $memberId = intval($_POST['member_id'] ?? 0);
      
      if ($photoId <= 0 || $memberId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid photo_id or member_id']);
        exit;
      }

      try {
        // Get the photo path to delete file
        $photoPath = null;
        if ($stmt = $conn->prepare("SELECT photo_path FROM additional_photos WHERE id=? AND member_id=? LIMIT 1")) {
          $stmt->bind_param('ii', $photoId, $memberId);
          $stmt->execute();
          $stmt->bind_result($path);
          if ($stmt->fetch()) { $photoPath = $path; }
          $stmt->close();
        }

        if (!$photoPath) {
          echo json_encode(['success' => false, 'message' => 'Photo not found']);
          exit;
        }

        // Delete database record
        $deleted = false;
        if ($stmt = $conn->prepare("DELETE FROM additional_photos WHERE id=? AND member_id=? LIMIT 1")) {
          $stmt->bind_param('ii', $photoId, $memberId);
          $stmt->execute();
          $deleted = ($stmt->affected_rows > 0);
          $stmt->close();
        }

        // Try to delete file from disk
        $fileToDelete = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($photoPath, '/');
        if (file_exists($fileToDelete)) {
          @unlink($fileToDelete);
        }

        echo json_encode(['success' => $deleted, 'message' => $deleted ? 'Photo deleted successfully' : 'Failed to delete photo']);
      } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
      }
      exit;
    }
?>




<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="header.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/css/intlTelInput.css" />
  <script>
    // Global error capture - helps find SyntaxError line numbers early
    window.addEventListener('error', function(e){ try { console.warn('Global error:', e && e.message, e && e.filename, e && e.lineno, e && e.colno); } catch(err){} });
    window.addEventListener('unhandledrejection', function(e){ try { console.warn('Unhandled rejection:', e && e.reason); } catch(err){} });
  </script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/intlTelInput.min.js"></script>

  <title>Members - Admin Panel</title>
  <style>
    :root {
      --primary: #4f46e5;
      --primary-dark: #4338ca;
      --secondary: #ec4899;
      --success: #10b981;
      --warning: #f59e0b;
      --danger: #ef4444;
      --light: #f8fafc;
      --dark: #1e293b;
      --gray: #64748b;
      --gray-light: #cbd5e1;
      --sidebar-width: 260px;
      --header-height: 70px;
      --border-radius: 12px;
      --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
      --transition: all 0.3s ease;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', sans-serif;
      background-color: #f1f5f9;
      color: var(--dark);
      line-height: 1.6;
    }

    /* Top Navigation */
    .top-nav {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      height: var(--header-height);
      background: white;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 2rem;
      box-shadow: var(--shadow);
      z-index: 100;
    }

    .logo-container {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .logo-circle {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: bold;
      font-size: 18px;
    }

    .matrimony-name-top {
      font-weight: 700;
      color: var(--primary);
      font-size: 1.25rem;
    }

    .nav-right {
      display: flex;
      align-items: center;
      gap: 1.5rem;
    }

    .main-menu {
      display: flex;
      gap: 1.5rem;
    }

    .logout {
      color: var(--danger);
      text-decoration: none;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transition: var(--transition);
    }

    .logout:hover {
      color: var(--primary);
    }

    .user-info {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
    }

    .user-type {
      background: rgba(79, 70, 229, 0.1);
      color: var(--primary);
      padding: 0.25rem 0.75rem;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
    }

    .username {
      font-weight: 600;
      color: var(--dark);
    }

    /* Dashboard Layout */
    .dashboard-layout {
      display: flex;
      margin-top: var(--header-height);
      min-height: calc(100vh - var(--header-height));
    }

    /* Sidebar */
    .sidebar {
      width: var(--sidebar-width);
      background: white;
      height: calc(100vh - var(--header-height));
      position: fixed;
      left: 0;
      top: var(--header-height);
      overflow-y: auto;
      padding: 1.5rem 0;
      box-shadow: var(--shadow);
      transition: var(--transition);
      z-index: 90;
    }

    .matrimony-name {
      padding: 0 1.5rem 1.5rem;
      font-weight: 700;
      color: var(--primary);
      font-size: 1.1rem;
      border-bottom: 1px solid var(--gray-light);
      margin-bottom: 1rem;
    }

    .sidebar-menu {
      list-style: none;
      padding: 0 1rem;
    }

    .sidebar-link {
      display: flex;
      align-items: center;
      padding: 0.875rem 1rem;
      color: var(--gray);
      text-decoration: none;
      border-radius: var(--border-radius);
      margin-bottom: 0.5rem;
      transition: var(--transition);
      font-weight: 500;
    }

    .sidebar-link:hover {
      background-color: #f1f5f9;
      color: var(--primary);
    }

    .sidebar-link.active {
      background-color: var(--primary);
      color: white;
    }

    .sidebar-link i {
      margin-right: 0.75rem;
      font-size: 1.1rem;
      width: 24px;
      text-align: center;
    }

    /* Main Content */
    .main-content {
      flex: 1;
      padding: 2rem;
      margin-left: var(--sidebar-width);
      transition: var(--transition);
    }

    /* Hide staff management for non-admin users */
    <?php if ($_SESSION['role'] !== 'admin'): ?>
    #staffLink {
      display: none;
    }
    <?php endif; ?>

    /* Top Actions */
    .top-actions {
      display: flex;
      gap: 15px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }
    
    .action-btn {
      padding: 10px 20px;
      background-color: #4a6cf7;
      color: white;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-weight: 600;
      transition: background-color 0.3s;
    }
    
    .action-btn:hover {
      background-color: #3b5be3;
    }
    
    /* Search Bar */
    .search-bar {
      position: relative;
      margin-bottom: 25px;
      max-width: 400px;
    }
    
    .search-bar i {
      position: absolute;
      left: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: #888;
    }
    
    .search-bar input {
      width: 100%;
      padding: 12px 15px 12px 45px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-size: 16px;
    }
    
    /* Content Section */
    .content-section {
      background-color: white;
      border-radius: 10px;
      padding: 25px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
      margin-bottom: 30px;
    }
    
    .content-section h2 {
      margin-bottom: 20px;
      color: #2c3e50;
    }
    
    .content-section h3 {
      margin: 30px 0 20px;
      color: #2c3e50;
    }
    
    /* Data Table */
    .data-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
    }
    
    .data-table th, .data-table td {
      padding: 12px 15px;
      text-align: left;
      border-bottom: 1px solid #eee;
    }
    
    .data-table th {
      background-color: #f8f9fa;
      font-weight: 600;
      color: #2c3e50;
    }
    
    .data-table tr:hover {
      background-color: #f8f9fa;
    }
    
    /* Package Badges */
    .package-badge {
      padding: 5px 10px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      color: white;
    }
    
    .premium {
      background-color: #9b59b6;
    }
    
    .gold {
      background-color: #f39c12;
    }
    
    .silver {
      background-color: #7f8c8d;
    }
    
    .free {
      background-color: #2ecc71;
    }
    
    /* Buttons */
    .view-details-btn {
      padding: 8px 15px;
      background-color: #4a6cf7;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-weight: 600;
      transition: background-color 0.3s;
    }
    
    .view-details-btn:hover {
      background-color: #3b5be3;
    }
    
    /* Modal */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      overflow: auto;
    }
    
    .modal-content {
      background-color: white;
      margin: 50px auto;
      border-radius: 10px;
      width: 90%;
      max-width: 800px;
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
      max-height: 90vh;
      overflow-y: auto;
    }
    
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 25px;
      border-bottom: 1px solid #eee;
    }
    
    .modal-header h2 {
      display: flex;
      align-items: center;
      gap: 10px;
      color: #2c3e50;
    }
    
    .close {
      font-size: 28px;
      font-weight: bold;
      cursor: pointer;
      color: #888;
    }
    
    .close:hover {
      color: #333;
    }
    
    .modal-body {
      padding: 25px;
    }
    
    /* Member Profile Styles */
    .member-profile {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    
    .profile-header {
      display: flex;
      align-items: center;
      gap: 20px;
      padding: 20px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 10px;
      color: white;
    }
    
    .profile-avatar {
      flex-shrink: 0;
    }
    
    .profile-avatar img {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #fff;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .profile-info h3 {
      margin: 0 0 5px 0;
      font-size: 1.5rem;
      font-weight: 600;
    }
    
    .profile-info p {
      margin: 0 0 10px 0;
      opacity: 0.9;
    }
    
    .member-details-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-top: 20px;
    }
    
    .detail-section {
      background: #f8f9fa;
      padding: 20px;
      border-radius: 8px;
    }
    
    .detail-section h4 {
      margin: 0 0 15px 0;
      color: #2c3e50;
      font-size: 1.1rem;
      font-weight: 600;
      border-bottom: 2px solid #667eea;
      padding-bottom: 5px;
    }
    
    .detail-item {
      display: flex;
      justify-content: space-between;
      margin-bottom: 10px;
      padding: 5px 0;
    }
    
    .detail-item label {
      font-weight: 600;
      color: #555;
    }
    
    .detail-item span {
      color: #333;
    }
    
    .member-actions {
      display: flex;
      gap: 10px;
      justify-content: center;
      margin-top: 20px;
      padding-top: 20px;
      border-top: 1px solid #eee;
    }
    
    .member-actions button {
      padding: 10px 20px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    
    .btn-primary {
      background: #667eea;
      color: white;
    }
    
    .btn-primary:hover {
      background: #5a67d8;
    }
    
    .btn-secondary {
      background: #6c757d;
      color: white;
    }
    
    .btn-secondary:hover {
      background: #5a6268;
    }
    
    .btn-danger {
      background: #dc3545;
      color: white;
    }
    
    .btn-danger:hover {
      background: #c82333;
    }
    
    .btn-warning {
      background: #ffc107;
      color: #333;
    }
    
    .btn-warning:hover {
      background: #e0a800;
    }
    
    .btn-success {
      background: #28a745;
      color: white;
    }
    
    .btn-success:hover {
      background: #218838;
    }
    
    /* Status badges */
    .status-badge {
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 600;
    }
    
    .status-badge.active {
      background: #d4edda;
      color: #155724;
    }
    
    /* Form Styles */
    .form-row {
      display: flex;
      gap: 20px;
      margin-bottom: 20px;
    }
    
    .form-column {
      flex: 1;
    }
    
    .form-group {
      margin-bottom: 15px;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: #2c3e50;
    }
    
    .form-group input, .form-group select {
      width: 100%;
      padding: 12px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-size: 16px;
      color: #111;
      background: #fff;
    }
    
    .form-actions {
      display: flex;
      justify-content: flex-end;
      gap: 15px;
      margin-top: 20px;
    }
    
    .btn-primary {
      padding: 12px 25px;
      background-color: #4a6cf7;
      color: white;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .btn-primary:hover {
      background-color: #3b5be3;
    }
    
    .btn-secondary {
      padding: 12px 25px;
      background-color: #95a5a6;
      color: white;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-weight: 600;
    }
    
    .btn-secondary:hover {
      background-color: #7f8c8d;
    }
    
    /* Alert */
    .alert {
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 5px;
      font-weight: 600;
    }
    
    .alert-success {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    
    /* Menu Toggle (for mobile) */
    .menu-toggle {
      display: none;
      background: none;
      border: none;
      font-size: 1.5rem;
      color: var(--dark);
      cursor: pointer;
    }

    /* Optional overlay for mobile */
    .overlay {
      display: none;
      position: fixed;
      top: var(--header-height);
      left: 0;
      width: 100%;
      height: calc(100vh - var(--header-height));
      background: rgba(0, 0, 0, 0.3);
      z-index: 80;
    }

    .overlay.active {
      display: block;
    }

    /* Country select: keep dropdown absolutely positioned and constrained */
    .country-select {
      position: relative;
    }
    #countryDropdown {
      position: absolute;
      left: 0;
      right: 0;
      z-index: 1200; /* above modals */
      width: 100%;
      max-height: 180px;
      overflow-y: auto;
      display: none; /* hidden until needed */
      background: white;
      border: 1px solid #ddd;
      border-radius: 4px;
      padding: 6px 8px;
    }
    #countrySearch {
      margin-top: 8px;
      width: 100%;
      padding: 8px;
      border: 1px solid #ddd;
      border-radius: 4px;
      box-sizing: border-box;
    }

    /* ========== RESPONSIVE DESIGN ========== */
    
    /* Large Tablets */
    @media (max-width: 1200px) {
      .main-content {
        margin-left: 0 !important;
        padding: 20px;
      }
      
      .data-table {
        font-size: 14px;
      }
      
      .data-table th, .data-table td {
        padding: 10px 12px;
      }
    }
    
    /* Tablets */
    @media (max-width: 992px) {
      .sidebar {
        transform: translateX(-100%);
      }
      
      .sidebar.active {
        transform: translateX(0);
      }
      
      .main-content {
        padding: 15px;
      }
      
      .form-row {
        flex-direction: column;
        gap: 10px;
      }
      
      .member-details-grid {
        grid-template-columns: 1fr;
      }
      
      .menu-toggle {
        display: block;
      }
      
      .top-nav {
        padding: 0 1.5rem;
      }
    }
    
    /* Mobile Devices */
    @media (max-width: 768px) {
      .top-actions {
        flex-direction: column;
        align-items: stretch;
      }
      
      .action-btn {
        width: 100%;
        margin-bottom: 10px;
        min-height: 44px;
      }
      
      .search-bar {
        max-width: 100%;
      }
      
      /* Make table responsive */
      .data-table-container {
        overflow-x: auto;
      }
      
      .data-table {
        min-width: 700px;
      }
      
      .modal-content {
        width: 95%;
        margin: 20px auto;
        max-height: 90vh;
        overflow-y: auto;
      }
      
      .member-actions {
        flex-direction: column;
      }
      
      .matrimony-name-top {
        display: none;
      }
      
      .top-nav {
        padding: 0 1rem;
      }
      
      .nav-right {
        gap: 1rem;
      }
      
      .user-info {
        align-items: flex-start;
      }
      
      .username {
        font-size: 0.9rem;
      }
      
      .content-section {
        padding: 20px;
      }
      
      .modal-body {
        padding: 20px;
      }
      
      .modal-header {
        padding: 15px 20px;
      }
      
      .profile-header {
        flex-direction: column;
        text-align: center;
        gap: 15px;
      }
      
      .form-actions {
        flex-direction: column;
      }
      
      .btn-primary, .btn-secondary {
        width: 100%;
        margin-bottom: 10px;
        justify-content: center;
      }
      
      .view-details-btn {
        min-height: 44px;
        width: 100%;
      }
    }
    
    /* Small Mobile Devices */
    @media (max-width: 576px) {
      .main-content {
        padding: 10px;
      }
      
      .content-section {
        padding: 15px;
        border-radius: 8px;
      }
      
      .modal-body {
        padding: 15px;
      }
      
      .modal-header {
        padding: 15px;
      }
      
      .modal-header h2 {
        font-size: 1.3rem;
      }
      
      .profile-info h3 {
        font-size: 1.3rem;
      }
      
      .detail-section {
        padding: 15px;
      }
      
      .detail-item {
        flex-direction: column;
        gap: 5px;
      }
      
      .top-nav {
        padding: 0 10px;
      }
      
      .logo-container {
        gap: 0.5rem;
      }
      
      .nav-right {
        gap: 0.75rem;
      }
      
      .main-menu {
        gap: 1rem;
      }
      
      .logout span {
        display: none;
      }
    }
    
    /* Extra Small Devices */
    @media (max-width: 400px) {
      .top-nav {
        padding: 0 8px;
      }
      
      .user-type {
        font-size: 0.7rem;
        padding: 0.2rem 0.5rem;
      }
      
      .username {
        font-size: 0.8rem;
      }
      
      .sidebar {
        width: 100%;
      }
      
      .main-content {
        padding: 8px;
      }
      
      .content-section {
        padding: 12px;
      }
      
      .modal-content {
        width: 98%;
        margin: 10px auto;
      }
    }

    /* Ensure images are responsive */
    img {
      max-width: 100%;
      height: auto;
    }

    /* Improve touch targets for mobile */
    @media (max-width: 768px) {
      .sidebar-link {
        min-height: 44px;
      }
      
      .search-bar input {
        min-height: 44px;
      }
      
      .form-group input, .form-group select {
        min-height: 44px;
      }
    }
</style>

</head>
<body>
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
        <span class="user-type"><?= htmlspecialchars($type) ?></span>
        <span class="username"><?= htmlspecialchars($name) ?></span>
      </div>
    </div>
  </header>

  <div class="dashboard-layout">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
      <div class="matrimony-name">Matrimony Admin Panel</div>
      <ul class="sidebar-menu">
        <li><a href="index.php" class="sidebar-link"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="members.php" class="sidebar-link"><i class="fas fa-users"></i> Manage Members</a></li>
        <li><a href="call-management.php" class="sidebar-link"><i class="fas fa-phone"></i> Call Management</a></li>
        <li><a href="user-message-management.php" class="sidebar-link"><i class="fas fa-comments"></i> User Messages</a></li>
        <li><a href="review-management.php" class="sidebar-link"><i class="fas fa-star"></i> Review Management</a></li>
        <li><a href="transaction-management.php" class="sidebar-link"><i class="fas fa-receipt"></i> Transactions</a></li>
        <li><a href="packages-management.php" class="sidebar-link"><i class="fas fa-box"></i> Packages</a></li>
        <li><a href="blog-management.php" class="sidebar-link"><i class="fas fa-blog"></i> Blog Management</a></li>
        <li><a href="total-earnings.php" class="sidebar-link"><i class="fas fa-chart-line"></i> Total Earnings</a></li>
        <li id="staffLink"><a href="staff.php" class="sidebar-link"><i class="fas fa-user-shield"></i> Staff Management</a></li>
      </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content" style="margin-left: 250px;">
      <div class="top-actions">
        <button class="action-btn" id="addMemberBtn"><i class="fas fa-user-plus"></i> ADD MEMBER</button>
        <button class="action-btn" id="activeMemberBtn">All Members</button>
        <button class="action-btn" id="freeMemberBtn">Free Members</button>
        <button class="action-btn" id="paidMemberBtn">Paid Members</button>

      </div>
      <div class="search-bar">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Search member"/>
      </div>
      <div class="content-section">
        <h2>All Members (<?php echo count($members); ?>)</h2>
        <table class="data-table">
          <thead>
            <tr>
              <th>Photo</th>
              <th>Branch</th>
              <th>Name</th>
              <th>Looking For</th>
              <th>Marital Status</th>
              <th>Register Date</th>
              <th>Package Status</th>
              <th>Expire Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($members as $member): ?>
            <tr data-member-id="<?php echo $member['id']; ?>">
              <td>
                 <?php
                 $photoField = $member['photo'] ?? '';
                 if ($photoField && trim($photoField) !== '') {
                   if (preg_match('#^https?://#i', $photoField)) {
                     $photoPath = $photoField;
                   } else {
                     $photoPath = 'https://thennilavu.lk/' . ltrim($photoField, '/');
                   }
                 } else {
                   $photoPath = 'https://thennilavu.lk/uploads/default.jpg';
                 }
                 ?>
                  <img src="<?php echo htmlspecialchars($photoPath); ?>" alt="Profile" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
              </td>
              <td>
                <?php 
                  if ($member['branch_name']) {
                    echo htmlspecialchars($member['branch_name']);
                  } else {
                    echo 'WEB';
                  }
                ?>
              </td>
              <td>
                <?php echo htmlspecialchars($member['name']); ?>
                <?php if ($member['profile_status'] === 'Deactivated'): ?>
                    <br><span style="color: red; font-size: 12px;">Deactivated</span>
                <?php endif; ?>
              </td>
              <td><?php echo htmlspecialchars($member['looking_for'] ?? '-'); ?></td>
              <td><?php echo htmlspecialchars($member['marital_status']); ?></td>
              <td><?php echo date('M d, Y H:i', strtotime($member['created_at'])); ?></td>
        <td>
        <?php
          // Consider a package expired for display if end_date exists and is in the past
          $isExpired = false;
          if (!empty($member['end_date']) && $member['end_date'] !== '0000-00-00' && $member['end_date'] !== null) {
            // Compare end_date timestamp to current time
            $isExpired = (strtotime($member['end_date']) < time());
          }

          // If expired, show as Free User per requirement
          if ($isExpired) {
            echo '<span class="package-badge free">Free User</span>';
          } elseif ($member['package_name'] !== 'Free User' && $member['requestPackage'] !== null) {
            echo "<strong>" . htmlspecialchars($member['package_name']) . "</strong><br>";
            echo '<span class="package-badge ';
            switch($member['package_status']) {
              case 'active':
                echo 'active">Active';
                break;
              case 'expired':
                echo 'expired">Expired';
                break;
              case 'pending':
                echo 'pending">Request Pending';
                break;
              default:
                echo 'free">Free User';
            }
            echo '</span>';
          } else {
            echo '<span class="package-badge free">Free User</span>';
          }
        ?>
        </td>
        <td>
        <?php
          // If expired, show '-' in Expire Date cell per requirement
          if (isset($isExpired) && $isExpired) {
            echo '-';
          } elseif ($member['package_name'] !== 'Free User' && $member['end_date']) {
            echo date('M d, Y', strtotime($member['end_date']));
          } else {
            echo '-';
          }
        ?>
        </td>
              <td>
                <button class="view-details-btn" data-member-id="<?php echo htmlspecialchars($member['id'], ENT_QUOTES); ?>">View</button>
              </td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (empty($members)): ?>
            <tr>
              <td colspan="8" style="text-align: center; padding: 20px; color: #666;">
                No members found in the database.
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
        <!-- Removed: High price package member table -->
      </div>
      
      <!-- Add Member Modal -->
      <div id="addMemberModal" class="modal">
        <div class="modal-content">
          <div class="modal-header">
            <h2><i class="fas fa-user-plus"></i> Add New Member</h2>
            <span class="close" id="closeAddMemberModal">&times;</span>
          </div>
          <div class="modal-body">
            <form id="addMemberForm" method="POST" enctype="multipart/form-data">
              <input type="hidden" name="edit_member_id" id="edit_member_id" value="" />
              <div class="form-row">
                <div class="form-column">
                  <div class="form-group">
                    <label for="memberNameInput">Name</label>
                    <input id="memberNameInput" name="name" type="text" required />
                  </div>
                </div>
                <div class="form-column">
                  <div class="form-group">
                    <label for="memberPhotoInput">Photo</label>
                    <input id="memberPhotoInput" name="photo" type="file" accept="image/*" />
                  </div>
                </div>
                <div class="form-column">
                  <div class="form-group">
                    <label for="memberGender">Gender</label>
                    <select id="memberGender" name="gender" required>
                      <option value="">Select gender</option>
                      <option value="Male">Male</option>
                      <option value="Female">Female</option>
                      <option value="Other">Other</option>
                    </select>
                  </div>
                </div>
              </div>
              <div class="form-row">
                <div class="form-column">
                  <div class="form-group">
                    <label for="memberLookingFor">Looking For</label>
                    <select id="memberLookingFor" name="looking_for" required>
                      <option value="">Select</option>
                      <option value="Male">Male</option>
                      <option value="Female">Female</option>
                      <option value="Other">Other</option>
                    </select>
                  </div>
                </div>
              </div>
              <div class="form-row">
                <div class="form-column">
                  <div class="form-group">
                    <label for="memberDob">Date of Birth</label>
                    <input id="memberDob" name="dob" type="date" />
                  </div>
                </div>
                
                
               
                
                 <div class="form-column">
                      <div class="form-group">
                          <label for="memberReligion">Religion</label>
                          <select id="memberReligion" name="religion">
                           <option value="">Select</option>
                           <option value="Hindu">Hindu</option>
                           <option value="Christian">Christian</option>
                           <option value="Islam">Islam</option>
                           <option value="Buddhist">Buddhist</option>
                          </select>
                        </div>
                    </div>
                <div class="form-column">
                  <div class="form-group">
                    <label for="memberMaritalStatus">Marital Status</label>
                    <select id="memberMaritalStatus" name="marital_status" required>
                      <option value="">Select status</option>
                      <option value="Single">Single</option>
                      <option value="Married">Married</option>
                      <option value="Divorced">Divorced</option>
                      <option value="Widowed">Widowed</option>
                    </select>
                  </div>
                </div>
              </div>
              <div class="form-row">
                <div class="form-column">
                  <div class="form-group">
                    <label for="memberLanguage">Language</label>
                    <select id="memberLanguage" name="language">
                      <option value="tamil">Tamil</option>
                      <option value="english">English</option>
                      <option value="sinhala">Sinhala</option>
                    </select>
                  </div>
                </div>

                <div class="form-column">
                  <div class="form-group">
                    <label for="memberProfession">Profession</label>
                    <input id="memberProfession" name="profession" type="text" />
                  </div>
                </div>
                <div class="form-column">
                  <div class="form-group">
                    <label for="memberCountry">Location</label>
                    <input id="memberCountry" name="country" type="text" placeholder="Jaffna , Srilanka"/>
                  </div>
                </div>
              </div>
              <div class="form-row">
                  
                  
               <div class="form-column">
  <div class="form-group">
    <label for="memberPhone">Phone</label>
    <input id="memberPhone" name="phone" type="tel" />
  </div>
</div>

                
                
                <div class="form-column">
                  <div class="form-group">
                    <label for="memberSmoking">Smoking</label>
                    <select id="memberSmoking" name="smoking">
                      <option value="">Select</option>
                      <option value="No">No</option>
                      <option value="Yes">Yes</option>
                      <option value="Occasionally">Occasionally</option>
                    </select>
                  </div>
                </div>
                <div class="form-column">
                  <div class="form-group">
                    <label for="memberDrinking">Drinking</label>
                    <select id="memberDrinking" name="drinking">
                      <option value="">Select</option>
                      <option value="No">No</option>
                      <option value="Yes">Yes</option>
                      <option value="Occasionally">Occasionally</option>
                    </select>
                  </div>
                </div>
              </div>
              
              <div class="form-row">
                <div class="form-column">
                  <div class="form-group">
                    <label for="memberBranch">Branch</label>
                     <select id="memberBranch" name="branch_id" required>
                      <option value="">Select Branch</option>
                    </select>
                  </div>
                </div>
              </div>
                <!-- Additional sections: Physical, Family, Partner Expectations, Horoscope, Education -->
                <hr />
                <h3>Physical Info (optional)</h3>
                <div class="form-row">
                  <div class="form-column">
                    <div class="form-group">
                      <label>Complexion</label>
                      <input name="complexion" type="text" />
                    </div>
                  </div>
                  <div class="form-column">
                    <div class="form-group">
                      <label>Height (cm)</label>
                      <input name="height" type="number" step="0.01" />
                    </div>
                  </div>
                  <div class="form-column">
                    <div class="form-group">
                      <label>Weight (kg)</label>
                      <input name="weight" type="number" step="0.01" />
                    </div>
                  </div>
                </div>
                <div class="form-row">
                    
                 <div class="form-column">
                      <div class="form-group">
                        <label>Blood Group</label>
                        <select name="blood_group">
                          <option value="A+">A+</option>
                          <option value="A-">A-</option>
                          <option value="B+">B+</option>
                          <option value="B-">B-</option>
                          <option value="AB+">AB+</option>
                          <option value="AB-">AB-</option>
                          <option value="O+">O+</option>
                          <option value="O-">O-</option>
                        </select>
                      </div>
                    </div>

                  
                  <div class="form-column">
                    <div class="form-group">
                      <label>Eye Color</label>
                      <input name="eye_color" type="text" />
                    </div>
                  </div>
                  <div class="form-column">
                    <div class="form-group">
                      <label>Hair Color</label>
                      <input name="hair_color" type="text" />
                    </div>
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-column">
                    <div class="form-group">
                      <label>Disability</label>
                      <input name="disability" type="text" />
                    </div>
                  </div>
                </div>

                <hr />
                <h3>Family Info (optional)</h3>
                <div class="form-row">
                  <div class="form-column">
                    <div class="form-group">
                      <label>Father Name</label>
                      <input name="father_name" type="text" />
                    </div>
                  </div>
                  <div class="form-column">
                    <div class="form-group">
                      <label>Father Profession</label>
                      <input name="father_profession" type="text" />
                    </div>
                  </div>
                  
                  
                  <div class="form-column">
                  <div class="form-group">
                    <label for="fatherContact">Father Contact</label>
                    <input id="fatherContact" name="father_contact" type="tel" />
                  </div>
                </div>

                  
                  
                </div>
                
                
                <div class="form-row">
                  <div class="form-column">
                    <div class="form-group">
                      <label>Mother Name</label>
                      <input name="mother_name" type="text" />
                    </div>
                  </div>
                  <div class="form-column">
                    <div class="form-group">
                      <label>Mother Profession</label>
                      <input name="mother_profession" type="text" />
                    </div>
                  </div>
                  
                  
                  <div class="form-column">
                  <div class="form-group">
                    <label for="motherContact">Mother Contact</label>
                    <input id="motherContact" name="mother_contact" type="tel" />
                  </div>
                </div>

                  
                  
                </div>
                
                
                <div class="form-row">
                  <div class="form-column">
                    <div class="form-group">
                      <label>Brothers Count</label>
                      <input name="brothers" type="number" min="0" />
                    </div>
                  </div>
                  <div class="form-column">
                    <div class="form-group">
                      <label>Sisters Count</label>
                      <input name="sisters" type="number" min="0" />
                    </div>
                  </div>
                </div>

                <hr />
                <h3>Partner Expectations (optional)</h3>
                <div class="form-row">
                    
                    
                  <div class="form-column">
                  <div class="form-group">
                      <label for="partnerCountryInput">Preferred Country</label>

                      <!-- Country select container: makes dropdown overlay instead of pushing layout -->
                      <div class="country-select">
                        <!-- Input field that gets filled -->
                        <input id="partnerCountryInput" name="partner_country" type="text" placeholder="Select country..." readonly />

                        <!-- Search box -->
                        <input type="text" id="countrySearch" placeholder="Search country..." />

                        <!-- Country dropdown (hidden by default) -->
                        <select id="countryDropdown" size="6">
                      <!-- Countries will load here -->
                    </select>
                    </div>
                  </div>
                </div>


                  
                  
                  <div class="form-column">
                    <div class="form-group">
                      <label>Age Range Min</label>
                      <input name="min_age" type="number" min="0" />
                    </div>
                  </div>
                  <div class="form-column">
                    <div class="form-group">
                      <label>Age Range Max</label>
                      <input name="max_age" type="number" min="0" />
                    </div>
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-column">
                    <div class="form-group">
                      <label>Min Height (cm)</label>
                      <input name="min_height" type="number" />
                    </div>
                  </div>
                  <div class="form-column">
                    <div class="form-group">
                      <label>Max Height (cm)</label>
                      <input name="max_height" type="number" />
                    </div>
                  </div>
                  <div class="form-column">
                    <div class="form-group">
                      <label>Marital Status</label>
                      <select id="partnerMaritalStatus" name="partner_marital_status">
                        <option value="">Select</option>
                        <option value="Never Married">Never Married</option>
                        <option value="Divorced">Divorced</option>
                        <option value="Widowed">Widowed</option>
                        <option value="Separated">Separated</option>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="form-row">
                    
                    
                
                  
                  <div class="form-column">
                      <div class="form-group">
                        <label>Religion</label>
                        <select name="partner_religion">
                         <option value="hindu">Hindu</option>
                        <option value="christian">Christian</option>
                        <option value="muslim">Muslim</option>
                        <option value="buddhist">Buddhist</option>

                        </select>
                      </div>
                    </div>

                  <div class="form-column">
                    <div class="form-group">
                      <label>Smoking</label>
                      <select name="partner_smoking">
                        <option value="">Select</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                        <option value="Occasionally">Occasionally</option>
                      </select>
                    </div>
                  </div>
                  <div class="form-column">
                    <div class="form-group">
                      <label>Drinking</label>
                      <select name="partner_drinking">
                        <option value="">Select</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                        <option value="Occasionally">Occasionally</option>
                      </select>
                    </div>
                  </div>
                </div>

                <hr />
                <h3>Horoscope (optional)</h3>
                <div class="form-row">
                  <div class="form-column">
                    <div class="form-group">
                      <label>Birth Date</label>
                      <input name="birth_date" type="date" />
                    </div>
                  </div>
                  <div class="form-column">
                    <div class="form-group">
                      <label>Birth Time</label>
                      <input name="birth_time" type="time" />
                    </div>
                  </div>
                  <div class="form-column">
                    <div class="form-group">
                      <label>Zodiac</label>
                      <input name="zodiac" type="text" />
                    </div>
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-column">
                    <div class="form-group">
                      <label>Nakshatra</label>
                      <select class="form-control" name="nakshatra">
                                      <option value="1001">
                                                                                    அஸ்வினி
                                                                                </option>
                                                                                                                                                            <option value="1002">
                                                                                    பரணி
                                                                                </option>
                                                                                                                                                            <option value="1003">
                                                                                    கார்த்திகை 1ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1004">
                                                                                    கார்த்திகை 2ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1005">
                                                                                    கார்த்திகை 3ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1006">
                                                                                    கார்த்திகை 4ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1007">
                                                                                    ரோகிணி
                                                                                </option>
                                                                                                                                                            <option value="1008">
                                                                                    மிருகசீரிடம் 1ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1009">
                                                                                    மிருகசீரிடம் 2ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1010">
                                                                                    மிருகசீரிடம் 3ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1011">
                                                                                    மிருகசீரிடம் 4ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1012">
                                                                                    திருவாதிரை
                                                                                </option>
                                                                                                                                                            <option value="1013">
                                                                                    புனர்பூசம் 1ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1014">
                                                                                    புனர்பூசம் 2ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1015">
                                                                                    புனர்பூசம் 3ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1016">
                                                                                    புனர்பூசம் 4ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1017">
                                                                                    பூசம்
                                                                                </option>
                                                                                                                                                            <option value="1018">
                                                                                    ஆயிலியம்
                                                                                </option>
                                                                                                                                                            <option value="1019">
                                                                                    மகம்
                                                                                </option>
                                                                                                                                                            <option value="1020">
                                                                                    பூரம்
                                                                                </option>
                                                                                                                                                            <option value="1021">
                                                                                    உத்தரம் 1ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1022">
                                                                                    உத்தரம் 2ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1023">
                                                                                    உத்தரம் 3ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1024">
                                                                                    உத்தரம் 4ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1025">
                                                                                    அஸ்தம்
                                                                                </option>
                                                                                                                                                            <option value="1026">
                                                                                    சித்திரை 1ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1027">
                                                                                    சித்திரை 2ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1028">
                                                                                    சித்திரை 3ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1029">
                                                                                    சித்திரை 4ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1030">
                                                                                    சுவாதி
                                                                                </option>
                                                                                                                                                            <option value="1031">
                                                                                    விசாகம் 1ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1032">
                                                                                    விசாகம் 2ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1033">
                                                                                    விசாகம் 3ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1034">
                                                                                    விசாகம் 4ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1035">
                                                                                    அனுஷம்
                                                                                </option>
                                                                                                                                                            <option value="1036">
                                                                                    கேட்டை
                                                                                </option>
                                                                                                                                                            <option value="1037">
                                                                                    மூலம்
                                                                                </option>
                                                                                                                                                            <option value="1038">
                                                                                    பூராடம்
                                                                                </option>
                                                                                                                                                            <option value="1039">
                                                                                    உத்திராடம் 1ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1040">
                                                                                    உத்திராடம் 2ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1041">
                                                                                    உத்திராடம் 3ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1042">
                                                                                    உத்திராடம் 4ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1043">
                                                                                    திருவோணம்
                                                                                </option>
                                                                                                                                                            <option value="1044">
                                                                                    அவிட்டம் 1 ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1045">
                                                                                    அவிட்டம் 2ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1046">
                                                                                    அவிட்டம் 3ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1047">
                                                                                    அவிட்டம் 4ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1048">
                                                                                    சதயம்
                                                                                </option>
                                                                                                                                                            <option value="1049">
                                                                                    பூரட்டாதி 1ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1050">
                                                                                    பூரட்டாதி 2ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1051">
                                                                                    பூரட்டாதி 3ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1052">
                                                                                    பூரட்டாதி 4ம் பாதம்
                                                                                </option>
                                                                                                                                                            <option value="1053">
                                                                                    உத்திரட்டாதி
                                                                                </option>
                                                                                                                                                            <option value="1054">
                                                                                    ரேவதி
                                                                                </option>
                                    </select>
                    </div>
                  </div>
                  <div class="form-column">
                    <div class="form-group">
                      <label>Karmic Debt</label>
                      <input name="karmic_debt" type="text" />
                    </div>
                  </div>
                  <div class="form-column">
                    <div class="form-group">
                      <label>Planet Image</label>
                      <input name="planet_image" type="file" accept="image/*" />
                    </div>
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-column">
                    <div class="form-group">
                      <label>Navamsha Image</label>
                      <input name="navamsha_image" type="file" accept="image/*" />
                    </div>
                  </div>
                </div>

                <hr />
                <h3>Education (optional)</h3>
                <div id="educationList">
                  <div class="form-row education-row">
                    <div class="form-column">
                      <div class="form-group">
                        <label>Institute</label>
                        <input name="institute[]" type="text" />
                      </div>
                    </div>
                    <div class="form-column">
                      <div class="form-group">
                        <label>Degree/Stream</label>
                        <input name="degree[]" type="text" />
                      </div>
                    </div>
                    <div class="form-column">
                      <div class="form-group">
                        <label>Field</label>
                        <input name="field[]" type="text" />
                      </div>
                    </div>
                  </div>
                  <div class="form-row">
                    <div class="form-column">
                      <div class="form-group">
                        <label>Reg Number</label>
                        <input name="regnum[]" type="text" />
                      </div>
                    </div>
                    <div class="form-column">
                      <div class="form-group">
                        <label>Start Year</label>
                        <input name="startyear[]" type="number" />
                      </div>
                    </div>
                    <div class="form-column">
                      <div class="form-group">
                        <label>End Year</label>
                        <input name="endyear[]" type="number" />
                      </div>
                    </div>
                  </div>
                </div>
                <div style="margin:10px 0">
                  <button type="button" id="addEducationBtn" class="btn-secondary">Add Another Education</button>
                </div>

                <hr />
                <h3>Additional Photos <span style="font-size: 0.85rem; color: #666;">(optional - click + button to add more)</span></h3>
                <div style="background: #f0f4f8; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                  <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 10px; font-weight: 600; color: #2c3e50;">
                      <i class="fas fa-images"></i> Upload Additional Photos
                    </label>
                    <div id="additionalPhotosInputContainer" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-start;">
                      <div class="form-column" style="flex: 0 1 auto;">
                        <input type="file" id="initialAdditionalPhoto" name="additional_photos[]" accept="image/*" style="padding: 8px; border: 1px dashed #4f46e5; border-radius: 5px; cursor: pointer;" />
                      </div>
                      <button type="button" id="addMorePhotosBtn" class="btn-secondary" style="padding: 8px 15px; margin-top: 0;"><i class="fas fa-plus"></i> Add More</button>
                    </div>
                  </div>
                  <div id="additionalPhotosPreview" style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px;">
                    <!-- Preview thumbnails will appear here -->
                  </div>
                </div>

              <div class="form-actions">
                <button type="submit" name="add_member" class="btn-primary"><i class="fas fa-save"></i> Save Member</button>
                <button type="button" id="cancelAddMember" class="btn-secondary">Cancel</button>
              </div>
            </form>
          </div>
        </div>
      </div>

    </main>
  </div>

  <!-- Member Details Modal -->
  <div id="memberDetailsModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2><i class="fas fa-user"></i> Member Details</h2>
        <span class="close">&times;</span>
      </div>
      <div class="modal-body">
        <div class="member-profile">
          <div class="profile-header">
            <div class="profile-avatar">
              <img id="memberPhotoImg" src="https://thennilavu.lk/uploads/default.jpg" alt="Profile" style="width:80px; height:80px; border-radius:50%; object-fit:cover;" />
            </div>
            <div class="profile-info">
              <h3 id="memberName">-</h3>
              <p id="memberId">Member ID: -</p>

            </div>
          </div>

          <!-- Photo Gallery Section -->
          <div class="detail-section" style="grid-column: 1 / -1;">
            <h4>Photo Gallery</h4>
            <div id="photoGallery" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-start;">
              <div style="position: relative;">
                <img id="mainPhotoThumb" src="https://thennilavu.lk/uploads/default.jpg" alt="Main Photo" style="width: 150px; height: 150px; border-radius: 8px; object-fit: cover; border: 2px solid #ddd; cursor: pointer;">
                <span style="position: absolute; top: 5px; left: 5px; background: #4f46e5; color: white; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">Main</span>
              </div>
              <div id="additionalPhotosContainer" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <!-- Additional photos will be loaded here -->
              </div>
            </div>
          </div>

          <div class="member-details-grid">
            <div class="detail-section">
              <h4>Personal Information</h4>
              <div class="detail-item"><label>Age:</label><span id="memberAge">-</span></div>
              <div class="detail-item"><label>Gender:</label><span id="memberGenderDisplay">-</span></div>
              <div class="detail-item"><label>Date of Birth:</label><span id="memberDobDisplay">-</span></div>
              <div class="detail-item"><label>Location:</label><span id="memberLocation">-</span></div>
              
              <div class="detail-item"><label>Marital Status:</label><span id="memberMaritalStatusDisplay">-</span></div>
              <div class="detail-item"><label>Looking For:</label><span id="memberLookingForDisplay">-</span></div>
              <div class="detail-item"><label>Language:</label><span id="memberLanguageDisplay">-</span></div>
              <div class="detail-item"><label>Profession:</label><span id="memberProfessionDisplay">-</span></div>
              <div class="detail-item"><label>Phone:</label><span id="memberPhoneDisplay">-</span></div>
            </div>

            <div class="detail-section">
              <h4>Physical Info</h4>
              <div class="detail-item"><label>Height (cm):</label><span id="memberHeight">-</span></div>
              <div class="detail-item"><label>Weight (kg):</label><span id="memberWeight">-</span></div>
              <div class="detail-item"><label>Complexion:</label><span id="memberComplexion">-</span></div>
              <div class="detail-item"><label>Blood Group:</label><span id="memberBloodGroup">-</span></div>
              <div class="detail-item"><label>Eye Color:</label><span id="memberEyeColor">-</span></div>
              <div class="detail-item"><label>Hair Color:</label><span id="memberHairColor">-</span></div>
              <div class="detail-item"><label>Disability:</label><span id="memberDisability">-</span></div>
            </div>

            <div class="detail-section">
              <h4>Family</h4>
              <div class="detail-item"><label>Father:</label><span id="memberFather">-</span></div>
              <div class="detail-item"><label>Mother:</label><span id="memberMother">-</span></div>
              <div class="detail-item"><label>Brothers:</label><span id="memberBrothers">-</span></div>
              <div class="detail-item"><label>Sisters:</label><span id="memberSisters">-</span></div>
            </div>

            <div class="detail-section">
              <h4>Partner Expectations</h4>
              <div class="detail-item"><label>Preferred Country:</label><span id="memberPartnerCountry">-</span></div>
              <div class="detail-item"><label>Age Range:</label><span id="memberPartnerAge">-</span></div>
              <div class="detail-item"><label>Height Range:</label><span id="memberPartnerHeight">-</span></div>
              <div class="detail-item"><label>Marital Status:</label><span id="memberPartnerMarital">-</span></div>
              <div class="detail-item"><label>Religion:</label><span id="memberPartnerReligion">-</span></div>
            </div>

            <div class="detail-section">
              <h4>Horoscope</h4>
              <div class="detail-item"><label>Birth Date:</label><span id="memberBirthDate">-</span></div>
              <div class="detail-item"><label>Birth Time:</label><span id="memberBirthTime">-</span></div>
              <div class="detail-item"><label>Zodiac:</label><span id="memberZodiac">-</span></div>
              <div class="detail-item"><label>Nakshatra:</label><span id="memberNakshatra">-</span></div>
              <div class="detail-item"><label>Planet Image:</label><div id="memberPlanetImageDisplay" style="margin-top:8px;">-</div></div>
              <div class="detail-item"><label>Navamsha Image:</label><div id="memberNavamshaImageDisplay" style="margin-top:8px;">-</div></div>
            </div>
              

              <div class="detail-section">
                <h4>Account & Package</h4>
                <div class="detail-item"><label>Branch:</label><span id="memberBranchDisplay">-</span></div>
                <div class="detail-item"><label>Registration Date:</label><span id="memberRegDate">-</span></div>
                <div class="detail-item"><label>Last Login:</label><span id="memberLastLogin">-</span></div>
                <div class="detail-item"><label>Profile Status:</label><span id="memberProfileStatus" class="status-badge active">-</span></div>
                <div class="detail-item"><label>Package:</label><span id="memberPackageDisplay">-</span></div>
                <div class="detail-item"><label>Package Expiry:</label><span id="memberExpiry">-</span></div>
              </div>
              
              
              <div class="detail-section">
                <h4>Education</h4>
                <div id="memberEducationList" style="padding:6px 0;">
                  <em>No education records</em>
                </div>
              </div>
              
          </div>

          <div class="member-actions">
            <button class="btn-primary" id="editMemberBtn" data-member-id="">Edit</button>
            <button class="btn-warning" id="hideProfileBtn" data-member-id="" data-hidden="0">Hide Profile</button>
            <button class="btn-danger" id="blockMemberBtn" data-member-id="">Block Member</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
  
  
  
<script>
  // Full list of countries with aliases for fuzzy search
  const countries = [
    { name: "Afghanistan", aliases: ["afghanistan","af","afg"] },
    { name: "Albania", aliases: ["albania","al","alb"] },
    { name: "Algeria", aliases: ["algeria","dz","dza"] },
    { name: "Andorra", aliases: ["andorra","ad","and"] },
    { name: "Angola", aliases: ["angola","ao","ago"] },
    { name: "Argentina", aliases: ["argentina","ar","arg"] },
    { name: "Armenia", aliases: ["armenia","am","arm"] },
    { name: "Australia", aliases: ["australia","au","aus"] },
    { name: "Austria", aliases: ["austria","at","aut"] },
    { name: "Bangladesh", aliases: ["bangladesh","bd","bgd"] },
    { name: "Belgium", aliases: ["belgium","be","bel"] },
    { name: "Bhutan", aliases: ["bhutan","bt","btn"] },
    { name: "Brazil", aliases: ["brazil","br","bra"] },
    { name: "Canada", aliases: ["canada","ca","can"] },
    { name: "China", aliases: ["china","cn","chn","peoples republic of china"] },
    { name: "Denmark", aliases: ["denmark","dk","dnk"] },
    { name: "Egypt", aliases: ["egypt","eg","egy"] },
    { name: "France", aliases: ["france","fr","fra"] },
    { name: "Germany", aliases: ["germany","de","deu"] },
    { name: "India", aliases: ["india","in","ind"] },
    { name: "Indonesia", aliases: ["indonesia","id","idn"] },
    { name: "Japan", aliases: ["japan","jp","jpn"] },
    { name: "Kenya", aliases: ["kenya","ke","ken"] },
    { name: "Maldives", aliases: ["maldives","mv","mdv"] },
    { name: "Nepal", aliases: ["nepal","np","npl"] },
    { name: "Pakistan", aliases: ["pakistan","pk","pak"] },
    { name: "Russia", aliases: ["russia","ru","rus","russian federation"] },
    { name: "South Africa", aliases: ["south africa","za","zaf","sa"] },
    { name: "Sri Lanka", aliases: ["srilanka","sri lanka","lk","lka","lanaka","ceylon"] },
    { name: "United Kingdom", aliases: ["united kingdom","uk","gb","great britain","britain","london","england","scotland","wales"] },
    { name: "United States", aliases: ["united states","usa","us","america","us of a","united states of america"] },
    // Add remaining countries similarly...
  ];

  const dropdown = document.getElementById("countryDropdown");
  const partnerCountryInputEl = document.getElementById("partnerCountryInput");
  const countrySearchEl = document.getElementById('countrySearch');

  // Load all countries into dropdown
  function loadCountries() {
    dropdown.innerHTML = "";
    countries.forEach(c => {
      const opt = document.createElement("option");
      opt.value = c.name;
      opt.textContent = c.name;
      dropdown.appendChild(opt);
    });
  }
  loadCountries();

  // Fuzzy search filter
  function filterCountries() {
    const text = countrySearchEl.value.toLowerCase().replace(/\s+/g,'');
    Array.from(dropdown.options).forEach(opt => {
      const countryObj = countries.find(c => c.name === opt.value);
      const match = countryObj.aliases.some(alias => alias.includes(text));
      opt.hidden = !match;
    });
    dropdown.style.display = "block"; // keep visible while typing
  }

  // Select country → fill input
  function selectCountry() {
    const selectedValue = dropdown.value;
    if(selectedValue) {
      partnerCountryInputEl.value = selectedValue;
      dropdown.style.display = "none";
      countrySearchEl.value = '';
    }
  }

  // Show dropdown on input click
  partnerCountryInputEl.addEventListener('click', () => {
    dropdown.style.display = 'block';
    countrySearchEl.focus();
  });

  // Search box input
  countrySearchEl.addEventListener('input', filterCountries);

  // Handle dropdown change
  dropdown.addEventListener('change', selectCountry);

  // Close dropdown if clicked outside
  document.addEventListener('click', function(ev) {
    if (!ev.target.closest || !ev.target.closest('.country-select')) {
      dropdown.style.display = 'none';
    }
  });
</script>

  
  
  <script>
  const motherInput = document.querySelector("#motherContact");
  if (typeof window.intlTelInput === 'function' && motherInput) {
  const motherIti = window.intlTelInput(motherInput, {
    initialCountry: "auto",
    nationalMode: false,
    separateDialCode: true,
    geoIpLookup: callback => {
      const token = 'YOUR_TOKEN';
      if (token && token !== 'YOUR_TOKEN') {
        fetch(`https://ipinfo.io/json?token=${token}`)
          .then(resp => resp.json())
          .then(resp => callback(resp.country))
          .catch(() => {
              fetch('https://ipapi.co/json/').then(r => r.json()).then(r => callback(r.country)).catch(()=>callback('LK'));
          });
      } else {
        fetch('https://ipapi.co/json/').then(r => r.json()).then(r => callback(r.country)).catch(()=>callback('LK'));
      }
    },
    utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js",
  });
  } else if (!motherInput) {
    console.warn('motherContact element not found');
  } else {
    console.warn('intlTelInput not available for motherContact');
  }
</script>



  <script>
  const fatherInput = document.querySelector("#fatherContact");
  if (typeof window.intlTelInput === 'function' && fatherInput) {
  const fatherIti = window.intlTelInput(fatherInput, {
    initialCountry: "auto",
    nationalMode: false,
    separateDialCode: true,
    geoIpLookup: callback => {
      const token = 'YOUR_TOKEN';
      if (token && token !== 'YOUR_TOKEN') {
        fetch(`https://ipinfo.io/json?token=${token}`)
          .then(resp => resp.json())
          .then(resp => callback(resp.country))
          .catch(() => {
              fetch('https://ipapi.co/json/').then(r => r.json()).then(r => callback(r.country)).catch(()=>callback('LK'));
          });
      } else {
        fetch('https://ipapi.co/json/').then(r => r.json()).then(r => callback(r.country)).catch(()=>callback('LK'));
      }
    },
    utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js",
  });
  } else if (!fatherInput) {
    console.warn('fatherContact element not found');
  } else {
    console.warn('intlTelInput not available for fatherContact');
  }
</script>



 <script>
  const input = document.querySelector("#memberPhone");
  if (typeof window.intlTelInput === 'function' && input) {
  const iti = window.intlTelInput(input, {
    initialCountry: "auto",
    nationalMode: false,
    separateDialCode: true,
    geoIpLookup: callback => {
      const token = 'YOUR_TOKEN';
      if (token && token !== 'YOUR_TOKEN') {
        fetch(`https://ipinfo.io/json?token=${token}`)
          .then(resp => resp.json())
          .then(resp => callback(resp.country))
          .catch(() => {
              fetch('https://ipapi.co/json/').then(r => r.json()).then(r => callback(r.country)).catch(()=>callback('LK'));
          });
      } else {
        fetch('https://ipapi.co/json/').then(r => r.json()).then(r => callback(r.country)).catch(()=>callback('LK'));
      }
    },
    utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js",
  });
  } else if (!input) {
    console.warn('memberPhone input not found');
  } else {
    console.warn('intlTelInput not available for memberPhone');
  }
</script>

  <script>
  // Global base URL for building public file URLs in client-side JS
  // We use json_encode to ensure proper quoting/escaping.
  const BASE_URL = <?= json_encode('https://thennilavu.lk/'); ?>;
  // Add another education entry by cloning the initial two rows (institute/degree/field + reg/start/end)
  document.addEventListener('DOMContentLoaded', function() {
    const addBtn = document.getElementById('addEducationBtn');
    const list = document.getElementById('educationList');
    if (!addBtn || !list) return;

    addBtn.addEventListener('click', function() {
      // Find the first pair of rows: the one with class education-row and its next sibling row
      const firstRow = list.querySelector('.education-row');
      if (!firstRow) return;
      const secondRow = firstRow.nextElementSibling;

      // Create a wrapper to keep the pair together
      const wrapper = document.createElement('div');
      wrapper.className = 'education-entry';

      const clone1 = firstRow.cloneNode(true);
      const clone2 = secondRow ? secondRow.cloneNode(true) : null;

      // Clear input values in clones
      clone1.querySelectorAll('input').forEach(function(i){ i.value = ''; });
      if (clone2) clone2.querySelectorAll('input').forEach(function(i){ i.value = ''; });

      wrapper.appendChild(clone1);
      if (clone2) wrapper.appendChild(clone2);

      // Append a small remove button for convenience
      var removeBtnRow = document.createElement('div');
      removeBtnRow.className = 'form-row';
      removeBtnRow.innerHTML = '<div class="form-column"><button type="button" class="btn-secondary remove-education">Remove This Education</button></div>';
      wrapper.appendChild(removeBtnRow);

      list.appendChild(wrapper);

      // wire remove button
      var removeButtons = wrapper.querySelectorAll('.remove-education');
      for (var i = 0; i < removeButtons.length; i++) {
        removeButtons[i].addEventListener('click', (function(w){ return function(){ w.remove(); }; })(wrapper));
      }
    });
  });

  // Add more photos handler
  const addMorePhotosBtn = document.getElementById('addMorePhotosBtn');
  const photosContainer = document.getElementById('additionalPhotosInputContainer');
  if (addMorePhotosBtn && photosContainer) {
    addMorePhotosBtn.addEventListener('click', function(e) {
      e.preventDefault();
      
      // Create a unique ID for this input+preview pair
      const inputId = 'photo-input-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
      
      // Create a new file input
      const newInput = document.createElement('input');
      newInput.type = 'file';
      newInput.name = 'additional_photos[]';
      newInput.accept = 'image/*';
      newInput.id = inputId;
      newInput.style.cssText = 'padding: 8px; border: 1px dashed #4f46e5; border-radius: 5px; cursor: pointer;';
      
      // Create a wrapper div for the new input
      const wrapper = document.createElement('div');
      wrapper.className = 'form-column';
      wrapper.style.cssText = 'flex: 0 1 auto; display: flex; gap: 5px; align-items: center;';
      wrapper.dataset.inputId = inputId;
      
      // Create remove button
      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'btn-secondary';
      removeBtn.style.cssText = 'padding: 8px 12px; margin: 0;';
      removeBtn.innerHTML = '<i class="fas fa-trash"></i>';
      removeBtn.title = 'Remove this upload field';
      removeBtn.onclick = function(e) {
        e.preventDefault();
        // Remove the preview associated with this input
        const previewContainer = document.getElementById('additionalPhotosPreview');
        if (previewContainer) {
          const previews = previewContainer.querySelectorAll(`[data-input-id="${inputId}"]`);
          previews.forEach(p => p.remove());
        }
        // Remove the input wrapper
        wrapper.remove();
      };
      
      wrapper.appendChild(newInput);
      wrapper.appendChild(removeBtn);
      
      // Insert before the "Add More" button
      photosContainer.insertBefore(wrapper, addMorePhotosBtn);
      
      // Handle file preview
      newInput.addEventListener('change', function() {
        // First, remove any existing preview for this input
        const previewContainer = document.getElementById('additionalPhotosPreview');
        if (previewContainer) {
          const existingPreviews = previewContainer.querySelectorAll(`[data-input-id="${inputId}"]`);
          existingPreviews.forEach(p => p.remove());
        }
        
        // If file is selected, show preview
        if (this.files && this.files[0]) {
          const reader = new FileReader();
          reader.onload = function(e) {
            const previewContainer = document.getElementById('additionalPhotosPreview');
            const preview = document.createElement('div');
            preview.style.cssText = 'position: relative; display: inline-block;';
            preview.dataset.inputId = inputId;
            preview.innerHTML = `
              <img src="${e.target.result}" style="width: 100px; height: 100px; border-radius: 6px; object-fit: cover; border: 2px solid #ddd;">
              <span style="position: absolute; top: 3px; right: 3px; background: #4f46e5; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 600;">New</span>
            `;
            previewContainer.appendChild(preview);
          };
          reader.readAsDataURL(this.files[0]);
        }
      });
    });
  }

  // Handle initial file input for additional photos
  const initialPhotoInput = document.getElementById('initialAdditionalPhoto');
  if (initialPhotoInput) {
    initialPhotoInput.addEventListener('change', function() {
      // First, remove any existing preview for this input
      const previewContainer = document.getElementById('additionalPhotosPreview');
      if (previewContainer) {
        const existingPreviews = previewContainer.querySelectorAll('[data-input-id="initial"]');
        existingPreviews.forEach(p => p.remove());
      }
      
      // If file is selected, show preview
      if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
          const previewContainer = document.getElementById('additionalPhotosPreview');
          const preview = document.createElement('div');
          preview.style.cssText = 'position: relative; display: inline-block;';
          preview.dataset.inputId = 'initial';
          preview.innerHTML = `
            <img src="${e.target.result}" style="width: 100px; height: 100px; border-radius: 6px; object-fit: cover; border: 2px solid #ddd;">
            <span style="position: absolute; top: 3px; right: 3px; background: #4f46e5; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 600;">New</span>
          `;
          previewContainer.appendChild(preview);
        };
        reader.readAsDataURL(this.files[0]);
      }
    });
  }

// Global functions
function viewMemberDetails(memberId) {
  const members = JSON.parse(<?php echo json_encode(json_encode($members)); ?>);
  const member = members.find(m => m.id == memberId);
  if (member) {
    // Pass the member ID to the details function so we always lookup the correct record
    showMemberDetails(member.id);
  } else {
    alert('Member not found');
  }
}

// Check authentication on page load
document.addEventListener('DOMContentLoaded', function() {
  const isLoggedIn = localStorage.getItem('isLoggedIn');
  const userType = localStorage.getItem('userType');
  const username = localStorage.getItem('username');
  
  // For demo purposes, set default values if not present
  if (!isLoggedIn) {
    localStorage.setItem('isLoggedIn', 'true');
    localStorage.setItem('userType', 'admin');
    localStorage.setItem('username', 'Admin User');
  }
  
  // Display user info
  const currentUserType = localStorage.getItem('userType');
  const currentUsername = localStorage.getItem('username');
  
  if (currentUsername && currentUserType) {
    const userDisplay = document.getElementById('userDisplay');
    if (userDisplay) {
      userDisplay.textContent = `${currentUserType.toUpperCase()}`;
    }
  }
  
  // Force update user info
  forceUpdateUserInfo();

  const staffLink = document.getElementById('staffLink');
  if (staffLink && currentUserType !== 'admin') {
    staffLink.style.display = 'none';
  }

  // Setup action buttons
  setupActionButtons();
  
  // Setup member search input filtering
  setupMemberSearch();
  
  // Wire up member detail buttons (replaces inline onclick handlers)
  if (typeof setupMemberDetailsButtons === 'function') {
    setupMemberDetailsButtons();
  }
  
  // Setup mobile menu functionality
  setupMobileMenu();
  
  // Setup modal functionality
  setupModals();
  
  // Handle window resize for responsiveness
  window.addEventListener('resize', handleResize);
  
  // Initial responsive setup
  handleResize();
  
  // Logout functionality
  const logoutBtn = document.querySelector('.logout');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', function(e) {
      e.preventDefault();
      localStorage.removeItem('isLoggedIn');
      localStorage.removeItem('userType');
      localStorage.removeItem('username');
      alert('You have been logged out');
      window.location.href = 'login.php';
    });
  }
});

// Mobile menu functionality
function setupMobileMenu() {
  const menuToggle = document.getElementById('menuToggle');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('overlay');

  // Toggle sidebar visibility on button click
  if (menuToggle && sidebar) {
    menuToggle.addEventListener('click', () => {
      sidebar.classList.toggle('active');
      if (overlay) overlay.classList.toggle('active');
      // Prevent body scroll when sidebar is open
      document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
    });

    // Close sidebar when clicking on overlay
    if (overlay) {
      overlay.addEventListener('click', () => {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
      });
    }
  }

  // Close sidebar when clicking on a link (mobile)
  const sidebarLinks = document.querySelectorAll('.sidebar-link');
  sidebarLinks.forEach(link => {
    link.addEventListener('click', () => {
      if (window.innerWidth <= 992) {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        if (sidebar) sidebar.classList.remove('active');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = '';
      }
    });
  });
}

// Load branches from database
function loadBranches(selectedId) {
  const branchSelect = document.getElementById('memberBranch');
  if (!branchSelect) return Promise.resolve();

  return fetch(window.location.pathname + '?action=get_branches')
    .then(response => response.json())
    .then(data => {
      if (data.success && data.branches) {
        branchSelect.innerHTML = '<option value="">Select Branch</option>';
        data.branches.forEach(branch => {
          const option = document.createElement('option');
          option.value = branch.id;
          option.textContent = branch.branch_name;
          branchSelect.appendChild(option);
        });
        if (selectedId) {
          // Try to set the selected value; if option not present yet, attempt fallback
          branchSelect.value = selectedId;
        }
      }
      return data;
    })
    .catch(error => {
      console.error('Error loading branches:', error);
      return { success: false, branches: [] };
    });
}

// Setup modal functionality
function setupModals() {
  // Add Member Modal
  const addMemberModal = document.getElementById('addMemberModal');
  const closeAddMemberModal = document.getElementById('closeAddMemberModal');
  const cancelAddMember = document.getElementById('cancelAddMember');
  
  if (closeAddMemberModal && addMemberModal) {
    closeAddMemberModal.addEventListener('click', () => {
      addMemberModal.style.display = 'none';
    });
  }
  
  if (cancelAddMember && addMemberModal) {
    cancelAddMember.addEventListener('click', () => {
      addMemberModal.style.display = 'none';
    });
  }

  // Open Add Member modal when ADD MEMBER button is clicked
  const addMemberBtn = document.getElementById('addMemberBtn');
  if (addMemberBtn && addMemberModal) {
    addMemberBtn.addEventListener('click', () => {
      // reset form fields
      const form = document.getElementById('addMemberForm');
      if (form) form.reset();
      const editId = document.getElementById('edit_member_id');
      if (editId) editId.value = '';
      const branchField = document.getElementById('memberBranch');
      if (branchField) branchField.disabled = false;
      loadBranches();
      const submitBtn = form.querySelector('button[name="add_member"]');
      if (submitBtn) submitBtn.innerHTML = '<i class="fas fa-user-plus"></i> Add Member';
      addMemberModal.style.display = 'block';
    });
  }
  
  // Member Details Modal
  const memberDetailsModal = document.getElementById('memberDetailsModal');
  const closeMemberDetails = memberDetailsModal?.querySelector('.close');
  
  if (closeMemberDetails && memberDetailsModal) {
    closeMemberDetails.addEventListener('click', () => {
      memberDetailsModal.style.display = 'none';
    });
  }
  
  // Close modals when clicking outside
  window.addEventListener('click', function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
      if (event.target === modal) {
        modal.style.display = 'none';
      }
    });
  });
}

// Handle window resize for responsiveness
function handleResize() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('overlay');
  
  // Close sidebar on resize to larger screens
  if (window.innerWidth > 992) {
    if (sidebar) sidebar.classList.remove('active');
    if (overlay) overlay.classList.remove('active');
    document.body.style.overflow = '';
  }
  
  // Make table scrollable on mobile if needed
  const table = document.querySelector('.data-table');
  if (table && window.innerWidth <= 768) {
    const tableContainer = table.closest('.content-section') || table.parentElement;
    if (tableContainer) {
      tableContainer.style.overflowX = 'auto';
      tableContainer.style.webkitOverflowScrolling = 'touch'; // Smooth scrolling on iOS
    }
  }
  
  // Adjust modal positioning for mobile
  adjustModalsForMobile();
}

// Adjust modals for mobile screens
function adjustModalsForMobile() {
  const modals = document.querySelectorAll('.modal-content');
  modals.forEach(modal => {
    if (window.innerWidth <= 768) {
      modal.style.margin = '20px auto';
      modal.style.maxHeight = '90vh';
      modal.style.overflowY = 'auto';
    } else {
      modal.style.margin = '50px auto';
      modal.style.maxHeight = '';
      modal.style.overflowY = '';
    }
  });
}

// Force update user info
function forceUpdateUserInfo() {
  const userType = localStorage.getItem('userType');
  const username = localStorage.getItem('username');
  
  if (username && userType) {
    console.log(`Welcome ${username} (${userType})`);
    const userDisplay = document.getElementById('userDisplay');
    if (userDisplay) {
      userDisplay.textContent = `${userType.toUpperCase()}`;
    }
  }
}

// Setup action buttons
function setupActionButtons() {
  const buttons = {
    'freeMemberBtn': showFreeMembers,
    'paidMemberBtn': showPaidMembers,
    'activeMemberBtn': showActiveMembers
  };

  Object.keys(buttons).forEach(buttonId => {
    const button = document.getElementById(buttonId);
    if (button) {
      button.addEventListener('click', buttons[buttonId]);
    }
  });
}

// Setup member details buttons
function setupMemberDetailsButtons() {
  const detailButtons = document.querySelectorAll('.view-details-btn');
  detailButtons.forEach(button => {
    button.addEventListener('click', function() {
      const mid = this.dataset.memberId || null;
      if (mid) { showMemberDetails(parseInt(mid, 10)); return; }
      const row = this.closest('tr');
      const memberName = row && row.cells && row.cells[2] ? row.cells[2].textContent : null;
      console.log('Clicked member:', memberName); // Debug log
      if (memberName) showMemberDetails(memberName);
    });
  });
}

// Setup search for members table
function setupMemberSearch() {
  const searchInput = document.querySelector('.search-bar input');
  if (!searchInput) return;

  searchInput.addEventListener('input', function() {
    const term = this.value.toLowerCase().trim();
    const table = document.querySelector('.content-section .data-table');
    if (!table) return;
    const rows = table.querySelectorAll('tbody tr');
    
    let visibleCount = 0;
    
    rows.forEach(row => {
      // Skip placeholder rows without normal cells
      const cells = row.querySelectorAll('td');
      if (!cells || cells.length < 2) return;

      const idCell = (cells[0] && cells[0].textContent) ? cells[0].textContent.toLowerCase() : '';
      const nameCell = (cells[2] && cells[2].textContent) ? cells[2].textContent.toLowerCase() : '';
      const packageCell = (cells[6] && cells[6].textContent) ? cells[6].textContent.toLowerCase() : '';
      const cityCell = (cells[4] && cells[4].textContent) ? cells[4].textContent.toLowerCase() : '';
      const createdCell = (cells[5] && cells[5].textContent) ? cells[5].textContent.toLowerCase() : '';

      const haystack = [idCell, nameCell, packageCell, cityCell, createdCell].join(' ');
      const match = term === '' || haystack.indexOf(term) !== -1;
      row.style.display = match ? '' : 'none';
      
      if (match) visibleCount++;
    });
    
    // Show message if no results found
    showNoResultsMessage(visibleCount === 0 && term !== '');
  });
}

// Show no results message
function showNoResultsMessage(show) {
  let noResultsRow = document.querySelector('.no-results-message');
  
  if (show && !noResultsRow) {
    const table = document.querySelector('.content-section .data-table tbody');
    if (table) {
      noResultsRow = document.createElement('tr');
      noResultsRow.className = 'no-results-message';
      noResultsRow.innerHTML = `
        <td colspan="9" style="text-align: center; padding: 20px; color: #666; font-style: italic;">
          No members found matching your search criteria.
        </td>
      `;
      table.appendChild(noResultsRow);
    }
  } else if (!show && noResultsRow) {
    noResultsRow.remove();
  }
}

// Show free members (filter table to only Free package)
function showFreeMembers() {
  const table = document.querySelector('.content-section .data-table');
  if (!table) return;
  const rows = table.querySelectorAll('tbody tr');
  
  let visibleCount = 0;
  
  rows.forEach(row => {
    if (row.classList.contains('no-results-message')) {
      row.remove();
      return;
    }
    
    const packageCell = row.cells[6];
    const isFree = packageCell && (packageCell.textContent.toLowerCase().includes('free') || !packageCell.textContent.trim() || packageCell.textContent.trim() === '-');
    row.style.display = isFree ? '' : 'none';
    
    if (isFree) visibleCount++;
  });
  
  showNoResultsMessage(visibleCount === 0);
}

// Show paid members (Premium/Gold/Silver)
function showPaidMembers() {
  const table = document.querySelector('.content-section .data-table');
  if (!table) return;
  const rows = table.querySelectorAll('tbody tr');
  
  let visibleCount = 0;
  
  rows.forEach(row => {
    if (row.classList.contains('no-results-message')) {
      row.remove();
      return;
    }
    
    const packageCell = row.cells[6];
    const text = packageCell ? packageCell.textContent.toLowerCase().trim() : '';
    // Treat as paid if package cell exists and does NOT indicate Free User or an empty/placeholder value
    const isFreeLike = text === '' || text === '-' || text.includes('free');
    const isPaid = !isFreeLike;
    row.style.display = isPaid ? '' : 'none';
    
    if (isPaid) visibleCount++;
  });
  
  showNoResultsMessage(visibleCount === 0);
}

// Show all members
function showActiveMembers() {
  const table = document.querySelector('.content-section .data-table');
  if (!table) return;
  const rows = table.querySelectorAll('tbody tr');
  
  rows.forEach(row => {
    if (row.classList.contains('no-results-message')) {
      row.remove();
    } else {
      row.style.display = '';
    }
  });
}

// Reset filter (show all members)
function showBlockedMembers() {
  showActiveMembers();
}

// Show member details modal with real data
function showMemberDetails(memberId) {
  const modal = document.getElementById('memberDetailsModal');
  const closeBtn = modal.querySelector('.close');

  // Find member data from PHP array by ID
  const members = <?php echo json_encode($members); ?>;
  const member = members.find(m => parseInt(m.id) === parseInt(memberId));

  if (!member) {
    alert('Member not found');
    return;
  }
  
  // Calculate age
  let age = '-';
  if (member.dob && member.dob !== '0000-00-00') {
    const dob = new Date(member.dob);
    const now = new Date();
    age = Math.floor((now - dob) / (365.25 * 24 * 60 * 60 * 1000)) + ' years';
  }
  
  


// Base URL of your live site (was declared here previously, now using global BASE_URL)

// Update member photo (handle multiple legacy formats and the new '/uploads/...' stored path)
const memberPhoto = document.getElementById('memberPhotoImg');
let photoPath = BASE_URL + 'uploads/default.jpg'; // default image

if (member.photo && member.photo.trim() !== '') {
  let cleanPhoto = member.photo.trim();

  // If already full URL, use it
  if (cleanPhoto.match(/^https?:\/\//i)) {
    photoPath = cleanPhoto;
  } else {
    // Remove any leading slashes
    cleanPhoto = cleanPhoto.replace(/^\/+/, '');
    // If it already starts with uploads/ or user/, append to BASE_URL directly
    const lower = cleanPhoto.toLowerCase();
    if (lower.startsWith('uploads/') || lower.startsWith('user/')) {
      photoPath = BASE_URL + cleanPhoto;
    } else {
      // fallback — assume it's a filename under uploads
      photoPath = BASE_URL + 'uploads/' + cleanPhoto;
    }
  }
}

if (memberPhoto) memberPhoto.src = photoPath;

// Also update the main photo thumbnail in photo gallery
const mainPhotoThumb = document.getElementById('mainPhotoThumb');
if (mainPhotoThumb) mainPhotoThumb.src = photoPath;





 


























  
  // Update modal content with real data
  document.getElementById('memberName').textContent = sanitizeVal(member.name) || '-';
  document.getElementById('memberId').textContent = `Member ID: M${String(member.id).padStart(3, '0')}`;
  document.getElementById('memberAge').textContent = age;
  document.getElementById('memberLocation').textContent = `${member.city || '-'}, ${member.country || '-'}`;
  const mStatEl = document.getElementById('memberMaritalStatusDisplay');
  if (mStatEl) mStatEl.textContent = sanitizeVal(member.marital_status) || '-';
  const mLookingEl = document.getElementById('memberLookingForDisplay');
  if (mLookingEl) mLookingEl.textContent = sanitizeVal(member.looking_for) || '-';
  document.getElementById('memberRegDate').textContent = member.created_at ? new Date(member.created_at).toLocaleString() : 'N/A';
  document.getElementById('memberLastLogin').textContent = sanitizeVal(member.last_login) || 'N/A';
  document.getElementById('memberBranchDisplay').textContent = sanitizeVal(member.branch_name) || 'Not Assigned';

  // Profile status - comes from the SQL as profile_status (Active or Deactivated)
  const profileStatusEl = document.getElementById('memberProfileStatus');
  if (member.profile_status && member.profile_status.toLowerCase() === 'deactivated') {
    profileStatusEl.textContent = 'Deactivated';
    profileStatusEl.className = 'status-badge deactivated';
  } else {
    profileStatusEl.textContent = 'Active';
    profileStatusEl.className = 'status-badge active';
  }

  // Package expiry - use end_date from the joined userpackage row
  const expiryEl = document.getElementById('memberExpiry');
  const safeEndDate = sanitizeVal(member.end_date);
  if (safeEndDate && safeEndDate !== '0000-00-00') {
    const d = new Date(safeEndDate);
    if (!isNaN(d.getTime())) {
      expiryEl.textContent = d.toLocaleDateString();
    } else {
      expiryEl.textContent = safeEndDate;
    }
  } else {
    expiryEl.textContent = 'N/A';
  }
  
  // Update package badge (use package_name from SQL)
  const packageBadge = document.getElementById('memberPackageDisplay');
  const pkg = sanitizeVal(member.package_name) || sanitizeVal(member.package) || 'Free User';
  if (packageBadge) packageBadge.textContent = pkg;
  // create a safe css class name
  const pkgClass = typeof pkg === 'string' ? pkg.toLowerCase().replace(/\s+/g,'-') : 'free-user';
  if (packageBadge) packageBadge.className = `package-badge ${pkgClass}`;

  // Prepare Block/Unblock button (toggle)
  const blockBtn = document.getElementById('blockMemberBtn');
  if (blockBtn) {
    blockBtn.dataset.memberId = member.id;
    blockBtn.disabled = false;
    blockBtn.dataset.blocked = '0'; // 0 = not blocked, 1 = blocked
    blockBtn.textContent = 'Block Member';

    // Fetch current user role status and update button to Unblock if needed
    fetch(`members.php?action=get_user_status&member_id=${member.id}`)
      .then(r=>r.json())
      .then(d=>{
        if (d.success) {
          if (!d.user_id) {
            // No linked user: allow deleting the member record
            blockBtn.textContent = 'Delete Member';
            blockBtn.disabled = false;
            blockBtn.dataset.delete = '1';
          } else if (d.role === 'block') {
            blockBtn.textContent = 'Unblock Member';
            blockBtn.dataset.blocked = '1';
            blockBtn.disabled = false;
          }
        }
      }).catch(()=>{});

    // Click handler toggles between block and unblock
    blockBtn.onclick = function() {
      const mid = this.dataset.memberId;
      // If this button is acting as Delete (no linked user), run delete flow
      if (this.dataset.delete === '1') {
        if (!confirm('Are you sure you want to DELETE this member and all related details? This cannot be undone.')) return;
        const formData = new FormData();
        formData.append('action', 'delete_member');
        formData.append('member_id', mid);
        this.disabled = true;
        fetch('members.php', { method: 'POST', body: formData })
          .then(r=>r.json())
          .then(d=>{
            if (d.success) {
              alert('Member deleted successfully.');
              // remove row from table and close modal
              try {
                const row = document.querySelector(`tr[data-member-id='${mid}']`);
                if (row) row.remove();
              } catch(e){}
              const modal = document.getElementById('memberDetailsModal');
              if (modal) modal.style.display = 'none';
            } else {
              alert('Delete failed: ' + (d.message || 'Unknown error'));
              this.disabled = false;
            }
          }).catch(e=>{ alert('Request error: '+e.message); this.disabled = false; });
        return;
      }

      const isBlocked = this.dataset.blocked === '1';
      const confirmMsg = isBlocked ? 'Are you sure you want to unblock this member?' : 'Are you sure you want to block this member?';
      if (!confirm(confirmMsg)) return;

      const formData = new FormData();
      formData.append('action', isBlocked ? 'unblock_member' : 'block_member');
      formData.append('member_id', mid);

      // Optimistically disable while request in progress
      this.disabled = true;
      fetch('members.php', { method: 'POST', body: formData })
        .then(r=>r.json())
        .then(d=>{
            if (d.success) {
              // New blocked state after the operation
              const newBlockedState = !isBlocked;
              if (newBlockedState) {
                blockBtn.textContent = 'Unblock Member';
                blockBtn.dataset.blocked = '1';
                blockBtn.disabled = false;
                alert('Member blocked successfully.');
              } else {
                blockBtn.textContent = 'Block Member';
                blockBtn.dataset.blocked = '0';
                blockBtn.disabled = false;
                alert('Member unblocked successfully.');
              }

              // Update the members table row immediately so the Name column shows activation status without page refresh
              try {
                const memberId = String(mid);
                const row = document.querySelector(`tr[data-member-id='${memberId}']`);
                if (row) {
                  const nameCell = row.cells[2]; // third column is Name
                  if (nameCell) {
                    const existingLabel = nameCell.querySelector('.deactivated-label');
                    if (newBlockedState) {
                      // add Deactivated label if not present
                      if (!existingLabel) {
                        const br = document.createElement('br');
                        const span = document.createElement('span');
                        span.className = 'deactivated-label';
                        span.style.color = 'red';
                        span.style.fontSize = '12px';
                        span.textContent = 'Deactivated';
                        nameCell.appendChild(br);
                        nameCell.appendChild(span);
                      }
                    } else {
                      // remove Deactivated label if present
                      if (existingLabel) {
                        const prev = existingLabel.previousSibling;
                        if (prev && prev.nodeName === 'BR') prev.remove();
                        existingLabel.remove();
                      }
                    }
                  }
                }
                // Update modal profile status badge if present
                const profileStatusEl = document.getElementById('memberProfileStatus');
                if (profileStatusEl) {
                  if (newBlockedState) {
                    profileStatusEl.textContent = 'Deactivated';
                    profileStatusEl.className = 'status-badge deactivated';
                  } else {
                    profileStatusEl.textContent = 'Active';
                    profileStatusEl.className = 'status-badge active';
                  }
                }
              } catch (err) {
                console.error('Failed to update table row after block/unblock:', err);
              }
            } else {
              blockBtn.disabled = false;
              alert((isBlocked ? 'Unblock' : 'Block') + ' failed: ' + (d.message || 'Unknown error'));
            }
          })
        .catch(e=>{
          blockBtn.disabled = false;
          alert('Request error: '+e.message);
        });
    };
  }

  // Prepare Hide/Show Profile button
  const hideProfileBtn = document.getElementById('hideProfileBtn');
  if (hideProfileBtn) {
    hideProfileBtn.dataset.memberId = member.id;
    hideProfileBtn.disabled = false;
    
    // Set initial button state based on profile_hidden value
    const isHidden = member.profile_hidden === 1 || member.profile_hidden === '1';
    hideProfileBtn.dataset.hidden = isHidden ? '1' : '0';
    hideProfileBtn.textContent = isHidden ? 'Show Profile' : 'Hide Profile';
    hideProfileBtn.className = isHidden ? 'btn-success' : 'btn-warning';
    
    // Click handler to toggle profile hidden status
    hideProfileBtn.onclick = function() {
      const mid = this.dataset.memberId;
      const currentlyHidden = this.dataset.hidden === '1';
      const confirmMsg = currentlyHidden 
        ? 'Are you sure you want to show this profile to users?' 
        : 'Are you sure you want to hide this profile from users?';
      
      if (!confirm(confirmMsg)) return;
      
      const formData = new FormData();
      formData.append('action', 'toggle_profile_hidden');
      formData.append('member_id', mid);
      
      this.disabled = true;
      fetch('members.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
          if (d.success) {
            const newHiddenState = d.profile_hidden === 1;
            this.dataset.hidden = newHiddenState ? '1' : '0';
            this.textContent = newHiddenState ? 'Show Profile' : 'Hide Profile';
            this.className = newHiddenState ? 'btn-success' : 'btn-warning';
            this.disabled = false;
            alert(d.message || (newHiddenState ? 'Profile hidden successfully' : 'Profile is now visible'));
          } else {
            this.disabled = false;
            alert('Operation failed: ' + (d.message || 'Unknown error'));
          }
        })
        .catch(e => {
          this.disabled = false;
          alert('Request error: ' + e.message);
        });
    };
  }

  // Populate additional DB-backed fields (physical, family, partner, horoscope, education)
  document.getElementById('memberHeight').textContent = sanitizeVal(member.height_cm) || sanitizeVal(member.height) || '-';
  document.getElementById('memberWeight').textContent = sanitizeVal(member.weight_kg) || sanitizeVal(member.weight) || '-';
  document.getElementById('memberComplexion').textContent = sanitizeVal(member.complexion) || '-';
  document.getElementById('memberBloodGroup').textContent = sanitizeVal(member.blood_group) || '-';
  document.getElementById('memberEyeColor').textContent = sanitizeVal(member.eye_color) || '-';
  document.getElementById('memberHairColor').textContent = sanitizeVal(member.hair_color) || '-';
  document.getElementById('memberDisability').textContent = sanitizeVal(member.disability) || '-';

  // Family
  if (member.family) {
    document.getElementById('memberFather').textContent = (member.family.father_name ? member.family.father_name : '-') + (member.family.father_profession ? (' ('+member.family.father_profession+')') : '') + (member.family.father_contact ? (' - '+member.family.father_contact) : '');
    document.getElementById('memberMother').textContent = (member.family.mother_name ? member.family.mother_name : '-') + (member.family.mother_profession ? (' ('+member.family.mother_profession+')') : '') + (member.family.mother_contact ? (' - '+member.family.mother_contact) : '');
    document.getElementById('memberBrothers').textContent = (member.family.brothers_count !== null ? member.family.brothers_count : '-');
    document.getElementById('memberSisters').textContent = (member.family.sisters_count !== null ? member.family.sisters_count : '-');
  } else {
    document.getElementById('memberFather').textContent = '-';
    document.getElementById('memberMother').textContent = '-';
    document.getElementById('memberBrothers').textContent = '-';
    document.getElementById('memberSisters').textContent = '-';
  }

  // Partner expectations
  if (member.partner) {
    document.getElementById('memberPartnerCountry').textContent = sanitizeVal(member.partner.preferred_country) || '-';
    document.getElementById('memberPartnerAge').textContent = ((member.partner.min_age||member.partner.min_age===0) ? member.partner.min_age : '-') + ' - ' + ((member.partner.max_age||member.partner.max_age===0) ? member.partner.max_age : '-');
    document.getElementById('memberPartnerHeight').textContent = ((member.partner.min_height||member.partner.min_height===0) ? member.partner.min_height : '-') + ' - ' + ((member.partner.max_height||member.partner.max_height===0) ? member.partner.max_height : '-');
    document.getElementById('memberPartnerMarital').textContent = sanitizeVal(member.partner.marital_status) || '-';
    document.getElementById('memberPartnerReligion').textContent = sanitizeVal(member.partner.religion) || '-';
  } else {
    document.getElementById('memberPartnerCountry').textContent = '-';
    document.getElementById('memberPartnerAge').textContent = '-';
    document.getElementById('memberPartnerHeight').textContent = '-';
    document.getElementById('memberPartnerMarital').textContent = '-';
    document.getElementById('memberPartnerReligion').textContent = '-';
  }

  // Horoscope
  if (member.horoscope) {
    document.getElementById('memberBirthDate').textContent = sanitizeVal(member.horoscope.birth_date) || '-';
    document.getElementById('memberBirthTime').textContent = sanitizeVal(member.horoscope.birth_time) || '-';
    document.getElementById('memberZodiac').textContent = sanitizeVal(member.horoscope.zodiac) || '-';
    
    const nakshatraNames = {
    "1001": "அஸ்வினி",
    "1002": "பரணி",
    "1003": "கார்த்திகை 1ம் பாதம்",
    "1004": "கார்த்திகை 2ம் பாதம்",
    "1005": "கார்த்திகை 3ம் பாதம்",
    "1006": "கார்த்திகை 4ம் பாதம்",
    "1007": "ரோகிணி",
    "1008": "மிருகசீரிடம் 1ம் பாதம்",
    "1009": "மிருகசீரிடம் 2ம் பாதம்",
    "1010": "மிருகசீரிடம் 3ம் பாதம்",
    "1011": "மிருகசீரிடம் 4ம் பாதம்",
    "1012": "திருவாதிரை",
    "1013": "புனர்பூசம் 1ம் பாதம்",
    "1014": "புனர்பூசம் 2ம் பாதம்",
    "1015": "புனர்பூசம் 3ம் பாதம்",
    "1016": "புனர்பூசம் 4ம் பாதம்",
    "1017": "பூசம்",
    "1018": "ஆயிலியம்",
    "1019": "மகம்",
    "1020": "பூரம்",
    "1021": "உத்தரம் 1ம் பாதம்",
    "1022": "உத்தரம் 2ம் பாதம்",
    "1023": "உத்தரம் 3ம் பாதம்",
    "1024": "உத்தரம் 4ம் பாதம்",
    "1025": "அஸ்தம்",
    "1026": "சித்திரை 1ம் பாதம்",
    "1027": "சித்திரை 2ம் பாதம்",
    "1028": "சித்திரை 3ம் பாதம்",
    "1029": "சித்திரை 4ம் பாதம்",
    "1030": "சுவாதி",
    "1031": "விசாகம் 1ம் பாதம்",
    "1032": "விசாகம் 2ம் பாதம்",
    "1033": "விசாகம் 3ம் பாதம்",
    "1034": "விசாகம் 4ம் பாதம்",
    "1035": "அனுஷம்",
    "1036": "கேட்டை",
    "1037": "மூலம்",
    "1038": "பூராடம்",
    "1039": "உத்திராடம் 1ம் பாதம்",
    "1040": "உத்திராடம் 2ம் பாதம்",
    "1041": "உத்திராடம் 3ம் பாதம்",
    "1042": "உத்திராடம் 4ம் பாதம்",
    "1043": "திருவோணம்",
    "1044": "அவிட்டம் 1 ம் பாதம்",
    "1045": "அவிட்டம் 2ம் பாதம்",
    "1046": "அவிட்டம் 3ம் பாதம்",
    "1047": "அவிட்டம் 4ம் பாதம்",
    "1048": "சதயம்",
    "1049": "பூரட்டாதி 1ம் பாதம்",
    "1050": "பூரட்டாதி 2ம் பாதம்",
    "1051": "பூரட்டாதி 3ம் பாதம்",
    "1052": "பூரட்டாதி 4ம் பாதம்",
    "1053": "உத்திரட்டாதி",
    "1054": "ரேவதி"
};

document.getElementById('memberNakshatra').textContent =
    nakshatraNames[member.horoscope.nakshatra] || '-';

    
    // Display Planet Image
    const planetDisplay = document.getElementById('memberPlanetImageDisplay');
    if (planetDisplay) {
      if (member.horoscope.planet_image && member.horoscope.planet_image.trim() !== '') {
        let planetPath = member.horoscope.planet_image.trim();
        if (!planetPath.match(/^https?:\/\//i)) {
          planetPath = planetPath.replace(/^\/+/, '');
          if (!planetPath.toLowerCase().startsWith('uploads/') && !planetPath.toLowerCase().startsWith('http')) {
            planetPath = 'uploads/' + planetPath;
          }
          planetPath = BASE_URL + planetPath;
        }
        planetDisplay.innerHTML = `<a href="${planetPath}" target="_blank"><img src="${planetPath}" style="max-width:200px; max-height:200px; border-radius:4px; cursor:pointer;" alt="Planet Image"></a>`;
      } else {
        planetDisplay.textContent = '-';
      }
    }
    
    // Display Navamsha Image
    const navamshaDisplay = document.getElementById('memberNavamshaImageDisplay');
    if (navamshaDisplay) {
      if (member.horoscope.navamsha_image && member.horoscope.navamsha_image.trim() !== '') {
        let navamshaPath = member.horoscope.navamsha_image.trim();
        if (!navamshaPath.match(/^https?:\/\//i)) {
          navamshaPath = navamshaPath.replace(/^\/+/, '');
          if (!navamshaPath.toLowerCase().startsWith('uploads/') && !navamshaPath.toLowerCase().startsWith('http')) {
            navamshaPath = 'uploads/' + navamshaPath;
          }
          navamshaPath = BASE_URL + navamshaPath;
        }
        navamshaDisplay.innerHTML = `<a href="${navamshaPath}" target="_blank"><img src="${navamshaPath}" style="max-width:200px; max-height:200px; border-radius:4px; cursor:pointer;" alt="Navamsha Image"></a>`;
      } else {
        navamshaDisplay.textContent = '-';
      }
    }
  } else {
    document.getElementById('memberBirthDate').textContent = '-';
    document.getElementById('memberBirthTime').textContent = '-';
    document.getElementById('memberZodiac').textContent = '-';
    document.getElementById('memberNakshatra').textContent = '-';
    document.getElementById('memberPlanetImageDisplay').textContent = '-';
    document.getElementById('memberNavamshaImageDisplay').textContent = '-';
  }

  // Display Additional Photos Gallery
  const additionalPhotosContainer = document.getElementById('additionalPhotosContainer');
  if (additionalPhotosContainer && member.additional_photos && member.additional_photos.length > 0) {
    additionalPhotosContainer.innerHTML = '';
    member.additional_photos.forEach((photo, index) => {
      let photoPath = photo.photo_path.trim();
      
      // Normalize photo path
      if (!photoPath.match(/^https?:\/\//i)) {
        photoPath = photoPath.replace(/^\/+/, '');
        if (!photoPath.toLowerCase().startsWith('uploads/') && !photoPath.toLowerCase().startsWith('http')) {
          photoPath = 'uploads/' + photoPath;
        }
        photoPath = BASE_URL + photoPath;
      }
      
      const photoDiv = document.createElement('div');
      photoDiv.style.cssText = 'position: relative; display: inline-block; cursor: pointer;';
      photoDiv.id = 'additional-photo-' + photo.id;
      
      const img = document.createElement('img');
      img.src = photoPath;
      img.alt = 'Additional Photo ' + (index + 1);
      img.style.cssText = 'width: 150px; height: 150px; border-radius: 8px; object-fit: cover; border: 2px solid #ddd; cursor: pointer;';
      img.onclick = function() { window.open(photoPath, '_blank'); };
      
      const deleteBtn = document.createElement('button');
      deleteBtn.type = 'button';
      deleteBtn.style.cssText = 'position: absolute; top: 5px; right: 5px; background: #ef4444; color: white; border: none; border-radius: 50%; width: 28px; height: 28px; cursor: pointer; font-size: 16px; display: flex; align-items: center; justify-content: center; padding: 0;';
      deleteBtn.title = 'Delete photo';
      deleteBtn.innerHTML = '<i class="fas fa-times"></i>';
      deleteBtn.onclick = function(e) {
        e.preventDefault();
        e.stopPropagation();
        deleteAdditionalPhoto(photo.id, member.id, this);
      };
      
      photoDiv.appendChild(img);
      photoDiv.appendChild(deleteBtn);
      additionalPhotosContainer.appendChild(photoDiv);
    });
  } else if (additionalPhotosContainer) {
    additionalPhotosContainer.innerHTML = '<em style="color: #999; font-size: 14px;">No additional photos yet</em>';
  }

  // Education list
  const eduContainer = document.getElementById('memberEducationList');
  if (eduContainer) {
    eduContainer.innerHTML = '';
    if (member.education && member.education.length > 0) {
      member.education.forEach(ed => {
        const div = document.createElement('div');
        div.style.marginBottom = '8px';
        div.innerHTML = `<strong>${ed.level || ''}</strong>: ${ed.school_or_institute || ''}${ed.stream_or_degree ? ' - '+ed.stream_or_degree : ''}${ed.field ? ' ('+ed.field+')' : ''}${ed.start_year ? ' ['+ed.start_year : ''}${ed.end_year ? ' - '+ed.end_year+']' : (ed.start_year?']':'')}`;
        eduContainer.appendChild(div);
      });
    } else {
      eduContainer.innerHTML = '<em>No education records</em>';
    }
  }

  // Populate missing personal fields
  const elSet = (id, value) => {
    const e = document.getElementById(id);
    if (e) e.textContent = value || '-';
  };
  elSet('memberGenderDisplay', member.gender || '-');
  elSet('memberDobDisplay', member.dob || '-');
  elSet('memberLanguageDisplay', member.language || '-');
  elSet('memberProfessionDisplay', sanitizeVal(member.profession) || '-');
  elSet('memberPhoneDisplay', sanitizeVal(member.phone) || '-');

  // Address fields
  const setDisplay = (id, value) => { const e = document.getElementById(id); if (e) e.textContent = value || '-'; };
  setDisplay('memberPresentAddressDisplay', member.present_address || '-');
  setDisplay('memberCityDisplay', member.city || '-');
  setDisplay('memberZipDisplay', member.zip || '-');
  setDisplay('memberPermanentAddressDisplay', member.permanent_address || '-');
  setDisplay('memberPermanentCityDisplay', member.permanent_city || '-');

  // Show modal after all content is updated
  setTimeout(() => {
    modal.style.display = 'block';
    console.log('Modal displayed with member:', member.name); // Debug log
    
    // Adjust modal for mobile if needed
    if (window.innerWidth <= 768) {
      const modalContent = modal.querySelector('.modal-content');
      modalContent.style.margin = '20px auto';
      modalContent.style.maxHeight = '90vh';
      modalContent.style.overflowY = 'auto';
    }
  }, 100);

  // Wire Edit button to open Add Member modal pre-filled (UI-only)
  const editBtn = document.getElementById('editMemberBtn');
  if (editBtn) {
    editBtn.dataset.memberId = member.id;
    editBtn.onclick = function() {
      prefillAddMemberForm(member.id);
    };
  }

  // Close modal functionality
  closeBtn.onclick = function() {
    modal.style.display = 'none';
  };

  window.onclick = function(event) {
    if (event.target === modal) {
      modal.style.display = 'none';
    }
  };
}

// Mobile-specific helper functions
function isMobileDevice() {
  return window.innerWidth <= 768;
}

// Sanitize JS values to prevent 'null' string from appearing
function sanitizeVal(v) {
  if (v === null || v === undefined) return '';
  if (typeof v === 'string') {
    var t = v.trim();
    if (t === '') return '';
    var low = t.toLowerCase();
    if (low === 'null' || low === 'undefined') return '';
    return t;
  }
  return v;
}

// Set select element value robustly: try exact match, then case-insensitive match on value or text
function setSelectValue(sel, value) {
  if (!sel) return;
  var val = sanitizeVal(value);
  if (!val) { sel.value = ''; return; }
  // Try exact match
  for (var i = 0; i < sel.options.length; i++) {
    var opt = sel.options[i];
    if (opt.value === val) { sel.value = val; return; }
  }
  // case-insensitive value
  for (var i = 0; i < sel.options.length; i++) {
    var opt = sel.options[i];
    if (opt.value && opt.value.toLowerCase() === val.toLowerCase()) { sel.value = opt.value; return; }
  }
  // case-insensitive text
  for (var i = 0; i < sel.options.length; i++) {
    var opt = sel.options[i];
    if (opt.textContent && opt.textContent.toLowerCase() === val.toLowerCase()) { sel.value = opt.value; return; }
  }
  // partial matches (value contains val)
  for (var i = 0; i < sel.options.length; i++) {
    var opt = sel.options[i];
    if (opt.value && opt.value.toLowerCase().indexOf(val.toLowerCase()) !== -1) { sel.value = opt.value; return; }
    if (opt.textContent && opt.textContent.toLowerCase().indexOf(val.toLowerCase()) !== -1) { sel.value = opt.value; return; }
  }
  // Nothing matched; leave it blank
  sel.value = '';
}

function isTabletDevice() {
  return window.innerWidth <= 992 && window.innerWidth > 768;
}

function isDesktopDevice() {
  return window.innerWidth > 992;
}

// Prevent zoom on input focus for mobile (improves UX)
document.addEventListener('DOMContentLoaded', function() {
  if (isMobileDevice()) {
    const inputs = document.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
      input.addEventListener('focus', function() {
        // Add a small delay to prevent immediate zoom
        setTimeout(() => {
          this.style.fontSize = '16px';
        }, 100);
      });
    });
  }
});

// Handle touch events for better mobile experience
document.addEventListener('touchstart', function() {}, { passive: true });

// Prevent default touch behaviors that might interfere
document.addEventListener('touchmove', function(e) {
  // Allow scrolling in modals
  const modals = document.querySelectorAll('.modal-content');
  let inModal = false;
  modals.forEach(modal => {
    if (modal.contains(e.target)) {
      inModal = true;
    }
  });
  
  if (!inModal) {
    e.preventDefault();
  }
}, { passive: false });

// Delete additional photo
function deleteAdditionalPhoto(photoId, memberId, button) {
  if (!confirm('Are you sure you want to delete this photo?')) return;
  
  const formData = new FormData();
  formData.append('action', 'delete_additional_photo');
  formData.append('photo_id', photoId);
  formData.append('member_id', memberId);
  
  button.disabled = true;
  button.style.opacity = '0.5';
  button.style.pointerEvents = 'none';
  
  fetch('members.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        // Find the photo container and remove it
        let photoDiv = button.closest('div[style*="position: relative"]');
        if (photoDiv) {
          photoDiv.style.opacity = '0.5';
          setTimeout(() => {
            photoDiv.remove();
          }, 300);
          alert('Photo deleted successfully');
        } else {
          alert('Photo deleted successfully');
          location.reload();
        }
      } else {
        alert('Delete failed: ' + (d.message || 'Unknown error'));
        button.disabled = false;
        button.style.opacity = '1';
        button.style.pointerEvents = 'auto';
      }
    })
    .catch(e => {
      alert('Error: ' + e.message);
      button.disabled = false;
      button.style.opacity = '1';
      button.style.pointerEvents = 'auto';
    });
}

// Prefill Add Member form for editing (UI-only)
function prefillAddMemberForm(memberId) {
  const members = JSON.parse(<?php echo json_encode(json_encode($members)); ?>);
  const member = members.find(m => parseInt(m.id) === parseInt(memberId));
  if (!member) { alert('Member not found for edit'); return; }

  const addMemberModal = document.getElementById('addMemberModal');
  const form = document.getElementById('addMemberForm');
  if (!form || !addMemberModal) return;

  // Hide the details modal (if open) so the edit modal appears in front
  const detailsModal = document.getElementById('memberDetailsModal');
  if (detailsModal && detailsModal.style.display === 'block') {
    detailsModal.style.display = 'none';
  }


  // Set hidden edit id so server can detect (note: no server-side update implemented)
  const editId = document.getElementById('edit_member_id');
  if (editId) editId.value = member.id;

  // Fill simple fields by id
  try {
    document.getElementById('memberNameInput').value = sanitizeVal(member.name);
    setSelectValue(document.getElementById('memberGender'), member.gender);
    document.getElementById('memberDob').value = sanitizeVal(member.dob);
    setSelectValue(document.getElementById('memberReligion'), member.religion);
    setSelectValue(document.getElementById('memberMaritalStatus'), member.marital_status);
    setSelectValue(document.getElementById('memberLookingFor'), member.looking_for);
    setSelectValue(document.getElementById('memberLanguage'), member.language);
    document.getElementById('memberProfession').value = sanitizeVal(member.profession);
    document.getElementById('memberCountry').value = sanitizeVal(member.country);
    // Set phone number using intl-tel-input if available
    if (typeof iti !== 'undefined' && iti && sanitizeVal(member.phone)) {
      try { iti.setNumber(sanitizeVal(member.phone)); } catch (err) { document.getElementById('memberPhone').value = sanitizeVal(member.phone); }
    } else {
      document.getElementById('memberPhone').value = sanitizeVal(member.phone);
    }
    setSelectValue(document.getElementById('memberSmoking'), member.smoking);
    setSelectValue(document.getElementById('memberDrinking'), member.drinking);
    document.getElementById('memberPresentAddress').value = sanitizeVal(member.present_address);
    document.getElementById('memberCity').value = sanitizeVal(member.city);
    document.getElementById('memberZip').value = sanitizeVal(member.zip);
    document.getElementById('memberPermanentAddress').value = sanitizeVal(member.permanent_address);
    document.getElementById('memberPermanentCity').value = sanitizeVal(member.permanent_city);
  } catch (e) {
    console.warn('Some form fields were not found while prefilling:', e);
  }

  // Debug: log what the form will submit for partner_marital_status
  const formEl = document.getElementById('addMemberForm');
  if (formEl && !formEl.dataset.submitDebugRegistered) {
    formEl.dataset.submitDebugRegistered = '1';
    formEl.addEventListener('submit', function(ev) {
      try {
        const fd = new FormData(formEl);
        console.log('Submitting partner_marital_status:', fd.get('partner_marital_status'));
      } catch (err) { console.warn('Error logging form data:', err); }
    });
  }

  // Fill physical info by name selectors when present
  const setByName = (name, value) => {
    const el = document.querySelector('[name="' + name + '"]');
    if (!el) return;
    if (el.tagName && el.tagName.toLowerCase() === 'select') {
      setSelectValue(el, value);
    } else {
      el.value = sanitizeVal(value);
    }
  };
  setByName('complexion', member.complexion || '');
  setByName('height', member.height_cm || member.height || '');
  setByName('weight', member.weight_kg || member.weight || '');
  setByName('blood_group', member.blood_group || '');
  setByName('eye_color', member.eye_color || '');
  setByName('hair_color', member.hair_color || '');
  setByName('disability', member.disability || '');

  // Family info
  setByName('father_name', (member.family && member.family.father_name) ? member.family.father_name : '');
  setByName('father_profession', (member.family && member.family.father_profession) ? member.family.father_profession : '');
  setByName('father_contact', (member.family && member.family.father_contact) ? member.family.father_contact : '');
  setByName('mother_name', (member.family && member.family.mother_name) ? member.family.mother_name : '');
  setByName('mother_profession', (member.family && member.family.mother_profession) ? member.family.mother_profession : '');
  setByName('mother_contact', (member.family && member.family.mother_contact) ? member.family.mother_contact : '');
  setByName('brothers', (member.family && (member.family.brothers_count !== undefined)) ? member.family.brothers_count : (member.brothers || ''));
  setByName('sisters', (member.family && (member.family.sisters_count !== undefined)) ? member.family.sisters_count : (member.sisters || ''));

  // Partner expectations
  console.log('Prefill partner values (country, marital, religion, smoking, drinking):', (member.partner || {}));
  setByName('partner_country', (member.partner && member.partner.preferred_country) ? member.partner.preferred_country : '');
  setByName('min_age', (member.partner && (member.partner.min_age !== undefined)) ? member.partner.min_age : '');
  setByName('max_age', (member.partner && (member.partner.max_age !== undefined)) ? member.partner.max_age : '');
  setByName('min_height', (member.partner && (member.partner.min_height !== undefined)) ? member.partner.min_height : '');
  setByName('max_height', (member.partner && (member.partner.max_height !== undefined)) ? member.partner.max_height : '');
  setByName('partner_marital_status', (member.partner && member.partner.marital_status) ? member.partner.marital_status : '');
  // Also set directly by id if present (robustness)
  const pmsEl = document.getElementById('partnerMaritalStatus');
  if (pmsEl) setSelectValue(pmsEl, (member.partner && member.partner.marital_status) ? member.partner.marital_status : '');
  if (pmsEl) {
    pmsEl.addEventListener('change', function(){ console.log('Partner marital select changed to:', this.value); });
  }
  setByName('partner_religion', (member.partner && member.partner.religion) ? member.partner.religion : '');
  setByName('partner_smoking', (member.partner && member.partner.smoking) ? member.partner.smoking : '');
  setByName('partner_drinking', (member.partner && member.partner.drinking) ? member.partner.drinking : '');

  // Horoscope
  setByName('birth_date', (member.horoscope && member.horoscope.birth_date) ? member.horoscope.birth_date : '');
  setByName('birth_time', (member.horoscope && member.horoscope.birth_time) ? member.horoscope.birth_time : '');
  setByName('zodiac', (member.horoscope && member.horoscope.zodiac) ? member.horoscope.zodiac : '');
  setByName('nakshatra', (member.horoscope && member.horoscope.nakshatra) ? member.horoscope.nakshatra : '');
  setByName('karmic_debt', (member.horoscope && member.horoscope.karmic_debt) ? member.horoscope.karmic_debt : '');
  
  // Display existing planet and navamsha images in edit form
  if (member.horoscope) {
    // Show existing planet image if available
    if (member.horoscope.planet_image && member.horoscope.planet_image.trim() !== '') {
      let planetPath = member.horoscope.planet_image.trim();
      if (!planetPath.match(/^https?:\/\//i)) {
        planetPath = planetPath.replace(/^\/+/, '');
        if (!planetPath.toLowerCase().startsWith('uploads/') && !planetPath.toLowerCase().startsWith('http')) {
          planetPath = 'uploads/' + planetPath;
        }
        planetPath = BASE_URL + planetPath;
      }
      const planetPreview = document.createElement('div');
      planetPreview.style.marginTop = '8px';
      planetPreview.innerHTML = `<small>Current Image:</small><br><img src="${planetPath}" style="max-width:100px; max-height:100px; border-radius:4px; margin-top:4px;" alt="Planet">`;
      const planetInput = document.querySelector('input[name="planet_image"]');
      if (planetInput && planetInput.parentElement) {
        planetInput.parentElement.appendChild(planetPreview);
      }
    }
    
    // Show existing navamsha image if available
    if (member.horoscope.navamsha_image && member.horoscope.navamsha_image.trim() !== '') {
      let navamshaPath = member.horoscope.navamsha_image.trim();
      if (!navamshaPath.match(/^https?:\/\//i)) {
        navamshaPath = navamshaPath.replace(/^\/+/, '');
        if (!navamshaPath.toLowerCase().startsWith('uploads/') && !navamshaPath.toLowerCase().startsWith('http')) {
          navamshaPath = 'uploads/' + navamshaPath;
        }
        navamshaPath = BASE_URL + navamshaPath;
      }
      const navamshaPreview = document.createElement('div');
      navamshaPreview.style.marginTop = '8px';
      navamshaPreview.innerHTML = `<small>Current Image:</small><br><img src="${navamshaPath}" style="max-width:100px; max-height:100px; border-radius:4px; margin-top:4px;" alt="Navamsha">`;
      const navamshaInput = document.querySelector('input[name="navamsha_image"]');
      if (navamshaInput && navamshaInput.parentElement) {
        navamshaInput.parentElement.appendChild(navamshaPreview);
      }
    }
  }

  // Display existing additional photos in edit form
  if (member.additional_photos && member.additional_photos.length > 0) {
    const previewContainer = document.getElementById('additionalPhotosPreview');
    if (previewContainer) {
      // Clear all previews (existing and new)
      previewContainer.innerHTML = '';
      
      // Display existing photos with delete option
      member.additional_photos.forEach((photo, index) => {
        let photoPath = photo.photo_path.trim();
        if (!photoPath.match(/^https?:\/\//i)) {
          photoPath = photoPath.replace(/^\/+/, '');
          if (!photoPath.toLowerCase().startsWith('uploads/') && !photoPath.toLowerCase().startsWith('http')) {
            photoPath = 'uploads/' + photoPath;
          }
          photoPath = BASE_URL + photoPath;
        }
        
        const preview = document.createElement('div');
        preview.style.cssText = 'position: relative; display: inline-block;';
        preview.dataset.photoId = photo.id;
        
        // Create image
        const img = document.createElement('img');
        img.src = photoPath;
        img.style.cssText = 'width: 100px; height: 100px; border-radius: 6px; object-fit: cover; border: 2px solid #4f46e5;';
        
        // Create "Existing" badge
        const badge = document.createElement('span');
        badge.style.cssText = 'position: absolute; top: 3px; right: 3px; background: #4f46e5; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 600;';
        badge.textContent = 'Existing';
        
        // Create delete button for existing photo
        const deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.style.cssText = 'position: absolute; bottom: 3px; right: 3px; background: #ef4444; color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; font-size: 12px; display: flex; align-items: center; justify-content: center; padding: 0; font-weight: bold;';
        deleteBtn.innerHTML = '×';
        deleteBtn.title = 'Remove this existing photo (will be deleted when you save)';
        deleteBtn.onclick = function(e) {
          e.preventDefault();
          e.stopPropagation();
          if (confirm('Remove this photo? It will be deleted when you save.')) {
            preview.style.opacity = '0.5';
            deleteBtn.disabled = true;
            
            // Add to a list of photos to delete
            if (!form.dataset.photosToDelete) {
              form.dataset.photosToDelete = photo.id + '';
            } else {
              form.dataset.photosToDelete += ',' + photo.id;
            }
            
            // Create hidden input for deletion
            let deleteInput = form.querySelector('input[name="delete_additional_photos"]');
            if (!deleteInput) {
              deleteInput = document.createElement('input');
              deleteInput.type = 'hidden';
              deleteInput.name = 'delete_additional_photos';
              form.appendChild(deleteInput);
            }
            deleteInput.value = form.dataset.photosToDelete;
            
            setTimeout(() => {
              preview.remove();
            }, 300);
          }
        };
        
        preview.appendChild(img);
        preview.appendChild(badge);
        preview.appendChild(deleteBtn);
        previewContainer.appendChild(preview);
      });
    }
  } else {
    // Clear preview if no existing photos
    const previewContainer = document.getElementById('additionalPhotosPreview');
    if (previewContainer) {
      previewContainer.innerHTML = '';
    }
  }

  // Clear file inputs (remove any additional file input fields, keep only the initial one)
  const inputContainer = document.getElementById('additionalPhotosInputContainer');
  if (inputContainer) {
    const inputs = inputContainer.querySelectorAll('div[data-input-id]');
    inputs.forEach(input => input.remove());
    
    // Also clear the initial file input
    const initialInput = document.getElementById('initialAdditionalPhoto');
    if (initialInput) {
      initialInput.value = '';
    }
  }

  // Education: clear existing entries and populate from member.education array
  try {
    const eduList = document.getElementById('educationList');
    if (eduList) {
      // Remove all existing children
      eduList.innerHTML = '';
      const educations = member.education && Array.isArray(member.education) ? member.education : [];
      if (educations.length === 0) {
        // create an empty template (same structure as original)
        eduList.innerHTML = `
          <div class="form-row education-row">
            <div class="form-column"><div class="form-group"><label>Institute</label><input name="institute[]" type="text" /></div></div>
            <div class="form-column"><div class="form-group"><label>Degree/Stream</label><input name="degree[]" type="text" /></div></div>
            <div class="form-column"><div class="form-group"><label>Field</label><input name="field[]" type="text" /></div></div>
          </div>
          <div class="form-row">
            <div class="form-column"><div class="form-group"><label>Reg Number</label><input name="regnum[]" type="text" /></div></div>
            <div class="form-column"><div class="form-group"><label>Start Year</label><input name="startyear[]" type="number" /></div></div>
            <div class="form-column"><div class="form-group"><label>End Year</label><input name="endyear[]" type="number" /></div></div>
          </div>`;
      } else {
        educations.forEach(ed => {
          const part1 = document.createElement('div');
          part1.className = 'form-row education-row';
          part1.innerHTML = `<div class="form-column"><div class="form-group"><label>Institute</label><input name="institute[]" type="text" value="${(ed.school_or_institute||'').replace(/"/g,'&quot;')}" /></div></div>
                              <div class="form-column"><div class="form-group"><label>Degree/Stream</label><input name="degree[]" type="text" value="${(ed.stream_or_degree||'').replace(/"/g,'&quot;')}" /></div></div>
                              <div class="form-column"><div class="form-group"><label>Field</label><input name="field[]" type="text" value="${(ed.field||'').replace(/"/g,'&quot;')}" /></div></div>`;

          const part2 = document.createElement('div');
          part2.className = 'form-row';
          part2.innerHTML = `<div class="form-column"><div class="form-group"><label>Reg Number</label><input name="regnum[]" type="text" value="${(ed.reg_number||'').replace(/"/g,'&quot;')}" /></div></div>
                              <div class="form-column"><div class="form-group"><label>Start Year</label><input name="startyear[]" type="number" value="${ed.start_year||''}" /></div></div>
                              <div class="form-column"><div class="form-group"><label>End Year</label><input name="endyear[]" type="number" value="${ed.end_year||''}" /></div></div>`;

          eduList.appendChild(part1);
          eduList.appendChild(part2);
        });
      }
    }
  } catch (e) {
    console.warn('Failed to populate education entries for edit:', e);
  }

  // Update submit button label to indicate edit (UI only)
  const submitBtn = form.querySelector('button[name="add_member"]');
  if (submitBtn) submitBtn.innerHTML = '<i class="fas fa-save"></i> Update Member';

  // Load branches and then open modal so the branch dropdown can be populated and selected
  loadBranches(member.branch_id).then(() => {
      // Disable branch field for edit mode
      const branchField = document.getElementById('memberBranch');
      if (branchField) branchField.disabled = true;
    addMemberModal.style.display = 'block';
    // For mobile, ensure modal sizing
    adjustModalsForMobile();
  }).catch(() => {
    // Fallback - still open modal even if branches failed to load
      const branchField = document.getElementById('memberBranch');
      if (branchField) branchField.disabled = true;
    addMemberModal.style.display = 'block';
    adjustModalsForMobile();
  });
}
</script>


</body>
</html>