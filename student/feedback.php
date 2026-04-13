<?php
session_start();
if (!isset($_SESSION['school_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

require '../db/dbconn.php';

$conn->query("
    CREATE TABLE IF NOT EXISTS feedback_tbl (
        feedback_id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        rating TINYINT NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$school_id = $_SESSION['school_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim($_POST['content'] ?? '');

    if (strlen($content) < 5) {
        $error = "Please share a short comment (at least 5 characters).";
    } else {
        $sql = "INSERT INTO feedback_tbl (student_id, content) VALUES (?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $school_id, $content);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Thanks! Your feedback was submitted and is pending review.";
        } else {
            $error = "Could not submit feedback. Please try again.";
        }
        mysqli_stmt_close($stmt);
    }
}

$submissions = [];
$listSql = "SELECT feedback_id, content, rating, status, created_at 
            FROM feedback_tbl 
            WHERE student_id = ?
            ORDER BY created_at DESC";
$listStmt = mysqli_prepare($conn, $listSql);
mysqli_stmt_bind_param($listStmt, "s", $school_id);
mysqli_stmt_execute($listStmt);
$res = mysqli_stmt_get_result($listStmt);
while ($row = mysqli_fetch_assoc($res)) {
    $submissions[] = $row;
}
mysqli_stmt_close($listStmt);

$activePage = 'feedback';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include_once __DIR__ . '/../partials/favicon.php'; ?>
    <title>Feedback</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <style>
        .feedback-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .feedback-table th,
        .feedback-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e9ecef;
            text-align: left;
        }
        .feedback-table th {
            background: #f8f9fa;
            color: #495057;
            font-weight: 600;
        }
        .feedback-table tr:last-child td {
            border-bottom: none;
        }
        .form-control {
            pointer-events: auto;
        }
    </style>
</head>
<body>
    <input type="checkbox" id="sidebar-toggle">
    <?php include 'sidebar.php'; ?>
    <label for="sidebar-toggle" class="sidebar-overlay"></label>

    <div class="main-content">
        <?php include 'header.php'; ?>

        <div class="container">
            <h2><span class="las la-comment-dots"></span> Feedback</h2>
            <p style="color:#666; margin-bottom: 20px;">Share your experience to help us improve.</p>

            <?php if ($message): ?>
                <div class="message" style="margin-bottom: 16px; background:#e6ffed; color:#0f5132; border-left:4px solid #2ecc71; padding: 12px 16px; border-radius: 6px;">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="message error" style="margin-bottom: 16px; background:#f8d7da; color:#721c24; border-left:4px solid #dc3545; padding: 12px 16px; border-radius: 6px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="card" style="background: #fff; border-radius: 10px; padding: 24px; margin-bottom: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.08);">
                <h3 style="margin: 0 0 16px 0; color: #333; font-size: 1.2rem; font-weight: 600;">
                    <i class="las la-pen"></i> Submit New Feedback
                </h3>
                <form method="POST">
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label for="content" style="display: block; margin-bottom: 8px; color: #555; font-weight: 500;">Your feedback</label>
                        <textarea id="content" name="content" rows="5" class="form-control" required placeholder="Tell us what worked well or what could improve..." style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; font-family: inherit; resize: vertical;"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
                       Submit Feedback
                    </button>
                </form>
            </div>

        </div>
    </div>
</body>
</html>

