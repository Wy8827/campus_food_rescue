// Open and populate View Details modal
function openDetailModal(btn) {
    const data = btn.dataset;

    document.getElementById('detailName').innerText = data.name;
    document.getElementById('detailMeta').innerText = `${data.displayId} • ${data.email}`;
    document.getElementById('detailUserId').innerText = data.id;
    document.getElementById('detailRole').innerText = data.roleLabel;
    document.getElementById('detailStatus').innerText = data.status.charAt(0).toUpperCase() + data.status.slice(1);
    document.getElementById('detailNoShow').innerText = data.noshow;
    document.getElementById('detailQuestion').innerText = data.question || 'No security question set';

    // Display provider outlet details if role is provider
    const providerContainer = document.getElementById('providerDetailsContainer');
    if (data.role.toLowerCase() === 'provider') {
        providerContainer.style.display = 'block';
        document.getElementById('detailProviderName').innerText = data.providerName || 'N/A';
        document.getElementById('detailContact').innerText = data.contact || 'N/A';
        document.getElementById('detailLocation').innerText = data.location || 'N/A';
        document.getElementById('detailHours').innerText = data.hours || 'N/A';
    } else {
        providerContainer.style.display = 'none';
    }

    // Avatar configuration
    const avatar = document.getElementById('detailAvatar');
    avatar.innerText = data.initials;
    avatar.className = `avatar-circle avatar-${data.role.toLowerCase()}`;

    // Credit score bar and text styling
    const fill = document.getElementById('detailScoreFill');
    fill.style.width = data.score + '%';
    fill.className = `progress-fill fill-${data.scoreColor}`;

    const scoreText = document.getElementById('detailScoreText');
    scoreText.innerText = data.score + '%';
    scoreText.className = `score-label text-${data.scoreColor}`;

    document.getElementById('detailModal').classList.add('show');
}

// Open and populate Edit User modal
function openEditModal(btn) {
    const data = btn.dataset;

    document.getElementById('editUserId').value = data.id;
    document.getElementById('editDisplayId').value = data.displayId;
    document.getElementById('editUserName').value = data.name;
    document.getElementById('editEmail').value = data.email;
    document.getElementById('editRole').value = data.role.toLowerCase();
    document.getElementById('editStatus').value = data.status.toLowerCase();
    document.getElementById('editNoShow').value = data.noshow;

    document.getElementById('editSecurityQuestion').value = data.question || '';
    document.getElementById('editSecurityAnswer').value = '';

    document.getElementById('editModal').classList.add('show');
}

// Close specified modal
function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}

// Close modal when clicking on backdrop
function handleBackdropClick(event, modalId) {
    if (event.target.id === modalId) {
        closeModal(modalId);
    }
}

// Close open modals on Escape key press
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal('detailModal');
        closeModal('editModal');
    }
});