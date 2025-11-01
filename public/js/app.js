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

// Global confirmation dialog (Alpine.js component)
function confirmDialog() {
    return {
        isOpen: false,
        message: '',
        resolveCallback: null,

        show(message) {
            this.message = message;
            this.isOpen = true;

            // Return a promise that resolves when user confirms/cancels
            return new Promise((resolve) => {
                this.resolveCallback = resolve;
            });
        },

        confirm() {
            this.isOpen = false;
            if (this.resolveCallback) {
                this.resolveCallback(true);
            }
        },

        cancel() {
            this.isOpen = false;
            if (this.resolveCallback) {
                this.resolveCallback(false);
            }
        }
    };
}

// Global reference to confirmation dialog
let globalConfirmDialog = null;

// Wait for Alpine to initialize
window.addEventListener('load', () => {
    const initDialog = () => {
        const wrapper = document.querySelector('[x-data*="confirmDialog"]');
        if (wrapper && wrapper.__x && wrapper.__x.$data) {
            globalConfirmDialog = wrapper.__x.$data;
            console.log('✓ Confirmation dialog initialized');
        } else {
            setTimeout(initDialog, 50);
        }
    };
    initDialog();
});

// Replacement for browser confirm() - uses custom dialog
async function confirmDelete(message) {
    if (!globalConfirmDialog) {
        console.warn('Confirmation dialog not ready, falling back to browser confirm');
        return confirm(message || 'Do you really want to delete this entry?');
    }

    return await globalConfirmDialog.show(message || 'Do you really want to delete this entry?');
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
