<?php
session_start();
include 'config.php';
include 'functions.php';

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    $_SESSION['general_error_message'] = "You must be logged in to make a payment.";
    header("Location: login1.php");
    exit();
}

$booking_id = $_POST['booking_id'] ?? 0;
$payment_method = $_POST['payment_method'] ?? '';
$amount = floatval($_POST['amount'] ?? 0);
$payment_type = $_POST['payment_type'] ?? 'Full Payment';
$transaction_id = $_POST['transaction_id'] ?? '';
$notes = $_POST['notes'] ?? '';

// Validate required fields
if (!$booking_id || !$payment_method || $amount <= 0) {
    $_SESSION['payments_error_message'] = "All required fields must be filled.";
    header("Location: student_dashboard.php#payments");
    exit();
}

// Get booking details
$stmt_booking = $conn->prepare("
    SELECT b.*, h.id as hostel_id, r.price_per_semester
    FROM bookings b
    JOIN hostels h ON b.hostel_id = h.id
    JOIN rooms r ON b.room_id = r.id
    WHERE b.id = ?
");
$stmt_booking->bind_param("i", $booking_id);
$stmt_booking->execute();
$result_booking = $stmt_booking->get_result();

if ($result_booking->num_rows === 0) {
    $_SESSION['payments_error_message'] = "Booking not found.";
    header("Location: student_dashboard.php#payments");
    exit();
}

$booking = $result_booking->fetch_assoc();
$stmt_booking->close();

// Check if payment method is accepted by hostel
$stmt_method = $conn->prepare("
    SELECT id FROM payment_methods 
    WHERE hostel_id = ? AND method_name = ? AND is_active = 1
");
$stmt_method->bind_param("is", $booking['hostel_id'], $payment_method);
$stmt_method->execute();
$result_method = $stmt_method->get_result();

if ($result_method->num_rows === 0) {
    $_SESSION['payments_error_message'] = "This payment method is not accepted by the hostel.";
    header("Location: student_dashboard.php#payments");
    exit();
}
$stmt_method->close();

// Create payment record
$user_id = getUserIdFromEmail($conn, $_SESSION['email']);
$status = 'Pending'; // Default status for new payments

$stmt_payment = $conn->prepare("
    INSERT INTO payments (
        booking_id, hostel_id, user_id, amount, payment_method,
        transaction_id, payment_type, status, notes
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt_payment->bind_param(
    "iiidsssss",
    $booking_id, $booking['hostel_id'], $user_id, $amount, $payment_method,
    $transaction_id, $payment_type, $status, $notes
);

if ($stmt_payment->execute()) {
    $payment_id = $conn->insert_id;
    
    // Create notification for hostel manager
    $manager_id = getHostelManagerId($conn, $booking['hostel_id']);
    if ($manager_id) {
        createNotification(
            $conn,
            'new_payment',
            'New Payment Received',
            "A new payment of " . number_format($amount, 2) . " UGX has been received.",
            $booking['hostel_id'],
            $payment_id,
            $manager_id
        );
    }
    
    // If using Flutterwave, redirect to payment gateway
    if ($payment_method == 'Credit/Debit Card' || $payment_method == 'MTN Mobile Money' || $payment_method == 'Airtel Money') {
        $_SESSION['payment_id'] = $payment_id;
        header("Location: flutterwave_payment.php");
        exit();
    }
    
    $_SESSION['payments_success_message'] = "Your payment has been submitted and is pending confirmation.";
} else {
    $_SESSION['payments_error_message'] = "Error processing payment: " . $conn->error;
}

$stmt_payment->close();
$conn->close();

header("Location: student_dashboard.php#payments");
exit();
?>
