<?php
session_start();
include "../../config/config.php";

if (!isset($_SESSION['email'])) {
    header("Location: ../../views/auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_email = $_SESSION['email'];
    $full_name = trim($_POST['full_name']);
    
    if (!empty($full_name)) {
        // Check if full_name column exists, if not add it
        $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'full_name'");
        if ($check_column->num_rows == 0) {
            $conn->query("ALTER TABLE users ADD COLUMN full_name VARCHAR(255) AFTER username");
        }
        
        $stmt = $conn->prepare("UPDATE users SET full_name = ? WHERE email = ?");
        $stmt->bind_param("ss", $full_name, $user_email);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Full name updated successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to update full name.";
        }
        $stmt->close();
    }
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit();
?>