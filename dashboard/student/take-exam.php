<?php
// Set page title and base URL for includes
$page_title = "Take Exam";
$base_url = "../..";

// Include config file
require_once "../../config.php";

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../../auth/login.php");
    exit;
}

// Check if user is a student
if ($_SESSION["role"] !== "student") {
    // Redirect to appropriate dashboard based on role
    if ($_SESSION["role"] == "trainer") {
        header("location: ../trainer/index.php");
    } elseif ($_SESSION["role"] == "supervisor") {
        header("location: ../supervisor/index.php");
    }
    exit;
}

// Get student ID
$student_id = $_SESSION["id"];

// Check if exam ID is provided
if (!isset($_GET["id"]) || empty($_GET["id"])) {
    header("location: exams.php");
    exit;
}

$exam_id = $_GET["id"];

// Fetch exam data and verify student is assigned to this exam
$exam = null;
$sql = "SELECT e.*, c.title as course_title 
        FROM exams e 
        JOIN exam_students es ON e.id = es.exam_id 
        LEFT JOIN courses c ON e.course_id = c.id 
        WHERE e.id = ? AND es.student_id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $exam_id, $student_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) == 1) {
            $exam = mysqli_fetch_assoc($result);
        } else {
            // Exam not found or student not assigned
            header("location: exams.php");
            exit;
        }
    } else {
        echo "Oops! Something went wrong. Please try again later.";
    }
    mysqli_stmt_close($stmt);
}

// Check if student has already taken this exam
$has_taken_exam = false;
$check_sql = "SELECT id FROM results WHERE exam_id = ? AND student_id = ?";
if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
    mysqli_stmt_bind_param($check_stmt, "ii", $exam_id, $student_id);
    if (mysqli_stmt_execute($check_stmt)) {
        $check_result = mysqli_stmt_get_result($check_stmt);
        if (mysqli_num_rows($check_result) > 0) {
            $has_taken_exam = true;
        }
    }
    mysqli_stmt_close($check_stmt);
}

if ($has_taken_exam) {
    // Redirect to view exam page with a message
    header("location: view-exam.php?id=" . $exam_id . "&error=already_taken");
    exit;
}

// Calculate exam status
$now = new DateTime();
$start_time = new DateTime($exam["start_time"]);
$end_time = clone $start_time;
$end_time->add(new DateInterval('PT' . $exam["duration"] . 'M'));

if ($now < $start_time) {
    // Exam hasn't started yet
    header("location: view-exam.php?id=" . $exam_id . "&error=not_started");
    exit;
} elseif ($now > $end_time) {
    // Exam has ended
    header("location: view-exam.php?id=" . $exam_id . "&error=ended");
    exit;
}

// Create or get exam attempt
$attempt_id = null;
$attempt_sql = "SELECT id FROM exam_attempts WHERE exam_id = ? AND student_id = ? AND status = 'in_progress'";
if ($attempt_stmt = mysqli_prepare($conn, $attempt_sql)) {
    mysqli_stmt_bind_param($attempt_stmt, "ii", $exam_id, $student_id);
    if (mysqli_stmt_execute($attempt_stmt)) {
        $attempt_result = mysqli_stmt_get_result($attempt_stmt);
        if (mysqli_num_rows($attempt_result) > 0) {
            $attempt_row = mysqli_fetch_assoc($attempt_result);
            $attempt_id = $attempt_row['id'];
        }
    }
    mysqli_stmt_close($attempt_stmt);
}

// Update the has_viewed flag for this student
$update_viewed_sql = "UPDATE exam_students SET has_viewed = TRUE WHERE exam_id = ? AND student_id = ?";
if ($update_viewed_stmt = mysqli_prepare($conn, $update_viewed_sql)) {
    mysqli_stmt_bind_param($update_viewed_stmt, "ii", $exam_id, $student_id);
    mysqli_stmt_execute($update_viewed_stmt);
    mysqli_stmt_close($update_viewed_stmt);
}

