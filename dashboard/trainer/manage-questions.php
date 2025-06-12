<?php
// Set page title and base URL for includes
$page_title = "Manage Questions";
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

// Check if exam_id is provided
if (!isset($_GET["exam_id"]) || empty($_GET["exam_id"])) {
    header("location: manage-exams.php");
    exit;
}

$exam_id = $_GET["exam_id"];

// Verify that the exam belongs to the current trainer
$sql = "SELECT e.*, c.title as course_title FROM exams e 
        JOIN courses c ON e.course_id = c.id 
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
        $exam = mysqli_fetch_assoc($result);
    } else {
        echo "Oops! Something went wrong. Please try again later.";
        exit;
    }
    mysqli_stmt_close($stmt);
} else {
    echo "Oops! Something went wrong. Please try again later.";
    exit;
}

// Define variables and initialize with empty values
$question_text = $question_type = $marks = "";
$question_text_err = $question_type_err = $marks_err = "";
$options = ["", "", "", ""];
$options_err = ["", "", "", ""];
$correct_option = "";
$correct_option_err = "";
$success_message = "";
$error_message = "";

// Get existing questions for this exam
$questions = [];
$sql = "SELECT q.*, COUNT(qo.id) as option_count FROM questions q 
        LEFT JOIN question_options qo ON q.id = qo.question_id 
        WHERE q.exam_id = ? 
        GROUP BY q.id 
        ORDER BY q.id ASC";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $exam_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $questions[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (isset($_POST["add_question"])) {
        // Validate question text
        if (empty(trim($_POST["question_text"]))) {
            $question_text_err = "Please enter the question text.";
        } else {
            $question_text = trim($_POST["question_text"]);
        }

        // Validate question type
        if (empty($_POST["question_type"])) {
            $question_type_err = "Please select a question type.";
        } else {
            $question_type = $_POST["question_type"];
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
            if (!isset($_POST["correct_option"]) || $_POST["correct_option"] === "") {
                $correct_option_err = "Please select the correct option.";
            } else {
                $correct_option = $_POST["correct_option"];
            }
        } elseif ($question_type == "true_false") {
            $options[0] = "True";
            $options[1] = "False";

            // Validate correct option for true/false
            if (!isset($_POST["tf_correct_option"]) || $_POST["tf_correct_option"] === "") {
                $correct_option_err = "Please select the correct option.";
            } else {
                $correct_option = $_POST["tf_correct_option"];
            }
        }

        // Check input errors before inserting in database
        if (
            empty($question_text_err) && empty($question_type_err) && empty($marks_err) &&
            (($question_type == "mcq" && !$has_option_error && empty($correct_option_err)) ||
                ($question_type == "true_false" && empty($correct_option_err)))
        ) {

            // Begin transaction
            mysqli_begin_transaction($conn);

            try {
                // Prepare an insert statement for question
                $sql = "INSERT INTO questions (exam_id, question_text, question_type, marks) VALUES (?, ?, ?, ?)";

                if ($stmt = mysqli_prepare($conn, $sql)) {
                    // Bind variables to the prepared statement as parameters
                    mysqli_stmt_bind_param($stmt, "issi", $param_exam_id, $param_question_text, $param_question_type, $param_marks);

                    // Set parameters
                    $param_exam_id = $exam_id;
                    $param_question_text = $question_text;
                    $param_question_type = $question_type;
                    $param_marks = $marks;

                    // Attempt to execute the prepared statement
                    if (mysqli_stmt_execute($stmt)) {
                        $question_id = mysqli_insert_id($conn);

                        // Insert options
                        $option_count = ($question_type == "mcq") ? 4 : 2;

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

                        // Success message
                        $success_message = "Question added successfully!";

                        // Reset form values
                        $question_text = $question_type = $marks = "";
                        $options = ["", "", "", ""];
                        $correct_option = "";

                        // Refresh questions list
                        $questions = [];
                        $sql = "SELECT q.*, COUNT(qo.id) as option_count FROM questions q 
                                LEFT JOIN question_options qo ON q.id = qo.question_id 
                                WHERE q.exam_id = ? 
                                GROUP BY q.id 
                                ORDER BY q.id ASC";
                        if ($stmt_refresh = mysqli_prepare($conn, $sql)) {
                            mysqli_stmt_bind_param($stmt_refresh, "i", $exam_id);
                            if (mysqli_stmt_execute($stmt_refresh)) {
                                $result = mysqli_stmt_get_result($stmt_refresh);
                                while ($row = mysqli_fetch_assoc($result)) {
                                    $questions[] = $row;
                                }
                            }
                            mysqli_stmt_close($stmt_refresh);
                        }
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
                $error_message = "Error: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST["delete_question"])) {
        // Delete question
        $question_id = $_POST["question_id"];

        $sql = "DELETE FROM questions WHERE id = ? AND exam_id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "ii", $question_id, $exam_id);
            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Question deleted successfully!";

                // Refresh questions list
                $questions = [];
                $sql = "SELECT q.*, COUNT(qo.id) as option_count FROM questions q 
                        LEFT JOIN question_options qo ON q.id = qo.question_id 
                        WHERE q.exam_id = ? 
                        GROUP BY q.id 
                        ORDER BY q.id ASC";
                if ($stmt_refresh = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt_refresh, "i", $exam_id);
                    if (mysqli_stmt_execute($stmt_refresh)) {
                        $result = mysqli_stmt_get_result($stmt_refresh);
                        while ($row = mysqli_fetch_assoc($result)) {
                            $questions[] = $row;
                        }
                    }
                    mysqli_stmt_close($stmt_refresh);
                }
            } else {
                $error_message = "Error deleting question.";
            }
            mysqli_stmt_close($stmt);
        }    } elseif (isset($_POST["delete_questions"]) && isset($_POST["question_ids"])) {
        // Delete multiple questions
        $question_ids = $_POST["question_ids"];
        $success_count = 0;
        $error_count = 0;

        // Debug logging
        error_log("Multi-delete request received. Question IDs: " . json_encode($question_ids));
        error_log("Exam ID: " . $exam_id);

        // Validate that we have an array and it's not empty
        if (!is_array($question_ids) || empty($question_ids)) {
            $error_message = "No questions selected for deletion.";
            error_log("Multi-delete failed: No questions selected");
        } else {
            foreach ($question_ids as $question_id) {
                // Validate that the question ID is numeric
                if (!is_numeric($question_id)) {
                    $error_count++;
                    error_log("Multi-delete: Invalid question ID: " . $question_id);
                    continue;
                }
                
                $question_id = intval($question_id);
                
                $sql = "DELETE FROM questions WHERE id = ? AND exam_id = ?";
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "ii", $question_id, $exam_id);
                    if (mysqli_stmt_execute($stmt)) {
                        if (mysqli_affected_rows($conn) > 0) {
                            $success_count++;
                            error_log("Multi-delete: Successfully deleted question ID: " . $question_id);
                        } else {
                            $error_count++;
                            error_log("Multi-delete: Question not found or doesn't belong to exam: " . $question_id);
                        }
                    } else {
                        $error_count++;
                        error_log("Multi-delete: SQL execution failed for question ID: " . $question_id . " Error: " . mysqli_error($conn));
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error_count++;
                    error_log("Multi-delete: SQL preparation failed for question ID: " . $question_id . " Error: " . mysqli_error($conn));
                }
            }
        }

        // Display appropriate message
        if ($success_count > 0) {
            $success_message = "$success_count question(s) deleted successfully!";

            // Refresh questions list
            $questions = [];
            $sql = "SELECT q.*, COUNT(qo.id) as option_count FROM questions q 
                    LEFT JOIN question_options qo ON q.id = qo.question_id 
                    WHERE q.exam_id = ? 
                    GROUP BY q.id 
                    ORDER BY q.id ASC";
            if ($stmt_refresh = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt_refresh, "i", $exam_id);
                if (mysqli_stmt_execute($stmt_refresh)) {
                    $result = mysqli_stmt_get_result($stmt_refresh);
                    while ($row = mysqli_fetch_assoc($result)) {
                        $questions[] = $row;
                    }
                }
                mysqli_stmt_close($stmt_refresh);
            }
        }

        if ($error_count > 0) {
            $error_message = "$error_count question(s) could not be deleted.";
        }
    } elseif (isset($_POST["finish_exam_setup"])) {
        // Check if there are any questions
        if (count($questions) > 0) {
            // Redirect to assign students page
            header("location: assign-students.php?exam_id=" . $exam_id);
            exit();
        } else {
            $error_message = "Please add at least one question before proceeding.";
        }
    } elseif (isset($_POST["add_from_bank"]) && isset($_POST["bank_questions"])) {
        // Process form submission for adding questions from bank
        $selected_questions = $_POST["bank_questions"];
        $success_count = 0;
        $error_count = 0;

        foreach ($selected_questions as $question_id) {
            // Begin transaction
            mysqli_begin_transaction($conn);

            try {
                // Get the original question details
                $sql = "SELECT * FROM questions WHERE id = ?";
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    mysqli_stmt_bind_param($stmt, "i", $question_id);
                    if (mysqli_stmt_execute($stmt)) {
                        $result = mysqli_stmt_get_result($stmt);
                        if ($row = mysqli_fetch_assoc($result)) {
                            $original_question = $row;

                            // Insert the question for the current exam
                            $sql = "INSERT INTO questions (exam_id, question_text, question_type, marks) 
                                    VALUES (?, ?, ?, ?)";

                            if ($stmt_insert = mysqli_prepare($conn, $sql)) {
                                mysqli_stmt_bind_param(
                                    $stmt_insert,
                                    "issi",
                                    $exam_id,
                                    $original_question['question_text'],
                                    $original_question['question_type'],
                                    $original_question['marks']
                                );

                                if (mysqli_stmt_execute($stmt_insert)) {
                                    $new_question_id = mysqli_insert_id($conn);

                                    // Get the original options
                                    $sql = "SELECT * FROM question_options WHERE question_id = ?";
                                    if ($stmt_options = mysqli_prepare($conn, $sql)) {
                                        mysqli_stmt_bind_param($stmt_options, "i", $question_id);
                                        if (mysqli_stmt_execute($stmt_options)) {
                                            $options_result = mysqli_stmt_get_result($stmt_options);

                                            // Insert each option for the new question
                                            while ($option = mysqli_fetch_assoc($options_result)) {
                                                $sql = "INSERT INTO question_options (question_id, option_text, is_correct) 
                                                        VALUES (?, ?, ?)";

                                                if ($stmt_insert_option = mysqli_prepare($conn, $sql)) {
                                                    mysqli_stmt_bind_param(
                                                        $stmt_insert_option,
                                                        "isi",
                                                        $new_question_id,
                                                        $option['option_text'],
                                                        $option['is_correct']
                                                    );

                                                    if (!mysqli_stmt_execute($stmt_insert_option)) {
                                                        throw new Exception("Error inserting option: " . mysqli_stmt_error($stmt_insert_option));
                                                    }

                                                    mysqli_stmt_close($stmt_insert_option);
                                                } else {
                                                    throw new Exception("Error preparing option insert statement");
                                                }
                                            }

                                            // Commit transaction
                                            mysqli_commit($conn);
                                            $success_count++;
                                        } else {
                                            throw new Exception("Error getting options: " . mysqli_stmt_error($stmt_options));
                                        }
                                        mysqli_stmt_close($stmt_options);
                                    } else {
                                        throw new Exception("Error preparing options statement");
                                    }
                                } else {
                                    throw new Exception("Error inserting question: " . mysqli_stmt_error($stmt_insert));
                                }
                                mysqli_stmt_close($stmt_insert);
                            } else {
                                throw new Exception("Error preparing question insert statement");
                            }
                        } else {
                            throw new Exception("Question not found");
                        }
                    } else {
                        throw new Exception("Error executing question query: " . mysqli_stmt_error($stmt));
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    throw new Exception("Error preparing question query");
                }
            } catch (Exception $e) {
                // Rollback transaction on error
                mysqli_rollback($conn);
                $error_count++;
            }
        }

        // Display appropriate message
        if ($success_count > 0) {
            $success_message = "$success_count question(s) added successfully from the question bank!";

            // Refresh questions list
            $questions = [];
            $sql = "SELECT q.*, COUNT(qo.id) as option_count FROM questions q 
                    LEFT JOIN question_options qo ON q.id = qo.question_id 
                    WHERE q.exam_id = ? 
                    GROUP BY q.id 
                    ORDER BY q.id ASC";
            if ($stmt_refresh = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt_refresh, "i", $exam_id);
                if (mysqli_stmt_execute($stmt_refresh)) {
                    $result = mysqli_stmt_get_result($stmt_refresh);
                    while ($row = mysqli_fetch_assoc($result)) {
                        $questions[] = $row;
                    }
                }
                mysqli_stmt_close($stmt_refresh);
            }
        }

        if ($error_count > 0) {
            $error_message = "$error_count question(s) could not be added.";
        }
    }
}

