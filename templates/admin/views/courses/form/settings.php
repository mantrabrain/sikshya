<?php
/**
 * Course Settings Form Template
 * 
 * @package Sikshya
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Settings Tab -->
<div class="sikshya-tab-content <?php echo ($active_tab === 'settings') ? 'active' : ''; ?>" id="settings">
    <div class="sikshya-section">
        <h3 class="sikshya-section-title">Course Visibility</h3>
        
        <div class="sikshya-form-row">
            <label>Course Status</label>
            <select name="course_status">
                <option value="draft">Draft</option>
                <option value="published">Published</option>
                <option value="private">Private</option>
                <option value="password_protected">Password Protected</option>
            </select>
        </div>
        
        <div class="sikshya-form-row" id="password-field" style="display: none;">
            <label>Course Password</label>
            <input type="password" name="course_password" placeholder="Enter course password">
        </div>
        
        <div class="sikshya-form-row">
            <label class="sikshya-checkbox-label">
                <input type="checkbox" name="featured_course">
                <span class="sikshya-checkbox"></span>
                Mark as Featured Course
            </label>
            <p class="sikshya-help-text">Featured courses appear prominently on your site</p>
        </div>
    </div>

    <div class="sikshya-section">
        <h3 class="sikshya-section-title">Discussion & Community</h3>
        
        <div class="sikshya-form-row">
            <label class="sikshya-checkbox-label">
                <input type="checkbox" name="enable_discussions" checked>
                <span class="sikshya-checkbox"></span>
                Enable Course Discussions
            </label>
            <p class="sikshya-help-text">Allow students to ask questions and discuss topics</p>
        </div>
        
        <div class="sikshya-form-row">
            <label class="sikshya-checkbox-label">
                <input type="checkbox" name="enable_qa">
                <span class="sikshya-checkbox"></span>
                Enable Q&A Section
            </label>
            <p class="sikshya-help-text">Dedicated section for course-related questions</p>
        </div>
        
        <div class="sikshya-form-row">
            <label class="sikshya-checkbox-label">
                <input type="checkbox" name="enable_reviews">
                <span class="sikshya-checkbox"></span>
                Allow Course Reviews
            </label>
            <p class="sikshya-help-text">Students can rate and review the course</p>
        </div>
    </div>

    <div class="sikshya-section">
        <h3 class="sikshya-section-title">Certificates & Completion</h3>
        
        <div class="sikshya-form-row">
            <label class="sikshya-checkbox-label">
                <input type="checkbox" name="enable_certificate" onchange="toggleCertificateSettings(this)">
                <span class="sikshya-checkbox"></span>
                Enable Course Completion Certificate
            </label>
            <p class="sikshya-help-text">Award certificate when students complete the course</p>
        </div>
        
        <div class="sikshya-form-grid" id="certificate-settings" style="display: none;">
            <div class="sikshya-form-row">
                <label>Certificate Template</label>
                <select name="certificate_template">
                    <option value="default">Default Template</option>
                    <option value="modern">Modern Template</option>
                    <option value="classic">Classic Template</option>
                    <option value="custom">Custom Template</option>
                </select>
            </div>
            
            <div class="sikshya-form-row">
                <label>Completion Threshold (%)</label>
                <input type="number" name="completion_threshold" value="100" min="50" max="100">
            </div>
        </div>
    </div>

    <div class="sikshya-section">
        <h3 class="sikshya-section-title">SEO & Metadata</h3>
        
        <div class="sikshya-form-row">
            <label>SEO Title</label>
            <input type="text" name="seo_title" placeholder="Optimized title for search engines">
            <p class="sikshya-help-text">Leave empty to use course title</p>
        </div>
        
        <div class="sikshya-form-row">
            <label>Meta Description</label>
            <textarea name="meta_description" placeholder="Brief description for search engine results (155 characters max)" maxlength="155"></textarea>
            <p class="sikshya-help-text">Recommended: 150-155 characters</p>
        </div>
        
        <div class="sikshya-form-row">
            <label>Focus Keywords</label>
            <input type="text" name="focus_keywords" placeholder="keyword1, keyword2, keyword3">
            <p class="sikshya-help-text">Comma-separated keywords for SEO</p>
        </div>
    </div>

    <div class="sikshya-section">
        <h3 class="sikshya-section-title">Advanced Settings</h3>
        
        <div class="sikshya-form-row">
            <label class="sikshya-checkbox-label">
                <input type="checkbox" name="enable_progress_tracking" checked>
                <span class="sikshya-checkbox"></span>
                Track Student Progress
            </label>
            <p class="sikshya-help-text">Monitor how students progress through the course</p>
        </div>
        
        <div class="sikshya-form-row">
            <label class="sikshya-checkbox-label">
                <input type="checkbox" name="enable_analytics">
                <span class="sikshya-checkbox"></span>
                Enable Course Analytics
            </label>
            <p class="sikshya-help-text">Detailed analytics and reporting for this course</p>
        </div>
        
        <div class="sikshya-form-row">
            <label class="sikshya-checkbox-label">
                <input type="checkbox" name="enable_offline_access">
                <span class="sikshya-checkbox"></span>
                Allow Offline Access
            </label>
            <p class="sikshya-help-text">Students can download content for offline viewing</p>
        </div>
        
        <div class="sikshya-form-row">
            <label>Course Expiry</label>
            <select name="course_expiry_type">
                <option value="never">Never Expires</option>
                <option value="fixed_date">Fixed Date</option>
                <option value="relative">Relative to Enrollment</option>
            </select>
        </div>
        
        <div class="sikshya-form-row" id="expiry-date-field" style="display: none;">
            <label>Expiry Date</label>
            <input type="date" name="expiry_date">
        </div>
        
        <div class="sikshya-form-row" id="expiry-duration-field" style="display: none;">
            <label>Access Duration (Days)</label>
            <input type="number" name="access_duration" placeholder="365" min="1">
        </div>
    </div>

    <div class="sikshya-section">
        <h3 class="sikshya-section-title">Notifications</h3>
        
        <div class="sikshya-form-row">
            <label class="sikshya-checkbox-label">
                <input type="checkbox" name="notify_enrollment" checked>
                <span class="sikshya-checkbox"></span>
                Email on New Enrollment
            </label>
            <p class="sikshya-help-text">Get notified when someone enrolls in this course</p>
        </div>
        
        <div class="sikshya-form-row">
            <label class="sikshya-checkbox-label">
                <input type="checkbox" name="notify_completion">
                <span class="sikshya-checkbox"></span>
                Email on Course Completion
            </label>
            <p class="sikshya-help-text">Get notified when someone completes this course</p>
        </div>
        
        <div class="sikshya-form-row">
            <label class="sikshya-checkbox-label">
                <input type="checkbox" name="send_welcome_email" checked>
                <span class="sikshya-checkbox"></span>
                Send Welcome Email to Students
            </label>
            <p class="sikshya-help-text">Automatically send welcome email upon enrollment</p>
        </div>
    </div>
</div>

