/**
 * Sikshya Modal System
 * Centralized modal functionality for all Sikshya components
 */

// Global modal object
window.SikshyaModal = {
    /**
     * Open a modal
     */
    open: function(modalElement) {
        if (!modalElement) {
            console.error('SikshyaModal: No modal element provided');
            return;
        }

        // Add active class
        modalElement.classList.add('active', 'show');
        
        // Prevent body scroll
        document.body.classList.add('sikshya-modal-open');
        
        // Focus management
        const focusableElements = modalElement.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        if (focusableElements.length > 0) {
            focusableElements[0].focus();
        }
    },

    /**
     * Close a modal
     */
    close: function(modalElement) {
        if (!modalElement) {
            // Find active modal
            modalElement = document.querySelector('.sikshya-modal-overlay.active');
        }

        if (modalElement) {
            // Remove active class
            modalElement.classList.remove('active', 'show');
            
            // Allow body scroll
            document.body.classList.remove('sikshya-modal-open');
        }
    },

    /**
     * Show confirmation modal
     */
    confirm: function(options) {
        const {
            title = 'Confirm',
            message = 'Are you sure?',
            confirmText = 'Yes',
            cancelText = 'Cancel',
            onConfirm = null,
            onCancel = null
        } = options;

        const modalHtml = `
            <div class="sikshya-modal-overlay">
                <div class="sikshya-modal sikshya-modal-small">
                    <div class="sikshya-modal-header">
                        <h3 class="sikshya-modal-title">${title}</h3>
                        <button class="sikshya-modal-close" onclick="SikshyaModal.close()">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div class="sikshya-modal-body">
                        <p>${message}</p>
                    </div>
                    <div class="sikshya-modal-footer">
                        <button class="sikshya-btn sikshya-btn-secondary" onclick="SikshyaModal.close()">
                            ${cancelText}
                        </button>
                        <button class="sikshya-btn sikshya-btn-primary" onclick="SikshyaModal.confirmAction()">
                            ${confirmText}
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Store callbacks
        window.sikshyaModalConfirmCallback = onConfirm;
        window.sikshyaModalCancelCallback = onCancel;

        // Add modal to DOM
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Open modal
        const modal = document.querySelector('.sikshya-modal-overlay:last-child');
        this.open(modal);
    },

    /**
     * Confirm action callback
     */
    confirmAction: function() {
        if (window.sikshyaModalConfirmCallback) {
            window.sikshyaModalConfirmCallback();
        }
        this.close();
    },

    /**
     * Show alert modal
     */
    alert: function(options) {
        const {
            title = 'Alert',
            message = '',
            buttonText = 'OK',
            onClose = null
        } = options;

        const modalHtml = `
            <div class="sikshya-modal-overlay">
                <div class="sikshya-modal sikshya-modal-small">
                    <div class="sikshya-modal-header">
                        <h3 class="sikshya-modal-title">${title}</h3>
                        <button class="sikshya-modal-close" onclick="SikshyaModal.close()">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div class="sikshya-modal-body">
                        <p>${message}</p>
                    </div>
                    <div class="sikshya-modal-footer">
                        <button class="sikshya-btn sikshya-btn-primary" onclick="SikshyaModal.close()">
                            ${buttonText}
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Store callback
        window.sikshyaModalAlertCallback = onClose;

        // Add modal to DOM
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Open modal
        const modal = document.querySelector('.sikshya-modal-overlay:last-child');
        this.open(modal);
    },

    /**
     * Initialize modal system
     */
    init: function() {
        // Handle modal close button clicks
        document.addEventListener('click', function(e) {
            if (e.target.closest('.sikshya-modal-close')) {
                e.preventDefault();
                const modal = e.target.closest('.sikshya-modal-overlay');
                SikshyaModal.close(modal);
            }
        });

        // Handle modal overlay clicks (close on outside click)
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('sikshya-modal-overlay')) {
                SikshyaModal.close(e.target);
            }
        });

        // Handle escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const activeModal = document.querySelector('.sikshya-modal-overlay.active');
                if (activeModal) {
                    SikshyaModal.close(activeModal);
                }
            }
        });
    }
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        SikshyaModal.init();
    });
} else {
    SikshyaModal.init();
}