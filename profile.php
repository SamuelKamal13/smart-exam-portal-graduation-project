<?php
// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: auth/login.php");
    exit;
}

// Include config file
require_once "config.php";

// Define variables and initialize with empty values
$email = $first_name = $last_name = $phone = "";
$email_err = $first_name_err = $last_name_err = $phone_err = "";
$success_msg = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
    } else {
        // Check if email exists (except for current user)
        $sql = "SELECT id FROM users WHERE email = ? AND id != ?";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "si", $param_email, $param_id);

            // Set parameters
            $param_email = trim($_POST["email"]);
            $param_id = $_SESSION["id"];

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                // Store result
                mysqli_stmt_store_result($stmt);

                if (mysqli_stmt_num_rows($stmt) > 0) {
                    $email_err = "This email is already taken.";
                } else {
                    $email = trim($_POST["email"]);
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }

    // Validate first name
    if (empty(trim($_POST["first_name"]))) {
        $first_name_err = "Please enter your first name.";
    } else {
        $first_name = trim($_POST["first_name"]);
    }

    // Validate last name
    if (empty(trim($_POST["last_name"]))) {
        $last_name_err = "Please enter your last name.";
    } else {
        $last_name = trim($_POST["last_name"]);
    }

    // Validate phone (optional)
    if (!empty(trim($_POST["phone"]))) {
        // Simple phone validation - can be enhanced based on your requirements
        if (!preg_match("/^[0-9\-\(\)\/\+\s]*$/", trim($_POST["phone"]))) {
            $phone_err = "Please enter a valid phone number.";
        } else {
            $phone = trim($_POST["phone"]);
        }
    }

    // Check input errors before updating the database
    if (empty($email_err) && empty($first_name_err) && empty($last_name_err) && empty($phone_err)) {
        // Prepare an update statement
        $sql = "UPDATE users SET email = ?, first_name = ?, last_name = ?, phone = ? WHERE id = ?";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "ssssi", $param_email, $param_first_name, $param_last_name, $param_phone, $param_id);

            // Set parameters
            $param_email = $email;
            $param_first_name = $first_name;
            $param_last_name = $last_name;
            $param_phone = $phone;
            $param_id = $_SESSION["id"];

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                // Profile updated successfully
                $success_msg = "Your profile has been updated successfully.";
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }
}

// Fetch user data from database
$sql = "SELECT user_id, email, first_name, last_name, phone, role, created_at FROM users WHERE id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $param_id);
    $param_id = $_SESSION["id"];

    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) == 1) {
            mysqli_stmt_bind_result($stmt, $user_id, $email, $first_name, $last_name, $phone, $role, $created_at);
            mysqli_stmt_fetch($stmt);
        }
    }

    mysqli_stmt_close($stmt);
}

// Set page title and base URL for includes
$page_title = "My Profile";
$base_url = ".";

// Include header
include_once "includes/header.php";
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">My Profile</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($success_msg)): ?>
                        <div class="alert alert-success"><?php echo $success_msg; ?></div>
                    <?php endif; ?>

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="user_id" class="form-label">User ID</label>
                                <input type="text" class="form-control" id="user_id" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>" disabled>
                                <small class="text-muted">User ID cannot be changed</small>
                            </div>
                            <div class="col-md-6">
                                <label for="role" class="form-label">Role</label>
                                <input type="text" class="form-control" id="role" name="role" value="<?php echo ucfirst(htmlspecialchars($role)); ?>" disabled>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control <?php echo (!empty($first_name_err)) ? 'is-invalid' : ''; ?>" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>">
                                <div class="invalid-feedback"><?php echo $first_name_err; ?></div>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control <?php echo (!empty($last_name_err)) ? 'is-invalid' : ''; ?>" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>">
                                <div class="invalid-feedback"><?php echo $last_name_err; ?></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
                            <div class="invalid-feedback"><?php echo $email_err; ?></div>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control <?php echo (!empty($phone_err)) ? 'is-invalid' : ''; ?>" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                            <div class="invalid-feedback"><?php echo $phone_err; ?></div>
                        </div>

                        <div class="mb-3">
                            <label for="created_at" class="form-label">Member Since</label>
                            <input type="text" class="form-control" id="created_at" name="created_at" value="<?php echo date('F j, Y', strtotime($created_at)); ?>" disabled>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                            <?php
                            // Determine the correct dashboard link based on user role
                            $dashboardLink = $base_url . "/dashboard/";
                            if (isset($_SESSION["role"])) {
                                if ($_SESSION["role"] == "student") {
                                    $dashboardLink .= "student/index.php";
                                } elseif ($_SESSION["role"] == "trainer") {
                                    $dashboardLink .= "trainer/index.php";
                                } elseif ($_SESSION["role"] == "supervisor") {
                                    $dashboardLink .= "supervisor/index.php";
                                }
                            } else {
                                $dashboardLink .= "index.php";
                            }
                            ?>
                            <a href="<?php echo $dashboardLink; ?>" class="btn btn-secondary">Back to Dashboard</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once "includes/footer.php";
?>