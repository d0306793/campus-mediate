<?php
session_start();
include '../../config/config.php';
include '../../includes/functions.php';

if (!isset($_SESSION['email'])) {
    header("Location: ../../views/auth/login.php");
    exit();
}

$hostel_id = $_GET['id'] ?? 0;
if (!$hostel_id) {
    header("Location: " . ($_SESSION['role'] === 'student' ?
     '../../views/dashboard/student/homepage.php' : 
     '../../views/dashboard/manager/dashboard.php'));
    exit();
}

// Get hostel details
$stmt = $conn->prepare("
    SELECT h.*, 
           COUNT(DISTINCT r.id) as total_room_types,
           SUM(CASE WHEN r.status = 'Available' THEN r.quantity ELSE 0 END) as available_rooms
    FROM hostels h
    LEFT JOIN rooms r ON h.id = r.hostel_id
    WHERE h.id = ?
    GROUP BY h.id
");
$stmt->bind_param("i", $hostel_id);
$stmt->execute();
$hostel = $stmt->get_result()->fetch_assoc();

// Get room types and prices
$stmt_rooms = $conn->prepare("
    SELECT room_type, quantity, price_per_semester, status
    FROM rooms 
    WHERE hostel_id = ?
");
$stmt_rooms->bind_param("i", $hostel_id);
$stmt_rooms->execute();
$rooms = $stmt_rooms->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($hostel['name']); ?> - Details</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/hostel-details.css">
</head>
<body>
    <div class="hostel-details-container">
        <!-- Hero Section -->
        <div class="hostel-hero" style="background-image: url('../../<?php echo htmlspecialchars($hostel['image_path']); ?>')">
            <div class="hero-overlay">
                <a href="../../views/dashboard/student/homepage.php#hostels" class="back-button"><i class="fas fa-arrow-left"></i> Back</a>
                <div class="container">
                    <div class="hero-content">
                        <h1><?php echo htmlspecialchars($hostel['name']); ?></h1>
                        <p class="location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($hostel['location']); ?></p>
                        <div class="status-badge <?php echo $hostel['status'] === 'Active' ? 'active' : 'inactive'; ?>">
                            <?php echo htmlspecialchars($hostel['status']); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="container">
            <div class="row">
                <!-- Left Column -->
                <div class="col-md-8">
                    <section class="details-section">
                        <h2>About this Hostel</h2>
                        <p><?php echo nl2br(htmlspecialchars($hostel['description'])); ?></p>
                    </section>

                    <section class="details-section">
                        <h2>Amenities</h2>
                        <div class="amenities-grid">
                            <?php 
                            $amenities = json_decode($hostel['amenities'], true);
                            foreach ($amenities as $amenity): 
                                $icon = getAmenityIcon($amenity); // Create this helper function
                            ?>
                            <div class="amenity-item">
                                <i class="fas <?php echo $icon; ?>"></i>
                                <span><?php echo htmlspecialchars($amenity); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="details-section">
                        <h2>Room Types</h2>
                        <?php if (empty($rooms)): ?>
                            <div class="alert alert-info">
                                <p><i class="fas fa-info-circle"></i> No rooms have been added to this hostel yet. The manager is still setting up the accommodation details.</p>
                            </div>
                        <?php else: ?>
                            <div class="room-types-grid">
                                <?php foreach ($rooms as $room): ?>
                                <div class="room-type-card">
                                    <div class="room-type-header">
                                        <h3><?php echo htmlspecialchars(ucfirst($room['room_type'])); ?></h3>
                                        <span class="price"><?php echo number_format($room['price_per_semester']); ?> UGX/semester</span>
                                    </div>
                                    <div class="room-type-details">
                                        <p><i class="fas fa-door-open"></i> Available: <?php echo $room['quantity']; ?> rooms</p>
                                        <p><i class="fas fa-info-circle"></i> Status: <?php echo $room['status']; ?></p>
                                    </div>
                                    <?php if ($_SESSION['role'] === 'student' && $room['status'] === 'Available'): ?>
                                    <button class="btn btn-book" onclick="bookRoom(<?php echo $hostel_id; ?>, '<?php echo $room['room_type']; ?>')">
                                        Book Now
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                </div>

                <!-- Right Column -->
                <div class="col-md-4">
                    <div class="contact-card">
                        <h3>Contact Information</h3>
                        <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($hostel['contact']); ?></p>
                        <div class="quick-stats">
                            <div class="stat-item">
                                <span class="stat-label">Total Rooms</span>
                                <span class="stat-value"><?php echo $hostel['total_rooms']; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Available</span>
                                <span class="stat-value"><?php echo $hostel['available_rooms']; ?></span>
                            </div>
                        </div>
                        <?php if ($_SESSION['role'] === 'student'): ?>
                        <button class="btn btn-primary btn-block" onclick="contactHostel(<?php echo $hostel_id; ?>)">
                            Contact Hostel
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function bookRoom(hostelId, roomType) {
        window.location.href = "../../controllers/booking/book_room.php?hostel_id=" + hostelId + "&room_type=" + encodeURIComponent(roomType);
    }

    function contactHostel(hostelId) {
        // Implement contact functionality
        alert('Contact feature coming soon!');
    }

    </script>
</body>
</html>
