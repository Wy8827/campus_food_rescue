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

// -----------------------------------------
// Alert Switches Toggle Logic
// -----------------------------------------
const switches = document.querySelectorAll('.switch');
switches.forEach(img => {
    img.addEventListener('click', () => {
        if (img.src.includes('off.png')) {
            img.src = '../../assets/images/on.png';
            img.alt = 'on Icon';
        } else {
            img.src = '../../assets/images/off.png';
            img.alt = 'off Icon';
        }
    });
});

// -----------------------------------------
// Password Verification Method Toggle Logic
// -----------------------------------------
const toggleSecurityBtn = document.getElementById('toggle-security-btn');
const togglePasswordBtn = document.getElementById('toggle-password-btn');
const currentPasswordGroup = document.getElementById('current-password-group');
const securityQuestionGroup = document.getElementById('security-question-group');
const useSecurityInput = document.getElementById('use_security_question');
const currentPasswordInput = document.getElementById('current_password');
const securityAnswerInput = document.getElementById('security_answer_input');

if (toggleSecurityBtn && togglePasswordBtn) {
    // Switch to Security Question verification
    toggleSecurityBtn.addEventListener('click', function(e) {
        e.preventDefault();
        currentPasswordGroup.style.display = 'none';
        securityQuestionGroup.style.display = 'flex';
        useSecurityInput.value = 'yes';
        currentPasswordInput.value = ''; // Clear current password input to prevent conflicts
    });

    // Switch back to Current Password verification
    togglePasswordBtn.addEventListener('click', function(e) {
        e.preventDefault();
        securityQuestionGroup.style.display = 'none';
        currentPasswordGroup.style.display = 'flex';
        useSecurityInput.value = 'no';
        securityAnswerInput.value = ''; // Clear security answer input
    });
}