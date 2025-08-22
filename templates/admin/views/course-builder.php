<?php
/**
 * Course Builder Template - Clean Version
 * 
 * @package Sikshya
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$active_tab = $data['active_tab'] ?? 'course';
$course_id = $data['course_id'] ?? '';
?>

<div class="sikshya-course-builder">
    <!-- Course Builder Form -->
    <form id="sikshya-course-builder-form" method="post" action="" enctype="multipart/form-data">
        <?php wp_nonce_field('sikshya_course_builder_nonce', 'sikshya_course_builder_nonce'); ?>
        <input type="hidden" name="action" value="sikshya_save_course" />
        <input type="hidden" name="course_id" value="<?php echo esc_attr($course_id); ?>" />
        <input type="hidden" name="course_status" value="draft" id="course-status-field" />
        
        <div class="sikshya-header">
            <div class="sikshya-header-title">
                <h1>
                    <i class="fas fa-graduation-cap"></i>
                    Course Builder
                </h1>
                <span class="sikshya-version">v<?php echo esc_html(SIKSHYA_VERSION); ?></span>
            </div>
            <div class="sikshya-header-actions">
                <button type="button" class="sikshya-btn sikshya-btn-secondary" onclick="previewCourse()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Preview
                </button>
                <button type="submit" class="sikshya-btn sikshya-btn-secondary" onclick="saveDraft()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                    </svg>
                    Save Draft
                </button>
                <button type="submit" class="sikshya-btn sikshya-btn-primary" onclick="publishCourse()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18"/>
                    </svg>
                    Publish Course
                </button>
            </div>
        </div>

        <div class="sikshya-main-content">
            <div class="sikshya-sidebar">
                <!-- Modern Clean Header -->
                <div class="sikshya-sidebar-header">
                    <div class="sikshya-header-icon"></div>
                    <div class="sikshya-course-title">
                        <h3>Course Builder</h3>
                        <p>Create amazing learning experiences</p>
                    </div>
                </div>
                
                <!-- Compact Progress Overview -->
                <div class="sikshya-progress-section">
                    <div class="sikshya-progress-header">
                        <h4>Course Progress</h4>
                        <span class="sikshya-progress-percentage">75%</span>
                    </div>
                    <div class="sikshya-progress-bar">
                        <div class="sikshya-progress-fill"></div>
                    </div>
                    <div class="sikshya-progress-stats">
                        3 of 4 steps completed
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <nav class="sikshya-sidebar-nav">
                    <div class="sikshya-nav-section">
                        <h4 class="sikshya-nav-section-title">Course Setup</h4>
                        <ul class="sikshya-nav-list">
                            <li class="sikshya-nav-item">
                                <a href="#" class="sikshya-nav-link <?php echo ($active_tab === 'course') ? 'active' : ''; ?>" onclick="switchTab('course'); return false;" data-tab="course">
                                    <svg class="sikshya-nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <div class="sikshya-nav-content">
                                        <span class="sikshya-nav-title">Course Information</span>
                                        <span class="sikshya-nav-desc">Title, description, and basic details</span>
                                    </div>
                                </a>
                            </li>
                            <li class="sikshya-nav-item">
                                <a href="#" class="sikshya-nav-link <?php echo ($active_tab === 'pricing') ? 'active' : ''; ?>" onclick="switchTab('pricing'); return false;" data-tab="pricing">
                                    <svg class="sikshya-nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                                    </svg>
                                    <div class="sikshya-nav-content">
                                        <span class="sikshya-nav-title">Pricing & Access</span>
                                        <span class="sikshya-nav-desc">Set price and enrollment options</span>
                                    </div>
                                </a>
                            </li>
                            <li class="sikshya-nav-item">
                                <a href="#" class="sikshya-nav-link <?php echo ($active_tab === 'curriculum') ? 'active' : ''; ?>" onclick="switchTab('curriculum'); return false;" data-tab="curriculum">
                                    <svg class="sikshya-nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                    </svg>
                                    <div class="sikshya-nav-content">
                                        <span class="sikshya-nav-title">Curriculum</span>
                                        <span class="sikshya-nav-desc">Add lessons, sections, and content</span>
                                    </div>
                                </a>
                            </li>
                            <li class="sikshya-nav-item">
                                <a href="#" class="sikshya-nav-link <?php echo ($active_tab === 'settings') ? 'active' : ''; ?>" onclick="switchTab('settings'); return false;" data-tab="settings">
                                    <svg class="sikshya-nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <div class="sikshya-nav-content">
                                        <span class="sikshya-nav-title">Settings</span>
                                        <span class="sikshya-nav-desc">Advanced options and SEO</span>
                                    </div>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- Quick Actions -->
                    <div class="sikshya-nav-section">
                        <h4 class="sikshya-nav-section-title">Quick Actions</h4>
                        <div class="sikshya-quick-actions">
                            <button type="button" class="sikshya-btn sikshya-btn-secondary" onclick="previewCourse()">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                Preview
                            </button>
                            <button type="submit" class="sikshya-btn sikshya-btn-secondary" onclick="saveDraft()">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                                </svg>
                                Save Draft
                            </button>
                            <button type="submit" class="sikshya-btn sikshya-btn-primary" onclick="publishCourse()">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18"/>
                                </svg>
                                Publish Course
                            </button>
                        </div>
                    </div>
                </nav>
            </div>

            <div class="sikshya-content">
                <?php
                // Include form templates based on active tab
                $templates_dir = plugin_dir_path(__FILE__) . 'courses/form/';
                
                // Course Information Form
                if (file_exists($templates_dir . 'course-info.php')) {
                    include $templates_dir . 'course-info.php';
                }
                
                // Pricing Form  
                if (file_exists($templates_dir . 'pricing.php')) {
                    include $templates_dir . 'pricing.php';
                }
                
                // Curriculum Form
                if (file_exists($templates_dir . 'curriculum.php')) {
                    include $templates_dir . 'curriculum.php';
                }
                
                // Settings Form
                if (file_exists($templates_dir . 'settings.php')) {
                    include $templates_dir . 'settings.php';
                }
                ?>
            </div>
        </div>
    </form>
</div>
