<?php
// Set page title and base URL for includes
$page_title = "View Course";
$base_url = "../..";

// Include config file
require_once "../../config.php";

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../../auth/login.php");
    exit;
}

// Check if user is a supervisor
if ($_SESSION["role"] !== "supervisor") {
    // Redirect to appropriate dashboard based on role
    if ($_SESSION["role"] == "student") {
        header("location: ../student/index.php");
    } elseif ($_SESSION["role"] == "trainer") {
        header("location: ../trainer/index.php");
    }
    exit;
}

// Check if course ID is provided
if (!isset($_GET["id"]) || empty($_GET["id"])) {
    header("location: reports.php");
    exit;
}

$course_id = $_GET["id"];

// Fetch course data
$course = null;
$sql = "SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as trainer_name, u.id as trainer_id 
        FROM courses c
        LEFT JOIN users u ON c.trainer_id = u.id
        WHERE c.id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $course_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) == 1) {
            $course = mysqli_fetch_assoc($result);
        } else {
            // Course not found
            header("location: reports.php");
            exit;
        }
    } else {
        echo "Oops! Something went wrong. Please try again later.";
    }
    mysqli_stmt_close($stmt);
}

// Fetch course topics
$topics = [];
$topics_sql = "SELECT * FROM course_topics WHERE course_id = ? ORDER BY sort_order";
if ($topics_stmt = mysqli_prepare($conn, $topics_sql)) {
    mysqli_stmt_bind_param($topics_stmt, "i", $course_id);

    // Use try-catch to handle the case when the table doesn't exist
    try {
        mysqli_stmt_execute($topics_stmt);
        $topics_result = mysqli_stmt_get_result($topics_stmt);
        while ($topic = mysqli_fetch_assoc($topics_result)) {
            $topics[] = $topic;
        }
    } catch (mysqli_sql_exception $e) {
        // Table doesn't exist or other SQL error
        // Just continue with empty topics array
        $topics = [];
    }

    mysqli_stmt_close($topics_stmt);
}

// Fetch exams for this course
$exams = [];
$exams_sql = "SELECT id, title, start_time, duration, total_marks, status FROM exams WHERE course_id = ? ORDER BY start_time DESC";
if ($exams_stmt = mysqli_prepare($conn, $exams_sql)) {
    mysqli_stmt_bind_param($exams_stmt, "i", $course_id);
    if (mysqli_stmt_execute($exams_stmt)) {
        $exams_result = mysqli_stmt_get_result($exams_stmt);
        while ($exam = mysqli_fetch_assoc($exams_result)) {
            $exams[] = $exam;
        }
    }
    mysqli_stmt_close($exams_stmt);
}

// Fetch enrolled students
$students = [];
$students_sql = "SELECT u.id, u.user_id, u.first_name, u.last_name, u.email, cr.enrollment_date 
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

// Include header
include_once "../../includes/header.php";
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($course["title"]); ?></h1>
        <div>
            <?php if (!empty($course["trainer_id"])): ?>
                <a href="instructor-profile.php?id=<?php echo $course["trainer_id"]; ?>" class="btn btn-primary btn-sm">
                    <i class="fas fa-user fa-sm"></i> View Instructor
                </a>
            <?php endif; ?>
            <a href="reports.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left fa-sm"></i> Back to Reports
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Course Details Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Course Details</h6>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h5>Description</h5>
                        <p><?php echo nl2br(htmlspecialchars($course["description"])); ?></p>
                    </div>

                    <?php if (!empty($topics)): ?>
                        <div class="mb-4">
                            <h5>Course Topics</h5>
                            <ol class="list-group list-group-numbered">
                                <?php foreach ($topics as $topic): ?>
                                    <li class="list-group-item"><?php echo htmlspecialchars($topic["title"]); ?></li>
                                <?php endforeach; ?>
                            </ol>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-4">
                                <h5>Course Information</h5>
                                <table class="table table-bordered">
                                    <tr>
                                        <th>Instructor</th>
                                        <td>
                                            <?php if (!empty($course["trainer_name"])): ?>
                                                <a href="instructor-profile.php?id=<?php echo $course["trainer_id"]; ?>">
                                                    <?php echo htmlspecialchars($course["trainer_name"]); ?>
                                                </a>
                                            <?php else: ?>
                                                Not Assigned
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Created On</th>
                                        <td><?php echo date('F d, Y', strtotime($course["created_at"])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Last Updated</th>
                                        <td><?php echo date('F d, Y', strtotime($course["updated_at"] ?? $course["created_at"])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Enrolled Students</th>
                                        <td><?php echo count($students); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Total Exams</th>
                                        <td><?php echo count($exams); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Exams Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Course Exams</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($exams)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Date</th>
                                        <th>Duration</th>
                                        <th>Total Marks</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($exams as $exam): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($exam["title"]); ?></td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($exam["start_time"])); ?></td>
                                            <td><?php echo $exam["duration"]; ?> mins</td>
                                            <td><?php echo $exam["total_marks"]; ?></td>
                                            <td>
                                                <?php if ($exam["status"] == "published"): ?>
                                                    <span class="badge text-success">Published</span>
                                                <?php else: ?>
                                                    <span class="badge text-warning">Draft</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p class="text-gray-500">No exams created for this course yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Enrolled Students Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Enrolled Students</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($students)): ?>
                        <div class="list-group">
                            <?php foreach ($students as $index => $student): ?>
                                <?php if ($index < 10): // Show only first 10 students 
                                ?>
                                    <div class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">
                                                <a href="student_profile.php?id=<?php echo $student["id"]; ?>">
                                                    <?php echo htmlspecialchars($student["first_name"] . " " . $student["last_name"]); ?>
                                                </a>
                                            </h6>
                                            <small><?php echo date('M d, Y', strtotime($student["enrollment_date"])); ?></small>
                                        </div>
                                        <small><?php echo htmlspecialchars($student["email"]); ?></small>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>

                        <?php if (count($students) > 10): ?>
                            <div class="text-center mt-3">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#allStudentsModal">
                                    View All <?php echo count($students); ?> Students
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p class="text-gray-500">No students enrolled in this course yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Course Statistics Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Course Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6 mb-3">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Students</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($students); ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-users fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Exams</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($exams); ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Delete Course Button -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Course Management</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <strong>Warning:</strong> Deleting a course will remove all associated exams, questions, and student enrollments. This action cannot be undone.
                        </div>
                        <a href="delete-course.php?id=<?php echo $course_id; ?>&confirm=no" class="btn btn-danger">
                            <i class="fas fa-trash-alt me-2"></i>Delete Course
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- All Students Modal -->
<?php if (count($students) > 10): ?>
    <div class="modal fade" id="allStudentsModal" tabindex="-1" role="dialog" aria-labelledby="allStudentsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="allStudentsModalLabel">All Enrolled Students</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="studentsTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Enrolled On</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student["user_id"]); ?></td>
                                        <td><?php echo htmlspecialchars($student["first_name"] . " " . $student["last_name"]); ?></td>
                                        <td><?php echo htmlspecialchars($student["email"]); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($student["enrollment_date"])); ?></td>
                                        <td>
                                            <a href="student_profile.php?id=<?php echo $student["id"]; ?>" class="btn btn-info btn-sm">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    $(document).ready(function() {
        // Initialize DataTable for students table in modal
        if ($('#studentsTable').length) {
            $('#studentsTable').DataTable({
                responsive: true
            });
        }
    });
</script>

<?php
// Include footer
include_once "../../includes/footer.php";
?>