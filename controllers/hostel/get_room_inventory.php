<?php
session_start();
include '../../config/config.php';
include '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$hostel_id = $_GET['hostel_id'] ?? 0;
$room_type = $_GET['room_type'] ?? '';

if (!$hostel_id || !$room_type) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing parameters']);
    exit();
}

// Get room inventory
$stmt = $conn->prepare("SELECT id, room_type, quantity, price_per_semester, status FROM rooms WHERE hostel_id = ? AND room_type = ? LIMIT 1");
$stmt->bind_param("is", $hostel_id, $room_type);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $room = $result->fetch_assoc();
    header('Content-Type: application/json');
    echo json_encode($room);
} else {
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Room type not found']);
}
?>
