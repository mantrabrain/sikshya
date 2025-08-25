<?php
/**
 * Curriculum Tab for Course Builder
 * 
 * @package Sikshya
 * @since 1.0.0
 */

namespace Sikshya\Admin\CourseBuilder\Tabs;

use Sikshya\Admin\CourseBuilder\Core\AbstractTab;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CurriculumTab extends AbstractTab
{
    /**
     * Get the unique identifier for this tab
     * 
     * @return string
     */
    public function getId(): string
    {
        return 'curriculum';
    }
    
    /**
     * Get the display title for this tab
     * 
     * @return string
     */
    public function getTitle(): string
    {
        return __('Curriculum', 'sikshya');
    }
    
    /**
     * Get the description for this tab
     * 
     * @return string
     */
    public function getDescription(): string
    {
        return __('Add lessons, sections, and content', 'sikshya');
    }
    
    /**
     * Get the SVG icon for this tab
     * 
     * @return string
     */
    public function getIcon(): string
    {
        return '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>';
    }
    
    /**
     * Get the tab order
     * 
     * @return int
     */
    public function getOrder(): int
    {
        return 3;
    }
    
    /**
     * Get the fields configuration for this tab
     * 
     * @return array
     */
    public function getFields(): array
    {
        return [
            'curriculum' => [
                'section' => [
                    'description' => __('Add visual elements to make your course more engaging', 'sikshya'),

                    'title' => __('Course Curriculum', 'sikshya'),
                    'icon' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>',
                ],
                'fields' => [
                    'curriculum_structure' => [
                        'type' => 'curriculum_builder',
                        'validation' => 'array',
                        'sanitization' => 'sanitize_text_field',
                    ],
                ],
            ],
        ];
    }
    
    /**
     * Render the tab content with exact same HTML markup
     * 
     * @param array $data
     * @return string
     */
    protected function renderContent(array $data): string
    {
        return $this->renderSections($data);
    }
    
    /**
     * Override save method for curriculum (handles chapters and lessons)
     * 
     * @param array $data
     * @param int $course_id
     * @return bool
     */
    public function save(array $data, int $course_id): bool
    {
        // Curriculum is handled separately via AJAX
        // This method is called for the main form submission
        return true;
    }
    
    /**
     * Override load method for curriculum
     * 
     * @param int $course_id
     * @return array
     */
    public function load(int $course_id): array
    {
        // Curriculum is loaded via AJAX
        return [];
    }
    
    /**
     * Override validate method for curriculum
     * 
     * @param array $data
     * @return array
     */
    public function validate(array $data): array
    {
        // Curriculum validation is handled via AJAX
        return [];
    }
}
