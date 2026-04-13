<?php

function getPrograms($conn) {
    $sql = "SELECT p.*, d.department_name 
            FROM program_tbl p
            LEFT JOIN department_tbl d ON p.department_id = d.department_id
            ORDER BY p.program_id ASC";
    $result = mysqli_query($conn, $sql);

    $programs = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $programs[] = $row;
    }
    return $programs;
}

function handleProgramDelete($conn, $id) {
    $delete = "DELETE FROM program_tbl WHERE program_id = $id";
    if (mysqli_query($conn, $delete)) {
        return "Program deleted successfully!";
    } else {
        return "Error deleting program: " . mysqli_error($conn);
    }
}
