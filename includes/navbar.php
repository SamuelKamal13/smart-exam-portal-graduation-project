<nav class="navbar navbar-expand-lg navbar-light bg-light mb-4 rounded shadow-sm">
    <div class="container-fluid">
        <button class="navbar-toggler d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle sidebar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <a class="navbar-brand" href="<?php echo isset($base_url) ? $base_url : ""; ?>/index.php">
            <span class="fs-4">Smart Exam Portal</span>
        </a>


        <div class="navbar-nav ms-auto">
            <div class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user-circle me-1"></i>
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
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                    <li><a class="dropdown-item" href="<?php echo isset($base_url) ? $base_url : ""; ?>/profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                    <li><a class="dropdown-item" href="<?php echo isset($base_url) ? $base_url : ""; ?>/auth/change-password.php"><i class="fas fa-key me-2"></i>Change Password</a></li>
                    <li>
                        <a class="dropdown-item" href="<?php echo isset($base_url) ? $base_url : ""; ?>/contact.php">
                            <i class="fas fa-envelope me-1"></i> Contact Us
                        </a>
                    </li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a style="color: red" class="dropdown-item" href="<?php echo isset($base_url) ? $base_url : ""; ?>/auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>