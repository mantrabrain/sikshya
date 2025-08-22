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

$content_id = $content_id ?? 'content-' . uniqid();
$content_type = $content_type ?? 'text';
$content_title = $content_title ?? '';
$content_duration = $content_duration ?? '';
$content_description = $content_description ?? '';

// Debug logging
error_log('Sikshya Content Item - Content Type: ' . $content_type);
error_log('Sikshya Content Item - Data: ' . print_r(get_defined_vars(), true));

// Create fallback title if empty
$display_title = !empty($content_title) ? $content_title : ucfirst($content_type) . ' Lesson';

// Get SVG icon based on content type - Updated to match the design
$content_svg_icons = [
    'text' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
    'video' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>',
    'audio' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></svg>',
    'quiz' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
    'assignment' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>'
];

// Check if content type exists in our icons array
if (!isset($content_svg_icons[$content_type])) {
    error_log('Sikshya Content Item - Content type not found in icons array: ' . $content_type);
    error_log('Sikshya Content Item - Available types: ' . implode(', ', array_keys($content_svg_icons)));
}

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
    
    <div class="sikshya-lesson-icon sikshya-lesson-type" data-type="<?php echo esc_attr($content_type); ?>">
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