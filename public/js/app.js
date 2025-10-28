/**
 * OpenBookManager JavaScript
 */

// Auto-hide flash messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const flashMessage = document.querySelector('.flash-message');
    if (flashMessage) {
        setTimeout(() => {
            flashMessage.style.transition = 'opacity 0.5s';
            flashMessage.style.opacity = '0';
            setTimeout(() => flashMessage.remove(), 500);
        }, 5000);
    }
});

// Confirm delete actions
function confirmDelete(message) {
    return confirm(message || 'Do you really want to delete this entry?');
}

// Form validation helper
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;

    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.style.borderColor = 'var(--danger-color)';
            isValid = false;
        } else {
            field.style.borderColor = '';
        }
    });

    return isValid;
}
