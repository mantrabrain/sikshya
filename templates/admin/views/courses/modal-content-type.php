<?php
/**
 * Content Type Selection Modal Template for Course Builder
 * 
 * @package Sikshya
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="sikshya-modal-overlay">
    <div class="sikshya-modal">
        <div class="sikshya-modal-header">
            <button class="sikshya-modal-close" onclick="closeModal(this)">×</button>
            <h3 class="sikshya-modal-title">
                <i class="fas fa-plus-circle"></i>
                Add New Content
            </h3>
            <p class="sikshya-modal-subtitle">Choose the type of content you want to add to your course</p>
        </div>
        <div class="sikshya-modal-body">
            <div class="sikshya-content-types">
                <div class="sikshya-content-type sikshya-content-type-text" onclick="selectContentType('text')">
                    <div class="sikshya-content-type-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="sikshya-content-type-title">Text Lesson</div>
                    <div class="sikshya-content-type-desc">Rich text content with images and formatting</div>
                </div>
                
                <div class="sikshya-content-type sikshya-content-type-video" onclick="selectContentType('video')">
                    <div class="sikshya-content-type-icon">
                        <i class="fas fa-video"></i>
                    </div>
                    <div class="sikshya-content-type-title">Video Lesson</div>
                    <div class="sikshya-content-type-desc">Upload video files with descriptions</div>
                </div>
                
                <div class="sikshya-content-type sikshya-content-type-audio" onclick="selectContentType('audio')">
                    <div class="sikshya-content-type-icon">
                        <i class="fas fa-headphones"></i>
                    </div>
                    <div class="sikshya-content-type-title">Audio Lesson</div>
                    <div class="sikshya-content-type-desc">Audio files with transcripts</div>
                </div>
                
                <div class="sikshya-content-type sikshya-content-type-quiz" onclick="selectContentType('quiz')">
                    <div class="sikshya-content-type-icon">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <div class="sikshya-content-type-title">Quiz</div>
                    <div class="sikshya-content-type-desc">Interactive assessments and tests</div>
                </div>
                
                <div class="sikshya-content-type sikshya-content-type-assignment" onclick="selectContentType('assignment')">
                    <div class="sikshya-content-type-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="sikshya-content-type-title">Assignment</div>
                    <div class="sikshya-content-type-desc">Student submissions and projects</div>
                </div>
            </div>
        </div>
        <div class="sikshya-modal-footer">
            <button class="sikshya-btn" onclick="closeModal(this)">Cancel</button>
            <button class="sikshya-btn sikshya-btn-primary" onclick="proceedToContentForm()" disabled>Continue</button>
        </div>
    </div>
</div> 