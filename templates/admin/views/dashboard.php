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

<div class="sikshya-dashboard">
    <!-- Header -->
    <div class="sikshya-header">
        <h1><?php echo esc_html($config['title']); ?></h1>
        <a href="<?php echo admin_url('admin.php?page=sikshya-add-course'); ?>" class="sikshya-btn">
            <?php _e('Add Course', 'sikshya'); ?>
        </a>
    </div>

    <!-- Stats -->
    <div class="sikshya-stats">
        <?php foreach ($widgets as $widget_id => $widget): ?>
            <?php if ($widget['type'] === 'stats'): ?>
                <div class="sikshya-stat">
                    <?php echo $widget['content']; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Content -->
    <div class="sikshya-content">
        <?php foreach ($widgets as $widget_id => $widget): ?>
            <?php if ($widget['type'] !== 'stats'): ?>
                <div class="sikshya-card">
                    <h3><?php echo esc_html($widget['title']); ?></h3>
                    <div class="sikshya-card-content">
                        <?php echo $widget['content']; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Quick Actions -->
        <div class="sikshya-card">
            <h3><?php _e('Quick Actions', 'sikshya'); ?></h3>
            <div class="sikshya-card-content">
                <div class="sikshya-actions">
                    <a href="<?php echo admin_url('admin.php?page=sikshya-add-course'); ?>" class="sikshya-action">
                        <?php _e('Add Course', 'sikshya'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=sikshya-lessons'); ?>" class="sikshya-action">
                        <?php _e('Add Lesson', 'sikshya'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=sikshya-quizzes'); ?>" class="sikshya-action">
                        <?php _e('Add Quiz', 'sikshya'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Welcome -->
    <?php if ($config['show_welcome']): ?>
        <div class="sikshya-welcome">
            <h2><?php _e('Welcome to Sikshya LMS', 'sikshya'); ?></h2>
            <p><?php _e('Get started by creating your first course.', 'sikshya'); ?></p>
            <div class="sikshya-welcome-actions">
                <a href="<?php echo admin_url('admin.php?page=sikshya-add-course'); ?>" class="sikshya-btn">
                    <?php _e('Create Your First Course', 'sikshya'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=sikshya-settings'); ?>" class="sikshya-btn sikshya-btn-secondary">
                    <?php _e('Settings', 'sikshya'); ?>
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Design system is now integrated into admin.css -->

<style>
/* Dashboard-specific styles using the new design system */
.sikshya-dashboard {
    max-width: 1400px;
    margin: 0 auto;
    padding: var(--sikshya-space-8) var(--sikshya-space-6);
    font-family: var(--sikshya-font-family);
    background: var(--sikshya-gray-50);
    min-height: 100vh;
}

/* Header Section */
.sikshya-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: var(--sikshya-space-12);
    padding: 0 0 var(--sikshya-space-6) 0;
    border-bottom: 1px solid var(--sikshya-gray-200);
}

.sikshya-header h1 {
    font-size: var(--sikshya-text-4xl);
    font-weight: var(--sikshya-font-semibold);
    color: var(--sikshya-gray-900);
    margin: 0;
    letter-spacing: -0.02em;
    line-height: var(--sikshya-leading-tight);
}

/* Override default button with design system primary */
.sikshya-header .sikshya-btn {
    background: var(--sikshya-primary);
    color: var(--sikshya-white);
    padding: var(--sikshya-space-4) var(--sikshya-space-6);
    text-decoration: none;
    border-radius: var(--sikshya-radius-lg);
    font-size: var(--sikshya-text-sm);
    font-weight: var(--sikshya-font-semibold);
    transition: all var(--sikshya-transition-fast);
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: var(--sikshya-space-2);
}

.sikshya-header .sikshya-btn:hover {
    background: var(--sikshya-primary-hover);
    transform: translateY(-1px);
    box-shadow: var(--sikshya-shadow-md);
}

/* Stats Grid */
.sikshya-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: var(--sikshya-space-6);
    margin-bottom: var(--sikshya-space-12);
}

.sikshya-stat {
    background: var(--sikshya-white);
    padding: var(--sikshya-space-8) var(--sikshya-space-6);
    border-radius: var(--sikshya-radius-xl);
    border: 1px solid var(--sikshya-gray-200);
    transition: all var(--sikshya-transition-normal);
    position: relative;
    overflow: hidden;
}

.sikshya-stat::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--sikshya-primary);
}

.sikshya-stat:hover {
    transform: translateY(-2px);
    box-shadow: var(--sikshya-shadow-lg);
    border-color: var(--sikshya-gray-300);
}

.sikshya-stat-number {
    font-size: var(--sikshya-text-4xl);
    font-weight: var(--sikshya-font-bold);
    color: var(--sikshya-gray-900);
    margin: 0 0 var(--sikshya-space-2) 0;
    display: block;
    line-height: 1;
}

.sikshya-stat-label {
    font-size: var(--sikshya-text-sm);
    color: var(--sikshya-gray-500);
    font-weight: var(--sikshya-font-medium);
    margin: 0;
}

/* Content Grid */
.sikshya-content {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: var(--sikshya-space-6);
    margin-bottom: var(--sikshya-space-12);
}

