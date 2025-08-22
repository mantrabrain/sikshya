<?php
/**
 * Course Curriculum Form Template
 * 
 * @package Sikshya
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Curriculum Tab -->
<div class="sikshya-tab-content <?php echo ($active_tab === 'curriculum') ? 'active' : ''; ?>" id="curriculum">
    <!-- Curriculum Content -->
    <div class="sikshya-curriculum-builder" id="curriculum-content">
        <!-- Compact Empty State -->
        <div class="sikshya-curriculum-empty-state" id="curriculum-empty-state">
            <!-- Header with Inline Actions -->
            <div class="sikshya-empty-header">
                <div class="sikshya-empty-content">
                    <div class="sikshya-empty-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" data-chapter-icon="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                    </div>
                    <div class="sikshya-empty-text">
                        <h3>Create Your First Chapter</h3>
                        <p>Start building your course curriculum with organized chapters and lessons.</p>
                    </div>
                </div>
                <div class="sikshya-empty-actions">
                    <button class="sikshya-btn sikshya-btn-primary" onclick="showChapterModal()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" data-chapter-icon="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                        Add Chapter
                    </button>
                </div>
            </div>
            
            <!-- Tips Strip -->
            <div class="sikshya-tips-strip">
                <div class="sikshya-tip-item">
                    <strong>💡 Pro Tip:</strong> Organize content into logical chapters for better learning flow
                </div>
                <div class="sikshya-tip-item">
                    <strong>📚 Best Practice:</strong> Keep lessons between 5-15 minutes for optimal engagement
                </div>
                <a href="#" class="sikshya-help-link">View Course Building Guide →</a>
            </div>
        </div>
        
        <!-- Existing Curriculum Structure (Hidden when empty) -->
        <div class="sikshya-curriculum-items" id="curriculum-items" style="display: none;">
            <?php
            // Sample chapter for demo (when not empty)
            // This will be dynamically populated via AJAX using chapter.php template
            ?>
        </div>
    </div>

    <!-- Curriculum Actions -->
    <div class="sikshya-curriculum-actions">
        <button class="sikshya-btn sikshya-btn-primary" onclick="showChapterModal()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" data-chapter-icon="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
            </svg>
            Add Chapter
        </button>
        
        <!-- Demo Button to Toggle Content -->
        <button class="sikshya-btn sikshya-btn-secondary" onclick="toggleDemoContent()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
            Load Sample Chapter
        </button>
        
        <div class="sikshya-action-divider"></div>
        
        <button class="sikshya-btn sikshya-btn-secondary" onclick="importFromTemplate()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
            </svg>
            Import from Template
        </button>
        
        <button class="sikshya-btn sikshya-btn-secondary" onclick="bulkImport()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Bulk Import
        </button>
        

    </div>
</div>

