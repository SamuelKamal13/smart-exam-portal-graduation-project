<?php
// Set page title and base URL for includes
$page_title = "Manage Students";
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

// Check if course ID is provided
if (!isset($_GET["course_id"]) || empty($_GET["course_id"])) {
    header("location: manage-courses.php");
    exit;
}

$course_id = $_GET["id"] ?? $_GET["course_id"];

// Fetch course data
$course = null;
$sql = "SELECT * FROM courses WHERE id = ? AND trainer_id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $course_id, $trainer_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) == 1) {
            $course = mysqli_fetch_assoc($result);
        } else {
            // Course not found or doesn't belong to this trainer
            header("location: manage-courses.php");
            exit;
        }
    } else {
        echo "Oops! Something went wrong. Please try again later.";
    }
    mysqli_stmt_close($stmt);
}

// Process add student operation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"])) {
    // Initialize counters
    $added_count = 0;
    $already_enrolled_count = 0;
    $not_found_count = 0;

    // Add single student
    if ($_POST["action"] == "add") {
        $student_email = trim($_POST["student_email"]);

        if (!empty($student_email)) {
            // Check if student exists
            $check_sql = "SELECT id FROM users WHERE email = ? AND role = 'student'";
            if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
                mysqli_stmt_bind_param($check_stmt, "s", $student_email);
                if (mysqli_stmt_execute($check_stmt)) {
                    $check_result = mysqli_stmt_get_result($check_stmt);
                    if (mysqli_num_rows($check_result) == 1) {
                        $student = mysqli_fetch_assoc($check_result);
                        $student_id = $student["id"];

                        // Check if student is already enrolled
                        $enrolled_sql = "SELECT * FROM course_registrations WHERE course_id = ? AND student_id = ?";
                        if ($enrolled_stmt = mysqli_prepare($conn, $enrolled_sql)) {
                            mysqli_stmt_bind_param($enrolled_stmt, "ii", $course_id, $student_id);
                            if (mysqli_stmt_execute($enrolled_stmt)) {
                                $enrolled_result = mysqli_stmt_get_result($enrolled_stmt);
                                if (mysqli_num_rows($enrolled_result) == 0) {
                                    // Enroll student
                                    $enroll_sql = "INSERT INTO course_registrations (course_id, student_id, enrollment_date) VALUES (?, ?, NOW())";
                                    if ($enroll_stmt = mysqli_prepare($conn, $enroll_sql)) {
                                        mysqli_stmt_bind_param($enroll_stmt, "ii", $course_id, $student_id);
                                        if (mysqli_stmt_execute($enroll_stmt)) {
                                            header("location: manage-students.php?course_id=" . $course_id . "&success=added");
                                            exit();
                                        }
                                        mysqli_stmt_close($enroll_stmt);
                                    }
                                } else {
                                    header("location: manage-students.php?course_id=" . $course_id . "&error=already_enrolled");
                                    exit();
                                }
                            }
                            mysqli_stmt_close($enrolled_stmt);
                        }
                    } else {
                        header("location: manage-students.php?course_id=" . $course_id . "&error=student_not_found");
                        exit();
                    }
                }
                mysqli_stmt_close($check_stmt);
            }
        }
    }
    // Remove student
    else if ($_POST["action"] == "remove") {
        $student_id = $_POST["student_id"];

        if (!empty($student_id)) {
            $remove_sql = "DELETE FROM course_registrations WHERE course_id = ? AND student_id = ?";
            if ($remove_stmt = mysqli_prepare($conn, $remove_sql)) {
                mysqli_stmt_bind_param($remove_stmt, "ii", $course_id, $student_id);
                if (mysqli_stmt_execute($remove_stmt)) {
                    header("location: manage-students.php?course_id=" . $course_id . "&success=removed");
                    exit();
                }
                mysqli_stmt_close($remove_stmt);
            }
        }
    }
    // Bulk add students
    else if ($_POST["action"] == "bulk_add") {
        $student_emails = trim($_POST["student_emails"]);
        $emails_array = explode("\n", $student_emails);

        foreach ($emails_array as $email) {
            $email = trim($email);
            if (empty($email)) continue;

            // Check if student exists
            $check_sql = "SELECT id FROM users WHERE email = ? AND role = 'student'";
            if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
                mysqli_stmt_bind_param($check_stmt, "s", $email);
                if (mysqli_stmt_execute($check_stmt)) {
                    $check_result = mysqli_stmt_get_result($check_stmt);
                    if (mysqli_num_rows($check_result) == 1) {
                        $student = mysqli_fetch_assoc($check_result);
                        $student_id = $student["id"];

                        // Check if student is already enrolled
                        $enrolled_sql = "SELECT * FROM course_registrations WHERE course_id = ? AND student_id = ?";
                        if ($enrolled_stmt = mysqli_prepare($conn, $enrolled_sql)) {
                            mysqli_stmt_bind_param($enrolled_stmt, "ii", $course_id, $student_id);
                            if (mysqli_stmt_execute($enrolled_stmt)) {
                                $enrolled_result = mysqli_stmt_get_result($enrolled_stmt);
                                if (mysqli_num_rows($enrolled_result) == 0) {
                                    // Enroll student
                                    $enroll_sql = "INSERT INTO course_registrations (course_id, student_id, enrollment_date) VALUES (?, ?, NOW())";
                                    if ($enroll_stmt = mysqli_prepare($conn, $enroll_sql)) {
                                        mysqli_stmt_bind_param($enroll_stmt, "ii", $course_id, $student_id);
                                        if (mysqli_stmt_execute($enroll_stmt)) {
                                            $added_count++;
                                        }
                                        mysqli_stmt_close($enroll_stmt);
                                    }
                                } else {
                                    $already_enrolled_count++;
                                }
                            }
                            mysqli_stmt_close($enrolled_stmt);
                        }
                    } else {
                        $not_found_count++;
                    }
                }
                mysqli_stmt_close($check_stmt);
            }
        }

        // Redirect with results
        header("location: manage-students.php?course_id=" . $course_id . "&success=bulk_added&added=" . $added_count . "&already=" . $already_enrolled_count . "&not_found=" . $not_found_count);
        exit();
    }
}

