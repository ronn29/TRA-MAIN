<?php
session_start();
if (!isset($_SESSION['school_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

require '../db/dbconn.php';
require 'includes/model_predict.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Career Resources</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .success {
            color: #28a745;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .error {
            color: #dc3545;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .info {
            color: #004085;
            background-color: #cce5ff;
            border: 1px solid #b8daff;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        h1, h2, h3 {
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        table th, table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success {
            background-color: #d4edda;
            color: #28a745;
        }
        .badge-danger {
            background-color: #f8d7da;
            color: #dc3545;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        code {
            background-color: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <h1>🧪 Career Resources - Test & Verification</h1>
    
    <div class="info">
        <strong>ℹ️ Purpose:</strong> This page verifies that the career resources feature is working correctly.
    </div>
    
    <?php
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'career_resources_tbl'");
    $table_exists = mysqli_num_rows($table_check) > 0;
    ?>
    
    <div class="card">
        <h2>✓ Test 1: Database Table</h2>
        <?php if ($table_exists): ?>
            <div class="success">
                ✅ <strong>PASS:</strong> Table <code>career_resources_tbl</code> exists.
            </div>
        <?php else: ?>
            <div class="error">
                ❌ <strong>FAIL:</strong> Table <code>career_resources_tbl</code> does not exist.
                <br><br>
                <strong>Solution:</strong> Run the SQL script: <code>db/create_career_resources_table.sql</code>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($table_exists): ?>
        
        <?php
        $count_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM career_resources_tbl");
        $count_row = mysqli_fetch_assoc($count_result);
        $total_resources = $count_row['total'];
        
        $active_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM career_resources_tbl WHERE is_active = 1");
        $active_row = mysqli_fetch_assoc($active_result);
        $active_resources = $active_row['total'];
        ?>
        
        <div class="card">
            <h2>✓ Test 2: Resource Count</h2>
            <?php if ($total_resources > 0): ?>
                <div class="success">
                    ✅ <strong>PASS:</strong> Found <strong><?php echo $total_resources; ?></strong> total resources 
                    (<strong><?php echo $active_resources; ?></strong> active).
                </div>
            <?php else: ?>
                <div class="error">
                    ⚠️ <strong>WARNING:</strong> No resources found in the database.
                    <br><br>
                    <strong>Solution:</strong> The sample data may not have been inserted. Run the INSERT statements from the SQL file.
                </div>
            <?php endif; ?>
        </div>
        
        <?php
        $test_careers = ['Web Developer', 'Software Engineer', 'Data Scientist'];
        $fetched_resources = getCareerResources($conn, $test_careers);
        ?>
        
        <div class="card">
            <h2>✓ Test 3: Function Test - <code>getCareerResources()</code></h2>
            <?php if (!empty($fetched_resources)): ?>
                <div class="success">
                    ✅ <strong>PASS:</strong> Successfully fetched resources for test careers.
                </div>
                
                <h3>Sample Resources Retrieved:</h3>
                <?php foreach ($fetched_resources as $career => $resources): ?>
                    <h4><?php echo htmlspecialchars($career); ?> (<?php echo count($resources); ?> resources)</h4>
                    <ul>
                        <?php foreach ($resources as $name => $url): ?>
                            <li>
                                <a href="<?php echo htmlspecialchars($url); ?>" target="_blank">
                                    <?php echo htmlspecialchars($name); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="error">
                    ❌ <strong>FAIL:</strong> Function returned no resources.
                    <br><br>
                    <strong>Possible Issues:</strong>
                    <ul>
                        <li>No resources marked as active (is_active = 1)</li>
                        <li>Career names don't match test careers</li>
                        <li>Function error (check PHP error logs)</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        
        <?php
        $careers_sql = "SELECT DISTINCT career_name, COUNT(*) as resource_count 
                       FROM career_resources_tbl 
                       WHERE is_active = 1
                       GROUP BY career_name 
                       ORDER BY resource_count DESC";
        $careers_result = mysqli_query($conn, $careers_sql);
        ?>
        
        <div class="card">
            <h2>✓ Test 4: Career Coverage</h2>
            <p>This shows which careers have resources and how many:</p>
            
            <table>
                <thead>
                    <tr>
                        <th>Career Name</th>
                        <th>Active Resources</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($career = mysqli_fetch_assoc($careers_result)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($career['career_name']); ?></td>
                            <td><?php echo $career['resource_count']; ?></td>
                            <td>
                                <?php if ($career['resource_count'] >= 3): ?>
                                    <span class="badge badge-success">Good Coverage</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Add More</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <?php
        $quality_issues = [];
        
        $empty_url_check = mysqli_query($conn, "SELECT COUNT(*) as total FROM career_resources_tbl WHERE resource_url = '' OR resource_url IS NULL");
        $empty_url_row = mysqli_fetch_assoc($empty_url_check);
        if ($empty_url_row['total'] > 0) {
            $quality_issues[] = $empty_url_row['total'] . " resources have empty URLs";
        }
        
        $empty_name_check = mysqli_query($conn, "SELECT COUNT(*) as total FROM career_resources_tbl WHERE resource_name = '' OR resource_name IS NULL");
        $empty_name_row = mysqli_fetch_assoc($empty_name_check);
        if ($empty_name_row['total'] > 0) {
            $quality_issues[] = $empty_name_row['total'] . " resources have empty names";
        }
        ?>
        
        <div class="card">
            <h2>✓ Test 5: Data Quality</h2>
            <?php if (empty($quality_issues)): ?>
                <div class="success">
                    ✅ <strong>PASS:</strong> All resources have valid data.
                </div>
            <?php else: ?>
                <div class="error">
                    ⚠️ <strong>WARNING:</strong> Data quality issues found:
                    <ul>
                        <?php foreach ($quality_issues as $issue): ?>
                            <li><?php echo $issue; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        
    <?php endif; ?>
    
    <div class="card">
        <h2>📋 Next Steps</h2>
        <ol>
            <li>✅ All tests passed? You're ready to use the feature!</li>
            <li>📝 Add more resources via <a href="career_resources.php">Career Resources Management</a></li>
            <li>👨‍🎓 Test student view by logging in as a student and viewing career predictions</li>
            <li>🔄 Regularly update resources to keep them current</li>
        </ol>
    </div>
    
    <a href="career_resources.php" class="btn">Go to Career Resources Management</a>
    <a href="index.php" class="btn" style="background-color: #6c757d;">Back to Dashboard</a>
    
</body>
</html>

