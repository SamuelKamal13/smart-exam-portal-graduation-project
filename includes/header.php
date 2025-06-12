<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . " - " : ""; ?>Smart Exam Portal</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo isset($base_url) ? $base_url : ""; ?>/assets/css/style.css">
    <!-- Modal styles -->
    <link href="<?php echo $base_url; ?>/assets/css/modals.css" rel="stylesheet">
    <!-- Responsive Sidebar CSS -->
    <link href="<?php echo $base_url; ?>/assets/css/responsive-sidebar.css" rel="stylesheet">
    <?php if (isset($extra_css)): ?>
        <?php echo $extra_css; ?>
    <?php endif; ?>
    <link href="<?php echo $base_url; ?>/assets/css/error-pages.css" rel="stylesheet">
    <link href="<?php echo $base_url; ?>/assets/css/manage-questions.css" rel="stylesheet">
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar will be included here -->
            <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                <!-- Sidebar for larger screens (visible) and smaller screens (collapsible) -->
                <div class="col-md-3 col-lg-2 px-0 bg-dark sidebar d-md-block collapse" id="sidebarMenu">
                    <?php include_once(dirname(__FILE__) . "/sidebar.php"); ?>
                </div>
                <!-- Main content area that adjusts based on sidebar visibility -->
                <div class="col-12 col-md-9 col-lg-10 ms-sm-auto px-4 py-3 content-wrapper">
                    <?php include_once(dirname(__FILE__) . "/navbar.php"); ?>
                <?php else: ?>
                    <div class="col-12 px-4 py-3 content-wrapper">
                    <?php endif; ?>