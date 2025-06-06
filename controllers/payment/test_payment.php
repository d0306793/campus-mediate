<?php
session_start();
include '../../config/config.php';
include '../../includes/functions.php';

if (!isset($_SESSION['email']) || getUserRoleFromEmail($conn, $_SESSION['email']) !== 'student') {
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
    SELECT b.*, h.name as hostel_name, r.room_type, r.price_per_semester
    FROM bookings b
    JOIN hostels h ON b.hostel_id = h.id
    JOIN rooms r ON b.room_id = r.id
    WHERE b.id = ?
");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    header("Location: ../../views/dashboard/student/homepage.php");
    exit();
}

$user_id = getUserIdFromEmail($conn, $_SESSION['email']);

// Process payment form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'] ?? '';
    
    if (!$payment_method) {
        $error = "Please select a payment method.";
    } else {
        // Create payment record
        $amount = $booking['price_per_semester'];
        $transaction_id = "TEST-" . strtoupper(substr(md5(rand()), 0, 10));
        $payment_type = 'Full Payment';
        $status = 'Pending'; // Initially pending
        
        $stmt_payment = $conn->prepare("
            INSERT INTO payments (booking_id, hostel_id, user_id, amount, payment_method, transaction_id, payment_type, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt_payment->bind_param(
            "iiidssss",
            $booking_id, $booking['hostel_id'], $user_id, $amount, $payment_method, $transaction_id, $payment_type, $status
        );
        
        if ($stmt_payment->execute()) {
            $payment_id = $conn->insert_id;
            
            // For testing, simulate payment completion
            if ($_POST['test_mode'] === 'success') {
                // Update payment status to completed
                $stmt_update = $conn->prepare("UPDATE payments SET status = 'Completed' WHERE id = ?");
                $stmt_update->bind_param("i", $payment_id);
                $stmt_update->execute();
                
                // Update booking status to confirmed
                $stmt_booking = $conn->prepare("UPDATE bookings SET status = 'Confirmed' WHERE id = ?");
                $stmt_booking->bind_param("i", $booking_id);
                $stmt_booking->execute();
                
                // Notify hostel manager
                $manager_id = getHostelManagerId($conn, $booking['hostel_id']);
                if ($manager_id) {
                    createNotification(
                        $conn,
                        'payment_completed',
                        'Payment Received',
                        "Payment of " . number_format($amount) . " UGX has been received for booking #$booking_id.",
                        $booking['hostel_id'],
                        $payment_id,
                        $manager_id
                    );
                }
                
                $_SESSION['payment_success'] = "Payment successful! Your booking has been confirmed.";
            } else {
                // Simulate failed payment
                $stmt_update = $conn->prepare("UPDATE payments SET status = 'Failed' WHERE id = ?");
                $stmt_update->bind_param("i", $payment_id);
                $stmt_update->execute();
                
                $_SESSION['payment_error'] = "Payment failed. Please try again.";
            }
            
            header("Location: ../../views/payment/payment_result.php?booking_id=$booking_id");
            exit();
        } else {
            $error = "Error processing payment: " . $conn->error;
        }
    }
}

// Get available payment methods
$stmt_methods = $conn->prepare("
    SELECT method_name FROM payment_methods 
    WHERE hostel_id = ? AND is_active = 1
");
$stmt_methods->bind_param("i", $booking['hostel_id']);
$stmt_methods->execute();
$result_methods = $stmt_methods->get_result();
$payment_methods = [];
while ($row = $result_methods->fetch_assoc()) {
    $payment_methods[] = $row['method_name'];
}

// If no payment methods are defined, add some defaults for testing
if (empty($payment_methods)) {
    $payment_methods = ['MTN Mobile Money', 'Airtel Money', 'Credit/Debit Card', 'Bank Transfer', 'Cash'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - <?php echo htmlspecialchars($booking['hostel_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .payment-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .payment-header {
            margin-bottom: 30px;
            text-align: center;
        }
        .booking-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .payment-methods {
            margin-bottom: 30px;
        }
        .payment-method-option {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .payment-method-option:hover {
            border-color: #2d3a4c;
            background: #f8f9fa;
        }
        .payment-method-option.selected {
            border-color: #2d3a4c;
            background: #f0f8ff;
        }
        .test-mode-toggle {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px dashed #ddd;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-header">
            <h1>Complete Payment</h1>
            <p><a href="../../controllers/booking/book_room.php?hostel_id=<?php echo $booking['hostel_id']; ?>&room_type=<?php echo urlencode($booking['room_type']); ?>"><i class="fas fa-arrow-left"></i> Back to Booking</a></p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="booking-summary">
            <h3>Booking Summary</h3>
            <p><strong>Hostel:</strong> <?php echo htmlspecialchars($booking['hostel_name']); ?></p>
            <p><strong>Room Type:</strong> <?php echo htmlspecialchars(ucfirst($booking['room_type'])); ?></p>
            <p><strong>Check-in:</strong> <?php echo htmlspecialchars($booking['check_in_date']); ?></p>
            <p><strong>Check-out:</strong> <?php echo htmlspecialchars($booking['check_out_date']); ?></p>
            <p><strong>Amount Due:</strong> <?php echo number_format($booking['price_per_semester']); ?> UGX</p>
        </div>
        
        <form method="POST">
            <div class="payment-methods">
                <h3>Select Payment Method</h3>
                
                <?php foreach ($payment_methods as $method): ?>
                    <div class="payment-method-option" onclick="selectPaymentMethod(this, '<?php echo $method; ?>')">
                        <input type="radio" name="payment_method" value="<?php echo htmlspecialchars($method); ?>" id="method_<?php echo md5($method); ?>" style="display: none;">
                        <label for="method_<?php echo md5($method); ?>">
                            <?php 
                            $icon = 'fa-money-bill-wave';
                            if (stripos($method, 'mtn') !== false) $icon = 'fa-mobile-alt';
                            elseif (stripos($method, 'airtel') !== false) $icon = 'fa-mobile-alt';
                            elseif (stripos($method, 'card') !== false) $icon = 'fa-credit-card';
                            elseif (stripos($method, 'bank') !== false) $icon = 'fa-university';
                            ?>
                            <i class="fas <?php echo $icon; ?>"></i> <?php echo htmlspecialchars($method); ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="test-mode-toggle">
                <h4>Test Mode</h4>
                <p class="text-muted">For testing purposes only</p>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="test_mode" id="test_success" value="success" checked>
                    <label class="form-check-label" for="test_success">
                        Simulate Successful Payment
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="test_mode" id="test_fail" value="fail">
                    <label class="form-check-label" for="test_fail">
                        Simulate Failed Payment
                    </label>
                </div>
            </div>
            
            <div class="d-grid gap-2 mt-4">
                <button type="submit" class="btn btn-primary" id="payButton" disabled>Pay Now</button>
            </div>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectPaymentMethod(element, method) {
            // Remove selected class from all options
            document.querySelectorAll('.payment-method-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            element.classList.add('selected');
            
            // Check the radio button
            element.querySelector('input[type="radio"]').checked = true;
            
            // Enable pay button
            document.getElementById('payButton').disabled = false;
        }
    </script>
</body>
</html>
