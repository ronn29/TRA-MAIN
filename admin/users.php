<?php
session_start();
if (!isset($_SESSION['school_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

require '../db/dbconn.php';
require 'includes/user_functions.php';
require 'includes/program_functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_selected' && isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])) {
    $ids = array_map('intval', $_POST['selected_ids']);
    if (count($ids) > 0) {
        $deleted_count = 0;
        $errors = [];
        
        foreach ($ids as $id) {
            $result = handleUserDelete($conn, $id);
            if (strpos($result, 'successfully') !== false) {
                $deleted_count++;
            } else {
                $errors[] = $result;
            }
        }
        
        if ($deleted_count > 0) {
            $_SESSION['message'] = $deleted_count . " user(s) deleted successfully!";
        } else {
            $_SESSION['message'] = "Bulk delete failed. " . implode(' ', $errors);
        }
    }
    header("Location: users.php");
    exit;
}

if (isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];

    switch ($_POST['action']) {
        case 'get':
            if (isset($_POST['id'])) {
                $id = intval($_POST['id']);
                $data = getUserById($conn, $id);
                
                if ($data && !empty($data)) {
                    if (!isset($data['role']) || empty($data['role'])) {
                        if (!empty($data['student_first_name']) || !empty($data['student_school_id'])) {
                            $data['role'] = 'student';
                        } elseif (!empty($data['admin_first_name'])) {
                            $data['role'] = 'admin';
                        }
                    }
                    
                    $response['success'] = true;
                    $response['data'] = $data;
                } else {
                    $response['message'] = "User not found!";
                }
            } else {
                $response['message'] = "User ID is required!";
            }
            break;

        case 'edit':
            if (isset($_POST['id'], $_POST['role'])) {
                $user_id = intval($_POST['id']);
                $role = mysqli_real_escape_string($conn, $_POST['role']);
                $email = mysqli_real_escape_string($conn, $_POST['email']);
                
                if ($role === 'admin') {
                    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
                    $middle_name = mysqli_real_escape_string($conn, $_POST['middle_name'] ?? '');
                    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
                    $contact_number = mysqli_real_escape_string($conn, $_POST['contact_number'] ?? '');
                    $result = updateAdmin($conn, $user_id, $email, $first_name, $middle_name, $last_name, $contact_number);
                } else if ($role === 'student') {
                    $school_id = mysqli_real_escape_string($conn, $_POST['school_id']);
                    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
                    $middle_name = mysqli_real_escape_string($conn, $_POST['middle_name'] ?? '');
                    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
                    $program_id = mysqli_real_escape_string($conn, $_POST['program_id'] ?? '');
                    $status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'active');
                    $ext_name = '';
                    $gender = '';
                    $date_of_birth = '';
                    $contact_number = '';
                    $address = '';
                    $result = updateStudent($conn, $user_id, $school_id, $email, $first_name, $middle_name, $last_name, $ext_name, $gender, $date_of_birth, $program_id, $contact_number, $address, $status);
                } else {
                    $result = ['success' => false, 'message' => 'Invalid role'];
                }
                
                $response = $result;
            }
            break;
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $_SESSION['message'] = handleUserDelete($conn, $id);
    header("Location: users.php");
    exit;
}

$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