// If no attempt exists, create one
if ($attempt_id === null) {
    $create_attempt_sql = "INSERT INTO exam_attempts (exam_id, student_id, start_time, status) VALUES (?, ?, NOW(), 'in_progress')";
    if ($create_stmt = mysqli_prepare($conn, $create_attempt_sql)) {
        mysqli_stmt_bind_param($create_stmt, "ii", $exam_id, $student_id);
        if (mysqli_stmt_execute($create_stmt)) {
            $attempt_id = mysqli_insert_id($conn);

            // Update the has_attempted flag for this student
            $update_attempted_sql = "UPDATE exam_students SET has_attempted = TRUE WHERE exam_id = ? AND student_id = ?";
            if ($update_attempted_stmt = mysqli_prepare($conn, $update_attempted_sql)) {
                mysqli_stmt_bind_param($update_attempted_stmt, "ii", $exam_id, $student_id);
                mysqli_stmt_execute($update_attempted_stmt);
                mysqli_stmt_close($update_attempted_stmt);
            }
        } else {
            // Error creating attempt
            header("location: view-exam.php?id=" . $exam_id . "&error=system");
            exit;
        }
        mysqli_stmt_close($create_stmt);
    }
}

// Fetch questions for this exam
$questions = [];
$questions_sql = "SELECT q.*, COUNT(qo.id) as option_count 
                 FROM questions q 
                 LEFT JOIN question_options qo ON q.id = qo.question_id 
                 WHERE q.exam_id = ? 
                 GROUP BY q.id 
                 ORDER BY q.id ASC";
if ($questions_stmt = mysqli_prepare($conn, $questions_sql)) {
    mysqli_stmt_bind_param($questions_stmt, "i", $exam_id);
    if (mysqli_stmt_execute($questions_stmt)) {
        $questions_result = mysqli_stmt_get_result($questions_stmt);
        while ($question = mysqli_fetch_assoc($questions_result)) {
            // Fetch options for this question
            $options = [];
            $options_sql = "SELECT * FROM question_options WHERE question_id = ? ORDER BY id ASC";
            if ($options_stmt = mysqli_prepare($conn, $options_sql)) {
                mysqli_stmt_bind_param($options_stmt, "i", $question['id']);
                if (mysqli_stmt_execute($options_stmt)) {
                    $options_result = mysqli_stmt_get_result($options_stmt);
                    while ($option = mysqli_fetch_assoc($options_result)) {
                        $options[] = $option;
                    }
                }
                mysqli_stmt_close($options_stmt);
            }

            $question['options'] = $options;
            $questions[] = $question;
        }
    }
    mysqli_stmt_close($questions_stmt);
}

