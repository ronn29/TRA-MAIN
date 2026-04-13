<?php
session_start();
if (!isset($_SESSION['school_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit;
    
}

require '../db/dbconn.php';


$student_id = $_SESSION['school_id'] ?? null;
$ackCookieKey = $student_id ? ("assessment_ack_" . $student_id) : null;


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acknowledge_disclaimer'])) {
    if ($student_id) {
        $_SESSION['assessment_disclaimer_ack'][$student_id] = true;
        if ($ackCookieKey) {
            setcookie($ackCookieKey, '1', time() + 60 * 60 * 24 * 30, '/');
        }
    }
    echo json_encode(['success' => true]);
    exit;
}


$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}


$student_profile_sql = "SELECT s.first_name, s.last_name, s.email, s.program_id, p.department_id
                        FROM student_tbl s
                        LEFT JOIN program_tbl p ON s.program_id = p.program_id
                        WHERE s.school_id = ?";
$stmt = mysqli_prepare($conn, $student_profile_sql);
mysqli_stmt_bind_param($stmt, "s", $student_id);
mysqli_stmt_execute($stmt);
$student_result = mysqli_stmt_get_result($stmt);
$student_data = mysqli_fetch_assoc($student_result);
$student_program_id = $student_data['program_id'] ?? null;
$student_department_id = $student_data['department_id'] ?? null;


$profile_fields = ['first_name', 'last_name', 'email', 'program_id'];
$isProfileComplete = true;
foreach ($profile_fields as $field) {
    $value = trim((string)($student_data[$field] ?? ''));
    if ($value === '') {
        $isProfileComplete = false;
        break;
    }
}

if (!$isProfileComplete) {
    $_SESSION['error'] = "Please complete your profile (name, email, and program) before taking assessments.";
    $_SESSION['show_profile_modal'] = true;
    header("Location: profile.php");
    exit;
}


$check_column = "SHOW COLUMNS FROM assessment_tbl LIKE 'program_id'";
$column_result = mysqli_query($conn, $check_column);
$has_program_id = ($column_result && mysqli_num_rows($column_result) > 0);

$check_dept_column = "SHOW COLUMNS FROM assessment_tbl LIKE 'department_id'";
$dept_column_result = mysqli_query($conn, $check_dept_column);
$has_department_id = ($dept_column_result && mysqli_num_rows($dept_column_result) > 0);

$sessionAck = !empty($_SESSION['assessment_disclaimer_ack'][$student_id]);
$cookieAck = $ackCookieKey && isset($_COOKIE[$ackCookieKey]) && $_COOKIE[$ackCookieKey] === '1';
$show_disclaimer = !($sessionAck || $cookieAck);


$result = null;
if ($has_program_id && $student_program_id) {
    if ($has_department_id && $student_department_id) {
        $sql = "SELECT a.*, 
                (SELECT COUNT(*) FROM question_tbl WHERE assessment_id = a.assessment_id) as question_count,
                p.program_name,
                p.department_id
                FROM assessment_tbl a 
                LEFT JOIN program_tbl p ON a.program_id = p.program_id
                WHERE a.visibility = 1 
                  AND (
                      a.program_id = ?
                      OR (a.program_id IS NULL AND a.department_id = ?)
                  )
                ORDER BY a.assessment_order ASC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $student_program_id, $student_department_id);
    } else {
        $sql = "SELECT a.*, 
                (SELECT COUNT(*) FROM question_tbl WHERE assessment_id = a.assessment_id) as question_count,
                p.program_name,
                p.department_id
                FROM assessment_tbl a 
                LEFT JOIN program_tbl p ON a.program_id = p.program_id
                WHERE a.visibility = 1 
                  AND a.program_id = ?
                ORDER BY a.assessment_order ASC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $student_program_id);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} elseif (!$has_program_id) {
    $sql = "SELECT a.*, 
            (SELECT COUNT(*) FROM question_tbl WHERE assessment_id = a.assessment_id) as question_count 
            FROM assessment_tbl a 
            WHERE a.visibility = 1
            ORDER BY a.assessment_order ASC";
    $result = mysqli_query($conn, $sql);
}

