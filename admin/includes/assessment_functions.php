<?php

function getAssessments($conn) {
    $check_column = "SHOW COLUMNS FROM assessment_tbl LIKE 'program_id'";
    $column_result = mysqli_query($conn, $check_column);
    $has_program_id = ($column_result && mysqli_num_rows($column_result) > 0);
    
    if ($has_program_id) {
        $sql = "SELECT a.*, 
                (SELECT COUNT(*) FROM question_tbl WHERE assessment_id = a.assessment_id) as question_count,
                p.program_name
                FROM assessment_tbl a 
                LEFT JOIN program_tbl p ON a.program_id = p.program_id
                ORDER BY a.assessment_order ASC";
    } else {
        $sql = "SELECT a.*, 
                (SELECT COUNT(*) FROM question_tbl WHERE assessment_id = a.assessment_id) as question_count
                FROM assessment_tbl a 
                ORDER BY a.assessment_order ASC";
    }
    
    $result = mysqli_query($conn, $sql);
    $assessments = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $assessments[] = $row;
    }
    return $assessments;
}

function handleAssessmentDelete($conn, $id) {
    mysqli_begin_transaction($conn);
    
    try {
        $delete_results = "DELETE ar FROM assessment_results_tbl ar 
                          INNER JOIN question_tbl q ON ar.question_id = q.question_id 
                          WHERE q.assessment_id = ?";
        $stmt = mysqli_prepare($conn, $delete_results);
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error deleting assessment results: " . mysqli_error($conn));
        }
        
        $delete_questions = "DELETE FROM question_tbl WHERE assessment_id = ?";
        $stmt = mysqli_prepare($conn, $delete_questions);
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error deleting questions: " . mysqli_error($conn));
        }
        
        $delete_assessment = "DELETE FROM assessment_tbl WHERE assessment_id = ?";
        $stmt = mysqli_prepare($conn, $delete_assessment);
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error deleting assessment: " . mysqli_error($conn));
        }
        
        mysqli_commit($conn);
        return "Assessment deleted successfully!";
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return "Error: " . $e->getMessage();
    }
}

function toggleAssessmentVisibility($conn, $id) {
    $sql = "UPDATE assessment_tbl SET visibility = NOT visibility WHERE assessment_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    
    if (mysqli_stmt_execute($stmt)) {
        return "Assessment visibility updated successfully!";
    } else {
        return "Error updating assessment visibility: " . mysqli_error($conn);
    }
}
