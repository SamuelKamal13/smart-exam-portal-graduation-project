<?php
// Set base URL for includes
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

// Check if exam ID is provided and confirmation is yes
if (!isset($_GET["id"]) || empty($_GET["id"]) || !isset($_GET["confirm"]) || $_GET["confirm"] !== "yes") {
    header("location: manage-exams.php");
    exit;
}

$exam_id = $_GET["id"];

// Verify that the exam belongs to the trainer
$check_sql = "SELECT id FROM exams WHERE id = ? AND created_by = ?";
if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
    mysqli_stmt_bind_param($check_stmt, "ii", $exam_id, $trainer_id);
    if (mysqli_stmt_execute($check_stmt)) {
        $check_result = mysqli_stmt_get_result($check_stmt);
        if (mysqli_num_rows($check_result) != 1) {
            // Exam not found or doesn't belong to this trainer
            header("location: manage-exams.php");
            exit;
        }
    } else {
        echo "Oops! Something went wrong. Please try again later.";
        exit;
    }
    mysqli_stmt_close($check_stmt);
}

// Begin transaction
mysqli_begin_transaction($conn);

try {
    // Delete exam results
    $delete_results_sql = "DELETE FROM results WHERE exam_id = ?";
    if ($delete_results_stmt = mysqli_prepare($conn, $delete_results_sql)) {
        mysqli_stmt_bind_param($delete_results_stmt, "i", $exam_id);
        mysqli_stmt_execute($delete_results_stmt);
        mysqli_stmt_close($delete_results_stmt);
    }

    // Delete question options
    $delete_options_sql = "DELETE qo FROM question_options qo 
                          JOIN questions q ON qo.question_id = q.id 
                          WHERE q.exam_id = ?";
    if ($delete_options_stmt = mysqli_prepare($conn, $delete_options_sql)) {
        mysqli_stmt_bind_param($delete_options_stmt, "i", $exam_id);
        mysqli_stmt_execute($delete_options_stmt);
        mysqli_stmt_close($delete_options_stmt);
    }

    // Delete questions
    $delete_questions_sql = "DELETE FROM questions WHERE exam_id = ?";
    if ($delete_questions_stmt = mysqli_prepare($conn, $delete_questions_sql)) {
        mysqli_stmt_bind_param($delete_questions_stmt, "i", $exam_id);
        mysqli_stmt_execute($delete_questions_stmt);
        mysqli_stmt_close($delete_questions_stmt);
    }

    // Delete exam
    $delete_exam_sql = "DELETE FROM exams WHERE id = ? AND created_by = ?";
    if ($delete_exam_stmt = mysqli_prepare($conn, $delete_exam_sql)) {
        mysqli_stmt_bind_param($delete_exam_stmt, "ii", $exam_id, $trainer_id);
        mysqli_stmt_execute($delete_exam_stmt);
        mysqli_stmt_close($delete_exam_stmt);
    }

    // Commit transaction
    mysqli_commit($conn);

    // Redirect to exams page with success message
    header("location: manage-exams.php?success=deleted");
    exit;
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    echo "Error: " . $e->getMessage();
    exit;
}
