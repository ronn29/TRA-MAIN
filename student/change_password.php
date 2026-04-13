<?php
session_start();
require '../db/dbconn.php';

if (!isset($_SESSION['school_id'])) {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit();
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    $_SESSION['error'] = "Invalid request. Please try again.";
    header('Location: profile.php');
    exit();
}

$school_id = $_SESSION['school_id'];
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_new_password = $_POST['confirm_new_password'] ?? '';

if ($new_password !== $confirm_new_password) {
    $_SESSION['error'] = "New passwords do not match.";
    header('Location: profile.php');
    exit();
}

if (strlen($new_password) < 8) {
    $_SESSION['error'] = "New password must be at least 8 characters long.";
    header('Location: profile.php');
    exit();
}

$user_query = "SELECT user_id, password FROM user_tbl WHERE school_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $user_query);

if (!$stmt) {
    $_SESSION['error'] = "Unable to prepare password check.";
    header('Location: profile.php');
    exit();
}

mysqli_stmt_bind_param($stmt, "s", $school_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = $result ? mysqli_fetch_assoc($result) : null;
mysqli_stmt_close($stmt);

if (!$user) {
    $_SESSION['error'] = "Account not found.";
    header('Location: profile.php');
    exit();
}

if (!password_verify($current_password, $user['password'])) {
    $_SESSION['error'] = "Current password is incorrect.";
    header('Location: profile.php');
    exit();
}

if (password_verify($new_password, $user['password'])) {
    $_SESSION['error'] = "New password must be different from the current password.";
    header('Location: profile.php');
    exit();
}

$hashed_new_password = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);

$update_query = "UPDATE user_tbl SET password = ? WHERE user_id = ?";
$update_stmt = mysqli_prepare($conn, $update_query);

if (!$update_stmt) {
    $_SESSION['error'] = "Unable to prepare password update.";
    header('Location: profile.php');
    exit();
}

mysqli_stmt_bind_param($update_stmt, "si", $hashed_new_password, $user['user_id']);

if (mysqli_stmt_execute($update_stmt)) {
    $_SESSION['success'] = "Password updated successfully.";
} else {
    $_SESSION['error'] = "Failed to update password. Please try again.";
}

mysqli_stmt_close($update_stmt);

header('Location: profile.php');
exit();

