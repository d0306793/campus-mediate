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

// Mark all notifications as read
$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE recipient_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$success = $stmt->execute();

if($success) {
    echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to mark notifications as read']);
}

$stmt->close();
$conn->close();
?>
