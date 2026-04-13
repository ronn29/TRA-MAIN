<?php
session_start();
if (!isset($_SESSION['school_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

require '../db/dbconn.php';
require 'includes/program_functions.php';
require 'includes/department_functions.php';

if (isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];

    switch ($_POST['action']) {
        case 'add':
            if (isset($_POST['program_name'], $_POST['department_id'])) {
                $name = mysqli_real_escape_string($conn, $_POST['program_name']);
                $department_id = intval($_POST['department_id']);
                
                $check_sql = "SELECT COUNT(*) as count FROM program_tbl WHERE program_name = ?";
                $check_stmt = mysqli_prepare($conn, $check_sql);
                mysqli_stmt_bind_param($check_stmt, "s", $name);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);
                $check_row = mysqli_fetch_assoc($check_result);
                
                if ($check_row['count'] > 0) {
                    $response['message'] = "Program name already exists!";
                } else {
                    $result = mysqli_query($conn, "SELECT MAX(program_id) as max_id FROM program_tbl");
                    $row = mysqli_fetch_assoc($result);
                    $new_id = $row['max_id'] + 1;
                    
                    $sql = "INSERT INTO program_tbl (program_id, program_name, department_id) VALUES (?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "isi", $new_id, $name, $department_id);

                    if (mysqli_stmt_execute($stmt)) {
                        $response['success'] = true;
                        $response['message'] = "Program added successfully!";
                        $response['data'] = [
                            'program_id' => $new_id,
                            'program_name' => $name,
                            'department_id' => $department_id
                        ];
                    } else {
                        $response['message'] = "Error adding program: " . mysqli_error($conn);
                    }
                }
            }
            break;

        case 'edit':
            if (isset($_POST['id'], $_POST['program_name'])) {
                $id = intval($_POST['id']);
                $name = mysqli_real_escape_string($conn, $_POST['program_name']);

                $sql = "UPDATE program_tbl SET program_name = ? WHERE program_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "si", $name, $id);

                if (mysqli_stmt_execute($stmt)) {
                    $response['success'] = true;
                    $response['message'] = "Program updated successfully!";
                } else {
                    $response['message'] = "Error updating program: " . mysqli_error($conn);
                }
            }
            break;

        case 'get':
            if (isset($_POST['id'])) {
                $id = intval($_POST['id']);
                $sql = "SELECT * FROM program_tbl WHERE program_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $data = mysqli_fetch_assoc($result);

                if ($data) {
                    $response['success'] = true;
                    $response['data'] = $data;
                } else {
                    $response['message'] = "Program not found!";
                }
            }
            break;
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $_SESSION['message'] = handleProgramDelete($conn, $id);
    header("Location: program.php");
    exit;
}

$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

$programs = getPrograms($conn);
$departments = getDepartments($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include_once __DIR__ . '/../partials/favicon.php'; ?>
    <title>Program Management</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/datatables.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
</head>

<body>
    <input type="checkbox" id="sidebar-toggle">
    
    <div class="main-content">
        <?php include 'header.php'; ?>
        <div class="container">
            <h2>
            <span class="las la-graduation-cap"></span>
                Program
            </h2>
        </div>

        <div class="container">
            
            <?php if ($message): ?>
                <div class="message <?php echo strpos($message, 'Error') !== false ? 'error' : ''; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <button class="add-button" onclick="openAddModal()">
                <i class="las la-plus"></i> Add New Program
            </button>

            <div class="table-card">
                <h2>Program List</h2>
                <div class="table-responsive">
                    <table id="myTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Program Name</th>
                                <th>Department</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($programs as $row): ?>
                            <tr>
                                <td><?php echo $row['program_id']; ?></td>
                                <td><?php echo htmlspecialchars($row['program_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['department_name']); ?></td>
                                <td class="action-buttons">
                                    <button class="btn btn-primary" onclick="openEditModal(<?php echo $row['program_id']; ?>)">
                                        <i class="las la-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-danger" onclick="openDeleteModal(<?php echo $row['program_id']; ?>)">
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
    </div>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeAddModal()">&times;</span>
            <h2 class="modal-title">Add New Program</h2>
            <form id="addForm" class="modal-form" onsubmit="handleAddSubmit(event)">
                <div class="form-group">
                    <label for="add_program_name">Program Name:</label>
                    <input type="text" id="add_program_name" name="program_name" required>
                </div>
                <div class="form-group">
                    <label for="add_department_id">Department:</label>
                    <select id="add_department_id" name="department_id" required>
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>">
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn btn-success">
                        <i class="las la-plus"></i> Add Program
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <?php include('program_edit_delete_modal.php'); ?>
    
    
    <?php 
    $activePage = 'program';
    include 'sidebar.php'; 
    
    ?>
    <script>
        let currentDeleteId = null;

        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
            document.getElementById('add_program_name').focus();
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
            document.getElementById('addForm').reset();
        }

        function openEditModal(id) {
            fetch('program.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('edit_id').value = data.data.program_id;
                    document.getElementById('edit_program_name').value = data.data.program_name;
                    document.getElementById('editModal').style.display = 'block';
                } else {
                    alert(data.message || 'Error fetching program data');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error fetching program data');
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
                window.location.href = `program.php?delete=${currentDeleteId}`;
            }
        }

        function handleAddSubmit(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            formData.append('action', 'add');
            
            fetch('program.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.message || 'Error adding program');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error adding program');
            });
        }

        function handleEditSubmit(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            formData.append('action', 'edit');
            
            fetch('program.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.message || 'Error updating program');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating program');
            });
        }

        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target == addModal) {
                closeAddModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
            if (event.target == deleteModal) {
                closeDeleteModal();
            }
        }
    </script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="../assets/js/datatables.js"></script>
</body>
</html>