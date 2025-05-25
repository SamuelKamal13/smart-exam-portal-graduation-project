<?php
// Include database configuration
require_once "config.php";

// Check if user is already logged in
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    // Redirect user to appropriate dashboard based on role
    if ($_SESSION["role"] == "student") {
        header("location: dashboard/student/index.php");
    } elseif ($_SESSION["role"] == "trainer") {
        header("location: dashboard/trainer/index.php");
    } elseif ($_SESSION["role"] == "supervisor") {
        header("location: dashboard/supervisor/index.php");
    }
    exit;
}

// Redirect to login page
header("location: auth/login.php");
exit;
