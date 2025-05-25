<?php
// Set page title and base URL for includes
$page_title = "Exam Results";
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

// Initialize variables
$exam_id = null;
$course_id = null;
$exams = [];
$results = [];
$students = [];
$questions = [];
$question_stats = [];

// Fetch all courses taught by this trainer
$courses = [];
$courses_sql = "SELECT id, title FROM courses WHERE trainer_id = ? ORDER BY title";
if ($courses_stmt = mysqli_prepare($conn, $courses_sql)) {
    mysqli_stmt_bind_param($courses_stmt, "i", $trainer_id);
    if (mysqli_stmt_execute($courses_stmt)) {
        $courses_result = mysqli_stmt_get_result($courses_stmt);
        while ($course = mysqli_fetch_assoc($courses_result)) {
            $courses[] = $course;
        }
    }
    mysqli_stmt_close($courses_stmt);
}

// Check if course ID is provided
if (isset($_GET["course_id"]) && !empty($_GET["course_id"])) {
    $course_id = $_GET["course_id"];

    // Fetch exams for this course
    $exams_sql = "SELECT id, title FROM exams WHERE course_id = ? ORDER BY start_time DESC";
    if ($exams_stmt = mysqli_prepare($conn, $exams_sql)) {
        mysqli_stmt_bind_param($exams_stmt, "i", $course_id);
        if (mysqli_stmt_execute($exams_stmt)) {
            $exams_result = mysqli_stmt_get_result($exams_stmt);
            while ($exam = mysqli_fetch_assoc($exams_result)) {
                $exams[] = $exam;
            }
        }
        mysqli_stmt_close($exams_stmt);
    }
}

