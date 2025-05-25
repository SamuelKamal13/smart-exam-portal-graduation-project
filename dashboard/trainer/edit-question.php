<?php
// Set page title and base URL for includes
$page_title = "Edit Question";
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

// Check if question_id and exam_id are provided
if (!isset($_GET["id"]) || empty($_GET["id"]) || !isset($_GET["exam_id"]) || empty($_GET["exam_id"])) {
    header("location: manage-exams.php");
    exit;
}

$question_id = $_GET["id"];
$exam_id = $_GET["exam_id"];

// Verify that the exam belongs to the current trainer
$sql = "SELECT e.* FROM exams e 
        WHERE e.id = ? AND e.created_by = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $exam_id, $trainer_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) == 0) {
            // Exam not found or doesn't belong to this trainer
            header("location: manage-exams.php");
            exit;
        }
    } else {
        echo "Oops! Something went wrong. Please try again later.";
        exit;
    }
    mysqli_stmt_close($stmt);
}

// Get question details
$sql = "SELECT * FROM questions WHERE id = ? AND exam_id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $question_id, $exam_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) == 0) {
            // Question not found
            header("location: manage-questions.php?exam_id=" . $exam_id);
            exit;
        }
        $question = mysqli_fetch_assoc($result);
    } else {
        echo "Oops! Something went wrong. Please try again later.";
        exit;
    }
    mysqli_stmt_close($stmt);
}

// Get question options
$options = [];
$correct_option = "";
$sql = "SELECT * FROM question_options WHERE question_id = ? ORDER BY id ASC";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $question_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $i = 0;
        while ($row = mysqli_fetch_assoc($result)) {
            $options[$i] = $row['option_text'];
            if ($row['is_correct'] == 1) {
                $correct_option = $i;
            }
            $i++;
        }
    }
    mysqli_stmt_close($stmt);
}

