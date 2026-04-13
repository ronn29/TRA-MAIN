<?php

function getDepartments($conn) {
    $sql = "SELECT department_id, department_code, department_name 
            FROM department_tbl 
            ORDER BY department_id ASC";
    $result = mysqli_query($conn, $sql);
    $departments = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $departments[] = $row;
    }

    return $departments;
}

function handleDepartmentDelete($conn, $id) {
    $delete = "DELETE FROM department_tbl WHERE department_id = $id";
    if (mysqli_query($conn, $delete)) {
        return "Department deleted successfully!";
    } else {
        return "Error deleting department: " . mysqli_error($conn);
    }
}
