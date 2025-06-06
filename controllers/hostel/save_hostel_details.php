<?php
session_start();
include '../../config/config.php';
include '../../includes/functions.php';

if (!isset($_SESSION['email'])) {
    $_SESSION['error_message'] = 'Not logged in.';
    header("Location: login.php");
    exit();
}

$manager_email = $_SESSION['email'];
$manager_id = getUserIdFromEmail($conn, $manager_email);

if ($manager_id === null) {
    $_SESSION['error_message'] = 'Invalid manager ID.';
    header("Location: ../../views/dashboard/manager/dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $location = $_POST['location'] ?? '';
    $description = $_POST['description'] ?? '';
    $amenities = $_POST['amenities'] ?? [];
    $contact = $_POST['contact'] ?? '';
    $total_rooms = $_POST['total_rooms'] ?? 0;
    $amenities_json = json_encode($amenities);    
    $status = $_POST['status'] ?? 'inactive';

    // Handle image upload
    $image_path = '';
    if (isset($_FILES['image_path']) && $_FILES['image_path']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../assets/uploads/';
        // Creates the upload directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $uniqueFilename = uniqid() . '_' . $_FILES['image_path']['name'];
        $image_path = $uploadDir . $uniqueFilename;
        
        // Move the file to the uploads directory
        if (move_uploaded_file($_FILES['image_path']['tmp_name'], $image_path)) {
            // Store the relative path in the database
            $image_path = 'assets/uploads/' . $uniqueFilename;
        } else {
            $_SESSION['hostel_error_message'] = 'Failed to upload image.';
            header("Location: ../../views/dashboard/manager/dashboard.php#hostel");
            exit();
        }
    }

    // Check if a hostel already exists for this manager
    $stmt_check = $conn->prepare("SELECT id, image_path FROM hostels WHERE manager_id = ? LIMIT 1");
    $stmt_check->bind_param("i", $manager_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        $existing_hostel = $result_check->fetch_assoc();
        

        if (!empty($image_path)) {
            // If new image is uploaded, use it
            $stmt_update = $conn->prepare("UPDATE hostels SET name = ?, location = ?, description = ?, amenities = ?, status = ?, total_rooms = ?, contact = ?, image_path = ? WHERE manager_id = ?");
            $stmt_update->bind_param("sssssissi", $name, $location, $description, $amenities_json, $status, $total_rooms, $contact, $image_path, $manager_id);
        } else {
            // If no new image, don't update the image_path field
            $stmt_update = $conn->prepare("UPDATE hostels SET name = ?, location = ?, description = ?, amenities = ?, status = ?, total_rooms = ?, contact = ? WHERE manager_id = ?");
            $stmt_update->bind_param("sssssisi", $name, $location, $description, $amenities_json, $status, $total_rooms, $contact, $manager_id);
        }
        
        if ($stmt_update->execute()) {
            $_SESSION['hostel_success_message'] = 'Hostel details updated successfully!';
        } else {
            $_SESSION['hostel_error_message'] = 'Failed to update hostel details: ' . $conn->error;
        }
        $stmt_update->close();
    } else {
        // Insert new hostel
        $stmt_insert = $conn->prepare("INSERT INTO hostels (manager_id, name, location, description, amenities, status, total_rooms, contact, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_insert->bind_param("isssssiss", $manager_id, $name, $location, $description, $amenities_json, $status, $total_rooms, $contact, $image_path);
        if ($stmt_insert->execute()) {
            $_SESSION['hostel_success_message'] = 'Hostel details saved successfully!';
        } else {
            $_SESSION['hostel_error_message'] = 'Failed to save hostel details: ' . $conn->error;
        }
        $stmt_insert->close();
    }
    $stmt_check->close();
} else {
    $_SESSION['hostel_error_message'] = 'Invalid request method.';
}

$conn->close();

// Set a flag in session to indicate we're coming from form submission
$_SESSION['from_form_submission'] = true;
$_SESSION['active_tab'] = $_POST['active_tab'] ?? 'hostel';
header("Location:../../views/dashboard/manager/dashboard.php#" . $_SESSION['active_tab']);
exit();

// // Redirect back to dashboard with hash
// header("Location: Location: ../../views/dashboard/manager/dashboard.php#dashboard");
// exit();
?>