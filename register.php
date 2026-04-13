


<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
require "./db/dbconn.php";
require_once __DIR__ . '/mailer.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $_SESSION['error'] = 'Invalid request. Please try again.';
        header('Location: register.php');
        exit();
    }
    $school_id = mysqli_real_escape_string($conn, $_POST['school_id']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Invalid email format';
        header('Location: register.php');
        exit();
    }
    if (!preg_match('/@pcb\.edu\.ph$/i', $email)) {
        $_SESSION['error'] = 'Email must end with @pcb.edu.ph';
        header('Location: register.php');
        exit();
    }

    if ($password !== $confirm_password) {
        $_SESSION['error'] = 'Passwords do not match';
        header('Location: register.php');
        exit();
    }
    if (strlen($password) < 8) {
        $_SESSION['error'] = 'Password must be at least 8 characters long';
        header('Location: register.php');
        exit();
    }

    $check_query = "SELECT * FROM user_tbl WHERE school_id = ? OR email = ? LIMIT 1";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "ss", $school_id, $email);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    if ($check_result && mysqli_num_rows($check_result) > 0) {
        $existing = mysqli_fetch_assoc($check_result);

        if (empty($existing['email_verified_at'])) {
            $code = random_int(100000, 999999);
            $hashedCode = password_hash((string)$code, PASSWORD_BCRYPT, ['cost' => 12]);
            $update_stmt = mysqli_prepare(
                $conn,
                "UPDATE user_tbl 
                 SET verification_code=?, verification_expires=DATE_ADD(NOW(), INTERVAL 1 HOUR) 
                 WHERE user_id=?"
            );
            mysqli_stmt_bind_param($update_stmt, "si", $hashedCode, $existing['user_id']);
            mysqli_stmt_execute($update_stmt);
            mysqli_stmt_close($update_stmt);

            $sendResult = sendMail([
                'to'      => $existing['email'],
                'name'    => $existing['school_id'],
                'subject' => 'Verify your account',
                'body'    => "<p>Hello,</p><p>Your verification code is: <strong>{$code}</strong></p><p>This code expires in 1 hour.</p>",
                'alt'     => "Hello,\nYour verification code is: {$code}\nThis code expires in 1 hour.",
            ]);

            $_SESSION['pending_email'] = $existing['email'];
            $_SESSION['success'] = $sendResult['success']
                ? 'Account already exists but is not verified. We re-sent your verification code.'
                : 'Account exists but is not verified. Failed to resend the code, please try again.';
            header('Location: verify.php');
            exit();
        }

        $_SESSION['error'] = 'School ID or email already exists';
        header('Location: register.php');
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    $code = random_int(100000, 999999);
    $hashedCode = password_hash((string)$code, PASSWORD_BCRYPT, ['cost' => 12]);

    $insert_user_query = "INSERT INTO user_tbl (school_id, email, password, verification_code, verification_expires) 
                          VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))";
    $insert_user_stmt = mysqli_prepare($conn, $insert_user_query);
    mysqli_stmt_bind_param($insert_user_stmt, "ssss", $school_id, $email, $hashed_password, $hashedCode);
    if (mysqli_stmt_execute($insert_user_stmt)) {
        $user_id = mysqli_insert_id($conn);

        $insert_student_query = "INSERT INTO student_tbl (school_id, user_id, email) 
                                 VALUES (?, ?, ?)"; 
        $insert_student_stmt = mysqli_prepare($conn, $insert_student_query);
        mysqli_stmt_bind_param($insert_student_stmt, "sis", $school_id, $user_id, $email);

        if (mysqli_stmt_execute($insert_student_stmt)) {
            $sendResult = sendMail([
                'to'      => $email,
                'name'    => $school_id,
                'subject' => 'Verify your account',
                'body'    => "<p>Hello,</p><p>Your verification code is: <strong>{$code}</strong></p><p>This code expires in 1 hour.</p>",
                'alt'     => "Hello,\nYour verification code is: {$code}\nThis code expires in 1 hour.",
            ]);

            $_SESSION['pending_email'] = $email;
            $_SESSION['success'] = $sendResult['success']
                ? 'Registration successful! Please check your email for the verification code.'
                : 'Registration saved, but email failed to send. Please request a new code on the verify page.';
            header('Location: verify.php');
            exit();
        } else {
            $_SESSION['error'] = 'Student table error: ' . mysqli_error($conn);
            header('Location: register.php');
            exit();
        }
    } else {
        $_SESSION['error'] = 'User table error: ' . mysqli_error($conn);
        header('Location: register.php');
        exit();
    }

    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <?php include_once __DIR__ . '/partials/favicon.php'; ?>
        <title>Tragabay Main - Register</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="assets/css/free.css">
        <link rel="stylesheet" href="assets/css/auth.css">
        <script src="https://kit.fontawesome.com/86d07e616e.js" crossorigin="anonymous" defer></script>
    </head>
<body>
    <?php include 'header.php'; ?>

    <section class="register">
        <div class="register-container">
            <h2>Register</h2>

            <?php
            if (isset($_SESSION['error'])) {
                echo '<p style="color:red; font-size: 12px;">' . $_SESSION['error'] . '</p>';
                unset($_SESSION['error']);
            }
            ?>



            <form action="register.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php
                    if (empty($_SESSION['csrf_token'])) {
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    }
                    echo htmlspecialchars($_SESSION['csrf_token']);
                ?>">
                <div class="input-group">
                    <label for="school_id"><i class="fa-solid fa-user"></i> School ID</label> 
                    <input type="text" id="school_id" name="school_id" required>
                </div>
                <div class="input-group">
                    <label for="email"><i class="fa-solid fa-user"></i> Email</label> 
                    <input type="email" id="email" name="email" required pattern=".+@pcb\.edu\.ph" title="Email must end with @pcb.edu.ph">
                </div>
                <div class="input-group password-group">
                    <label for="password"><i class="fa-solid fa-lock"></i> Password</label>
                    <input type="password" id="password" name="password" required>
                    <button type="button" class="toggle-password" data-target="password" aria-label="Toggle password visibility">
                        <i class="fa-solid fa-eye-slash"></i>
                    </button>
                </div>
                <div class="input-group password-group">
                    <label for="confirm_password"><i class="fa-solid fa-lock"></i> Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <button type="button" class="toggle-password" data-target="confirm_password" aria-label="Toggle password visibility">
                        <i class="fa-solid fa-eye-slash"></i>
                    </button>
                </div>
                <button type="submit" class="register-btn">Sign Up</button>
                <div class="text"><a href="login.php">Sign in here</a></div>
            </form>
        </div>
    </section>
    <?php include 'footer.php'; ?>
    <script>
        document.querySelectorAll('.toggle-password').forEach(btn => {
            const target = document.getElementById(btn.dataset.target);
            if (!target) return;
            btn.addEventListener('click', () => {
                const isPassword = target.type === 'password';
                target.type = isPassword ? 'text' : 'password';
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-eye');
                    icon.classList.toggle('fa-eye-slash');
                }
            });
        });
    </script>
</body>
</html>


