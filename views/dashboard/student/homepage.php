<?php
session_start();
include "../../../config/config.php";
include "../../../includes/functions.php";

// hasPayment function Defined in PHP
function hasPayment($payments, $booking_id) {
    foreach ($payments as $payment) {
        if ($payment['booking_id'] == $booking_id) {
            return true;
        }
    }
    return false;
}

// timeAgo function Defined in PHP
// This timeAgo function takes a datetime string and returns a human-readable 
// string indicating how long ago that datetime was. It handles seconds, minutes, hours, days, 
// weeks, months, and years.
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return $diff . " seconds ago";
    } elseif ($diff < 3600) {
        $mins = round($diff / 60);
        return $mins . " minute" . ($mins > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = round($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } elseif ($diff < 604800) {
        $days = round($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } elseif ($diff < 2592000) {
        $weeks = round($diff / 604800);
        return $weeks . " week" . ($weeks > 1 ? "s" : "") . " ago";
    } elseif ($diff < 31536000) {
        $months = round($diff / 2592000);
        return $months . " month" . ($months > 1 ? "s" : "") . " ago";
    } else {
        $years = round($diff / 31536000);
        return $years . " year" . ($years > 1 ? "s" : "") . " ago";
    }
}
  


// To detect form submissions
$from_form_submission = isset($_SESSION['from_form_submission']) && $_SESSION['from_form_submission'];
unset($_SESSION['from_form_submission']); // Clear the flag after using it

if(!isset($_SESSION['email'])){
    header("Location: login.php");
    exit();
}

// Check if user role is student
$userRole = getUserRoleFromEmail($conn, $_SESSION['email']);
if($userRole !== 'student') {
    header("Location: dashboard.php");
    exit();
}

$user_email = $_SESSION['email'];
$username = ucfirst(getUsernameFromEmail($conn, $user_email));  // Capitalize first letter
$user_id = getUserIdFromEmail($conn, $user_email);

// Get student's bookings
$stmt_bookings = $conn->prepare("
    SELECT b.*, h.name as hostel_name, r.room_type, r.price_per_semester,
           COALESCE(b.full_name, u.username) as student_name
    FROM bookings b
    JOIN hostels h ON b.hostel_id = h.id
    JOIN rooms r ON b.room_id = r.id
    JOIN users u ON b.user_id = u.id
    WHERE b.user_id = ?
    ORDER BY b.booking_date DESC
");
$stmt_bookings->bind_param("i", $user_id);
$stmt_bookings->execute();
$result_bookings = $stmt_bookings->get_result();
$bookings = [];
while ($row = $result_bookings->fetch_assoc()) {
    $bookings[] = $row;
}
$stmt_bookings->close();

// Get student's payments
$stmt_payments = $conn->prepare("
    SELECT p.*, b.check_in_date, b.check_out_date, h.name as hostel_name, r.room_type,
           COALESCE(b.full_name, u.username) as student_name
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    JOIN hostels h ON p.hostel_id = h.id
    JOIN rooms r ON b.room_id = r.id
    JOIN users u ON b.user_id = u.id
    WHERE p.user_id = ?
    ORDER BY p.payment_date DESC
");

$stmt_payments->bind_param("i", $user_id);
$stmt_payments->execute();
$result_payments = $stmt_payments->get_result();
$payments = [];
while ($row = $result_payments->fetch_assoc()) {
    $payments[] = $row;
}
$stmt_payments->close();

// Get all notifications (both read and unread)
$stmt_notifications = $conn->prepare("
    SELECT * FROM notifications
    WHERE recipient_id = ?
    ORDER BY is_read ASC, created_at DESC
");
$stmt_notifications->bind_param("i", $user_id);
$stmt_notifications->execute();
$result_notifications = $stmt_notifications->get_result();
$notifications = [];
$notification_count = 0;
while ($row = $result_notifications->fetch_assoc()) {
    $notifications[] = $row;
    if ($row['is_read'] == 0) {
        $notification_count++;
    }
}
$stmt_notifications->close();

// Get available hostels with correct room availability calculation
$current_date = date('Y-m-d');
$stmt_hostels = $conn->prepare("
    SELECT h.*, 
           COALESCE((
               SELECT 
                   SUM(CASE WHEN r.quantity IS NOT NULL THEN r.quantity ELSE 1 END) - 
                   COALESCE((
                       SELECT COUNT(*) FROM bookings b 
                       WHERE b.hostel_id = h.id AND b.status IN ('Confirmed') 
                       AND b.check_in_date <= ?
                   ), 0) - 
                   SUM(CASE WHEN r.status = 'Occupied' THEN 
                       (CASE WHEN r.quantity IS NOT NULL THEN r.quantity ELSE 1 END) 
                       ELSE 0 END)
               FROM rooms r WHERE r.hostel_id = h.id AND r.status = 'Available'
           ), 0) as available_rooms
    FROM hostels h
    WHERE h.status = 'Active'
    ORDER BY available_rooms DESC
");
$stmt_hostels->bind_param("s", $current_date);
$stmt_hostels->execute();
$result_hostels = $stmt_hostels->get_result();
$hostels = [];
while ($row = $result_hostels->fetch_assoc()) {
    $hostels[] = $row;
}
$stmt_hostels->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Campus Mediate | Student Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600&display=swap" rel="stylesheet">
  
  <script>
    // All functions are defined at the top
    function showSection(sectionId) {
      // Hide all sections
      var sections = document.getElementsByClassName('dashboard-section');
      for (var i = 0; i < sections.length; i++) {
        sections[i].classList.remove('active');
      }
      
      // Show selected section
      var targetSection = document.getElementById(sectionId);
      if (targetSection) {
        targetSection.classList.add('active');
      }
      
      // Update active menu item
      var menuItems = document.getElementsByClassName('sidebar-menu')[0].getElementsByTagName('a');
      for (var i = 0; i < menuItems.length; i++) {
        menuItems[i].classList.remove('active');
      }
      
      // Find the link for this section and add active class
      for (var i = 0; i < menuItems.length; i++) {
        if (menuItems[i].getAttribute('onclick') && 
            menuItems[i].getAttribute('onclick').indexOf("showSection('" + sectionId + "'") !== -1) {
          menuItems[i].classList.add('active');
          break;
        }
      }
      
      // Update URL hash without scrolling
      history.pushState(null, null, '#' + sectionId);
    }
    
    function viewHostelDetails(hostelId) {
      window.location.href = "../../hostel/hostel_details.php?id=" + hostelId;
    }

    // function getActiveSemesterEndDate($conn) {
    //     $stmt = $conn->prepare("SELECT end_date FROM university_calendar WHERE is_active = 1 ORDER BY end_date DESC LIMIT 1");
    //     $stmt->execute();
    //     $result = $stmt->get_result();
        
    //     if ($result->num_rows > 0) {
    //         $row = $result->fetch_assoc();
    //         return $row['end_date'];
    //     }
        
    //     // Default fallback - 4 months from now
    //     return date('Y-m-d', strtotime('+4 months'));
    // }

    function updateCheckoutDate() {
        // Fetch the active semester end date via AJAX
        fetch('../../../controllers/calendar/get_semester_end.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('check_out_date').value = data.end_date;
                    document.getElementById('checkout_display').textContent = 
                        'Check-out date will be ' + formatDate(data.end_date) + 
                        ' (end of semester)';
                }
            });
    }

    // Format date for display
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
    }

    
    
    function viewBookingDetails(bookingId) {
      window.location.href = "../../../controllers/booking/view_booking.php?id=" + bookingId;
    }
    
    function makePayment(bookingId) {
      window.location.href = "../../../controllers/payment/test_payment.php?booking_id=" + bookingId;
    }
    
    function cancelBooking(bookingId) {
      if (confirm('Are you sure you want to cancel this booking?')) {
        window.location.href = "../../../controllers/booking/cancel_booking.php?id=" + bookingId;
      }
    }
    
    function viewPaymentDetails(paymentId) {
      window.location.href = "../../../controllers/payment/view_payment.php?id=" + paymentId;
    }
    
    function completePayment(paymentId) {
      window.location.href = "../../../controllers/payment/test_payment.php?payment_id=" + paymentId;
    }
    
// Removes individual notifications from the list when marked as read
// Updates the notification count in all badges
// Hides the notification badge when the count reaches zero
// Refreshs the page when all notifications are marked as read

    function markAsRead(notificationId) {
      fetch("../../../controllers/notification/mark_notification_read.php?id=" + notificationId)
        .then(response => {
          if (!response.ok) {
            throw new Error('Network response was not ok');
          }
          return response.json();
        })
        .then(data => {
          if (data.success) {
            // Mark notification as archived
            const item = document.querySelector(`button[onclick="markAsRead(${notificationId})"]`).closest('.list-group-item');
            item.classList.add('bg-light', 'archived');
            item.style.opacity = '0.7';

            // Update button appearance
            const button = item.querySelector('button');
            button.classList.remove('btn-light');
            button.classList.add('btn-secondary');
            button.textContent = 'Archived';
            button.disabled = true;
            
            // Add archived label
            const header = item.querySelector('.d-flex');
            if (!header.querySelector('.archived-label')) {
              const archivedLabel = document.createElement('span');
              archivedLabel.className = 'badge bg-secondary archived-label';
              archivedLabel.textContent = 'Archived';
              header.appendChild(archivedLabel);
            }
            
            // Update notification count
            updateNotificationCount(-1);
          }
        })
        .catch(error => {
          console.error('Error marking notification as read:', error);
          alert('Failed to mark notification as read. Please try again later.');
        });
    }

    function markAllAsRead() {
      fetch('../../../controllers/notification/mark_all_notifications_read.php')
        .then(response => {
          if (!response.ok) {
            throw new Error('Network response was not ok');
          }
          return response.json();
        })
        .then(data => {
          if (data.success) {
            // Mark all notifications as read VISUALLY
            const items = document.querySelectorAll('.list-group-item');
            items.forEach(item => {
              item.classList.add('bg-light');

              // Update button appearance
              const button = item.querySelector('button');
              button.classList.remove('btn-light');
              button.classList.add('btn-secondary');
              button.textContent = 'Read';
              button.disabled = true;
            });

            // Update notification count to zero
            updateNotificationCount(-9999); // Large negative number to ensure it goes to zero
            
            // Refresh the page to show "no notifications" message
            location.reload();
          }
        })
        .catch(error => {
          console.error('Error marking all notifications as read:', error);
          alert('Failed to mark all notifications as read. Please try again later.');
        });
    }


    function updateNotificationCount(change) {
      // Get all notification badges
      const badges = document.querySelectorAll('.notification-badge');
      
      badges.forEach(badge => {
        // Get current count
        let count = parseInt(badge.textContent);
        
        // Update count (ensure it doesn't go below 0)
        count = Math.max(0, count + change);
        
        if (count === 0) {
          // If count is zero, hide the badge
          badge.style.display = 'none';
          
          // Also hide the badge in the navbar if it exists
          const navBadge = document.querySelector('.nav-item .notification-badge');
          if (navBadge) {
            navBadge.parentElement.style.display = 'none';
          }
        } else {
          // Update the badge text
          badge.textContent = count;
        }
      });
    }

    
    function hasPaymentJS(payments, bookingId) {
      for (var i = 0; i < payments.length; i++) {
        if (payments[i].booking_id == bookingId) {
          return true;
        }
      }
      return false;
    }
    
    // Initialize when page loads
    window.onload = function() {
      // Check if there's a hash in the URL
      if (window.location.hash) {
        var sectionId = window.location.hash.substring(1);
        showSection(sectionId);
      }
      
      // Handle form submission flag
      <?php if ($from_form_submission): ?>
      console.log("Form submission detected, forcing tab activation");
      setTimeout(function() {
        var tabName = window.location.hash.substring(1) || 'dashboard';
        showSection(tabName);
      }, 100);
      <?php endif; ?>
      
      // Set up search functionality
      document.getElementById('hostelSearch').addEventListener('input', function() {
        var searchTerm = this.value.toLowerCase();
        var hostelCards = document.querySelectorAll('#hostelsContainer .hostel-card');
        
        hostelCards.forEach(function(card) {
          var hostelName = card.querySelector('h4').textContent.toLowerCase();
          var hostelLocation = card.querySelector('p').textContent.toLowerCase();
          
          if (hostelName.includes(searchTerm) || hostelLocation.includes(searchTerm)) {
            card.parentElement.style.display = 'block';
          } else {
            card.parentElement.style.display = 'none';
          }
        });
      });
      
      // Set up profile form submission
      document.getElementById('profileForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        var currentPassword = document.getElementById('currentPassword').value;
        var newPassword = document.getElementById('newPassword').value;
        var confirmPassword = document.getElementById('confirmPassword').value;
        
        if (!currentPassword || !newPassword || !confirmPassword) {
          alert('Please fill in all password fields');
          return;
        }
        
        if (newPassword !== confirmPassword) {
          alert('New passwords do not match');
          return;
        }
        
        // Implement password update
        alert('Password updated successfully');
        
        // Clear form
        document.getElementById('currentPassword').value = '';
        document.getElementById('newPassword').value = '';
        document.getElementById('confirmPassword').value = '';
      });
    };
  </script>
  
  <style>
    /* Dashboard styles */
    body {
      display: flex;
      min-height: 100vh;
      flex-direction: column; /* Stacks elements vertically */
      /* background-color: #f5f5f5; */
    }

    .navbar {
      position: sticky;
      top: 0;
      z-index: 1000;
    }

    .content-area {
      display: flex;
      flex: 1;
    }
    
    .sidebar {
      width: 250px;
      background-color: #2d3a4c;
      color: white;
      padding: 20px 0;
      height: calc(100vh - 56px);
      position: sticky;
      top: 56px;
    }
    
    .main-content {
      flex: 1;
      padding: 20px;
      overflow-y: auto;
    }
    
    .sidebar-header {
      padding: 0 20px 20px;
      border-bottom: 1px solid rgba(255,255,255,0.1);
      text-align: center;
    }
    
    .sidebar-menu {
      list-style: none;
      padding: 0;
      margin: 20px 0;
    }
    
    .sidebar-menu li {
      margin-bottom: 5px;
    }
    
    .sidebar-menu a {
      display: block;
      padding: 10px 20px;
      color: white;
      text-decoration: none;
      transition: all 0.3s;
    }
    
    .sidebar-menu a:hover, .sidebar-menu a.active {
      background-color: rgba(255,255,255,0.1);
      border-left: 4px solid #cd0cf3;
    }
    
    .sidebar-menu a i {
      margin-right: 10px;
      width: 20px;
      text-align: center;
    }
    
    .dashboard-section {
      display: none;
    }
    
    .dashboard-section.active {
      display: block;
    }
    
    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }
    
    /* Add this to your CSS styles section around line 120 */
    .card {
      margin-bottom: 20px;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      height: 100%; /* Make all cards the same height */
    }

    .card-body {
      display: flex;
      flex-direction: column;
      height: 100%; /* Fill the card height */
    }

    .card-text.display-4 {
      margin-top: auto;
      margin-bottom: auto;
      padding: 10px 0;
    }

    
    .notification-badge {
      background-color: #cd0cf3;
      color: white;
      border-radius: 50%;
      padding: 2px 6px;
      font-size: 12px;
      margin-left: 5px;
    }
    
    .hostel-card {
      border: 1px solid #ddd;
      border-radius: 10px;
      overflow: hidden;
      margin-bottom: 20px;
      transition: transform 0.3s;
    }
    
    .hostel-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    
    .hostel-image img {
      width: 100%;
      height: 200px;
      object-fit: cover;
    }
    
    .hostel-info {
      padding: 15px;
    }
    
    .btn-primary {
      background-color: #cd0cf3;
      border-color: #cd0cf3;
    }
    
    .btn-primary:hover {
      background-color: #b00ad3;
      border-color: #b00ad3;
    }
    
    .booking-status {
      font-weight: bold;
    }
    
    .status-pending {
      color: #ffc107;
    }
    
    .status-confirmed {
      color: #28a745;
    }
    
    .status-cancelled {
      color: #dc3545;
    }
    
    .payment-status {
      font-weight: bold;
    }
    
    .payment-pending {
      color: #ffc107;
    }
    
    .payment-completed {
      color: #28a745;
    }
    
    .payment-failed {
      color: #dc3545;
    }


    .profile-image-container {
      width: 150px;
      height: 150px;
      margin: 0 auto;
      overflow: hidden;
      border-radius: 50%;
      border: 5px solid #f8f9fa;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }

    .profile-image {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .profile-avatar-placeholder {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .profile-avatar-placeholder svg {
      width: 100%;
      height: 100%;
    }

  </style>
</head>

<body>
   <nav class="navbar navbar-expand-md navbar-dark bg-dark">
    <div class="container-fluid">
      <a class="navbar-brand" href="#">Campus Mediate</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <span class="nav-link">Hello, <?php echo htmlspecialchars($username); ?></span>
          </li>
          <?php if ($notification_count > 0): ?>
          <li class="nav-item">
            <a class="nav-link" href="#" onclick="showSection('notifications'); return false;">
              <i class="fas fa-bell"></i>
              <span class="notification-badge"><?php echo $notification_count; ?></span>
            </a>
          </li>
          <?php endif; ?>
          <li class="nav-item">
            <a class="nav-link" href="../../../logout.php">Logout</a>
          </li>
        </ul>
      </div>
    </div>
   </nav>

  <!-- Content area with sidebar and main content -->
  <div class="content-area">
    <!-- Sidebar on the left -->
    <div class="sidebar">
      <div class="sidebar-header">
        <h4>Student Dashboard</h4>
        <p>Welcome <?php echo htmlspecialchars($username); ?></p>
      </div>
      <ul class="sidebar-menu">
        <li><a href="#" class="active" onclick="showSection('dashboard'); return false;"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="#" onclick="showSection('hostels'); return false;"><i class="fas fa-building"></i> Browse Hostels</a></li>
        <li><a href="#" onclick="showSection('bookings'); return false;"><i class="fas fa-calendar-check"></i> My Bookings</a></li>
        <li><a href="#" onclick="showSection('payments'); return false;"><i class="fas fa-credit-card"></i> Payments</a></li>
        <li><a href="#" onclick="showSection('notifications'); return false;"><i class="fas fa-bell"></i> Notifications <?php if ($notification_count > 0): ?><span class="notification-badge"><?php echo $notification_count; ?></span><?php endif; ?></a></li>
        <li><a href="#" onclick="showSection('profile'); return false;"><i class="fas fa-user"></i> Profile</a></li>
      </ul>
    </div>

    <div class="main-content">
      <!-- Dashboard Overview Section -->
      <section id="dashboard" class="dashboard-section active">
        <div class="section-header">
          <h2>Dashboard Overview</h2>
        </div>
        
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
          <div class="col">
            <div class="card text-center">
              <div class="card-body">
                <i class="fas fa-building fa-3x mb-3 text-primary"></i>
                <h5 class="card-title">Available Hostels</h5>
                <p class="card-text display-4"><?php echo count($hostels); ?></p>
                <button class="btn btn-sm btn-primary" onclick="showSection('hostels'); return false;">Browse Hostels</button>
              </div>
            </div>
          </div>
          
          <div class="col">
            <div class="card text-center">
              <div class="card-body">
                <i class="fas fa-calendar-check fa-3x mb-3 text-success"></i>
                <h5 class="card-title">My Bookings</h5>
                <p class="card-text display-4"><?php echo count($bookings); ?></p>
                <button class="btn btn-sm btn-primary" onclick="showSection('bookings'); return false;">View Bookings</button>
              </div>
            </div>
          </div>
          
          <div class="col">
            <div class="card text-center">
              <div class="card-body">
                <i class="fas fa-credit-card fa-3x mb-3 text-warning"></i>
                <h5 class="card-title">Payments</h5>
                <p class="card-text display-4"><?php echo count($payments); ?></p>
                <button class="btn btn-sm btn-primary" onclick="showSection('payments'); return false;">View Payments</button>
              </div>
            </div>
          </div>
          
          <div class="col">
            <div class="card text-center">
              <div class="card-body">
                <i class="fas fa-bell fa-3x mb-3 text-danger"></i>
                <h5 class="card-title">Notifications</h5>
                <p class="card-text display-4"><?php echo $notification_count; ?></p>
                <button class="btn btn-sm btn-primary" onclick="showSection('notifications'); return false;">View Notifications</button>
              </div>
            </div>
          </div>
        </div>
        
        <?php if (count($bookings) > 0): ?>
        <div class="card mt-4">
          <div class="card-header">
            <h5>Recent Bookings</h5>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>Hostel</th>
                    <th>Room Type</th>
                    <th>Check-in</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach(array_slice($bookings, 0, 3) as $booking): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($booking['hostel_name']); ?></td>
                    <td><?php echo htmlspecialchars($booking['room_type']); ?></td>
                    <td><?php echo htmlspecialchars($booking['check_in_date']); ?></td>
                    <td>
                      <span class="booking-status status-<?php echo strtolower($booking['status']); ?>">
                        <?php echo htmlspecialchars($booking['status']); ?>
                      </span>
                    </td>
                    <td>
                      <a href="#" class="btn btn-sm btn-primary" onclick="viewBookingDetails(<?php echo $booking['id']; ?>); return false;">Details</a>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </section>

      <!-- Browse Hostels Section -->
      <section id="hostels" class="dashboard-section">
        <div class="section-header">
          <h2>Browse Hostels</h2>
          <div>
            <input type="text" class="form-control" id="hostelSearch" placeholder="Search hostels...">
          </div>
        </div>
        
        <div class="row" id="hostelsContainer">
          <?php foreach($hostels as $hostel): ?>
          <div class="col-md-6 col-lg-4">
            <div class="hostel-card">
              <div class="hostel-image">
                <?php if (!empty($hostel['image_path'])): ?>
                <img src="<?php echo htmlspecialchars('../../../' . $hostel['image_path']); ?>" alt="<?php echo htmlspecialchars($hostel['name']); ?>">
                <?php else: ?>
                <img src="../../../assets/images/hostel-1.jpg" alt="Hostel Image">
                <?php endif; ?>
              </div>
              <div class="hostel-info">
                <h4><?php echo htmlspecialchars($hostel['name']); ?></h4>
                <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($hostel['location']); ?></p>
                <p><i class="fas fa-bed"></i> Available Rooms: <?php echo htmlspecialchars($hostel['available_rooms']); ?></p>
                <a href="#" class="btn btn-primary" onclick="viewHostelDetails(<?php echo $hostel['id']; ?>); return false;">View Details</a>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- My Bookings Section -->
      <section id="bookings" class="dashboard-section">
        <div class="section-header">
          <h2>My Bookings</h2>
        </div>
        
        <?php if (count($bookings) > 0): ?>
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>Booking ID</th>
                <th>Hostel</th>
                <th>Room Type</th>
                <th>Check-in</th>
                <th>Check-out</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($bookings as $booking): ?>
              <tr>
                <td>#<?php echo $booking['id']; ?></td>
                <td><?php echo htmlspecialchars($booking['hostel_name']); ?></td>
                <td><?php echo htmlspecialchars($booking['room_type']); ?></td>
                <td><?php echo htmlspecialchars($booking['check_in_date']); ?></td>
                <td><?php echo htmlspecialchars($booking['check_out_date']); ?></td>
                <td>
                  <span class="booking-status status-<?php echo strtolower($booking['status']); ?>">
                    <?php echo htmlspecialchars($booking['status']); ?>
                  </span>
                </td>

                <td>
                  <a href="#" class="btn btn-sm btn-primary" onclick="viewBookingDetails(<?php echo $booking['id']; ?>); return false;">Details</a>
                  <?php if ($booking['status'] == 'Confirmed' && !hasPayment($payments, $booking['id'])): ?>
                  <a href="#" class="btn btn-sm btn-success" onclick="makePayment(<?php echo $booking['id']; ?>); return false;">Pay Now</a>
                  <?php endif; ?>
                  <?php if ($booking['status'] == 'Pending'): ?>
                  <a href="#" class="btn btn-sm btn-danger" onclick="cancelBooking(<?php echo $booking['id']; ?>); return false;">Cancel</a>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
          <p>You don't have any bookings yet. <a href="#" onclick="showSection('hostels'); return false;">Browse hostels</a> to make a booking.</p>
        </div>
        <?php endif; ?>
      </section>

      <!-- Payments Section -->
      <section id="payments" class="dashboard-section">
        <div class="section-header">
          <h2>Payments</h2>
        </div>
        
        <?php if (count($payments) > 0): ?>
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>Payment ID</th>
                <th>Booking</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Date</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($payments as $payment): ?>
              <tr>
                <td>#<?php echo $payment['id']; ?></td>
                <td><?php echo htmlspecialchars($payment['hostel_name']); ?> - <?php echo htmlspecialchars($payment['room_type']); ?></td>
                <td><?php echo number_format($payment['amount'], 2); ?> UGX</td>
                <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                <td><?php echo date('Y-m-d', strtotime($payment['payment_date'])); ?></td>
                <td>
                  <span class="payment-status payment-<?php echo strtolower($payment['status']); ?>">
                    <?php echo htmlspecialchars($payment['status']); ?>
                  </span>
                </td>
                <td>
                  <a href="#" class="btn btn-sm btn-primary" onclick="viewPaymentDetails(<?php echo $payment['id']; ?>); return false;">Details</a>
                  <?php if ($payment['status'] == 'Pending'): ?>
                  <a href="#" class="btn btn-sm btn-success" onclick="completePayment(<?php echo $payment['id']; ?>); return false;">Complete</a>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
          <p>You don't have any payments yet.</p>
        </div>
        <?php endif; ?>
      </section>

      <!-- Notifications Section -->
      <section id="notifications" class="dashboard-section">
        <div class="section-header">
          <h2>Notifications</h2>
          <?php if (count($notifications) > 0): ?>
          <button class="btn btn-sm btn-secondary" onclick="markAllAsRead()">Mark All as Read</button>
          <?php endif; ?>
        </div>
        
        <?php if (count($notifications) > 0): ?>
        <div class="list-group">
          <?php foreach($notifications as $notification): ?>
          <div class="list-group-item <?php echo $notification['is_read'] ? 'bg-light archived' : ''; ?>" style="<?php echo $notification['is_read'] ? 'opacity: 0.7;' : ''; ?>">
            <div class="d-flex w-100 justify-content-between">
              <h5 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h5>
              <div class="d-flex align-items-center gap-2">
                <?php if ($notification['is_read']): ?>
                <span class="badge bg-secondary">Archived</span>
                <?php endif; ?>
                <small><?php echo timeAgo($notification['created_at']); ?></small>
              </div>
            </div>
            <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
            <?php if ($notification['is_read']): ?>
            <button class="btn btn-sm btn-secondary" disabled>Archived</button>
            <?php else: ?>
            <button class="btn btn-sm btn-light" onclick="markAsRead(<?php echo $notification['id']; ?>)">Mark as Read</button>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
          <p>You don't have any new notifications.</p>
        </div>
        <?php endif; ?>
      </section>

      <!-- Profile Section -->
      <section id="profile" class="dashboard-section">
        <div class="section-header">
          <h2>My Profile</h2>
        </div>
        
        <div class="card">
          <div class="card-body">
            <div class="row">
              <div class="col-md-4 text-center mb-4">
                <div class="profile-image-container">
                  <div class="profile-avatar-placeholder">
                    <svg width="150" height="150" viewBox="0 0 150 150" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <circle cx="75" cy="75" r="75" fill="#e9ecef"/>
                      <circle cx="75" cy="60" r="25" fill="#6c757d"/>
                      <path d="M30 120c0-24.853 20.147-45 45-45s45 20.147 45 45" fill="#6c757d"/>
                    </svg>
                  </div>
                </div>
                <h4 class="mt-3"><?php echo htmlspecialchars($username); ?></h4>
                <p class="text-muted"><?php echo htmlspecialchars($user_email); ?></p>
              </div>
              <div class="col-md-8">
                <form id="profileForm">
                  <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($username); ?>" readonly>
                  </div>
                  <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user_email); ?>" readonly>
                  </div>
                  <div class="mb-3">
                    <label for="currentPassword" class="form-label">Current Password</label>
                    <input type="password" class="form-control" id="currentPassword">
                  </div>
                  <div class="mb-3">
                    <label for="newPassword" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="newPassword">
                  </div>
                  <div class="mb-3">
                    <label for="confirmPassword" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirmPassword">
                  </div>
                  <button type="submit" class="btn btn-primary">Update Password</button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </section>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
