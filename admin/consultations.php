<?php
session_start();
if (!isset($_SESSION['school_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

require '../db/dbconn.php';
require __DIR__ . '/includes/consultation_functions.php';

ensureConsultationTables($conn);

$activePage = 'consultations';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include_once __DIR__ . '/../partials/favicon.php'; ?>
    <title>Consultations</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <style>
        .card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .card h3 {
            margin: 0 0 10px;
        }
        .form-grid {
            display: grid;
            gap: 15px;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            margin-top: 10px;
        }
        .toggle-group {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table th, table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        table th {
            text-align: left;
            background: #f8f9fc;
        }
        .chip-on { background: #d1e7dd; color: #0f5132; }
        .chip-off { background: #f8d7da; color: #842029; }
        .status-chip {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .chip-on { background: #d1e7dd; color: #0f5132; }
        .chip-off { background: #f8d7da; color: #842029; }
        .chip-booked { background: #e9ecef; color: #495057; }
        .chip-complete { background: #d1e7dd; color: #0f5132; }
        .chip-cancelled { background: #f8d7da; color: #842029; }
        .muted { color: #6c757d; font-size: 13px; }
        .table-wrap { overflow-x: auto; }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-xs {
            padding: 6px 10px;
            font-size: 12px;
            line-height: 1.2;
        }
        .btn-xs {
            padding: 6px 10px;
            font-size: 12px;
            line-height: 1.2;
        }
    </style>
</head>
<body>
<input type="checkbox" id="sidebar-toggle">

<div class="main-content">
    <?php include 'header.php'; ?>

    <div class="container">
        <h2>
            <span class="las la-calendar-check"></span>
            Consultation Calendar
        </h2>

        <div id="adminMessage"></div>

        <div class="card">
            <h3>Availability per Day</h3>
            <p class="muted">Enable sessions for each day and (optionally) set a daily consultation limit. Leave limit blank or 0 for unlimited.</p>
            <form id="availabilityForm">
                <div class="form-grid">
                    <div>
                        <label for="availDate">Date</label>
                        <input type="date" id="availDate" name="date" required>
                    </div>
                    <div class="toggle-group">
                        <label><input type="checkbox" id="morningEnabled" name="morning_enabled" checked> Morning</label>
                        <label><input type="checkbox" id="afternoonEnabled" name="afternoon_enabled" checked> Afternoon</label>
                    </div>
                    <div>
                        <label for="dailyLimit">Daily Limit</label>
                        <input type="number" id="dailyLimit" name="daily_limit" min="0" placeholder="0 = unlimited" value="0">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary"><span class="las la-save"></span>Save</button>
                    </div>
                </div>
            </form>
            <div class="table-wrap" style="margin-top:15px;">
                <table id="availabilityTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Morning</th>
                            <th>Afternoon</th>
                            <th>Daily Limit</th>
                        </tr>
                    </thead>
                    <tbody id="availabilityBody"></tbody>
                </table>
            </div>
        </div>

    
    </div>
</div>

<?php include 'sidebar.php'; ?>
<label for="sidebar-toggle" class="sidebar-overlay"></label>

<script>
const messageBox = document.getElementById('adminMessage');

function showMessage(text, type = 'success') {
    messageBox.innerHTML = `<div class="message ${type === 'error' ? 'error' : ''}">${text}</div>`;
    setTimeout(() => messageBox.innerHTML = '', 4000);
}

async function fetchBookings() {
    const res = await fetch('consultation_api.php?action=get_schedule');
    const data = await res.json();
    const tbody = document.querySelector('#bookingTable tbody');
    tbody.innerHTML = '';
    (data.data || []).forEach(row => {
        const studentName = [row.first_name, row.last_name].filter(Boolean).join(' ') || row.student_school_id;
        const prog = row.program_name ? row.program_name : '—';
        const session = row.session === 'afternoon' ? 'Afternoon' : 'Morning';
        const status = row.status || 'booked';
        const statusClass = status === 'completed'
            ? 'chip-complete'
            : status === 'cancelled'
                ? 'chip-cancelled'
                : 'chip-booked';
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${row.scheduled_date}</td>
            <td>${session}</td>
            <td>${studentName}</td>
            <td>${prog}</td>
            <td><span class="status-chip ${statusClass}">${status}</span></td>
            <td>${row.created_at}</td>
            <td>
                ${status === 'booked' ? `<button class="btn btn-primary btn-xs mark-complete" data-id="${row.booking_id}"><span class="las la-check"></span> Mark Complete</button>` : ''}
            </td>
        `;
        tbody.appendChild(tr);
    });

    document.querySelectorAll('.mark-complete').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.getAttribute('data-id');
            btn.disabled = true;
            btn.textContent = 'Saving...';
            try {
                const res = await fetch('consultation_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'update_schedule_status',
                        booking_id: id,
                        status: 'completed'
                    })
                });
                const json = await res.json();
                if (json.success) {
                    showMessage('Marked as completed.');
                    fetchBookings();
                    fetchAvailability();
                } else {
                    showMessage(json.error || 'Unable to update status', 'error');
                }
            } catch (err) {
                showMessage('Unable to update status', 'error');
            } finally {
                btn.disabled = false;
            }
        });
    });
}

async function fetchAvailability() {
    const res = await fetch('consultation_api.php?action=get_availability');
    const data = await res.json();
    const tbody = document.getElementById('availabilityBody');
    tbody.innerHTML = '';
    (data.data || []).forEach(row => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${row.available_date}</td>
            <td><span class="status-chip ${row.morning_enabled ? 'chip-on' : 'chip-off'}">${row.morning_enabled ? 'On' : 'Off'}</span></td>
            <td><span class="status-chip ${row.afternoon_enabled ? 'chip-on' : 'chip-off'}">${row.afternoon_enabled ? 'On' : 'Off'}</span></td>
            <td>${row.daily_limit && Number(row.daily_limit) > 0 ? row.daily_limit : 'Unlimited'}</td>
        `;
        tbody.appendChild(tr);
    });
}

document.getElementById('availabilityForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const res = await fetch('consultation_api.php', {
        method: 'POST',
        body: new URLSearchParams({
            action: 'save_availability',
            date: formData.get('date'),
            morning_enabled: formData.get('morning_enabled') ? 1 : 0,
            afternoon_enabled: formData.get('afternoon_enabled') ? 1 : 0,
            daily_limit: formData.get('daily_limit') || 0
        })
    });
    const data = await res.json();
    if (data.success) {
        showMessage('Availability saved.');
        fetchAvailability();
        fetchBookings();
    } else {
        showMessage(data.error || 'Unable to save availability', 'error');
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('availDate').value = today;
    fetchBookings();
    fetchAvailability();
});
</script>
</body>
</html>

