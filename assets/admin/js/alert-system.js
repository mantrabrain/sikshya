/**
 * Sikshya Custom Alert & Confirmation System
 * 
 * @package Sikshya
 * @since 1.0.0
 */

(function($) {
    'use strict';

    window.SikshyaAlert = {
        
        /**
         * Clear all existing alerts
         */
        clearAll: function() {
            const existingAlerts = document.querySelectorAll('.sikshya-alert-overlay');
            existingAlerts.forEach(alert => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            });
        },

        /**
         * Show a custom alert
         * 
         * @param {string} message - The alert message
         * @param {string} type - Alert type: success, warning, error, info
         * @param {Object} options - Additional options
         */
        alert: function(message, type = 'info', options = {}) {
            // Clear any existing alerts first
            this.clearAll();
            const defaultOptions = {
                title: this.getDefaultTitle(type),
                showCloseButton: true,
                autoClose: false,
                autoCloseDelay: 0,
                onClose: null,
                className: ''
            };
            
            const config = Object.assign({}, defaultOptions, options);
            
            return this.show({
                type: type,
                title: config.title,
                message: message,
                showCloseButton: config.showCloseButton,
                autoClose: config.autoClose,
                autoCloseDelay: config.autoCloseDelay,
                onClose: config.onClose,
                className: config.className,
                buttons: [
                    {
                        text: 'OK',
                        type: 'primary',
                        action: 'close'
                    }
                ]
            });
        },

        /**
         * Show a custom confirmation dialog
         * 
         * @param {string} message - The confirmation message
         * @param {Object} options - Additional options
         */
        confirm: function(message, options = {}) {
            const defaultOptions = {
                title: 'Confirm Action',
                confirmText: 'Yes',
                cancelText: 'Cancel',
                confirmType: 'danger',
                cancelType: 'secondary',
                onConfirm: null,
                onCancel: null,
                className: ''
            };
            
            const config = Object.assign({}, defaultOptions, options);
            
            return this.show({
                type: 'confirm',
                title: config.title,
                message: message,
                showCloseButton: false,
                autoClose: false,
                className: config.className,
                buttons: [
                    {
                        text: config.cancelText,
                        type: config.cancelType,
                        action: 'cancel',
                        callback: config.onCancel
                    },
                    {
                        text: config.confirmText,
                        type: config.confirmType,
                        action: 'confirm',
                        callback: config.onConfirm
                    }
                ]
            });
        },

        /**
         * Show a custom prompt dialog
         * 
         * @param {string} message - The prompt message
         * @param {Object} options - Additional options
         */
        prompt: function(message, options = {}) {
            const defaultOptions = {
                title: 'Enter Value',
                placeholder: '',
                defaultValue: '',
                confirmText: 'OK',
                cancelText: 'Cancel',
                onConfirm: null,
                onCancel: null,
                className: ''
            };
            
            const config = Object.assign({}, defaultOptions, options);
            
            return this.show({
                type: 'prompt',
                title: config.title,
                message: message,
                showCloseButton: false,
                autoClose: false,
                className: config.className,
                input: {
                    placeholder: config.placeholder,
                    value: config.defaultValue
                },
                buttons: [
                    {
                        text: config.cancelText,
                        type: 'secondary',
                        action: 'cancel',
                        callback: config.onCancel
                    },
                    {
                        text: config.confirmText,
                        type: 'primary',
                        action: 'confirm',
                        callback: config.onConfirm
                    }
                ]
            });
        },

        /**
         * Show the main alert dialog
         * 
         * @param {Object} config - Alert configuration
         */
        show: function(config) {
            return new Promise((resolve, reject) => {
                const overlay = this.createOverlay(config);
                const alert = this.createAlert(config);
                
                overlay.appendChild(alert);
                document.body.appendChild(overlay);
                
                // Show with animation
                setTimeout(() => {
                    overlay.classList.add('active');
                }, 10);
                
                // Handle auto close
                if (config.autoClose && config.autoCloseDelay > 0) {
                    setTimeout(() => {
                        this.close(overlay, 'auto');
                        resolve('auto');
                    }, config.autoCloseDelay);
                }
                
                // Store resolve/reject for button callbacks
                overlay._resolve = resolve;
                overlay._reject = reject;
            });
        },

        /**
         * Create the overlay element
         */
        createOverlay: function(config) {
            const overlay = document.createElement('div');
            overlay.className = `sikshya-alert-overlay ${config.type}`;
            
            // Close on overlay click
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    this.close(overlay, 'overlay');
                }
            });
            
            return overlay;
        },

        /**
         * Create the alert element
         */
        createAlert: function(config) {
            const alert = document.createElement('div');
            alert.className = `sikshya-alert ${config.className}`;
            
            if (config.type === 'confirm') {
                alert.classList.add('confirmation');
            }
            
            alert.innerHTML = this.getAlertHTML(config);
            
            // Bind events
            this.bindEvents(alert, config);
            
            return alert;
        },

        /**
         * Get the alert HTML structure
         */
        getAlertHTML: function(config) {
            const iconHTML = this.getIconHTML(config.type);
            const inputHTML = config.input ? this.getInputHTML(config.input) : '';
            
            return `
                <div class="sikshya-alert-header">
                    <div class="sikshya-alert-icon ${config.type}">
                        ${iconHTML}
                    </div>
                    <h3 class="sikshya-alert-title">${config.title}</h3>
                    ${config.showCloseButton ? '<button class="sikshya-alert-close" data-action="close">×</button>' : ''}
                </div>
                <div class="sikshya-alert-body">
                    <p class="sikshya-alert-message">${config.message}</p>
                    ${inputHTML}
                </div>
                <div class="sikshya-alert-footer">
                    ${this.getButtonsHTML(config.buttons)}
                </div>
            `;
        },

        /**
         * Get icon HTML for different types - Minimal Sikshya Style
         */
        getIconHTML: function(type) {
            const icons = {
                success: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>',
                warning: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>',
                error: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                info: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                confirm: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
            };
            
            return icons[type] || icons.info;
        },

        /**
         * Get input HTML for prompt dialogs
         */
        getInputHTML: function(inputConfig) {
            return `
                <div style="margin-top: 16px;">
                    <input type="text" 
                           class="sikshya-alert-input" 
                           placeholder="${inputConfig.placeholder || ''}" 
                           value="${inputConfig.value || ''}"
                           style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                </div>
            `;
        },

        /**
         * Get buttons HTML with icons
         */
        getButtonsHTML: function(buttons) {
            return buttons.map(button => {
                const icon = this.getButtonIcon(button.action, button.type);
                return `
                    <button class="sikshya-alert-btn ${button.type}" 
                            data-action="${button.action}"
                            data-callback="${button.callback ? 'true' : 'false'}">
                        ${icon}
                        ${button.text}
                    </button>
                `;
            }).join('');
        },

        /**
         * Get button icon based on action and type
         */
        getButtonIcon: function(action, type) {
            const icons = {
                'close': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>',
                'cancel': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>',
                'confirm': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>',
                'ok': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>',
                'delete': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>',
                'save': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3-3m0 0l-3 3m3-3v12"/></svg>',
                'edit': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>',
                'add': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>',
                'refresh': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>'
            };
            
            // Default icons based on button type
            if (type === 'danger') {
                return icons.delete;
            } else if (type === 'success') {
                return icons.confirm;
            } else if (type === 'primary') {
                return icons.confirm;
            } else {
                return icons.cancel;
            }
        },

        /**
         * Bind event handlers
         */
        bindEvents: function(alert, config) {
            const overlay = alert.closest('.sikshya-alert-overlay');
            const self = this;
            
            // Close button
            const closeBtn = alert.querySelector('.sikshya-alert-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    self.close(overlay, 'close');
                });
            }
            
            // Action buttons - Use setTimeout to ensure DOM is ready
            setTimeout(() => {
                const buttons = alert.querySelectorAll('.sikshya-alert-btn');
                
                buttons.forEach((button, index) => {
                    
                    // Remove any existing event listeners
                    button.onclick = null;
                    
                    // Add new event listener
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        const action = this.dataset.action;
                        const hasCallback = this.dataset.callback === 'true';
                        
                        
                        // Get input value for prompt dialogs
                        let inputValue = null;
                        if (config.type === 'prompt') {
                            const input = alert.querySelector('.sikshya-alert-input');
                            if (input) {
                                inputValue = input.value;
                            }
                        }
                        
                        // Execute callback if provided
                        if (hasCallback && config.buttons) {
                            const buttonConfig = config.buttons.find(b => b.action === action);
                            if (buttonConfig && buttonConfig.callback) {
                                const result = buttonConfig.callback(inputValue);
                                if (result === false) {
                                    return; // Prevent closing if callback returns false
                                }
                            }
                        }
                        
                        self.close(overlay, action, inputValue);
                    });
                });
            }, 50);
            
            // Focus management
            const firstInput = alert.querySelector('.sikshya-alert-input');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 100);
            } else {
                const firstButton = alert.querySelector('.sikshya-alert-btn');
                if (firstButton) {
                    setTimeout(() => firstButton.focus(), 100);
                }
            }
            
            // Handle Enter key for prompt
            if (config.type === 'prompt') {
                const input = alert.querySelector('.sikshya-alert-input');
                if (input) {
                    input.addEventListener('keypress', (e) => {
                        if (e.key === 'Enter') {
                            const confirmBtn = alert.querySelector('.sikshya-alert-btn[data-action="confirm"]');
                            if (confirmBtn) {
                                confirmBtn.click();
                            }
                        }
                    });
                }
            }
        },

        /**
         * Close the alert
         */
        close: function(overlay, action, value = null) {
            
            if (!overlay) {
                overlay = document.querySelector('.sikshya-alert-overlay.active');
            }
            
            if (!overlay) {
                return;
            }
            
            overlay.classList.remove('active');
            
            setTimeout(() => {
                if (overlay.parentNode) {
                    overlay.parentNode.removeChild(overlay);
                }
                
                if (overlay._resolve) {
                    overlay._resolve({ action: action, value: value });
                }
            }, 300);
        },

        /**
         * Get default title for alert type
         */
        getDefaultTitle: function(type) {
            const titles = {
                success: 'Success',
                warning: 'Warning',
                error: 'Error',
                info: 'Information',
                confirm: 'Confirm Action'
            };
            
            return titles[type] || 'Alert';
        },

        /**
         * Initialize the alert system
         */
        init: function() {
            // Handle escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    const activeOverlay = document.querySelector('.sikshya-alert-overlay.active');
                    if (activeOverlay) {
                        this.close(activeOverlay, 'escape');
                    }
                }
            });
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        SikshyaAlert.init();
    });

    // Global shortcuts for easy access
    window.sikshyaAlert = SikshyaAlert.alert.bind(SikshyaAlert);
    window.sikshyaConfirm = SikshyaAlert.confirm.bind(SikshyaAlert);
    window.sikshyaPrompt = SikshyaAlert.prompt.bind(SikshyaAlert);


})(jQuery);
