<?php
// Include config file and start session
require_once "../../config.php";

// Set header to return JSON
header('Content-Type: application/json');

// Initialize response array
$response = ['success' => false, 'message' => '', 'questions' => null];

// Check if user is logged in and is a supervisor
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "supervisor") {
    $response['message'] = 'Unauthorized access.';
    echo json_encode($response);
    exit;
}

// Check if exam_id is provided
if (!isset($_GET['exam_id']) || !filter_var($_GET['exam_id'], FILTER_VALIDATE_INT)) {
    $response['message'] = 'Invalid Exam ID provided.';
    echo json_encode($response);
    exit;
}

$exam_id = (int)$_GET['exam_id'];

// --- Database Query ---
// Prepare SQL to get questions and their answer statistics for the given exam
$sql = "SELECT
            q.id AS question_id,
            q.question_text,
            SUM(CASE WHEN sa.is_correct = 1 THEN 1 ELSE 0 END) AS correct_count,
            SUM(CASE WHEN sa.is_correct = 0 THEN 1 ELSE 0 END) AS incorrect_count,
            COUNT(sa.id) AS total_answers
        FROM questions q
        LEFT JOIN student_answers sa ON q.id = sa.question_id
        WHERE q.exam_id = ?
        GROUP BY q.id, q.question_text
        ORDER BY q.id";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $exam_id);

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $questions_data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Ensure counts are integers
            $row['correct_count'] = (int)$row['correct_count'];
            $row['incorrect_count'] = (int)$row['incorrect_count'];
            $row['total_answers'] = (int)$row['total_answers'];
            $questions_data[] = $row;
        }
        mysqli_free_result($result);

        $response['success'] = true;
        $response['questions'] = $questions_data;
    } else {
        $response['message'] = 'Error executing query: ' . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
} else {
    $response['message'] = 'Error preparing query: ' . mysqli_error($conn);
}

// Close connection
mysqli_close($conn);

// Output JSON response
echo json_encode($response);
exit;
