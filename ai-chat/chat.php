<?php
// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../auth/login.php");
    exit;
}

// Include API connector
require_once "api-connector.php";

// Set page title and base URL for header
$page_title = "AI Chat Assistant";
$base_url = "..";

// Add custom CSS for AI chat
$extra_css = '<link rel="stylesheet" href="' . $base_url . '/assets/css/ai-chat.css">';

// Include header
include "../includes/header.php";

// Initialize variables
$message = "";
$response = "";
$error = "";
$history = [];

// Create Gemini connector with API key
$gemini = new GeminiConnector("AIzaSyBbByOdKHQW6lTS9R0zjmNTU9BXnmVUKvQ");

// Get user ID from session
$userId = $_SESSION["id"];

// Get user information for personalization
$userRole = $_SESSION["role"] ?? "";
$userName = $_SESSION["first_name"] ?? "";
$userLastName = $_SESSION["last_name"] ?? "";

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["message"])) {
    $message = trim($_POST["message"]);

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
            "If asked about topics unrelated to the website, politely redirect the conversation " .
            "back to Smart Exam Portal topics. Here's the user's question: " . $message;

        // Get response from Gemini API
        $result = $gemini->generateResponse($contextPrompt);

        if (isset($result["response"])) {
            $response = $result["response"];

            // Save to chat history
            $gemini->saveChatHistory($userId, $message, $response);

            // Don't redirect, just get the updated history
            $history = $gemini->getChatHistory($userId);
        } else if (isset($result["error"])) {
            $error = $result["error"];
        }
    } else {
        $error = "Please enter a message.";
    }
} else {
    // Get chat history only if not a POST request
    $history = $gemini->getChatHistory($userId);
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">AI Chat Assistant</h1>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="chat-header">
                    <i class="fas fa-robot"></i>
                    <h6>Chat with AI Assistant</h6>
                </div>
                <div class="card-body">
                    <div class="chat-container" id="chatContainer">
                        <?php if (count($history) > 0): ?>
                            <?php foreach (array_reverse($history) as $chat): ?>
                                <div class="user-message clearfix">
                                    <div class="font-weight-bold">You</div>
                                    <div><?php echo nl2br(htmlspecialchars($chat['message'])); ?></div>
                                </div>
                                <div class="ai-message clearfix">
                                    <div class="font-weight-bold">AI Assistant</div>
                                    <div><?php echo nl2br(htmlspecialchars($chat['response'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="chat-form-container">
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <div class="form-group">
                                <textarea class="form-control chat-textarea" name="message" rows="2" placeholder="Ask a question about the Smart Exam Portal..."></textarea>
                                <button type="submit" class="btn btn-primary send-button">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Scroll to the bottom of the chat container when the page loads
    document.addEventListener('DOMContentLoaded', function() {
        var chatContainer = document.getElementById('chatContainer');
        if (chatContainer) {
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }
    });
</script>

<?php
// Include footer
include "../includes/footer.php";
?>