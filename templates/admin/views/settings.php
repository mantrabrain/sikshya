<?php
/**
 * Comprehensive LMS Settings Page Template
 * 
 * @package Sikshya
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current tab
$current_tab = sanitize_text_field($_GET['tab'] ?? 'general');

// Define all setting groups
$setting_groups = [
    'general' => [
        'title' => __('General', 'sikshya'),
        'icon' => 'fas fa-cog',
        'description' => __('Basic LMS configuration settings', 'sikshya')
    ],
    'courses' => [
        'title' => __('Courses', 'sikshya'),
        'icon' => 'fas fa-graduation-cap',
        'description' => __('Course management and display settings', 'sikshya')
    ],
    'enrollment' => [
        'title' => __('Enrollment', 'sikshya'),
        'icon' => 'fas fa-user-plus',
        'description' => __('Student enrollment and access settings', 'sikshya')
    ],
    'payment' => [
        'title' => __('Payment', 'sikshya'),
        'icon' => 'fas fa-credit-card',
        'description' => __('Payment gateway and pricing settings', 'sikshya')
    ],
    'certificates' => [
        'title' => __('Certificates', 'sikshya'),
        'icon' => 'fas fa-certificate',
        'description' => __('Certificate generation and design settings', 'sikshya')
    ],
    'email' => [
        'title' => __('Email', 'sikshya'),
        'icon' => 'fas fa-envelope',
        'description' => __('Email notification and template settings', 'sikshya')
    ],
    'instructors' => [
        'title' => __('Instructors', 'sikshya'),
        'icon' => 'fas fa-chalkboard-teacher',
        'description' => __('Instructor management and permissions', 'sikshya')
    ],
    'students' => [
        'title' => __('Students', 'sikshya'),
        'icon' => 'fas fa-users',
        'description' => __('Student management and profile settings', 'sikshya')
    ],
    'quizzes' => [
        'title' => __('Quizzes', 'sikshya'),
        'icon' => 'fas fa-question-circle',
        'description' => __('Quiz and assessment settings', 'sikshya')
    ],
    'assignments' => [
        'title' => __('Assignments', 'sikshya'),
        'icon' => 'fas fa-tasks',
        'description' => __('Assignment submission and grading settings', 'sikshya')
    ],
    'progress' => [
        'title' => __('Progress', 'sikshya'),
        'icon' => 'fas fa-chart-line',
        'description' => __('Progress tracking and completion settings', 'sikshya')
    ],
    'notifications' => [
        'title' => __('Notifications', 'sikshya'),
        'icon' => 'fas fa-bell',
        'description' => __('In-app and push notification settings', 'sikshya')
    ],
    'integrations' => [
        'title' => __('Integrations', 'sikshya'),
        'icon' => 'fas fa-plug',
        'description' => __('Third-party integrations and APIs', 'sikshya')
    ],
    'security' => [
        'title' => __('Security', 'sikshya'),
        'icon' => 'fas fa-shield-alt',
        'description' => __('Security and privacy settings', 'sikshya')
    ],
    'advanced' => [
        'title' => __('Advanced', 'sikshya'),
        'icon' => 'fas fa-tools',
        'description' => __('Advanced configuration and debugging', 'sikshya')
    ]
];
?>

<div class="wrap sikshya-settings-page">
    <div class="sikshya-header">
        <div class="sikshya-header-title">
            <h1>
                <i class="fas fa-cogs"></i>
                <?php _e('Settings', 'sikshya'); ?>
            </h1>
            <span class="sikshya-version">v<?php echo esc_html(SIKSHYA_VERSION); ?></span>
        </div>
        <div class="sikshya-header-actions">
            <!-- Additional header actions can be added here if needed -->
        </div>
    </div>
    
    <div class="sikshya-settings-container">
        <!-- Left Sidebar - Setting Groups -->
        <div class="sikshya-settings-sidebar">
            <div class="sikshya-settings-nav">
                <?php foreach ($setting_groups as $tab_key => $tab_data): ?>
                    <a href="?page=sikshya-settings&tab=<?php echo esc_attr($tab_key); ?>" 
                       class="sikshya-settings-tab <?php echo $current_tab === $tab_key ? 'active' : ''; ?>"
                       data-tab="<?php echo esc_attr($tab_key); ?>">
                        <i class="<?php echo esc_attr($tab_data['icon']); ?>"></i>
                        <div class="tab-content">
                            <span class="tab-title"><?php echo esc_html($tab_data['title']); ?></span>
                            <span class="tab-description"><?php echo esc_html($tab_data['description']); ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right Side - Settings Form -->
        <div class="sikshya-settings-content">
            <div class="sikshya-settings-header">
                <h2>
                    <i class="<?php echo esc_attr($setting_groups[$current_tab]['icon']); ?>"></i>
                    <?php echo esc_html($setting_groups[$current_tab]['title']); ?> Settings
                </h2>
                <p><?php echo esc_html($setting_groups[$current_tab]['description']); ?></p>
            </div>

            <div class="sikshya-settings-form-container">
                <form id="sikshya-settings-form" method="post" action="">
                    <?php wp_nonce_field('sikshya_settings_nonce', 'sikshya_nonce'); ?>
                    <input type="hidden" name="action" value="sikshya_settings_save">
                    <input type="hidden" name="current_tab" value="<?php echo esc_attr($current_tab); ?>">

                    <!-- Settings content will be loaded here via AJAX -->
                    <div id="sikshya-settings-content">
                        <div class="sikshya-loading">
                            <i class="fas fa-spinner fa-spin"></i>
                            <span><?php _e('Loading settings...', 'sikshya'); ?></span>
                        </div>
                    </div>

                    <div class="sikshya-settings-actions">
                        <button type="submit" class="button button-primary sikshya-save-settings">
                            <i class="fas fa-save"></i>
                            <?php _e('Save Settings', 'sikshya'); ?>
                        </button>
                        <button type="button" class="button sikshya-reset-settings" data-tab="<?php echo esc_attr($current_tab); ?>">
                            <i class="fas fa-undo"></i>
                            <?php _e('Reset to Defaults', 'sikshya'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>