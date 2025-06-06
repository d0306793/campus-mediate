<?php
session_start();
include 'config.php';
include 'functions.php';
include 'payment_config.php';  // Include the payment configuration file


// Check if user is logged in and payment_id is set
if (!isset($_SESSION['email']) || !isset($_SESSION['payment_id'])) {
    header("Location: login1.php");
    exit();
}

$payment_id = $_SESSION['payment_id'];

// Get payment details
$stmt = $conn->prepare("
    SELECT p.*, b.id as booking_id, u.email as user_email, u.username as user_name
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    JOIN users u ON p.user_id = u.id
    WHERE p.id = ?
");
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['payments_error_message'] = "Payment not found.";
    header("Location: student_dashboard.php#payments");
    exit();
}

$payment = $result->fetch_assoc();
$stmt->close();

// Flutterwave API configuration
$flutterwave_public_key = getFlutterwavePublicKey(); // Replace with your actual key
$tx_ref = "CM-PMT-" . time() . "-" . $payment_id;
$redirect_url = "https://yourwebsite.com/flutterwave_callback.php";

// Update payment with transaction reference
$stmt_update = $conn->prepare("UPDATE payments SET transaction_id = ? WHERE id = ?");
$stmt_update->bind_param("si", $tx_ref, $payment_id);
$stmt_update->execute();
$stmt_update->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Payment</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <div class="payment-container">
        <h2>Complete Your Payment</h2>
        <p>You are about to make a payment of <?php echo number_format($payment['amount'], 2); ?> UGX.</p>
        
        <button type="button" id="pay-button" class="btn-primary">Pay Now</button>
        <a href="student_dashboard.php#payments" class="btn-cancel">Cancel</a>
    </div>
    
    <script src="https://checkout.flutterwave.com/v3.js"></script>
    <script>
        document.getElementById('pay-button').addEventListener('click', function() {
            FlutterwaveCheckout({
                public_key: "<?php echo $flutterwave_public_key; ?>",
                tx_ref: "<?php echo $tx_ref; ?>",
                amount: <?php echo $payment['amount']; ?>,
                currency: "UGX",
                payment_options: "<?php echo strtolower(str_replace(' ', '_', $payment['payment_method'])); ?>",
                redirect_url: "<?php echo $redirect_url; ?>",
                customer: {
                    email: "<?php echo $payment['user_email']; ?>",
                    name: "<?php echo $payment['user_name']; ?>"
                },
                customizations: {
                    title: "Campus Mediate Hostel Payment",
                    description: "Payment for booking #<?php echo $payment['booking_id']; ?>",
                    logo: "https://yourwebsite.com/assets/images/logo.png"
                }
            });
        });
    </script>
</body>
</html>
