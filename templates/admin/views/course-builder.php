<?php
/**
 * Course Builder Template
 *
 * @package Sikshya\Admin\Views
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue Font Awesome
wp_enqueue_script('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js', [], '6.4.0', true);
?>

<div class="sikshya-course-builder">
    <div class="sikshya-header">
        <h1>
            <i class="fas fa-graduation-cap"></i>
            Course Builder
        </h1>
        <div class="sikshya-header-actions">
            <button class="sikshya-btn" onclick="previewCourse()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                Preview
            </button>
            <button class="sikshya-btn" onclick="saveDraft()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                </svg>
                Save Draft
            </button>
            <button class="sikshya-btn sikshya-btn-primary" onclick="publishCourse()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18"/>
                </svg>
                Publish Course
            </button>
        </div>
    </div>

    <div class="sikshya-main-content">
        <div class="sikshya-sidebar">
            <!-- Modern Clean Header -->
            <div class="sikshya-sidebar-header">
                <div class="sikshya-header-icon"></div>
                <div class="sikshya-course-title">
                    <h3>Course Builder</h3>
                    <p>Create amazing learning experiences</p>
                </div>
            </div>
            
            <!-- Compact Progress Overview -->
            <div class="sikshya-progress-section">
                <div class="sikshya-progress-header">
                    <h4>Course Progress</h4>
                    <span class="sikshya-progress-percentage">75%</span>
                </div>
                <div class="sikshya-progress-bar">
                    <div class="sikshya-progress-fill"></div>
                </div>
                <div class="sikshya-progress-stats">
                    3 of 4 steps completed
                </div>
            </div>

            <!-- Navigation Tabs -->
            <nav class="sikshya-sidebar-nav">
                <div class="sikshya-nav-section">
                    <h4 class="sikshya-nav-section-title">Course Setup</h4>
                    <ul class="sikshya-nav-list">
                        <li class="sikshya-nav-item">
                            <a href="#" class="sikshya-nav-link <?php echo ($active_tab === 'course') ? 'active' : ''; ?>" onclick="switchTab('course'); return false;" data-tab="course">
                                <svg class="sikshya-nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <div class="sikshya-nav-content">
                                    <span class="sikshya-nav-title">Course Information</span>
                                    <span class="sikshya-nav-desc">Title, description, and basic details</span>
                                    <div class="sikshya-nav-status completed">Completed</div>
                                    <div class="sikshya-nav-time">2 min ago</div>
                                </div>
                            </a>
                        </li>
                        <li class="sikshya-nav-item">
                            <a href="#" class="sikshya-nav-link <?php echo ($active_tab === 'pricing') ? 'active' : 'completed'; ?>" onclick="switchTab('pricing'); return false;" data-tab="pricing">
                                <svg class="sikshya-nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                                </svg>
                                <div class="sikshya-nav-content">
                                    <span class="sikshya-nav-title">Pricing & Access</span>
                                    <span class="sikshya-nav-desc">Set price and enrollment options</span>
                                    <div class="sikshya-nav-status completed">Completed</div>
                                    <div class="sikshya-nav-time">5 min ago</div>
                                </div>
                            </a>
                        </li>
                        <li class="sikshya-nav-item">
                            <a href="#" class="sikshya-nav-link <?php echo ($active_tab === 'curriculum') ? 'active' : ''; ?>" onclick="switchTab('curriculum'); return false;" data-tab="curriculum">
                                <svg class="sikshya-nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                </svg>
                                <div class="sikshya-nav-content">
                                    <span class="sikshya-nav-title">Curriculum</span>
                                    <span class="sikshya-nav-desc">Add lessons, sections, and content</span>
                                    <div class="sikshya-nav-status in-progress">In Progress</div>
                                    <div class="sikshya-nav-time">~10 min left</div>
                                </div>
                            </a>
                        </li>
                        <li class="sikshya-nav-item">
                            <a href="#" class="sikshya-nav-link <?php echo ($active_tab === 'settings') ? 'active' : ''; ?>" onclick="switchTab('settings'); return false;" data-tab="settings">
                                <svg class="sikshya-nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <div class="sikshya-nav-content">
                                    <span class="sikshya-nav-title">Settings</span>
                                    <span class="sikshya-nav-desc">Configure course features and preferences</span>
                                    <div class="sikshya-nav-status pending">Pending</div>
                                    <div class="sikshya-nav-time">~5 min</div>
                                </div>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Modern Quick Actions -->
                <div class="sikshya-quick-actions-section">
                    <h4 class="sikshya-quick-actions-title">Quick Actions</h4>
                    
                    <a href="#" class="sikshya-quick-action" onclick="previewCourse()">
                        <svg class="sikshya-action-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <span>Preview</span>
                    </a>
                    
                    <a href="#" class="sikshya-quick-action" onclick="saveDraft()">
                        <svg class="sikshya-action-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                        </svg>
                        <span>Save Draft</span>
                    </a>
                    
                    <a href="#" class="sikshya-quick-action primary" onclick="publishCourse()">
                        <svg class="sikshya-action-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18"/>
                        </svg>
                        <span>Publish Course</span>
                    </a>
                </div>
            </nav>

            <!-- Publish Section -->
            <div class="sikshya-sidebar-footer">
                <div class="sikshya-publish-checklist">
                    <h4>Ready to Publish?</h4>
                    <ul class="sikshya-checklist">
                        <li class="completed">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20,6 9,17 4,12"/>
                            </svg>
                            Course information
                        </li>
                        <li class="pending">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M12 6v6l4 2"/>
                            </svg>
                            Add curriculum content
                        </li>
                        <li class="pending">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M12 6v6l4 2"/>
                            </svg>
                            Configure settings
                        </li>
                    </ul>
                </div>
                <button class="sikshya-publish-btn" onclick="publishCourse()" disabled>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18"/>
                    </svg>
                    Publish Course
                </button>
            </div>
        </div>

        <div class="sikshya-content">
            <!-- Course Tab -->
            <div class="sikshya-tab-content <?php echo ($active_tab === 'course') ? 'active' : ''; ?>" id="course">
                <form id="course-form">
                    <div class="sikshya-section">
                        <h3 class="sikshya-section-title">Basic Information</h3>
                        
                        <div class="sikshya-form-row">
                            <label>Course Title *</label>
                            <input type="text" name="title" placeholder="Enter an engaging course title" required>
                        </div>
                        
                        <div class="sikshya-form-row">
                            <label>Short Description</label>
                            <input type="text" name="short_description" placeholder="Brief one-line description for course cards">
                        </div>
                        
                        <div class="sikshya-form-row">
                            <label>Detailed Description *</label>
                            <textarea name="description" placeholder="Detailed description of what students will learn, course benefits, and outcomes" required></textarea>
                        </div>
                        
                        <div class="sikshya-form-grid">
                            <div class="sikshya-form-row">
                                <label>Category *</label>
                                <select name="category" required>
                                    <option value="">Select Category</option>
                                    <option value="web-development">Web Development</option>
                                    <option value="mobile-development">Mobile Development</option>
                                    <option value="data-science">Data Science</option>
                                    <option value="ui-ux-design">UI/UX Design</option>
                                    <option value="digital-marketing">Digital Marketing</option>
                                    <option value="business">Business</option>
                                </select>
                            </div>
                            
                            <div class="sikshya-form-row">
                                <label>Difficulty Level</label>
                                <select name="difficulty">
                                    <option value="beginner">Beginner</option>
                                    <option value="intermediate">Intermediate</option>
                                    <option value="advanced">Advanced</option>
                                    <option value="all-levels">All Levels</option>
                                </select>
                            </div>
                        </div>

                        <div class="sikshya-form-grid-3">
                            <div class="sikshya-form-row">
                                <label>Language</label>
                                <select name="language">
                                    <option value="english">English</option>
                                    <option value="spanish">Spanish</option>
                                    <option value="french">French</option>
                                    <option value="german">German</option>
                                </select>
                            </div>
                            
                            <div class="sikshya-form-row">
                                <label>Duration (hours)</label>
                                <input type="number" name="duration" placeholder="0" min="0" step="0.5">
                            </div>

                            <div class="sikshya-form-row">
                                <label>Max Students</label>
                                <input type="number" name="max_students" placeholder="Unlimited" min="0">
                            </div>
                        </div>
                    </div>

                    <div class="sikshya-section">
                        <h3 class="sikshya-section-title">Course Media</h3>
                        
                        <div class="sikshya-form-row">
                            <label>Course Thumbnail *</label>
                            <div class="sikshya-upload-area" onclick="document.getElementById('thumbnail').click()">
                                <div class="sikshya-upload-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <div>
                                    <strong>Click to upload course thumbnail</strong>
                                    <small>Recommended: 1280x720px, PNG or JPG, max 5MB</small>
                                </div>
                                <input type="file" id="thumbnail" name="thumbnail" style="display: none;" accept="image/*" required>
                            </div>
                        </div>

                        <div class="sikshya-form-row">
                            <label>Course Preview Video</label>
                            <div class="sikshya-upload-area" onclick="document.getElementById('preview-video').click()">
                                <div class="sikshya-upload-icon">
                                    <i class="fas fa-video"></i>
                                </div>
                                <div>
                                    <strong>Upload preview video (optional)</strong>
                                    <small>MP4 format, max 100MB, 2-5 minutes recommended</small>
                                </div>
                                <input type="file" id="preview-video" name="preview_video" style="display: none;" accept="video/*">
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Pricing Tab -->
            <div class="sikshya-tab-content <?php echo ($active_tab === 'pricing') ? 'active' : ''; ?>" id="pricing">
                <form id="pricing-form">
                    <div class="sikshya-section">
                        <h3 class="sikshya-section-title">Pricing</h3>
                        
                        <div class="sikshya-form-row">
                            <label>Course Type</label>
                            <select name="course_type" onchange="togglePricing(this)">
                                <option value="free">Free Course</option>
                                <option value="paid" selected>Paid Course</option>
                                <option value="subscription">Subscription Only</option>
                            </select>
                        </div>

                        <div class="sikshya-form-grid" id="pricing-fields">
                            <div class="sikshya-form-row">
                                <label>Course Price *</label>
                                <input type="number" name="price" placeholder="99.99" step="0.01" min="0">
                            </div>

                            <div class="sikshya-form-row">
                                <label>Discount Price</label>
                                <input type="number" name="sale_price" placeholder="79.99" step="0.01" min="0">
                            </div>
                        </div>
                    </div>

                    <div class="sikshya-section">
                        <h3 class="sikshya-section-title">Access Settings</h3>

                        <div class="sikshya-checkbox-group">
                            <input type="checkbox" id="enrollment" name="allow_enrollment" checked>
                            <label for="enrollment">Allow New Enrollments</label>
                        </div>

                        <div class="sikshya-checkbox-group">
                            <input type="checkbox" id="approval" name="require_approval">
                            <label for="approval">Require Instructor Approval</label>
                        </div>

                        <div class="sikshya-form-row">
                            <label>Access Duration</label>
                            <select name="access_duration">
                                <option value="lifetime" selected>Lifetime Access</option>
                                <option value="1_month">1 Month</option>
                                <option value="3_months">3 Months</option>
                                <option value="6_months">6 Months</option>
                                <option value="1_year">1 Year</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Curriculum Tab -->
            <div class="sikshya-tab-content <?php echo ($active_tab === 'curriculum') ? 'active' : ''; ?>" id="curriculum">
                <!-- Curriculum Content -->
                <div class="sikshya-curriculum-builder" id="curriculum-content">
                    <!-- Compact Empty State -->
                    <div class="sikshya-curriculum-empty-state" id="curriculum-empty-state">
                        <!-- Header with Inline Actions -->
                        <div class="sikshya-empty-header">
                            <div class="sikshya-empty-content">
                                <div class="sikshya-empty-icon">
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                    </svg>
                                </div>
                                <div class="sikshya-empty-text">
                                    <h3>Create Your First Chapter</h3>
                                    <p>Start building your course curriculum with organized chapters and lessons.</p>
                                </div>
                            </div>
                            <div class="sikshya-empty-actions">
                                <button class="sikshya-btn sikshya-btn-primary" onclick="showChapterModal()">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Create Chapter
                                </button>
                                <button class="sikshya-btn sikshya-btn-secondary" onclick="showTemplateModal()">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Use Template
                                </button>
                                <button class="sikshya-btn sikshya-btn-secondary" onclick="showImportModal()">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                                    </svg>
                                    Import
                                </button>
                            </div>
                        </div>

                        <!-- Quick Tips Strip -->
                        <div class="sikshya-tips-strip">
                            <div class="sikshya-tip-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span>Organize content into logical chapters</span>
                            </div>
                            <div class="sikshya-tip-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span>Mix videos, text, and interactive content</span>
                            </div>
                            <div class="sikshya-tip-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span>Add quizzes to test understanding</span>
                            </div>
                            <div class="sikshya-help-link">
                                <a href="#" onclick="openHelpGuide()">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                                        <path d="m12 17 .01 0"/>
                                    </svg>
                                    Course creation guide
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Existing Curriculum Structure (Hidden when empty) -->
                    <div class="sikshya-curriculum-items" id="curriculum-items" style="display: none;">
                            <div class="sikshya-chapter-header" onclick="toggleChapter('chapter-1')">
                                <div class="sikshya-chapter-info">
                                    <div class="sikshya-chapter-drag">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 6h.01M8 10h.01M8 14h.01M8 18h.01M16 6h.01M16 10h.01M16 14h.01M16 18h.01"/>
                                        </svg>
                                    </div>
                                    <div class="sikshya-chapter-details">
                                        <h4 class="sikshya-chapter-title">Chapter 1: Introduction</h4>
                                        <div class="sikshya-chapter-meta">
                                            <span class="sikshya-lesson-count">3 lessons</span>
                                            <span class="sikshya-chapter-duration">15 min</span>
                                            <span class="sikshya-chapter-status">Published</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="sikshya-chapter-actions">
                                    <button class="sikshya-icon-btn" onclick="event.stopPropagation(); addLesson('chapter-1')" title="Add Lesson">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                        </svg>
                                    </button>
                                    <button class="sikshya-icon-btn" onclick="event.stopPropagation(); editChapter('chapter-1')" title="Edit Chapter">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    <button class="sikshya-icon-btn" onclick="event.stopPropagation(); deleteChapter('chapter-1')" title="Delete Chapter">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                    <button class="sikshya-chapter-toggle" onclick="event.stopPropagation(); toggleChapter('chapter-1')" title="Toggle Chapter">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="sikshya-chapter-content" id="content-chapter-1">
                                <!-- Chapter Content Header -->
                                <div class="sikshya-chapter-content-header">
                                    <div class="sikshya-content-summary">
                                        <div class="sikshya-content-icon">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                            </svg>
                                        </div>
                                        <div class="sikshya-content-stats">
                                            <div class="sikshya-content-count">0 content items</div>
                                            <div class="sikshya-content-duration">No content added yet</div>
                                        </div>
                                    </div>
                                    <div class="sikshya-content-actions">
                                        <button class="sikshya-content-action-btn" onclick="addLesson('chapter-1')" title="Add Content">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                            </svg>
                                        </button>
                                        <button class="sikshya-content-action-btn" onclick="editChapter('chapter-1')" title="Edit Chapter">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                        </button>
                                        <button class="sikshya-content-action-btn" onclick="deleteChapter('chapter-1')" title="Delete Chapter">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <!-- Lessons Container -->
                                <div class="sikshya-lessons-container">
                                    <!-- Empty State for Chapter Content -->
                                    <div class="sikshya-chapter-empty">
                                        <div class="sikshya-chapter-empty-icon">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                        </div>
                                        <h4>No content yet</h4>
                                        <p>Add your first content item to this chapter</p>
                                        
                                        <!-- Add New Content Button -->
                                        <div class="sikshya-add-new-content">
                                            <button class="sikshya-add-content-cta" onclick="addLesson('chapter-1')">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                                </svg>
                                                Add New Content
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Sample Lesson Items (will replace empty state when content exists) -->
                                    <div class="sikshya-lesson-item" data-lesson-id="lesson-1" data-type="video" style="display: none;">
                                        <div class="sikshya-lesson-drag">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 6h.01M8 10h.01M8 14h.01M8 18h.01M16 6h.01M16 10h.01M16 14h.01M16 18h.01"/>
                                            </svg>
                                        </div>
                                        <div class="sikshya-lesson-type">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h8M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </div>
                                        <div class="sikshya-lesson-info">
                                            <h5 class="sikshya-lesson-title">Welcome to the Course</h5>
                                            <div class="sikshya-lesson-meta">
                                                <span class="sikshya-lesson-type-label">Video</span>
                                                <span class="sikshya-lesson-duration">5 min</span>
                                                <span class="sikshya-lesson-status published">Published</span>
                                            </div>
                                        </div>
                                        <div class="sikshya-lesson-actions">
                                            <button class="sikshya-icon-btn" onclick="editLesson('lesson-1')" title="Edit Lesson">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>
                                            <button class="sikshya-icon-btn" onclick="deleteLesson('lesson-1')" title="Delete Lesson">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Additional lesson types for demo -->
                                    <div class="sikshya-lesson-item" data-lesson-id="lesson-2" data-type="text" style="display: none;">
                                        <div class="sikshya-lesson-drag">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 6h.01M8 10h.01M8 14h.01M8 18h.01M16 6h.01M16 10h.01M16 14h.01M16 18h.01"/>
                                            </svg>
                                        </div>
                                        <div class="sikshya-lesson-type">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                        </div>
                                        <div class="sikshya-lesson-info">
                                            <h5 class="sikshya-lesson-title">Course Overview</h5>
                                            <div class="sikshya-lesson-meta">
                                                <span class="sikshya-lesson-type-label">Text</span>
                                                <span class="sikshya-lesson-duration">3 min</span>
                                                <span class="sikshya-lesson-status draft">Draft</span>
                                            </div>
                                        </div>
                                        <div class="sikshya-lesson-actions">
                                            <button class="sikshya-icon-btn" onclick="editLesson('lesson-2')" title="Edit Lesson">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>
                                            <button class="sikshya-icon-btn" onclick="deleteLesson('lesson-2')" title="Delete Lesson">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="sikshya-lesson-item" data-lesson-id="lesson-3" data-type="quiz" style="display: none;">
                                        <div class="sikshya-lesson-drag">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 6h.01M8 10h.01M8 14h.01M8 18h.01M16 6h.01M16 10h.01M16 14h.01M16 18h.01"/>
                                            </svg>
                                        </div>
                                        <div class="sikshya-lesson-type">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                            </svg>
                                        </div>
                                        <div class="sikshya-lesson-info">
                                            <h5 class="sikshya-lesson-title">Knowledge Check</h5>
                                            <div class="sikshya-lesson-meta">
                                                <span class="sikshya-lesson-type-label">Quiz</span>
                                                <span class="sikshya-lesson-duration">2 min</span>
                                                <span class="sikshya-lesson-status completed">Completed</span>
                                            </div>
                                        </div>
                                        <div class="sikshya-lesson-actions">
                                            <button class="sikshya-icon-btn" onclick="editLesson('lesson-3')" title="Edit Quiz">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>
                                            <button class="sikshya-icon-btn" onclick="deleteLesson('lesson-3')" title="Delete Quiz">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Add More Content (shown when lessons exist) -->
                                    <div class="sikshya-add-lesson" style="display: none;">
                                        <button class="sikshya-add-lesson-btn" onclick="addLesson('chapter-1')">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                            </svg>
                                            Add Lesson
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                                                <!-- Curriculum Actions -->
                <div class="sikshya-curriculum-actions">
                    <button class="sikshya-btn-outline" onclick="showChapterModal()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Chapter
                    </button>
                    
                    <!-- Demo Button to Toggle Content -->
                    <button class="sikshya-btn-outline" onclick="toggleDemoContent()" style="background: #f3e8ff; color: #9333ea; border-color: #c4b5fd;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Preview Content
                    </button>
                    
                    <div class="sikshya-action-divider"></div>
                    
                    <button class="sikshya-btn-outline" onclick="importContent()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                        </svg>
                        Import Content
                    </button>
                    
                    <button class="sikshya-btn-outline" onclick="toggleBulkMode()" id="bulk-mode-btn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Bulk Actions
                    </button>
                </div>
            </div>

            <!-- Settings Tab -->
            <div class="sikshya-tab-content <?php echo ($active_tab === 'settings') ? 'active' : ''; ?>" id="settings">
                <div class="sikshya-section">
                    <h3 class="sikshya-section-title">Enrollment Settings</h3>

                    <div class="sikshya-checkbox-group">
                        <input type="checkbox" id="enrollment" name="allow_enrollment" checked>
                        <label for="enrollment">Allow New Enrollments</label>
                    </div>

                    <div class="sikshya-checkbox-group">
                        <input type="checkbox" id="approval" name="require_approval">
                        <label for="approval">Require Instructor Approval</label>
                    </div>

                    <div class="sikshya-form-row">
                        <label>Access Duration</label>
                        <select name="access_duration">
                            <option value="lifetime" selected>Lifetime Access</option>
                            <option value="1_month">1 Month</option>
                            <option value="3_months">3 Months</option>
                            <option value="6_months">6 Months</option>
                            <option value="1_year">1 Year</option>
                        </select>
                    </div>
                </div>

                <div class="sikshya-section">
                    <h3 class="sikshya-section-title">Course Features</h3>

                    <div class="sikshya-checkbox-group">
                        <input type="checkbox" id="certificate" name="include_certificate" checked>
                        <label for="certificate">Include Certificate of Completion</label>
                    </div>

                    <div class="sikshya-checkbox-group">
                        <input type="checkbox" id="downloadable" name="allow_download">
                        <label for="downloadable">Allow Content Download</label>
                    </div>

                    <div class="sikshya-checkbox-group">
                        <input type="checkbox" id="discussion" name="enable_discussion" checked>
                        <label for="discussion">Enable Q&A Discussion</label>
                    </div>

                    <div class="sikshya-checkbox-group">
                        <input type="checkbox" id="reviews" name="allow_reviews" checked>
                        <label for="reviews">Allow Student Reviews & Ratings</label>
                    </div>
                </div>

                <div class="sikshya-section">
                    <h3 class="sikshya-section-title">Course Tags & SEO</h3>

                    <div class="sikshya-form-row">
                        <label>Course Tags</label>
                        <input type="text" name="tags" placeholder="Add tags separated by commas">
                    </div>

                    <div class="sikshya-form-row">
                        <label>SEO Keywords</label>
                        <input type="text" name="seo_keywords" placeholder="SEO keywords for better discoverability">
                    </div>

                    <div class="sikshya-form-row">
                        <label>Course URL Slug</label>
                        <input type="text" name="slug" placeholder="course-url-slug">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Design system is now integrated into admin.css -->

<style>
/* Course Builder using Design System */
.sikshya-course-builder {
    background: var(--sikshya-gray-50);
    margin: 0;
    border-radius: 0;
    box-shadow: none;
    font-family: var(--sikshya-font-family);
    min-height: 100vh;
}

