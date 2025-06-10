<?php
// Initialize the session
session_start();

// Check if the user is already logged in, if yes then redirect to dashboard
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    if ($_SESSION["role"] == "student") {
        header("location: ../dashboard/student/index.php");
    } elseif ($_SESSION["role"] == "trainer") {
        header("location: ../dashboard/trainer/index.php");
    } elseif ($_SESSION["role"] == "supervisor") {
        header("location: ../dashboard/supervisor/index.php");
    }
    exit;
}

// Include config file
require_once "../config.php";

// Define variables and initialize with empty values
$user_id = $password = "";
$user_id_err = $password_err = $login_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Check if user_id is empty
    if (empty(trim($_POST["user_id"]))) {
        $user_id_err = "Please enter your ID.";
    } else {
        $user_id = trim($_POST["user_id"]);
    }

    // Check if password is empty
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate credentials
    if (empty($user_id_err) && empty($password_err)) {
        // Prepare a select statement - modified to check both user_id and email
        $sql = "SELECT id, user_id, password, role, first_name, last_name FROM users WHERE user_id = ? OR email = ?";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "ss", $param_user_id, $param_user_id);

            // Set parameters
            $param_user_id = $user_id;

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                // Store result
                mysqli_stmt_store_result($stmt);

                // Check if user_id or email exists, if yes then verify password
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    // Bind result variables
                    mysqli_stmt_bind_result($stmt, $id, $user_id, $hashed_password, $role, $first_name, $last_name);
                    if (mysqli_stmt_fetch($stmt)) {
                        if (password_verify($password, $hashed_password)) {
                            // Password is correct, so start a new session
                            session_start();

                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["user_id"] = $user_id;
                            $_SESSION["role"] = $role;
                            $_SESSION["first_name"] = $first_name;
                            $_SESSION["last_name"] = $last_name;

                            // Redirect user to appropriate dashboard
                            if ($role == "student") {
                                header("location: ../dashboard/student/index.php");
                            } elseif ($role == "trainer") {
                                header("location: ../dashboard/trainer/index.php");
                            } elseif ($role == "supervisor") {
                                header("location: ../dashboard/supervisor/index.php");
                            }
                        } else {
                            // Password is not valid, display a generic error message
                            $login_err = "Invalid ID/Email or password.";
                        }
                    }
                } else {
                    // User_id/email doesn't exist, display a generic error message
                    $login_err = "Invalid ID/Email or password.";
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }

    // Close connection
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Smart Exam Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            background-color: #f8f9fa;
        }

        .login-container {
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
            .login-container {
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }
    </style>
</head>

<body>
    <div class="container login-container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-5">
                <div class="card">
                    <div class="card-header py-3">
                        <h2 class="text-center mb-0 fs-4">Login</h2>
                    </div>
                    <div class="card-body p-4">
                        <p class="text-center mb-4">Please fill in your credentials to login.</p>

                        <?php
                        if (!empty($login_err)) {
                            echo '<div class="alert alert-danger">' . $login_err . '</div>';
                        }
                        ?>

                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <div class="mb-3">
                                <label for="user_id" class="form-label">User ID or Email</label>
                                <input type="text" name="user_id" id="user_id" class="form-control <?php echo (!empty($user_id_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $user_id; ?>">
                                <div class="invalid-feedback"><?php echo $user_id_err; ?></div>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                                <div class="invalid-feedback"><?php echo $password_err; ?></div>
                            </div>
                            <div class="mb-4">
                                <button type="submit" class="btn btn-primary w-100 py-2">Login</button>
                            </div>
                            <div class="text-center">
                                <p class="mb-2">Forgot your password? <a href="reset-password.php">Reset it here</a>.</p>
                                <p class="mb-2">Don't have an account? <a href="register.php">Sign up now</a>.</p>
                                <p class="mb-0">Have questions? <a href="../contact.php">Contact us</a>.</p>
                            </div>
                        </form>
                    </div>
</body>

</html>