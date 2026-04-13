<?php
session_start();
if (!isset($_SESSION['school_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit;
}

require '../db/dbconn.php';

$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

$student_id = $_SESSION['school_id'];
$assessment_id = isset($_POST['assessment_id']) ? intval($_POST['assessment_id']) : 0;
$answers = isset($_POST['answers']) ? $_POST['answers'] : [];

if (!$assessment_id || empty($answers)) {
    $_SESSION['message'] = "Error: Missing assessment data. Please try again.";
    header("Location: assessment_test.php");
    exit;
}

$answer_map = [
    'Yes' => 1,
    'No' => 0,
    'Prefer not to say' => 2
];

mysqli_begin_transaction($conn);

try {
    $delete_sql = "DELETE FROM assessment_results_tbl WHERE student_id = ? AND assessment_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("si", $student_id, $assessment_id);
    $delete_stmt->execute();
    
    $stmt = $conn->prepare("INSERT INTO assessment_results_tbl (student_id, assessment_id, question_id, answer, assessment_status) VALUES (?, ?, ?, ?, 'Completed')");
    
    $score = 0;
    $total_questions = count($answers);
    
    foreach ($answers as $question_id => $answer) {
        if (!isset($answer_map[$answer])) {
            throw new Exception("Invalid answer value for question ID $question_id.");
        }

        $numeric_answer = $answer_map[$answer];
        
        if ($numeric_answer === 1) {
            $score++;
        }

        $stmt->bind_param("siii", $student_id, $assessment_id, $question_id, $numeric_answer);
        $stmt->execute();
    }
    
    $score_sql = "INSERT INTO assessment_scores (student_id, assessment_id, score, total_questions, date_taken) 
                 VALUES (?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE 
                 score = VALUES(score), 
                 total_questions = VALUES(total_questions), 
                 date_taken = NOW()";
                 
    $score_stmt = $conn->prepare($score_sql);
    
    $score_stmt->bind_param("siii", $student_id, $assessment_id, $score, $total_questions);
    $score_stmt->execute();

    mysqli_commit($conn);
    $_SESSION['message'] = "Assessment submitted successfully!";
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    $error_message = "Error submitting assessment: " . $e->getMessage();
    $_SESSION['message'] = $error_message;
}

header("Location: assessment_test.php");
exit;
?>
