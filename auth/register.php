<?php
// Include config file
require_once "../config.php";

// Define variables and initialize with empty values
$user_id = $password = $confirm_password = $email = $role = $invitation_code = $first_name = $last_name = $phone = "";
$user_id_err = $password_err = $confirm_password_err = $email_err = $role_err = $invitation_code_err = $first_name_err = $last_name_err = $phone_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Generate user_id based on role and year
    $year = date("Y");
    $year_short = substr($year, -2); // Get last 2 digits of year

    if (isset($_POST["invitation_code"])) {
        $invitation_code = trim($_POST["invitation_code"]);

        // Check if invitation code exists and is valid
        $sql = "SELECT role, used FROM invitation_codes WHERE code = ?";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $invitation_code);

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);

                if (mysqli_stmt_num_rows($stmt) == 1) {
                    mysqli_stmt_bind_result($stmt, $code_role, $used);
                    mysqli_stmt_fetch($stmt);

                    if ($used) {
                        $invitation_code_err = "This invitation code has already been used.";
                    } else {
                        // Set the role based on the invitation code
                        $role = $code_role;

                        // Generate user_id pattern: YYNNNNN (e.g., 2301949)
                        // Get the latest user_id with this pattern
                        $pattern = $year_short . "%";
                        $sql = "SELECT user_id FROM users WHERE user_id LIKE ? ORDER BY user_id DESC LIMIT 1";

                        if ($stmt2 = mysqli_prepare($conn, $sql)) {
                            mysqli_stmt_bind_param($stmt2, "s", $pattern);

                            if (mysqli_stmt_execute($stmt2)) {
                                mysqli_stmt_store_result($stmt2);

                                if (mysqli_stmt_num_rows($stmt2) == 1) {
                                    mysqli_stmt_bind_result($stmt2, $last_user_id);
                                    mysqli_stmt_fetch($stmt2);

                                    // Extract the sequence number and increment
                                    $seq_num = intval(substr($last_user_id, 2)) + 1;
                                } else {
                                    // First user with this pattern
                                    $seq_num = 1000; // Start from 1000 to get a 5-digit number
                                }

                                // Format the user_id: YY + sequence number
                                $user_id = $year_short . $seq_num;
                            }

                            mysqli_stmt_close($stmt2);
                        }
                    }
                } else {
                    $invitation_code_err = "Invalid invitation code.";
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            mysqli_stmt_close($stmt);
        }
    }

    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
    } else {
        // Prepare a select statement
        $sql = "SELECT id FROM users WHERE email = ?";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "s", $param_email);

            // Set parameters
            $param_email = trim($_POST["email"]);

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                // Store result
                mysqli_stmt_store_result($stmt);

                if (mysqli_stmt_num_rows($stmt) == 1) {
                    $email_err = "This email is already registered.";
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
        // Simple phone validation
        if (!preg_match("/^[0-9\-\(\)\/\+\s]*$/", trim($_POST["phone"]))) {
            $phone_err = "Please enter a valid phone number.";
        } else {
            $phone = trim($_POST["phone"]);
        }
    }

    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }

    // Check input errors before inserting in database
    if (
        empty($user_id_err) && empty($password_err) && empty($confirm_password_err) && empty($email_err) &&
        empty($invitation_code_err) && empty($first_name_err) && empty($last_name_err) && empty($phone_err)
    ) {

        // Start transaction
        mysqli_begin_transaction($conn);

        try {
            // Prepare an insert statement for user
            $sql = "INSERT INTO users (user_id, password, email, role, first_name, last_name, phone) VALUES (?, ?, ?, ?, ?, ?, ?)";

            if ($stmt = mysqli_prepare($conn, $sql)) {
                // Bind variables to the prepared statement as parameters
                mysqli_stmt_bind_param($stmt, "sssssss", $param_user_id, $param_password, $param_email, $param_role, $param_first_name, $param_last_name, $param_phone);

                // Set parameters
                $param_user_id = $user_id;
                $param_password = password_hash($password, PASSWORD_DEFAULT); // Creates a password hash
                $param_email = $email;
                $param_role = $role;
                $param_first_name = $first_name;
                $param_last_name = $last_name;
                $param_phone = $phone;

                // Attempt to execute the prepared statement
                if (mysqli_stmt_execute($stmt)) {
                    // Mark invitation code as used
                    $sql = "UPDATE invitation_codes SET used = TRUE WHERE code = ?";
                    if ($stmt2 = mysqli_prepare($conn, $sql)) {
                        mysqli_stmt_bind_param($stmt2, "s", $invitation_code);
                        mysqli_stmt_execute($stmt2);
                        mysqli_stmt_close($stmt2);
                    }

                    // Commit transaction
                    mysqli_commit($conn);

                    // Redirect to login page
                    header("location: login.php");
                } else {
                    // Rollback transaction
                    mysqli_rollback($conn);
                    echo "Something went wrong. Please try again later.";
                }

                // Close statement
                mysqli_stmt_close($stmt);
            }
        } catch (Exception $e) {
            // Rollback transaction
            mysqli_rollback($conn);
            echo "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign Up - Smart Exam Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            background-color: #f8f9fa;
        }

        .register-container {
            padding-top: 2rem;
            padding-bottom: 2rem;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }

        @media (max-width: 576px) {
            .register-container {
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }
    </style>
</head>

<body>
    <div class="container register-container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="card">
                    <div class="card-header py-3">
                        <h2 class="text-center mb-0 fs-4">Sign Up</h2>
                    </div>
                    <div class="card-body p-4">
                        <p class="text-center mb-4">Please fill this form to create an account.</p>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" name="first_name" id="first_name" class="form-control <?php echo (!empty($first_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $first_name; ?>">
                                <div class="invalid-feedback"><?php echo $first_name_err; ?></div>
                            </div>
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" name="last_name" id="last_name" class="form-control <?php echo (!empty($last_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $last_name; ?>">
                                <div class="invalid-feedback"><?php echo $last_name_err; ?></div>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" name="email" id="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>">
                                <div class="invalid-feedback"><?php echo $email_err; ?></div>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $password; ?>">
                                <div class="invalid-feedback"><?php echo $password_err; ?></div>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $confirm_password; ?>">
                                <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number (Optional)</label>
                                <input type="text" name="phone" id="phone" class="form-control <?php echo (!empty($phone_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $phone; ?>">
                                <div class="invalid-feedback"><?php echo $phone_err; ?></div>
                            </div>
                            <div class="mb-3">
                                <label for="invitation_code" class="form-label">Invitation Code</label>
                                <input type="text" name="invitation_code" id="invitation_code" class="form-control <?php echo (!empty($invitation_code_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $invitation_code; ?>">
                                <div class="invalid-feedback"><?php echo $invitation_code_err; ?></div>
                            </div>
                            <div class="mb-4">
                                <button type="submit" class="btn btn-primary w-100 py-2">Sign Up</button>
                            </div>
                            <div class="text-center">
                                <p class="mb-0">Already have an account? <a href="login.php">Login here</a>.</p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
</body>

</html>