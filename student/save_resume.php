<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
require '../db/dbconn.php';

if (!isset($_SESSION['school_id'])) {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid request. Please try again.";
        header('Location: resume.php');
        exit();
    }
    $school_id = $_SESSION['school_id'];
    
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $middle_name = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : '';
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $contact_number = isset($_POST['contact_number']) ? trim($_POST['contact_number']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $linkedin_profile = isset($_POST['linkedin_profile']) ? trim($_POST['linkedin_profile']) : '';
    $personal_statement = isset($_POST['personal_statement']) ? trim($_POST['personal_statement']) : '';
    $education = isset($_POST['education']) ? trim($_POST['education']) : '';
    $work_experience = isset($_POST['work_experience']) ? trim($_POST['work_experience']) : '';
    $skills = isset($_POST['skills']) ? trim($_POST['skills']) : '';
    $extracurricular = isset($_POST['extracurricular']) ? trim($_POST['extracurricular']) : '';
    $awards = isset($_POST['awards']) ? trim($_POST['awards']) : '';
    $ref = isset($_POST['ref']) ? trim($_POST['ref']) : '';

    if (empty($first_name) || empty($last_name) || empty($email)) {
        $_SESSION['error'] = "Please fill in all required fields (First Name, Last Name, and Email).";
        header('Location: resume.php');
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Please enter a valid email address.";
        header('Location: resume.php');
        exit();
    }

    $profile_picture_blob = null;
    $profile_picture_mime = null;
    $remove_picture = isset($_POST['remove_profile_picture']) && $_POST['remove_profile_picture'] == '1';
    
    $existing_pic_query = "SELECT profile_picture_blob, profile_picture_mime FROM student_tbl WHERE school_id = ?";
    $existing_pic_stmt = mysqli_prepare($conn, $existing_pic_query);
    mysqli_stmt_bind_param($existing_pic_stmt, "s", $school_id);
    mysqli_stmt_execute($existing_pic_stmt);
    $existing_pic_result = mysqli_stmt_get_result($existing_pic_stmt);
    $existing_pic_row = mysqli_fetch_assoc($existing_pic_result);
    $existing_blob = $existing_pic_row['profile_picture_blob'] ?? null;
    $existing_mime = $existing_pic_row['profile_picture_mime'] ?? null;

    if ($remove_picture) {
        $profile_picture_blob = null;
        $profile_picture_mime = null;
        $_SESSION['profile_img_version'] = time();
    } elseif (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $file = $_FILES['profile_picture'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $file_type = mime_content_type($file_tmp);
        
        if (!in_array($file_type, $allowed_types)) {
            $_SESSION['error'] = "Invalid file type. Please upload a JPG, PNG, or GIF image.";
            header('Location: resume.php');
            exit();
        }
        
        if ($file_size > 2 * 1024 * 1024) {
            $_SESSION['error'] = "Image size must be less than 2MB.";
            header('Location: resume.php');
            exit();
        }
        
        $profile_picture_blob = file_get_contents($file_tmp);
        $profile_picture_mime = $file_type;
        $_SESSION['profile_img_version'] = time();
    } else {
        $profile_picture_blob = $existing_blob;
        $profile_picture_mime = $existing_mime;
    }

    try {
        $check_query = "SELECT school_id FROM student_tbl WHERE school_id = ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, "s", $school_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) > 0) {
            $update_query = "UPDATE student_tbl SET 
                first_name = ?,
                middle_name = ?,
                last_name = ?,
                email = ?,
                contact_number = ?,
                address = ?,
                linkedin_profile = ?,
                profile_picture_blob = ?,
                profile_picture_mime = ?,
                profile_picture = '',
                personal_statement = ?,
                education = ?,
                work_experience = ?,
                skills = ?,
                extracurricular = ?,
                awards = ?,
                ref = ?,
                date_updated = NOW()
                WHERE school_id = ?";

            $stmt = mysqli_prepare($conn, $update_query);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . mysqli_error($conn));
            }

            mysqli_stmt_bind_param(
                $stmt,
                "sssssssbsssssssss",
                $first_name,
                $middle_name,
                $last_name,
                $email,
                $contact_number,
                $address,
                $linkedin_profile,
                $profile_picture_blob,
                $profile_picture_mime,
                $personal_statement,
                $education,
                $work_experience,
                $skills,
                $extracurricular,
                $awards,
                $ref,
                $school_id
            );
            if ($profile_picture_blob !== null) {
                mysqli_stmt_send_long_data($stmt, 7, $profile_picture_blob);
            }

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Execute failed: " . mysqli_stmt_error($stmt));
            }

            if (mysqli_stmt_affected_rows($stmt) > 0) {
                $_SESSION['success'] = "Resume saved successfully!";
            } else {
                $_SESSION['success'] = "Resume information saved!";
            }
        } else {
            $_SESSION['error'] = "Student record not found. Please contact administrator.";
        }

    } catch (Exception $e) {
        error_log("Resume save error: " . $e->getMessage());
        $_SESSION['error'] = "Error saving resume: " . $e->getMessage();
    }

    header('Location: resume.php');
    exit();
} else {
    header('Location: resume.php');
    exit();
}
?>

