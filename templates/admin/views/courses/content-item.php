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

// Create fallback title if empty
$display_title = !empty($content_title) ? $content_title : ucfirst($content_type) . ' Lesson';

// Get SVG icon based on content type
$content_svg_icons = [
    'text' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>',
    'video' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>',
    'audio' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>',
    'quiz' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
    'assignment' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>'
];

$svg_icon = $content_svg_icons[$content_type] ?? $content_svg_icons['text'];
?>

<div class="sikshya-lesson-card" id="<?php echo esc_attr($content_id); ?>" 
     data-type="<?php echo esc_attr($content_type); ?>"
     data-duration="<?php echo esc_attr($content_duration); ?>"
     data-description="<?php echo esc_attr($content_description); ?>">
    
    <div class="sikshya-lesson-checkbox">
        <input type="checkbox" id="content-<?php echo esc_attr($content_id); ?>" class="sikshya-checkbox">
        <label for="content-<?php echo esc_attr($content_id); ?>"></label>
    </div>
    
    <div class="sikshya-lesson-icon sikshya-lesson-type">
        <?php echo $svg_icon; ?>
    </div>
    
    <div class="sikshya-lesson-content">
        <h5 class="sikshya-lesson-title"><?php echo esc_html($display_title); ?></h5>
        <?php if (!empty($content_description)): ?>
            <p class="sikshya-lesson-description"><?php echo esc_html($content_description); ?></p>
        <?php endif; ?>
    </div>
    
    <div class="sikshya-lesson-duration">
        <?php echo !empty($content_duration) ? esc_html($content_duration) . ' min' : '5 min'; ?>
    </div>
    
    <div class="sikshya-lesson-actions">
        <button class="sikshya-btn-icon" onclick="editContentModal('<?php echo esc_attr($content_id); ?>', '<?php echo esc_attr($content_type); ?>')" title="Edit Content">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
        </button>
        <button class="sikshya-btn-icon" onclick="deleteContent('<?php echo esc_attr($content_id); ?>')" title="Delete Content">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
        </button>
    </div>
</div> 