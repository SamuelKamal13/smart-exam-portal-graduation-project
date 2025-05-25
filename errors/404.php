<?php
// Set page title and base URL for includes
$page_title = "404 - Page Not Found";

// Use absolute path for base URL
$base_url = "/dashboard/SmartExamPortal";

// Include header
include_once "../includes/header.php";
?>

<div class="container-fluid">
    <div class="text-center mt-5">
        <div class="error mx-auto" data-text="404">404</div>
        <p class="lead text-gray-800 mb-3">Page Not Found</p>
        <p class="text-gray-500 mb-0">It seems you've found a glitch in the matrix...</p>
        <a href="<?php echo $base_url; ?>/index.php">&larr; Back to Home</a>
    </div>
</div>

<?php
// Include footer
include_once "../includes/footer.php";
?>