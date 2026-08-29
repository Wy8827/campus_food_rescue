document.addEventListener('DOMContentLoaded', function () {
    const categorySelect = document.getElementById('categoryFilter');
    const urgencySelect = document.getElementById('urgencyFilter');
    const foodCards = document.querySelectorAll('.food-item-card');
    const noMatchMsg = document.getElementById('noFilterMatchMessage');

    function applyFilters() {
        if (!categorySelect || !urgencySelect) return;

        const selectedCategory = categorySelect.value.toLowerCase().trim();
        const selectedUrgency = urgencySelect.value.toLowerCase().trim();
        let visibleCount = 0;

        foodCards.forEach(card => {
            const cardTags = (card.getAttribute('data-tags') || '').split(',').map(t => t.trim());
            const cardUrgency = (card.getAttribute('data-urgency') || '').trim();

            const matchesCategory = (selectedCategory === 'all') || cardTags.includes(selectedCategory);
            const matchesUrgency = (selectedUrgency === 'all') || (cardUrgency === selectedUrgency);

            if (matchesCategory && matchesUrgency) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        if (noMatchMsg) {
            if (visibleCount === 0 && foodCards.length > 0) {
                noMatchMsg.style.display = 'block';
            } else {
                noMatchMsg.style.display = 'none';
            }
        }
    }

    if (categorySelect && urgencySelect) {
        categorySelect.addEventListener('change', applyFilters);
        urgencySelect.addEventListener('change', applyFilters);
    }

    const listViewBtn = document.getElementById('listViewBtn');
    const gridViewBtn = document.getElementById('gridViewBtn');
    const moderationList = document.getElementById('moderationList');

    if (listViewBtn && gridViewBtn && moderationList) {
        listViewBtn.addEventListener('click', function () {
            listViewBtn.classList.add('active');
            gridViewBtn.classList.remove('active');
            moderationList.classList.add('list-view');
        });

        gridViewBtn.addEventListener('click', function () {
            gridViewBtn.classList.add('active');
            listViewBtn.classList.remove('active');
            moderationList.classList.remove('list-view');
        });
    }
});

// ==========================================
// Reject Modal Controls
// ==========================================
function openReasonModal(action, type, id, title) {
    const modal = document.getElementById('reasonModal');
    if (!modal) return;

    const modalAction = document.getElementById('modalAction');
    const modalType = document.getElementById('modalType');
    const modalTargetId = document.getElementById('modalTargetId');
    const modalTitle = document.getElementById('reasonModalTitle');
    const modalDesc = document.getElementById('reasonModalDesc');
    const reasonInput = document.getElementById('actionReason');

    modalAction.value = action;
    modalType.value = type;
    modalTargetId.value = id;
    reasonInput.value = '';

    modalTitle.innerText = `Reject ${type === 'food' ? 'Food Listing' : 'Provider Registration'}`;
    modalDesc.innerHTML = `Provide a reason for rejecting <strong>${title}</strong>. This will be recorded in the audit log.`;

    modal.classList.add('show');
    setTimeout(() => reasonInput.focus(), 100);
}

function closeReasonModal() {
    const modal = document.getElementById('reasonModal');
    if (modal) modal.classList.remove('show');
}

function handleReasonBackdropClick(event) {
    if (event.target.id === 'reasonModal') {
        closeReasonModal();
    }
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeReasonModal();
    }
});