// Calculate remaining time in seconds
$remaining_time = $end_time->getTimestamp() - $now->getTimestamp();

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["submit_exam"])) {
    // Validate the attempt ID
    if (isset($_POST["attempt_id"]) && $_POST["attempt_id"] == $attempt_id) {
        // Process each question
        $total_score = 0;

        foreach ($questions as $question) {
            $question_id = $question['id'];
            $is_correct = false;
            $points_earned = 0;
            $selected_option_id = null;

            // Get student's answer
            if (isset($_POST["question_" . $question_id])) {
                $selected_option_id = $_POST["question_" . $question_id];

                // Check if answer is correct
                if ($question['question_type'] == 'mcq' || $question['question_type'] == 'true_false') {
                    // For MCQ and True/False, check if selected option is correct
                    $check_answer_sql = "SELECT is_correct FROM question_options WHERE id = ? AND question_id = ?";
                    if ($check_answer_stmt = mysqli_prepare($conn, $check_answer_sql)) {
                        mysqli_stmt_bind_param($check_answer_stmt, "ii", $selected_option_id, $question_id);
                        if (mysqli_stmt_execute($check_answer_stmt)) {
                            $check_answer_result = mysqli_stmt_get_result($check_answer_stmt);
                            if ($check_answer_row = mysqli_fetch_assoc($check_answer_result)) {
                                $is_correct = (bool)$check_answer_row['is_correct'];
                                if ($is_correct) {
                                    $points_earned = $question['marks'];
                                    $total_score += $points_earned;
                                }
                            }
                        }
                        mysqli_stmt_close($check_answer_stmt);
                    }
                }

                // Save student's answer
                $save_answer_sql = "INSERT INTO student_answers (attempt_id, question_id, option_id, is_correct, points_earned) 
                                   VALUES (?, ?, ?, ?, ?)";
                if ($save_answer_stmt = mysqli_prepare($conn, $save_answer_sql)) {
                    mysqli_stmt_bind_param($save_answer_stmt, "iiiis", $attempt_id, $question_id, $selected_option_id, $is_correct, $points_earned);
                    mysqli_stmt_execute($save_answer_stmt);
                    mysqli_stmt_close($save_answer_stmt);
                }
            }
        }

        // Update attempt status
        $update_attempt_sql = "UPDATE exam_attempts SET end_time = NOW(), score = ?, status = 'completed' WHERE id = ?";
        if ($update_attempt_stmt = mysqli_prepare($conn, $update_attempt_sql)) {
            mysqli_stmt_bind_param($update_attempt_stmt, "ii", $total_score, $attempt_id);
            mysqli_stmt_execute($update_attempt_stmt);
            mysqli_stmt_close($update_attempt_stmt);
        }

        // Calculate percentage
        $percentage = ($total_score / $exam['total_marks']) * 100;

        // Save result
        $save_result_sql = "INSERT INTO results (exam_id, student_id, score, total_marks, percentage) 
                           VALUES (?, ?, ?, ?, ?)";
        if ($save_result_stmt = mysqli_prepare($conn, $save_result_sql)) {
            mysqli_stmt_bind_param($save_result_stmt, "iiiid", $exam_id, $student_id, $total_score, $exam['total_marks'], $percentage);
            if (mysqli_stmt_execute($save_result_stmt)) {
                $result_id = mysqli_insert_id($conn);
                // Redirect to result page
                header("location: view-result.php?id=" . $result_id);
                exit;
            }
            mysqli_stmt_close($save_result_stmt);
        }
    }
}

