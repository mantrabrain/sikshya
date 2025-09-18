<?php
/**
 * Sikshya LMS Taxonomy Constants
 * 
 * This file contains all the custom taxonomy constants used throughout the plugin.
 * All taxonomies follow the 'sikshya_' prefix convention.
 * 
 * @package Sikshya
 * @since 1.0.0
 */

namespace Sikshya\Constants;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom Taxonomy Constants
 * 
 * These constants define the taxonomy names used throughout the plugin.
 * All taxonomies use the 'sikshya_' prefix to avoid conflicts with other plugins.
 */
class Taxonomies {
    
    /**
     * Course Category Taxonomy
     * 
     * Used for organizing courses into categories.
     * 
     * @var string
     */
    const COURSE_CATEGORY = 'sikshya_course_category';
    
    /**
     * Course Tag Taxonomy
     * 
     * Used for tagging courses with keywords.
     * 
     * @var string
     */
    const COURSE_TAG = 'sikshya_course_tag';
    
    /**
     * Difficulty Level Taxonomy
     * 
     * Used for categorizing courses and lessons by difficulty.
     * 
     * @var string
     */
    const DIFFICULTY = 'sikshya_difficulty';
    
    /**
     * Lesson Type Taxonomy
     * 
     * Used for categorizing lessons by type.
     * 
     * @var string
     */
    const LESSON_TYPE = 'sikshya_lesson_type';
    
    /**
     * Get all taxonomies as an array
     * 
     * @return array Array of all taxonomy constants
     */
    public static function getAll() {
        return [
            self::COURSE_CATEGORY,
            self::COURSE_TAG,
            self::DIFFICULTY,
            self::LESSON_TYPE,
        ];
    }
    
    /**
     * Get taxonomies that belong to courses
     * 
     * @return array Array of course-related taxonomies
     */
    public static function getCourseTaxonomies() {
        return [
            self::COURSE_CATEGORY,
            self::COURSE_TAG,
            self::DIFFICULTY,
        ];
    }
    
    /**
     * Get taxonomies that belong to lessons
     * 
     * @return array Array of lesson-related taxonomies
     */
    public static function getLessonTaxonomies() {
        return [
            self::DIFFICULTY,
            self::LESSON_TYPE,
        ];
    }
}
