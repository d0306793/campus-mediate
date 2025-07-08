<?php
session_start();
include '../../config/config.php';
include '../../includes/functions.php';

// Set default response
$response = [
    'success' => false,
    'end_date' => '',
    'message' => 'Failed to get semester end date'
];

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    $response['message'] = 'User not authenticated';
    echo json_encode($response);
    exit;
}

try {
    // Get the active semester end date
    $end_date = getActiveSemesterEndDate($conn);
    
    $response = [
        'success' => true,
        'end_date' => $end_date,
        'message' => 'Successfully retrieved semester end date'
    ];
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>