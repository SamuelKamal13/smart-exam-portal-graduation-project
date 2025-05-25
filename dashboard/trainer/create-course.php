<?php
// Set page title and base URL for includes
$page_title = "Create Course";
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

// Define variables and initialize with empty values
$title = $description = "";
$title_err = "";

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

    // Check input errors before inserting in database
    if (empty($title_err)) {

        // Prepare an insert statement
        $sql = "INSERT INTO courses (title, description, trainer_id) VALUES (?, ?, ?)";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "ssi", $param_title, $param_description, $param_trainer_id);

            // Set parameters
            $param_title = $title;
            $param_description = $description;
            $param_trainer_id = $trainer_id;

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                // Get the ID of the newly created course
                $course_id = mysqli_insert_id($conn);

                // Redirect to course management page
                header("location: manage-courses.php?success=created");
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
    <h1 class="mt-4 mb-4">Create New Course</h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Course Details</h6>
        </div>
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
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
                    <label>Course Topics (Optional)</label>
                    <div id="topics-container">
                        <div class="input-group mb-2">
                            <input type="text" name="topics[]" class="form-control" placeholder="Enter topic title">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-danger remove-topic" disabled>
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <button type="button" id="add-topic" class="btn btn-sm btn-outline-primary mt-2">
                        <i class="fas fa-plus"></i> Add Another Topic
                    </button>
                    <small class="form-text text-muted">Add lecture topics or modules for your course.</small>
                </div>

                <hr>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Create Course</button>
                    <a href="manage-courses.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Course Creation Tips</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-6">
                    <h5><i class="fas fa-lightbulb text-warning"></i> Best Practices</h5>
                    <ul>
                        <li>Use clear, descriptive titles for your courses</li>
                        <li>Break down your course into logical topics or modules</li>
                        <li>Include learning objectives in your description</li>
                        <li>Consider the target audience when describing your course</li>
                    </ul>
                </div>
                <div class="col-lg-6">
                    <h5><i class="fas fa-cogs text-info"></i> Next Steps</h5>
                    <ul>
                        <li>After creating a course, you can add exams to it</li>
                        <li>Invite students to join your course</li>
                        <li>Create study groups for collaborative learning</li>
                        <li>Upload course materials and resources</li>
                    </ul>
                </div>
            </div>
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