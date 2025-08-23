/**
 * Sikshya Toast Notification System
 * 
 * @package Sikshya
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Toast System Namespace
    window.SikshyaToast = {
        
        // Toast container
        container: null,
        
        // Toast queue
        queue: [],
        
        // Default options
        defaults: {
            type: 'info',
            title: '',
            message: '',
            duration: 5000,
            showProgress: true,
            showClose: true,
            position: 'top-right'
        },
        
        // Initialize toast system
        init: function() {
            this.createContainer();
            this.bindEvents();
        },
        
        // Create toast container
        createContainer: function() {
            if (this.container) {
                return;
            }
            
            this.container = $('<div class="sikshya-toast-container"></div>');
            $('body').append(this.container);
        },
        
        // Bind events
        bindEvents: function() {
            // Close button click
            $(document).on('click', '.sikshya-toast-close', function() {
                const $toast = $(this).closest('.sikshya-toast');
                SikshyaToast.hide($toast);
            });
            
            // Toast click to dismiss
            $(document).on('click', '.sikshya-toast', function(e) {
                if (!$(e.target).closest('.sikshya-toast-close').length) {
                    const $toast = $(this);
                    SikshyaToast.hide($toast);
                }
            });
        },
        
        // Show toast
        show: function(options) {
            const config = $.extend({}, this.defaults, options);
            
            // Create toast element
            const $toast = this.createToast(config);
            
            // Add to container
            this.container.append($toast);
            
            // Trigger show animation
            setTimeout(() => {
                $toast.addClass('show');
            }, 10);
            
            // Start progress bar
            if (config.showProgress && config.duration > 0) {
                this.startProgress($toast, config.duration);
            }
            
            // Auto hide
            if (config.duration > 0) {
                setTimeout(() => {
                    this.hide($toast);
                }, config.duration);
            }
            
            return $toast;
        },
        
        // Create toast element
        createToast: function(config) {
            const iconClass = this.getIconClass(config.type);
            const iconText = this.getIconText(config.type);
            
            let html = `
                <div class="sikshya-toast ${config.type}">
                    <div class="sikshya-toast-icon">
                        <i class="${iconClass}"></i>
                    </div>
                    <div class="sikshya-toast-content">
            `;
            
            if (config.title) {
                html += `<div class="sikshya-toast-title">${config.title}</div>`;
            }
            
            if (config.message) {
                html += `<div class="sikshya-toast-message">${config.message}</div>`;
            }
            
            html += `
                    </div>
            `;
            
            if (config.showClose) {
                html += `
                    <button class="sikshya-toast-close" title="Close">
                        <i class="fas fa-times"></i>
                    </button>
                `;
            }
            
            if (config.showProgress) {
                html += `
                    <div class="sikshya-toast-progress">
                        <div class="sikshya-toast-progress-bar"></div>
                    </div>
                `;
            }
            
            html += '</div>';
            
            return $(html);
        },
        
        // Get icon class for toast type
        getIconClass: function(type) {
            const icons = {
                success: 'fas fa-check',
                error: 'fas fa-exclamation-triangle',
                warning: 'fas fa-exclamation-circle',
                info: 'fas fa-info-circle'
            };
            return icons[type] || icons.info;
        },
        
        // Get icon text for toast type
        getIconText: function(type) {
            const texts = {
                success: '✓',
                error: '⚠',
                warning: '⚠',
                info: 'ℹ'
            };
            return texts[type] || texts.info;
        },
        
        // Start progress bar animation
        startProgress: function($toast, duration) {
            const $progressBar = $toast.find('.sikshya-toast-progress-bar');
            if ($progressBar.length) {
                setTimeout(() => {
                    $progressBar.css('transform', 'translateX(0)');
                }, 100);
            }
        },
        
        // Hide toast
        hide: function($toast) {
            if (!$toast || !$toast.length) {
                return;
            }
            
            $toast.addClass('hide');
            
            setTimeout(() => {
                $toast.remove();
            }, 300);
        },
        
        // Hide all toasts
        hideAll: function() {
            this.container.find('.sikshya-toast').each((index, toast) => {
                this.hide($(toast));
            });
        },
        
        // Success toast
        success: function(message, title = 'Success', options = {}) {
            return this.show({
                type: 'success',
                title: title,
                message: message,
                ...options
            });
        },
        
        // Error toast
        error: function(message, title = 'Error', options = {}) {
            return this.show({
                type: 'error',
                title: title,
                message: message,
                ...options
            });
        },
        
        // Warning toast
        warning: function(message, title = 'Warning', options = {}) {
            return this.show({
                type: 'warning',
                title: title,
                message: message,
                ...options
            });
        },
        
        // Info toast
        info: function(message, title = 'Info', options = {}) {
            return this.show({
                type: 'info',
                title: title,
                message: message,
                ...options
            });
        },
        
        // Quick success message
        successMessage: function(message) {
            return this.success(message, 'Success', { duration: 3000 });
        },
        
        // Quick error message
        errorMessage: function(message) {
            return this.error(message, 'Error', { duration: 4000 });
        },
        
        // Quick warning message
        warningMessage: function(message) {
            return this.warning(message, 'Warning', { duration: 3500 });
        },
        
        // Quick info message
        infoMessage: function(message) {
            return this.info(message, 'Info', { duration: 3000 });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        SikshyaToast.init();
    });

})(jQuery);
