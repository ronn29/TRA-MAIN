<?php
session_start();
if (!isset($_SESSION['school_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

require '../db/dbconn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $career_name = mysqli_real_escape_string($conn, $_POST['career_name']);
    $resource_name = mysqli_real_escape_string($conn, $_POST['resource_name']);
    $resource_url = mysqli_real_escape_string($conn, $_POST['resource_url']);
    $resource_type = mysqli_real_escape_string($conn, $_POST['resource_type']);
    $resource_provider = mysqli_real_escape_string($conn, $_POST['resource_provider']);
    $is_free = isset($_POST['is_free']) ? 1 : 0;
    $display_order = intval($_POST['display_order']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $sql = "INSERT INTO career_resources_tbl 
            (career_name, resource_name, resource_url, resource_type, resource_provider, is_free, display_order, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sssssiii", $career_name, $resource_name, $resource_url, $resource_type, $resource_provider, $is_free, $display_order, $is_active);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['message'] = "Resource added successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error adding resource: " . mysqli_error($conn);
        $_SESSION['message_type'] = "error";
    }
    
    mysqli_stmt_close($stmt);
    header("Location: career_resources.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $resource_id = intval($_POST['resource_id']);
    $career_name = mysqli_real_escape_string($conn, $_POST['career_name']);
    $resource_name = mysqli_real_escape_string($conn, $_POST['resource_name']);
    $resource_url = mysqli_real_escape_string($conn, $_POST['resource_url']);
    $resource_type = mysqli_real_escape_string($conn, $_POST['resource_type']);
    $resource_provider = mysqli_real_escape_string($conn, $_POST['resource_provider']);
    $is_free = isset($_POST['is_free']) ? 1 : 0;
    $display_order = intval($_POST['display_order']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $sql = "UPDATE career_resources_tbl 
            SET career_name = ?, resource_name = ?, resource_url = ?, resource_type = ?, 
                resource_provider = ?, is_free = ?, display_order = ?, is_active = ?
            WHERE resource_id = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sssssiiii", $career_name, $resource_name, $resource_url, $resource_type, $resource_provider, $is_free, $display_order, $is_active, $resource_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['message'] = "Resource updated successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error updating resource: " . mysqli_error($conn);
        $_SESSION['message_type'] = "error";
    }
    
    mysqli_stmt_close($stmt);
    header("Location: career_resources.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $resource_id = intval($_POST['resource_id']);
    
    $sql = "DELETE FROM career_resources_tbl WHERE resource_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $resource_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['message'] = "Resource deleted successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error deleting resource: " . mysqli_error($conn);
        $_SESSION['message_type'] = "error";
    }
    
    mysqli_stmt_close($stmt);
    header("Location: career_resources.php");
    exit;
}

$resources_sql = "SELECT * FROM career_resources_tbl ORDER BY career_name ASC, display_order ASC, resource_id ASC";
$resources_result = mysqli_query($conn, $resources_sql);

$careers_sql = "SELECT DISTINCT career_name FROM career_resources_tbl ORDER BY career_name ASC";
$careers_result = mysqli_query($conn, $careers_sql);
$unique_careers = [];
while ($row = mysqli_fetch_assoc($careers_result)) {
    $unique_careers[] = $row['career_name'];
}

$career_program_sql = "SELECT DISTINCT career_name FROM career_program_tbl WHERE is_active = 1 ORDER BY career_name ASC";
$career_program_result = mysqli_query($conn, $career_program_sql);
while ($row = mysqli_fetch_assoc($career_program_result)) {
    if (!in_array($row['career_name'], $unique_careers)) {
        $unique_careers[] = $row['career_name'];
    }
}
sort($unique_careers);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include_once __DIR__ . '/../partials/favicon.php'; ?>
    <title>Career Resources Management</title>
    <?php $activePage = 'career_resources'; ?>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/datatables.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <style>
        .resource-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-course {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-certification {
            background-color: #fff3e0;
            color: #e65100;
        }
        
        .badge-learning_path {
            background-color: #f3e5f5;
            color: #6a1b9a;
        }
        
        .badge-other {
            background-color: #e0f2f1;
            color: #00695c;
        }
        
        .badge-free {
            background-color: #c8e6c9;
            color: #2e7d32;
            margin-left: 5px;
        }
        
        .badge-active {
            background-color: #c8e6c9;
            color: #2e7d32;
        }
        
        .badge-inactive {
            background-color: #ffcdd2;
            color: #c62828;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input[type="text"],
        .form-group input[type="url"],
        .form-group input[type="number"],
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-group-inline {
            display: flex;
            gap: 15px;
        }
        
        .form-group-inline .form-group {
            flex: 1;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            margin-bottom: 0 !important;
            cursor: pointer;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-icon {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .btn-edit {
            background-color: #2196f3;
            color: white;
        }
        
        .btn-edit:hover {
            background-color: #1976d2;
        }
        
        .btn-delete {
            background-color: #f44336;
            color: white;
        }
        
        .btn-delete:hover {
            background-color: #d32f2f;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #333;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        
        .close:hover,
        .close:focus {
            color: #000;
        }
        
        .url-preview {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <input type="checkbox" id="sidebar-toggle">
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'header.php'; ?>
        
        <main>
            <div class="page-header">
                <div>
                    <h2><i class="las la-link"></i> Career Resources Management</h2>
                    <p>Manage learning resources, courses, and certifications for career recommendations</p>
                </div>
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i class="las la-plus"></i> Add Resource
                </button>
            </div>
            
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
                    <?php 
                    echo $_SESSION['message']; 
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                    ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="las la-list"></i> All Career Resources</h3>
                </div>
                <div class="card-body">
                    <table id="resourcesTable" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Career Name</th>
                                <th>Resource Name</th>
                                <th>Type</th>
                                <th>Provider</th>
                                <th>Free</th>
                                <th>Order</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($resource = mysqli_fetch_assoc($resources_result)): ?>
                                <tr>
                                    <td><?php echo $resource['resource_id']; ?></td>
                                    <td><?php echo htmlspecialchars($resource['career_name']); ?></td>
                                    <td>
                                        <a href="<?php echo htmlspecialchars($resource['resource_url']); ?>" 
                                           target="_blank" 
                                           rel="noopener noreferrer"
                                           style="color: #007bff; text-decoration: none;">
                                            <?php echo htmlspecialchars($resource['resource_name']); ?>
                                            <i class="las la-external-link-alt" style="font-size: 12px;"></i>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="resource-badge badge-<?php echo $resource['resource_type']; ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $resource['resource_type'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($resource['resource_provider'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($resource['is_free'] == 1): ?>
                                            <span class="resource-badge badge-free">FREE</span>
                                        <?php else: ?>
                                            <span style="color: #666;">Paid</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $resource['display_order']; ?></td>
                                    <td>
                                        <?php if ($resource['is_active'] == 1): ?>
                                            <span class="resource-badge badge-active">Active</span>
                                        <?php else: ?>
                                            <span class="resource-badge badge-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon btn-edit" onclick='openEditModal(<?php echo json_encode($resource); ?>)'>
                                                <i class="las la-edit"></i>
                                            </button>
                                            <button class="btn-icon btn-delete" onclick="openDeleteModal(<?php echo $resource['resource_id']; ?>, '<?php echo htmlspecialchars($resource['resource_name'], ENT_QUOTES); ?>')">
                                                <i class="las la-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="las la-plus-circle"></i> Add Career Resource</h3>
                <span class="close" onclick="closeAddModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label for="career_name">Career Name *</label>
                    <input type="text" id="career_name" name="career_name" list="careerList" required>
                    <datalist id="careerList">
                        <?php foreach ($unique_careers as $career): ?>
                            <option value="<?php echo htmlspecialchars($career); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                
                <div class="form-group">
                    <label for="resource_name">Resource Name *</label>
                    <input type="text" id="resource_name" name="resource_name" required placeholder="e.g., Complete Web Development Bootcamp">
                </div>
                
                <div class="form-group">
                    <label for="resource_url">Resource URL *</label>
                    <input type="url" id="resource_url" name="resource_url" required placeholder="https://example.com/resource">
                </div>
                
                <div class="form-group-inline">
                    <div class="form-group">
                        <label for="resource_type">Resource Type *</label>
                        <select id="resource_type" name="resource_type" required>
                            <option value="course">Course</option>
                            <option value="certification">Certification</option>
                            <option value="learning_path">Learning Path</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="resource_provider">Provider</label>
                        <input type="text" id="resource_provider" name="resource_provider" placeholder="e.g., Udemy, Coursera">
                    </div>
                </div>
                
                <div class="form-group-inline">
                    <div class="form-group">
                        <label for="display_order">Display Order</label>
                        <input type="number" id="display_order" name="display_order" value="0" min="0">
                    </div>
                    
                    <div class="form-group" style="flex: 2;">
                        <label style="visibility: hidden;">Options</label>
                        <div style="display: flex; gap: 20px; align-items: center; height: 42px;">
                            <div class="checkbox-group">
                                <input type="checkbox" id="is_free" name="is_free" value="1">
                                <label for="is_free">Free Resource</label>
                            </div>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="is_active" name="is_active" value="1" checked>
                                <label for="is_active">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 25px;">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="las la-save"></i> Add Resource
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="las la-edit"></i> Edit Career Resource</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit_resource_id" name="resource_id">
                
                <div class="form-group">
                    <label for="edit_career_name">Career Name *</label>
                    <input type="text" id="edit_career_name" name="career_name" list="careerList" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_resource_name">Resource Name *</label>
                    <input type="text" id="edit_resource_name" name="resource_name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_resource_url">Resource URL *</label>
                    <input type="url" id="edit_resource_url" name="resource_url" required>
                </div>
                
                <div class="form-group-inline">
                    <div class="form-group">
                        <label for="edit_resource_type">Resource Type *</label>
                        <select id="edit_resource_type" name="resource_type" required>
                            <option value="course">Course</option>
                            <option value="certification">Certification</option>
                            <option value="learning_path">Learning Path</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_resource_provider">Provider</label>
                        <input type="text" id="edit_resource_provider" name="resource_provider">
                    </div>
                </div>
                
                <div class="form-group-inline">
                    <div class="form-group">
                        <label for="edit_display_order">Display Order</label>
                        <input type="number" id="edit_display_order" name="display_order" value="0" min="0">
                    </div>
                    
                    <div class="form-group" style="flex: 2;">
                        <label style="visibility: hidden;">Options</label>
                        <div style="display: flex; gap: 20px; align-items: center; height: 42px;">
                            <div class="checkbox-group">
                                <input type="checkbox" id="edit_is_free" name="is_free" value="1">
                                <label for="edit_is_free">Free Resource</label>
                            </div>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" id="edit_is_active" name="is_active" value="1">
                                <label for="edit_is_active">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 25px;">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="las la-save"></i> Update Resource
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3><i class="las la-exclamation-triangle"></i> Confirm Delete</h3>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" id="delete_resource_id" name="resource_id">
                
                <p style="margin: 20px 0;">Are you sure you want to delete this resource?</p>
                <p id="delete_resource_name" style="font-weight: bold; color: #dc3545; margin-bottom: 20px;"></p>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="las la-trash"></i> Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/datatables.js"></script>
    <script>
        $(document).ready(function() {
            $('#resourcesTable').DataTable({
                order: [[1, 'asc'], [6, 'asc']],
                pageLength: 25,
                columnDefs: [
                    { orderable: false, targets: 8 }
                ]
            });
        });
        
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }
        
        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }
        
        function openEditModal(resource) {
            document.getElementById('edit_resource_id').value = resource.resource_id;
            document.getElementById('edit_career_name').value = resource.career_name;
            document.getElementById('edit_resource_name').value = resource.resource_name;
            document.getElementById('edit_resource_url').value = resource.resource_url;
            document.getElementById('edit_resource_type').value = resource.resource_type;
            document.getElementById('edit_resource_provider').value = resource.resource_provider || '';
            document.getElementById('edit_display_order').value = resource.display_order;
            document.getElementById('edit_is_free').checked = resource.is_free == 1;
            document.getElementById('edit_is_active').checked = resource.is_active == 1;
            
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        function openDeleteModal(resourceId, resourceName) {
            document.getElementById('delete_resource_id').value = resourceId;
            document.getElementById('delete_resource_name').textContent = resourceName;
            document.getElementById('deleteModal').style.display = 'block';
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>

