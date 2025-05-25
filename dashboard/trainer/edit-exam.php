<?php
// Set page title and base URL for includes
$page_title = "Edit Exam";
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
if (!isset($_GET["id"]) || empty($_GET["id"])) {
    header("location: manage-exams.php");
    exit;
}

$exam_id = $_GET["id"];

// Define variables and initialize with empty values
$title = $description = $course_id = $duration = $total_marks = $start_time = "";
$title_err = $course_id_err = $duration_err = $total_marks_err = $start_time_err = "";

// Fetch exam data
$sql = "SELECT * FROM exams WHERE id = ? AND created_by = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $exam_id, $trainer_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) == 1) {
            $exam = mysqli_fetch_assoc($result);
            $title = $exam["title"];
            $description = $exam["description"];
            $course_id = $exam["course_id"];
            $duration = $exam["duration"];
            $total_marks = $exam["total_marks"];
            $start_time = date('Y-m-d\TH:i', strtotime($exam["start_time"]));
        } else {
            // Exam not found or doesn't belong to this trainer
            header("location: manage-exams.php");
            exit;
        }
    } else {
        echo "Oops! Something went wrong. Please try again later.";
    }
    mysqli_stmt_close($stmt);
}

// Fetch courses created by the trainer
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

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate title
    if (empty(trim($_POST["title"]))) {
        $title_err = "Please enter an exam title.";
    } else {
        $title = trim($_POST["title"]);
    }

    // Validate course
    if (empty(trim($_POST["course_id"]))) {
        $course_id_err = "Please select a course.";
    } else {
        $course_id = trim($_POST["course_id"]);

        // Check if course belongs to the trainer
        $check_course_sql = "SELECT id FROM courses WHERE id = ? AND trainer_id = ?";
        if ($check_course_stmt = mysqli_prepare($conn, $check_course_sql)) {
            mysqli_stmt_bind_param($check_course_stmt, "ii", $course_id, $trainer_id);
            if (mysqli_stmt_execute($check_course_stmt)) {
                $check_course_result = mysqli_stmt_get_result($check_course_stmt);
                if (mysqli_num_rows($check_course_result) != 1) {
                    $course_id_err = "Invalid course selection.";
                }
            }
            mysqli_stmt_close($check_course_stmt);
        }
    }

    // Validate duration
    if (empty(trim($_POST["duration"]))) {
        $duration_err = "Please enter exam duration.";
    } elseif (!is_numeric(trim($_POST["duration"])) || intval(trim($_POST["duration"])) <= 0) {
        $duration_err = "Duration must be a positive number.";
    } else {
        $duration = intval(trim($_POST["duration"]));
    }

    // Validate total marks
    if (empty(trim($_POST["total_marks"]))) {
        $total_marks_err = "Please enter total marks.";
    } elseif (!is_numeric(trim($_POST["total_marks"])) || intval(trim($_POST["total_marks"])) <= 0) {
        $total_marks_err = "Total marks must be a positive number.";
    } else {
        $total_marks = intval(trim($_POST["total_marks"]));
    }

    // Validate start time
    if (empty(trim($_POST["start_time"]))) {
        $start_time_err = "Please enter start time.";
    } else {
        $start_time = trim($_POST["start_time"]);
    }

    // Get description
    $description = trim($_POST["description"]);

    // Check input errors before updating in database
    if (empty($title_err) && empty($course_id_err) && empty($duration_err) && empty($total_marks_err) && empty($start_time_err)) {

        // Determine exam status based on start time - REMOVED THIS LOGIC
        // $start_time_datetime = new DateTime($start_time);
        // $current_datetime = new DateTime();
        // $status = ($start_time_datetime > $current_datetime) ? 'published' : 'draft'; // REMOVED

        // Prepare an update statement - REMOVED status = ? from the query
        $sql = "UPDATE exams SET title = ?, description = ?, course_id = ?, duration = ?, total_marks = ?, start_time = ? WHERE id = ? AND created_by = ?";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            // Bind variables to the prepared statement as parameters - REMOVED the status parameter ('s') and variable
            mysqli_stmt_bind_param($stmt, "ssiiisii", $param_title, $param_description, $param_course_id, $param_duration, $param_total_marks, $param_start_time, $param_id, $param_trainer_id);

            // Set parameters
            $param_title = $title;
            $param_description = $description;
            $param_course_id = $course_id;
            $param_duration = $duration;
            $param_total_marks = $total_marks;
            $param_start_time = date('Y-m-d H:i:s', strtotime($start_time));
            // $param_status = $status; // REMOVED
            $param_id = $exam_id;
            $param_trainer_id = $trainer_id;

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                // Redirect to exam view page
                header("location: view-exam.php?id=" . $exam_id);
                exit();
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }
}

// Include header
include_once "../../includes/header.php";
?>

<div class="container-fluid">
    <h1 class="mt-4 mb-4">Edit Exam</h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Exam Details</h6>
        </div>
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?id=" . $exam_id; ?>" method="post">
                <div class="form-group">
                    <label>Exam Title</label>
                    <input type="text" name="title" class="form-control <?php echo (!empty($title_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $title; ?>" placeholder="Enter exam title">
                    <span class="invalid-feedback"><?php echo $title_err; ?></span>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="5" placeholder="Enter exam description"><?php echo $description; ?></textarea>
                    <small class="form-text text-muted">Provide instructions, scope, and other details about the exam.</small>
                </div>

                <div class="form-group">
                    <label>Course</label>
                    <select name="course_id" class="form-control <?php echo (!empty($course_id_err)) ? 'is-invalid' : ''; ?>">
                        <option value="">Select Course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>" <?php echo ($course_id == $course['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="invalid-feedback"><?php echo $course_id_err; ?></span>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Duration (minutes)</label>
                            <input type="number" name="duration" class="form-control <?php echo (!empty($duration_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $duration; ?>" min="1">
                            <span class="invalid-feedback"><?php echo $duration_err; ?></span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Total Marks</label>
                            <input type="number" name="total_marks" class="form-control <?php echo (!empty($total_marks_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $total_marks; ?>" min="1">
                            <span class="invalid-feedback"><?php echo $total_marks_err; ?></span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Start Time</label>
                            <input type="datetime-local" name="start_time" class="form-control <?php echo (!empty($start_time_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $start_time; ?>">
                            <span class="invalid-feedback"><?php echo $start_time_err; ?></span>
                        </div>
                    </div>
                </div>

                <div class="form-group mt-4">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="view-exam.php?id=<?php echo $exam_id; ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Manage Questions</h6>
            <a href="manage-questions.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-sm btn-primary">
                <i class="fas fa-question-circle fa-sm"></i> Manage Questions
            </a>
        </div>
        <div class="card-body">
            <p>You can add, edit, or remove questions for this exam from the question management page.</p>
            <a href="manage-questions.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-success">
                <i class="fas fa-question-circle"></i> Manage Questions
            </a>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-danger">Danger Zone</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <h5>Delete Exam</h5>
                    <p>Once you delete an exam, there is no going back. Please be certain.</p>
                </div>
                <div class="col-md-4 text-right">
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteExamModal">
                        Delete Exam
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Exam Modal -->
<div class="modal fade" id="deleteExamModal" tabindex="-1" aria-labelledby="deleteExamModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteExamModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this exam? This action cannot be undone.</p>
                <p><strong>Exam Title:</strong> <?php echo htmlspecialchars($title); ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="delete-exam.php?id=<?php echo $exam_id; ?>&confirm=yes" class="btn btn-danger">Delete Exam</a>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once "../../includes/footer.php";
?>