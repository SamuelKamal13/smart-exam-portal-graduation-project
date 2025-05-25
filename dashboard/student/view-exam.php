<?php
// Set page title and base URL for includes
$page_title = "View Exam";
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

// Check if exam ID is provided
if (!isset($_GET["id"]) || empty($_GET["id"])) {
    header("location: exams.php");
    exit;
}

$exam_id = $_GET["id"];

// Fetch exam data and verify student is assigned to this exam
$exam = null;
$sql = "SELECT e.*, c.title as course_title, c.id as course_id 
        FROM exams e 
        JOIN exam_students es ON e.id = es.exam_id 
        JOIN courses c ON e.course_id = c.id 
        WHERE e.id = ? AND es.student_id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $exam_id, $student_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) == 1) {
            $exam = mysqli_fetch_assoc($result);
        } else {
            // Exam not found or student not assigned
            header("location: exams.php");
            exit;
        }
    } else {
        echo "Oops! Something went wrong. Please try again later.";
    }
    mysqli_stmt_close($stmt);
}

// Check if student has already taken this exam
$has_taken_exam = false;
$result_id = null;
$check_sql = "SELECT id FROM results WHERE exam_id = ? AND student_id = ?";
if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
    mysqli_stmt_bind_param($check_stmt, "ii", $exam_id, $student_id);
    if (mysqli_stmt_execute($check_stmt)) {
        $check_result = mysqli_stmt_get_result($check_stmt);
        if (mysqli_num_rows($check_result) > 0) {
            $has_taken_exam = true;
            $result_row = mysqli_fetch_assoc($check_result);
            $result_id = $result_row['id'];
        }
    }
    mysqli_stmt_close($check_stmt);
}

// Calculate exam status
$now = new DateTime();
$start_time = new DateTime($exam["start_time"]);
$end_time = clone $start_time;
$end_time->add(new DateInterval('PT' . $exam["duration"] . 'M'));

if ($now < $start_time) {
    $status = "upcoming";
    $status_text = "Upcoming";
    $status_class = "text-info";
} elseif ($now >= $start_time && $now <= $end_time) {
    $status = "in_progress";
    $status_text = "In Progress";
    $status_class = "text-warning";
} else {
    $status = "completed";
    $status_text = "Completed";
    $status_class = "text-secondary";
}

// Include header
include_once "../../includes/header.php";
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($exam["title"]); ?></h1>
        <a href="exams.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left fa-sm"></i> Back to Exams
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Exam Details Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Exam Details</h6>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h5>Description</h5>
                        <p><?php echo nl2br(htmlspecialchars($exam["description"])); ?></p>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-4">
                                <h5>Exam Information</h5>
                                <table class="table table-bordered">
                                    <tr>
                                        <th>Course</th>
                                        <td><?php echo htmlspecialchars($exam["course_title"]); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Start Time</th>
                                        <td><?php echo date('F d, Y h:i A', strtotime($exam["start_time"])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>End Time</th>
                                        <td><?php echo $end_time->format('F d, Y h:i A'); ?></td>
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

                        <div class="col-md-6">
                            <div class="mb-4">
                                <h5>Instructions</h5>
                                <ul class="list-group">
                                    <li class="list-group-item">Read all questions carefully before answering.</li>
                                    <li class="list-group-item">Once you start the exam, the timer cannot be paused.</li>
                                    <li class="list-group-item">Submit your answers before the time expires.</li>
                                    <li class="list-group-item">Do not refresh the page during the exam.</li>
                                    <li class="list-group-item">Ensure you have a stable internet connection.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Exam Status Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Exam Status</h6>
                </div>
                <div class="card-body text-center">
                    <h4 class="<?php echo $status_class; ?> mb-3"><?php echo $status_text; ?></h4>

                    <?php if ($has_taken_exam): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-check-circle"></i> You have already completed this exam.
                        </div>
                        <a href="view-result.php?id=<?php echo $result_id; ?>" class="btn btn-success btn-block mb-3">
                            <i class="fas fa-chart-bar"></i> View Your Results
                        </a>
                    <?php elseif ($status == "upcoming"): ?>
                        <p>Exam starts in:</p>
                        <div id="countdown" class="h4 mb-3"></div>
                        <div class="alert alert-warning">
                            <i class="fas fa-clock"></i> Please return when the exam starts.
                        </div>
                    <?php elseif ($status == "in_progress"): ?>
                        <p>Time remaining:</p>
                        <div id="countdown" class="h4 mb-3"></div>
                        <a href="take-exam.php?id=<?php echo $exam_id; ?>" class="btn btn-primary btn-block mb-3">
                            <i class="fas fa-pencil-alt"></i> Start Exam Now
                        </a>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> Once started, you must complete the exam.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-secondary">
                            <i class="fas fa-calendar-times"></i> This exam has ended.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Links Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Links</h6>
                </div>
                <div class="card-body">
                    <a href="exams.php" class="btn btn-info btn-block mb-2">
                        <i class="fas fa-calendar-alt"></i> All Exams
                    </a>
                    <a href="index.php" class="btn btn-secondary btn-block">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Countdown timer
    <?php if ($status == "upcoming" || $status == "in_progress"): ?>
        var countDownDate = new Date("<?php echo ($status == 'upcoming') ? $start_time->format('Y-m-d H:i:s') : $end_time->format('Y-m-d H:i:s'); ?>").getTime();

        var x = setInterval(function() {
            var now = new Date().getTime();
            var distance = countDownDate - now;

            var days = Math.floor(distance / (1000 * 60 * 60 * 24));
            var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            var seconds = Math.floor((distance % (1000 * 60)) / 1000);

            var countdownText = "";
            if (days > 0) countdownText += days + "d ";
            countdownText += hours + "h " + minutes + "m " + seconds + "s";

            document.getElementById("countdown").innerHTML = countdownText;

            if (distance < 0) {
                clearInterval(x);
                document.getElementById("countdown").innerHTML = "0h 0m 0s";
                // Reload page to update status
                location.reload();
            }
        }, 1000);
    <?php endif; ?>
</script>

<?php
// Include footer
include_once "../../includes/footer.php";
?>