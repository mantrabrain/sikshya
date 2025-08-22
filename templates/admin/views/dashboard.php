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
        <div class="sikshya-header-title">
            <h1>
                <i class="fas fa-tachometer-alt"></i>
                <?php echo esc_html($config['title']); ?>
            </h1>
            <span class="sikshya-version">v<?php echo esc_html(SIKSHYA_VERSION); ?></span>
        </div>
        <div class="sikshya-header-actions">
            <a href="<?php echo admin_url('admin.php?page=sikshya-add-course'); ?>" class="sikshya-btn sikshya-btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                <?php _e('Add Course', 'sikshya'); ?>
            </a>
        </div>
    </div>

    <div class="sikshya-main-content">
        <!-- Stats Grid -->
        <div class="sikshya-stats-grid">
            <?php foreach ($widgets as $widget_id => $widget): ?>
                <?php if ($widget['type'] === 'stats'): ?>
                    <?php echo $widget['content']; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Content Grid -->
        <div class="sikshya-content-grid">
            <?php foreach ($widgets as $widget_id => $widget): ?>
                <?php if ($widget['type'] !== 'stats'): ?>
                    <div class="sikshya-content-card">
                        <div class="sikshya-content-card-header">
                            <h3 class="sikshya-content-card-title">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <?php echo esc_html($widget['title']); ?>
                            </h3>
                        </div>
                        <div class="sikshya-content-card-body">
                            <?php echo $widget['content']; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <!-- Quick Actions Card -->
            <div class="sikshya-content-card">
                <div class="sikshya-content-card-header">
                    <h3 class="sikshya-content-card-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        <?php _e('Quick Actions', 'sikshya'); ?>
                    </h3>
                    <p class="sikshya-content-card-subtitle"><?php _e('Common tasks and shortcuts', 'sikshya'); ?></p>
                </div>
                <div class="sikshya-content-card-body">
                    <div class="sikshya-quick-actions">
                        <a href="<?php echo admin_url('admin.php?page=sikshya-add-course'); ?>" class="sikshya-quick-action">
                            <svg class="sikshya-quick-action-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                            <?php _e('Add Course', 'sikshya'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=sikshya-lessons'); ?>" class="sikshya-quick-action">
                            <svg class="sikshya-quick-action-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                            <?php _e('Add Lesson', 'sikshya'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=sikshya-quizzes'); ?>" class="sikshya-quick-action">
                            <svg class="sikshya-quick-action-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            <?php _e('Add Quiz', 'sikshya'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=sikshya-students'); ?>" class="sikshya-quick-action">
                            <svg class="sikshya-quick-action-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                            </svg>
                            <?php _e('Manage Students', 'sikshya'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Welcome Section -->
        <?php if ($config['show_welcome']): ?>
            <div class="sikshya-welcome-section">
                <div class="sikshya-welcome-content">
                    <div class="sikshya-welcome-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                    </div>
                    <h2 class="sikshya-welcome-title"><?php _e('Welcome to Sikshya LMS', 'sikshya'); ?></h2>
                    <p class="sikshya-welcome-description"><?php _e('Get started by creating your first course and building an amazing learning experience for your students.', 'sikshya'); ?></p>
                    <div class="sikshya-welcome-actions">
                        <a href="<?php echo admin_url('admin.php?page=sikshya-add-course'); ?>" class="sikshya-btn sikshya-btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                            <?php _e('Create Your First Course', 'sikshya'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=sikshya-settings'); ?>" class="sikshya-btn sikshya-btn-secondary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <?php _e('Settings', 'sikshya'); ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div> 