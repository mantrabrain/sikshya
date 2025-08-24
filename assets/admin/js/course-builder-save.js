/**
 * Dynamic Course Builder Save Handler
 * 
 * @package Sikshya
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Course Builder Save Handler
    window.SikshyaCourseBuilder = {
        /**
         * Initialize the course builder
         */
        init: function() {
            this.bindEvents();
            this.initAutoSave();
            this.loadExistingData();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Form submission
            $('#sikshya-course-builder-form').on('submit', this.handleFormSubmit.bind(this));
            
            // Field changes for auto-save
            $('#sikshya-course-builder-form').on('change', 'input, select, textarea', this.handleFieldChange.bind(this));
            
            // Tab switching
            $(document).on('click', '.sikshya-nav-link', this.handleTabSwitch.bind(this));
            
            // Auto-save toggle
            $(document).on('click', '.sikshya-auto-save-toggle', this.toggleAutoSave.bind(this));
        },

        /**
         * Handle field changes for auto-save
         */
        handleFieldChange: function(e) {
            const field = $(e.target);
            const fieldName = field.attr('name');
            
            if (!fieldName) return;
            
            // Update save status
            this.updateSaveStatus('unsaved');
            
            // Schedule auto-save
            this.scheduleAutoSave();
        },

        /**
         * Get field value based on type
         */
        getFieldValue: function(field) {
            const type = field.attr('type');
            
            if (type === 'checkbox') {
                return field.is(':checked') ? '1' : '0';
            } else if (type === 'radio') {
                return field.filter(':checked').val() || '';
            } else {
                return field.val() || '';
            }
        },

        /**
         * Handle form submission
         */
        handleFormSubmit: function(e) {
            e.preventDefault();
            
            const form = $(e.target);
            const submitButton = form.find('button[type="submit"]');
            const originalText = submitButton.text();
            
            // Show loading state
            submitButton.prop('disabled', true).text('Saving...');
            
            // Collect form data
            const formData = this.collectFormData();
            
            // Validate form
            const errors = this.validateForm(formData);
            if (errors.length > 0) {
                this.showFieldErrors(errors);
                submitButton.prop('disabled', false).text(originalText);
                return;
            }
            
            // Save data
            this.saveData(formData, function(success, response) {
                if (success) {
                    this.handleSaveSuccess(response);
                } else {
                    this.handleSaveError(response);
                }
                submitButton.prop('disabled', false).text(originalText);
            }.bind(this));
        },

        /**
         * Save data via AJAX
         */
        saveData: function(data, callback) {
            $.ajax({
                url: sikshya_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sikshya_save_course_builder',
                    nonce: sikshya_ajax.nonce,
                    data: data
                },
                success: function(response) {
                    if (response.success) {
                        callback(true, response.data);
                    } else {
                        callback(false, response.data);
                    }
                },
                error: function(xhr, status, error) {
                    callback(false, { message: 'Network error occurred' });
                }
            });
        },

        /**
         * Collect form data
         */
        collectFormData: function() {
            const formData = {};
            const form = $('#sikshya-course-builder-form');
            
            // Collect all form fields
            form.find('input, select, textarea').each(function() {
                const field = $(this);
                const name = field.attr('name');
                
                if (name) {
                    const value = this.getFieldValue(field);
                    formData[name] = value;
                }
            }.bind(this));
            
            return formData;
        },

        /**
         * Handle successful save
         */
        handleSaveSuccess: function(response) {
            this.updateSaveStatus('saved');
            this.updateLastSavedTime();
            
            if (response.course_id) {
                this.updateCourseIdInForm(response.course_id);
            }
            
            this.showNotification('success', 'Course saved successfully!');
        },

        /**
         * Handle save error
         */
        handleSaveError: function(response) {
            this.updateSaveStatus('error');
            this.showNotification('error', response.message || 'Failed to save course');
        },

        /**
         * Initialize auto-save functionality
         */
        initAutoSave: function() {
            this.autoSaveEnabled = true;
            this.autoSaveInterval = null;
            this.autoSaveDelay = 3000; // 3 seconds
            
            // Create auto-save status indicator
            this.createAutoSaveIndicator();
        },

        /**
         * Create auto-save status indicator
         */
        createAutoSaveIndicator: function() {
            const indicator = $(`
                <div class="sikshya-auto-save-status">
                    <span class="sikshya-auto-save-text">All changes saved</span>
                    <button class="sikshya-auto-save-toggle" title="Toggle auto-save">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </button>
                </div>
            `);
            
            $('.sikshya-header-actions').append(indicator);
        },

        /**
         * Start auto-save timer
         */
        startAutoSave: function() {
            this.stopAutoSave();
            
            if (this.autoSaveEnabled) {
                this.autoSaveInterval = setTimeout(function() {
                    this.performAutoSave();
                }.bind(this), this.autoSaveDelay);
            }
        },

        /**
         * Stop auto-save timer
         */
        stopAutoSave: function() {
            if (this.autoSaveInterval) {
                clearTimeout(this.autoSaveInterval);
                this.autoSaveInterval = null;
            }
        },

        /**
         * Toggle auto-save
         */
        toggleAutoSave: function() {
            this.autoSaveEnabled = !this.autoSaveEnabled;
            
            if (this.autoSaveEnabled) {
                this.showNotification('info', 'Auto-save enabled');
            } else {
                this.showNotification('info', 'Auto-save disabled');
            }
        },

        /**
         * Schedule auto-save
         */
        scheduleAutoSave: function() {
            this.updateSaveStatus('saving');
            this.startAutoSave();
        },

        /**
         * Perform auto-save
         */
        performAutoSave: function() {
            const formData = this.collectFormData();
            
            this.saveData(formData, function(success, response) {
                if (success) {
                    this.updateSaveStatus('saved');
                    this.updateLastSavedTime();
                } else {
                    this.updateSaveStatus('error');
                }
            }.bind(this));
        },

        /**
         * Load existing course data
         */
        loadExistingData: function() {
            const courseId = window.sikshyaCourseBuilder.courseId;
            
            if (courseId && courseId > 0) {
                this.loadCourseData(courseId);
            }
        },

        /**
         * Load course data via AJAX
         */
        loadCourseData: function(courseId) {
            $.ajax({
                url: sikshya_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sikshya_load_course_data',
                    nonce: sikshya_ajax.nonce,
                    course_id: courseId
                },
                success: function(response) {
                    if (response.success) {
                        this.populateFormData(response.data);
                    }
                }.bind(this)
            });
        },

        /**
         * Populate form with existing data
         */
        populateFormData: function(data) {
            const form = $('#sikshya-course-builder-form');
            
            Object.keys(data).forEach(function(fieldName) {
                const field = form.find(`[name="${fieldName}"]`);
                if (field.length) {
                    this.setFieldValue(field, data[fieldName]);
                }
            }.bind(this));
        },

        /**
         * Set field value based on type
         */
        setFieldValue: function(field, value) {
            const type = field.attr('type');
            
            if (type === 'checkbox') {
                field.prop('checked', value === '1' || value === true);
            } else if (type === 'radio') {
                field.filter(`[value="${value}"]`).prop('checked', true);
            } else {
                field.val(value);
            }
        },

        /**
         * Handle tab switching
         */
        handleTabSwitch: function(e) {
            const tabId = $(e.currentTarget).data('tab');
            
            // Update active tab
            window.sikshyaCourseBuilder.activeTab = tabId;
            
            // Update URL without page reload
            const url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.history.pushState({}, '', url);
        },

        /**
         * Validate form data
         */
        validateForm: function(data) {
            const errors = [];
            
            // Basic validation
            if (!data.title || data.title.trim() === '') {
                errors.push({ field: 'title', message: 'Course title is required' });
            }
            
            if (!data.description || data.description.trim() === '') {
                errors.push({ field: 'description', message: 'Course description is required' });
            }
            
            return errors;
        },

        /**
         * Show field errors
         */
        showFieldErrors: function(errors) {
            // Clear previous errors
            $('.sikshya-field-error').remove();
            $('.sikshya-form-row').removeClass('has-error');
            
            // Show new errors
            errors.forEach(function(error) {
                const field = $(`[name="${error.field}"]`);
                const formRow = field.closest('.sikshya-form-row');
                
                formRow.addClass('has-error');
                formRow.append(`<div class="sikshya-field-error">${error.message}</div>`);
            });
        },

        /**
         * Update save status
         */
        updateSaveStatus: function(status) {
            const statusElement = $('.sikshya-auto-save-text');
            
            switch (status) {
                case 'saved':
                    statusElement.text('All changes saved');
                    statusElement.removeClass('saving error').addClass('saved');
                    break;
                case 'saving':
                    statusElement.text('Saving...');
                    statusElement.removeClass('saved error').addClass('saving');
                    break;
                case 'unsaved':
                    statusElement.text('Unsaved changes');
                    statusElement.removeClass('saved saving').addClass('error');
                    break;
                case 'error':
                    statusElement.text('Save failed');
                    statusElement.removeClass('saved saving').addClass('error');
                    break;
            }
        },

        /**
         * Update last saved time
         */
        updateLastSavedTime: function() {
            const now = new Date();
            const timeString = now.toLocaleTimeString();
            
            $('.sikshya-auto-save-text').attr('title', `Last saved: ${timeString}`);
        },

        /**
         * Update course ID in form
         */
        updateCourseIdInForm: function(courseId) {
            $('input[name="course_id"]').val(courseId);
            window.sikshyaCourseBuilder.courseId = courseId;
        },

        /**
         * Get course ID from URL
         */
        getCourseIdFromUrl: function() {
            const urlParams = new URLSearchParams(window.location.search);
            return parseInt(urlParams.get('course_id')) || 0;
        },

        /**
         * Show notification
         */
        showNotification: function(type, message) {
            if (window.SikshyaToast) {
                window.SikshyaToast[type](message);
            } else {
                // Fallback to alert
                alert(message);
            }
        },

        /**
         * Update content item (for curriculum)
         */
        updateContentItem: function(itemId, data) {
            $.ajax({
                url: sikshya_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sikshya_save_content_type',
                    nonce: sikshya_ajax.nonce,
                    item_id: itemId,
                    data: data
                },
                success: function(response) {
                    if (response.success) {
                        this.showNotification('success', 'Content updated successfully');
                    } else {
                        this.showNotification('error', response.data.message || 'Failed to update content');
                    }
                }.bind(this)
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        window.SikshyaCourseBuilder.init();
    });

})(jQuery);
