<?php
session_start();
include '../../config/config.php';
include '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    header("Location: ../../views/auth/login.php");
    exit();
}

// Check if user is a manager
$user_role = getUserRoleFromEmail($conn, $_SESSION['email']);
if ($user_role !== 'manager') {
    $_SESSION['error_message'] = "You don't have permission to perform this action.";
    header("Location: ../../views/dashboard/student/homepage.php");
    exit();
}

// Get hostel ID and action
$hostel_id = $_POST['hostel_id'] ?? 0;
$action = $_POST['action'] ?? '';

if (!$hostel_id || !$action) {
    $_SESSION['error_message'] = "Invalid request parameters.";
    header("Location: ../../views/dashboard/manager/dashboard.php#bookings");
    exit();
}

// Process action
switch ($action) {
    case 'confirm_all':
        // Confirm all pending bookings
        $stmt = $conn->prepare("
            UPDATE bookings 
            SET status = 'Confirmed' 
            WHERE hostel_id = ? AND status = 'Pending'
        ");
        $stmt->bind_param("i", $hostel_id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        if ($affected > 0) {
            // Get all updated bookings to send notifications
            $stmt_bookings = $conn->prepare("
                SELECT b.id, b.user_id 
                FROM bookings b
                WHERE b.hostel_id = ? AND b.status = 'Confirmed'
            ");
            $stmt_bookings->bind_param("i", $hostel_id);
            $stmt_bookings->execute();
            $result_bookings = $stmt_bookings->get_result();
            
            while ($booking = $result_bookings->fetch_assoc()) {
                createNotification(
                    $conn,
                    'booking_confirmed',
                    'Booking Confirmed',
                    "Your booking has been confirmed by the hostel manager.",
                    $hostel_id,
                    $booking['id'],
                    $booking['user_id']
                );
            }
            
            $_SESSION['success_message'] = "Successfully confirmed $affected pending bookings.";
        } else {
            $_SESSION['info_message'] = "No pending bookings to confirm.";
        }
        break;
        
    case 'assign_all_rooms':
        // Get all confirmed bookings without room assignments
        $stmt_bookings = $conn->prepare("
            SELECT b.id, b.room_id, b.user_id, r.room_type
            FROM bookings b
            JOIN rooms r ON b.room_id = r.id
            LEFT JOIN room_assignments ra ON b.id = ra.booking_id
            WHERE b.hostel_id = ? AND b.status = 'Confirmed' AND ra.id IS NULL
        ");
        $stmt_bookings->bind_param("i", $hostel_id);
        $stmt_bookings->execute();
        $result_bookings = $stmt_bookings->get_result();
        
        $assigned_count = 0;
        $failed_count = 0;
        
        while ($booking = $result_bookings->fetch_assoc()) {
            // Get all currently assigned rooms for this room type
            $stmt_assigned = $conn->prepare("
                SELECT ra.assigned_room_number 
                FROM room_assignments ra
                JOIN bookings b ON ra.booking_id = b.id
                JOIN rooms r ON b.room_id = r.id
                WHERE b.hostel_id = ? AND r.room_type = ?
            ");
            $stmt_assigned->bind_param("is", $hostel_id, $booking['room_type']);
            $stmt_assigned->execute();
            $result_assigned = $stmt_assigned->get_result();
            $assigned_rooms = [];
            while ($row = $result_assigned->fetch_assoc()) {
                $assigned_rooms[] = $row['assigned_room_number'];
            }
            $stmt_assigned->close();
            
            // Get room quantity for this room type
            $stmt_quantity = $conn->prepare("
                SELECT quantity 
                FROM rooms 
                WHERE id = ?
            ");
            $stmt_quantity->bind_param("i", $booking['room_id']);
            $stmt_quantity->execute();
            $quantity = $stmt_quantity->get_result()->fetch_assoc()['quantity'] ?? 0;
            $stmt_quantity->close();
            
            // Generate available room numbers
            $available_rooms = [];
            for ($i = 1; $i <= $quantity; $i++) {
                $room_number = $booking['room_type'] . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
                if (!in_array($room_number, $assigned_rooms)) {
                    $available_rooms[] = $room_number;
                }
            }
            
            // Randomly select a room from available rooms
            if (!empty($available_rooms)) {
                $random_index = array_rand($available_rooms);
                $assigned_room = $available_rooms[$random_index];
                
                // Create room assignment
                $stmt_insert = $conn->prepare("
                    INSERT INTO room_assignments (booking_id, room_id, assigned_room_number)
                    VALUES (?, ?, ?)
                ");
                $stmt_insert->bind_param("iis", $booking['id'], $booking['room_id'], $assigned_room);
                
                if ($stmt_insert->execute()) {
                    // Notify student
                    createNotification(
                        $conn,
                        'room_assigned',
                        'Room Assigned',
                        "Your room has been assigned: $assigned_room",
                        $hostel_id,
                        $booking['id'],
                        $booking['user_id']
                    );
                    $assigned_count++;
                } else {
                    $failed_count++;
                }
                $stmt_insert->close();
            } else {
                $failed_count++;
            }
        }
        
        if ($assigned_count > 0) {
            $_SESSION['success_message'] = "Successfully assigned rooms to $assigned_count bookings.";
        } else {
            $_SESSION['info_message'] = "No bookings to assign rooms to.";
        }
        
        if ($failed_count > 0) {
            $_SESSION['error_message'] = "$failed_count bookings could not be assigned rooms (no available rooms).";
        }
        break;
        
    case 'complete_all_past':
        // Complete all confirmed bookings with past check-out dates
        $today = date('Y-m-d');
        $stmt = $conn->prepare("
            UPDATE bookings 
            SET status = 'Completed' 
            WHERE hostel_id = ? AND status = 'Confirmed' AND check_out_date < ?
        ");
        $stmt->bind_param("is", $hostel_id, $today);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        if ($affected > 0) {
            // Get all updated bookings to send notifications
            $stmt_bookings = $conn->prepare("
                SELECT b.id, b.user_id 
                FROM bookings b
                WHERE b.hostel_id = ? AND b.status = 'Completed' AND b.check_out_date < ?
            ");
            $stmt_bookings->bind_param("is", $hostel_id, $today);
            $stmt_bookings->execute();
            $result_bookings = $stmt_bookings->get_result();
            
            while ($booking = $result_bookings->fetch_assoc()) {
                createNotification(
                    $conn,
                    'booking_completed',
                    'Booking Completed',
                    "Your booking has been marked as completed.",
                    $hostel_id,
                    $booking['id'],
                    $booking['user_id']
                );
            }
            
            $_SESSION['success_message'] = "Successfully completed $affected past bookings.";
        } else {
            $_SESSION['info_message'] = "No past bookings to complete.";
        }
        break;
        
    case 'toggle_auto_process':
        $auto_process = $_POST['enabled'] ?? '0';
        setHostelSetting($conn, $hostel_id, 'auto_process_bookings', $auto_process);
        echo json_encode(['success' => true]);
        exit();
        
    default:
        $_SESSION['error_message'] = "Invalid action.";
        break;
}

// Redirect back to bookings page
header("Location: ../../views/dashboard/manager/dashboard.php#bookings");
exit();
?>
