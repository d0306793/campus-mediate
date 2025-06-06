<?php
session_start();
// session security
session_regenerate_id(true);
include '../../config/config.php';
// include 'functions.php'; // Included later after successful authentication

// Checks and stores the success message if it exists
$registerSuccess = '';

if (isset($_SESSION['success_message'])) {
    $registerSuccess = $_SESSION['success_message'];
    unset($_SESSION['success_message']);  // clear message after displaying.
}

// Check if user is already logged in AND if the 'remember_me' cookie exists
if (!isset($_SESSION['email']) && isset($_COOKIE['remember_me'])) {
    // Retrieve the token from the cookie
    $token = $_COOKIE['remember_me'];

    // Prepare a query to find a user with this token
    $sql = "SELECT user_id, email, role FROM users WHERE remember_token = ? LIMIT 1";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            // Automatically log the user in by setting session variables
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['email'] = $row['email'];

            include 'functions.php'; // Include functions here as we need getUserRoleFromEmail
            $_SESSION['role'] = getUserRoleFromEmail($conn, $row['email']);

            // Redirect the user based on their role
            if ($_SESSION['role'] === 'manager') {
                header("Location: ../dashboard/manager/dashboard.php");
            } elseif ($_SESSION['role'] === 'student') {
                header("Location: ../dashboard/student/homepage.php");
            } elseif ($_SESSION['role'] === 'admin') {
                header("Location: admin_dashboard.php");
            }
            exit();
        } else {
            // If the token is not found or invalid, clear the cookie
            setcookie('remember_me', '', time() - 3600, '/', '', true, true);
        }
        $stmt->close();
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST'){

    // print_r($_POST);
    // Sanitize input
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    // Check if fields are empty
    if(empty($email) || empty($password)){
        $error = "Both fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $error = "Invalid email format.";
    } else {
        // Prepare query to check user by email
        $sql = "SELECT id, email, password_hash FROM users WHERE email=?";  // Select only necessary columns
        $stmt = $conn -> prepare($sql);
        $stmt -> bind_param("s", $email);
        $stmt -> execute();
        $result = $stmt -> get_result();

        if ($result -> num_rows > 0) {
            $row = $result -> fetch_assoc();
                if(password_verify($password, $row['password_hash'])){

                    $_SESSION['user_id'] = $row['id']; // Changed 'user_id' to 'id' to match your query
                    $_SESSION['email'] = $row['email'];

                    include '../../includes/functions.php'; // Include here after successful authentication
                    $userRole = getUserRoleFromEmail($conn, $row['email']);
                    $_SESSION['role'] = $userRole;

                    // Check if the "Remember Me" checkbox was selected
                    if (isset($_POST['remember'])) {
                        // Generate a unique, strong token
                        $rememberToken = bin2hex(random_bytes(64));

                        // Update the user's record in the database with the token
                        $updateSql = "UPDATE users SET remember_token = ? WHERE id = ?"; // Changed 'user_id' to 'id'
                        $updateStmt = $conn->prepare($updateSql);
                        if ($updateStmt) {
                            $updateStmt->bind_param("si", $rememberToken, $row['id']); // Changed 'user_id' to 'id'
                            $updateStmt->execute();
                            $updateStmt->close();

                            // Set a cookie to store the token on the user's browser
                            $expire = time() + (30 * 24 * 60 * 60); // Cookie expires in 30 days
                            setcookie('remember_me', $rememberToken, $expire, '/', '', true, true);
                        }
                    } else {
                        // If "Remember Me" is not checked, ensure any existing cookie is cleared
                        setcookie('remember_me', '', time() - 3600, '/', '', true, true);
                        // And clear the token from the database
                        $updateSql = "UPDATE users SET remember_token = NULL WHERE id = ?"; // Changed 'user_id' to 'id'
                        $updateStmt = $conn->prepare($updateSql);
                        if ($updateStmt) {
                            $updateStmt->bind_param("i", $row['id']); // Changed 'user_id' to 'id'
                            $updateStmt->execute();
                            $updateStmt->close();
                        }
                    }
                    // Redirect the user based on their role
                    if ($_SESSION['role'] === 'manager') {
                        header("Location: ../dashboard/manager/dashboard.php");
                    } elseif ($_SESSION['role'] === 'student') {
                        header("Location: ../dashboard/student/homepage.php");
                    } elseif ($_SESSION['role'] === 'admin') {
                        header("Location: admin_dashboard.php");
                    } else {
                        $error = "Unauthorized role.";
                    }
                    exit();

                } else {
                    $error = "Invalid email or password.";
                }
        } else {
            // No user found with that email
            $error = "No user is registered with that email!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login to Hostel Management System">
    <title>Hostel Management System - Login</title>
    <style>
        /* Your existing CSS styles */
        :root {
            --primary-purple: #6a0dad;
            --purple-light: #9c27b0;
            --purple-dark: #4b0082;
            --secondary-blue: #2196f3;
            --blue-light: #64b5f6;
            --accent-green: #4caf50;
            --text-dark: #2d3748;
            --text-light: #f8f9fa;
            --gray-light: #edf2f7;
            --gray-medium: #e2e8f0;
            --error-red: #dc3545;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.12);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
        }

        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background-image: url('../../assets/images/logo/logo_bg.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-color: var(--gray-light);
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
        }

        .form-container {
            background-color: white;
            border-radius: 0.625rem;
            box-shadow: var(--shadow-md);
            padding: clamp(1.5rem, 5vw, 2.5rem);
            width: min(100% - 2rem, 500px);
            margin: 1rem;
            border-top: 5px solid var(--primary-purple);
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-header h1 {
            color: var(--primary-purple);
            font-size: clamp(1.5rem, 4vw, 1.8rem);
            margin-bottom: 0.5rem;
        }

        .success-message {
            background-color: var(--accent-green);
            color: var(--text-light);
            padding: 1rem 1.5rem;
            border-radius: 5px;
            box-shadow: var(--shadow-sm);
            font-weight: bold;
            text-align: center;
            margin: 1rem auto;
            width: fit-content;
            max-width: 90%;
            animation: fadeOut 0.5s ease-in-out 3s forwards;
        }

        @keyframes fadeOut {
            to {
                opacity: 0;
                visibility: hidden;
            }
        }

        .alert {
            padding: 10px;
            border-radius: 5px;
        }
        .alert-success {
            background-color: #dff0d8;
            border: 1px solid var(--accent-green);;
            color: var(--accent-green);
        }

        .error-message {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--error-red);
            padding: 0.75rem;
            border-radius: 0.375rem;
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

        .form-group input {
            width: 100%;
            padding: 0.75rem;
            font-size: 1rem;
            border: 2px solid var(--gray-medium);
            border-radius: 0.375rem;
            background-color: var(--gray-light);
            box-sizing: border-box;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--purple-light);
            box-shadow: 0 0 0 3px rgba(106, 13, 173, 0.2);
            background-color: white;
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap; /* Prevent wrapping of the container's content */
        }

        .remember-me input[type="checkbox"] {
            width: 1rem;
            height: 1rem;
            accent-color: var(--primary-purple);
        }

        .remember-me .remember-text {
            font-size: 0.8em;
            color: #777;
            margin-left: 0.5em;
            transition: color 0.3s ease;
        }

        .remember-me:hover .remember-text {
            color: var(--secondary-blue);
        }

        .forgot-password a {
            color: var(--secondary-blue);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .forgot-password a:hover {
            color: var(--blue-light);
            text-decoration: underline;
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
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .btn-primary:hover {
            background-color: var(--purple-dark);
            transform: translateY(-2px);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .form-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-dark);
        }

        .form-footer a {
            color: var(--secondary-blue);
            text-decoration: none;
            font-weight: 600;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 576px) {
            .form-options {
                flex-direction: column;
                align-items: flex-start;
            }

            .forgot-password {
                align-self: flex-end;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                transition: none !important;
            }
        }
    </style>
</head>
<body>
    <?php if (isset($_GET['message']) && $_GET['message'] === 'logged_out'): ?>
        <div class="success-message">You have been successfully logged out.</div>
    <?php endif; ?>


    <div class="form-container">
        <div style="text-align: center; margin-bottom: 1rem;">
            <img src="../../assets/images/logo/logo_bg.png" alt="Campus Mediate Logo" style="width: 150px; height: auto;">
        </div>
        <div class="form-header">
            <h1>Welcome Back</h1>
            <?php if (!empty($registerSuccess)) : ?>
                <div class ="alert alert-success"><?php echo htmlspecialchars($registerSuccess) ?></div>
            <?php endif; ?>

            <?php if (isset($error)) : ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <p>Login to manage your hostel experience</p>
        </div>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" novalidate>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email"
                       id="email"
                       name="email"
                       value="<?php echo htmlspecialchars($email ?? ''); ?>"
                       required
                       autocomplete="off">
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password"
                       id="password"
                       name="password"
                       required>
            </div>

            <div class="form-options">
                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Remember me</label>
                    <span class="remember-text">
                        Stay signed in for 30 days (Recommended on personal devices).
                    </span>
                </div>
                <div class="forgot-password">
                    <a href="forgot-password.php">Forgot password?</a>
                </div>
            </div>

            <button id="btn" type="submit" class="btn-primary">Login</button>
        </form>

        <div class="form-footer">
            Don't have an account? <a href="register.php">Register here</a>
        </div>
    </div>
</body>
</html>