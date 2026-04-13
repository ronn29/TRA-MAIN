<?php
session_start();
if (!isset($_SESSION['school_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

require '../db/dbconn.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $assessment_name = mysqli_real_escape_string($conn, $_POST['assessment_name']);
    
    $result = mysqli_query($conn, "SELECT MAX(assessment_order) as max_order FROM assessment_tbl");
    $row = mysqli_fetch_assoc($result);
    $new_order = $row['max_order'] + 1;
    
    $sql = "INSERT INTO assessment_tbl (assessment_name, assessment_order, visibility) VALUES (?, ?, 1)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $assessment_name, $new_order);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['message'] = "Assessment added successfully!";
    } else {
        $_SESSION['message'] = "Error adding assessment: " . mysqli_error($conn);
    }
    
    header("Location: assessment.php");
    exit;
}
?> 