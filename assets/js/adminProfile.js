// 1. get Edit Profile button (class: export-button) 
const editBtn = document.querySelector('.export-button');
// 2. get all input fields
const inputFields = document.querySelectorAll('.info-value');

if (editBtn) {
    editBtn.addEventListener('click', function() {
        // differentiate current button text is "Cancel Edit"
        const isEditing = editBtn.textContent.trim() === 'Cancel Edit';

        if (!isEditing) {
            inputFields.forEach(input => {
                input.removeAttribute('readonly'); 
            });
        
            editBtn.textContent = 'Cancel Edit';
            editBtn.classList.add('cancel-mode');
            
            // auto focus the first input field (Username)
            inputFields[0].focus();
        } else {
            // cancel editing, restore readonly attributes
            inputFields.forEach(input => {
                input.setAttribute('readonly', 'readonly');
            });
            
            // restore the button to its original appearance
            editBtn.textContent = 'Edit Profile';
            editBtn.classList.remove('cancel-mode');
        }
    });
}