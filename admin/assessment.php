<?php
session_start();
if (!isset($_SESSION['school_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

require '../db/dbconn.php';
require 'includes/assessment_functions.php';

if (isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    switch ($_POST['action']) {
        case 'add':
            if (isset($_POST['assessment_name'])) {
                $assessment_name = mysqli_real_escape_string($conn, $_POST['assessment_name']);
                $description = isset($_POST['description']) ? mysqli_real_escape_string($conn, $_POST['description']) : '';
                
                $check_column = "SHOW COLUMNS FROM assessment_tbl LIKE 'program_id'";
                $column_result = mysqli_query($conn, $check_column);
                $has_program_id = ($column_result && mysqli_num_rows($column_result) > 0);
                
                $program_id = null;
                if ($has_program_id && isset($_POST['program_id']) && $_POST['program_id'] !== '') {
                    $program_id = intval($_POST['program_id']);
                }
                
                $check_sql = "SELECT COUNT(*) as count FROM assessment_tbl WHERE assessment_name = ?";
                $check_stmt = mysqli_prepare($conn, $check_sql);
                mysqli_stmt_bind_param($check_stmt, "s", $assessment_name);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);
                $check_row = mysqli_fetch_assoc($check_result);
                
                if ($check_row['count'] > 0) {
                    $response['message'] = "Assessment name already exists!";
                } else {
                    $result = mysqli_query($conn, "SELECT MAX(assessment_order) as max_order FROM assessment_tbl");
                    $row = mysqli_fetch_assoc($result);
                    $new_order = $row['max_order'] + 1;
                    
                    if ($has_program_id) {
                        $sql = "INSERT INTO assessment_tbl (assessment_name, assessment_order, visibility, description, program_id) VALUES (?, ?, 1, ?, ?)";
                        $stmt = mysqli_prepare($conn, $sql);
                        mysqli_stmt_bind_param($stmt, "sisi", $assessment_name, $new_order, $description, $program_id);
                    } else {
                        $sql = "INSERT INTO assessment_tbl (assessment_name, assessment_order, visibility, description) VALUES (?, ?, 1, ?)";
                        $stmt = mysqli_prepare($conn, $sql);
                        mysqli_stmt_bind_param($stmt, "sis", $assessment_name, $new_order, $description);
                    }
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $response['success'] = true;
                        $response['message'] = "Assessment added successfully!";
                        $response['data'] = [
                            'assessment_id' => mysqli_insert_id($conn),
                            'assessment_name' => $assessment_name,
                            'assessment_order' => $new_order,
                            'description' => $description,
                            'program_id' => $program_id
                        ];
                    } else {
                        $response['message'] = "Error adding assessment: " . mysqli_error($conn);
                    }
                }
            }
            break;

        case 'edit':
            if (isset($_POST['id'], $_POST['assessment_name'])) {
                $id = intval($_POST['id']);
                $assessment_name = mysqli_real_escape_string($conn, $_POST['assessment_name']);
                $description = isset($_POST['description']) ? mysqli_real_escape_string($conn, $_POST['description']) : '';
                
                $check_column = "SHOW COLUMNS FROM assessment_tbl LIKE 'program_id'";
                $column_result = mysqli_query($conn, $check_column);
                $has_program_id = ($column_result && mysqli_num_rows($column_result) > 0);
                
                $program_id = null;
                if ($has_program_id && isset($_POST['program_id']) && $_POST['program_id'] !== '') {
                    $program_id = intval($_POST['program_id']);
                }
                
                if ($has_program_id) {
                    if ($program_id === null) {
                        $sql = "UPDATE assessment_tbl SET assessment_name = ?, description = ?, program_id = NULL WHERE assessment_id = ?";
                        $stmt = mysqli_prepare($conn, $sql);
                        mysqli_stmt_bind_param($stmt, "ssi", $assessment_name, $description, $id);
                    } else {
                        $sql = "UPDATE assessment_tbl SET assessment_name = ?, description = ?, program_id = ? WHERE assessment_id = ?";
                        $stmt = mysqli_prepare($conn, $sql);
                        mysqli_stmt_bind_param($stmt, "ssii", $assessment_name, $description, $program_id, $id);
                    }
                } else {
                    $sql = "UPDATE assessment_tbl SET assessment_name = ?, description = ? WHERE assessment_id = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "ssi", $assessment_name, $description, $id);
                }
                
                if (mysqli_stmt_execute($stmt)) {
                    $response['success'] = true;
                    $response['message'] = "Assessment updated successfully!";
                } else {
                    $response['message'] = "Error updating assessment: " . mysqli_error($conn);
                }
            }
            break;
            
        case 'get':
            if (isset($_POST['id'])) {
                $id = intval($_POST['id']);
                $sql = "SELECT * FROM assessment_tbl WHERE assessment_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $assessment = mysqli_fetch_assoc($result);
                
                if ($assessment) {
                    $response['success'] = true;
                    $response['data'] = $assessment;
                } else {
                    $response['message'] = "Assessment not found!";
                }
            }
            break;
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $_SESSION['message'] = handleAssessmentDelete($conn, $id);
    header("Location: assessment.php");
    exit;
}

if (isset($_GET['toggle_visibility'])) {
    $id = intval($_GET['toggle_visibility']);
    $_SESSION['message'] = toggleAssessmentVisibility($conn, $id);
    header("Location: assessment.php");
    exit;
}

$programs_sql = "SELECT program_id, program_code, program_name FROM program_tbl ORDER BY program_name";
$programs_result = mysqli_query($conn, $programs_sql);
$programs = [];
while ($program = mysqli_fetch_assoc($programs_result)) {
    $programs[] = $program;
}

$assessment = getAssessments($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include_once __DIR__ . '/../partials/favicon.php'; ?>
    <title>Assessment Management</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/datatables.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">

</head>

<style>
    .back-button {
    margin: 50px 0 24px 0;
    padding-top: 20px;
    } 
    .add-button {
        background-color: #28a745;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 20px;
    }

    .add-button:hover {
        background-color: #218838;
    }
</style>

<body>
    <input type="checkbox" name="" id="sidebar-toggle">
    
    <div class="main-content">
        <?php include 'header.php'; ?>
        <h2>
            <span class="las la-clipboard-list "></span>
            Assessment
        </h2>
        <div class="container">
            
            <?php if ($message): ?>
                <div class="message <?php echo strpos($message, 'Error') !== false ? 'error' : ''; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <button class="add-button" onclick="openAddModal()">
                <i class="las la-plus"></i> Add New Assessment
            </button>

            <div class="table-card">
                <h2>Assessment List</h2>
                <div class="table-responsive">
                    <table id="myTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Assessment Name</th>
                                <th>Program</th>
                                <th>Questions</th>
                                <th>Visibility</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assessment as $row): ?>
                            <tr>
                                <td><?php echo $row['assessment_order']; ?></td>
                                <td><?php echo htmlspecialchars($row['assessment_name']); ?></td>
                                <td>
                                    <?php 
                                    if (!empty($row['program_id']) && !empty($row['program_name'])) {
                                        echo '<span style=" color: #666; border-radius: 4px; font-size: 12px;">' . htmlspecialchars($row['program_name']) . '</span>';
                                    } else {
                                        echo '<span style="color: #666; font-size: 12px;">General</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo $row['question_count']; ?></td>
                                <td>
                                    <a href="assessment.php?toggle_visibility=<?php echo $row['assessment_id']; ?>" 
                                       class="btn <?php echo $row['visibility'] ? 'btn-success' : 'btn-secondary'; ?>">
                                        <i class="las <?php echo $row['visibility'] ? 'la-eye' : 'la-eye-slash'; ?>"></i>
                                        <?php echo $row['visibility'] ? 'Visible' : 'Hidden'; ?>
                                    </a>
                                </td>
                                <td class="action-buttons">
                                    <a href="question.php?assessment_id=<?php echo $row['assessment_id']; ?>" 
                                       class="btn btn-primary">
                                        <i class="las la-list"></i> Manage Questions
                                    </a>
                                    <button class="btn btn-primary" onclick="openEditModal(<?php echo $row['assessment_id']; ?>)">
                                        <i class="las la-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-danger" onclick="openDeleteModal(<?php echo $row['assessment_id']; ?>)">
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
            <h2 class="modal-title">Add New Assessment</h2>
            <form id="addForm" class="modal-form" onsubmit="handleAddSubmit(event)">
                <div class="form-group">
                    <label for="add_assessment_name">Assessment Name:</label>
                    <input type="text" id="add_assessment_name" name="assessment_name" required>
                </div>
                <div class="form-group">
                    <label for="add_description">Description:</label>
                    <textarea id="add_description" name="description" rows="4" placeholder="Enter a description of what this assessment evaluates..."></textarea>
                </div>
                <div class="form-group">
                    <label for="add_program_id">Program (Optional):</label>
                    <select id="add_program_id" name="program_id" class="form-control">
                        <option value="">General Assessment (All Programs)</option>
                        <?php foreach ($programs as $program): ?>
                            <option value="<?php echo $program['program_id']; ?>">
                                <?php echo htmlspecialchars($program['program_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #666; font-size: 12px;">Leave blank for general assessment available to all students, or select a specific program.</small>
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn btn-success">
                        <i class="las la-plus"></i> Add Assessment
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include('assessment_edit_delete_modal.php'); ?>
    
    <?php 
    include 'sidebar.php'; 
    ?>
    
    <script>
        let currentDeleteId = null;

        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
            document.getElementById('add_assessment_name').focus();
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
            document.getElementById('addForm').reset();
        }

        function openEditModal(id) {
            fetch('assessment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('edit_id').value = data.data.assessment_id;
                    document.getElementById('edit_assessment_name').value = data.data.assessment_name;
                    document.getElementById('edit_description').value = data.data.description || '';
                    document.getElementById('edit_program_id').value = data.data.program_id || '';
                    document.getElementById('editModal').style.display = 'block';
                } else {
                    alert(data.message || 'Error fetching assessment data');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error fetching assessment data');
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
                window.location.href = `assessment.php?delete=${currentDeleteId}`;
            }
        }

        function handleAddSubmit(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            formData.append('action', 'add');
            
            fetch('assessment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.message || 'Error adding assessment');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error adding assessment');
            });
        }
        
        function handleEditSubmit(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            formData.append('action', 'edit');
            
            fetch('assessment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.message || 'Error updating assessment');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating assessment');
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
