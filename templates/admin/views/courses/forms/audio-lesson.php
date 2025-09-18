<?php
/**
 * Advanced Audio Lesson Form Template with Tabbed Layout
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
            
            <button type="button" class="sikshya-tab-btn" data-tab="audio-settings">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
                </svg>
                <span>Audio Settings</span>
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
                    <label>Audio Title *</label>
                    <input type="text" id="audio-lesson-title" name="title" placeholder="Enter audio lesson title" required>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>Course *</label>
                    <select id="audio-lesson-course" name="course_id" required>
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
                    <textarea id="audio-lesson-description" name="description" placeholder="Brief description of this audio lesson"></textarea>
                </div>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Duration (minutes)</label>
                        <input type="number" id="audio-lesson-duration" name="duration" placeholder="15" min="1">
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Difficulty Level</label>
                        <select id="audio-lesson-difficulty" name="difficulty">
                            <option value="beginner">Beginner</option>
                            <option value="intermediate">Intermediate</option>
                            <option value="advanced">Advanced</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="sikshya-form-section">
                <h4 class="sikshya-form-section-title">Audio Content</h4>
                
                <div class="sikshya-form-row-small">
                    <label>Audio Source *</label>
                    <select id="audio-lesson-source" name="audio_source" onchange="toggleAudioSource()">
                        <option value="upload">Upload Audio File</option>
                        <option value="spotify">Spotify URL</option>
                        <option value="soundcloud">SoundCloud URL</option>
                        <option value="external">External URL</option>
                    </select>
                </div>
                
                <!-- Upload Audio -->
                <div id="audio-upload-section" class="sikshya-form-row-small">
                    <label>Upload Audio File *</label>
                    <div class="sikshya-upload-area" onclick="document.getElementById('audio-file-input').click()">
                        <input type="file" id="audio-file-input" accept="audio/*" style="display: none;" onchange="handleAudioUpload(this)">
                        <div class="sikshya-upload-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                            </svg>
                        </div>
                        <strong>Click to upload audio</strong>
                        <small>MP3, WAV, M4A, OGG (Max 100MB)</small>
                    </div>
                    <div id="audio-upload-progress" style="display: none;">
                        <div class="sikshya-progress-bar">
                            <div class="sikshya-progress-fill" id="audio-upload-fill"></div>
                        </div>
                        <small id="audio-upload-status">Uploading...</small>
                    </div>
                </div>
                
                <!-- External URL -->
                <div id="audio-url-section" class="sikshya-form-row-small" style="display: none;">
                    <label>Audio URL *</label>
                    <input type="url" id="audio-lesson-url" name="audio_url" placeholder="https://..." onchange="extractAudioInfo()">
                    <small>Paste Spotify, SoundCloud, or external audio URL</small>
                </div>
                
                <!-- Audio Preview -->
                <div id="audio-preview-section" class="sikshya-form-row-small" style="display: none;">
                    <label>Audio Preview</label>
                    <div id="audio-preview-container" style="background: #f8f9fa; border: 1px solid #e1e8ed; border-radius: 6px; padding: 20px; text-align: center;">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--sikshya-primary);">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
                        </svg>
                        <p id="audio-preview-title">Audio will appear here</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tab 2: Audio Settings -->
        <div class="sikshya-tab-panel" id="audio-settings" data-tab="audio-settings">
            <div class="sikshya-form-section">
                <h4 class="sikshya-form-section-title">Audio Player Settings</h4>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Audio Quality</label>
                        <select id="audio-lesson-quality" name="audio_quality">
                            <option value="auto">Auto (Best Available)</option>
                            <option value="320kbps">320 kbps (High)</option>
                            <option value="256kbps">256 kbps (Medium)</option>
                            <option value="128kbps">128 kbps (Low)</option>
                        </select>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Allow Downloads</label>
                        <select id="audio-lesson-download" name="allow_download">
                            <option value="no">No</option>
                            <option value="yes">Yes</option>
                        </select>
                    </div>
                </div>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Autoplay</label>
                        <select id="audio-lesson-autoplay" name="autoplay">
                            <option value="no">No</option>
                            <option value="yes">Yes</option>
                        </select>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Show Controls</label>
                        <select id="audio-lesson-controls" name="show_controls">
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                </div>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Playback Speed</label>
                        <select id="audio-lesson-speed" name="playback_speed">
                            <option value="1.0">Normal (1x)</option>
                            <option value="0.75">Slow (0.75x)</option>
                            <option value="0.5">Very Slow (0.5x)</option>
                            <option value="1.25">Fast (1.25x)</option>
                            <option value="1.5">Very Fast (1.5x)</option>
                            <option value="2.0">Double Speed (2x)</option>
                        </select>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Volume Control</label>
                        <select id="audio-lesson-volume" name="volume_control">
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
                        <select id="audio-lesson-skip" name="allow_skip">
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Require Completion</label>
                        <select id="audio-lesson-completion" name="require_completion">
                            <option value="yes">Yes (90% listened)</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                </div>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Background Music</label>
                        <select id="audio-lesson-bgmusic" name="background_music">
                            <option value="no">No Background Music</option>
                            <option value="yes">Include Background Music</option>
                        </select>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Audio Format</label>
                        <select id="audio-lesson-format" name="audio_format">
                            <option value="mp3">MP3 (Recommended)</option>
                            <option value="wav">WAV (High Quality)</option>
                            <option value="m4a">M4A (AAC)</option>
                            <option value="ogg">OGG (Open Source)</option>
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
                    <label>Audio Cover Art</label>
                    <div class="sikshya-upload-area" onclick="document.getElementById('audio-cover-input').click()">
                        <input type="file" id="audio-cover-input" accept="image/*" style="display: none;" onchange="handleCoverUpload(this)">
                        <div class="sikshya-upload-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <strong>Upload Cover Art</strong>
                        <small>JPG, PNG (Square ratio recommended)</small>
                    </div>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>Audio Transcript</label>
                    <textarea id="audio-lesson-transcript" name="transcript" placeholder="Paste audio transcript or enable auto-transcription"></textarea>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>Learning Objectives</label>
                    <textarea id="audio-lesson-objectives" name="objectives" placeholder="What will students learn from this audio?"></textarea>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>Additional Resources</label>
                    <textarea id="audio-lesson-resources" name="resources" placeholder="Links to related resources, downloads, etc."></textarea>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>Discussion Questions</label>
                    <textarea id="audio-lesson-questions" name="discussion_questions" placeholder="Questions to encourage discussion after listening"></textarea>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>Key Points Summary</label>
                    <textarea id="audio-lesson-summary" name="key_points" placeholder="Key points and takeaways from this audio"></textarea>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>Audio Chapters/Markers</label>
                    <textarea id="audio-lesson-chapters" name="chapters" placeholder="Add chapter markers (format: 00:00 - Chapter Title)"></textarea>
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
                        <textarea id="audio-lesson-prerequisites" name="prerequisites" placeholder="What should students know before listening?"></textarea>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Tags</label>
                        <input type="text" id="audio-lesson-tags" name="tags" placeholder="Enter tags separated by commas">
                    </div>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>SEO Description</label>
                    <textarea id="audio-lesson-seo" name="seo_description" placeholder="SEO-friendly description for search engines"></textarea>
                </div>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Access Level</label>
                        <select id="audio-lesson-access" name="access_level">
                            <option value="public">Public</option>
                            <option value="private">Private</option>
                            <option value="premium">Premium Only</option>
                        </select>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Publish Status</label>
                        <select id="audio-lesson-status" name="status">
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
// Audio source toggle functionality
function toggleAudioSource() {
    const source = document.getElementById('audio-lesson-source').value;
    const uploadSection = document.getElementById('audio-upload-section');
    const urlSection = document.getElementById('audio-url-section');
    
    if (source === 'upload') {
        uploadSection.style.display = 'block';
        urlSection.style.display = 'none';
    } else {
        uploadSection.style.display = 'none';
        urlSection.style.display = 'block';
    }
}

// Audio upload handler
function handleAudioUpload(input) {
    const file = input.files[0];
    if (file) {
        console.log('Audio file selected:', file.name);
        // Add upload logic here
    }
}

// Cover art upload handler
function handleCoverUpload(input) {
    const file = input.files[0];
    if (file) {
        console.log('Cover art file selected:', file.name);
        // Add upload logic here
    }
}

// Extract audio info from URL
function extractAudioInfo() {
    const url = document.getElementById('audio-lesson-url').value;
    if (url) {
        console.log('Extracting audio info from:', url);
        // Add audio info extraction logic here
    }
}
</script>