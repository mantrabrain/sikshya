<?php
/**
 * Chapter Modal Template for Course Builder
 * 
 * @package Sikshya
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$chapter_order = $args['chapter_order'] ?? 1;
?>

<div class="sikshya-modal-overlay">
    <div class="sikshya-modal">
        <div class="sikshya-modal-header">
            <button class="sikshya-modal-close" onclick="closeModal(this)">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
            <div class="sikshya-modal-header-content">
                <div class="sikshya-modal-title-wrapper">
                    <div class="sikshya-modal-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                    <h3 class="sikshya-modal-title">Add New Chapter</h3>
                </div>
                <p class="sikshya-modal-subtitle">Create a new chapter to organize your course content</p>
            </div>
        </div>
        <div class="sikshya-modal-body">
            <div class="sikshya-form-section">
                <h4 class="sikshya-form-section-title">Chapter Information</h4>
                
                <div class="sikshya-form-row-small">
                    <label>Chapter Title *</label>
                    <input type="text" id="chapter-title" placeholder="Enter chapter title" required>
                </div>
                
                <div class="sikshya-form-row-small">
                    <label>Description</label>
                    <textarea id="chapter-description" placeholder="Brief description of this chapter"></textarea>
                </div>
                
                <div class="sikshya-form-grid-2">
                    <div class="sikshya-form-row-small">
                        <label>Estimated Duration (hours)</label>
                        <input type="number" id="chapter-duration" placeholder="2" min="0.5" step="0.5">
                    </div>
                    
                    <div class="sikshya-form-row-small">
                        <label>Chapter Order</label>
                        <input type="number" id="chapter-order" placeholder="1" min="1" value="<?php echo esc_attr($chapter_order); ?>">
                    </div>
                </div>
            </div>
        </div>
        <div class="sikshya-modal-footer">
            <button class="sikshya-btn sikshya-btn-secondary" onclick="closeModal(this)">Cancel</button>
            <button class="sikshya-btn sikshya-btn-primary" onclick="saveChapter()">Create Chapter</button>
        </div>
    </div>
</div> 