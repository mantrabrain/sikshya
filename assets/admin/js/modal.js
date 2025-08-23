/**
 * Sikshya Custom Modal System
 * Replaces browser alerts with custom confirmation modals
 */
window.SikshyaModal = {
    
    /**
     * Initialize the modal system
     */
    init: function() {
        this.createModalContainer();
        this.bindEvents();
    },

    /**
     * Create modal container if it doesn't exist
     */
    createModalContainer: function() {
        if (!document.getElementById('sikshya-modal-container')) {
            const container = document.createElement('div');
            container.id = 'sikshya-modal-container';
            document.body.appendChild(container);
        }
    },

    /**
     * Bind modal events
     */
    bindEvents: function() {
        // Close modal on overlay click
        $(document).on('click', '.sikshya-modal-overlay', function(e) {
            if (e.target === this) {
                SikshyaModal.close();
            }
        });

        // Close modal on escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('.sikshya-modal-overlay').is(':visible')) {
                SikshyaModal.close();
            }
        });

        // Handle modal button clicks
        $(document).on('click', '.sikshya-modal-btn', function(e) {
            e.preventDefault();
            const action = $(this).data('action');
            const callback = $(this).data('callback');
            
            if (action === 'confirm' && callback) {
                // Execute the callback function
                if (typeof window[callback] === 'function') {
                    window[callback]();
                }
            }
            
            SikshyaModal.close();
        });
    },

    /**
     * Show confirmation modal
     * 
     * @param {Object} options - Modal options
     * @param {string} options.title - Modal title
     * @param {string} options.message - Modal message
     * @param {string} options.warning - Warning text (optional)
     * @param {string} options.confirmText - Confirm button text
     * @param {string} options.cancelText - Cancel button text
     * @param {string} options.confirmCallback - Function name to call on confirm
     * @param {string} options.type - Modal type (danger, warning, info)
     */
    confirm: function(options) {
        const defaults = {
            title: 'Confirm Action',
            message: 'Are you sure you want to proceed?',
            warning: '',
            confirmText: 'Confirm',
            cancelText: 'Cancel',
            confirmCallback: '',
            type: 'danger'
        };

        const config = { ...defaults, ...options };
        
        // Create modal HTML
        const modalHtml = `
            <div class="sikshya-modal-overlay">
                <div class="sikshya-modal">
                    <div class="sikshya-modal-header">
                        <h3 class="sikshya-modal-title">
                            <i class="fas fa-exclamation-triangle"></i>
                            ${config.title}
                        </h3>
                    </div>
                    <div class="sikshya-modal-body">
                        <p class="sikshya-modal-message">${config.message}</p>
                        ${config.warning ? `
                            <div class="sikshya-modal-warning">
                                <i class="fas fa-exclamation-circle"></i>
                                <p class="sikshya-modal-warning-text">${config.warning}</p>
                            </div>
                        ` : ''}
                    </div>
                    <div class="sikshya-modal-footer">
                        <button type="button" class="sikshya-modal-btn sikshya-modal-btn-secondary" data-action="cancel">
                            ${config.cancelText}
                        </button>
                        <button type="button" class="sikshya-modal-btn sikshya-modal-btn-${config.type}" data-action="confirm" data-callback="${config.confirmCallback}">
                            ${config.confirmText}
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Remove existing modal
        $('.sikshya-modal-overlay').remove();
        
        // Add new modal
        $('#sikshya-modal-container').html(modalHtml);
        
        // Show modal with animation
        setTimeout(() => {
            $('.sikshya-modal-overlay').addClass('show');
        }, 10);
    },

    /**
     * Close modal
     */
    close: function() {
        $('.sikshya-modal-overlay').removeClass('show');
        setTimeout(() => {
            $('.sikshya-modal-overlay').remove();
        }, 300);
    },

    /**
     * Show info modal
     */
    info: function(title, message) {
        this.confirm({
            title: title,
            message: message,
            confirmText: 'OK',
            type: 'info',
            confirmCallback: 'SikshyaModal.close'
        });
    },

    /**
     * Show warning modal
     */
    warning: function(title, message) {
        this.confirm({
            title: title,
            message: message,
            confirmText: 'OK',
            type: 'warning',
            confirmCallback: 'SikshyaModal.close'
        });
    }
};

// Initialize modal system when document is ready
$(document).ready(function() {
    SikshyaModal.init();
});
