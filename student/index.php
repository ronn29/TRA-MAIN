<?php
session_start();
require '../db/dbconn.php';

if (!isset($_SESSION['school_id'])) {
    header('Location: ../login.php');
    
    exit();
}

$school_id = $_SESSION['school_id'];

$query = "SELECT * FROM student_tbl WHERE school_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $school_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($result);
if (!$student) {
    $student = [
        'first_name' => '',
        'last_name' => '',
        'school_id' => $school_id,
        'program_id' => null
    ];
}

$student_program_id = $student['program_id'] ?? null;

$check_column = "SHOW COLUMNS FROM assessment_tbl LIKE 'program_id'";
$column_result = mysqli_query($conn, $check_column);
$has_program_id = ($column_result && mysqli_num_rows($column_result) > 0);

if ($has_program_id && $student_program_id) {
    $assessment_query = "SELECT 
        (SELECT COUNT(*) FROM question_tbl q 
         INNER JOIN assessment_tbl a ON q.assessment_id = a.assessment_id 
         WHERE a.visibility = 1 AND (a.program_id IS NULL OR a.program_id = ?)) as total_questions,
        (SELECT COUNT(*) FROM assessment_results_tbl WHERE student_id = ? AND assessment_status = 'Completed') as completed_questions,
        (SELECT COUNT(*) FROM assessment_tbl WHERE visibility = 1 AND (program_id IS NULL OR program_id = ?)) as total_assessments,
        (SELECT COUNT(DISTINCT assessment_id) FROM assessment_results_tbl WHERE student_id = ? AND assessment_status = 'Completed') as completed_assessments";
    $stmt = mysqli_prepare($conn, $assessment_query);
    mysqli_stmt_bind_param($stmt, "isis", $student_program_id, $school_id, $student_program_id, $school_id);
    mysqli_stmt_execute($stmt);
    $assessment_stats = mysqli_fetch_assoc($stmt->get_result());
} else {
    $assessment_query = "SELECT 
        (SELECT COUNT(*) FROM question_tbl q 
         INNER JOIN assessment_tbl a ON q.assessment_id = a.assessment_id 
         WHERE a.visibility = 1) as total_questions,
        (SELECT COUNT(*) FROM assessment_results_tbl WHERE student_id = ? AND assessment_status = 'Completed') as completed_questions,
        (SELECT COUNT(*) FROM assessment_tbl WHERE visibility = 1) as total_assessments,
        (SELECT COUNT(DISTINCT assessment_id) FROM assessment_results_tbl WHERE student_id = ? AND assessment_status = 'Completed') as completed_assessments";
    $stmt = mysqli_prepare($conn, $assessment_query);
    mysqli_stmt_bind_param($stmt, "ss", $school_id, $school_id);
    mysqli_stmt_execute($stmt);
    $assessment_stats = mysqli_fetch_assoc($stmt->get_result());
}



$activePage = 'index';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/student.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include_once __DIR__ . '/../partials/favicon.php'; ?>
    <title>Student Dashboard</title>

</head>
<body>
    
    <input type="checkbox" id="sidebar-toggle">
    <?php include 'sidebar.php'; ?>
    <label for="sidebar-toggle" class="sidebar-overlay"></label>

    <div class="main-content">
        <?php include 'header.php'; ?>
        
        <div class="container">
            
            <h2>
                <span class="las la-money-check"></span>
                Dashboard
            </h2>

            <h3>Welcome, <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>!</h3>
            <?php if (!$student_program_id): ?>
                <div class="alert" style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="las la-exclamation-triangle" style="font-size: 24px; color: #856404;"></i>
                        <div>
                            <strong style="color: #856404;">Complete Your Profile</strong>
                            <p style="margin: 5px 0 0 0; color: #856404;">
                                Please select your program in your profile to access all available assessments and get personalized career recommendations.
                                <a href="profile.php" style="color: #0056b3; text-decoration: underline; font-weight: 600; margin-left: 5px;">Update Profile Now</a>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="dashboard-cards">
                <div class="card">
                    <div class="card-header">
                        <h3>Assessment Progress</h3>
                        <span class="las la-clipboard-list card-icon"></span>
                    </div>
                    <div class="stat-number">
                        <?php echo $assessment_stats['completed_questions'] ?? 0; ?>/<?php echo $assessment_stats['total_questions'] ?? 0; ?>
                    </div>
                    <div class="stat-label">Completed Questions</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php 
                            echo $assessment_stats['total_questions'] > 0 
                                ? ($assessment_stats['completed_questions'] / $assessment_stats['total_questions'] * 100) 
                                : 0; ?>%"></div>
                    </div>
                    <div class="stat-number" style="margin-top: 20px;">
                        <?php echo $assessment_stats['completed_assessments'] ?? 0; ?>/<?php echo $assessment_stats['total_assessments'] ?? 0; ?>
                    </div>
                    <div class="stat-label">Completed Assessments</div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Career Tip of the Day</h3>
                        <span class="las la-lightbulb card-icon"></span>
                    </div>
                    <div id="career-tip" class="tip-box">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const tips = [
            "Your career is a marathon, not a sprint.",
            "Explore your passions — they often lead to purpose.",
            "Don't be afraid to start small.",
            "Keep learning, always.",
            "Talk to someone in the field you're interested in.",
            "Failure is a stepping stone to success.",
            "Build your network early.",
            "Ask questions — curiosity leads to growth.",
            "Your soft skills are just as important as technical ones.",
            "A clear goal makes your path easier to plan."
        ];

        const today = new Date().getDate();
        const tipIndex = (today - 1) % tips.length;
        document.getElementById('career-tip').textContent = tips[tipIndex];
    </script>
</body>
</html>