// Function to handle exam abandonment via AJAX
if (isset($_POST['action']) && $_POST['action'] == 'abandon_exam') {
    if (isset($_POST['attempt_id']) && $_POST['attempt_id'] == $attempt_id) {
        // Process any answers that were submitted
        $total_score = 0;

        foreach ($questions as $question) {
            $question_id = $question['id'];
            $is_correct = false;
            $points_earned = 0;
            $selected_option_id = null;

            // Get student's answer if provided
            if (isset($_POST["question_" . $question_id])) {
                $selected_option_id = $_POST["question_" . $question_id];

                // Check if answer is correct
                if ($question['question_type'] == 'mcq' || $question['question_type'] == 'true_false') {
                    $check_answer_sql = "SELECT is_correct FROM question_options WHERE id = ? AND question_id = ?";
                    if ($check_answer_stmt = mysqli_prepare($conn, $check_answer_sql)) {
                        mysqli_stmt_bind_param($check_answer_stmt, "ii", $selected_option_id, $question_id);
                        if (mysqli_stmt_execute($check_answer_stmt)) {
                            $check_answer_result = mysqli_stmt_get_result($check_answer_stmt);
                            if ($check_answer_row = mysqli_fetch_assoc($check_answer_result)) {
                                $is_correct = (bool)$check_answer_row['is_correct'];
                                if ($is_correct) {
                                    $points_earned = $question['marks'];
                                    $total_score += $points_earned;
                                }
                            }
                        }
                        mysqli_stmt_close($check_answer_stmt);
                    }
                }

                // Save student's answer
                $save_answer_sql = "INSERT INTO student_answers (attempt_id, question_id, option_id, is_correct, points_earned) 
                                   VALUES (?, ?, ?, ?, ?)";
                if ($save_answer_stmt = mysqli_prepare($conn, $save_answer_sql)) {
                    mysqli_stmt_bind_param($save_answer_stmt, "iiiid", $attempt_id, $question_id, $selected_option_id, $is_correct, $points_earned);
                    mysqli_stmt_execute($save_answer_stmt);
                    mysqli_stmt_close($save_answer_stmt);
                }
            }
        }

        // Update attempt status to abandoned
        $abandon_sql = "UPDATE exam_attempts SET end_time = NOW(), score = ?, status = 'abandoned' WHERE id = ?";
        if ($abandon_stmt = mysqli_prepare($conn, $abandon_sql)) {
            mysqli_stmt_bind_param($abandon_stmt, "ii", $total_score, $attempt_id);
            if (mysqli_stmt_execute($abandon_stmt)) {
                // Calculate percentage
                $percentage = ($total_score / $exam['total_marks']) * 100;

                // Save result
                $save_result_sql = "INSERT INTO results (exam_id, student_id, score, total_marks, percentage, submission_time) 
                                   VALUES (?, ?, ?, ?, ?, NOW())";
                if ($save_result_stmt = mysqli_prepare($conn, $save_result_sql)) {
                    mysqli_stmt_bind_param($save_result_stmt, "iiiid", $exam_id, $student_id, $total_score, $exam['total_marks'], $percentage);
                    mysqli_stmt_execute($save_result_stmt);
                    mysqli_stmt_close($save_result_stmt);
                }

                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    // This is an AJAX request
                    echo json_encode(['status' => 'success']);
                } else {
                    // This is a regular form submission
                    header("location: view-exam.php?id=" . $exam_id . "&error=abandoned");
                }
            } else {
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to update attempt status']);
                } else {
                    header("location: view-exam.php?id=" . $exam_id . "&error=system");
                }
            }
            mysqli_stmt_close($abandon_stmt);
        }

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            exit;
        } else {
            exit;
        }
    }
}

// Include header (with special parameters to prevent navigation)
$no_navigation = true;
$no_sidebar = true;
$fullscreen_mode = true;
include_once "../../includes/header.php";
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo htmlspecialchars($exam["title"]); ?></h6>
                    <div class="d-flex align-items-center">
                        <div class="mr-3">
                            <span class="font-weight-bold">Time Remaining:</span>
                            <span id="timer" class="text-danger font-weight-bold"></span>
                        </div>
                        <button type="button" id="submit-btn" class="btn btn-primary" onclick="confirmSubmit()">
                            <i class="fas fa-paper-plane"></i> Submit Exam
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <form id="exam-form" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?id=" . $exam_id; ?>">
                        <input type="hidden" name="attempt_id" value="<?php echo $attempt_id; ?>">

                        <?php if (count($questions) > 0): ?>
                            <?php foreach ($questions as $index => $question): ?>
                                <div class="card mb-4 question-card" id="question-<?php echo $question['id']; ?>">
                                    <div class="card-header">
                                        <h6 class="m-0 font-weight-bold">
                                            Question <?php echo $index + 1; ?>
                                            <span class="text-muted">(<?php echo $question['marks']; ?> mark<?php echo $question['marks'] > 1 ? 's' : ''; ?>)</span>
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-3"><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>

                                        <?php if ($question['question_type'] == 'mcq' || $question['question_type'] == 'true_false'): ?>
                                            <div class="options-list">
                                                <?php foreach ($question['options'] as $option): ?>
                                                    <div class="custom-control custom-radio mb-2">
                                                        <input type="radio" id="option-<?php echo $option['id']; ?>"
                                                            name="question_<?php echo $question['id']; ?>"
                                                            value="<?php echo $option['id']; ?>"
                                                            class="custom-control-input">
                                                        <label class="custom-control-label" for="option-<?php echo $option['id']; ?>">
                                                            <?php echo htmlspecialchars($option['option_text']); ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="text-center mt-4">
                                <button type="button" class="btn btn-success btn-lg" onclick="confirmSubmit()">
                                    <i class="fas fa-check-circle"></i> Submit Exam
                                </button>
                                <input type="hidden" name="submit_exam" value="1">
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> No questions found for this exam.
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="submitConfirmModal" tabindex="-1" role="dialog" aria-labelledby="submitConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="submitConfirmModalLabel"><i class="fas fa-paper-plane"></i> Confirm Submission</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fas fa-question-circle confirmation-icon text-primary"></i>
                </div>
                <p class="text-center">Are you sure you want to submit your exam?</p>
                <div id="unanswered-warning" class="alert alert-warning d-none">
                    <i class="fas fa-exclamation-triangle"></i> You have unanswered questions. Are you sure you want to proceed?
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" id="cancelSubmitBtn"><i class="fas fa-times"></i> Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmSubmitBtn"><i class="fas fa-check"></i> Yes, submit exam</button>
            </div>
        </div>
    </div>
