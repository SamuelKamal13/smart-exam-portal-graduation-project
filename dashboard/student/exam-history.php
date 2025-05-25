<?php
// Set page title and base URL for includes
$page_title = "Exam History";
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

// Get all exams assigned to the student (including those not taken)
$exam_history = [];
$sql = "SELECT es.exam_id, es.has_viewed, es.has_attempted, es.auto_graded,
               e.id, e.title, e.description, e.start_time, e.duration, e.total_marks,
               c.title AS course_title,
               r.id AS result_id, r.score, r.percentage, r.submission_time
        FROM exam_students es
        JOIN exams e ON es.exam_id = e.id
        LEFT JOIN courses c ON e.course_id = c.id
        LEFT JOIN results r ON r.exam_id = es.exam_id AND r.student_id = es.student_id
        WHERE es.student_id = ?
        ORDER BY e.start_time DESC";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $exam_history[] = $row;
        }
    }

    mysqli_stmt_close($stmt);
}

// Function to determine progress bar color based on percentage
function getProgressBarColor($percentage)
{
    if ($percentage >= 80) {
        return "success";
    } elseif ($percentage >= 60) {
        return "info";
    } elseif ($percentage >= 40) {
        return "warning";
    } else {
        return "danger";
    }
}

// Function to determine exam status
function getExamStatus($exam)
{
    if ($exam['result_id'] !== null) {
        return ['status' => 'completed', 'badge' => 'success', 'text' => 'Completed'];
    } elseif ($exam['auto_graded']) {
        return ['status' => 'missed', 'badge' => 'danger', 'text' => 'Missed'];
    } elseif ($exam['has_attempted']) {
        return ['status' => 'in_progress', 'badge' => 'warning', 'text' => 'In Progress'];
    } elseif ($exam['has_viewed']) {
        return ['status' => 'viewed', 'badge' => 'info', 'text' => 'Viewed'];
    } else {
        // Check if exam is in the future, ongoing, or past
        $start_time = strtotime($exam['start_time']);
        $end_time = $start_time + ($exam['duration'] * 60);
        $now = time();

        if ($now < $start_time) {
            return ['status' => 'upcoming', 'badge' => 'primary', 'text' => 'Upcoming'];
        } elseif ($now <= $end_time) {
            return ['status' => 'available', 'badge' => 'warning', 'text' => 'Available Now'];
        } else {
            return ['status' => 'missed', 'badge' => 'danger', 'text' => 'Missed'];
        }
    }
}

// Include header
include_once "../../includes/header.php";
?>

<div class="container-fluid">
    <h1 class="mt-4 mb-4">Exam History</h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">All Your Exams</h6>
        </div>
        <div class="card-body">
            <?php if (count($exam_history) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="examHistoryTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Exam Title</th>
                                <th>Course</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Score</th>
                                <th>Percentage</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exam_history as $exam):
                                $status = getExamStatus($exam);
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                    <td><?php echo htmlspecialchars($exam['course_title'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php
                                        if ($exam['result_id'] !== null) {
                                            echo date('M d, Y h:i A', strtotime($exam['submission_time']));
                                        } else {
                                            echo date('M d, Y h:i A', strtotime($exam['start_time']));
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge text-<?php echo $status['badge']; ?>">
                                            <?php echo $status['text']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($exam['result_id'] !== null): ?>
                                            <?php echo $exam['score'] . '/' . $exam['total_marks']; ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($exam['result_id'] !== null): ?>
                                            <div class="progress">
                                                <div class="progress-bar bg-<?php echo getProgressBarColor($exam['percentage']); ?>"
                                                    role="progressbar" style="width: <?php echo $exam['percentage']; ?>%"
                                                    aria-valuenow="<?php echo $exam['percentage']; ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <?php echo $exam['percentage']; ?>%
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($exam['result_id'] !== null): ?>
                                            <a href="view-result.php?id=<?php echo $exam['result_id']; ?>" class="btn btn-info btn-sm">
                                                <i class="fas fa-eye"></i> View Details
                                            </a>
                                        <?php elseif ($status['status'] == 'available'): ?>
                                            <a href="../exams/take-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-success btn-sm">
                                                <i class="fas fa-pen"></i> Take Exam
                                            </a>
                                        <?php elseif ($status['status'] == 'upcoming'): ?>
                                            <button class="btn btn-secondary btn-sm" disabled>
                                                <i class="fas fa-clock"></i> Not Available Yet
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-danger btn-sm" disabled>
                                                <i class="fas fa-times-circle"></i> Missed
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> You haven't been assigned any exams yet.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add DataTables JS for better table functionality -->
<script>
    $(document).ready(function() {
        $('#examHistoryTable').DataTable({
            order: [
                [2, 'desc']
            ], // Sort by date (descending)
            responsive: true,
            language: {
                search: "Search exams:",
                lengthMenu: "Show _MENU_ exams per page",
                info: "Showing _START_ to _END_ of _TOTAL_ exams",
                emptyTable: "No exams available"
            }
        });
    });
</script>

<?php
// Include footer
include_once "../../includes/footer.php";
?>