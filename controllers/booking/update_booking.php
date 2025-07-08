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

// Get booking ID and action
$booking_id = $_POST['booking_id'] ?? 0;
$action = $_POST['action'] ?? '';

if (!$booking_id || !$action) {
    $_SESSION['bookings_error_message'] = "Missing required parameters.";
    header("Location: ../../views/dashboard/manager/dashboard.php#bookings");
    exit();
}

// Verify the booking belongs to a hostel managed by this manager
$stmt_check = $conn->prepare("
    SELECT b.*, h.manager_id, u.email as student_email 
    FROM bookings b
    JOIN hostels h ON b.hostel_id = h.id
    JOIN users u ON b.user_id = u.id
    WHERE b.id = ? AND h.manager_id = ?
");
$stmt_check->bind_param("ii", $booking_id, $manager_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows === 0) {
    $_SESSION['bookings_error_message'] = "You don't have permission to update this booking.";
    header("Location: ../../views/dashboard/manager/dashboard.php#bookings");
    exit();
}

$booking = $result_check->fetch_assoc();
$student_email = $booking['student_email'];
$stmt_check->close();

// Update booking status based on action
$new_status = '';
$notification_type = '';
$notification_title = '';
$notification_message = '';

switch ($action) {
    case 'confirm':
        $new_status = 'Confirmed';
        $notification_type = 'booking_confirmed';
        $notification_title = 'Booking Confirmed';
        $notification_message = "Your booking request has been confirmed. Please proceed with payment.";
        break;
    case 'cancel':
        $new_status = 'Cancelled';
        $notification_type = 'booking_cancelled';
        $notification_title = 'Booking Cancelled';
        $notification_message = "Your booking request has been cancelled.";
        break;
    case 'complete':
        $new_status = 'Completed';
        $notification_type = 'booking_completed';
        $notification_title = 'Booking Completed';
        $notification_message = "Your booking has been marked as completed.";
        break;
    case 'undo_complete':
        $new_status = 'Confirmed';
        $notification_type = 'booking_reopened';
        $notification_title = 'Booking Reopened';
        $notification_message = "Your completed booking has been reopened.";
        break;
    default:
        $_SESSION['bookings_error_message'] = "Invalid action.";
        header("Location: ../../views/dashboard/manager/dashboard.php#bookings");
        exit();
}

// Update booking status
$stmt_update = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
$stmt_update->bind_param("si", $new_status, $booking_id);

if ($stmt_update->execute()) {
    // Update room availability when booking is completed
    if ($action === 'complete') {
        // Check if room is assigned and payment is completed
        $stmt_room_check = $conn->prepare("
            SELECT ra.room_id, p.status as payment_status
            FROM room_assignments ra
            LEFT JOIN payments p ON p.booking_id = ra.booking_id
            WHERE ra.booking_id = ?
        ");
        $stmt_room_check->bind_param("i", $booking_id);
        $stmt_room_check->execute();
        $room_result = $stmt_room_check->get_result();
        
        if ($room_result->num_rows > 0) {
            $room_data = $room_result->fetch_assoc();
            if ($room_data['payment_status'] === 'Completed') {
                // Update room status to Available
                $stmt_room_update = $conn->prepare("UPDATE rooms SET status = 'Available' WHERE id = ?");
                $stmt_room_update->bind_param("i", $room_data['room_id']);
                $stmt_room_update->execute();
                $stmt_room_update->close();
            }
        }
        $stmt_room_check->close();
    }
    
    // Create notification for student
    $student_id = $booking['user_id'];
    createNotification(
        $conn,
        $notification_type,
        $notification_title,
        $notification_message,
        $booking['hostel_id'],
        $booking_id,
        $student_id
    );
    
    $_SESSION['bookings_success_message'] = "Booking status updated successfully.";
} else {
    $_SESSION['bookings_error_message'] = "Error updating booking status: " . $conn->error;
}

$stmt_update->close();
$conn->close();

// Redirect back to bookings section
header("Location: ../../views/dashboard/manager/dashboard.php#bookings");
exit();
?>
