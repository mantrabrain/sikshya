/**
 * Sikshya Settings JavaScript
 * 
 * @package Sikshya
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Settings namespace
    window.SikshyaSettings = {
        
        // Current active tab
        currentTab: 'general',
        
        // Initialize settings functionality
        init: function() {
            this.bindEvents();
            this.loadInitialTab();
        },

        // Bind event listeners
        bindEvents: function() {
            // Tab switching
            $(document).on('click', '.sikshya-settings-tab', this.handleTabSwitch);
            
            // Form submission
            $(document).on('submit', '#sikshya-settings-form', this.handleFormSubmit);
            
            // Reset button
            $(document).on('click', '.sikshya-reset-settings', this.handleResetSettings);
            
            // URL hash change (for direct tab access)
            $(window).on('hashchange', this.handleHashChange);
        },

        // Load initial tab based on URL or default
        loadInitialTab: function() {
            const hash = window.location.hash.replace('#', '');
            if (hash && $('.sikshya-settings-tab[data-tab="' + hash + '"]').length) {
                this.switchTab(hash);
            } else {
                // Set initial tab from URL parameter or default
                const urlParams = new URLSearchParams(window.location.search);
                const tabParam = urlParams.get('tab');
                if (tabParam && $('.sikshya-settings-tab[data-tab="' + tabParam + '"]').length) {
                    this.switchTab(tabParam);
                }
            }
        },

        // Handle tab switching
        handleTabSwitch: function(e) {
            e.preventDefault();
            const tab = $(this).data('tab');
            SikshyaSettings.switchTab(tab);
        },

        // Switch to a specific tab
        switchTab: function(tab) {
            if (tab === this.currentTab) {
                return;
            }

            // Update active tab
            $('.sikshya-settings-tab').removeClass('active');
            $('.sikshya-settings-tab[data-tab="' + tab + '"]').addClass('active');

            // Update current tab
            this.currentTab = tab;

            // Update URL
            this.updateURL(tab);

            // Load tab content via AJAX
            this.loadTabContent(tab);

            // Update form hidden field
            $('input[name="current_tab"]').val(tab);
        },

        // Load tab content via AJAX
        loadTabContent: function(tab) {
            const $content = $('#sikshya-settings-content');
            
            // Show skeleton loading state
            $content.html(`
                <div class="sikshya-skeleton-section">
                    <div class="sikshya-skeleton-header">
                        <div class="sikshya-skeleton-icon sikshya-skeleton"></div>
                        <div class="sikshya-skeleton-title sikshya-skeleton"></div>
                    </div>
                    <div class="sikshya-skeleton-content">
                        <div class="sikshya-skeleton-field">
                            <div class="sikshya-skeleton-label sikshya-skeleton"></div>
                            <div class="sikshya-skeleton-input sikshya-skeleton"></div>
                            <div class="sikshya-skeleton-description sikshya-skeleton"></div>
                        </div>
                        <div class="sikshya-skeleton-field">
                            <div class="sikshya-skeleton-label sikshya-skeleton"></div>
                            <div class="sikshya-skeleton-input sikshya-skeleton"></div>
                            <div class="sikshya-skeleton-description sikshya-skeleton"></div>
                        </div>
                        <div class="sikshya-skeleton-field">
                            <div class="sikshya-skeleton-label sikshya-skeleton"></div>
                            <div class="sikshya-skeleton-input sikshya-skeleton"></div>
                            <div class="sikshya-skeleton-description sikshya-skeleton"></div>
                        </div>
                    </div>
                </div>
                <div class="sikshya-skeleton-section">
                    <div class="sikshya-skeleton-header">
                        <div class="sikshya-skeleton-icon sikshya-skeleton"></div>
                        <div class="sikshya-skeleton-title sikshya-skeleton"></div>
                    </div>
                    <div class="sikshya-skeleton-content">
                        <div class="sikshya-skeleton-field">
                            <div class="sikshya-skeleton-label sikshya-skeleton"></div>
                            <div class="sikshya-skeleton-input sikshya-skeleton"></div>
                            <div class="sikshya-skeleton-description sikshya-skeleton"></div>
                        </div>
                        <div class="sikshya-skeleton-field">
                            <div class="sikshya-skeleton-label sikshya-skeleton"></div>
                            <div class="sikshya-skeleton-input sikshya-skeleton"></div>
                            <div class="sikshya-skeleton-description sikshya-skeleton"></div>
                        </div>
                    </div>
                </div>
            `);

            const nonce = window.sikshya_settings_nonce || sikshya_ajax.nonce;
            
            // Make AJAX request
            $.ajax({
                url: sikshya_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sikshya_load_settings_tab',
                    tab: tab,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        $content.html(response.data.content);
                        SikshyaSettings.showNotification('Settings loaded successfully.', 'success');
                    } else {
                        $content.html(`
                            <div class="sikshya-error">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>${response.data.message || 'Error loading settings.'}</span>
                            </div>
                        `);
                        SikshyaSettings.showNotification(response.data.message || 'Error loading settings.', 'error');
                    }
                },
                error: function() {
                    $content.html(`
                        <div class="sikshya-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Network error. Please try again.</span>
                        </div>
                    `);
                    SikshyaSettings.showNotification('Network error. Please try again.', 'error');
                }
            });
        },

        // Handle form submission
        handleFormSubmit: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitBtn = $form.find('.sikshya-save-settings');
            const originalText = $submitBtn.html();
            
            // Show loading state
            $submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
            
            // Collect form data
            const formData = new FormData($form[0]);
            const nonce = window.sikshya_settings_nonce || sikshya_ajax.nonce;
            formData.append('action', 'sikshya_save_settings');
            formData.append('nonce', nonce);
            formData.append('tab', SikshyaSettings.currentTab);
            
            // Convert FormData to object for settings
            const settings = {};
            for (let [key, value] of formData.entries()) {
                if (key !== 'action' && key !== 'nonce' && key !== 'current_tab') {
                    settings[key] = value;
                }
            }
            formData.append('settings', JSON.stringify(settings));
            
            // Make AJAX request
            $.ajax({
                url: sikshya_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        SikshyaSettings.showNotification(response.data.message, 'success');
                        
                        // Reload tab content to show updated values
                        SikshyaSettings.loadTabContent(SikshyaSettings.currentTab);
                    } else {
                        SikshyaSettings.showNotification(response.data.message, 'error');
                        
                        if (response.data.errors && response.data.errors.length > 0) {
                            console.error('Settings errors:', response.data.errors);
                        }
                    }
                },
                error: function() {
                    SikshyaSettings.showNotification('Network error. Please try again.', 'error');
                },
                complete: function() {
                    // Restore button state
                    $submitBtn.html(originalText).prop('disabled', false);
                }
            });
        },

        // Handle reset settings
        handleResetSettings: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to reset all settings for this tab to their default values? This action cannot be undone.')) {
                return;
            }
            
            const $btn = $(this);
            const originalText = $btn.html();
            
            // Show loading state
            $btn.html('<i class="fas fa-spinner fa-spin"></i> Resetting...').prop('disabled', true);
            
            // Make AJAX request
            const nonce = window.sikshya_settings_nonce || sikshya_ajax.nonce;
            $.ajax({
                url: sikshya_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sikshya_reset_settings',
                    tab: SikshyaSettings.currentTab,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        SikshyaSettings.showNotification(response.data.message, 'success');
                        
                        // Reload tab content to show reset values
                        SikshyaSettings.loadTabContent(SikshyaSettings.currentTab);
                    } else {
                        SikshyaSettings.showNotification(response.data.message, 'error');
                    }
                },
                error: function() {
                    SikshyaSettings.showNotification('Network error. Please try again.', 'error');
                },
                complete: function() {
                    // Restore button state
                    $btn.html(originalText).prop('disabled', false);
                }
            });
        },

        // Handle URL hash change
        handleHashChange: function() {
            const hash = window.location.hash.replace('#', '');
            if (hash && $('.sikshya-settings-tab[data-tab="' + hash + '"]').length) {
                SikshyaSettings.switchTab(hash);
            }
        },

        // Update URL without page reload
        updateURL: function(tab) {
            const url = new URL(window.location);
            url.searchParams.set('tab', tab);
            url.hash = tab;
            window.history.replaceState({}, '', url);
        },

        // Show notification
        showNotification: function(message, type = 'info') {
            // Remove existing notifications
            $('.sikshya-notification').remove();
            
            // Create notification element
            const $notification = $(`
                <div class="sikshya-notification sikshya-notification-${type}">
                    <div class="sikshya-notification-content">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'}"></i>
                        <span>${message}</span>
                    </div>
                    <button class="sikshya-notification-close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `);
            
            // Add to page
            $('.sikshya-settings-page').prepend($notification);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Close button functionality
            $notification.find('.sikshya-notification-close').on('click', function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            });
        },

        // Get setting value
        getSetting: function(key, defaultValue = '') {
            const $field = $('[name="' + key + '"]');
            if ($field.length) {
                if ($field.attr('type') === 'checkbox') {
                    return $field.is(':checked') ? '1' : '0';
                } else {
                    return $field.val() || defaultValue;
                }
            }
            return defaultValue;
        },

        // Set setting value
        setSetting: function(key, value) {
            const $field = $('[name="' + key + '"]');
            if ($field.length) {
                if ($field.attr('type') === 'checkbox') {
                    $field.prop('checked', value === '1' || value === true);
                } else {
                    $field.val(value);
                }
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        SikshyaSettings.init();
    });

})(jQuery); 