// Enhanced Sikshya LMS Settings Page JavaScript

jQuery(document).ready(function($) {
    // Initialize settings page
    initializeSettingsPage();
    
    // Load settings content for current tab
    loadSettingsContent(getCurrentTab());
    
    // Handle tab clicks
    $('.sikshya-settings-tab').on('click', function(e) {
        e.preventDefault();
        const tab = $(this).data('tab');
        
        // Load content directly
        loadSettingsContent(tab);
        
        // Update URL without page reload
        const url = new URL(window.location);
        url.searchParams.set('tab', tab);
        window.history.pushState({}, '', url);
        
        // Update active tab
        $('.sikshya-settings-tab').removeClass('active');
        $(this).addClass('active');
    });
    
    // Handle form submission with enhanced UX
    $('#sikshya-settings-form').on('submit', function(e) {
        e.preventDefault();
        saveSettings();
    });
    
    // Handle reset button with confirmation
    $('.sikshya-reset-settings').on('click', function() {
        const tab = $(this).data('tab');
        const button = $(this);
        
        // Show confirmation dialog
        if (confirm('Are you sure you want to reset all settings for this tab to defaults? This action cannot be undone.')) {
            button.addClass('loading').prop('disabled', true);
            resetSettings(tab);
            
            setTimeout(() => {
                button.removeClass('loading').prop('disabled', false);
            }, 2000);
        }
    });
    
    
    // Checkbox animations
    $('.sikshya-checkbox-label input[type="checkbox"]').on('change', function() {
        const field = $(this).closest('.sikshya-settings-field');
        if (this.checked) {
            field.addClass('success').removeClass('error');
        } else {
            field.removeClass('success error');
        }
    });
    
    // Auto-save indicator
    let saveTimeout;
    $('.sikshya-settings-field input, .sikshya-settings-field select, .sikshya-settings-field textarea').on('input change', function() {
        clearTimeout(saveTimeout);
        showAutoSaveIndicator();
        
        saveTimeout = setTimeout(() => {
            hideAutoSaveIndicator();
        }, 2000);
    });
    
    // Enhanced form field interactions for existing functionality
    $(document).on('change', '#prerequisite_check_type', function() {
        const selectedValue = $(this).val();
        const $gradeField = $('#minimum_grade_prerequisite');
        const $gradeLabel = $('#minimum_grade_label');
        const $gradeDesc = $('#minimum_grade_desc');
        
        if (selectedValue === 'grade') {
            $gradeField.show();
            $gradeLabel.show();
            $gradeDesc.show();
        } else {
            $gradeField.hide();
            $gradeLabel.hide();
            $gradeDesc.hide();
        }
    });
    $('#prerequisite_check_type').trigger('change');

    // Courses/Progress Tab: Completion criteria fields
    $(document).on('change', '#course_completion_criteria, #completion_method', function() {
        const selectedValue = $(this).val();
        const $percentageField = $('#completion_percentage');
        const $percentageLabel = $('#completion_percentage_label');
        const $percentageDesc = $('#completion_percentage_desc');
        const $timeField = $('#minimum_time_minutes');
        const $timeLabel = $('#minimum_time_label');
        const $timeDesc = $('#minimum_time_desc');
        
        // Hide all fields first
        $percentageField.hide();
        $percentageLabel.hide();
        $percentageDesc.hide();
        $timeField && $timeField.hide();
        $timeLabel && $timeLabel.hide();
        $timeDesc && $timeDesc.hide();
        
        // Show relevant fields
        if (selectedValue === 'percentage') {
            $percentageField.show();
            $percentageLabel.show();
            $percentageDesc.show();
        } else if (selectedValue === 'time_spent') {
            $timeField && $timeField.show();
            $timeLabel && $timeLabel.show();
            $timeDesc && $timeDesc.show();
        }
    });
    $('#course_completion_criteria, #completion_method').trigger('change');

    // Integrations Tab: AWS fields
    $(document).on('change', '#cloud_storage_provider', function() {
        const selectedValue = $(this).val();
        const $awsFields = $('#aws_access_key, #aws_access_key_label, #aws_access_key_desc, #aws_secret_key, #aws_secret_key_label, #aws_secret_key_desc, #aws_bucket, #aws_bucket_label, #aws_bucket_desc');
        
        if (selectedValue === 'aws') {
            $awsFields.show();
        } else {
            $awsFields.hide();
        }
    });
    $('#cloud_storage_provider').trigger('change');
});

function initializeSettingsPage() {
    // Initialize tooltips
    jQuery('.sikshya-settings-field .description').each(function() {
        jQuery(this).attr('title', jQuery(this).text());
    });
    
    // Add progress indicator
    jQuery('body').append('<div class="sikshya-settings-progress"><div class="sikshya-settings-progress-bar"></div></div>');
}

function getCurrentTab() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('tab') || 'general';
}

