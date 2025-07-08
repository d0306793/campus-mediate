<?php
/**
 * Toggle Notification Read Status Controller
 * 
 * This script handles toggling the read status of a notification.
 * It receives a notification ID via POST request and toggles its read status.
 * Starts a session and includes the database connection
 * Validates that the user is logged in
 * Checks that a notification ID was provided in the POST request
 * Sanitizes the input to prevent SQL injection
 * Verifies that the notification exists and belongs to the current user
 * Toggles the read status (from read to unread or vice versa)
 * Updates the database with the new status
 * Returns a JSON response with the result
 * This script is a complete cycle of:
 * Receiving data from JavaScript
 * Validating it for security
 * Checking the database
 * Making changes to the database
 * Sending a response back to JavaScript
 */

// Start session if not already started
session_start();

// Include database connection
require_once '../../config/config.php';

// Set default response
$response = [
    'success' => false,
    'message' => 'An error occurred',
    'is_read' => false
];

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    $response['message'] = 'User not authenticated';
    echo json_encode($response);
    exit;
}

// Get user ID from email
$manager_email = $_SESSION['email'];
$stmt_user = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt_user->bind_param("s", $manager_email);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
if ($result_user->num_rows === 0) {
    $response['message'] = 'User not found';
    echo json_encode($response);
    exit;
}
$user_row = $result_user->fetch_assoc();
$user_id = $user_row['id'];
$stmt_user->close();

// Check if notification_id is provided
if (!isset($_POST['notification_id']) || empty($_POST['notification_id'])) {
    $response['message'] = 'Notification ID is required';
    echo json_encode($response);
    exit;
}

// Sanitize input
$notification_id = filter_var($_POST['notification_id'], FILTER_SANITIZE_NUMBER_INT);

try {
    // First, check if the notification exists and belongs to the user
    $check_stmt = $conn->prepare("
        SELECT id, is_read, recipient_id 
        FROM notifications 
        WHERE id = ?
    ");
    $check_stmt->bind_param("i", $notification_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        $response['message'] = 'Notification not found';
        echo json_encode($response);
        exit;
    }
    
    $notification = $result->fetch_assoc();
    
    // Verify the notification belongs to the current user
    if ($notification['recipient_id'] != $user_id) {
        $response['message'] = 'You do not have permission to modify this notification';
        echo json_encode($response);
        exit;
    }
    
    // Toggle the is_read status
    $new_status = $notification['is_read'] ? 0 : 1;
    
    // Update the notification read status
    $update_stmt = $conn->prepare("
        UPDATE notifications 
        SET is_read = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $update_stmt->bind_param("ii", $new_status, $notification_id);
    
    if ($update_stmt->execute()) {
        $response = [
            'success' => true,
            'message' => 'Notification status updated successfully',
            'is_read' => (bool)$new_status
        ];
    } else {
        $response['message'] = 'Failed to update notification status';
    }
    
    $update_stmt->close();
    
} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
} finally {
    // Close the database connection if it exists
    if (isset($check_stmt)) {
        $check_stmt->close();
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
}
?>