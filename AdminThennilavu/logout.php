<?php
session_start();

// If a staff member is logged in, append a logout event to the server-side log file (no DB writes)
if (isset($_SESSION['staff_id'])) {
	$staff_id = (int)$_SESSION['staff_id'];
	try {
		$logDir = __DIR__ . DIRECTORY_SEPARATOR . 'logs';
		if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
		$logFile = $logDir . DIRECTORY_SEPARATOR . 'staff_events.log';
		$event = [
			'type' => 'logout',
			'staff_id' => $staff_id,
			'time' => date('c')
		];
		@file_put_contents($logFile, json_encode($event) . "\n", FILE_APPEND | LOCK_EX);
	} catch (Exception $e) {
		// ignore logging errors
	}
}

// Destroy session and redirect to login
$_SESSION = [];
session_destroy();
header("Location: login.php");
exit;
?>