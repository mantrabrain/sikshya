<?php
/**
 * Course Information Form Template
 * 
 * @package Sikshya
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Course Tab -->
<div class="sikshya-tab-content <?php echo ($active_tab === 'course') ? 'active' : ''; ?>" id="course">
    <div class="sikshya-section sikshya-section-modern">
        <div class="sikshya-section-header">
            <div class="sikshya-section-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div class="sikshya-section-content">
                <h3 class="sikshya-section-title">Basic Information</h3>
                <p class="sikshya-section-desc">Set up the fundamental details of your course</p>
            </div>
        </div>
        
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
                <label>Course Category</label>
                <select name="category">
                    <option value="">Select Category</option>
                    <option value="programming">Programming</option>
                    <option value="design">Design</option>
                    <option value="business">Business</option>
                    <option value="marketing">Marketing</option>
                    <option value="photography">Photography</option>
                    <option value="music">Music</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <div class="sikshya-form-row">
                <label>Difficulty Level</label>
                <select name="difficulty">
                    <option value="beginner">Beginner</option>
                    <option value="intermediate">Intermediate</option>
                    <option value="advanced">Advanced</option>
                </select>
            </div>
        </div>
        
        <div class="sikshya-form-grid">
            <div class="sikshya-form-row">
                <label>Estimated Duration (hours)</label>
                <input type="number" name="duration" placeholder="10" min="1" step="0.5">
            </div>
            
            <div class="sikshya-form-row">
                <label>Course Language</label>
                <select name="language">
                    <option value="en">English</option>
                    <option value="es">Spanish</option>
                    <option value="fr">French</option>
                    <option value="de">German</option>
                    <option value="it">Italian</option>
                    <option value="pt">Portuguese</option>
                    <option value="other">Other</option>
                </select>
            </div>
        </div>
    </div>

    <div class="sikshya-section sikshya-section-modern">
        <div class="sikshya-section-header">
            <div class="sikshya-section-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <div class="sikshya-section-content">
                <h3 class="sikshya-section-title">Media & Visuals</h3>
                <p class="sikshya-section-desc">Add visual elements to make your course more engaging</p>
            </div>
        </div>
        
        <div class="sikshya-form-row">
            <label>Course Featured Image</label>
            <div class="sikshya-media-upload">
                <div class="sikshya-media-preview" id="featured_image_preview">
                    <div class="sikshya-media-placeholder">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span>No image selected</span>
                    </div>
                </div>
                <input type="hidden" name="featured_image" id="featured_image">
                <button type="button" class="sikshya-btn sikshya-btn-outline sikshya-media-btn" onclick="openMediaUpload('featured_image')">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                    </svg>
                    Upload Featured Image
                </button>
                <p class="sikshya-help-text">Recommended:ddddd 1200x675px (16:9 ratio)</p>
            </div>
        </div>
        
        <div class="sikshya-form-row">
            <label>Course Trailer Video</label>
            <div class="sikshya-media-upload">
                <div class="sikshya-media-preview" id="trailer_video_preview">
                    <div class="sikshya-media-placeholder">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        <span>No video selected</span>
                    </div>
                </div>
                <input type="hidden" name="trailer_video" id="trailer_video">
                <button type="button" class="sikshya-btn sikshya-btn-outline sikshya-media-btn" onclick="openMediaUpload('trailer_video')">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                    </svg>
                    Upload Trailer Video
                </button>
                <p class="sikshya-help-text">Optional promotional video for your course</p>
            </div>
        </div>
    </div>

    <div class="sikshya-section sikshya-section-modern">
        <div class="sikshya-section-header">
            <div class="sikshya-section-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="sikshya-section-content">
                <h3 class="sikshya-section-title">Learning Outcomes</h3>
                <p class="sikshya-section-desc">What will students learn from this course?</p>
            </div>
        </div>
        
        <div class="sikshya-repeater" id="learning-outcomes">
            <div class="sikshya-repeater-item">
                <div class="sikshya-repeater-input">
                    <input type="text" name="learning_outcomes[]" placeholder="Students will be able to...">
                </div>
                <button type="button" class="sikshya-btn sikshya-btn-icon sikshya-btn-danger" onclick="removeRepeaterItem(this)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <button type="button" class="sikshya-btn sikshya-btn-outline sikshya-add-item" onclick="addRepeaterItem('learning-outcomes', 'learning_outcomes[]', 'Students will be able to...')">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            Add Learning Outcome
        </button>
    </div>
</div>

