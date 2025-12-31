<?php
session_start();
if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

// Database connection
$host = "localhost";
$user = "thennilavu_matrimonial";
$pass = "OYVuiEKfS@FQ";
$db = "thennilavu_thennilavu";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: ".$conn->connect_error);

$format = $_GET['format'] ?? 'pdf';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Build query with date filter
$date_condition = "";
$period_text = "All Time";
if($start_date && $end_date) {
    $date_condition = " AND up.start_date BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";
    $period_text = "From $start_date to $end_date";
}

// Query for active packages
$active_query = "
    SELECT up.*, u.username, u.email, p.price, p.name as package_name
    FROM userpackage up 
    JOIN users u ON up.user_id = u.id 
    LEFT JOIN packages p ON up.status = p.name
    WHERE up.requestPackage = 'accept' 
    AND up.end_date > NOW()
    $date_condition
    ORDER BY up.start_date DESC
";

// Query for expired packages  
$expired_query = "
    SELECT up.*, u.username, u.email, p.price, p.name as package_name
    FROM userpackage up 
    JOIN users u ON up.user_id = u.id 
    LEFT JOIN packages p ON up.status = p.name
    WHERE up.requestPackage = 'accept' 
    AND up.end_date <= NOW()
    $date_condition
    ORDER BY up.end_date DESC
";

$active_result = $conn->query($active_query);
$expired_result = $conn->query($expired_query);

