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
        <input type="url" id="video-lesson-url" placeholder="https://www.youtube.com/watch?v=..." onchange="extractVideoInfo()">
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
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
    </svg>
    Advanced Options
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
    </div>
</div> 