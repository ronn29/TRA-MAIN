<?php
session_start();
if (!isset($_SESSION['school_id']) || $_SESSION['role'] !== 'admin') {
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

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'approve' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $sql = "UPDATE feedback_tbl SET status = 'approved' WHERE feedback_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Feedback approved.";
        } else {
            $message = "Update failed.";
        }
        mysqli_stmt_close($stmt);
    }

    if ($action === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $sql = "DELETE FROM feedback_tbl WHERE feedback_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Feedback deleted.";
        } else {
            $message = "Delete failed.";
        }
        mysqli_stmt_close($stmt);
    }

    if ($action === 'delete_selected' && isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])) {
        $ids = array_map('intval', $_POST['selected_ids']);
        if (count($ids) > 0) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            $sql = "DELETE FROM feedback_tbl WHERE feedback_id IN ($placeholders)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, $types, ...$ids);
            if (mysqli_stmt_execute($stmt)) {
                $message = count($ids) . " feedback item(s) deleted.";
            } else {
                $message = "Bulk delete failed.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

$feedback = [];
$sql = "SELECT f.feedback_id, f.student_id, f.content, f.rating, f.status, f.created_at,
               s.first_name, s.last_name, p.program_name
        FROM feedback_tbl f
        LEFT JOIN student_tbl s ON s.school_id = f.student_id
        LEFT JOIN program_tbl p ON p.program_id = s.program_id
        ORDER BY 
            FIELD(f.status, 'pending','approved','rejected'),
            f.created_at DESC";
$res = mysqli_query($conn, $sql);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $feedback[] = $row;
    }
}

$activePage = 'feedback';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Feedback</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include_once __DIR__ . '/../partials/favicon.php'; ?>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/datatables.css">
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
            vertical-align: top;
        }
        .feedback-table th {
            background: #f8f9fa;
            color: #495057;
            font-weight: 600;
        }
        .feedback-table tr:last-child td {
            border-bottom: none;
        }
        .status-pill {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 12px;
            text-transform: capitalize;
        }
        .status-pill.pending { background:#fff3cd; color:#856404; }
        .status-pill.approved { background:#d4edda; color:#155724; }
        .status-pill.rejected { background:#f8d7da; color:#721c24; }
        .action-buttons form { display: inline; }
        .action-buttons .btn { margin-right: 4px; }
    </style>
</head>
<body>
<input type="checkbox" id="sidebar-toggle">
<div class="main-content">
    <?php include 'header.php'; ?>

    <div class="container">
        <h2>
            <span class="las la-comment-dots"></span>
            Student Feedback
        </h2>
        <p style="color:#666;">Review and approve feedback to feature on the landing page.</p>

        <?php if ($message): ?>
            <div class="message" style="margin: 12px 0;"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="dashboard-card" style="margin-top:16px;">
            <h3 style="margin-bottom:12px;">All Feedback</h3>
            <?php if (count($feedback) > 0): ?>
            <div class="bulk-actions" id="bulkActions" style="display: none; margin-bottom:10px; align-items:center; gap:10px;">
                <div class="bulk-actions-left" style="display:flex; align-items:center; gap:10px;">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAllFeedback()">
                        <label for="selectAll">Select All</label>
                    </div>
                    <span id="selectedCount">0 selected</span>
                </div>
                <div class="bulk-actions-right" style="display:flex; gap:10px;">
                    <button class="btn btn-danger" type="button" onclick="submitBulkDeleteFeedback()" id="deleteSelectedBtn" disabled>
                        <i class="las la-trash"></i> Delete Selected
                    </button>
                </div>
            </div>
            <?php endif; ?>
            <div class="table-card" style="margin:0;">
                <div class="table-responsive">
                    <table id="myTable" class="feedback-table">
                        <thead>
                            <tr>
                                <th style="width:42px;">
                                    <input type="checkbox" id="headerCheckbox" onchange="toggleSelectAllFeedback()">
                                </th>
                                <th>Student ID</th>
                                <th>Student</th>
                                <th>Program</th>
                                <th>Feedback</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feedback as $fb): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="feedback-checkbox" value="<?php echo (int)$fb['feedback_id']; ?>" onchange="updateBulkActionsFeedback()">
                                    </td>
                                    <td><?php echo htmlspecialchars($fb['student_id']); ?></td>
                                    <td>
                                        <?php 
                                            $name = trim(($fb['first_name'] ?? '') . ' ' . ($fb['last_name'] ?? ''));
                                            echo htmlspecialchars($name ?: $fb['student_id']);
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($fb['program_name'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($fb['content']); ?></td>
                                    <td><span class="status-pill <?php echo htmlspecialchars($fb['status']); ?>"><?php echo htmlspecialchars($fb['status']); ?></span></td>
                                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($fb['created_at']))); ?></td>
                                    <td class="action-buttons">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="id" value="<?php echo (int)$fb['feedback_id']; ?>">
        <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-success" <?php echo $fb['status']==='approved' ? 'disabled' : ''; ?>>
                                                Approve
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this feedback?');">
                                            <input type="hidden" name="id" value="<?php echo (int)$fb['feedback_id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn btn-danger">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
include 'sidebar.php'; 
?>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="../assets/js/datatables.js"></script>

</body>
</html>

<script>
    function toggleSelectAllFeedback() {
        const checkboxes = document.querySelectorAll('.feedback-checkbox');
        const headerCheckbox = document.getElementById('headerCheckbox');
        const selectAllCheckbox = document.getElementById('selectAll');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        const newState = !allChecked;
        checkboxes.forEach(cb => cb.checked = newState);
        if (headerCheckbox) {
            headerCheckbox.checked = newState;
            headerCheckbox.indeterminate = false;
        }
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = newState;
        }
        updateBulkActionsFeedback();
    }

    function updateBulkActionsFeedback() {
        const checked = document.querySelectorAll('.feedback-checkbox:checked');
        const bulkActions = document.getElementById('bulkActions');
        const selectedCount = document.getElementById('selectedCount');
        const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
        const headerCheckbox = document.getElementById('headerCheckbox');
        const selectAllCheckbox = document.getElementById('selectAll');
        const total = document.querySelectorAll('.feedback-checkbox').length;

        if (checked.length > 0) {
            if (bulkActions) bulkActions.style.display = 'flex';
            if (selectedCount) selectedCount.textContent = `${checked.length} selected`;
            if (deleteSelectedBtn) deleteSelectedBtn.disabled = false;
        } else {
            if (bulkActions) bulkActions.style.display = 'none';
            if (deleteSelectedBtn) deleteSelectedBtn.disabled = true;
        }

        if (headerCheckbox) {
            headerCheckbox.checked = checked.length === total && total > 0;
            headerCheckbox.indeterminate = checked.length > 0 && checked.length < total;
        }
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = checked.length === total && total > 0;
        }
    }

    function submitBulkDeleteFeedback() {
        const checked = document.querySelectorAll('.feedback-checkbox:checked');
        if (checked.length === 0) {
            alert('Please select at least one feedback to delete.');
            return;
        }
        if (!confirm('Delete selected feedback items?')) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_selected';
        form.appendChild(actionInput);

        checked.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_ids[]';
            input.value = cb.value;
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    }
</script>
