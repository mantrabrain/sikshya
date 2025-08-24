<?php
/**
 * Curriculum Tab for Course Builder
 * 
 * @package Sikshya
 * @since 1.0.0
 */

namespace Sikshya\Admin\CourseBuilder\Tabs;

use Sikshya\Admin\CourseBuilder\Core\AbstractTab;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CurriculumTab extends AbstractTab
{
    /**
     * Get the unique identifier for this tab
     * 
     * @return string
     */
    public function getId(): string
    {
        return 'curriculum';
    }
    
    /**
     * Get the display title for this tab
     * 
     * @return string
     */
    public function getTitle(): string
    {
        return __('Curriculum', 'sikshya');
    }
    
    /**
     * Get the description for this tab
     * 
     * @return string
     */
    public function getDescription(): string
    {
        return __('Add lessons, sections, and content', 'sikshya');
    }
    
    /**
     * Get the SVG icon for this tab
     * 
     * @return string
     */
    public function getIcon(): string
    {
        return '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>';
    }
    
    /**
     * Get the tab order
     * 
     * @return int
     */
    public function getOrder(): int
    {
        return 3;
    }
    
    /**
     * Get the fields configuration for this tab
     * 
     * @return array
     */
    public function getFields(): array
    {
        return [
            'curriculum_structure' => [
                'type' => 'curriculum_builder',
                'label' => __('Curriculum Structure', 'sikshya'),
                'description' => __('Build your course curriculum with chapters and lessons', 'sikshya'),
            ],
        ];
    }
    
    /**
     * Render the tab content with exact same HTML markup
     * 
     * @param array $data
     * @return string
     */
    protected function renderContent(array $data): string
    {
        ob_start();
        ?>
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
                            <h3><?php _e('Create Your First Chapter', 'sikshya'); ?></h3>
                            <p><?php _e('Start building your course curriculum with organized chapters and lessons.', 'sikshya'); ?></p>
                        </div>
                    </div>
                    <div class="sikshya-empty-actions">
                        <button class="sikshya-btn sikshya-btn-primary" onclick="showChapterModal()">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" data-chapter-icon="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                            <?php _e('Add Chapter', 'sikshya'); ?>
                        </button>
                    </div>
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
                <?php _e('Add Chapter', 'sikshya'); ?>
            </button>
            
            <!-- Demo Button to Toggle Content -->
            <button class="sikshya-btn sikshya-btn-secondary" onclick="toggleDemoContent()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                <?php _e('Load Sample Chapter', 'sikshya'); ?>
            </button>
            
            <div class="sikshya-action-divider"></div>
            
            <button class="sikshya-btn sikshya-btn-secondary" onclick="importFromTemplate()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                </svg>
                <?php _e('Import from Template', 'sikshya'); ?>
            </button>
            
            <button class="sikshya-btn sikshya-btn-secondary" onclick="bulkImport()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <?php _e('Bulk Import', 'sikshya'); ?>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Override save method for curriculum (handles chapters and lessons)
     * 
     * @param array $data
     * @param int $course_id
     * @return bool
     */
    public function save(array $data, int $course_id): bool
    {
        // Curriculum is handled separately via AJAX
        // This method is called for the main form submission
        return true;
    }
    
    /**
     * Override load method for curriculum
     * 
     * @param int $course_id
     * @return array
     */
    public function load(int $course_id): array
    {
        // Curriculum is loaded via AJAX
        return [];
    }
    
    /**
     * Override validate method for curriculum
     * 
     * @param array $data
     * @return array
     */
    public function validate(array $data): array
    {
        // Curriculum validation is handled via AJAX
        return [];
    }
}
