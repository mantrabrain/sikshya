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
            <button class="sikshya-modal-close">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
            <div class="sikshya-modal-header-content">
                <div class="sikshya-modal-title-wrapper">
                    <div class="sikshya-modal-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                    </div>
                    <h3 class="sikshya-modal-title">Add New Content</h3>
                </div>
                <p class="sikshya-modal-subtitle">Choose the type of content you want to add to your course</p>
            </div>
        </div>
        <div class="sikshya-modal-body">
            <div class="sikshya-content-types">
                <div class="sikshya-content-type sikshya-content-type-text" data-content-type="text">
                    <div class="sikshya-content-type-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div class="sikshya-content-type-title">Text Lesson</div>
                    <div class="sikshya-content-type-desc">Rich text content with images and formatting</div>
                </div>
                
                <div class="sikshya-content-type sikshya-content-type-video" data-content-type="video">
                    <div class="sikshya-content-type-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div class="sikshya-content-type-title">Video Lesson</div>
                    <div class="sikshya-content-type-desc">Upload video files with descriptions</div>
                </div>
                
                <div class="sikshya-content-type sikshya-content-type-audio" data-content-type="audio">
                    <div class="sikshya-content-type-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
                        </svg>
                    </div>
                    <div class="sikshya-content-type-title">Audio Lesson</div>
                    <div class="sikshya-content-type-desc">Audio files with transcripts</div>
                </div>
                
                <div class="sikshya-content-type sikshya-content-type-quiz" data-content-type="quiz">
                    <div class="sikshya-content-type-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="sikshya-content-type-title">Quiz</div>
                    <div class="sikshya-content-type-desc">Interactive assessments and tests</div>
                </div>
                
                <div class="sikshya-content-type sikshya-content-type-assignment" data-content-type="assignment">
                    <div class="sikshya-content-type-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                        </svg>
                    </div>
                    <div class="sikshya-content-type-title">Assignment</div>
                    <div class="sikshya-content-type-desc">Student submissions and projects</div>
                </div>
            </div>
        </div>
        <div class="sikshya-modal-footer">
            <button class="sikshya-btn sikshya-btn-secondary">Cancel</button>
            <button class="sikshya-btn sikshya-btn-primary" disabled>Continue</button>
        </div>
    </div>
</div> 