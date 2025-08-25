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
            // Wait for DOM to be ready and form to be loaded
            $(document).ready(() => {
                // Wait a bit more for dynamic content to load
                setTimeout(() => {
                    this.bindEvents();
                }, 500);
            });
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
         * Collect form data using FormData API
         */
        collectFormData: function() {
            const form = document.getElementById('sikshya-course-builder-form');
            
            if (!form) {
                console.error('Form not found!');
                return {};
            }
            
            // Use FormData to automatically collect all form fields
            const formData = new FormData(form);
            const data = {};
            
            // Convert FormData to plain object
            for (let [key, value] of formData.entries()) {
                // Skip nonce and action fields
                if (key !== 'sikshya_course_builder_nonce' && key !== 'action') {
                    data[key] = value;
                }
            }
            
            return data;
        },

        /**
         * Handle successful save
         */
        handleSaveSuccess: function(response) {
            if (response.course_id) {
                this.updateCourseIdInForm(response.course_id);
                
                // Update URL to use consistent course_id parameter
                const currentUrl = new URL(window.location);
                currentUrl.searchParams.set('course_id', response.course_id);
                
                // Remove the 'id' parameter if it exists to avoid confusion
                currentUrl.searchParams.delete('id');
                
                // Update the URL without page reload
                window.history.replaceState({}, '', currentUrl.toString());
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
            const originalHtml = submitButton.html();
            submitButton.prop('disabled', true).html('<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>Saving Draft...');
            
            // Collect form data
            const formData = this.collectFormData();
            formData.course_status = 'draft';
            
            // Save data
            this.saveData(formData, function(success, response) {
                submitButton.prop('disabled', false).html(originalHtml);
                
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
            const originalHtml = submitButton.html();
            submitButton.prop('disabled', true).html('<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18"/></svg>Publishing...');
            
            // Collect form data
            const formData = this.collectFormData();
            formData.course_status = 'published';
            
            // Save data
            this.saveData(formData, function(success, response) {
                submitButton.prop('disabled', false).html(originalHtml);
                
                if (success) {
                    this.handleSaveSuccess(response);
                } else {
                    this.handleSaveError(response);
                }
            }.bind(this));
        },

        /**
         * Save data via form submission
         */
        saveData: function(formData, callback) {
            const form = document.getElementById('sikshya-course-builder-form');
            
            if (!form) {
                callback(false, { message: 'Form not found' });
                return;
            }
            
            // Create a temporary form for submission
            const tempForm = document.createElement('form');
            tempForm.method = 'POST';
            tempForm.action = sikshya_ajax.ajax_url;
            tempForm.style.display = 'none';
            
            // Add action and nonce
            const actionField = document.createElement('input');
            actionField.type = 'hidden';
            actionField.name = 'action';
            actionField.value = 'sikshya_save_course_builder';
            tempForm.appendChild(actionField);
            
            const nonceField = document.createElement('input');
            nonceField.type = 'hidden';
            nonceField.name = 'nonce';
            nonceField.value = sikshya_ajax.nonce;
            tempForm.appendChild(nonceField);
            
            // Add all form data as hidden fields
            for (let [key, value] of Object.entries(formData)) {
                const field = document.createElement('input');
                field.type = 'hidden';
                field.name = key;
                field.value = value;
                tempForm.appendChild(field);
            }
            
            // Add course status
            const statusField = document.createElement('input');
            statusField.type = 'hidden';
            statusField.name = 'course_status';
            statusField.value = formData.course_status || 'draft';
            tempForm.appendChild(statusField);
            
            // Append form to body and submit
            document.body.appendChild(tempForm);
            
            // Use fetch to submit the form and handle response
            fetch(sikshya_ajax.ajax_url, {
                method: 'POST',
                body: new FormData(tempForm)
            })
            .then(response => response.json())
            .then(data => {
                // Remove temporary form
                document.body.removeChild(tempForm);
                
                if (data.success) {
                    callback(true, data.data);
                } else {
                    callback(false, data.data);
                }
            })
            .catch(error => {
                // Remove temporary form
                document.body.removeChild(tempForm);
                callback(false, { message: 'Network error occurred' });
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
            const currentCourseId = urlParams.get('course_id') || urlParams.get('id');
            
            if (response.course_id && !currentCourseId) {
                // First time saving - reload page with course ID after 2 seconds
                setTimeout(() => {
                    const newUrl = new URL(window.location);
                    newUrl.searchParams.set('course_id', response.course_id);
                    newUrl.searchParams.delete('id'); // Remove old 'id' parameter if it exists
                    newUrl.searchParams.set('tab', 'course');
                    window.location.href = newUrl.toString();
                }, 2000);
            } else if (response.course_id) {
                // Subsequent saves - just update the form and URL
                this.updateCourseIdInForm(response.course_id);
                
                // Update URL to use consistent course_id parameter
                const currentUrl = new URL(window.location);
                currentUrl.searchParams.set('course_id', response.course_id);
                
                // Remove the 'id' parameter if it exists to avoid confusion
                currentUrl.searchParams.delete('id');
                
                // Update the URL without page reload
                window.history.replaceState({}, '', currentUrl.toString());
            }
        },

        /**
         * Handle save error
         */
        handleSaveError: function(response) {
            // Clear previous field errors
            $('.sikshya-field-error').remove();
            $('.sikshya-form-row').removeClass('has-error');
            
            // Handle validation errors
            if (response.course || response.pricing) {
                let errorMessages = [];
                let firstErrorField = null;
                let firstErrorTab = null;
                
                // Process course validation errors
                if (response.course) {
                    Object.keys(response.course).forEach(function(field) {
                        const errorMessage = response.course[field];
                        const fieldElement = $(`[name="${field}"]`);
                        
                        if (fieldElement.length) {
                            const formRow = fieldElement.closest('.sikshya-form-row');
                            formRow.addClass('has-error');
                            formRow.append(`<div class="sikshya-field-error">${errorMessage}</div>`);
                            
                            // Track first error field and its tab
                            if (!firstErrorField) {
                                firstErrorField = fieldElement;
                                firstErrorTab = this.getFieldTab(fieldElement);
                            }
                        }
                        
                        errorMessages.push(errorMessage);
                    }.bind(this));
                }
                
                // Process pricing validation errors
                if (response.pricing) {
                    Object.keys(response.pricing).forEach(function(field) {
                        const errorMessage = response.pricing[field];
                        const fieldElement = $(`[name="${field}"]`);
                        
                        if (fieldElement.length) {
                            const formRow = fieldElement.closest('.sikshya-form-row');
                            formRow.addClass('has-error');
                            formRow.append(`<div class="sikshya-field-error">${errorMessage}</div>`);
                            
                            // Track first error field and its tab
                            if (!firstErrorField) {
                                firstErrorField = fieldElement;
                                firstErrorTab = this.getFieldTab(fieldElement);
                            }
                        }
                        
                        errorMessages.push(errorMessage);
                    }.bind(this));
                }
                
                // Show the first error message in notification
                if (errorMessages.length > 0) {
                    this.showNotification('error', errorMessages[0]);
                } else {
                    this.showNotification('error', response.message || 'Validation failed');
                }
                
                // Switch to error tab and focus on first error field
                if (firstErrorTab && firstErrorField) {
                    this.switchToTabAndFocus(firstErrorTab, firstErrorField);
                }
            } else {
                // Handle general errors
                this.showNotification('error', response.message || 'Failed to save course');
            }
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
        },

        /**
         * Get the tab ID for a field
         */
        getFieldTab: function(fieldElement) {
            // Find which tab contains this field
            const tabContent = fieldElement.closest('.sikshya-tab-content');
            if (tabContent.length) {
                return tabContent.attr('id');
            }
            return null;
        },

        /**
         * Switch to a specific tab and focus on a field
         */
        switchToTabAndFocus: function(tabId, fieldElement) {
            // Switch to the tab
            this.switchTab(tabId);
            
            // Wait a bit for the tab switch animation to complete
            setTimeout(() => {
                // Scroll to the field
                $('html, body').animate({
                    scrollTop: fieldElement.offset().top - 100
                }, 500);
                
                // Focus on the field
                fieldElement.focus();
                
                // Add a highlight effect
                fieldElement.addClass('sikshya-error-highlight');
                setTimeout(() => {
                    fieldElement.removeClass('sikshya-error-highlight');
                }, 2000);
            }, 300);
        },

        /**
         * Switch to a specific tab
         */
        switchTab: function(tabId) {
            // Hide all tab contents
            $('.sikshya-tab-content').removeClass('active');
            
            // Show the target tab content
            $(`#${tabId}`).addClass('active');
            
            // Update navigation links
            $('.sikshya-nav-link').removeClass('active');
            $(`.sikshya-nav-link[data-tab="${tabId}"]`).addClass('active');
            
            // Update URL
            const url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.history.pushState({}, '', url);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if (window.SikshyaCourseBuilderSave) {
            window.SikshyaCourseBuilderSave.init();
        }
    });

})(jQuery);