// Include header
include_once "../../includes/header.php";
?>

<div class="container-fluid">
    <h1 class="mt-4 mb-4">Manage Questions for "<?php echo htmlspecialchars($exam['title']); ?>"</h1>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Exam Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Exam Title:</strong> <?php echo htmlspecialchars($exam['title']); ?></p>
                            <p><strong>Course:</strong> <?php echo htmlspecialchars($exam['course_title']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Duration:</strong> <?php echo $exam['duration']; ?> minutes</p>
                            <p><strong>Total Marks:</strong> <?php echo $exam['total_marks']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Add New Question</h6>
                </div>
                <div class="card-body">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?exam_id=" . $exam_id); ?>" method="post" id="questionForm">
                        <div class="form-group">
                            <label>Question Text</label>
                            <textarea name="question_text" class="form-control <?php echo (!empty($question_text_err)) ? 'is-invalid' : ''; ?>" rows="3"><?php echo $question_text; ?></textarea>
                            <span class="invalid-feedback"><?php echo $question_text_err; ?></span>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Question Type</label>
                                    <select name="question_type" id="questionType" class="form-control <?php echo (!empty($question_type_err)) ? 'is-invalid' : ''; ?>">
                                        <option value="">Select Type</option>
                                        <option value="mcq" <?php echo ($question_type == "mcq") ? 'selected' : ''; ?>>Multiple Choice (MCQ)</option>
                                        <option value="true_false" <?php echo ($question_type == "true_false") ? 'selected' : ''; ?>>True/False</option>
                                    </select>
                                    <span class="invalid-feedback"><?php echo $question_type_err; ?></span>
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

                        <!-- MCQ Options -->
                        <div id="mcqOptions" style="display: <?php echo ($question_type == "mcq") ? 'block' : 'none'; ?>">
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

                        <!-- True/False Options -->
                        <div id="trueFalseOptions" style="display: <?php echo ($question_type == "true_false") ? 'block' : 'none'; ?>">
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

                        <div class="form-group mt-4">
                            <button type="submit" name="add_question" class="btn btn-primary">Add Question</button>
                        </div>
                    </form>
                </div>
            </div>            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Add Questions from Question Bank</h6>
                </div>
                <div class="card-body">
                    <?php
                    // Get questions from question bank (questions created by this trainer for other exams)
                    $bank_questions = [];
                    $sql = "SELECT q.id, q.question_text, q.question_type, q.marks, 
                            GROUP_CONCAT(DISTINCT e.title ORDER BY e.id DESC SEPARATOR ', ') as exam_titles
                            FROM questions q 
                            JOIN exams e ON q.exam_id = e.id 
                            WHERE e.created_by = ? AND q.exam_id != ? 
                            GROUP BY q.question_text, q.question_type
                            ORDER BY q.id DESC";

                    if ($stmt = mysqli_prepare($conn, $sql)) {
                        mysqli_stmt_bind_param($stmt, "ii", $trainer_id, $exam_id);
                        if (mysqli_stmt_execute($stmt)) {
                            $result = mysqli_stmt_get_result($stmt);
                            while ($row = mysqli_fetch_assoc($result)) {
                                $bank_questions[] = $row;
                            }
                        }
                        mysqli_stmt_close($stmt);
                    }
                    ?>

                    <?php if (count($bank_questions) > 0): ?>
                        <!-- Search and Filter Controls -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="questionSearch">Search Questions:</label>
                                    <input type="text" id="questionSearch" class="form-control" placeholder="Search by question text or exam title...">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="typeFilter">Filter by Type:</label>
                                    <select id="typeFilter" class="form-control">
                                        <option value="">All Types</option>
                                        <option value="mcq">Multiple Choice</option>
                                        <option value="true_false">True/False</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="marksFilter">Filter by Marks:</label>
                                    <select id="marksFilter" class="form-control">
                                        <option value="">All Marks</option>
                                        <?php
                                        $unique_marks = array_unique(array_column($bank_questions, 'marks'));
                                        sort($unique_marks);
                                        foreach ($unique_marks as $marks): ?>
                                            <option value="<?php echo $marks; ?>"><?php echo $marks; ?> mark(s)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?exam_id=" . $exam_id); ?>" method="post">
                            <!-- Scrollable table container with fixed height -->
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto; border: 1px solid #dee2e6;">
                                <table class="table table-bordered table-hover mt-0 mb-0" width="100%" cellspacing="0" id="questionBankTable">
                                    <thead class="thead-light" style="position: sticky; top: 0; z-index: 10;">
                                        <tr>
                                            <th width="5%"><input type="checkbox" id="selectAll"></th>
                                            <th width="50%">Question</th>
                                            <th width="15%">Type</th>
                                            <th width="10%">Marks</th>
                                            <th width="20%">Source Exam</th>
                                        </tr>
                                    </thead>
                                    <tbody id="questionBankTableBody">
                                        <?php foreach ($bank_questions as $question): ?>
                                            <tr class="question-row" 
                                                data-question-text="<?php echo strtolower(htmlspecialchars($question['question_text'])); ?>"
                                                data-question-type="<?php echo $question['question_type']; ?>"
                                                data-question-marks="<?php echo $question['marks']; ?>"
                                                data-exam-title="<?php echo strtolower(htmlspecialchars($question['exam_titles'])); ?>">
                                                <td><input type="checkbox" name="bank_questions[]" value="<?php echo $question['id']; ?>" class="question-checkbox"></td>
                                                <td><?php echo htmlspecialchars($question['question_text']); ?></td>
                                                <td>
                                                    <?php if ($question['question_type'] == 'mcq'): ?>
                                                        <span class="badge text-info">Multiple Choice</span>
                                                    <?php elseif ($question['question_type'] == 'true_false'): ?>
                                                        <span class="badge text-success">True/False</span>
                                                    <?php else: ?>
                                                        <span class="badge text-secondary"><?php echo ucfirst($question['question_type']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $question['marks']; ?></td>
                                                <td>
                                                    <?php
                                                    $exam_titles = $question['exam_titles'];
                                                    if (strlen($exam_titles) > 50) {
                                                        echo '<span title="' . htmlspecialchars($exam_titles) . '">' . substr($exam_titles, 0, 47) . '...</span>';
                                                    } else {
                                                        echo $exam_titles;
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Results info and action button -->
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <small class="text-muted" id="resultsInfo">
                                    Showing <span id="visibleCount"><?php echo count($bank_questions); ?></span> of <?php echo count($bank_questions); ?> questions
                                </small>
                                <button type="submit" name="add_from_bank" class="btn btn-primary" id="addFromBankBtn" disabled>
                                    <i class="fas fa-plus-circle"></i> Add Selected Questions (<span id="selectedCount">0</span>)
                                </button>
                            </div>
                        </form>

                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const questionSearch = document.getElementById('questionSearch');
                                const typeFilter = document.getElementById('typeFilter');
                                const marksFilter = document.getElementById('marksFilter');
                                const questionRows = document.querySelectorAll('.question-row');
                                const selectAllCheckbox = document.getElementById('selectAll');
                                const addFromBankBtn = document.getElementById('addFromBankBtn');
                                const visibleCountSpan = document.getElementById('visibleCount');
                                const selectedCountSpan = document.getElementById('selectedCount');
                                const totalQuestions = <?php echo count($bank_questions); ?>;

                                // Filter and search functionality
                                function filterQuestions() {
                                    const searchTerm = questionSearch.value.toLowerCase();
                                    const typeFilterValue = typeFilter.value;
                                    const marksFilterValue = marksFilter.value;
                                    
                                    let visibleCount = 0;
                                    
                                    questionRows.forEach(row => {
                                        const questionText = row.dataset.questionText;
                                        const examTitle = row.dataset.examTitle;
                                        const questionType = row.dataset.questionType;
                                        const questionMarks = row.dataset.questionMarks;
                                        
                                        const matchesSearch = searchTerm === '' || 
                                            questionText.includes(searchTerm) || 
                                            examTitle.includes(searchTerm);
                                        
                                        const matchesType = typeFilterValue === '' || questionType === typeFilterValue;
                                        const matchesMarks = marksFilterValue === '' || questionMarks === marksFilterValue;
                                        
                                        if (matchesSearch && matchesType && matchesMarks) {
                                            row.style.display = '';
                                            visibleCount++;
                                        } else {
                                            row.style.display = 'none';
                                            // Uncheck hidden rows
                                            const checkbox = row.querySelector('input[type="checkbox"]');
                                            if (checkbox.checked) {
                                                checkbox.checked = false;
                                                updateSelectedCount();
                                            }
                                        }
                                    });
                                    
                                    visibleCountSpan.textContent = visibleCount;
                                    
                                    // Update select all checkbox state
                                    updateSelectAllState();
                                }

                                // Update selected count and button state
                                function updateSelectedCount() {
                                    const checkedBoxes = document.querySelectorAll('.question-checkbox:checked');
                                    const count = checkedBoxes.length;
                                    selectedCountSpan.textContent = count;
                                    addFromBankBtn.disabled = count === 0;
                                }

                                // Update select all checkbox state
                                function updateSelectAllState() {
                                    const visibleCheckboxes = [];
                                    questionRows.forEach(row => {
                                        if (row.style.display !== 'none') {
                                            visibleCheckboxes.push(row.querySelector('.question-checkbox'));
                                        }
                                    });
                                    
                                    const checkedVisible = visibleCheckboxes.filter(cb => cb.checked).length;
                                    
                                    selectAllCheckbox.indeterminate = checkedVisible > 0 && checkedVisible < visibleCheckboxes.length;
                                    selectAllCheckbox.checked = visibleCheckboxes.length > 0 && checkedVisible === visibleCheckboxes.length;
                                }

                                // Event listeners
                                questionSearch.addEventListener('input', filterQuestions);
                                typeFilter.addEventListener('change', filterQuestions);
                                marksFilter.addEventListener('change', filterQuestions);

                                // Select all functionality (only for visible rows)
                                selectAllCheckbox.addEventListener('change', function() {
                                    const visibleCheckboxes = [];
                                    questionRows.forEach(row => {
                                        if (row.style.display !== 'none') {
                                            visibleCheckboxes.push(row.querySelector('.question-checkbox'));
                                        }
                                    });
                                    
                                    visibleCheckboxes.forEach(checkbox => {
                                        checkbox.checked = this.checked;
                                    });
                                    
                                    updateSelectedCount();
                                });

                                // Individual checkbox change
                                document.querySelectorAll('.question-checkbox').forEach(checkbox => {
                                    checkbox.addEventListener('change', function() {
                                        updateSelectedCount();
                                        updateSelectAllState();
                                    });
                                });

                                // Initialize counts
                                updateSelectedCount();
                            });
                        </script>
                    <?php else: ?>
                        <div class="alert alert-info">
                            No questions available in your question bank. Questions you create for other exams will appear here.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Generate Questions with AI</h6>
                </div>
                <div class="card-body">
                    <p>Upload PDF materials and let AI generate exam questions for you.</p>

                    <form id="aiQuestionForm" enctype="multipart/form-data">
                        <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">

                        <div class="form-group">
                            <label for="pdfFiles" class="form-label">Upload PDF Files</label>
                            <div class="input-group mb-3" style="width: fit-content">
                                <input type="file" class="form-control" id="pdfFiles" name="pdfFiles[]" accept=".pdf" multiple required>
                                <label class="input-group-text" for="pdfFiles">Browse</label>
                            </div>
                            <small class="form-text text-muted">You can upload multiple PDF files (max 10MB each)</small>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="questionCount">Number of Questions</label>
                                <input type="number" class="form-control" id="questionCount" name="questionCount" min="1" max="20" value="5" required>
                            </div>

                            <div class="form-group col-md-4">
                                <label for="questionType">Question Type</label>
                                <select class="form-control" id="questionType" name="questionType" required>
                                    <option value="mcq">Multiple Choice</option>
                                    <option value="true_false">True/False</option>
                                    <option value="both">Both Types</option>
                                </select>
                            </div>

                            <div class="form-group col-md-4">
                                <label for="marksPerQuestion">Marks per Question</label>
                                <input type="number" class="form-control" id="marksPerQuestion" name="marksPerQuestion" min="1" max="10" value="1" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <button type="button" id="generateQuestionsBtn" class="btn btn-primary">
                                <i class="fas fa-robot"></i> Generate Questions
                            </button>
                        </div>
                    </form>

                    <div id="generationProgress" class="mt-3" style="display: none;">
                        <div class="progress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                        </div>
                        <p class="text-center mt-2">Generating questions... This may take a minute.</p>
                    </div>

                    <div id="generatedQuestions" class="mt-3" style="display: none;">
                        <h5>Generated Questions</h5>
                        <div id="questionsContainer" class="border p-3 bg-light">
                            <!-- Generated questions will appear here -->
                        </div>
                        <div class="mt-3">
                            <button type="button" id="addGeneratedQuestionsBtn" class="btn btn-success">
                                <i class="fas fa-plus-circle"></i> Add Selected Questions to Exam
                            </button>
                        </div>
                    </div>

                    <div id="generationError" class="alert alert-danger mt-3" style="display: none;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">                
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Questions List</h6>
                    <span class="badge text-primary"><?php echo count($questions); ?> Total Questions</span>
                </div><div class="card-body">
                    <?php if (count($questions) > 0): ?>
                        <!-- Search and Filter Controls for Questions List -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="questionsSearch">Search Questions:</label>
                                    <input type="text" id="questionsSearch" class="form-control" placeholder="Search by question text...">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="questionsTypeFilter">Filter by Type:</label>
                                    <select id="questionsTypeFilter" class="form-control">
                                        <option value="">All Types</option>
                                        <option value="mcq">Multiple Choice</option>
                                        <option value="true_false">True/False</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="questionsMarksFilter">Filter by Marks:</label>
                                    <select id="questionsMarksFilter" class="form-control">
                                        <option value="">All Marks</option>
                                        <?php
                                        $unique_marks = array_unique(array_column($questions, 'marks'));
                                        sort($unique_marks);
                                        foreach ($unique_marks as $marks): ?>
                                            <option value="<?php echo $marks; ?>"><?php echo $marks; ?> mark(s)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Scrollable table container with fixed height -->
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto; border: 1px solid #dee2e6;">
                            <table class="table table-bordered table-hover mt-0 mb-0" width="100%" cellspacing="0" id="questionsListTable">
                                <thead class="thead-light" style="position: sticky; top: 0; z-index: 10;">
                                    <tr>
                                        <th width="5%"><input type="checkbox" id="selectAllQuestions"></th>
                                        <th width="5%">#</th>
                                        <th width="45%">Question</th>
                                        <th width="15%">Type</th>
                                        <th width="10%">Marks</th>
                                        <th width="10%">Options</th>
                                        <th width="10%">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="questionsListTableBody">
                                    <?php foreach ($questions as $index => $question): ?>
                                        <tr class="questions-list-row" 
                                            data-question-text="<?php echo strtolower(htmlspecialchars($question['question_text'])); ?>"
                                            data-question-type="<?php echo $question['question_type']; ?>"
                                            data-question-marks="<?php echo $question['marks']; ?>"
                                            data-question-number="<?php echo $index + 1; ?>">
                                            <td><input type="checkbox" class="questions-list-checkbox" value="<?php echo $question['id']; ?>"></td>
                                            <td class="question-number"><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($question['question_text']); ?></td>
                                            <td>
                                                <?php if ($question['question_type'] == 'mcq'): ?>
                                                    <span class="badge text-info">Multiple Choice</span>
                                                <?php elseif ($question['question_type'] == 'true_false'): ?>
                                                    <span class="badge text-success">True/False</span>
                                                <?php else: ?>
                                                    <span class="badge text-secondary"><?php echo ucfirst($question['question_type']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $question['marks']; ?></td>
                                            <td><?php echo $question['option_count']; ?></td>
                                            <td>
                                                <button type="button" class="btn btn-danger btn-sm delete-single-btn" data-question-id="<?php echo $question['id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <a href="edit-question.php?id=<?php echo $question['id']; ?>&exam_id=<?php echo $exam_id; ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Results info and action buttons -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <small class="text-muted" id="questionsResultsInfo">
                                Showing <span id="questionsVisibleCount"><?php echo count($questions); ?></span> of <?php echo count($questions); ?> questions
                            </small>
                            <div>
                                <button id="deleteSelectedBtn" class="btn btn-danger btn-sm mr-2" disabled>
                                    <i class="fas fa-trash"></i> Delete Selected (<span id="questionsSelectedCount">0</span>)
                                </button>
                                <span class="badge text-info"><?php echo count($questions); ?> Total Questions</span>
                            </div>
                        </div>

                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const questionsSearch = document.getElementById('questionsSearch');
                                const questionsTypeFilter = document.getElementById('questionsTypeFilter');
                                const questionsMarksFilter = document.getElementById('questionsMarksFilter');
                                const questionsRows = document.querySelectorAll('.questions-list-row');
                                const selectAllQuestionsCheckbox = document.getElementById('selectAllQuestions');
                                const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
                                const questionsVisibleCountSpan = document.getElementById('questionsVisibleCount');
                                const questionsSelectedCountSpan = document.getElementById('questionsSelectedCount');
                                const totalQuestions = <?php echo count($questions); ?>;

                                // Filter and search functionality for questions list
                                function filterQuestionsList() {
                                    const searchTerm = questionsSearch.value.toLowerCase();
                                    const typeFilterValue = questionsTypeFilter.value;
                                    const marksFilterValue = questionsMarksFilter.value;
                                    
                                    let visibleCount = 0;
                                    let questionNumber = 1;
                                    
                                    questionsRows.forEach(row => {
                                        const questionText = row.dataset.questionText;
                                        const questionType = row.dataset.questionType;
                                        const questionMarks = row.dataset.questionMarks;
                                        
                                        const matchesSearch = searchTerm === '' || questionText.includes(searchTerm);
                                        const matchesType = typeFilterValue === '' || questionType === typeFilterValue;
                                        const matchesMarks = marksFilterValue === '' || questionMarks === marksFilterValue;
                                        
                                        if (matchesSearch && matchesType && matchesMarks) {
                                            row.style.display = '';
                                            // Update question numbers for visible rows
                                            const numberCell = row.querySelector('.question-number');
                                            if (numberCell) {
                                                numberCell.textContent = questionNumber;
                                            }
                                            questionNumber++;
                                            visibleCount++;
                                        } else {
                                            row.style.display = 'none';
                                            // Uncheck hidden rows
                                            const checkbox = row.querySelector('input[type="checkbox"]');
                                            if (checkbox.checked) {
                                                checkbox.checked = false;
                                                updateQuestionsSelectedCount();
                                            }
                                        }
                                    });
                                    
                                    questionsVisibleCountSpan.textContent = visibleCount;
                                    
                                    // Update select all checkbox state
                                    updateQuestionsSelectAllState();
                                }

                                // Update selected count and button state for questions list
                                function updateQuestionsSelectedCount() {
                                    const checkedBoxes = document.querySelectorAll('.questions-list-checkbox:checked');
                                    const count = checkedBoxes.length;
                                    questionsSelectedCountSpan.textContent = count;
                                    deleteSelectedBtn.disabled = count === 0;
                                }

                                // Update select all checkbox state for questions list
                                function updateQuestionsSelectAllState() {
                                    const visibleCheckboxes = [];
                                    questionsRows.forEach(row => {
                                        if (row.style.display !== 'none') {
                                            visibleCheckboxes.push(row.querySelector('.questions-list-checkbox'));
                                        }
                                    });
                                    
                                    const checkedVisible = visibleCheckboxes.filter(cb => cb.checked).length;
                                    
                                    selectAllQuestionsCheckbox.indeterminate = checkedVisible > 0 && checkedVisible < visibleCheckboxes.length;
                                    selectAllQuestionsCheckbox.checked = visibleCheckboxes.length > 0 && checkedVisible === visibleCheckboxes.length;
                                }

                                // Event listeners for questions list
                                questionsSearch.addEventListener('input', filterQuestionsList);
                                questionsTypeFilter.addEventListener('change', filterQuestionsList);
                                questionsMarksFilter.addEventListener('change', filterQuestionsList);

                                // Select all functionality for questions list (only for visible rows)
                                selectAllQuestionsCheckbox.addEventListener('change', function() {
                                    const visibleCheckboxes = [];
                                    questionsRows.forEach(row => {
                                        if (row.style.display !== 'none') {
                                            visibleCheckboxes.push(row.querySelector('.questions-list-checkbox'));
                                        }
                                    });
                                    
                                    visibleCheckboxes.forEach(checkbox => {
                                        checkbox.checked = this.checked;
                                    });
                                    
                                    updateQuestionsSelectedCount();
                                });                                // Individual checkbox change for questions list
                                document.querySelectorAll('.questions-list-checkbox').forEach(checkbox => {
                                    checkbox.addEventListener('change', function() {
                                        updateQuestionsSelectedCount();
                                        updateQuestionsSelectAllState();
                                    });
                                });

                                // Initialize counts for questions list
                                updateQuestionsSelectedCount();
                            });
                        </script>
                    <?php else: ?>
                        <div class="alert alert-info">
                            No questions added yet. Use the form above to add questions to this exam.
                        </div>
                    <?php endif; ?>

                    <div class="mt-3">
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?exam_id=" . $exam_id); ?>" method="post">
                            <button type="submit" name="finish_exam_setup" class="btn btn-success">
                                <i class="fas fa-check-circle"></i> Proceed to Assign Students
                            </button>
                            <a href="manage-exams.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Exams
                            </a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Exam Creation Process</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-12">
                    <div class="steps">
                        <div class="step">
                            <div class="step-icon">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <div class="step-text">
                                <h5>Step 1: Basic Information</h5>
                                <p>Enter exam details like title, description, and schedule.</p>
                            </div>
                        </div>
                        <div class="step active">
                            <div class="step-icon">
                                <i class="fas fa-question-circle"></i>
                            </div>
                            <div class="step-text">
                                <h5>Step 2: Add Questions</h5>
                                <p>Create questions for your exam.</p>
                            </div>
                        </div>
                        <div class="step">
                            <div class="step-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="step-text">
                                <h5>Step 3: Assign Students</h5>
                                <p>Select students who will take this exam.</p>
                            </div>
                        </div>
                        <div class="step">
                            <div class="step-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="step-text">
                                <h5>Step 4: Review & Publish</h5>
                                <p>Review all details and publish the exam.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add this script at the end of the file -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const generateQuestionsBtn = document.getElementById('generateQuestionsBtn');
        const generationProgress = document.getElementById('generationProgress');
        const generatedQuestions = document.getElementById('generatedQuestions');
        const questionsContainer = document.getElementById('questionsContainer');
        const generationError = document.getElementById('generationError');
        const addGeneratedQuestionsBtn = document.getElementById('addGeneratedQuestionsBtn');

        generateQuestionsBtn.addEventListener('click', function() {
            const form = document.getElementById('aiQuestionForm');
            const formData = new FormData(form);

            // Validate file input
            const fileInput = document.getElementById('pdfFiles');
            if (fileInput.files.length === 0) {
                alert('Please select at least one PDF file');
                return;
            }

            // Show progress indicator
            generationProgress.style.display = 'block';
            generatedQuestions.style.display = 'none';
            generationError.style.display = 'none';
            generateQuestionsBtn.disabled = true;

            // Process each PDF file
            processFiles(fileInput.files, formData);
        });

        async function processFiles(files, formData) {
            try {
                const questionCount = formData.get('questionCount');
                const questionType = formData.get('questionType');
                const marksPerQuestion = formData.get('marksPerQuestion');
                const examId = formData.get('exam_id');

                // Create an array to hold file data
                const fileContents = [];

                // Process each file
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    const base64Data = await readFileAsBase64(file);
                    fileContents.push({
                        name: file.name,
                        data: base64Data,
                        mimeType: file.type
                    });
                }

                // Prepare the request data
                const requestData = {
                    apiKey: 'AIzaSyBbByOdKHQW6lTS9R0zjmNTU9BXnmVUKvQ',
                    files: fileContents,
                    questionCount: parseInt(questionCount),
                    questionType: questionType,
                    marksPerQuestion: parseInt(marksPerQuestion),
                    examId: examId
                };

                // Send the request to the server-side handler
                fetch('generate-questions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(requestData)
                    })
                    .then(response => response.json())
                    .then(data => {
                        // Hide progress indicator
                        generationProgress.style.display = 'none';
                        generateQuestionsBtn.disabled = false;

                        if (data.error) {
                            // Show error message
                            generationError.textContent = data.error;
                            generationError.style.display = 'block';
                        } else if (data.questions && data.questions.length > 0) {
                            // Display generated questions
                            displayGeneratedQuestions(data.questions);
                            generatedQuestions.style.display = 'block';
                        } else {
                            generationError.textContent = 'No questions were generated. Please try again with different PDF files.';
                            generationError.style.display = 'block';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        generationProgress.style.display = 'none';
                        generateQuestionsBtn.disabled = false;
                        generationError.textContent = 'An error occurred while generating questions. Please try again.';
                        generationError.style.display = 'block';
                    });

            } catch (error) {
                console.error('Error processing files:', error);
                generationProgress.style.display = 'none';
                generateQuestionsBtn.disabled = false;
                generationError.textContent = 'An error occurred while processing files. Please try again.';
                generationError.style.display = 'block';
            }
        }

        function readFileAsBase64(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => {
                    // Extract the base64 data from the result
                    const base64String = reader.result.split(',')[1];
                    resolve(base64String);
                };
                reader.onerror = reject;
                reader.readAsDataURL(file);
            });
        }

        function displayGeneratedQuestions(questions) {
            questionsContainer.innerHTML = '';

            questions.forEach((question, index) => {
                const questionDiv = document.createElement('div');
                questionDiv.className = 'card mb-3';

                let optionsHtml = '';
                if (question.options && question.options.length > 0) {
                    optionsHtml = '<div class="mt-2"><strong>Options:</strong><ul>';
                    question.options.forEach((option, optIndex) => {
                        const isCorrect = optIndex === question.correctOption;
                        optionsHtml += `<li>${option} ${isCorrect ? '<span class="text-success">(Correct)</span>' : ''}</li>`;
                    });
                    optionsHtml += '</ul></div>';
                }

                questionDiv.innerHTML = `
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <input type="checkbox" id="selectQuestion${index}" class="question-select" checked>
                            <label for="selectQuestion${index}" class="mb-0 ml-2">Question ${index + 1}</label>
                        </div>
                        <span class="badge text-info">${question.type === 'mcq' ? 'Multiple Choice' : 'True/False'}</span>
                    </div>
                    <div class="card-body">
                        <p>${question.text}</p>
                        ${optionsHtml}
                        <input type="hidden" class="question-data" value='${JSON.stringify(question)}'>
                    </div>
                `;

                questionsContainer.appendChild(questionDiv);
            });
        }

        addGeneratedQuestionsBtn.addEventListener('click', function() {
            const selectedQuestions = [];
            const checkboxes = document.querySelectorAll('.question-select');

            checkboxes.forEach((checkbox, index) => {
                if (checkbox.checked) {
                    const questionDataInput = document.querySelectorAll('.question-data')[index];
                    const questionData = JSON.parse(questionDataInput.value);
                    selectedQuestions.push(questionData);
                }
            });

            if (selectedQuestions.length === 0) {
                alert('Please select at least one question to add');
                return;
            }

            // Show progress indicator
            generationProgress.style.display = 'block';
            addGeneratedQuestionsBtn.disabled = true;

            // Send selected questions to the server to add to the exam
            const examId = document.querySelector('input[name="exam_id"]').value;

            fetch('add-generated-questions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        examId: examId,
                        questions: selectedQuestions
                    })
                })
                .then(response => response.json())
                .then(data => {
                    generationProgress.style.display = 'none';
                    addGeneratedQuestionsBtn.disabled = false;

                    if (data.success) {
                        window.location.reload();
                    } else {
                        generationError.textContent = data.error || 'Failed to add questions to the exam.';
                        generationError.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    generationProgress.style.display = 'none';
                    addGeneratedQuestionsBtn.disabled = false;
                    generationError.textContent = 'An error occurred while adding questions. Please try again.';
                    generationError.style.display = 'block';
                });
        });
    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const questionType = document.getElementById('questionType');
        const mcqOptions = document.getElementById('mcqOptions');
        const trueFalseOptions = document.getElementById('trueFalseOptions');

        questionType.addEventListener('change', function() {
            if (this.value === 'mcq') {
                mcqOptions.style.display = 'block';
                trueFalseOptions.style.display = 'none';
            } else if (this.value === 'true_false') {
                mcqOptions.style.display = 'none';
                trueFalseOptions.style.display = 'block';
            } else {
                mcqOptions.style.display = 'none';
                trueFalseOptions.style.display = 'none';
            }
        });
    });
