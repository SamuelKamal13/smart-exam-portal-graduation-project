<?php
// Include config file and start session
require_once "../../config.php";

// Set header to return JSON
header('Content-Type: application/json');

// Initialize response array
$response = ['success' => false, 'message' => '', 'student' => null, 'stats' => null, 'exams' => null];

// Check if user is logged in and is a supervisor
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "supervisor") {
    $response['message'] = 'Unauthorized access.';
    echo json_encode($response);
    exit;
}

// Check if student_id is provided
if (!isset($_GET['student_id']) || !filter_var($_GET['student_id'], FILTER_VALIDATE_INT)) {
    $response['message'] = 'Invalid Student ID provided.';
    echo json_encode($response);
    exit;
}

$student_id = (int)$_GET['student_id'];

// Get student information
$sql = "SELECT id, user_id, first_name, last_name, email, phone, created_at 
        FROM users 
        WHERE id = ? AND role = 'student'";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) == 1) {
            $response['student'] = mysqli_fetch_assoc($result);
        } else {
            // Student not found
            $response['message'] = 'Student not found.';
            echo json_encode($response);
            exit;
        }
    } else {
        $response['message'] = 'Error executing query: ' . mysqli_error($conn);
        echo json_encode($response);
        exit;
    }
    mysqli_stmt_close($stmt);
}

// Get student exam results
$sql = "SELECT r.id, e.title as exam_title, c.title as course_title, 
        r.score, r.total_marks, r.percentage, r.submission_time
        FROM results r
        JOIN exams e ON r.exam_id = e.id
        LEFT JOIN courses c ON e.course_id = c.id
        WHERE r.student_id = ?
        ORDER BY r.submission_time DESC";

$exam_results = [];
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            // Convert numeric fields to proper types
            $row['score'] = (int)$row['score'];
            $row['total_marks'] = (int)$row['total_marks'];
            $row['percentage'] = (float)$row['percentage'];
            $exam_results[] = $row;
        }
    } else {
        $response['message'] = 'Error executing query: ' . mysqli_error($conn);
        echo json_encode($response);
        exit;
    }
    mysqli_stmt_close($stmt);
}

// Calculate overall statistics
$total_exams = count($exam_results);
$total_score = 0;
$total_marks = 0;
$exams_passed = 0;

foreach ($exam_results as $result) {
    $total_score += $result['score'];
    $total_marks += $result['total_marks'];
    if ($result['percentage'] >= 60) {
        $exams_passed++;
    }
}

$overall_percentage = ($total_marks > 0) ? ($total_score / $total_marks) * 100 : 0;

// Prepare stats array
$response['stats'] = [
    'total_exams' => $total_exams,
    'total_score' => $total_score,
    'total_marks' => $total_marks,
    'exams_passed' => $exams_passed,
    'avg_percentage' => $overall_percentage
];

// Add exam results to response
$response['exams'] = $exam_results;

// Set success flag
$response['success'] = true;

// Close connection
mysqli_close($conn);

// Output JSON response
echo json_encode($response);
exit;
