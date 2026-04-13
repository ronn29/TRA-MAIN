<?php
session_start();
if (!isset($_SESSION['school_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit;
}

require '../db/dbconn.php';
require '../admin/includes/model_predict.php';

$student_id = $_SESSION['school_id'];

$student_program_sql = "SELECT s.program_id, p.department_id 
                        FROM student_tbl s 
                        LEFT JOIN program_tbl p ON s.program_id = p.program_id 
                        WHERE s.school_id = ?";
$stmt = mysqli_prepare($conn, $student_program_sql);
mysqli_stmt_bind_param($stmt, "s", $student_id);
mysqli_stmt_execute($stmt);
$student_result = mysqli_stmt_get_result($stmt);
$student_data = mysqli_fetch_assoc($student_result);
$student_program_id = $student_data['program_id'] ?? null;
$student_department_id = $student_data['department_id'] ?? null;

$check_column = "SHOW COLUMNS FROM assessment_tbl LIKE 'program_id'";
$column_result = mysqli_query($conn, $check_column);
$has_program_id = ($column_result && mysqli_num_rows($column_result) > 0);

$check_dept_column = "SHOW COLUMNS FROM assessment_tbl LIKE 'department_id'";
$dept_column_result = mysqli_query($conn, $check_dept_column);
$has_department_id = ($dept_column_result && mysqli_num_rows($dept_column_result) > 0);

$visible_result = null;
if ($has_program_id && $student_program_id) {
    if ($has_department_id && $student_department_id) {
        $sql = "SELECT a.assessment_id, a.assessment_name, a.assessment_order
                FROM assessment_tbl a 
                WHERE a.visibility = 1 
                  AND (
                      a.program_id = ?
                      OR (a.program_id IS NULL AND a.department_id = ?)
                  )
                ORDER BY a.assessment_order ASC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $student_program_id, $student_department_id);
        mysqli_stmt_execute($stmt);
        $visible_result = mysqli_stmt_get_result($stmt);
    } else {
        $sql = "SELECT a.assessment_id, a.assessment_name, a.assessment_order
                FROM assessment_tbl a 
                WHERE a.visibility = 1 
                  AND a.program_id = ?
                ORDER BY a.assessment_order ASC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $student_program_id);
        mysqli_stmt_execute($stmt);
        $visible_result = mysqli_stmt_get_result($stmt);
    }
} else {
    $visible_result = null;
}

$assessments = [];
if ($visible_result) {
    while ($row = mysqli_fetch_assoc($visible_result)) {
        $assessments[] = $row;
    }
}

$visibleAssessmentIds = array_column($assessments, 'assessment_id');
$total_count = count($visibleAssessmentIds);

$completed_assessments = [];
$completed_count = 0;
if ($total_count > 0) {
    $placeholders = implode(',', array_fill(0, $total_count, '?'));
    $types = str_repeat('i', $total_count);
    $sql = "SELECT DISTINCT a.assessment_id, a.assessment_name, a.assessment_order
            FROM assessment_tbl a 
            INNER JOIN assessment_results_tbl r ON a.assessment_id = r.assessment_id 
            WHERE r.student_id = ?
              AND r.assessment_status = 'Completed'
              AND a.assessment_id IN ($placeholders)
            ORDER BY a.assessment_order";
    $stmt = mysqli_prepare($conn, $sql);
    $params = array_merge([$student_id], $visibleAssessmentIds);
    mysqli_stmt_bind_param($stmt, 's' . $types, ...$params);
    mysqli_stmt_execute($stmt);
    $assessments_result = mysqli_stmt_get_result($stmt);
    while ($assessment = mysqli_fetch_assoc($assessments_result)) {
        $completed_assessments[] = $assessment;
    }

    $count_sql = "SELECT COUNT(DISTINCT assessment_id) as assessment_count 
                  FROM assessment_results_tbl 
                  WHERE student_id = ? 
                    AND assessment_status = 'Completed'
                    AND assessment_id IN ($placeholders)";
    $stmt = mysqli_prepare($conn, $count_sql);
    mysqli_stmt_bind_param($stmt, 's' . $types, ...$params);
    mysqli_stmt_execute($stmt);
    $count_result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($count_result);
    $completed_count = $row['assessment_count'] ?? 0;
}

