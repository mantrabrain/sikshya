<?php
/**
 * Dynamic Course Builder Template
 * 
 * @package Sikshya
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Initialize the course builder manager
try {
    $course_builder_manager = new \Sikshya\Admin\CourseBuilder\CourseBuilderManager($this->plugin);
} catch (Exception $e) {
    error_log('Sikshya Course Builder Error: ' . $e->getMessage());
    // Fallback to static content if dynamic system fails
    $course_builder_manager = null;
}

$active_tab = $data['active_tab'] ?? '';
$course_id = $data['course_id'] ?? '';

// Get default active tab if not set
if (empty($active_tab) && $course_builder_manager) {
    $active_tab = $course_builder_manager->getFirstTabId();
}
?>

<div class="sikshya-course-builder">
    <!-- Course Builder Form -->
    <form id="sikshya-course-builder-form" method="post" action="" enctype="multipart/form-data">
        <?php wp_nonce_field('sikshya_course_builder_nonce', 'sikshya_course_builder_nonce'); ?>
        <input type="hidden" name="action" value="sikshya_save_course" />
        <input type="hidden" name="course_id" value="<?php echo esc_attr($course_id); ?>" />
        <input type="hidden" name="course_status" value="draft" id="course-status-field" />
        
        <div class="sikshya-header">
            <div class="sikshya-header-title">
                <h1>
                    <i class="fas fa-graduation-cap"></i>
                    <?php _e('Course Builder', 'sikshya'); ?>
                </h1>
                <span class="sikshya-version">v<?php echo esc_html(SIKSHYA_VERSION); ?></span>
            </div>
            <div class="sikshya-header-actions">
                <button type="button" class="sikshya-btn sikshya-btn-secondary" onclick="previewCourse()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <?php _e('Preview', 'sikshya'); ?>
                </button>
                <button type="submit" class="sikshya-btn sikshya-btn-secondary" onclick="saveDraft()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                    </svg>
                    <?php _e('Save Draft', 'sikshya'); ?>
                </button>
                <button type="submit" class="sikshya-btn sikshya-btn-primary" onclick="publishCourse()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18"/>
                    </svg>
                    <?php _e('Publish Course', 'sikshya'); ?>
                </button>
            </div>
        </div>

        <div class="sikshya-main-content">
            <div class="sikshya-sidebar">
                <!-- Modern Clean Header -->
                <div class="sikshya-sidebar-header">
                    <div class="sikshya-header-icon"></div>
                    <div class="sikshya-course-title">
                        <h3><?php _e('Course Builder', 'sikshya'); ?></h3>
                        <p><?php _e('Create amazing learning experiences', 'sikshya'); ?></p>
                    </div>
                </div>
                
                <!-- Compact Progress Overview -->
                <div class="sikshya-progress-section">
                    <div class="sikshya-progress-header">
                        <h4><?php _e('Course Progress', 'sikshya'); ?></h4>
                        <span class="sikshya-progress-percentage">75%</span>
                    </div>
                    <div class="sikshya-progress-bar">
                        <div class="sikshya-progress-fill"></div>
                    </div>
                    <div class="sikshya-progress-stats">
                        <?php _e('3 of 4 steps completed', 'sikshya'); ?>
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <?php if ($course_builder_manager): ?>
                    <?php echo $course_builder_manager->renderNavigation($active_tab); ?>
                <?php else: ?>
                    <!-- Fallback static navigation -->
                    <nav class="sikshya-sidebar-nav">
                        <div class="sikshya-nav-section">
                            <h4 class="sikshya-nav-section-title"><?php _e('Course Setup', 'sikshya'); ?></h4>
                            <ul class="sikshya-nav-list">
                                <li class="sikshya-nav-item">
                                    <a href="#" class="sikshya-nav-link active" onclick="switchTab('course'); return false;" data-tab="course">
                                        <svg class="sikshya-nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <div class="sikshya-nav-content">
                                            <span class="sikshya-nav-title"><?php _e('Course Information', 'sikshya'); ?></span>
                                            <span class="sikshya-nav-desc"><?php _e('Title, description, and basic details', 'sikshya'); ?></span>
                                        </div>
                                    </a>
                                </li>
                                <li class="sikshya-nav-item">
                                    <a href="#" class="sikshya-nav-link" onclick="switchTab('pricing'); return false;" data-tab="pricing">
                                        <svg class="sikshya-nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                                        </svg>
                                        <div class="sikshya-nav-content">
                                            <span class="sikshya-nav-title"><?php _e('Pricing & Access', 'sikshya'); ?></span>
                                            <span class="sikshya-nav-desc"><?php _e('Set price and enrollment options', 'sikshya'); ?></span>
                                        </div>
                                    </a>
                                </li>
                                <li class="sikshya-nav-item">
                                    <a href="#" class="sikshya-nav-link" onclick="switchTab('curriculum'); return false;" data-tab="curriculum">
                                        <svg class="sikshya-nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                        </svg>
                                        <div class="sikshya-nav-content">
                                            <span class="sikshya-nav-title"><?php _e('Curriculum', 'sikshya'); ?></span>
                                            <span class="sikshya-nav-desc"><?php _e('Add lessons, sections, and content', 'sikshya'); ?></span>
                                        </div>
                                    </a>
                                </li>
                                <li class="sikshya-nav-item">
                                    <a href="#" class="sikshya-nav-link" onclick="switchTab('settings'); return false;" data-tab="settings">
                                        <svg class="sikshya-nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <div class="sikshya-nav-content">
                                            <span class="sikshya-nav-title"><?php _e('Settings', 'sikshya'); ?></span>
                                            <span class="sikshya-nav-desc"><?php _e('Advanced options and SEO', 'sikshya'); ?></span>
                                        </div>
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <!-- Quick Actions -->
                        <div class="sikshya-nav-section">
                            <h4 class="sikshya-nav-section-title"><?php _e('Quick Actions', 'sikshya'); ?></h4>
                            <div class="sikshya-quick-actions">
                                <button type="button" class="sikshya-btn sikshya-btn-secondary" onclick="previewCourse()">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <?php _e('Preview', 'sikshya'); ?>
                                </button>
                                <button type="submit" class="sikshya-btn sikshya-btn-secondary" onclick="saveDraft()">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                                    </svg>
                                    <?php _e('Save Draft', 'sikshya'); ?>
                                </button>
                                <button type="submit" class="sikshya-btn sikshya-btn-primary" onclick="publishCourse()">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18"/>
                                    </svg>
                                    <?php _e('Publish Course', 'sikshya'); ?>
                                </button>
                            </div>
                        </div>
                    </nav>
                <?php endif; ?>
            </div>

            <!-- Tab Content -->
            <?php if ($course_builder_manager): ?>
                <?php echo $course_builder_manager->renderTabContent($active_tab, intval($course_id)); ?>
            <?php else: ?>
                <!-- Fallback static content -->
                <div class="sikshya-content">
                    <div class="sikshya-tab-content active" id="course">
                        <div class="sikshya-section sikshya-section-modern">
                            <div class="sikshya-section-header">
                                <div class="sikshya-section-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <div class="sikshya-section-content">
                                    <h3 class="sikshya-section-title"><?php _e('Basic Information', 'sikshya'); ?></h3>
                                    <p class="sikshya-section-desc"><?php _e('Set up the fundamental details of your course', 'sikshya'); ?></p>
                                </div>
                            </div>
                            
                            <div class="sikshya-form-row">
                                <label><?php _e('Course Title', 'sikshya'); ?> *</label>
                                <input type="text" name="title" placeholder="<?php _e('Enter an engaging course title', 'sikshya'); ?>" required>
                            </div>
                            
                            <div class="sikshya-form-row">
                                <label><?php _e('Short Description', 'sikshya'); ?></label>
                                <input type="text" name="short_description" placeholder="<?php _e('Brief one-line description for course cards', 'sikshya'); ?>">
                            </div>
                            
                            <div class="sikshya-form-row">
                                <label><?php _e('Detailed Description', 'sikshya'); ?> *</label>
                                <textarea name="description" placeholder="<?php _e('Detailed description of what students will learn, course benefits, and outcomes', 'sikshya'); ?>" required></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
// Initialize course builder data for JavaScript
window.sikshyaCourseBuilder = {
    activeTab: '<?php echo esc_js($active_tab); ?>',
    courseId: '<?php echo esc_js($course_id); ?>',
    <?php if ($course_builder_manager): ?>
    tabFields: <?php echo json_encode($course_builder_manager->getTabFieldsForJs()); ?>,
    <?php endif; ?>
    ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('sikshya_course_builder_nonce'); ?>'
};

// Tab switching function
function switchTab(tabId) {
    // Remove active class from all tabs
    document.querySelectorAll('.sikshya-nav-link').forEach(link => {
        link.classList.remove('active');
    });
    
    // Add active class to clicked tab
    document.querySelector(`[data-tab="${tabId}"]`).classList.add('active');
    
    // Hide all tab content
    document.querySelectorAll('.sikshya-tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Show selected tab content
    const selectedContent = document.getElementById(tabId);
    if (selectedContent) {
        selectedContent.classList.add('active');
    }
    
    // Update active tab
    window.sikshyaCourseBuilder.activeTab = tabId;
}

// Course action functions
function previewCourse() {
    // Implementation for preview functionality
    console.log('Preview course functionality');
}

function saveDraft() {
    const form = document.getElementById('sikshya-course-builder-form');
    const statusField = document.getElementById('course-status-field');
    const submitButton = document.querySelector('button[onclick="saveDraft()"]');
    
    if (!form || !statusField) {
        console.error('Form elements not found');
        return;
    }
    
    // Set status to draft
    statusField.value = 'draft';
    
    // Show loading state
    if (submitButton) {
        const originalText = submitButton.textContent;
        submitButton.disabled = true;
        submitButton.textContent = 'Saving Draft...';
        
        // Submit form via AJAX
        submitFormAjax(form, 'draft', function(success, response) {
            submitButton.disabled = false;
            submitButton.textContent = originalText;
            
            if (success) {
                if (window.SikshyaToast) {
                    window.SikshyaToast.successMessage('Course draft saved successfully!');
                }
                
                // Update course ID if returned
                if (response.course_id) {
                    updateCourseIdInForm(response.course_id);
                }
            } else {
                if (window.SikshyaToast) {
                    window.SikshyaToast.errorMessage(response.message || 'Failed to save draft');
                }
            }
        });
    }
}

function publishCourse() {
    const form = document.getElementById('sikshya-course-builder-form');
    const statusField = document.getElementById('course-status-field');
    const submitButton = document.querySelector('button[onclick="publishCourse()"]');
    
    if (!form || !statusField) {
        console.error('Form elements not found');
        return;
    }
    
    // Validate required fields before publishing
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    const errors = [];
    
    requiredFields.forEach(function(field) {
        if (!field.value.trim()) {
            isValid = false;
            errors.push(field.name + ' is required');
            field.classList.add('error');
        } else {
            field.classList.remove('error');
        }
    });
    
    if (!isValid) {
        if (window.SikshyaToast) {
            window.SikshyaToast.errorMessage('Please fill in all required fields before publishing');
        }
        return;
    }
    
    // Set status to published
    statusField.value = 'published';
    
    // Show loading state
    if (submitButton) {
        const originalText = submitButton.textContent;
        submitButton.disabled = true;
        submitButton.textContent = 'Publishing...';
        
        // Submit form via AJAX
        submitFormAjax(form, 'published', function(success, response) {
            submitButton.disabled = false;
            submitButton.textContent = originalText;
            
            if (success) {
                if (window.SikshyaToast) {
                    window.SikshyaToast.successMessage('Course published successfully!');
                }
                
                // Update course ID if returned
                if (response.course_id) {
                    updateCourseIdInForm(response.course_id);
                }
                
                // Redirect to course list or show success page
                setTimeout(function() {
                    window.location.href = '<?php echo admin_url('admin.php?page=sikshya-courses'); ?>';
                }, 1500);
            } else {
                if (window.SikshyaToast) {
                    window.SikshyaToast.errorMessage(response.message || 'Failed to publish course');
                }
            }
        });
    }
}

// Helper function to submit form via AJAX
function submitFormAjax(form, status, callback) {
    const formData = new FormData(form);
    formData.append('action', 'sikshya_save_course_builder');
    formData.append('nonce', '<?php echo wp_create_nonce('sikshya_course_builder_nonce'); ?>');
    formData.append('course_status', status);
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            callback(true, data.data);
        } else {
            callback(false, data.data);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        callback(false, { message: 'Network error occurred' });
    });
}

// Helper function to update course ID in form
function updateCourseIdInForm(courseId) {
    const courseIdField = document.querySelector('input[name="course_id"]');
    if (courseIdField) {
        courseIdField.value = courseId;
    }
    
    // Update URL if needed
    const url = new URL(window.location);
    url.searchParams.set('course_id', courseId);
    window.history.pushState({}, '', url);
}

// Conditional field functions
function togglePricing(select) {
    const pricingFields = document.getElementById('pricing-fields');
    if (select.value === 'free') {
        pricingFields.style.display = 'none';
    } else {
        pricingFields.style.display = 'block';
    }
}

function togglePasswordField(select) {
    const passwordField = document.getElementById('password-field');
    if (select.value === 'password_protected') {
        passwordField.style.display = 'block';
    } else {
        passwordField.style.display = 'none';
    }
}

function toggleCertificateSettings(checkbox) {
    const certificateSettings = document.getElementById('certificate-settings');
    if (checkbox.checked) {
        certificateSettings.style.display = 'block';
    } else {
        certificateSettings.style.display = 'none';
    }
}

// Permalink functions
function togglePermalinkEdit() {
    const display = document.getElementById('permalink-display');
    const edit = document.getElementById('permalink-edit');
    const editBtn = document.getElementById('edit-permalink-btn');
    
    if (display.style.display !== 'none') {
        display.style.display = 'none';
        edit.style.display = 'flex';
        editBtn.style.display = 'none';
        document.getElementById('permalink-input').focus();
    } else {
        display.style.display = 'flex';
        edit.style.display = 'none';
        editBtn.style.display = 'inline-block';
    }
}

function savePermalink() {
    const input = document.getElementById('permalink-input');
    const slug = input.value.trim();
    const slugDisplay = document.getElementById('permalink-slug');
    
    if (slug) {
        slugDisplay.textContent = slug;
        togglePermalinkEdit();
    } else {
        // If slug is empty, revert to original
        cancelPermalinkEdit();
    }
}

function cancelPermalinkEdit() {
    const input = document.getElementById('permalink-input');
    const slugDisplay = document.getElementById('permalink-slug');
    const originalSlug = slugDisplay.textContent || '<?php echo esc_js($data['slug'] ?? ''); ?>';
    
    input.value = originalSlug;
    togglePermalinkEdit();
}

// Auto-generate slug from title
function generateSlugFromTitle() {
    const titleInput = document.querySelector('input[name="title"]');
    const slugInput = document.getElementById('permalink-input');
    const slugDisplay = document.getElementById('permalink-slug');
    
    if (titleInput && slugInput) {
        titleInput.addEventListener('input', function() {
            const title = this.value;
            const slug = title.toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '') // Remove special characters
                .replace(/\s+/g, '-') // Replace spaces with hyphens
                .replace(/-+/g, '-') // Replace multiple hyphens with single
                .trim('-'); // Remove leading/trailing hyphens
            
            slugInput.value = slug;
            slugDisplay.textContent = slug;
        });
    }
}

// Initialize permalink functionality
document.addEventListener('DOMContentLoaded', function() {
    generateSlugFromTitle();
    
    // Initialize permalink display
    const slugDisplay = document.getElementById('permalink-slug');
    const slugInput = document.getElementById('permalink-input');
    
    if (slugDisplay && slugInput) {
        // Set initial display
        if (!slugDisplay.textContent.trim()) {
            slugDisplay.textContent = 'course-slug';
        }
        
        // Handle Enter key in permalink input
        slugInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                savePermalink();
            }
        });
    }
});
    } else {
        certificateSettings.style.display = 'none';
    }
}

// Curriculum functions
function showChapterModal() {
    // Implementation for chapter modal
    console.log('Show chapter modal');
}

function toggleDemoContent() {
    // Implementation for demo content
    console.log('Toggle demo content');
}

function importFromTemplate() {
    // Implementation for template import
    console.log('Import from template');
}

function bulkImport() {
    // Implementation for bulk import
    console.log('Bulk import');
}

// Test toast system on page load
document.addEventListener('DOMContentLoaded', function() {
    // Test toast system
    if (window.SikshyaToast) {
        console.log('SikshyaToast is loaded and available');
        // Test toast after 2 seconds
        setTimeout(function() {
            window.SikshyaToast.infoMessage('Course builder loaded successfully!');
        }, 2000);
    } else {
        console.error('SikshyaToast is not loaded');
    }
});
</script>
