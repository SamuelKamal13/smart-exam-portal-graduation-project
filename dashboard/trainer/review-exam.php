<?php
// Set page title and base URL for includes
$page_title = "Review Exam";
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

// Check if exam ID is provided
if (!isset($_GET["exam_id"]) || empty($_GET["exam_id"])) {
    header("location: index.php");
    exit;
}

$exam_id = $_GET["exam_id"];
$publish_success = false;
$publish_error = "";
$auto_fail_success = false;
$auto_fail_error = "";

// Get current date/time for comparison
$now = new DateTime();

// Fetch exam data and verify it belongs to this trainer
$exam = null;
$sql = "SELECT e.*, c.title AS course_title, c.id AS course_id 
        FROM exams e 
        JOIN courses c ON e.course_id = c.id 
        WHERE e.id = ? AND c.trainer_id = ?";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $exam_id, $trainer_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) == 1) {
            $exam = mysqli_fetch_assoc($result);

            // Calculate end time
            $start_time = new DateTime($exam["start_time"]);
            $end_time = clone $start_time;
            $end_time->add(new DateInterval('PT' . $exam["duration"] . 'M'));
        } else {
            // Exam not found or doesn't belong to this trainer
            header("location: index.php");
            exit;
        }
    } else {
        echo "Oops! Something went wrong. Please try again later.";
    }
    mysqli_stmt_close($stmt);
}

// Process auto-fail request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["auto_fail"])) {
    // Check if exam has ended
    if ($now < $end_time) {
        $auto_fail_error = "Cannot auto-fail students before the exam has ended.";
    } else {
        // Call the stored procedure to auto-fail absent students
        $auto_fail_sql = "CALL auto_fail_absent_students(?)";
        if ($auto_fail_stmt = mysqli_prepare($conn, $auto_fail_sql)) {
            mysqli_stmt_bind_param($auto_fail_stmt, "i", $exam_id);
            if (mysqli_stmt_execute($auto_fail_stmt)) {
                $auto_fail_success = true;
            } else {
                $auto_fail_error = "Failed to auto-fail absent students. Please try again.";
            }
            mysqli_stmt_close($auto_fail_stmt);
        }
    }
}

// Fetch questions for this exam
$questions = [];
$questions_sql = "SELECT * FROM questions WHERE exam_id = ? ORDER BY id";
if ($questions_stmt = mysqli_prepare($conn, $questions_sql)) {
    mysqli_stmt_bind_param($questions_stmt, "i", $exam_id);
    if (mysqli_stmt_execute($questions_stmt)) {
        $questions_result = mysqli_stmt_get_result($questions_stmt);
        while ($question = mysqli_fetch_assoc($questions_result)) {
            // Fetch options for MCQ and true/false questions
            if ($question['question_type'] == 'mcq' || $question['question_type'] == 'true_false') {
                $options = [];
                $options_sql = "SELECT * FROM question_options WHERE question_id = ? ORDER BY id";
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
            }
            $questions[] = $question;
        }
    }
    mysqli_stmt_close($questions_stmt);
}

// Fetch assigned students
$assigned_students = [];
$students_sql = "SELECT u.id, u.first_name, u.last_name, u.email
                FROM exam_students es
                JOIN users u ON es.student_id = u.id
                WHERE es.exam_id = ?
                ORDER BY u.last_name, u.first_name";
if ($students_stmt = mysqli_prepare($conn, $students_sql)) {
    mysqli_stmt_bind_param($students_stmt, "i", $exam_id);
    if (mysqli_stmt_execute($students_stmt)) {
        $students_result = mysqli_stmt_get_result($students_stmt);
        while ($student = mysqli_fetch_assoc($students_result)) {
            $assigned_students[] = $student;
        }
    }
    mysqli_stmt_close($students_stmt);
}

// Process publish request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["publish"])) {
    // Validate exam has questions
    if (count($questions) == 0) {
        $publish_error = "Cannot publish an exam with no questions. Please add questions first.";
    }
    // Validate exam has assigned students
    else if (count($assigned_students) == 0) {
        $publish_error = "Cannot publish an exam with no assigned students. Please assign students first.";
    } else {
        // Update exam status to published
        $publish_sql = "UPDATE exams SET status = 'published' WHERE id = ?";
        if ($publish_stmt = mysqli_prepare($conn, $publish_sql)) {
            mysqli_stmt_bind_param($publish_stmt, "i", $exam_id);
            if (mysqli_stmt_execute($publish_stmt)) {
                $publish_success = true;
            } else {
                $publish_error = "Failed to publish exam. Please try again.";
            }
            mysqli_stmt_close($publish_stmt);
        } else {
            $publish_error = "System error. Please try again later.";
        }
    }
}