</div>

<!-- Warning Modal -->
<div class="modal fade" id="warningModal" tabindex="-1" role="dialog" aria-labelledby="warningModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="warningModalLabel"><i class="fas fa-exclamation-triangle"></i> Warning!</h5>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fas fa-exclamation-circle confirmation-icon text-danger"></i>
                </div>
                <p class="text-center">You are attempting to leave the exam page.</p>
                <p class="text-center">This action will result in automatic submission of your exam.</p>
                <div class="text-center mt-4">
                    <button type="button" class="btn btn-danger" onclick="abandonExam()"><i class="fas fa-check"></i> I Understand</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Time's Up Modal -->
<div class="modal fade" id="timeUpModal" tabindex="-1" role="dialog" aria-labelledby="timeUpModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="timeUpModalLabel"><i class="fas fa-clock"></i> Time's Up!</h5>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fas fa-hourglass-end confirmation-icon text-warning"></i>
                </div>
                <p class="text-center">Your exam time has expired. Please submit your answers now.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="timeUpSubmitBtn"><i class="fas fa-paper-plane"></i> Submit Now</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Set initial time
    var timeRemaining = <?php echo $remaining_time; ?>;
    var timerInterval;
    var documentHidden = false;
    var attemptId = <?php echo $attempt_id; ?>;
    var examId = <?php echo $exam_id; ?>;
    var visibilityChangeCount = 0;
    var maxVisibilityChanges = 3;

    // Submit the exam
    function submitExam() {
        // Disable the submit button to prevent multiple submissions
        $('.btn-primary').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Submitting...');

        // Remove the beforeunload event listener
        window.removeEventListener('beforeunload', beforeUnloadHandler);

        // Add a hidden field to indicate form submission
        document.getElementById("exam-form").submit_exam.value = "1";

        // Submit the form
        document.getElementById("exam-form").submit();
    }

    // Update timer display
    function updateTimer() {
        var hours = Math.floor(timeRemaining / 3600);
        var minutes = Math.floor((timeRemaining % 3600) / 60);
        var seconds = timeRemaining % 60;

        // Format time as HH:MM:SS
        var formattedTime =
            (hours < 10 ? "0" + hours : hours) + ":" +
            (minutes < 10 ? "0" + minutes : minutes) + ":" +
            (seconds < 10 ? "0" + seconds : seconds);

        document.getElementById("timer").textContent = formattedTime;

        // Decrease time
        timeRemaining--;

        // If time is up, show the modal and stop the timer
        if (timeRemaining < 0) {
            clearInterval(timerInterval);
            document.getElementById("timer").textContent = "00:00:00";

            // Save modal state to localStorage
            localStorage.setItem('timeUpModalShown_' + attemptId, 'true');

            // Show time's up modal
            $('#timeUpModal').modal({
                backdrop: 'static',
                keyboard: false
            });
            $('#timeUpModal').modal('show');
        }
    }

    // Start the timer
    timerInterval = setInterval(updateTimer, 1000);
    updateTimer(); // Initial call to display time immediately

    // Confirm submission
    function confirmSubmit() {
        // Check for unanswered questions
        var totalQuestions = <?php echo count($questions); ?>;
        var answeredQuestions = document.querySelectorAll('input[type="radio"]:checked').length;

        if (answeredQuestions < totalQuestions) {
            document.getElementById('unanswered-warning').classList.remove('d-none');
        } else {
            document.getElementById('unanswered-warning').classList.add('d-none');
        }

        $('#submitConfirmModal').modal('show');
    }

    // Abandon the exam
    function abandonExam() {
        // First try to collect all answers that have been selected
        var formData = new FormData(document.getElementById('exam-form'));
        formData.append('action', 'abandon_exam');
        formData.append('attempt_id', attemptId);

        // Clear localStorage when abandoning exam
        localStorage.removeItem('timeUpModalShown_' + attemptId);
        localStorage.removeItem('warningModalShown_' + attemptId);

        // Disable the button to prevent multiple clicks
        $('.btn-danger').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

        // Remove the beforeunload event listener to prevent the "Changes you made may not be saved" alert
        window.removeEventListener('beforeunload', beforeUnloadHandler);

        // Send AJAX request to save answers and wait for response before redirecting
        $.ajax({
            url: 'take-exam.php?id=' + examId,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                // Redirect after saving is complete
                window.location.href = 'view-exam.php?id=' + examId + '&error=abandoned';
            },
            error: function() {
                // Even if there's an error, still redirect but after a short delay to give server time to process
                setTimeout(function() {
                    window.location.href = 'view-exam.php?id=' + examId + '&error=abandoned';
                }, 1000);
            }
        });

        // Prevent further execution
        return false;
    }

    // Store the beforeunload handler as a named function so we can remove it
    function beforeUnloadHandler(e) {
        e.preventDefault();
        e.returnValue = '';
        return '';
    }

    // Remove the directSubmit function as it's no longer needed

    // Detect tab/window switching
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            documentHidden = true;
            visibilityChangeCount++;

            if (visibilityChangeCount >= maxVisibilityChanges) {
                // Auto-submit after multiple visibility changes
                abandonExam();
            } else {
                // Save modal state to localStorage
                localStorage.setItem('warningModalShown_' + attemptId, 'true');

                $('#warningModal').modal({
                    backdrop: 'static',
                    keyboard: false
                });
                $('#warningModal').modal('show');
            }
        } else {
            documentHidden = false;
        }
    });

    // Prevent right-click
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
        return false;
    });

    // Prevent keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Prevent Ctrl+C, Ctrl+V, Ctrl+X, Ctrl+P, Alt+Tab, F12
        if (
            (e.ctrlKey && (e.keyCode === 67 || e.keyCode === 86 || e.keyCode === 88 || e.keyCode === 80)) ||
            (e.altKey && e.keyCode === 9) ||
            e.keyCode === 123
        ) {
            e.preventDefault();
            return false;
        }
    });

    // Prevent copy and paste
    document.addEventListener('copy', function(e) {
        e.preventDefault();
        return false;
    });

    document.addEventListener('paste', function(e) {
        e.preventDefault();
        return false;
    });

    document.addEventListener('cut', function(e) {
        e.preventDefault();
        return false;
    });

    // Prevent browser back button
    history.pushState(null, null, location.href);
    window.onpopstate = function() {
        history.go(1);
    };

    // Detect fullscreen exit
    document.addEventListener('fullscreenchange', function() {
        if (!document.fullscreenElement) {
            visibilityChangeCount++;
            if (visibilityChangeCount >= maxVisibilityChanges) {
                abandonExam();
            } else {
                // Save modal state to localStorage
                localStorage.setItem('warningModalShown_' + attemptId, 'true');

                $('#warningModal').modal({
                    backdrop: 'static',
                    keyboard: false
                });
                $('#warningModal').modal('show');
            }
        }
    });

    // Request fullscreen on page load
    document.addEventListener('DOMContentLoaded', function() {
        var elem = document.documentElement;
        if (elem.requestFullscreen) {
            elem.requestFullscreen();
        } else if (elem.mozRequestFullScreen) {
            /* Firefox */
            elem.mozRequestFullScreen();
        } else if (elem.webkitRequestFullscreen) {
            /* Chrome, Safari & Opera */
            elem.webkitRequestFullscreen();
        } else if (elem.msRequestFullscreen) {
            /* IE/Edge */
            elem.msRequestFullscreen();
        }
    });

    // Detect window resize (potential tab switching)
    window.addEventListener('resize', function() {
        if (documentHidden) {
            visibilityChangeCount++;
            if (visibilityChangeCount >= maxVisibilityChanges) {
                abandonExam();
            }
        }
    });
