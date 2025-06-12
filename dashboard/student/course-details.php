<?php
// Set page title and base URL for includes
$page_title = "Course Details";
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

// Check if course ID is provided
if (!isset($_GET["id"]) || empty($_GET["id"])) {
    header("location: index.php");
    exit;
}

$course_id = $_GET["id"];

// Check if student is enrolled in this course
$is_enrolled = false;
$sql = "SELECT * FROM course_registrations WHERE course_id = ? AND student_id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $course_id, $student_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) > 0) {
            $is_enrolled = true;
        }
    }
    mysqli_stmt_close($stmt);
}

// If not enrolled, redirect to dashboard
if (!$is_enrolled) {
    header("location: index.php");
    exit;
}

// Get course details
$course = null;
$sql = "SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) AS trainer_name, u.email AS trainer_email 
        FROM courses c 
        LEFT JOIN users u ON c.trainer_id = u.id 
        WHERE c.id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $course_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) > 0) {
            $course = mysqli_fetch_assoc($result);
        }
    }
    mysqli_stmt_close($stmt);
}

// If course not found, redirect to dashboard
if ($course === null) {
    header("location: index.php");
    exit;
}

// Get course topics
$topics = [];
$sql = "SELECT * FROM course_topics WHERE course_id = ? ORDER BY sort_order ASC";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $course_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $topics[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

// Get upcoming exams for this course
$upcoming_exams = [];
$sql = "SELECT e.id, e.title, e.description, e.start_time, e.duration, e.total_marks 
        FROM exams e 
        JOIN exam_students es ON e.id = es.exam_id 
        WHERE e.course_id = ? AND es.student_id = ? AND e.start_time > NOW() 
        ORDER BY e.start_time ASC";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $course_id, $student_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $upcoming_exams[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

// Get past exams and results for this course
$past_exams = [];
$sql = "SELECT e.id, e.title, e.start_time, r.score, r.total_marks, r.percentage 
        FROM exams e 
        LEFT JOIN results r ON e.id = r.exam_id AND r.student_id = ? 
        WHERE e.course_id = ? AND e.start_time < NOW() 
        ORDER BY e.start_time DESC";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $student_id, $course_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $past_exams[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

// Include header
include_once "../../includes/header.php";
?>

<div class="container-fluid">
    <!-- Back button -->
    <div class="mb-4">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <!-- Course Header -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Course Details</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <h2 class="mb-3"><?php echo htmlspecialchars($course['title']); ?></h2>
                    <p class="lead"><?php echo htmlspecialchars($course['description']); ?></p>
                </div>
                <div class="col-md-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Trainer</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo htmlspecialchars($course['trainer_name'] ?? 'Not Assigned'); ?>
                                    </div>
                                    <?php if (!empty($course['trainer_email'])): ?>
                                        <div class="small mt-2">
                                            <a href="mailto:<?php echo htmlspecialchars($course['trainer_email']); ?>">
                                                <i class="fas fa-envelope"></i> Contact Trainer
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-tie fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Course Topics -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Course Topics</h6>
                </div>
                <div class="card-body">
                    <?php if (count($topics) > 0): ?>
                        <div class="accordion" id="topicsAccordion">
                            <?php foreach ($topics as $index => $topic): ?>
                                <div class="card">
                                    <div class="card-header" id="heading<?php echo $topic['id']; ?>">
                                        <h2 class="mb-0">
                                            <button class="btn btn-link btn-block text-left" type="button"
                                                data-toggle="collapse" data-target="#collapse<?php echo $topic['id']; ?>"
                                                aria-expanded="<?php echo ($index === 0) ? 'true' : 'false'; ?>"
                                                aria-controls="collapse<?php echo $topic['id']; ?>">
                                                <?php echo htmlspecialchars($topic['title']); ?>
                                            </button>
                                        </h2>
                                    </div>

                                    <div id="collapse<?php echo $topic['id']; ?>"
                                        class="collapse <?php echo ($index === 0) ? 'show' : ''; ?>"
                                        aria-labelledby="heading<?php echo $topic['id']; ?>"
                                        data-parent="#topicsAccordion">
                                        <div class="card-body">
                                            <!-- Topic content would go here if we had it in the database -->
                                            <p>This topic is part of your course curriculum.</p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center">No topics have been added to this course yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Upcoming Exams -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Upcoming Exams</h6>
                </div>
                <div class="card-body">
                    <?php if (count($upcoming_exams) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Exam</th>
                                        <th>Date & Time</th>
                                        <th>Duration</th>
                                        <th>Total Marks</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcoming_exams as $exam): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($exam['start_time'])); ?></td>
                                            <td><?php echo $exam['duration']; ?> minutes</td>
                                            <td><?php echo $exam['total_marks']; ?></td>
                                            <td>
                                                <?php
                                                $now = new DateTime();
                                                $exam_time = new DateTime($exam['start_time']);
                                                $time_diff = $now->diff($exam_time);
                                                $days_remaining = $time_diff->days;

                                                if ($now >= $exam_time) {
                                                    // Exam has started
                                                    echo '<a href="../exam/take-exam.php?id=' . $exam['id'] . '" class="btn btn-success btn-sm">Take Exam</a>';
                                                } else {
                                                    // Exam is in the future
                                                    echo '<span class="badge text-info">Starts in ' . $days_remaining . ' day' . ($days_remaining != 1 ? 's' : '') . '</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center">No upcoming exams for this course.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Past Exams and Results -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Past Exams & Results</h6>
        </div>
        <div class="card-body">
            <?php if (count($past_exams) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="pastExamsTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Exam</th>
                                <th>Date</th>
                                <th>Score</th>
                                <th>Percentage</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($past_exams as $exam): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($exam['start_time'])); ?></td>
                                    <td>
                                        <?php
                                        if (isset($exam['score'])) {
                                            echo $exam['score'] . '/' . $exam['total_marks'];
                                        } else {
                                            echo 'Not taken';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        if (isset($exam['percentage'])) {
                                            echo '<div class="progress mb-4">
                                                <div class="progress-bar bg-' . getProgressBarColor($exam['percentage']) . '" 
                                                     role="progressbar" style="width: ' . $exam['percentage'] . '%"
                                                     aria-valuenow="' . $exam['percentage'] . '" aria-valuemin="0" aria-valuemax="100">
                                                    ' . $exam['percentage'] . '%
                                                </div>
                                            </div>';
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        if (isset($exam['score'])) {
                                            $status = ($exam['percentage'] >= 50) ? 'Pass' : 'Fail';
                                            $badge_class = ($exam['percentage'] >= 50) ? 'text-success' : 'text-danger';
                                            echo '<span class="badge ' . $badge_class . '">' . $status . '</span>';
                                        } else {
                                            echo '<span class="badge text-warning">Not Attempted</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        if (isset($exam['score'])) {
                                            echo '<a href="view-result.php?id=' . $exam['id'] . '" class="btn btn-info btn-sm">View Result</a>';
                                        } else {
                                            // Check if exam is still available to take
                                            $now = new DateTime();
                                            $exam_time = new DateTime($exam['start_time']);
                                            $end_time = clone $exam_time;
                                            // Assuming exams are available for 24 hours after start time
                                            $end_time->modify('+24 hours');

                                            if ($now <= $end_time) {
                                                echo '<a href="../exam/take-exam.php?id=' . $exam['id'] . '" class="btn btn-success btn-sm">Take Exam</a>';
                                            } else {
                                                echo '<span class="badge text-secondary">Expired</span>';
                                            }
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center">No past exams for this course.</p>
            <?php endif; ?>
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

<script>
    $(document).ready(function() {
        $('#pastExamsTable').DataTable({
            order: [
                [1, 'desc']
            ], // Sort by date (descending)
            responsive: true
        });
    });
</script>