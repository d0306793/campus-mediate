<?php
session_start();
include "../../config/config.php";
include "../../includes/functions.php";

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if(!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Get user ID
$user_email = $_SESSION['email'];
$user_id = getUserIdFromEmail($conn, $user_email);

// Check if notification ID is provided
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
    exit();
}

$notification_id = intval($_GET['id']);

// Verify the notification belongs to the user
$stmt = $conn->prepare("SELECT id FROM notifications WHERE id = ? AND recipient_id = ?");
$stmt->bind_param("ii", $notification_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Notification not found or not authorized']);
    exit();
}

// Mark notification as read
$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
$stmt->bind_param("i", $notification_id);
$success = $stmt->execute();

if($success) {
    echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to mark notification as read']);
}

$stmt->close();
$conn->close();
?>
