<?php
session_start();
require './db/dbconn.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $code = trim($_POST['code'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $candidates = mysqli_query($conn, "SELECT user_id, reset_token, reset_expires FROM user_tbl WHERE reset_token IS NOT NULL AND reset_expires > NOW()");

    $matchedUser = null;
    if ($candidates) {
        while ($row = mysqli_fetch_assoc($candidates)) {
            if (password_verify($code, $row['reset_token'])) {
                $matchedUser = $row;
                break;
            }
        }
    }

    if (!$matchedUser) {
        $error = "Invalid or expired code.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        mysqli_query($conn, "UPDATE user_tbl SET password='$hashed', reset_token=NULL, reset_expires=NULL WHERE user_id={$matchedUser['user_id']}");
        $_SESSION['success'] = "Password reset successfully! You can login now.";
        header("Location: login.php");
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
    <title>Reset Password</title>
    <link rel="stylesheet" href="assets/css/free.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <script>
        function validateForm() {
            const pwd = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            if (pwd !== confirm) {
                alert("Passwords do not match!");
                return false;
            }
            return true;
        }

        window.addEventListener('DOMContentLoaded', () => {
            const flash = document.getElementById('flash-success');
            if (flash) {
                setTimeout(() => { flash.style.display = 'none'; }, 4000);
            }
        });
    </script>
</head>
<body>
<?php include 'header.php'; ?>

<section class="login">
    <div class="login-container">
        <h2>Reset Password</h2>

        <?php
        if (isset($_SESSION['success'])) {
            echo '<p id="flash-success" style="color:green; font-size:12px;">' . $_SESSION['success'] . '</p>';
            unset($_SESSION['success']);
        }
        if(isset($error)) echo '<p style="color:red; font-size:12px;">'.$error.'</p>';
        ?>

        <form method="POST" onsubmit="return validateForm()">
            <div class="input-group">
                <label for="code"><i class="fa-solid fa-key"></i> Reset Code</label>
                <input type="text" id="code" name="code" required>
            </div>
            <div class="input-group">
                <label for="password"><i class="fa-solid fa-lock"></i> New Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="input-group">
                <label for="confirm_password"><i class="fa-solid fa-lock"></i> Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="login-btn">Reset Password</button>
            <div class="text"><a href="login.php">Back to login</a></div>
        </form>
    </div>
</section>

<?php include 'footer.php'; ?>
</body>
</html>
