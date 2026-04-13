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

// Fetch assessment details if assessment_id is provided
$assessment_id = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : null;
$assessment_name = '';

if ($assessment_id) {
    $sql = "SELECT assessment_name FROM assessment_tbl WHERE assessment_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $assessment_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $assessment_name = $row['assessment_name'];
        
        $update_sql = "UPDATE assessment_results_tbl SET assessment_status = 'In Progress' 
                      WHERE student_id = ? AND assessment_id = ?";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, "si", $student_id, $assessment_id);
        mysqli_stmt_execute($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include_once __DIR__ . '/../partials/favicon.php'; ?>
    <title>Take Assessment</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/datatables.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <style>
        .assessment-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 20px;
        }
        
        .question-container {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .question-container label:first-child {
            display: block;
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 15px;
            color: #333;
        }
        
        .radio-group {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-top: 10px;
        }
        
        .radio-group input[type="radio"] {
            margin: 0;
            cursor: pointer;
            width: 18px;
            height: 18px;
        }
        
        .radio-group label {
            margin: 0;
            cursor: pointer;
            font-weight: normal;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
        }
        
        .btn-primary:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <input type="checkbox" id="sidebar-toggle">

    <div class="main-content">
        <?php include 'header.php'; ?>
        <h2>
            <span class="las la-clipboard-list"></span>
            Take Assessment
        </h2>
        <div class="container">
            <?php if ($message): ?>
                <div class="message <?php echo strpos($message, 'Error') !== false ? 'error' : ''; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="assessment-card">
                <h2><?php echo htmlspecialchars($assessment_name); ?></h2>
                <p>Please answer the following questions carefully.</p>
                <?php if ($assessment_id == 50): ?>
                    <div class="program-notice" style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 12px; margin: 15px 0; border-radius: 4px;">
                        <strong>Aptitude Assessment:</strong> This test evaluates your technical aptitude, problem-solving skills, and general capabilities. Please answer all questions honestly.
                    </div>
                <?php endif; ?>

            <form action="submit_assessment.php" method="post">
                <input type="hidden" name="assessment_id" value="<?php echo htmlspecialchars($assessment_id); ?>">
                <?php
                $questions_sql = "SELECT * FROM question_tbl WHERE assessment_id = ? ORDER BY question_id";
                $stmt = mysqli_prepare($conn, $questions_sql);
                mysqli_stmt_bind_param($stmt, "i", $assessment_id);
                mysqli_stmt_execute($stmt);
                $questions_result = mysqli_stmt_get_result($stmt);
                
                $question_count = 0;
                while ($question = mysqli_fetch_assoc($questions_result)) {
                    $question_count++;
                    echo '<div class="question-container">';
                    echo '<label for="question_' . $question['question_id'] . '">' . $question_count . '. ' . htmlspecialchars($question['question_text']) . '</label>';
                    echo '<div class="radio-group">';
                    echo '<div class="radio-option">';
                    echo '<input type="radio" id="yes_' . $question['question_id'] . '" name="answers[' . $question['question_id'] . ']" value="Yes" required>';
                    echo '<label for="yes_' . $question['question_id'] . '">Yes</label>';
                    echo '</div>';
                    echo '<div class="radio-option">';
                    echo '<input type="radio" id="no_' . $question['question_id'] . '" name="answers[' . $question['question_id'] . ']" value="No" required>';
                    echo '<label for="no_' . $question['question_id'] . '">No</label>';
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                }
                
                if ($question_count == 0): ?>
                    <div class="alert alert-warning">
                        <strong>No Questions Available:</strong> There are no questions available for this assessment. Please contact your administrator.
                    </div>
                <?php endif; ?>
                
                <?php if ($question_count > 0): ?>
                    <button type="submit" class="btn btn-primary">Submit Assessment</button>
                <?php endif; ?>
            </form>
            </div>
        </div>
    </div>

    <?php 
    $activePage = 'take_assessment';
    include 'sidebar.php'; 
    ?>
    <label for="sidebar-toggle" class="sidebar-overlay"></label>
</body>
</html>