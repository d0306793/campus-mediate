<?php
session_start();
include '../../config/config.php';
include '../../includes/functions.php';

// Check if user is logged in as a manager
if (!isset($_SESSION['email']) || getUserRoleFromEmail($conn, $_SESSION['email']) !== 'manager') {
    $_SESSION['general_error_message'] = "You must be logged in as a manager to perform this action.";
    header("Location: ../../views/auth/login.php");
    exit();
}

$manager_email = $_SESSION['email'];
$manager_id = getUserIdFromEmail($conn, $manager_email);

// Get booking ID
$booking_id = $_POST['booking_id'] ?? 0;

if (!$booking_id) {
    $_SESSION['bookings_error_message'] = "Missing booking ID.";
    header("Location: ../../views/dashboard/manager/dashboard.php#bookings");
    exit();
}

// Verify the booking belongs to a hostel managed by this manager
$stmt_check = $conn->prepare("
    SELECT b.*, h.manager_id, r.room_type
    FROM bookings b
    JOIN hostels h ON b.hostel_id = h.id
    JOIN rooms r ON b.room_id = r.id
    WHERE b.id = ? AND h.manager_id = ?
");
$stmt_check->bind_param("ii", $booking_id, $manager_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows === 0) {
    $_SESSION['bookings_error_message'] = "You don't have permission to assign rooms for this booking.";
    header("Location: ../../views/dashboard/manager/dashboard.php#bookings");
    exit();
}

$booking = $result_check->fetch_assoc();
$stmt_check->close();

// Check if room is already assigned
$stmt_existing = $conn->prepare("SELECT id FROM room_assignments WHERE booking_id = ?");
$stmt_existing->bind_param("i", $booking_id);
$stmt_existing->execute();
$result_existing = $stmt_existing->get_result();

if ($result_existing->num_rows > 0) {
    $_SESSION['bookings_error_message'] = "Room is already assigned for this booking.";
    header("Location: ../../views/dashboard/manager/dashboard.php#bookings");
    exit();
}
$stmt_existing->close();

// Assign room number using the template
try {
    $room_number = assignRoomNumber($conn, $booking_id, $booking['hostel_id'], $booking['room_id'], $booking['room_type']);
    
    // Create notification for student
    $student_id = $booking['user_id'];
    createNotification(
        $conn,
        'room_assigned',
        'Room Assigned',
        "Your room number {$room_number} has been assigned for your booking.",
        $booking['hostel_id'],
        $booking_id,
        $student_id
    );
    
    $_SESSION['bookings_success_message'] = "Room number {$room_number} assigned successfully.";
} catch (Exception $e) {
    $_SESSION['bookings_error_message'] = "Error assigning room: " . $e->getMessage();
}

$conn->close();

// Redirect back to bookings section
header("Location: ../../views/dashboard/manager/dashboard.php#bookings");
exit();
?>