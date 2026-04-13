<?php
session_start();
require '../db/dbconn.php';

if (!isset($_SESSION['school_id'])) {
    header('Location: ../login.php');
    exit();
}

$programs_sql = "SELECT program_id, program_code, program_name FROM program_tbl ORDER BY program_name";
$programs_result = mysqli_query($conn, $programs_sql);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $school_id = $_SESSION['school_id'];

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid request. Please try again.";
        header('Location: profile.php');
        exit();
    }
    
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $contact_number = $_POST['contact_number'] ?? '';
    $address = $_POST['address'] ?? '';
    $program_id = $_POST['program_id'] ? intval($_POST['program_id']) : null;


    $query = "UPDATE student_tbl SET 
        first_name = ?,
        last_name = ?,
        email = ?,
        contact_number = ?,
        address = ?,
        program_id = ?,
        date_updated = NOW()
        WHERE school_id = ?";

    try {
        mysqli_begin_transaction($conn);
        
        $stmt = mysqli_prepare($conn, $query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($stmt, "sssssis", 
            $first_name, $last_name, $email, $contact_number, $address,
            $program_id, $school_id
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Execute failed: " . mysqli_stmt_error($stmt));
        }
        
        $user_query = "UPDATE user_tbl SET email = ? WHERE school_id = ?";
        $user_stmt = mysqli_prepare($conn, $user_query);
        if (!$user_stmt) {
            throw new Exception("User table update prepare failed: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($user_stmt, "ss", $email, $school_id);
        
        if (!mysqli_stmt_execute($user_stmt)) {
            throw new Exception("User table update failed: " . mysqli_stmt_error($user_stmt));
        }
        
        mysqli_commit($conn);

        if (mysqli_stmt_affected_rows($stmt) > 0 || mysqli_stmt_affected_rows($user_stmt) > 0) {
            $_SESSION['success'] = "Profile updated successfully!";
        } else {
            $_SESSION['success'] = "No changes were made to the profile.";
        }
        
        mysqli_stmt_close($user_stmt);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Profile update error: " . $e->getMessage());
        $_SESSION['error'] = "Error updating profile: " . $e->getMessage();
    }

    header('Location: profile.php');
    exit();
} else {
    header('Location: profile.php');
    exit();
}
?> 