<?php
session_start();
if (!isset($_SESSION['school_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

require '../db/dbconn.php';
require 'includes/department_functions.php';

if (isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];

    switch ($_POST['action']) {
        case 'add':
            if (isset($_POST['department_name'])) {
                $name = mysqli_real_escape_string($conn, $_POST['department_name']);
                
                $check_sql = "SELECT COUNT(*) as count FROM department_tbl WHERE department_name = ?";
                $check_stmt = mysqli_prepare($conn, $check_sql);
                mysqli_stmt_bind_param($check_stmt, "s", $name);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);
                $check_row = mysqli_fetch_assoc($check_result);
                
                if ($check_row['count'] > 0) {
                    $response['message'] = "Department name already exists!";
                } else {
                    $result = mysqli_query($conn, "SELECT MAX(department_id) as max_id FROM department_tbl");
                    $row = mysqli_fetch_assoc($result);
                    $new_id = $row['max_id'] + 1;
                    
                    $sql = "INSERT INTO department_tbl (department_id, department_name) VALUES (?, ?)";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "is", $new_id, $name);

                    if (mysqli_stmt_execute($stmt)) {
                        $response['success'] = true;
                        $response['message'] = "Department added successfully!";
                        $response['data'] = [
                            'department_id' => $new_id,
                            'department_name' => $name
                        ];
                    } else {
                        $response['message'] = "Error adding department: " . mysqli_error($conn);
                    }
                }
            }
            break;

        case 'edit':
            if (isset($_POST['id'], $_POST['department_name'])) {
                $id = intval($_POST['id']);
                $name = mysqli_real_escape_string($conn, $_POST['department_name']);

                $sql = "UPDATE department_tbl SET department_name = ? WHERE department_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "si", $name, $id);

                if (mysqli_stmt_execute($stmt)) {
                    $response['success'] = true;
                    $response['message'] = "Department updated successfully!";
                } else {
                    $response['message'] = "Error updating department: " . mysqli_error($conn);
                }
            }
            break;

        case 'get':
            if (isset($_POST['id'])) {
                $id = intval($_POST['id']);
                $sql = "SELECT * FROM department_tbl WHERE department_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $data = mysqli_fetch_assoc($result);

                if ($data) {
                    $response['success'] = true;
                    $response['data'] = $data;
                } else {
                    $response['message'] = "Department not found!";
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
    $_SESSION['message'] = handleDepartmentDelete($conn, $id);
    header("Location: department.php");
    exit;
}

$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

$departments = getDepartments($conn);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include_once __DIR__ . '/../partials/favicon.php'; ?>
    <title>Department Management</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/styles.css"> 
    <link rel="stylesheet" href="../assets/css/datatables.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
</head>

<body>
    <input type="checkbox" id="sidebar-toggle">
    
    <div class="main-content">
        <?php include 'header.php'; ?>
        <div class="container">
            <h2>
            <span class="las la-building"></span>
                Department
            </h2>
        </div>

        <div class="container">
            
            <?php if ($message): ?>
                <div class="message <?php echo strpos($message, 'Error') !== false ? 'error' : ''; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <button class="add-button" onclick="openAddModal()">
                <i class="las la-plus"></i> Add New Department
            </button>

            <div class="table-card">
                <h2>Department List</h2>
                <div class="table-responsive">
                    <table id="myTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Department Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($departments as $row): ?>
                            <tr>
                                <td><?php echo $row['department_id']; ?></td>
                                <td><?php echo htmlspecialchars($row['department_name']); ?></td>
                    
                                <td class="action-buttons">
                                    <button class="btn btn-primary" onclick="openEditModal(<?php echo $row['department_id']; ?>)">
                                        <i class="las la-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-danger" onclick="openDeleteModal(<?php echo $row['department_id']; ?>)">
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
            <h2 class="modal-title">Add New Department</h2>
            <form id="addForm" class="modal-form" onsubmit="handleAddSubmit(event)">
                <div class="form-group">
                    <label for="add_department_name">Department Name:</label>
                    <input type="text" id="add_department_name" name="department_name" required>
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn btn-success">
                        <i class="las la-plus"></i> Add Department
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <?php include('department_edit_delete_modal.php'); ?>
    
    
    <?php 
    $activePage = 'department';
    include 'sidebar.php'; 
    
    ?>
    <script>
        let currentDeleteId = null;

        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
            document.getElementById('add_department_name').focus();
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
            document.getElementById('addForm').reset();
        }

        function openEditModal(id) {
            fetch('department.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('edit_id').value = data.data.department_id;
                    document.getElementById('edit_department_name').value = data.data.department_name;
                    document.getElementById('editModal').style.display = 'block';
                } else {
                    alert(data.message || 'Error fetching department data');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error fetching department data');
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
                window.location.href = `department.php?delete=${currentDeleteId}`;
            }
        }

        function handleAddSubmit(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            formData.append('action', 'add');
            
            fetch('department.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.message || 'Error adding department');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error adding department');
            });
        }

        function handleEditSubmit(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            formData.append('action', 'edit');
            
            fetch('department.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.message || 'Error updating department');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating department');
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