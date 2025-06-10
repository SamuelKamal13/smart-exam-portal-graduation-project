<div class="d-flex flex-column flex-shrink-0 p-3 text-white bg-dark sidebar-nav" id="sidebarMenu">
    <a href="<?php echo isset($base_url) ? $base_url : ""; ?>/index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
        <i class="fas fa-graduation-cap fs-4 me-2"></i>
        <span class="fs-4">Smart Exam</span>
    </a>
    <hr>
    <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item">
            <?php
            // Determine the correct dashboard link based on user role
            $dashboardLink = "/dashboard/";
            if (isset($_SESSION["role"])) {
                if ($_SESSION["role"] == "student") {
                    $dashboardLink .= "student/index.php";
                } elseif ($_SESSION["role"] == "trainer") {
                    $dashboardLink .= "trainer/index.php";
                } elseif ($_SESSION["role"] == "supervisor") {
                    $dashboardLink .= "supervisor/index.php";
                }
            } else {
                $dashboardLink .= "index.php";
            }
            ?>
            <a href="<?php echo isset($base_url) ? $base_url : ""; ?><?php echo $dashboardLink; ?>" class="nav-link text-white <?php echo (strpos($_SERVER['REQUEST_URI'], $dashboardLink) !== false) ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt me-2"></i>
                Dashboard
            </a>
        </li>

        <?php if (isset($_SESSION["role"]) && $_SESSION["role"] == "student"): ?>
            <li>
                <a href="<?php echo isset($base_url) ? $base_url : ""; ?>/dashboard/student/exams.php" class="nav-link text-white <?php echo (strpos($_SERVER['REQUEST_URI'], '/dashboard/student/exams.php') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt me-2"></i>
                    Upcoming Exams
                </a>
            </li>
            <li>
                <a href="<?php echo isset($base_url) ? $base_url : ""; ?>/dashboard/student/exam-history.php" class="nav-link text-white <?php echo (strpos($_SERVER['REQUEST_URI'], '/dashboard/student/exam-history.php') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-history me-2"></i>
                    Exam History
                </a>
            </li>
            <li>
                <a href="<?php echo isset($base_url) ? $base_url : ""; ?>/dashboard/student/results.php" class="nav-link text-white <?php echo (strpos($_SERVER['REQUEST_URI'], '/dashboard/student/results.php') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar me-2"></i>
                    Results & Analytics
                </a>
            </li>
            <li>
                <a class="nav-link" href="<?php echo $base_url; ?>/ai-chat/chat.php">
                    <i class="fas fa-robot"></i>
                    AI Assistant
                </a>
            </li>
        <?php endif; ?>

        <?php if (isset($_SESSION["role"]) && $_SESSION["role"] == "trainer"): ?>
            <li>
                <a href="<?php echo isset($base_url) ? $base_url : ""; ?>/dashboard/trainer/manage-courses.php" class="nav-link text-white <?php echo (strpos($_SERVER['REQUEST_URI'], '/dashboard/trainer/manage-courses.php') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-book me-2"></i>
                    Manage Courses
                </a>
            </li>
            <li>
                <a href="<?php echo isset($base_url) ? $base_url : ""; ?>/dashboard/trainer/manage-exams.php" class="nav-link text-white <?php echo (strpos($_SERVER['REQUEST_URI'], '/dashboard/trainer/manage-exams.php') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt me-2"></i>
                    Manage Exams
                </a>
            </li>
            <li>
                <a href="<?php echo isset($base_url) ? $base_url : ""; ?>/dashboard/trainer/results.php" class="nav-link text-white <?php echo (strpos($_SERVER['REQUEST_URI'], '/dashboard/trainer/results.php') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar me-2"></i>
                    Student Results
                </a>
            </li>
            <li>
                <a class="nav-link" href="<?php echo $base_url; ?>/ai-chat/chat.php">
                    <i class="fas fa-robot"></i>
                    AI Assistant
                </a>
            </li>
        <?php endif; ?>

        <?php if (isset($_SESSION["role"]) && $_SESSION["role"] == "supervisor"): ?>
            <li>
                <a href="<?php echo isset($base_url) ? $base_url : ""; ?>/dashboard/supervisor/manage-instructors.php" class="nav-link text-white <?php echo (strpos($_SERVER['REQUEST_URI'], '/dashboard/supervisor/manage-instructors.php') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-chalkboard-teacher me-2"></i>
                    Manage Instructors
                </a>
            </li>
            <li>
                <a href="<?php echo isset($base_url) ? $base_url : ""; ?>/dashboard/supervisor/manage-students.php" class="nav-link text-white <?php echo (strpos($_SERVER['REQUEST_URI'], '/dashboard/supervisor/manage-students.php') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-user-graduate me-2"></i>
                    Manage Students
                </a>
            </li>
            <li>
                <a href="<?php echo isset($base_url) ? $base_url : ""; ?>/dashboard/supervisor/generate-codes.php" class="nav-link text-white <?php echo (strpos($_SERVER['REQUEST_URI'], '/dashboard/supervisor/generate-codes.php') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-key me-2"></i>
                    Invitation Codes
                </a>
            </li>
            <li>
                <a href="<?php echo isset($base_url) ? $base_url : ""; ?>/dashboard/supervisor/reports.php" class="nav-link text-white <?php echo (strpos($_SERVER['REQUEST_URI'], '/dashboard/supervisor/reports.php') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line me-2"></i>
                    Reports
                </a>
            </li>
            <li>
                <a class="nav-link" href="<?php echo $base_url; ?>/ai-chat/chat.php">
                    <i class="fas fa-robot"></i>
                    AI Assistant
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <!-- Contact Us Link - Available to all users -->
    <hr>
    <ul class="nav nav-pills flex-column">
        <li>
            <a href="<?php echo isset($base_url) ? $base_url : ""; ?>/contact.php" class="nav-link text-white <?php echo (strpos($_SERVER['REQUEST_URI'], '/contact.php') !== false) ? 'active' : ''; ?>">
                <i class="fas fa-envelope me-2"></i>
                Contact Us
            </a>
        </li>
    </ul>

    <hr>
    <div class="dropdown">
        <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-user-circle me-2 fs-5"></i>
            <strong>
                <?php
                $displayName = "";
                if (isset($_SESSION["first_name"]) && isset($_SESSION["last_name"])) {
                    $displayName = htmlspecialchars($_SESSION["first_name"] . " " . $_SESSION["last_name"]);
                } elseif (isset($_SESSION["user_id"])) {
                    $displayName = htmlspecialchars($_SESSION["user_id"]);
                } elseif (isset($_SESSION["username"])) {
                    $displayName = htmlspecialchars($_SESSION["username"]);
                } else {
                    $displayName = "User";
                }
                echo $displayName;
                ?>
            </strong>
        </a>
        <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
            <li><a class="dropdown-item" href="<?php echo isset($base_url) ? $base_url : ""; ?>/profile.php">Profile</a></li>
            <li><a class="dropdown-item" href="<?php echo isset($base_url) ? $base_url : ""; ?>/auth/change-password.php">Change Password</a></li>
            <li>
                <hr class="dropdown-divider">
            </li>
            <li><a style="color: red" class="dropdown-item" href="<?php echo isset($base_url) ? $base_url : ""; ?>/auth/logout.php">Sign out</a></li>
        </ul>
    </div>
</div>