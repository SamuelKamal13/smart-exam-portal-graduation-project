<?php
// Set page title and base URL for includes
$page_title = "Results & Analytics";
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
$assigned_exams = [];
$sql = "SELECT es.exam_id, es.has_viewed, es.has_attempted, es.auto_graded,
               e.title AS exam_title, e.start_time, e.duration, e.total_marks,
               c.title AS course_title,
               r.id as result_id, r.score, r.percentage, r.submission_time
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
            $assigned_exams[] = $row;
        }
    }

    mysqli_stmt_close($stmt);
}

// Separate completed exams for statistics
$results = array_filter($assigned_exams, function ($exam) {
    return $exam['result_id'] !== null;
});

// Get statistics by course
$course_stats = [];
$sql = "SELECT c.title AS course_title, COUNT(r.id) AS exam_count, 
               AVG(r.percentage) AS avg_percentage, MAX(r.percentage) AS max_percentage,
               MIN(r.percentage) AS min_percentage
        FROM results r 
        JOIN exams e ON r.exam_id = e.id 
        JOIN courses c ON e.course_id = c.id
        WHERE r.student_id = ? 
        GROUP BY c.id
        ORDER BY avg_percentage DESC";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $course_stats[] = $row;
        }
    }

    mysqli_stmt_close($stmt);
}

// Get overall statistics
$overall_stats = [
    'total_exams' => count($results),
    'avg_percentage' => 0,
    'passed_exams' => 0,
    'failed_exams' => 0
];

if (count($results) > 0) {
    $total_percentage = 0;
    foreach ($results as $result) {
        $total_percentage += $result['percentage'];
        if ($result['percentage'] >= 40) { // Assuming 40% is pass mark
            $overall_stats['passed_exams']++;
        } else {
            $overall_stats['failed_exams']++;
        }
    }
    $overall_stats['avg_percentage'] = $total_percentage / count($results);
}