$has_completed_assessments = ($total_count > 0 && $completed_count >= $total_count);

$student_sql = "SELECT first_name, last_name FROM student_tbl WHERE school_id = ?";
$stmt = mysqli_prepare($conn, $student_sql);
mysqli_stmt_bind_param($stmt, "s", $student_id);
mysqli_stmt_execute($stmt);
$student_result = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($student_result);

$career_prediction = null;
$assessment_scores = [];
$descriptions = [];

if ($has_completed_assessments) {
    $all_answers = [];
    $assessment_scores = [];
    $total_yes_answers = 0;
    $total_answers = 0;
    
    foreach ($completed_assessments as $assessment) {
        $assessment_id = $assessment['assessment_id'];
        
        $answers = getStudentAssessmentResults($conn, $assessment_id, $student_id);
        
        foreach ($answers as $question_id => $answer) {
            $all_answers["q_{$assessment_id}_{$question_id}"] = $answer;
            $total_answers++;
            if ($answer === 1) {
                $total_yes_answers++;
            }
        }
        
        $score_sql = "SELECT score, total_questions FROM assessment_scores 
                     WHERE student_id = ? AND assessment_id = ?";
        $stmt = mysqli_prepare($conn, $score_sql);
        mysqli_stmt_bind_param($stmt, "si", $student_id, $assessment_id);
        mysqli_stmt_execute($stmt);
        $score_result = mysqli_stmt_get_result($stmt);
        
        if ($score_row = mysqli_fetch_assoc($score_result)) {
            $assessment_scores[] = [
                'name' => $assessment['assessment_name'],
                'score' => $score_row['score'],
                'total' => $score_row['total_questions'],
                'percentage' => ($score_row['score'] / $score_row['total_questions']) * 100
            ];
        }
    }
    
    $total_score = array_sum(array_column($assessment_scores, 'score'));
    $total_questions = array_sum(array_column($assessment_scores, 'total'));
    $overall_percentage = $total_questions > 0 ? ($total_score / $total_questions) * 100 : 0;
    $yes_percentage = $total_answers > 0 ? ($total_yes_answers / $total_answers) * 100 : 0;
    
    $career_prediction = predictCareerWithRandomForest($conn, $student_id);
    $descriptions = $career_prediction['career_descriptions'] ?? [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include_once __DIR__ . '/../partials/favicon.php'; ?>
    <title>Career Prediction</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/datatables.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <style>
        .job-title-tooltip {
            position: relative;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .job-title-tooltip::after {
            content: attr(data-desc);
            position: absolute;
            left: 50%;
            top: 115%;
            transform: translateX(-50%) translateY(6px);
            background: rgba(0, 0, 0, 0.85);
            color: #fff;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            line-height: 1.4;
            width: min(280px, calc(100vw - 40px));
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.15s ease, transform 0.15s ease;
            z-index: 10;
            text-align: center;
        }
        .job-title-tooltip[data-desc=""]::after {
            display: none;
        }
        .job-title-tooltip:hover::after {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
        
        .job-title-tooltip.active::after {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
        
        @media (hover: none) and (pointer: coarse) {
            .job-title-tooltip {
                padding: 5px;
                margin: -5px;
            }
            
            .job-title-tooltip::after {
                pointer-events: auto;
            }
        }
        
        .resource-link:hover {
            background: #fff !important;
            border-color: #007bff !important;
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.15) !important;
            transform: translateY(-2px);
        }
        
        .career-resource-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.1) !important;
        }
        
        .resource-link,
        .career-resource-card {
            transition: all 0.2s ease;
        }
        
        @media screen and (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .back-button {
                margin-bottom: 15px;
            }
            
            .back-button .btn {
                width: 100%;
                justify-content: center;
                padding: 12px 16px;
            }
            
            .no-assessments {
                padding: 20px;
            }
            
            .no-assessments i {
                font-size: 3rem;
            }
            
            .no-assessments h3 {
                font-size: 1.3rem;
            }
            
            .prediction-header h2 {
                font-size: 1.5rem;
            }
            
            .prediction-header p {
                font-size: 0.9rem;
            }
            
            .likelihood-grid {
                grid-template-columns: 1fr !important;
                gap: 15px !important;
            }
            
            .likelihood-card {
                padding: 20px !important;
            }
            
            .likelihood-icon i {
                font-size: 2rem !important;
                padding: 12px !important;
            }
            
            .likelihood-card h4 {
                font-size: 1rem !important;
            }
            
            .likelihood-card .job-title {
                font-size: 1.1rem !important;
            }
            
            .likelihood-card .job-description {
                font-size: 0.85rem !important;
            }
            
            .career-list .career-item {
                padding: 12px 10px !important;
                font-size: 0.9rem;
            }
            
            .resources-grid {
                grid-template-columns: 1fr !important;
                gap: 15px !important;
            }
            
            .career-resource-card {
                padding: 15px !important;
            }
            
            .career-resource-card h5 {
                font-size: 1rem !important;
            }
            
            .resource-link {
                padding: 8px 10px !important;
                font-size: 0.85rem !important;
            }
            
            .career-disclaimer {
                font-size: 14px !important;
                padding: 15px;
            }
            
            .job-title-tooltip::after {
                width: calc(100vw - 60px);
                left: 50%;
                font-size: 0.8rem;
                padding: 8px 10px;
            }
        }
        
        @media screen and (max-width: 480px) {
            .container {
                padding: 10px;
            }
            
            .prediction-header h2 {
                font-size: 1.3rem;
            }
            
            .prediction-header p {
                font-size: 0.85rem;
            }
            
            .likelihood-card {
                padding: 15px !important;
            }
            
            .likelihood-icon i {
                font-size: 1.8rem !important;
                padding: 10px !important;
            }
            
            .likelihood-card h4 {
                font-size: 0.95rem !important;
                margin-bottom: 10px !important;
            }
            
            .likelihood-card .job-title {
                font-size: 1rem !important;
            }
            
            .likelihood-card .job-description {
                font-size: 0.8rem !important;
            }
            
            .career-section h4 {
                font-size: 1rem;
            }
            
            .career-item {
                padding: 10px !important;
                font-size: 0.85rem !important;
            }
            
            .career-resource-card {
                padding: 12px !important;
            }
            
            .career-resource-card h5 {
                font-size: 0.95rem !important;
            }
            
            .resource-link {
                padding: 8px !important;
                font-size: 0.8rem !important;
            }
            
            .resource-link i {
                font-size: 12px !important;
            }
            
            .career-disclaimer {
                font-size: 12px !important;
                padding: 12px;
                line-height: 1.5;
            }
            
            .las.la-info-circle {
                font-size: 0.85rem !important;
            }
        }
    </style>

</head>
<body>
    <input type="checkbox" id="sidebar-toggle">
    
    <div class="main-content">
        <?php include 'header.php'; ?>
        
        <div class="container">
            <div class="back-button">
                <a href="assessment_test.php" class="btn btn-secondary">
                    <i class="las la-arrow-left"></i> Back to Assessments
                </a>
            </div>
            
                         <?php if (!$has_completed_assessments): ?>
                 <div class="no-assessments">
                     <i class="las la-clipboard-list"></i>
                     <h3>Complete All Assessments First</h3>
                     <p>You need to complete all <?php echo $total_count; ?> assessments to get your comprehensive career prediction. This ensures we have a complete profile of your aptitude, personality, and interests.</p>
                     <a href="assessment_test.php">Go to Assessments</a>
                 </div>
            <?php elseif (!$career_prediction || isset($career_prediction['error'])): ?>
                <div class="no-assessments">
                    <i class="las la-robot"></i>
                    <h3>We couldn't generate a prediction</h3>
                    <?php if (isset($career_prediction['error']) && $career_prediction['error'] === 'no_allowed_careers_match'): ?>
                        <p>No recommended careers match your program yet. Please contact your administrator to add careers for your program.</p>
                    <?php else: ?>
                        <p>Our model couldn't produce a recommendation right now. Please try again later or contact support.</p>
                    <?php endif; ?>
                    <?php if (isset($career_prediction['debug'])): ?>
                        <div style="margin-top:10px; font-size: 12px; color:#6c757d;">
                            Debug info: <?php echo htmlspecialchars(json_encode($career_prediction['debug'])); ?>
                        </div>
                    <?php endif; ?>
                    <a href="assessment_test.php">Back to Assessments</a>
                </div>
            <?php else: ?>
                <div class="prediction-container">
                    <div class="prediction-header">
                        <h2>Your Career Recommendation</h2>
                        <p>Based on all your assessment results, we've analyzed your responses to generate a personalized career recommendation.</p>
                        <?php if (!empty($career_prediction['model_debug']['metrics']['accuracy'])): ?>
                        <p style="margin-top: 5px; color:#6c757d; font-size: 14px;">
                            Model accuracy: <?php echo number_format($career_prediction['model_debug']['metrics']['accuracy'] * 100, 2); ?>%
                        </p>
                        <?php endif; ?>
                    </div>
                    
                    
                                         <div class="career-section">
                         <div class="career-header">
                             <h3><i class="las la-briefcase"></i> Compiled Career Recommendation</h3>
                         </div>
                         
                         
                         <div class="likelihood-section" style="margin: 30px 0;">
                             <div class="likelihood-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                                <div class="likelihood-card most-likely" style="background: #fff; border-radius: 12px; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center; border-left: 4px solid #28a745;">
                                     <div class="likelihood-icon" style="margin-bottom: 15px;">
                                         <i class="las la-thumbs-up" style="font-size: 2.5rem; padding: 15px; border-radius: 50%; background-color: #d4edda; color: #28a745;"></i>
                                     </div>
                                     <h4 style="margin: 0 0 15px 0; color: #333; font-size: 1.1rem; font-weight: 600;">Most Likely Job</h4>
                                    <p class="job-title" style="font-size: 1.3rem; font-weight: bold; margin: 0 0 10px 0; color: #007bff;">
                                        <?php 
                                            $mlDesc = $career_prediction['career_descriptions'][$career_prediction['most_likely']] ?? '';
                                            $mlTooltip = trim($mlDesc) !== '' ? $mlDesc : 'No description available.';
                                        ?>
                                        <span class="job-title-tooltip" data-desc="<?php echo htmlspecialchars($mlTooltip, ENT_QUOTES); ?>">
                                            <?php echo htmlspecialchars($career_prediction['most_likely'] ?? 'Not available'); ?>
                                            <i class="las la-info-circle" style="font-size: 0.95rem; color: #007bff;"></i>
                                        </span>
                                    </p>
                                     <p class="job-description" style="font-size: 0.9rem; color: #6c757d; line-height: 1.5; margin: 0;">Based on your compiled assessment responses, this career path aligns best with your overall strengths and preferences.</p>
                                 </div>
                                 
                                 <div class="likelihood-card least-likely" style="background: #fff; border-radius: 12px; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center; border-left: 4px solid #dc3545;">
                                     <div class="likelihood-icon" style="margin-bottom: 15px;">
                                         <i class="las la-thumbs-down" style="font-size: 2.5rem; padding: 15px; border-radius: 50%; background-color: #f8d7da; color: #dc3545;"></i>
                                     </div>
                                     <h4 style="margin: 0 0 15px 0; color: #333; font-size: 1.1rem; font-weight: 600;">Least Likely Job</h4>
                                    <p class="job-title" style="font-size: 1.3rem; font-weight: bold; margin: 0 0 10px 0; color: #007bff;">
                                        <?php 
                                            $llDesc = $career_prediction['career_descriptions'][$career_prediction['least_likely']] ?? '';
                                            $llTooltip = trim($llDesc) !== '' ? $llDesc : 'No description available.';
                                        ?>
                                        <span class="job-title-tooltip" data-desc="<?php echo htmlspecialchars($llTooltip, ENT_QUOTES); ?>">
                                            <?php echo htmlspecialchars($career_prediction['least_likely'] ?? 'Not available'); ?>
                                            <i class="las la-info-circle" style="font-size: 0.95rem; color: #007bff;"></i>
                                        </span>
                                    </p>
                                     <p class="job-description" style="font-size: 0.9rem; color: #6c757d; line-height: 1.5; margin: 0;">This career path may not align well with your compiled assessment profile and preferences.</p>
                                 </div>
                             </div>
                         </div>
                         
                        <h4>Top 3 Suggested Career Paths:</h4>
                        <div class="career-list">
                            <?php 
                            $topCareers = array_slice($career_prediction['careers'], 0, 3);
                            foreach ($topCareers as $careerOption): 
                                $desc = $descriptions[$careerOption] ?? '';
                                $tooltip = trim($desc) !== '' ? $desc : 'No description available.';
                            ?>
                                <div class="career-item" style="display:block; margin-bottom:12px; background:#f8f9fa; padding:10px 12px; border-radius:8px; border:1px solid #e9ecef;">
                                    <div style="font-weight:600; color:#007bff;">
                                        <span class="job-title-tooltip" data-desc="<?php echo htmlspecialchars($tooltip, ENT_QUOTES); ?>">
                                            <?php echo htmlspecialchars($careerOption); ?>
                                            <i class="las la-info-circle" style="font-size: 0.9rem; color: #007bff;"></i>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                         
                         <?php if (!empty($career_prediction['resources'])): ?>
                         <div class="career-resources" style="margin-top: 40px;">
                             <h4 style="color: #333; margin-bottom: 20px; font-size: 1.2rem;">
                                 <i class="las la-link"></i> Career Development Resources
                             </h4>
                             <p style="color: #666; margin-bottom: 25px; font-size: 0.95rem;">
                                 Explore these curated resources to develop skills for your recommended careers. 
                                 <span style="display: inline-flex; align-items: center; gap: 10px; margin-left: 15px;">
                                     <span style="padding: 3px 8px; background: #d4edda; color: #155724; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">
                                         <i class="las la-check-circle"></i> Free
                                     </span>
                                     <span style="padding: 3px 8px; background: #fff3cd; color: #856404; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">
                                         <i class="las la-certificate"></i> Certification
                                     </span>
                                 </span>
                             </p>
                             
                             <div class="resources-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px;">
                                 <?php foreach ($career_prediction['resources'] as $career => $resources): ?>
                                     <div class="career-resource-card" style="background: #fff; border: 1px solid #e9ecef; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); transition: transform 0.2s ease, box-shadow 0.2s ease;">
                                         <h5 style="color: #007bff; margin: 0 0 15px 0; font-size: 1.1rem; font-weight: 600; border-bottom: 2px solid #e9f5ff; padding-bottom: 8px;">
                                             <i class="las la-briefcase"></i> <?php echo htmlspecialchars($career); ?>
                                         </h5>
                                         <div class="resource-links" style="display: flex; flex-direction: column; gap: 8px;">
                                             <?php 
                                             $count = 0;
                                             $maxDisplay = 6;
                                             foreach ($resources as $resourceName => $resourceUrl): 
                                                 if ($count >= $maxDisplay) break;
                                                 $count++;
                                                 
                                                 $displayName = $resourceName;
                                                 $badges = [];
                                                 if (preg_match('/\((.*?)\)$/', $resourceName, $matches)) {
                                                     $displayName = trim(preg_replace('/\((.*?)\)$/', '', $resourceName));
                                                     $badges = array_map('trim', explode('•', $matches[1]));
                                                 }
                                             ?>
                                                 <a href="<?php echo htmlspecialchars($resourceUrl); ?>" 
                                                    target="_blank" 
                                                    rel="noopener noreferrer"
                                                    class="resource-link" 
                                                    style="display: flex; flex-direction: column; padding: 12px; background: #f8f9fa; border-radius: 8px; text-decoration: none; color: #495057; transition: all 0.2s ease; border: 1px solid #e9ecef;">
                                                     <div style="display: flex; align-items: flex-start; gap: 8px;">
                                                         <i class="las la-external-link-alt" style="margin-top: 2px; color: #007bff; font-size: 14px; flex-shrink: 0;"></i>
                                                         <div style="flex: 1;">
                                                             <div style="font-size: 0.9rem; font-weight: 500; line-height: 1.4; margin-bottom: 4px;">
                                                                 <?php echo htmlspecialchars($displayName); ?>
                                                             </div>
                                                             <?php if (!empty($badges)): ?>
                                                                 <div style="display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px;">
                                                                     <?php foreach ($badges as $badge): ?>
                                                                         <?php 
                                                                         $badgeLower = strtolower($badge);
                                                                         $badgeColor = '#6c757d';
                                                                         $badgeBg = '#e9ecef';
                                                                         $badgeIcon = 'la-tag';
                                                                         
                                                                         if ($badgeLower === 'free') {
                                                                             $badgeColor = '#155724';
                                                                             $badgeBg = '#d4edda';
                                                                             $badgeIcon = 'la-gift';
                                                                         } elseif ($badgeLower === 'certification') {
                                                                             $badgeColor = '#856404';
                                                                             $badgeBg = '#fff3cd';
                                                                             $badgeIcon = 'la-certificate';
                                                                         } elseif (in_array($badgeLower, ['google', 'microsoft', 'aws', 'ibm', 'oracle', 'cisco'])) {
                                                                             $badgeColor = '#004085';
                                                                             $badgeBg = '#cce5ff';
                                                                             $badgeIcon = 'la-building';
                                                                         }
                                                                         ?>
                                                                         <span style="padding: 2px 6px; background: <?php echo $badgeBg; ?>; color: <?php echo $badgeColor; ?>; border-radius: 3px; font-size: 0.7rem; font-weight: 600; display: inline-flex; align-items: center; gap: 3px;">
                                                                             <i class="las <?php echo $badgeIcon; ?>"></i>
                                                                             <?php echo htmlspecialchars($badge); ?>
                                                                         </span>
                                                                     <?php endforeach; ?>
                                                                 </div>
                                                             <?php endif; ?>
                                                         </div>
                                                         <i class="las la-arrow-right" style="color: #6c757d; font-size: 12px; margin-top: 2px; flex-shrink: 0;"></i>
                                                     </div>
                                                 </a>
                                             <?php endforeach; ?>
                                             <?php if (count($resources) > $maxDisplay): ?>
                                                 <div style="text-align: center; padding: 8px; color: #6c757d; font-size: 0.85rem; font-style: italic;">
                                                     +<?php echo count($resources) - $maxDisplay; ?> more resources available
                                                 </div>
                                             <?php endif; ?>
                                         </div>
                                     </div>
                                 <?php endforeach; ?>
                             </div>
                             
                             <div class="resources-note" style="margin-top: 20px; padding: 15px; background: #e3f2fd; border-radius: 8px; border-left: 4px solid #2196f3;">
                                 <p style="margin: 0; color: #1976d2; font-size: 0.9rem;">
                                     <i class="las la-info-circle"></i> 
                                     <strong>Tip:</strong> Start with free resources and certifications to build foundational skills. Prioritize resources from reputable providers like Google, Microsoft, Coursera, and edX.
                                 </p>
                             </div>
                         </div>
                         <?php endif; ?>
                         
                         <div class="career-disclaimer" style="font-size: 16px;">
                             <strong>Important:</strong> This compiled recommendation is based on analyzing all your assessment responses together 
                             to provide a comprehensive career guidance. The system considers your aptitude, personality, interests and program specific assessment
                             as a unified profile rather than separate results. Your actual career path should consider your 
                             interests, skills, education, personal goals, and consultation with career counselors.
                         </div>
                     </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php 
    $activePage = 'assessment_test';
    include 'sidebar.php'; 
    ?>
    <label for="sidebar-toggle" class="sidebar-overlay"></label>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tooltips = document.querySelectorAll('.job-title-tooltip');
            
            const isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
            
            if (isTouchDevice) {
                tooltips.forEach(function(tooltip) {
                    tooltip.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        tooltips.forEach(function(t) {
                            if (t !== tooltip) {
                                t.classList.remove('active');
                            }
                        });
                        
                        tooltip.classList.toggle('active');
                    });
                });
                
                document.addEventListener('click', function(e) {
                    if (!e.target.closest('.job-title-tooltip')) {
                        tooltips.forEach(function(t) {
                            t.classList.remove('active');
                        });
                    }
                });
            }
        });
    </script>
</body>
</html> 