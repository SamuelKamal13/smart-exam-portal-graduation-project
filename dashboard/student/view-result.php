<?php
// Set page title and base URL for includes
$page_title = "View Result";
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

// Check if result ID is provided
if (!isset($_GET["id"]) || empty($_GET["id"])) {
    header("location: exams.php");
    exit;
}

$result_id = $_GET["id"];

// Fetch result data
$result = null;
$sql = "SELECT r.*, e.title as exam_title, e.description, e.duration, e.start_time, c.title as course_title 
        FROM results r 
        JOIN exams e ON r.exam_id = e.id 
        LEFT JOIN courses c ON e.course_id = c.id 
        WHERE r.id = ? AND r.student_id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $result_id, $student_id);
    if (mysqli_stmt_execute($stmt)) {
        $result_data = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result_data) == 1) {
            $result = mysqli_fetch_assoc($result_data);
        } else {
            // Result not found or doesn't belong to this student
            header("location: exams.php");
            exit;
        }
    } else {
        echo "Oops! Something went wrong. Please try again later.";
    }
    mysqli_stmt_close($stmt);
}

// Get the attempt ID for this result
$attempt_id = null;
$attempt_sql = "SELECT id FROM exam_attempts 
                WHERE exam_id = ? AND student_id = ? AND status = 'completed' 
                ORDER BY end_time DESC LIMIT 1";
if ($attempt_stmt = mysqli_prepare($conn, $attempt_sql)) {
    mysqli_stmt_bind_param($attempt_stmt, "ii", $result['exam_id'], $student_id);
    if (mysqli_stmt_execute($attempt_stmt)) {
        $attempt_result = mysqli_stmt_get_result($attempt_stmt);
        if ($attempt_row = mysqli_fetch_assoc($attempt_result)) {
            $attempt_id = $attempt_row['id'];
        }
    }
    mysqli_stmt_close($attempt_stmt);
}

// Fetch questions and student answers
$questions = [];
if ($attempt_id) {
    $questions_sql = "SELECT q.*, sa.option_id, sa.is_correct, sa.points_earned 
                     FROM questions q 
                     LEFT JOIN student_answers sa ON q.id = sa.question_id AND sa.attempt_id = ? 
                     WHERE q.exam_id = ? 
                     ORDER BY q.id ASC";
    if ($questions_stmt = mysqli_prepare($conn, $questions_sql)) {
        mysqli_stmt_bind_param($questions_stmt, "ii", $attempt_id, $result['exam_id']);
        if (mysqli_stmt_execute($questions_stmt)) {
            $questions_result = mysqli_stmt_get_result($questions_stmt);
            while ($question = mysqli_fetch_assoc($questions_result)) {
                // Fetch options for this question
                $options = [];
                $options_sql = "SELECT * FROM question_options WHERE question_id = ? ORDER BY id ASC";
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
                $questions[] = $question;
            }
        }
        mysqli_stmt_close($questions_stmt);
    }
}

