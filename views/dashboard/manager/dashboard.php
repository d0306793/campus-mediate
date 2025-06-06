<?php
session_start();

include "../../../config/config.php";
include "../../../includes/functions.php";

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

// Check if we're coming from form submission
$from_form_submission = isset($_SESSION['from_form_submission']) && $_SESSION['from_form_submission'];
unset($_SESSION['from_form_submission']); // Clear it after use

$manager_email = $_SESSION['email'];
$username = getUsernameFromEmail($conn, $manager_email);
$manager_id = getUserIdFromEmail($conn, $manager_email);

// Initialize data arrays
$hostel_exists = false;
$hostel = [
    'name' => '', 'location' => '', 'description' => '', 'amenities' => '', 
    'total_rooms' => 0, 'available_rooms' => 0, 'status' => 'Inactive', 
    'contact' => '', 'image_path' => ''
];

// Fetch hostel info
if ($manager_id !== null) {
    $stmt_hostel = $conn->prepare("SELECT * FROM hostels WHERE manager_id = ? LIMIT 1");
    $stmt_hostel->bind_param("i", $manager_id);
    $stmt_hostel->execute();
    $result_hostel = $stmt_hostel->get_result();
    if ($result_hostel->num_rows > 0) {
        $hostel = $result_hostel->fetch_assoc();
        $hostel_exists = true;
    }
    $stmt_hostel->close();
}

// Get room statistics
$occupied_rooms = 0;
$available_rooms = 0;

// Always use the total_rooms from hostel table
$total_rooms = $hostel['total_rooms'] ?? 0;

