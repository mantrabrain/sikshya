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
            <ul class="sikshya-tab-nav">
                <li class="sikshya-tab-nav-item">
                    <a href="#" class="sikshya-tab-nav-link active" onclick="switchTab('course')">
                        <i class="fas fa-book sikshya-tab-icon"></i>
                        Course Information
                    </a>
                </li>
                <li class="sikshya-tab-nav-item">
                    <a href="#" class="sikshya-tab-nav-link" onclick="switchTab('curriculum')">
                        <i class="fas fa-list-ul sikshya-tab-icon"></i>
                        Curriculum
                    </a>
                </li>
                <li class="sikshya-tab-nav-item">
                    <a href="#" class="sikshya-tab-nav-link" onclick="switchTab('settings')">
                        <i class="fas fa-cog sikshya-tab-icon"></i>
                        Settings
                    </a>
                </li>
            </ul>
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