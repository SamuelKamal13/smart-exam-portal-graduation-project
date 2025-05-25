<?php
// Include config file
require_once "../config.php";
require_once "../includes/mail_helper.php";

// Define variables and initialize with empty values
$email = $new_password = $confirm_password = "";
$email_err = $new_password_err = $confirm_password_err = $success_msg = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Check if we're in step 1 (email submission) or step 2 (new password)
    if (isset($_POST["email_submit"])) {
        // Validate email
        if (empty(trim($_POST["email"]))) {
            $email_err = "Please enter your email.";
        } else {
            $email = trim($_POST["email"]);

            // Check if email exists and get user details
            $sql = "SELECT id, user_id, first_name, last_name FROM users WHERE email = ?";

            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "s", $email);

                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_store_result($stmt);

                    if (mysqli_stmt_num_rows($stmt) == 1) {
                        mysqli_stmt_bind_result($stmt, $id, $user_id, $first_name, $last_name);
                        mysqli_stmt_fetch($stmt);

                        // Generate a reset token
                        $token = bin2hex(random_bytes(32));
                        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                        // Check if a token already exists for this user
                        $check_sql = "SELECT id FROM password_resets WHERE user_id = ?";
                        $check_stmt = mysqli_prepare($conn, $check_sql);
                        mysqli_stmt_bind_param($check_stmt, "i", $id);
                        mysqli_stmt_execute($check_stmt);
                        mysqli_stmt_store_result($check_stmt);

                        if (mysqli_stmt_num_rows($check_stmt) > 0) {
                            // Update existing token
                            $update_sql = "UPDATE password_resets SET token = ?, expires_at = ? WHERE user_id = ?";
                            $update_stmt = mysqli_prepare($conn, $update_sql);
                            mysqli_stmt_bind_param($update_stmt, "ssi", $token, $expires, $id);
                            mysqli_stmt_execute($update_stmt);
                            mysqli_stmt_close($update_stmt);
                        } else {
                            // Insert new token
                            $insert_sql = "INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)";
                            $insert_stmt = mysqli_prepare($conn, $insert_sql);
                            mysqli_stmt_bind_param($insert_stmt, "iss", $id, $token, $expires);
                            mysqli_stmt_execute($insert_stmt);
                            mysqli_stmt_close($insert_stmt);
                        }

                        mysqli_stmt_close($check_stmt);

                        // Generate reset link
                        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset-password.php?token=" . $token;

                        // Send password reset email
                        // Find this section in your reset-password.php file where you send the email

                        // Send password reset email with full name
                        if (send_password_reset_email($email, $user_id, $reset_link, $first_name, $last_name)) {
                            $success_msg = "A password reset link has been sent to your email address. Please check your inbox and spam folder.";
                        } else {
                            // For debugging, temporarily store the error in a log file
                            $error_log_file = "../logs/email_error_" . time() . ".log";
                            file_put_contents($error_log_file, "Failed to send email to: $email\n");

                            $email_err = "Failed to send password reset email. Please try again later or contact support.";
                        }
                    } else {
                        $email_err = "No account found with that email address.";
                    }
                } else {
                    echo "Oops! Something went wrong. Please try again later.";
                }

                mysqli_stmt_close($stmt);
            }
        }
    } elseif (isset($_POST["password_submit"])) {
        // We're in step 2 - validate and update the password

        // Get token from hidden field
        $token = trim($_POST["token"]);

        // Validate new password
        if (empty(trim($_POST["new_password"]))) {
            $new_password_err = "Please enter the new password.";
        } elseif (strlen(trim($_POST["new_password"])) < 6) {
            $new_password_err = "Password must have at least 6 characters.";
        } else {
            $new_password = trim($_POST["new_password"]);
        }

        // Validate confirm password
        if (empty(trim($_POST["confirm_password"]))) {
            $confirm_password_err = "Please confirm the password.";
        } else {
            $confirm_password = trim($_POST["confirm_password"]);
            if (empty($new_password_err) && ($new_password != $confirm_password)) {
                $confirm_password_err = "Password did not match.";
            }
        }

        // Check input errors before updating the password
        if (empty($new_password_err) && empty($confirm_password_err)) {
            // Get user ID from token
            $sql = "SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW()";

            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "s", $token);

                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_store_result($stmt);

                    if (mysqli_stmt_num_rows($stmt) == 1) {
                        mysqli_stmt_bind_result($stmt, $user_id);
                        mysqli_stmt_fetch($stmt);

                        // Update password
                        $update_sql = "UPDATE users SET password = ? WHERE id = ?";

                        if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
                            // Set parameters
                            $param_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $param_id = $user_id;

                            // Bind variables to the prepared statement as parameters
                            mysqli_stmt_bind_param($update_stmt, "si", $param_password, $param_id);

                            // Attempt to execute the prepared statement
                            if (mysqli_stmt_execute($update_stmt)) {
                                // Delete the used token
                                $delete_sql = "DELETE FROM password_resets WHERE user_id = ?";
                                $delete_stmt = mysqli_prepare($conn, $delete_sql);
                                mysqli_stmt_bind_param($delete_stmt, "i", $user_id);
                                mysqli_stmt_execute($delete_stmt);
                                mysqli_stmt_close($delete_stmt);

                                // Password updated successfully. Redirect to login page
                                $success_msg = "Your password has been reset successfully. You can now <a href='login.php'>log in</a> with your new password.";
                            } else {
                                echo "Oops! Something went wrong. Please try again later.";
                            }

                            mysqli_stmt_close($update_stmt);
                        }
                    } else {
                        // Token is invalid or expired
                        echo "The password reset link is invalid or has expired.";
                    }
                } else {
                    echo "Oops! Something went wrong. Please try again later.";
                }

                mysqli_stmt_close($stmt);
            }
        }
    }
}

