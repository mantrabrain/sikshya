<?php
/**
 * Question Types Test Page
 * 
 * @package Sikshya\Admin\Views
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Run tests if requested
if (isset($_GET['run_tests']) && current_user_can('manage_options')) {
    require_once plugin_dir_path(SIKSHYA_PLUGIN_FILE) . 'tests/question-types-test.php';
    $test = new SikshyaQuestionTypesTest();
    $test->runAllTests();
    return;
}
?>

<div class="wrap">
    <div class="sikshya-header">
        <h1>
            <i class="fas fa-vial"></i>
            <?php _e('Question Types Test', 'sikshya'); ?>
        </h1>
        <div class="sikshya-header-actions">
            <span class="sikshya-version-info">
                <?php printf(__('Version %s', 'sikshya'), SIKSHYA_VERSION); ?>
            </span>
        </div>
    </div>

    <div class="sikshya-settings-container">
        <div class="sikshya-settings-content">
            <div class="sikshya-settings-header">
                <h2>
                    <i class="fas fa-vial"></i>
                    <?php _e('Question Types Test', 'sikshya'); ?>
                </h2>
                <p><?php _e('Test all question types to ensure they work properly', 'sikshya'); ?></p>
            </div>

            <div class="sikshya-form-section">
                <h4 class="sikshya-form-section-title">Test Overview</h4>
                
                <div class="sikshya-form-row-small">
                    <p>This test will verify that all question types are working correctly:</p>
                    <ul style="margin-left: 20px; margin-top: 10px;">
                        <li><strong>Multiple Choice:</strong> Choose one correct answer from multiple options</li>
                        <li><strong>True/False:</strong> Choose between True or False</li>
                        <li><strong>Fill in the Blank:</strong> Fill in the missing word or phrase</li>
                        <li><strong>Essay:</strong> Write a detailed response</li>
                        <li><strong>Matching:</strong> Match items from two columns</li>
                    </ul>
                </div>

                <div class="sikshya-form-row-small">
                    <h5>What the test covers:</h5>
                    <ul style="margin-left: 20px; margin-top: 10px;">
                        <li>Question type validation</li>
                        <li>Data conversion between frontend and database formats</li>
                        <li>Quiz scoring with different question types</li>
                        <li>Question type features and capabilities</li>
                        <li>Default options and configurations</li>
                    </ul>
                </div>

                <div class="sikshya-form-row-small" style="margin-top: 20px;">
                    <a href="<?php echo admin_url('admin.php?page=sikshya-question-test&run_tests=1'); ?>" 
                       class="sikshya-btn sikshya-btn-primary">
                        <i class="fas fa-play"></i>
                        <?php _e('Run Question Types Test', 'sikshya'); ?>
                    </a>
                </div>
            </div>

            <div class="sikshya-form-section">
                <h4 class="sikshya-form-section-title">Question Type Details</h4>
                
                <?php
                $questionTypeService = new \Sikshya\Services\QuestionTypeService();
                $questionTypes = $questionTypeService->getAllQuestionTypes();
                ?>
                
                <div class="sikshya-question-types-grid">
                    <?php foreach ($questionTypes as $type => $config): ?>
                        <div class="sikshya-question-type-card">
                            <div class="sikshya-question-type-header">
                                <i class="<?php echo esc_attr($config['icon']); ?>"></i>
                                <h5><?php echo esc_html($config['label']); ?></h5>
                            </div>
                            <p><?php echo esc_html($config['description']); ?></p>
                            <div class="sikshya-question-type-features">
                                <span class="sikshya-feature-badge <?php echo $config['auto_gradable'] ? 'auto-gradable' : 'manual-grading'; ?>">
                                    <?php echo $config['auto_gradable'] ? 'Auto-gradable' : 'Manual Grading'; ?>
                                </span>
                                <?php if ($config['supports_options']): ?>
                                    <span class="sikshya-feature-badge supports-options">Supports Options</span>
                                <?php endif; ?>
                                <?php if ($config['requires_text_input']): ?>
                                    <span class="sikshya-feature-badge text-input">Text Input</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.sikshya-question-types-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.sikshya-question-type-card {
    background: #fff;
    border: 1px solid #e1e8ed;
    border-radius: 8px;
    padding: 20px;
    transition: all 0.2s ease;
}

.sikshya-question-type-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.sikshya-question-type-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.sikshya-question-type-header i {
    font-size: 20px;
    color: #3498db;
}

.sikshya-question-type-header h5 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
}

.sikshya-question-type-card p {
    margin: 0 0 16px 0;
    color: #7f8c8d;
    line-height: 1.5;
}

.sikshya-question-type-features {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.sikshya-feature-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.sikshya-feature-badge.auto-gradable {
    background: #d5f4e6;
    color: #27ae60;
}

.sikshya-feature-badge.manual-grading {
    background: #fdf2e9;
    color: #e67e22;
}

.sikshya-feature-badge.supports-options {
    background: #e8f4fd;
    color: #3498db;
}

.sikshya-feature-badge.text-input {
    background: #f4e6ff;
    color: #9b59b6;
}
</style> 