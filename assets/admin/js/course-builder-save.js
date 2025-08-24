/**
 * Dynamic Course Builder Save Handler
 * 
 * @package Sikshya
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Course Builder Save Handler
    window.SikshyaCourseBuilderSave = {
        /**
         * Initialize the course builder
         */
        init: function() {
            console.log('CourseBuilderSave initializing...');
            console.log('sikshya_ajax object:', window.sikshya_ajax);
            this.bindEvents();
            this.loadExistingData();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Prevent ALL form submissions - we handle everything via AJAX
            $(document).on('submit', '#sikshya-course-builder-form', function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            });
            
            // Prevent Enter key from submitting forms
            $(document).on('keypress', '#sikshya-course-builder-form input, #sikshya-course-builder-form textarea', function(e) {
                if (e.which === 13) { // Enter key
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            });
            
            // Save Draft button
            $(document).on('click', '#save-draft-btn, #sidebar-save-draft-btn', this.handleSaveDraft.bind(this));
            
            // Publish Course button
            $(document).on('click', '#publish-course-btn, #sidebar-publish-btn', this.handlePublishCourse.bind(this));
            
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
        },

        /**
         * Handle Save Draft button click
         */
        handleSaveDraft: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const form = $('#sikshya-course-builder-form');
            const statusField = $('#course-status-field');
            const submitButton = $(e.currentTarget);
            
            if (!form.length || !statusField.length) {
                console.error('Form elements not found');
                return;
            }
            
            // Set status to draft
            statusField.val('draft');
            
            // Show loading state
            const originalText = submitButton.text();
            submitButton.prop('disabled', true).text('Saving Draft...');
            
            // Collect form data
            const formData = this.collectFormData();
            formData.course_status = 'draft';
            
            // Save data
            this.saveData(formData, function(success, response) {
                submitButton.prop('disabled', false).text(originalText);
                
                if (success) {
                    this.handleSaveSuccess(response);
                } else {
                    this.handleSaveError(response);
                }
            }.bind(this));
        },

        /**
         * Handle Publish Course button click
         */
        handlePublishCourse: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const form = $('#sikshya-course-builder-form');
            const statusField = $('#course-status-field');
            const submitButton = $(e.currentTarget);
            
            if (!form.length || !statusField.length) {
                console.error('Form elements not found');
                return;
            }
            
            // Validate required fields before publishing
            const errors = this.validateForm(this.collectFormData());
            if (errors.length > 0) {
                this.showFieldErrors(errors);
                this.showNotification('error', 'Please fill in all required fields before publishing');
                return;
            }
            
            // Set status to published
            statusField.val('published');
            
            // Show loading state
            const originalText = submitButton.text();
            submitButton.prop('disabled', true).text('Publishing...');
            
            // Collect form data
            const formData = this.collectFormData();
            formData.course_status = 'published';
            
            // Save data
            this.saveData(formData, function(success, response) {
                submitButton.prop('disabled', false).text(originalText);
                
                if (success) {
                    this.handleSaveSuccess(response);
                } else {
                    this.handleSaveError(response);
                }
            }.bind(this));
        },

        /**
         * Save data via AJAX
         */
        saveData: function(formData, callback) {
            console.log('saveData method called with:', formData);
            console.log('sikshya_ajax available:', typeof sikshya_ajax !== 'undefined');
            if (typeof sikshya_ajax !== 'undefined') {
                console.log('sikshya_ajax object:', sikshya_ajax);
            }
            
            const ajaxData = {
                action: 'sikshya_save_course_builder',
                nonce: sikshya_ajax.nonce,
                data: formData,
                course_status: formData.course_status || 'draft'
            };
            
            console.log('Sending AJAX request to save course:', {
                url: sikshya_ajax.ajax_url,
                action: 'sikshya_save_course_builder',
                nonce: sikshya_ajax.nonce,
                data: formData
            });
            
            console.log('Full AJAX data being sent:', ajaxData);
            
            $.ajax({
                url: sikshya_ajax.ajax_url,
                type: 'POST',
                data: ajaxData,
                success: function(response) {
                    if (response.success) {
                        callback(true, response.data);
                    } else {
                        callback(false, response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    callback(false, { message: 'Network error occurred' });
                }
            });
        },

        /**
         * Handle successful save
         */
        handleSaveSuccess: function(response) {
            const message = response.status === 'published' 
                ? 'Course published successfully!' 
                : 'Course draft saved successfully!';
            
            this.showNotification('success', message);
            
            // Check if this is the first time saving (no course ID in URL)
            const urlParams = new URLSearchParams(window.location.search);
            const currentCourseId = urlParams.get('id');
            
            if (response.course_id && !currentCourseId) {
                // First time saving - reload page with course ID after 2 seconds
                setTimeout(() => {
                    const newUrl = new URL(window.location);
                    newUrl.searchParams.set('id', response.course_id);
                    newUrl.searchParams.set('tab', 'course');
                    window.location.href = newUrl.toString();
                }, 2000);
            } else if (response.course_id) {
                // Subsequent saves - just update the form
                this.updateCourseIdInForm(response.course_id);
            }
        },

        /**
         * Handle save error
         */
        handleSaveError: function(response) {
            this.showNotification('error', response.message || 'Failed to save course');
        },

        /**
         * Update course ID in form
         */
        updateCourseIdInForm: function(courseId) {
            const courseIdField = $('input[name="course_id"]');
            if (courseIdField.length) {
                courseIdField.val(courseId);
            }
            
            // Update URL if needed
            const url = new URL(window.location);
            url.searchParams.set('course_id', courseId);
            window.history.pushState({}, '', url);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        window.SikshyaCourseBuilder.init();
    });

})(jQuery);
