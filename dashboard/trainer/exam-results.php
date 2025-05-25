<?php
// Set page title and base URL for includes
$page_title = "Exam Results";
$base_url = "../..";

// Include config file
require_once "../../config.php";

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../../auth/login.php");
    exit;
}

// Check if user is a trainer
if ($_SESSION["role"] !== "trainer") {
    // Redirect to appropriate dashboard based on role
    if ($_SESSION["role"] == "student") {
        header("location: ../student/index.php");
    } elseif ($_SESSION["role"] == "supervisor") {
        header("location: ../supervisor/index.php");
    }
    exit;
}

// Get trainer ID
$trainer_id = $_SESSION["id"];

// Fetch all courses taught by this trainer
$courses = [];
$courses_sql = "SELECT id, title FROM courses WHERE trainer_id = ? ORDER BY title";
if ($courses_stmt = mysqli_prepare($conn, $courses_sql)) {
    mysqli_stmt_bind_param($courses_stmt, "i", $trainer_id);
    if (mysqli_stmt_execute($courses_stmt)) {
        $courses_result = mysqli_stmt_get_result($courses_stmt);
        while ($course = mysqli_fetch_assoc($courses_result)) {
            $courses[] = $course;
        }
    }
    mysqli_stmt_close($courses_stmt);
}

// If a specific exam ID is provided, redirect to the results page for that exam
if (isset($_GET["exam_id"]) && !empty($_GET["exam_id"])) {
    $exam_id = $_GET["exam_id"];

    // Verify the exam belongs to this trainer
    $exam_sql = "SELECT e.id, e.course_id FROM exams e 
                JOIN courses c ON e.course_id = c.id 
                WHERE e.id = ? AND c.trainer_id = ?";
    if ($exam_stmt = mysqli_prepare($conn, $exam_sql)) {
        mysqli_stmt_bind_param($exam_stmt, "ii", $exam_id, $trainer_id);
        if (mysqli_stmt_execute($exam_stmt)) {
            $exam_result = mysqli_stmt_get_result($exam_stmt);
            if (mysqli_num_rows($exam_result) == 1) {
                $exam = mysqli_fetch_assoc($exam_result);
                header("location: results.php?course_id=" . $exam["course_id"] . "&exam_id=" . $exam_id);
                exit;
            }
        }
        mysqli_stmt_close($exam_stmt);
    }
}

// If no valid exam ID is provided, redirect to the results page
header("location: results.php");
exit;
