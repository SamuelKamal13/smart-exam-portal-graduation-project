<?php
// Include config file
require_once "../config.php";

class GeminiConnector
{
    private $apiKey;
    private $baseUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent";

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function generateResponse($prompt)
    {
        // Prepare the request data
        $data = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt]
                    ]
                ]
            ],
            "generationConfig" => [
                "temperature" => 0.7,
                "maxOutputTokens" => 800,
                "topP" => 0.8,
                "topK" => 40
            ]
        ];

        // Convert data to JSON
        $jsonData = json_encode($data);

        // Prepare the URL with API key
        $url = $this->baseUrl . "?key=" . $this->apiKey;

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
            return ["error" => "API request failed: " . curl_error($ch)];
        }

        // Close cURL session
        curl_close($ch);

        // Decode the response
        $responseData = json_decode($response, true);

        // Extract the generated text
        if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            return ["response" => $responseData['candidates'][0]['content']['parts'][0]['text']];
        } else if (isset($responseData['error'])) {
            return ["error" => "API error: " . $responseData['error']['message']];
        } else {
            return ["error" => "Unknown response format"];
        }
    }

    // Function to save chat history to database
    public function saveChatHistory($userId, $message, $response)
    {
        global $conn;

        $sql = "INSERT INTO ai_chat_history (user_id, message, response) VALUES (?, ?, ?)";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "iss", $userId, $message, $response);

            if (mysqli_stmt_execute($stmt)) {
                return true;
            } else {
                return false;
            }
        }

        return false;
    }

    // Function to get chat history for a user
    public function getChatHistory($userId, $limit = 10)
    {
        global $conn;

        $sql = "SELECT * FROM ai_chat_history WHERE user_id = ? ORDER BY created_at DESC LIMIT ?";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "ii", $userId, $limit);

            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);

                $history = [];
                while ($row = mysqli_fetch_assoc($result)) {
                    $history[] = $row;
                }

                return $history;
            }
        }

        return [];
    }
}
