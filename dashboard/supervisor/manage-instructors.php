<?php
// Set page title and base URL for includes
$page_title = "Manage Instructors";
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

// Define variables and initialize with empty values
$search_term = $success_msg = $error_msg = "";

// Process action requests (delete, reset password)
if (isset($_GET["action"]) && isset($_GET["id"])) {
    $instructor_id = trim($_GET["id"]);
    $action = trim($_GET["action"]);

    // Validate instructor ID
    if (empty($instructor_id) || !is_numeric($instructor_id)) {
        $error_msg = "Invalid instructor ID.";
    } else {
        // Check if instructor exists
        $check_sql = "SELECT id FROM users WHERE id = ? AND role = 'trainer'";
        if ($stmt = mysqli_prepare($conn, $check_sql)) {
            mysqli_stmt_bind_param($stmt, "i", $instructor_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);

            if (mysqli_stmt_num_rows($stmt) != 1) {
                $error_msg = "Instructor not found.";
            }
            mysqli_stmt_close($stmt);
        }

        // Process based on action type
        if (empty($error_msg)) {
            switch ($action) {
                case "delete":
                    // Check if instructor has courses
                    $check_courses_sql = "SELECT COUNT(*) as count FROM courses WHERE trainer_id = ?";
                    if ($stmt = mysqli_prepare($conn, $check_courses_sql)) {
                        mysqli_stmt_bind_param($stmt, "i", $instructor_id);
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);
                        $row = mysqli_fetch_assoc($result);

                        if ($row["count"] > 0) {
                            $error_msg = "Cannot delete instructor as they have courses assigned. Please reassign or delete their courses first.";
                        } else {
                            // Delete instructor
                            $delete_sql = "DELETE FROM users WHERE id = ?";
                            if ($stmt = mysqli_prepare($conn, $delete_sql)) {
                                mysqli_stmt_bind_param($stmt, "i", $instructor_id);

                                if (mysqli_stmt_execute($stmt)) {
                                    $success_msg = "Instructor deleted successfully.";
                                } else {
                                    $error_msg = "Error deleting instructor: " . mysqli_error($conn);
                                }
                                mysqli_stmt_close($stmt);
                            }
                        }
                    }
                    break;

                case "reset_password":
                    // Generate a random password
                    $new_password = bin2hex(random_bytes(4)); // 8 characters
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                    // Update password
                    $update_pwd_sql = "UPDATE users SET password = ? WHERE id = ?";
                    if ($stmt = mysqli_prepare($conn, $update_pwd_sql)) {
                        mysqli_stmt_bind_param($stmt, "si", $hashed_password, $instructor_id);

                        if (mysqli_stmt_execute($stmt)) {
                            $success_msg = "Password reset successfully. New password: " . $new_password;
                        } else {
                            $error_msg = "Error resetting password: " . mysqli_error($conn);
                        }
                        mysqli_stmt_close($stmt);
                    }
                    break;
            }
        }
    }
}

// Process search request
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET["search"])) {
    $search_term = trim($_GET["search"]);
}

// Fetch instructors with optional search filter
$instructors = [];
$sql = "SELECT u.id, u.user_id, u.first_name, u.last_name, u.email, u.phone, u.created_at, 
               COUNT(DISTINCT c.id) as courses_count, COUNT(DISTINCT e.id) as exams_count 
        FROM users u 
        LEFT JOIN courses c ON u.id = c.trainer_id 
        LEFT JOIN exams e ON c.id = e.course_id 
        WHERE u.role = 'trainer'";

if (!empty($search_term)) {
    $sql .= " AND (u.user_id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%" . $search_term . "%";
    $sql .= " GROUP BY u.id";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ssss", $search_param, $search_param, $search_param, $search_param);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $instructors[] = $row;
        }

        mysqli_stmt_close($stmt);
    }
} else {
    // No search term, fetch all instructors
    $sql .= " GROUP BY u.id";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $instructors[] = $row;
    }
    mysqli_free_result($result);
}

// Include header
include_once "../../includes/header.php";
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Manage Instructors</h1>
        <a href="generate-codes.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus fa-sm"></i> Generate Invitation Codes
        </a>
    </div>

    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success_msg; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error_msg; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">All Instructors</h6>
            <form class="form-inline" method="GET">
                <div class="input-group">
                    <input type="text" class="form-control" name="search" placeholder="Search instructors..." value="<?php echo htmlspecialchars($search_term); ?>">
                    <div class="input-group-append">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search fa-sm"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (count($instructors) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="instructorsTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Courses</th>
                                <th>Exams</th>
                                <th>Registered On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($instructors as $instructor): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($instructor['user_id']); ?></td>
                                    <td><?php echo htmlspecialchars($instructor['first_name'] . ' ' . $instructor['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($instructor['email']); ?></td>
                                    <td><?php echo htmlspecialchars($instructor['phone'] ?? 'N/A'); ?></td>
                                    <td><?php echo $instructor['courses_count']; ?></td>
                                    <td><?php echo $instructor['exams_count']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($instructor['created_at'])); ?></td>
                                    <td>
                                        <a href="instructor-profile.php?id=<?php echo $instructor['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#resetPasswordModal<?php echo $instructor['id']; ?>">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteInstructorModal<?php echo $instructor['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>

                                <!-- Reset Password Modal -->
                                <!-- Reset Password Modal -->
                                <div class="modal fade" id="resetPasswordModal<?php echo $instructor['id']; ?>" tabindex="-1" aria-labelledby="resetPasswordModalLabel<?php echo $instructor['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="resetPasswordModalLabel<?php echo $instructor['id']; ?>">Reset Password</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                Are you sure you want to reset the password for <?php echo htmlspecialchars($instructor['first_name'] . ' ' . $instructor['last_name']); ?>?
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <a href="?action=reset_password&id=<?php echo $instructor['id']; ?>" class="btn btn-warning">Reset Password</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Delete Instructor Modal -->
                                <div class="modal fade" id="deleteInstructorModal<?php echo $instructor['id']; ?>" tabindex="-1" aria-labelledby="deleteInstructorModalLabel<?php echo $instructor['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="deleteInstructorModalLabel<?php echo $instructor['id']; ?>">Delete Instructor</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                Are you sure you want to delete <?php echo htmlspecialchars($instructor['first_name'] . ' ' . $instructor['last_name']); ?>? This action cannot be undone.
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <a href="?action=delete&id=<?php echo $instructor['id']; ?>" class="btn btn-danger">Delete</a>
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
                    <p class="lead">No instructors found<?php echo !empty($search_term) ? ' matching "' . htmlspecialchars($search_term) . '"' : ''; ?>.</p>
                    <?php if (!empty($search_term)): ?>
                        <a href="manage-instructors.php" class="btn btn-outline-primary">Clear Search</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once "../../includes/footer.php"; ?>