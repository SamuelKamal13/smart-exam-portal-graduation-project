<?php
// Set page title and base URL for includes
$page_title = "Manage Courses";
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
    $sql = "DELETE FROM courses WHERE id = ? AND trainer_id = ?";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        // Bind variables to the prepared statement as parameters
        mysqli_stmt_bind_param($stmt, "ii", $param_id, $param_trainer_id);

        // Set parameters
        $param_id = trim($_GET["id"]);
        $param_trainer_id = $trainer_id;

        // Attempt to execute the prepared statement
        if (mysqli_stmt_execute($stmt)) {
            // Records deleted successfully. Redirect to landing page
            header("location: manage-courses.php?success=deleted");
            exit();
        } else {
            echo "Oops! Something went wrong. Please try again later.";
        }
    }

    // Close statement
    mysqli_stmt_close($stmt);
}

// Get all courses created by the trainer
$courses = [];
$sql = "SELECT c.id, c.title, c.description, c.created_at, 
               (SELECT COUNT(*) FROM course_registrations WHERE course_id = c.id) as student_count,
               (SELECT COUNT(*) FROM exams WHERE course_id = c.id) as exam_count
        FROM courses c
        WHERE c.trainer_id = ?
        ORDER BY c.created_at DESC";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $trainer_id);

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $courses[] = $row;
        }
    }

    mysqli_stmt_close($stmt);
}

// Include header
include_once "../../includes/header.php";
?>

<div class="container-fluid">
    <h1 class="mt-4 mb-4">Manage Courses</h1>

    <?php if (isset($_GET["success"])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php if ($_GET["success"] == "created"): ?>
                Course created successfully.
            <?php elseif ($_GET["success"] == "deleted"): ?>
                Course deleted successfully.
            <?php elseif ($_GET["success"] == "updated"): ?>
                Course updated successfully.
            <?php endif; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">All Courses</h6>
            <a href="create-course.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create New Course
            </a>
        </div>
        <div class="card-body">
            <?php if (count($courses) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="coursesTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Students</th>
                                <th>Exams</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($course['title']); ?></td>
                                    <td>
                                        <?php
                                        $desc = htmlspecialchars($course['description']);
                                        echo (strlen($desc) > 100) ? substr($desc, 0, 100) . '...' : $desc;
                                        ?>
                                    </td>
                                    <td><?php echo $course['student_count']; ?></td>
                                    <td><?php echo $course['exam_count']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($course['created_at'])); ?></td>
                                    <td>
                                        <a href="view-course.php?id=<?php echo $course['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit-course.php?id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="manage-students.php?course_id=<?php echo $course['id']; ?>" class="btn btn-success btn-sm">
                                            <i class="fas fa-users"></i>
                                        </a>
                                        <a href="create-exam.php?course_id=<?php echo $course['id']; ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-file-alt"></i>
                                        </a>
                                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteCourseModal<?php echo $course['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>

                                <!-- Delete Course Modal -->
                                <div class="modal fade" id="deleteCourseModal<?php echo $course['id']; ?>" tabindex="-1" aria-labelledby="deleteCourseModalLabel<?php echo $course['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="deleteCourseModalLabel<?php echo $course['id']; ?>">Confirm Delete</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete this course? This will also delete all associated exams and student registrations.</p>
                                                <p><strong>Course Title:</strong> <?php echo htmlspecialchars($course['title']); ?></p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <a href="manage-courses.php?action=delete&id=<?php echo $course['id']; ?>" class="btn btn-danger">Delete Course</a>
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
                    <p class="text-gray-500">No courses found. Create your first course now!</p>
                    <a href="create-course.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create New Course
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Course Management</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-6">
                    <h5><i class="fas fa-graduation-cap text-primary"></i> Course Features</h5>
                    <ul>
                        <li><strong>Create Exams</strong> - Add assessments to your courses</li>
                        <li><strong>Manage Students</strong> - Add or remove students from your courses</li>
                        <li><strong>Course Materials</strong> - Upload lecture notes and resources</li>
                        <li><strong>Track Progress</strong> - Monitor student performance in your courses</li>
                    </ul>
                </div>
                <div class="col-lg-6">
                    <h5><i class="fas fa-chart-line text-success"></i> Quick Stats</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Total Courses</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($courses); ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-book fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Total Students</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                <?php
                                                $total_students = 0;
                                                foreach ($courses as $course) {
                                                    $total_students += $course['student_count'];
                                                }
                                                echo $total_students;
                                                ?>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-users fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- DataTables JavaScript -->
<script>
    $(document).ready(function() {
        $('#coursesTable').DataTable({
            order: [
                [4, 'desc']
            ], // Sort by created date (descending)
            responsive: true
        });
    });
</script>

<?php
// Include footer
include_once "../../includes/footer.php";
?>