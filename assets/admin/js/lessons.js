/**
 * Lessons JavaScript
 * Handles lesson form submission and validation
 */

(function($) {
    'use strict';

    // Global variables
    let currentContentType = null;

    // Initialize when document is ready
    $(document).ready(function() {
        initLessonForms();
        initLessonTabs();
    });

    /**
     * Initialize lesson tabs functionality
     */
    function initLessonTabs() {
        // Handle tab button clicks
        $(document).on('click', '.sikshya-tab-btn', function(e) {
            // Check if we're in course builder context - if so, let course-builder.js handle it
            if (window.location.href.includes('sikshya-add-course') || 
                document.querySelector('.sikshya-course-builder') ||
                e.target.closest('.sikshya-modal-overlay')) {
                return; // Let course-builder.js handle this
            }
            
            e.preventDefault();
            
            const $btn = $(this);
            const tabId = $btn.data('tab');
            
            // Remove active class from all buttons and panels
            $('.sikshya-tab-btn').removeClass('active');
            $('.sikshya-tab-panel').removeClass('active');
            
            // Add active class to clicked button and corresponding panel
            $btn.addClass('active');
            $('#' + tabId).addClass('active');
        });
    }

    /**
     * Initialize lesson forms
     */
    function initLessonForms() {
        // Handle lesson form submission
        $(document).on('submit', '.sikshya-lesson-form', function(e) {
            e.preventDefault();
            handleLessonFormSubmit($(this));
        });

        // Handle video source toggle
        $(document).on('change', '#video-lesson-source', function() {
            toggleVideoSource();
        });

        // Handle file uploads
        $(document).on('change', 'input[type="file"]', function() {
            handleFileUpload(this);
        });

        // Handle add lesson button click
        $(document).on('click', '.sikshya-add-lesson-btn', function(e) {
            e.preventDefault();
            showContentTypeSelection();
        });

        // Handle content type card clicks (single lesson page context)
        $(document).on('click', '.sikshya-content-type-card', function(e) {
            e.preventDefault();
            const contentType = $(this).data('content-type');
            if (contentType) {
                // Redirect to lesson form page
                const url = new URL(window.location.href);
                url.searchParams.set('type', contentType);
                window.location.href = url.toString();
            }
        });


        // Handle lesson content type selection (modal)
        $(document).on('click', '.sikshya-modal .sikshya-content-type', function(e) {
            e.preventDefault();
            const contentType = $(this).data('content-type');
            if (contentType) {
                selectContentType(contentType);
            }
        });

        // Handle page content type selection (direct page)
        $(document).on('click', '.sikshya-content-card-body .sikshya-content-type', function(e) {
            e.preventDefault();
            const contentType = $(this).data('content-type');
            if (contentType) {
                proceedToContentFormPage(contentType);
            }
        });

        // Handle lesson modal continue button (only for standalone lesson creation)
        $(document).on('click', '.sikshya-modal-footer .sikshya-btn-primary', function(e) {
            e.preventDefault();
            
            // Check if we're in course builder context - if so, let course-builder.js handle it
            if (window.location.href.includes('sikshya-add-course') || 
                document.querySelector('.sikshya-course-builder')) {
                return; // Let course-builder.js handle this
            }
            
            if (!$(this).prop('disabled')) {
                proceedToContentForm();
            }
        });

        // Handle lesson modal cancel button
        $(document).on('click', '.sikshya-modal-footer .sikshya-btn-secondary', function(e) {
            e.preventDefault();
            SikshyaModal.close();
        });
    }

    /**
     * Select content type (called from modal template)
     */
    window.selectContentType = function(type) {
        console.log('selectContentType called with type:', type);
        
        // Remove previous selection
        document.querySelectorAll('.sikshya-content-type').forEach(item => {
            item.classList.remove('selected');
        });
        
        // Add selection to clicked item
        event.target.closest('.sikshya-content-type').classList.add('selected');
        
        // Enable continue button
        const continueBtn = document.querySelector('.sikshya-modal-footer .sikshya-btn-primary');
        if (continueBtn) {
            continueBtn.disabled = false;
        }
        
        currentContentType = type;
        console.log('currentContentType set to:', currentContentType);
    }

    /**
     * Close modal (called from modal template)
     */
    window.closeModal = function(button) {
        console.log('closeModal called');
        
        // Find the modal overlay and close it
        const modalOverlay = button.closest('.sikshya-modal-overlay');
        if (modalOverlay) {
            modalOverlay.classList.remove('active');
            setTimeout(() => {
                modalOverlay.remove();
            }, 300);
        }
        
        // Reset current content type
        currentContentType = null;
    }

                        /**
                     * Proceed to content form (called from modal template)
                     */
                    window.proceedToContentForm = function() {
                        console.log('proceedToContentForm called, currentContentType:', currentContentType);
                        
                        if (!currentContentType) {
                            console.error('No content type selected');
                            return;
                        }
                        
                        // Redirect to add lesson page with selected content type
                        const addLessonUrl = sikshya_ajax.admin_url + 'admin.php?page=' + sikshya_ajax.add_lesson_page + '&type=' + currentContentType;
                        window.location.href = addLessonUrl;
                    }

                    /**
                     * Proceed to content form page (direct page flow)
                     */
                    window.proceedToContentFormPage = function(contentType) {
                        console.log('proceedToContentFormPage called with contentType:', contentType);
                        
                        if (!contentType) {
                            console.error('No content type provided');
                            return;
                        }
                        
                        // Redirect to add lesson page with selected content type
                        const addLessonUrl = sikshya_ajax.admin_url + 'admin.php?page=' + sikshya_ajax.add_lesson_page + '&type=' + contentType;
                        window.location.href = addLessonUrl;
                    }





    /**
     * Show custom modal using SikshyaModal system
     */
    function showCustomModal(title, content, subtitle = '') {
        // Create modal HTML using Sikshya modal structure
        const modalHtml = `
            <div class="sikshya-modal-overlay">
                <div class="sikshya-modal sikshya-modal-full">
                    <div class="sikshya-modal-header">
                        <button class="sikshya-modal-close">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                        <div class="sikshya-modal-header-content">
                            <div class="sikshya-modal-title-wrapper">
                                <h3 class="sikshya-modal-title">${title}</h3>
                            </div>
                            ${subtitle ? `<p class="sikshya-modal-subtitle">${subtitle}</p>` : ''}
                        </div>
                    </div>
                    <div class="sikshya-modal-body" id="custom-modal-body">
                        ${content}
                    </div>
                </div>
            </div>
        `;

        // Remove existing modal
        $('.sikshya-modal-overlay').remove();
        
        // Add new modal to SikshyaModal container
        $('#sikshya-modal-container').html(modalHtml);
        
        // Show modal with animation
        setTimeout(() => {
            $('.sikshya-modal-overlay').addClass('show');
        }, 10);
    }

    /**
     * Show content type selection modal using existing Sikshya modal system
     */
    window.showContentTypeSelection = function() {
        // Check if sikshya_ajax is available
        if (typeof sikshya_ajax === 'undefined') {
            console.error('sikshya_ajax is not defined!');
            alert('AJAX configuration is missing. Please refresh the page.');
            return;
        }
        
        // Check if SikshyaModal is available
        if (typeof SikshyaModal === 'undefined') {
            console.error('SikshyaModal is not defined!');
            alert('Modal system is not available. Please refresh the page.');
            return;
        }
        
        // Use the existing modal template system
        $.ajax({
            url: sikshya_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sikshya_load_lesson_modal_template',
                nonce: sikshya_ajax.nonce,
                modal_type: 'content-type'
            },
            success: function(response) {
                if (response.success) {
                    // Remove existing modal
                    $('.sikshya-modal-overlay').remove();
                    
                    // Ensure modal container exists
                    if (!$('#sikshya-modal-container').length) {
                        $('body').append('<div id="sikshya-modal-container"></div>');
                    }
                    
                    // Add new modal to SikshyaModal container
                    $('#sikshya-modal-container').html(response.data.html);
                    
                    // Show modal with animation
                    setTimeout(() => {
                        $('.sikshya-modal-overlay').addClass('show');
                    }, 10);
                } else {
                    console.error('Failed to load modal template:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Response:', xhr.responseText);
            }
        });
    };



    /**
     * Show custom modal using Sikshya modal system
     */
    function showCustomModal(title, content, subtitle = '') {
        // Create modal HTML using Sikshya modal structure
        const modalHtml = `
            <div class="sikshya-modal-overlay">
                <div class="sikshya-modal sikshya-modal-full">
                    <div class="sikshya-modal-header">
                        <button class="sikshya-modal-close">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                        <div class="sikshya-modal-header-content">
                            <div class="sikshya-modal-title-wrapper">
                                <h3 class="sikshya-modal-title">${title}</h3>
                            </div>
                            ${subtitle ? `<p class="sikshya-modal-subtitle">${subtitle}</p>` : ''}
                        </div>
                    </div>
                    <div class="sikshya-modal-body" id="custom-modal-body">
                        ${content}
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
    }

    /**
     * Update modal content
     */
    function updateModalContent(content) {
        $('#custom-modal-body').html(content);
    }

    /**
     * Close custom modal
     */
    window.closeCustomModal = function() {
        $('.sikshya-modal-overlay').removeClass('show');
        setTimeout(() => {
            $('.sikshya-modal-overlay').remove();
        }, 300);
    };

    /**
     * Initialize form handlers for modal content
     */
    function initLessonFormHandlers() {
        // Form is already handled by document.on events
        // Additional initialization can be added here
    }

    /**
     * Handle lesson form submission
     */
    function handleLessonFormSubmit($form) {
        const contentType = $form.data('content-type');
        const formData = new FormData($form[0]);
        
        // Check if we're in course builder context
        const isCourseBuilder = $form.closest('.sikshya-modal-overlay').length > 0 && 
                               (window.location.href.includes('sikshya-add-course') || 
                                document.querySelector('.sikshya-course-builder'));
        
        // Add loading state
        $form.addClass('loading');
        
        // Show loading message
        showNotification('Saving lesson...', 'info');
        
        // Add content type to form data
        formData.append('content_type', contentType);
        
        $.ajax({
            url: sikshya_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $form.removeClass('loading');
                
                if (response.success) {
                    const lessonId = response.data.lesson_id;
                    const lessonTitle = response.data.lesson_title || 'Lesson';
                    
                    showNotification(response.data.message || 'Lesson saved successfully!', 'success');
                    
                    if (isCourseBuilder && lessonId) {
                        // Course builder context: Add lesson to chapter
                        handleCourseBuilderLessonSave(lessonId, lessonTitle, contentType);
                    } else {
                        // Standalone lesson creation: Redirect to lessons page
                        setTimeout(function() {
                            closeCustomModal();
                            window.location.href = sikshya_ajax.admin_url + 'admin.php?page=sikshya-lessons';
                        }, 1500);
                    }
                } else {
                    showNotification(response.data.message || 'Error saving lesson', 'error');
                }
            },
            error: function(xhr, status, error) {
                $form.removeClass('loading');
                showNotification('Error saving lesson. Please try again.', 'error');
                console.error('Lesson save error:', error);
            }
        });
    }

    /**
     * Handle lesson save in course builder context
     */
    function handleCourseBuilderLessonSave(lessonId, lessonTitle, contentType) {
        // Close the lesson form modal
        closeCustomModal();
        
        // Trigger custom event to notify course builder
        const event = new CustomEvent('sikshya:lessonSaved', {
            detail: {
                lessonId: lessonId,
                lessonTitle: lessonTitle,
                contentType: contentType
            }
        });
        
        document.dispatchEvent(event);
        
        // Show success message
        showNotification('Lesson added to chapter successfully!', 'success');
    }

    /**
     * Toggle video source sections
     */
    function toggleVideoSource() {
        const source = $('#video-lesson-source').val();
        
        // Hide all sections
        $('#video-upload-section, #video-url-section').hide();
        
        // Show relevant section
        if (source === 'upload') {
            $('#video-upload-section').show();
        } else if (['youtube', 'vimeo', 'external'].includes(source)) {
            $('#video-url-section').show();
        }
    }

    /**
     * Handle file upload
     */
    function handleFileUpload(input) {
        const file = input.files[0];
        if (!file) return;
        
        // Show upload progress
        const $progress = $(input).closest('.sikshya-form-row-small').find('.sikshya-progress-bar');
        const $progressFill = $progress.find('.sikshya-progress-fill');
        const $status = $progress.siblings('small');
        
        if ($progress.length) {
            $progress.show();
            $status.text('Uploading...');
            
            // Simulate upload progress (replace with actual upload logic)
            let progress = 0;
            const interval = setInterval(function() {
                progress += 10;
                $progressFill.css('width', progress + '%');
                
                if (progress >= 100) {
                    clearInterval(interval);
                    $status.text('Upload complete!');
                    setTimeout(function() {
                        $progress.hide();
                    }, 2000);
                }
            }, 200);
        }
    }

    /**
     * Show notification
     */
    function showNotification(message, type = 'info') {
        // Use existing toast system if available
        if (typeof sikshyaToast !== 'undefined') {
            sikshyaToast.show(message, type);
        } else {
            // Fallback to alert
            alert(message);
        }
    }

    /**
     * Validate form fields
     */
    function validateForm($form) {
        let isValid = true;
        
        // Remove previous error states
        $form.find('.error').removeClass('error');
        $form.find('.error-message').remove();
        
        // Check required fields
        $form.find('[required]').each(function() {
            const $field = $(this);
            const value = $field.val().trim();
            
            if (!value) {
                $field.closest('.sikshya-form-row-small').addClass('error');
                $field.after('<div class="error-message">This field is required</div>');
                isValid = false;
            }
        });
        
        // Validate email fields
        $form.find('input[type="email"]').each(function() {
            const $field = $(this);
            const value = $field.val().trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (value && !emailRegex.test(value)) {
                $field.closest('.sikshya-form-row-small').addClass('error');
                $field.after('<div class="error-message">Please enter a valid email address</div>');
                isValid = false;
            }
        });
        
        // Validate URL fields
        $form.find('input[type="url"]').each(function() {
            const $field = $(this);
            const value = $field.val().trim();
            const urlRegex = /^https?:\/\/.+/;
            
            if (value && !urlRegex.test(value)) {
                $field.closest('.sikshya-form-row-small').addClass('error');
                $field.after('<div class="error-message">Please enter a valid URL</div>');
                isValid = false;
            }
        });
        
        return isValid;
    }

    /**
     * Extract video info from URL
     */
    function extractVideoInfo() {
        const url = $('#video-lesson-url').val();
        if (!url) return;
        
        // Show preview section
        $('#video-preview-section').show();
        
        // Extract video ID and show preview (simplified)
        const videoId = extractVideoId(url);
        if (videoId) {
            $('#video-preview-title').text('Video preview will be loaded here');
        }
    }

    /**
     * Extract video ID from URL
     */
    function extractVideoId(url) {
        // YouTube
        const youtubeMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/);
        if (youtubeMatch) return youtubeMatch[1];
        
        // Vimeo
        const vimeoMatch = url.match(/vimeo\.com\/(\d+)/);
        if (vimeoMatch) return vimeoMatch[1];
        
        return null;
    }

    // Make functions globally available
    window.sikshyaLessons = {
        toggleVideoSource: toggleVideoSource,
        extractVideoInfo: extractVideoInfo,
        handleFileUpload: handleFileUpload
    };

})(jQuery);