// Check if exam ID is provided
if (isset($_GET["exam_id"]) && !empty($_GET["exam_id"])) {
    $exam_id = $_GET["exam_id"];

    // Fetch exam details
    $exam_details = null;
    $exam_sql = "SELECT e.*, c.title AS course_title 
                FROM exams e 
                JOIN courses c ON e.course_id = c.id 
                WHERE e.id = ? AND c.trainer_id = ?";
    if ($exam_stmt = mysqli_prepare($conn, $exam_sql)) {
        mysqli_stmt_bind_param($exam_stmt, "ii", $exam_id, $trainer_id);
        if (mysqli_stmt_execute($exam_stmt)) {
            $exam_result = mysqli_stmt_get_result($exam_stmt);
            if (mysqli_num_rows($exam_result) == 1) {
                $exam_details = mysqli_fetch_assoc($exam_result);
            }
        }
        mysqli_stmt_close($exam_stmt);
    }

    if ($exam_details) {
        // Fetch all assigned students (including those who didn't take the exam)
        $students_sql = "SELECT 
                            u.id, u.first_name, u.last_name, u.email,
                            es.has_viewed, es.has_attempted, es.auto_graded,
                            r.id as result_id, r.score, r.total_marks, r.percentage, r.submission_time
                        FROM exam_students es
                        JOIN users u ON es.student_id = u.id
                        LEFT JOIN results r ON es.exam_id = r.exam_id AND es.student_id = r.student_id
                        WHERE es.exam_id = ?
                        ORDER BY u.last_name, u.first_name";

        $students = [];
        $results = [];

        if ($students_stmt = mysqli_prepare($conn, $students_sql)) {
            mysqli_stmt_bind_param($students_stmt, "i", $exam_id);
            if (mysqli_stmt_execute($students_stmt)) {
                $students_result = mysqli_stmt_get_result($students_stmt);
                while ($student = mysqli_fetch_assoc($students_result)) {
                    $students[] = $student;
                    if ($student['result_id'] !== null) {
                        $results[] = $student; // Add to results only if submitted
                    }
                }
            }
            mysqli_stmt_close($students_stmt);
        }

        // Calculate statistics based on submitted results
        $total_students = count($students);
        $completed_students = count($results);
        $not_attempted_students = $total_students - $completed_students;
        $avg_score = 0;
        $max_score = 0;
        $min_score = $exam_details["total_marks"];
        $pass_count = 0;
        $fail_count = 0;
        $pass_percentage = 50; // Default pass percentage

        if ($completed_students > 0) {
            $total_percentage = 0;
            foreach ($results as $result) {
                $total_percentage += $result["percentage"];
                if ($result["score"] > $max_score) $max_score = $result["score"];
                if ($result["score"] < $min_score) $min_score = $result["score"];
                if ($result["percentage"] >= $pass_percentage) {
                    $pass_count++;
                } else {
                    $fail_count++;
                }
            }
            $avg_score = $total_percentage / $completed_students;
        }

        // Count not attempted students as failed
        $fail_count += $not_attempted_students;

        // Fetch questions for this exam
        $questions_sql = "SELECT * FROM questions WHERE exam_id = ? ORDER BY id";
        if ($questions_stmt = mysqli_prepare($conn, $questions_sql)) {
            mysqli_stmt_bind_param($questions_stmt, "i", $exam_id);
            if (mysqli_stmt_execute($questions_stmt)) {
                $questions_result = mysqli_stmt_get_result($questions_stmt);
                while ($question = mysqli_fetch_assoc($questions_result)) {
                    $questions[] = $question;

                    // Initialize question stats
                    $question_stats[$question['id']] = [
                        'question_text' => substr($question['question_text'], 0, 50) . (strlen($question['question_text']) > 50 ? '...' : ''),
                        'correct_count' => 0,
                        'incorrect_count' => 0,
                        'correct_percentage' => 0
                    ];
                }
            }
            mysqli_stmt_close($questions_stmt);
        }

        // Fetch question performance statistics
        if (!empty($questions)) {
            $question_performance_sql = "SELECT sa.question_id, sa.is_correct, COUNT(*) as count
                                      FROM student_answers sa
                                      JOIN exam_attempts ea ON sa.attempt_id = ea.id
                                      WHERE ea.exam_id = ? AND ea.status = 'completed'
                                      GROUP BY sa.question_id, sa.is_correct";
            if ($question_performance_stmt = mysqli_prepare($conn, $question_performance_sql)) {
                mysqli_stmt_bind_param($question_performance_stmt, "i", $exam_id);
                if (mysqli_stmt_execute($question_performance_stmt)) {
                    $question_performance_result = mysqli_stmt_get_result($question_performance_stmt);
                    while ($stat = mysqli_fetch_assoc($question_performance_result)) {
                        if ($stat['is_correct']) {
                            $question_stats[$stat['question_id']]['correct_count'] = $stat['count'];
                        } else {
                            $question_stats[$stat['question_id']]['incorrect_count'] = $stat['count'];
                        }
                    }
                }
                mysqli_stmt_close($question_performance_stmt);
            }

            // Calculate percentages
            foreach ($question_stats as $q_id => &$stat) {
                $total_answers = $stat['correct_count'] + $stat['incorrect_count'];
                if ($total_answers > 0) {
                    $stat['correct_percentage'] = ($stat['correct_count'] / $total_answers) * 100;
                }
            }
        }

        // Prepare data for charts
        $score_distribution = array_fill(0, 11, 0); // 0-10, 11-20, ..., 91-100, Not Attempted
        foreach ($students as $student) {
            if ($student["result_id"] !== null) {
                $bucket = min(9, floor($student["percentage"] / 10));
                $score_distribution[$bucket]++;
            } else {
                // Not attempted goes in the last bucket
                $score_distribution[10]++;
            }
        }

        // Prepare question performance data for chart
        $question_labels = [];
        $question_correct_percentages = [];
        foreach ($question_stats as $q_id => $stat) {
            $question_labels[] = "Q" . $q_id;
            $question_correct_percentages[] = $stat['correct_percentage'];
        }
    }
}

