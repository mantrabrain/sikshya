<?php
/**
 * Content Item Template for Course Builder
 * 
 * @package Sikshya
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$content_id = $args['content_id'] ?? 'content-' . uniqid();
$content_type = $args['content_type'] ?? 'text';
$content_title = $args['content_title'] ?? '';
$content_duration = $args['content_duration'] ?? '';
$content_description = $args['content_description'] ?? '';

// Get icon and color based on content type
$content_icons = [
    'text' => 'fas fa-file-alt',
    'video' => 'fas fa-video',
    'audio' => 'fas fa-headphones',
    'quiz' => 'fas fa-question-circle',
    'assignment' => 'fas fa-tasks'
];

$content_colors = [
    'text' => '#e74c3c',
    'video' => '#e67e22',
    'audio' => '#f39c12',
    'quiz' => '#9b59b6',
    'assignment' => '#27ae60'
];

$icon = $content_icons[$content_type] ?? 'fas fa-file';
$color = $content_colors[$content_type] ?? '#3498db';

// Create fallback title if empty
$display_title = !empty($content_title) ? $content_title : ucfirst($content_type) . ' Lesson';
?>

<div class="sikshya-lesson-item" id="<?php echo esc_attr($content_id); ?>" 
     data-type="<?php echo esc_attr($content_type); ?>"
     data-duration="<?php echo esc_attr($content_duration); ?>"
     data-description="<?php echo esc_attr($content_description); ?>">
    
    <div class="sikshya-lesson-header">
        <div class="sikshya-lesson-title">
            <i class="<?php echo esc_attr($icon); ?>" style="color: <?php echo esc_attr($color); ?>;"></i>
            <span class="sikshya-lesson-title-text"><?php echo esc_html($display_title); ?></span>
        </div>
        
        <div class="sikshya-lesson-actions">
            <?php if (!empty($content_duration)): ?>
                <span class="sikshya-lesson-duration"><?php echo esc_html($content_duration); ?> min</span>
            <?php endif; ?>
            
            <button class="sikshya-icon-btn" onclick="editContentModal('<?php echo esc_attr($content_id); ?>', '<?php echo esc_attr($content_type); ?>')" title="Edit Content">
                <i class="fas fa-edit"></i>
            </button>
            
            <button class="sikshya-icon-btn" onclick="deleteContent(this)" title="Delete Content">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </div>
    
    <?php if (!empty($content_description)): ?>
        <div class="sikshya-lesson-description">
            <?php echo esc_html($content_description); ?>
        </div>
    <?php endif; ?>
</div> 