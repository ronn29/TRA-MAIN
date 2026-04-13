<?php
session_start();
if (!isset($_SESSION['school_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
require '../db/dbconn.php';
require __DIR__ . '/includes/consultation_functions.php';
ensureConsultationTables($conn);

$program_options = mysqli_query($conn, "SELECT program_name FROM program_tbl ORDER BY program_name ASC");

$today = date('Y-m-d');
$stat_today = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM consultation_bookings WHERE scheduled_date = '$today' AND status != 'cancelled'"))[0] ?? 0;
$stat_upcoming = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM consultation_bookings WHERE scheduled_date > '$today' AND status != 'cancelled'"))[0] ?? 0;
$stat_completed_week = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM consultation_bookings WHERE status = 'completed' AND YEARWEEK(scheduled_date, 1) = YEARWEEK(CURDATE(), 1)"))[0] ?? 0;
$stat_cancelled = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM consultation_bookings WHERE status = 'cancelled'"))[0] ?? 0;

$rc_per_page = 10;
$rc_page = isset($_GET['rc_page']) ? max(1, (int)$_GET['rc_page']) : 1;
$rc_offset = ($rc_page - 1) * $rc_per_page;
$rc_total_today = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM consultation_bookings WHERE scheduled_date = '$today'"))[0] ?? 0;
$rc_total_pages = max(1, (int)ceil($rc_total_today / $rc_per_page));
?>       

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include_once __DIR__ . '/../partials/favicon.php'; ?>

    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">

    
</head>
<body>

<input type="checkbox" id="sidebar-toggle">

<div class="main-content">
    <?php include 'header.php'; ?>

    <div class="container">
        <div class="dashboard-header">
            <h2>
                <span class="las la-money-check"></span>
                Dashboard
            </h2>
        </div>

        <div class="dashboard-grid">
            <div class="dashboard-left">
                <div class="dashboard-stats">
                    <div class="stat-pill pill-primary">
                        <div>
                            <div class="stat-label">Today’s Consultations</div>
                            <div class="stat-value"><?php echo (int)$stat_today; ?></div>
                        </div>
                        <span class="las la-sun"></span>
                    </div>
                    <div class="stat-pill pill-info">
                        <div>
                            <div class="stat-label">Upcoming Consultations</div>
                            <div class="stat-value"><?php echo (int)$stat_upcoming; ?></div>
                        </div>
                        <span class="las la-calendar-check"></span>
                    </div>
                    <div class="stat-pill pill-success">
                        <div>
                            <div class="stat-label">Completed (This Week)</div>
                            <div class="stat-value"><?php echo (int)$stat_completed_week; ?></div>
                        </div>
                        <span class="las la-check-circle"></span>
                    </div>
                    <div class="stat-pill pill-warning">
                        <div>
                            <div class="stat-label">Cancelled</div>
                            <div class="stat-value"><?php echo (int)$stat_cancelled; ?></div>
                        </div>
                        <span class="las la-times-circle"></span>
                    </div>
                </div>
                <?php
                $recent_consultations = mysqli_query(
                    $conn,
                    "SELECT b.booking_id, b.scheduled_date, b.session, b.status, s.first_name, s.last_name, s.school_id, p.program_name
                     FROM consultation_bookings b
                     LEFT JOIN student_tbl s ON b.student_school_id = s.school_id
                     LEFT JOIN program_tbl p ON s.program_id = p.program_id
                     WHERE b.scheduled_date = '$today'
                     ORDER BY b.scheduled_date ASC, b.session ASC, b.created_at DESC
                     LIMIT $rc_per_page OFFSET $rc_offset"
                );
                ?>
                <div class="dashboard-card" style="margin-top:20px;">
                    <div class="dashboard-student-list-header">
                        <h3 class="dashboard-student-list-title">Recent Consultations</h3>
                    </div>
                    <?php if ($recent_consultations && mysqli_num_rows($recent_consultations) > 0): ?>
                        <div class="dashboard-student-list-table-container" style="max-height:250px;">
                            <table class="dashboard-student-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Program</th>
                                        <th>Date</th>
                                        <th>Session</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($c = mysqli_fetch_assoc($recent_consultations)): ?>
                                        <?php
                                            $studentName = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
                                            $studentName = $studentName ?: ($c['school_id'] ?? 'Unknown');
                                            $sessionLabel = ($c['session'] === 'afternoon') ? 'Afternoon' : 'Morning';
                                            $isActive = !in_array($c['status'] ?? '', ['completed', 'cancelled']);
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($studentName); ?></td>
                                            <td><?php echo htmlspecialchars($c['program_name'] ?: '—'); ?></td>
                                            <td><?php echo htmlspecialchars($c['scheduled_date']); ?></td>
                                            <td><?php echo htmlspecialchars($sessionLabel); ?></td>
                                            <td>
                                                <?php if ($isActive): ?>
                                                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                                        <button class="btn btn-primary btn-xs consult-action" data-id="<?php echo (int)$c['booking_id']; ?>" data-status="completed">
                                                            <span class="las la-check"></span> Complete
                                                        </button>
                                                        <button class="btn btn-outline btn-xs consult-action" data-id="<?php echo (int)$c['booking_id']; ?>" data-status="cancelled">
                                                            <span class="las la-times"></span> Cancel
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="muted"><?php echo htmlspecialchars(ucfirst($c['status'] ?? '—')); ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($rc_total_pages > 1): ?>
                            <div class="dashboard-pagination">
                                <?php if ($rc_page > 1): ?>
                                    <a class="page-btn" href="?rc_page=<?php echo $rc_page - 1; ?>">&laquo; Prev</a>
                                <?php endif; ?>
                                <span class="page-info">Page <?php echo $rc_page; ?> of <?php echo $rc_total_pages; ?></span>
                                <?php if ($rc_page < $rc_total_pages): ?>
                                    <a class="page-btn" href="?rc_page=<?php echo $rc_page + 1; ?>">Next &raquo;</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="muted" style="margin:10px 0;">No consultations yet.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="dashboard-right">
                <div class="cards cards-compact cards-stack">
                    <div class="card-single card-compact">
                        <div class="card-flex">
                            <div class="card-info">
                                <div class="card-head">
                                    <span>Admin</span>
                                    <small>Total Admin</small>
                                </div>
                                <?php
                                $sql = "SELECT * FROM admin_tbl ORDER BY admin_id ASC";

                                $result = mysqli_query($conn, $sql);
                                $total_admin = mysqli_num_rows($result);
                                ?>
                                <h2 style="color: rgb(238, 61, 61);"><?php echo $total_admin; ?></h2>
                            </div>
                            <div class="card-chart danger">
                                <span class="las la-user-cog"></span>
                            </div>
                        </div>
                    </div>

                    <div class="card-single card-compact">
                        <div class="card-flex">
                            <div class="card-info">
                                <div class="card-head">
                                    <span>Students</span>
                                    <small>Total Students</small>
                                </div>
                                <?php
                                $sql = "SELECT * FROM student_tbl ORDER BY class_id ASC";

                                $result = mysqli_query($conn, $sql);
                                $total_students = mysqli_num_rows($result);
                                ?>
                                <h2 style="color: rgb(26, 167, 192);"><?php echo $total_students; ?></h2>
                            </div>
                            <div class="card-chart primary">
                                <span class="las la-user-graduate"></span>
                            </div>
                        </div>
                    </div>

                    <div class="card-single card-compact">
                        <div class="card-flex">
                            <div class="card-info">
                                <div class="card-head">
                                    <span>Users</span>
                                    <small>Total Users</small>
                                </div>
                                <?php
                                $sql = "SELECT * FROM user_tbl ORDER BY user_id ASC";

                                $result = mysqli_query($conn, $sql);
                                $total_users= mysqli_num_rows($result);
                                ?>
                                <h2 style="color: rgb(45, 199, 31);"><?php echo $total_users; ?></h2>
                            </div>
                            <div class="card-chart success">
                                <span class="las la-users"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <?php 
    $activePage = 'index';
    include 'sidebar.php'; 
    ?>




<script>
    (function() {
        const table = document.getElementById('studentTable');
        if (!table) return;

        const rows = Array.from(table.querySelectorAll('tbody tr'));
        const searchInput = document.getElementById('studentSearch');
        const programFilter = document.getElementById('programFilter');
        const statusFilter = document.getElementById('statusFilter');

        function norm(val) {
            return (val || '').toString().toLowerCase().trim();
        }

        function applyFilters() {
            const term = norm(searchInput.value);
            const programVal = norm(programFilter.value);
            const statusVal = norm(statusFilter.value);
            rows.forEach(row => {
                const rowText = row.textContent.toLowerCase();
                const rowProgram = norm(row.dataset.program);
                const rowStatus = norm(row.dataset.status);

                const matchesText = term === '' || rowText.includes(term);
                const matchesProgram = programVal === 'all' || (rowProgram && rowProgram.includes(programVal));
                const matchesStatus = statusVal === 'all' || (rowStatus && rowStatus === statusVal);

                row.style.display = (matchesText && matchesProgram && matchesStatus) ? '' : 'none';
            });
        }

        searchInput.addEventListener('input', applyFilters);
        programFilter.addEventListener('change', applyFilters);
        statusFilter.addEventListener('change', applyFilters);
    })();

    document.querySelectorAll('.consult-action').forEach(btn => {
        btn.addEventListener('click', async () => {
            const bookingId = btn.getAttribute('data-id');
            const status = btn.getAttribute('data-status');
            if (!bookingId || !status) return;
            btn.disabled = true;
            const originalText = btn.textContent;
            btn.textContent = 'Saving...';
            try {
                const res = await fetch('consultation_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'update_schedule_status',
                        booking_id: bookingId,
                        status
                    })
                });
                const json = await res.json();
                if (res.ok && json.success) {
                    window.location.reload();
                } else {
                    alert(json.error || 'Unable to update status');
                }
            } catch (err) {
                alert('Unable to update status');
            } finally {
                btn.disabled = false;
                btn.textContent = originalText;
            }
        });
    });
</script>

</body>
</html>