// Include header
include_once "../../includes/header.php";
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Exam Results</h1>
    </div>

    <!-- Filter Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Select Exam</h6>
        </div>
        <div class="card-body">
            <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="form-inline">
                <div class="form-group mb-2 mr-2">
                    <label for="course_id" class="mr-2">Course:</label>
                    <select class="form-control" id="course_id" name="course_id" onchange="this.form.submit()">
                        <option value="">Select Course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>" <?php echo ($course_id == $course['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!empty($exams)): ?>
                    <div class="form-group mb-2 mr-2">
                        <label for="exam_id" class="mr-2">Exam:</label>
                        <select class="form-control" id="exam_id" name="exam_id" onchange="this.form.submit()">
                            <option value="">Select Exam</option>
                            <?php foreach ($exams as $exam): ?>
                                <option value="<?php echo $exam['id']; ?>" <?php echo ($exam_id == $exam['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($exam['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if (isset($exam_details)): ?>
        <!-- Results Overview -->
        <div class="row">
            <!-- Overall Statistics -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Students Completed</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_students; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-users fa-2x text-gray-300"></i>
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
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($avg_score, 2); ?>%</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-percent fa-2x text-gray-300"></i>
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
                                    Pass Rate</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo ($total_students > 0) ? number_format(($pass_count / $total_students) * 100, 2) : 0; ?>%
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clipboard-check fa-2x text-gray-300"></i>
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
                                    Score Range</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo ($total_students > 0) ? $min_score : 'N/A'; ?> - <?php echo $max_score; ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Score Distribution Chart -->
            <div class="col-xl-6 col-lg-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Score Distribution</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-bar">
                            <canvas id="scoreDistributionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Question Performance Chart -->
            <div class="col-xl-6 col-lg-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Question Performance</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-bar">
                            <canvas id="questionPerformanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Completion Status Chart -->
        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Completion Status</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4">
                        <canvas id="completionStatusChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Pass/Fail Chart -->
            <div class="col-xl-6 col-lg-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Pass/Fail Status</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-pie pt-4">
                            <canvas id="passFailChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Question Performance Details -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Question Performance Details</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="questionTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Question</th>
                                <th>Type</th>
                                <th>Correct Answers</th>
                                <th>Incorrect Answers</th>
                                <th>Success Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($questions as $question): ?>
                                <?php $stats = $question_stats[$question['id']]; ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(substr($question['question_text'], 0, 100)) . (strlen($question['question_text']) > 100 ? '...' : ''); ?></td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?></td>
                                    <td><?php echo $stats['correct_count']; ?></td>
                                    <td><?php echo $stats['incorrect_count']; ?></td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $stats['correct_percentage']; ?>%"
                                                aria-valuenow="<?php echo $stats['correct_percentage']; ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?php echo number_format($stats['correct_percentage'], 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Student Results Table -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Student Results</h6>
            </div>
            <div class="card-body">
                <?php if (empty($students)): ?>
                    <div class="text-center py-4">
                        <p class="text-gray-500">No students have been assigned to this exam yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered" id="resultsTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Score</th>
                                    <th>Percentage</th>
                                    <th>Submission Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student["first_name"] . " " . $student["last_name"]); ?></td>
                                        <td><?php echo htmlspecialchars($student["email"]); ?></td>
                                        <td>
                                            <?php
                                            if ($student["result_id"] !== null) {
                                                echo '<span class="badge text-success">Completed</span>';
                                            } elseif ($student["has_attempted"]) {
                                                echo '<span class="badge text-warning">Started but not completed</span>';
                                            } elseif ($student["has_viewed"]) {
                                                echo '<span class="badge text-info">Viewed only</span>';
                                            } else {
                                                echo '<span class="badge text-danger">Not attempted</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            if ($student["result_id"] !== null) {
                                                echo $student["score"] . " / " . $student["total_marks"];
                                            } else {
                                                echo "N/A";
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($student["result_id"] !== null): ?>
                                                <div class="progress">
                                                    <div class="progress-bar <?php echo ($student["percentage"] >= $pass_percentage) ? 'bg-success' : 'bg-danger'; ?>" role="progressbar"
                                                        style="width: <?php echo $student["percentage"]; ?>%" aria-valuenow="<?php echo $student["percentage"]; ?>"
                                                        aria-valuemin="0" aria-valuemax="100">
                                                        <?php echo number_format($student["percentage"], 2); ?>%
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            if ($student["result_id"] !== null) {
                                                echo date("M d, Y h:i A", strtotime($student["submission_time"]));
                                            } else {
                                                echo "N/A";
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chart Scripts -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            // Score Distribution Chart
            var scoreCtx = document.getElementById('scoreDistributionChart').getContext('2d');
            var scoreChart = new Chart(scoreCtx, {
                type: 'bar',
                data: {
                    labels: ['0-10%', '11-20%', '21-30%', '31-40%', '41-50%', '51-60%', '61-70%', '71-80%', '81-90%', '91-100%', 'Not Attempted'],
                    datasets: [{
                        label: 'Number of Students',
                        data: <?php echo json_encode($score_distribution); ?>,
                        backgroundColor: function(context) {
                            var index = context.dataIndex;
                            // Use red color for Not Attempted (last index)
                            return index === 10 ? 'rgba(231, 74, 59, 0.8)' : 'rgba(78, 115, 223, 0.8)';
                        },
                        borderColor: function(context) {
                            var index = context.dataIndex;
                            return index === 10 ? 'rgba(231, 74, 59, 1)' : 'rgba(78, 115, 223, 1)';
                        },
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            precision: 0
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var label = context.dataset.label || '';
                                    var value = context.parsed.y;
                                    return label + ': ' + value + ' student(s)';
                                }
                            }
                        }
                    }
                }
            });

            // Question Performance Chart
            var questionCtx = document.getElementById('questionPerformanceChart').getContext('2d');
            var questionChart = new Chart(questionCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($question_labels); ?>,
                    datasets: [{
                        label: 'Correct Answer Percentage',
                        data: <?php echo json_encode($question_correct_percentages); ?>,
                        backgroundColor: 'rgba(28, 200, 138, 0.8)',
                        borderColor: 'rgba(28, 200, 138, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Percentage Correct'
                            }
                        }
                    }
                }
            });

            // Completion Status Chart
            var completionCtx = document.getElementById('completionStatusChart').getContext('2d');
            var completionChart = new Chart(completionCtx, {
                type: 'pie',
                data: {
                    labels: ['Completed', 'Not Attempted'],
                    datasets: [{
                        data: [<?php echo $completed_students; ?>, <?php echo $not_attempted_students; ?>],
                        backgroundColor: ['rgba(28, 200, 138, 0.8)', 'rgba(231, 74, 59, 0.8)'],
                        borderColor: ['rgba(28, 200, 138, 1)', 'rgba(231, 74, 59, 1)'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var label = context.label || '';
                                    var value = context.parsed;
                                    var total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    var percentage = Math.round((value / total) * 100);
                                    return label + ': ' + value + ' student(s) (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });

            // Pass/Fail Chart
            var passFailCtx = document.getElementById('passFailChart').getContext('2d');
            var passFailChart = new Chart(passFailCtx, {
                type: 'pie',
                data: {
                    labels: ['Passed', 'Failed/Not Attempted'],
                    datasets: [{
                        data: [<?php echo $pass_count; ?>, <?php echo $fail_count; ?>],
                        backgroundColor: ['rgba(28, 200, 138, 0.8)', 'rgba(231, 74, 59, 0.8)'],
                        borderColor: ['rgba(28, 200, 138, 1)', 'rgba(231, 74, 59, 1)'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var label = context.label || '';
                                    var value = context.parsed;
                                    var total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    var percentage = Math.round((value / total) * 100);
                                    return label + ': ' + value + ' student(s) (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        </script>
    <?php endif; ?>
</div>

<!-- DataTables JavaScript -->
<script>
    $(document).ready(function() {
        $('#resultsTable').DataTable({
            order: [
                [3, 'desc']
            ], // Sort by percentage (descending)
            responsive: true
        });

        $('#questionTable').DataTable({
            order: [
                [4, 'desc']
            ], // Sort by success rate (descending)
            responsive: true
        });
    });
</script>

<?php
// Include footer
include_once "../../includes/footer.php";
