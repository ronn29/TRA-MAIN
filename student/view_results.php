<?php
session_start();
if (!isset($_SESSION['school_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit;
}

require '../db/dbconn.php';
require '../admin/includes/model_predict.php';

$assessment_id = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : 0;
$student_id = $_SESSION['school_id'];

$assessment_sql = "SELECT * FROM assessment_tbl WHERE assessment_id = ?";
$stmt = mysqli_prepare($conn, $assessment_sql);
mysqli_stmt_bind_param($stmt, "i", $assessment_id);
mysqli_stmt_execute($stmt);
$assessment_result = mysqli_stmt_get_result($stmt);
$assessment = mysqli_fetch_assoc($assessment_result);

if (!$assessment) {
    $_SESSION['message'] = "Assessment not found!";
    header("Location: assessment_test.php");
    exit;
}

$results_sql = "SELECT * FROM assessment_scores 
                WHERE student_id = ? AND assessment_id = ?";
$stmt = mysqli_prepare($conn, $results_sql);
mysqli_stmt_bind_param($stmt, "si", $student_id, $assessment_id);
mysqli_stmt_execute($stmt);
$results = mysqli_stmt_get_result($stmt);
$result = mysqli_fetch_assoc($results);

if (!$result) {
    $_SESSION['message'] = "No results found for this assessment!";
    header("Location: assessment_test.php");
    exit;
}

$percentage = ($result['score'] / $result['total_questions']) * 100;

$career = predictCareerWithRandomForest($conn, $student_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include_once __DIR__ . '/../partials/favicon.php'; ?>
    <title>View Results</title>
    <link rel="stylesheet" href="../admin/admin.css">
    <link rel="stylesheet" href="../admin/css/assessment.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <style>
        .result-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        .result-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .result-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-box {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-box h3 {
            margin: 0;
            color: #6c757d;
            font-size: 14px;
        }
        .stat-box p {
            margin: 10px 0 0;
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        .back-button {
            margin-top: 20px;
        }
        .career-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        .career-header {
            margin-bottom: 20px;
        }
        .career-description {
            margin-bottom: 25px;
            color: #495057;
            line-height: 1.6;
        }
        .career-list {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        .career-item {
            background-color: #e9f5ff;
            color: #0056b3;
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 14px;
            display: inline-block;
        }
        .career-disclaimer {
            font-size: 12px;
            color: #6c757d;
            font-style: italic;
            margin-top: 20px;
        }
        
        .likelihood-section {
            margin: 30px 0;
        }
        
        .likelihood-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .likelihood-card {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .likelihood-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        .likelihood-card.most-likely {
            border-left: 4px solid #28a745;
        }
        
        .likelihood-card.least-likely {
            border-left: 4px solid #dc3545;
        }
        
        .likelihood-icon {
            margin-bottom: 15px;
        }
        
        .likelihood-icon i {
            font-size: 2.5rem;
            padding: 15px;
            border-radius: 50%;
        }
        
        .most-likely .likelihood-icon i {
            background-color: #d4edda;
            color: #28a745;
        }
        
        .least-likely .likelihood-icon i {
            background-color: #f8d7da;
            color: #dc3545;
        }
        
        .likelihood-card h4 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .job-title {
            font-size: 1.3rem;
            font-weight: bold;
            margin: 0 0 10px 0;
            color: #007bff;
        }
        
        .job-description {
            font-size: 0.9rem;
            color: #6c757d;
            line-height: 1.5;
            margin: 0;
        }
        
        @media (max-width: 768px) {
            .likelihood-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .likelihood-card {
                padding: 20px;
            }
            
            .likelihood-icon i {
                font-size: 2rem;
                padding: 12px;
            }
            
            .job-title {
                font-size: 1.1rem;
            }
        }
        
        .feedback-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        .resource-link:hover {
            background: #fff !important;
            border-color: #007bff !important;
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.15) !important;
            transform: translateY(-2px);
        }
        
        .resource-link:hover .las.la-external-link-alt {
            color: #0056b3 !important;
        }
        
        .career-resource-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.1) !important;
        }
        
        .resource-link,
        .career-resource-card {
            transition: all 0.2s ease;
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

            <div class="result-container">
                <div class="result-header">
                    <h2><?php echo htmlspecialchars($assessment['assessment_name']); ?> - Results</h2>
                </div>

                <div class="result-stats">
                    <div class="stat-box">
                        <h3>Score</h3>
                        <p><?php echo $result['score']; ?>/<?php echo $result['total_questions']; ?></p>
                    </div>
                    <div class="stat-box">
                        <h3>Percentage</h3>
                        <p><?php echo number_format($percentage, 1); ?>%</p>
                    </div>
                    <div class="stat-box">
                        <h3>Date Taken</h3>
                        <p><?php echo date('M d, Y', strtotime($result['date_taken'])); ?></p>
                    </div>
                </div>

                <?php if (!$career || isset($career['error'])): ?>
                    <div class="career-section">
                        <div class="career-header">
                            <h3><i class="las la-briefcase"></i> Career Recommendation</h3>
                        </div>
                        <div class="career-description" style="color: #dc3545;">
                            We couldn't generate a recommendation right now. Please try again later.
                        </div>
                        <?php if (isset($career['debug'])): ?>
                        <div style="margin-top:10px; font-size: 12px; color:#6c757d;">
                            Debug info: <?php echo htmlspecialchars(json_encode($career['debug'])); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="career-section">
                        <div class="career-header">
                            <h3><i class="las la-briefcase"></i> Career Recommendation</h3>
                            <?php if (!empty($career['model_debug']['metrics']['accuracy'])): ?>
                                <div style="margin-top:6px; color:#6c757d; font-size: 14px;">
                                    Model accuracy: <?php echo number_format($career['model_debug']['metrics']['accuracy'] * 100, 2); ?>%
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <h4><?php echo htmlspecialchars($career['title']); ?></h4>
                        <div class="career-description">
                            <?php echo htmlspecialchars($career['description']); ?>
                        </div>
                        
                        <div class="likelihood-section">
                            <div class="likelihood-grid">
                                <div class="likelihood-card most-likely">
                                    <div class="likelihood-icon">
                                        <i class="las la-thumbs-up"></i>
                                    </div>
                                    <h4>Most Likely Job</h4>
                                    <p class="job-title"><?php echo htmlspecialchars($career['most_likely'] ?? 'Not available'); ?></p>
                                    <p class="job-description">Based on your assessment responses, this career path aligns best with your strengths and preferences.</p>
                                </div>
                                
                                <div class="likelihood-card least-likely">
                                    <div class="likelihood-icon">
                                        <i class="las la-thumbs-down"></i>
                                    </div>
                                    <h4>Least Likely Job</h4>
                                    <p class="job-title"><?php echo htmlspecialchars($career['least_likely'] ?? 'Not available'); ?></p>
                                    <p class="job-description">This career path may not align well with your current assessment profile and preferences.</p>
                                </div>
                            </div>
                        </div>
                        
                        <h4>All Suggested Career Paths:</h4>
                        <div class="career-list">
                            <?php foreach ($career['careers'] as $careerOption): ?>
                                <span class="career-item"><?php echo htmlspecialchars($careerOption); ?></span>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (!empty($career['resources'])): ?>
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
                                <?php foreach ($career['resources'] as $careerName => $resources): ?>
                                    <div class="career-resource-card" style="background: #fff; border: 1px solid #e9ecef; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); transition: transform 0.2s ease, box-shadow 0.2s ease;">
                                        <h5 style="color: #007bff; margin: 0 0 15px 0; font-size: 1.1rem; font-weight: 600; border-bottom: 2px solid #e9f5ff; padding-bottom: 8px;">
                                            <i class="las la-briefcase"></i> <?php echo htmlspecialchars($careerName); ?>
                                        </h5>
                                        <div class="resource-links" style="display: flex; flex-direction: column; gap: 8px;">
                                            <?php 
                                            $count = 0;
                                            $maxDisplay = 6; // Show top 6 resources per career
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
                        
                        <div class="career-disclaimer">
                            Note: These recommendations are based on patterns in your assessment responses 
                            and are meant to be suggestions. Your actual career path should consider your 
                            interests, skills, education, and personal goals.
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (isset($result['feedback']) && !empty($result['feedback'])): ?>
                <div class="feedback-section">
                    <h3>Feedback</h3>
                    <p><?php echo nl2br(htmlspecialchars($result['feedback'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php 
    $activePage = 'assessment_test';
    include 'sidebar.php'; 
    ?>
    <label for="sidebar-toggle" class="sidebar-overlay"></label>
</body>
</html> 