$assessments = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $assessments[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include_once __DIR__ . '/../partials/favicon.php'; ?>
    <title>Assessment Test</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <link href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="../assets/css/datatables.css">
    <style>
        .disclaimer-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.55);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .disclaimer-modal {
            background: #fff;
            border-radius: 10px;
            padding: 24px;
            max-width: 520px;
            width: 90%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .disclaimer-modal h3 {
            margin-top: 0;
            margin-bottom: 12px;
            font-size: 20px;
        }

        .disclaimer-modal p {
            margin: 0 0 16px 0;
            color: #444;
            line-height: 1.5;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-secondary {
            background: #e0e0e0;
            color: #333;
            border: none;
            padding: 10px 18px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-secondary:hover {
            background: #d5d5d5;
        }

        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(255, 255, 255, 0.8);
            display: none;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 12px;
            z-index: 10000;
        }

        .loading-overlay.show {
            display: flex;
        }

        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid #cce5ff;
            border-top-color: #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading-text {
            color: #0c5460;
            font-weight: 600;
        }

        #assessmentTable thead th.no-sort::before,
        #assessmentTable thead th.no-sort::after {
            display: none !important;
        }
        
        #assessmentTable thead th.no-sort {
            cursor: default !important;
        }
    </style>
</head>
<body>
    <div class="disclaimer-overlay" id="disclaimer-modal" style="display: <?php echo $show_disclaimer ? 'flex' : 'none'; ?>;">
        <div class="disclaimer-modal">
            <h3>Please Read Before Proceeding</h3>
            <p>
                The Tragabay is not responsible for any outcomes that may follow from these assessments.
                Our only goal is to provide a recommendation based on your submitted answers.
            </p>
            <p>
                By continuing, you acknowledge that you understand and accept this notice.
            </p>
            <div class="modal-actions">
                <button type="button" class="btn-primary" id="acknowledge-btn">I Understand</button>
            </div>
        </div>
    </div>

    <input type="checkbox" id="sidebar-toggle">

    <div class="main-content">
        <?php include 'header.php'; ?>
        <h2>
            <span class="las la-clipboard-list"></span>
            Assessment Test
        </h2>
        <div class="container">
            <?php if ($message): ?>
                <div class="message <?php echo strpos($message, 'Error') !== false ? 'error' : ''; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div style="background:#fff; border-radius:10px; padding:20px; box-shadow:0 4px 12px rgba(0,0,0,0.08);">
                <?php if (!$student_program_id): ?>
                    <div class="alert" style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <i class="las la-exclamation-triangle" style="font-size: 24px; color: #856404;"></i>
                            <div>
                                <strong style="color: #856404;">Program Not Selected</strong>
                                <p style="margin: 5px 0 0 0; color: #856404;">
                                    You haven't selected your program yet. Please update your profile to access program-specific assessments.
                                    <a href="profile.php" style="color: #0056b3; text-decoration: underline; font-weight: 600;">Go to Profile</a>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>


                <table id="assessmentTable">
                    <thead>
                        <tr>
                            <th class="no-sort"> Assessment Name</th>
                            <th class="no-sort">Questions</th>
                            <th class="no-sort">Status</th>
                            <th class="no-sort">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assessments as $row): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($row['assessment_name']); ?>
                                <?php if (isset($row['program_name']) && !empty($row['program_name'])): ?>
                                    <span class="badge" style="background-color: #17a2b8; color: white; padding: 3px 8px; border-radius: 12px; font-size: 11px; margin-left: 8px;">
                                        <?php echo htmlspecialchars($row['program_name']); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($row['description'])): ?>
                                    <div class="tooltip" style="display: inline-block; margin-left: 8px;">
                                        <i class="las la-info-circle assessment-info" 
                                           style="color: #007bff; cursor: pointer; font-size: 16px;"></i>
                                        <span class="tooltiptext"><?php echo htmlspecialchars($row['description']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $row['question_count']; ?></td>
                            <td>
                                <?php
                                // Check if student has already taken this assessment
                                $student_id = $_SESSION['school_id']; // Convert to integer
                                $assessment_id = $row['assessment_id'];
                                
                                $check_sql = "SELECT assessment_status FROM assessment_results_tbl 
                                            WHERE student_id = ? AND assessment_id = ? AND assessment_status = 'Completed' 
                                            LIMIT 1";
                                $check_stmt = mysqli_prepare($conn, $check_sql);
                                mysqli_stmt_bind_param($check_stmt, "si", $student_id, $assessment_id);
                                mysqli_stmt_execute($check_stmt);
                                $check_result = mysqli_stmt_get_result($check_stmt);
                                
                                $status = 'Not Started';
                                $status_class = 'btn-secondary';
                                
                                if ($check_result && mysqli_num_rows($check_result) > 0) {
                                    $status = 'Completed';
                                    $status_class = 'btn-success';
                                } else {
                                    $progress_sql = "SELECT assessment_status FROM assessment_results_tbl 
                                                   WHERE student_id = ? AND assessment_id = ? AND assessment_status = 'In Progress' 
                                                   LIMIT 1";
                                    $progress_stmt = mysqli_prepare($conn, $progress_sql);
                                    mysqli_stmt_bind_param($progress_stmt, "si", $student_id, $assessment_id);
                                    mysqli_stmt_execute($progress_stmt);
                                    $progress_result = mysqli_stmt_get_result($progress_stmt);
                                    
                                    if ($progress_result && mysqli_num_rows($progress_result) > 0) {
                                        $status = 'In Progress';
                                        $status_class = 'btn-warning';
                                    }
                                }
                                ?>
                                <span class="btn <?php echo $status_class; ?>"><?php echo $status; ?></span>
                            </td>
                            <td>
                                <?php if ($status === 'Not Started'): ?>
                                    <a href="take_assessment.php?assessment_id=<?php echo $row['assessment_id']; ?>" class="btn btn-primary">
                                        <i class="las la-pencil-alt"></i> Take Assessment
                                    </a>
                                <?php elseif ($status === 'Completed'): ?>
                                    <span class="btn btn-success" style="cursor: default;">
                                        <i class="las la-check"></i> Completed
                                    </span>
                                <?php elseif ($status === 'In Progress'): ?>
                                    <a href="take_assessment.php?assessment_id=<?php echo $row['assessment_id']; ?>" class="btn btn-warning">
                                        <i class="las la-play"></i> Continue Assessment
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- View Results Section -->
                <div class="view-results-section" style="margin-top: 30px; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin-bottom: 15px; color: #333; font-size: 18px;">Your Career Reccomendations</h3>
                    <p style="margin-bottom: 20px; color: #666;">Get a comprehensive career recommendation based on all your assessment results:</p>
                    
                    <?php
                    $visibleAssessmentIds = array_column($assessments, 'assessment_id');
                    $total_count = count($visibleAssessmentIds);
                    
                    if ($total_count > 0) {
                        $placeholders = implode(',', array_fill(0, $total_count, '?'));
                        $types = str_repeat('i', $total_count);
                        $sql = "SELECT COUNT(DISTINCT assessment_id) as completed 
                                FROM assessment_results_tbl 
                                WHERE student_id = ? 
                                  AND assessment_status = 'Completed'
                                  AND assessment_id IN ($placeholders)";
                        
                        $stmt = mysqli_prepare($conn, $sql);
                        $params = array_merge([$_SESSION['school_id']], $visibleAssessmentIds);
                        mysqli_stmt_bind_param($stmt, 's' . $types, ...$params);
                        mysqli_stmt_execute($stmt);
                        $completed_result = mysqli_stmt_get_result($stmt);
                        $completed_count = mysqli_fetch_assoc($completed_result)['completed'] ?? 0;
                    } else {
                        $completed_count = 0;
                    }
                    
                    if ($total_count === 0):
                    ?>
                    <div style="margin-top: 15px; color: #856404; background-color: #fff3cd; padding: 12px; border-radius: 4px; border: 1px solid #ffeeba;">
                        <i class="las la-exclamation-circle"></i> No assessments are available for your program yet.
                    </div>
                    <?php elseif ($completed_count == 0): ?>
                    <div style="margin-top: 15px; color: #856404; background-color: #fff3cd; padding: 12px; border-radius: 4px; border: 1px solid #ffeeba;">
                        <i class="las la-exclamation-circle"></i> You haven't completed any assessments yet. Complete all assessments to get your comprehensive career recommendation.
                    </div>
                    <?php elseif ($completed_count < $total_count): ?>
                    <div style="margin-top: 15px; color: #856404; background-color: #fff3cd; padding: 12px; border-radius: 4px; border: 1px solid #ffeeba;">
                        <i class="las la-exclamation-circle"></i> Please complete all <?php echo $total_count; ?> assessments to get your comprehensive career recommendation. You have completed <?php echo $completed_count; ?> out of <?php echo $total_count; ?> assessments.
                    </div>
                    <?php else: ?>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="flex-grow: 1;">
                            <p style="margin: 0; color: #495057;">
                                <strong>Congratulations!</strong> You have completed all assessments. Our algorithm will now analyze all your responses 
                                to provide you with a comprehensive career recommendation that considers your aptitude, personality, and interests together.
                            </p>
                        </div>
                        <div>
                            <a href="career_prediction.php" class="btn btn-success career-link" style="padding: 10px 20px; font-weight: 500;">
                                <i class="las la-chart-line"></i> View Career Reccomendations
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php 
    $activePage = 'assessment_test';
    include 'sidebar.php'; 
    ?>
    <label for="sidebar-toggle" class="sidebar-overlay"></label> 

    <div class="loading-overlay" id="loading-overlay">
        <div class="spinner"></div>
        <div class="loading-text">Generating recommendations...</div>
    </div>
    <script>  
        $(document).ready(function() {
            if (!$.fn.DataTable.isDataTable('#assessmentTable')) {
                $('#assessmentTable').DataTable({
                    "pageLength": 10,
                    "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
                    "dom": "tip",
                    "order": [[0, 'asc']],
                    "language": {
                        "info": "Showing _START_ to _END_ of _TOTAL_ assessments",
                        "infoEmpty": "No assessments available",
                        "infoFiltered": "(filtered from _MAX_ total assessments)"
                    },
                    "columnDefs": [
                        { "orderable": false, "targets": 'no-sort' }
                    ]
                });
            }
        });

        (function() {
            const modal = document.getElementById('disclaimer-modal');
            const acknowledgeBtn = document.getElementById('acknowledge-btn');
            const cancelBtn = document.getElementById('cancel-assessment');

            acknowledgeBtn.addEventListener('click', function () {
                fetch('assessment_test.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'acknowledge_disclaimer=1'
                })
                .then(() => {
                    try {
                        const studentId = <?php echo json_encode($student_id); ?>;
                        if (studentId) {
                            const key = 'assessment_ack_' + studentId;
                            const expires = new Date(Date.now() + 1000 * 60 * 60 * 24 * 30).toUTCString();
                            document.cookie = `${key}=1; expires=${expires}; path=/`;
                        }
                    } catch (e) {}
                    modal.style.display = 'none';
                })
                .catch(() => {
                    modal.style.display = 'none';
                });
            });

            cancelBtn.addEventListener('click', function () {
                window.location.href = 'profile.php';
            });
        })();

        (function() {
            const overlay = document.getElementById('loading-overlay');
            const links = document.querySelectorAll('.career-link');
            links.forEach(link => {
                link.addEventListener('click', function() {
                    if (overlay) {
                        overlay.classList.add('show');
                    }
                });
            });
        })();
    </script>
</body>
</html>