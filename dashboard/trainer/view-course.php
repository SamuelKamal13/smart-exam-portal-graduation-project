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
if (!isset($_GET["id"]) || empty($_GET["id"])) {
    header("location: manage-courses.php");
    exit;
}

$course_id = $_GET["id"];

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
$exams_sql = "SELECT id, title, start_time, duration, total_marks FROM exams WHERE course_id = ? ORDER BY start_time DESC";
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
        <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($course["title"]); ?></h1>
        <div>
            <a href="edit-course.php?id=<?php echo $course_id; ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-edit fa-sm"></i> Edit Course
            </a>
            <a href="create-exam.php?course_id=<?php echo $course_id; ?>" class="btn btn-success btn-sm">
                <i class="fas fa-plus fa-sm"></i> Add Exam
            </a>
            <a href="manage-students.php?course_id=<?php echo $course_id; ?>" class="btn btn-info btn-sm">
                <i class="fas fa-users fa-sm"></i> Manage Students
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
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Course Exams</h6>
                    <a href="create-exam.php?course_id=<?php echo $course_id; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus fa-sm"></i> Add Exam
                    </a>
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
                                        <th>Actions</th>
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
                                                <a href="view-exam.php?id=<?php echo $exam["id"]; ?>" class="btn btn-info btn-sm">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit-exam.php?id=<?php echo $exam["id"]; ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="manage-questions.php?exam_id=<?php echo $exam["id"]; ?>" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-question-circle"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p class="text-gray-500">No exams created for this course yet.</p>
                            <a href="create-exam.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Create New Exam
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Enrolled Students Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Enrolled Students</h6>
                    <a href="manage-students.php?course_id=<?php echo $course_id; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-users fa-sm"></i> Manage
                    </a>
                </div>
                <div class="card-body">
                    <?php if (!empty($students)): ?>
                        <div class="list-group">
                            <?php foreach ($students as $index => $student): ?>
                                <?php if ($index < 10): // Show only first 10 students 
                                ?>
                                    <div class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($student["first_name"] . " " . $student["last_name"]); ?></h6>
                                            <small><?php echo date('M d, Y', strtotime($student["enrollment_date"])); ?></small>
                                        </div>
                                        <small><?php echo htmlspecialchars($student["email"]); ?></small>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>

                        <?php if (count($students) > 10): ?>
                            <div class="text-center mt-3">
                                <a href="manage-students.php?course_id=<?php echo $course_id; ?>" class="btn btn-sm btn-outline-primary">
                                    View All <?php echo count($students); ?> Students
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p class="text-gray-500">No students enrolled in this course yet.</p>
                            <a href="manage-students.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i> Add Students
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="create-exam.php?course_id=<?php echo $course_id; ?>" class="btn btn-success btn-block mb-2">
                            <i class="fas fa-plus fa-sm"></i> Create Exam
                        </a>
                        <a href="manage-students.php?course_id=<?php echo $course_id; ?>" class="btn btn-info btn-block mb-2">
                            <i class="fas fa-user-plus fa-sm"></i> Add Students
                        </a>
                        <a href="edit-course.php?id=<?php echo $course_id; ?>" class="btn btn-primary btn-block mb-2">
                            <i class="fas fa-edit fa-sm"></i> Edit Course
                        </a>
                        <a href="manage-courses.php" class="btn btn-secondary btn-block">
                            <i class="fas fa-arrow-left fa-sm"></i> Back to Courses
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once "../../includes/footer.php";
?>