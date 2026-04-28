/**
 * Sikshya LMS Frontend JavaScript
 * Modern SaaS Functionality with Clean UX
 */

(function($) {
    'use strict';

    // Sikshya Frontend Object
    window.SikshyaFrontend = {
        
        // Initialize
        init: function() {
            this.bindEvents();
            this.initComponents();
        },

        // Bind Events
        bindEvents: function() {
            $(document).on('click', '.sikshya-btn--enroll', this.handleEnrollment);
            $(document).on('click', '.sikshya-quiz__option', this.handleQuizOption);
            $(document).on('submit', '.sikshya-quiz-form', this.handleQuizSubmit);
            $(document).on('click', '.sikshya-btn--progress', this.handleProgressUpdate);
            $(document).on('click', '.sikshya-btn--certificate', this.handleCertificateDownload);
            $(document).on('click', '.sikshya-btn--favorite', this.handleFavorite);
            $(document).on('click', '.sikshya-btn--share', this.handleShare);
            $(document).on('input', '.sikshya-search', this.handleSearch);
            $(document).on('change', '.sikshya-filter', this.handleFilter);
            
            // Video player events
            $(document).on('timeupdate', '.sikshya-video-player', this.handleVideoProgress);
            $(document).on('ended', '.sikshya-video-player', this.handleVideoComplete);
            
            // Form validation
            $(document).on('submit', '.sikshya-form', this.handleFormSubmit);
            $(document).on('blur', '.sikshya-form__input', this.validateField);
        },

        // Initialize Components
        initComponents: function() {
            this.initTooltips();
            this.initModals();
            this.initTabs();
            this.initAccordion();
            this.initProgressBars();
            this.initCounters();
            this.initLazyLoading();
            this.initCourseArchiveViewToggle();
        },

        // Course archive grid/list toggle (client-side, persisted; no URL change).
        // Use getAttribute / classList — jQuery .data() does not reliably read
        // data-sikshya-archive-view (hyphenated HTML5 attributes map to camelCase internally).
        initCourseArchiveViewToggle: function() {
            const root = document.querySelector('.sikshya-archive-courses');
            if (!root) {
                return;
            }
            const grid = root.querySelector('.sikshya-course-grid');
            const btns = root.querySelectorAll('[data-sikshya-archive-view]');
            if (!grid || !btns.length) {
                return;
            }

            const KEY = 'sikshya_course_archive_view';
            const readBtnView = function(btn) {
                return String(btn.getAttribute('data-sikshya-archive-view') || '').trim();
            };

            const apply = function(view) {
                const v = view === 'list' ? 'list' : 'grid';
                grid.classList.toggle('sikshya-course-grid--list', v === 'list');
                btns.forEach(function(b) {
                    const isActive = readBtnView(b) === v;
                    b.classList.toggle('is-active', isActive);
                    b.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });
            };

            // Honor admin "Course catalog layout" when localStorage is unset; otherwise
            // apply() defaults to grid and strips server-rendered list mode.
            const serverLayout = String(root.getAttribute('data-sikshya-archive-layout') || 'grid')
                .trim()
                .toLowerCase();
            const serverToggle = serverLayout === 'list' ? 'list' : 'grid';

            let boot = serverToggle;
            try {
                const stored = window.localStorage.getItem(KEY);
                if (stored === 'list' || stored === 'grid') {
                    boot = stored;
                }
            } catch (e) {
                boot = serverToggle;
            }
            apply(boot);

            root.addEventListener('click', function(ev) {
                const btn = ev.target.closest('[data-sikshya-archive-view]');
                if (!btn || !root.contains(btn)) {
                    return;
                }
                ev.preventDefault();
                const view = readBtnView(btn) || 'grid';
                apply(view);
                try {
                    window.localStorage.setItem(KEY, view === 'list' ? 'list' : 'grid');
                } catch (e) {
                    // ignore
                }
            });
        },

        // Handle Course Enrollment
        handleEnrollment: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const courseId = $button.data('course-id');
            const originalText = $button.text();
            
            if (!sikshya_frontend.is_user_logged_in) {
                window.location.href = sikshya_frontend.login_url || '/wp-login.php';
                return;
            }
            
            if (!confirm(sikshya_frontend.strings.confirm_enroll)) {
                return;
            }
            
            $button.prop('disabled', true).text(sikshya_frontend.strings.loading);
            
            $.ajax({
                url: sikshya_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'sikshya_enroll_course',
                    course_id: courseId,
                    nonce: sikshya_frontend.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SikshyaFrontend.showAlert('success', sikshya_frontend.strings.enroll_success);
                        $button.removeClass('sikshya-btn--primary').addClass('sikshya-btn--success')
                              .text('Enrolled').prop('disabled', true);
                        
                        // Update enrollment count
                        const $count = $('.sikshya-enrollment-count');
                        if ($count.length) {
                            const currentCount = parseInt($count.text()) || 0;
                            $count.text(currentCount + 1);
                        }
                    } else {
                        SikshyaFrontend.showAlert('error', response.data || sikshya_frontend.strings.enroll_error);
                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    SikshyaFrontend.showAlert('error', sikshya_frontend.strings.error);
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        // Certificate download (expects href or data-url on the control).
        handleCertificateDownload: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const url = $btn.attr('href') || $btn.data('url');
            if (url) {
                window.open(url, '_blank', 'noopener,noreferrer');
            }
        },

        // Favorites UI hook (toggle state; persistence can be wired to REST later).
        handleFavorite: function(e) {
            e.preventDefault();
            $(e.currentTarget).toggleClass('is-favorited');
        },

        // Share course link (Web Share API or clipboard / prompt fallback).
        handleShare: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const $card = $btn.closest('.sikshya-course-card');
            let url = '';
            if ($card.length) {
                const $link = $card.find('.sikshya-course-title a, .sikshya-course-card-image-link').first();
                url = ($link.attr('href') || '').toString();
            }
            if (!url) {
                url = window.location.href;
            }
            if (navigator.share) {
                navigator.share({ url: url }).catch(function() {});
            } else if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function() {
                    SikshyaFrontend.showAlert('success', 'Link copied to clipboard');
                }).catch(function() {
                    window.prompt('Copy this course link:', url);
                });
            } else {
                window.prompt('Copy this course link:', url);
            }
        },

        // Handle Quiz Option Selection
        handleQuizOption: function(e) {
            const $option = $(this);
            const $question = $option.closest('.sikshya-quiz__question');
            
            // Remove selection from other options in same question
            $question.find('.sikshya-quiz__option').removeClass('sikshya-quiz__option--selected');
            
            // Add selection to clicked option
            $option.addClass('sikshya-quiz__option--selected');
            
            // Update radio button
            $option.find('input[type="radio"]').prop('checked', true);
        },

        // Handle Quiz Submission
        handleQuizSubmit: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitBtn = $form.find('.sikshya-btn--submit');
            const originalText = $submitBtn.text();
            
            if (!confirm(sikshya_frontend.strings.confirm_quiz_submit)) {
                return;
            }
            
            // Collect answers
            const answers = {};
            $form.find('input[name^="question_"]').each(function() {
                const questionId = $(this).attr('name').replace('question_', '');
                answers[questionId] = $(this).val();
            });
            
            // Get time taken
            const timeTaken = $form.data('time-taken') || 0;
            
            $submitBtn.prop('disabled', true).text(sikshya_frontend.strings.loading);
            
            $.ajax({
                url: sikshya_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'sikshya_submit_quiz',
                    quiz_id: $form.data('quiz-id'),
                    answers: answers,
                    time_taken: timeTaken,
                    nonce: sikshya_frontend.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SikshyaFrontend.showQuizResults(response.data);
                    } else {
                        SikshyaFrontend.showAlert('error', response.data || sikshya_frontend.strings.quiz_submit_error);
                        $submitBtn.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    SikshyaFrontend.showAlert('error', sikshya_frontend.strings.error);
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        },

        // Show Quiz Results
        showQuizResults: function(data) {
            const resultsHtml = `
                <div class="sikshya-quiz-results">
                    <h3>Quiz Results</h3>
                    <div class="sikshya-quiz-results__score">
                        <div class="sikshya-quiz-results__percentage">${data.score}%</div>
                        <div class="sikshya-quiz-results__details">
                            <p>Correct Answers: ${data.correct_answers} / ${data.total_questions}</p>
                            <p>Time Taken: ${Math.floor(data.time_taken / 60)}:${(data.time_taken % 60).toString().padStart(2, '0')}</p>
                        </div>
                    </div>
                    <div class="sikshya-quiz-results__actions">
                        <button class="sikshya-btn sikshya-btn--primary" onclick="location.reload()">Retake Quiz</button>
                        <button class="sikshya-btn sikshya-btn--secondary" onclick="history.back()">Back to Lesson</button>
                    </div>
                </div>
            `;
            
            $('.sikshya-quiz').html(resultsHtml);
        },

        // Handle Progress Update
        handleProgressUpdate: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const lessonId = $button.data('lesson-id');
            const courseId = $button.data('course-id');
            const status = $button.data('status');
            const timeSpent = $button.data('time-spent') || 0;
            
            $.ajax({
                url: sikshya_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'sikshya_update_progress',
                    lesson_id: lessonId,
                    course_id: courseId,
                    status: status,
                    time_spent: timeSpent,
                    nonce: sikshya_frontend.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SikshyaFrontend.updateProgressBar(courseId);
                        if (status === 'completed') {
                            $button.removeClass('sikshya-btn--primary').addClass('sikshya-btn--success')
                                  .text('Completed').prop('disabled', true);
                        }
                    } else {
                        SikshyaFrontend.showAlert('error', response.data || sikshya_frontend.strings.progress_update_error);
                    }
                },
                error: function() {
                    SikshyaFrontend.showAlert('error', sikshya_frontend.strings.error);
                }
            });
        },

        // Update Progress Bar
        updateProgressBar: function(courseId) {
            const $progressBar = $(`.sikshya-progress[data-course-id="${courseId}"] .sikshya-progress__bar`);
            const $progressText = $(`.sikshya-progress[data-course-id="${courseId}"] .sikshya-progress__text`);
            
            // Calculate new progress (this would normally come from server)
            const currentProgress = parseInt($progressBar.css('width')) || 0;
            const newProgress = Math.min(currentProgress + 10, 100);
            
            $progressBar.css('width', newProgress + '%');
            $progressText.text(`${newProgress}% Complete`);
        },

        // Handle Video Progress
        handleVideoProgress: function() {
            const video = this;
            const currentTime = video.currentTime;
            const duration = video.duration;
            const progress = (currentTime / duration) * 100;
            
            // Update progress bar
            const $progressBar = $(video).closest('.sikshya-lesson-player').find('.sikshya-progress__bar');
            $progressBar.css('width', progress + '%');
            
            // Auto-save progress every 30 seconds
            if (Math.floor(currentTime) % 30 === 0) {
                const lessonId = $(video).data('lesson-id');
                const courseId = $(video).data('course-id');
                
                $.ajax({
                    url: sikshya_frontend.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sikshya_update_progress',
                        lesson_id: lessonId,
                        course_id: courseId,
                        status: 'in_progress',
                        time_spent: Math.floor(currentTime),
                        nonce: sikshya_frontend.nonce
                    }
                });
            }
        },

        // Handle Video Complete
        handleVideoComplete: function() {
            const video = this;
            const lessonId = $(video).data('lesson-id');
            const courseId = $(video).data('course-id');
            const duration = video.duration;
            
            // Mark lesson as completed
            $.ajax({
                url: sikshya_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'sikshya_update_progress',
                    lesson_id: lessonId,
                    course_id: courseId,
                    status: 'completed',
                    time_spent: Math.floor(duration),
                    nonce: sikshya_frontend.nonce
                },
                success: function(response) {
                    if (response.success) {
                        SikshyaFrontend.showAlert('success', 'Lesson completed!');
                        SikshyaFrontend.updateProgressBar(courseId);
                    }
                }
            });
        },

        // Handle Form Submit
        handleFormSubmit: function(e) {
            const $form = $(this);
            const $submitBtn = $form.find('.sikshya-btn--submit');
            
            // Validate form
            if (!SikshyaFrontend.validateForm($form)) {
                e.preventDefault();
                return false;
            }
            
            $submitBtn.prop('disabled', true).text(sikshya_frontend.strings.loading);
        },

        // Validate Form
        validateForm: function($form) {
            let isValid = true;
            
            $form.find('.sikshya-form__input[required], .sikshya-form__textarea[required]').each(function() {
                if (!SikshyaFrontend.validateField.call(this)) {
                    isValid = false;
                }
            });
            
            return isValid;
        },

        // Validate Field
        validateField: function() {
            const $field = $(this);
            const value = $field.val().trim();
            const type = $field.attr('type');
            const required = $field.prop('required');
            
            $field.removeClass('sikshya-form__input--error');
            
            // Required validation
            if (required && !value) {
                $field.addClass('sikshya-form__input--error');
                return false;
            }
            
            // Email validation
            if (type === 'email' && value) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    $field.addClass('sikshya-form__input--error');
                    return false;
                }
            }
            
            return true;
        },

        // Handle Search
        handleSearch: function() {
            const query = $(this).val().toLowerCase();
            const $courses = $('.sikshya-course-card');
            
            $courses.each(function() {
                const title = $(this).find('.sikshya-course-card__title').text().toLowerCase();
                const excerpt = $(this).find('.sikshya-course-card__excerpt').text().toLowerCase();
                
                if (title.includes(query) || excerpt.includes(query)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        },

        // Handle Filter
        handleFilter: function() {
            const filter = $(this).val();
            const $courses = $('.sikshya-course-card');
            
            if (filter === 'all') {
                $courses.show();
            } else {
                $courses.each(function() {
                    const category = $(this).data('category');
                    if (category === filter) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            }
        },

        // Show Alert
        showAlert: function(type, message) {
            const alertHtml = `
                <div class="sikshya-alert sikshya-alert--${type}">
                    <span>${message}</span>
                    <button class="sikshya-alert__close">&times;</button>
                </div>
            `;
            
            const $alert = $(alertHtml);
            $('body').append($alert);
            
            // Auto remove after 5 seconds
            setTimeout(function() {
                $alert.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Manual close
            $alert.find('.sikshya-alert__close').on('click', function() {
                $alert.fadeOut(function() {
                    $(this).remove();
                });
            });
        },

        // Initialize Tooltips
        initTooltips: function() {
            $('[data-tooltip]').each(function() {
                const $element = $(this);
                const tooltipText = $element.data('tooltip');
                
                $element.on('mouseenter', function() {
                    const $tooltip = $(`<div class="sikshya-tooltip">${tooltipText}</div>`);
                    $('body').append($tooltip);
                    
                    const elementRect = this.getBoundingClientRect();
                    $tooltip.css({
                        position: 'absolute',
                        top: elementRect.top - $tooltip.outerHeight() - 10,
                        left: elementRect.left + (elementRect.width / 2) - ($tooltip.outerWidth() / 2),
                        zIndex: 1000
                    });
                }).on('mouseleave', function() {
                    $('.sikshya-tooltip').remove();
                });
            });
        },

        // Initialize Modals
        initModals: function() {
            $('[data-modal]').on('click', function() {
                const modalId = $(this).data('modal');
                $(`#${modalId}`).addClass('sikshya-modal--active');
            });
            
            $('.sikshya-modal__close, .sikshya-modal__overlay').on('click', function() {
                $(this).closest('.sikshya-modal').removeClass('sikshya-modal--active');
            });
        },

        // Initialize Tabs
        initTabs: function() {
            $('.sikshya-tabs__nav-item').on('click', function() {
                const tabId = $(this).data('tab');
                const $tabContent = $(this).closest('.sikshya-tabs').find('.sikshya-tabs__content');
                
                $(this).siblings().removeClass('sikshya-tabs__nav-item--active');
                $(this).addClass('sikshya-tabs__nav-item--active');
                
                $tabContent.find('.sikshya-tabs__panel').removeClass('sikshya-tabs__panel--active');
                $tabContent.find(`[data-tab="${tabId}"]`).addClass('sikshya-tabs__panel--active');
            });
        },

        // Initialize Accordion
        initAccordion: function() {
            $('.sikshya-accordion__header').on('click', function() {
                const $accordion = $(this).closest('.sikshya-accordion');
                const $content = $accordion.find('.sikshya-accordion__content');
                
                if ($accordion.hasClass('sikshya-accordion--active')) {
                    $accordion.removeClass('sikshya-accordion--active');
                    $content.slideUp();
                } else {
                    $accordion.addClass('sikshya-accordion--active');
                    $content.slideDown();
                }
            });
        },

        // Initialize Progress Bars
        initProgressBars: function() {
            $('.sikshya-progress__bar').each(function() {
                const $bar = $(this);
                const progress = $bar.data('progress') || 0;
                
                setTimeout(function() {
                    $bar.css('width', progress + '%');
                }, 100);
            });
        },

        // Initialize Counters
        initCounters: function() {
            $('.sikshya-counter').each(function() {
                const $counter = $(this);
                const target = parseInt($counter.data('target')) || 0;
                const duration = parseInt($counter.data('duration')) || 2000;
                const step = target / (duration / 16);
                let current = 0;
                
                const timer = setInterval(function() {
                    current += step;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    $counter.text(Math.floor(current));
                }, 16);
            });
        },

        // Initialize Lazy Loading
        initLazyLoading: function() {
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver(function(entries, observer) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src;
                            img.classList.remove('sikshya-lazy');
                            imageObserver.unobserve(img);
                        }
                    });
                });
                
                document.querySelectorAll('.sikshya-lazy').forEach(function(img) {
                    imageObserver.observe(img);
                });
            }
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        SikshyaFrontend.init();
    });

})(jQuery); 