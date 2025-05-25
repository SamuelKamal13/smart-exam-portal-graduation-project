<?php
// Set page title and base URL for includes
$page_title = "Student Profile Management";
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

// Process form submission for edit/delete operations
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["action"])) {
        $student_id = isset($_POST["student_id"]) ? (int)$_POST["student_id"] : 0;

        // Validate student ID
        if ($student_id <= 0) {
            $error_msg = "Invalid student ID provided.";
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
            if (!isset($error_msg)) {
                switch ($_POST["action"]) {
                    case "update":
                        // Update student information
                        $first_name = trim($_POST["first_name"]);
                        $last_name = trim($_POST["last_name"]);
                        $email = trim($_POST["email"]);
                        $phone = trim($_POST["phone"]);

                        // Validate inputs
                        if (empty($first_name) || empty($last_name) || empty($email)) {
                            $error_msg = "Please fill all required fields.";
                        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $error_msg = "Please enter a valid email address.";
                        } else {
                            // Update student record
                            $update_sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?";
                            if ($stmt = mysqli_prepare($conn, $update_sql)) {
                                mysqli_stmt_bind_param($stmt, "ssssi", $first_name, $last_name, $email, $phone, $student_id);

                                if (mysqli_stmt_execute($stmt)) {
                                    $success_msg = "Student profile updated successfully.";
                                } else {
                                    $error_msg = "Error updating profile: " . mysqli_error($conn);
                                }
                                mysqli_stmt_close($stmt);
                            }
                        }
                        break;

                    case "delete":
                        // Delete student account
                        // First check if student has exam results
                        $check_results_sql = "SELECT COUNT(*) as count FROM results WHERE student_id = ?";
                        if ($stmt = mysqli_prepare($conn, $check_results_sql)) {
                            mysqli_stmt_bind_param($stmt, "i", $student_id);
                            mysqli_stmt_execute($stmt);
                            $result = mysqli_stmt_get_result($stmt);
                            $row = mysqli_fetch_assoc($result);

                            if ($row["count"] > 0) {
                                // Student has exam results, show warning
                                $warning_msg = "Cannot delete student as they have exam results. Try to delete his exams first.";
                            } else {
                                // Delete student
                                $delete_sql = "DELETE FROM users WHERE id = ?";
                                if ($stmt = mysqli_prepare($conn, $delete_sql)) {
                                    mysqli_stmt_bind_param($stmt, "i", $student_id);

                                    if (mysqli_stmt_execute($stmt)) {
                                        $success_msg = "Student deleted successfully.";
                                        // Redirect to reports page after successful deletion
                                        header("location: reports.php?type=student_performance");
                                        exit;
                                    } else {
                                        $error_msg = "Error deleting student: " . mysqli_error($conn);
                                    }
                                    mysqli_stmt_close($stmt);
                                }
                            }
                        }
                        break;

                    case "delete_exam_result":
                        // Delete a specific exam result
                        $result_id = isset($_POST["result_id"]) ? (int)$_POST["result_id"] : 0;

                        if ($result_id <= 0) {
                            $error_msg = "Invalid result ID provided.";
                        } else {
                            // Verify the result belongs to this student
                            $check_result_sql = "SELECT id FROM results WHERE id = ? AND student_id = ?";
                            if ($stmt = mysqli_prepare($conn, $check_result_sql)) {
                                mysqli_stmt_bind_param($stmt, "ii", $result_id, $student_id);
                                mysqli_stmt_execute($stmt);
                                mysqli_stmt_store_result($stmt);

                                if (mysqli_stmt_num_rows($stmt) != 1) {
                                    $error_msg = "Exam result not found for this student.";
                                }
                                mysqli_stmt_close($stmt);
                            }

                            if (!isset($error_msg)) {
                                // Delete the exam result
                                $delete_result_sql = "DELETE FROM results WHERE id = ?";
                                if ($stmt = mysqli_prepare($conn, $delete_result_sql)) {
                                    mysqli_stmt_bind_param($stmt, "i", $result_id);

                                    if (mysqli_stmt_execute($stmt)) {
                                        $success_msg = "Exam result deleted successfully.";
                                    } else {
                                        $error_msg = "Error deleting exam result: " . mysqli_error($conn);
                                    }
                                    mysqli_stmt_close($stmt);
                                }
                            }
                        }
                        break;

                    case "delete_multiple_results":
                        // Delete multiple exam results
                        if (!isset($_POST["result_ids"]) || !is_array($_POST["result_ids"]) || empty($_POST["result_ids"])) {
                            $error_msg = "No exam results selected for deletion.";
                        } else {
                            $result_ids = array_map('intval', $_POST["result_ids"]);
                            $deleted_count = 0;
                            $error_count = 0;

                            // Begin transaction
                            mysqli_begin_transaction($conn);

                            try {
                                foreach ($result_ids as $result_id) {
                                    // Verify the result belongs to this student
                                    $check_result_sql = "SELECT id FROM results WHERE id = ? AND student_id = ?";
                                    if ($stmt = mysqli_prepare($conn, $check_result_sql)) {
                                        mysqli_stmt_bind_param($stmt, "ii", $result_id, $student_id);
                                        mysqli_stmt_execute($stmt);
                                        mysqli_stmt_store_result($stmt);

                                        if (mysqli_stmt_num_rows($stmt) == 1) {
                                            mysqli_stmt_close($stmt);

                                            // Delete the exam result
                                            $delete_result_sql = "DELETE FROM results WHERE id = ?";
                                            if ($stmt = mysqli_prepare($conn, $delete_result_sql)) {
                                                mysqli_stmt_bind_param($stmt, "i", $result_id);

                                                if (mysqli_stmt_execute($stmt)) {
                                                    $deleted_count++;
                                                } else {
                                                    $error_count++;
                                                }
                                                mysqli_stmt_close($stmt);
                                            }
                                        } else {
                                            mysqli_stmt_close($stmt);
                                            $error_count++;
                                        }
                                    }
                                }

                                // Commit transaction
                                mysqli_commit($conn);

                                if ($deleted_count > 0) {
                                    $success_msg = "Successfully deleted $deleted_count exam result(s).";
                                    if ($error_count > 0) {
                                        $success_msg .= " $error_count result(s) could not be deleted.";
                                    }
                                } else {
                                    $error_msg = "Failed to delete any exam results.";
                                }
                            } catch (Exception $e) {
                                // Rollback transaction on error
                                mysqli_rollback($conn);
                                $error_msg = "Error: " . $e->getMessage();
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
}

// Get student ID from URL parameter
$student_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

// Fetch student data if ID is provided
$student = null;
if ($student_id > 0) {
    $sql = "SELECT id, user_id, first_name, last_name, email, phone, created_at 
            FROM users 
            WHERE id = ? AND role = 'student'";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $student_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if (mysqli_num_rows($result) == 1) {
                $student = mysqli_fetch_assoc($result);
            }
        }
        mysqli_stmt_close($stmt);
    }
}

// Get student exam results
$exam_results = [];
if ($student) {
    $sql = "SELECT r.id, e.title as exam_title, c.title as course_title, 
            r.score, r.total_marks, r.percentage, r.submission_time
            FROM results r
            JOIN exams e ON r.exam_id = e.id
            LEFT JOIN courses c ON e.course_id = c.id
            WHERE r.student_id = ?
            ORDER BY r.submission_time DESC";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $student_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $exam_results[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }

    // Calculate overall statistics
    $total_exams = count($exam_results);
    $total_score = 0;
    $total_marks = 0;
    $exams_passed = 0;

    foreach ($exam_results as $result) {
        $total_score += $result["score"];
        $total_marks += $result["total_marks"];
        if ($result["percentage"] >= 60) {
            $exams_passed++;
        }
    }

    $overall_percentage = ($total_marks > 0) ? ($total_score / $total_marks) * 100 : 0;
}

// Include header
include_once "../../includes/header.php";
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Student Profile Management</h1>
        <a href="reports.php?type=student_performance" class="btn btn-sm btn-primary">
            <i class="fas fa-arrow-left fa-sm"></i> Back to Student Reports
        </a>
    </div>

    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle mr-2"></i><?php echo $error_msg; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($success_msg)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle mr-2"></i><?php echo $success_msg; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($warning_msg)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $warning_msg; ?>
        </div>
    <?php endif; ?>

    <?php if (!$student && $student_id > 0): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle mr-2"></i>Student not found.
        </div>
        <div class="text-center mt-4">
            <a href="reports.php?type=student_performance" class="btn btn-primary">
                <i class="fas fa-arrow-left mr-2"></i>Return to Student Reports
            </a>
        </div>
    <?php elseif (!$student): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle mr-2"></i>Please select a student from the Student Performance Report.
        </div>
        <div class="text-center mt-4">
            <a href="reports.php?type=student_performance" class="btn btn-primary">
                <i class="fas fa-list mr-2"></i>View Student Reports
            </a>
        </div>
    <?php else: ?>
        <!-- Student Profile Card -->
        <div class="row">
            <div class="col-lg-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Student Information</h6>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                            <input type="hidden" name="action" value="update">

                            <div class="form-group">
                                <label>Student ID</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($student['user_id']); ?>" readonly>
                            </div>

                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($student['email']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Phone</label>
                                <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($student['phone']); ?>">
                            </div>

                            <div class="form-group">
                                <label>Registered On</label>
                                <input type="text" class="form-control" value="<?php echo date('F j, Y', strtotime($student['created_at'])); ?>" readonly>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-save mr-2"></i>Save Changes
                            </button>
                        </form>

                        <hr>

                        <form method="post" action="" class="mt-3" id="resetPasswordForm">
                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                            <input type="hidden" name="action" value="reset_password">
                            <button type="button" class="btn btn-warning btn-block" id="resetPasswordBtn">
                                <i class="fas fa-key mr-2"></i>Reset Password
                            </button>
                        </form>

                        <form method="post" action="" class="mt-3" id="deleteStudentForm">
                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="button" class="btn btn-danger btn-block" id="deleteStudentBtn">
                                <i class="fas fa-trash mr-2"></i>Delete Student
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <!-- Performance Summary -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Performance Summary</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-4">
                                <div class="card border-left-primary shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Exams</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_exams; ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3 mb-4">
                                <div class="card border-left-success shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Exams Passed</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $exams_passed; ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3 mb-4">
                                <div class="card border-left-info shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Pass Rate</div>
                                                <div class="row no-gutters align-items-center">
                                                    <div class="col-auto">
                                                        <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800">
                                                            <?php echo $total_exams > 0 ? round(($exams_passed / $total_exams) * 100) : 0; ?>%
                                                        </div>
                                                    </div>
                                                    <div class="col">
                                                        <div class="progress progress-sm mr-2">
                                                            <div class="progress-bar bg-info" role="progressbar"
                                                                style="width: <?php echo $total_exams > 0 ? round(($exams_passed / $total_exams) * 100) : 0; ?>%"
                                                                aria-valuenow="<?php echo $total_exams > 0 ? round(($exams_passed / $total_exams) * 100) : 0; ?>"
                                                                aria-valuemin="0" aria-valuemax="100"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-percentage fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3 mb-4">
                                <div class="card border-left-warning shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Avg. Score</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    <?php echo number_format($overall_percentage, 1); ?>%
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-star fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Exam Results -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Exam Results</h6>
                        <button type="button" id="deleteSelectedBtn" class="btn btn-sm btn-danger" disabled>
                            <i class="fas fa-trash mr-1"></i> Delete Selected
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($exam_results)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle mr-2"></i>No exam results found for this student.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered" id="examResultsTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th width="5%">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="selectAll">
                                                </div>
                                            </th>
                                            <th>Exam</th>
                                            <th>Course</th>
                                            <th>Score</th>
                                            <th>Percentage</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($exam_results as $result): ?>
                                            <tr>
                                                <td>
                                                    <div class="form-check">
                                                        <input class="form-check-input result-checkbox" type="checkbox" name="result_ids[]" value="<?php echo $result['id']; ?>">
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($result['exam_title']); ?></td>
                                                <td><?php echo htmlspecialchars($result['course_title']); ?></td>
                                                <td><?php echo $result['score'] . '/' . $result['total_marks']; ?></td>
                                                <td><?php echo number_format($result['percentage'], 1) . '%'; ?></td>
                                                <td>
                                                    <?php if ($result['percentage'] >= 60): ?>
                                                        <span class="badge text-success">Passed</span>
                                                    <?php else: ?>
                                                        <span class="badge text-danger">Failed</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('M j, Y, g:i a', strtotime($result['submission_time'])); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-danger btn-sm delete-result-btn"
                                                        data-result-id="<?php echo $result['id']; ?>"
                                                        data-exam-title="<?php echo htmlspecialchars($result['exam_title']); ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title" id="resetPasswordModalLabel">Confirm Password Reset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to reset this student's password?</p>
                <p>A new random password will be generated.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="confirmResetPassword">Reset Password</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Student Modal -->
<div class="modal fade" id="deleteStudentModal" tabindex="-1" aria-labelledby="deleteStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteStudentModalLabel">Confirm Student Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this student?</p>
                <p><strong>Warning:</strong> This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteStudent">Delete Student</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Exam Result Modal -->
<div class="modal fade" id="deleteExamResultModal" tabindex="-1" aria-labelledby="deleteExamResultModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteExamResultModalLabel">Confirm Exam Result Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this exam result?</p>
                <p><strong>Exam:</strong> <span id="examTitleToDelete"></span></p>
                <p><strong>Warning:</strong> This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteResult">Delete Result</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Multiple Exam Results Modal -->
<div class="modal fade" id="deleteMultipleResultsModal" tabindex="-1" aria-labelledby="deleteMultipleResultsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteMultipleResultsModalLabel">Confirm Multiple Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the selected exam results?</p>
                <p><span id="selectedCount"></span> results will be deleted.</p>
                <p><strong>Warning:</strong> This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteMultiple">Delete Selected</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Reset Password Form and Modal
        const resetPasswordForm = document.querySelector('form[action][name="action"][value="reset_password"]');
        const resetPasswordBtn = document.querySelector('.btn-warning.btn-block');
        const confirmResetPasswordBtn = document.getElementById('confirmResetPassword');

        if (resetPasswordBtn) {
            resetPasswordBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const resetModal = new bootstrap.Modal(document.getElementById('resetPasswordModal'));
                resetModal.show();
            });
        }

        if (confirmResetPasswordBtn) {
            confirmResetPasswordBtn.addEventListener('click', function() {
                // Remove beforeunload and submit form
                window.onbeforeunload = null;
                $(window).off('beforeunload');
                document.querySelector('form input[name="action"][value="reset_password"]').closest('form').submit();
            });
        }

        // Delete Student Form and Modal
        const deleteStudentForm = document.querySelector('form[action][name="action"][value="delete"]');
        const deleteStudentBtn = document.querySelector('.btn-danger.btn-block');
        const confirmDeleteStudentBtn = document.getElementById('confirmDeleteStudent');

        if (deleteStudentBtn) {
            deleteStudentBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const deleteModal = new bootstrap.Modal(document.getElementById('deleteStudentModal'));
                deleteModal.show();
            });
        }

        if (confirmDeleteStudentBtn) {
            confirmDeleteStudentBtn.addEventListener('click', function() {
                // Remove beforeunload and submit form
                window.onbeforeunload = null;
                $(window).off('beforeunload');
                document.querySelector('form input[name="action"][value="delete"]').closest('form').submit();
            });
        }

        // Delete Exam Result
        const deleteResultBtns = document.querySelectorAll('.delete-result-btn');
        const confirmDeleteResultBtn = document.getElementById('confirmDeleteResult');
        let resultIdToDelete = null;

        if (deleteResultBtns) {
            deleteResultBtns.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    resultIdToDelete = this.getAttribute('data-result-id');
                    const examTitle = this.getAttribute('data-exam-title');
                    document.getElementById('examTitleToDelete').textContent = examTitle;

                    const deleteResultModal = new bootstrap.Modal(document.getElementById('deleteExamResultModal'));
                    deleteResultModal.show();
                });
            });
        }

        if (confirmDeleteResultBtn) {
            confirmDeleteResultBtn.addEventListener('click', function() {
                // Create and submit form for deleting the result
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';

                const studentIdInput = document.createElement('input');
                studentIdInput.type = 'hidden';
                studentIdInput.name = 'student_id';
                studentIdInput.value = '<?php echo $student["id"]; ?>';

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_exam_result';

                const resultIdInput = document.createElement('input');
                resultIdInput.type = 'hidden';
                resultIdInput.name = 'result_id';
                resultIdInput.value = resultIdToDelete;

                form.appendChild(studentIdInput);
                form.appendChild(actionInput);
                form.appendChild(resultIdInput);

                document.body.appendChild(form);
                form.submit();
            });
        }

        // Multiple Exam Results Selection and Deletion
        const selectAllCheckbox = document.getElementById('selectAll');
        const resultCheckboxes = document.querySelectorAll('.result-checkbox');
        const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
        const confirmDeleteMultipleBtn = document.getElementById('confirmDeleteMultiple');

        // Function to update delete button state
        function updateDeleteButtonState() {
            const checkedBoxes = document.querySelectorAll('.result-checkbox:checked');
            deleteSelectedBtn.disabled = checkedBoxes.length === 0;

            // Update the count in the modal
            if (document.getElementById('selectedCount')) {
                document.getElementById('selectedCount').textContent = checkedBoxes.length;
            }
        }

        // Select All checkbox functionality
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                resultCheckboxes.forEach(checkbox => {
                    checkbox.checked = selectAllCheckbox.checked;
                });
                updateDeleteButtonState();
            });
        }

        // Individual checkboxes functionality
        if (resultCheckboxes) {
            resultCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    // Update 'Select All' checkbox state
                    if (!this.checked) {
                        selectAllCheckbox.checked = false;
                    } else {
                        // Check if all checkboxes are checked
                        const allChecked = Array.from(resultCheckboxes).every(cb => cb.checked);
                        selectAllCheckbox.checked = allChecked;
                    }

                    updateDeleteButtonState();
                });
            });
        }

        // Delete Selected button functionality
        if (deleteSelectedBtn) {
            deleteSelectedBtn.addEventListener('click', function() {
                const checkedBoxes = document.querySelectorAll('.result-checkbox:checked');
                if (checkedBoxes.length > 0) {
                    const deleteMultipleModal = new bootstrap.Modal(document.getElementById('deleteMultipleResultsModal'));
                    deleteMultipleModal.show();
                }
            });
        }

        // Confirm Delete Multiple button functionality
        if (confirmDeleteMultipleBtn) {
            confirmDeleteMultipleBtn.addEventListener('click', function() {
                // Create and submit form for deleting multiple results
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';

                const studentIdInput = document.createElement('input');
                studentIdInput.type = 'hidden';
                studentIdInput.name = 'student_id';
                studentIdInput.value = '<?php echo $student["id"]; ?>';

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_multiple_results';

                // Add all selected result IDs
                const checkedBoxes = document.querySelectorAll('.result-checkbox:checked');
                checkedBoxes.forEach(checkbox => {
                    const resultIdInput = document.createElement('input');
                    resultIdInput.type = 'hidden';
                    resultIdInput.name = 'result_ids[]';
                    resultIdInput.value = checkbox.value;
                    form.appendChild(resultIdInput);
                });

                form.appendChild(studentIdInput);
                form.appendChild(actionInput);

                document.body.appendChild(form);
                form.submit();
            });
        }
    });
</script>

<?php
// Include footer
include_once "../../includes/footer.php";
?>