.sikshya-card {
    background: var(--sikshya-white);
    border-radius: var(--sikshya-radius-xl);
    border: 1px solid var(--sikshya-gray-200);
    overflow: hidden;
    transition: all var(--sikshya-transition-normal);
}

.sikshya-card:hover {
    box-shadow: var(--sikshya-shadow-md);
    border-color: var(--sikshya-gray-300);
}

.sikshya-card h3 {
    font-size: var(--sikshya-text-xl);
    font-weight: var(--sikshya-font-semibold);
    color: var(--sikshya-gray-900);
    margin: 0;
    padding: var(--sikshya-space-6) var(--sikshya-space-6) var(--sikshya-space-4) var(--sikshya-space-6);
    background: var(--sikshya-gray-50);
    border-bottom: 1px solid var(--sikshya-gray-200);
}

.sikshya-card-content {
    padding: var(--sikshya-space-6);
}

/* Quick Actions */
.sikshya-actions {
    display: flex;
    flex-direction: column;
    gap: var(--sikshya-space-2);
}

.sikshya-action {
    display: flex;
    align-items: center;
    padding: var(--sikshya-space-4) var(--sikshya-space-5);
    background: var(--sikshya-gray-50);
    color: var(--sikshya-gray-600);
    text-decoration: none;
    border-radius: var(--sikshya-radius-lg);
    font-size: var(--sikshya-text-sm);
    font-weight: var(--sikshya-font-medium);
    transition: all var(--sikshya-transition-fast);
    border: 1px solid var(--sikshya-gray-100);
}

.sikshya-action:hover {
    background: var(--sikshya-primary-bg);
    color: var(--sikshya-primary);
    transform: translateX(4px);
    border-color: var(--sikshya-primary-border);
}

/* List Styles */
.sikshya-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.sikshya-list-item {
    padding: var(--sikshya-space-4) 0;
    border-bottom: 1px solid var(--sikshya-gray-200);
    font-size: var(--sikshya-text-sm);
    color: var(--sikshya-gray-600);
    transition: color var(--sikshya-transition-fast);
}

.sikshya-list-item:last-child {
    border-bottom: none;
}

.sikshya-list-item:hover {
    color: var(--sikshya-gray-700);
}

.sikshya-link {
    color: var(--sikshya-primary);
    text-decoration: none;
    font-weight: var(--sikshya-font-medium);
    transition: color var(--sikshya-transition-fast);
}

.sikshya-link:hover {
    color: var(--sikshya-primary-hover);
    text-decoration: underline;
}

/* Welcome Section */
.sikshya-welcome {
    background: var(--sikshya-white);
    text-align: center;
    padding: var(--sikshya-space-16) var(--sikshya-space-12);
    border-radius: var(--sikshya-radius-2xl);
    border: 1px solid var(--sikshya-gray-200);
    position: relative;
}

.sikshya-welcome h2 {
    font-size: var(--sikshya-text-3xl);
    font-weight: var(--sikshya-font-bold);
    color: var(--sikshya-gray-900);
    margin: 0 0 var(--sikshya-space-4) 0;
    letter-spacing: -0.02em;
}

.sikshya-welcome p {
    font-size: var(--sikshya-text-lg);
    color: var(--sikshya-gray-500);
    margin: 0 0 var(--sikshya-space-8) 0;
    line-height: var(--sikshya-leading-relaxed);
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.sikshya-welcome-actions {
    display: flex;
    gap: var(--sikshya-space-4);
    justify-content: center;
    flex-wrap: wrap;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .sikshya-content {
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    }
}

@media (max-width: 768px) {
    .sikshya-dashboard {
        padding: var(--sikshya-space-6) var(--sikshya-space-4);
    }
    
    .sikshya-header {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--sikshya-space-6);
        margin-bottom: var(--sikshya-space-8);
    }
    
    .sikshya-header h1 {
        font-size: var(--sikshya-text-3xl);
    }
    
    .sikshya-stats {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: var(--sikshya-space-4);
        margin-bottom: var(--sikshya-space-8);
    }
    
    .sikshya-stat {
        padding: var(--sikshya-space-6) var(--sikshya-space-5);
    }
    
    .sikshya-stat-number {
        font-size: var(--sikshya-text-3xl);
    }
    
    .sikshya-content {
        grid-template-columns: 1fr;
        gap: var(--sikshya-space-4);
        margin-bottom: var(--sikshya-space-8);
    }
    
    .sikshya-welcome {
        padding: var(--sikshya-space-12) var(--sikshya-space-6);
    }
    
    .sikshya-welcome h2 {
        font-size: var(--sikshya-text-2xl);
    }
    
    .sikshya-welcome-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .sikshya-welcome-actions .sikshya-btn {
        width: 100%;
        max-width: 280px;
    }
}

@media (max-width: 480px) {
    .sikshya-stats {
        grid-template-columns: 1fr;
    }
    
    .sikshya-header h1 {
        font-size: var(--sikshya-text-2xl);
    }
    
    .sikshya-card h3 {
        font-size: var(--sikshya-text-lg);
        padding: var(--sikshya-space-5) var(--sikshya-space-5) var(--sikshya-space-3) var(--sikshya-space-5);
    }
    
    .sikshya-card-content {
        padding: var(--sikshya-space-5);
    }
}
</style> 