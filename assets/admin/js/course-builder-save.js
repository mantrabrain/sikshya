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
            this.loadExistingData();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Form submission
            $('#sikshya-course-builder-form').on('submit', this.handleFormSubmit.bind(this));
            
            // Tab switching
            $(document).on('click', '.sikshya-nav-link', this.handleTabSwitch.bind(this));
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
            if (response.course_id) {
                this.updateCourseIdInForm(response.course_id);
            }
            
            this.showNotification('success', 'Course saved successfully!');
        },

        /**
         * Handle save error
         */
        handleSaveError: function(response) {
            this.showNotification('error', response.message || 'Failed to save course');
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
                switch(type) {
                    case 'success':
                        window.SikshyaToast.successMessage(message);
                        break;
                    case 'error':
                        window.SikshyaToast.errorMessage(message);
                        break;
                    case 'warning':
                        window.SikshyaToast.warningMessage(message);
                        break;
                    case 'info':
                        window.SikshyaToast.infoMessage(message);
                        break;
                    default:
                        window.SikshyaToast.infoMessage(message);
                }
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
