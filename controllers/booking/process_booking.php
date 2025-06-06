<?php
session_start();
include 'config.php';
include 'functions.php';

// In your controller files (like process_payment.php, etc.)
$_SESSION['from_form_submission'] = true;
header("Location: ../../views/dashboard/student/homepage.php#booking");
exit();


// Check if user is logged in
if (!isset($_SESSION['email'])) {
    $_SESSION['general_error_message'] = "You must be logged in to make a booking.";
    header("Location: ../../views/auth/login.php");
    exit();
}

$user_email = $_SESSION['email'];
$user_id = getUserIdFromEmail($conn, $user_email);

if (!$user_id) {
    $_SESSION['general_error_message'] = "Error: Could not retrieve your user ID.";
    header("Location: dashboard1.php");
    exit();
}

// Get booking details from form
$hostel_id = $_POST['hostel_id'] ?? 0;
$room_id = $_POST['room_id'] ?? 0;
$check_in_date = $_POST['check_in_date'] ?? '';
$check_out_date = $_POST['check_out_date'] ?? '';
$special_requests = $_POST['special_requests'] ?? '';
$quantity = intval($_POST['quantity'] ?? 1);

// Validate required fields
if (!$hostel_id || !$room_id || !$check_in_date || !$check_out_date) {
    $_SESSION['bookings_error_message'] = "All required fields must be filled.";
    header("Location: dashboard1.php#bookings");
    exit();
}

// Validate dates
$today = date('Y-m-d');
if ($check_in_date < $today) {
    $_SESSION['bookings_error_message'] = "Check-in date cannot be in the past.";
    header("Location: dashboard1.php#bookings");
    exit();
}

if ($check_out_date <= $check_in_date) {
    $_SESSION['bookings_error_message'] = "Check-out date must be after check-in date.";
    header("Location: dashboard1.php#bookings");
    exit();
}

// Check room availability
if (!checkRoomAvailability($conn, $hostel_id, $room_id, $check_in_date, $check_out_date, $quantity)) {
    $_SESSION['bookings_error_message'] = "Sorry, there are not enough rooms available for the selected dates.";
    header("Location: dashboard1.php#bookings");
    exit();
}

// Create booking
$status = 'Pending'; // Default status for new bookings
$stmt_booking = $conn->prepare("
    INSERT INTO bookings (
        hostel_id, room_id, user_id, check_in_date, check_out_date, 
        status, special_requests, quantity
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt_booking->bind_param(
    "iiisssi", 
    $hostel_id, $room_id, $user_id, $check_in_date, $check_out_date, 
    $status, $special_requests, $quantity
);

if ($stmt_booking->execute()) {
    $booking_id = $conn->insert_id;
    
    // Create notification for hostel manager
    $manager_id = getHostelManagerId($conn, $hostel_id);
    if ($manager_id) {
        createNotification(
            $conn, 
            'new_booking', 
            'New Booking Request', 
            "A new booking request has been made for your hostel.", 
            $hostel_id, 
            $booking_id, 
            $manager_id
        );
    }
    
    $_SESSION['bookings_success_message'] = "Your booking request has been submitted successfully!";
} else {
    $_SESSION['bookings_error_message'] = "Error creating booking: " . $conn->error;
}

$stmt_booking->close();
$conn->close();

// Redirect back to bookings section
header("Location: dashboard1.php#bookings");
exit();
?>
