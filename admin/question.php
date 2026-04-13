<?php
session_start();
if (!isset($_SESSION['school_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

require '../db/dbconn.php';

$assessment_id = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : 0;

if ($assessment_id <= 0) {
    header("Location: assessment.php");
    exit;
}

$assessment_sql = "SELECT assessment_name FROM assessment_tbl WHERE assessment_id = ?";
$stmt = mysqli_prepare($conn, $assessment_sql);
mysqli_stmt_bind_param($stmt, "i", $assessment_id);
mysqli_stmt_execute($stmt);
$assessment_result = mysqli_stmt_get_result($stmt);
$assessment = mysqli_fetch_assoc($assessment_result);

if (!$assessment) {
    header("Location: assessment.php");
    exit;
}



if (isset($_POST['action']) && $_POST['action'] == 'import') {
    $response = ['success' => false, 'message' => '', 'imported' => 0, 'errors' => []];
    
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $file = $_FILES['csv_file'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($file_extension != 'csv') {
            $response['message'] = "Please upload a CSV file only.";
        } else {
            if (($handle = fopen($file['tmp_name'], "r")) !== FALSE) {
                $imported_count = 0;
                $errors = [];
                $line_number = 1;
                
                fgetcsv($handle);
                $line_number++;
                
                while (($data = fgetcsv($handle)) !== FALSE) {
                    if (count($data) >= 1) {
                        $question_text = trim($data[0]);
                        
                        if (!empty($question_text)) {
                            $check_sql = "SELECT question_id FROM question_tbl WHERE question_text = ? AND assessment_id = ?";
                            $check_stmt = mysqli_prepare($conn, $check_sql);
                            mysqli_stmt_bind_param($check_stmt, "si", $question_text, $assessment_id);
                            mysqli_stmt_execute($check_stmt);
                            $check_result = mysqli_stmt_get_result($check_stmt);
                            
                            if (mysqli_num_rows($check_result) == 0) {
                                $insert_sql = "INSERT INTO question_tbl (assessment_id, question_text) VALUES (?, ?)";
                                $insert_stmt = mysqli_prepare($conn, $insert_sql);
                                mysqli_stmt_bind_param($insert_stmt, "is", $assessment_id, $question_text);
                                
                                if (mysqli_stmt_execute($insert_stmt)) {
                                    $imported_count++;
                                } else {
                                    $errors[] = "Line $line_number: " . mysqli_error($conn);
                                }
                            } else {
                                $errors[] = "Line $line_number: Question already exists";
                            }
                        } else {
                            $errors[] = "Line $line_number: Empty question text";
                        }
                    } else {
                        $errors[] = "Line $line_number: Invalid data format";
                    }
                    $line_number++;
                }
                fclose($handle);
                
                $response['success'] = true;
                $response['imported'] = $imported_count;
                $response['errors'] = $errors;
                $response['message'] = "Import completed. $imported_count questions imported successfully.";
                
                if (!empty($errors)) {
                    $response['message'] .= " " . count($errors) . " errors occurred.";
                }
            } else {
                $response['message'] = "Error reading the file.";
            }
        }
    } else {
        $response['message'] = "Please select a file to upload.";
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

if (isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];

    switch ($_POST['action']) {
        case 'add':
            if (isset($_POST['question_text'])) {
                $question_text = mysqli_real_escape_string($conn, $_POST['question_text']);
                
                $insert_sql = "INSERT INTO question_tbl (assessment_id, question_text) VALUES (?, ?)";
                $stmt = mysqli_prepare($conn, $insert_sql);
                mysqli_stmt_bind_param($stmt, "is", $assessment_id, $question_text);
                
                if (mysqli_stmt_execute($stmt)) {
                    $response['success'] = true;
                    $response['message'] = "Question added successfully!";
                } else {
                    $response['message'] = "Error adding question: " . mysqli_error($conn);
                }
            }
            break;

        case 'edit':
            if (isset($_POST['id'], $_POST['question_text'])) {
                $id = intval($_POST['id']);
                $question_text = mysqli_real_escape_string($conn, $_POST['question_text']);
                
                $sql = "UPDATE question_tbl SET question_text = ? WHERE question_id = ? AND assessment_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "sii", $question_text, $id, $assessment_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $response['success'] = true;
                    $response['message'] = "Question updated successfully!";
                } else {
                    $response['message'] = "Error updating question: " . mysqli_error($conn);
                }
            }
            break;

        case 'delete':
            if (isset($_POST['id'])) {
                $id = intval($_POST['id']);
                
                $delete_results_sql = "DELETE FROM assessment_results_tbl WHERE question_id = ?";
                $stmt = mysqli_prepare($conn, $delete_results_sql);
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                
                $delete_sql = "DELETE FROM question_tbl WHERE question_id = ? AND assessment_id = ?";
                $stmt = mysqli_prepare($conn, $delete_sql);
                mysqli_stmt_bind_param($stmt, "ii", $id, $assessment_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $response['success'] = true;
                    $response['message'] = "Question deleted successfully!";
                } else {
                    $response['message'] = "Error deleting question: " . mysqli_error($conn);
                }
            }
            break;

        case 'delete_selected':
            if (isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])) {
                $selected_ids = array_map('intval', $_POST['selected_ids']);
                $deleted_count = 0;
                
                foreach ($selected_ids as $id) {
                    $delete_results_sql = "DELETE FROM assessment_results_tbl WHERE question_id = ?";
                    $stmt = mysqli_prepare($conn, $delete_results_sql);
                    mysqli_stmt_bind_param($stmt, "i", $id);
                    mysqli_stmt_execute($stmt);
                    
                    $delete_sql = "DELETE FROM question_tbl WHERE question_id = ? AND assessment_id = ?";
                    $stmt = mysqli_prepare($conn, $delete_sql);
                    mysqli_stmt_bind_param($stmt, "ii", $id, $assessment_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $deleted_count++;
                    }
                }
                
                $response['success'] = true;
                $response['message'] = "$deleted_count questions deleted successfully!";
            } else {
                $response['message'] = "No questions selected for deletion.";
            }
            break;

        case 'delete_all':
            $delete_results_sql = "DELETE ar FROM assessment_results_tbl ar 
                                 INNER JOIN question_tbl q ON ar.question_id = q.question_id 
                                 WHERE q.assessment_id = ?";
            $stmt = mysqli_prepare($conn, $delete_results_sql);
            mysqli_stmt_bind_param($stmt, "i", $assessment_id);
            mysqli_stmt_execute($stmt);
            
            $delete_all_sql = "DELETE FROM question_tbl WHERE assessment_id = ?";
            $stmt = mysqli_prepare($conn, $delete_all_sql);
            mysqli_stmt_bind_param($stmt, "i", $assessment_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $response['success'] = true;
                $response['message'] = "All questions deleted successfully!";
            } else {
                $response['message'] = "Error deleting questions: " . mysqli_error($conn);
            }
            break;

        case 'get':
            if (isset($_POST['id'])) {
                $id = intval($_POST['id']);
                $sql = "SELECT * FROM question_tbl WHERE question_id = ? AND assessment_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ii", $id, $assessment_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $question = mysqli_fetch_assoc($result);
                
                if ($question) {
                    $response['success'] = true;
                    $response['data'] = $question;
                } else {
                    $response['message'] = "Question not found!";
                }
            }
            break;
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

if (isset($_GET['delete_question'])) {
    $delete_id = intval($_GET['delete_question']);
    $delete_sql = "DELETE FROM question_tbl WHERE question_id = $delete_id AND assessment_id = $assessment_id";
    if (mysqli_query($conn, $delete_sql)) {
        $success = 'Question deleted successfully!';
    } else {
        $error = 'Failed to delete question.';
    }
}

$questions_sql = "SELECT * FROM question_tbl WHERE assessment_id = ? ORDER BY question_id ASC";
$stmt = mysqli_prepare($conn, $questions_sql);
mysqli_stmt_bind_param($stmt, "i", $assessment_id);
mysqli_stmt_execute($stmt);
$questions_result = mysqli_stmt_get_result($stmt);

$question_count = mysqli_num_rows($questions_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include_once __DIR__ . '/../partials/favicon.php'; ?>
    <title>Question Management - Tragabay</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.lineicons.com/2.0/LineIcons.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/datatables.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
</head>

<style>
  
    .container {
        padding: 30px 30px 0 30px;
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

    .warning {
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
    }

    .form-group input, .form-group textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
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
        transition: all 0.3s ease;
    }

    .btn-primary {
        background-color: #007bff;
        color: white;
    }

    .btn-primary:hover {
        background-color: #0056b3;
    }

    .btn-danger {
        background-color: #dc3545;
        color: white;
    }

    .btn-danger:hover {
        background-color: #c82333;
    }

    .btn-success {
        background-color: #28a745;
        color: white;
    }

    .btn-success:hover {
        background-color: #218838;
    }

    .btn-secondary {
        background-color: #6c757d;
        color: white;
    }

    .btn-secondary:hover {
        background-color: #5a6268;
    }

    .btn-info {
        background-color: #17a2b8;
        color: white;
    }

    .btn-info:hover {
        background-color: #138496;
    }

    .btn-warning {
        background-color: #ffc107;
        color: #212529;
    }

    .btn-warning:hover {
        background-color: #e0a800;
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

    th {
        background-color: #f8f9fa;
        font-weight: 600;
    }

    tr:hover {
        background-color: #f5f5f5;
    }

    .action-buttons {
        display: flex;
        gap: 5px;
    }

    .button-group {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .bulk-actions {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .bulk-actions-left {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .bulk-actions-right {
        display: flex;
        gap: 10px;
    }

    .checkbox-wrapper {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .checkbox-wrapper input[type="checkbox"] {
        width: auto;
        margin: 0;
        cursor: pointer;
    }

    .checkbox-wrapper label {
        margin: 0;
        cursor: pointer;
        font-weight: normal;
    }

    .stats-bar {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .stats-info {
        display: flex;
        gap: 20px;
        align-items: center;
    }

    .stat-item {
        display: flex;
        align-items: center;
        gap: 5px;
        color: #6c757d;
    }

    .stat-number {
        font-weight: bold;
        color: #495057;
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
    }

    .modal-content {
        background-color: #fff;
        margin: 10% auto;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        width: 90%;
        max-width: 500px;
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

    .modal-form input[type="text"], .modal-form textarea, .modal-form input[type="file"] {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
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
        color: #333;
    }

    .add-button, .import-button, .delete-selected-button {
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.3s ease;
    }

    .add-button {
        background-color: #28a745;
    }

    .add-button:hover {
        background-color: #218838;
    }

    .import-button {
        background-color: #17a2b8;
    }

    .import-button:hover {
        background-color: #138496;
    }

    .delete-selected-button {
        background-color: #dc3545;
    }

    .delete-selected-button:hover {
        background-color: #c82333;
    }

    .back-button {
        background-color: #6c757d;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        text-decoration: none;
        display: inline-block;
        margin-bottom: 20px;
        margin-top: 50px;
        
    }

    .back-button:hover {
        background-color: #5a6268;
    }

    .csv-template {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 20px;
    }

    .csv-template h4 {
        margin-top: 0;
        color: #495057;
    }

    .csv-template p {
        margin-bottom: 10px;
        color: #6c757d;
    }

    .download-template {
        background-color: #6f42c1;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        text-decoration: none;
        display: inline-block;
    }

    .download-template:hover {
        background-color: #5a32a3;
    }

    .no-questions {
        text-align: center;
        padding: 40px;
        color: #6c757d;
        font-style: italic;
    }

    .checkbox-cell {
        width: 40px;
        text-align: center;
    }

    .question-checkbox {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }

    .file-upload-area {
        border: 2px dashed #ccc;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        background-color: #f9f9f9;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 120px;
    }

    .file-upload-area:hover,
    .file-upload-area.dragover {
        border-color: #007bff;
        background-color: #e9ecef;
    }

    .file-upload-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #6c757d;
    }

    .file-upload-content i {
        font-size: 48px;
        margin-bottom: 10px;
        color: #007bff;
    }

    .file-upload-content p {
        margin: 5px 0;
        font-size: 14px;
    }

    .file-info {
        display: flex;
        align-items: center;
        background-color: #e9ecef;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 8px 12px;
        margin-top: 10px;
        color: #495057;
    }

    .file-info i {
        margin-right: 8px;
        color: #28a745;
    }

    .file-info span {
        flex-grow: 1;
        font-weight: 500;
    }

    .file-info .btn-sm {
        padding: 4px 8px;
        font-size: 12px;
    }

    .btn-outline-primary {
        color: #007bff;
        border-color: #007bff;
        background-color: transparent;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-outline-primary:hover {
        color: #fff;
        background-color: #007bff;
    }

    .btn-outline-danger {
        color: #dc3545;
        border-color: #dc3545;
        background-color: transparent;
        padding: 4px 8px;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-outline-danger:hover {
        color: #fff;
        background-color: #dc3545;
    }

    .dataTables_wrapper {
        margin-top: 20px;
    }

    .dataTables_filter {
        margin-bottom: 15px;
    }

    .dataTables_filter input {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-left: 8px;
        font-size: 14px;
    }

    .dataTables_length {
        margin-bottom: 15px;
    }

    .dataTables_length select {
        padding: 6px 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin: 0 8px;
        font-size: 14px;
    }

    .dataTables_info {
        margin-top: 15px;
        font-size: 14px;
        color: #666;
    }

    .dataTables_paginate {
        margin-top: 15px;
    }

    .dataTables_paginate .paginate_button {
        padding: 8px 12px;
        margin: 0 2px;
        border: 1px solid #ddd;
        background: #fff;
        color: #333;
        cursor: pointer;
        border-radius: 4px;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .dataTables_paginate .paginate_button:hover {
        background: #f8f9fa;
        border-color: #007bff;
        color: #007bff;
    }

    .dataTables_paginate .paginate_button.current {
        background: #007bff;
        border-color: #007bff;
        color: #fff;
    }

    .dataTables_paginate .paginate_button.disabled {
        color: #999;
        cursor: not-allowed;
        background: #f8f9fa;
    }

    .dt-buttons {
        margin-bottom: 15px;
    }

    .dt-button {
        padding: 8px 16px;
        margin-right: 8px;
        border: 1px solid #ddd;
        background: #fff;
        color: #333;
        cursor: pointer;
        border-radius: 4px;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .dt-button:hover {
        background: #f8f9fa;
        border-color: #007bff;
        color: #007bff;
    }

    .dt-button.btn-success {
        background: #28a745;
        border-color: #28a745;
        color: #fff;
    }

    .dt-button.btn-success:hover {
        background: #218838;
        border-color: #1e7e34;
    }

    .dt-button.btn-danger {
        background: #dc3545;
        border-color: #dc3545;
        color: #fff;
    }

    .dt-button.btn-danger:hover {
        background: #c82333;
        border-color: #bd2130;
    }

    .dt-button.btn-info {
        background: #17a2b8;
        border-color: #17a2b8;
        color: #fff;
    }

    .dt-button.btn-info:hover {
        background: #138496;
        border-color: #117a8b;
    }

    #myTable {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    #myTable thead th {
        background: #f8f9fa;
        padding: 12px 8px;
        text-align: left;
        font-weight: 600;
        border-bottom: 2px solid #dee2e6;
        color: #495057;
    }

    #myTable tbody td {
        padding: 12px 8px;
        border-bottom: 1px solid #dee2e6;
        vertical-align: middle;
    }

    #myTable tbody tr:hover {
        background-color: #f8f9fa;
    }

    @media (max-width: 768px) {
        .dt-buttons {
            text-align: center;
        }
        
        .dt-button {
            margin-bottom: 8px;
            display: inline-block;
        }
        
        .dataTables_filter {
            text-align: center;
        }
        
        .dataTables_length {
            text-align: center;
        }
    }
</style>

<body>
    <?php include 'header.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <a href="assessment.php" class="back-button">
                <i class="las la-arrow-left"></i> Back to Assessments
            </a>
            
            <h1>Question Management - <?php echo htmlspecialchars($assessment['assessment_name']); ?></h1>
            
            <?php if (isset($success)): ?>
                <div class="message">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="message error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div class="stats-bar">
                <div class="stats-info">
                    <div class="stat-item">
                        <i class="las la-question-circle"></i>
                        <span>Total Questions: <span class="stat-number"><?php echo $question_count; ?></span></span>
                    </div>
                    <div class="stat-item">
                        <i class="las la-clipboard-list"></i>
                        <span>Assessment: <span class="stat-number"><?php echo htmlspecialchars($assessment['assessment_name']); ?></span></span>
                    </div>
                </div>
            </div>
            
            <div class="button-group">
                <button class="add-button" onclick="openAddModal()">
                    <i class="las la-plus"></i> Add New Question
                </button>
                <button class="import-button" onclick="openImportModal()">
                    <i class="las la-upload"></i> Import Questions
                </button>
            </div>


            <?php if ($question_count > 0): ?>
            <div class="bulk-actions" id="bulkActions" style="display: none;">
                <div class="bulk-actions-left">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                        <label for="selectAll">Select All</label>
                    </div>
                    <span id="selectedCount">0 questions selected</span>
                </div>
                <div class="bulk-actions-right">
                    <button class="delete-selected-button" onclick="openDeleteSelectedModal()" id="deleteSelectedBtn" disabled>
                        <i class="las la-trash"></i> Delete Selected
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <h2>Questions List</h2>
            <?php if ($question_count > 0): ?>
            <table id="myTable" >
                <thead>
                    <tr>
                        <th class="checkbox-cell">
                            <input type="checkbox" id="headerCheckbox" onchange="toggleSelectAll()">
                        </th>
                        <th>#</th>
                        <th>Question</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $question_number = 1;
                    while ($question = mysqli_fetch_assoc($questions_result)): 
                    ?>
                    <tr>
                        <td class="checkbox-cell">
                            <input type="checkbox" class="question-checkbox" value="<?php echo $question['question_id']; ?>" onchange="updateBulkActions()">
                        </td>
                        <td class="question-number"><?php echo $question_number; ?></td>
                        <td class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></td>
                        <td class="action-buttons">
                            <button class="btn btn-primary" onclick="openEditModal(<?php echo $question['question_id']; ?>)">
                                <i class="las la-edit"></i> Edit
                            </button>
                        </td>
                    </tr>
                    <?php 
                    $question_number++;
                    endwhile; 
                    ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-questions">
                <i class="las la-inbox" style="font-size: 48px; color: #dee2e6;"></i>
                <h3>No Questions Found</h3>
                <p>This assessment doesn't have any questions yet. Add some questions to get started!</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeAddModal()">&times;</span>
            <h2 class="modal-title">Add New Question</h2>
            <form id="addForm" class="modal-form" onsubmit="handleAddSubmit(event)">
                <div class="form-group">
                    <label for="add_question_text">Question Text:</label>
                    <textarea id="add_question_text" name="question_text" required placeholder="Enter your question here..."></textarea>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-success">
                        <i class="las la-plus"></i> Add Question
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div id="importModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeImportModal()">&times;</span>
            <h2 class="modal-title">Import Questions from CSV</h2>
            <form id="importForm" class="modal-form" enctype="multipart/form-data" onsubmit="handleImportSubmit(event)">
                <div class="form-group">
                    <label for="csv_file">Upload CSV File:</label>
                    <div class="file-upload-area" id="fileUploadArea">
                        <div class="file-upload-content">
                            <i class="las la-cloud-upload-alt"></i>
                            <p>Drag and drop your CSV file here</p>
                            <p>or</p>
                            <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('csv_file').click()">
                                Choose File
                            </button>
                        </div>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv" required style="display: none;">
                    </div>
                    <div id="fileInfo" class="file-info" style="display: none;">
                        <i class="las la-file-csv"></i>
                        <span id="fileName"></span>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFile()">
                            <i class="las la-times"></i>
                        </button>
                    </div>
                    <small style="color: #6c757d; margin-top: 10px; display: block;">
                        <strong>CSV Format:</strong> First column should contain the question text. First row is treated as header.
                    </small>
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn btn-info" id="importBtn" disabled>
                        <i class="las la-upload"></i> Import Questions
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeImportModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeEditModal()">&times;</span>
            <h2 class="modal-title">Edit Question</h2>
            <form id="editForm" class="modal-form" onsubmit="handleEditSubmit(event)">
                <input type="hidden" id="edit_id" name="id">
                <div class="form-group">
                    <label for="edit_question_text">Question Text:</label>
                    <textarea id="edit_question_text" name="question_text" required></textarea>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Update</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeDeleteModal()">&times;</span>
            <h2 class="modal-title">Delete Question</h2>
            <p>Are you sure you want to delete this question?</p>
            <div class="btn-group">
                <button type="button" class="btn btn-danger" onclick="confirmDelete()">Delete</button>
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
            </div>
        </div>
    </div>

    <div id="deleteSelectedModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeDeleteSelectedModal()">&times;</span>
            <h2 class="modal-title">Delete Selected Questions</h2>
            <p>Are you sure you want to delete the selected questions?</p>
            <p><strong>This action cannot be undone.</strong></p>
            <div class="btn-group">
                <button type="button" class="btn btn-danger" onclick="confirmDeleteSelected()">
                    <i class="las la-trash"></i> Delete Selected
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeDeleteSelectedModal()">Cancel</button>
            </div>
        </div>
    </div>

    <div id="deleteAllModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeDeleteAllModal()">&times;</span>
            <h2 class="modal-title">Delete All Questions</h2>
            <p><strong>Warning:</strong> This action will permanently delete all <?php echo $question_count; ?> questions from this assessment.</p>
            <p>This action cannot be undone. Are you sure you want to continue?</p>
            <div class="btn-group">
                <button type="button" class="btn btn-danger" onclick="confirmDeleteAll()">
                    <i class="las la-trash"></i> Delete All Questions
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeDeleteAllModal()">Cancel</button>
            </div>
        </div>
    </div>
 
    <?php 
    $activePage = 'assessment';
    include 'sidebar.php'; 
    ?>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    
    <script>
        $(document).ready(function() {
            if ($.fn.DataTable.isDataTable('#myTable')) {
                console.log('DataTable already initialized by footer.php');
            } else {
                if (typeof $.fn.DataTable !== 'undefined') {
                    $('#myTable').DataTable({
                        "paging": true,
                        "lengthChange": true,
                        "searching": true,
                        "ordering": true,
                        "info": true,
                        "autoWidth": false,
                        "responsive": true
                    });
                } else {
                    console.error('DataTables library not loaded!');
                }
            }
        });
    </script>

    <script>
        let currentDeleteId = null;

        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
            document.getElementById('addForm').reset();
        }

        function openImportModal() {
            document.getElementById('importModal').style.display = 'block';
        }

        function closeImportModal() {
            document.getElementById('importModal').style.display = 'none';
            document.getElementById('importForm').reset();
            document.getElementById('fileUploadArea').style.display = 'flex';
            document.getElementById('fileInfo').style.display = 'none';
            document.getElementById('importBtn').disabled = true;
        }

        function openEditModal(id) {
            fetch('question.php?assessment_id=<?php echo $assessment_id; ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get&id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('edit_id').value = data.data.question_id;
                    document.getElementById('edit_question_text').value = data.data.question_text;
                    document.getElementById('editModal').style.display = 'block';
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error fetching question data');
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

        function openDeleteSelectedModal() {
            document.getElementById('deleteSelectedModal').style.display = 'block';
        }

        function closeDeleteSelectedModal() {
            document.getElementById('deleteSelectedModal').style.display = 'none';
        }

        function openDeleteAllModal() {
            document.getElementById('deleteAllModal').style.display = 'block';
        }

        function closeDeleteAllModal() {
            document.getElementById('deleteAllModal').style.display = 'none';
        }

        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('.question-checkbox');
            const headerCheckbox = document.getElementById('headerCheckbox');
            const selectAllCheckbox = document.getElementById('selectAll');
            
            const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
            
            const newState = !allChecked;
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = newState;
            });
            
            headerCheckbox.checked = newState;
            headerCheckbox.indeterminate = false;
            selectAllCheckbox.checked = newState;
            
            updateBulkActions();
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.question-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
            const headerCheckbox = document.getElementById('headerCheckbox');
            const selectAllCheckbox = document.getElementById('selectAll');
            
            const checkedCount = checkboxes.length;
            const totalCheckboxes = document.querySelectorAll('.question-checkbox').length;
            
            if (checkedCount > 0) {
                bulkActions.style.display = 'flex';
                selectedCount.textContent = `${checkedCount} question${checkedCount > 1 ? 's' : ''} selected`;
                deleteSelectedBtn.disabled = false;
                
                headerCheckbox.checked = checkedCount === totalCheckboxes;
                headerCheckbox.indeterminate = checkedCount > 0 && checkedCount < totalCheckboxes;
                selectAllCheckbox.checked = checkedCount === totalCheckboxes;
            } else {
                bulkActions.style.display = 'none';
                headerCheckbox.checked = false;
                headerCheckbox.indeterminate = false;
                selectAllCheckbox.checked = false;
            }
        }

        function handleAddSubmit(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            formData.append('action', 'add');

            fetch('question.php?assessment_id=<?php echo $assessment_id; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error adding question');
            });
        }

        function handleImportSubmit(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            formData.append('action', 'import');

            fetch('question.php?assessment_id=<?php echo $assessment_id; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let message = data.message;
                    if (data.errors && data.errors.length > 0) {
                        message += '\n\nErrors:\n' + data.errors.slice(0, 5).join('\n');
                        if (data.errors.length > 5) {
                            message += '\n... and ' + (data.errors.length - 5) + ' more errors';
                        }
                    }
                    alert(message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error importing questions');
            });
        }

        function handleEditSubmit(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            formData.append('action', 'edit');

            fetch('question.php?assessment_id=<?php echo $assessment_id; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating question');
            });
        }

        function confirmDelete() {
            if (currentDeleteId) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', currentDeleteId);

                fetch('question.php?assessment_id=<?php echo $assessment_id; ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting question');
                });
            }
        }

        function confirmDeleteSelected() {
            const checkboxes = document.querySelectorAll('.question-checkbox:checked');
            const selectedIds = Array.from(checkboxes).map(cb => cb.value);
            
            if (selectedIds.length > 0) {
                const formData = new FormData();
                formData.append('action', 'delete_selected');
                selectedIds.forEach(id => {
                    formData.append('selected_ids[]', id);
                });

                fetch('question.php?assessment_id=<?php echo $assessment_id; ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting selected questions');
                });
            }
        }

        function confirmDeleteAll() {
            const formData = new FormData();
            formData.append('action', 'delete_all');

            fetch('question.php?assessment_id=<?php echo $assessment_id; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting all questions');
            });
        }

        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }

        const fileUploadArea = document.getElementById('fileUploadArea');
        const fileInput = document.getElementById('csv_file');
        const fileNameSpan = document.getElementById('fileName');
        const fileInfo = document.getElementById('fileInfo');
        const importBtn = document.getElementById('importBtn');

        fileUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            fileUploadArea.classList.add('dragover');
        });

        fileUploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
        });

        fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                const file = files[0];
                if (file.type === 'text/csv' || file.name.endsWith('.csv')) {
                    fileInput.files = files;
                    handleFileSelect(file);
                } else {
                    alert('Please select a CSV file only.');
                }
            }
        });

        fileInput.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                handleFileSelect(file);
            } else {
                resetFileUpload();
            }
        });

        function handleFileSelect(file) {
            fileNameSpan.textContent = file.name;
            fileUploadArea.style.display = 'none';
            fileInfo.style.display = 'flex';
            importBtn.disabled = false;
        }

        function removeFile() {
            fileInput.value = '';
            resetFileUpload();
        }

        function resetFileUpload() {
            fileNameSpan.textContent = '';
            fileUploadArea.style.display = 'flex';
            fileInfo.style.display = 'none';
            importBtn.disabled = true;
        }

    </script>
</body>
</html> 