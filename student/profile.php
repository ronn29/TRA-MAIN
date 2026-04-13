<?php 
    session_start();
    require '../db/dbconn.php';

    if (!isset($_SESSION['school_id'])) {
        header('Location: ../login.php');
        exit();
    }

    $school_id = $_SESSION['school_id'];
    $query = "SELECT * FROM student_tbl WHERE school_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $school_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $student = mysqli_fetch_assoc($result);


    $programs_query = "SELECT program_id, program_code, program_name FROM program_tbl ORDER BY program_name";
    $programs_result = mysqli_query($conn, $programs_query);

    $default_fields = [
        'first_name' => '',
        'last_name' => '',
        'email' => '',
        'program_id' => '',
        'school_id' => $school_id
    ];

    // Merge database values with defaults
    if ($student) {
        $student = array_merge($default_fields, $student);
    } else {
        $student = $default_fields;
    }

    $required_fields = ['first_name', 'last_name', 'email', 'program_id'];
    $isProfileComplete = true;
    foreach ($required_fields as $field) {
        $value = trim((string)($student[$field] ?? ''));
        if ($value === '') {
            $isProfileComplete = false;
            break;
        }
    }

    // Show modal if profile is incomplete or explicitly requested
    $showProfileModal = !$isProfileComplete || (!empty($_SESSION['show_profile_modal']));
    if (!empty($_SESSION['show_profile_modal'])) {
        unset($_SESSION['show_profile_modal']);
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    $activePage = 'profile';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include_once __DIR__ . '/../partials/favicon.php'; ?>
    <title>Student Profile</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/student.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <style>
        #profileForm input,
        #profileForm select,
        #profileForm textarea {
            pointer-events: auto !important;
        }

        .profile-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.55);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .profile-modal {
            background: #fff;
            padding: 24px;
            max-width: 520px;
            width: 90%;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: left;
        }

        .profile-modal h3 {
            margin-top: 0;
            margin-bottom: 12px;
        }

        .profile-modal p {
            margin: 0 0 16px 0;
            color: #444;
            line-height: 1.5;
        }

        .profile-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-secondary {
            background: #e0e0e0;
            color: #333;
            border: none;
            padding: 10px 18px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-secondary:hover {
            background: #d5d5d5;
        }
    </style>
</head>
<body>
    <!-- Prompt students to complete their profile before accessing assessments -->
    <div class="profile-modal-overlay" id="profileModal" style="display: <?php echo $showProfileModal ? 'flex' : 'none'; ?>;">
        <div class="profile-modal">
            <h3>Complete Your Profile</h3>
            <p>
                Please finish your profile details (name, email, and program) before taking any assessments.
            </p>
            <div class="profile-modal-actions">
                <button type="button" class="btn btn-primary" id="profileModalFocus">Update Profile</button>
            </div>
        </div>
    </div>

    <input type="checkbox" id="sidebar-toggle">
    <?php include 'sidebar.php'; ?>
    <label for="sidebar-toggle" class="sidebar-overlay"></label>

    <div class="main-content">
        <?php include 'header.php'; ?>

        <?php
            $photoVersion = $_SESSION['profile_img_version'] ?? time();
            $photoFileVersioned = "profile_image.php?v=" . $photoVersion;

            $fullName = htmlspecialchars(trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')) ?: 'Student');
            $emailDisplay = htmlspecialchars($student['email'] ?? '');
        ?>

        <div class="container settings-page">
            <h2>
                <span class="las la-user"></span>
                Profile
            </h2>

            <div class="settings-hero">
                <div class="settings-hero-avatar">
                    <img src="<?php echo htmlspecialchars($photoFileVersioned); ?>" alt="Profile photo">
                </div>
                <div class="settings-hero-details">
                    <h3><?php echo $fullName; ?></h3>
                    <p class="settings-hero-role">Student</p>
                    <p class="settings-hero-email"><?php echo $emailDisplay; ?></p>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php 
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="settings-grid">
                <div class="settings-card">
                    <div class="settings-card-header">
                        <h3>Profile Information</h3>
                    </div>
                    <form class="settings-form" method="POST" action="update_profile.php" id="profileForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <label class="settings-label">First Name</label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($student['first_name']); ?>" required>

                        <label class="settings-label">Last Name</label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($student['last_name']); ?>" required>

                        <label class="settings-label">Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($student['email']); ?>" required>

                        <label class="settings-label">School ID</label>
                        <input type="text" value="<?php echo htmlspecialchars($student['school_id']); ?>" readonly>

                        <label class="settings-label">Program</label>
                        <select name="program_id">
                            <option value="">Select Program</option>
                            <?php while ($program = mysqli_fetch_assoc($programs_result)): ?>
                                <option value="<?php echo $program['program_id']; ?>" 
                                        <?php echo ($student['program_id'] == $program['program_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($program['program_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>

                        <button type="submit" class="btn-primary">Save Changes</button>
                    </form>
                </div>

                <div class="settings-card">
                    <div class="settings-card-header">
                        <h3>Password &amp; Security</h3>
                    </div>
                    <form class="settings-form" method="POST" action="change_password.php">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <label class="settings-label">Current Password</label>
                        <input type="password" name="current_password" autocomplete="current-password" required>

                        <label class="settings-label">New Password</label>
                        <input type="password" name="new_password" autocomplete="new-password" minlength="8" required>

                        <label class="settings-label">Confirm New Password</label>
                        <input type="password" name="confirm_new_password" autocomplete="new-password" minlength="8" required>

                        <p style="font-size: 13px; color: #666; margin-top: 4px;">Use at least 8 characters. Avoid reusing your old password.</p>

                        <button type="submit" class="btn-primary">Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function() {
            const modal = document.getElementById('profileModal');
            const focusBtn = document.getElementById('profileModalFocus');
            const dismissBtn = document.getElementById('profileModalDismiss');
            const firstNameInput = document.querySelector('input[name="first_name"]');

            if (!modal) return;

            focusBtn?.addEventListener('click', function() {
                modal.style.display = 'none';
                firstNameInput?.focus();
            });

            dismissBtn?.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        })();
    </script>
</body>
</html>