// Count missed exams as failed exams
foreach ($assigned_exams as $exam) {
    $status = getExamStatus($exam);
    if ($status['status'] === 'missed' && $exam['result_id'] === null) {
        $overall_stats['failed_exams']++;
        $overall_stats['total_exams']++; // Also count missed exams in total
    }
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
    <h1 class="mt-4 mb-4">Results & Analytics</h1>

    <!-- Overall Statistics -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Exams Taken</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $overall_stats['total_exams']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
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
                                Average Score</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($overall_stats['avg_percentage'], 1); ?>%</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-percentage fa-2x text-gray-300"></i>
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
                                Passed Exams</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $overall_stats['passed_exams']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                                Failed Exams</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $overall_stats['failed_exams']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Performance Chart -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Performance Over Time</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="performanceChart"></canvas>
                    </div>
                    <hr>
                    <small class="text-muted">This chart shows your exam scores over time.</small>
                </div>
            </div>
        </div>

        <!-- Course Performance -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Performance by Course</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie mb-4">
                        <canvas id="coursePerformanceChart"></canvas>
                    </div>
                    <hr>
                    <small class="text-muted">This chart shows your average performance across different courses.</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Results -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Exam History</h6>
            <a href="exam-history.php" class="btn btn-sm btn-primary">View All History</a>
        </div>
        <div class="card-body">
            <?php if (count($assigned_exams) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="resultsTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Exam</th>
                                <th>Course</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Score</th>
                                <th>Percentage</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assigned_exams as $exam):
                                $status = getExamStatus($exam);
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($exam['exam_title']); ?></td>
                                    <td><?php echo htmlspecialchars($exam['course_title'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($exam['start_time'])); ?></td>
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
                                            <a href="../exams/take-exam.php?id=<?php echo $exam['exam_id']; ?>" class="btn btn-success btn-sm">
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

    <!-- Course Statistics -->
    <?php if (count($course_stats) > 0): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Course Statistics</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Exams Taken</th>
                                <th>Average Score</th>
                                <th>Highest Score</th>
                                <th>Lowest Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($course_stats as $stat): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($stat['course_title']); ?></td>
                                    <td><?php echo $stat['exam_count']; ?></td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar bg-<?php echo getProgressBarColor($stat['avg_percentage']); ?>"
                                                role="progressbar" style="width: <?php echo $stat['avg_percentage']; ?>%"
                                                aria-valuenow="<?php echo $stat['avg_percentage']; ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?php echo number_format($stat['avg_percentage'], 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo number_format($stat['max_percentage'], 1); ?>%</td>
                                    <td><?php echo number_format($stat['min_percentage'], 1); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Initialize Charts -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Performance Over Time Chart
        var performanceCtx = document.getElementById('performanceChart').getContext('2d');

        // Prepare data for chart including both completed and missed exams
        var chartData = [];
        var chartLabels = [];

        <?php
        // Sort all exams by date for the chart
        usort($assigned_exams, function ($a, $b) {
            $dateA = isset($a['submission_time']) ? strtotime($a['submission_time']) : strtotime($a['start_time']);
            $dateB = isset($b['submission_time']) ? strtotime($b['submission_time']) : strtotime($b['start_time']);
            return $dateB - $dateA; // Sort descending
        });

        // Get the 10 most recent exams (completed or missed)
        $recent_exams = array_slice($assigned_exams, 0, 10);

        // Reverse to show chronological order in chart
        $recent_exams = array_reverse($recent_exams);

        echo "chartLabels = [";
        $labels = [];
        foreach ($recent_exams as $exam) {
            $date = isset($exam['submission_time']) ? $exam['submission_time'] : $exam['start_time'];
            $labels[] = "'" . date('M d, Y', strtotime($date)) . "'";
        }
        echo implode(', ', $labels);
        echo "];\n";

        echo "chartData = [";
        $data = [];
        foreach ($recent_exams as $exam) {
            $status = getExamStatus($exam);
            if ($exam['result_id'] !== null) {
                // Completed exam with actual percentage
                $data[] = $exam['percentage'];
            } elseif ($status['status'] === 'missed') {
                // Missed exam - show as 0%
                $data[] = 0;
            } else {
                // Upcoming or available exam - don't include
                $data[] = 'null';
            }
        }
        echo implode(', ', $data);
        echo "];\n";
        ?>

        var performanceData = {
            labels: chartLabels,
            datasets: [{
                label: 'Score Percentage',
                data: chartData,
                backgroundColor: 'rgba(78, 115, 223, 0.05)',
                borderColor: 'rgba(78, 115, 223, 1)',
                pointRadius: 3,
                pointBackgroundColor: function(context) {
                    var index = context.dataIndex;
                    var value = context.dataset.data[index];
                    return value === 0 ? 'rgba(231, 74, 59, 1)' : 'rgba(78, 115, 223, 1)';
                },
                pointBorderColor: function(context) {
                    var index = context.dataIndex;
                    var value = context.dataset.data[index];
                    return value === 0 ? 'rgba(231, 74, 59, 1)' : 'rgba(78, 115, 223, 1)';
                },
                pointHoverRadius: 5,
                pointHoverBackgroundColor: function(context) {
                    var index = context.dataIndex;
                    var value = context.dataset.data[index];
                    return value === 0 ? 'rgba(231, 74, 59, 1)' : 'rgba(78, 115, 223, 1)';
                },
                pointHoverBorderColor: function(context) {
                    var index = context.dataIndex;
                    var value = context.dataset.data[index];
                    return value === 0 ? 'rgba(231, 74, 59, 1)' : 'rgba(78, 115, 223, 1)';
                },
                pointHitRadius: 10,
                pointBorderWidth: 2,
                lineTension: 0.3,
                spanGaps: true
            }]
        };

        var performanceChart = new Chart(performanceCtx, {
            type: 'line',
            data: performanceData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var value = context.parsed.y;
                                if (value === 0) {
                                    return 'Missed Exam (0%)';
                                }
                                return context.dataset.label + ': ' + value + '%';
                            }
                        }
                    }
                }
            }
        });

        // Course Performance Chart
        <?php if (count($course_stats) > 0): ?>
            // Add missed exams to course statistics for the chart
            var courseLabels = [
                <?php
                $labels = array_map(function ($stat) {
                    return "'" . addslashes($stat['course_title']) . "'";
                }, $course_stats);
                echo implode(', ', $labels);
                ?>
            ];

            var courseData = [
                <?php
                $data = array_map(function ($stat) {
                    return $stat['avg_percentage'];
                }, $course_stats);
                echo implode(', ', $data);
                ?>
            ];

            // Add a "Missed Exams" category if there are any
            <?php
            $missed_count = 0;
            foreach ($assigned_exams as $exam) {
                $status = getExamStatus($exam);
                if ($status['status'] === 'missed' && $exam['result_id'] === null) {
                    $missed_count++;
                }
            }
            if ($missed_count > 0):
            ?>
                courseLabels.push('Missed Exams');
                courseData.push(0); // 0% for missed exams
            <?php endif; ?>

            var courseCtx = document.getElementById('coursePerformanceChart').getContext('2d');
            var courseChartData = {
                labels: courseLabels,
                datasets: [{
                    data: courseData,
                    backgroundColor: [
                        'rgba(78, 115, 223, 0.8)',
                        'rgba(28, 200, 138, 0.8)',
                        'rgba(54, 185, 204, 0.8)',
                        'rgba(246, 194, 62, 0.8)',
                        'rgba(231, 74, 59, 0.8)',
                        'rgba(133, 135, 150, 0.8)'
                    ],
                    hoverBackgroundColor: [
                        'rgba(78, 115, 223, 1)',
                        'rgba(28, 200, 138, 1)',
                        'rgba(54, 185, 204, 1)',
                        'rgba(246, 194, 62, 1)',
                        'rgba(231, 74, 59, 1)',
                        'rgba(133, 135, 150, 1)'
                    ],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }]
            };

            var courseChart = new Chart(courseCtx, {
                type: 'doughnut',
                data: courseChartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var label = context.label;
                                    var value = context.parsed;
                                    if (label === 'Missed Exams') {
                                        return label + ': ' + <?php echo $missed_count; ?> + ' exam(s)';
                                    }
                                    return label + ': ' + value + '%';
                                }
                            }
                        },
                        legend: {
                            position: 'bottom'
                        }
                    },
                    cutout: '70%'
                }
            });
        <?php endif; ?>

        // Initialize DataTable
        $('#resultsTable').DataTable({
            order: [
                [2, 'desc']
            ], // Sort by date (descending)
            pageLength: 5,
            lengthMenu: [
                [5, 10, 25, -1],
                [5, 10, 25, "All"]
            ]
        });
    });
</script>

<?php
// Include footer
include_once "../../includes/footer.php";
?>