<?php
// Set page title and base URL for includes
$page_title = "Assign Students to Exam";
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

// Fetch exam data and verify it belongs to this trainer
$exam = null;
$course_id = null;
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
            $course_id = $exam["course_id"];
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

// Fetch students already assigned to this exam
$assigned_students = [];
$assigned_sql = "SELECT student_id FROM exam_students WHERE exam_id = ?";
if ($assigned_stmt = mysqli_prepare($conn, $assigned_sql)) {
    mysqli_stmt_bind_param($assigned_stmt, "i", $exam_id);
    if (mysqli_stmt_execute($assigned_stmt)) {
        $assigned_result = mysqli_stmt_get_result($assigned_stmt);
        while ($row = mysqli_fetch_assoc($assigned_result)) {
            $assigned_students[] = $row["student_id"];
        }
    }
    mysqli_stmt_close($assigned_stmt);
}

// Fetch all students enrolled in the course
$students = [];
$students_sql = "SELECT u.id, u.first_name, u.last_name, u.email
                FROM course_registrations cr 
                JOIN users u ON cr.student_id = u.id 
                WHERE cr.course_id = ? 
                ORDER BY u.last_name, u.first_name";
if ($students_stmt = mysqli_prepare($conn, $students_sql)) {
    mysqli_stmt_bind_param($students_stmt, "i", $course_id);
    if (mysqli_stmt_execute($students_stmt)) {
        $students_result = mysqli_stmt_get_result($students_stmt);
        while ($student = mysqli_fetch_assoc($students_result)) {
            $students[] = $student;
        }
    }
    mysqli_stmt_close($students_stmt);
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // First, remove all existing assignments for this exam
    $delete_sql = "DELETE FROM exam_students WHERE exam_id = ?";
    if ($delete_stmt = mysqli_prepare($conn, $delete_sql)) {
        mysqli_stmt_bind_param($delete_stmt, "i", $exam_id);
        mysqli_stmt_execute($delete_stmt);
        mysqli_stmt_close($delete_stmt);
    }

    // Then add the selected students
    if (isset($_POST["student_ids"]) && is_array($_POST["student_ids"])) {
        $insert_sql = "INSERT INTO exam_students (exam_id, student_id) VALUES (?, ?)";
        if ($insert_stmt = mysqli_prepare($conn, $insert_sql)) {
            foreach ($_POST["student_ids"] as $student_id) {
                mysqli_stmt_bind_param($insert_stmt, "ii", $exam_id, $student_id);
                mysqli_stmt_execute($insert_stmt);
            }
            mysqli_stmt_close($insert_stmt);
        }
    }

    // Redirect to review-exam.php instead of assign-students.php
    header("location: review-exam.php?exam_id=" . $exam_id);
    exit;
}

// Include header
include_once "../../includes/header.php";
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Assign Students to Exam</h1>
        <a href="view-exam.php?id=<?php echo $exam_id; ?>" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left fa-sm"></i> Back to Exam
        </a>
    </div>

    <?php if (isset($_GET["success"])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Students have been successfully assigned to the exam.
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Exam Details</h6>
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
                                <th>Start Time</th>
                                <td><?php echo date('F d, Y - h:i A', strtotime($exam["start_time"])); ?></td>
                            </tr>
                            <tr>
                                <th>Duration</th>
                                <td><?php echo $exam["duration"]; ?> minutes</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Assign Students</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($students)): ?>
                        <div class="text-center py-4">
                            <p class="text-gray-500">No students enrolled in this course yet.</p>
                            <a href="manage-students.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i> Add Students to Course
                            </a>
                        </div>
                    <?php else: ?>
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?exam_id=" . $exam_id; ?>">
                            <div class="form-group">
                                <div class="custom-control custom-checkbox mb-3">
                                    <input type="checkbox" class="custom-control-input" id="selectAll">
                                    <label class="custom-control-label" for="selectAll">Select All Students</label>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered" id="studentsTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th width="50">Select</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): ?>
                                            <tr>
                                                <td class="text-center">
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="checkbox" class="custom-control-input student-checkbox"
                                                            id="student_<?php echo $student['id']; ?>"
                                                            name="student_ids[]"
                                                            value="<?php echo $student['id']; ?>"
                                                            <?php echo in_array($student['id'], $assigned_students) ? 'checked' : ''; ?>>
                                                        <label class="custom-control-label" for="student_<?php echo $student['id']; ?>"></label>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="form-group mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save and Review
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
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
                    <div class="step active">
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Select All functionality
        const selectAllCheckbox = document.getElementById('selectAll');
        const studentCheckboxes = document.querySelectorAll('.student-checkbox');

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const isChecked = this.checked;
                studentCheckboxes.forEach(checkbox => {
                    checkbox.checked = isChecked;
                });
            });
        }

        // Update "Select All" state based on individual checkboxes
        studentCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allChecked = Array.from(studentCheckboxes).every(cb => cb.checked);
                const anyChecked = Array.from(studentCheckboxes).some(cb => cb.checked);

                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = allChecked;
                    selectAllCheckbox.indeterminate = anyChecked && !allChecked;
                }
            });
        });

        // Initialize DataTable for better user experience
        if (typeof $.fn.DataTable !== 'undefined') {
            $('#studentsTable').DataTable({
                "order": [
                    [1, "asc"]
                ],
                "pageLength": 25
            });
        }
    });
</script>

<?php
// Include footer
include_once "../../includes/footer.php";
?>