.sikshya-main-content {
    display: flex;
    min-height: calc(100vh - 80px);
}

/* Sidebar Styling */
.sikshya-sidebar {
    width: 320px;
    background: white;
    border-right: 1px solid #e5e7eb;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}

/* Sidebar Header */
.sikshya-sidebar-header {
    padding: 24px;
    border-bottom: 1px solid #f1f5f9;
    background: #fafbfc;
}

.sikshya-course-status {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.sikshya-status-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 500;
    padding: 4px 12px;
    border-radius: 16px;
    background: #fef3c7;
    color: #92400e;
}

.sikshya-status-indicator.draft {
    background: #fef3c7;
    color: #92400e;
}

.sikshya-status-indicator.published {
    background: #d1fae5;
    color: #065f46;
}

.sikshya-status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.sikshya-completion-circle {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

.sikshya-progress-ring {
    transform: rotate(-90deg);
}

.sikshya-progress-ring-circle {
    transition: stroke-dashoffset 0.35s;
}

.sikshya-progress-text {
    position: absolute;
    font-size: 11px;
    font-weight: 600;
    color: #374151;
}

.sikshya-course-title h3 {
    font-size: 16px;
    font-weight: 600;
    color: #1f2937;
    margin: 0 0 4px 0;
}

.sikshya-course-title p {
    font-size: 13px;
    color: #6b7280;
    margin: 0;
}

/* Navigation */
.sikshya-sidebar-nav {
    flex: 1;
    padding: 0;
    overflow-y: auto;
}

.sikshya-nav-section {
    padding: 24px 16px 16px 16px;
    border-bottom: 1px solid #f1f5f9;
}

.sikshya-nav-section:last-child {
    border-bottom: none;
}

.sikshya-nav-section-title {
    font-size: 12px;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 0 0 16px 8px;
}

.sikshya-nav-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.sikshya-nav-item {
    margin-bottom: 2px;
}

.sikshya-nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-radius: 8px;
    text-decoration: none;
    color: #374151;
    transition: all 0.15s ease;
    position: relative;
}

.sikshya-nav-link:hover {
    background: #f3f4f6;
    color: #1f2937;
}

.sikshya-nav-link.active {
    background: #eff6ff;
    color: #2563eb;
    border: 1px solid #dbeafe;
    outline: none;
    box-shadow: none;
}

.sikshya-nav-link:focus {
    outline: none;
    box-shadow: none;
}

.sikshya-nav-link.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: #2563eb;
    border-radius: 0 2px 2px 0;
}

