<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeEditModal()">&times;</span>
        <h2 class="modal-title">Edit Assessment</h2>
        <form id="editForm" class="modal-form" onsubmit="handleEditSubmit(event)">
            <input type="hidden" id="edit_id" name="id">
            <div class="form-group">
                <label for="edit_assessment_name">Assessment Name:</label>
                <input type="text" id="edit_assessment_name" name="assessment_name" required>
            </div>
            <div class="form-group">
                <label for="edit_description">Description:</label>
                <textarea id="edit_description" name="description" rows="4" placeholder="Enter a description of what this assessment evaluates..."></textarea>
            </div>
            <div class="form-group">
                <label for="edit_program_id">Program (Optional):</label>
                <select id="edit_program_id" name="program_id" class="form-control">
                    <option value="">General Assessment (All Programs)</option>
                    <?php
                    require '../db/dbconn.php';
                    $programs_sql = "SELECT program_id, program_code, program_name FROM program_tbl ORDER BY program_name";
                    $programs_result = mysqli_query($conn, $programs_sql);
                    while ($program = mysqli_fetch_assoc($programs_result)): ?>
                        <option value="<?php echo $program['program_id']; ?>">
                            <?php echo htmlspecialchars($program['program_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <small style="color: #666; font-size: 12px;">Leave blank for general assessment available to all students, or select a specific program.</small>
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
        <h2 class="modal-title">Delete Assessment</h2>
        <p>Are you sure you want to delete this assessment? This will also delete all associated questions.</p>
        <div class="btn-group">
            <button type="button" class="btn btn-danger" onclick="confirmDelete()">Delete</button>
            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
        </div>
    </div>
</div> 