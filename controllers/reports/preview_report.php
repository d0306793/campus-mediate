<?php
session_start();
include "../../config/config.php";
include "../../includes/functions.php";

if (!isset($_SESSION['email'])) {
    echo "<p>Access denied</p>";
    exit();
}

$manager_id = getUserIdFromEmail($conn, $_SESSION['email']);
$report_type = $_POST['report_type'] ?? '';
$date_from = $_POST['date_from'] ?? '';
$date_to = $_POST['date_to'] ?? '';
$hostel_id = $_POST['hostel_id'] ?? 0;

if (!$report_type || !$hostel_id) {
    echo "<p>Invalid parameters</p>";
    exit();
}

// Set date range if not provided
if (!$date_from) $date_from = date('Y-m-01'); // First day of current month
if (!$date_to) $date_to = date('Y-m-d'); // Today

echo "<div class='report-header'>";
echo "<h4>" . ucfirst($report_type) . " Report</h4>";
echo "<p>Period: " . date('M d, Y', strtotime($date_from)) . " to " . date('M d, Y', strtotime($date_to)) . "</p>";
echo "</div>";

switch ($report_type) {
    case 'bookings':
        $stmt = $conn->prepare("
            SELECT b.*, COALESCE(u.full_name, u.username) as student_name, 
                   u.email, r.room_type, r.price_per_semester
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            JOIN rooms r ON b.room_id = r.id
            WHERE b.hostel_id = ? AND DATE(b.booking_date) BETWEEN ? AND ?
            ORDER BY b.booking_date DESC
        ");
        $stmt->bind_param("iss", $hostel_id, $date_from, $date_to);
        $stmt->execute();
        $result = $stmt->get_result();
        
        echo "<table class='preview-table'>";
        echo "<tr><th>Student Name</th><th>Room Type</th><th>Check-in</th><th>Status</th><th>Amount</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['student_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['room_type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['check_in_date']) . "</td>";
            echo "<td>" . htmlspecialchars($row['status']) . "</td>";
            echo "<td>" . number_format($row['price_per_semester'], 2) . " UGX</td>";
            echo "</tr>";
        }
        echo "</table>";
        break;
        
    case 'payments':
        $stmt = $conn->prepare("
            SELECT p.*, COALESCE(u.full_name, u.username) as student_name, 
                   u.email, r.room_type
            FROM payments p
            JOIN bookings b ON p.booking_id = b.id
            JOIN users u ON p.user_id = u.id
            JOIN rooms r ON b.room_id = r.id
            WHERE p.hostel_id = ? AND DATE(p.payment_date) BETWEEN ? AND ?
            ORDER BY p.payment_date DESC
        ");
        $stmt->bind_param("iss", $hostel_id, $date_from, $date_to);
        $stmt->execute();
        $result = $stmt->get_result();
        
        echo "<table class='preview-table'>";
        echo "<tr><th>Student Name</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr>";
        $total = 0;
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['student_name']) . "</td>";
            echo "<td>" . number_format($row['amount'], 2) . " UGX</td>";
            echo "<td>" . htmlspecialchars($row['payment_method']) . "</td>";
            echo "<td>" . htmlspecialchars($row['status']) . "</td>";
            echo "<td>" . date('M d, Y', strtotime($row['payment_date'])) . "</td>";
            echo "</tr>";
            if ($row['status'] == 'Completed') $total += $row['amount'];
        }
        echo "<tr class='total-row'><td colspan='4'><strong>Total Revenue</strong></td><td><strong>" . number_format($total, 2) . " UGX</strong></td></tr>";
        echo "</table>";
        break;
        
    case 'occupancy':
        $stmt = $conn->prepare("
            SELECT r.room_type, r.quantity, r.status,
                   COUNT(b.id) as bookings_count
            FROM rooms r
            LEFT JOIN bookings b ON r.id = b.room_id AND b.status IN ('Confirmed', 'Completed')
            WHERE r.hostel_id = ?
            GROUP BY r.id
        ");
        $stmt->bind_param("i", $hostel_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        echo "<table class='preview-table'>";
        echo "<tr><th>Room Type</th><th>Total Units</th><th>Occupied</th><th>Available</th><th>Occupancy Rate</th></tr>";
        while ($row = $result->fetch_assoc()) {
            $total_units = $row['quantity'] ?? 1;
            $occupied = min($row['bookings_count'], $total_units);
            $available = $total_units - $occupied;
            $rate = $total_units > 0 ? round(($occupied / $total_units) * 100, 1) : 0;
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['room_type']) . "</td>";
            echo "<td>" . $total_units . "</td>";
            echo "<td>" . $occupied . "</td>";
            echo "<td>" . $available . "</td>";
            echo "<td>" . $rate . "%</td>";
            echo "</tr>";
        }
        echo "</table>";
        break;
        
    case 'financial':
        $stmt = $conn->prepare("
            SELECT 
                COUNT(CASE WHEN p.status = 'Completed' THEN 1 END) as completed_payments,
                COUNT(CASE WHEN p.status = 'Pending' THEN 1 END) as pending_payments,
                SUM(CASE WHEN p.status = 'Completed' THEN p.amount ELSE 0 END) as total_revenue,
                SUM(CASE WHEN p.status = 'Pending' THEN p.amount ELSE 0 END) as pending_revenue
            FROM payments p
            WHERE p.hostel_id = ? AND DATE(p.payment_date) BETWEEN ? AND ?
        ");
        $stmt->bind_param("iss", $hostel_id, $date_from, $date_to);
        $stmt->execute();
        $result = $stmt->get_result();
        $financial = $result->fetch_assoc();
        
        echo "<div class='financial-summary'>";
        echo "<div class='summary-item'><h5>Total Revenue</h5><p>" . number_format($financial['total_revenue'], 2) . " UGX</p></div>";
        echo "<div class='summary-item'><h5>Pending Revenue</h5><p>" . number_format($financial['pending_revenue'], 2) . " UGX</p></div>";
        echo "<div class='summary-item'><h5>Completed Payments</h5><p>" . $financial['completed_payments'] . "</p></div>";
        echo "<div class='summary-item'><h5>Pending Payments</h5><p>" . $financial['pending_payments'] . "</p></div>";
        echo "</div>";
        break;
}

echo "<style>
.preview-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
.preview-table th, .preview-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
.preview-table th { background-color: #f2f2f2; }
.total-row { background-color: #f9f9f9; font-weight: bold; }
.financial-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px; }
.summary-item { background: #f8f9fa; padding: 15px; border-radius: 5px; text-align: center; }
.summary-item h5 { margin: 0 0 10px 0; color: #666; }
.summary-item p { margin: 0; font-size: 24px; font-weight: bold; color: #2d3a4c; }
.report-header { margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #eee; }
</style>";
?>