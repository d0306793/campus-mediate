<?php
/**
 * Add updated_at column to notifications table
 */

// Include database connection
require_once '../../config/config.php';

try {
    // Check if column already exists
    $check_column = $conn->query("SHOW COLUMNS FROM notifications LIKE 'updated_at'");
    
    if ($check_column->num_rows == 0) {
        // Column doesn't exist, add it
        $alter_query = "ALTER TABLE notifications ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
        
        if ($conn->query($alter_query)) {
            echo "Success: 'updated_at' column added to notifications table.";
        } else {
            echo "Error: " . $conn->error;
        }
    } else {
        echo "Column 'updated_at' already exists in notifications table.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>