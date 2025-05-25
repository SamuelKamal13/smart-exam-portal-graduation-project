<?php
// Set page title and base URL for includes
$error_code = isset($_GET['code']) ? intval($_GET['code']) : 0;
$error_message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : "An error occurred";

$page_title = "Error $error_code";
$base_url = "/dashboard/SmartExamPortal";

// Include header
include_once "../includes/header.php";
?>

<div class="container-fluid">
    <div class="text-center mt-5">
        <div class="error mx-auto" data-text="<?php echo $error_code; ?>"><?php echo $error_code; ?></div>
        <p class="lead text-gray-800 mb-3"><?php echo $error_message; ?></p>
        <p class="text-gray-500 mb-0">We apologize for the inconvenience.</p>
        <a href="<?php echo $base_url; ?>/index.php">&larr; Back to Home</a>
    </div>
</div>

<?php
// Include footer
include_once "../includes/footer.php";
?>