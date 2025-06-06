<?php
session_start();
include '../../config/config.php';
include '../../includes/functions.php';

if (!isset($_SESSION['email'])) {
    header("Location: ../../views/auth/login.php");
    exit();
}

$payment_id = $_GET['id'] ?? 0;

if (!$payment_id) {
    header("Location: ../../views/dashboard/student/homepage.php#payments");
    exit();
}

// Get payment details with room number
$stmt = $conn->prepare("
    SELECT p.*, b.check_in_date, b.check_out_date, b.id as booking_id, 
           h.name as hostel_name, h.location, h.contact,
           r.room_type, r.price_per_semester,
           u.username as student_name, u.email as student_email,
           (SELECT ra.assigned_room_number FROM room_assignments ra WHERE ra.booking_id = b.id LIMIT 1) as room_number
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    JOIN hostels h ON p.hostel_id = h.id
    JOIN rooms r ON b.room_id = r.id
    JOIN users u ON p.user_id = u.id
    WHERE p.id = ?
");
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();

if (!$payment) {
    header("Location: ../../views/dashboard/student/homepage.php#payments");
    exit();
}

// Check if user has permission to view this payment
$user_id = getUserIdFromEmail($conn, $_SESSION['email']);
$user_role = getUserRoleFromEmail($conn, $_SESSION['email']);

if ($user_role !== 'admin' && $payment['user_id'] != $user_id) {
    header("Location: ../../views/dashboard/student/homepage.php#payments");
    exit();
}

// Generate a more complex receipt number
$receipt_number = 'CM-' . date('Y', strtotime($payment['payment_date'])) . '-' . 
                 str_pad($payment['id'], 6, '0', STR_PAD_LEFT) . '-' . 
                 strtoupper(substr(md5($payment['transaction_id']), 0, 6));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
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
        .payment-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .payment-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: bold;
        }
        .payment-completed {
            background-color: #d4edda;
            color: #155724;
        }
        .payment-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .payment-failed {
            background-color: #f8d7da;
            color: #721c24;
        }
        .btn-print {
            background-color: #6c757d;
            color: white;
        }
        .receipt-header {
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .receipt-number {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        .receipt-date {
            font-size: 0.9rem;
            color: #666;
        }
        .receipt-footer {
            border-top: 1px solid #ddd;
            padding-top: 15px;
            margin-top: 20px;
            font-size: 0.9rem;
        }
        .qr-code {
            text-align: center;
            margin-top: 20px;
        }
        .qr-code img {
            width: 100px;
            height: 100px;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 0;
                margin: 0;
            }
            .payment-container {
                box-shadow: none;
                margin: 0;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-header no-print">
            <h1>Payment Receipt</h1>
            <p><a href="../../views/dashboard/student/homepage.php#payments"><i class="fas fa-arrow-left"></i> Back to Payments</a></p>
        </div>
        
        <div class="receipt-header">
            <div class="row">
                <div class="col-md-6">
                    <img src="../../assets/images/logo/logo.png" alt="Campus Mediate Logo" style="max-height: 60px;">
                    <h2>Campus Mediate</h2>
                    <p>Your Hostel Booking Partner</p>
                </div>
                <div class="col-md-6 text-end">
                    <div class="receipt-number">Receipt #: <?php echo $receipt_number; ?></div>
                    <div class="receipt-date">Date: <?php echo date('F j, Y', strtotime($payment['payment_date'])); ?></div>
                    <div class="receipt-date">Time: <?php echo date('h:i A', strtotime($payment['payment_date'])); ?></div>
                </div>
            </div>
        </div>
        
        <div class="payment-details">
            <div class="row">
                <div class="col-md-6">
                    <h5>Payment Information</h5>
                    <p><strong>Payment ID:</strong> #<?php echo $payment['id']; ?></p>
                    <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($payment['transaction_id']); ?></p>
                    <p><strong>Method:</strong> <?php echo htmlspecialchars($payment['payment_method']); ?></p>
                    <p><strong>Status:</strong> 
                        <span class="payment-status payment-<?php echo strtolower($payment['status']); ?>">
                            <?php echo htmlspecialchars($payment['status']); ?>
                        </span>
                    </p>
                </div>
                <div class="col-md-6">
                    <h5>Student Information</h5>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars(ucfirst($payment['student_name'])); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($payment['student_email']); ?></p>
                    <p><strong>Booking ID:</strong> #<?php echo $payment['booking_id']; ?></p>
                </div>
            </div>
            
            <hr>
            
            <div class="row">
                <div class="col-md-6">
                    <h5>Hostel Details</h5>
                    <p><strong>Hostel:</strong> <?php echo htmlspecialchars($payment['hostel_name']); ?></p>
                    <p><strong>Location:</strong> <?php echo htmlspecialchars($payment['location']); ?></p>
                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($payment['contact']); ?></p>
                    <p><strong>Room Type:</strong> <?php echo htmlspecialchars(ucfirst($payment['room_type'])); ?></p>
                    <?php if (!empty($payment['room_number'])): ?>
                    <p><strong>Room Number:</strong> <?php echo htmlspecialchars($payment['room_number']); ?></p>
                    <?php else: ?>
                    <p><strong>Room Number:</strong> <span class="text-muted">Not yet assigned</span></p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <h5>Booking Period</h5>
                    <p><strong>Check-in:</strong> <?php echo htmlspecialchars($payment['check_in_date']); ?></p>
                    <p><strong>Check-out:</strong> <?php echo htmlspecialchars($payment['check_out_date']); ?></p>
                    
                    <h5 class="mt-4">Payment Summary</h5>
                    <table class="table table-sm">
                        <tr>
                            <td>Accommodation Fee</td>
                            <td class="text-end"><?php echo number_format($payment['amount'], 2); ?> UGX</td>
                        </tr>
                        <tr>
                            <td>Processing Fee</td>
                            <td class="text-end">0.00 UGX</td>
                        </tr>
                        <tr>
                            <th>Total Paid</th>
                            <th class="text-end"><?php echo number_format($payment['amount'], 2); ?> UGX</th>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="receipt-footer">
            <div class="row">
                <div class="col-md-8">
                    <p><strong>Payment Notes:</strong></p>
                    <p>This receipt confirms your payment for accommodation at <?php echo htmlspecialchars($payment['hostel_name']); ?>.</p>
                    <p>Please keep this receipt for your records. For any inquiries, please contact us at support@campusmediate.com or call +256-700-123456.</p>
                </div>
                <div class="col-md-4 qr-code">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo urlencode($receipt_number); ?>" alt="QR Code">
                    <p class="mt-2">Scan to verify</p>
                </div>
            </div>
        </div>
        
        <div class="d-flex justify-content-between mt-4 no-print">
            <button class="btn btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Print Receipt
            </button>
            <a href="../../views/dashboard/student/homepage.php#payments" class="btn btn-primary">Back to Dashboard</a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
