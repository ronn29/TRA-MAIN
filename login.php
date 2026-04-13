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
require './db/dbconn.php'; 


$MAX_ATTEMPTS = 10;          
$MAX_ATTEMPTS_IP = 20;      
$WINDOW_SECONDS = 900;       
$FAIL_DELAY_MICROS = 300000; 


mysqli_query(
    $conn,
    "CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        identifier VARCHAR(255),
        ip VARCHAR(64),
        attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        success TINYINT(1) DEFAULT 0,
        INDEX idx_identifier_time (identifier, attempt_time),
        INDEX idx_ip_time (ip, attempt_time)
    ) ENGINE=InnoDB"
);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $_SESSION['error'] = 'Invalid request. Please try again.';
        header('Location: login.php');
        exit();
    }
    
    $identifier = mysqli_real_escape_string($conn, $_POST['school_id']);
    $password = $_POST['password'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';


    $stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) FROM login_attempts 
         WHERE attempt_time >= DATE_SUB(NOW(), INTERVAL ? SECOND) 
           AND success = 0 
           AND identifier = ?"
    );
    mysqli_stmt_bind_param($stmt, "is", $WINDOW_SECONDS, $identifier);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $failCount);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

 
    if ($failCount >= $MAX_ATTEMPTS) {
        $_SESSION['error'] = 'Too many attempts. Please try again in a few minutes.';
        header('Location: login.php');
        exit();
    }

  
    $stmtIp = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) FROM login_attempts 
         WHERE attempt_time >= DATE_SUB(NOW(), INTERVAL ? SECOND) 
           AND success = 0 
           AND ip = ?"
    );
    mysqli_stmt_bind_param($stmtIp, "is", $WINDOW_SECONDS, $ip);
    mysqli_stmt_execute($stmtIp);
    mysqli_stmt_bind_result($stmtIp, $failCountIp);
    mysqli_stmt_fetch($stmtIp);
    mysqli_stmt_close($stmtIp);

    if ($failCountIp >= $MAX_ATTEMPTS_IP) {
        $_SESSION['error'] = 'Too many attempts from your network. Please try again in a few minutes.';
        header('Location: login.php');
        exit();
    }

    $query = "SELECT * FROM user_tbl WHERE school_id = ? OR email = ? LIMIT 1";
    $stmtUser = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmtUser, "ss", $identifier, $identifier);
    mysqli_stmt_execute($stmtUser);
    $result = mysqli_stmt_get_result($stmtUser);

    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        $hashed_password = $user['password'];

     
        if (password_verify($password, $hashed_password)) {
            if (empty($user['email_verified_at'])) {
                $_SESSION['pending_email'] = $user['email'];
                $_SESSION['error'] = 'Please verify your email to continue.';
            
                $stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO login_attempts (identifier, ip, success) VALUES (?, ?, 0)"
                );
                mysqli_stmt_bind_param($stmt, "ss", $identifier, $ip);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                usleep($FAIL_DELAY_MICROS);
                header('Location: verify.php');
                exit();
            }

            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['school_id'] = $user['school_id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role']; 

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO login_attempts (identifier, ip, success) VALUES (?, ?, 1)"
            );
            mysqli_stmt_bind_param($stmt, "ss", $identifier, $ip);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if ($user['role'] === 'admin') {
                header("Location: ./admin/index.php");

            } elseif ($user['role'] === 'student') {
                header("Location: ./student/profile.php");

            } else {
                $_SESSION['error'] = 'Unknown user role!';
                header('Location: login.php');
            }
            exit();
        } else {
            $_SESSION['error'] = 'Invalid password!';
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO login_attempts (identifier, ip, success) VALUES (?, ?, 0)"
            );
            mysqli_stmt_bind_param($stmt, "ss", $identifier, $ip);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            usleep($FAIL_DELAY_MICROS);
            header('Location: login.php');
            exit();
        }
    } else {
        $_SESSION['error'] = 'Account not found!';
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO login_attempts (identifier, ip, success) VALUES (?, ?, 0)"
        );
        mysqli_stmt_bind_param($stmt, "ss", $identifier, $ip);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        usleep($FAIL_DELAY_MICROS);
        header('Location: login.php');
        exit();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include_once __DIR__ . '/partials/favicon.php'; ?>
    <title>Tragabay Main - Login</title>
 
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="assets/css/free.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <script src="https://kit.fontawesome.com/86d07e616e.js" crossorigin="anonymous" defer></script>
</head>
<body>
    <?php include 'header.php'; ?>

    <section class="login">
        <div class="login-container">
            <h2>Login</h2>

            <?php
            if (isset($_SESSION['success'])) {
                echo '<p style="color:green; font-size: 12px;">' . $_SESSION['success'] . '</p>';
                unset($_SESSION['success']);
            }
            if (isset($_SESSION['error'])) {
                echo '<p style="color:red; font-size: 12px;">' . $_SESSION['error'] . '</p>';
                unset($_SESSION['error']);
            }
            ?>

<form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php
                    if (empty($_SESSION['csrf_token'])) {
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    }
                    echo htmlspecialchars($_SESSION['csrf_token']);
                ?>">
                <div class="input-group">
                    <label for="school_id"><i class="fa-solid fa-user"></i> School ID or Email</label>
                    <input type="text" id="school_id" name="school_id" required placeholder="22-00000">
                    
                </div>

                <div class="input-group password-group">
                    <label for="password"><i class="fa-solid fa-lock"></i> Password</label>
                    <input type="password" id="password" name="password" required>
                    <button type="button" class="toggle-password" data-target="password" aria-label="Toggle password visibility">
                        <i class="fa-solid fa-eye-slash"></i>
                    </button>
                </div>

                <div class="text"><a href="forgot-password.php">Forgot password?</a></div>
                <button type="submit" class="login-btn">Login</button>
                <div class="text"><a href="register.php">Sign up here</a></div>
            </form>
        </div>
    </section>
    <?php include 'footer.php'; ?>
    <script src="assets/js/main.js" defer></script>
</body>
</html>
