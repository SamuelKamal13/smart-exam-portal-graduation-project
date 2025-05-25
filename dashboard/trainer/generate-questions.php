<?php
// Set headers for JSON response
header('Content-Type: application/json');

// Include config file
require_once "../../config.php";

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

// Check if user is a trainer
if ($_SESSION["role"] !== "trainer") {
    echo json_encode(["error" => "Unauthorized access"]);
    exit;
}

// Get trainer ID
$trainer_id = $_SESSION["id"];

// Get JSON data from request
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Validate required fields
if (
    !isset($data['apiKey']) || !isset($data['files']) || !isset($data['questionCount']) ||
    !isset($data['questionType']) || !isset($data['examId'])
) {
    echo json_encode(["error" => "Missing required parameters"]);
    exit;
}

// Verify that the exam belongs to the current trainer
$exam_id = $data['examId'];
$sql = "SELECT id FROM exams WHERE id = ? AND created_by = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $exam_id, $trainer_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) == 0) {
            echo json_encode(["error" => "Exam not found or access denied"]);
            exit;
        }
    } else {
        echo json_encode(["error" => "Database error"]);
        exit;
    }
    mysqli_stmt_close($stmt);
}

// Process the files and generate questions using Gemini API
try {
    $apiKey = $data['apiKey'];
    $files = $data['files'];
    $questionCount = intval($data['questionCount']);
    $questionType = $data['questionType'];
    $marksPerQuestion = isset($data['marksPerQuestion']) ? intval($data['marksPerQuestion']) : 1;

    // Prepare the prompt based on question type
    $prompt = "Generate {$questionCount} ";

    if ($questionType === 'mcq') {
        $prompt .= "multiple-choice questions";
    } elseif ($questionType === 'true_false') {
        $prompt .= "true/false questions";
    } else { // both
        $prompt .= "questions with a mix of multiple-choice and true/false formats";
    }

    $prompt .= " based on the content of the provided PDF(s). For each question, provide: "
        . "1) The question text, "
        . "2) The options (4 options for multiple-choice, 'True' and 'False' for true/false), "
        . "3) The correct answer. "
        . "Format the response as a JSON array with each question having 'text', 'type' (either 'mcq' or 'true_false'), "
        . "'options' (array of option texts), and 'correctOption' (index of the correct option, 0-based). "
        . "IMPORTANT: Do not include prefixes like 'In the first question', 'In question 4', etc. in the question text. "
        . "Start each question directly with the actual question content.";

    // Prepare the request data for Gemini API
    $requestData = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.7,
            "maxOutputTokens" => 2048,
            "topP" => 0.8,
            "topK" => 40
        ]
    ];

    // Add file contents to the request
    foreach ($files as $file) {
        $requestData["contents"][0]["parts"][] = [
            "inlineData" => [
                "mimeType" => "application/pdf",
                "data" => $file["data"]
            ]
        ];
    }

    // Convert data to JSON
    $jsonData = json_encode($requestData);

    // Prepare the URL with API key
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey;

    // Initialize cURL session
    $ch = curl_init($url);

    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);

    // Execute cURL request
    $response = curl_exec($ch);

    // Check for errors
    if (curl_errno($ch)) {
        echo json_encode(["error" => "API request failed: " . curl_error($ch)]);
        exit;
    }

    // Close cURL session
    curl_close($ch);

    // Decode the response
    $responseData = json_decode($response, true);

    // Extract the generated text
    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        $generatedText = $responseData['candidates'][0]['content']['parts'][0]['text'];

        // Try to extract JSON from the response
        preg_match('/\[\s*{.*}\s*\]/s', $generatedText, $matches);

        if (!empty($matches[0])) {
            $questionsJson = $matches[0];
            $questions = json_decode($questionsJson, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($questions)) {
                // Add marks to each question
                foreach ($questions as &$question) {
                    $question['marks'] = $marksPerQuestion;
                }

                echo json_encode(["questions" => $questions]);
                exit;
            }
        }

        // If JSON extraction failed, return the raw text
        echo json_encode(["error" => "Failed to parse generated questions. Raw response: " . $generatedText]);
    } else if (isset($responseData['error'])) {
        echo json_encode(["error" => "API error: " . $responseData['error']['message']]);
    } else {
        echo json_encode(["error" => "Unknown response format"]);
    }
} catch (Exception $e) {
    echo json_encode(["error" => "Exception: " . $e->getMessage()]);
}
