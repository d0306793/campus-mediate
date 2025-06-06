<?php
session_start();
include '../../config/config.php';
include '../../includes/functions.php';

if (!isset($_SESSION['email'])) {
    header("Location: ../../views/auth/login.php");
    exit();
}

$booking_id = $_GET['booking_id'] ?? 0;

if (!$booking_id) {
    header("Location: ../../views/dashboard/student/homepage.php");
    exit();
}

// Get booking details
$stmt = $conn->prepare("
    SELECT b.*, h.name as hostel_name, r.room_type, p.status as payment_status, p.transaction_id
    FROM bookings b
    JOIN hostels h ON b.hostel_id = h.id
    JOIN rooms r ON b.room_id = r.id
    LEFT JOIN payments p ON b.id = p.booking_id
    WHERE b.id = ?
    ORDER BY p.id DESC
    LIMIT 1
");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header("Location: ../../views/dashboard/student/homepage.php");
    exit();
}

$success = isset($_SESSION['payment_success']);
$error = isset($_SESSION['payment_error']) ? $_SESSION['payment_error'] : null;

// Clear session messages
unset($_SESSION['payment_success']);
unset($_SESSION['payment_error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Result</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .result-container {
            max-width: 600px;
            margin: 100px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .success-icon {
            font-size: 5rem;
            color: #4CAF50;
            margin-bottom: 20px;
        }
        .error-icon {
            font-size: 5rem;
            color: #F44336;
            margin-bottom: 20px;
        }
        .booking-details {
            margin-top: 30px;
            text-align: left;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="result-container">
        <?php if ($success || $booking['payment_status'] === 'Completed'): ?>
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Payment Successful!</h1>
            <p>Your booking has been confirmed.</p>
            <p>Transaction ID: <?php echo htmlspecialchars($booking['transaction_id']); ?></p>
        <?php else: ?>
            <div class="error-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <h1>Payment Failed</h1>
            <p><?php echo $error ?? 'There was an issue processing your payment.'; ?></p>
        <?php endif; ?>
        
        <div class="booking-details">
            <h3>Booking Details</h3>
            <p><strong>Hostel:</strong> <?php echo htmlspecialchars($booking['hostel_name']); ?></p>
            <p><strong>Room Type:</strong> <?php echo htmlspecialchars(ucfirst($booking['room_type'])); ?></p>
            <p><strong>Check-in:</strong> <?php echo htmlspecialchars($booking['check_in_date']); ?></p>
            <p><strong>Check-out:</strong> <?php echo htmlspecialchars($booking['check_out_date']); ?></p>
            <p><strong>Status:</strong> <?php echo htmlspecialchars($booking['status']); ?></p>
        </div>
        
        <div class="mt-4">
            <a href="../../views/dashboard/student/homepage.php#bookings" class="btn btn-primary">View My Bookings</a>
        </div>
    </div>
</body>
</html>
