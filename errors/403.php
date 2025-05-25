<?php
// Set page title and base URL for includes
$page_title = "403 - Forbidden";
$base_url = "/dashboard/SmartExamPortal";

// Include header
include_once "../includes/header.php";
?>

<div class="container-fluid">
    <div class="text-center mt-5">
        <div class="error mx-auto" data-text="403">403</div>
        <p class="lead text-gray-800 mb-3">Access Forbidden</p>
        <p class="text-gray-500 mb-0">You don't have permission to access this resource.</p>
        <a href="<?php echo $base_url; ?>/index.php">&larr; Back to Home</a>
    </div>
</div>

<?php
// Include footer
include_once "../includes/footer.php";
?>