<?php
// Set page title and base URL for includes
$page_title = "Manage Exams";
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

// Process delete operation
if (isset($_GET["action"]) && $_GET["action"] == "delete" && isset($_GET["id"]) && !empty($_GET["id"])) {
    // Prepare a delete statement
    $sql = "DELETE FROM exams WHERE id = ? AND created_by = ?";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        // Bind variables to the prepared statement as parameters
        mysqli_stmt_bind_param($stmt, "ii", $param_id, $param_trainer_id);

        // Set parameters
        $param_id = trim($_GET["id"]);
        $param_trainer_id = $trainer_id;

        // Attempt to execute the prepared statement
        if (mysqli_stmt_execute($stmt)) {
            // Records deleted successfully. Redirect to landing page
            header("location: manage-exams.php?success=1");
            exit();
        } else {
            echo "Oops! Something went wrong. Please try again later.";
        }
    }

    // Close statement
    mysqli_stmt_close($stmt);
}

// Get all exams created by the trainer
$exams = [];
$sql = "SELECT e.id, e.title, e.description, e.start_time, e.duration, e.total_marks, 
               c.title as course_title, 
               (SELECT COUNT(*) FROM exam_students WHERE exam_id = e.id) as student_count
        FROM exams e
        LEFT JOIN courses c ON e.course_id = c.id
        WHERE e.created_by = ?
        ORDER BY e.start_time DESC";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $trainer_id);

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $exams[] = $row;
        }
    }

    mysqli_stmt_close($stmt);
}

// Include header
include_once "../../includes/header.php";
?>

<div class="container-fluid">
    <h1 class="mt-4 mb-4">Manage Exams</h1>

    <?php if (isset($_GET["success"]) && $_GET["success"] == "1"): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Exam deleted successfully.
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">All Exams</h6>
            <a href="create-exam.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create New Exam
            </a>
        </div>
        <div class="card-body">
            <?php if (count($exams) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="examsTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Course</th>
                                <th>Date & Time</th>
                                <th>Duration</th>
                                <th>Total Marks</th>
                                <th>Students</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exams as $exam): ?>
                                <?php
                                $now = new DateTime();
                                $exam_time = new DateTime($exam['start_time']);
                                $end_time = clone $exam_time;
                                $end_time->add(new DateInterval('PT' . $exam['duration'] . 'M'));

                                if ($now < $exam_time) {
                                    $status = "Upcoming";
                                    $status_class = "badge-info";
                                } elseif ($now >= $exam_time && $now <= $end_time) {
                                    $status = "In Progress";
                                    $status_class = "badge-warning";
                                } else {
                                    $status = "Completed";
                                    $status_class = "badge-success";
                                }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                    <td><?php echo htmlspecialchars($exam['course_title'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($exam['start_time'])); ?></td>
                                    <td><?php echo $exam['duration']; ?> min</td>
                                    <td><?php echo $exam['total_marks']; ?></td>
                                    <td><?php echo $exam['student_count']; ?></td>
                                    <td><span class="text<?php echo $status_class; ?>"><?php echo $status; ?></span></td>
                                    <td>
                                        <a href="view-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="manage-questions.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-question"></i>
                                        </a>
                                        <a href="assign-students.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-success btn-sm">
                                            <i class="fas fa-users"></i>
                                        </a>
                                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteExamModal<?php echo $exam['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>

                                <!-- Delete Exam Modal -->
                                <div class="modal fade" id="deleteExamModal<?php echo $exam['id']; ?>" tabindex="-1" aria-labelledby="deleteExamModalLabel<?php echo $exam['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="deleteExamModalLabel<?php echo $exam['id']; ?>">Confirm Delete</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete this exam? This action cannot be undone.</p>
                                                <p><strong>Exam Title:</strong> <?php echo htmlspecialchars($exam['title']); ?></p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <a href="manage-exams.php?action=delete&id=<?php echo $exam['id']; ?>" class="btn btn-danger">Delete Exam</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-gray-500">No exams found. Create your first exam now!</p>
                    <a href="create-exam.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create New Exam
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- DataTables JavaScript -->
<script>
    $(document).ready(function() {
        $('#examsTable').DataTable({
            order: [
                [2, 'desc']
            ], // Sort by date (descending)
            responsive: true
        });
    });
</script>

<?php
// Include footer
include_once "../../includes/footer.php";
?>