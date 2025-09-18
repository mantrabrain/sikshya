<?php
/**
 * Advanced Text Lesson Form Template with Tabbed Layout
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
            
            <button type="button" class="sikshya-tab-btn" data-tab="media-resources">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span>Media & Resources</span>
            </button>
            
            <button type="button" class="sikshya-tab-btn" data-tab="settings">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span>Settings</span>
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
                    <label>Lesson Title *</label>
                    <input type="text" id="text-lesson-title" name="title" placeholder="Enter lesson title" required>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>Course *</label>
                    <select id="text-lesson-course" name="course_id" required>
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
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Estimated Reading Time (minutes)</label>
                        <input type="number" id="text-lesson-duration" name="duration" placeholder="15" min="1">
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Difficulty Level</label>
                        <select id="text-lesson-difficulty" name="difficulty">
                            <option value="beginner">Beginner</option>
                            <option value="intermediate">Intermediate</option>
                            <option value="advanced">Advanced</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="sikshya-form-section">
                <h4 class="sikshya-form-section-title">Lesson Content</h4>
                
                <div class="sikshya-form-row-small">
                    <label>Lesson Content *</label>
                    <textarea id="text-lesson-content" name="content" placeholder="Enter your lesson content here..." required style="min-height: 400px;"></textarea>
                    <small>You can use HTML formatting for rich text content</small>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>Learning Objectives</label>
                    <textarea id="text-lesson-objectives" name="objectives" placeholder="What will students learn from this lesson? (One objective per line)"></textarea>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>Key Takeaways</label>
                    <textarea id="text-lesson-takeaways" name="takeaways" placeholder="Main points students should remember from this lesson"></textarea>
                </div>
            </div>
        </div>
        
        <!-- Tab 2: Media & Resources -->
        <div class="sikshya-tab-panel" id="media-resources" data-tab="media-resources">
            <div class="sikshya-form-section">
                <h4 class="sikshya-form-section-title">Supporting Materials</h4>
                
                <div class="sikshya-form-row-small">
                    <label>Lesson Images</label>
                    <div class="sikshya-upload-area" onclick="document.getElementById('text-images-input').click()">
                        <input type="file" id="text-images-input" accept="image/*" multiple style="display: none;" onchange="handleImagesUpload(this)">
                        <div class="sikshya-upload-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <strong>Upload Images</strong>
                        <small>JPG, PNG, GIF (Max 5MB each)</small>
                    </div>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>Additional Resources</label>
                    <textarea id="text-lesson-resources" name="resources" placeholder="Links to additional resources, downloads, external readings, etc."></textarea>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>Downloads & Attachments</label>
                    <div class="sikshya-upload-area" onclick="document.getElementById('text-attachments-input').click()">
                        <input type="file" id="text-attachments-input" multiple style="display: none;" onchange="handleAttachmentsUpload(this)">
                        <div class="sikshya-upload-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                            </svg>
                        </div>
                        <strong>Upload Files</strong>
                        <small>PDF, DOC, PPT, etc. (Max 20MB each)</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tab 3: Settings -->
        <div class="sikshya-tab-panel" id="settings" data-tab="settings">
            <div class="sikshya-form-section">
                <h4 class="sikshya-form-section-title">Lesson Settings</h4>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Require Completion</label>
                        <select id="text-lesson-completion" name="completion">
                            <option value="yes">Yes (Mark as read)</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Allow Comments</label>
                        <select id="text-lesson-comments" name="comments">
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                </div>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Show Progress</label>
                        <select id="text-lesson-progress" name="progress">
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Print Friendly</label>
                        <select id="text-lesson-print" name="print">
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
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
                        <textarea id="text-lesson-prerequisites" name="prerequisites" placeholder="What should students know before this lesson?"></textarea>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Tags</label>
                        <input type="text" id="text-lesson-tags" name="tags" placeholder="Enter tags separated by commas">
                    </div>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>SEO Description</label>
                    <textarea id="text-lesson-seo" name="seo" placeholder="SEO-friendly description for search engines"></textarea>
                </div>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Lesson Format</label>
                        <select id="text-lesson-format" name="format">
                            <option value="article">Article</option>
                            <option value="tutorial">Tutorial</option>
                            <option value="guide">Guide</option>
                            <option value="reference">Reference</option>
                            <option value="case_study">Case Study</option>
                        </select>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Reading Level</label>
                        <select id="text-lesson-reading-level" name="reading_level">
                            <option value="basic">Basic</option>
                            <option value="intermediate">Intermediate</option>
                            <option value="advanced">Advanced</option>
                            <option value="expert">Expert</option>
                        </select>
                    </div>
                </div>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Estimated Word Count</label>
                        <input type="number" id="text-lesson-word-count" name="word_count" placeholder="1000" min="1">
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Language</label>
                        <select id="text-lesson-language" name="language">
                            <option value="en">English</option>
                            <option value="es">Spanish</option>
                            <option value="fr">French</option>
                            <option value="de">German</option>
                            <option value="it">Italian</option>
                            <option value="pt">Portuguese</option>
                            <option value="ru">Russian</option>
                            <option value="zh">Chinese</option>
                            <option value="ja">Japanese</option>
                            <option value="ko">Korean</option>
                        </select>
                    </div>
                </div>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Include Table of Contents</label>
                        <select id="text-lesson-toc" name="toc">
                            <option value="auto">Auto-generate</option>
                            <option value="manual">Manual</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Enable Search</label>
                        <select id="text-lesson-search" name="search">
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>Related Lessons</label>
                    <textarea id="text-lesson-related" name="related" placeholder="Enter lesson IDs or titles of related lessons"></textarea>
                </div>
            </div>
        </div>
    </div>
</div> 