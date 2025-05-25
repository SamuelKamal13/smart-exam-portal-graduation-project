<?php
// Custom error handler
function customErrorHandler($errno, $errstr, $errfile, $errline)
{
    // Log the error
    error_log("Error [$errno] $errstr in $errfile on line $errline");

    // For fatal errors, redirect to the 500 error page
    if ($errno == E_ERROR || $errno == E_CORE_ERROR || $errno == E_COMPILE_ERROR || $errno == E_USER_ERROR) {
        header("Location: " . getBaseUrl() . "/errors/500.php");
        exit;
    }

    // For other errors, continue normal error handling
    return false;
}

// Function to get base URL
function getBaseUrl()
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    // The application's base path is fixed.
    $base_path = "/dashboard/SmartExamPortal";
    return "$protocol://$host$base_path";
}

// Set the custom error handler
set_error_handler("customErrorHandler");

// Handle uncaught exceptions
function exceptionHandler($exception)
{
    error_log("Uncaught Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    header("Location: " . getBaseUrl() . "/errors/500.php");
    exit;
}

// Set the exception handler
set_exception_handler("exceptionHandler");
