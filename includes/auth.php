<?php
// auth.php
function loginUser($user) {
    $_SESSION['user_id'] = $user['id'];
}

function isAuthenticated() {
    return isset($SESSION['user_id']);
}

function logoutUser() {
    session_destroy();

}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

?>