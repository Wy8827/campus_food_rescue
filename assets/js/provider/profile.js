// providerProfile.js
// Same generic edit-toggle / switch-toggle / password-method-toggle
// behavior as the admin's adminProfile.js, owned as a separate file
// so the food_provider module has no dependency on the admin's files.

// -----------------------------------------
// Edit Profile Toggle Logic
// -----------------------------------------
const editBtn = document.querySelector('.export-button');
const inputFields = document.querySelectorAll('.info-value');

if (editBtn) {
    editBtn.addEventListener('click', function() {
        const isEditing = editBtn.textContent.trim() === 'Cancel Edit';

        if (!isEditing) {
            inputFields.forEach(input => input.removeAttribute('readonly'));
            editBtn.textContent = 'Cancel Edit';
            editBtn.classList.add('cancel-mode');
            inputFields[0].focus();
        } else {
            inputFields.forEach(input => input.setAttribute('readonly', 'readonly'));
            editBtn.textContent = 'Edit Profile';
            editBtn.classList.remove('cancel-mode');
        }
    });
}

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
    toggleSecurityBtn.addEventListener('click', function(e) {
        e.preventDefault();
        currentPasswordGroup.style.display = 'none';
        securityQuestionGroup.style.display = 'flex';
        useSecurityInput.value = 'yes';
        currentPasswordInput.value = '';
    });

    togglePasswordBtn.addEventListener('click', function(e) {
        e.preventDefault();
        securityQuestionGroup.style.display = 'none';
        currentPasswordGroup.style.display = 'flex';
        useSecurityInput.value = 'no';
        securityAnswerInput.value = '';
    });
}