</script>

<!-- Delete confirmation modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" role="dialog" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteConfirmModalLabel"><i class="fas fa-trash-alt"></i> Confirm Deletion</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fas fa-exclamation-triangle confirmation-icon text-danger" style="font-size: 3rem;"></i>
                </div>
                <p class="text-center">Are you sure you want to delete the selected question(s)?</p>
                <p id="deleteCountMessage" class="text-center font-weight-bold"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-times"></i> Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn"><i class="fas fa-trash-alt"></i> Delete</button>
            </div>
        </div>
    </div>
</div>

<script>    document.addEventListener('DOMContentLoaded', function() {
        // Handle select all questions checkbox
        const selectAllQuestions = document.getElementById('selectAllQuestions');
        const questionCheckboxes = document.querySelectorAll('.questions-list-checkbox');
        const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');

        if (selectAllQuestions) {
            selectAllQuestions.addEventListener('change', function() {
                questionCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateDeleteButtonState();
            });
        }

        // Handle individual question checkboxes
        questionCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateDeleteButtonState();

                // Update "select all" checkbox state
                if (selectAllQuestions) {
                    const allChecked = Array.from(questionCheckboxes).every(cb => cb.checked);
                    const someChecked = Array.from(questionCheckboxes).some(cb => cb.checked);

                    selectAllQuestions.checked = allChecked;
                    selectAllQuestions.indeterminate = someChecked && !allChecked;
                }
            });
        });

        // Update delete button state based on selections
        function updateDeleteButtonState() {
            const selectedCount = Array.from(questionCheckboxes).filter(cb => cb.checked).length;
            deleteSelectedBtn.disabled = selectedCount === 0;
        }        // Handle delete selected button
        if (deleteSelectedBtn) {
            deleteSelectedBtn.addEventListener('click', function() {
                const selectedIds = Array.from(questionCheckboxes)
                    .filter(cb => cb.checked)
                    .map(cb => cb.value);

                if (selectedIds.length > 0) {
                    // Update modal message
                    document.getElementById('deleteCountMessage').textContent =
                        `You are about to delete ${selectedIds.length} question(s).`;

                    // Show the modal
                    $('#deleteConfirmModal').modal('show');

                    // Set up the confirm delete button
                    document.getElementById('confirmDeleteBtn').onclick = function() {
                        // Create a form to submit the delete request
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?exam_id=" . $exam_id); ?>';

                        // Add question IDs
                        selectedIds.forEach(id => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'question_ids[]';
                            input.value = id;
                            form.appendChild(input);
                        });

                        // Add delete action
                        const actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'delete_questions';
                        actionInput.value = '1';
                        form.appendChild(actionInput);

                        // Submit the form
                        document.body.appendChild(form);
                        form.submit();
                    };
                }
            });
        }

        // Handle single delete buttons
        const singleDeleteButtons = document.querySelectorAll('.delete-single-btn');
        singleDeleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const questionId = this.getAttribute('data-question-id');

                // Update modal message
                document.getElementById('deleteCountMessage').textContent =
                    'You are about to delete 1 question.';

                // Show the modal
                $('#deleteConfirmModal').modal('show');

                // Set up the confirm delete button
                document.getElementById('confirmDeleteBtn').onclick = function() {
                    // Create a form to submit the delete request
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?exam_id=" . $exam_id); ?>';

                    // Add question ID
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'question_id';
                    input.value = questionId;
                    form.appendChild(input);

                    // Add delete action
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'delete_question';
                    actionInput.value = '1';
                    form.appendChild(actionInput);

                    // Submit the form
                    document.body.appendChild(form);
                    form.submit();
                };
            });
        });
    });
</script>

<?php
// Include footer
include_once "../../includes/footer.php";
?>