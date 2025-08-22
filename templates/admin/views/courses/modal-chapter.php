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
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
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