<?php
// Set page title and base URL for includes
$page_title = "System Reports";
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

// Get report type from query string (default to overview)
$report_type = isset($_GET['type']) ? $_GET['type'] : 'overview';

// Get date range filters if provided
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Function to get overview statistics
function getOverviewStats($conn)
{
    $stats = [];

    // Get total users by role
    $roles_sql = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
    if ($result = mysqli_query($conn, $roles_sql)) {
        $stats['users'] = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $stats['users'][$row['role']] = $row['count'];
        }
        mysqli_free_result($result);
    }

    // Get total courses
    $courses_sql = "SELECT COUNT(*) as count FROM courses";
    if ($result = mysqli_query($conn, $courses_sql)) {
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['total_courses'] = $row['count'];
        }
        mysqli_free_result($result);
    }

    // Get total exams
    $exams_sql = "SELECT COUNT(*) as count FROM exams";
    if ($result = mysqli_query($conn, $exams_sql)) {
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['total_exams'] = $row['count'];
        }
        mysqli_free_result($result);
    }

    // Get exam statistics
    $exam_stats_sql = "SELECT 
                        COUNT(*) as total_exams,
                        SUM(CASE WHEN start_time > NOW() THEN 1 ELSE 0 END) as upcoming_exams,
                        SUM(CASE WHEN start_time <= NOW() AND DATE_ADD(start_time, INTERVAL duration MINUTE) >= NOW() THEN 1 ELSE 0 END) as ongoing_exams,
                        SUM(CASE WHEN DATE_ADD(start_time, INTERVAL duration MINUTE) < NOW() THEN 1 ELSE 0 END) as completed_exams
                      FROM exams";
    if ($result = mysqli_query($conn, $exam_stats_sql)) {
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['exam_stats'] = $row;
        }
        mysqli_free_result($result);
    }

    // Get average exam score
    $avg_score_sql = "SELECT AVG(percentage) as avg_score FROM results";
    if ($result = mysqli_query($conn, $avg_score_sql)) {
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['avg_score'] = $row['avg_score'] ? round($row['avg_score'], 2) : 0;
        }
        mysqli_free_result($result);
    }

    return $stats;
}

// Function to get exam performance report
function getExamPerformanceReport($conn, $start_date, $end_date)
{
    $exams = [];

    $sql = "SELECT e.id, e.title, e.start_time, e.total_marks, c.title as course_title,
                  COUNT(r.id) as total_students,
                  AVG(r.percentage) as avg_percentage,
                  MIN(r.percentage) as min_percentage,
                  MAX(r.percentage) as max_percentage
           FROM exams e
           LEFT JOIN courses c ON e.course_id = c.id
           LEFT JOIN results r ON e.id = r.exam_id
           WHERE DATE(e.start_time) BETWEEN ? AND ?
           GROUP BY e.id
           ORDER BY e.start_time DESC";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $exams[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }

    return $exams;
}

// Function to get student performance report
function getStudentPerformanceReport($conn, $start_date, $end_date)
{
    $students = [];

    $sql = "SELECT u.id, u.user_id, u.first_name, u.last_name, u.email,
                  COUNT(r.id) as exams_taken,
                  AVG(r.percentage) as avg_percentage,
                  SUM(CASE WHEN r.percentage >= 60 THEN 1 ELSE 0 END) as exams_passed
           FROM users u
           LEFT JOIN results r ON u.id = r.student_id
           LEFT JOIN exams e ON r.exam_id = e.id
           WHERE u.role = 'student' AND (r.id IS NULL OR DATE(e.start_time) BETWEEN ? AND ?)
           GROUP BY u.id
           ORDER BY avg_percentage DESC";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $students[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }

    return $students;
}

// Function to get trainer performance report
function getTrainerPerformanceReport($conn, $start_date, $end_date)
{
    $trainers = [];

    $sql = "SELECT u.id, u.first_name, u.last_name, u.email,
                  COUNT(DISTINCT c.id) as courses_count,
                  COUNT(DISTINCT e.id) as exams_count,
                  COUNT(DISTINCT r.student_id) as students_count,
                  AVG(r.percentage) as avg_student_score
           FROM users u
           LEFT JOIN courses c ON u.id = c.trainer_id
           LEFT JOIN exams e ON c.id = e.course_id
           LEFT JOIN results r ON e.id = r.exam_id
           WHERE u.role = 'trainer' AND (e.id IS NULL OR DATE(e.start_time) BETWEEN ? AND ?)
           GROUP BY u.id
           ORDER BY courses_count DESC";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $trainers[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }

    return $trainers;
}

// Get data based on report type
$report_data = [];
switch ($report_type) {
    case 'exam_performance':
        $report_data = getExamPerformanceReport($conn, $start_date, $end_date);
        break;
    case 'student_performance':
        $report_data = getStudentPerformanceReport($conn, $start_date, $end_date);
        break;
    case 'trainer_performance':
        $report_data = getTrainerPerformanceReport($conn, $start_date, $end_date);
        break;
    default:
        $report_data = getOverviewStats($conn);
        break;
}

// Include header
include_once "../../includes/header.php";
?>

<style>
    .form-group {
        display: flex;
    }
