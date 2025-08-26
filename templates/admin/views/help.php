<?php
/**
 * Help & Support Page Template
 *
 * @package Sikshya
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

$page_title = $this->data['page_title'] ?? __('Help & Support', 'sikshya');
$page_description = $this->data['page_description'] ?? __('Get help and support for Sikshya LMS', 'sikshya');
?>

<div class="sikshya-dashboard">
    <!-- Header -->
    <div class="sikshya-header">
        <div class="sikshya-header-title">
            <h1>
                <i class="fas fa-question-circle"></i>
                <?php echo esc_html($page_title); ?>
            </h1>
            <span class="sikshya-version">v1.0.0</span>
        </div>
    </div>

    <div class="sikshya-main-content">
        <!-- Quick Start Guide Card -->
        <div class="sikshya-content-card">
            <div class="sikshya-content-card-header">
                <div class="sikshya-content-card-header-left">
                    <h3 class="sikshya-content-card-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <?php esc_html_e('Quick Start Guide', 'sikshya'); ?>
                    </h3>
                    <p class="sikshya-content-card-subtitle"><?php esc_html_e('Get started with Sikshya LMS in minutes', 'sikshya'); ?></p>
                </div>
            </div>
            <div class="sikshya-content-card-body">
                <div class="sikshya-help-grid">
                    <div class="sikshya-help-item">
                        <div class="sikshya-help-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                        </div>
                        <div class="sikshya-help-content">
                            <h4><?php esc_html_e('1. Create Your First Course', 'sikshya'); ?></h4>
                            <p><?php esc_html_e('Start by creating your first course. Go to Courses → Add Course and fill in the basic information.', 'sikshya'); ?></p>
                            <a href="<?php echo admin_url('admin.php?page=sikshya-add-course'); ?>" class="sikshya-btn sikshya-btn-primary">
                                <?php esc_html_e('Create Course', 'sikshya'); ?>
                            </a>
                        </div>
                    </div>

                    <div class="sikshya-help-item">
                        <div class="sikshya-help-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div class="sikshya-help-content">
                            <h4><?php esc_html_e('2. Add Lessons & Content', 'sikshya'); ?></h4>
                            <p><?php esc_html_e('Add lessons, quizzes, and other content to your course using the curriculum builder.', 'sikshya'); ?></p>
                            <a href="<?php echo admin_url('admin.php?page=sikshya-lessons'); ?>" class="sikshya-btn sikshya-btn-secondary">
                                <?php esc_html_e('Manage Lessons', 'sikshya'); ?>
                            </a>
                        </div>
                    </div>

                    <div class="sikshya-help-item">
                        <div class="sikshya-help-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <div class="sikshya-help-content">
                            <h4><?php esc_html_e('3. Invite Students', 'sikshya'); ?></h4>
                            <p><?php esc_html_e('Invite students to enroll in your courses and start learning.', 'sikshya'); ?></p>
                            <a href="<?php echo admin_url('admin.php?page=sikshya-students'); ?>" class="sikshya-btn sikshya-btn-secondary">
                                <?php esc_html_e('Manage Students', 'sikshya'); ?>
                            </a>
                        </div>
                    </div>

                    <div class="sikshya-help-item">
                        <div class="sikshya-help-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <div class="sikshya-help-content">
                            <h4><?php esc_html_e('4. Monitor Progress', 'sikshya'); ?></h4>
                            <p><?php esc_html_e('Track student progress and view detailed reports and analytics.', 'sikshya'); ?></p>
                            <a href="<?php echo admin_url('admin.php?page=sikshya-reports'); ?>" class="sikshya-btn sikshya-btn-secondary">
                                <?php esc_html_e('View Reports', 'sikshya'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Documentation Card -->
        <div class="sikshya-content-card">
            <div class="sikshya-content-card-header">
                <div class="sikshya-content-card-header-left">
                    <h3 class="sikshya-content-card-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <?php esc_html_e('Documentation', 'sikshya'); ?>
                    </h3>
                    <p class="sikshya-content-card-subtitle"><?php esc_html_e('Comprehensive guides and tutorials', 'sikshya'); ?></p>
                </div>
            </div>
            <div class="sikshya-content-card-body">
                <div class="sikshya-docs-grid">
                    <div class="sikshya-doc-item">
                        <div class="sikshya-doc-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                        </div>
                        <div class="sikshya-doc-content">
                            <h4><?php esc_html_e('Course Management', 'sikshya'); ?></h4>
                            <p><?php esc_html_e('Learn how to create, edit, and manage courses effectively.', 'sikshya'); ?></p>
                            <a href="#" class="sikshya-link"><?php esc_html_e('Read Guide', 'sikshya'); ?> →</a>
                        </div>
                    </div>

                    <div class="sikshya-doc-item">
                        <div class="sikshya-doc-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div class="sikshya-doc-content">
                            <h4><?php esc_html_e('Content Creation', 'sikshya'); ?></h4>
                            <p><?php esc_html_e('Create engaging lessons, quizzes, and multimedia content.', 'sikshya'); ?></p>
                            <a href="#" class="sikshya-link"><?php esc_html_e('Read Guide', 'sikshya'); ?> →</a>
                        </div>
                    </div>

                    <div class="sikshya-doc-item">
                        <div class="sikshya-doc-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <div class="sikshya-doc-content">
                            <h4><?php esc_html_e('Student Management', 'sikshya'); ?></h4>
                            <p><?php esc_html_e('Manage student enrollments, progress tracking, and communications.', 'sikshya'); ?></p>
                            <a href="#" class="sikshya-link"><?php esc_html_e('Read Guide', 'sikshya'); ?> →</a>
                        </div>
                    </div>

                    <div class="sikshya-doc-item">
                        <div class="sikshya-doc-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <div class="sikshya-doc-content">
                            <h4><?php esc_html_e('Analytics & Reports', 'sikshya'); ?></h4>
                            <p><?php esc_html_e('Understand your data with comprehensive analytics and reporting tools.', 'sikshya'); ?></p>
                            <a href="#" class="sikshya-link"><?php esc_html_e('Read Guide', 'sikshya'); ?> →</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Support Options Card -->
        <div class="sikshya-content-card">
            <div class="sikshya-content-card-header">
                <div class="sikshya-content-card-header-left">
                    <h3 class="sikshya-content-card-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192L5.636 18.364M12 2.25a9.75 9.75 0 100 19.5 9.75 9.75 0 000-19.5z"></path>
                        </svg>
                        <?php esc_html_e('Get Support', 'sikshya'); ?>
                    </h3>
                    <p class="sikshya-content-card-subtitle"><?php esc_html_e('We\'re here to help you succeed', 'sikshya'); ?></p>
                </div>
            </div>
            <div class="sikshya-content-card-body">
                <div class="sikshya-support-grid">
                    <div class="sikshya-support-item">
                        <div class="sikshya-support-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="sikshya-support-content">
                            <h4><?php esc_html_e('FAQ', 'sikshya'); ?></h4>
                            <p><?php esc_html_e('Find answers to commonly asked questions.', 'sikshya'); ?></p>
                            <a href="#" class="sikshya-btn sikshya-btn-secondary">
                                <?php esc_html_e('Browse FAQ', 'sikshya'); ?>
                            </a>
                        </div>
                    </div>

                    <div class="sikshya-support-item">
                        <div class="sikshya-support-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <div class="sikshya-support-content">
                            <h4><?php esc_html_e('Email Support', 'sikshya'); ?></h4>
                            <p><?php esc_html_e('Get personalized help from our support team.', 'sikshya'); ?></p>
                            <a href="mailto:support@sikshya.com" class="sikshya-btn sikshya-btn-primary">
                                <?php esc_html_e('Contact Support', 'sikshya'); ?>
                            </a>
                        </div>
                    </div>

                    <div class="sikshya-support-item">
                        <div class="sikshya-support-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                        </div>
                        <div class="sikshya-support-content">
                            <h4><?php esc_html_e('Live Chat', 'sikshya'); ?></h4>
                            <p><?php esc_html_e('Chat with our support team in real-time.', 'sikshya'); ?></p>
                            <a href="#" class="sikshya-btn sikshya-btn-secondary" id="start-chat">
                                <?php esc_html_e('Start Chat', 'sikshya'); ?>
                            </a>
                        </div>
                    </div>

                    <div class="sikshya-support-item">
                        <div class="sikshya-support-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path>
                            </svg>
                        </div>
                        <div class="sikshya-support-content">
                            <h4><?php esc_html_e('Video Tutorials', 'sikshya'); ?></h4>
                            <p><?php esc_html_e('Watch step-by-step video tutorials.', 'sikshya'); ?></p>
                            <a href="#" class="sikshya-btn sikshya-btn-secondary">
                                <?php esc_html_e('Watch Videos', 'sikshya'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Status Card -->
        <div class="sikshya-content-card">
            <div class="sikshya-content-card-header">
                <div class="sikshya-content-card-header-left">
                    <h3 class="sikshya-content-card-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <?php esc_html_e('System Status', 'sikshya'); ?>
                    </h3>
                    <p class="sikshya-content-card-subtitle"><?php esc_html_e('Check the status of our services', 'sikshya'); ?></p>
                </div>
                <div class="sikshya-content-card-header-right">
                    <span class="sikshya-status-indicator sikshya-status-operational">
                        <span class="sikshya-status-dot"></span>
                        <?php esc_html_e('All Systems Operational', 'sikshya'); ?>
                    </span>
                </div>
            </div>
            <div class="sikshya-content-card-body">
                <div class="sikshya-status-grid">
                    <div class="sikshya-status-item">
                        <span class="sikshya-status-label"><?php esc_html_e('API Services', 'sikshya'); ?></span>
                        <span class="sikshya-status-value sikshya-status-operational"><?php esc_html_e('Operational', 'sikshya'); ?></span>
                    </div>
                    <div class="sikshya-status-item">
                        <span class="sikshya-status-label"><?php esc_html_e('Database', 'sikshya'); ?></span>
                        <span class="sikshya-status-value sikshya-status-operational"><?php esc_html_e('Operational', 'sikshya'); ?></span>
                    </div>
                    <div class="sikshya-status-item">
                        <span class="sikshya-status-label"><?php esc_html_e('File Storage', 'sikshya'); ?></span>
                        <span class="sikshya-status-value sikshya-status-operational"><?php esc_html_e('Operational', 'sikshya'); ?></span>
                    </div>
                    <div class="sikshya-status-item">
                        <span class="sikshya-status-label"><?php esc_html_e('Email Services', 'sikshya'); ?></span>
                        <span class="sikshya-status-value sikshya-status-operational"><?php esc_html_e('Operational', 'sikshya'); ?></span>
                    </div>
                </div>
                <div class="sikshya-status-footer">
                    <a href="#" class="sikshya-link"><?php esc_html_e('View detailed status page', 'sikshya'); ?> →</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Start chat functionality
    $('#start-chat').on('click', function(e) {
        e.preventDefault();
        alert('<?php esc_html_e('Live chat feature coming soon!', 'sikshya'); ?>');
    });
    
    // External link handling
    $('.sikshya-link').on('click', function(e) {
        e.preventDefault();
        const href = $(this).attr('href');
        if (href && href !== '#') {
            window.open(href, '_blank');
        } else {
            alert('<?php esc_html_e('This feature is coming soon!', 'sikshya'); ?>');
        }
    });
});
</script>
