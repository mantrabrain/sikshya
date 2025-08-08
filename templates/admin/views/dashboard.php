<?php
/**
 * Dashboard Template
 *
 * @package Sikshya\Admin\Views
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="sikshya-admin">
    <div class="sikshya-container">
        <!-- Header -->
        <div class="sikshya-header">
            <div class="sikshya-header-content">
                <div>
                    <h1 class="sikshya-header-title"><?php echo esc_html($config['title']); ?></h1>
                    <?php if (!empty($config['description'])): ?>
                        <p class="sikshya-header-description"><?php echo esc_html($config['description']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="sikshya-header-actions">
                    <a href="<?php echo admin_url('admin.php?page=sikshya-add-course'); ?>" class="sikshya-btn sikshya-btn-primary">
                        <?php _e('Add Course', 'sikshya'); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="sikshya-grid sikshya-grid-cols-<?php echo esc_attr($config['columns']); ?>">
            <?php foreach ($widgets as $widget_id => $widget): ?>
                <div class="sikshya-card sikshya-widget sikshya-widget-<?php echo esc_attr($widget['type']); ?>">
                    <div class="sikshya-card-header">
                        <h3 class="sikshya-card-title"><?php echo esc_html($widget['title']); ?></h3>
                    </div>
                    <div class="sikshya-card-body">
                        <?php echo $widget['content']; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Welcome Section -->
        <?php if ($config['show_welcome']): ?>
            <div class="sikshya-card sikshya-mt-6">
                <div class="sikshya-card-body">
                    <div class="sikshya-welcome">
                        <h2><?php _e('Welcome to Sikshya LMS!', 'sikshya'); ?></h2>
                        <p><?php _e('Get started by creating your first course or exploring the features below.', 'sikshya'); ?></p>
                        <div class="sikshya-welcome-actions">
                            <a href="<?php echo admin_url('admin.php?page=sikshya-add-course'); ?>" class="sikshya-btn sikshya-btn-primary">
                                <?php _e('Create Your First Course', 'sikshya'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=sikshya-settings'); ?>" class="sikshya-btn sikshya-btn-secondary">
                                <?php _e('Configure Settings', 'sikshya'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Dashboard-specific styles */
.sikshya-widget {
    min-height: 200px;
}

.sikshya-widget-stats .sikshya-stat-card {
    text-align: center;
    padding: var(--sikshya-spacing-4);
    background: var(--sikshya-gray-50);
    border-radius: var(--sikshya-radius-md);
    border: 1px solid var(--sikshya-gray-200);
}

.sikshya-stat-number {
    font-size: var(--sikshya-font-size-2xl);
    font-weight: 700;
    color: var(--sikshya-primary);
    margin-bottom: var(--sikshya-spacing-1);
}

.sikshya-stat-label {
    font-size: var(--sikshya-font-size-sm);
    color: var(--sikshya-gray-600);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.sikshya-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.sikshya-list-item {
    padding: var(--sikshya-spacing-2) 0;
    border-bottom: 1px solid var(--sikshya-gray-100);
}

.sikshya-list-item:last-child {
    border-bottom: none;
}

.sikshya-link {
    color: var(--sikshya-primary);
    text-decoration: none;
    font-weight: 500;
}

.sikshya-link:hover {
    text-decoration: underline;
}

.sikshya-quick-actions {
    display: flex;
    flex-direction: column;
    gap: var(--sikshya-spacing-2);
}

.sikshya-welcome {
    text-align: center;
    padding: var(--sikshya-spacing-8) 0;
}

.sikshya-welcome h2 {
    font-size: var(--sikshya-font-size-2xl);
    margin-bottom: var(--sikshya-spacing-4);
    color: var(--sikshya-gray-900);
}

.sikshya-welcome p {
    font-size: var(--sikshya-font-size-lg);
    color: var(--sikshya-gray-600);
    margin-bottom: var(--sikshya-spacing-6);
}

.sikshya-welcome-actions {
    display: flex;
    gap: var(--sikshya-spacing-4);
    justify-content: center;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .sikshya-welcome-actions {
        flex-direction: column;
        align-items: center;
    }
}
</style> 