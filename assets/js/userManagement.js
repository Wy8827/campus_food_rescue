
// Opens the Edit User modal and pre-fills form data
function openEditModal(button) {
    document.getElementById('modal-overlay').style.display = 'flex';
    document.getElementById('edit-modal').style.display = 'block';
    document.getElementById('view-modal').style.display = 'none';
    
    // Populate form values
    document.getElementById('edit_user_id').value = button.getAttribute('data-id');
    document.getElementById('edit_account_status').value = button.getAttribute('data-status');
    document.getElementById('edit_admin_note').value = ''; // Clear note input on open
}

// Opens the View Details modal and dynamically populates user data
function openViewModal(button) {
    document.getElementById('modal-overlay').style.display = 'flex';
    document.getElementById('view-modal').style.display = 'block';
    document.getElementById('edit-modal').style.display = 'none';
    
    // Set text values
    document.getElementById('view_user_id').innerText = "#USER-" + button.getAttribute('data-id');
    document.getElementById('view_name').innerText = button.getAttribute('data-name');
    document.getElementById('view_email').innerText = button.getAttribute('data-email');
    document.getElementById('view_role').innerText = button.getAttribute('data-role');
    document.getElementById('view_noshow').innerText = button.getAttribute('data-noshow');
    document.getElementById('view_status').innerText = button.getAttribute('data-status');
    
    // Set avatar initials and specific background color class
    let avatar = document.getElementById('view_avatar');
    avatar.innerText = button.getAttribute('data-initials');
    avatar.className = "avatar " + button.getAttribute('data-roleclass');
    
    // Calculate and set visual credit score indicator
    let score = parseInt(button.getAttribute('data-score'));
    document.getElementById('view_score_text').innerText = score + "%";
    
    let bar = document.getElementById('view_score_bar');
    bar.style.width = score + "%";
    
    // Assign appropriate color based on threshold logic
    let colorClass = score < 50 ? "fill-red" : (score < 80 ? "fill-orange" : "fill-green");
    let textClass = score < 50 ? "score-red" : (score < 80 ? "score-orange" : "score-green");
    
    bar.className = "score-bar-fill " + colorClass;
    document.getElementById('view_score_text').className = "score-text " + textClass;

    // Handle Dynamic Admin Note Display
    let statusValue = button.getAttribute('data-status').toLowerCase();
    let reasonContainer = document.getElementById('view_reason_container');

    if (statusValue === 'banned' || statusValue === 'throttled') {
        reasonContainer.style.display = 'block';
        // Adjust styling based on severity
        if(statusValue === 'banned') {
            reasonContainer.style.background = '#FFF5F5';
            reasonContainer.style.borderColor = '#FCA5A5';
            reasonContainer.style.color = '#7F1D1D';
        } else {
            reasonContainer.style.background = '#FFFBEB';
            reasonContainer.style.borderColor = '#FCD34D';
            reasonContainer.style.color = '#92400E';
        }
        document.getElementById('view_reason').innerText = button.getAttribute('data-reason');
    } else {
        reasonContainer.style.display = 'none';
    }
}

// Closes all modals by hiding the overlay container
function closeModals() {
    document.getElementById('modal-overlay').style.display = 'none';
}

// Allows closing the modal by clicking anywhere outside of the modal content
window.onclick = function(event) {
    let overlay = document.getElementById('modal-overlay');
    if (event.target == overlay) {
        closeModals();
    }
}