<?php
/**
 * Advanced Audio Lesson Form Template
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
        <label>Audio Title *</label>
        <input type="text" id="audio-lesson-title" placeholder="Enter audio lesson title" required>
    </div>
    
    <div class="sikshya-form-row-small">
        <label>Description</label>
        <textarea id="audio-lesson-description" placeholder="Brief description of this audio lesson"></textarea>
    </div>
    
    <div class="sikshya-form-grid-2">
        <div class="sikshya-form-row-small">
            <label>Duration (minutes)</label>
            <input type="number" id="audio-lesson-duration" placeholder="15" min="1">
        </div>
        
        <div class="sikshya-form-row-small">
            <label>Difficulty Level</label>
            <select id="audio-lesson-difficulty">
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
        <select id="audio-lesson-source" onchange="toggleAudioSource()">
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
            <i class="sikshya-upload-icon fas fa-cloud-upload-alt"></i>
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
        <input type="url" id="audio-lesson-url" placeholder="https://..." onchange="extractAudioInfo()">
        <small>Paste Spotify, SoundCloud, or external audio URL</small>
    </div>
    
    <!-- Audio Preview -->
    <div id="audio-preview-section" class="sikshya-form-row-small" style="display: none;">
        <label>Audio Preview</label>
        <div id="audio-preview-container" style="background: #f8f9fa; border: 1px solid #e1e8ed; border-radius: 6px; padding: 20px; text-align: center;">
            <i class="fas fa-play-circle" style="font-size: 48px; color: #3498db;"></i>
            <p id="audio-preview-title">Audio will appear here</p>
        </div>
    </div>
    
    <div class="sikshya-form-grid-2">
        <div class="sikshya-form-row-small">
            <label>Audio Quality</label>
            <select id="audio-lesson-quality">
                <option value="auto">Auto (Best Available)</option>
                <option value="320kbps">320 kbps (High)</option>
                <option value="256kbps">256 kbps (Medium)</option>
                <option value="128kbps">128 kbps (Low)</option>
            </select>
        </div>
        
        <div class="sikshya-form-row-small">
            <label>Allow Downloads</label>
            <select id="audio-lesson-download">
                <option value="no">No</option>
                <option value="yes">Yes</option>
            </select>
        </div>
    </div>
</div>

<div class="sikshya-form-section">
    <h4 class="sikshya-form-section-title">Audio Settings</h4>
    
    <div class="sikshya-form-grid-2">
        <div class="sikshya-form-row-small">
            <label>Autoplay</label>
            <select id="audio-lesson-autoplay">
                <option value="no">No</option>
                <option value="yes">Yes</option>
            </select>
        </div>
        
        <div class="sikshya-form-row-small">
            <label>Show Controls</label>
            <select id="audio-lesson-controls">
                <option value="yes">Yes</option>
                <option value="no">No</option>
            </select>
        </div>
    </div>
    
    <div class="sikshya-form-grid-2">
        <div class="sikshya-form-row-small">
            <label>Allow Skipping</label>
            <select id="audio-lesson-skip">
                <option value="yes">Yes</option>
                <option value="no">No</option>
            </select>
        </div>
        
        <div class="sikshya-form-row-small">
            <label>Require Completion</label>
            <select id="audio-lesson-completion">
                <option value="yes">Yes (90% listened)</option>
                <option value="no">No</option>
            </select>
        </div>
    </div>
    
    <div class="sikshya-form-grid-2">
        <div class="sikshya-form-row-small">
            <label>Playback Speed</label>
            <select id="audio-lesson-speed">
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
            <select id="audio-lesson-volume">
                <option value="yes">Yes</option>
                <option value="no">No</option>
            </select>
        </div>
    </div>
</div>

<div class="sikshya-form-section">
    <h4 class="sikshya-form-section-title">Supporting Content</h4>
    
    <div class="sikshya-form-row-small">
        <label>Audio Transcript</label>
        <textarea id="audio-lesson-transcript" placeholder="Paste audio transcript or enable auto-transcription"></textarea>
    </div>
    
    <div class="sikshya-form-row-small">
        <label>Learning Objectives</label>
        <textarea id="audio-lesson-objectives" placeholder="What will students learn from this audio?"></textarea>
    </div>
    
    <div class="sikshya-form-row-small">
        <label>Additional Resources</label>
        <textarea id="audio-lesson-resources" placeholder="Links to related resources, downloads, etc."></textarea>
    </div>
    
    <div class="sikshya-form-row-small">
        <label>Discussion Questions</label>
        <textarea id="audio-lesson-questions" placeholder="Questions to encourage discussion after listening"></textarea>
    </div>
    
    <div class="sikshya-form-row-small">
        <label>Key Points Summary</label>
        <textarea id="audio-lesson-summary" placeholder="Key points and takeaways from this audio"></textarea>
    </div>
</div>

<button class="sikshya-form-toggle" onclick="toggleAdvancedForm(this)">
    <i class="fas fa-chevron-down"></i> Advanced Options
</button>

<div class="sikshya-form-advanced">
    <div class="sikshya-form-grid-2">
        <div class="sikshya-form-row-small">
            <label>Prerequisites</label>
            <textarea id="audio-lesson-prerequisites" placeholder="What should students know before listening?"></textarea>
        </div>
        
        <div class="sikshya-form-row-small">
            <label>Tags</label>
            <input type="text" id="audio-lesson-tags" placeholder="Enter tags separated by commas">
        </div>
    </div>
    
    <div class="sikshya-form-row-small">
        <label>SEO Description</label>
        <textarea id="audio-lesson-seo" placeholder="SEO-friendly description for search engines"></textarea>
    </div>
    
    <div class="sikshya-form-grid-2">
        <div class="sikshya-form-row-small">
            <label>Audio Cover Art</label>
            <div class="sikshya-upload-area" onclick="document.getElementById('audio-cover-input').click()">
                <input type="file" id="audio-cover-input" accept="image/*" style="display: none;" onchange="handleCoverUpload(this)">
                <i class="sikshya-upload-icon fas fa-image"></i>
                <strong>Upload Cover Art</strong>
                <small>JPG, PNG (Square ratio recommended)</small>
            </div>
        </div>
        
        <div class="sikshya-form-row-small">
            <label>Audio Chapters/Markers</label>
            <textarea id="audio-lesson-chapters" placeholder="Add chapter markers (format: 00:00 - Chapter Title)"></textarea>
        </div>
    </div>
    
    <div class="sikshya-form-grid-2">
        <div class="sikshya-form-row-small">
            <label>Background Music</label>
            <select id="audio-lesson-bgmusic">
                <option value="no">No Background Music</option>
                <option value="yes">Include Background Music</option>
            </select>
        </div>
        
        <div class="sikshya-form-row-small">
            <label>Audio Format</label>
            <select id="audio-lesson-format">
                <option value="mp3">MP3 (Recommended)</option>
                <option value="wav">WAV (High Quality)</option>
                <option value="m4a">M4A (AAC)</option>
                <option value="ogg">OGG (Open Source)</option>
            </select>
        </div>
    </div>
</div> 