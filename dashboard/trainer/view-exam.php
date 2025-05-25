<?php
// Set page title and base URL for includes
$page_title = "View Exam";
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

// Check if exam ID is provided
if (!isset($_GET["id"]) || empty($_GET["id"])) {
    header("location: manage-exams.php");
    exit;
}

$exam_id = $_GET["id"];

// Fetch exam data
$exam = null;
$sql = "SELECT e.*, c.title as course_title, c.id as course_id 
        FROM exams e 
        JOIN courses c ON e.course_id = c.id 
        WHERE e.id = ? AND e.created_by = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $exam_id, $trainer_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) == 1) {
            $exam = mysqli_fetch_assoc($result);
        } else {
            // Exam not found or doesn't belong to this trainer
            header("location: manage-exams.php");
            exit;
        }
    } else {
        echo "Oops! Something went wrong. Please try again later.";
    }
    mysqli_stmt_close($stmt);
}

// Fetch questions for this exam
$questions = [];
$questions_sql = "SELECT * FROM questions WHERE exam_id = ? ORDER BY id";
if ($questions_stmt = mysqli_prepare($conn, $questions_sql)) {
    mysqli_stmt_bind_param($questions_stmt, "i", $exam_id);
    if (mysqli_stmt_execute($questions_stmt)) {
        $questions_result = mysqli_stmt_get_result($questions_stmt);
        while ($question = mysqli_fetch_assoc($questions_result)) {
            // Fetch options for MCQ and true/false questions
            if ($question['question_type'] == 'mcq' || $question['question_type'] == 'true_false') {
                $options = [];
                $options_sql = "SELECT * FROM question_options WHERE question_id = ? ORDER BY id";
                if ($options_stmt = mysqli_prepare($conn, $options_sql)) {
                    mysqli_stmt_bind_param($options_stmt, "i", $question['id']);
                    if (mysqli_stmt_execute($options_stmt)) {
                        $options_result = mysqli_stmt_get_result($options_stmt);
                        while ($option = mysqli_fetch_assoc($options_result)) {
                            $options[] = $option;
                        }
                    }
                    mysqli_stmt_close($options_stmt);
                }
                $question['options'] = $options;
            } else {
                $question['options'] = []; // Initialize as empty array for other question types
            }
            $questions[] = $question;
        }
    }
    mysqli_stmt_close($questions_stmt);
}

// Fetch student results
$results = [];
$results_sql = "SELECT r.*, u.first_name, u.last_name, u.email 
                FROM results r 
                JOIN users u ON r.student_id = u.id 
                WHERE r.exam_id = ? 
                ORDER BY r.percentage DESC";
if ($results_stmt = mysqli_prepare($conn, $results_sql)) {
    mysqli_stmt_bind_param($results_stmt, "i", $exam_id);
    if (mysqli_stmt_execute($results_stmt)) {
        $results_result = mysqli_stmt_get_result($results_stmt);
        while ($result = mysqli_fetch_assoc($results_result)) {
            $results[] = $result;
        }
    }
    mysqli_stmt_close($results_stmt);
}

// Calculate statistics
$total_students = count($results);
$avg_score = 0;
$max_score = 0;
$min_score = $exam['total_marks'];
$pass_count = 0;
$pass_percentage = 60; // Default pass percentage

if ($total_students > 0) {
    $total_percentage = 0;
    foreach ($results as $result) {
        $total_percentage += $result['percentage'];
        if ($result['score'] > $max_score) $max_score = $result['score'];
        if ($result['score'] < $min_score) $min_score = $result['score'];
        if ($result['percentage'] >= $pass_percentage) $pass_count++;
    }
    $avg_score = $total_percentage / $total_students;
}

