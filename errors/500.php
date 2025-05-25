<?php
// Set page title and base URL for includes
$page_title = "500 - Server Error";
$base_url = "/dashboard/SmartExamPortal";

// Include header
include_once "../includes/header.php";
?>

<div class="container-fluid">
    <div class="text-center mt-5">
        <div class="error mx-auto" data-text="500">500</div>
        <p class="lead text-gray-800 mb-3">Internal Server Error</p>
        <p class="text-gray-500 mb-0">Something went wrong on our end. Please try again later.</p>
        <a href="<?php echo $base_url; ?>/index.php">&larr; Back to Home</a>
    </div>
</div>

<?php
// Include footer
include_once "../includes/footer.php";
?>