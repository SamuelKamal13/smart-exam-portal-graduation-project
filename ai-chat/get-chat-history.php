<?php
// Initialize the session
session_start();

// Check if the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header('Content-Type: application/json');
    echo json_encode(["error" => "User not logged in"]);
    exit;
}

// Include API connector
require_once "api-connector.php";

// Get user ID from session
$userId = $_SESSION["id"];

// Create Gemini connector with API key
$gemini = new GeminiConnector("AIzaSyBbByOdKHQW6lTS9R0zjmNTU9BXnmVUKvQ");

// Get chat history
$history = $gemini->getChatHistory($userId, 5); // Limit to last 5 messages for the floating chat

// Return JSON response
header('Content-Type: application/json');
echo json_encode(["history" => array_reverse($history)]);