</script>

<?php
// Include footer
include_once "../../includes/footer.php";
?>

<script>
    // Initialize timer
    var timeRemaining = <?php echo $remaining_time; ?>;
    var timerInterval;

    function updateTimer() {
        var hours = Math.floor(timeRemaining / 3600);
        var minutes = Math.floor((timeRemaining % 3600) / 60);
        var seconds = timeRemaining % 60;

        // Format time as HH:MM:SS
        var formattedTime =
            (hours < 10 ? "0" + hours : hours) + ":" +
            (minutes < 10 ? "0" + minutes : minutes) + ":" +
            (seconds < 10 ? "0" + seconds : seconds);

        document.getElementById("timer").textContent = formattedTime;

        if (timeRemaining <= 0) {
            clearInterval(timerInterval);
            // Save modal state to localStorage
            localStorage.setItem('timeUpModalShown_' + attemptId, 'true');

            // Show time up modal
            $("#timeUpModal").modal({
                backdrop: 'static',
                keyboard: false
            });
            $("#timeUpModal").modal('show');
        } else {
            timeRemaining--;
        }
    }

    $(document).ready(function() {
        updateTimer();
        timerInterval = setInterval(updateTimer, 1000);

        // Check localStorage for modal states
        if (localStorage.getItem('warningModalShown_' + attemptId) === 'true') {
            $('#warningModal').modal({
                backdrop: 'static',
                keyboard: false
            });
            $('#warningModal').modal('show');
        }

        if (localStorage.getItem('timeUpModalShown_' + attemptId) === 'true') {
            $('#timeUpModal').modal({
                backdrop: 'static',
                keyboard: false
            });
            $('#timeUpModal').modal('show');
        }

        // Submit Now button in Time Up modal
        $("#timeUpSubmitBtn").on("click", function() {
            // Clear localStorage when submitting
            localStorage.removeItem('timeUpModalShown_' + attemptId);
            localStorage.removeItem('warningModalShown_' + attemptId);

            window.onbeforeunload = null;
            $(window).off('beforeunload');
            $("#exam-form").submit();
        });

        // Remove beforeunload and submit form on Confirm modal button
        $("#submitExamBtn").on("click", function() {
            // Clear localStorage when submitting
            localStorage.removeItem('timeUpModalShown_' + attemptId);
            localStorage.removeItem('warningModalShown_' + attemptId);

            window.onbeforeunload = null;
            $(window).off('beforeunload');
            $("#exam-form").submit();
        });
    });
</script>

<script>
    // Show the confirmation modal when submit is requested
    function confirmSubmit() {
        $('#submitConfirmModal').modal('show');
    }

    // Cancel button closes the modal
    document.getElementById('cancelSubmitBtn').onclick = function() {
        $('#submitConfirmModal').modal('hide');
    };

    // Confirm button submits the form
    document.getElementById('confirmSubmitBtn').onclick = function() {
        document.getElementById('exam-form').submit();
    };
</script>