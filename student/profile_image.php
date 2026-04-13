<?php
session_start();
require '../db/dbconn.php';

if (!isset($_SESSION['school_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$school_id = $_SESSION['school_id'];

$sql = "SELECT profile_picture_blob, profile_picture_mime FROM student_tbl WHERE school_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    http_response_code(500);
    exit('Error preparing statement');
}

mysqli_stmt_bind_param($stmt, "s", $school_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = $result ? mysqli_fetch_assoc($result) : null;

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($row && !empty($row['profile_picture_blob'])) {
    $mime = !empty($row['profile_picture_mime']) ? $row['profile_picture_mime'] : 'image/jpeg';
    header('Content-Type: ' . $mime);
    echo $row['profile_picture_blob'];
    exit();
}

$default = __DIR__ . '/../assets/img/profile.jpg';
if (file_exists($default)) {
    header('Content-Type: image/jpeg');
    readfile($default);
} else {
    http_response_code(404);
    echo 'No image available';
}

