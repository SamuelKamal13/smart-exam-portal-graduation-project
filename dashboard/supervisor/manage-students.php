<?php
// Set page title and base URL for includes
$page_title = "Manage Students";
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
    $student_id = trim($_GET["id"]);
    $action = trim($_GET["action"]);

    // Validate student ID
    if (empty($student_id) || !is_numeric($student_id)) {
        $error_msg = "Invalid student ID.";
    } else {
        // Check if student exists
        $check_sql = "SELECT id FROM users WHERE id = ? AND role = 'student'";
        if ($stmt = mysqli_prepare($conn, $check_sql)) {
            mysqli_stmt_bind_param($stmt, "i", $student_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);

            if (mysqli_stmt_num_rows($stmt) != 1) {
                $error_msg = "Student not found.";
            }
            mysqli_stmt_close($stmt);
        }

        // Process based on action type
        if (empty($error_msg)) {
            switch ($action) {
                case "delete":
                    // Check if student has exam results
                    $check_results_sql = "SELECT COUNT(*) as count FROM results WHERE student_id = ?";
                    if ($stmt = mysqli_prepare($conn, $check_results_sql)) {
                        mysqli_stmt_bind_param($stmt, "i", $student_id);
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);
                        $row = mysqli_fetch_assoc($result);

                        if ($row["count"] > 0) {
                            $error_msg = "Cannot delete student as they have exam results. Try to delete his exams first.";
                        } else {
                            // Delete student
                            $delete_sql = "DELETE FROM users WHERE id = ?";
                            if ($stmt = mysqli_prepare($conn, $delete_sql)) {
                                mysqli_stmt_bind_param($stmt, "i", $student_id);

                                if (mysqli_stmt_execute($stmt)) {
                                    $success_msg = "Student deleted successfully.";
                                } else {
                                    $error_msg = "Error deleting student: " . mysqli_error($conn);
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
                        mysqli_stmt_bind_param($stmt, "si", $hashed_password, $student_id);

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

// Fetch students with optional search filter
$students = [];
$sql = "SELECT id, user_id, first_name, last_name, email, phone, created_at FROM users WHERE role = 'student'";

if (!empty($search_term)) {
    $sql .= " AND (user_id LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $search_param = "%" . $search_term . "%";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ssss", $search_param, $search_param, $search_param, $search_param);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $students[] = $row;
        }

        mysqli_stmt_close($stmt);
    }
} else {
    // No search term, fetch all students
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $students[] = $row;
    }
    mysqli_free_result($result);
}

// Include header
include_once "../../includes/header.php";
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Manage Students</h1>
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
            <h6 class="m-0 font-weight-bold text-primary">All Students</h6>
            <form class="form-inline" method="GET">
                <div class="input-group">
                    <input type="text" class="form-control" name="search" placeholder="Search students..." value="<?php echo htmlspecialchars($search_term); ?>">
                    <div class="input-group-append">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search fa-sm"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (count($students) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="studentsTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Registered On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['user_id']); ?></td>
                                    <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($student['created_at'])); ?></td>
                                    <td>
                                        <a href="student_profile.php?id=<?php echo $student['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#resetPasswordModal<?php echo $student['id']; ?>">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteStudentModal<?php echo $student['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>

                                <!-- Reset Password Modal -->
                                <div class="modal fade" id="resetPasswordModal<?php echo $student['id']; ?>" tabindex="-1" aria-labelledby="resetPasswordModalLabel<?php echo $student['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="resetPasswordModalLabel<?php echo $student['id']; ?>">Reset Password</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                Are you sure you want to reset the password for <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>?
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="button" class="btn btn-warning confirm-reset" data-student-id="<?php echo $student['id']; ?>">Reset Password</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Delete Student Modal -->
                                <div class="modal fade" id="deleteStudentModal<?php echo $student['id']; ?>" tabindex="-1" aria-labelledby="deleteStudentModalLabel<?php echo $student['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="deleteStudentModalLabel<?php echo $student['id']; ?>">Delete Student</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                Are you sure you want to delete <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>? This action cannot be undone.
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="button" class="btn btn-danger confirm-delete" data-student-id="<?php echo $student['id']; ?>">Delete</button>
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
                    <p class="lead">No students found<?php echo !empty($search_term) ? ' matching "' . htmlspecialchars($search_term) . '"' : ''; ?>.</p>
                    <?php if (!empty($search_term)): ?>
                        <a href="manage-students.php" class="btn btn-outline-primary">Clear Search</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Delete Student functionality
        const deleteButtons = document.querySelectorAll('.confirm-delete');

        if (deleteButtons) {
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const studentId = this.getAttribute('data-student-id');

                    // Create and submit form for deleting the student
                    const form = document.createElement('form');
                    form.method = 'GET';
                    form.action = '';

                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'delete';

                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'id';
                    idInput.value = studentId;

                    form.appendChild(actionInput);
                    form.appendChild(idInput);

                    document.body.appendChild(form);
                    form.submit();
                });
            });
        }

        // Reset Password functionality
        const resetButtons = document.querySelectorAll('.confirm-reset');

        if (resetButtons) {
            resetButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const studentId = this.getAttribute('data-student-id');

                    // Create and submit form for resetting password
                    const form = document.createElement('form');
                    form.method = 'GET';
                    form.action = '';

                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'reset_password';

                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'id';
                    idInput.value = studentId;

                    form.appendChild(actionInput);
                    form.appendChild(idInput);

                    document.body.appendChild(form);
                    form.submit();
                });
            });
        }
    });
</script>

<?php include_once "../../includes/footer.php"; ?>