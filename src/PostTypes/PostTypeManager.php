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
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post', [$this, 'saveMetaData']);
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
            '_sikshya_certificate_layout',
            [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => $auth,
                'sanitize_callback' => [self::class, 'sanitize_certificate_layout_string'],
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
            return wp_json_encode(['version' => 1, 'blocks' => []]);
        }

        $decoded = json_decode(wp_unslash($meta_value), true);
        if (!is_array($decoded)) {
            return wp_json_encode(['version' => 1, 'blocks' => []]);
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

        return wp_json_encode(
            [
                'version' => 1,
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
        $allowed_types = ['heading', 'text', 'merge_field', 'spacer', 'divider', 'image'];

        if (!in_array($type, $allowed_types, true)) {
            return null;
        }

        $id = isset($b['id']) && is_string($b['id']) && $b['id'] !== ''
            ? preg_replace('/[^a-zA-Z0-9_-]/', '', $b['id'])
            : '';
        if ($id === '') {
            $id = 'b_' . wp_generate_password(8, false, false);
        }

        $props = isset($b['props']) && is_array($b['props']) ? $b['props'] : [];

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
                ];
                break;
            case 'text':
                $props = [
                    'text' => wp_kses_post((string) ($props['text'] ?? '')),
                    'align' => in_array($props['align'] ?? 'left', ['left', 'center', 'right'], true) ? $props['align'] : 'left',
                    'fontSize' => max(10, min(48, absint($props['fontSize'] ?? 14))),
                    'color' => sanitize_hex_color((string) ($props['color'] ?? '')) ?: '#334155',
                ];
                break;
            case 'merge_field':
                $field = sanitize_key((string) ($props['field'] ?? 'student_name'));
                $fields = ['student_name', 'course_name', 'completion_date', 'certificate_id', 'site_name'];
                if (!in_array($field, $fields, true)) {
                    $field = 'student_name';
                }
                $props = [
                    'field' => $field,
                    'align' => in_array($props['align'] ?? 'center', ['left', 'center', 'right'], true) ? $props['align'] : 'center',
                    'fontSize' => max(10, min(72, absint($props['fontSize'] ?? 22))),
                    'color' => sanitize_hex_color((string) ($props['color'] ?? '')) ?: '#0f172a',
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
                $props = [
                    'src' => esc_url_raw((string) ($props['src'] ?? '')),
                    'width' => max(20, min(800, absint($props['width'] ?? 120))),
                    'align' => in_array($props['align'] ?? 'center', ['left', 'center', 'right'], true) ? $props['align'] : 'center',
                ];
                break;
            default:
                return null;
        }

        return ['id' => $id, 'type' => $type, 'props' => $props];
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
        $labels = [
            'name'               => __('Courses', 'sikshya'),
            'singular_name'      => __('Course', 'sikshya'),
            'menu_name'          => __('Courses', 'sikshya'),
            'add_new'            => __('Add New Course', 'sikshya'),
            'add_new_item'       => __('Add New Course', 'sikshya'),
            'edit_item'          => __('Edit Course', 'sikshya'),
            'new_item'           => __('New Course', 'sikshya'),
            'view_item'          => __('View Course', 'sikshya'),
            'search_items'       => __('Search Courses', 'sikshya'),
            'not_found'          => __('No courses found', 'sikshya'),
            'not_found_in_trash' => __('No courses found in trash', 'sikshya'),
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
        $labels = [
            'name'               => __('Lessons', 'sikshya'),
            'singular_name'      => __('Lesson', 'sikshya'),
            'menu_name'          => __('Lessons', 'sikshya'),
            'add_new'            => __('Add New Lesson', 'sikshya'),
            'add_new_item'       => __('Add New Lesson', 'sikshya'),
            'edit_item'          => __('Edit Lesson', 'sikshya'),
            'new_item'           => __('New Lesson', 'sikshya'),
            'view_item'          => __('View Lesson', 'sikshya'),
            'search_items'       => __('Search Lessons', 'sikshya'),
            'not_found'          => __('No lessons found', 'sikshya'),
            'not_found_in_trash' => __('No lessons found in trash', 'sikshya'),
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
        $labels = [
            'name'               => __('Assignments', 'sikshya'),
            'singular_name'      => __('Assignment', 'sikshya'),
            'menu_name'          => __('Assignments', 'sikshya'),
            'add_new'            => __('Add New Assignment', 'sikshya'),
            'add_new_item'       => __('Add New Assignment', 'sikshya'),
            'edit_item'          => __('Edit Assignment', 'sikshya'),
            'new_item'           => __('New Assignment', 'sikshya'),
            'view_item'          => __('View Assignment', 'sikshya'),
            'search_items'       => __('Search Assignments', 'sikshya'),
            'not_found'          => __('No assignments found', 'sikshya'),
            'not_found_in_trash' => __('No assignments found in trash', 'sikshya'),
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
        $labels = [
            'name'               => __('Quizzes', 'sikshya'),
            'singular_name'      => __('Quiz', 'sikshya'),
            'menu_name'          => __('Quizzes', 'sikshya'),
            'add_new'            => __('Add New Quiz', 'sikshya'),
            'add_new_item'       => __('Add New Quiz', 'sikshya'),
            'edit_item'          => __('Edit Quiz', 'sikshya'),
            'new_item'           => __('New Quiz', 'sikshya'),
            'view_item'          => __('View Quiz', 'sikshya'),
            'search_items'       => __('Search Quizzes', 'sikshya'),
            'not_found'          => __('No quizzes found', 'sikshya'),
            'not_found_in_trash' => __('No quizzes found in trash', 'sikshya'),
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
            'supports'            => ['title', 'editor', 'custom-fields'],
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
        $labels = [
            'name'               => __('Chapters', 'sikshya'),
            'singular_name'      => __('Chapter', 'sikshya'),
            'menu_name'          => __('Chapters', 'sikshya'),
            'add_new'            => __('Add New Chapter', 'sikshya'),
            'add_new_item'       => __('Add New Chapter', 'sikshya'),
            'edit_item'          => __('Edit Chapter', 'sikshya'),
            'new_item'           => __('New Chapter', 'sikshya'),
            'view_item'          => __('View Chapter', 'sikshya'),
            'search_items'       => __('Search Chapters', 'sikshya'),
            'not_found'          => __('No chapters found', 'sikshya'),
            'not_found_in_trash' => __('No chapters found in trash', 'sikshya'),
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
            'supports'            => ['title', 'editor', 'custom-fields'],
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
        register_taxonomy('sik_course_category', [PostTypes::COURSE], [
            'labels' => [
                'name'              => __('Course Categories', 'sikshya'),
                'singular_name'     => __('Course Category', 'sikshya'),
                'search_items'      => __('Search Course Categories', 'sikshya'),
                'all_items'         => __('All Course Categories', 'sikshya'),
                'parent_item'       => __('Parent Course Category', 'sikshya'),
                'parent_item_colon' => __('Parent Course Category:', 'sikshya'),
                'edit_item'         => __('Edit Course Category', 'sikshya'),
                'update_item'       => __('Update Course Category', 'sikshya'),
                'add_new_item'      => __('Add New Course Category', 'sikshya'),
                'new_item_name'     => __('New Course Category Name', 'sikshya'),
                'menu_name'         => __('Course Categories', 'sikshya'),
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
                'name'                       => __('Course Tags', 'sikshya'),
                'singular_name'              => __('Course Tag', 'sikshya'),
                'search_items'               => __('Search Course Tags', 'sikshya'),
                'popular_items'              => __('Popular Course Tags', 'sikshya'),
                'all_items'                  => __('All Course Tags', 'sikshya'),
                'parent_item'                => null,
                'parent_item_colon'          => null,
                'edit_item'                  => __('Edit Course Tag', 'sikshya'),
                'update_item'                => __('Update Course Tag', 'sikshya'),
                'add_new_item'               => __('Add New Course Tag', 'sikshya'),
                'new_item_name'              => __('New Course Tag Name', 'sikshya'),
                'separate_items_with_commas' => __('Separate course tags with commas', 'sikshya'),
                'add_or_remove_items'        => __('Add or remove course tags', 'sikshya'),
                'choose_from_most_used'      => __('Choose from the most used course tags', 'sikshya'),
                'not_found'                  => __('No course tags found.', 'sikshya'),
                'menu_name'                  => __('Course Tags', 'sikshya'),
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

    /**
     * Add meta boxes for post types
     */
    public function addMetaBoxes()
    {
        // Course meta boxes
        add_meta_box(
            'sikshya_course_settings',
            __('Course Settings', 'sikshya'),
            [$this, 'renderCourseMetaBox'],
            PostTypes::COURSE,
            'normal',
            'high'
        );

        // Lesson meta boxes
        add_meta_box(
            'sikshya_lesson_settings',
            __('Lesson Settings', 'sikshya'),
            [$this, 'renderLessonMetaBox'],
            PostTypes::LESSON,
            'normal',
            'high'
        );

        // Assignment meta boxes
        add_meta_box(
            'sikshya_assignment_settings',
            __('Assignment Settings', 'sikshya'),
            [$this, 'renderAssignmentMetaBox'],
            PostTypes::ASSIGNMENT,
            'normal',
            'high'
        );

        // Quiz meta boxes
        add_meta_box(
            'sikshya_quiz_settings',
            __('Quiz Settings', 'sikshya'),
            [$this, 'renderQuizMetaBox'],
            PostTypes::QUIZ,
            'normal',
            'high'
        );

        // Question meta boxes
        add_meta_box(
            'sikshya_question_settings',
            __('Question Settings', 'sikshya'),
            [$this, 'renderQuestionMetaBox'],
            PostTypes::QUESTION,
            'normal',
            'high'
        );

        // Chapter meta boxes
        add_meta_box(
            'sikshya_chapter_settings',
            __('Chapter Settings', 'sikshya'),
            [$this, 'renderChapterMetaBox'],
            PostTypes::CHAPTER,
            'normal',
            'high'
        );
    }

    /**
     * Render Course Meta Box
     */
    public function renderCourseMetaBox($post)
    {
        wp_nonce_field('sikshya_course_meta_box', 'sikshya_course_meta_box_nonce');

        $course_price = get_post_meta($post->ID, '_sikshya_course_price', true);
        $course_duration = get_post_meta($post->ID, '_sikshya_course_duration', true);
        $course_level = get_post_meta($post->ID, '_sikshya_course_level', true);
        $course_status = get_post_meta($post->ID, '_sikshya_course_status', true);

        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="sikshya_course_price"><?php _e('Course Price', 'sikshya'); ?></label>
                </th>
                <td>
                    <input type="number" id="sikshya_course_price" name="sikshya_course_price" 
                           value="<?php echo esc_attr($course_price); ?>" step="0.01" min="0" />
                    <p class="description"><?php _e('Set the course price (0 for free courses)', 'sikshya'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sikshya_course_duration"><?php _e('Course Duration', 'sikshya'); ?></label>
                </th>
                <td>
                    <input type="text" id="sikshya_course_duration" name="sikshya_course_duration" 
                           value="<?php echo esc_attr($course_duration); ?>" />
                    <p class="description"><?php _e('e.g., 10 hours, 2 weeks', 'sikshya'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sikshya_course_level"><?php _e('Course Level', 'sikshya'); ?></label>
                </th>
                <td>
                    <select id="sikshya_course_level" name="sikshya_course_level">
                        <option value=""><?php _e('Select Level', 'sikshya'); ?></option>
                        <option value="beginner" <?php selected($course_level, 'beginner'); ?>><?php _e('Beginner', 'sikshya'); ?></option>
                        <option value="intermediate" <?php selected($course_level, 'intermediate'); ?>><?php _e('Intermediate', 'sikshya'); ?></option>
                        <option value="advanced" <?php selected($course_level, 'advanced'); ?>><?php _e('Advanced', 'sikshya'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sikshya_course_status"><?php _e('Course Status', 'sikshya'); ?></label>
                </th>
                <td>
                    <select id="sikshya_course_status" name="sikshya_course_status">
                        <option value="draft" <?php selected($course_status, 'draft'); ?>><?php _e('Draft', 'sikshya'); ?></option>
                        <option value="published" <?php selected($course_status, 'published'); ?>><?php _e('Published', 'sikshya'); ?></option>
                        <option value="archived" <?php selected($course_status, 'archived'); ?>><?php _e('Archived', 'sikshya'); ?></option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render Lesson Meta Box
     */
    public function renderLessonMetaBox($post)
    {
        wp_nonce_field('sikshya_lesson_meta_box', 'sikshya_lesson_meta_box_nonce');

        $lesson_duration = get_post_meta($post->ID, '_sikshya_lesson_duration', true);
        $lesson_type = get_post_meta($post->ID, '_sikshya_lesson_type', true);
        $lesson_video_url = get_post_meta($post->ID, '_sikshya_lesson_video_url', true);

        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="sikshya_lesson_duration"><?php _e('Lesson Duration', 'sikshya'); ?></label>
                </th>
                <td>
                    <input type="text" id="sikshya_lesson_duration" name="sikshya_lesson_duration" 
                           value="<?php echo esc_attr($lesson_duration); ?>" />
                    <p class="description"><?php _e('e.g., 15 minutes, 1 hour', 'sikshya'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sikshya_lesson_type"><?php _e('Lesson Type', 'sikshya'); ?></label>
                </th>
                <td>
                    <select id="sikshya_lesson_type" name="sikshya_lesson_type">
                        <option value=""><?php _e('Select Type', 'sikshya'); ?></option>
                        <option value="video" <?php selected($lesson_type, 'video'); ?>><?php _e('Video', 'sikshya'); ?></option>
                        <option value="text" <?php selected($lesson_type, 'text'); ?>><?php _e('Text', 'sikshya'); ?></option>
                        <option value="audio" <?php selected($lesson_type, 'audio'); ?>><?php _e('Audio', 'sikshya'); ?></option>
                        <option value="document" <?php selected($lesson_type, 'document'); ?>><?php _e('Document', 'sikshya'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sikshya_lesson_video_url"><?php _e('Video URL', 'sikshya'); ?></label>
                </th>
                <td>
                    <input type="url" id="sikshya_lesson_video_url" name="sikshya_lesson_video_url" 
                           value="<?php echo esc_attr($lesson_video_url); ?>" class="regular-text" />
                    <p class="description"><?php _e('YouTube, Vimeo, or direct video URL', 'sikshya'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render Assignment Meta Box
     */
    public function renderAssignmentMetaBox($post)
    {
        wp_nonce_field('sikshya_assignment_meta_box', 'sikshya_assignment_meta_box_nonce');

        $assignment_due_date = get_post_meta($post->ID, '_sikshya_assignment_due_date', true);
        $assignment_points = get_post_meta($post->ID, '_sikshya_assignment_points', true);
        $assignment_type = get_post_meta($post->ID, '_sikshya_assignment_type', true);

        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="sikshya_assignment_due_date"><?php _e('Due Date', 'sikshya'); ?></label>
                </th>
                <td>
                    <input type="datetime-local" id="sikshya_assignment_due_date" name="sikshya_assignment_due_date" 
                           value="<?php echo esc_attr($assignment_due_date); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sikshya_assignment_points"><?php _e('Points', 'sikshya'); ?></label>
                </th>
                <td>
                    <input type="number" id="sikshya_assignment_points" name="sikshya_assignment_points" 
                           value="<?php echo esc_attr($assignment_points); ?>" min="0" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sikshya_assignment_type"><?php _e('Assignment Type', 'sikshya'); ?></label>
                </th>
                <td>
                    <select id="sikshya_assignment_type" name="sikshya_assignment_type">
                        <option value=""><?php _e('Select Type', 'sikshya'); ?></option>
                        <option value="essay" <?php selected($assignment_type, 'essay'); ?>><?php _e('Essay', 'sikshya'); ?></option>
                        <option value="file_upload" <?php selected($assignment_type, 'file_upload'); ?>><?php _e('File Upload', 'sikshya'); ?></option>
                        <option value="url_submission" <?php selected($assignment_type, 'url_submission'); ?>><?php _e('URL Submission', 'sikshya'); ?></option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render Quiz Meta Box
     */
    public function renderQuizMetaBox($post)
    {
        wp_nonce_field('sikshya_quiz_meta_box', 'sikshya_quiz_meta_box_nonce');

        $quiz_time_limit = get_post_meta($post->ID, '_sikshya_quiz_time_limit', true);
        $quiz_passing_score = get_post_meta($post->ID, '_sikshya_quiz_passing_score', true);
        $quiz_attempts_allowed = get_post_meta($post->ID, '_sikshya_quiz_attempts_allowed', true);

        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="sikshya_quiz_time_limit"><?php _e('Time Limit (minutes)', 'sikshya'); ?></label>
                </th>
                <td>
                    <input type="number" id="sikshya_quiz_time_limit" name="sikshya_quiz_time_limit" 
                           value="<?php echo esc_attr($quiz_time_limit); ?>" min="0" />
                    <p class="description"><?php _e('0 for no time limit', 'sikshya'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sikshya_quiz_passing_score"><?php _e('Passing Score (%)', 'sikshya'); ?></label>
                </th>
                <td>
                    <input type="number" id="sikshya_quiz_passing_score" name="sikshya_quiz_passing_score" 
                           value="<?php echo esc_attr($quiz_passing_score); ?>" min="0" max="100" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sikshya_quiz_attempts_allowed"><?php _e('Attempts Allowed', 'sikshya'); ?></label>
                </th>
                <td>
                    <input type="number" id="sikshya_quiz_attempts_allowed" name="sikshya_quiz_attempts_allowed" 
                           value="<?php echo esc_attr($quiz_attempts_allowed); ?>" min="1" />
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render Question Meta Box
     */
    public function renderQuestionMetaBox($post)
    {
        wp_nonce_field('sikshya_question_meta_box', 'sikshya_question_meta_box_nonce');

        $question_type = get_post_meta($post->ID, '_sikshya_question_type', true);
        $question_points = get_post_meta($post->ID, '_sikshya_question_points', true);

        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="sikshya_question_type"><?php _e('Question Type', 'sikshya'); ?></label>
                </th>
                <td>
                    <select id="sikshya_question_type" name="sikshya_question_type">
                        <option value=""><?php _e('Select Type', 'sikshya'); ?></option>
                        <option value="multiple_choice" <?php selected($question_type, 'multiple_choice'); ?>><?php _e('Multiple Choice', 'sikshya'); ?></option>
                        <option value="true_false" <?php selected($question_type, 'true_false'); ?>><?php _e('True/False', 'sikshya'); ?></option>
                        <option value="short_answer" <?php selected($question_type, 'short_answer'); ?>><?php _e('Short Answer', 'sikshya'); ?></option>
                        <option value="essay" <?php selected($question_type, 'essay'); ?>><?php _e('Essay', 'sikshya'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sikshya_question_points"><?php _e('Points', 'sikshya'); ?></label>
                </th>
                <td>
                    <input type="number" id="sikshya_question_points" name="sikshya_question_points" 
                           value="<?php echo esc_attr($question_points); ?>" min="0" />
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render Chapter Meta Box
     */
    public function renderChapterMetaBox($post)
    {
        wp_nonce_field('sikshya_chapter_meta_box', 'sikshya_chapter_meta_box_nonce');

        $chapter_order = get_post_meta($post->ID, '_sikshya_chapter_order', true);
        $chapter_course_id = get_post_meta($post->ID, '_sikshya_chapter_course_id', true);

        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="sikshya_chapter_order"><?php _e('Chapter Order', 'sikshya'); ?></label>
                </th>
                <td>
                    <input type="number" id="sikshya_chapter_order" name="sikshya_chapter_order" 
                           value="<?php echo esc_attr($chapter_order); ?>" min="0" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sikshya_chapter_course_id"><?php _e('Parent Course', 'sikshya'); ?></label>
                </th>
                <td>
                    <?php
                    $courses = get_posts([
                        'post_type' => PostTypes::COURSE,
                        'numberposts' => -1,
                        'post_status' => 'publish'
                    ]);
                    ?>
                    <select id="sikshya_chapter_course_id" name="sikshya_chapter_course_id">
                        <option value=""><?php _e('Select Course', 'sikshya'); ?></option>
                        <?php foreach ($courses as $course) : ?>
                            <option value="<?php echo $course->ID; ?>" <?php selected($chapter_course_id, $course->ID); ?>>
                                <?php echo esc_html($course->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save meta data for all post types
     */
    public function saveMetaData($post_id)
    {
        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $post_type = get_post_type($post_id);

        switch ($post_type) {
            case PostTypes::COURSE:
                $this->saveCourseMetaData($post_id);
                break;
            case PostTypes::LESSON:
                $this->saveLessonMetaData($post_id);
                break;
            case PostTypes::ASSIGNMENT:
                $this->saveAssignmentMetaData($post_id);
                break;
            case PostTypes::QUIZ:
                $this->saveQuizMetaData($post_id);
                break;
            case PostTypes::QUESTION:
                $this->saveQuestionMetaData($post_id);
                break;
            case PostTypes::CHAPTER:
                $this->saveChapterMetaData($post_id);
                break;
        }
    }

    /**
     * Save Course Meta Data
     */
    private function saveCourseMetaData($post_id)
    {
        if (!wp_verify_nonce($_POST['sikshya_course_meta_box_nonce'] ?? '', 'sikshya_course_meta_box')) {
            return;
        }

        $fields = [
            'sikshya_course_price' => 'floatval',
            'sikshya_course_duration' => 'sanitize_text_field',
            'sikshya_course_level' => 'sanitize_text_field',
            'sikshya_course_status' => 'sanitize_text_field',
        ];

        foreach ($fields as $field => $sanitize_callback) {
            if (isset($_POST[$field])) {
                $value = call_user_func($sanitize_callback, $_POST[$field]);
                update_post_meta($post_id, '_' . $field, $value);
            }
        }
    }

    /**
     * Save Lesson Meta Data
     */
    private function saveLessonMetaData($post_id)
    {
        if (!wp_verify_nonce($_POST['sikshya_lesson_meta_box_nonce'] ?? '', 'sikshya_lesson_meta_box')) {
            return;
        }

        $fields = [
            'sikshya_lesson_duration' => 'sanitize_text_field',
            'sikshya_lesson_type' => 'sanitize_text_field',
            'sikshya_lesson_video_url' => 'esc_url_raw',
        ];

        foreach ($fields as $field => $sanitize_callback) {
            if (isset($_POST[$field])) {
                $value = call_user_func($sanitize_callback, $_POST[$field]);
                update_post_meta($post_id, '_' . $field, $value);
            }
        }
    }

    /**
     * Save Assignment Meta Data
     */
    private function saveAssignmentMetaData($post_id)
    {
        if (!wp_verify_nonce($_POST['sikshya_assignment_meta_box_nonce'] ?? '', 'sikshya_assignment_meta_box')) {
            return;
        }

        $fields = [
            'sikshya_assignment_due_date' => 'sanitize_text_field',
            'sikshya_assignment_points' => 'intval',
            'sikshya_assignment_type' => 'sanitize_text_field',
        ];

        foreach ($fields as $field => $sanitize_callback) {
            if (isset($_POST[$field])) {
                $value = call_user_func($sanitize_callback, $_POST[$field]);
                update_post_meta($post_id, '_' . $field, $value);
            }
        }
    }

    /**
     * Save Quiz Meta Data
     */
    private function saveQuizMetaData($post_id)
    {
        if (!wp_verify_nonce($_POST['sikshya_quiz_meta_box_nonce'] ?? '', 'sikshya_quiz_meta_box')) {
            return;
        }

        $fields = [
            'sikshya_quiz_time_limit' => 'intval',
            'sikshya_quiz_passing_score' => 'intval',
            'sikshya_quiz_attempts_allowed' => 'intval',
        ];

        foreach ($fields as $field => $sanitize_callback) {
            if (isset($_POST[$field])) {
                $value = call_user_func($sanitize_callback, $_POST[$field]);
                update_post_meta($post_id, '_' . $field, $value);
            }
        }
    }

    /**
     * Save Question Meta Data
     */
    private function saveQuestionMetaData($post_id)
    {
        if (!wp_verify_nonce($_POST['sikshya_question_meta_box_nonce'] ?? '', 'sikshya_question_meta_box')) {
            return;
        }

        $fields = [
            'sikshya_question_type' => 'sanitize_text_field',
            'sikshya_question_points' => 'intval',
        ];

        foreach ($fields as $field => $sanitize_callback) {
            if (isset($_POST[$field])) {
                $value = call_user_func($sanitize_callback, $_POST[$field]);
                update_post_meta($post_id, '_' . $field, $value);
            }
        }
    }

    /**
     * Save Chapter Meta Data
     */
    private function saveChapterMetaData($post_id)
    {
        if (!wp_verify_nonce($_POST['sikshya_chapter_meta_box_nonce'] ?? '', 'sikshya_chapter_meta_box')) {
            return;
        }

        $fields = [
            'sikshya_chapter_order' => 'intval',
            'sikshya_chapter_course_id' => 'intval',
        ];

        foreach ($fields as $field => $sanitize_callback) {
            if (isset($_POST[$field])) {
                $value = call_user_func($sanitize_callback, $_POST[$field]);
                update_post_meta($post_id, '_' . $field, $value);
            }
        }
    }
}