</style>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">System Reports</h1>
        <div>
            <button class="btn btn-sm btn-primary" id="printReport">
                <i class="fas fa-print fa-sm"></i> Print Report
            </button>
            <button class="btn btn-sm btn-success" id="exportCSV">
                <i class="fas fa-file-csv fa-sm"></i> Export CSV
            </button>
        </div>
    </div>

    <!-- Report Navigation Tabs -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <ul class="nav nav-tabs card-header-tabs">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($report_type == 'overview') ? 'active' : ''; ?>" href="?type=overview">
                        <i class="fas fa-chart-pie mr-1"></i> Overview
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($report_type == 'exam_performance') ? 'active' : ''; ?>" href="?type=exam_performance">
                        <i class="fas fa-file-alt mr-1"></i> Exam Performance
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($report_type == 'student_performance') ? 'active' : ''; ?>" href="?type=student_performance">
                        <i class="fas fa-user-graduate mr-1"></i> Student Performance
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($report_type == 'trainer_performance') ? 'active' : ''; ?>" href="?type=trainer_performance">
                        <i class="fas fa-chalkboard-teacher mr-1"></i> Trainer Performance
                    </a>
                </li>
            </ul>
        </div>

        <div class="card-body">
            <?php if ($report_type != 'overview'): ?>
                <!-- Date Range Filter -->
                <form method="get" class="mb-4">
                    <input type="hidden" name="type" value="<?php echo $report_type; ?>">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Start Date</label>
                                <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>End Date</label>
                                <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-primary btn-block">Apply Filter</button>
                            </div>
                        </div>
                    </div>
                </form>
            <?php endif; ?>

            <!-- Report Content -->
            <div id="reportContent">
                <?php if ($report_type == 'overview'): ?>
                    <!-- Overview Report -->
                    <div class="row">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Students</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                <?php echo $report_data['users']['student'] ?? 0; ?>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Trainers</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                <?php echo $report_data['users']['trainer'] ?? 0; ?>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-chalkboard-teacher fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                Courses</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                <?php echo $report_data['total_courses'] ?? 0; ?>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-book fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-warning shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                Exams</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                <?php echo $report_data['total_exams'] ?? 0; ?>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Exam Status Chart -->
                        <div class="col-lg-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Exam Status</h6>
                                </div>
                                <div class="card-body">
                                    <div class="chart-pie pt-4 pb-2">
                                        <canvas id="examStatusChart"></canvas>
                                    </div>
                                    <div class="mt-4 text-center small">
                                        <span class="mr-2">
                                            <i class="fas fa-circle text-primary"></i> Upcoming
                                        </span>
                                        <span class="mr-2">
                                            <i class="fas fa-circle text-success"></i> Ongoing
                                        </span>
                                        <span class="mr-2">
                                            <i class="fas fa-circle text-info"></i> Completed
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- User Distribution Chart -->
                        <div class="col-lg-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">User Distribution</h6>
                                </div>
                                <div class="card-body">
                                    <div class="chart-pie pt-4 pb-2">
                                        <canvas id="userDistributionChart"></canvas>
                                    </div>
                                    <div class="mt-4 text-center small">
                                        <span class="mr-2">
                                            <i class="fas fa-circle text-primary"></i> Students
                                        </span>
                                        <span class="mr-2">
                                            <i class="fas fa-circle text-success"></i> Trainers
                                        </span>
                                        <span class="mr-2">
                                            <i class="fas fa-circle text-warning"></i> Supervisors
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System Statistics -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">System Statistics</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            Exam Statistics
                                        </div>
                                        <div class="card-body">
                                            <table class="table table-bordered">
                                                <tr>
                                                    <th>Total Exams</th>
                                                    <td><?php echo $report_data['exam_stats']['total_exams'] ?? 0; ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Upcoming Exams</th>
                                                    <td><?php echo $report_data['exam_stats']['upcoming_exams'] ?? 0; ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Ongoing Exams</th>
                                                    <td><?php echo $report_data['exam_stats']['ongoing_exams'] ?? 0; ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Completed Exams</th>
                                                    <td><?php echo $report_data['exam_stats']['completed_exams'] ?? 0; ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Average Score</th>
                                                    <td><?php echo $report_data['avg_score'] ?? 0; ?>%</td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            Quick Actions
                                        </div>
                                        <div class="card-body">
                                            <div class="list-group">
                                                <a href="?type=exam_performance" class="list-group-item list-group-item-action">
                                                    <i class="fas fa-chart-bar mr-2"></i> View Detailed Exam Performance
                                                </a>
                                                <a href="?type=student_performance" class="list-group-item list-group-item-action">
                                                    <i class="fas fa-user-graduate mr-2"></i> View Student Performance
                                                </a>
                                                <a href="?type=trainer_performance" class="list-group-item list-group-item-action">
                                                    <i class="fas fa-chalkboard-teacher mr-2"></i> View Trainer Performance
                                                </a>
                                                <a href="generate-codes.php" class="list-group-item list-group-item-action">
                                                    <i class="fas fa-key mr-2"></i> Generate Invitation Codes
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif ($report_type == 'exam_performance'): ?>
                    <!-- Exam Performance Report -->
                    <div class="table-responsive">
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Exam Title</th>
                                    <th>Course</th>
                                    <th>Start Time</th>
                                    <th>Total Students</th>
                                    <th>Avg. Score (%)</th>
                                    <th>Min Score (%)</th>
                                    <th>Max Score (%)</th>
                                    <th>Actions</th> <!-- Added Actions Header -->
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $exam): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                        <td><?php echo htmlspecialchars($exam['course_title'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($exam['start_time'])); ?></td>
                                        <td><?php echo $exam['total_students']; ?></td>
                                        <td><?php echo round($exam['avg_percentage'] ?? 0, 2); ?>%</td>
                                        <td><?php echo round($exam['min_percentage'] ?? 0, 2); ?>%</td>
                                        <td><?php echo round($exam['max_percentage'] ?? 0, 2); ?>%</td>
                                        <td>
                                            <!-- Added View Details Button -->
                                            <button class="btn btn-info btn-sm view-exam-details-btn"
                                                data-exam-id="<?php echo $exam['id']; ?>"
                                                data-exam-title="<?php echo htmlspecialchars($exam['title']); ?>">
                                                <i class="fas fa-eye"></i> View Details
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($report_data)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No exam data available for the selected period.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($report_type == 'student_performance'): ?>
                    <!-- Student Performance Report -->
                    <div class="table-responsive">
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Exams Taken</th>
                                    <th>Avg. Score (%)</th>
                                    <th>Highest Score (%)</th>
                                    <th>Lowest Score (%)</th>
                                    <th>Actions</th> <!-- Added Actions Header -->
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['user_id'] ?? $student['user_id']); ?></td>
                                        <td><?php echo htmlspecialchars($student['name'] ?? ($student['first_name'] . ' ' . $student['last_name'])); ?></td>
                                        <td><?php echo $student['exams_taken']; ?></td>
                                        <td><?php echo round($student['avg_score'] ?? $student['avg_percentage'] ?? 0, 2); ?>%</td>
                                        <td><?php echo round($student['highest_score'] ?? 0, 2); ?>%</td>
                                        <td><?php echo round($student['lowest_score'] ?? 0, 2); ?>%</td>
                                        <td>
                                            <!-- Added View Profile Button -->
                                            <button class="btn btn-info btn-sm view-student-profile-btn"
                                                data-student-id="<?php echo $student['id']; ?>"
                                                data-student-name="<?php echo htmlspecialchars($student['name'] ?? ($student['first_name'] . ' ' . $student['last_name'])); ?>">
                                                <i class="fas fa-eye"></i> View Details
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($report_data)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No student data available for the selected period.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($report_type == 'trainer_performance'): ?>
                    <!-- Trainer Performance Report -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Trainer Performance Report</h6>
                        </div>
                        <div class="card-body">
                            <?php if (count($report_data) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="trainerPerformanceTable" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>Trainer Name</th>
                                                <th>Email</th>
                                                <th>Courses</th>
                                                <th>Exams</th>
                                                <th>Students</th>
                                                <th>Avg. Student Score</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data as $trainer): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($trainer['email']); ?></td>
                                                    <td><?php echo $trainer['courses_count']; ?></td>
                                                    <td><?php echo $trainer['exams_count']; ?></td>
                                                    <td><?php echo $trainer['students_count']; ?></td>
                                                    <td>
                                                        <?php
                                                        if ($trainer['avg_student_score']) {
                                                            echo round($trainer['avg_student_score'], 2) . '%';
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <a href="#" class="btn btn-info btn-sm view-trainer-details" data-id="<?php echo $trainer['id']; ?>">
                                                            <i class="fas fa-eye"></i> View Details
                                                        </a>
                                                    </td>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    No trainer data available for the selected date range.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Page level custom scripts -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize charts if they exist on the page
        if (document.getElementById('examStatusChart')) {
            initExamStatusChart();
        }

        if (document.getElementById('userDistributionChart')) {
            initUserDistributionChart();
        }

        // Add event listeners for export and print buttons
        document.getElementById('printReport').addEventListener('click', printReport);
        document.getElementById('exportCSV').addEventListener('click', exportToCSV);

        // Initialize DataTables if they exist
        if ($.fn.DataTable) {
            $('.dataTable').DataTable({
                responsive: true,
                dom: 'Bfrtip',
                buttons: [
                    'copy', 'excel', 'pdf'
                ]
            });
        }
    });

    // Function to initialize Exam Status Chart
    function initExamStatusChart() {
        const ctx = document.getElementById('examStatusChart').getContext('2d');

        // Get data from chartData div
        const chartData = document.getElementById('chartData');
        const upcomingExams = parseInt(chartData.dataset.upcoming || 0);
        const ongoingExams = parseInt(chartData.dataset.ongoing || 0);
        const completedExams = parseInt(chartData.dataset.completed || 0);

        const examStatusChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Upcoming', 'Ongoing', 'Completed'],
                datasets: [{
                    data: [upcomingExams, ongoingExams, completedExams],
                    backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc'],
                    hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf'],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }],
            },
            options: {
                maintainAspectRatio: false,
                tooltips: {
                    backgroundColor: "rgb(255,255,255)",
                    bodyFontColor: "#858796",
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    xPadding: 15,
                    yPadding: 15,
                    displayColors: false,
                    caretPadding: 10,
                },
                legend: {
                    display: true
                },
                cutout: '80%',
            },
        });
    }

    // Function to initialize User Distribution Chart
    function initUserDistributionChart() {
        const ctx = document.getElementById('userDistributionChart').getContext('2d');

        // Get data from chartData div
        const chartData = document.getElementById('chartData');
        const students = parseInt(chartData.dataset.students || 0);
        const trainers = parseInt(chartData.dataset.trainers || 0);
        const supervisors = parseInt(chartData.dataset.supervisors || 0);

        const userDistributionChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Students', 'Trainers', 'Supervisors'],
                datasets: [{
                    data: [students, trainers, supervisors],
                    backgroundColor: ['#4e73df', '#1cc88a', '#f6c23e'],
                    hoverBackgroundColor: ['#2e59d9', '#17a673', '#e0b12c'],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }],
            },
            options: {
                maintainAspectRatio: false,
                tooltips: {
                    backgroundColor: "rgb(255,255,255)",
                    bodyFontColor: "#858796",
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    xPadding: 15,
                    yPadding: 15,
                    displayColors: false,
                    caretPadding: 10,
                },
                legend: {
                    display: true
                },
                cutout: '80%',
            },
        });
    }

    // Function to print the report
    function printReport() {
        // Store the original content
        const originalContent = document.body.innerHTML;

        // Create a print-friendly version
        let printContent = document.getElementById('reportContent').innerHTML;

        // Set the body content to just the report content
        document.body.innerHTML = `
        <div style="padding: 20px;">
            <h1 style="text-align: center; margin-bottom: 20px;">Smart Exam Portal - System Report</h1>
            <div>${printContent}</div>
        </div>
    `;

        // Print the document
        window.print();

        // Restore the original content
        document.body.innerHTML = originalContent;

        // Reinitialize event listeners and charts
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('examStatusChart')) {
                initExamStatusChart();
            }

            if (document.getElementById('userDistributionChart')) {
                initUserDistributionChart();
            }

            document.getElementById('printReport').addEventListener('click', printReport);
            document.getElementById('exportCSV').addEventListener('click', exportToCSV);
        });
    }

    // Function to export data to CSV
    function exportToCSV() {
        let csvContent = '';
        const reportType = document.querySelector('.nav-link.active').textContent.trim();
        const tables = document.querySelectorAll('#reportContent table');

        if (tables.length === 0) {
            alert('No data available to export');
            return;
        }

        // Add report title
        csvContent += 'Smart Exam Portal - ' + reportType + ' Report\n\n';

        // Process each table in the report
        tables.forEach(table => {
            // Get table header
            const headerRow = table.querySelector('thead tr');
            if (headerRow) {
                const headers = Array.from(headerRow.querySelectorAll('th')).map(th =>
                    '"' + th.textContent.trim().replace(/"/g, '""') + '"'
                );
                csvContent += headers.join(',') + '\n';
            }

            // Get table body rows
            const bodyRows = table.querySelectorAll('tbody tr');
            bodyRows.forEach(row => {
                const rowData = Array.from(row.querySelectorAll('td')).map(cell =>
                    '"' + cell.textContent.trim().replace(/"/g, '""') + '"'
                );
                csvContent += rowData.join(',') + '\n';
            });

            csvContent += '\n'; // Add space between tables
        });

        // Create a download link
        const encodedUri = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csvContent);
        const link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute('download', 'report_' + new Date().toISOString().slice(0, 10) + '.csv');
        document.body.appendChild(link);

        // Trigger download and remove link
        link.click();
        document.body.removeChild(link);
    }
</script>

<?php
// Add data attributes for charts
if ($report_type == 'overview') {
    echo '<div id="chartData" 
        data-upcoming="' . ($report_data['exam_stats']['upcoming_exams'] ?? 0) . '" 
        data-ongoing="' . ($report_data['exam_stats']['ongoing_exams'] ?? 0) . '" 
        data-completed="' . ($report_data['exam_stats']['completed_exams'] ?? 0) . '"
        data-students="' . ($report_data['users']['student'] ?? 0) . '"
        data-trainers="' . ($report_data['users']['trainer'] ?? 0) . '"
        data-supervisors="' . ($report_data['users']['supervisor'] ?? 0) . '"
        style="display:none;"></div>';
}
?>

<?php
// Include footer
include_once "../../includes/footer.php";
?>

<!-- Exam Details Modal -->
<div class="modal fade" id="examDetailsModal" tabindex="-1" role="dialog" aria-labelledby="examDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="examDetailsModalLabel">
                    <i class="fas fa-chart-bar"></i> Exam Details: <span></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4" id="examSummary">
                    <!-- Summary stats will appear here -->
                </div>
                <div id="examDetailsContent">
                    <!-- Question stats will be loaded here -->
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading exam details...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times"></i> Close
                </button>
                <button type="button" class="btn btn-primary" id="printExamDetails">
                    <i class="fas fa-print"></i> Print Details
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Student Profile Modal -->
<div class="modal fade" id="studentProfileModal" tabindex="-1" role="dialog" aria-labelledby="studentProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="studentProfileModalLabel">
                    <i class="fas fa-user-graduate"></i> Student Profile: <span></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <!-- Student Info Section - Removed picture -->
                    <div class="col-md-4">
                        <h5 id="studentName" class="text-center mb-3">Loading...</h5>
                        <p id="studentId" class="text-center text-muted mb-3">Loading...</p>
                        <div id="studentContact" class="mt-3">
                            <!-- Contact info will be loaded here -->
                        </div>
                    </div>

                    <!-- Performance Summary Section -->
                    <div class="col-md-8">
                        <div class="row" id="performanceSummary">
                            <!-- Performance cards will be loaded here -->
                        </div>

                        <!-- Progress Bar -->
                        <div class="mt-4" id="progressBarSection">
                            <!-- Progress bar will be loaded here -->
                        </div>
                    </div>
                </div>

                <!-- Exam Results Section -->
                <div id="examResultsSection">
                    <h5 class="border-bottom pb-2 mb-3"><i class="fas fa-clipboard-list"></i> Exam Results</h5>
                    <div id="examResultsContent">
                        <!-- Exam results table will be loaded here -->
                        <div class="text-center py-5">
                            <div class="spinner-border text-info" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="mt-2 text-muted">Loading student data...</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" class="btn btn-info" id="viewFullProfileBtn" target="_blank">
                    <i class="fas fa-external-link-alt"></i> View Full Profile
                </a>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Trainer Profile Modal -->
<div class="modal fade" id="trainerProfileModal" tabindex="-1" role="dialog" aria-labelledby="trainerProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="trainerProfileModalLabel"><i class="fas fa-chalkboard-teacher"></i> Trainer Profile</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card shadow mb-4 border-left-primary">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-user"></i> Trainer Information</h6>
                            </div>
                            <div class="card-body">
                                <div id="trainerInfoContent">
                                    <div class="text-center py-3">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="sr-only">Loading...</span>
                                        </div>
                                        <p class="mt-2 text-muted">Loading trainer information...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card shadow mb-4 border-left-success">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-chart-line"></i> Performance Summary</h6>
                            </div>
                            <div class="card-body">
                                <div id="trainerProgressBarSection">
                                    <div class="text-center py-3">
                                        <div class="spinner-border text-success" role="status">
                                            <span class="sr-only">Loading...</span>
                                        </div>
                                        <p class="mt-2 text-muted">Loading performance data...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4 border-left-info">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-info"><i class="fas fa-book"></i> Courses and Exams</h6>
                    </div>
                    <div class="card-body">
                        <div id="trainerCoursesContent">
                            <div class="text-center py-3">
                                <div class="spinner-border text-info" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                                <p class="mt-2 text-muted">Loading courses data...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4 border-left-warning">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-warning"><i class="fas fa-user-graduate"></i> Student Performance</h6>
                    </div>
                    <div class="card-body">
                        <div id="trainerStudentsContent">
                            <div class="text-center py-3">
                                <div class="spinner-border text-warning" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                                <p class="mt-2 text-muted">Loading student data...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" class="btn btn-primary" id="viewTrainerProfileBtn" target="_blank">
                    <i class="fas fa-external-link-alt"></i> View Full Profile
                </a>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer and scripts
include_once "../../includes/footer.php";
?>

<!-- Page level plugins -->
<script src="<?php echo $base_url; ?>/vendor/chart.js/Chart.min.js"></script>
<script src="<?php echo $base_url; ?>/vendor/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo $base_url; ?>/vendor/datatables/dataTables.bootstrap4.min.js"></script>

<!-- Page level custom scripts -->
<script src="<?php echo $base_url; ?>/js/demo/datatables-demo.js"></script>

<!-- Custom Report Scripts -->
<script>
    // Charting logic (if any, like for overview)
    <?php if ($report_type == 'overview'): ?>
        // ... (existing chart JS code) ...
    <?php endif; ?>

    // DataTables initialization for relevant tables
    $(document).ready(function() {
        // Initialize DataTable for performance tables if they exist
        if ($('#dataTable').length) {
            $('#dataTable').DataTable({
                "order": [] // Optional: disable initial sorting or set default
            });
        }
    });

    // Print and Export functionality
    $('#printReport').on('click', function() {
        window.print();
    });

    $('#exportCSV').on('click', function() {
        // Basic CSV export (adjust based on the visible table)
        let csv = [];
        let rows = document.querySelectorAll("#reportContent table tr");

        for (let i = 0; i < rows.length; i++) {
            let row = [],
                cols = rows[i].querySelectorAll("td, th");

            // Skip the actions column for export
            for (let j = 0; j < cols.length; j++) {
                if (cols[j].querySelector('.view-exam-details-btn') === null && cols[j].innerText.trim() !== 'Actions') {
                    // Escape commas and quotes
                    let data = cols[j].innerText.replace(/"/g, '""');
                    if (data.includes(',')) {
                        data = '"' + data + '"';
                    }
                    row.push(data);
                }
            }
            csv.push(row.join(","));
        }

        // Download CSV file
        let csvFile = new Blob([csv.join("\n")], {
            type: "text/csv"
        });
        let downloadLink = document.createElement("a");
        let reportTitle = "<?php echo $page_title . '_' . $report_type; ?>";
        downloadLink.download = reportTitle.replace(/ /g, "_") + "_export.csv";
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.style.display = "none";
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
    });

    // --- NEW: Exam Details Modal Logic ---
    $('.view-exam-details-btn').on('click', function() {
        var examId = $(this).data('exam-id');
        var examTitle = $(this).data('exam-title');
        var modal = $('#examDetailsModal');
        var contentArea = $('#examDetailsContent');
        var summaryArea = $('#examSummary');

        // Set modal title
        modal.find('.modal-title span').text(examTitle);

        // Show loading state
        contentArea.html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div><p class="mt-2 text-muted">Loading exam details...</p></div>');
        summaryArea.html('');

        // Show the modal
        modal.modal('show');

        // Fetch exam question stats via AJAX
        $.ajax({
            url: 'get_exam_question_stats.php',
            type: 'GET',
            data: {
                exam_id: examId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.questions) {
                    // Calculate summary statistics
                    var totalQuestions = response.questions.length;
                    var totalAnswers = 0;
                    var totalCorrect = 0;
                    var totalIncorrect = 0;

                    $.each(response.questions, function(index, q) {
                        totalAnswers += q.total_answers;
                        totalCorrect += q.correct_count;
                        totalIncorrect += q.incorrect_count;
                    });

                    var correctPercentage = totalAnswers > 0 ? Math.round((totalCorrect / totalAnswers) * 100) : 0;

                    // Display summary statistics
                    var summaryHtml = '<div class="row">';
                    summaryHtml += '<div class="col-md-3"><div class="card bg-primary text-white shadow"><div class="card-body py-3"><div class="font-weight-bold text-center">Questions<br><span class="h3">' + totalQuestions + '</span></div></div></div></div>';
                    summaryHtml += '<div class="col-md-3"><div class="card bg-success text-white shadow"><div class="card-body py-3"><div class="font-weight-bold text-center">Correct<br><span class="h3">' + totalCorrect + '</span></div></div></div></div>';
                    summaryHtml += '<div class="col-md-3"><div class="card bg-danger text-white shadow"><div class="card-body py-3"><div class="font-weight-bold text-center">Incorrect<br><span class="h3">' + totalIncorrect + '</span></div></div></div></div>';
                    summaryHtml += '<div class="col-md-3"><div class="card bg-info text-white shadow"><div class="card-body py-3"><div class="font-weight-bold text-center">Success Rate<br><span class="h3">' + correctPercentage + '%</span></div></div></div></div>';
                    summaryHtml += '</div>';

                    summaryArea.html(summaryHtml);

                    // Build questions table
                    var html = '<div class="table-responsive mt-4"><table class="table table-hover table-striped">';
                    html += '<thead class="thead-dark"><tr><th width="5%">#</th><th width="50%">Question</th><th width="15%" class="text-center"><i class="fas fa-check text-success"></i> Correct</th><th width="15%" class="text-center"><i class="fas fa-times text-danger"></i> Incorrect</th><th width="15%" class="text-center"><i class="fas fa-users"></i> Total</th></tr></thead>';
                    html += '<tbody>';

                    if (response.questions.length > 0) {
                        $.each(response.questions, function(index, q) {
                            var correctPercent = q.total_answers > 0 ? Math.round((q.correct_count / q.total_answers) * 100) : 0;
                            var rowClass = correctPercent >= 70 ? 'table-success' : (correctPercent <= 30 ? 'table-danger' : '');

                            html += '<tr class="' + rowClass + '">';
                            html += '<td>' + (index + 1) + '</td>';
                            html += '<td>' + $('<div>').text(q.question_text).html() + '</td>';
                            html += '<td class="text-center font-weight-bold text-success">' + q.correct_count + '</td>';
                            html += '<td class="text-center font-weight-bold text-danger">' + q.incorrect_count + '</td>';
                            html += '<td class="text-center">' + q.total_answers + ' <small>(' + correctPercent + '%)</small></td>';
                            html += '</tr>';
                        });
                    } else {
                        html += '<tr><td colspan="5" class="text-center">No questions found or no attempts made for this exam.</td></tr>';
                    }

                    html += '</tbody></table></div>';
                    contentArea.html(html);
                } else {
                    contentArea.html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-2"></i>Error: ' + (response.message || 'Could not load exam details.') + '</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", status, error);
                contentArea.html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-2"></i>An error occurred while fetching data. Please try again later.</div>');
            }
        });
    });

    // Print exam details
    $('#printExamDetails').on('click', function() {
        var modalContent = $('#examDetailsModal .modal-body').html();
        var examTitle = $('#examDetailsModalLabel span').text();
        var printWindow = window.open('', '_blank');

        printWindow.document.write('<html><head><title>Exam Details: ' + examTitle + '</title>');
        printWindow.document.write('<link rel="stylesheet" href="<?php echo $base_url; ?>/vendor/fontawesome-free/css/all.min.css">');
        printWindow.document.write('<link rel="stylesheet" href="<?php echo $base_url; ?>/css/sb-admin-2.min.css">');
        printWindow.document.write('<style>body { padding: 20px; } .card { margin-bottom: 20px; }</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write('<h2 class="mb-4">Exam Details: ' + examTitle + '</h2>');
        printWindow.document.write(modalContent);
        printWindow.document.write('</body></html>');

        printWindow.document.close();
        printWindow.focus();

        setTimeout(function() {
            printWindow.print();
            printWindow.close();
        }, 500);
    });

    $(document).ready(function() {
        // Test modal functionality
        $('.close, .btn-secondary[data-dismiss="modal"]').on('click', function() {
            console.log('Close button clicked');
            $('#examDetailsModal, #studentProfileModal, #trainerProfileModal').modal('hide');
        });
    });
    // --- END: Exam Details Modal Logic ---



    // --- Student Profile Modal Logic ---
    $('.view-student-profile-btn').on('click', function() {
        var studentId = $(this).data('student-id');
        var studentName = $(this).data('student-name');
        var modal = $('#studentProfileModal');

        // Set modal title
        modal.find('.modal-title span').text(studentName);

        // Update the "View Full Profile" button link
        $('#viewFullProfileBtn').attr('href', 'student_profile.php?id=' + studentId);

        // Show the modal
        modal.modal('show');

        // Fetch student profile data via AJAX
        $.ajax({
            url: 'get_student_profile.php',
            type: 'GET',
            data: {
                student_id: studentId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Update student info
                    $('#studentName').text(response.student.first_name + ' ' + response.student.last_name);
                    $('#studentId').text(response.student.user_id);

                    // Update contact info
                    var contactHtml = '<div class="mb-2"><i class="fas fa-envelope mr-2"></i>' + response.student.email + '</div>';
                    if (response.student.phone) {
                        contactHtml += '<div class="mb-2"><i class="fas fa-phone mr-2"></i>' + response.student.phone + '</div>';
                    }
                    contactHtml += '<div><i class="fas fa-calendar-alt mr-2"></i>Member since: ' +
                        new Date(response.student.created_at).toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        }) + '</div>';
                    $('#studentContact').html(contactHtml);

                    // Update performance summary
                    var summaryHtml = '';
                    summaryHtml += '<div class="col-md-4 mb-3">' +
                        '<div class="card border-left-primary shadow h-100 py-2">' +
                        '<div class="card-body py-2">' +
                        '<div class="row no-gutters align-items-center">' +
                        '<div class="col mr-2">' +
                        '<div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Exams Taken</div>' +
                        '<div class="h5 mb-0 font-weight-bold text-gray-800">' + response.stats.total_exams + '</div>' +
                        '</div>' +
                        '<div class="col-auto">' +
                        '<i class="fas fa-clipboard-list fa-2x text-gray-300"></i>' +
                        '</div></div></div></div></div>';

                    summaryHtml += '<div class="col-md-4 mb-3">' +
                        '<div class="card border-left-success shadow h-100 py-2">' +
                        '<div class="card-body py-2">' +
                        '<div class="row no-gutters align-items-center">' +
                        '<div class="col mr-2">' +
                        '<div class="text-xs font-weight-bold text-success text-uppercase mb-1">Average Score</div>' +
                        '<div class="h5 mb-0 font-weight-bold text-gray-800">' + response.stats.avg_percentage.toFixed(2) + '%</div>' +
                        '</div>' +
                        '<div class="col-auto">' +
                        '<i class="fas fa-percentage fa-2x text-gray-300"></i>' +
                        '</div></div></div></div></div>';

                    summaryHtml += '<div class="col-md-4 mb-3">' +
                        '<div class="card border-left-warning shadow h-100 py-2">' +
                        '<div class="card-body py-2">' +
                        '<div class="row no-gutters align-items-center">' +
                        '<div class="col mr-2">' +
                        '<div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Exams Passed</div>' +
                        '<div class="h5 mb-0 font-weight-bold text-gray-800">' + response.stats.exams_passed + ' / ' + response.stats.total_exams + '</div>' +
                        '</div>' +
                        '<div class="col-auto">' +
                        '<i class="fas fa-award fa-2x text-gray-300"></i>' +
                        '</div></div></div></div></div>';

                    $('#performanceSummary').html(summaryHtml);

                    // Update progress bar
                    var barClass = "bg-danger";
                    if (response.stats.avg_percentage >= 60 && response.stats.avg_percentage < 75) {
                        barClass = "bg-warning";
                    } else if (response.stats.avg_percentage >= 75 && response.stats.avg_percentage < 90) {
                        barClass = "bg-info";
                    } else if (response.stats.avg_percentage >= 90) {
                        barClass = "bg-success";
                    }

                    var progressHtml = '<h4 class="small font-weight-bold">Overall Performance <span class="float-right">' +
                        response.stats.avg_percentage.toFixed(2) + '%</span></h4>' +
                        '<div class="progress mb-4">' +
                        '<div class="progress-bar ' + barClass + '" role="progressbar" style="width: ' +
                        response.stats.avg_percentage + '%" aria-valuenow="' + response.stats.avg_percentage +
                        '" aria-valuemin="0" aria-valuemax="100"></div></div>';

                    $('#progressBarSection').html(progressHtml);

                    // Update exam results table
                    if (response.exams.length > 0) {
                        var tableHtml = '<div class="table-responsive">' +
                            '<table class="table table-bordered table-hover table-sm">' +
                            '<thead class="thead-light">' +
                            '<tr>' +
                            '<th>Exam</th>' +
                            '<th>Course</th>' +
                            '<th>Score</th>' +
                            '<th>Total</th>' +
                            '<th>Percentage</th>' +
                            '<th>Status</th>' +
                            '<th>Date</th>' +
                            '</tr></thead><tbody>';

                        $.each(response.exams, function(index, exam) {
                            var status = exam.percentage >= 60 ?
                                '<span class="badge text-success">Passed</span>' :
                                '<span class="badge text-danger">Failed</span>';

                            tableHtml += '<tr>' +
                                '<td>' + exam.exam_title + '</td>' +
                                '<td>' + (exam.course_title || 'N/A') + '</td>' +
                                '<td>' + exam.score + '</td>' +
                                '<td>' + exam.total_marks + '</td>' +
                                '<td>' + exam.percentage.toFixed(2) + '%</td>' +
                                '<td>' + status + '</td>' +
                                '<td>' + new Date(exam.submission_time).toLocaleDateString() + '</td>' +
                                '</tr>';
                        });

                        tableHtml += '</tbody></table></div>';
                        $('#examResultsContent').html(tableHtml);
                    } else {
                        $('#examResultsContent').html('<div class="alert alert-info">This student has not taken any exams yet.</div>');
                    }
                } else {
                    // Show error message
                    $('#examResultsContent').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-2"></i>Error: ' +
                        (response.message || 'Could not load student data.') + '</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", status, error);
                $('#examResultsContent').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-2"></i>An error occurred while fetching data. Please try again later.</div>');
            }
        });
    });
    // --- END: Student Profile Modal Logic ---

    // --- Trainer Profile Modal Logic ---
    $(document).on('click', '.view-trainer-details', function(e) {
        e.preventDefault();
        const trainerId = $(this).data('id');

        // Show the modal
        $('#trainerProfileModal').modal('show');

        // Update the "View Full Profile" button link
        $('#viewTrainerProfileBtn').attr('href', 'instructor-profile.php?id=' + trainerId);

        // Reset content
        $('#trainerInfoContent, #trainerProgressBarSection, #trainerCoursesContent, #trainerStudentsContent').html(
            '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div></div>'
        );

        // Fetch trainer data via AJAX
        $.ajax({
            url: 'ajax/get_trainer_profile.php',
            type: 'GET',
            data: {
                trainer_id: trainerId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Update trainer info
                    var infoHtml = '<table class="table table-bordered">' +
                        '<tr><th>Name</th><td>' + response.trainer.first_name + ' ' + response.trainer.last_name + '</td></tr>' +
                        '<tr><th>Email</th><td>' + response.trainer.email + '</td></tr>' +
                        '<tr><th>Courses</th><td>' + response.stats.courses_count + '</td></tr>' +
                        '<tr><th>Exams</th><td>' + response.stats.exams_count + '</td></tr>' +
                        '<tr><th>Students</th><td>' + response.stats.students_count + '</td></tr>' +
                        '<tr><th>Pass Rate</th><td>' + response.stats.pass_rate + '%</td></tr>' +
                        '</table>';

                    $('#trainerInfoContent').html(infoHtml);

                    // Update progress bar
                    var barClass = "bg-danger";
                    if (response.stats.pass_rate >= 60 && response.stats.pass_rate < 75) {
                        barClass = "bg-warning";
                    } else if (response.stats.pass_rate >= 75 && response.stats.pass_rate < 90) {
                        barClass = "bg-info";
                    } else if (response.stats.pass_rate >= 90) {
                        barClass = "bg-success";
                    }

                    var progressHtml = '<h4 class="small font-weight-bold">Overall Pass Rate <span class="float-right">' +
                        response.stats.pass_rate.toFixed(2) + '%</span></h4>' +
                        '<div class="progress mb-4">' +
                        '<div class="progress-bar ' + barClass + '" role="progressbar" style="width: ' +
                        response.stats.pass_rate + '%" aria-valuenow="' + response.stats.pass_rate +
                        '" aria-valuemin="0" aria-valuemax="100"></div></div>';

                    $('#trainerProgressBarSection').html(progressHtml);

                    // Update courses table
                    if (response.courses.length > 0) {
                        var coursesHtml = '<div class="table-responsive">' +
                            '<table class="table table-bordered table-hover table-sm">' +
                            '<thead class="thead-light">' +
                            '<tr>' +
                            '<th>Course</th>' +
                            '<th>Exams</th>' +
                            '<th>Students</th>' +
                            '<th>Avg. Score</th>' +
                            '<th>Pass Rate</th>' +
                            '</tr></thead><tbody>';

                        $.each(response.courses, function(index, course) {
                            coursesHtml += '<tr>' +
                                '<td>' + course.title + '</td>' +
                                '<td>' + course.exams_count + '</td>' +
                                '<td>' + course.students_count + '</td>' +
                                '<td>' + course.avg_score.toFixed(2) + '%</td>' +
                                '<td>' + course.pass_rate.toFixed(2) + '%</td>' +
                                '</tr>';
                        });

                        coursesHtml += '</tbody></table></div>';
                        $('#trainerCoursesContent').html(coursesHtml);
                    } else {
                        $('#trainerCoursesContent').html('<div class="alert alert-info">This trainer has not created any courses yet.</div>');
                    }

                    // Update students table
                    if (response.students.length > 0) {
                        var studentsHtml = '<div class="table-responsive">' +
                            '<table class="table table-bordered table-hover table-sm">' +
                            '<thead class="thead-light">' +
                            '<tr>' +
                            '<th>Student</th>' +
                            '<th>Exams Taken</th>' +
                            '<th>Exams Passed</th>' +
                            '<th>Avg. Score</th>' +
                            '</tr></thead><tbody>';

                        $.each(response.students, function(index, student) {
                            studentsHtml += '<tr>' +
                                '<td>' + student.first_name + ' ' + student.last_name + '</td>' +
                                '<td>' + student.exams_taken + '</td>' +
                                '<td>' + student.exams_passed + '</td>' +
                                '<td>' + student.avg_percentage.toFixed(2) + '%</td>' +
                                '</tr>';
                        });

                        studentsHtml += '</tbody></table></div>';
                        $('#trainerStudentsContent').html(studentsHtml);
                    } else {
                        $('#trainerStudentsContent').html('<div class="alert alert-info">This trainer has no students yet.</div>');
                    }
                } else {
                    // Show error message
                    $('#trainerInfoContent, #trainerCoursesContent, #trainerStudentsContent').html(
                        '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-2"></i>Error: ' +
                        (response.message || 'Could not load trainer data.') + '</div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", status, error);
                $('#trainerInfoContent, #trainerCoursesContent, #trainerStudentsContent').html(
                    '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-2"></i>An error occurred while fetching data. Please try again later.</div>'
                );
            }
        });
    });
    // --- END: Trainer Profile Modal Logic ---
</script>

</body>

</html>