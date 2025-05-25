<?php
// Set page title and base URL for includes
$page_title = "Generate Invitation Codes";
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

// Define variables and initialize with empty values
$role = $count = "";
$role_err = $count_err = $success_msg = "";

// Process delete request
if (isset($_GET["action"]) && $_GET["action"] == "delete" && isset($_GET["id"])) {
    $code_id = trim($_GET["id"]);

    // Prepare a delete statement
    $sql = "DELETE FROM invitation_codes WHERE id = ? AND used = 0";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        // Bind variables to the prepared statement as parameters
        mysqli_stmt_bind_param($stmt, "i", $code_id);

        // Attempt to execute the prepared statement
        if (mysqli_stmt_execute($stmt)) {
            // Record deleted successfully. Redirect to this page
            $success_msg = "Invitation code deleted successfully.";
        } else {
            $success_msg = "Error: Could not delete the invitation code.";
        }

        // Close statement
        mysqli_stmt_close($stmt);
    }
}

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate role
    if (empty(trim($_POST["role"]))) {
        $role_err = "Please select a role.";
    } else {
        $role = trim($_POST["role"]);
        // Make sure role is one of the allowed values
        if (!in_array($role, ["student", "trainer", "supervisor"])) {
            $role_err = "Invalid role selected.";
        }
    }

    // Validate count
    if (empty(trim($_POST["count"]))) {
        $count_err = "Please enter the number of codes to generate.";
    } else {
        $count = trim($_POST["count"]);
        // Check if count is a positive integer
        if (!ctype_digit($count) || $count < 1 || $count > 100) {
            $count_err = "Please enter a valid number between 1 and 100.";
        }
    }

    // Check input errors before generating codes
    if (empty($role_err) && empty($count_err)) {

        // Generate the specified number of invitation codes
        $generated_codes = [];

        for ($i = 0; $i < $count; $i++) {
            // Generate a random code
            $code = generateRandomCode(10);

            // Insert the code into the database
            $sql = "INSERT INTO invitation_codes (code, role) VALUES (?, ?)";

            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "ss", $code, $role);

                if (mysqli_stmt_execute($stmt)) {
                    $generated_codes[] = $code;
                }

                mysqli_stmt_close($stmt);
            }
        }

        // Set success message
        if (count($generated_codes) > 0) {
            $success_msg = count($generated_codes) . " invitation code(s) generated successfully.";
        } else {
            $success_msg = "Failed to generate invitation codes.";
        }
    }
}

// Function to generate a random code
function generateRandomCode($length = 10)
{
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';

    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }

    return $code;
}

// Get all existing invitation codes
$invitation_codes = [];
$sql = "SELECT id, code, role, used, created_at FROM invitation_codes ORDER BY created_at DESC";

if ($result = mysqli_query($conn, $sql)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $invitation_codes[] = $row;
    }
    mysqli_free_result($result);
}

// Include header
include_once "../../includes/header.php";
?>

<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Generate Invitation Codes</h4>
        </div>
        <div class="card-body">
            <p class="lead">Create new invitation codes for users to register.</p>

            <?php
            if (!empty($success_msg)) {
                echo '<div class="alert alert-success">' . $success_msg . '</div>';
            }
            ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group mb-3">
                    <label>Role</label>
                    <select name="role" class="form-control <?php echo (!empty($role_err)) ? 'is-invalid' : ''; ?>">
                        <option value="" <?php echo empty($role) ? 'selected' : ''; ?>>Select Role</option>
                        <option value="student" <?php echo ($role == "student") ? 'selected' : ''; ?>>Student</option>
                        <option value="trainer" <?php echo ($role == "trainer") ? 'selected' : ''; ?>>Trainer</option>
                        <option value="supervisor" <?php echo ($role == "supervisor") ? 'selected' : ''; ?>>Supervisor</option>
                    </select>
                    <span class="invalid-feedback"><?php echo $role_err; ?></span>
                </div>
                <div class="form-group mb-3">
                    <label>Number of Codes</label>
                    <input type="number" name="count" class="form-control <?php echo (!empty($count_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $count; ?>" min="1" max="100">
                    <span class="invalid-feedback"><?php echo $count_err; ?></span>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Generate Codes</button>
                    <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mt-4">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Existing Invitation Codes</h4>
        </div>
        <div class="card-body">
            <?php if (count($invitation_codes) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Code</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invitation_codes as $code): ?>
                                <tr>
                                    <td><?php echo $code['code']; ?></td>
                                    <td><?php echo ucfirst($code['role']); ?></td>
                                    <td>
                                        <?php if ($code['used']): ?>
                                            <span class="badge bg-danger">Used</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Available</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $code['created_at']; ?></td>
                                    <td>
                                        <?php if (!$code['used']): ?>
                                            <button type="button" class="btn btn-danger btn-sm delete-btn"
                                                data-id="<?php echo $code['id']; ?>"
                                                data-code="<?php echo $code['code']; ?>">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm" disabled>
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted">No invitation codes found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel"><i class="fas fa-trash-alt"></i> Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fas fa-exclamation-triangle confirmation-icon text-danger" style="font-size: 3rem;"></i>
                </div>
                <p class="text-center">Are you sure you want to delete the invitation code: <strong id="codeToDelete"></strong>?</p>
                <p class="text-center font-weight-bold">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> Cancel</button>
                <a href="#" id="confirmDelete" class="btn btn-danger"><i class="fas fa-trash-alt"></i> Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize delete buttons
    document.addEventListener('DOMContentLoaded', function() {
        const deleteButtons = document.querySelectorAll('.delete-btn');
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        const codeToDeleteElement = document.getElementById('codeToDelete');
        const confirmDeleteButton = document.getElementById('confirmDelete');

        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const codeId = this.getAttribute('data-id');
                const codeText = this.getAttribute('data-code');

                // Set the code in the modal
                codeToDeleteElement.textContent = codeText;

                // Set the delete URL
                confirmDeleteButton.setAttribute('href', '<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?action=delete&id=' + codeId);

                // Show the modal
                deleteModal.show();
            });
        });
    });
</script>

<?php
// Include footer
include_once "../../includes/footer.php";
?>