// Check if we have a token in the URL (step 2)
$show_password_form = false;
$token_valid = false;
$token = "";

if (isset($_GET["token"]) && !empty($_GET["token"])) {
    $token = trim($_GET["token"]);

    // Verify token is valid and not expired
    $sql = "SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW()";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $token);

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_store_result($stmt);

            if (mysqli_stmt_num_rows($stmt) == 1) {
                $show_password_form = true;
                $token_valid = true;
            }
        }

        mysqli_stmt_close($stmt);
    }

    if (!$token_valid) {
        echo "The password reset link is invalid or has expired.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password - Smart Exam Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            background-color: #f8f9fa;
        }

        .reset-container {
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
            .reset-container {
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }
    </style>
</head>

<body>
    <div class="container reset-container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-5">
                <div class="card">
                    <div class="card-header py-3">
                        <h2 class="text-center mb-0 fs-4">Reset Password</h2>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($success_msg)): ?>
                            <div class="alert alert-success"><?php echo $success_msg; ?></div>
                        <?php elseif ($show_password_form): ?>
                            <p class="text-center mb-4">Please enter your new password.</p>
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" name="new_password" id="new_password" class="form-control <?php echo (!empty($new_password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $new_password; ?>">
                                    <div class="invalid-feedback"><?php echo $new_password_err; ?></div>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password</label>
                                    <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>">
                                    <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                                </div>
                                <input type="hidden" name="token" value="<?php echo $token; ?>">
                                <div class="mb-4">
                                    <button type="submit" name="password_submit" class="btn btn-primary w-100 py-2">Reset Password</button>
                                </div>
                                <div class="text-center">
                                    <a href="login.php">Back to Login</a>
                                </div>
                            </form>
                        <?php else: ?>
                            <p class="text-center mb-4">Please enter your email address to reset your password.</p>
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" name="email" id="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>">
                                    <div class="invalid-feedback"><?php echo $email_err; ?></div>
                                </div>
                                <div class="mb-4">
                                    <button type="submit" name="email_submit" class="btn btn-primary w-100 py-2">Submit</button>
                                </div>
                                <div class="text-center">
                                    <a href="login.php">Back to Login</a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
</body>

</html>