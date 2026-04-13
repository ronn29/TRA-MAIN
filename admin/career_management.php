<?php
session_start();
if (!isset($_SESSION['school_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

require '../db/dbconn.php';

if (isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    switch ($_POST['action']) {
        case 'add':
            $program_id = intval($_POST['program_id']);
            $department_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
            $career_name = mysqli_real_escape_string($conn, $_POST['career_name']);
            $career_description = mysqli_real_escape_string($conn, $_POST['career_description'] ?? '');
            
            if ($department_id) {
                $sql = "INSERT INTO career_program_tbl (program_id, department_id, career_name, career_description) 
                        VALUES (?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "iiss", $program_id, $department_id, $career_name, $career_description);
            } else {
                $sql = "INSERT INTO career_program_tbl (program_id, career_name, career_description) 
                        VALUES (?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "iss", $program_id, $career_name, $career_description);
            }
            
            if (mysqli_stmt_execute($stmt)) {
                $response['success'] = true;
                $response['message'] = "Career added successfully!";
            } else {
                $response['message'] = "Error adding career: " . mysqli_error($conn);
            }
            break;

        case 'edit':
            $career_id = intval($_POST['career_id']);
            $program_id = intval($_POST['program_id']);
            $department_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
            $career_name = mysqli_real_escape_string($conn, $_POST['career_name']);
            $career_description = mysqli_real_escape_string($conn, $_POST['career_description'] ?? '');
            
            $sql = "UPDATE career_program_tbl SET 
                    program_id = ?, department_id = ?, career_name = ?, career_description = ?
                    WHERE career_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "iissi", $program_id, $department_id, $career_name, $career_description, $career_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $response['success'] = true;
                $response['message'] = "Career updated successfully!";
            } else {
                $response['message'] = "Error updating career: " . mysqli_error($conn);
            }
            break;

        case 'get':
            $career_id = intval($_POST['career_id']);
            $sql = "SELECT * FROM career_program_tbl WHERE career_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $career_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $career = mysqli_fetch_assoc($result);
            
            if ($career) {
                $response['success'] = true;
                $response['data'] = $career;
            } else {
                $response['message'] = "Career not found!";
            }
            break;

        case 'delete':
            $career_id = intval($_POST['career_id']);
            $sql = "DELETE FROM career_program_tbl WHERE career_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $career_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $response['success'] = true;
                $response['message'] = "Career deleted successfully!";
            } else {
                $response['message'] = "Error deleting career: " . mysqli_error($conn);
            }
            break;

        case 'toggle_status':
            $career_id = intval($_POST['career_id']);
            $sql = "UPDATE career_program_tbl SET is_active = NOT is_active WHERE career_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $career_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $response['success'] = true;
                $response['message'] = "Career status updated!";
            } else {
                $response['message'] = "Error updating status: " . mysqli_error($conn);
            }
            break;
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$programs_sql = "SELECT p.*, d.department_name 
                FROM program_tbl p 
                LEFT JOIN department_tbl d ON p.department_id = d.department_id 
                ORDER BY p.program_name";
$programs_result = mysqli_query($conn, $programs_sql);
$programs = [];
while ($program = mysqli_fetch_assoc($programs_result)) {
    $programs[] = $program;
}

$departments_sql = "SELECT * FROM department_tbl ORDER BY department_name";
$departments_result = mysqli_query($conn, $departments_sql);
$departments = [];
while ($department = mysqli_fetch_assoc($departments_result)) {
    $departments[] = $department;
}

$careers_sql = "SELECT 
                c.career_id,
                c.career_name,
                c.program_id,
                c.department_id,
                c.career_description,
                c.is_active,
                p.program_name, 
                p.program_code, 
                d.department_name
                FROM career_program_tbl c
                LEFT JOIN program_tbl p ON c.program_id = p.program_id
                LEFT JOIN department_tbl d ON c.department_id = d.department_id
                ORDER BY p.program_code, c.career_name";

$careers_result = mysqli_query($conn, $careers_sql);

if (!$careers_result) {
    die("Query Error: " . mysqli_error($conn));
}

$careers = mysqli_fetch_all($careers_result, MYSQLI_ASSOC);

$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

$activePage = 'career_management';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include_once __DIR__ . '/../partials/favicon.php'; ?>
    <title>Career Management</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/datatables.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <style>
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-primary { background-color: #007bff; color: white; }
        .badge-success { background-color: #28a745; color: white; }
        .badge-secondary { background-color: #6c757d; color: white; }
        .badge-info { background-color: #17a2b8; color: white; }
        .table-card td strong {
             font-weight: 400; }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <input type="checkbox" id="sidebar-toggle">
    
    <div class="main-content">
        <?php include 'header.php'; ?>
        <h2>
            <span class="las la-briefcase"></span>
            Career Management
        </h2>
        <div class="container">
            <?php if ($message): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            
            <button class="btn btn-success" onclick="openAddModal()" style="margin-bottom: 20px;">
                <i class="las la-plus"></i> Add New Career
            </button>

            <div class="table-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3>Careers by Program</h3>
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label for="filterDepartment" style="margin: 0; font-weight: 600;">Department:</label>
                            <select id="filterDepartment" class="form-control" style="width: 200px; padding: 8px;">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['department_name']); ?>">
                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <label for="filterProgram" style="margin: 0; font-weight: 600;">Program:</label>
                            <select id="filterProgram" class="form-control" style="width: 250px; padding: 8px;">
                                <option value="">All Programs</option>
                                <?php foreach ($programs as $prog): ?>
                                    <option value="<?php echo htmlspecialchars($prog['program_name']); ?>">
                                        <?php echo htmlspecialchars($prog['program_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn btn-secondary" onclick="clearFilters()" style="padding: 8px 15px;">
                            <i class="las la-redo"></i> Clear
                        </button>
                    </div>
                </div>
                <table id="careersTable">
                    <thead>
                        <tr>
                            <th>Career Name</th>
                            <th>Program</th>
                            <th>Department</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($careers as $index => $career): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($career['career_name']); ?></strong></td>
                            <td>
                                <?php if (!empty($career['program_name'])): ?>
                                    <span class="badge badge-primary">
                                        <?php echo htmlspecialchars($career['program_name']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-secondary" style="background-color: #6c757d;">
                                        No Program
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-info">
                                    <?php echo htmlspecialchars($career['department_name'] ?? '-'); ?>
                                </span>
                            </td>
                            <td class="action-buttons">
                                <button class="btn btn-primary btn-sm" onclick="openEditModal(<?php echo $career['career_id']; ?>)">
                                    <i class="las la-edit"></i> Edit
                                </button>
                                <button class="btn btn-danger btn-sm confirm-delete" data-career-id="<?php echo $career['career_id']; ?>" data-confirm-message="Delete this career? This cannot be undone.">
                                    <i class="las la-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="addModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <span class="close-modal" onclick="closeAddModal()">&times;</span>
            <h2 class="modal-title">Add New Career</h2>
            <form id="addForm" onsubmit="handleAddSubmit(event)">
                <div class="form-row">
                    <div class="form-group">
                        <label for="add_program_id">Program *</label>
                        <select id="add_program_id" name="program_id" required>
                            <option value="">Select Program</option>
                            <?php foreach ($programs as $program): ?>
                                <option value="<?php echo $program['program_id']; ?>">
                                    <?php echo htmlspecialchars($program['program_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="add_department_id">Department</label>
                        <select id="add_department_id" name="department_id">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $department): ?>
                                <option value="<?php echo $department['department_id']; ?>">
                                    <?php echo htmlspecialchars($department['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="add_career_name">Career Name *</label>
                    <input type="text" id="add_career_name" name="career_name" required>
                </div>
                
                <div class="form-group">
                    <label for="add_career_description">Description</label>
                    <textarea id="add_career_description" name="career_description" rows="3" placeholder="Brief description of the career"></textarea>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-success">
                        <i class="las la-plus"></i> Add Career
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <span class="close-modal" onclick="closeEditModal()">&times;</span>
            <h2 class="modal-title">Edit Career</h2>
            <form id="editForm" onsubmit="handleEditSubmit(event)">
                <input type="hidden" id="edit_career_id" name="career_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_program_id">Program *</label>
                        <select id="edit_program_id" name="program_id" required>
                            <?php foreach ($programs as $program): ?>
                                <option value="<?php echo $program['program_id']; ?>">
                                    <?php echo htmlspecialchars($program['program_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_department_id">Department</label>
                        <select id="edit_department_id" name="department_id">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $department): ?>
                                <option value="<?php echo $department['department_id']; ?>">
                                    <?php echo htmlspecialchars($department['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_career_name">Career Name *</label>
                    <input type="text" id="edit_career_name" name="career_name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_career_description">Description</label>
                    <textarea id="edit_career_description" name="career_description" rows="3" placeholder="Brief description of the career"></textarea>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-success">
                        <i class="las la-save"></i> Update Career
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <?php include 'sidebar.php'; ?>
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script>
        let table;
        
        $(document).ready(function() {
            table = $('#careersTable').DataTable({
                "order": [[1, 'asc'], [0, 'asc']],
                "pageLength": 25
            });
            
            $('#filterDepartment').on('change', function() {
                const value = this.value;
                table.column(2).search(value).draw();
            });
            
            $('#filterProgram').on('change', function() {
                const value = this.value;
                table.column(1).search(value ? value : '').draw();
            });
        });
        
        function clearFilters() {
            $('#filterDepartment').val('');
            $('#filterProgram').val('');
            table.columns().search('').draw();
        }

        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
            document.getElementById('addForm').reset();
        }

        function openEditModal(careerId) {
            fetch('career_management.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=get&career_id=${careerId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('edit_career_id').value = data.data.career_id;
                    document.getElementById('edit_program_id').value = data.data.program_id;
                    document.getElementById('edit_department_id').value = data.data.department_id || '';
                    document.getElementById('edit_career_name').value = data.data.career_name;
                    document.getElementById('edit_career_description').value = data.data.career_description || '';
                    document.getElementById('editModal').style.display = 'block';
                } else {
                    alert(data.message);
                }
            });
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function handleAddSubmit(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            formData.append('action', 'add');
            
            fetch('career_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    window.location.reload();
                }
            });
        }

        function handleEditSubmit(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            formData.append('action', 'edit');
            
            fetch('career_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    window.location.reload();
                }
            });
        }

        function bindCareerDeleteButtons() {
            const buttons = document.querySelectorAll('.confirm-delete[data-career-id]');
            buttons.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const careerId = btn.dataset.careerId;
                    const msg = btn.dataset.confirmMessage || 'Delete this career? This cannot be undone.';
                    if (window.triggerDeleteConfirm) {
                        window.triggerDeleteConfirm(msg, () => performDelete(careerId));
                    } else {
                        if (confirm(msg)) performDelete(careerId);
                    }
                });
            });
        }

        function performDelete(careerId) {
            fetch('career_management.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=delete&career_id=${careerId}`
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    window.location.reload();
                }
            })
            .catch(err => {
                console.error(err);
                alert('An error occurred. Please try again.');
            });
        }

        document.addEventListener('DOMContentLoaded', bindCareerDeleteButtons);

        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            if (event.target == addModal) closeAddModal();
            if (event.target == editModal) closeEditModal();
        }
    </script>
</body>
</html>

