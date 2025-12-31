<?php
/**
 * Session Verification for Protected Pages
 * 
 * Include this at the TOP of any protected page (after session_start)
 * to verify the user's IP is still approved (only if they logged in from an approved IP).
 * 
 * Distinguishes between two login methods:
 * - approved_ip: Staff logged in directly from an approved IP (need to verify IP still exists)
 * - pending_approval: Staff was approved after pending request (no IP verification needed)
 * 
 * If a staff member's IP was removed from approved_ips by admin, they will be logged out.
 */

// Only check for staff logins from APPROVED IPs, not admins or pending approval logins
if (isset($_SESSION['staff_id']) && 
    isset($_SESSION['login_method']) && 
    $_SESSION['login_method'] === 'approved_ip' && 
    isset($_SESSION['login_ip']) && 
    $_SESSION['role'] !== 'admin') {
    
    // Get current IP
    $current_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
    $session_ip = $_SESSION['login_ip'];
    
    // Only verify if user is accessing from the same IP they logged in from
    if ($current_ip === $session_ip) {
        
        // Connect to database
        $conn = new mysqli(
            "localhost",
            "thennilavu_matrimonial",
            "OYVuiEKfS@FQ",
            "thennilavu_thennilavu"
        );
        
        if (!$conn->connect_error) {
            // Verify that this exact session is still registered as an approved session
            $sess_id = session_id();
            $stmt = $conn->prepare("SELECT session_id FROM approved_sessions WHERE session_id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $sess_id);
                $stmt->execute();
                $stmt->store_result();

                // If session record not found, treat as invalidated and log out
                if ($stmt->num_rows === 0) {
                    $stmt->close();
                    $conn->close();

                    // Log the forced logout
                    $staff_id = $_SESSION['staff_id'];
                    $logDir = __DIR__ . DIRECTORY_SEPARATOR . 'logs';
                    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
                    $event = [
                        'type' => 'auto_logout',
                        'staff_id' => $staff_id,
                        'ip' => $current_ip,
                        'reason' => 'approved session removed',
                        'time' => date('c')
                    ];
                    @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'staff_events.log', json_encode($event) . "\n", FILE_APPEND | LOCK_EX);

                    // Destroy session and redirect to login with message
                    session_destroy();
                    header('Location: login.php?msg=ip_removed');
                    exit;
                }

                $stmt->close();
            }
            $conn->close();
        }
    }
}

