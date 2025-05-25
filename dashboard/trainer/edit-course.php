<?php
// Set page title and base URL for includes
$page_title = "Edit Course";
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

// Check if course ID is provided
if (!isset($_GET["id"]) || empty($_GET["id"])) {
    header("location: manage-courses.php");
    exit;
}

$course_id = $_GET["id"];

// Define variables and initialize with empty values
$title = $description = "";
$title_err = "";
$topics = [];

// Fetch course data
$sql = "SELECT * FROM courses WHERE id = ? AND trainer_id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $course_id, $trainer_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) == 1) {
            $course = mysqli_fetch_assoc($result);
            $title = $course["title"];
            $description = $course["description"];

            // Fetch course topics if they exist
            $topics_sql = "SELECT * FROM course_topics WHERE course_id = ? ORDER BY sort_order";
            if ($topics_stmt = mysqli_prepare($conn, $topics_sql)) {
                mysqli_stmt_bind_param($topics_stmt, "i", $course_id);
                if (mysqli_stmt_execute($topics_stmt)) {
                    $topics_result = mysqli_stmt_get_result($topics_stmt);
                    while ($topic = mysqli_fetch_assoc($topics_result)) {
                        $topics[] = $topic["title"];
                    }
                }
                mysqli_stmt_close($topics_stmt);
            }
        } else {
            // Course not found or doesn't belong to this trainer
            header("location: manage-courses.php");
            exit;
        }
    } else {
        echo "Oops! Something went wrong. Please try again later.";
    }
    mysqli_stmt_close($stmt);
}

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate title
    if (empty(trim($_POST["title"]))) {
        $title_err = "Please enter a course title.";
    } else {
        $title = trim($_POST["title"]);
    }

    // Get description
    $description = trim($_POST["description"]);

    // Get topics
    $topics = isset($_POST["topics"]) ? $_POST["topics"] : [];
    $topics = array_filter($topics, function ($topic) {
        return !empty(trim($topic));
    });

    // Check input errors before updating in database
    if (empty($title_err)) {

        // Prepare an update statement
        $sql = "UPDATE courses SET title = ?, description = ? WHERE id = ? AND trainer_id = ?";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "ssii", $param_title, $param_description, $param_id, $param_trainer_id);

            // Set parameters
            $param_title = $title;
            $param_description = $description;
            $param_id = $course_id;
            $param_trainer_id = $trainer_id;

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                // Delete existing topics
                $delete_topics_sql = "DELETE FROM course_topics WHERE course_id = ?";
                if ($delete_stmt = mysqli_prepare($conn, $delete_topics_sql)) {
                    mysqli_stmt_bind_param($delete_stmt, "i", $course_id);
                    mysqli_stmt_execute($delete_stmt);
                    mysqli_stmt_close($delete_stmt);
                }

                // Insert new topics
                if (!empty($topics)) {
                    $insert_topics_sql = "INSERT INTO course_topics (course_id, title, sort_order) VALUES (?, ?, ?)";
                    if ($insert_stmt = mysqli_prepare($conn, $insert_topics_sql)) {
                        mysqli_stmt_bind_param($insert_stmt, "isi", $course_id, $topic_title, $sort_order);

                        foreach ($topics as $index => $topic) {
                            $topic_title = trim($topic);
                            $sort_order = $index + 1;
                            mysqli_stmt_execute($insert_stmt);
                        }

                        mysqli_stmt_close($insert_stmt);
                    }
                }

                // Redirect to course management page
                header("location: manage-courses.php?success=updated");
                exit();
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }
}

// Include header
include_once "../../includes/header.php";
?>

<div class="container-fluid">
    <h1 class="mt-4 mb-4">Edit Course</h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Course Details</h6>
        </div>
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?id=" . $course_id; ?>" method="post">
                <div class="form-group">
                    <label>Course Title</label>
                    <input type="text" name="title" class="form-control <?php echo (!empty($title_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $title; ?>" placeholder="Enter course title">
                    <span class="invalid-feedback"><?php echo $title_err; ?></span>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="5" placeholder="Enter course description"><?php echo $description; ?></textarea>
                    <small class="form-text text-muted">Provide a detailed description of the course content, objectives, and learning outcomes.</small>
                </div>

                <div class="form-group">
                    <label>Course Topics</label>
                    <div id="topics-container">
                        <?php if (empty($topics)): ?>
                            <div class="input-group mb-2">
                                <input type="text" name="topics[]" class="form-control" placeholder="Enter topic title">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-danger remove-topic" disabled>
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($topics as $index => $topic): ?>
                                <div class="input-group mb-2">
                                    <input type="text" name="topics[]" class="form-control" value="<?php echo htmlspecialchars($topic); ?>" placeholder="Enter topic title">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-danger remove-topic" <?php echo (count($topics) == 1) ? 'disabled' : ''; ?>>
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="add-topic" class="btn btn-sm btn-outline-primary mt-2">
                        <i class="fas fa-plus"></i> Add Another Topic
                    </button>
                    <small class="form-text text-muted">Add lecture topics or modules for your course.</small>
                </div>

                <hr>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Update Course</button>
                    <a href="manage-courses.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add topic functionality
        document.getElementById('add-topic').addEventListener('click', function() {
            const topicsContainer = document.getElementById('topics-container');
            const topicCount = topicsContainer.children.length;

            const newTopic = document.createElement('div');
            newTopic.className = 'input-group mb-2';
            newTopic.innerHTML = `
            <input type="text" name="topics[]" class="form-control" placeholder="Enter topic title">
            <div class="input-group-append">
                <button type="button" class="btn btn-outline-danger remove-topic">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

            topicsContainer.appendChild(newTopic);

            // Enable all remove buttons if there's more than one topic
            if (topicCount >= 1) {
                const removeButtons = document.querySelectorAll('.remove-topic');
                removeButtons.forEach(button => {
                    button.disabled = false;
                });
            }
        });

        // Remove topic functionality (using event delegation)
        document.getElementById('topics-container').addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-topic') || e.target.parentElement.classList.contains('remove-topic')) {
                const button = e.target.classList.contains('remove-topic') ? e.target : e.target.parentElement;
                const topicElement = button.closest('.input-group');
                topicElement.remove();

                // If only one topic remains, disable its remove button
                const topicsContainer = document.getElementById('topics-container');
                if (topicsContainer.children.length === 1) {
                    const lastRemoveButton = topicsContainer.querySelector('.remove-topic');
                    lastRemoveButton.disabled = true;
                }
            }
        });
    });
</script>

<?php
// Include footer
include_once "../../includes/footer.php";
?>