<?php

function getUsers($conn, $role = null) {
    $sql = "SELECT 
                u.user_id, u.school_id, u.email, u.role, u.created_at,
                COALESCE(a.first_name, s.first_name) as first_name,
                COALESCE(a.middle_name, s.middle_name) as middle_name,
                COALESCE(a.last_name, s.last_name) as last_name,
                COALESCE(a.admin_id, s.school_id) as record_id,
                COALESCE(a.status, s.status) as status,
                s.program_id,
                s.school_id AS student_school_id,
                p.program_name
            FROM user_tbl u
            LEFT JOIN admin_tbl a 
                ON u.user_id = a.user_id AND u.role = 'admin'
            LEFT JOIN student_tbl s 
                ON u.user_id = s.user_id AND u.role = 'student'
            LEFT JOIN program_tbl p 
                ON s.program_id = p.program_id
            WHERE 1=1";

    if ($role !== null) {
        $sql .= " AND u.role = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $role);
    } else {
        $stmt = mysqli_prepare($conn, $sql);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function getUserById($conn, $user_id) {
    $sql = "SELECT 
                u.*, 
                a.admin_id, a.first_name AS admin_first_name, a.middle_name AS admin_middle_name, 
                a.last_name AS admin_last_name, a.contact_number AS admin_contact, a.status AS admin_status,
                s.school_id AS student_school_id, s.first_name AS student_first_name, 
                s.middle_name AS student_middle_name, s.last_name AS student_last_name,
                s.ext_name, s.gender, s.date_of_birth, 
                s.program_id, s.contact_number AS student_contact, 
                s.address, s.status AS student_status,
                p.program_name
            FROM user_tbl u
            LEFT JOIN admin_tbl a 
                ON u.user_id = a.user_id AND u.role = 'admin'
            LEFT JOIN student_tbl s 
                ON u.user_id = s.user_id AND u.role = 'student'
            LEFT JOIN program_tbl p 
                ON s.program_id = p.program_id
            WHERE u.user_id = ?";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return mysqli_fetch_assoc($result);
}

function handleUserDelete($conn, $user_id) {
    $sql = "DELETE FROM user_tbl WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);

    if (mysqli_stmt_execute($stmt)) {
        return "User deleted successfully!";
    } else {
        return "Error deleting user: " . mysqli_error($conn);
    }
}

function addAdmin($conn, $school_id, $email, $password, $first_name, $middle_name, $last_name, $contact_number) {
    $check_sql = "SELECT user_id FROM user_tbl WHERE school_id = ? OR email = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "ss", $school_id, $email);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);

    if (mysqli_num_rows($check_result) > 0) {
        return ['success' => false, 'message' => 'School ID or email already exists'];
    }

    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    $user_sql = "INSERT INTO user_tbl (school_id, email, password, role) VALUES (?, ?, ?, 'admin')";
    $user_stmt = mysqli_prepare($conn, $user_sql);
    mysqli_stmt_bind_param($user_stmt, "sss", $school_id, $email, $hashed_password);

    if (!mysqli_stmt_execute($user_stmt)) {
        return ['success' => false, 'message' => 'Error creating user: ' . mysqli_error($conn)];
    }

    $user_id = mysqli_insert_id($conn);

    $admin_sql = "INSERT INTO admin_tbl (user_id, first_name, middle_name, last_name, email, contact_number) 
                  VALUES (?, ?, ?, ?, ?, ?)";
    $admin_stmt = mysqli_prepare($conn, $admin_sql);
    mysqli_stmt_bind_param($admin_stmt, "isssss", 
        $user_id, $first_name, $middle_name, $last_name, $email, $contact_number
    );

    if (mysqli_stmt_execute($admin_stmt)) {
        return ['success' => true, 'message' => 'Admin added successfully!'];
    } else {
        mysqli_query($conn, "DELETE FROM user_tbl WHERE user_id = $user_id");
        return ['success' => false, 'message' => 'Error adding admin: ' . mysqli_error($conn)];
    }
}

function addStudent($conn, $school_id, $email, $password, $first_name, $middle_name, $last_name, $gender, $date_of_birth, $program_id, $contact_number, $address) {
    $check_sql = "SELECT user_id FROM user_tbl WHERE school_id = ? OR email = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "ss", $school_id, $email);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);

    if (mysqli_num_rows($check_result) > 0) {
        return ['success' => false, 'message' => 'School ID or email already exists'];
    }

    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    $user_sql = "INSERT INTO user_tbl (school_id, email, password, role) VALUES (?, ?, ?, 'student')";
    $user_stmt = mysqli_prepare($conn, $user_sql);
    mysqli_stmt_bind_param($user_stmt, "sss", $school_id, $email, $hashed_password);

    if (!mysqli_stmt_execute($user_stmt)) {
        return ['success' => false, 'message' => 'Error creating user: ' . mysqli_error($conn)];
    }

    $user_id = mysqli_insert_id($conn);

    $student_sql = "INSERT INTO student_tbl 
                    (school_id, user_id, first_name, middle_name, last_name, email, gender, date_of_birth, program_id, contact_number, address) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $student_stmt = mysqli_prepare($conn, $student_sql);
    mysqli_stmt_bind_param($student_stmt, "sisssssssis", 
        $school_id, $user_id, $first_name, $middle_name, $last_name, 
        $email, $gender, $date_of_birth, $program_id, $contact_number, $address
    );

    if (mysqli_stmt_execute($student_stmt)) {
        return ['success' => true, 'message' => 'Student added successfully!'];
    } else {
        mysqli_query($conn, "DELETE FROM user_tbl WHERE user_id = $user_id");
        return ['success' => false, 'message' => 'Error adding student: ' . mysqli_error($conn)];
    }
}

function updateAdmin($conn, $user_id, $email, $first_name, $middle_name, $last_name, $contact_number) {
    $user_sql = "UPDATE user_tbl SET email = ? WHERE user_id = ?";
    $user_stmt = mysqli_prepare($conn, $user_sql);
    mysqli_stmt_bind_param($user_stmt, "si", $email, $user_id);

    if (!mysqli_stmt_execute($user_stmt)) {
        return ['success' => false, 'message' => 'Error updating user: ' . mysqli_error($conn)];
    }

    $admin_sql = "UPDATE admin_tbl SET first_name = ?, middle_name = ?, last_name = ?, email = ?, contact_number = ? WHERE user_id = ?";
    $admin_stmt = mysqli_prepare($conn, $admin_sql);
    mysqli_stmt_bind_param($admin_stmt, "sssssi", 
        $first_name, $middle_name, $last_name, $email, $contact_number, $user_id
    );

    if (mysqli_stmt_execute($admin_stmt)) {
        return ['success' => true, 'message' => 'Admin updated successfully!'];
    } else {
        return ['success' => false, 'message' => 'Error updating admin: ' . mysqli_error($conn)];
    }
}

function updateStudent($conn, $user_id, $school_id, $email, $first_name, $middle_name, $last_name, $ext_name, $gender, $date_of_birth, $program_id, $contact_number, $address, $status) {
    mysqli_begin_transaction($conn);

    $user_sql = "UPDATE user_tbl SET email = ?, school_id = ? WHERE user_id = ?";
    $user_stmt = mysqli_prepare($conn, $user_sql);
    mysqli_stmt_bind_param($user_stmt, "ssi", $email, $school_id, $user_id);

    if (!mysqli_stmt_execute($user_stmt)) {
        mysqli_rollback($conn);
        return ['success' => false, 'message' => 'Error updating user: ' . mysqli_error($conn)];
    }

    $student_sql = "UPDATE student_tbl 
                    SET school_id = ?, first_name = ?, middle_name = ?, last_name = ?, 
                        ext_name = ?, email = ?, gender = ?, date_of_birth = ?, 
                        program_id = ?, contact_number = ?, address = ?, status = ?
                    WHERE user_id = ?";

    $student_stmt = mysqli_prepare($conn, $student_sql);
    mysqli_stmt_bind_param($student_stmt,
        "ssssssssssssi",
        $school_id, $first_name, $middle_name, $last_name,
        $ext_name, $email, $gender, $date_of_birth,
        $program_id, $contact_number, $address, $status,
        $user_id
    );

    if (!mysqli_stmt_execute($student_stmt)) {
        mysqli_rollback($conn);
        return ['success' => false, 'message' => 'Error updating student: ' . mysqli_error($conn)];
    }

    mysqli_commit($conn);
    return ['success' => true, 'message' => 'Student updated successfully!'];
}

function updateUserPassword($conn, $user_id, $new_password) {
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

    $sql = "UPDATE user_tbl SET password = ? WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $hashed_password, $user_id);

    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'message' => 'Password updated successfully!'];
    } else {
        return ['success' => false, 'message' => 'Error updating password: ' . mysqli_error($conn)];
    }
}
