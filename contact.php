<?php
// Include database configuration
require_once "config.php";

// Set page title
$page_title = "Contact Us";

// Define base URL for assets
$base_url = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
$base_url .= $_SERVER['HTTP_HOST'];
$base_url .= dirname($_SERVER['PHP_SELF']) == '/' ? '' : dirname($_SERVER['PHP_SELF']);

// Include mail helper
require_once __DIR__ . "/includes/mail_helper.php";

// Initialize variables
$name = $email = $subject = $message = "";
$name_err = $email_err = $subject_err = $message_err = "";
$success_message = $error_message = "";

// Get admin/supervisor emails from database
function get_admin_emails($conn)
{
    $emails = [];

    // Query to get all supervisors
    $sql = "SELECT email FROM users WHERE role = 'supervisor'";

    if ($result = mysqli_query($conn, $sql)) {
        while ($row = mysqli_fetch_assoc($result)) {
            $emails[] = $row['email'];
        }
    }

    // If no supervisors found, return a default email
    if (empty($emails)) {
        // Use the default email from mail_helper.php
        $emails[] = 'samuelkamal61@gmail.com';
    }

    return $emails;
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate name
    if (empty(trim($_POST["name"]))) {
        $name_err = "Please enter your name.";
    } else {
        $name = trim($_POST["name"]);
    }

    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email.";
    } else {
        $email = trim($_POST["email"]);
        // Check if email is valid
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email_err = "Please enter a valid email address.";
        }
    }

    // Validate subject
    if (empty(trim($_POST["subject"]))) {
        $subject_err = "Please enter a subject.";
    } else {
        $subject = trim($_POST["subject"]);
    }

    // Validate message
    if (empty(trim($_POST["message"]))) {
        $message_err = "Please enter your message.";
    } else {
        $message = trim($_POST["message"]);
    }

    // Check if there are no errors
    if (empty($name_err) && empty($email_err) && empty($subject_err) && empty($message_err)) {

        // Get admin/supervisor emails
        $admin_emails = get_admin_emails($conn);

        // Format email message
        $email_subject = "Contact Form: " . $subject;
        $email_body = "<html><body>";
        $email_body .= "<h2>Contact Form Submission</h2>";
        $email_body .= "<p><strong>Name:</strong> " . htmlspecialchars($name) . "</p>";
        $email_body .= "<p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>";
        $email_body .= "<p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>";
        $email_body .= "<p><strong>Message:</strong></p>";
        $email_body .= "<p>" . nl2br(htmlspecialchars($message)) . "</p>";
        $email_body .= "</body></html>";

        // Send email to all admin/supervisor emails
        $mail_sent = false;
        foreach ($admin_emails as $admin_email) {
            if (send_mail($admin_email, $email_subject, $email_body, $email, $name)) {
                $mail_sent = true;
            }
        }

        if ($mail_sent) {
            $success_message = "Your message has been sent successfully. We will get back to you soon.";
            // Clear form fields
            $name = $email = $subject = $message = "";
        } else {
            $error_message = "Oops! Something went wrong. Please try again later.";
        }
    }
}

// Include header
include "includes/header.php";
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h2 class="mb-0"><i class="fas fa-envelope me-2"></i>Contact Us</h2>
                </div>
                <div class="card-body">
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>

                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>

                    <p class="lead">Have questions or feedback? Fill out the form below to get in touch with our team.</p>

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control <?php echo (!empty($name_err)) ? 'is-invalid' : ''; ?>" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>">
                            <div class="invalid-feedback"><?php echo $name_err; ?></div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
                            <div class="invalid-feedback"><?php echo $email_err; ?></div>
                        </div>

                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" class="form-control <?php echo (!empty($subject_err)) ? 'is-invalid' : ''; ?>" id="subject" name="subject" value="<?php echo htmlspecialchars($subject); ?>">
                            <div class="invalid-feedback"><?php echo $subject_err; ?></div>
                        </div>

                        <div class="mb-3">
                            <label for="message" class="form-label">Message</label>
                            <textarea class="form-control <?php echo (!empty($message_err)) ? 'is-invalid' : ''; ?>" id="message" name="message" rows="6"><?php echo htmlspecialchars($message); ?></textarea>
                            <div class="invalid-feedback"><?php echo $message_err; ?></div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-2"></i>Send Message</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow mt-4">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0"><i class="fas fa-info-circle me-2"></i>Contact Information</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-4 mb-md-0">
                            <h4><i class="fas fa-map-marker-alt me-2 text-primary"></i>Address</h4>
                            <p>Smart Exam Portal<br>123 Project Street<br>Bani Suef, Egypt</p>
                        </div>
                        <div class="col-md-6">
                            <h4><i class="fas fa-phone me-2 text-primary"></i>Phone</h4>
                            <p>+20 120 610 9475</p>

                            <h4><i class="fas fa-envelope me-2 text-primary"></i>Email</h4>
                            <p>info@smartexamportal.com</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include "includes/footer.php";
?>