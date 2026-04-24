<?php

namespace Sikshya\Services;

use Sikshya\Core\Plugin;
use Sikshya\Constants\PostTypes;
use Sikshya\PostTypes\PostTypeManager;

/**
 * Post Type Management Service
 *
 * @package Sikshya\Services
 */
class PostTypeService
{
    /**
     * Plugin instance
     *
     * @var Plugin
     */
    private Plugin $plugin;

    /**
     * Constructor
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Register post types
     */
    public function register(): void
    {
        add_action('init', [$this, 'registerCoursePostType']);
        add_action('init', [$this, 'registerLessonPostType']);
        add_action('init', [$this, 'registerQuizPostType']);
        add_action('init', [$this, 'registerAssignmentPostType']);
        add_action('init', [$this, 'registerCertificatePostType']);
        add_action('init', [$this, 'registerQuestionPostType']);
        add_action('init', [$this, 'registerChapterPostType']);
        // Expose Sikshya meta keys to the block editor / REST API (React admin uses WP core REST).
        add_action('init', [$this, 'registerRestAccessiblePostMeta'], 20);
    }

    /**
     * Expose Sikshya meta keys to the block editor / REST API so the React admin can load & save them.
     *
     * IMPORTANT: WP core REST hides underscore-prefixed meta unless context=edit is used.
     */
    public function registerRestAccessiblePostMeta(): void
    {
        $auth = static function (): bool {
            return current_user_can('edit_posts');
        };

        $int_meta = static function (string $post_type, string $key) use ($auth): void {
            register_post_meta(
                $post_type,
                $key,
                [
                    'type' => 'integer',
                    'single' => true,
                    'show_in_rest' => true,
                    'auth_callback' => $auth,
                ]
            );
        };

        $str_meta = static function (string $post_type, string $key) use ($auth): void {
            register_post_meta(
                $post_type,
                $key,
                [
                    'type' => 'string',
                    'single' => true,
                    'show_in_rest' => true,
                    'auth_callback' => $auth,
                ]
            );
        };

        $int_array_meta = static function (string $post_type, string $key) use ($auth): void {
            register_post_meta(
                $post_type,
                $key,
                [
                    'type' => 'array',
                    'single' => true,
                    'show_in_rest' => [
                        'schema' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'integer',
                            ],
                        ],
                    ],
                    'auth_callback' => $auth,
                ]
            );
        };

        $string_array_meta = static function (string $post_type, string $key) use ($auth): void {
            register_post_meta(
                $post_type,
                $key,
                [
                    'type' => 'array',
                    'single' => true,
                    'show_in_rest' => [
                        'schema' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                    'auth_callback' => $auth,
                    'sanitize_callback' => static function ($meta_value) {
                        if (!is_array($meta_value)) {
                            return [];
                        }

                        return array_values(
                            array_map(
                                static fn ($v) => sanitize_text_field((string) $v),
                                $meta_value
                            )
                        );
                    },
                ]
            );
        };

        // Certificate template (sikshya_certificate).
        register_post_meta(
            PostTypes::CERTIFICATE,
            '_sikshya_certificate_orientation',
            [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => $auth,
                'default' => 'landscape',
                'sanitize_callback' => static function ($meta_value) {
                    $v = sanitize_key((string) $meta_value);

                    return in_array($v, ['landscape', 'portrait'], true) ? $v : 'landscape';
                },
            ]
        );

        register_post_meta(
            PostTypes::CERTIFICATE,
            '_sikshya_certificate_page_size',
            [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => $auth,
                'default' => 'a4',
                'sanitize_callback' => static function ($meta_value) {
                    $v = sanitize_key((string) $meta_value);

                    return in_array($v, ['letter', 'a4', 'a5'], true) ? $v : 'a4';
                },
            ]
        );

        register_post_meta(
            PostTypes::CERTIFICATE,
            '_sikshya_certificate_page_color',
            [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => $auth,
                'default' => '',
                'sanitize_callback' => static function ($meta_value) {
                    $c = sanitize_hex_color((string) $meta_value);

                    return $c ? $c : '';
                },
            ]
        );

        register_post_meta(
            PostTypes::CERTIFICATE,
            '_sikshya_certificate_page_pattern',
            [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => $auth,
                'default' => 'none',
                'sanitize_callback' => static function ($meta_value) {
                    $s = sanitize_key((string) $meta_value);
                    // Must match CERT_PAGE_PATTERN_ORDER in admin-ui certificateLayout.ts.
                    // Note: sanitize_key lowercases, so microDots/paperGrain arrive as microdots/papergrain.
                    $allowed = ['none', 'dots', 'lines', 'grid', 'diagonals', 'microdots', 'papergrain'];

                    return in_array($s, $allowed, true) ? $s : 'none';
                },
            ]
        );

        register_post_meta(
            PostTypes::CERTIFICATE,
            '_sikshya_certificate_page_deco',
            [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => $auth,
                'default' => 'none',
                'sanitize_callback' => static function ($meta_value) {
                    // Must match CERT_PAGE_DECO_ORDER in admin-ui certificateLayout.ts (camelCase ids).
                    // Do not use sanitize_key() here — it lowercases and breaks ids like paperFolio.
                    $raw = (string) $meta_value;
                    $s = preg_replace('/[^a-zA-Z0-9_-]/', '', $raw) ?? '';
                    $allowed = [
                        'none',
                        'slate',
                        'cream',
                        'paperFolio',
                        'corporateLetter',
                        'formalBlueBand',
                        'diplomaGold',
                        'educationMint',
                        'minimalFrame',
                        'dawn',
                        'sky',
                        'rose',
                        'forest',
                        'sand',
                        'gold',
                        'mint',
                        'coral',
                        'sea',
                        'plum',
                        'aurora',
                        'night',
                        'dusk',
                    ];

                    return in_array($s, $allowed, true) ? $s : 'none';
                },
            ]
        );

        // Public template preview hash (auto-generated server-side; used to resolve /certificates/{hash}).
        register_post_meta(
            PostTypes::CERTIFICATE,
            '_sikshya_certificate_preview_hash',
            [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => $auth,
                'default' => '',
                'sanitize_callback' => static function ($meta_value) {
                    $s = strtolower(preg_replace('/[^a-f0-9]/', '', (string) $meta_value) ?? '');
                    return strlen($s) === 64 ? $s : '';
                },
            ]
        );

        register_post_meta(
            PostTypes::CERTIFICATE,
            '_sikshya_certificate_layout',
            [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => $auth,
                'sanitize_callback' => [PostTypeManager::class, 'sanitize_certificate_layout_string'],
            ]
        );

        // Public “View” / QR preview hash for certificate templates (not issued certificates).
        // 64-hex chars, stored in post meta so the admin UI can build stable preview URLs.
        register_post_meta(
            PostTypes::CERTIFICATE,
            '_sikshya_certificate_preview_hash',
            [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => $auth,
                'sanitize_callback' => static function ($meta_value): string {
                    $raw = strtolower(preg_replace('/[^a-fA-F0-9]/', '', (string) $meta_value) ?? '');
                    if (strlen($raw) !== 64) {
                        return '';
                    }

                    return $raw;
                },
            ]
        );

        // Keep these helpers for other content types that store meta via REST.
        // (Only the certificate keys are critical for the builder.)
        $str_meta(PostTypes::LESSON, '_sikshya_lesson_duration');
        $str_meta(PostTypes::LESSON, '_sikshya_lesson_type');
        $str_meta(PostTypes::LESSON, '_sikshya_lesson_video_url');

        $str_meta(PostTypes::QUESTION, '_sikshya_question_type');
        $int_meta(PostTypes::QUESTION, '_sikshya_question_points');
        $string_array_meta(PostTypes::QUESTION, '_sikshya_question_options');
        $str_meta(PostTypes::QUESTION, '_sikshya_question_correct_answer');

        $str_meta(PostTypes::ASSIGNMENT, '_sikshya_assignment_due_date');
        $int_meta(PostTypes::ASSIGNMENT, '_sikshya_assignment_points');
        $str_meta(PostTypes::ASSIGNMENT, '_sikshya_assignment_type');
        $int_meta(PostTypes::ASSIGNMENT, '_sikshya_assignment_course');

        $int_meta(PostTypes::CHAPTER, '_sikshya_chapter_order');
        $int_meta(PostTypes::CHAPTER, '_sikshya_chapter_course_id');

        $int_array_meta(PostTypes::QUIZ, '_sikshya_quiz_questions');
        $int_meta(PostTypes::QUIZ, '_sikshya_quiz_time_limit');
        $int_meta(PostTypes::QUIZ, '_sikshya_quiz_passing_score');
        $int_meta(PostTypes::QUIZ, '_sikshya_quiz_attempts_allowed');
    }

    /**
     * Register Course post type
     */
    public function registerCoursePostType(): void
    {
        $rewrite = PermalinkService::get();

        $labels = [
            'name' => __('Courses', 'sikshya'),
            'singular_name' => __('Course', 'sikshya'),
            'menu_name' => __('Courses', 'sikshya'),
            'add_new' => __('Add New Course', 'sikshya'),
            'add_new_item' => __('Add New Course', 'sikshya'),
            'edit_item' => __('Edit Course', 'sikshya'),
            'new_item' => __('New Course', 'sikshya'),
            'view_item' => __('View Course', 'sikshya'),
            'search_items' => __('Search Courses', 'sikshya'),
            'not_found' => __('No courses found', 'sikshya'),
            'not_found_in_trash' => __('No courses found in trash', 'sikshya'),
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_admin_bar' => false,
            'query_var' => true,
            'rewrite' => [ 'slug' => $rewrite['rewrite_base_course'], 'with_front' => false ],
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions'],
            'show_in_rest' => true,
        ];

        register_post_type(PostTypes::COURSE, $args);
    }

    /**
     * Register Lesson post type
     */
    public function registerLessonPostType(): void
    {
        $rewrite = PermalinkService::get();

        $labels = [
            'name' => __('Lessons', 'sikshya'),
            'singular_name' => __('Lesson', 'sikshya'),
            'menu_name' => __('Lessons', 'sikshya'),
            'add_new' => __('Add New Lesson', 'sikshya'),
            'add_new_item' => __('Add New Lesson', 'sikshya'),
            'edit_item' => __('Edit Lesson', 'sikshya'),
            'new_item' => __('New Lesson', 'sikshya'),
            'view_item' => __('View Lesson', 'sikshya'),
            'search_items' => __('Search Lessons', 'sikshya'),
            'not_found' => __('No lessons found', 'sikshya'),
            'not_found_in_trash' => __('No lessons found in trash', 'sikshya'),
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_admin_bar' => false,
            'query_var' => true,
            'rewrite' => [ 'slug' => $rewrite['rewrite_base_lesson'], 'with_front' => false ],
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields', 'revisions'],
            'show_in_rest' => true,
        ];

        register_post_type(PostTypes::LESSON, $args);
    }

    /**
     * Register Quiz post type
     */
    public function registerQuizPostType(): void
    {
        $rewrite = PermalinkService::get();

        $labels = [
            'name' => __('Quizzes', 'sikshya'),
            'singular_name' => __('Quiz', 'sikshya'),
            'menu_name' => __('Quizzes', 'sikshya'),
            'add_new' => __('Add New Quiz', 'sikshya'),
            'add_new_item' => __('Add New Quiz', 'sikshya'),
            'edit_item' => __('Edit Quiz', 'sikshya'),
            'new_item' => __('New Quiz', 'sikshya'),
            'view_item' => __('View Quiz', 'sikshya'),
            'search_items' => __('Search Quizzes', 'sikshya'),
            'not_found' => __('No quizzes found', 'sikshya'),
            'not_found_in_trash' => __('No quizzes found in trash', 'sikshya'),
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_admin_bar' => false,
            'query_var' => true,
            'rewrite' => [ 'slug' => $rewrite['rewrite_base_quiz'], 'with_front' => false ],
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'supports' => ['title', 'editor', 'custom-fields', 'revisions'],
            'show_in_rest' => true,
        ];

        register_post_type(PostTypes::QUIZ, $args);
    }

    /**
     * Register Assignment post type
     */
    public function registerAssignmentPostType(): void
    {
        $rewrite = PermalinkService::get();

        $labels = [
            'name' => __('Assignments', 'sikshya'),
            'singular_name' => __('Assignment', 'sikshya'),
            'menu_name' => __('Assignments', 'sikshya'),
            'add_new' => __('Add New Assignment', 'sikshya'),
            'add_new_item' => __('Add New Assignment', 'sikshya'),
            'edit_item' => __('Edit Assignment', 'sikshya'),
            'new_item' => __('New Assignment', 'sikshya'),
            'view_item' => __('View Assignment', 'sikshya'),
            'search_items' => __('Search Assignments', 'sikshya'),
            'not_found' => __('No assignments found', 'sikshya'),
            'not_found_in_trash' => __('No assignments found in trash', 'sikshya'),
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_admin_bar' => false,
            'query_var' => true,
            'rewrite' => [ 'slug' => $rewrite['rewrite_base_assignment'], 'with_front' => false ],
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'supports' => ['title', 'editor', 'custom-fields', 'revisions'],
            'show_in_rest' => true,
        ];

        register_post_type(PostTypes::ASSIGNMENT, $args);
    }

    /**
     * Register Certificate post type
     */
    public function registerCertificatePostType(): void
    {
        $rewrite = PermalinkService::get();

        $labels = [
            'name' => __('Certificates', 'sikshya'),
            'singular_name' => __('Certificate', 'sikshya'),
            'menu_name' => __('Certificates', 'sikshya'),
            'add_new' => __('Add New Certificate', 'sikshya'),
            'add_new_item' => __('Add New Certificate', 'sikshya'),
            'edit_item' => __('Edit Certificate', 'sikshya'),
            'new_item' => __('New Certificate', 'sikshya'),
            'view_item' => __('View Certificate', 'sikshya'),
            'search_items' => __('Search Certificates', 'sikshya'),
            'not_found' => __('No certificates found', 'sikshya'),
            'not_found_in_trash' => __('No certificates found in trash', 'sikshya'),
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_admin_bar' => false,
            'query_var' => true,
            'rewrite' => [ 'slug' => $rewrite['rewrite_base_certificate'], 'with_front' => false ],
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields', 'revisions'],
            'show_in_rest' => true,
        ];

        register_post_type(PostTypes::CERTIFICATE, $args);
    }

    /**
     * Quiz questions (optional CPT; also used from quiz meta in builder).
     */
    public function registerQuestionPostType(): void
    {
        $labels = [
            'name' => __('Questions', 'sikshya'),
            'singular_name' => __('Question', 'sikshya'),
            'menu_name' => __('Questions', 'sikshya'),
            'add_new' => __('Add New Question', 'sikshya'),
            'add_new_item' => __('Add New Question', 'sikshya'),
            'edit_item' => __('Edit Question', 'sikshya'),
            'new_item' => __('New Question', 'sikshya'),
            'view_item' => __('View Question', 'sikshya'),
            'search_items' => __('Search Questions', 'sikshya'),
            'not_found' => __('No questions found', 'sikshya'),
            'not_found_in_trash' => __('No questions found in trash', 'sikshya'),
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_admin_bar' => false,
            'hierarchical' => false,
            'supports' => ['title', 'editor', 'custom-fields'],
            'has_archive' => false,
            'rewrite' => false,
            'capability_type' => 'post',
            'show_in_rest' => true,
        ];

        register_post_type(PostTypes::QUESTION, $args);
    }

    /**
     * Course chapters (curriculum organization).
     */
    public function registerChapterPostType(): void
    {
        $labels = [
            'name' => __('Chapters', 'sikshya'),
            'singular_name' => __('Chapter', 'sikshya'),
            'menu_name' => __('Chapters', 'sikshya'),
            'add_new' => __('Add New Chapter', 'sikshya'),
            'add_new_item' => __('Add New Chapter', 'sikshya'),
            'edit_item' => __('Edit Chapter', 'sikshya'),
            'new_item' => __('New Chapter', 'sikshya'),
            'view_item' => __('View Chapter', 'sikshya'),
            'search_items' => __('Search Chapters', 'sikshya'),
            'not_found' => __('No chapters found', 'sikshya'),
            'not_found_in_trash' => __('No chapters found in trash', 'sikshya'),
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_admin_bar' => false,
            'hierarchical' => true,
            'supports' => ['title', 'editor', 'custom-fields'],
            'has_archive' => false,
            'rewrite' => false,
            'capability_type' => 'post',
            'show_in_rest' => true,
        ];

        register_post_type(PostTypes::CHAPTER, $args);
    }
}