$admins = getUsers($conn, 'admin');
$students = getUsers($conn, 'student');
$programs = getPrograms($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include_once __DIR__ . '/../partials/favicon.php'; ?>
    <title>User Management</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/datatables.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
</head>

<style>
    .container {
        padding:30px 30px 0 30px;
    }

    .message {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 4px;
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
    }

    .form-group input[type="text"],
    .form-group input[type="email"],
    .form-group input[type="password"],
    .form-group input[type="date"],
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        box-sizing: border-box;
    }

    .form-group textarea {
        resize: vertical;
        min-height: 80px;
    }

    .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        text-decoration: none;
        display: inline-block;
        margin-right: 5px;
    }

    .btn-primary {
        background-color: #007bff;
        color: white;
    }

    .btn-danger {
        background-color: #dc3545;
        color: white;
    }

    .btn-success {
        background-color: #28a745;
        color: white;
    }

    .btn-secondary {
        background-color: #6c757d;
        color: white;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        background-color: #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    th, td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }

    tbody tr:last-child td {
        border-bottom: none;
    }

    th {
        background-color: #f8f9fa;
        font-weight: 600;
    }

    tr:hover {
        background-color: #f5f5f5;
    }

    th.actions-col,
    td.actions-col {
        width: 80px;
        
    }

    .action-buttons {
        display: inline-flex;
        gap: 8px;
        justify-content: center;
        align-items: center;
        white-space: nowrap;
        position: relative;
        z-index: 1;
    }
    
    .action-buttons .btn {
        position: relative;
        z-index: 2;
        pointer-events: auto;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
    }
        
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        overflow-y: auto;
    }

    .modal-content {
        background-color: #fff;
        margin: 5% auto;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        width: 90%;
        max-width: 600px;
        position: relative;
    }

    .close-modal {
        position: absolute;
        right: 20px;
        top: 15px;
        font-size: 24px;
        font-weight: bold;
        cursor: pointer;
        color: #666;
    }

    .close-modal:hover {
        color: #000;
    }

    .modal-title {
        margin-top: 0;
        margin-bottom: 20px;
        color: #333;
    }

    .modal-form .form-group {
        margin-bottom: 20px;
    }

    .btn-group {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 20px;
    }

    .main-content h2 {
        margin-top: 30px;
        padding: 20px 0 0 30px;
        font-size: 2rem;
        font-weight: 600;
        color: #333;
        display: flex;
        gap: 10px;
    }

    .main-content h2 span {
        font-size: 3rem;
    }

    .tab-buttons {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }

    .tab-btn {
        padding: 10px 20px;
        border: none;
        background-color: #f8f9fa;
        color: #333;
        cursor: pointer;
        border-radius: 4px;
        font-size: 14px;
    }

    .tab-btn.active {
        background-color: #007bff;
        color: white;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .role-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
    }

    .role-badge.admin {
        background-color: #28a745;
        color: white;
    }

    .role-badge.student {
        background-color: #28a745;
        color: white;
    }

    .filter-container {
        margin-bottom: 20px;
        padding: 15px;
        background-color: #f8f9fa;
        border-radius: 6px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .filter-container label {
        font-weight: 600;
        margin: 0;
        color: #333;
    }

    .filter-container select {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        min-width: 200px;
        background-color: white;
        cursor: pointer;
    }

    .filter-container select:focus {
        outline: none;
        border-color: #007bff;
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
    }

    .bulk-actions {
        display: none;
        margin-bottom: 15px;
        padding: 15px;
        background-color: #f8f9fa;
        border-radius: 6px;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }

    .bulk-actions-left {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .bulk-actions-right {
        display: flex;
        gap: 10px;
        margin-left: auto;
    }

    .checkbox-wrapper {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .checkbox-wrapper input[type="checkbox"] {
        cursor: pointer;
    }

    .checkbox-wrapper label {
        margin: 0;
        cursor: pointer;
        font-weight: normal;
    }

    #selectedCount {
        font-weight: 600;
        color: #333;
    }
</style>
<body>
    <input type="checkbox" id="sidebar-toggle">

    <div class="main-content">
        <?php include 'header.php'; ?>
        <div class="container">
            <h2>
                <span class="las la-users"></span>
                User Management
            </h2>
        </div>

        <div class="container">
            <?php if ($message): ?>
                <div class="message <?php echo strpos($message, 'Error') !== false ? 'error' : ''; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="tab-buttons">
                <button class="tab-btn active" onclick="showTab('admins', event)">
                    <i class="las la-user-shield"></i> Admins
                </button>
                <button class="tab-btn" onclick="showTab('students', event)">
                    <i class="las la-user-graduate"></i> Students
                </button>
            </div>

            <div id="admins-tab" class="tab-content active">
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <button class="add-button" onclick="openAddModal('admin')">
                    <i class="las la-plus"></i> Add Admin
                </button>
                <?php endif; ?>
                <div class="table-card">
                    <h2>Admin List</h2>
                    <?php if (count($admins) > 0): ?>
                    <div class="bulk-actions" id="bulkActionsAdmins" style="display: none;">
                        <div class="bulk-actions-left">
                            <div class="checkbox-wrapper">
                                <input type="checkbox" id="selectAllAdmins" onchange="toggleSelectAll('admins')">
                                <label for="selectAllAdmins">Select All</label>
                            </div>
                            <span id="selectedCountAdmins">0 selected</span>
                        </div>
                        <div class="bulk-actions-right">
                            <button class="btn btn-danger" type="button" onclick="submitBulkDelete('admins')" id="deleteSelectedAdmins" disabled>
                                <i class="las la-trash"></i> Delete Selected
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table id="myTable">
                            <thead>
                                <tr>
                                    <th style="width:42px;">
                                        <input type="checkbox" id="headerCheckboxAdmins" onchange="toggleSelectAll('admins')">
                                    </th>
                                    <th>School ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th class="actions-col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admins as $admin): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="user-checkbox admin-checkbox" value="<?php echo (int)$admin['user_id']; ?>" onchange="updateBulkActions('admins')">
                                    </td>
                                    <td><?php echo htmlspecialchars($admin['school_id']); ?></td>
                                    <td><?php echo htmlspecialchars(trim($admin['first_name'] . ' ' . ($admin['middle_name'] ?? '') . ' ' . $admin['last_name'])); ?></td>
                                    <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                    <td>
                                        <span class="role-badge admin"><?php echo ucfirst($admin['status'] ?? 'active'); ?></span>
                                    </td>
                                    <td class="actions-col">
                                        <div class="action-buttons">
                                            <button class="btn btn-primary" onclick="openEditModal(<?php echo $admin['user_id']; ?>)">
                                                <i class="las la-edit"></i> Edit
                                            </button>
                                            <button class="btn btn-danger" onclick="openDeleteModal(<?php echo $admin['user_id']; ?>)">
                                                <i class="las la-trash"></i> Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="students-tab" class="tab-content">
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <button class="add-button" onclick="openAddModal('student')">
                    <i class="las la-plus"></i> Add Student
                </button>
                <?php endif; ?>
                <div class="table-card">
                    <h2>Student List</h2>
                    <div class="filter-container">
                        <label for="programFilter">Filter by Program:</label>
                        <select id="programFilter">
                            <option value="">All Programs</option>
                            <?php foreach ($programs as $program): ?>
                                <option value="<?php echo htmlspecialchars($program['program_name']); ?>">
                                    <?php echo htmlspecialchars($program['program_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (count($students) > 0): ?>
                    <div class="bulk-actions" id="bulkActionsStudents" style="display: none;">
                        <div class="bulk-actions-left">
                            <div class="checkbox-wrapper">
                                <input type="checkbox" id="selectAllStudents" onchange="toggleSelectAll('students')">
                                <label for="selectAllStudents">Select All</label>
                            </div>
                            <span id="selectedCountStudents">0 selected</span>
                        </div>
                        <div class="bulk-actions-right">
                            <button class="btn btn-danger" type="button" onclick="submitBulkDelete('students')" id="deleteSelectedStudents" disabled>
                                <i class="las la-trash"></i> Delete Selected
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table id="studentsTable">
                            <thead>
                                <tr>
                                    <th style="width:42px;">
                                        <input type="checkbox" id="headerCheckboxStudents" onchange="toggleSelectAll('students')">
                                    </th>
                                    <th>School ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Program</th>
                                    <th>Status</th>
                                    <th class="actions-col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="user-checkbox student-checkbox" value="<?php echo (int)$student['user_id']; ?>" onchange="updateBulkActions('students')">
                                    </td>
                                    <td><?php echo htmlspecialchars($student['student_school_id'] ?? $student['school_id']); ?></td>
                                    <td><?php echo htmlspecialchars(trim($student['first_name'] . ' ' . ($student['middle_name'] ?? '') . ' ' . $student['last_name'])); ?></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td><?php echo htmlspecialchars($student['program_name'] ?? '-'); ?></td>
                                    <td>
                                        <span class="role-badge student"><?php echo ucfirst($student['status'] ?? 'active'); ?></span>
                                    </td>
                                    <td class="actions-col">
                                        <div class="action-buttons">
                                            <button class="btn btn-primary" onclick="openEditModal(<?php echo $student['user_id']; ?>)">
                                                <i class="las la-edit"></i> Edit
                                            </button>
                                            <button class="btn btn-danger" onclick="openDeleteModal(<?php echo $student['user_id']; ?>)">
                                                <i class="las la-trash"></i> Delete
                                            </button>
                                        </div>
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
    $activePage = 'users';
    include 'sidebar.php'; 
    ?>
    <?php include('user_edit_delete_modal.php'); ?>
    
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeAddModal()">&times;</span>
            <h2 class="modal-title" id="addModalTitle">Add New User</h2>
            <form id="addUserForm" method="POST" action="user_add.php">
                <input type="hidden" id="add_role" name="role">
                
                <div class="form-group">
                    <label for="add_school_id">School ID:</label>
                    <input type="text" id="add_school_id" name="school_id" required>
                </div>
                
                <div class="form-group">
                    <label for="add_email">Email:</label>
                    <input type="email" id="add_email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="add_password">Password:</label>
                    <input type="password" id="add_password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="add_first_name">First Name:</label>
                    <input type="text" id="add_first_name" name="first_name" required>
                </div>
                
                <div class="form-group">
                    <label for="add_middle_name">Middle Name:</label>
                    <input type="text" id="add_middle_name" name="middle_name">
                </div>
                
                <div class="form-group">
                    <label for="add_last_name">Last Name:</label>
                    <input type="text" id="add_last_name" name="last_name" required>
                </div>
                
                <div id="studentFields" style="display: none;">
                    <div class="form-group">
                        <label for="add_program">Program:</label>
                        <select id="add_program" name="program_id" required>
                            <option value="">Select Program</option>
                            <?php foreach ($programs as $program): ?>
                                <option value="<?php echo $program['program_id']; ?>"><?php echo htmlspecialchars($program['program_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="las la-plus"></i> Add User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentDeleteId = null;
        let currentRole = null;

        function openAddModal(role) {
            currentRole = role;
            document.getElementById('add_role').value = role;
            document.getElementById('addModalTitle').textContent = 'Add New ' + (role === 'admin' ? 'Admin' : 'Student');

            const studentFields = document.getElementById('studentFields');
            const programField = document.getElementById('add_program');

            if (role === 'admin') {
                if (studentFields) studentFields.style.display = 'none';
                if (programField) programField.removeAttribute('required');
            } else {
                if (studentFields) studentFields.style.display = 'block';
                if (programField) programField.setAttribute('required', 'required');
            }

            document.getElementById('addModal').style.display = 'block';
        }

        function showTab(tabName, evt) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            document.getElementById(tabName + '-tab').classList.add('active');
            
            if (evt && evt.target) {
                evt.target.classList.add('active');
            } else {
                document.querySelectorAll('.tab-btn').forEach(btn => {
                    if (btn.textContent.includes(tabName === 'admins' ? 'Admins' : 'Students')) {
                        btn.classList.add('active');
                    }
                });
            }
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
            document.getElementById('addUserForm').reset();
        }

        function openEditModal(id) {
            fetch('users.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get&id=${id}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        const user = data.data;
                        
                        if (!user.role) {
                            if (user.student_first_name || user.student_school_id || user.program_id) {
                                user.role = 'student';
                            } else if (user.admin_first_name) {
                                user.role = 'admin';
                            }
                        }
                        
                        if (!user.role) {
                            alert('Unable to determine user role');
                            return;
                        }
                        
                        currentRole = user.role;
                        
                        if (user.role === 'admin') {
                            document.getElementById('edit_id').value = user.user_id;
                            document.getElementById('edit_role').value = 'admin';
                            document.getElementById('edit_school_id').value = user.school_id || '';
                            document.getElementById('edit_email').value = user.email || '';
                            document.getElementById('edit_first_name').value = user.admin_first_name || '';
                            document.getElementById('edit_middle_name').value = user.admin_middle_name || '';
                            document.getElementById('edit_last_name').value = user.admin_last_name || '';
                            
                            const editStudentFields = document.getElementById('editStudentFields');
                            if (editStudentFields) {
                                editStudentFields.style.display = 'none';
                            }
                        } else if (user.role === 'student') {
                            document.getElementById('edit_id').value = user.user_id;
                            document.getElementById('edit_role').value = 'student';
                            document.getElementById('edit_school_id').value = user.school_id || user.student_school_id || '';
                            document.getElementById('edit_email').value = user.email || '';
                            document.getElementById('edit_first_name').value = user.student_first_name || '';
                            document.getElementById('edit_middle_name').value = user.student_middle_name || '';
                            document.getElementById('edit_last_name').value = user.student_last_name || '';
                            document.getElementById('edit_program').value = user.program_id || '';
                            document.getElementById('edit_status').value = user.student_status || 'active';
                            
                            const editStudentFields = document.getElementById('editStudentFields');
                            if (editStudentFields) {
                                editStudentFields.style.display = 'block';
                            }
                        }
                        
                        document.getElementById('editModal').style.display = 'block';
                    } else {
                        alert(data.message || 'Error fetching user data');
                    }
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Response text:', text);
                    alert('Error parsing server response. Check console for details.');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert('Error fetching user data: ' + error.message);
            });
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function openDeleteModal(id) {
            currentDeleteId = id;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            currentDeleteId = null;
        }

        function confirmDelete() {
            if (currentDeleteId) {
                window.location.href = `users.php?delete=${currentDeleteId}`;
            }
        }
        
        function handleEditSubmit(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            formData.append('action', 'edit');
            
            fetch('users.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.message || 'Error updating user');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating user');
            });
        }
        
        document.getElementById('addUserForm').addEventListener('submit', function(e) {
            const role = document.getElementById('add_role').value;
            const programField = document.getElementById('add_program');
            
            if (role === 'admin') {
                programField.removeAttribute('required');
            } else {
                programField.setAttribute('required', 'required');
            }
        });
        
        window.onclick = function(event) {
            const editModal = document.getElementById('editModal');
            const deleteModal = document.getElementById('deleteModal');
            const addModal = document.getElementById('addModal');
            if (event.target == editModal) {
                closeEditModal();
            }
            if (event.target == deleteModal) {
                closeDeleteModal();
            }
            if (event.target == addModal) {
                closeAddModal();
            }
        }

        function toggleSelectAll(tab) {
            const checkboxClass = tab === 'admins' ? '.admin-checkbox' : '.student-checkbox';
            const checkboxes = document.querySelectorAll(checkboxClass);
            const headerCheckbox = document.getElementById('headerCheckbox' + (tab === 'admins' ? 'Admins' : 'Students'));
            const selectAllCheckbox = document.getElementById('selectAll' + (tab === 'admins' ? 'Admins' : 'Students'));
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
            
            updateBulkActions(tab);
        }

        function updateBulkActions(tab) {
            const checkboxClass = tab === 'admins' ? '.admin-checkbox' : '.student-checkbox';
            const checked = document.querySelectorAll(checkboxClass + ':checked');
            const bulkActions = document.getElementById('bulkActions' + (tab === 'admins' ? 'Admins' : 'Students'));
            const selectedCount = document.getElementById('selectedCount' + (tab === 'admins' ? 'Admins' : 'Students'));
            const deleteSelectedBtn = document.getElementById('deleteSelected' + (tab === 'admins' ? 'Admins' : 'Students'));
            const headerCheckbox = document.getElementById('headerCheckbox' + (tab === 'admins' ? 'Admins' : 'Students'));
            const selectAllCheckbox = document.getElementById('selectAll' + (tab === 'admins' ? 'Admins' : 'Students'));
            const total = document.querySelectorAll(checkboxClass).length;

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

        function submitBulkDelete(tab) {
            const checkboxClass = tab === 'admins' ? '.admin-checkbox' : '.student-checkbox';
            const checked = document.querySelectorAll(checkboxClass + ':checked');
            
            if (checked.length === 0) {
                alert('Please select at least one user to delete.');
                return;
            }
            
            if (!confirm(`Delete ${checked.length} selected user(s)? This action cannot be undone.`)) {
                return;
            }

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

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="../assets/js/datatables.js"></script>

    <script>
        let studentsTable = null;
        let programFilterFunction = null;

        function initStudentsTable() {
            if (studentsTable === null || !$.fn.DataTable.isDataTable('#studentsTable')) {
                studentsTable = $('#studentsTable').DataTable({
                    columnDefs: [
                        { orderable: false, targets: 0 },
                        { orderable: false, targets: -1 }
                    ]
                });
            }
            return studentsTable;
        }

        $(document).ready(function () {
            studentsTable = initStudentsTable();

            $('#programFilter').on('change', function () {
                if (studentsTable === null) {
                    studentsTable = initStudentsTable();
                }

                const selectedProgram = this.value.trim();

                if (programFilterFunction !== null) {
                    const index = $.fn.dataTable.ext.search.indexOf(programFilterFunction);
                    if (index !== -1) {
                        $.fn.dataTable.ext.search.splice(index, 1);
                    }
                    programFilterFunction = null;
                }

                if (selectedProgram !== '') {
                    programFilterFunction = function (settings, data) {
                        if (settings.nTable.id !== 'studentsTable') {
                            return true;
                        }

                            const programName = data[4] ? data[4].trim() : '';
                        return programName.toLowerCase() === selectedProgram.toLowerCase();
                    };

                    $.fn.dataTable.ext.search.push(programFilterFunction);
                }

                studentsTable.draw();
            });
        });
    </script>
</body>
</html>

