<?php
session_start();
require './db/dbconn.php';
require_once __DIR__ . '/mailer.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);

   
    $query = "
        SELECT u.*, s.first_name
        FROM user_tbl u
        LEFT JOIN student_tbl s ON s.user_id = u.user_id
        WHERE u.email = '$email'
        LIMIT 1
    ";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);

        $code = random_int(100000, 999999);
        $hashedCode = password_hash((string)$code, PASSWORD_BCRYPT, ['cost' => 12]);
        mysqli_query(
            $conn,
            "UPDATE user_tbl 
             SET reset_token='$hashedCode', reset_expires=DATE_ADD(NOW(), INTERVAL 1 HOUR) 
             WHERE user_id={$user['user_id']}"
        );

        $name = $user['first_name'] ?? $user['school_id'];
        $subject = "Password Reset Code";
        $body = "
            <p>Hello {$name},</p>
            <p>Your password reset code is: <strong>{$code}</strong></p>
            <p>This code expires in 1 hour.</p>
            <p>If you did not request this, you can ignore this email.</p>
        ";
        $alt = "Hello {$name},\nYour password reset code is: {$code}\nThis code expires in 1 hour.\nIf you did not request this, you can ignore this email.";
        $sendResult = sendMail([
            'to'      => $email,
            'name'    => $name,
            'subject' => $subject,
            'body'    => $body,
            'alt'     => $alt,
        ]);

        if (!$sendResult['success']) {
            $_SESSION['error'] = "Email failed to send. Please try again.";
        }
    }

    $_SESSION['success'] = "If your email exists, a reset code has been sent.";
    header("Location: reset-password.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include_once __DIR__ . '/partials/favicon.php'; ?>
    <title>Forgot Password</title>
    <link rel="stylesheet" href="assets/css/free.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
<?php include 'header.php'; ?>

<section class="login">
    <div class="login-container">
        <h2>Forgot Password</h2>

        <?php
        if (isset($_SESSION['success'])) {
            echo '<p style="color:green; font-size:12px;">' . $_SESSION['success'] . '</p>';
            unset($_SESSION['success']);
        }
        ?>

        <form method="POST">
            <div class="input-group">
                <label for="email"><i class="fa-solid fa-envelope"></i> Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <button type="submit" class="login-btn">Send Reset Code</button>
            <div class="text"><a href="login.php">Back to login</a></div>
        </form>
    </div>
</section>

<?php include 'footer.php'; ?>
</body>
</html>
