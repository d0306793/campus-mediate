<?php
session_start();
include '../../config/config.php';
include '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    $_SESSION['rooms_error_message'] = "You must be logged in to perform this action.";
    header("Location: ../../views/auth/login.php");
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['rooms_error_message'] = "Invalid request method.";
    header("Location: ../../views/dashboard/manager/dashboard.php#rooms");
    exit();
}

// Get form data
$hostel_id = $_POST['hostel_id'] ?? 0;
$room_type = $_POST['room_type'] ?? '';
$prefix = $_POST['prefix'] ?? '';
$start_number = intval($_POST['start_number'] ?? 1);
$padding = intval($_POST['padding'] ?? 2);
$floor_prefix = isset($_POST['floor_prefix']) ? 1 : 0;

// Validate required fields
if (!$hostel_id || !$room_type) {
    $_SESSION['rooms_error_message'] = "Missing required fields.";
    header("Location: ../../views/dashboard/manager/dashboard.php#rooms");
    exit();
}

// Check if template already exists for this room type
$stmt_check = $conn->prepare("SELECT id FROM room_numbering_templates WHERE hostel_id = ? AND room_type = ?");
$stmt_check->bind_param("is", $hostel_id, $room_type);
$stmt_check->execute();
$result_check = $stmt_check->get_result();
$template_exists = ($result_check->num_rows > 0);
$stmt_check->close();

if ($template_exists) {
    // Update existing template
    $stmt = $conn->prepare("UPDATE room_numbering_templates SET prefix = ?, start_number = ?, padding = ?, floor_prefix = ? WHERE hostel_id = ? AND room_type = ?");
    $stmt->bind_param("siiisi", $prefix, $start_number, $padding, $floor_prefix, $hostel_id, $room_type);
} else {
    // Insert new template
    $stmt = $conn->prepare("INSERT INTO room_numbering_templates (hostel_id, room_type, prefix, start_number, padding, floor_prefix) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issiii", $hostel_id, $room_type, $prefix, $start_number, $padding, $floor_prefix);
}

if ($stmt->execute()) {
    $_SESSION['rooms_success_message'] = "Room numbering template saved successfully!";
} else {
    $_SESSION['rooms_error_message'] = "Error saving room numbering template: " . $conn->error;
}

$stmt->close();
$conn->close();

// Set flag for form submission
$_SESSION['from_form_submission'] = true;

// Redirect back to rooms section
header("Location: ../../views/dashboard/manager/dashboard.php#rooms");
exit();
?>
