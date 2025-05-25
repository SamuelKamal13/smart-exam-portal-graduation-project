<?php
// Set page title and base URL for includes
$page_title = "Instructor Profile Management";
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
        $instructor_id = isset($_POST["instructor_id"]) ? (int)$_POST["instructor_id"] : 0;

        // Validate instructor ID
        if ($instructor_id <= 0) {
            $error_msg = "Invalid instructor ID provided.";
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
            if (!isset($error_msg)) {
                switch ($_POST["action"]) {
                    case "update":
                        // Update instructor information
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
                            // Update instructor record
                            $update_sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?";
                            if ($stmt = mysqli_prepare($conn, $update_sql)) {
                                mysqli_stmt_bind_param($stmt, "ssssi", $first_name, $last_name, $email, $phone, $instructor_id);

                                if (mysqli_stmt_execute($stmt)) {
                                    $success_msg = "Instructor profile updated successfully.";
                                } else {
                                    $error_msg = "Error updating profile: " . mysqli_error($conn);
                                }
                                mysqli_stmt_close($stmt);
                            }
                        }
                        break;

                    case "delete":
                        // Delete instructor account
                        // First check if instructor has courses
                        $check_courses_sql = "SELECT COUNT(*) as count FROM courses WHERE trainer_id = ?";
                        if ($stmt = mysqli_prepare($conn, $check_courses_sql)) {
                            mysqli_stmt_bind_param($stmt, "i", $instructor_id);
                            mysqli_stmt_execute($stmt);
                            $result = mysqli_stmt_get_result($stmt);
                            $row = mysqli_fetch_assoc($result);

                            if ($row["count"] > 0) {
                                // Instructor has courses, show warning
                                $warning_msg = "Cannot delete instructor as they have courses assigned. Please reassign or delete their courses first.";
                            } else {
                                // Delete instructor
                                $delete_sql = "DELETE FROM users WHERE id = ?";
                                if ($stmt = mysqli_prepare($conn, $delete_sql)) {
                                    mysqli_stmt_bind_param($stmt, "i", $instructor_id);

                                    if (mysqli_stmt_execute($stmt)) {
                                        $success_msg = "Instructor deleted successfully.";
                                        // Redirect to reports page after successful deletion
                                        header("location: reports.php?type=trainer_performance");
                                        exit;
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

                    case "delete_course":
                        // Delete a specific course
                        $course_id = isset($_POST["course_id"]) ? (int)$_POST["course_id"] : 0;

                        if ($course_id <= 0) {
                            $error_msg = "Invalid course ID provided.";
                        } else {
                            // Verify the course belongs to this instructor
                            $check_course_sql = "SELECT id FROM courses WHERE id = ? AND trainer_id = ?";
                            if ($stmt = mysqli_prepare($conn, $check_course_sql)) {
                                mysqli_stmt_bind_param($stmt, "ii", $course_id, $instructor_id);
                                mysqli_stmt_execute($stmt);
                                mysqli_stmt_store_result($stmt);

                                if (mysqli_stmt_num_rows($stmt) != 1) {
                                    $error_msg = "Course not found for this instructor.";
                                }
                                mysqli_stmt_close($stmt);
                            }

                            if (!isset($error_msg)) {
                                // Delete the course
                                $delete_course_sql = "DELETE FROM courses WHERE id = ?";
                                if ($stmt = mysqli_prepare($conn, $delete_course_sql)) {
                                    mysqli_stmt_bind_param($stmt, "i", $course_id);

                                    if (mysqli_stmt_execute($stmt)) {
                                        $success_msg = "Course deleted successfully.";
                                    } else {
                                        $error_msg = "Error deleting course: " . mysqli_error($conn);
                                    }
                                    mysqli_stmt_close($stmt);
                                }
                            }
                        }
                        break;
                    case "delete_multiple_courses":
                        // Delete multiple courses
                        if (!isset($_POST["course_ids"]) || !is_array($_POST["course_ids"]) || empty($_POST["course_ids"])) {
                            $error_msg = "No courses selected for deletion.";
                        } else {
                            $course_ids = array_map('intval', $_POST["course_ids"]);
                            $deleted_count = 0;
                            $error_count = 0;

                            // Begin transaction
                            mysqli_begin_transaction($conn);

                            try {
                                foreach ($course_ids as $course_id) {
                                    // Verify the course belongs to this instructor
                                    $check_course_sql = "SELECT id FROM courses WHERE id = ? AND trainer_id = ?";
                                    if ($stmt = mysqli_prepare($conn, $check_course_sql)) {
                                        mysqli_stmt_bind_param($stmt, "ii", $course_id, $instructor_id);
                                        mysqli_stmt_execute($stmt);
                                        mysqli_stmt_store_result($stmt);

                                        if (mysqli_stmt_num_rows($stmt) == 1) {
                                            mysqli_stmt_close($stmt);

                                            // Delete the course
                                            $delete_course_sql = "DELETE FROM courses WHERE id = ?";
                                            if ($stmt = mysqli_prepare($conn, $delete_course_sql)) {
                                                mysqli_stmt_bind_param($stmt, "i", $course_id);

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
                                    $success_msg = "Successfully deleted $deleted_count course(s).";
                                    if ($error_count > 0) {
                                        $success_msg .= " $error_count course(s) could not be deleted.";
                                    }
                                } else {
                                    $error_msg = "Failed to delete any courses.";
                                }
                            } catch (Exception $e) {
                                // Rollback transaction on error
                                mysqli_rollback($conn);
                                $error_msg = "Error: " . $e->getMessage();
                            }
                        }
                        break;
                }
            }
        }
    }
}

// Get instructor ID from URL parameter
$instructor_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

// Fetch instructor data if ID is provided
$instructor = null;
if ($instructor_id > 0) {
    $sql = "SELECT id, user_id, first_name, last_name, email, phone, created_at 
            FROM users 
            WHERE id = ? AND role = 'trainer'";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $instructor_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if (mysqli_num_rows($result) == 1) {
                $instructor = mysqli_fetch_assoc($result);
            }
        }
        mysqli_stmt_close($stmt);
    }
}

// Get instructor courses and exams
$courses = [];
if ($instructor) {
    $sql = "SELECT c.id, c.title, c.description, c.created_at, 
            COUNT(DISTINCT e.id) as exams_count, COUNT(DISTINCT cr.student_id) as students_count
            FROM courses c
            LEFT JOIN exams e ON c.id = e.course_id
            LEFT JOIN course_registrations cr ON c.id = cr.course_id
            WHERE c.trainer_id = ?
            GROUP BY c.id
            ORDER BY c.created_at DESC";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $instructor_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $courses[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }

    // Calculate overall statistics
    $total_courses = count($courses);
    $total_exams = 0;
    $total_students = 0;

    foreach ($courses as $course) {
        $total_exams += $course["exams_count"];
        $total_students += $course["students_count"];
    }
}

// Include header
include_once "../../includes/header.php";
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Instructor Profile Management</h1>
        <a href="reports.php?type=trainer_performance" class="btn btn-sm btn-primary">
            <i class="fas fa-arrow-left fa-sm"></i> Back to Instructor Reports
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

    <?php if (!$instructor && $instructor_id > 0): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle mr-2"></i>Instructor not found.
        </div>
        <div class="text-center mt-4">
            <a href="reports.php?type=trainer_performance" class="btn btn-primary">
                <i class="fas fa-arrow-left mr-2"></i>Return to Instructor Reports
            </a>
        </div>
    <?php elseif (!$instructor): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle mr-2"></i>Please select an instructor from the Instructor Performance Report.
        </div>
        <div class="text-center mt-4">
            <a href="reports.php?type=trainer_performance" class="btn btn-primary">
                <i class="fas fa-list mr-2"></i>View Instructor Reports
            </a>
        </div>
    <?php else: ?>
        <!-- Instructor Profile Card -->
        <div class="row">
            <div class="col-lg-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Instructor Information</h6>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <input type="hidden" name="instructor_id" value="<?php echo $instructor['id']; ?>">
                            <input type="hidden" name="action" value="update">

                            <div class="form-group">
                                <label>Instructor ID</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($instructor['user_id']); ?>" readonly>
                            </div>

                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($instructor['first_name']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($instructor['last_name']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($instructor['email']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Phone</label>
                                <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($instructor['phone']); ?>">
                            </div>

                            <div class="form-group">
                                <label>Registered On</label>
                                <input type="text" class="form-control" value="<?php echo date('F j, Y', strtotime($instructor['created_at'])); ?>" readonly>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-save mr-2"></i>Save Changes
                            </button>
                        </form>

                        <hr>

                        <form method="post" action="" class="mt-3" id="resetPasswordForm">
                            <input type="hidden" name="instructor_id" value="<?php echo $instructor['id']; ?>">
                            <input type="hidden" name="action" value="reset_password">
                            <button type="button" class="btn btn-warning btn-block" id="resetPasswordBtn">
                                <i class="fas fa-key mr-2"></i>Reset Password
                            </button>
                        </form>

                        <form method="post" action="" class="mt-3" id="deleteInstructorForm">
                            <input type="hidden" name="instructor_id" value="<?php echo $instructor['id']; ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="button" class="btn btn-danger btn-block" id="deleteInstructorBtn">
                                <i class="fas fa-trash mr-2"></i>Delete Instructor
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
                            <div class="col-md-4 mb-4">
                                <div class="card border-left-primary shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Courses</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_courses; ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-book fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4 mb-4">
                                <div class="card border-left-success shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Exams</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_exams; ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4 mb-4">
                                <div class="card border-left-info shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Students</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_students; ?></div>
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

                <!-- Courses List -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Courses</h6>
                        <button type="button" id="deleteSelectedCoursesBtn" class="btn btn-sm btn-danger" disabled>
                            <i class="fas fa-trash mr-1"></i> Delete Selected
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($courses)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle mr-2"></i>No courses found for this instructor.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered" id="coursesTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th width="5%">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="selectAllCourses">
                                                </div>
                                            </th>
                                            <th>Title</th>
                                            <th>Description</th>
                                            <th>Students</th>
                                            <th>Exams</th>
                                            <th>Created On</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($courses as $course): ?>
                                            <tr>
                                                <td>
                                                    <div class="form-check">
                                                        <input class="form-check-input course-checkbox" type="checkbox" name="course_ids[]" value="<?php echo $course['id']; ?>">
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($course['title']); ?></td>
                                                <td><?php echo htmlspecialchars(substr($course['description'], 0, 50)) . (strlen($course['description']) > 50 ? '...' : ''); ?></td>
                                                <td><?php echo $course['students_count']; ?></td>
                                                <td><?php echo $course['exams_count']; ?></td>
                                                <td><?php echo date('M j, Y', strtotime($course['created_at'])); ?></td>
                                                <td>
                                                    <a href="view-course.php?id=<?php echo $course['id']; ?>" class="btn btn-info btn-sm">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-danger btn-sm delete-course-btn"
                                                        data-course-id="<?php echo $course['id']; ?>"
                                                        data-course-title="<?php echo htmlspecialchars($course['title']); ?>">
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
                <p>Are you sure you want to reset this instructor's password?</p>
                <p>A new random password will be generated.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="confirmResetPassword">Reset Password</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Instructor Modal -->
<div class="modal fade" id="deleteInstructorModal" tabindex="-1" aria-labelledby="deleteInstructorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteInstructorModalLabel">Confirm Instructor Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this instructor?</p>
                <p><strong>Warning:</strong> This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteInstructor">Delete Instructor</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Multiple Courses Modal -->
<div class="modal fade" id="deleteMultipleCoursesModal" tabindex="-1" aria-labelledby="deleteMultipleCoursesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteMultipleCoursesModalLabel">Confirm Multiple Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the selected courses?</p>
                <p><span id="selectedCoursesCount"></span> courses will be deleted.</p>
                <p><strong>Warning:</strong> This action cannot be undone. All associated exams and student registrations will also be deleted.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteMultipleCourses">Delete Selected</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Course Modal -->
<div class="modal fade" id="deleteCourseModal" tabindex="-1" aria-labelledby="deleteCourseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteCourseModalLabel">Confirm Course Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this course?</p>
                <p><strong>Course:</strong> <span id="courseTitleToDelete"></span></p>
                <p><strong>Warning:</strong> This action cannot be undone. All associated exams and student registrations will also be deleted.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteCourse">Delete Course</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Reset Password Form and Modal
        const resetPasswordBtn = document.getElementById('resetPasswordBtn');
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
                document.getElementById('resetPasswordForm').submit();
            });
        }

        // Delete Instructor Form and Modal
        const deleteInstructorBtn = document.getElementById('deleteInstructorBtn');
        const confirmDeleteInstructorBtn = document.getElementById('confirmDeleteInstructor');

        if (deleteInstructorBtn) {
            deleteInstructorBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const deleteModal = new bootstrap.Modal(document.getElementById('deleteInstructorModal'));
                deleteModal.show();
            });
        }

        if (confirmDeleteInstructorBtn) {
            confirmDeleteInstructorBtn.addEventListener('click', function() {
                // Remove beforeunload and submit form
                window.onbeforeunload = null;
                $(window).off('beforeunload');
                document.getElementById('deleteInstructorForm').submit();
            });
        }

        // Initialize DataTable for courses
        if (document.getElementById('coursesTable')) {
            $('#coursesTable').DataTable({
                order: [
                    [4, 'desc']
                ],
                pageLength: 10,
                lengthMenu: [
                    [5, 10, 25, 50, -1],
                    [5, 10, 25, 50, "All"]
                ]
            });
        }
    });


    // ... existing code ...

    // Delete Course
    const deleteCourseBtns = document.querySelectorAll('.delete-course-btn');
    const confirmDeleteCourseBtn = document.getElementById('confirmDeleteCourse');
    let courseIdToDelete = null;

    if (deleteCourseBtns) {
        deleteCourseBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                courseIdToDelete = this.getAttribute('data-course-id');
                const courseTitle = this.getAttribute('data-course-title');
                document.getElementById('courseTitleToDelete').textContent = courseTitle;

                const deleteCourseModal = new bootstrap.Modal(document.getElementById('deleteCourseModal'));
                deleteCourseModal.show();
            });
        });
    }

    if (confirmDeleteCourseBtn) {
        confirmDeleteCourseBtn.addEventListener('click', function() {
            // Create and submit form for deleting the course
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';

            const instructorIdInput = document.createElement('input');
            instructorIdInput.type = 'hidden';
            instructorIdInput.name = 'instructor_id';
            instructorIdInput.value = '<?php echo $instructor["id"]; ?>';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete_course';

            const courseIdInput = document.createElement('input');
            courseIdInput.type = 'hidden';
            courseIdInput.name = 'course_id';
            courseIdInput.value = courseIdToDelete;

            form.appendChild(instructorIdInput);
            form.appendChild(actionInput);
            form.appendChild(courseIdInput);

            document.body.appendChild(form);
            form.submit();
        });
    }

    // Multiple Course Selection and Deletion
    const selectAllCoursesCheckbox = document.getElementById('selectAllCourses');
    const courseCheckboxes = document.querySelectorAll('.course-checkbox');
    const deleteSelectedCoursesBtn = document.getElementById('deleteSelectedCoursesBtn');
    const confirmDeleteMultipleCoursesBtn = document.getElementById('confirmDeleteMultipleCourses');

    // Function to update delete button state
    function updateDeleteButtonState() {
        const checkedBoxes = document.querySelectorAll('.course-checkbox:checked');
        deleteSelectedCoursesBtn.disabled = checkedBoxes.length === 0;

        // Update the count in the modal
        if (document.getElementById('selectedCoursesCount')) {
            document.getElementById('selectedCoursesCount').textContent = checkedBoxes.length;
        }
    }

    // Select All checkbox functionality
    if (selectAllCoursesCheckbox) {
        selectAllCoursesCheckbox.addEventListener('change', function() {
            courseCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCoursesCheckbox.checked;
            });
            updateDeleteButtonState();
        });
    }

    // Individual checkboxes functionality
    if (courseCheckboxes) {
        courseCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                // Update 'Select All' checkbox state
                if (!this.checked) {
                    selectAllCoursesCheckbox.checked = false;
                } else {
                    // Check if all checkboxes are checked
                    const allChecked = Array.from(courseCheckboxes).every(cb => cb.checked);
                    selectAllCoursesCheckbox.checked = allChecked;
                }

                updateDeleteButtonState();
            });
        });
    }

    // Delete Selected button functionality
    if (deleteSelectedCoursesBtn) {
        deleteSelectedCoursesBtn.addEventListener('click', function() {
            const checkedBoxes = document.querySelectorAll('.course-checkbox:checked');
            if (checkedBoxes.length > 0) {
                document.getElementById('selectedCoursesCount').textContent = checkedBoxes.length;
                const deleteMultipleModal = new bootstrap.Modal(document.getElementById('deleteMultipleCoursesModal'));
                deleteMultipleModal.show();
            }
        });
    }

    // Confirm Delete Multiple button functionality
    if (confirmDeleteMultipleCoursesBtn) {
        confirmDeleteMultipleCoursesBtn.addEventListener('click', function() {
            // Create and submit form for deleting multiple courses
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';

            const instructorIdInput = document.createElement('input');
            instructorIdInput.type = 'hidden';
            instructorIdInput.name = 'instructor_id';
            instructorIdInput.value = '<?php echo $instructor["id"]; ?>';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete_multiple_courses';

            // Add all checked course IDs
            const checkedBoxes = document.querySelectorAll('.course-checkbox:checked');
            checkedBoxes.forEach(checkbox => {
                const courseIdInput = document.createElement('input');
                courseIdInput.type = 'hidden';
                courseIdInput.name = 'course_ids[]';
                courseIdInput.value = checkbox.value;
                form.appendChild(courseIdInput);
            });

            form.appendChild(instructorIdInput);
            form.appendChild(actionInput);

            document.body.appendChild(form);
            form.submit();
        });
    }
</script>

<?php
// Include footer
include_once "../../includes/footer.php";
?>