// Include header
include_once "../../includes/header.php";
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($exam["title"]); ?></h1>
        <div>
            <a href="edit-exam.php?id=<?php echo $exam_id; ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-edit fa-sm"></i> Edit Exam
            </a>
            <a href="manage-questions.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-success btn-sm">
                <i class="fas fa-question-circle fa-sm"></i> Manage Questions
            </a>
            <a href="view-course.php?id=<?php echo $exam['course_id']; ?>" class="btn btn-info btn-sm">
                <i class="fas fa-book fa-sm"></i> View Course
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Exam Details Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Exam Details</h6>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h5>Description</h5>
                        <p><?php echo nl2br(htmlspecialchars($exam["description"])); ?></p>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-4">
                                <h5>Exam Information</h5>
                                <table class="table table-bordered">
                                    <tr>
                                        <th>Course</th>
                                        <td><?php echo htmlspecialchars($exam["course_title"]); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Start Time</th>
                                        <td><?php echo date('F d, Y h:i A', strtotime($exam["start_time"])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Duration</th>
                                        <td><?php echo $exam["duration"]; ?> minutes</td>
                                    </tr>
                                    <tr>
                                        <th>Total Marks</th>
                                        <td><?php echo $exam["total_marks"]; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Questions</th>
                                        <td><?php echo count($questions); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Created On</th>
                                        <td><?php echo date('F d, Y', strtotime($exam["created_at"])); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-4">
                                <h5>Results Summary</h5>
                                <table class="table table-bordered">
                                    <tr>
                                        <th>Students Completed</th>
                                        <td><?php echo $total_students; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Average Score</th>
                                        <td><?php echo number_format($avg_score, 2); ?>%</td>
                                    </tr>
                                    <tr>
                                        <th>Highest Score</th>
                                        <td><?php echo $max_score; ?> / <?php echo $exam["total_marks"]; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Lowest Score</th>
                                        <td><?php echo ($total_students > 0) ? $min_score : 'N/A'; ?> / <?php echo $exam["total_marks"]; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Pass Rate</th>
                                        <td><?php echo ($total_students > 0) ? number_format(($pass_count / $total_students) * 100, 2) : 0; ?>%</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Questions Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Exam Questions</h6>
                    <a href="manage-questions.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-edit fa-sm"></i> Manage Questions
                    </a>
                </div>
                <div class="card-body">
                    <?php if (!empty($questions)): ?>
                        <div class="accordion" id="questionsAccordion">
                            <?php foreach ($questions as $index => $question): ?>
                                <div class="card mb-2">
                                    <div class="card-header" id="heading<?php echo $index; ?>">
                                        <h2 class="mb-0">
                                            <button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse" data-target="#collapse<?php echo $index; ?>" aria-expanded="<?php echo ($index == 0) ? 'true' : 'false'; ?>" aria-controls="collapse<?php echo $index; ?>">
                                                <span class="font-weight-bold">Q<?php echo $index + 1; ?>:</span>
                                                <?php echo htmlspecialchars(substr($question['question_text'], 0, 100)) . (strlen($question['question_text']) > 100 ? '...' : ''); ?>
                                                <span class="badge text-primary float-right"><?php echo $question['marks']; ?> points</span>
                                            </button>
                                        </h2>
                                    </div>

                                    <div id="collapse<?php echo $index; ?>" class="collapse <?php echo ($index == 0) ? 'show' : ''; ?>" aria-labelledby="heading<?php echo $index; ?>" data-parent="#questionsAccordion">
                                        <div class="card-body">
                                            <p><strong>Question:</strong> <?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>
                                            <p><strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?></p>
                                            <p><strong>Marks:</strong> <?php echo $question['marks']; ?></p>
                                            <?php if ($question['question_type'] == 'mcq'): ?>
                                                <p><strong>Options:</strong></p>
                                                <ul class="list-group">
                                                    <?php if (isset($question['options']) && is_array($question['options'])): ?>
                                                        <?php foreach ($question['options'] as $option): ?>
                                                            <li class="list-group-item <?php echo $option['is_correct'] ? 'list-group-item-success' : ''; ?>">
                                                                <?php echo htmlspecialchars($option['option_text']); ?>
                                                                <?php if ($option['is_correct']): ?>
                                                                    <span class="badge badge-success float-right">Correct</span>
                                                                <?php endif; ?>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <li class="list-group-item">No options available</li>
                                                    <?php endif; ?>
                                                </ul>
                                            <?php elseif ($question['question_type'] == 'true_false'): ?>
                                                <p><strong>Correct Answer:</strong>
                                                    <?php
                                                    $correct_option = 'Not specified';
                                                    if (isset($question['options']) && is_array($question['options'])) {
                                                        foreach ($question['options'] as $option) {
                                                            if ($option['is_correct']) {
                                                                $correct_option = $option['option_text'];
                                                                break;
                                                            }
                                                        }
                                                    }
                                                    echo $correct_option;
                                                    ?>
                                                </p>
                                            <?php elseif ($question['question_type'] == 'short_answer'): ?>
                                                <p><strong>Expected Answer:</strong> <?php echo isset($question['answer_text']) ? htmlspecialchars($question['answer_text']) : 'N/A'; ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p class="text-gray-500">No questions added to this exam yet.</p>
                            <a href="manage-questions.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Questions
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Student Results Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Student Results</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($results)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" id="resultsTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Score</th>
                                        <th>%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $result): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($result["first_name"] . " " . $result["last_name"]); ?></td>
                                            <td><?php echo $result["score"]; ?> / <?php echo $result["total_marks"]; ?></td>
                                            <td>
                                                <div class="progress">
                                                    <div class="progress-bar <?php echo ($result["percentage"] >= $pass_percentage) ? 'bg-success' : 'bg-danger'; ?>" role="progressbar" style="width: <?php echo $result["percentage"]; ?>%" aria-valuenow="<?php echo $result["percentage"]; ?>" aria-valuemin="0" aria-valuemax="100">
                                                        <?php echo number_format($result["percentage"], 1); ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p class="text-gray-500">No students have completed this exam yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="edit-exam.php?id=<?php echo $exam_id; ?>" class="btn btn-primary btn-block mb-2">
                            <i class="fas fa-edit fa-sm"></i> Edit Exam
                        </a>
                        <a href="manage-questions.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-success btn-block mb-2">
                            <i class="fas fa-question-circle fa-sm"></i> Manage Questions
                        </a>
                        <a href="view-course.php?id=<?php echo $exam['course_id']; ?>" class="btn btn-info btn-block mb-2">
                            <i class="fas fa-book fa-sm"></i> View Course
                        </a>
                        <a href="review-exam.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-warning btn-block mb-2">
                            <i class="fas fa-search fa-sm"></i> Review Exam
                        </a>
                        <a href="manage-exams.php" class="btn btn-secondary btn-block">
                            <i class="fas fa-arrow-left fa-sm"></i> Back to Exams
                        </a>
                    </div>
                </div>
            </div>

            <!-- Exam Status Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Exam Status</h6>
                </div>
                <div class="card-body">
                    <?php
                    $now = new DateTime();
                    $start_time = new DateTime($exam["start_time"]);
                    $end_time = clone $start_time;
                    $end_time->add(new DateInterval('PT' . $exam["duration"] . 'M'));

                    if ($now < $start_time) {
                        $status = "Upcoming";
                        $status_class = "text-info";
                    } elseif ($now >= $start_time && $now <= $end_time) {
                        $status = "In Progress";
                        $status_class = "text-warning";
                    } else {
                        $status = "Completed";
                        $status_class = "text-success";
                    }
                    ?>

                    <h4 class="<?php echo $status_class; ?> text-center mb-3"><?php echo $status; ?></h4>

                    <div class="text-center mb-3">
                        <?php if ($status == "Upcoming"): ?>
                            <p>Exam starts in:</p>
                            <div id="countdown" class="h4"></div>
                        <?php elseif ($status == "In Progress"): ?>
                            <p>Exam ends in:</p>
                            <div id="countdown" class="h4"></div>
                        <?php else: ?>
                            <p>Exam completed on:</p>
                            <div class="h4"><?php echo $end_time->format('M d, Y h:i A'); ?></div>
                        <?php endif; ?>
                    </div>

                    <script>
                        // Countdown timer
                        var countDownDate = new Date("<?php echo ($status == 'Upcoming') ? $start_time->format('Y-m-d H:i:s') : $end_time->format('Y-m-d H:i:s'); ?>").getTime();

                        var x = setInterval(function() {
                            var now = new Date().getTime();
                            var distance = countDownDate - now;

                            var days = Math.floor(distance / (1000 * 60 * 60 * 24));
                            var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                            var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                            var seconds = Math.floor((distance % (1000 * 60)) / 1000);

                            document.getElementById("countdown").innerHTML = days + "d " + hours + "h " + minutes + "m " + seconds + "s ";

                            if (distance < 0) {
                                clearInterval(x);
                                document.getElementById("countdown").innerHTML = "EXPIRED";
                                // Reload page to update status
                                location.reload();
                            }
                        }, 1000);
                    </script>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- DataTables JavaScript -->
<script>
    $(document).ready(function() {
        $('#resultsTable').DataTable({
            order: [
                [2, 'desc']
            ], // Sort by percentage (descending)
            responsive: true
        });
    });
</script>

<!-- Add these scripts just before the closing body tag -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<?php
// Include footer
include_once "../../includes/footer.php";
