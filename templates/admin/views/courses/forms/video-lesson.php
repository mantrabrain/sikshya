<?php
/**
 * Advanced Video Lesson Form Template
 * 
 * @package Sikshya
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="sikshya-form-section">
    <h4 class="sikshya-form-section-title">Basic Information</h4>
    
    <div class="sikshya-form-row-small">
        <label>Video Title *</label>
        <input type="text" id="video-lesson-title" placeholder="Enter video lesson title" required>
    </div>
    
    <div class="sikshya-form-row-small">
        <label>Description</label>
        <textarea id="video-lesson-description" placeholder="Brief description of this video lesson"></textarea>
    </div>
    
    <div class="sikshya-form-grid-2">
        <div class="sikshya-form-row-small">
            <label>Duration (minutes)</label>
            <input type="number" id="video-lesson-duration" placeholder="15" min="1">
        </div>
        
        <div class="sikshya-form-row-small">
            <label>Difficulty Level</label>
            <select id="video-lesson-difficulty">
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
        <select id="video-lesson-source" onchange="toggleVideoSource()">
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
            <i class="sikshya-upload-icon fas fa-cloud-upload-alt"></i>
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
        <input type="url" id="video-lesson-url" placeholder="https://www.youtube.com/watch?v=..." onchange="extractVideoInfo()">
        <small>Paste YouTube, Vimeo, or external video URL</small>
    </div>
    
    <!-- Video Preview -->
    <div id="video-preview-section" class="sikshya-form-row-small" style="display: none;">
        <label>Video Preview</label>
        <div id="video-preview-container" style="background: #f8f9fa; border: 1px solid #e1e8ed; border-radius: 6px; padding: 20px; text-align: center;">
            <i class="fas fa-play-circle" style="font-size: 48px; color: #3498db;"></i>
            <p id="video-preview-title">Video will appear here</p>
        </div>
    </div>
    
    <div class="sikshya-form-grid-2">
        <div class="sikshya-form-row-small">
            <label>Video Quality</label>
            <select id="video-lesson-quality">
                <option value="auto">Auto (Best Available)</option>
                <option value="1080p">1080p HD</option>
                <option value="720p">720p HD</option>
                <option value="480p">480p</option>
                <option value="360p">360p</option>
            </select>
        </div>
        
        <div class="sikshya-form-row-small">
            <label>Allow Downloads</label>
            <select id="video-lesson-download">
                <option value="no">No</option>
                <option value="yes">Yes</option>
            </select>
        </div>
    </div>
</div>

<div class="sikshya-form-section">
    <h4 class="sikshya-form-section-title">Video Settings</h4>
    
    <div class="sikshya-form-grid-2">
        <div class="sikshya-form-row-small">
            <label>Autoplay</label>
            <select id="video-lesson-autoplay">
                <option value="no">No</option>
                <option value="yes">Yes (Muted)</option>
            </select>
        </div>
        
        <div class="sikshya-form-row-small">
            <label>Show Controls</label>
            <select id="video-lesson-controls">
                <option value="yes">Yes</option>
                <option value="no">No</option>
            </select>
        </div>
    </div>
    
    <div class="sikshya-form-grid-2">
        <div class="sikshya-form-row-small">
            <label>Allow Skipping</label>
            <select id="video-lesson-skip">
                <option value="yes">Yes</option>
                <option value="no">No</option>
            </select>
        </div>
        
        <div class="sikshya-form-row-small">
            <label>Require Completion</label>
            <select id="video-lesson-completion">
                <option value="yes">Yes (90% watched)</option>
                <option value="no">No</option>
            </select>
        </div>
    </div>
</div>

<div class="sikshya-form-section">
    <h4 class="sikshya-form-section-title">Supporting Content</h4>
    
    <div class="sikshya-form-row-small">
        <label>Video Transcript</label>
        <textarea id="video-lesson-transcript" placeholder="Paste video transcript or enable auto-transcription"></textarea>
    </div>
    
    <div class="sikshya-form-row-small">
        <label>Learning Objectives</label>
        <textarea id="video-lesson-objectives" placeholder="What will students learn from this video?"></textarea>
    </div>
    
    <div class="sikshya-form-row-small">
        <label>Additional Resources</label>
        <textarea id="video-lesson-resources" placeholder="Links to related resources, downloads, etc."></textarea>
    </div>
    
    <div class="sikshya-form-row-small">
        <label>Discussion Questions</label>
        <textarea id="video-lesson-questions" placeholder="Questions to encourage discussion after watching"></textarea>
    </div>
</div>

<button class="sikshya-form-toggle" onclick="toggleAdvancedForm(this)">
    <i class="fas fa-chevron-down"></i> Advanced Options
</button>

<div class="sikshya-form-advanced">
    <div class="sikshya-form-grid-2">
        <div class="sikshya-form-row-small">
            <label>Prerequisites</label>
            <textarea id="video-lesson-prerequisites" placeholder="What should students know before watching?"></textarea>
        </div>
        
        <div class="sikshya-form-row-small">
            <label>Tags</label>
            <input type="text" id="video-lesson-tags" placeholder="Enter tags separated by commas">
        </div>
    </div>
    
    <div class="sikshya-form-row-small">
        <label>SEO Description</label>
        <textarea id="video-lesson-seo" placeholder="SEO-friendly description for search engines"></textarea>
    </div>
    
    <div class="sikshya-form-grid-2">
        <div class="sikshya-form-row-small">
            <label>Video Thumbnail</label>
            <div class="sikshya-upload-area" onclick="document.getElementById('video-thumbnail-input').click()">
                <input type="file" id="video-thumbnail-input" accept="image/*" style="display: none;" onchange="handleThumbnailUpload(this)">
                <i class="sikshya-upload-icon fas fa-image"></i>
                <strong>Upload Thumbnail</strong>
                <small>JPG, PNG (16:9 ratio recommended)</small>
            </div>
        </div>
        
        <div class="sikshya-form-row-small">
            <label>Captions/Subtitles</label>
            <div class="sikshya-upload-area" onclick="document.getElementById('video-captions-input').click()">
                <input type="file" id="video-captions-input" accept=".srt,.vtt" style="display: none;" onchange="handleCaptionsUpload(this)">
                <i class="sikshya-upload-icon fas fa-closed-captioning"></i>
                <strong>Upload Captions</strong>
                <small>SRT, VTT format</small>
            </div>
        </div>
    </div>
</div> 