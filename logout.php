<?php
session_start();

// Handle logout request
if (isset($_GET['logout']) || isset($_POST['logout'])) {
    // Clear all session variables
    $_SESSION = [];
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    header('Location: login.php');
    exit;
}

// Ensure only authenticated users can access this page
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}