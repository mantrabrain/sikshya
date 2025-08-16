<?php
/**
 * Advanced Assignment Form Template
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
        <label>Assignment Title *</label>
        <input type="text" id="assignment-lesson-title" placeholder="Enter assignment title" required>
    </div>
    
    <div class="sikshya-form-row-small">
        <label>Description</label>
        <textarea id="assignment-lesson-description" placeholder="Brief description of this assignment"></textarea>
    </div>
    
    <div class="sikshya-form-grid-2">
        <div class="sikshya-form-row-small">
            <label>Estimated Time (hours)</label>
            <input type="number" id="assignment-lesson-duration" placeholder="2" min="0.5" step="0.5">
        </div>
        
        <div class="sikshya-form-row-small">
            <label>Difficulty Level</label>
            <select id="assignment-lesson-difficulty">
                <option value="beginner">Beginner</option>
                <option value="intermediate">Intermediate</option>
                <option value="advanced">Advanced</option>
            </select>
        </div>
    </div>
</div>

<div class="sikshya-form-section">
    <h4 class="sikshya-form-section-title">Assignment Details</h4>
    
    <div class="sikshya-form-row-small">
        <label>Assignment Instructions *</label>
        <textarea id="assignment-lesson-instructions" placeholder="Provide detailed instructions for students" required style="min-height: 200px;"></textarea>
    </div>
    
    <div class="sikshya-form-row-small">
        <label>Learning Objectives</label>
        <textarea id="assignment-lesson-objectives" placeholder="What will students learn from this assignment?"></textarea>
    </div>
    
    <div class="sikshya-form-row-small">
        <label>Success Criteria</label>
        <textarea id="assignment-lesson-criteria" placeholder="What constitutes a successful completion of this assignment?"></textarea>
    </div>
</div>

<div class="sikshya-form-section">
    <h4 class="sikshya-form-section-title">Submission Requirements</h4>
    
    <div class="sikshya-form-row-small">
        <label>Submission Type</label>
        <select id="assignment-lesson-submission-type" onchange="toggleSubmissionOptions()">
            <option value="file">File Upload</option>
            <option value="text">Text Entry</option>
            <option value="url">URL/Link</option>
            <option value="media">Media Recording</option>
            <option value="multiple">Multiple Types</option>
        </select>
    </div>
    
    <!-- File Upload Options -->
    <div id="file-upload-options" class="sikshya-form-row-small">
        <label>Allowed File Types</label>
        <div class="sikshya-checkbox-group">
            <input type="checkbox" id="file-pdf" value="pdf">
            <label for="file-pdf">PDF Documents</label>
        </div>
        <div class="sikshya-checkbox-group">
            <input type="checkbox" id="file-doc" value="doc">
            <label for="file-doc">Word Documents (.doc, .docx)</label>
        </div>
        <div class="sikshya-checkbox-group">
            <input type="checkbox" id="file-ppt" value="ppt">
            <label for="file-ppt">PowerPoint (.ppt, .pptx)</label>
        </div>
        <div class="sikshya-checkbox-group">
            <input type="checkbox" id="file-excel" value="excel">
            <label for="file-excel">Excel (.xls, .xlsx)</label>
        </div>
        <div class="sikshya-checkbox-group">
            <input type="checkbox" id="file-image" value="image">
            <label for="file-image">Images (.jpg, .png, .gif)</label>
        </div>
        <div class="sikshya-checkbox-group">
            <input type="checkbox" id="file-video" value="video">
            <label for="file-video">Videos (.mp4, .mov, .avi)</label>
        </div>
        <div class="sikshya-checkbox-group">
            <input type="checkbox" id="file-audio" value="audio">
            <label for="file-audio">Audio (.mp3, .wav)</label>
        </div>
        <div class="sikshya-checkbox-group">
            <input type="checkbox" id="file-zip" value="zip">
            <label for="file-zip">Compressed Files (.zip, .rar)</label>
        </div>
    </div>
    
    <div class="sikshya-form-grid-2">
        <div class="sikshya-form-row-small">
            <label>Maximum File Size (MB)</label>
            <input type="number" id="assignment-lesson-file-size" placeholder="10" min="1" max="100">
        </div>
        
        <div class="sikshya-form-row-small">
            <label>Number of Files Allowed</label>
            <input type="number" id="assignment-lesson-file-count" placeholder="1" min="1" max="10">
        </div>
    </div>
    
    <div class="sikshya-form-row-small">
        <label>Text Entry Word Limit</label>
        <input type="number" id="assignment-lesson-word-limit" placeholder="500" min="1">
        <small>Leave empty for no limit</small>
    </div>
</div>

<div class="sikshya-form-section">
    <h4 class="sikshya-form-section-title">Grading & Assessment</h4>
    
    <div class="sikshya-form-grid-2">
        <div class="sikshya-form-row-small">
            <label>Grading Method</label>
            <select id="assignment-lesson-grading">
                <option value="points">Points (0-100)</option>
                <option value="letter">Letter Grade (A-F)</option>
                <option value="percentage">Percentage</option>
                <option value="pass_fail">Pass/Fail</option>
                <option value="rubric">Rubric</option>
            </select>
        </div>
        
        <div class="sikshya-form-row-small">
            <label>Maximum Points</label>
            <input type="number" id="assignment-lesson-points" placeholder="100" min="1">
        </div>
    </div>
    
    <div class="sikshya-form-grid-2">
        <div class="sikshya-form-row-small">
            <label>Passing Score (%)</label>
            <input type="number" id="assignment-lesson-passing" placeholder="70" min="0" max="100">
        </div>
        
        <div class="sikshya-form-row-small">
            <label>Allow Resubmission</label>
            <select id="assignment-lesson-resubmit">
                <option value="no">No</option>
                <option value="yes">Yes</option>
                <option value="until_passed">Until Passed</option>
            </select>
        </div>
    </div>
    
    <div class="sikshya-form-row-small">
        <label>Rubric Criteria</label>
        <textarea id="assignment-lesson-rubric" placeholder="Define grading criteria and point values for each criterion"></textarea>
        <small>Format: Criterion | Points | Description</small>
    </div>
</div>

<div class="sikshya-form-section">
    <h4 class="sikshya-form-section-title">Timeline & Availability</h4>
    
    <div class="sikshya-form-grid-2">
        <div class="sikshya-form-row-small">
            <label>Available From</label>
            <input type="datetime-local" id="assignment-lesson-available-from">
        </div>
        
        <div class="sikshya-form-row-small">
            <label>Due Date</label>
            <input type="datetime-local" id="assignment-lesson-due-date">
        </div>
    </div>
    
    <div class="sikshya-form-grid-2">
        <div class="sikshya-form-row-small">
            <label>Late Submission</label>
            <select id="assignment-lesson-late">
                <option value="not_allowed">Not Allowed</option>
                <option value="allowed">Allowed with Penalty</option>
                <option value="allowed_no_penalty">Allowed without Penalty</option>
            </select>
        </div>
        
        <div class="sikshya-form-row-small">
            <label>Late Penalty (% per day)</label>
            <input type="number" id="assignment-lesson-late-penalty" placeholder="10" min="0" max="100">
        </div>
    </div>
    
    <div class="sikshya-form-grid-2">
        <div class="sikshya-form-row-small">
            <label>Grace Period (hours)</label>
            <input type="number" id="assignment-lesson-grace" placeholder="24" min="0">
        </div>
        
        <div class="sikshya-form-row-small">
            <label>Require Completion</label>
            <select id="assignment-lesson-completion">
                <option value="yes">Yes</option>
                <option value="no">No</option>
            </select>
        </div>
    </div>
</div>

<div class="sikshya-form-section">
    <h4 class="sikshya-form-section-title">Supporting Materials</h4>
    
    <div class="sikshya-form-row-small">
        <label>Assignment Resources</label>
        <div class="sikshya-upload-area" onclick="document.getElementById('assignment-resources-input').click()">
            <input type="file" id="assignment-resources-input" multiple style="display: none;" onchange="handleResourcesUpload(this)">
            <div class="sikshya-upload-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                </svg>
            </div>
            <strong>Upload Supporting Files</strong>
            <small>Upload files that students need to complete the assignment</small>
        </div>
    </div>
    
    <div class="sikshya-form-row-small">
        <label>External Resources</label>
        <textarea id="assignment-lesson-resources" placeholder="Links to external resources, readings, or references"></textarea>
    </div>
    
    <div class="sikshya-form-row-small">
        <label>Sample Submission</label>
        <div class="sikshya-upload-area" onclick="document.getElementById('assignment-sample-input').click()">
            <input type="file" id="assignment-sample-input" style="display: none;" onchange="handleSampleUpload(this)">
            <div class="sikshya-upload-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <strong>Upload Sample Work</strong>
            <small>Provide an example of what you expect</small>
        </div>
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
            <textarea id="assignment-lesson-prerequisites" placeholder="What should students know before attempting this assignment?"></textarea>
        </div>
        
        <div class="sikshya-form-row-small">
            <label>Tags</label>
            <input type="text" id="assignment-lesson-tags" placeholder="Enter tags separated by commas">
        </div>
    </div>
    
    <div class="sikshya-form-row-small">
        <label>SEO Description</label>
        <textarea id="assignment-lesson-seo" placeholder="SEO-friendly description for search engines"></textarea>
    </div>
    
    <div class="sikshya-form-grid-2">
        <div class="sikshya-form-row-small">
            <label>Peer Review</label>
            <select id="assignment-lesson-peer-review">
                <option value="no">No</option>
                <option value="optional">Optional</option>
                <option value="required">Required</option>
            </select>
        </div>
        
        <div class="sikshya-form-row-small">
            <label>Group Assignment</label>
            <select id="assignment-lesson-group">
                <option value="no">Individual</option>
                <option value="yes">Group Assignment</option>
            </select>
        </div>
    </div>
    
    <div class="sikshya-form-grid-2">
        <div class="sikshya-form-row-small">
            <label>Plagiarism Check</label>
            <select id="assignment-lesson-plagiarism">
                <option value="no">No</option>
                <option value="yes">Yes</option>
            </select>
        </div>
        
        <div class="sikshya-form-row-small">
            <label>Send Notifications</label>
            <select id="assignment-lesson-notifications">
                <option value="yes">Yes</option>
                <option value="no">No</option>
            </select>
        </div>
    </div>
    
    <div class="sikshya-form-grid-2">
        <div class="sikshya-form-row-small">
            <label>Require Draft</label>
            <select id="assignment-lesson-draft">
                <option value="no">No</option>
                <option value="yes">Yes</option>
            </select>
        </div>
        
        <div class="sikshya-form-row-small">
            <label>Allow Comments</label>
            <select id="assignment-lesson-comments">
                <option value="yes">Yes</option>
                <option value="no">No</option>
            </select>
        </div>
    </div>
</div> 