<?php
session_start();
include '../../config/config.php';
include '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    header("Location: ../../views/auth/login.php");
    exit();
}

$manager_email = $_SESSION['email'];
$manager_id = getUserIdFromEmail($conn, $manager_email);

if (!$manager_id) {
    $_SESSION['error_message'] = "Error: Could not retrieve your manager ID.";
    header("Location: ../../views/dashboard/manager/dashboard.php#rooms");
    exit();
}

// Retrieve hostel ID from the form
$hostel_id = $_POST['hostel_id'] ?? null;

if (!$hostel_id) {
    $_SESSION['error_message'] = "Error: Hostel ID not provided.";
    header("Location: ../../views/dashboard/manager/dashboard.php#rooms");
    exit();
}

// Get hostel details to check total rooms
$stmt_hostel = $conn->prepare("SELECT total_rooms FROM hostels WHERE id = ?");
$stmt_hostel->bind_param("i", $hostel_id);
$stmt_hostel->execute();
$result_hostel = $stmt_hostel->get_result();
$hostel = $result_hostel->fetch_assoc();
$total_rooms_allowed = $hostel['total_rooms'] ?? 0;
$stmt_hostel->close();

// Get current total rooms in inventory
$stmt_current = $conn->prepare("SELECT SUM(quantity) as current_total FROM rooms WHERE hostel_id = ?");
$stmt_current->bind_param("i", $hostel_id);
$stmt_current->execute();
$result_current = $stmt_current->get_result();
$row_current = $result_current->fetch_assoc();
$current_total_rooms = $row_current['current_total'] ?? 0;
$stmt_current->close();

// Handle updating room inventory
if (isset($_POST['update_inventory']) && isset($_POST['room_type']) && isset($_POST['quantity']) && isset($_POST['price_per_semester'])) {
    $room_type = $_POST['room_type'];
    $quantity = intval($_POST['quantity']);
    $price_per_semester = $_POST['price_per_semester'];
    $status = $_POST['status'] ?? 'Available';
    $room_id = $_POST['room_id'] ?? '';

    // Check if this is an update or new entry
    if ($room_id) {
        // Get current quantity for this room
        $stmt_room = $conn->prepare("SELECT quantity FROM rooms WHERE id = ?");
        $stmt_room->bind_param("i", $room_id);
        $stmt_room->execute();
        $result_room = $stmt_room->get_result();
        $room = $result_room->fetch_assoc();
        $current_quantity = $room['quantity'] ?? 0;
        $stmt_room->close();
        
        // Calculate new total after update
        $new_total = $current_total_rooms - $current_quantity + $quantity;
        
        // Check if new total exceeds allowed total
        if ($new_total > $total_rooms_allowed) {
            $_SESSION['rooms_error_message'] = "Error: Cannot add more rooms than the total rooms defined in hostel details ({$total_rooms_allowed}). Current inventory: {$current_total_rooms}, Attempted to add: {$quantity}";
            header("Location: ../../views/dashboard/manager/dashboard.php#rooms");
            exit();
        }
        
        // Update existing inventory
        $stmt_update_inventory = $conn->prepare("UPDATE rooms SET quantity = ?, price_per_semester = ?, status = ? WHERE id = ? AND hostel_id = ?");
        $stmt_update_inventory->bind_param("idsis", $quantity, $price_per_semester, $status, $room_id, $hostel_id);
        if ($stmt_update_inventory->execute()) {
            $_SESSION['rooms_success_message'] = "Room inventory updated successfully!";
        } else {
            $_SESSION['rooms_error_message'] = "Error updating room inventory.";
        }
        $stmt_update_inventory->close();
    } else {
        // Calculate new total after adding new room type
        $new_total = $current_total_rooms + $quantity;
        
        // Check if new total exceeds allowed total
        if ($new_total > $total_rooms_allowed) {
            $_SESSION['rooms_error_message'] = "Error: Cannot add more rooms than the total rooms defined in hostel details ({$total_rooms_allowed}). Current inventory: {$current_total_rooms}, Attempted to add: {$quantity}";
            header("Location: ../../views/dashboard/manager/dashboard.php#rooms");
            exit();
        }
        
        // Insert new inventory entry
        $stmt_insert_inventory = $conn->prepare("INSERT INTO rooms (hostel_id, room_type, quantity, price_per_semester, status) VALUES (?, ?, ?, ?, ?)");
        $stmt_insert_inventory->bind_param("isids", $hostel_id, $room_type, $quantity, $price_per_semester, $status);
        if ($stmt_insert_inventory->execute()) {
            $_SESSION['rooms_success_message'] = "Room inventory added successfully!";
        } else {
            $_SESSION['rooms_error_message'] = "Error adding room inventory.";
        }
        $stmt_insert_inventory->close();
    }
}

// Set a flag to indicate we're coming from a form submission
$_SESSION['from_form_submission'] = true;

$conn->close();
header("Location: ../../views/dashboard/manager/dashboard.php#rooms");
exit();
?>
