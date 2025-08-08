<?php
/**
 * Test Template
 *
 * @package Sikshya\Admin\Views
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div style="padding: 20px; background: #f0f0f0; border: 2px solid #333;">
    <h1>Test Template Works!</h1>
    <p>If you can see this, the template rendering is working.</p>
    <p>Plugin: <?php echo esc_html($plugin->version ?? 'Unknown'); ?></p>
    <p>Time: <?php echo esc_html(date('Y-m-d H:i:s')); ?></p>
</div> 