// Define variables and initialize with empty values
$question_text = $question['question_text'];
$question_type = $question['question_type'];
$marks = $question['marks'];
$question_text_err = $question_type_err = $marks_err = "";
$options_err = ["", "", "", ""];
$correct_option_err = "";
$success_message = "";
$error_message = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate question text
    if (empty(trim($_POST["question_text"]))) {
        $question_text_err = "Please enter the question text.";
    } else {
        $question_text = trim($_POST["question_text"]);
    }

    // Validate marks
    if (empty(trim($_POST["marks"]))) {
        $marks_err = "Please enter marks.";
    } elseif (!is_numeric(trim($_POST["marks"])) || trim($_POST["marks"]) <= 0) {
        $marks_err = "Please enter a valid number for marks.";
    } else {
        $marks = trim($_POST["marks"]);
    }

    // Validate options based on question type
    if ($question_type == "mcq") {
        $has_option_error = false;
        for ($i = 0; $i < 4; $i++) {
            if (empty(trim($_POST["option"][$i]))) {
                $options_err[$i] = "Please enter option text.";
                $has_option_error = true;
            } else {
                $options[$i] = trim($_POST["option"][$i]);
            }
        }

        // Validate correct option
        if (empty($_POST["correct_option"])) {
            $correct_option_err = "Please select the correct option.";
        } else {
            $correct_option = $_POST["correct_option"];
        }
    } elseif ($question_type == "true_false") {
        $options[0] = "True";
        $options[1] = "False";

        // Validate correct option for true/false
        if (empty($_POST["tf_correct_option"])) {
            $correct_option_err = "Please select the correct option.";
        } else {
            $correct_option = $_POST["tf_correct_option"];
        }
    }

    // Check input errors before updating in database
    if (
        empty($question_text_err) && empty($marks_err) &&
        (($question_type == "mcq" && !$has_option_error && empty($correct_option_err)) ||
            ($question_type == "true_false" && empty($correct_option_err)))
    ) {

        // Begin transaction
        mysqli_begin_transaction($conn);

        try {
            // Prepare an update statement for question
            $sql = "UPDATE questions SET question_text = ?, marks = ? WHERE id = ?";

            if ($stmt = mysqli_prepare($conn, $sql)) {
                // Bind variables to the prepared statement as parameters
                mysqli_stmt_bind_param($stmt, "sii", $param_question_text, $param_marks, $param_question_id);

                // Set parameters
                $param_question_text = $question_text;
                $param_marks = $marks;
                $param_question_id = $question_id;

                // Attempt to execute the prepared statement
                if (mysqli_stmt_execute($stmt)) {
                    // Get existing options
                    $existing_options = [];
                    $sql_get_options = "SELECT id FROM question_options WHERE question_id = ? ORDER BY id ASC";
                    if ($stmt_get = mysqli_prepare($conn, $sql_get_options)) {
                        mysqli_stmt_bind_param($stmt_get, "i", $question_id);
                        if (mysqli_stmt_execute($stmt_get)) {
                            $result = mysqli_stmt_get_result($stmt_get);
                            while ($row = mysqli_fetch_assoc($result)) {
                                $existing_options[] = $row['id'];
                            }
                        }
                        mysqli_stmt_close($stmt_get);
                    }

                    // Update options
                    $option_count = ($question_type == "mcq") ? 4 : 2;

                    for ($i = 0; $i < $option_count; $i++) {
                        if (isset($existing_options[$i])) {
                            // Update existing option
                            $sql = "UPDATE question_options SET option_text = ?, is_correct = ? WHERE id = ?";

                            if ($stmt_option = mysqli_prepare($conn, $sql)) {
                                // Determine if this option is correct
                                $is_correct = ($i == $correct_option) ? 1 : 0;

                                // Bind variables
                                mysqli_stmt_bind_param($stmt_option, "sii", $param_option_text, $param_is_correct, $param_option_id);

                                // Set parameters
                                $param_option_text = $options[$i];
                                $param_is_correct = $is_correct;
                                $param_option_id = $existing_options[$i];

                                // Execute
                                if (!mysqli_stmt_execute($stmt_option)) {
                                    throw new Exception("Error updating option: " . mysqli_stmt_error($stmt_option));
                                }

                                mysqli_stmt_close($stmt_option);
                            } else {
                                throw new Exception("Error preparing option update statement");
                            }
                        } else {
                            // Insert new option
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
                                throw new Exception("Error preparing option insert statement");
                            }
                        }
                    }

                    // Remove any extra options if needed
                    if (count($existing_options) > $option_count) {
                        for ($i = $option_count; $i < count($existing_options); $i++) {
                            $sql = "DELETE FROM question_options WHERE id = ?";
                            if ($stmt_delete = mysqli_prepare($conn, $sql)) {
                                mysqli_stmt_bind_param($stmt_delete, "i", $existing_options[$i]);
                                if (!mysqli_stmt_execute($stmt_delete)) {
                                    throw new Exception("Error deleting extra option: " . mysqli_stmt_error($stmt_delete));
                                }
                                mysqli_stmt_close($stmt_delete);
                            }
                        }
                    }

                    // Commit transaction
                    mysqli_commit($conn);

                    // Success message
                    $success_message = "Question updated successfully!";
                } else {
                    throw new Exception("Error updating question: " . mysqli_stmt_error($stmt));
                }

                mysqli_stmt_close($stmt);
            } else {
                throw new Exception("Error preparing question statement");
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Include header
include_once "../../includes/header.php";
?>

<div class="container-fluid">
    <h1 class="mt-4 mb-4">Edit Question</h1>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Edit Question</h6>
        </div>
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $question_id . "&exam_id=" . $exam_id); ?>" method="post">
                <div class="form-group">
                    <label>Question Text</label>
                    <textarea name="question_text" class="form-control <?php echo (!empty($question_text_err)) ? 'is-invalid' : ''; ?>" rows="3"><?php echo $question_text; ?></textarea>
                    <span class="invalid-feedback"><?php echo $question_text_err; ?></span>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Question Type</label>
                            <input type="text" class="form-control" value="<?php echo ($question_type == 'mcq') ? 'Multiple Choice' : 'True/False'; ?>" readonly>
                            <small class="form-text text-muted">Question type cannot be changed.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Marks</label>
                            <input type="number" name="marks" class="form-control <?php echo (!empty($marks_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $marks; ?>">
                            <span class="invalid-feedback"><?php echo $marks_err; ?></span>
                        </div>
                    </div>
                </div>

                <?php if ($question_type == "mcq"): ?>
                    <!-- MCQ Options -->
                    <div id="mcqOptions">
                        <h5 class="mt-3">Options</h5>
                        <?php for ($i = 0; $i < 4; $i++): ?>
                            <div class="form-group">
                                <label>Option <?php echo chr(65 + $i); ?></label>
                                <input type="text" name="option[<?php echo $i; ?>]" class="form-control <?php echo (!empty($options_err[$i])) ? 'is-invalid' : ''; ?>" value="<?php echo $options[$i]; ?>">
                                <span class="invalid-feedback"><?php echo $options_err[$i]; ?></span>
                            </div>
                        <?php endfor; ?>

                        <div class="form-group">
                            <label>Correct Answer</label>
                            <select name="correct_option" class="form-control <?php echo (!empty($correct_option_err)) ? 'is-invalid' : ''; ?>">
                                <option value="">Select Correct Answer</option>
                                <option value="0" <?php echo ($correct_option == "0") ? 'selected' : ''; ?>>A</option>
                                <option value="1" <?php echo ($correct_option == "1") ? 'selected' : ''; ?>>B</option>
                                <option value="2" <?php echo ($correct_option == "2") ? 'selected' : ''; ?>>C</option>
                                <option value="3" <?php echo ($correct_option == "3") ? 'selected' : ''; ?>>D</option>
                            </select>
                            <span class="invalid-feedback"><?php echo $correct_option_err; ?></span>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- True/False Options -->
                    <div id="trueFalseOptions">
                        <div class="form-group mt-3">
                            <label>Correct Answer</label>
                            <select name="tf_correct_option" class="form-control <?php echo (!empty($correct_option_err)) ? 'is-invalid' : ''; ?>">
                                <option value="">Select Correct Answer</option>
                                <option value="0" <?php echo ($correct_option == "0") ? 'selected' : ''; ?>>True</option>
                                <option value="1" <?php echo ($correct_option == "1") ? 'selected' : ''; ?>>False</option>
                            </select>
                            <span class="invalid-feedback"><?php echo $correct_option_err; ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-group mt-4">
                    <button type="submit" class="btn btn-primary">Update Question</button>
                    <a href="manage-questions.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
include_once "../../includes/footer.php";
?>