// Fetch enrolled students
$students = [];
$students_sql = "SELECT u.id, u.first_name, u.last_name, u.email, cr.enrollment_date 
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
        <h1 class="h3 mb-0 text-gray-800">Manage Students: <?php echo htmlspecialchars($course["title"]); ?></h1>
        <a href="view-course.php?id=<?php echo $course_id; ?>" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left fa-sm"></i> Back to Course
        </a>
    </div>

    <?php if (isset($_GET["success"])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php if ($_GET["success"] == "added"): ?>
                Student added successfully to the course.
            <?php elseif ($_GET["success"] == "removed"): ?>
                Student removed successfully from the course.
            <?php elseif ($_GET["success"] == "bulk_added"): ?>
                Bulk enrollment completed:
                <?php echo $_GET["added"]; ?> students added,
                <?php echo $_GET["already"]; ?> already enrolled,
                <?php echo $_GET["not_found"]; ?> not found.
            <?php endif; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET["error"])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php if ($_GET["error"] == "student_not_found"): ?>
                Student not found. Please check the email address.
            <?php elseif ($_GET["error"] == "already_enrolled"): ?>
                Student is already enrolled in this course.
            <?php endif; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <!-- Enrolled Students Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Enrolled Students</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($students)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" id="studentsTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Enrollment Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($student["first_name"] . " " . $student["last_name"]); ?></td>
                                            <td><?php echo htmlspecialchars($student["email"]); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($student["enrollment_date"])); ?></td>
                                            <td>
                                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?course_id=" . $course_id; ?>" method="post" class="d-inline">
                                                    <input type="hidden" name="action" value="remove">
                                                    <input type="hidden" name="student_id" value="<?php echo $student["id"]; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to remove this student from the course?');">
                                                        <i class="fas fa-user-minus"></i> Remove
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p class="text-gray-500">No students enrolled in this course yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Add Student Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Add Student</h6>
                </div>
                <div class="card-body">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?course_id=" . $course_id; ?>" method="post">
                        <input type="hidden" name="action" value="add">
                        <div class="form-group">
                            <label>Student Email</label>
                            <input type="email" name="student_email" class="form-control" required placeholder="Enter student email">
                            <small class="form-text text-muted">Enter the email address of a registered student.</small>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-user-plus"></i> Add Student
                        </button>
                    </form>
                </div>
            </div>

            <!-- Bulk Add Students Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Bulk Add Students</h6>
                </div>
                <div class="card-body">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?course_id=" . $course_id; ?>" method="post">
                        <input type="hidden" name="action" value="bulk_add">
                        <div class="form-group">
                            <label>Student Emails (One per line)</label>
                            <textarea name="student_emails" class="form-control" rows="6" required placeholder="student1@example.com&#10;student2@example.com&#10;student3@example.com"></textarea>
                            <small class="form-text text-muted">Enter one email address per line.</small>
                        </div>
                        <button type="submit" class="btn btn-success btn-block">
                            <i class="fas fa-users"></i> Bulk Enroll Students
                        </button>
                    </form>
                </div>
            </div>

            <!-- Course Info Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Course Information</h6>
                </div>
                <div class="card-body">
                    <p><strong>Course:</strong> <?php echo htmlspecialchars($course["title"]); ?></p>
                    <p><strong>Total Students:</strong> <?php echo count($students); ?></p>
                    <div class="d-grid gap-2">
                        <a href="view-course.php?id=<?php echo $course_id; ?>" class="btn btn-info btn-block">
                            <i class="fas fa-eye"></i> View Course Details
                        </a>
                        <a href="manage-courses.php" class="btn btn-secondary btn-block">
                            <i class="fas fa-list"></i> All Courses
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- DataTables JavaScript -->
<script>
    $(document).ready(function() {
        $('#studentsTable').DataTable({
            order: [
                [0, 'asc']
            ], // Sort by name (ascending)
            responsive: true
        });
    });
</script>

<?php
// Include footer
include_once "../../includes/footer.php";
?>