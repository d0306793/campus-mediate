<?php
session_start();
include "../../config/config.php";
include "../../includes/functions.php";

if (!isset($_SESSION['email'])) {
    header("Location: ../../views/auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = getUserIdFromEmail($conn, $_SESSION['email']);
    $hostel_id = $_POST['hostel_id'];
    $room_id = $_POST['room_id'];
    $full_name = trim($_POST['full_name']);
    $check_in_date = $_POST['check_in_date'];
    $check_out_date = $_POST['check_out_date'];
    $quantity = $_POST['quantity'] ?? 1;
    
    if (empty($full_name)) {
        $_SESSION['error_message'] = "Full name is required for booking.";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }
    
    // Check if full_name column exists in bookings table, if not add it
    $check_column = $conn->query("SHOW COLUMNS FROM bookings LIKE 'full_name'");
    if ($check_column->num_rows == 0) {
        $conn->query("ALTER TABLE bookings ADD COLUMN full_name VARCHAR(255) AFTER user_id");
    }
    
    // Insert booking with full name
    $stmt = $conn->prepare("INSERT INTO bookings (user_id, full_name, hostel_id, room_id, check_in_date, check_out_date, quantity, status, booking_date) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())");
    $stmt->bind_param("isiiisi", $user_id, $full_name, $hostel_id, $room_id, $check_in_date, $check_out_date, $quantity);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Booking request submitted successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to submit booking request.";
    }
    
    $stmt->close();
}

header("Location: ../../views/dashboard/student/homepage.php#bookings");
exit();
?>