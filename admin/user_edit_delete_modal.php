<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeEditModal()">&times;</span>
        <h2 class="modal-title">Edit User</h2>
        <form id="editForm" class="modal-form" onsubmit="handleEditSubmit(event)">
            <input type="hidden" id="edit_id" name="id">
            <input type="hidden" id="edit_role" name="role">
            
            <div class="form-group">
                <label for="edit_school_id">School ID:</label>
                <input type="text" id="edit_school_id" name="school_id" required>
            </div>
            
            <div class="form-group">
                <label for="edit_email">Email:</label>
                <input type="email" id="edit_email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="edit_first_name">First Name:</label>
                <input type="text" id="edit_first_name" name="first_name" required>
            </div>
            
            <div class="form-group">
                <label for="edit_middle_name">Middle Name:</label>
                <input type="text" id="edit_middle_name" name="middle_name">
            </div>
            
            <div class="form-group">
                <label for="edit_last_name">Last Name:</label>
                <input type="text" id="edit_last_name" name="last_name" required>
            </div>
            
            <div id="editStudentFields" style="display: none;">
                <div class="form-group">
                    <label for="edit_program">Program:</label>
                    <select id="edit_program" name="program_id">
                        <option value="">Select Program</option>
                        <?php foreach ($programs as $program): ?>
                            <option value="<?php echo $program['program_id']; ?>"><?php echo htmlspecialchars($program['program_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_status">Status:</label>
                    <select id="edit_status" name="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="graduated">Graduated</option>
                    </select>
                </div>
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
        <h2 class="modal-title">Delete User</h2>
        <p>Are you sure you want to delete this user? This action cannot be undone.</p>
        <div class="btn-group">
            <button type="button" class="btn btn-danger" onclick="confirmDelete()">Delete</button>
            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
        </div>
    </div>
</div>