.sikshya-nav-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    flex-shrink: 0;
}

.sikshya-nav-content {
    flex: 1;
    min-width: 0;
}

.sikshya-nav-title {
    display: block;
    font-size: 14px;
    font-weight: 500;
    line-height: 1.3;
}

.sikshya-nav-desc {
    display: block;
    font-size: 12px;
    color: #6b7280;
    margin-top: 2px;
}

.sikshya-nav-status {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.sikshya-nav-status.completed {
    color: #059669;
}

.sikshya-nav-status.in-progress {
    color: #d97706;
}

.sikshya-nav-status.pending {
    color: #6b7280;
}

.sikshya-progress-count {
    font-size: 11px;
    font-weight: 600;
    background: #f3f4f6;
    color: #374151;
    padding: 2px 6px;
    border-radius: 10px;
}

.sikshya-quick-action {
    padding: 10px 12px;
}

.sikshya-quick-action .sikshya-nav-content {
    display: none;
}

/* Sidebar Footer */
.sikshya-sidebar-footer {
    padding: 20px;
    border-top: 1px solid #f1f5f9;
    background: #fafbfc;
}

.sikshya-publish-checklist h4 {
    font-size: 14px;
    font-weight: 600;
    color: #1f2937;
    margin: 0 0 12px 0;
}

.sikshya-checklist {
    list-style: none;
    margin: 0 0 20px 0;
    padding: 0;
}

.sikshya-checklist li {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 0;
    font-size: 13px;
    color: #6b7280;
}

.sikshya-checklist li.completed {
    color: #059669;
}

.sikshya-checklist li.pending {
    color: #6b7280;
}

.sikshya-publish-btn {
    width: 100%;
    background: #2563eb;
    color: white;
    border: none;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.15s ease;
}

.sikshya-publish-btn:hover:not(:disabled) {
    background: #1d4ed8;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
}

.sikshya-publish-btn:disabled {
    background: #9ca3af;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* Content Area */
.sikshya-content {
    flex: 1;
    background: white;
    overflow-y: auto;
    padding: 32px;
}

/* Header Updates */
.sikshya-header {
    background: white;
    border-bottom: 1px solid #e5e7eb;
    color: #1f2937;
    padding: 20px 32px;
}

.sikshya-header h1 {
    color: #1f2937;
    font-size: 20px;
    font-weight: 600;
}

/* Button Updates */
.sikshya-btn {
    background: #f3f4f6;
    color: #374151;
    border: 1px solid #d1d5db;
    padding: 10px 16px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.15s ease;
}

.sikshya-btn:hover {
    background: #e5e7eb;
    border-color: #9ca3af;
}

.sikshya-btn-primary {
    background: #2563eb;
    color: white;
    border-color: #2563eb;
}

.sikshya-btn-primary:hover {
    background: #1d4ed8;
    border-color: #1d4ed8;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .sikshya-sidebar {
        width: 280px;
    }
    
    .sikshya-content {
        padding: 24px;
    }
}

@media (max-width: 768px) {
    .sikshya-main-content {
        flex-direction: column;
    }
    
    .sikshya-sidebar {
        width: 100%;
        max-height: 300px;
        border-right: none;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .sikshya-sidebar-nav {
        max-height: 200px;
        overflow-y: auto;
    }
    
    .sikshya-nav-desc {
        display: none;
    }
    
    .sikshya-content {
        padding: 20px;
    }
}
</style> 