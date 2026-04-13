<?php
session_start();
if (!isset($_SESSION['school_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

require '../db/dbconn.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $program_name   = mysqli_real_escape_string($conn, $_POST['program_name']);
    $department_id  = intval($_POST['department_id']);

    $result = mysqli_query($conn, "SELECT MAX(program_id) as max_id FROM program_tbl");
    $row = mysqli_fetch_assoc($result);
    $new_id = $row['max_id'] + 1;

    $sql = "INSERT INTO program_tbl (program_id, program_name, department_id) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "isi", $new_id, $program_name, $department_id);

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['message'] = "Program added successfully!";
    } else {
        $_SESSION['message'] = "Error adding program: " . mysqli_error($conn);
    }

    header("Location: program.php");
    exit;
}
?>
