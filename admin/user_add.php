<?php
session_start();
if (!isset($_SESSION['school_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

require '../db/dbconn.php';
require 'includes/user_functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? '';
    $school_id = mysqli_real_escape_string($conn, $_POST['school_id'] ?? '');
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name'] ?? '');
    $middle_name = mysqli_real_escape_string($conn, $_POST['middle_name'] ?? '');
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name'] ?? '');

    if (empty($role) || empty($school_id) || empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
        $_SESSION['message'] = 'Error: Please fill in all required fields';
        header("Location: users.php");
        exit;
    }

    if ($role === 'admin') {
        $contact_number = mysqli_real_escape_string($conn, $_POST['contact_number'] ?? '');
        
        $result = addAdmin($conn, $school_id, $email, $password, $first_name, $middle_name, $last_name, $contact_number);
        
        if ($result['success']) {
            $_SESSION['message'] = $result['message'];
        } else {
            $_SESSION['message'] = $result['message'];
        }
    } else if ($role === 'student') {
        $gender = mysqli_real_escape_string($conn, $_POST['gender'] ?? '');
        $date_of_birth = mysqli_real_escape_string($conn, $_POST['date_of_birth'] ?? '');
        $program_id = mysqli_real_escape_string($conn, $_POST['program_id'] ?? '');
        $contact_number = mysqli_real_escape_string($conn, $_POST['contact_number'] ?? '');
        $address = mysqli_real_escape_string($conn, $_POST['address'] ?? '');
        
        if (empty($program_id)) {
            $_SESSION['message'] = 'Error: Program is required for students';
            header("Location: users.php");
            exit;
        }
        
        $result = addStudent($conn, $school_id, $email, $password, $first_name, $middle_name, $last_name, $gender, $date_of_birth, $program_id, $contact_number, $address);
        
        if ($result['success']) {
            $_SESSION['message'] = $result['message'];
        } else {
            $_SESSION['message'] = $result['message'];
        }
    } else {
        $_SESSION['message'] = 'Error: Invalid role specified';
    }
    
    header("Location: users.php");
    exit;
} else {
    header("Location: users.php");
    exit;
}
?>

