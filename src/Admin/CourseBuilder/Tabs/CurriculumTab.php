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
            'curriculum' => [
                'section' => [
                    'description' => __('Organize your course content with chapters and lessons', 'sikshya'),

                    'title' => __('Course Curriculum', 'sikshya'),
                    'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>',
                ],
                'fields' => [
                    'curriculum_structure' => [
                        'type' => 'curriculum_builder',
                        'validation' => 'array',
                        'sanitization' => 'sanitize_text_field',
                    ],
                ],
            ],
        ];
    }
    
    /**
     * Render the tab content with curriculum data loaded directly
     * 
     * @param array $data
     * @return string
     */
    protected function renderContent(array $data): string
    {
        error_log('Sikshya: renderContent called with data: ' . print_r($data, true));
        
        $course_id = $data['course_id'] ?? 0;
        error_log('Sikshya: Extracted course_id: ' . $course_id);
        
        $curriculum_html = $this->loadCurriculumHTML($course_id);
        
        ob_start();
        ?>
        
        <!-- Bulk Actions Toolbar (Always visible at top) -->
        <div class="sikshya-bulk-actions-toolbar" id="bulk-actions-toolbar">
            <div class="sikshya-bulk-actions-left">
                <div class="sikshya-select-all-checkbox">
                    <input type="checkbox" id="select-all-chapters" class="sikshya-checkbox sikshya-select-all">
                    <label for="select-all-chapters">Select All Chapters</label>
                </div>
                <span class="sikshya-selected-count">
                    <span id="selected-count">0</span> items selected
                </span>
            </div>
            <div class="sikshya-bulk-actions-right">
                <button class="sikshya-btn sikshya-btn-secondary sikshya-btn-sm" id="expand-collapse-btn" data-action="toggle-expand-collapse">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                    </svg>
                    <span id="expand-collapse-text">Expand All</span>
                </button>
                <button class="sikshya-btn sikshya-btn-danger sikshya-btn-sm" id="bulk-delete-btn" data-action="bulk-delete">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Delete Selected
                </button>
                <button class="sikshya-btn sikshya-btn-secondary sikshya-btn-sm" data-action="clear-selection">
                    Clear Selection
                </button>
            </div>
        </div>
        
        <div class="sikshya-curriculum-builder">
            <?php echo $curriculum_html; ?>
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
     * Load curriculum HTML directly from database
     * 
     * @param int $course_id
     * @return string
     */
    private function loadCurriculumHTML(int $course_id): string
    {
        error_log('Sikshya: loadCurriculumHTML called with course_id: ' . $course_id);
        
        if ($course_id <= 0) {
            error_log('Sikshya: course_id is 0 or negative, returning empty state');
            return $this->getEmptyCurriculumHTML();
        }
        
        // Get all chapters for this course
        $args = [
            'post_type' => \Sikshya\Constants\PostTypes::CHAPTER,
            'post_parent' => $course_id,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'meta_value_num',
            'meta_key' => '_sikshya_order',
            'order' => 'ASC'
        ];
        
        error_log('Sikshya: Query args: ' . print_r($args, true));
        
        $chapters = get_posts($args);
        
        error_log('Sikshya: Found ' . count($chapters) . ' chapters for course_id: ' . $course_id);
        
        if (empty($chapters)) {
            error_log('Sikshya: No chapters found, returning empty state');
            return $this->getEmptyCurriculumHTML();
        }
        
        ob_start();
        ?>
        <div class="sikshya-curriculum-items" id="curriculum-items">
            <?php
            foreach ($chapters as $chapter) {
                $chapter_id = $chapter->ID;
                $chapter_title = $chapter->post_title;
                $chapter_description = $chapter->post_content;
                $chapter_order = get_post_meta($chapter_id, '_sikshya_order', true) ?: 1;
                $chapter_duration = get_post_meta($chapter_id, '_sikshya_duration', true);
                
                // Get content counts
                $chapter_contents = get_post_meta($chapter_id, '_sikshya_contents', true);
                if (!is_array($chapter_contents)) {
                    $chapter_contents = [];
                }
                
                $lesson_count = 0;
                $quiz_count = 0;
                $assignment_count = 0;
                
                foreach ($chapter_contents as $content_id) {
                    $content_post_type = get_post_type($content_id);
                    switch ($content_post_type) {
                        case \Sikshya\Constants\PostTypes::LESSON:
                            $lesson_count++;
                            break;
                        case \Sikshya\Constants\PostTypes::QUIZ:
                            $quiz_count++;
                            break;
                        case \Sikshya\Constants\PostTypes::ASSIGNMENT:
                            $assignment_count++;
                            break;
                    }
                }
                
                echo $this->generateChapterHTML($chapter_id, $chapter_title, $chapter_description, $chapter_duration, $chapter_order, $lesson_count, $quiz_count, $assignment_count, $chapter_contents);
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Generate empty curriculum HTML
     * 
     * @return string
     */
    private function getEmptyCurriculumHTML(): string
    {
        ob_start();
        ?>
        <div class="sikshya-curriculum-empty-state" id="curriculum-empty-state">
            <div class="sikshya-empty-header">
                <div class="sikshya-empty-content">
                    <div class="sikshya-empty-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                        <?php _e('Add Chapter', 'sikshya'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Generate chapter HTML
     * 
     * @param int $chapter_id
     * @param string $title
     * @param string $description
     * @param string $duration
     * @param int $order
     * @param int $lesson_count
     * @param int $quiz_count
     * @param int $assignment_count
     * @param array $chapter_contents
     * @return string
     */
    private function generateChapterHTML(int $chapter_id, string $title, string $description, string $duration, int $order, int $lesson_count, int $quiz_count, int $assignment_count, array $chapter_contents): string
    {
        ob_start();
        ?>
        <div class="sikshya-chapter-card" id="chapter-<?php echo esc_attr($chapter_id); ?>" 
             data-chapter-id="chapter-<?php echo esc_attr($chapter_id); ?>"
             data-description="<?php echo esc_attr($description); ?>"
             data-duration="<?php echo esc_attr($duration); ?>"
             data-order="<?php echo esc_attr($order); ?>" draggable="true">
            
            <div class="sikshya-chapter-header">
                <div class="sikshya-sortable-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <circle cx="8" cy="6" r="1.5"></circle>
                        <circle cx="16" cy="6" r="1.5"></circle>
                        <circle cx="8" cy="12" r="1.5"></circle>
                        <circle cx="16" cy="12" r="1.5"></circle>
                        <circle cx="8" cy="18" r="1.5"></circle>
                        <circle cx="16" cy="18" r="1.5"></circle>
                    </svg>
                </div>
                <div class="sikshya-chapter-controls">
                    <div class="sikshya-chapter-checkbox">
                        <input type="checkbox" id="chapter-<?php echo esc_attr($chapter_id); ?>" class="sikshya-checkbox">
                        <label for="chapter-<?php echo esc_attr($chapter_id); ?>"></label>
                    </div>
                    <div class="sikshya-chapter-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                    </div>
                    <div class="sikshya-chapter-number"><?php echo esc_html($order); ?></div>
                </div>
                
                <div class="sikshya-chapter-info">
                    <div class="sikshya-chapter-main">
                        <h4 class="sikshya-chapter-title"><?php echo esc_html($title); ?></h4>
                        <div class="sikshya-chapter-content-summary">
                            <div class="sikshya-chapter-meta">
                                <span class="sikshya-chapter-lessons"><span class="lesson-count"><?php echo esc_html($lesson_count); ?></span> lessons</span>
                                <span class="sikshya-chapter-quizzes"><span class="quiz-count"><?php echo esc_html($quiz_count); ?></span> quizzes</span>
                                <span class="sikshya-chapter-assignments"><span class="assignment-count"><?php echo esc_html($assignment_count); ?></span> assignments</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="sikshya-chapter-actions">
                    <button class="sikshya-btn-icon" onclick="event.stopPropagation(); editChapter('chapter-<?php echo esc_attr($chapter_id); ?>')" title="Edit Chapter">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                    <button class="sikshya-btn-icon" onclick="event.stopPropagation(); deleteChapter('chapter-<?php echo esc_attr($chapter_id); ?>')" title="Delete Chapter">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                    <button class="sikshya-btn-icon sikshya-chapter-toggle" onclick="toggleChapter('chapter-<?php echo esc_attr($chapter_id); ?>')" title="Toggle Chapter">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="sikshya-chapter-content" id="content-chapter-<?php echo esc_attr($chapter_id); ?>">
                <div class="sikshya-chapter-content-inner">
                    <div class="sikshya-lesson-list">
                        <?php if ($lesson_count + $quiz_count + $assignment_count > 0): ?>
                            <?php
                            // Display content items
                            foreach ($chapter_contents as $content_id) {
                                $content_post = get_post($content_id);
                                if ($content_post) {
                                    $content_title = $content_post->post_title;
                                    $content_description = $content_post->post_content;
                                    $content_duration = get_post_meta($content_id, '_sikshya_duration', true);
                                    $content_type = str_replace('sik_', '', $content_post->post_type);
                                    
                                    echo $this->generateContentHTML($content_id, $content_title, $content_description, $content_duration, $content_type);
                                }
                            }
                            ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Add More Content -->
                    <div class="sikshya-add-lesson">
                        <button class="sikshya-add-lesson-btn" onclick="addContent('chapter-<?php echo esc_attr($chapter_id); ?>')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add Content
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Generate content HTML
     * 
     * @param int $content_id
     * @param string $title
     * @param string $description
     * @param string $duration
     * @param string $content_type
     * @return string
     */
    private function generateContentHTML(int $content_id, string $title, string $description, string $duration, string $content_type): string
    {
        ob_start();
        ?>
        <div class="sikshya-lesson-item" id="content-<?php echo esc_attr($content_id); ?>" data-content-id="<?php echo esc_attr($content_id); ?>">
            <div class="sikshya-lesson-header">
                <div class="sikshya-lesson-controls">
                    <div class="sikshya-lesson-checkbox">
                        <input type="checkbox" id="content-<?php echo esc_attr($content_id); ?>" class="sikshya-checkbox">
                        <label for="content-<?php echo esc_attr($content_id); ?>"></label>
                    </div>
                    <div class="sikshya-lesson-icon">
                        <?php echo $this->getContentTypeIcon($content_type); ?>
                    </div>
                </div>
                
                <div class="sikshya-lesson-info">
                    <div class="sikshya-lesson-main">
                        <h5 class="sikshya-lesson-title"><?php echo esc_html($title); ?></h5>
                        <?php if (!empty($description)): ?>
                            <p class="sikshya-lesson-description"><?php echo esc_html($description); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="sikshya-content-actions">
                    <button class="sikshya-btn-icon" onclick="editContent(<?php echo esc_attr($content_id); ?>)" title="Edit Content">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                    <button class="sikshya-btn-icon" onclick="deleteContent(<?php echo esc_attr($content_id); ?>)" title="Delete Content">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get content type icon
     * 
     * @param string $content_type
     * @return string
     */
    private function getContentTypeIcon(string $content_type): string
    {
        $icons = [
            'lesson' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>',
            'quiz' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
            'assignment' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
        ];
        
        return $icons[$content_type] ?? $icons['lesson'];
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
