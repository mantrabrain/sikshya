<?php

/**
 * Sikshya LMS Post Type Manager
 *
 * Handles registration and management of all custom post types.
 *
 * @package Sikshya
 * @since 1.0.0
 */

namespace Sikshya\PostTypes;

use Sikshya\Constants\PostTypes;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Post Type Manager Class
 *
 * Manages the registration and configuration of all custom post types.
 */
class PostTypeManager
{
    /**
     * Initialize the post type manager
     */
    public function __construct()
    {
        add_action('init', [$this, 'registerPostTypes']);
        add_action('init', [$this, 'registerTaxonomies']);
        add_action('init', [$this, 'registerRestAccessiblePostMeta'], 20);
    }

    /**
     * Expose Sikshya meta keys to the block editor / REST API so the React admin can load & save them.
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

        // Course (sik_course) — list tables + React admin.
        $str_meta(PostTypes::COURSE, '_sikshya_course_price');
        $str_meta(PostTypes::COURSE, '_sikshya_course_duration');
        $str_meta(PostTypes::COURSE, '_sikshya_course_level');
        // Legacy sample / theme keys (mirror into canonical keys on save is optional; REST exposes for read).
        $str_meta(PostTypes::COURSE, '_sikshya_price');
        $str_meta(PostTypes::COURSE, '_sikshya_duration');
        $str_meta(PostTypes::COURSE, '_sikshya_difficulty');

        // Course type (free / paid / subscription / bundle) — used by pricing tab + bundle learn UX.
        register_post_meta(
            PostTypes::COURSE,
            '_sikshya_course_type',
            [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => $auth,
                'sanitize_callback' => static function ($meta_value): string {
                    $k = sanitize_key((string) $meta_value);

                    return in_array($k, ['free', 'paid', 'subscription', 'bundle'], true) ? $k : 'paid';
                },
            ]
        );

        register_post_meta(
            PostTypes::COURSE,
            '_sikshya_bundle_visible_in_listing',
            [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => $auth,
                'sanitize_callback' => static function ($meta_value): string {
                    return ($meta_value === '0' || $meta_value === 0 || $meta_value === false) ? '0' : '1';
                },
            ]
        );

        register_post_meta(
            PostTypes::COURSE,
            '_sikshya_learn_curriculum_sidebar_scrollable',
            [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => $auth,
                'sanitize_callback' => static function ($meta_value): string {
                    return ($meta_value === '1' || $meta_value === 1 || $meta_value === true) ? '1' : '0';
                },
            ]
        );

        $int_array_meta(PostTypes::COURSE, '_sikshya_bundle_course_ids');

        // Quiz (sik_quiz): _sikshya_quiz_* stored as sikshya_quiz_* in forms → DB key _sikshya_quiz_*.
        $int_meta(PostTypes::LESSON, '_sikshya_lesson_course');
        $int_meta(PostTypes::QUIZ, '_sikshya_quiz_course');
        $int_meta(PostTypes::QUIZ, '_sikshya_quiz_time_limit');
        $int_meta(PostTypes::QUIZ, '_sikshya_quiz_passing_score');
        $int_meta(PostTypes::QUIZ, '_sikshya_quiz_attempts_allowed');
        $int_array_meta(PostTypes::QUIZ, '_sikshya_quiz_questions');

        // Lesson (sik_lesson).
        $str_meta(PostTypes::LESSON, '_sikshya_lesson_duration');
        $str_meta(PostTypes::LESSON, '_sikshya_lesson_type');
        $str_meta(PostTypes::LESSON, '_sikshya_lesson_video_url');
        // Lesson: free preview toggle.
        $str_meta(PostTypes::LESSON, '_sikshya_is_free');

        // Question (sik_question).
        $str_meta(PostTypes::QUESTION, '_sikshya_question_type');
        $int_meta(PostTypes::QUESTION, '_sikshya_question_points');
        $string_array_meta(PostTypes::QUESTION, '_sikshya_question_options');
        $str_meta(PostTypes::QUESTION, '_sikshya_question_correct_answer');

        // Assignment (sik_assignment).
        $str_meta(PostTypes::ASSIGNMENT, '_sikshya_assignment_due_date');
        $int_meta(PostTypes::ASSIGNMENT, '_sikshya_assignment_points');
        $str_meta(PostTypes::ASSIGNMENT, '_sikshya_assignment_type');
        $int_meta(PostTypes::ASSIGNMENT, '_sikshya_assignment_course');

        // Chapter (sik_chapter).
        $int_meta(PostTypes::CHAPTER, '_sikshya_chapter_order');
        $int_meta(PostTypes::CHAPTER, '_sikshya_chapter_course_id');

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
            '_sikshya_certificate_accent_color',
            [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => $auth,
                'sanitize_callback' => static function ($meta_value) {
                    $c = sanitize_hex_color((string) $meta_value);

                    return $c ? $c : '';
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
                    // Must match CERT_PAGE_PATTERN_ORDER in client/src/pages/content-editors/certificateLayout.ts.
                    $allowed = ['none', 'dots', 'microdots', 'lines', 'grid', 'diagonals', 'papergrain'];

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
                    // Must match CERT_PAGE_DECO_ORDER in client/src/pages/content-editors/certificateLayout.ts (camelCase ids).
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

        register_post_meta(
            PostTypes::CERTIFICATE,
            '_sikshya_certificate_layout',
            [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => $auth,
                'sanitize_callback' => [self::class, 'sanitize_certificate_layout_string'],
            ]
        );

        // Public “View” / QR preview hash for certificate templates (not issued certificates).
        // 64-hex chars, stored in post meta so admin UI can build stable preview URLs.
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
    }

    /**
     * Sanitize JSON layout for the visual certificate builder (stored as JSON string).
     *
     * @param mixed $meta_value
     */
    public static function sanitize_certificate_layout_string($meta_value): string
    {
        if (!is_string($meta_value) || $meta_value === '') {
            return wp_json_encode(['version' => 2, 'blocks' => []]);
        }

        // Guardrails: avoid memory blow-ups from accidental base64 blobs or huge payloads.
        // JSON for a typical certificate is tiny (<10KB). Anything massive is likely user error.
        if (strlen($meta_value) > 200000) {
            return wp_json_encode(['version' => 2, 'blocks' => []]);
        }

        $decoded = json_decode(wp_unslash($meta_value), true);
        if (!is_array($decoded)) {
            return wp_json_encode(['version' => 2, 'blocks' => []]);
        }

        $blocks_in = isset($decoded['blocks']) && is_array($decoded['blocks']) ? $decoded['blocks'] : [];
        $blocks_out = [];

        foreach ($blocks_in as $b) {
            if (!is_array($b) || empty($b['type'])) {
                continue;
            }
            $clean = self::sanitize_certificate_block_array($b);
            if ($clean !== null) {
                $blocks_out[] = $clean;
            }
        }

        $ver_in = isset($decoded['version']) ? absint($decoded['version']) : 0;
        if ($ver_in < 1) {
            $ver_in = 2;
        }
        if ($ver_in > 100) {
            $ver_in = 100;
        }

        return wp_json_encode(
            [
                'version' => $ver_in,
                'blocks' => $blocks_out,
            ],
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * @param array<string, mixed> $b
     * @return array<string, mixed>|null
     */
    private static function sanitize_certificate_block_array(array $b): ?array
    {
        $type = sanitize_key((string) $b['type']);
        $allowed_types = ['heading', 'text', 'merge_field', 'spacer', 'divider', 'image', 'qr'];

        if (!in_array($type, $allowed_types, true)) {
            return null;
        }

        $id = isset($b['id']) && is_string($b['id']) && $b['id'] !== ''
            ? preg_replace('/[^a-zA-Z0-9_-]/', '', $b['id'])
            : '';
        if ($id === '') {
            $id = 'b_' . wp_generate_password(8, false, false);
        }

        $raw_props = isset($b['props']) && is_array($b['props']) ? $b['props'] : [];
        $props = $raw_props;

        switch ($type) {
            case 'heading':
                $props = [
                    'text' => wp_kses_post((string) ($props['text'] ?? '')),
                    'tag' => in_array($props['tag'] ?? 'h1', ['h1', 'h2', 'h3'], true) ? $props['tag'] : 'h1',
                    'align' => in_array($props['align'] ?? 'center', ['left', 'center', 'right'], true) ? $props['align'] : 'center',
                    'fontSize' => max(10, min(96, absint($props['fontSize'] ?? 28))),
                    'color' => sanitize_hex_color((string) ($props['color'] ?? '')) ?: '#0f172a',
                    'fontWeight' => in_array((string) ($props['fontWeight'] ?? '700'), ['400', '500', '600', '700', 'normal', 'bold'], true)
                        ? (string) $props['fontWeight']
                        : '700',
                    'fontFamily' => in_array((string) ($raw_props['fontFamily'] ?? 'serif'), ['sans', 'serif', 'mono'], true)
                        ? (string) $raw_props['fontFamily']
                        : 'serif',
                    'lineHeight' => max(1.0, min(2.4, (float) ($raw_props['lineHeight'] ?? 1.12))),
                    'letterSpacing' => max(-0.05, min(0.5, (float) ($raw_props['letterSpacing'] ?? 0.0))),
                ];
                break;
            case 'text':
                $props = [
                    'text' => wp_kses_post((string) ($props['text'] ?? '')),
                    'align' => in_array($props['align'] ?? 'left', ['left', 'center', 'right'], true) ? $props['align'] : 'left',
                    'fontSize' => max(10, min(48, absint($props['fontSize'] ?? 14))),
                    'color' => sanitize_hex_color((string) ($props['color'] ?? '')) ?: '#334155',
                    'fontWeight' => in_array((string) ($raw_props['fontWeight'] ?? '400'), ['400', '500', '600', '700', 'normal', 'bold'], true)
                        ? (string) $raw_props['fontWeight']
                        : '400',
                    'fontFamily' => in_array((string) ($raw_props['fontFamily'] ?? 'sans'), ['sans', 'serif', 'mono'], true)
                        ? (string) $raw_props['fontFamily']
                        : 'sans',
                    'lineHeight' => max(1.0, min(2.4, (float) ($raw_props['lineHeight'] ?? 1.5))),
                    'letterSpacing' => max(-0.05, min(0.5, (float) ($raw_props['letterSpacing'] ?? 0.0))),
                ];
                break;
            case 'merge_field':
                $field = sanitize_key((string) ($props['field'] ?? 'student_name'));
                $fields = [
                    'student_name',
                    'course_name',
                    'instructor_name',
                    'completion_date',
                    'completion_time',
                    'duration',
                    'points',
                    'grade',
                    'certificate_number',
                    'verification_code',
                    'site_name',
                ];
                if (!in_array($field, $fields, true)) {
                    $field = 'student_name';
                }
                $props = [
                    'field' => $field,
                    'align' => in_array($props['align'] ?? 'center', ['left', 'center', 'right'], true) ? $props['align'] : 'center',
                    'fontSize' => max(10, min(72, absint($props['fontSize'] ?? 22))),
                    'color' => sanitize_hex_color((string) ($props['color'] ?? '')) ?: '#0f172a',
                    'fontWeight' => in_array((string) ($raw_props['fontWeight'] ?? '600'), ['400', '500', '600', '700', 'normal', 'bold'], true)
                        ? (string) $raw_props['fontWeight']
                        : '600',
                    'fontFamily' => in_array((string) ($raw_props['fontFamily'] ?? 'sans'), ['sans', 'serif', 'mono'], true)
                        ? (string) $raw_props['fontFamily']
                        : 'sans',
                    'lineHeight' => max(1.0, min(2.4, (float) ($raw_props['lineHeight'] ?? 1.2))),
                    'letterSpacing' => max(-0.05, min(0.5, (float) ($raw_props['letterSpacing'] ?? 0.0))),
                ];
                break;
            case 'spacer':
                $props = ['height' => max(0, min(400, absint($props['height'] ?? 24)))];
                break;
            case 'divider':
                $props = [
                    'color' => sanitize_hex_color((string) ($props['color'] ?? '')) ?: '#cbd5e1',
                    'thickness' => max(1, min(20, absint($props['thickness'] ?? 2))),
                ];
                break;
            case 'image':
                $src_raw = (string) ($props['src'] ?? '');
                // Disallow data URIs; they can be enormous and are not suitable for storage in meta.
                if (stripos($src_raw, 'data:') === 0) {
                    $src_raw = '';
                }
                $props = [
                    'src' => esc_url_raw($src_raw),
                    'width' => max(20, min(800, absint($props['width'] ?? 120))),
                    'align' => in_array($props['align'] ?? 'center', ['left', 'center', 'right'], true) ? $props['align'] : 'center',
                ];
                break;
            case 'qr':
                $props = [
                    // Used only for sizing the QR image placeholder inside the template.
                    // The actual QR <img> is injected by server-side rendering with a real verification URL.
                    'size' => max(80, min(260, absint($props['size'] ?? 140))),
                ];
                break;
            default:
                return null;
        }

        $geom = self::sanitize_certificate_block_geometry($raw_props);
        $props = array_merge($props, $geom);

        return ['id' => $id, 'type' => $type, 'props' => $props];
    }

    /**
     * @param array<string, mixed> $raw_props
     * @return array<string, float|int>
     */
    private static function sanitize_certificate_block_geometry(array $raw_props): array
    {
        $x = isset($raw_props['x']) ? (float) $raw_props['x'] : 0.0;
        $y = isset($raw_props['y']) ? (float) $raw_props['y'] : 0.0;
        $w = isset($raw_props['w']) ? (float) $raw_props['w'] : 50.0;
        $h = isset($raw_props['h']) ? (float) $raw_props['h'] : 12.0;
        $z = isset($raw_props['z']) ? absint($raw_props['z']) : 1;
        if ($z < 0) {
            $z = 0;
        }
        if ($z > 200) {
            $z = 200;
        }

        return [
            'x' => (float) max(0, min(100, $x)),
            'y' => (float) max(0, min(100, $y)),
            'w' => (float) max(5, min(100, $w)),
            'h' => (float) max(2, min(100, $h)),
            'z' => (int) $z,
        ];
    }

    /**
     * Register all custom post types
     */
    public function registerPostTypes()
    {
        $this->registerCoursePostType();
        $this->registerLessonPostType();
        $this->registerAssignmentPostType();
        $this->registerQuizPostType();
        $this->registerQuestionPostType();
        $this->registerChapterPostType();
    }

    /**
     * Register Course Post Type
     */
    private function registerCoursePostType()
    {
        $course = function_exists('sikshya_label') ? sikshya_label('course', __('Course', 'sikshya'), 'admin') : __('Course', 'sikshya');
        $courses = function_exists('sikshya_label_plural') ? sikshya_label_plural('course', 'courses', __('Courses', 'sikshya'), 'admin') : __('Courses', 'sikshya');
        $labels = [
            'name'               => $courses,
            'singular_name'      => $course,
            'menu_name'          => $courses,
            /* translators: %s: singular label */
            'add_new'            => sprintf(__('Add New %s', 'sikshya'), $course),
            /* translators: %s: singular label */
            'add_new_item'       => sprintf(__('Add New %s', 'sikshya'), $course),
            /* translators: %s: singular label */
            'edit_item'          => sprintf(__('Edit %s', 'sikshya'), $course),
            /* translators: %s: singular label */
            'new_item'           => sprintf(__('New %s', 'sikshya'), $course),
            /* translators: %s: singular label */
            'view_item'          => sprintf(__('View %s', 'sikshya'), $course),
            /* translators: %s: plural label */
            'search_items'       => sprintf(__('Search %s', 'sikshya'), $courses),
            /* translators: %s: plural label */
            'not_found'          => sprintf(__('No %s found', 'sikshya'), strtolower($courses)),
            /* translators: %s: plural label */
            'not_found_in_trash' => sprintf(__('No %s found in trash', 'sikshya'), strtolower($courses)),
        ];

        $args = [
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_admin_bar'   => true,
            'menu_position'       => 5,
            'menu_icon'           => 'dashicons-welcome-learn-more',
            'hierarchical'        => false,
            'supports'            => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
            'has_archive'         => true,
            'rewrite'             => ['slug' => 'courses'],
            'capability_type'     => 'post',
            'show_in_rest'        => true,
        ];

        register_post_type(PostTypes::COURSE, $args);
    }

    /**
     * Register Lesson Post Type
     */
    private function registerLessonPostType()
    {
        $lesson = function_exists('sikshya_label') ? sikshya_label('lesson', __('Lesson', 'sikshya'), 'admin') : __('Lesson', 'sikshya');
        $lessons = function_exists('sikshya_label_plural') ? sikshya_label_plural('lesson', 'lessons', __('Lessons', 'sikshya'), 'admin') : __('Lessons', 'sikshya');
        $labels = [
            'name'               => $lessons,
            'singular_name'      => $lesson,
            'menu_name'          => $lessons,
            /* translators: %s: singular label */
            'add_new'            => sprintf(__('Add New %s', 'sikshya'), $lesson),
            /* translators: %s: singular label */
            'add_new_item'       => sprintf(__('Add New %s', 'sikshya'), $lesson),
            /* translators: %s: singular label */
            'edit_item'          => sprintf(__('Edit %s', 'sikshya'), $lesson),
            /* translators: %s: singular label */
            'new_item'           => sprintf(__('New %s', 'sikshya'), $lesson),
            /* translators: %s: singular label */
            'view_item'          => sprintf(__('View %s', 'sikshya'), $lesson),
            /* translators: %s: plural label */
            'search_items'       => sprintf(__('Search %s', 'sikshya'), $lessons),
            /* translators: %s: plural label */
            'not_found'          => sprintf(__('No %s found', 'sikshya'), strtolower($lessons)),
            /* translators: %s: plural label */
            'not_found_in_trash' => sprintf(__('No %s found in trash', 'sikshya'), strtolower($lessons)),
        ];

        $args = [
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_admin_bar'   => true,
            'menu_position'       => 6,
            'menu_icon'           => 'dashicons-book',
            'hierarchical'        => false,
            'supports'            => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
            'has_archive'         => false,
            'rewrite'             => ['slug' => 'lessons'],
            'capability_type'     => 'post',
            'show_in_rest'        => true,
        ];

        register_post_type(PostTypes::LESSON, $args);
    }

    /**
     * Register Assignment Post Type
     */
    private function registerAssignmentPostType()
    {
        $assignment = function_exists('sikshya_label') ? sikshya_label('assignment', __('Assignment', 'sikshya'), 'admin') : __('Assignment', 'sikshya');
        $assignments = function_exists('sikshya_label_plural') ? sikshya_label_plural('assignment', 'assignments', __('Assignments', 'sikshya'), 'admin') : __('Assignments', 'sikshya');
        $labels = [
            'name'               => $assignments,
            'singular_name'      => $assignment,
            'menu_name'          => $assignments,
            /* translators: %s: singular label */
            'add_new'            => sprintf(__('Add New %s', 'sikshya'), $assignment),
            /* translators: %s: singular label */
            'add_new_item'       => sprintf(__('Add New %s', 'sikshya'), $assignment),
            /* translators: %s: singular label */
            'edit_item'          => sprintf(__('Edit %s', 'sikshya'), $assignment),
            /* translators: %s: singular label */
            'new_item'           => sprintf(__('New %s', 'sikshya'), $assignment),
            /* translators: %s: singular label */
            'view_item'          => sprintf(__('View %s', 'sikshya'), $assignment),
            /* translators: %s: plural label */
            'search_items'       => sprintf(__('Search %s', 'sikshya'), $assignments),
            /* translators: %s: plural label */
            'not_found'          => sprintf(__('No %s found', 'sikshya'), strtolower($assignments)),
            /* translators: %s: plural label */
            'not_found_in_trash' => sprintf(__('No %s found in trash', 'sikshya'), strtolower($assignments)),
        ];

        $args = [
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_admin_bar'   => true,
            'menu_position'       => 7,
            'menu_icon'           => 'dashicons-clipboard',
            'hierarchical'        => false,
            'supports'            => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
            'has_archive'         => false,
            'rewrite'             => ['slug' => 'assignments'],
            'capability_type'     => 'post',
            'show_in_rest'        => true,
        ];

        register_post_type(PostTypes::ASSIGNMENT, $args);
    }

    /**
     * Register Quiz Post Type
     */
    private function registerQuizPostType()
    {
        $quiz = function_exists('sikshya_label') ? sikshya_label('quiz', __('Quiz', 'sikshya'), 'admin') : __('Quiz', 'sikshya');
        $quizzes = function_exists('sikshya_label_plural') ? sikshya_label_plural('quiz', 'quizzes', __('Quizzes', 'sikshya'), 'admin') : __('Quizzes', 'sikshya');
        $labels = [
            'name'               => $quizzes,
            'singular_name'      => $quiz,
            'menu_name'          => $quizzes,
            /* translators: %s: singular label */
            'add_new'            => sprintf(__('Add New %s', 'sikshya'), $quiz),
            /* translators: %s: singular label */
            'add_new_item'       => sprintf(__('Add New %s', 'sikshya'), $quiz),
            /* translators: %s: singular label */
            'edit_item'          => sprintf(__('Edit %s', 'sikshya'), $quiz),
            /* translators: %s: singular label */
            'new_item'           => sprintf(__('New %s', 'sikshya'), $quiz),
            /* translators: %s: singular label */
            'view_item'          => sprintf(__('View %s', 'sikshya'), $quiz),
            /* translators: %s: plural label */
            'search_items'       => sprintf(__('Search %s', 'sikshya'), $quizzes),
            /* translators: %s: plural label */
            'not_found'          => sprintf(__('No %s found', 'sikshya'), strtolower($quizzes)),
            /* translators: %s: plural label */
            'not_found_in_trash' => sprintf(__('No %s found in trash', 'sikshya'), strtolower($quizzes)),
        ];

        $args = [
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_admin_bar'   => true,
            'menu_position'       => 8,
            'menu_icon'           => 'dashicons-forms',
            'hierarchical'        => false,
            'supports'            => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
            'has_archive'         => false,
            'rewrite'             => ['slug' => 'quizzes'],
            'capability_type'     => 'post',
            'show_in_rest'        => true,
        ];

        register_post_type(PostTypes::QUIZ, $args);
    }

    /**
     * Register Question Post Type
     */
    private function registerQuestionPostType()
    {
        $labels = [
            'name'               => __('Questions', 'sikshya'),
            'singular_name'      => __('Question', 'sikshya'),
            'menu_name'          => __('Questions', 'sikshya'),
            'add_new'            => __('Add New Question', 'sikshya'),
            'add_new_item'       => __('Add New Question', 'sikshya'),
            'edit_item'          => __('Edit Question', 'sikshya'),
            'new_item'           => __('New Question', 'sikshya'),
            'view_item'          => __('View Question', 'sikshya'),
            'search_items'       => __('Search Questions', 'sikshya'),
            'not_found'          => __('No questions found', 'sikshya'),
            'not_found_in_trash' => __('No questions found in trash', 'sikshya'),
        ];

        $args = [
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_admin_bar'   => false,
            'menu_position'       => 9,
            'menu_icon'           => 'dashicons-editor-help',
            'hierarchical'        => false,
            'supports'            => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'has_archive'         => false,
            'rewrite'             => false,
            'capability_type'     => 'post',
            'show_in_rest'        => true,
        ];

        register_post_type(PostTypes::QUESTION, $args);
    }

    /**
     * Register Chapter Post Type
     */
    private function registerChapterPostType()
    {
        $chapter = function_exists('sikshya_label') ? sikshya_label('chapter', __('Chapter', 'sikshya'), 'admin') : __('Chapter', 'sikshya');
        $chapters = function_exists('sikshya_label_plural') ? sikshya_label_plural('chapter', 'chapters', __('Chapters', 'sikshya'), 'admin') : __('Chapters', 'sikshya');
        $labels = [
            'name'               => $chapters,
            'singular_name'      => $chapter,
            'menu_name'          => $chapters,
            /* translators: %s: singular label */
            'add_new'            => sprintf(__('Add New %s', 'sikshya'), $chapter),
            /* translators: %s: singular label */
            'add_new_item'       => sprintf(__('Add New %s', 'sikshya'), $chapter),
            /* translators: %s: singular label */
            'edit_item'          => sprintf(__('Edit %s', 'sikshya'), $chapter),
            /* translators: %s: singular label */
            'new_item'           => sprintf(__('New %s', 'sikshya'), $chapter),
            /* translators: %s: singular label */
            'view_item'          => sprintf(__('View %s', 'sikshya'), $chapter),
            /* translators: %s: plural label */
            'search_items'       => sprintf(__('Search %s', 'sikshya'), $chapters),
            /* translators: %s: plural label */
            'not_found'          => sprintf(__('No %s found', 'sikshya'), strtolower($chapters)),
            /* translators: %s: plural label */
            'not_found_in_trash' => sprintf(__('No %s found in trash', 'sikshya'), strtolower($chapters)),
        ];

        $args = [
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_admin_bar'   => false,
            'menu_position'       => 10,
            'menu_icon'           => 'dashicons-list-view',
            'hierarchical'        => true,
            'supports'            => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'has_archive'         => false,
            'rewrite'             => false,
            'capability_type'     => 'post',
            'show_in_rest'        => true,
        ];

        register_post_type(PostTypes::CHAPTER, $args);
    }

    /**
     * Register taxonomies
     */
    public function registerTaxonomies()
    {
        // Course Categories
        $course = function_exists('sikshya_label') ? sikshya_label('course', __('Course', 'sikshya'), 'admin') : __('Course', 'sikshya');
        $courses = function_exists('sikshya_label_plural') ? sikshya_label_plural('course', 'courses', __('Courses', 'sikshya'), 'admin') : __('Courses', 'sikshya');
        $course_categories = sprintf(
            /* translators: %s: plural label (e.g. Courses) */
            __('%s Categories', 'sikshya'),
            $courses
        );
        $course_category = sprintf(
            /* translators: %s: singular label (e.g. Course) */
            __('%s Category', 'sikshya'),
            $course
        );
        register_taxonomy('sik_course_category', [PostTypes::COURSE], [
            'labels' => [
                'name'              => $course_categories,
                'singular_name'     => $course_category,
                /* translators: %s: plural label */
                'search_items'      => sprintf(__('Search %s Categories', 'sikshya'), $courses),
                /* translators: %s: plural label */
                'all_items'         => sprintf(__('All %s Categories', 'sikshya'), $courses),
                /* translators: %s: singular label */
                'parent_item'       => sprintf(__('Parent %s Category', 'sikshya'), $course),
                /* translators: %s: singular label */
                'parent_item_colon' => sprintf(__('Parent %s Category:', 'sikshya'), $course),
                /* translators: %s: singular label */
                'edit_item'         => sprintf(__('Edit %s Category', 'sikshya'), $course),
                /* translators: %s: singular label */
                'update_item'       => sprintf(__('Update %s Category', 'sikshya'), $course),
                /* translators: %s: singular label */
                'add_new_item'      => sprintf(__('Add New %s Category', 'sikshya'), $course),
                /* translators: %s: singular label */
                'new_item_name'     => sprintf(__('New %s Category Name', 'sikshya'), $course),
                'menu_name'         => $course_categories,
            ],
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'course-category'],
            'show_in_rest'      => true,
        ]);

        // Course Tags
        register_taxonomy('sik_course_tag', [PostTypes::COURSE], [
            'labels' => [
                /* translators: %s: plural label */
                'name'                       => sprintf(__('%s Tags', 'sikshya'), $courses),
                /* translators: %s: singular label */
                'singular_name'              => sprintf(__('%s Tag', 'sikshya'), $course),
                /* translators: %s: plural label */
                'search_items'               => sprintf(__('Search %s Tags', 'sikshya'), $courses),
                /* translators: %s: plural label */
                'popular_items'              => sprintf(__('Popular %s Tags', 'sikshya'), $courses),
                /* translators: %s: plural label */
                'all_items'                  => sprintf(__('All %s Tags', 'sikshya'), $courses),
                'parent_item'                => null,
                'parent_item_colon'          => null,
                /* translators: %s: singular label */
                'edit_item'                  => sprintf(__('Edit %s Tag', 'sikshya'), $course),
                /* translators: %s: singular label */
                'update_item'                => sprintf(__('Update %s Tag', 'sikshya'), $course),
                /* translators: %s: singular label */
                'add_new_item'               => sprintf(__('Add New %s Tag', 'sikshya'), $course),
                /* translators: %s: singular label */
                'new_item_name'              => sprintf(__('New %s Tag Name', 'sikshya'), $course),
                /* translators: %s: plural label */
                'separate_items_with_commas' => sprintf(__('Separate %s tags with commas', 'sikshya'), strtolower($courses)),
                /* translators: %s: plural label */
                'add_or_remove_items'        => sprintf(__('Add or remove %s tags', 'sikshya'), strtolower($courses)),
                /* translators: %s: plural label */
                'choose_from_most_used'      => sprintf(__('Choose from the most used %s tags', 'sikshya'), strtolower($courses)),
                /* translators: %s: plural label */
                'not_found'                  => sprintf(__('No %s tags found.', 'sikshya'), strtolower($courses)),
                /* translators: %s: plural label */
                'menu_name'                  => sprintf(__('%s Tags', 'sikshya'), $courses),
            ],
            'hierarchical'          => false,
            'show_ui'               => true,
            'show_admin_column'     => true,
            'update_count_callback' => '_update_post_term_count',
            'query_var'             => true,
            'rewrite'               => ['slug' => 'course-tag'],
            'show_in_rest'          => true,
        ]);
    }

}
