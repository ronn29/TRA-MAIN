<div class="sidebar">
    <?php
    if (!isset($admin_name)) {
        require_once '../db/dbconn.php';
        $user_id = $_SESSION['user_id'];
        $query = "SELECT first_name, last_name FROM admin_tbl WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $admin_data = mysqli_fetch_assoc($result);
        $admin_name = $admin_data ? htmlspecialchars($admin_data['first_name'] . ' ' . $admin_data['last_name']) : 'Administrator';
    }
    ?>
    <div class="admin-indicator">
        <?php
        $profileVersion = $_SESSION['admin_profile_img_version'] ?? time();
        $profileImg = "profile_image.php?v=" . $profileVersion;
        ?>
        <div class="profile-img">
            <img src="<?php echo htmlspecialchars($profileImg); ?>" alt="Profile">
        </div>
        <div class="admin-info">
            <h4><?php echo $admin_name; ?></h4>
            <p class="role-badge">Administrator</p>
        </div>
    </div>

    <div class="sidebar-content">
        <div class="sidebar-menu">
            <ul>
                <li class="<?php echo ($activePage === 'index') ? 'active' : ''; ?>">
                    <a href="index.php">
                        <span class="las la-money-check"></span>
                        Dashboard
                    </a>                       
                </li>
                <li class="<?php echo ($activePage === 'department') ? 'active' : ''; ?>">
                    <a href="department.php">
                        <span class="las la-building"></span>
                        Department 
                    </a>
                </li>
                <li class="<?php echo ($activePage === 'program') ? 'active' : ''; ?>">
                    <a href="program.php">
                        <span class="las la-stream"></span>
                        Program 
                    </a>
                </li>
                
                <li class="<?php echo ($activePage === 'assessment') ? 'active' : ''; ?>">
                    <a href="assessment.php">
                        <span class="las la-clipboard-list "></span>
                        Assessment 
                    </a>
                </li>
                <li class="<?php echo ($activePage === 'career_templates') ? 'active' : ''; ?>">
                    <a href="career_management.php">
                        <span class="las la-briefcase"></span>
                        Career Templates 
                    </a>
                </li>
                
                <li class="<?php echo ($activePage === 'users') ? 'active' : ''; ?>">
                    <a href="users.php">
                        <span class="las la-users"></span>
                        Users
                    </a>
                </li>
                <li class="<?php echo ($activePage === 'calendar') ? 'active' : ''; ?>">
                    <a href="consultations.php">
                        <span class="las la-calendar-alt"></span>
                        Calendar
                    </a>
                </li>
                <li class="<?php echo ($activePage === 'feedback') ? 'active' : ''; ?>">
                    <a href="feedback.php">
                        <span class="las la-comment-dots"></span>
                        Feedback
                    </a>
                </li>
                <li class="<?php echo ($activePage === 'settings') ? 'active' : ''; ?>">
                    <a href="settings.php">
                        <span class="las la-cog"></span>
                        Settings
                    </a>
                </li>
    
            </ul>
        </div>
    </div>
    <div class="logout">
        <a href="../logout.php">
            <span class="las la-sign-out-alt la-flip-horizontal"></span>
            Logout
        </a>
    </div>
</div>
<script src="../assets/js/admin-confirm.js"></script>