<?php
require_once 'config.php';

if (isLoggedIn()) {
    logActivity($_SESSION['user_id'], 'LOGOUT', 'user', $_SESSION['user_id'], 'User logged out');
}

// Destroy session
session_destroy();

// Redirect to login
redirect('login.php');
?>