// Include header
include_once "../../includes/header.php";
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Exam Result</h1>

    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Result Summary</h6>
                    <a href="exams.php" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Exams
                    </a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Exam:</strong> <?php echo htmlspecialchars($result["exam_title"]); ?></p>
                            <p><strong>Course:</strong> <?php echo htmlspecialchars($result["course_title"] ?? 'N/A'); ?></p>
                            <p><strong>Date Taken:</strong> <?php echo date("F j, Y, g:i a", strtotime($result["submission_time"])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <div class="text-center">
                                <h4 class="font-weight-bold">Your Score</h4>
                                <div class="result-circle <?php echo ($result["percentage"] >= 70) ? 'bg-success' : (($result["percentage"] >= 50) ? 'bg-warning' : 'bg-danger'); ?>">
                                    <span class="result-percentage"><?php echo round($result["percentage"], 1); ?>%</span>
                                </div>
                                <p class="mt-2">
                                    <strong><?php echo $result["score"]; ?></strong> out of
                                    <strong><?php echo $result["total_marks"]; ?></strong> marks
                                </p>
                                <p class="mt-2">
                                    <span class="badge text-<?php echo ($result["percentage"] >= 70) ? 'success' : (($result["percentage"] >= 50) ? 'warning' : 'danger'); ?> p-2">
                                        <?php
                                        if ($result["percentage"] >= 70) {
                                            echo "Excellent";
                                        } elseif ($result["percentage"] >= 50) {
                                            echo "Good";
                                        } else {
                                            echo "Needs Improvement";
                                        }
                                        ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (count($questions) > 0): ?>
        <div class="row">
            <div class="col-lg-12">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Question Review</h6>
                    </div>
                    <div class="card-body">
                        <div class="accordion" id="questionAccordion">
                            <?php foreach ($questions as $index => $question): ?>
                                <div class="card mb-3">
                                    <div class="card-header d-flex justify-content-between align-items-center" id="heading<?php echo $index; ?>">
                                        <h2 class="mb-0">
                                            <button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#collapse<?php echo $index; ?>" aria-expanded="false" aria-controls="collapse<?php echo $index; ?>">
                                                Question <?php echo $index + 1; ?>:
                                                <?php echo substr(htmlspecialchars($question['question_text']), 0, 100) . (strlen($question['question_text']) > 100 ? '...' : ''); ?>

                                                <?php if ($question['is_correct']): ?>
                                                    <span class="badge text-success ml-2">Correct</span>
                                                <?php else: ?>
                                                    <span class="badge text-danger ml-2">Incorrect</span>
                                                <?php endif; ?>
                                            </button>
                                        </h2>
                                        <span class="badge text-primary"><?php echo $question['marks']; ?> mark<?php echo $question['marks'] > 1 ? 's' : ''; ?></span>
                                    </div>

                                    <div id="collapse<?php echo $index; ?>" class="collapse" aria-labelledby="heading<?php echo $index; ?>" data-parent="#questionAccordion">
                                        <div class="card-body">
                                            <p class="mb-3"><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>

                                            <?php if ($question['question_type'] == 'mcq' || $question['question_type'] == 'true_false'): ?>
                                                <div class="options-list">
                                                    <?php foreach ($question['options'] as $option): ?>
                                                        <div class="option-item <?php
                                                                                if ($option['id'] == $question['option_id'] && $option['is_correct']) {
                                                                                    echo 'correct-selected';
                                                                                } elseif ($option['id'] == $question['option_id'] && !$option['is_correct']) {
                                                                                    echo 'incorrect-selected';
                                                                                } elseif ($option['is_correct']) {
                                                                                    echo 'correct-answer';
                                                                                }
                                                                                ?>">
                                                            <i class="fas <?php
                                                                            if ($option['id'] == $question['option_id'] && $option['is_correct']) {
                                                                                echo 'fa-check-circle text-success';
                                                                            } elseif ($option['id'] == $question['option_id'] && !$option['is_correct']) {
                                                                                echo 'fa-times-circle text-danger';
                                                                            } elseif ($option['is_correct']) {
                                                                                echo 'fa-check text-success';
                                                                            } else {
                                                                                echo 'fa-circle';
                                                                            }
                                                                            ?>"></i>
                                                            <?php echo htmlspecialchars($option['option_text']); ?>

                                                            <?php if ($option['id'] == $question['option_id']): ?>
                                                                <span class="badge text-info ml-2">Your Answer</span>
                                                            <?php endif; ?>

                                                            <?php if ($option['is_correct']): ?>
                                                                <span class="badge text-success ml-2">Correct Answer</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>

                                            <div class="mt-3">
                                                <p class="mb-0">
                                                    <strong>Points earned:</strong>
                                                    <?php echo $question['points_earned']; ?> / <?php echo $question['marks']; ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Next Steps</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 text-center mb-4">
                            <div class="next-step-card">
                                <i class="fas fa-book fa-3x mb-3 text-primary"></i>
                                <h5>Review Course Material</h5>
                                <p>Go back to your course materials to strengthen your understanding.</p>
                            </div>
                        </div>
                        <div class="col-md-4 text-center mb-4">
                            <div class="next-step-card">
                                <i class="fas fa-question-circle fa-3x mb-3 text-primary"></i>
                                <h5>Practice More Questions</h5>
                                <p>Continue practicing with similar questions to improve your skills.</p>
                            </div>
                        </div>
                        <div class="col-md-4 text-center mb-4">
                            <div class="next-step-card">
                                <i class="fas fa-user-graduate fa-3x mb-3 text-primary"></i>
                                <h5>Seek Help</h5>
                                <p>Reach out to your instructor if you need additional support.</p>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mt-3">
                        <a href="exams.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Back to Exams
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .result-circle {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        color: white;
    }

    .result-percentage {
        font-size: 2.5rem;
        font-weight: bold;
    }

    .options-list {
        margin-left: 20px;
    }

    .option-item {
        padding: 10px;
        margin-bottom: 5px;
        border-radius: 5px;
    }

    .correct-selected {
        background-color: rgba(40, 167, 69, 0.2);
    }

    .incorrect-selected {
        background-color: rgba(220, 53, 69, 0.2);
    }

    .correct-answer {
        background-color: rgba(40, 167, 69, 0.1);
    }

    .next-step-card {
        padding: 20px;
        border-radius: 5px;
        background-color: #f8f9fc;
        height: 100%;
        transition: all 0.3s;
    }

    .next-step-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }
</style>

<!-- Add these scripts just before the closing body tag -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<?php
// Include footer
include_once "../../includes/footer.php";
?>