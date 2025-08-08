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
            <button class="sikshya-modal-close" onclick="closeModal(this)">×</button>
            <h3 class="sikshya-modal-title">
                <i class="fas fa-folder-plus"></i>
                Add New Chapter
            </h3>
            <p class="sikshya-modal-subtitle">Create a new chapter to organize your course content</p>
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
            <button class="sikshya-btn" onclick="closeModal(this)">Cancel</button>
            <button class="sikshya-btn sikshya-btn-primary" onclick="saveChapter()">Create Chapter</button>
        </div>
    </div>
</div> 