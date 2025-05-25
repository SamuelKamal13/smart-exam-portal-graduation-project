<?php
// Set page title and base URL for includes
$page_title = "Trainer Dashboard";
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

// Get count of courses
$course_count = 0;
$sql = "SELECT COUNT(*) as count FROM courses WHERE trainer_id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $trainer_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $course_count = $row["count"];
        }
    }
    mysqli_stmt_close($stmt);
}

// Get count of exams
$exam_count = 0;
$sql = "SELECT COUNT(*) as count FROM exams WHERE created_by = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $trainer_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $exam_count = $row["count"];
        }
    }
    mysqli_stmt_close($stmt);
}

// Get count of students in trainer's courses
$student_count = 0;
$sql = "SELECT COUNT(DISTINCT cr.student_id) as count 
        FROM course_registrations cr 
        JOIN courses c ON cr.course_id = c.id 
        WHERE c.trainer_id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $trainer_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $student_count = $row["count"];
        }
    }
    mysqli_stmt_close($stmt);
}

// Get upcoming exams
$upcoming_exams = [];
$sql = "SELECT e.id, e.title, e.start_time, e.duration, c.title as course_title, 
               (SELECT COUNT(*) FROM exam_students WHERE exam_id = e.id) as student_count
        FROM exams e
        LEFT JOIN courses c ON e.course_id = c.id
        WHERE e.created_by = ? AND e.start_time > NOW()
        ORDER BY e.start_time ASC
        LIMIT 5";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $trainer_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $upcoming_exams[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

// Get recent results
$recent_results = [];
$sql = "SELECT r.id, r.exam_id, r.student_id, r.score, r.total_marks, r.percentage, 
               r.submission_time, e.title as exam_title, 
               CONCAT(u.first_name, ' ', u.last_name) as student_name
        FROM results r
        JOIN exams e ON r.exam_id = e.id
        JOIN users u ON r.student_id = u.id
        WHERE e.created_by = ?
        ORDER BY r.submission_time DESC
        LIMIT 5";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $trainer_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $recent_results[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

// Include header
include_once "../../includes/header.php";
?>

<div class="container-fluid">
    <h1 class="mt-4 mb-4">Trainer Dashboard</h1>

    <!-- Dashboard Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Courses</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $course_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-book fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Exams</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $exam_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Students</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $student_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Next Exam</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php
                                if (count($upcoming_exams) > 0) {
                                    echo date('M d, Y', strtotime($upcoming_exams[0]['start_time']));
                                } else {
                                    echo "No upcoming exams";
                                }
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Upcoming Exams -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Upcoming Exams</h6>
                    <a href="manage-exams.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (count($upcoming_exams) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Exam Title</th>
                                        <th>Course</th>
                                        <th>Date</th>
                                        <th>Students</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcoming_exams as $exam): ?>
                                        <tr>
                                            <td><a href="view-exam.php?id=<?php echo $exam['id']; ?>"><?php echo htmlspecialchars($exam['title']); ?></a></td>
                                            <td><?php echo htmlspecialchars($exam['course_title'] ?? 'N/A'); ?></td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($exam['start_time'])); ?></td>
                                            <td><?php echo $exam['student_count']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p class="text-gray-500">No upcoming exams scheduled.</p>
                            <a href="create-exam.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Create New Exam
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Results -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Results</h6>
                    <a href="exam-results.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (count($recent_results) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Exam</th>
                                        <th>Score</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_results as $result): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($result['student_name']); ?></td>
                                            <td><?php echo htmlspecialchars($result['exam_title']); ?></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar <?php echo ($result['percentage'] >= 40) ? 'bg-success' : 'bg-danger'; ?>"
                                                        role="progressbar" style="width: <?php echo $result['percentage']; ?>%"
                                                        aria-valuenow="<?php echo $result['percentage']; ?>" aria-valuemin="0" aria-valuemax="100">
                                                        <?php echo $result['percentage']; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($result['submission_time'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p class="text-gray-500">No exam results available yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="create-course.php" class="btn btn-primary btn-block py-3">
                                <i class="fas fa-book-medical fa-fw"></i> Create New Course
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="create-exam.php" class="btn btn-success btn-block py-3">
                                <i class="fas fa-file-alt fa-fw"></i> Create New Exam
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="manage-questions.php" class="btn btn-info btn-block py-3">
                                <i class="fas fa-question-circle fa-fw"></i> Manage Questions
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="manage-courses.php" class="btn btn-warning btn-block py-3">
                                <i class="fas fa-book me-2 fa-fw"></i> Manage Courses
                            </a>
                        </div>
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