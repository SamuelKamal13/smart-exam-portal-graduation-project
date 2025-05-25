<?php
// Mail helper functions using PHPMailer

// Include PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer autoloader
require_once __DIR__ . '/../vendor/phpmailer/PHPMailer-6.8.0/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/PHPMailer-6.8.0/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/PHPMailer-6.8.0/src/SMTP.php';

/**
 * Send an email using PHPMailer
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email body (HTML)
 * @param string $from_email Sender email (optional)
 * @param string $from_name Sender name (optional)
 * @return bool True if email sent successfully, false otherwise
 */
function send_mail($to, $subject, $message, $from_email = null, $from_name = null)
{
    // Default sender details if not provided
    if ($from_email === null) {
        $from_email = 'samuelkamal61@gmail.com';
    }

    if ($from_name === null) {
        $from_name = 'Smart Exam Portal';
    }

    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->SMTPDebug  = 0;                     // Enable verbose debug output (0 = off, 1 = client messages, 2 = client and server messages)
        $mail->isSMTP();                           // Send using SMTP
        $mail->Host       = 'smtp.gmail.com';      // Gmail SMTP server
        $mail->SMTPAuth   = true;                  // Enable SMTP authentication
        $mail->Username   = 'samuelkamal61@gmail.com'; // Your Gmail address
        $mail->Password   = 'xahl soso pfbq cnum'; // Your Gmail app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Enable TLS encryption
        $mail->Port       = 587;                   // TCP port to connect to

        // Additional settings to fix potential issues
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Recipients
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->CharSet = 'UTF-8';

        // Send email
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log the error for debugging with more details
        error_log("Email sending failed: " . $mail->ErrorInfo);
        // For development, you can uncomment this line to see the error in the browser
        // echo "Email sending failed: " . $mail->ErrorInfo;
        return false;
    }
}

/**
 * Send password reset email
 * 
 * @param string $to Recipient email
 * @param string $username Username
 * @param string $reset_link Password reset link
 * @param string $first_name User's first name (optional)
 * @param string $last_name User's last name (optional)
 * @return bool True if email sent successfully, false otherwise
 */
function send_password_reset_email($to, $username, $reset_link, $first_name = null, $last_name = null)
{
    $subject = "Password Reset - Smart Exam Portal";

    // Create greeting with full name if available, otherwise use username
    $greeting = "Hello ";
    if (!empty($first_name) && !empty($last_name)) {
        $greeting .= "$first_name $last_name";
    } elseif (!empty($first_name)) {
        $greeting .= $first_name;
    } else {
        $greeting .= $username;
    }

    $message = "
    <html>
    <head>
        <title>Password Reset</title>
    </head>
    <body>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px; font-family: Arial, sans-serif;'>
            <h2 style='color: #333;'>Password Reset Request</h2>
            <p>$greeting,</p>
            <p>We received a request to reset your password for your Smart Exam Portal account. Click the button below to reset your password:</p>
            <p style='text-align: center;'>
                <a href='$reset_link' style='display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Reset Password</a>
            </p>
            <p>If you did not request a password reset, please ignore this email or contact support if you have concerns.</p>
            <p>This link will expire in 1 hour for security reasons.</p>
            <p>Regards,<br>Smart Exam Portal Team</p>
        </div>
    </body>
    </html>
    ";

    return send_mail($to, $subject, $message);
}

/**
 * For testing purposes - this function simulates sending an email
 * by saving it to a file instead of actually sending it
 */
function send_test_email($to, $subject, $message, $from_email = null, $from_name = null)
{
    // Default sender details if not provided
    if ($from_email === null) {
        $from_email = 'noreply@smartexamportal.com';
    }

    if ($from_name === null) {
        $from_name = 'Smart Exam Portal';
    }

    // Create a log directory if it doesn't exist
    $log_dir = __DIR__ . '/../logs';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0777, true);
    }

    // Create a log file with the email content
    $log_file = $log_dir . '/email_' . time() . '_' . md5($to . $subject) . '.html';
    $email_content = "To: $to\n";
    $email_content .= "From: $from_name <$from_email>\n";
    $email_content .= "Subject: $subject\n";
    $email_content .= "Date: " . date('Y-m-d H:i:s') . "\n\n";
    $email_content .= $message;

    // Save to file
    if (file_put_contents($log_file, $email_content)) {
        return true;
    }

    return false;
}
