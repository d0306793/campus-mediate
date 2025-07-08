<?php
// Function to get username from email
// function getUsernameFromEmail($conn, $email) {
//     $sql = "SELECT username FROM users WHERE email=?";
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param("s", $email);
//     $stmt->execute();
//     $result = $stmt->get_result();
    
//     if ($row = $result->fetch_assoc()) {
//         return htmlspecialchars($row['username'] ?? '', ENT_QUOTES, 'UTF-8');
//     } else {
//         return "No user found";
//     }
// }

// function getuserROLEFromEmail($conn, $email) {
//     $sql = "SELECT role FROM users WHERE email=?";
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param("s", $email);
//     $stmt->execute();
//     $result = $stmt->get_result();
    
//     if ($row = $result->fetch_assoc()) {
//         return htmlspecialchars($row['role'] ?? '', ENT_QUOTES, 'UTF-8');
//     } else {
//         return "No user found";
//     }
// }

// Secure email validation helper
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function getUsernameFromEmail(mysqli $conn, string $email): string {
    if (!isValidEmail($email)) {
        error_log("Invalid email format in getUsernameFromEmail: $email");
        return "Invalid email";
    }

    $sql = "SELECT username FROM users WHERE email = ? LIMIT 1";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $email);
        if ($stmt->execute()) {
            $stmt->bind_result($username);
            if ($stmt->fetch()) {
                return htmlspecialchars(trim($username), ENT_QUOTES, 'UTF-8');
            }
        }
        $stmt->close();
    }
    error_log("User not found or error in getUsernameFromEmail for email: $email");
    return "No user found";
}

function getUserRoleFromEmail(mysqli $conn, string $email): string {
    if (!isValidEmail($email)) {
        error_log("Invalid email format in getUserRoleFromEmail: $email");
        return "Invalid email";
    }

    $sql = "SELECT role FROM users WHERE email = ? LIMIT 1";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $email);
        if ($stmt->execute()) {
            $stmt->bind_result($role);
            if ($stmt->fetch()) {
                return htmlspecialchars(trim($role), ENT_QUOTES, 'UTF-8');
            }
        }
        $stmt->close();
    }
    error_log("User role not found or error in getUserRoleFromEmail for email: $email");
    return "No user found";
}


function getUserIdFromEmail($conn, $email) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['id'];
    }
    return null;
}

/**
 * Get the manager ID for a hostel
 */
function getHostelManagerId($conn, $hostel_id) {
    $stmt = $conn->prepare("SELECT manager_id FROM hostels WHERE id = ?");
    $stmt->bind_param("i", $hostel_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['manager_id'];
    }
    return null;
}


/**
 * Create a notification
 */