if ($hostel_exists) {
    // Check if we have any rooms in the rooms table
    $stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM rooms WHERE hostel_id = ?");
    $stmt_check->bind_param("i", $hostel['id']);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $row_check = $result_check->fetch_assoc();
    $has_rooms = ($row_check['count'] > 0);
    $stmt_check->close();
    
    if ($has_rooms) {
        // If we have rooms in the rooms table, get occupied and available statistics
        $stmt_rooms = $conn->prepare("SELECT 
                                    SUM(CASE WHEN status = 'Occupied' THEN (CASE WHEN quantity IS NOT NULL THEN quantity ELSE 1 END) ELSE 0 END) as occupied,
                                    SUM(CASE WHEN status = 'Available' THEN (CASE WHEN quantity IS NOT NULL THEN quantity ELSE 1 END) ELSE 0 END) as available
                                    FROM rooms WHERE hostel_id = ?");
        $stmt_rooms->bind_param("i", $hostel['id']);
        $stmt_rooms->execute();
        $result_rooms = $stmt_rooms->get_result();
        if ($row = $result_rooms->fetch_assoc()) {
            $occupied_rooms = $row['occupied'] ?? 0;
            $available_rooms = $row['available'] ?? 0;
        }
        $stmt_rooms->close();
    } else {
        // If no rooms in rooms table, all rooms are available
        $available_rooms = $total_rooms;
    }
}

// Get booking statistics
$total_bookings = 0;
$pending_bookings = 0;
$confirmed_bookings = 0;

if ($hostel_exists) {
    $stmt_bookings = $conn->prepare("SELECT COUNT(*) as total,
                                   SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                                   SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed
                                   FROM bookings WHERE hostel_id = ?");
    $stmt_bookings->bind_param("i", $hostel['id']);
    $stmt_bookings->execute();
    $result_bookings = $stmt_bookings->get_result();
    if ($row = $result_bookings->fetch_assoc()) {
        $total_bookings = $row['total'] ?? 0;
        $pending_bookings = $row['pending'] ?? 0;
        $confirmed_bookings = $row['confirmed'] ?? 0;
    }
    $stmt_bookings->close();
}

// Get payment statistics
$total_payments = 0;
$total_amount = 0;

if ($hostel_exists) {
    $stmt_payments = $conn->prepare("SELECT COUNT(*) as total, SUM(amount) as total_amount 
                                  FROM payments WHERE hostel_id = ?");
    $stmt_payments->bind_param("i", $hostel['id']);
    $stmt_payments->execute();
    $result_payments = $stmt_payments->get_result();
    if ($row = $result_payments->fetch_assoc()) {
        $total_payments = $row['total'] ?? 0;
        $total_amount = $row['total_amount'] ?? 0;
    }
    $stmt_payments->close();
}

// Get notification count
$notification_count = 0;
if ($manager_id !== null) {
    $stmt_notifications = $conn->prepare("SELECT COUNT(*) as count FROM notifications 
                                       WHERE recipient_id = ? AND is_read = 0");
    $stmt_notifications->bind_param("i", $manager_id);
    $stmt_notifications->execute();
    $result_notifications = $stmt_notifications->get_result();
    if ($row = $result_notifications->fetch_assoc()) {
        $notification_count = $row['count'] ?? 0;
    }
    $stmt_notifications->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Dashboard</title>
    <link rel="stylesheet" href="../../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .badge {
            background-color: #ff4757;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
            margin-left: 5px;
        }
        .dashboard-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .summary-card:hover {
            transform: translateY(-5px);
        }
        .summary-card h3 {
            margin-top: 0;
            color: #2d3a4c;
            font-size: 18px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        .summary-card .stat {
            font-size: 24px;
            font-weight: bold;
            color: #2d3a4c;
            margin: 15px 0;
        }
        .summary-card .details {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
        }
        .summary-card .icon {
            font-size: 32px;
            float: right;
            margin-top: -40px;
            color: rgba(45, 58, 76, 0.2);
        }
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .status-active {
            background-color: #4CAF50;
        }
        .status-inactive {
            background-color: #F44336;
        }
        .quick-actions {
            margin-top: 30px;
        }
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }
        .action-button {
            padding: 10px 15px;
            background-color: #2d3a4c;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
        }
        .action-button:hover {
            background-color: #3e4e66;
        }
        /* Hide all sections by default */
        section {
            display: none;
        }
        /* Show active section */
        section.active {
            display: block !important;
        }
        /* Default dashboard visible */
        #dashboard {
            display: block;
        }

        /* Hostel Card Styling for Dashboard */
        .hostel-summary {
            margin-top: 30px;
        }

        .hostel-summary h3 {
            margin-bottom: 20px;
            color: #2d3a4c;
        }

        .hostel-card-view {
            position: relative;
            height: 400px;
            background: transparent;
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 20px;
        }

        .hostel-card-view:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }

        .hostel-image-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        .hostel-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .hostel-card-view:hover .hostel-image-container img {
            transform: scale(1.1);
        }

        .hostel-image-container::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                to bottom,
                rgba(0, 0, 0, 0.1) 0%,
                rgba(0, 0, 0, 0.7) 70%,
                rgba(0, 0, 0, 0.8) 100%
            );
            transition: opacity 0.3s ease;
        }

        .hostel-details {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            padding: 2rem;
            color: white;
            z-index: 2;
        }

        .hostel-details h3 {
            color: white;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .hostel-location {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .hostel-amenities {
            list-style: none;
            padding: 0;
            margin: 1rem 0;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .hostel-amenities li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9rem;
            background: rgba(45, 58, 76, 0.3);
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            backdrop-filter: blur(4px);
        }

        .hostel-amenities i {
            color: #4CAF50;
            font-size: 1rem;
        }

        .edit-hostel-btn {
            width: auto;
            padding: 0.8rem 1.5rem;
            background-color: #2d3a4c;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            display: inline-block;
            margin-top: 1rem;
            text-decoration: none;
        }

        .edit-hostel-btn:hover {
            background-color: #3e4e66;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(45, 58, 76, 0.3);
        }



    </style>
    <script>
    // Define openTab function at the top so it's available for onclick attributes
    function openTab(evt, tabName) {
      // Hide all sections
      var sections = document.getElementsByTagName("section");
      for (var i = 0; i < sections.length; i++) {
        sections[i].classList.remove("active");
        sections[i].style.display = "none";
      }
      
      // Remove active class from all tab links
      var tablinks = document.getElementsByClassName("tab-link");
      for (var i = 0; i < tablinks.length; i++) {
        tablinks[i].classList.remove("active");
      }
      
      // Show the current tab and add active class to the button
      var targetSection = document.getElementById(tabName);
      if (targetSection) {
        targetSection.classList.add("active");
        targetSection.style.display = "block";
      } else {
        // If section doesn't exist, show dashboard instead
        document.getElementById("dashboard").classList.add("active");
        document.getElementById("dashboard").style.display = "block";
      }
      
      // Add active class to the tab link
      if (evt && evt.currentTarget) {
        evt.currentTarget.classList.add("active");
      }
    }

    function showBookingTab(tabName, event) {
        // Gets the event object from the parameter or from window.event (for older browsers)
        var e = event || window.event;

        // Hide all booking content
        var contents = document.getElementsByClassName('booking-content');
        for (var i = 0; i < contents.length; i++) {
            contents[i].classList.remove('active');
        }
        
        // Remove active class from all tabs
        var tabs = document.getElementsByClassName('booking-tab');
        for (var i = 0; i < tabs.length; i++) {
            tabs[i].classList.remove('active');
        }
        
        // Show selected tab content
        document.getElementById(tabName + '-bookings').classList.add('active');
        
        // Add active class to clicked tab
        if (e && e.currentTarget) {
            event.currentTarget.classList.add('active');
        }
    }

    function showPaymentTab(tabName, event) {
        // Get the event object from thhe parameter or from window.event (for older browsers)
        var e = event || window.event;

        // Hide all payment content
        var contents = document.getElementsByClassName('payment-content');
        for (var i = 0; i < contents.length; i++) {
            contents[i].classList.remove('active');
        }
        
        // Remove active class from all tabs
        var tabs = document.getElementsByClassName('payment-tab');
        for (var i = 0; i < tabs.length; i++) {
            tabs[i].classList.remove('active');
        }
        
        // Show selected tab content
        document.getElementById(tabName + '-payments').classList.add('active');
        
        // Add active class to clicked tab
        if (e && e.currentTarget) {
            event.currentTarget.classList.add('active');
        }
    }

    // Initialize tabs on page load
    document.addEventListener("DOMContentLoaded", function() {
      // Check for hash in URL first
      if (window.location.hash) {
        var tabName = window.location.hash.substring(1);
        var tabLink = document.querySelector('a[href="#' + tabName + '"]');
        if (tabLink) {
          // Activate the tab from hash
          tabLink.click();
        } else {
          // Default to dashboard if hash doesn't match any tab
          document.getElementById("defaultOpen").click();
        }
      } else {
        // If no hash or invalid hash, click the default tab
        document.getElementById("defaultOpen").click();
      }
    });
    </script>
</head>
<body>
    <aside class="sidebar">
        <div class="profile-section">
            <br>
            <div class="profile-avatar">
                <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="32" cy="32" r="30" stroke="white" stroke-width="2" fill="#2d3a4c"/>
                    <circle cx="32" cy="24" r="10" fill="white"/>
                    <path d="M16 52c0-8.837 7.163-16 16-16s16 7.163 16 16" fill="white"/>
                </svg>
            </div>
            <br>
            <h2>👋<?php echo $_SESSION['greeting'] = getGreeting()." ". getUserRoleFromEmail($conn, $_SESSION['email'])." ".getUsernameFromEmail($conn, $_SESSION['email']); ?></h2>
        </div>
        <ul>
            <!-- <li><a href="#dashboard" class="tab-link" onclick="openTab(event, 'dashboard')" id="defaultOpen"> Dashboard</a></li> 
            <li><a href="#hostel" class="tab-link" onclick="openTab(event, 'hostel')"><?php // echo $hostel_exists ? 'My Hostel' : 'Add Hostel'; ?></a></li>
            <li><a href="#rooms" class="tab-link" onclick="openTab(event, 'rooms')">🛏️ Rooms</a></li>
            <li><a href="#bookings" class="tab-link" onclick="openTab(event, 'bookings')">📋 Bookings</a></li>
            <li><a href="#payments" class="tab-link" onclick="openTab(event, 'payments')">💳 Payments</a></li>
            <li><a href="#notifications" class="tab-link" onclick="openTab(event, 'notifications')">🔔 Notifications</a></li>
            <li><a href="#reports" class="tab-link" onclick="openTab(event, 'reports')">📊 Reports</a></li>
            <li><a href="#profile" class="tab-link" onclick="openTab(event, 'profile')">👤 Profile</a></li>
            <li><a href="logout.php" class="tab-link logout-link">🚪 Logout</a></li> -->
            <li><a href="#dashboard" class="tab-link" onclick="openTab(event, 'dashboard')" id="defaultOpen"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li> 
            <li><a href="#hostel" class="tab-link" onclick="openTab(event, 'hostel')"><i class="fas fa-hotel"></i> <?php echo $hostel_exists ? 'My Hostel' : 'Add Hostel'; ?></a></li>
            <li><a href="#rooms" class="tab-link" onclick="openTab(event, 'rooms')"><i class="fas fa-bed"></i> Rooms</a></li>
            <li><a href="#bookings" class="tab-link" onclick="openTab(event, 'bookings')"><i class="fas fa-calendar-check"></i> Bookings</a></li>
            <li><a href="#payments" class="tab-link" onclick="openTab(event, 'payments')"><i class="fas fa-credit-card"></i> Payments</a></li>
            <li><a href="#notifications" class="tab-link" onclick="openTab(event, 'notifications')"><i class="fas fa-bell"></i> Notifications</a></li>
            <li><a href="#reports" class="tab-link" onclick="openTab(event, 'reports')"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="#profile" class="tab-link" onclick="openTab(event, 'profile')"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="../../../logout.php" class="tab-link logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>

        </ul>
    </aside>
    <main class="content">
        <?php if (isset($_SESSION['general_success_message'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['general_success_message']); ?>
            </div>
            <?php unset($_SESSION['general_success_message']); ?>
            <?php if ($from_form_submission): ?>
                <script>
                    // Force tab activation after form submission
                    window.onload = function() {
                        console.log("Form submission detected, forcing tab activation");
                        setTimeout(function() {
                            var tabName = window.location.hash.substring(1) || 'dashboard';
                            var tabLink = document.querySelector('a[href="#' + tabName + '"]');
                            if (tabLink) {
                                tabLink.click();
                            } else {
                                document.getElementById("defaultOpen").click();
                            }
                        }, 100);
                    };
                </script>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['general_error_message'])): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($_SESSION['general_error_message']); ?>
            </div>
            <?php unset($_SESSION['general_error_message']); ?>
        <?php endif; ?>

        <section id="dashboard">
            <h2>Dashboard Overview</h2>

            <?php if (isset($_SESSION['dashboard_success_message'])): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($_SESSION['dashboard_success_message']); ?>
                </div>
                <?php unset($_SESSION['dashboard_success_message']); ?>
            <?php endif; ?>
            <p>Welcome to your enhanced dashboard, <?php echo htmlspecialchars($username); ?>!</p>
            
            <?php if (!$hostel_exists): ?>
                <div class="summary-card">
                    <h3>Get Started</h3>
                    <p>You haven't added your hostel details yet. Add your hostel to access all features.</p>
                    <a href="#hostel" class="action-button tab-link" onclick="openTab(event, 'hostel')">Add Hostel Now</a>
                </div>
            <?php else: ?>
                <div class="dashboard-summary">
                    <div class="summary-card">
                        <h3>Hostel Status</h3>
                        <div class="icon">🏨</div>
                        <div class="stat">
                            <span class="status-indicator <?php echo $hostel['status'] === 'Active' ? 'status-active' : 'status-inactive'; ?>"></span>
                            <?php echo htmlspecialchars($hostel['status']); ?>
                        </div>
                        <div class="details">
                            <span><?php echo htmlspecialchars($hostel['name']); ?></span>
                            <span><?php echo htmlspecialchars($hostel['location']); ?></span>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <h3>Room Statistics</h3>
                        <div class="icon">🛏️</div>
                        <div class="stat"><?php echo $hostel['total_rooms']; ?> Total Rooms</div>
                        <div class="details">
                            <span><?php echo $available_rooms; ?> Available</span>
                            <span><?php echo $occupied_rooms; ?> Occupied</span>
                            <?php if ($hostel['total_rooms'] > 0 && !$has_rooms): ?>
                            <div style="margin-top: 10px; font-size: 12px; color: #666;">
                                <a href="#rooms" class="tab-link" onclick="openTab(event, 'rooms')">Add room inventory</a> to manage availability
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <h3>Booking Status</h3>
                        <div class="icon">📋</div>
                        <div class="stat"><?php echo $total_bookings; ?> Bookings</div>
                        <div class="details">
                            <span><?php echo $pending_bookings; ?> Pending</span>
                            <span><?php echo $confirmed_bookings; ?> Confirmed</span>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <h3>Payment Summary</h3>
                        <div class="icon">💳</div>
                        <div class="stat"><?php echo number_format($total_amount, 2); ?> UGX</div>
                        <div class="details">
                            <span>Total Payments</span>
                            <span><?php echo $total_payments; ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="quick-actions">
                    <h3>Quick Actions</h3>
                    <div class="action-buttons">
                        <a href="#rooms" class="action-button tab-link" onclick="openTab(event, 'rooms')">Manage Rooms</a>
                        <a href="#bookings" class="action-button tab-link" onclick="openTab(event, 'bookings')">View Bookings</a>
                        <a href="#payments" class="action-button tab-link" onclick="openTab(event, 'payments')">Check Payments</a>
                        <a href="#notifications" class="action-button tab-link" onclick="openTab(event, 'notifications')">
                            Notifications
                            <?php if ($notification_count > 0): ?>
                                <span class="badge"><?php echo $notification_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="#reports" class="action-button tab-link" onclick="openTab(event, 'reports')">Generate Reports</a>
                    </div>
                </div>
                
                <div class="hostel-summary">
                    <h3>Hostel Overview</h3>
                    <div class="hostel-card-view">
                        <div class="hostel-image-container">
                            <?php if (!empty($hostel['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars('../../../' . $hostel['image_path']); ?>" alt="<?php echo htmlspecialchars($hostel['name']); ?>">
                            <?php else: ?>
                                <img src="../../../assets/images/hostel-placeholder.jpg" alt="Hostel Image">
                            <?php endif; ?>
                        </div>

                        <div class="hostel-details">
                            <h3><?php echo htmlspecialchars($hostel['name']); ?></h3>
                            <p class="hostel-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($hostel['location']); ?></p>
                            
                            <?php if (!empty($amenities_list)): ?>
                                <ul class="hostel-amenities">
                                    <?php foreach ($amenities_list as $amenity): ?>
                                        <li>
                                            <?php 
                                            // Map amenities to appropriate icons
                                            $icon = 'fa-check';
                                            if (stripos($amenity, 'wifi') !== false) $icon = 'fa-wifi';
                                            elseif (stripos($amenity, 'security') !== false) $icon = 'fa-shield-alt';
                                            elseif (stripos($amenity, 'water') !== false) $icon = 'fa-tint';
                                            elseif (stripos($amenity, 'bathroom') !== false) $icon = 'fa-bath';
                                            elseif (stripos($amenity, 'kitchen') !== false) $icon = 'fa-utensils';
                                            ?>
                                            <i class="fas <?php echo $icon; ?>"></i> <?php echo htmlspecialchars($amenity); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            
                            <p><i class="fas fa-bed"></i> Total Rooms: <?php echo htmlspecialchars($hostel['total_rooms']); ?></p>
                            <p><i class="fas fa-phone"></i> Contact: <?php echo htmlspecialchars($hostel['contact']); ?></p>

                            
                            <a href="#hostel" class="edit-hostel-btn tab-link" onclick="openTab(event, 'hostel')">
                                <i class="fas fa-edit"></i> Edit Hostel Details
                            </a>
                        </div>
                    </div>
                    
                    <?php if (!empty($hostel['description'])): ?>
                        <div class="hostel-description">
                            <h4>Description</h4>
                            <p><?php echo htmlspecialchars($hostel['description']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php endif; ?>
        </section>
        <section id="hostel">
            <h2><?php echo $hostel_exists ? 'My Hostel Details' : 'Add Hostel Details'; ?></h2>

            <?php if (isset($_SESSION['hostel_success_message'])): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($_SESSION['hostel_success_message']); ?>
                </div>
                <?php unset($_SESSION['hostel_success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['hostel_error_message'])): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($_SESSION['hostel_error_message']); ?>
                </div>
                <?php unset($_SESSION['hostel_error_message']); ?>
                <?php endif; ?>

            <div class="hostel-form">
                <form id="hostelForm" action="../../../controllers/hostel/save_hostel_details.php" method="POST" enctype="multipart/form-data">
                    <label for="hostel_name">Hostel Name*</label>
                    <input type="text" id="hostel_name" name="name" value="<?php echo htmlspecialchars($hostel['name'] ?? ''); ?>" required>

                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($hostel['location'] ?? ''); ?>">

                    <label for="description">Description</label>
                    <textarea id="description" name="description"><?php echo htmlspecialchars($hostel['description'] ?? ''); ?></textarea>

                    <div class="form-group">
                        <label>Amenities</label>
                        <div class="checkbox-grid">
                            <div class="checkbox-item">
                                <?php $amenities_array = isset($hostel['amenities']) ? json_decode($hostel['amenities'], true) : []; ?>
                                <input type="checkbox" id="wifi" name="amenities[]" value="WiFi" <?php if (is_array($amenities_array) && in_array('WiFi', $amenities_array)): ?>checked<?php endif; ?>>
                                <label for="wifi">WiFi</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="security" name="amenities[]" value="24/7 Security" <?php if (is_array($amenities_array) && in_array('24/7 Security', $amenities_array)): ?>checked<?php endif; ?>>
                                <label for="security">24/7 Security</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="water" name="amenities[]" value="Water" <?php if (is_array($amenities_array) && in_array('Water', $amenities_array)): ?>checked<?php endif; ?>>
                                <label for="water">Water</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="bathroom" name="amenities[]" value="Private Bathroom" <?php if (is_array($amenities_array) && in_array('Private Bathroom', $amenities_array)): ?>checked<?php endif; ?>>
                                <label for="bathroom">Private Bathroom</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="kitchen" name="amenities[]" value="Kitchen" <?php if (is_array($amenities_array) && in_array('Kitchen', $amenities_array)): ?>checked<?php endif; ?>>
                                <label for="kitchen">Kitchen</label>
                            </div>
                        </div>
                    </div>

                    <label for="contact">Contact Number</label>
                    <input type="tel" id="contact" name="contact" value="<?php echo htmlspecialchars($hostel['contact'] ?? ''); ?>" autocomplete="tel">
                    <div class="form-group">
                        <label for="status">Hostel Status</label>
                        <select id="status" name="status">
                            <option value="Active" <?php echo ($hostel['status'] ?? '') === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo ($hostel['status'] ?? '') === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>


                    <label for="total_rooms">Total Number of Rooms</label>
                    <input type="number" id="total_rooms" name="total_rooms" value="<?php echo htmlspecialchars($hostel['total_rooms'] ?? ''); ?>" autocomplete="off">

                    <label for="hostel_image" id="hostel_image_label">Upload Image</label>
                    <input type="file" id="hostel_image" name="image_path" aria-labelledby="hostel_image_label">
                    <input type="hidden" name="active_tab" value="hostel">
                    <?php if ($hostel_exists && !empty($hostel['image_path'])): ?>
                        <p>Current Image: <img src="<?php echo htmlspecialchars('../../../' . $hostel['image_path']); ?>" alt="Hostel Image" style="max-width: 100px;"></p>
                    <?php endif; ?>

                    <button type="submit"><?php echo $hostel_exists ? 'Update Details' : 'Save Details'; ?></button>
                </form>
            </div>
        </section>
        <section id="rooms">
            <h2>🛏️ Room Management</h2>

            <?php if (isset($_SESSION['rooms_success_message'])): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($_SESSION['rooms_success_message']); ?>
                </div>
                <?php unset($_SESSION['rooms_success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['rooms_error_message'])): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($_SESSION['rooms_error_message']); ?>
                </div>
                <?php unset($_SESSION['rooms_error_message']); ?>
            <?php endif; ?>

            <div class="add-room-form">
                <h3>Add/Update Room Inventory</h3>
                <form action="../../../controllers/hostel/manage_rooms.php" method="POST">
                    <div class="form-group">
                        <label for="room_type">Room Type*</label>
                        <select id="room_type" name="room_type" required onchange="checkRoomType(this.value)">
                            <option value="">Select Room Type</option>
                            <option value="single">Single</option>
                            <option value="double">Double</option>
                            <option value="suite">Suite</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="quantity">Quantity*</label>
                        <input type="number" id="quantity" name="quantity" value="1" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="price_per_semester">Price per Semester (UGX)*</label>
                        <input type="number" id="price_per_semester" name="price_per_semester" required min="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="Available">Available</option>
                            <option value="Occupied">Occupied</option>
                            <option value="Maintenance">Maintenance</option>
                        </select>
                    </div>
                    <input type="hidden" name="room_id" id="room_id" value="">
                    <input type="hidden" name="hostel_id" value="<?php echo htmlspecialchars($hostel['id'] ?? ''); ?>">
                    <button type="submit" name="update_inventory" id="inventory_button">Add Room</button>
                </form>
            </div>

            <script>
            function checkRoomType(roomType) {
                if (!roomType) return;
                
                // Reset form first
                document.getElementById('room_id').value = '';
                document.getElementById('quantity').value = '1';
                document.getElementById('price_per_semester').value = '';
                document.getElementById('status').value = 'Available';
                document.getElementById('inventory_button').textContent = 'Add Room';
                
                // Check if this room type already exists
                fetch('../../../controllers/hostel/get_room_inventory.php?hostel_id=<?php echo $hostel['id']; ?>&room_type=' + encodeURIComponent(roomType))
                    .then(response => response.json())
                    .then(data => {
                        if (data && data.id) {
                            // Room type exists, populate form for update
                            document.getElementById('room_id').value = data.id;
                            document.getElementById('quantity').value = data.quantity;
                            document.getElementById('price_per_semester').value = data.price_per_semester;
                            document.getElementById('status').value = data.status;
                            document.getElementById('inventory_button').textContent = 'Update Room';
                        }
                    })
                    .catch(error => console.error('Error checking room type:', error));
            }
            </script>

            <div class="rooms-list">
                <h3>Current Room Inventory</h3>
                <?php
                if ($hostel_exists):
                    try {
                        // Check if rooms table has any entries for this hostel
                        $stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM rooms WHERE hostel_id = ?");
                        $stmt_check->bind_param("i", $hostel['id']);
                        $stmt_check->execute();
                        $result_check = $stmt_check->get_result();
                        $row_check = $result_check->fetch_assoc();
                        $has_rooms = ($row_check['count'] > 0);
                        $stmt_check->close();
                        
                        if ($has_rooms) {
                            // Check if the quantity column exists in the rooms table
                            $check_column = $conn->query("SHOW COLUMNS FROM rooms LIKE 'quantity'");
                            
                            if ($check_column->num_rows == 0) {
                                // If quantity column doesn't exist, use a query without it
                                $stmt_inventory = $conn->prepare("SELECT room_type, status, price_per_semester FROM rooms WHERE hostel_id = ?");
                            } else {
                                // If quantity column exists, include it in the query
                                $stmt_inventory = $conn->prepare("SELECT room_type, status, quantity, price_per_semester FROM rooms WHERE hostel_id = ?");
                            }
                            
                            $stmt_inventory->bind_param("i", $hostel['id']);
                            $stmt_inventory->execute();
                            $result_inventory = $stmt_inventory->get_result();

                            if ($result_inventory->num_rows > 0): ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Room Type</th>
                                            <th>Status</th>
                                            <?php if ($check_column->num_rows > 0): ?>
                                            <th>Quantity</th>
                                            <?php endif; ?>
                                            <th>Price per Semester</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($inventory = $result_inventory->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($inventory['room_type']); ?></td>
                                                <td><?php echo htmlspecialchars($inventory['status']); ?></td>
                                                <?php if ($check_column->num_rows > 0): ?>
                                                <td><?php echo htmlspecialchars($inventory['quantity']); ?></td>
                                                <?php endif; ?>
                                                <td><?php echo htmlspecialchars(number_format($inventory['price_per_semester'], 2)); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            <?php endif;
                            $stmt_inventory->close();
                        } else {
                            if ($hostel['total_rooms'] > 0) {
                                echo "<p>You have {$hostel['total_rooms']} total rooms defined in your hostel details. Use the form above to add room inventory.</p>";
                            } else {
                                echo "<p>No room inventory added yet for this hostel. Use the form above to add rooms.</p>";
                            }
                        }
                    } catch (Exception $e) {
                        // Don't show error message for first-time users
                        echo "<p>No room inventory added yet. Use the form above to add rooms.</p>";
                    }
                else: ?>
                    <p>Please save your hostel details first to manage room inventory.</p>
                <?php endif; ?>
            </div>

            <!-- Add Room Numbering Templates section here -->
            <?php
            if ($hostel_exists):
                // Get existing templates
                $stmt_templates = $conn->prepare("SELECT * FROM room_numbering_templates WHERE hostel_id = ?");
                $stmt_templates->bind_param("i", $hostel['id']);
                $stmt_templates->execute();
                $result_templates = $stmt_templates->get_result();
            ?>
                <div class="room-numbering-section">
                    <h3>Room Numbering Templates</h3>
                    <p>Define how room numbers should be generated for each room type.</p>
                    
                    <form action="../../../controllers/hostel/save_room_template.php" method="POST">
                        <div class="form-group">
                            <label for="template_room_type">Room Type*</label>
                            <select id="template_room_type" name="room_type" required>
                                <option value="">Select Room Type</option>
                                <option value="single">Single</option>
                                <option value="double">Double</option>
                                <option value="suite">Suite</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="prefix">Prefix (e.g., "A-", "Room")</label>
                            <input type="text" id="prefix" name="prefix" maxlength="10">
                        </div>
                        <div class="form-group">
                            <label for="start_number">Start Number</label>
                            <input type="number" id="start_number" name="start_number" value="1" min="1">
                        </div>
                        <div class="form-group">
                            <label for="padding">Number Padding (e.g., 2 for "01", "02")</label>
                            <input type="number" id="padding" name="padding" value="2" min="0" max="5">
                        </div>
                        <div class="form-group">
                            <input type="checkbox" id="floor_prefix" name="floor_prefix" value="1">
                            <label for="floor_prefix">Include floor in room number</label>
                        </div>
                        <div class="form-group">
                            <label>Example: <span id="room_number_example">A-01</span></label>
                        </div>
                        <input type="hidden" name="hostel_id" value="<?php echo htmlspecialchars($hostel['id']); ?>">
                        <button type="submit">Save Template</button>
                    </form>
                    
                    <?php if ($result_templates->num_rows > 0): ?>
                        <h4>Current Templates</h4>
                        <table>
                            <thead>
                                <tr>
                                    <th>Room Type</th>
                                    <th>Format</th>
                                    <th>Example</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($template = $result_templates->fetch_assoc()): 
                                    $example = generateRoomNumberExample($template);
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($template['room_type']); ?></td>
                                        <td><?php echo formatTemplateDescription($template); ?></td>
                                        <td><?php echo htmlspecialchars($example); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <script>
                // Live update the room number example
                document.addEventListener('DOMContentLoaded', function() {
                    const prefix = document.getElementById('prefix');
                    const startNumber = document.getElementById('start_number');
                    const padding = document.getElementById('padding');
                    const floorPrefix = document.getElementById('floor_prefix');
                    const example = document.getElementById('room_number_example');
                    
                    function updateExample() {
                        let paddedNumber = String(startNumber.value).padStart(parseInt(padding.value), '0');
                        let roomNumber = prefix.value + paddedNumber;
                        if (floorPrefix.checked) {
                            roomNumber = '1-' + roomNumber; // Example with floor 1
                        }
                        example.textContent = roomNumber;
                    }
                    
                    prefix.addEventListener('input', updateExample);
                    startNumber.addEventListener('input', updateExample);
                    padding.addEventListener('input', updateExample);
                    floorPrefix.addEventListener('change', updateExample);
                    
                    // Initialize
                    updateExample();
                });
                </script>
            <?php 
                $stmt_templates->close();
            endif; 
            ?>
        </section>
        <section id="bookings">
            <h2>📋 Bookings Management</h2>
            
            <?php if (isset($_SESSION['bookings_success_message'])): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($_SESSION['bookings_success_message']); ?>
                </div>
                <?php unset($_SESSION['bookings_success_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['bookings_error_message'])): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($_SESSION['bookings_error_message']); ?>
                </div>
                <?php unset($_SESSION['bookings_error_message']); ?>
            <?php endif; ?>
            
            <div class="booking-tabs">
                <button class="booking-tab active" onclick="showBookingTab('pending', event)">Pending (<?php echo $pending_bookings; ?>)</button>
                <button class="booking-tab" onclick="showBookingTab('confirmed', event)">Confirmed (<?php echo $confirmed_bookings; ?>)</button>
                <button class="booking-tab" onclick="showBookingTab('cancelled', event)">Cancelled</button>
                <button class="booking-tab" onclick="showBookingTab('completed', event)">Completed</button>
            </div>
            
            <div id="pending-bookings" class="booking-content active">
                <div class="bulk-actions-panel">
                    <h4>Bulk Actions</h4>
                    <div class="bulk-actions-buttons">
                        <form action="../../../controllers/booking/bulk_actions.php" method="POST" style="display:inline;">
                            <input type="hidden" name="hostel_id" value="<?php echo $hostel['id']; ?>">
                            <input type="hidden" name="action" value="confirm_all">
                            <button type="submit" class="btn-confirm">Confirm All Pending Bookings</button>
                        </form>
                        
                        <form action="../../../controllers/booking/bulk_actions.php" method="POST" style="display:inline;">
                            <input type="hidden" name="hostel_id" value="<?php echo $hostel['id']; ?>">
                            <input type="hidden" name="action" value="assign_all_rooms">
                            <button type="submit" class="btn-assign">Auto-Assign All Rooms</button>
                        </form>
                        
                        <form action="../../../controllers/booking/bulk_actions.php" method="POST" style="display:inline;">
                            <input type="hidden" name="hostel_id" value="<?php echo $hostel['id']; ?>">
                            <input type="hidden" name="action" value="complete_all_past">
                            <button type="submit" class="btn-complete">Complete All Past Bookings</button>
                        </form>
                    </div>
                    <div class="auto-process-toggle">
                        <label>
                            <input type="checkbox" id="autoProcessBookings">
                            Automatically process bookings (confirm pending, assign rooms, complete past)
                        </label>
                    </div>
                </div>
                
                <h3>Pending Booking Requests</h3>

                <?php
                if ($hostel_exists) {
                    $stmt_pending = $conn->prepare("
                        SELECT b.*, u.username as student_name, u.email as student_email, 
                            r.room_type, r.price_per_semester
                        FROM bookings b
                        JOIN users u ON b.user_id = u.id
                        JOIN rooms r ON b.room_id = r.id
                        WHERE b.hostel_id = ? AND b.status = 'Pending'
                        ORDER BY b.check_in_date ASC
                    ");
                    $stmt_pending->bind_param("i", $hostel['id']);
                    $stmt_pending->execute();
                    $result_pending = $stmt_pending->get_result();
                    
                    if ($result_pending->num_rows > 0):
                ?>
                    <table class="bookings-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Room Type</th>
                                <th>Quantity</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>Price</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($booking = $result_pending->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($booking['student_name']); ?><br>
                                        <small><?php echo htmlspecialchars($booking['student_email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($booking['room_type']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['quantity'] ?? 1); ?></td>
                                    <td><?php echo htmlspecialchars($booking['check_in_date']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['check_out_date']); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($booking['price_per_semester'], 2)); ?> UGX</td>
                                    <td>
                                        <form action="../../../controllers/booking/update_booking.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <input type="hidden" name="action" value="confirm">
                                            <button type="submit" class="btn-confirm">Confirm</button>
                                        </form>
                                        <form action="../../../controllers/booking/update_booking.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <button type="submit" class="btn-cancel">Cancel</button>
                                        </form>

                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php 
                    else:
                        echo "<p>No pending booking requests.</p>";
                    endif;
                    $stmt_pending->close();
                } else {
                    echo "<p>Please add your hostel details first to manage bookings.</p>";
                }
                ?>
            </div>
            
            <div id="confirmed-bookings" class="booking-content">
                <h3>Confirmed Bookings</h3>
                <?php
                if ($hostel_exists) {
                    $stmt_confirmed = $conn->prepare("
                        SELECT b.*, u.username as student_name, u.email as student_email, 
                            r.room_type, r.price_per_semester,
                            (SELECT ra.assigned_room_number FROM room_assignments ra WHERE ra.booking_id = b.id LIMIT 1) as room_number
                        FROM bookings b
                        JOIN users u ON b.user_id = u.id
                        JOIN rooms r ON b.room_id = r.id
                        WHERE b.hostel_id = ? AND b.status = 'Confirmed'
                        ORDER BY b.check_in_date ASC
                    ");
                    $stmt_confirmed->bind_param("i", $hostel['id']);
                    $stmt_confirmed->execute();
                    $result_confirmed = $stmt_confirmed->get_result();
                    
                    if ($result_confirmed->num_rows > 0):
                ?>
                        <table class="bookings-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Room Type</th>
                                    <th>Room Number</th>
                                    <th>Check-in</th>
                                    <th>Check-out</th>
                                    <th>Payment Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($booking = $result_confirmed->fetch_assoc()): 
                                    // Get payment status
                                    $stmt_payment = $conn->prepare("SELECT status FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1");
                                    $stmt_payment->bind_param("i", $booking['id']);
                                    $stmt_payment->execute();
                                    $payment_result = $stmt_payment->get_result();
                                    $payment_status = ($payment_result->num_rows > 0) ? $payment_result->fetch_assoc()['status'] : 'Pending';
                                    $stmt_payment->close();
                                ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($booking['student_name']); ?><br>
                                            <small><?php echo htmlspecialchars($booking['student_email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($booking['room_type']); ?></td>
                                        <td>
                                            <?php if (!empty($booking['room_number'])): ?>
                                                <?php echo htmlspecialchars($booking['room_number']); ?>
                                            <?php else: ?>
                                                <form action="../../../controllers/booking/assign_room.php" method="POST" style="display:inline;">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    <button type="submit" class="btn-assign">Assign Room</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($booking['check_in_date']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['check_out_date']); ?></td>
                                        <td>
                                            <span class="payment-status payment-<?php echo strtolower($payment_status); ?>">
                                                <?php echo htmlspecialchars($payment_status); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form action="../../../controllers/booking/update_booking.php" method="POST" style="display:inline;">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <input type="hidden" name="action" value="complete">
                                                <button type="submit" class="btn-complete">Mark Complete</button>
                                            </form>
                                            <form action="../../../controllers/booking/update_booking.php" method="POST" style="display:inline;">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <input type="hidden" name="action" value="cancel">
                                                <button type="submit" class="btn-cancel">Cancel</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                <?php 
                    else:
                        echo "<p>No confirmed bookings.</p>";
                    endif;
                    $stmt_confirmed->close();
                } else {
                    echo "<p>Please add your hostel details first to manage bookings.</p>";
                }
                ?>
            </div>

            <div id="cancelled-bookings" class="booking-content">
                <h3>Cancelled Bookings</h3>
                <?php
                if ($hostel_exists) {
                    $stmt_cancelled = $conn->prepare("
                        SELECT b.*, u.username as student_name, u.email as student_email, 
                            r.room_type, r.price_per_semester
                        FROM bookings b
                        JOIN users u ON b.user_id = u.id
                        JOIN rooms r ON b.room_id = r.id
                        WHERE b.hostel_id = ? AND b.status = 'Cancelled'
                        ORDER BY b.booking_date DESC
                    ");
                    $stmt_cancelled->bind_param("i", $hostel['id']);
                    $stmt_cancelled->execute();
                    $result_cancelled = $stmt_cancelled->get_result();
                    
                    if ($result_cancelled->num_rows > 0):
                ?>
                        <table class="bookings-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Room Type</th>
                                    <th>Check-in</th>
                                    <th>Check-out</th>
                                    <th>Cancelled Date</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($booking = $result_cancelled->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($booking['student_name']); ?><br>
                                            <small><?php echo htmlspecialchars($booking['student_email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($booking['room_type']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['check_in_date']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['check_out_date']); ?></td>
                                        <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($booking['booking_date']))); ?></td>
                                        <td><?php echo htmlspecialchars($booking['cancellation_reason'] ?? 'Not specified'); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                <?php 
                    else:
                        echo "<p>No cancelled bookings.</p>";
                    endif;
                    $stmt_cancelled->close();
                } else {
                    echo "<p>Please add your hostel details first to manage bookings.</p>";
                }
                ?>
            </div>

            <div id="completed-bookings" class="booking-content">
                <h3>Completed Bookings</h3>
                <?php
                if ($hostel_exists) {
                    $stmt_completed = $conn->prepare("
                        SELECT b.*, u.username as student_name, u.email as student_email, 
                            r.room_type, r.price_per_semester,
                            (SELECT ra.assigned_room_number FROM room_assignments ra WHERE ra.booking_id = b.id LIMIT 1) as room_number
                        FROM bookings b
                        JOIN users u ON b.user_id = u.id
                        JOIN rooms r ON b.room_id = r.id
                        WHERE b.hostel_id = ? AND b.status = 'Completed'
                        ORDER BY b.booking_date DESC
                    ");
                    $stmt_completed->bind_param("i", $hostel['id']);
                    $stmt_completed->execute();
                    $result_completed = $stmt_completed->get_result();
                    
                    if ($result_completed->num_rows > 0):
                ?>
                        <table class="bookings-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Room Type</th>
                                    <th>Room Number</th>
                                    <th>Check-in</th>
                                    <th>Check-out</th>
                                    <th>Completed Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($booking = $result_completed->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($booking['student_name']); ?><br>
                                            <small><?php echo htmlspecialchars($booking['student_email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($booking['room_type']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['room_number'] ?? 'Not assigned'); ?></td>
                                        <td><?php echo htmlspecialchars($booking['check_in_date']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['check_out_date']); ?></td>
                                        <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($booking['booking_date']))); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                <?php 
                    else:
                        echo "<p>No completed bookings.</p>";
                    endif;
                    $stmt_completed->close();
                } else {
                    echo "<p>Please add your hostel details first to manage bookings.</p>";
                }
                ?>
            </div>

        </section>

        <script>
            document.getElementById('autoProcessBookings')?.addEventListener('change', function() {
                fetch('../../../controllers/booking/bulk_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=toggle_auto_process&hostel_id=<?php echo $hostel['id']; ?>&enabled=${this.checked ? '1' : '0'}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message
                        alert(this.checked ? 'Auto-processing enabled' : 'Auto-processing disabled');
                    }
                });
            });

        </script>

        <section id="payments">
            <h2>💳 Payments Management</h2>
            
            <?php if (isset($_SESSION['payments_success_message'])): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($_SESSION['payments_success_message']); ?>
                </div>
                <?php unset($_SESSION['payments_success_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['payments_error_message'])): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($_SESSION['payments_error_message']); ?>
                </div>
                <?php unset($_SESSION['payments_error_message']); ?>
            <?php endif; ?>
            
            <?php if ($hostel_exists): ?>
                <!-- Payment Methods Configuration -->
                <div class="payment-methods-section">
                    <h3>Payment Methods</h3>
                    <p>Select which payment methods you accept for your hostel.</p>
                    
                    <form action="save_payment_methods.php" method="POST">
                        <div class="payment-methods-grid">
                            <div class="payment-method-item">
                                <input type="checkbox" id="method_mtn" name="payment_methods[]" value="MTN Mobile Money" 
                                    <?php echo isPaymentMethodActive($conn, $hostel['id'], 'MTN Mobile Money') ? 'checked' : ''; ?>>
                                <label for="method_mtn">MTN Mobile Money</label>
                            </div>
                            <div class="payment-method-item">
                                <input type="checkbox" id="method_airtel" name="payment_methods[]" value="Airtel Money" 
                                    <?php echo isPaymentMethodActive($conn, $hostel['id'], 'Airtel Money') ? 'checked' : ''; ?>>
                                <label for="method_airtel">Airtel Money</label>
                            </div>
                            <div class="payment-method-item">
                                <input type="checkbox" id="method_card" name="payment_methods[]" value="Credit/Debit Card" 
                                    <?php echo isPaymentMethodActive($conn, $hostel['id'], 'Credit/Debit Card') ? 'checked' : ''; ?>>
                                <label for="method_card">Credit/Debit Card</label>
                            </div>
                            <div class="payment-method-item">
                                <input type="checkbox" id="method_bank" name="payment_methods[]" value="Bank Transfer" 
                                    <?php echo isPaymentMethodActive($conn, $hostel['id'], 'Bank Transfer') ? 'checked' : ''; ?>>
                                <label for="method_bank">Bank Transfer</label>
                            </div>
                            <div class="payment-method-item">
                                <input type="checkbox" id="method_cash" name="payment_methods[]" value="Cash" 
                                    <?php echo isPaymentMethodActive($conn, $hostel['id'], 'Cash') ? 'checked' : ''; ?>>
                                <label for="method_cash">Cash</label>
                            </div>
                        </div>
                        <input type="hidden" name="hostel_id" value="<?php echo $hostel['id']; ?>">
                        <button type="submit" class="btn-primary">Save Payment Methods</button>
                    </form>
                </div>
                
                <!-- Payment Transactions -->
                <div class="payment-transactions">
                    <h3>Payment Transactions</h3>
                    
                    <div class="payment-tabs">
                        <button class="payment-tab active" onclick="showPaymentTab('pending', event)">Pending</button>
                        <button class="payment-tab" onclick="showPaymentTab('completed', event)">Completed</button>
                        <button class="payment-tab" onclick="showPaymentTab('failed', event)">Failed</button>
                        <button class="payment-tab" onclick="showPaymentTab('refunded', event)">Refunded</button>
                    </div>
                    
                    <div id="pending-payments" class="payment-content active">
                        <?php
                        $stmt_pending = $conn->prepare("
                            SELECT p.*, b.check_in_date, b.check_out_date, 
                                u.username as student_name, u.email as student_email,
                                r.room_type
                            FROM payments p
                            JOIN bookings b ON p.booking_id = b.id
                            JOIN users u ON p.user_id = u.id
                            JOIN rooms r ON b.room_id = r.id
                            WHERE p.hostel_id = ? AND p.status = 'Pending'
                            ORDER BY p.payment_date DESC
                        ");
                        $stmt_pending->bind_param("i", $hostel['id']);
                        $stmt_pending->execute();
                        $result_pending = $stmt_pending->get_result();
                        
                        if ($result_pending->num_rows > 0):
                        ?>
                        <table class="payments-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Room Type</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($payment = $result_pending->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($payment['student_name']); ?><br>
                                        <small><?php echo htmlspecialchars($payment['student_email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['room_type']); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($payment['amount'], 2)); ?> UGX</td>
                                    <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($payment['payment_date']))); ?></td>
                                    <td>
                                        <form action="update_payment.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                            <input type="hidden" name="action" value="complete">
                                            <button type="submit" class="btn-confirm">Confirm</button>
                                        </form>
                                        <form action="update_payment.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                            <input type="hidden" name="action" value="fail">
                                            <button type="submit" class="btn-cancel">Reject</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p>No pending payments.</p>
                        <?php 
                        endif;
                        $stmt_pending->close();
                        ?>
                    </div>
                    
                    <div id="completed-payments" class="payment-content">
                        <?php
                        $stmt_completed = $conn->prepare("
                            SELECT p.*, b.check_in_date, b.check_out_date, 
                                u.username as student_name, u.email as student_email,
                                r.room_type
                            FROM payments p
                            JOIN bookings b ON p.booking_id = b.id
                            JOIN users u ON p.user_id = u.id
                            JOIN rooms r ON b.room_id = r.id
                            WHERE p.hostel_id = ? AND p.status = 'Completed'
                            ORDER BY p.payment_date DESC
                        ");
                        $stmt_completed->bind_param("i", $hostel['id']);
                        $stmt_completed->execute();
                        $result_completed = $stmt_completed->get_result();
                        
                        if ($result_completed->num_rows > 0):
                        ?>
                        <table class="payments-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Room Type</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($payment = $result_completed->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($payment['student_name']); ?><br>
                                        <small><?php echo htmlspecialchars($payment['student_email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['room_type']); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($payment['amount'], 2)); ?> UGX</td>
                                    <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($payment['payment_date']))); ?></td>
                                    <td>
                                        <form action="update_payment.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                            <input type="hidden" name="action" value="refund">
                                            <button type="submit" class="btn-cancel">Refund</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p>No completed payments.</p>
                        <?php 
                        endif;
                        $stmt_completed->close();
                        ?>
                    </div>
                    
                    <div id="failed-payments" class="payment-content">
                        <?php
                        $stmt_failed = $conn->prepare("
                            SELECT p.*, b.check_in_date, b.check_out_date, 
                                u.username as student_name, u.email as student_email,
                                r.room_type
                            FROM payments p
                            JOIN bookings b ON p.booking_id = b.id
                            JOIN users u ON p.user_id = u.id
                            JOIN rooms r ON b.room_id = r.id
                            WHERE p.hostel_id = ? AND p.status = 'Failed'
                            ORDER BY p.payment_date DESC
                        ");
                        $stmt_failed->bind_param("i", $hostel['id']);
                        $stmt_failed->execute();
                        $result_failed = $stmt_failed->get_result();
                        
                        if ($result_failed->num_rows > 0):
                        ?>
                        <table class="payments-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Room Type</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($payment = $result_failed->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($payment['student_name']); ?><br>
                                        <small><?php echo htmlspecialchars($payment['student_email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['room_type']); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($payment['amount'], 2)); ?> UGX</td>
                                    <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($payment['payment_date']))); ?></td>
                                    <td>
                                        <form action="update_payment.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                            <input type="hidden" name="action" value="retry">
                                            <button type="submit" class="btn-confirm">Retry</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p>No failed payments.</p>
                        <?php 
                        endif;
                        $stmt_failed->close();
                        ?>
                    </div>

                    
                    <div id="refunded-payments" class="payment-content">
                        <?php
                        $stmt_refunded = $conn->prepare("
                            SELECT p.*, b.check_in_date, b.check_out_date, 
                                u.username as student_name, u.email as student_email,
                                r.room_type
                            FROM payments p
                            JOIN bookings b ON p.booking_id = b.id
                            JOIN users u ON p.user_id = u.id
                            JOIN rooms r ON b.room_id = r.id
                            WHERE p.hostel_id = ? AND p.status = 'Refunded'
                            ORDER BY p.payment_date DESC
                        ");
                        $stmt_refunded->bind_param("i", $hostel['id']);
                        $stmt_refunded->execute();
                        $result_refunded = $stmt_refunded->get_result();
                        
                        if ($result_refunded->num_rows > 0):
                        ?>
                        <table class="payments-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Room Type</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Date</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($payment = $result_refunded->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($payment['student_name']); ?><br>
                                        <small><?php echo htmlspecialchars($payment['student_email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['room_type']); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($payment['amount'], 2)); ?> UGX</td>
                                    <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($payment['payment_date']))); ?></td>
                                    <td><?php echo htmlspecialchars($payment['notes'] ?? 'No notes'); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p>No refunded payments.</p>
                        <?php 
                        endif;
                        $stmt_refunded->close();
                        ?>
                </div>
                
            
            <?php else: ?>
                <p>Please add your hostel details first to manage payments.</p>
            <?php endif; ?>
        </section>


        <section id="notifications">
            <h2>🔔 Notifications & Alerts</h2>
            
            <?php
            // Get notifications for this manager
            $stmt_notifications = $conn->prepare("
                SELECT n.*, h.name as hostel_name
                FROM notifications n
                LEFT JOIN hostels h ON n.hostel_id = h.id
                WHERE n.recipient_id = ? 
                ORDER BY n.is_read ASC, n.created_at DESC
            ");
            $stmt_notifications->bind_param("i", $manager_id);
            $stmt_notifications->execute();
            $result_notifications = $stmt_notifications->get_result();
            $notification_count = $result_notifications->num_rows;
            
            if ($notification_count > 0):
            ?>
            <div class="notification-actions">
                <button class="btn-primary" onclick="markAllNotificationsAsRead()">Mark All as Read</button>
                <button class="btn-secondary" onclick="clearAllNotifications()">Clear All</button>
            </div>
            
            <div class="notifications-list">
                <?php while ($notification = $result_notifications->fetch_assoc()): ?>
                <div class="notification-card <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>" id="notification-<?php echo $notification['id']; ?>">
                    <div class="notification-header">
                        <h3><?php echo htmlspecialchars($notification['title']); ?></h3>
                        <span class="notification-time"><?php echo timeAgo($notification['created_at']); ?></span>
                    </div>
                    <div class="notification-body">
                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                        <?php if ($notification['hostel_id']): ?>
                        <p class="notification-meta">Hostel: <?php echo htmlspecialchars($notification['hostel_name']); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($notification['notification_type'] === 'new_booking'): ?>
                        <div class="notification-actions">
                            <a href="#bookings" class="btn-primary" onclick="openTab(event, 'bookings'); showBookingTab('pending');">View Booking</a>
                        </div>
                        <?php elseif ($notification['notification_type'] === 'payment_completed'): ?>
                        <div class="notification-actions">
                            <a href="#payments" class="btn-primary" onclick="openTab(event, 'payments'); showPaymentTab('completed');">View Payment</a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="notification-footer">
                        <button class="btn-text" onclick="markNotificationAsRead(<?php echo $notification['id']; ?>)">
                            <?php echo $notification['is_read'] ? 'Mark as Unread' : 'Mark as Read'; ?>
                        </button>
                        <button class="btn-text text-danger" onclick="deleteNotification(<?php echo $notification['id']; ?>)">Delete</button>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-bell-slash"></i>
                </div>
                <h3>No Notifications</h3>
                <p>You don't have any notifications at the moment.</p>
            </div>
            <?php 
            endif;
            $stmt_notifications->close();
            ?>
            
            <div class="notification-settings">
                <h3>Notification Settings</h3>
                <form id="notificationSettingsForm">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="notify_new_bookings" id="notify_new_bookings" checked>
                            Notify me about new bookings
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="notify_payments" id="notify_payments" checked>
                            Notify me about payments
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="notify_cancellations" id="notify_cancellations" checked>
                            Notify me about booking cancellations
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="email_notifications" id="email_notifications">
                            Send notifications to my email
                        </label>
                    </div>
                    <button type="submit" class="btn-primary">Save Settings</button>
                </form>
            </div>
        </section>

        <style>
        /* Notification styles */
        .notifications-list {
            margin-top: 20px;
        }

        .notification-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .notification-card.unread {
            border-left: 4px solid #cd0cf3;
            background-color: #f8f4ff;
        }

        .notification-card.read {
            border-left: 4px solid #ddd;
        }

        .notification-header {
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
        }

        .notification-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }

        .notification-time {
            font-size: 12px;
            color: #777;
        }

        .notification-body {
            padding: 15px;
        }

        .notification-meta {
            font-size: 13px;
            color: #666;
            margin-top: 10px;
        }

        .notification-footer {
            padding: 10px 15px;
            background-color: #f9f9f9;
            display: flex;
            justify-content: space-between;
        }

        .notification-actions {
            display: flex;
            gap: 10px;
            margin: 15px 0;
        }

        .btn-text {
            background: none;
            border: none;
            color: #2d3a4c;
            cursor: pointer;
            padding: 5px;
            font-size: 14px;
        }

        .text-danger {
            color: #dc3545;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
            margin: 20px 0;
        }

        .empty-state-icon {
            font-size: 48px;
            color: #ccc;
            margin-bottom: 15px;
        }

        .notification-settings {
            margin-top: 30px;
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
        }

        .notification-settings h3 {
            margin-top: 0;
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }
        </style>

        <script>
        function markNotificationAsRead(notificationId) {
            fetch('../../../controllers/notification/toggle_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'notification_id=' + notificationId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const notification = document.getElementById('notification-' + notificationId);
                    if (notification) {
                        if (data.is_read) {
                            notification.classList.remove('unread');
                            notification.classList.add('read');
                            notification.querySelector('.btn-text').textContent = 'Mark as Unread';
                        } else {
                            notification.classList.remove('read');
                            notification.classList.add('unread');
                            notification.querySelector('.btn-text').textContent = 'Mark as Read';
                        }
                    }
                }
            });
        }

        function markAllNotificationsAsRead() {
            fetch('../../../controllers/notification/mark_all_notifications_read.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelectorAll('.notification-card.unread').forEach(card => {
                        card.classList.remove('unread');
                        card.classList.add('read');
                        card.querySelector('.btn-text').textContent = 'Mark as Unread';
                    });
                }
            });
        }

        function deleteNotification(notificationId) {
            if (confirm('Are you sure you want to delete this notification?')) {
                fetch('../../../controllers/notification/delete_notification.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'notification_id=' + notificationId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const notification = document.getElementById('notification-' + notificationId);
                        if (notification) {
                            notification.style.height = notification.offsetHeight + 'px';
                            setTimeout(() => {
                                notification.style.height = '0';
                                notification.style.opacity = '0';
                                notification.style.marginBottom = '0';
                                setTimeout(() => {
                                    notification.remove();
                                    if (document.querySelectorAll('.notification-card').length === 0) {
                                        location.reload();
                                    }
                                }, 300);
                            }, 10);
                        }
                    }
                });
            }
        }

        function clearAllNotifications() {
            if (confirm('Are you sure you want to delete all notifications?')) {
                fetch('../../../controllers/notification/clear_all_notifications.php', {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                });
            }
        }

        document.getElementById('notificationSettingsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const settings = {
                notify_new_bookings: document.getElementById('notify_new_bookings').checked,
                notify_payments: document.getElementById('notify_payments').checked,
                notify_cancellations: document.getElementById('notify_cancellations').checked,
                email_notifications: document.getElementById('email_notifications').checked
            };
            
            fetch('../../../controllers/notification/save_notification_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(settings)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Notification settings saved successfully!');
                } else {
                    alert('Failed to save notification settings.');
                }
            });
        });
        </script>

        <section id="reports">
            <h2>📊 Reports & Downloads</h2>
            <p>Reports will be available soon.</p>
        </section>

        <section id="profile">
            <h2>👤 Profile Management</h2>
            <p>Profile management will be available soon.</p>
        </section>

        <footer>
            <p>&copy; 2025 Campus Mediate. All rights reserved.</p>
        </footer>
    </main>

</body>
</html>