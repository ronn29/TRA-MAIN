<div class="sidebar">
    <?php
    if (!isset($student_name)) {
        require_once '../db/dbconn.php';
        $school_id = $_SESSION['school_id'];
        $query = "SELECT first_name, last_name FROM student_tbl WHERE school_id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $school_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $student_data = mysqli_fetch_assoc($result);
        $student_name = $student_data ? htmlspecialchars($student_data['first_name'] . ' ' . $student_data['last_name']) : 'Student';
    }

    $photo_version = $_SESSION['profile_img_version'] ?? time();
    $student_photo = "profile_image.php?v=" . $photo_version;
    ?>
    <div class="admin-indicator">
        <a class="profile-img" href="resume.php" title="Edit your resume and photo">
            <img src="<?php echo htmlspecialchars($student_photo); ?>" alt="Profile">
        </a>
        <div class="admin-info">
            <h4><?php echo $student_name; ?></h4>
            <p class="role-badge">Student</p>
        </div>
    </div>
    
    <div class="sidebar-content">
            <div class="sidebar-menu">
                <ul>
                    
                    
                    <!-- <li class="<?php echo ($activePage === 'index') ? 'active' : ''; ?>">
                     <a href="index.php">
                            <span class="las la-money-check"></span>
                            Dashboard
                        </a>                       
                    </li> -->
                    <li class="<?php echo ($activePage === 'profile') ? 'active' : ''; ?>">
                        <a href="profile.php">
                            <span class="las la-user"></span>
                            Profile
                        </a>                       
                    </li>
                    <li class="<?php echo ($activePage === 'assessment_test') ? 'active' : ''; ?>">
                        <a href="assessment_test.php">
                            <span class="las la-clipboard-list "></span>
                            Assessment  
                        </a>
                    </li>
                    <li class="<?php echo ($activePage === 'resume') ? 'active' : ''; ?>">
                        <a href="resume.php">
                            <span class="las la-calendar-alt"></span>
                            Resume
                        </a>
                    </li>
                    <li class="<?php echo ($activePage === 'consultation_calendar') ? 'active' : ''; ?>">
                        <a href="consultation_calendar.php">
                            <span class="las la-calendar"></span>
                            Calendar
                        </a>
                    </li>
                    <li class="<?php echo ($activePage === 'feedback') ? 'active' : ''; ?>">
                        <a href="feedback.php">
                            <span class="las la-comment-dots"></span>
                            Feedback
                        </a>
                    </li>
                </ul>
            </div>
    
            <div class="logout">
                <a href="../index.php">
                    <span class="las la-sign-out-alt la-flip-horizontal"></span>
                    Logout
                </a>
            </div>
        </div>
    </div>