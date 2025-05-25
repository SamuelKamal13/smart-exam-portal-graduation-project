<?php
// Set page title and base URL for includes
$page_title = "Create Exam";
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

// Define variables and initialize with empty values
$title = $description = $course_id = $duration = $total_marks = $start_time = "";
$title_err = $course_id_err = $duration_err = $total_marks_err = $start_time_err = "";

// Get courses for dropdown
$courses = [];
$sql = "SELECT id, title FROM courses WHERE trainer_id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $trainer_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $courses[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate title
    if (empty(trim($_POST["title"]))) {
        $title_err = "Please enter a title.";
    } else {
        $title = trim($_POST["title"]);
    }

    // Validate course
    if (empty(trim($_POST["course_id"]))) {
        $course_id_err = "Please select a course.";
    } else {
        $course_id = trim($_POST["course_id"]);
    }

    // Validate duration
    if (empty(trim($_POST["duration"]))) {
        $duration_err = "Please enter duration.";
    } elseif (!is_numeric(trim($_POST["duration"])) || trim($_POST["duration"]) <= 0) {
        $duration_err = "Please enter a valid duration.";
    } else {
        $duration = trim($_POST["duration"]);
    }

    // Validate total marks
    if (empty(trim($_POST["total_marks"]))) {
        $total_marks_err = "Please enter total marks.";
    } elseif (!is_numeric(trim($_POST["total_marks"])) || trim($_POST["total_marks"]) <= 0) {
        $total_marks_err = "Please enter valid total marks.";
    } else {
        $total_marks = trim($_POST["total_marks"]);
    }

    // Validate start time
    if (empty(trim($_POST["start_time"]))) {
        $start_time_err = "Please enter start time.";
    } else {
        $start_time = trim($_POST["start_time"]);
    }

    // Get description
    $description = trim($_POST["description"]);

    // Check input errors before inserting in database
    if (empty($title_err) && empty($course_id_err) && empty($duration_err) && empty($total_marks_err) && empty($start_time_err)) {

        // Prepare an insert statement
        $sql = "INSERT INTO exams (title, description, course_id, duration, total_marks, start_time, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "ssiissi", $param_title, $param_description, $param_course_id, $param_duration, $param_total_marks, $param_start_time, $param_created_by);

            // Set parameters
            $param_title = $title;
            $param_description = $description;
            $param_course_id = $course_id;
            $param_duration = $duration;
            $param_total_marks = $total_marks;
            $param_start_time = $start_time;
            $param_created_by = $trainer_id;

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                // Get the ID of the newly created exam
                $exam_id = mysqli_insert_id($conn);

                // Redirect to add questions page
                header("location: manage-questions.php?exam_id=" . $exam_id);
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
    <h1 class="mt-4 mb-4">Create New Exam</h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Exam Details</h6>
        </div>
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <label>Exam Title</label>
                    <input type="text" name="title" class="form-control <?php echo (!empty($title_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $title; ?>">
                    <span class="invalid-feedback"><?php echo $title_err; ?></span>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3"><?php echo $description; ?></textarea>
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
                    <?php if (count($courses) == 0): ?>
                        <small class="text-muted">No courses available. <a href="create-course.php">Create a course</a> first.</small>
                    <?php endif; ?>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Duration (minutes)</label>
                            <input type="number" name="duration" class="form-control <?php echo (!empty($duration_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $duration; ?>">
                            <span class="invalid-feedback"><?php echo $duration_err; ?></span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Total Marks</label>
                            <input type="number" name="total_marks" class="form-control <?php echo (!empty($total_marks_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $total_marks; ?>">
                            <span class="invalid-feedback"><?php echo $total_marks_err; ?></span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Start Date & Time</label>
                            <input type="datetime-local" name="start_time" class="form-control <?php echo (!empty($start_time_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $start_time; ?>">
                            <span class="invalid-feedback"><?php echo $start_time_err; ?></span>
                        </div>
                    </div>
                </div>

                <hr>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Create Exam</button>
                    <a href="manage-exams.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
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
                        <div class="step active">
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

<?php
// Include footer
include_once "../../includes/footer.php";
?>