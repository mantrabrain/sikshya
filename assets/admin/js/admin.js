// Sikshya LMS Admin Base JavaScript

jQuery(document).ready(function($) {
    'use strict';
    
    // Initialize admin functionality
    SikshyaAdmin.init();
});

// Main admin object
var SikshyaAdmin = {
    
    /**
     * Initialize admin functionality
     */
    init: function() {
        this.initTooltips();
        this.initConfirmations();
        this.initFormValidation();
        this.initAjaxHandlers();
    },
    
    /**
     * Initialize tooltips
     */
    initTooltips: function() {
        jQuery('[data-tooltip]').each(function() {
            var $element = jQuery(this);
            var tooltipText = $element.data('tooltip');
            
            $element.attr('title', tooltipText);
        });
    },
    
    /**
     * Initialize confirmation dialogs
     */
    initConfirmations: function() {
        jQuery('[data-confirm]').on('click', function(e) {
            var $element = jQuery(this);
            var confirmMessage = $element.data('confirm');
            
            if (!confirm(confirmMessage)) {
                e.preventDefault();
                return false;
            }
        });
    },
    
    /**
     * Initialize form validation
     */
    initFormValidation: function() {
        jQuery('.sikshya-form').on('submit', function(e) {
            var $form = jQuery(this);
            var isValid = true;
            
            // Check required fields
            $form.find('[required]').each(function() {
                var $field = jQuery(this);
                var value = $field.val().trim();
                
                if (!value) {
                    $field.addClass('error');
                    isValid = false;
                } else {
                    $field.removeClass('error');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                SikshyaAdmin.showAlert('Please fill in all required fields.', 'error');
                return false;
            }
        });
        
        // Remove error class on input
        jQuery('.sikshya-form input, .sikshya-form textarea, .sikshya-form select').on('input change', function() {
            jQuery(this).removeClass('error');
        });
    },
    
    /**
     * Initialize AJAX handlers
     */
    initAjaxHandlers: function() {
        // Handle AJAX form submissions
        jQuery('.sikshya-ajax-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = jQuery(this);
            var $submitBtn = $form.find('[type="submit"]');
            var originalText = $submitBtn.text();
            
            // Show loading state
            $submitBtn.prop('disabled', true).text('Processing...');
            
            jQuery.ajax({
                url: sikshya.ajax_url,
                type: 'POST',
                data: $form.serialize(),
                success: function(response) {
                    if (response.success) {
                        SikshyaAdmin.showAlert(response.data.message || 'Operation completed successfully.', 'success');
                        
                        // Trigger success callback if defined
                        if (response.data.callback && typeof window[response.data.callback] === 'function') {
                            window[response.data.callback](response.data);
                        }
                    } else {
                        SikshyaAdmin.showAlert(response.data || 'An error occurred.', 'error');
                    }
                },
                error: function() {
                    SikshyaAdmin.showAlert('Network error occurred. Please try again.', 'error');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        });
    },
    
    /**
     * Show alert message
     */
    showAlert: function(message, type) {
        var alertClass = 'sikshya-alert-' + (type || 'info');
        var $alert = jQuery('<div class="sikshya-alert ' + alertClass + '">' + message + '</div>');
        
        // Remove existing alerts
        jQuery('.sikshya-alert').remove();
        
        // Add new alert
        jQuery('.wrap').prepend($alert);
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            $alert.fadeOut(function() {
                jQuery(this).remove();
            });
        }, 5000);
    },
    
    /**
     * Show loading overlay
     */
    showLoading: function() {
        if (jQuery('#sikshya-loading-overlay').length === 0) {
            var $overlay = jQuery('<div id="sikshya-loading-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center;"><div style="background: white; padding: 20px; border-radius: 8px; text-align: center;"><i class="fas fa-spinner fa-spin" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>Loading...</div></div>');
            jQuery('body').append($overlay);
        } else {
            jQuery('#sikshya-loading-overlay').show();
        }
    },
    
    /**
     * Hide loading overlay
     */
    hideLoading: function() {
        jQuery('#sikshya-loading-overlay').hide();
    },
    
    /**
     * Format date
     */
    formatDate: function(date) {
        if (!date) return '';
        
        var d = new Date(date);
        return d.toLocaleDateString() + ' ' + d.toLocaleTimeString();
    },
    
    /**
     * Format file size
     */
    formatFileSize: function(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    },
    
    /**
     * Debounce function
     */
    debounce: function(func, wait, immediate) {
        var timeout;
        return function() {
            var context = this, args = arguments;
            var later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            var callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    },
    
    /**
     * Throttle function
     */
    throttle: function(func, limit) {
        var inThrottle;
        return function() {
            var args = arguments;
            var context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(function() {
                    inThrottle = false;
                }, limit);
            }
        };
    }
};

// Utility functions
function sikshyaConfirm(message, callback) {
    if (confirm(message)) {
        if (typeof callback === 'function') {
            callback();
        }
    }
}

function sikshyaAlert(message, type) {
    SikshyaAdmin.showAlert(message, type);
}

function sikshyaShowLoading() {
    SikshyaAdmin.showLoading();
}

function sikshyaHideLoading() {
    SikshyaAdmin.hideLoading();
} 