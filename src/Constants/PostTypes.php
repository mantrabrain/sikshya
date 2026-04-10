<?php

/**
 * Sikshya LMS Post Type Constants
 *
 * This file contains all the custom post type constants used throughout the plugin.
 * All post types follow the 'sik_' prefix convention.
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
 * Custom Post Type Constants
 *
 * These constants define the post type names used throughout the plugin.
 * All post types use the 'sik_' prefix to avoid conflicts with other plugins.
 */
class PostTypes
{
    /**
     * Course Post Type
     *
     * Used for storing course information, settings, and metadata.
     *
     * @var string
     */
    const COURSE = 'sik_course';

    /**
     * Lesson Post Type
     *
     * Used for storing lesson content, settings, and metadata.
     *
     * @var string
     */
    const LESSON = 'sik_lesson';

    /**
     * Assignment Post Type
     *
     * Used for storing assignment information, requirements, and metadata.
     *
     * @var string
     */
    const ASSIGNMENT = 'sik_assignment';

    /**
     * Quiz Post Type
     *
     * Used for storing quiz information, settings, and metadata.
     *
     * @var string
     */
    const QUIZ = 'sik_quiz';

    /**
     * Question Post Type
     *
     * Used for storing individual quiz questions and their metadata.
     *
     * @var string
     */
    const QUESTION = 'sik_question';

    /**
     * Chapter Post Type
     *
     * Used for storing chapter information and organization.
     *
     * @var string
     */
    const CHAPTER = 'sik_chapter';

    /**
     * Certificate template / record post type (admin-managed).
     *
     * @var string
     */
    const CERTIFICATE = 'sikshya_certificate';

    /**
     * Get all post types as an array
     *
     * @return array Array of all post type constants
     */
    public static function getAll()
    {
        return [
            self::COURSE,
            self::LESSON,
            self::ASSIGNMENT,
            self::QUIZ,
            self::QUESTION,
            self::CHAPTER,
            self::CERTIFICATE,
        ];
    }

    /**
     * Get post types that can be organized in chapters
     *
     * @return array Array of post types that can be organized
     */
    public static function getOrganizable()
    {
        return [
            self::LESSON,
            self::ASSIGNMENT,
            self::QUIZ,
        ];
    }

    /**
     * Get post types that are content types (not organizational)
     *
     * @return array Array of content post types
     */
    public static function getContentTypes()
    {
        return [
            self::LESSON,
            self::ASSIGNMENT,
            self::QUIZ,
            self::QUESTION,
        ];
    }

    /**
     * Get post types that are organizational types
     *
     * @return array Array of organizational post types
     */
    public static function getOrganizationalTypes()
    {
        return [
            self::COURSE,
            self::CHAPTER,
        ];
    }
}
