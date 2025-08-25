<?php
/**
 * Course Builder Manager
 * 
 * @package Sikshya
 * @since 1.0.0
 */

namespace Sikshya\Admin\CourseBuilder;

use Sikshya\Admin\CourseBuilder\Core\TabRegistry;
use Sikshya\Admin\CourseBuilder\Tabs\CourseInfoTab;
use Sikshya\Admin\CourseBuilder\Tabs\PricingTab;
use Sikshya\Admin\CourseBuilder\Tabs\CurriculumTab;
use Sikshya\Admin\CourseBuilder\Tabs\SettingsTab;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CourseBuilderManager
{
    /**
     * Plugin instance
     * 
     * @var \Sikshya\Core\Plugin
     */
    private $plugin;
    
    /**
     * Constructor
     * 
     * @param \Sikshya\Core\Plugin $plugin
     */
    public function __construct($plugin)
    {
        $this->plugin = $plugin;
        $this->initTabs();
    }
    
    /**
     * Initialize all tabs
     * 
     * @return void
     */
    private function initTabs(): void
    {
        // Register all tabs
        TabRegistry::registerTab(new CourseInfoTab($this->plugin));
        TabRegistry::registerTab(new PricingTab($this->plugin));
        TabRegistry::registerTab(new CurriculumTab($this->plugin));
        TabRegistry::registerTab(new SettingsTab($this->plugin));
        
        // Allow other plugins to register additional tabs
        do_action('sikshya_register_course_builder_tabs', $this->plugin);
    }
    
    /**
     * Get all registered tabs
     * 
     * @return array
     */
    public function getAllTabs(): array
    {
        return TabRegistry::getTabsByOrder();
    }
    
    /**
     * Get a specific tab
     * 
     * @param string $tab_id
     * @return \Sikshya\Admin\CourseBuilder\Core\TabInterface|null
     */
    public function getTab(string $tab_id)
    {
        return TabRegistry::getTab($tab_id);
    }
    
    /**
     * Get the first tab ID (for default active tab)
     * 
     * @return string|null
     */
    public function getFirstTabId(): ?string
    {
        return TabRegistry::getFirstTabId();
    }
    
    /**
     * Render the navigation tabs
     * 
     * @param string $active_tab
     * @return string
     */
    public function renderNavigation(string $active_tab = ''): string
    {
        $tabs = $this->getAllTabs();
        $active_tab = $active_tab ?: $this->getFirstTabId();
        
        ob_start();
        ?>
        <nav class="sikshya-sidebar-nav">
            <div class="sikshya-nav-section">
                <h4 class="sikshya-nav-section-title"><?php _e('Course Setup', 'sikshya'); ?></h4>
                <ul class="sikshya-nav-list">
                    <?php foreach ($tabs as $tab): ?>
                        <li class="sikshya-nav-item">
                            <a href="#" class="sikshya-nav-link <?php echo ($active_tab === $tab->getId()) ? 'active' : ''; ?>" 
                               onclick="switchTab('<?php echo esc_attr($tab->getId()); ?>'); return false;" 
                               data-tab="<?php echo esc_attr($tab->getId()); ?>">
                                <svg class="sikshya-nav-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <?php echo $tab->getIcon(); ?>
                                </svg>
                                <div class="sikshya-nav-content">
                                    <span class="sikshya-nav-title"><?php echo esc_html($tab->getTitle()); ?></span>
                                    <span class="sikshya-nav-desc"><?php echo esc_html($tab->getDescription()); ?></span>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Quick Actions -->
            <div class="sikshya-nav-section">
                <h4 class="sikshya-nav-section-title"><?php _e('Quick Actions', 'sikshya'); ?></h4>
                <div class="sikshya-quick-actions">
                    <button type="button" class="sikshya-btn sikshya-btn-secondary" onclick="previewCourse()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <?php _e('Preview', 'sikshya'); ?>
                    </button>
                    <button type="submit" class="sikshya-btn sikshya-btn-secondary" onclick="saveDraft()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                        </svg>
                        <?php _e('Save Draft', 'sikshya'); ?>
                    </button>
                    <button type="submit" class="sikshya-btn sikshya-btn-primary" onclick="publishCourse()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18"/>
                        </svg>
                        <?php _e('Publish Course', 'sikshya'); ?>
                    </button>
                </div>
            </div>
        </nav>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render all tab content
     * 
     * @param string $active_tab
     * @param int $course_id
     * @return string
     */
    public function renderTabContent(string $active_tab = '', int $course_id = 0): string
    {
        $tabs = $this->getAllTabs();
        $active_tab = $active_tab ?: $this->getFirstTabId();
        
        ob_start();
        ?>
        <div class="sikshya-content">
            <?php foreach ($tabs as $tab): ?>
                <?php
                $data = [];
                if ($course_id > 0) {
                    $data = $tab->load($course_id);
                }
                echo $tab->render($data, $active_tab);
                ?>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Save all tab data
     * 
     * @param array $data
     * @param int $course_id
     * @return array Array of errors
     */
    public function saveAllTabs(array $data, int $course_id): array
    {
        $errors = [];
        $tabs = $this->getAllTabs();
        
        foreach ($tabs as $tab) {
            $tab_id = $tab->getId();
            
            // For flat data structure, pass all data to each tab
            // Each tab will save only its own fields based on field definitions
            
            // Save tab data
            $success = $tab->save($data, $course_id);
            if (!$success) {
                $errors[$tab_id] = [__('Failed to save tab data.', 'sikshya')];
            }
        }
        
        return $errors;
    }
    
    /**
     * Load all tab data
     * 
     * @param int $course_id
     * @return array
     */
    public function loadAllTabs(int $course_id): array
    {
        $data = [];
        $tabs = $this->getAllTabs();
        
        foreach ($tabs as $tab) {
            $tab_id = $tab->getId();
            $data[$tab_id] = $tab->load($course_id);
        }
        
        return $data;
    }
    
    /**
     * Validate all tab data
     * 
     * @param array $data
     * @return array Array of errors
     */
    public function validateAllTabs(array $data): array
    {
        $errors = [];
        $tabs = $this->getAllTabs();
        
        foreach ($tabs as $tab) {
            $tab_id = $tab->getId();
            
            // For flat data structure, pass all data to each tab for validation
            // Each tab will validate only its own fields based on field definitions
            $tab_errors = $tab->validate($data);
            if (!empty($tab_errors)) {
                $errors[$tab_id] = $tab_errors;
            }
        }
        
        return $errors;
    }
    
    /**
     * Get tab fields for JavaScript
     * 
     * @return array
     */
    public function getTabFieldsForJs(): array
    {
        $fields = [];
        $tabs = $this->getAllTabs();
        
        foreach ($tabs as $tab) {
            $tab_id = $tab->getId();
            $fields[$tab_id] = $tab->getFields();
        }
        
        return $fields;
    }
}
