<?php
session_start();
if (!isset($_SESSION['school_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

require '../db/dbconn.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $department_name = mysqli_real_escape_string($conn, $_POST['department_name']);
    
    $result = mysqli_query($conn, "SELECT MAX(department_id) as max_order FROM department_tbl");
    $row = mysqli_fetch_assoc($result);
    $new_order = $row['max_order'] + 1;
    
    $sql = "INSERT INTO department_tbl (department_name, department_id) VALUES (?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $department_name, $new_order);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['message'] = "Department added successfully!";
    } else {
        $_SESSION['message'] = "Error adding department: " . mysqli_error($conn);
    }
    
    header("Location: department.php");
    exit;
}
?> 