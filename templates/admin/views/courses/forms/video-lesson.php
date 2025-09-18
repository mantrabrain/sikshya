<?php
/**
 * Advanced Video Lesson Form Template with Tabbed Layout
 * 
 * @package Sikshya
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="sikshya-lesson-form-tabs">
    <!-- Left Sidebar Navigation -->
    <div class="sikshya-tabs-sidebar">
        <div class="sikshya-tabs-nav">
            <button type="button" class="sikshya-tab-btn active" data-tab="basic-content">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Basic & Content</span>
            </button>
            
            <button type="button" class="sikshya-tab-btn" data-tab="video-settings">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                <span>Video Settings</span>
            </button>
            
            <button type="button" class="sikshya-tab-btn" data-tab="media-resources">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span>Media & Resources</span>
            </button>
            
            <button type="button" class="sikshya-tab-btn" data-tab="advanced">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4"/>
                </svg>
                <span>Advanced</span>
            </button>
        </div>
    </div>
    
    <!-- Right Content Area -->
    <div class="sikshya-tabs-content">
        <!-- Tab 1: Basic & Content -->
        <div class="sikshya-tab-panel active" id="basic-content" data-tab="basic-content">
            <div class="sikshya-form-section">
                <h4 class="sikshya-form-section-title">Basic Information</h4>
                
                <div class="sikshya-form-row-small">
                    <label>Video Title *</label>
                    <input type="text" id="video-lesson-title" name="title" placeholder="Enter video lesson title" required>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>Course *</label>
                    <select id="video-lesson-course" name="course_id" required>
                        <option value="">Select Course</option>
                        <?php
                        $courses = get_posts([
                            'post_type' => 'sikshya_course',
                            'post_status' => 'publish',
                            'posts_per_page' => -1,
                            'orderby' => 'title',
                            'order' => 'ASC'
                        ]);
                        
                        foreach ($courses as $course) {
                            echo '<option value="' . esc_attr($course->ID) . '">' . esc_html($course->post_title) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>Description</label>
                    <textarea id="video-lesson-description" name="description" placeholder="Brief description of this video lesson"></textarea>
                </div>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Duration (minutes)</label>
                        <input type="number" id="video-lesson-duration" name="duration" placeholder="15" min="1">
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Difficulty Level</label>
                        <select id="video-lesson-difficulty" name="difficulty">
                            <option value="beginner">Beginner</option>
                            <option value="intermediate">Intermediate</option>
                            <option value="advanced">Advanced</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="sikshya-form-section">
                <h4 class="sikshya-form-section-title">Video Content</h4>
                
                <div class="sikshya-form-row-small">
                    <label>Video Source *</label>
                    <select id="video-lesson-source" name="video_source" onchange="toggleVideoSource()">
                        <option value="upload">Upload Video File</option>
                        <option value="youtube">YouTube URL</option>
                        <option value="vimeo">Vimeo URL</option>
                        <option value="external">External URL</option>
                    </select>
                </div>
                
                <!-- Upload Video -->
                <div id="video-upload-section" class="sikshya-form-row-small">
                    <label>Upload Video File *</label>
                    <div class="sikshya-upload-area" onclick="document.getElementById('video-file-input').click()">
                        <input type="file" id="video-file-input" accept="video/*" style="display: none;" onchange="handleVideoUpload(this)">
                        <div class="sikshya-upload-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                            </svg>
                        </div>
                        <strong>Click to upload video</strong>
                        <small>MP4, WebM, MOV (Max 500MB)</small>
                    </div>
                    <div id="video-upload-progress" style="display: none;">
                        <div class="sikshya-progress-bar">
                            <div class="sikshya-progress-fill" id="video-upload-fill"></div>
                        </div>
                        <small id="video-upload-status">Uploading...</small>
                    </div>
                </div>
                
                <!-- YouTube/Vimeo URL -->
                <div id="video-url-section" class="sikshya-form-row-small" style="display: none;">
                    <label>Video URL *</label>
                    <input type="url" id="video-lesson-url" name="video_url" placeholder="https://www.youtube.com/watch?v=..." onchange="extractVideoInfo()">
                    <small>Paste YouTube, Vimeo, or external video URL</small>
                </div>
                
                <!-- Video Preview -->
                <div id="video-preview-section" class="sikshya-form-row-small" style="display: none;">
                    <label>Video Preview</label>
                    <div id="video-preview-container" style="background: #f8f9fa; border: 1px solid #e1e8ed; border-radius: 6px; padding: 20px; text-align: center;">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--sikshya-primary);">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p id="video-preview-title">Video will appear here</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tab 2: Video Settings -->
        <div class="sikshya-tab-panel" id="video-settings" data-tab="video-settings">
            <div class="sikshya-form-section">
                <h4 class="sikshya-form-section-title">Video Player Settings</h4>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Video Quality</label>
                        <select id="video-lesson-quality" name="video_quality">
                            <option value="auto">Auto (Best Available)</option>
                            <option value="1080p">1080p HD</option>
                            <option value="720p">720p HD</option>
                            <option value="480p">480p</option>
                            <option value="360p">360p</option>
                        </select>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Allow Downloads</label>
                        <select id="video-lesson-download" name="allow_download">
                            <option value="no">No</option>
                            <option value="yes">Yes</option>
                        </select>
                    </div>
                </div>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Autoplay</label>
                        <select id="video-lesson-autoplay" name="autoplay">
                            <option value="no">No</option>
                            <option value="yes">Yes (Muted)</option>
                        </select>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Show Controls</label>
                        <select id="video-lesson-controls" name="show_controls">
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="sikshya-form-section">
                <h4 class="sikshya-form-section-title">Lesson Settings</h4>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Allow Skipping</label>
                        <select id="video-lesson-skip" name="allow_skip">
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Require Completion</label>
                        <select id="video-lesson-completion" name="require_completion">
                            <option value="yes">Yes (90% watched)</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tab 3: Media & Resources -->
        <div class="sikshya-tab-panel" id="media-resources" data-tab="media-resources">
            <div class="sikshya-form-section">
                <h4 class="sikshya-form-section-title">Supporting Materials</h4>
                
                <div class="sikshya-form-row-small">
                    <label>Video Thumbnail</label>
                    <div class="sikshya-upload-area" onclick="document.getElementById('video-thumbnail-input').click()">
                        <input type="file" id="video-thumbnail-input" accept="image/*" style="display: none;" onchange="handleThumbnailUpload(this)">
                        <div class="sikshya-upload-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <strong>Upload Thumbnail</strong>
                        <small>JPG, PNG (16:9 ratio recommended)</small>
                    </div>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>Captions/Subtitles</label>
                    <div class="sikshya-upload-area" onclick="document.getElementById('video-captions-input').click()">
                        <input type="file" id="video-captions-input" accept=".srt,.vtt" style="display: none;" onchange="handleCaptionsUpload(this)">
                        <div class="sikshya-upload-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                            </svg>
                        </div>
                        <strong>Upload Captions</strong>
                        <small>SRT, VTT format</small>
                    </div>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>Video Transcript</label>
                    <textarea id="video-lesson-transcript" name="transcript" placeholder="Paste video transcript or enable auto-transcription"></textarea>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>Learning Objectives</label>
                    <textarea id="video-lesson-objectives" name="objectives" placeholder="What will students learn from this video?"></textarea>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>Additional Resources</label>
                    <textarea id="video-lesson-resources" name="resources" placeholder="Links to related resources, downloads, etc."></textarea>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>Discussion Questions</label>
                    <textarea id="video-lesson-questions" name="discussion_questions" placeholder="Questions to encourage discussion after watching"></textarea>
                </div>
            </div>
        </div>
        
        <!-- Tab 4: Advanced -->
        <div class="sikshya-tab-panel" id="advanced" data-tab="advanced">
            <div class="sikshya-form-section">
                <h4 class="sikshya-form-section-title">Advanced Options</h4>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Prerequisites</label>
                        <textarea id="video-lesson-prerequisites" name="prerequisites" placeholder="What should students know before watching?"></textarea>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Tags</label>
                        <input type="text" id="video-lesson-tags" name="tags" placeholder="Enter tags separated by commas">
                    </div>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>SEO Description</label>
                    <textarea id="video-lesson-seo" name="seo_description" placeholder="SEO-friendly description for search engines"></textarea>
                </div>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Access Level</label>
                        <select id="video-lesson-access" name="access_level">
                            <option value="public">Public</option>
                            <option value="private">Private</option>
                            <option value="premium">Premium Only</option>
                        </select>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Publish Status</label>
                        <select id="video-lesson-status" name="status">
                            <option value="draft">Draft</option>
                            <option value="publish">Published</option>
                            <option value="private">Private</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Video source toggle functionality
function toggleVideoSource() {
    const source = document.getElementById('video-lesson-source').value;
    const uploadSection = document.getElementById('video-upload-section');
    const urlSection = document.getElementById('video-url-section');
    
    if (source === 'upload') {
        uploadSection.style.display = 'block';
        urlSection.style.display = 'none';
    } else {
        uploadSection.style.display = 'none';
        urlSection.style.display = 'block';
    }
}

// Video upload handler
function handleVideoUpload(input) {
    const file = input.files[0];
    if (file) {
        console.log('Video file selected:', file.name);
        // Add upload logic here
    }
}

// Thumbnail upload handler
function handleThumbnailUpload(input) {
    const file = input.files[0];
    if (file) {
        console.log('Thumbnail file selected:', file.name);
        // Add upload logic here
    }
}

// Captions upload handler
function handleCaptionsUpload(input) {
    const file = input.files[0];
    if (file) {
        console.log('Captions file selected:', file.name);
        // Add upload logic here
    }
}

// Extract video info from URL
function extractVideoInfo() {
    const url = document.getElementById('video-lesson-url').value;
    if (url) {
        console.log('Extracting video info from:', url);
        // Add video info extraction logic here
    }
}
</script>