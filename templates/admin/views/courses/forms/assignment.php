<?php
/**
 * Advanced Assignment Form Template with Tabbed Layout
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
            
            <button type="button" class="sikshya-tab-btn" data-tab="submission-grading">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                </svg>
                <span>Submission & Grading</span>
            </button>
            
            <button type="button" class="sikshya-tab-btn" data-tab="timeline-settings">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Timeline & Settings</span>
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
                    <label>Assignment Title *</label>
                    <input type="text" id="assignment-lesson-title" name="title" placeholder="Enter assignment title" required>
                </div>
                
                
                <div class="sikshya-form-row-small">
                    <label>Description</label>
                    <textarea id="assignment-lesson-description" name="description" placeholder="Brief description of this assignment"></textarea>
                </div>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Estimated Time (hours)</label>
                        <input type="number" id="assignment-lesson-duration" name="duration" placeholder="2" min="0.5" step="0.5">
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Difficulty Level</label>
                        <select id="assignment-lesson-difficulty" name="difficulty">
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
                    <textarea id="assignment-lesson-instructions" name="instructions" placeholder="Provide detailed instructions for students" required style="min-height: 200px;"></textarea>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>Learning Objectives</label>
                    <textarea id="assignment-lesson-objectives" name="objectives" placeholder="What will students learn from this assignment?"></textarea>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>Success Criteria</label>
                    <textarea id="assignment-lesson-criteria" name="success_criteria" placeholder="What constitutes a successful completion of this assignment?"></textarea>
                </div>
            </div>
        </div>
        
        <!-- Tab 2: Submission & Grading -->
        <div class="sikshya-tab-panel" id="submission-grading" data-tab="submission-grading">
            <div class="sikshya-form-section">
                <h4 class="sikshya-form-section-title">Submission Requirements</h4>
                
                <div class="sikshya-form-row-small">
                    <label>Submission Type</label>
                    <select id="assignment-lesson-submission-type" name="submission_type" onchange="toggleSubmissionOptions()">
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
                        <input type="checkbox" id="file-pdf" name="allowed_files[]" value="pdf">
                        <label for="file-pdf">PDF Documents</label>
                    </div>
                    <div class="sikshya-checkbox-group">
                        <input type="checkbox" id="file-doc" name="allowed_files[]" value="doc">
                        <label for="file-doc">Word Documents (.doc, .docx)</label>
                    </div>
                    <div class="sikshya-checkbox-group">
                        <input type="checkbox" id="file-ppt" name="allowed_files[]" value="ppt">
                        <label for="file-ppt">PowerPoint (.ppt, .pptx)</label>
                    </div>
                    <div class="sikshya-checkbox-group">
                        <input type="checkbox" id="file-excel" name="allowed_files[]" value="excel">
                        <label for="file-excel">Excel (.xls, .xlsx)</label>
                    </div>
                    <div class="sikshya-checkbox-group">
                        <input type="checkbox" id="file-image" name="allowed_files[]" value="image">
                        <label for="file-image">Images (.jpg, .png, .gif)</label>
                    </div>
                    <div class="sikshya-checkbox-group">
                        <input type="checkbox" id="file-video" name="allowed_files[]" value="video">
                        <label for="file-video">Videos (.mp4, .mov, .avi)</label>
                    </div>
                    <div class="sikshya-checkbox-group">
                        <input type="checkbox" id="file-audio" name="allowed_files[]" value="audio">
                        <label for="file-audio">Audio (.mp3, .wav)</label>
                    </div>
                    <div class="sikshya-checkbox-group">
                        <input type="checkbox" id="file-zip" name="allowed_files[]" value="zip">
                        <label for="file-zip">Compressed Files (.zip, .rar)</label>
                    </div>
                </div>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Maximum File Size (MB)</label>
                        <input type="number" id="assignment-lesson-file-size" name="max_file_size" placeholder="10" min="1" max="100">
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Number of Files Allowed</label>
                        <input type="number" id="assignment-lesson-file-count" name="max_files" placeholder="1" min="1" max="10">
                    </div>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>Text Entry Word Limit</label>
                    <input type="number" id="assignment-lesson-word-limit" name="word_limit" placeholder="500" min="1">
                    <small>Leave empty for no limit</small>
                </div>
            </div>
            
            <div class="sikshya-form-section">
                <h4 class="sikshya-form-section-title">Grading & Assessment</h4>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Grading Method</label>
                        <select id="assignment-lesson-grading" name="grading_method">
                            <option value="points">Points (0-100)</option>
                            <option value="letter">Letter Grade (A-F)</option>
                            <option value="percentage">Percentage</option>
                            <option value="pass_fail">Pass/Fail</option>
                            <option value="rubric">Rubric</option>
                        </select>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Maximum Points</label>
                        <input type="number" id="assignment-lesson-points" name="max_points" placeholder="100" min="1">
                    </div>
                </div>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Passing Score (%)</label>
                        <input type="number" id="assignment-lesson-passing" name="passing_score" placeholder="70" min="0" max="100">
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Allow Resubmission</label>
                        <select id="assignment-lesson-resubmit" name="allow_resubmission">
                            <option value="no">No</option>
                            <option value="yes">Yes</option>
                            <option value="until_passed">Until Passed</option>
                        </select>
                    </div>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>Rubric Criteria</label>
                    <textarea id="assignment-lesson-rubric" name="rubric_criteria" placeholder="Define grading criteria and point values for each criterion"></textarea>
                    <small>Format: Criterion | Points | Description</small>
                </div>
            </div>
        </div>
        
        <!-- Tab 3: Timeline & Settings -->
        <div class="sikshya-tab-panel" id="timeline-settings" data-tab="timeline-settings">
            <div class="sikshya-form-section">
                <h4 class="sikshya-form-section-title">Timeline & Availability</h4>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Available From</label>
                        <input type="datetime-local" id="assignment-lesson-available-from" name="available_from">
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Due Date</label>
                        <input type="datetime-local" id="assignment-lesson-due-date" name="due_date">
                    </div>
                </div>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Late Submission</label>
                        <select id="assignment-lesson-late" name="late_submission">
                            <option value="not_allowed">Not Allowed</option>
                            <option value="allowed">Allowed with Penalty</option>
                            <option value="allowed_no_penalty">Allowed without Penalty</option>
                        </select>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Late Penalty (% per day)</label>
                        <input type="number" id="assignment-lesson-late-penalty" name="late_penalty" placeholder="10" min="0" max="100">
                    </div>
                </div>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Grace Period (hours)</label>
                        <input type="number" id="assignment-lesson-grace" name="grace_period" placeholder="24" min="0">
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Require Completion</label>
                        <select id="assignment-lesson-completion" name="require_completion">
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
                        <input type="file" id="assignment-resources-input" name="resources[]" multiple style="display: none;" onchange="handleResourcesUpload(this)">
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
                    <textarea id="assignment-lesson-resources" name="external_resources" placeholder="Links to external resources, readings, or references"></textarea>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>Sample Submission</label>
                    <div class="sikshya-upload-area" onclick="document.getElementById('assignment-sample-input').click()">
                        <input type="file" id="assignment-sample-input" name="sample_submission" style="display: none;" onchange="handleSampleUpload(this)">
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
        </div>
        
        <!-- Tab 4: Advanced -->
        <div class="sikshya-tab-panel" id="advanced" data-tab="advanced">
            <div class="sikshya-form-section">
                <h4 class="sikshya-form-section-title">Advanced Options</h4>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Prerequisites</label>
                        <textarea id="assignment-lesson-prerequisites" name="prerequisites" placeholder="What should students know before attempting this assignment?"></textarea>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Tags</label>
                        <input type="text" id="assignment-lesson-tags" name="tags" placeholder="Enter tags separated by commas">
                    </div>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>SEO Description</label>
                    <textarea id="assignment-lesson-seo" name="seo_description" placeholder="SEO-friendly description for search engines"></textarea>
                </div>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Access Level</label>
                        <select id="assignment-lesson-access" name="access_level">
                            <option value="public">Public</option>
                            <option value="private">Private</option>
                            <option value="premium">Premium Only</option>
                        </select>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Publish Status</label>
                        <select id="assignment-lesson-status" name="status">
                            <option value="draft">Draft</option>
                            <option value="publish">Published</option>
                            <option value="private">Private</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="sikshya-form-section">
                <h4 class="sikshya-form-section-title">Collaboration & Review</h4>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Peer Review</label>
                        <select id="assignment-lesson-peer-review" name="peer_review">
                            <option value="no">No</option>
                            <option value="optional">Optional</option>
                            <option value="required">Required</option>
                        </select>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Group Assignment</label>
                        <select id="assignment-lesson-group" name="group_assignment">
                            <option value="no">Individual</option>
                            <option value="yes">Group Assignment</option>
                        </select>
                    </div>
                </div>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Plagiarism Check</label>
                        <select id="assignment-lesson-plagiarism" name="plagiarism_check">
                            <option value="no">No</option>
                            <option value="yes">Yes</option>
                        </select>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Send Notifications</label>
                        <select id="assignment-lesson-notifications" name="send_notifications">
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                </div>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Require Draft</label>
                        <select id="assignment-lesson-draft" name="require_draft">
                            <option value="no">No</option>
                            <option value="yes">Yes</option>
                        </select>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Allow Comments</label>
                        <select id="assignment-lesson-comments" name="allow_comments">
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Submission type toggle functionality
function toggleSubmissionOptions() {
    const type = document.getElementById('assignment-lesson-submission-type').value;
    const fileOptions = document.getElementById('file-upload-options');
    
    if (type === 'file' || type === 'multiple') {
        fileOptions.style.display = 'block';
    } else {
        fileOptions.style.display = 'none';
    }
}

// Resources upload handler
function handleResourcesUpload(input) {
    const files = input.files;
    if (files.length > 0) {
        console.log('Resources files selected:', files.length);
        // Add upload logic here
    }
}

// Sample upload handler
function handleSampleUpload(input) {
    const file = input.files[0];
    if (file) {
        console.log('Sample file selected:', file.name);
        // Add upload logic here
    }
}
</script>