<?php
/**
 * Chapter Template for Course Builder
 * 
 * @package Sikshya
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$chapter_id = $args['chapter_id'] ?? 'chapter-' . uniqid();
$chapter_title = $args['chapter_title'] ?? '';
$chapter_description = $args['chapter_description'] ?? '';
$chapter_duration = $args['chapter_duration'] ?? '';
$chapter_order = $args['chapter_order'] ?? '';
$content_count = $args['content_count'] ?? 0;
?>

<div class="sikshya-chapter-card" id="<?php echo esc_attr($chapter_id); ?>" 
     data-chapter-id="<?php echo esc_attr($chapter_id); ?>"
     data-description="<?php echo esc_attr($chapter_description); ?>"
     data-duration="<?php echo esc_attr($chapter_duration); ?>"
     data-order="<?php echo esc_attr($chapter_order); ?>">
    
    <div class="sikshya-chapter-header" onclick="toggleChapter('<?php echo esc_attr($chapter_id); ?>')">
        <div class="sikshya-chapter-drag">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 6h.01M8 10h.01M8 14h.01M8 18h.01M16 6h.01M16 10h.01M16 14h.01M16 18h.01"/>
            </svg>
        </div>
        <div class="sikshya-chapter-info">
            <div class="sikshya-chapter-main">
                <h4 class="sikshya-chapter-title"><?php echo esc_html($chapter_title); ?></h4>
                <?php if (!empty($chapter_description)): ?>
                    <p class="sikshya-chapter-description"><?php echo esc_html($chapter_description); ?></p>
                <?php endif; ?>
            </div>
                                <div class="sikshya-chapter-meta">
                        <span class="sikshya-chapter-lessons">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                            <span class="lesson-count"><?php echo esc_html($content_count); ?></span> lessons
                        </span>
                        <span class="sikshya-chapter-quizzes">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="quiz-count">0</span> quizzes
                        </span>
                        <span class="sikshya-chapter-assignments">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                            </svg>
                            <span class="assignment-count">0</span> assignments
                        </span>
                        <?php if (!empty($chapter_duration)): ?>
                            <span class="sikshya-chapter-duration">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <?php echo esc_html($chapter_duration); ?> min
                            </span>
                        <?php endif; ?>
                        <span class="sikshya-chapter-status" data-status="draft">Draft</span>
                    </div>
        </div>
        <div class="sikshya-chapter-actions">
            <button class="sikshya-btn-icon" onclick="event.stopPropagation(); editChapter('<?php echo esc_attr($chapter_id); ?>')" title="Edit Chapter">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </button>
            <button class="sikshya-btn-icon" onclick="event.stopPropagation(); deleteChapter('<?php echo esc_attr($chapter_id); ?>')" title="Delete Chapter">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </button>
            <button class="sikshya-btn-icon sikshya-chapter-toggle" onclick="event.stopPropagation(); toggleChapter('<?php echo esc_attr($chapter_id); ?>')" title="Toggle Chapter">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
        </div>
    </div>
    
    <div class="sikshya-chapter-content" id="content-<?php echo esc_attr($chapter_id); ?>">
        <div class="sikshya-chapter-content-inner">
            <div class="sikshya-chapter-lessons">
                <?php if ($content_count === 0): ?>
                    <div class="sikshya-chapter-empty">
                        <div class="sikshya-chapter-empty-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                        </div>
                        <h4>No Lessons Yet</h4>
                        <p>Add your first lesson to this chapter</p>
                    </div>
                <?php endif; ?>
                
                <!-- Lesson items will be added here dynamically -->
            </div>
            
            <!-- Add More Content -->
            <div class="sikshya-add-lesson">
                <button class="sikshya-add-lesson-btn" onclick="addLesson('<?php echo esc_attr($chapter_id); ?>')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Lesson
                </button>
            </div>
        </div>
    </div>
</div> 