function createNotification($conn, $notification_type, $title, $message, $hostel_id, $related_id, $recipient_id) {
    $stmt = $conn->prepare("
        INSERT INTO notifications (
            notification_type, title, message, hostel_id, related_id, recipient_id
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssiii", $notification_type, $title, $message, $hostel_id, $related_id, $recipient_id);
    $stmt->execute();
    $stmt->close();
}


/**
 * Get room inventory details by room type
 */
function getRoomInventoryByType($conn, $hostel_id, $room_type) {
    $stmt = $conn->prepare("SELECT id, quantity, price_per_semester, status FROM rooms 
                           WHERE hostel_id = ? AND room_type = ? LIMIT 1");
    $stmt->bind_param("is", $hostel_id, $room_type);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}


/**
 * Generate a room number based on template
 */
function generateRoomNumber($template, $index = null) {
    $prefix = $template['prefix'] ?? '';
    $startNumber = $template['start_number'] ?? 1;
    $padding = $template['padding'] ?? 2;
    
    if ($index === null) {
        $index = 0;
    }
    
    $number = $startNumber + $index;
    $paddedNumber = str_pad($number, $padding, '0', STR_PAD_LEFT);
    
    $roomNumber = $prefix . $paddedNumber;
    
    if ($template['floor_prefix']) {
        // Calculate floor based on room number (simplified example)
        $floor = ceil($number / 10);
        $roomNumber = $floor . '-' . $roomNumber;
    }
    
    return $roomNumber;
}


/**
 * Generate an example room number for display
 */
function generateRoomNumberExample($template) {
    return generateRoomNumber($template);
}

/**
 * Format template description for display
 */
function formatTemplateDescription($template) {
    $desc = '';
    
    if ($template['floor_prefix']) {
        $desc .= 'Floor-';
    }
    
    if (!empty($template['prefix'])) {
        $desc .= $template['prefix'];
    }
    
    $desc .= str_pad('N', $template['padding'], '0', STR_PAD_LEFT);
    $desc .= ' (starting from ' . $template['start_number'] . ')';
    
    return $desc;
}

/**
 * Assign a room number for a booking
 */
function assignRoomNumber($conn, $booking_id, $hostel_id, $room_id, $room_type) {
    // Get the template for this room type
    $stmt = $conn->prepare("SELECT * FROM room_numbering_templates WHERE hostel_id = ? AND room_type = ?");
    $stmt->bind_param("is", $hostel_id, $room_type);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // No template, use default numbering
        $template = [
            'prefix' => '',
            'start_number' => 1,
            'padding' => 3,
            'floor_prefix' => false
        ];
    } else {
        $template = $result->fetch_assoc();
    }
    
    // Get existing assignments for this hostel to avoid duplicates
    $stmt = $conn->prepare("
        SELECT ra.assigned_room_number 
        FROM room_assignments ra
        JOIN bookings b ON ra.booking_id = b.id
        WHERE b.hostel_id = ?
    ");
    $stmt->bind_param("i", $hostel_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $existingNumbers = [];
    while ($row = $result->fetch_assoc()) {
        $existingNumbers[] = $row['assigned_room_number'];
    }
    
    // Find an available room number
    $index = 0;
    $roomNumber = '';
    do {
        $roomNumber = generateRoomNumber($template, $index);
        $index++;
    } while (in_array($roomNumber, $existingNumbers) && $index < 1000); // Prevent infinite loop
    
    // Insert the assignment
    $stmt = $conn->prepare("INSERT INTO room_assignments (booking_id, room_id, assigned_room_number) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $booking_id, $room_id, $roomNumber);
    $stmt->execute();
    
    return $roomNumber;
}


/**
 * Check if rooms are available for BOOKING
 * 
 * @param mysqli $conn Database connection
 * @param int $hostel_id Hostel ID
 * @param int $room_id Room ID
 * @param string $check_in_date Check-in date (YYYY-MM-DD)
 * @param string $check_out_date Check-out date (YYYY-MM-DD)
 * @param int $quantity Number of rooms needed
 * @return bool True if rooms are available, false otherwise
 */
function checkRoomAvailability($conn, $hostel_id, $room_id, $check_in_date, $check_out_date, $quantity = 1) {
    // Get total quantity of this room type
    $stmt_room = $conn->prepare("SELECT quantity FROM rooms WHERE id = ? AND hostel_id = ?");
    $stmt_room->bind_param("ii", $room_id, $hostel_id);
    $stmt_room->execute();
    $result_room = $stmt_room->get_result();
    $room = $result_room->fetch_assoc();
    $total_quantity = $room['quantity'] ?? 0;
    $stmt_room->close();
    
    if ($total_quantity < $quantity) {
        // Not enough rooms of this type in total
        return false;
    }
    
    // Count how many rooms are already booked for overlapping dates
    $stmt_bookings = $conn->prepare("
        SELECT COUNT(*) as booked_count 
        FROM bookings 
        WHERE room_id = ? 
        AND hostel_id = ? 
        AND status != 'Cancelled' 
        AND (
            (check_in_date <= ? AND check_out_date >= ?) OR
            (check_in_date <= ? AND check_out_date >= ?) OR
            (check_in_date >= ? AND check_out_date <= ?)
        )
    ");
    $stmt_bookings->bind_param(
        "iissssss", 
        $room_id, 
        $hostel_id, 
        $check_out_date, $check_in_date,  // Case 1: Booking starts before and ends after check-in
        $check_in_date, $check_in_date,   // Case 2: Booking starts before and ends after check-out
        $check_in_date, $check_out_date   // Case 3: Booking is completely within the requested period
    );
    $stmt_bookings->execute();
    $result_bookings = $stmt_bookings->get_result();
    $row_bookings = $result_bookings->fetch_assoc();
    $booked_count = $row_bookings['booked_count'] ?? 0;
    $stmt_bookings->close();
    
    // Check if there are enough rooms available
    $available_rooms = $total_quantity - $booked_count;
    return $available_rooms >= $quantity;
}


/**
 * Check if a payment method is active for a hostel
 */
function isPaymentMethodActive($conn, $hostel_id, $method_name) {
    $stmt = $conn->prepare("SELECT id FROM payment_methods WHERE hostel_id = ? AND method_name = ? AND is_active = 1");
    $stmt->bind_param("is", $hostel_id, $method_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $is_active = ($result->num_rows > 0);
    $stmt->close();
    return $is_active;
}


function getAmenityIcon($amenity) {
    $icons = [
        'wifi' => 'fa-wifi',
        'security' => 'fa-shield-alt',
        'water' => 'fa-tint',
        'bathroom' => 'fa-bath',
        'kitchen' => 'fa-utensils',
        'study' => 'fa-book',
        'parking' => 'fa-parking',
        'gym' => 'fa-dumbbell',
        'laundry' => 'fa-tshirt',
        'electricity' => 'fa-bolt'
    ];
    
    foreach ($icons as $key => $icon) {
        if (stripos($amenity, $key) !== false) {
            return $icon;
        }
    }
    
    return 'fa-check'; // Default icon
}

function getAssetUrl($path) {
    // Adjust this base URL to match your project's web root
    $baseUrl = '/campus-mediate/';
    return $baseUrl . ltrim($path, '/');
}

function getHostelSetting($conn, $hostel_id, $setting_name) {
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM hostel_settings WHERE hostel_id = ? AND setting_name = ?");
        $stmt->bind_param("is", $hostel_id, $setting_name);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            return $result->fetch_assoc()['setting_value'];
        }
    } catch (Exception $e) {
        // Table doesn't exist or other error
        return null;
    }
    return null;
}


function setHostelSetting($conn, $hostel_id, $setting_name, $setting_value) {
    $stmt = $conn->prepare("
        INSERT INTO hostel_settings (hostel_id, setting_name, setting_value)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE setting_value = ?
    ");
    $stmt->bind_param("isss", $hostel_id, $setting_name, $setting_value, $setting_value);
    return $stmt->execute();
}



function getGreeting() {
    $hour = date('G');
    if ($hour < 12) {
        return "Good morning";
    } else if ($hour < 18) {
        return "Good afternoon";
    } else {
        return " Good evening";
    }
}

/**
 * Get the end date of the currently active semester
 * If no active semester is found, returns a date 4 months from now
 * 
 * @param mysqli $conn Database connection
 * @return string Date in Y-m-d format
 */
function getActiveSemesterEndDate($conn) {
    try {
        // Check if university_calendar table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'university_calendar'");
        
        if ($table_check->num_rows > 0) {
            // Table exists, get active semester end date
            $stmt = $conn->prepare("
                SELECT end_date FROM university_calendar 
                WHERE is_active = 1 
                ORDER BY end_date DESC 
                LIMIT 1
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                return $row['end_date'];
            }
        }
    } catch (Exception $e) {
        error_log("Error getting semester end date: " . $e->getMessage());
    }
    
    // Default fallback - 4 months from now (typical semester length)
    return date('Y-m-d', strtotime('+4 months'));
}
?>
