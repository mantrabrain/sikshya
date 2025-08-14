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
                <i class="fas fa-eye"></i> Preview
            </button>
            <button class="sikshya-btn" onclick="saveDraft()">
                <i class="fas fa-save"></i> Save Draft
            </button>
            <button class="sikshya-btn sikshya-btn-primary" onclick="publishCourse()">
                <i class="fas fa-rocket"></i> Publish Course
            </button>
        </div>
    </div>

    <div class="sikshya-main-content">
        <div class="sikshya-sidebar">
            <!-- Course Progress Summary -->
            <div class="sikshya-sidebar-header">
                <div class="sikshya-course-status">
                    <div class="sikshya-status-indicator draft">
                        <span class="sikshya-status-dot"></span>
                        Draft
                    </div>
                    <div class="sikshya-completion-circle">
                        <svg class="sikshya-progress-ring" width="40" height="40">
                            <circle class="sikshya-progress-ring-circle" stroke="#e5e7eb" stroke-width="3" fill="transparent" r="16" cx="20" cy="20"/>
                            <circle class="sikshya-progress-ring-progress" stroke="#2563eb" stroke-width="3" fill="transparent" r="16" cx="20" cy="20" style="stroke-dasharray: 100.53 100.53; stroke-dashoffset: 70.37;"/>
                        </svg>
                        <span class="sikshya-progress-text">30%</span>
                    </div>
                </div>
                <div class="sikshya-course-title">
                    <h3>New Course</h3>
                    <p>Complete all sections to publish</p>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <nav class="sikshya-sidebar-nav">
                <div class="sikshya-nav-section">
                    <h4 class="sikshya-nav-section-title">Course Setup</h4>
                    <ul class="sikshya-nav-list">
                        <li class="sikshya-nav-item">
                            <a href="#" class="sikshya-nav-link active" onclick="switchTab('course')" data-tab="course">
                                <div class="sikshya-nav-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                                    </svg>
                                </div>
                                <div class="sikshya-nav-content">
                                    <span class="sikshya-nav-title">Course Information</span>
                                    <span class="sikshya-nav-desc">Basic details & media</span>
                                </div>
                                <div class="sikshya-nav-status completed">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="20,6 9,17 4,12"/>
                                    </svg>
                                </div>
                            </a>
                        </li>
                        <li class="sikshya-nav-item">
                            <a href="#" class="sikshya-nav-link" onclick="switchTab('curriculum')" data-tab="curriculum">
                                <div class="sikshya-nav-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>
                                    </svg>
                                </div>
                                <div class="sikshya-nav-content">
                                    <span class="sikshya-nav-title">Curriculum</span>
                                    <span class="sikshya-nav-desc">Lessons & content</span>
                                </div>
                                <div class="sikshya-nav-status in-progress">
                                    <span class="sikshya-progress-count">2/5</span>
                                </div>
                            </a>
                        </li>
                        <li class="sikshya-nav-item">
                            <a href="#" class="sikshya-nav-link" onclick="switchTab('settings')" data-tab="settings">
                                <div class="sikshya-nav-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="3"/>
                                        <path d="M12 1v6m0 6v6"/>
                                        <path d="M1 12h6m6 0h6"/>
                                    </svg>
                                </div>
                                <div class="sikshya-nav-content">
                                    <span class="sikshya-nav-title">Settings</span>
                                    <span class="sikshya-nav-desc">Pricing & preferences</span>
                                </div>
                                <div class="sikshya-nav-status pending">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <path d="M12 6v6l4 2"/>
                                    </svg>
                                </div>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="sikshya-nav-section">
                    <h4 class="sikshya-nav-section-title">Quick Actions</h4>
                    <ul class="sikshya-nav-list">
                        <li class="sikshya-nav-item">
                            <a href="#" class="sikshya-nav-link sikshya-quick-action" onclick="previewCourse()">
                                <div class="sikshya-nav-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </div>
                                <span class="sikshya-nav-title">Preview Course</span>
                            </a>
                        </li>
                        <li class="sikshya-nav-item">
                            <a href="#" class="sikshya-nav-link sikshya-quick-action" onclick="saveDraft()">
                                <div class="sikshya-nav-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                                        <polyline points="17,21 17,13 7,13 7,21"/>
                                        <polyline points="7,3 7,8 15,8"/>
                                    </svg>
                                </div>
                                <span class="sikshya-nav-title">Save Draft</span>
                            </a>
                        </li>
                    </ul>
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
            <div class="sikshya-tab-content active" id="course">
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
                </form>
            </div>

            <!-- Curriculum Tab -->
            <div class="sikshya-tab-content" id="curriculum">
                <div class="sikshya-section">
                    <div class="sikshya-section-title" style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-list"></i>
                            Course Curriculum
                        </div>
                        <button class="sikshya-btn sikshya-btn-secondary" onclick="toggleBulkMode()" style="font-size: 12px; padding: 6px 12px;">
                            <i class="fas fa-check-square"></i> Bulk Actions
                        </button>
                    </div>
                    
                    <div class="sikshya-progress-bar">
                        <div class="sikshya-progress-fill" id="curriculum-progress"></div>
                    </div>
                    
                    <div id="curriculum-content">
                        <div class="sikshya-empty-state">
                            <i class="fas fa-play-circle"></i>
                            <h3>No content added yet</h3>
                            <p>Start building your course by adding your first content below.</p>
                        </div>
                    </div>
                    
                    <div class="sikshya-curriculum-actions" style="display: flex; gap: 12px; margin-top: 20px;">
                        <button class="sikshya-add-chapter-btn" onclick="showChapterModal()">
                            <i class="fas fa-plus"></i>
                            Add New Chapter
                        </button>
                        
                        <button class="sikshya-add-content-btn" onclick="showContentTypeModal()" style="background: #27ae60; border-color: #27ae60;">
                            <i class="fas fa-plus"></i>
                            Add New Content
                        </button>
                    </div>
                </div>
            </div>

            <!-- Settings Tab -->
            <div class="sikshya-tab-content" id="settings">
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