function showAutoSaveIndicator() {
    if (jQuery('.auto-save-indicator').length === 0) {
        jQuery('.sikshya-settings-actions').prepend(
            '<div class="auto-save-indicator" style="position: absolute; top: -30px; right: 0; background: #00a32a; color: white; padding: 5px 10px; border-radius: 4px; font-size: 12px;">Auto-saving...</div>'
        );
    }
    jQuery('.auto-save-indicator').show();
}

function hideAutoSaveIndicator() {
    jQuery('.auto-save-indicator').hide();
    setTimeout(() => {
        jQuery('.auto-save-indicator').remove();
    }, 100);
}

function showNotification(message, type = 'info') {
    const notification = jQuery(`
        <div class="sikshya-notification ${type}">
            ${message}
        </div>
    `);
    
    jQuery('body').append(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

function updateProgressBar(percentage) {
    jQuery('.sikshya-settings-progress-bar').css('width', percentage + '%');
}

function loadSettingsContent(tab) {
    const $content = jQuery('#sikshya-settings-content');
    $content.html('<div class="sikshya-loading"><i class="fas fa-spinner fa-spin"></i><span>Loading settings...</span></div>');
    
    updateProgressBar(30);
    
    jQuery.ajax({
        url: sikshya_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'sikshya_load_settings_tab',
            tab: tab,
            nonce: sikshya_ajax.nonce
        },
        success: function(response) {
            updateProgressBar(100);
            if (response.success) {
                $content.html(response.data.html);
                
                // Update the settings header
                if (response.data.header) {
                    const header = response.data.header;
                    jQuery('.sikshya-settings-header h2').html('<i class="' + header.icon + '"></i> ' + header.title);
                    jQuery('.sikshya-settings-header p').text(header.description);
                }
                
                // Re-initialize form interactions for new content
                initializeFormInteractions();
                showNotification('Settings loaded successfully', 'success');
            } else {
                $content.html('<div class="notice notice-error"><p>Error loading settings: ' + response.data + '</p></div>');
                showNotification('Failed to load settings', 'error');
            }
        },
        error: function() {
            updateProgressBar(100);
            $content.html('<div class="notice notice-error"><p>Failed to load settings. Please try again.</p></div>');
            showNotification('Network error occurred', 'error');
        }
    });
}

function saveSettings() {
    const form = jQuery('#sikshya-settings-form');
    const formData = new FormData(form[0]);
    const currentTab = getCurrentTab();
    
    // Add loading state
    jQuery('.sikshya-save-settings').addClass('loading').prop('disabled', true);
    updateProgressBar(20);
    
    jQuery.ajax({
        url: sikshya_ajax.ajax_url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            updateProgressBar(100);
            if (response.success) {
                showNotification('Settings saved successfully!', 'success');
                // Reload the current tab to show updated values
                loadSettingsContent(currentTab);
            } else {
                showNotification('Failed to save settings: ' + response.data, 'error');
            }
        },
        error: function() {
            updateProgressBar(100);
            showNotification('Network error occurred while saving', 'error');
        },
        complete: function() {
            setTimeout(() => {
                jQuery('.sikshya-save-settings').removeClass('loading').prop('disabled', false);
                updateProgressBar(0);
            }, 1000);
        }
    });
}

function resetSettings(tab) {
    updateProgressBar(20);
    
    jQuery.ajax({
        url: sikshya_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'sikshya_reset_settings_tab',
            tab: tab,
            nonce: sikshya_ajax.nonce
        },
        success: function(response) {
            updateProgressBar(100);
            if (response.success) {
                showNotification('Settings reset to defaults successfully!', 'success');
                // Reload the current tab to show reset values
                loadSettingsContent(tab);
            } else {
                showNotification('Failed to reset settings: ' + response.data, 'error');
            }
        },
        error: function() {
            updateProgressBar(100);
            showNotification('Network error occurred while resetting', 'error');
        },
        complete: function() {
            setTimeout(() => {
                updateProgressBar(0);
            }, 1000);
        }
    });
}

function initializeFormInteractions() {
    
    
    // Re-initialize checkbox animations
    jQuery('.sikshya-checkbox-label input[type="checkbox"]').off('change').on('change', function() {
        const field = jQuery(this).closest('.sikshya-settings-field');
        if (this.checked) {
            field.addClass('success').removeClass('error');
        } else {
            field.removeClass('success error');
        }
    });
    
    // Re-initialize auto-save indicator
    let saveTimeout;
    jQuery('.sikshya-settings-field input, .sikshya-settings-field select, .sikshya-settings-field textarea').off('input change').on('input change', function() {
        clearTimeout(saveTimeout);
        showAutoSaveIndicator();
        
        saveTimeout = setTimeout(() => {
            hideAutoSaveIndicator();
        }, 2000);
    });
} 