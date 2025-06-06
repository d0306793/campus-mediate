<?php
// db_config.php

function dbConnect() {
    $host = '127.0.0.1';
    $username = 'root';
    $password = '';
    $database = 'hostel_management_system';

    $conn = mysqli_connect($host, $username, $password, $database);

    if (!$conn) {
        echo "<h1>Not Connected</h1>";
        die("Connection failed: " . mysqli_connect_error());
    }
    
    echo "<h1>Connected</h1>";
    mysqli_set_charset($conn, "utf8mb4");
    return $conn;

}

// Verify tables exist or create them
function verifyDatabaseTables() {
    $conn = dbConnect();
    
    // Create users table if not exists
    $usersTable = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('student', 'manager', 'admin') DEFAULT 'student',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    // Create sessions table if not exists
    $sessionsTable = "CREATE TABLE IF NOT EXISTS sessions (
        session_id VARCHAR(128) PRIMARY KEY,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    mysqli_query($conn, $usersTable);
    mysqli_query($conn, $sessionsTable);
    
    // Check for errors
    if (mysqli_error($conn)) {
        die("Table creation failed: " . mysqli_error($conn));
    }
}

// First check if user exists
function checkUserExists($username, $email) {
    $conn = dbConnect();
    $username = mysqli_real_escape_string($conn, $username);
    $email = mysqli_real_escape_string($conn, $email);
    
    $query = "SELECT username, email FROM users 
              WHERE username = '$username' OR email = '$email'";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        if ($row['email'] == $email) {
            return "Email already registered";
        }
        if ($row['username'] == $username) {
            return "Username already taken";
        }
    }
    return false;
}

// Create User Function with role support
function createUser($username, $email, $password, $role = 'student') {
    $conn = dbConnect();
    
    // Check if user exists
    $exists = checkUserExists($username, $email);
    if ($exists) {
        return $exists;
    }
    
    // Proceed with user creation
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $username = mysqli_real_escape_string($conn, $username);
    $email = mysqli_real_escape_string($conn, $email);
    $role = mysqli_real_escape_string($conn, $role);
    
    $query = "INSERT INTO users (username, email, password, role) 
              VALUES ('$username', '$email', '$hashedPassword', '$role')";
    
    if (mysqli_query($conn, $query)) {
        return mysqli_insert_id($conn);
    }
    return "Registration failed: " . mysqli_error($conn);
}

// Session Management Functions
function createSession($user_id, $remember = false) {
    $conn = dbConnect();
    $session_id = bin2hex(random_bytes(64));
    $expires_at = $remember ? 
        date('Y-m-d H:i:s', strtotime('+30 days')) : 
        date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $query = "INSERT INTO sessions (session_id, user_id, expires_at)
              VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sis", $session_id, $user_id, $expires_at);
    
    if (mysqli_stmt_execute($stmt)) {
        return $session_id;
    }
    return false;
}

function validateSession($session_id) {
    $conn = dbConnect();
    $session_id = mysqli_real_escape_string($conn, $session_id);
    
    $query = "SELECT u.* FROM sessions s
              JOIN users u ON s.user_id = u.id
              WHERE s.session_id = '$session_id' 
              AND s.expires_at > NOW()";
    
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return false;
}

function deleteSession($session_id) {
    $conn = dbConnect();
    $session_id = mysqli_real_escape_string($conn, $session_id);
    
    $query = "DELETE FROM sessions WHERE session_id = '$session_id'";
    return mysqli_query($conn, $query);
}

// Get User by Email (for login)
function getUserByEmail($email) {
    $conn = dbConnect();
    $email = mysqli_real_escape_string($conn, $email);
    
    $query = "SELECT * FROM users WHERE email = '$email'";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return false;
}

// Enhanced Login Function with Session Support
function loginUser($email, $password, $remember = false) {
    $user = getUserByEmail($email);
    
    if ($user && password_verify($password, $user['password'])) {
        $session_id = createSession($user['id'], $remember);
        if ($session_id) {
            return [
                'user' => $user,
                'session_id' => $session_id
            ];
        }
    }
    return false;
}

// Get All Users
function getUsers() {
    $conn = dbConnect();
    $query = "SELECT id, username, email, role, created_at FROM users";
    $result = mysqli_query($conn, $query);
    
    $users = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
    return $users;
}

// Update User
function updateUser($id, $username, $email, $role = null) {
    $conn = dbConnect();
    
    $username = mysqli_real_escape_string($conn, $username);
    $email = mysqli_real_escape_string($conn, $email);
    $id = (int)$id;
    
    $query = "UPDATE users SET username = '$username', email = '$email'";
    if ($role) {
        $role = mysqli_real_escape_string($conn, $role);
        $query .= ", role = '$role'";
    }
    $query .= " WHERE id = $id";
    
    return mysqli_query($conn, $query);
}

// Delete User (with session cleanup)
function deleteUser($id) {
    $conn = dbConnect();
    $id = (int)$id;
    
    // First delete sessions
    mysqli_query($conn, "DELETE FROM sessions WHERE user_id = $id");
    
    // Then delete user
    $query = "DELETE FROM users WHERE id = $id";
    return mysqli_query($conn, $query);
}

// Initialize database tables on include
verifyDatabaseTables();
