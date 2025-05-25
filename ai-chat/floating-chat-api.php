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

// Get user information for personalization
$userRole = $_SESSION["role"] ?? "";
$userName = $_SESSION["first_name"] ?? "";
$userLastName = $_SESSION["last_name"] ?? "";

// Create Gemini connector with API key
$gemini = new GeminiConnector("AIzaSyBbByOdKHQW6lTS9R0zjmNTU9BXnmVUKvQ");

// Get JSON data from request
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Initialize response
$response = ["error" => "Invalid request"];

if (isset($data["message"])) {
    $message = trim($data["message"]);

    if (!empty($message)) {
        // Create personalized context based on user role
        $personalizedContext = "";

        if ($userRole == "student") {
            $personalizedContext = "You are speaking with a student user named $userName $userLastName. " .
                "Focus on helping with exam preparation, course navigation, and study tips. " .
                "Use a supportive and encouraging tone.";
        } else if ($userRole == "trainer") {
            $personalizedContext = "You are speaking with a trainer/instructor named $userName $userLastName. " .
                "Focus on helping with course management, exam creation, and student assessment. " .
                "Use a professional and collaborative tone.";
        } else if ($userRole == "supervisor") {
            $personalizedContext = "You are speaking with a supervisor named $userName $userLastName. " .
                "Focus on helping with administrative tasks, reporting, and system oversight. " .
                "Use a formal and efficient tone.";
        } else {
            $personalizedContext = "You are speaking with a user named $userName $userLastName. ";
        }

        // Add context to the prompt to make it website-specific and personalized
        $contextPrompt = "You are an AI assistant for the Smart Exam Portal. " .
            $personalizedContext . " " .
            "You can only answer questions related to the Smart Exam Portal website, " .
            "such as how to use features, navigate the portal, take exams, manage courses, etc. " .
            "Keep your responses brief and concise as this is a floating chat. " .
            "If asked about topics unrelated to the website, politely redirect the conversation " .
            "back to Smart Exam Portal topics. Here's the user's question: " . $message;

        // Get response from Gemini API
        $result = $gemini->generateResponse($contextPrompt);

        if (isset($result["response"])) {
            // Save to chat history
            $gemini->saveChatHistory($userId, $message, $result["response"]);

            $response = ["response" => $result["response"]];
        } else if (isset($result["error"])) {
            $response = ["error" => $result["error"]];
        }
    } else {
        $response = ["error" => "Please enter a message."];
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
