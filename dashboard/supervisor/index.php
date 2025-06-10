<?php
// Set page title and base URL for includes
$page_title = "Supervisor Dashboard";
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

// Get supervisor ID
$supervisor_id = $_SESSION["id"];

// Get count of students
$student_count = 0;
$sql = "SELECT COUNT(*) as count FROM users WHERE role = 'student'";
if ($stmt = mysqli_prepare($conn, $sql)) {
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $student_count = $row["count"];
        }
    }
    mysqli_stmt_close($stmt);
}

// Get count of instructors
$instructor_count = 0;
$sql = "SELECT COUNT(*) as count FROM users WHERE role = 'trainer'";
if ($stmt = mysqli_prepare($conn, $sql)) {
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $instructor_count = $row["count"];
        }
    }
    mysqli_stmt_close($stmt);
}

// Get count of exams
$exam_count = 0;
$sql = "SELECT COUNT(*) as count FROM exams";
if ($stmt = mysqli_prepare($conn, $sql)) {
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $exam_count = $row["count"];
        }
    }
    mysqli_stmt_close($stmt);
}

// Include header
include_once "../../includes/header.php";
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Supervisor Dashboard</h1>
    </div>

    <!-- Dashboard Stats -->
    <div class="row">
        <!-- Students Card -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Students</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $student_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Instructors Card -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Total Instructors</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $instructor_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chalkboard-teacher fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Exams Card -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Total Exams</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $exam_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity Section -->
    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="row justify-content-center">
                        <div class="col-md-3 mb-4 text-center">
                            <a href="manage-students.php" class="btn btn-primary py-3" style="width: 100%; min-width: 200px;">
                                <i class="fas fa-user-graduate mr-2"></i> Manage Students
                            </a>
                        </div>
                        <div class="col-md-3 mb-4 text-center">
                            <a href="manage-instructors.php" class="btn btn-success py-3" style="width: 100%; min-width: 200px;">
                                <i class="fas fa-chalkboard-teacher mr-2"></i> Manage Instructors
                            </a>
                        </div>
                        <div class="col-md-3 mb-4 text-center">
                            <a href="reports.php" class="btn btn-warning py-3" style="width: 100%; min-width: 200px;">
                                <i class="fas fa-file-alt mr-2"></i> Reports
                            </a>
                        </div>
                        <div class="col-md-3 mb-4 text-center">
                            <a href="generate-codes.php" class="btn btn-danger py-3" style="width: 100%; min-width: 200px;">
                                <i class="fas fa-key mr-2"></i> Invitation Codes
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once "../../includes/footer.php";
?>