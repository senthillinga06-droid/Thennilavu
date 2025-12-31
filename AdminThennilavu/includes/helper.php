<?php
/**
 * Parses a date-time string into a local time format
 */
function parse_dt_local($dt_str) {
    if (empty($dt_str)) return '';
    $date = new DateTime($dt_str);
    return $date->format('Y-m-d H:i:s');
}

/**
 * Outputs JSON response and exits
 */
function json_out($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Sends an email to staff members
 */
function send_email_to_staff($conn, $subject, $html, &$error = null) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        $mail->isSMTP();
        // Get staff emails from database
        $result = $conn->query("SELECT email FROM staff WHERE status = 'active'");
        if (!$result) {
            throw new Exception("Failed to fetch staff emails");
        }
        
        while ($row = $result->fetch_assoc()) {
            $mail->addAddress($row['email']);
        }
        
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->isHTML(true);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        $error = $e->getMessage();
        return false;
    }
}
?>