// Include header
include_once "../../includes/header.php";
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Review Exam</h1>
        <a href="view-exam.php?id=<?php echo $exam_id; ?>" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left fa-sm"></i> Back to Exam
        </a>
    </div>

    <?php if ($publish_success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Exam has been successfully published! Students can now take this exam at the scheduled time.
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <?php if (!empty($publish_error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $publish_error; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <!-- Exam Details Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Exam Details</h6>
                    <a href="edit-exam.php?id=<?php echo $exam_id; ?>" class="btn btn-sm btn-info">
                        <i class="fas fa-edit fa-sm"></i> Edit Details
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <tr>
                                <th width="150">Exam Title</th>
                                <td><?php echo htmlspecialchars($exam["title"]); ?></td>
                            </tr>
                            <tr>
                                <th>Course</th>
                                <td><?php echo htmlspecialchars($exam["course_title"]); ?></td>
                            </tr>
                            <tr>
                                <th>Description</th>
                                <td><?php echo nl2br(htmlspecialchars($exam["description"])); ?></td>
                            </tr>
                            <tr>
                                <th>Start Time</th>
                                <td><?php echo date('F d, Y - h:i A', strtotime($exam["start_time"])); ?></td>
                            </tr>
                            <tr>
                                <th>Duration</th>
                                <td><?php echo $exam["duration"]; ?> minutes</td>
                            </tr>
                            <tr>
                                <th>Total Marks</th>
                                <td><?php echo $exam["total_marks"]; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Questions Summary Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Questions Summary</h6>
                    <a href="manage-questions.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-sm btn-info">
                        <i class="fas fa-edit fa-sm"></i> Manage Questions
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($questions)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> No questions have been added to this exam yet.
                            <a href="manage-questions.php?exam_id=<?php echo $exam_id; ?>" class="alert-link">Add questions now</a>.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Question</th>
                                        <th>Type</th>
                                        <th>Marks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($questions as $index => $question): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars(substr($question['question_text'], 0, 100)) . (strlen($question['question_text']) > 100 ? '...' : ''); ?></td>
                                            <td><?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?></td>
                                            <td><?php echo $question['marks']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="3" class="text-right">Total Marks:</th>
                                        <th><?php
                                            $total_marks = 0;
                                            foreach ($questions as $question) {
                                                $total_marks += $question['marks'];
                                            }
                                            echo $total_marks;
                                            ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Assigned Students Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Assigned Students</h6>
                    <a href="assign-students.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-sm btn-info">
                        <i class="fas fa-edit fa-sm"></i> Edit Assignments
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($assigned_students)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> No students have been assigned to this exam yet.
                            <a href="assign-students.php?exam_id=<?php echo $exam_id; ?>" class="alert-link">Assign students now</a>.
                        </div>
                    <?php else: ?>
                        <!-- Display assigned students -->
                        <div class="table-responsive">
                            <table class="table table-bordered" id="studentsTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assigned_students as $index => $student): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="2">Total: <?php echo count($assigned_students); ?> student(s)</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <?php if ($now > $end_time): ?>
                            <div class="mt-4">
                                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#autoFailModal">
                                    <i class="fas fa-user-times"></i> Auto-Fail Absent Students
                                </button>

                                <?php if ($auto_fail_success): ?>
                                    <div class="alert alert-success mt-3">
                                        <i class="fas fa-check-circle"></i> Successfully assigned failing grades to students who did not attempt the exam.
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($auto_fail_error)): ?>
                                    <div class="alert alert-danger mt-3">
                                        <i class="fas fa-exclamation-circle"></i> <?php echo $auto_fail_error; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Publish Exam Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Publish Exam</h6>
                </div>
                <div class="card-body">
                    <p>Before publishing, please ensure:</p>
                    <ul>
                        <li class="<?php echo !empty($questions) ? 'text-success' : 'text-danger'; ?>">
                            <i class="fas <?php echo !empty($questions) ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                            Exam has questions
                        </li>
                        <li class="<?php echo !empty($assigned_students) ? 'text-success' : 'text-danger'; ?>">
                            <i class="fas <?php echo !empty($assigned_students) ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                            Students are assigned
                        </li>
                        <li class="<?php echo strtotime($exam["start_time"]) > time() ? 'text-success' : 'text-warning'; ?>">
                            <i class="fas <?php echo strtotime($exam["start_time"]) > time() ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                            Start time is in the future
                            <?php if (strtotime($exam["start_time"]) <= time()): ?>
                                <small>(Exam will be immediately available)</small>
                            <?php endif; ?>
                        </li>
                    </ul>

                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?exam_id=" . $exam_id; ?>" class="mt-4">
                        <div class="form-group">
                            <button type="submit" name="publish" class="btn btn-success btn-block">
                                <i class="fas fa-check-circle"></i> Publish Exam
                            </button>
                        </div>
                        <div class="form-group">
                            <a href="view-exam.php?id=<?php echo $exam_id; ?>" class="btn btn-secondary btn-block">
                                <i class="fas fa-eye"></i> View Exam
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Exam Creation Process Stepper -->
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
                    <div class="step">
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
                    <div class="step active">
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

<style>
    .steps {
        display: flex;
        justify-content: space-between;
        margin: 20px 0;
        position: relative;
    }

    .steps:before {
        content: '';
        position: absolute;
        top: 20px;
        left: 0;
        right: 0;
        height: 2px;
        background: #e9ecef;
        z-index: 1;
    }

    .step {
        position: relative;
        z-index: 2;
        background: #fff;
        width: 22%;
        text-align: center;
        padding: 10px;
    }

    .step-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #e9ecef;
        color: #6c757d;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
        font-size: 18px;
    }

    .step.active .step-icon {
        background: #4e73df;
        color: #fff;
    }

    .step-text h5 {
        font-size: 16px;
        margin-bottom: 5px;
    }

    .step-text p {
        font-size: 12px;
        color: #6c757d;
        margin-bottom: 0;
    }
</style>

<!-- Auto-Fail Modal -->
<div class="modal fade" id="autoFailModal" tabindex="-1" aria-labelledby="autoFailModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="autoFailModalLabel">Confirm Auto-Fail Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to automatically fail all students who did not attempt this exam?</p>
                <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?exam_id=" . $exam_id; ?>" class="d-inline">
                    <button type="submit" name="auto_fail" class="btn btn-warning">
                        <i class="fas fa-user-times"></i> Auto-Fail Absent Students
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once "../../includes/footer.php";
