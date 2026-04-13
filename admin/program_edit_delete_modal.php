<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeEditModal()">&times;</span>
        <h2 class="modal-title">Edit Program</h2>
        <form id="editForm" class="modal-form" onsubmit="handleEditSubmit(event)">
            <input type="hidden" id="edit_id" name="id">
            <div class="form-group">
                <label for="edit_program_name">Program Name:</label>
                <input type="text" id="edit_program_name" name="program_name" required>
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
        <h2 class="modal-title">Delete Program</h2>
        <p>Are you sure you want to delete this program?</p>
        <div class="btn-group">
            <button type="button" class="btn btn-danger" onclick="confirmDelete()">Delete</button>
            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
        </div>
    </div>
</div> 