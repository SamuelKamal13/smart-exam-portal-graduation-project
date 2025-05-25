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
if (!isset($data['examId']) || !isset($data['questions']) || !is_array($data['questions'])) {
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

// Process and add the questions to the database
try {
    $questions = $data['questions'];
    $success_count = 0;
    $error_count = 0;

    foreach ($questions as $question) {
        // Begin transaction
        mysqli_begin_transaction($conn);

        try {
            // Validate question data
            if (
                !isset($question['text']) || !isset($question['type']) ||
                !isset($question['options']) || !isset($question['correctOption'])
            ) {
                throw new Exception("Invalid question format");
            }

            $question_text = $question['text'];
            $question_type = $question['type'];
            $marks = isset($question['marks']) ? $question['marks'] : 1;
            $options = $question['options'];
            $correct_option = $question['correctOption'];

            // Prepare an insert statement for question
            $sql = "INSERT INTO questions (exam_id, question_text, question_type, marks) VALUES (?, ?, ?, ?)";

            if ($stmt = mysqli_prepare($conn, $sql)) {
                // Bind variables to the prepared statement as parameters
                mysqli_stmt_bind_param($stmt, "issi", $exam_id, $question_text, $question_type, $marks);

                // Attempt to execute the prepared statement
                if (mysqli_stmt_execute($stmt)) {
                    $question_id = mysqli_insert_id($conn);

                    // Insert options
                    $option_count = count($options);

                    for ($i = 0; $i < $option_count; $i++) {
                        $sql = "INSERT INTO question_options (question_id, option_text, is_correct) VALUES (?, ?, ?)";

                        if ($stmt_option = mysqli_prepare($conn, $sql)) {
                            // Determine if this option is correct
                            $is_correct = ($i == $correct_option) ? 1 : 0;

                            // Bind variables
                            mysqli_stmt_bind_param($stmt_option, "isi", $param_question_id, $param_option_text, $param_is_correct);

                            // Set parameters
                            $param_question_id = $question_id;
                            $param_option_text = $options[$i];
                            $param_is_correct = $is_correct;

                            // Execute
                            if (!mysqli_stmt_execute($stmt_option)) {
                                throw new Exception("Error inserting option: " . mysqli_stmt_error($stmt_option));
                            }

                            mysqli_stmt_close($stmt_option);
                        } else {
                            throw new Exception("Error preparing option statement");
                        }
                    }

                    // Commit transaction
                    mysqli_commit($conn);
                    $success_count++;
                } else {
                    throw new Exception("Error inserting question: " . mysqli_stmt_error($stmt));
                }

                mysqli_stmt_close($stmt);
            } else {
                throw new Exception("Error preparing question statement");
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            $error_count++;
        }
    }

    // Return the results
    echo json_encode([
        "success" => true,
        "count" => $success_count,
        "errors" => $error_count
    ]);
} catch (Exception $e) {
    echo json_encode(["error" => "Exception: " . $e->getMessage()]);
}
