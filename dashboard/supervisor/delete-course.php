<?php
// Set base URL for includes
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

// Check if course ID is provided and confirmation is yes
if (!isset($_GET["id"]) || empty($_GET["id"])) {
    header("location: index.php");
    exit;
}

$course_id = $_GET["id"];
$confirm = $_GET["confirm"] ?? "no";

// If not confirmed, show confirmation page
if ($confirm !== "yes") {
    // Set page title
    $page_title = "Confirm Delete Course";

    // Get course details
    $course = null;
    $sql = "SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as trainer_name 
            FROM courses c
            LEFT JOIN users u ON c.trainer_id = u.id
            WHERE c.id = ?";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $course_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $course = $row;
            } else {
                // Course not found
                header("location: index.php");
                exit;
            }
        }
        mysqli_stmt_close($stmt);
    }

    // Include header
    include "../../includes/header.php";
    include "../../includes/navbar.php";
?>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h4 class="mb-0">Confirm Course Deletion</h4>
            </div>
            <div class="card-body">
                <div class="alert alert-danger">
                    <h5>Warning: This action cannot be undone!</h5>
                    <p>You are about to delete the course: <strong><?php echo htmlspecialchars($course["title"]); ?></strong></p>
                    <p>This will permanently delete:</p>
                    <ul>
                        <li>All course topics</li>
                        <li>All exams associated with this course</li>
                        <li>All questions in those exams</li>
                        <li>All student enrollments and exam results</li>
                    </ul>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <a href="view-course.php?id=<?php echo $course_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Cancel
                    </a>
                    <a href="delete-course.php?id=<?php echo $course_id; ?>&confirm=yes" class="btn btn-danger">
                        <i class="fas fa-trash-alt me-2"></i>Yes, Delete Course
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php
    // Include footer
    include "../../includes/footer.php";
} else {
    // Confirmation is yes, proceed with deletion

    // The database has CASCADE constraints, so deleting the course will automatically
    // delete related records in other tables
    $delete_sql = "DELETE FROM courses WHERE id = ?";

    if ($delete_stmt = mysqli_prepare($conn, $delete_sql)) {
        mysqli_stmt_bind_param($delete_stmt, "i", $course_id);

        if (mysqli_stmt_execute($delete_stmt)) {
            // Redirect to courses page with success message
            $_SESSION["success_msg"] = "Course has been deleted successfully.";
            header("location: index.php");
            exit;
        } else {
            // Error occurred
            $_SESSION["error_msg"] = "Error deleting course: " . mysqli_error($conn);
            header("location: view-course.php?id=" . $course_id);
            exit;
        }

        mysqli_stmt_close($delete_stmt);
    }
}
?>