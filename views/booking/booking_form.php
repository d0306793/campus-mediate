<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>Complete Your Booking</h4>
                        <p class="mb-0">Please provide your full name for booking verification</p>
                    </div>
                    <div class="card-body">
                        <form action="../../controllers/booking/process_booking_with_name.php" method="POST">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                                <div class="form-text">This name will appear on your booking confirmation and payment receipts</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="check_in_date" class="form-label">Check-in Date *</label>
                                <input type="date" class="form-control" id="check_in_date" name="check_in_date" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="check_out_date" class="form-label">Check-out Date *</label>
                                <input type="date" class="form-control" id="check_out_date" name="check_out_date" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="quantity" class="form-label">Number of Rooms</label>
                                <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="1" max="5">
                            </div>
                            
                            <!-- Hidden fields for booking details -->
                            <input type="hidden" name="hostel_id" value="<?php echo $_GET['hostel_id'] ?? ''; ?>">
                            <input type="hidden" name="room_id" value="<?php echo $_GET['room_id'] ?? ''; ?>">
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Submit Booking Request</button>
                                <a href="../dashboard/student/homepage.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>