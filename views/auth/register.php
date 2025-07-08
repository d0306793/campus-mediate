<?php 
// Session started at the beginng of the script
session_start();
// Include database configuration
include '../../config/config.php';

// initialize variables to prevent undefined variable errors
$error = '';
$username = '';
$email = '';
$role = '';
$confirm_password = ''; // Added confirm password variable

if ($_SERVER['REQUEST_METHOD'] == 'POST'){
// if (isset($POST['registerNow'])) {
    // echo "All is set upto here!";
    // All inputs sanitized - to remove any harmful characters
    $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password']; // Password will be hashed, sanitizing needed
    $confirm_password = $_POST['confirm_password']; // Get confirm password value
    $role = filter_var($_POST['role'], FILTER_SANITIZE_STRING);

    // echo "$username" . "$email" . "$password" . "$role";
    // All inputs validated
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($role)){
        $error = "All fields are required";
    
    }
    // Validate email format 
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $error = "Invalid email format";
    }
    // Validate password strength (at least 8 characters) 
    elseif (strlen($username) < 5) {
        $error = "Username must be at least 5 characters long";
    } 
    // Validate username length
    elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    }
    // Check if passwords match
    elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    }
    // If all validations pass, proceed with registration
    else {
        // Check if username already exists
        $checkUsername = "SELECT * FROM users WHERE username = ?";
        $stmt = $conn->prepare($checkUsername);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "Username Already Exists!";
        }
        // Check if email already exists    
        $checkEmail = "SELECT * FROM users WHERE email= ?";
        $stmt = $conn->prepare($checkEmail);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "Email address already Exists";
        } else {
            // Hash password for security
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user using prepared statement (Prepare SQL to insert new user)
            $insertQuery = "INSERT INTO users(username, email, password_hash, role) VALUES (?, ?, ?, ?);";
            $stmt = $conn->prepare($insertQuery);
        
            // echo "IT IS hERE now";
            $stmt -> bind_param("ssss", $username, $email, $hashed_password, $role);
            
            if ($stmt->execute()) {
                $lastInsertId = $stmt->insert_id; // Get the last inserted ID
                $updateQuery = "UPDATE users SET created_by = ?, modified_by = ? WHERE id = ?";
                $stmtUpdate = $conn->prepare($updateQuery);

                $stmtUpdate->bind_param("iii", $lastInsertId, $lastInsertId, $lastInsertId);
                $stmtUpdate->execute();

                $_SESSION['success_message'] = "Registration Successful!";
                header("location: login.php");
                exit();
            } else {
                $error = "Registration failed. Please try again.";
            }    
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Register for Hostel Management System">
    <title>Hostel Management System - Registration</title>
    <style>

    /* Your existing CSS styles */
    :root {
        --primary-purple: #6a0dad;
        --purple-light: #9c27b0;
        --purple-dark: #4b0082;
        --secondary-blue: #2196f3;
        --text-dark: #2d3748;
        --text-light: #f8f9fa;
        --gray-light: #edf2f7;
        --gray-medium: #e2e8f0;
        --error-red: #dc3545;
        --success-green: #28a745;
    }

    /* Optimized CSS with better performance */
    body {
        font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        line-height: 1.6;
        color: var(--text-dark);
        /* original background:  background: var(--gray-light);*/
        background-image: url('../../assets/images/register_bg.jpg'); /* Generated image */
        background-size: cover; /* Cover the entire background */
        background-position: center; /* Center the image */
        background-repeat: no-repeat; /* Prevent image from repeating */
        background-color: var(--gray-light); /* Fallback color if the image fails to load */
        margin: 0;
        min-height: 100vh;
        display: grid;
        place-items: center;
    }

    .form-container {
        background-color: white;
        border-radius: 0.625rem;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        padding: clamp(1.5rem, 5vw, 2.5rem);
        width: min(100% - 2rem, 500px);
        margin: 1rem;
        border-top: 5px solid var(--primary-purple);

    }

    .form-header {
        text-align: center;
        margin-bottom: 2rem;
    }

    .error-message {
        color: var(--error-red);
        background-color: rgba(220, 53, 69, 0.1);
        padding: 0.75rem;
        border-radius: 0.25rem;
        margin-bottom: 1rem;
        text-align: center;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: var(--primary-purple);
    }

    .form-group input,
    .form-group select {
        width: 100%;
        padding: 0.75rem;
        font-size: 1rem;
        border: 2px solid var(--gray-medium);
        border-radius: 0.375rem;
        box-sizing: border-box;

    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: var(--purple-light);
        box-shadow: 0 0 0 3px rgba(106, 13, 173, 0.2);
    }

    .btn-primary {
        width: 100%;
        padding: 0.75rem;
        background-color: var(--primary-purple);
        color: var(--text-light);
        border: none;
        border-radius: 0.375rem;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    .btn-primary:hover {
        background-color: var(--purple-dark);
    }

    .form-footer {
        text-align: center;
        margin-top: 1.5rem;
    }

    .form-footer a {
        color: var(--secondary-blue);
        text-decoration: none;
        font-weight: 600;
    }

    .form-footer a:hover {
        text-decoration: underline;
    }

    @media (prefers-reduced-motion: reduce) {
        * {
            transition: none !important;
        }
    }
    
    /* Login pointer animation */
    @keyframes pointToLogin {
        0% {
            transform: translateX(0);
            opacity: 0.7;
        }
        50% {
            transform: translateX(-10px);
            opacity: 1;
        }
        100% {
            transform: translateX(0);
            opacity: 0.7;
        }
    }
    
    .login-pointer {
        display: inline-block;
        color: var(--primary-purple);
        font-size: 1.2rem;
        margin-right: 5px;
        animation: pointToLogin 1.5s infinite ease-in-out;
    }
    
    .sticky-login-hint {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background-color: white;
        padding: 10px 15px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        border-left: 4px solid var(--primary-purple);
        z-index: 100;
        display: flex;
        align-items: center;
    }
    </style>
</head>
<body>
    <div class="form-container">
        <div style="text-align: center; margin-bottom: 1rem;">
            <img src="../../assets/images/logo/logo_bg.png" alt="Campus Mediate Logo" style="width: 150px; height: auto;">
        </div>
        <div class="form-header">
  <?php      
       // Display error message if any
       if (!empty($error)) {
           echo '<div class="error-message">' . htmlspecialchars($error) . '</div>';
       }
   ?>


            <h1>Create Your Account</h1>
            <p>Join our hostel management system today</p>
        </div>

        <form id="registrationForm" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" novalidate>
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required 
                       pattern="[a-zA-Z0-9]{5,20}" 
                       title="5-20 alphanumeric characters"
                       value="<?php echo htmlspecialchars($username); ?>"
                       required minlength="5">
            </div>
         
    <!--Please recall why you used BOTH MECHANISMS value="
    echo htmlspecialchars($email); AND
    echo htmlspecialchars($email ?? '');-->

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required
                       value="<?php echo htmlspecialchars($email ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required 
                       minlength="8" 
                       pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}"
                       aria-describedby="password-hint">
                <p id="password-hint" class="password-hint">Must include uppercase, lowercase, number, and 8+ characters</p>
            </div>
            
            <!-- Added confirm password field -->
            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required 
                       minlength="8">
            </div>
            
            <div class="form-group">
                <label for="role">I am a:</label>
                <select id="role" name="role" required>
                    <option value="">Select role</option>
                    <option value="student" <?php echo ($role ?? '') === 'student' ? 'selected' : ''; ?>>Student</option>
                    <option value="manager" <?php echo ($role ?? '') === 'manager' ? 'selected' : ''; ?>>Hostel Manager</option>
                    <!-- Added admin role option -->
                    <option value="admin" <?php echo ($role ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>
            
            <button type="submit" class="btn-primary" name="registerNow">Register Now</button>
        </form>

        <div class="form-footer">
            <span class="login-pointer">→</span> Already have an account? <a href="login.php">Sign in</a>
        </div>
        
        <div class="sticky-login-hint">
            <span class="login-pointer">→</span>
            <span>Already registered? <a href="login.php">Sign in here</a></span>
        </div>
    </div>
</body>
</html>