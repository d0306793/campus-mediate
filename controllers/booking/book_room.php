<?php
session_start();
include '../../config/config.php';
include '../../includes/functions.php';

if (!isset($_SESSION['email']) || getUserRoleFromEmail($conn, $_SESSION['email']) !== 'student') {
    header("Location: ../../views/auth/login.php");
    exit();
}

$hostel_id = $_GET['hostel_id'] ?? 0;
$room_type = $_GET['room_type'] ?? '';

if (!$hostel_id || !$room_type) {
    header("Location: ../../views/dashboard/student/homepage.php");
    exit();
}

$user_id = getUserIdFromEmail($conn, $_SESSION['email']);

// Get hostel and room details
$stmt_hostel = $conn->prepare("SELECT name, location FROM hostels WHERE id = ?");
$stmt_hostel->bind_param("i", $hostel_id);
$stmt_hostel->execute();
$hostel = $stmt_hostel->get_result()->fetch_assoc();

$stmt_room = $conn->prepare("SELECT id, price_per_semester FROM rooms WHERE hostel_id = ? AND room_type = ?");
$stmt_room->bind_param("is", $hostel_id, $room_type);
$stmt_room->execute();
$room = $stmt_room->get_result()->fetch_assoc();
$room_id = $room['id'];
$price = $room['price_per_semester'];

// Process booking form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $check_in_date = $_POST['check_in_date'] ?? '';
    $check_out_date = $_POST['check_out_date'] ?? '';
    $special_requests = $_POST['special_requests'] ?? '';
    
    // If check_out_date is empty or not provided, use semester end date
    if (empty($check_out_date)) {
        $check_out_date = getActiveSemesterEndDate($conn);
    }
    
    if (!$check_in_date || !$check_out_date) {
        $error = "Please select check-in and check-out dates.";
    } else {
        // Check room availability
        if (checkRoomAvailability($conn, $hostel_id, $room_id, $check_in_date, $check_out_date)) {
            // Create booking
            $stmt_booking = $conn->prepare("
                INSERT INTO bookings (hostel_id, room_id, user_id, check_in_date, check_out_date, status, special_requests)
                VALUES (?, ?, ?, ?, ?, 'Pending', ?)
            ");
            $stmt_booking->bind_param("iiisss", $hostel_id, $room_id, $user_id, $check_in_date, $check_out_date, $special_requests);
            
            if ($stmt_booking->execute()) {
                $booking_id = $conn->insert_id;
                
                // Notify hostel manager
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
                
                // Redirect to payment page
                header("Location: ../../controllers/payment/test_payment.php?booking_id=$booking_id");
                exit();
            } else {
                $error = "Error creating booking: " . $conn->error;
            }
        } else {
            $error = "Sorry, no rooms available for the selected dates.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Room - <?php echo htmlspecialchars($hostel['name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .booking-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .booking-header {
            margin-bottom: 30px;
            text-align: center;
        }
        .booking-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .price-summary {
            font-size: 1.2rem;
            font-weight: bold;
            text-align: right;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="booking-container">
        <div class="booking-header">
            <h1>Book a Room</h1>
            <p><a href="../../views/hostel/hostel_details.php?id=<?php echo $hostel_id; ?>"><i class="fas fa-arrow-left"></i> Back to Hostel Details</a></p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="booking-details">
            <h3><?php echo htmlspecialchars($hostel['name']); ?></h3>
            <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($hostel['location']); ?></p>
            <p><i class="fas fa-bed"></i> Room Type: <?php echo htmlspecialchars(ucfirst($room_type)); ?></p>
            <p><i class="fas fa-money-bill-wave"></i> Price: <?php echo number_format($price); ?> UGX per semester</p>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label for="check_in_date">Check-in Date</label>
                <input type="date" id="check_in_date" name="check_in_date" class="form-control" required onchange="updateCheckoutDate()">
            </div>

            <div class="form-group">
                <label for="check_out_date">Check-out Date</label>
                <input type="date" id="check_out_date" name="check_out_date" class="form-control" readonly>
                <small id="checkout_display" class="form-text text-muted">Check-out date will be set to semester end</small>
            </div>

            <!-- Add this for admin or special cases -->
            <div class="form-check mt-2">
                <input type="checkbox" id="custom_checkout" class="form-check-input" onchange="toggleCustomCheckout()">
                <label for="custom_checkout" class="form-check-label">Use custom check-out date</label>
            </div>

            
            <div class="price-summary">
                Total: <?php echo number_format($price); ?> UGX
            </div>
            
            <div class="d-grid gap-2 mt-4">
                <button type="submit" class="btn btn-primary">Proceed to Payment</button>
            </div>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validate dates
        document.getElementById('check_out_date').addEventListener('change', function() {
            const checkInDate = document.getElementById('check_in_date').value;
            const checkOutDate = this.value;
            
            if (checkInDate && checkOutDate && checkOutDate <= checkInDate) {
                alert('Check-out date must be after check-in date');
                this.value = '';
            }
        });

        function toggleCustomCheckout() {
            const checkoutField = document.getElementById('check_out_date');
            const isCustom = document.getElementById('custom_checkout').checked;
            
            checkoutField.readOnly = !isCustom;
            
            if (!isCustom) {
                // Reset to semester end date
                updateCheckoutDate();
            }
        }

    </script>
</body>
</html>
