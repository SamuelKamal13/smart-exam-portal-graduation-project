<?php
// Set page title and base URL for includes
$page_title = "Student Dashboard";
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

// Get upcoming exams for the student
$upcoming_exams = [];
$sql = "SELECT e.id, e.title, e.description, e.start_time, e.duration, c.title AS course_title 
        FROM exams e 
        JOIN exam_students es ON e.id = es.exam_id 
        LEFT JOIN courses c ON e.course_id = c.id
        WHERE es.student_id = ? AND (
            e.start_time > NOW() OR 
            (e.start_time <= NOW() AND DATE_ADD(e.start_time, INTERVAL e.duration MINUTE) > NOW())
        )
        ORDER BY e.start_time ASC LIMIT 5";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            // Add a status field to indicate if the exam is running
            $start_time = new DateTime($row['start_time']);
            $end_time = clone $start_time;
            $end_time->add(new DateInterval('PT' . $row['duration'] . 'M'));
            $now = new DateTime();

            if ($now >= $start_time && $now <= $end_time) {
                $row['status'] = 'running';
            } else {
                $row['status'] = 'upcoming';
            }

            $upcoming_exams[] = $row;
        }
    }

    mysqli_stmt_close($stmt);
}

// Get recent exam results
$recent_results = [];
$sql = "SELECT r.id, r.score, r.total_marks, r.percentage, r.submission_time, e.title AS exam_title 
        FROM results r 
        JOIN exams e ON r.exam_id = e.id 
        WHERE r.student_id = ? 
        ORDER BY r.submission_time DESC LIMIT 5";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $recent_results[] = $row;
        }
    }

    mysqli_stmt_close($stmt);
}

// Get enrolled courses
$enrolled_courses = [];
$sql = "SELECT c.id, c.title, c.description, 
        CONCAT(u.first_name, ' ', u.last_name) AS trainer_name 
        FROM courses c 
        JOIN course_registrations cr ON c.id = cr.course_id 
        LEFT JOIN users u ON c.trainer_id = u.id
        WHERE cr.student_id = ? 
        ORDER BY cr.enrollment_date DESC";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $enrolled_courses[] = $row;
        }
    }

    mysqli_stmt_close($stmt);
}

// Include header
include_once "../../includes/header.php";
?>

<div class="container-fluid">
    <h1 class="mt-4 mb-4">Student Dashboard</h1>

    <div class="row">
        <div class="col-xl col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Upcoming Exams</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($upcoming_exams); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Enrolled Courses</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($enrolled_courses); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-book fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Completed Exams</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($recent_results); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
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
                    <a href="exams.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (count($upcoming_exams) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Exam</th>
                                        <th>Course</th>
                                        <th>Date & Time</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcoming_exams as $exam): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                            <td><?php echo htmlspecialchars($exam['course_title'] ?? 'N/A'); ?></td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($exam['start_time'])); ?></td>
                                            <td><?php echo $exam['duration']; ?> minutes</td>
                                            <td>
                                                <?php if ($exam['status'] == 'running'): ?>
                                                    <span class="badge bg-success">Running</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary">Upcoming</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($exam['status'] == 'running'): ?>
                                                    <a href="take-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-success btn-sm">
                                                        <i class="fas fa-play-circle"></i> Take Exam
                                                    </a>
                                                <?php else: ?>
                                                    <a href="view-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-primary btn-sm">
                                                        <i class="fas fa-info-circle"></i> View
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center">No upcoming or running exams scheduled.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Results -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Results</h6>
                    <a href="results.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (count($recent_results) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Exam</th>
                                        <th>Score</th>
                                        <th>Percentage</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_results as $result): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($result['exam_title']); ?></td>
                                            <td><?php echo $result['score'] . '/' . $result['total_marks']; ?></td>
                                            <td>
                                                <div class="progress mb-4">
                                                    <div class="progress-bar bg-<?php echo getProgressBarColor($result['percentage']); ?>"
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
                        <p class="text-center">No exam results yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Enrolled Courses -->
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">My Courses</h6>
                </div>
                <div class="card-body">
                    <?php if (count($enrolled_courses) > 0): ?>
                        <div class="row">
                            <?php foreach ($enrolled_courses as $course): ?>
                                <div class="col-lg-4 col-md-6 mb-4">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                                            <h6 class="card-subtitle mb-2 text-muted">Trainer: <?php echo htmlspecialchars($course['trainer_name'] ?? 'Not Assigned'); ?></h6>
                                            <p class="card-text"><?php echo htmlspecialchars(substr($course['description'], 0, 100)) . (strlen($course['description']) > 100 ? '...' : ''); ?></p>
                                        </div>
                                        <div class="card-footer">
                                            <a href="course-details.php?id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">View Details</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center">You are not enrolled in any courses yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Helper function to determine progress bar color based on percentage
function getProgressBarColor($percentage)
{
    if ($percentage >= 80) {
        return 'success';
    } elseif ($percentage >= 60) {
        return 'info';
    } elseif ($percentage >= 40) {
        return 'warning';
    } else {
        return 'danger';
    }
}

// Include footer
include_once "../../includes/footer.php";
?>