if($format === 'excel') {
    // Generate Excel file using HTML table format
    $filename = 'earnings_report_'.date('Y-m-d_H-i-s').'.xls';
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<style>';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th, td { border: 1px solid #000; padding: 8px; text-align: left; }';
    echo 'th { background-color: #f2f2f2; font-weight: bold; }';
    echo '.total { background-color: #e8f5e9; font-weight: bold; }';
    echo '.header { background-color: #4a90e2; color: white; text-align: center; padding: 10px; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<div class="header">';
    echo '<h1>Matrimony Earnings Report</h1>';
    echo '<p>Generated on: '.date('Y-m-d H:i:s').'</p>';
    echo '<p>Period: '.$period_text.'</p>';
    echo '</div>';
    
    // Active Packages Table
    echo '<h3 style="color: #28a745; margin-top: 20px;">ACTIVE PACKAGES</h3>';
    echo '<table>';
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>User Name</th>';
    echo '<th>Email</th>';
    echo '<th>Package Name</th>';
    echo '<th>Price ($)</th>';
    echo '<th>Duration (Days)</th>';
    echo '<th>Start Date</th>';
    echo '<th>End Date</th>';
    echo '<th>Days Remaining</th>';
    echo '</tr>';
    
    $total_earnings = 0;
    $active_count = 0;
    
    while($row = $active_result->fetch_assoc()) {
        $total_earnings += $row['price'];
        $active_count++;
        
        $end_date_timestamp = strtotime($row['end_date']);
        $current_date = time();
        $days_remaining = ceil(($end_date_timestamp - $current_date) / (60 * 60 * 24));
        
        echo '<tr>';
        echo '<td>'.$row['id'].'</td>';
        echo '<td>'.htmlspecialchars($row['username']).'</td>';
        echo '<td>'.htmlspecialchars($row['email']).'</td>';
        echo '<td>'.htmlspecialchars($row['package_name']).'</td>';
        echo '<td>'.number_format($row['price'], 2).'</td>';
        echo '<td>'.$row['duration'].'</td>';
        echo '<td>'.date('Y-m-d H:i', strtotime($row['start_date'])).'</td>';
        echo '<td>'.date('Y-m-d H:i', strtotime($row['end_date'])).'</td>';
        echo '<td>'.$days_remaining.' days</td>';
        echo '</tr>';
    }
    echo '</table>';
    
    // Expired Packages Table
    echo '<h3 style="color: #dc3545; margin-top: 30px;">EXPIRED PACKAGES</h3>';
    echo '<table>';
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>User Name</th>';
    echo '<th>Email</th>';
    echo '<th>Package Name</th>';
    echo '<th>Price ($)</th>';
    echo '<th>Duration (Days)</th>';
    echo '<th>Start Date</th>';
    echo '<th>End Date</th>';
    echo '<th>Expired Days</th>';
    echo '</tr>';
    
    $expired_count = 0;
    
    while($row = $expired_result->fetch_assoc()) {
        $total_earnings += $row['price'];
        $expired_count++;
        
        $end_date_timestamp = strtotime($row['end_date']);
        $current_date = time();
        $expired_days = ceil(($current_date - $end_date_timestamp) / (60 * 60 * 24));
        
        echo '<tr>';
        echo '<td>'.$row['id'].'</td>';
        echo '<td>'.htmlspecialchars($row['username']).'</td>';
        echo '<td>'.htmlspecialchars($row['email']).'</td>';
        echo '<td>'.htmlspecialchars($row['package_name']).'</td>';
        echo '<td>'.number_format($row['price'], 2).'</td>';
        echo '<td>'.$row['duration'].'</td>';
        echo '<td>'.date('Y-m-d H:i', strtotime($row['start_date'])).'</td>';
        echo '<td>'.date('Y-m-d H:i', strtotime($row['end_date'])).'</td>';
        echo '<td>'.$expired_days.' days ago</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    
    // Summary row
    echo '<h3 style="background-color: #f8f9fa; padding: 15px; margin-top: 20px;">TOTAL SUMMARY</h3>';
    echo '<table>';
    echo '<tr class="total">';
    echo '<td><strong>Total Earnings</strong></td>';
    echo '<td><strong>$'.number_format($total_earnings, 2).'</strong></td>';
    echo '</tr>';
    echo '<tr class="total">';
    echo '<td><strong>Active Packages</strong></td>';
    echo '<td><strong>'.$active_count.'</strong></td>';
    echo '</tr>';
    echo '<tr class="total">';
    echo '<td><strong>Expired Packages</strong></td>';
    echo '<td><strong>'.$expired_count.'</strong></td>';
    echo '</tr>';
    echo '<tr class="total">';
    echo '<td><strong>Total Packages</strong></td>';
    echo '<td><strong>'.($active_count + $expired_count).'</strong></td>';
    echo '</tr>';
    
    echo '</table>';
    echo '</body></html>';
    
} else {
    // Generate PDF using HTML to PDF conversion
    $filename = 'earnings_report_'.date('Y-m-d_H-i-s').'.pdf';
    
    // For better PDF generation, you could use libraries like TCPDF or FPDF
    // For now, using HTML with print-friendly CSS
    header('Content-Type: text/html');
    
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Earnings Report</title>';
    echo '<style>';
    echo '@media print { .no-print { display: none; } }';
    echo 'body { font-family: Arial, sans-serif; margin: 20px; }';
    echo '.header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #4a90e2; padding-bottom: 15px; }';
    echo '.header h1 { color: #4a90e2; margin-bottom: 5px; }';
    echo '.header p { margin: 5px 0; color: #666; }';
    echo 'table { width: 100%; border-collapse: collapse; margin-top: 20px; }';
    echo 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }';
    echo 'th { background-color: #f8f9fa; font-weight: bold; color: #333; }';
    echo '.total { background-color: #e8f5e9; font-weight: bold; }';
    echo '.summary { margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 5px; }';
    echo '.download-btn { background: #4a90e2; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin: 10px 5px; }';
    echo '.download-btn:hover { background: #357abd; }';
    echo '</style>';
    echo '<script>';
    echo 'function downloadPDF() {';
    echo '  window.print();';
    echo '}';
    echo 'function downloadExcel() {';
    echo '  window.location.href = "download_report.php?format=excel&start_date='.$start_date.'&end_date='.$end_date.'";';
    echo '}';
    echo '</script>';
    echo '</head>';
    echo '<body>';
    
    echo '<div class="no-print">';
    echo '<button class="download-btn" onclick="downloadPDF()">🖨️ Print/Save as PDF</button>';
    echo '<button class="download-btn" onclick="downloadExcel()">📊 Download Excel</button>';
    echo '<a href="total-earnings.php" class="download-btn">← Back to Earnings</a>';
    echo '</div>';
    
    echo '<div class="header">';
    echo '<h1>💰 Matrimony Earnings Report</h1>';
    echo '<p><strong>Generated on:</strong> '.date('Y-m-d H:i:s').'</p>';
    echo '<p><strong>Report Period:</strong> '.$period_text.'</p>';
    echo '</div>';
    
    // Summary statistics
    $total_earnings = 0;
    $active_count = 0;
    $expired_count = 0;
    
    // Calculate totals from both result sets
    $temp_active = $conn->query($active_query);
    while($row = $temp_active->fetch_assoc()) {
        $total_earnings += $row['price'];
        $active_count++;
    }
    
    $temp_expired = $conn->query($expired_query);
    while($row = $temp_expired->fetch_assoc()) {
        $total_earnings += $row['price'];
        $expired_count++;
    }
    
    echo '<div class="summary">';
    echo '<h3>📊 Summary Statistics</h3>';
    echo '<p><strong>Total Earnings:</strong> $'.number_format($total_earnings, 2).'</p>';
    echo '<p><strong>Total Packages:</strong> '.($active_count + $expired_count).'</p>';
    echo '<p><strong>Active Packages:</strong> '.$active_count.'</p>';
    echo '<p><strong>Expired Packages:</strong> '.$expired_count.'</p>';
    echo '</div>';
    
    // Active Packages Table
    echo '<h3 style="color: #28a745; margin-top: 30px; border-bottom: 2px solid #28a745; padding-bottom: 10px;">✅ ACTIVE PACKAGES</h3>';
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>User Name</th>';
    echo '<th>Email</th>';
    echo '<th>Package</th>';
    echo '<th>Price</th>';
    echo '<th>Duration</th>';
    echo '<th>Start Date</th>';
    echo '<th>End Date</th>';
    echo '<th>Days Remaining</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    $active_result = $conn->query($active_query);
    while($row = $active_result->fetch_assoc()) {
        $end_date_timestamp = strtotime($row['end_date']);
        $current_date = time();
        $days_remaining = ceil(($end_date_timestamp - $current_date) / (60 * 60 * 24));
        
        echo '<tr>';
        echo '<td>'.$row['id'].'</td>';
        echo '<td>'.htmlspecialchars($row['username']).'</td>';
        echo '<td>'.htmlspecialchars($row['email']).'</td>';
        echo '<td>'.htmlspecialchars($row['package_name']).'</td>';
        echo '<td>$'.number_format($row['price'], 2).'</td>';
        echo '<td>'.$row['duration'].' days</td>';
        echo '<td>'.date('Y-m-d H:i', strtotime($row['start_date'])).'</td>';
        echo '<td>'.date('Y-m-d H:i', strtotime($row['end_date'])).'</td>';
        echo '<td style="color: #28a745; font-weight: bold;">'.$days_remaining.' days</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
    
    // Expired Packages Table
    echo '<h3 style="color: #dc3545; margin-top: 30px; border-bottom: 2px solid #dc3545; padding-bottom: 10px;">⏰ EXPIRED PACKAGES</h3>';
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>User Name</th>';
    echo '<th>Email</th>';
    echo '<th>Package</th>';
    echo '<th>Price</th>';
    echo '<th>Duration</th>';
    echo '<th>Start Date</th>';
    echo '<th>End Date</th>';
    echo '<th>Expired Days</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    $expired_result = $conn->query($expired_query);
    while($row = $expired_result->fetch_assoc()) {
        $end_date_timestamp = strtotime($row['end_date']);
        $current_date = time();
        $expired_days = ceil(($current_date - $end_date_timestamp) / (60 * 60 * 24));
        
        echo '<tr>';
        echo '<td>'.$row['id'].'</td>';
        echo '<td>'.htmlspecialchars($row['username']).'</td>';
        echo '<td>'.htmlspecialchars($row['email']).'</td>';
        echo '<td>'.htmlspecialchars($row['package_name']).'</td>';
        echo '<td>$'.number_format($row['price'], 2).'</td>';
        echo '<td>'.$row['duration'].' days</td>';
        echo '<td>'.date('Y-m-d H:i', strtotime($row['start_date'])).'</td>';
        echo '<td>'.date('Y-m-d H:i', strtotime($row['end_date'])).'</td>';
        echo '<td style="color: #dc3545; font-weight: bold;">'.$expired_days.' days ago</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
    
    // Total Summary
    echo '<div style="margin-top: 30px; padding: 20px; background-color: #f8f9fa; border-radius: 8px; border-left: 4px solid #4a90e2;">';
    echo '<h3 style="color: #4a90e2; margin-bottom: 15px;">🎯 TOTAL SUMMARY</h3>';
    echo '<p><strong>Total Earnings:</strong> $'.number_format($total_earnings, 2).'</p>';
    echo '<p><strong>Active Packages:</strong> '.$active_count.'</p>';
    echo '<p><strong>Expired Packages:</strong> '.$expired_count.'</p>';
    echo '<p><strong>Total Packages:</strong> '.($active_count + $expired_count).'</p>';
    echo '</div>';
    
    echo '<div style="margin-top: 30px; padding: 15px; background-color: #f8f9fa; border-left: 4px solid #4a90e2; font-size: 12px; color: #666;">';
    echo '<p><strong>Report Generated by:</strong> Matrimony Admin System</p>';
    echo '<p><strong>Generated on:</strong> '.date('Y-m-d H:i:s').'</p>';
    echo '<p><strong>Administrator:</strong> '.htmlspecialchars($_SESSION['name']).'</p>';
    echo '</div>';
    
    echo '</body></html>';
}

$conn->close();
?>