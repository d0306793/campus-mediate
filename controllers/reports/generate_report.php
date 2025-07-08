<?php
session_start();
include "../../config/config.php";
include "../../includes/functions.php";

if (!isset($_SESSION['email'])) {
    header("Location: ../../views/auth/login.php");
    exit();
}

$manager_email = $_SESSION['email'];
$manager_id = getUserIdFromEmail($conn, $manager_email);

if (!$manager_id || !isset($_POST['hostel_id']) || !isset($_POST['report_type'])) {
    die("Invalid request");
}

$hostel_id = (int)$_POST['hostel_id'];
$report_type = $_POST['report_type'];
$format = $_POST['format'] ?? 'pdf';
$start_date = $_POST['start_date'] ?? date('Y-m-01');
$end_date = $_POST['end_date'] ?? date('Y-m-t');

// Verify hostel ownership
$stmt = $conn->prepare("SELECT name FROM hostels WHERE id = ? AND manager_id = ?");
$stmt->bind_param("ii", $hostel_id, $manager_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("Unauthorized access");
}
$hostel = $result->fetch_assoc();
$stmt->close();

// Generate report data based on type
$data = [];
$title = "";

switch ($report_type) {
    case 'bookings':
        $title = "Booking Report";
        $stmt = $conn->prepare("
            SELECT b.*, u.username, u.email, r.room_type, r.price_per_semester
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            JOIN rooms r ON b.room_id = r.id
            WHERE b.hostel_id = ? AND DATE(b.booking_date) BETWEEN ? AND ?
            ORDER BY b.booking_date DESC
        ");
        $stmt->bind_param("iss", $hostel_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        break;
        
    case 'financial':
        $title = "Financial Report";
        $stmt = $conn->prepare("
            SELECT p.*, b.booking_date, u.username, u.email, r.room_type
            FROM payments p
            JOIN bookings b ON p.booking_id = b.id
            JOIN users u ON p.user_id = u.id
            JOIN rooms r ON b.room_id = r.id
            WHERE p.hostel_id = ? AND DATE(p.payment_date) BETWEEN ? AND ?
            ORDER BY p.payment_date DESC
        ");
        $stmt->bind_param("iss", $hostel_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        break;
        
    case 'occupancy':
        $title = "Occupancy Report";
        $month = $_POST['month'] ?? date('Y-m');
        $stmt = $conn->prepare("
            SELECT r.room_type, r.quantity, r.status,
                   COUNT(b.id) as bookings_count,
                   SUM(CASE WHEN b.status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed_bookings
            FROM rooms r
            LEFT JOIN bookings b ON r.id = b.room_id AND DATE_FORMAT(b.check_in_date, '%Y-%m') = ?
            WHERE r.hostel_id = ?
            GROUP BY r.id, r.room_type, r.quantity, r.status
        ");
        $stmt->bind_param("si", $month, $hostel_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        break;
        
    case 'students':
        $title = "Student Report";
        $status_filter = $_POST['status'] ?? 'all';
        $where_clause = "";
        if ($status_filter === 'active') {
            $where_clause = "AND b.status = 'Confirmed' AND b.check_in_date <= CURDATE()";
        } elseif ($status_filter === 'checked_out') {
            $where_clause = "AND b.status = 'Completed'";
        }
        
        $stmt = $conn->prepare("
            SELECT DISTINCT u.username, u.email, b.status, b.check_in_date, b.check_out_date, 
                   r.room_type, ra.assigned_room_number
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            JOIN rooms r ON b.room_id = r.id
            LEFT JOIN room_assignments ra ON b.id = ra.booking_id
            WHERE b.hostel_id = ? $where_clause
            ORDER BY u.username
        ");
        $stmt->bind_param("i", $hostel_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        break;
}

// Generate output based on format
if ($format === 'csv') {
    generateCSV($data, $title, $hostel['name']);
} elseif ($format === 'preview') {
    generatePreview($data, $title, $hostel['name'], $report_type);
} else {
    generatePDF($data, $title, $hostel['name'], $report_type);
}

function generateCSV($data, $title, $hostel_name) {
    $filename = sanitizeFilename($title . "_" . $hostel_name . "_" . date('Y-m-d') . ".csv");
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    if (!empty($data)) {
        // Write headers
        fputcsv($output, array_keys($data[0]));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
}

function generatePDF($data, $title, $hostel_name, $report_type) {
    $filename = sanitizeFilename($title . "_" . $hostel_name . "_" . date('Y-m-d') . ".pdf");
    
    // Simple HTML to PDF conversion (basic implementation)
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // For now, generate HTML that can be printed as PDF
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>$title - $hostel_name</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #2d3a4c; border-bottom: 2px solid #ddd; padding-bottom: 10px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f8f9fa; font-weight: bold; }
            .header { margin-bottom: 20px; }
            .date { color: #666; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>$title</h1>
            <p><strong>Hostel:</strong> $hostel_name</p>
            <p class='date'><strong>Generated:</strong> " . date('Y-m-d H:i:s') . "</p>
        </div>";
    
    if (!empty($data)) {
        echo "<table>";
        echo "<thead><tr>";
        foreach (array_keys($data[0]) as $header) {
            echo "<th>" . htmlspecialchars(ucwords(str_replace('_', ' ', $header))) . "</th>";
        }
        echo "</tr></thead>";
        echo "<tbody>";
        foreach ($data as $row) {
            echo "<tr>";
            foreach ($row as $cell) {
                echo "<td>" . htmlspecialchars($cell ?? '') . "</td>";
            }
            echo "</tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p>No data available for the selected criteria.</p>";
    }
    
    echo "</body></html>";
}

function generatePreview($data, $title, $hostel_name, $report_type) {
    header('Content-Type: text/html');
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>$title - $hostel_name</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
            .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #2d3a4c; border-bottom: 3px solid #17a2b8; padding-bottom: 10px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
            th { background-color: #17a2b8; color: white; font-weight: bold; }
            tr:nth-child(even) { background-color: #f8f9fa; }
            .header { margin-bottom: 20px; }
            .date { color: #666; font-size: 14px; }
            .summary { background: #e8f4f8; padding: 15px; border-radius: 5px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>📊 $title</h1>
                <p><strong>Hostel:</strong> $hostel_name</p>
                <p class='date'><strong>Generated:</strong> " . date('Y-m-d H:i:s') . "</p>
            </div>";
    
    if (!empty($data)) {
        echo "<div class='summary'>
                <strong>Total Records:</strong> " . count($data) . "
              </div>";
        
        echo "<table>";
        echo "<thead><tr>";
        foreach (array_keys($data[0]) as $header) {
            echo "<th>" . htmlspecialchars(ucwords(str_replace('_', ' ', $header))) . "</th>";
        }
        echo "</tr></thead>";
        echo "<tbody>";
        foreach ($data as $row) {
            echo "<tr>";
            foreach ($row as $cell) {
                echo "<td>" . htmlspecialchars($cell ?? '') . "</td>";
            }
            echo "</tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<div class='summary'>No data available for the selected criteria.</div>";
    }
    
    echo "        </div>
    </body>
    </html>";
}

function sanitizeFilename($filename) {
    return preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
}
?>