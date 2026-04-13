<?php
session_start();
require './db/dbconn.php';
require_once __DIR__ . '/mailer.php';

$prefillEmail = $_SESSION['pending_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $code = trim($_POST['code'] ?? '');

    if (isset($_POST['resend'])) {
        $res = mysqli_query($conn, "SELECT user_id, school_id FROM user_tbl WHERE email='$email' LIMIT 1");
        if ($res && mysqli_num_rows($res) === 1) {
            $user = mysqli_fetch_assoc($res);
            $newCode = random_int(100000, 999999);
            $hashedCode = password_hash((string)$newCode, PASSWORD_BCRYPT, ['cost' => 12]);
            mysqli_query(
                $conn,
                "UPDATE user_tbl 
                 SET verification_code='$hashedCode', verification_expires=DATE_ADD(NOW(), INTERVAL 1 HOUR) 
                 WHERE user_id={$user['user_id']}"
            );
            $sendResult = sendMail([
                'to'      => $email,
                'name'    => $user['school_id'],
                'subject' => 'Verify your account',
                'body'    => "<p>Hello,</p><p>Your verification code is: <strong>{$newCode}</strong></p><p>This code expires in 1 hour.</p>",
                'alt'     => "Hello,\nYour verification code is: {$newCode}\nThis code expires in 1 hour.",
            ]);

            $_SESSION['pending_email'] = $email;
            $_SESSION['success'] = $sendResult['success']
                ? 'Verification code re-sent. Check your email.'
                : 'Failed to resend verification code. Please try again.';
        } else {
            $_SESSION['error'] = 'Account not found for that email.';
        }
        header('Location: verify.php');
        exit();
    }

    $query = mysqli_query($conn, "SELECT user_id, verification_code, verification_expires FROM user_tbl WHERE email='$email' LIMIT 1");
    if ($query && mysqli_num_rows($query) === 1) {
        $user = mysqli_fetch_assoc($query);

        if (empty($user['verification_code']) || empty($user['verification_expires']) || strtotime($user['verification_expires']) < time()) {
            $_SESSION['error'] = 'Code expired or unavailable. Please resend a new code.';
        } elseif (!password_verify($code, $user['verification_code'])) {
            $_SESSION['error'] = 'Invalid verification code.';
        } else {
            mysqli_query(
                $conn,
                "UPDATE user_tbl 
                 SET email_verified_at=NOW(), verification_code=NULL, verification_expires=NULL 
                 WHERE user_id={$user['user_id']}"
            );
            unset($_SESSION['pending_email']);
            $_SESSION['success'] = 'Email verified! You can login now.';
            header('Location: login.php');
            exit();
        }
    } else {
        $_SESSION['error'] = 'Account not found for that email.';
    }

    $_SESSION['pending_email'] = $email;
    header('Location: verify.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include_once __DIR__ . '/partials/favicon.php'; ?>
    <title>Verify Email</title>
    <link rel="stylesheet" href="assets/css/free.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <script src="https://kit.fontawesome.com/86d07e616e.js" crossorigin="anonymous"></script>
</head>
<body>
<?php include 'header.php'; ?>

<section class="login">
    <div class="login-container">
        <h2>Verify Email</h2>

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

        <form method="POST" action="verify.php">
            <div class="input-group">
                <label for="email"><i class="fa-solid fa-envelope"></i> Email</label>
                <input type="email" id="email" name="email" required pattern=".+@pcb\.edu\.ph" title="Email must end with @pcb.edu.ph" value="<?php echo htmlspecialchars($prefillEmail); ?>">
            </div>
            <div class="input-group">
                <label for="code"><i class="fa-solid fa-key"></i> Verification Code</label>
                <input type="text" id="code" name="code" required minlength="6" maxlength="6" pattern="\d{6}">
            </div>
            <button type="submit" class="login-btn">Verify</button>
        </form>

        <form method="POST" action="verify.php" style="margin-top: 10px;">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($prefillEmail); ?>">
            <button type="submit" name="resend" class="login-btn" style="background:#6c757d;">Resend Code</button>
        </form>

        <div class="text" style="margin-top:10px;"><a href="login.php">Back to login</a></div>
    </div>
</section>

<?php include 'footer.php'; ?>
</body>
</html>

