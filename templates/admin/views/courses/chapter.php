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

<div class="sikshya-chapter" id="<?php echo esc_attr($chapter_id); ?>" 
     data-description="<?php echo esc_attr($chapter_description); ?>"
     data-duration="<?php echo esc_attr($chapter_duration); ?>"
     data-order="<?php echo esc_attr($chapter_order); ?>">
    
    <div class="sikshya-chapter-header" onclick="toggleChapter('<?php echo esc_attr($chapter_id); ?>')">
        <div class="sikshya-chapter-title">
            <i class="sikshya-chapter-icon fas fa-folder"></i>
            <?php echo esc_html($chapter_title); ?>
        </div>
        
        <div class="sikshya-chapter-info">
            <span><?php echo esc_html($content_count); ?> content items</span>
            <?php if (!empty($chapter_duration)): ?>
                <span><?php echo esc_html($chapter_duration); ?> hours</span>
            <?php endif; ?>
        </div>
        
        <div class="sikshya-chapter-actions">
            <button class="sikshya-chapter-btn" onclick="event.stopPropagation(); addContentToChapter('<?php echo esc_attr($chapter_id); ?>')" title="Add Content">
                <i class="fas fa-plus"></i>
            </button>
            <button class="sikshya-chapter-btn" onclick="event.stopPropagation(); editChapter('<?php echo esc_attr($chapter_id); ?>')" title="Edit Chapter">
                <i class="fas fa-edit"></i>
            </button>
            <button class="sikshya-chapter-btn" onclick="event.stopPropagation(); deleteChapter('<?php echo esc_attr($chapter_id); ?>')" title="Delete Chapter">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </div>
    
    <div class="sikshya-chapter-content" id="content-<?php echo esc_attr($chapter_id); ?>">
        <div class="sikshya-chapter-content-inner">
            <?php if ($content_count === 0): ?>
                <div class="sikshya-chapter-empty">
                    <i class="fas fa-plus-circle"></i>
                    <h4>No content yet</h4>
                    <p>Add your first content item to this chapter</p>
                </div>
            <?php endif; ?>
        </div>
        
        <button class="sikshya-add-lesson-btn" onclick="addContentToChapter('<?php echo esc_attr($chapter_id); ?>')">
            <i class="fas fa-plus"></i>
            Add New Content
        </button>
    </div>
</div> 