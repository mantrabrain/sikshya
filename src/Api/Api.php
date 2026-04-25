<?php

namespace Sikshya\Api;

use Sikshya\Core\Plugin;
use Sikshya\Addons\Addons;
use WP_REST_Server;

/**
 * Main API Class
 *
 * @package Sikshya\Api
 */
class Api
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
        $this->initHooks();
    }

    /**
     * Initialize hooks
     */
    private function initHooks(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * Register REST API routes
     */
    public function registerRoutes(): void
    {
        $namespace = 'sikshya/v1';

        /*
         * WP Core REST (`/wp-json/wp/v2`) does not always include underscore-prefixed meta in collections,
         * even when registered with `show_in_rest=true` (depends on context, fields filters, and plugins).
         * The React admin course list needs a reliable flag to show the Bundle badge and filter bundles.
         */
        register_rest_field(
            \Sikshya\Constants\PostTypes::COURSE,
            'sikshya_course_type',
            [
                'get_callback' => static function (array $obj): string {
                    $id = isset($obj['id']) ? (int) $obj['id'] : 0;
                    if ($id <= 0) {
                        return '';
                    }
                    return sanitize_key((string) get_post_meta($id, '_sikshya_course_type', true));
                },
                'schema' => [
                    'description' => 'Sikshya course type (free, paid, subscription, bundle).',
                    'type' => 'string',
                    'context' => ['view', 'edit'],
                ],
            ]
        );

        // Preview URL for unpublished course/lesson/quiz/assignment posts.
        // WP Core's REST exposes `link` (the public permalink) but never a
        // preview-mode URL, so the admin React row actions can't render a
        // "Preview" link for drafts/pending/private/auto-draft. We compute
        // it via get_preview_post_link(), which signs the URL with the
        // capability-aware preview nonce, and only emit it when the current
        // user can actually edit the post — so unauthorized REST consumers
        // get an empty string.
        $preview_link_callback = static function (array $obj): string {
            $id = isset($obj['id']) ? (int) $obj['id'] : 0;
            if ($id <= 0) {
                return '';
            }
            $post = get_post($id);
            if (!$post instanceof \WP_Post) {
                return '';
            }
            // For published/scheduled posts, `link` already points to the
            // canonical permalink; the preview link is the same. We only
            // expose this field for editable, not-yet-public posts to keep
            // the React UI logic simple (Preview = drafts; View = published).
            $editable_statuses = ['draft', 'pending', 'private', 'future', 'auto-draft'];
            if (!in_array($post->post_status, $editable_statuses, true)) {
                return '';
            }
            if (!current_user_can('edit_post', $id)) {
                return '';
            }
            $url = get_preview_post_link($post);
            return is_string($url) ? esc_url_raw($url) : '';
        };
        $preview_link_schema = [
            'description' => 'Preview URL (with preview nonce) for unpublished posts; empty for published or unauthorized.',
            'type' => 'string',
            'format' => 'uri',
            'context' => ['view', 'edit'],
            'readonly' => true,
        ];
        foreach ([
            \Sikshya\Constants\PostTypes::COURSE,
            \Sikshya\Constants\PostTypes::LESSON,
            \Sikshya\Constants\PostTypes::QUIZ,
            \Sikshya\Constants\PostTypes::ASSIGNMENT,
        ] as $sik_post_type) {
            register_rest_field(
                $sik_post_type,
                'sikshya_preview_link',
                [
                    'get_callback' => $preview_link_callback,
                    'schema' => $preview_link_schema,
                ]
            );
        }

        // Certificate template public preview URL (base + hash).
        // Hash is auto-generated server-side and stored in meta (so we can resolve preview by hash
        // without scanning templates). Admin UI should never need a manual "save once".
        register_rest_field(
            \Sikshya\Constants\PostTypes::CERTIFICATE,
            'sikshya_certificate_preview_url',
            [
                'get_callback' => static function (array $obj): string {
                    $id = isset($obj['id']) ? (int) $obj['id'] : 0;
                    if ($id <= 0) {
                        return '';
                    }
                    $hash = (string) get_post_meta($id, '_sikshya_certificate_preview_hash', true);
                    $hash = strtolower(preg_replace('/[^a-f0-9]/', '', $hash) ?? '');
                    if (strlen($hash) !== 64) {
                        // Auto-generate for existing templates that predate this feature.
                        try {
                            $hash = bin2hex(random_bytes(32));
                        } catch (\Throwable $e) {
                            $hash = bin2hex(openssl_random_pseudo_bytes(32) ?: random_bytes(32));
                        }
                        update_post_meta($id, '_sikshya_certificate_preview_hash', $hash);
                    }

                    $p = \Sikshya\Services\PermalinkService::get();
                    $base = isset($p['rewrite_base_certificate']) ? \Sikshya\Services\PermalinkService::sanitizeSlug((string) $p['rewrite_base_certificate']) : 'certificates';

                    if (\Sikshya\Services\PermalinkService::isPlainPermalinks()) {
                        return home_url('/' . $base . '/?hash=' . rawurlencode($hash));
                    }

                    return user_trailingslashit(home_url('/' . $base . '/' . $hash));
                },
                'schema' => [
                    'description' => 'Public certificate template preview URL (?hash=...)',
                    'type' => 'string',
                    'context' => ['view', 'edit'],
                ],
            ]
        );

        // Courses
        register_rest_route($namespace, '/courses', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getCourses'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createCourse'],
                'permission_callback' => [$this, 'canManageCourses'],
            ],
        ]);
        register_rest_route($namespace, '/courses/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getCourse'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateCourse'],
                'permission_callback' => [$this, 'canManageCourses'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'deleteCourse'],
                'permission_callback' => [$this, 'canManageCourses'],
            ],
        ]);

        // Lessons
        register_rest_route($namespace, '/lessons', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getLessons'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createLesson'],
                'permission_callback' => [$this, 'canManageLessons'],
            ],
        ]);
        register_rest_route($namespace, '/lessons/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getLesson'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateLesson'],
                'permission_callback' => [$this, 'canManageLessons'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'deleteLesson'],
                'permission_callback' => [$this, 'canManageLessons'],
            ],
        ]);

        // Quizzes
        register_rest_route($namespace, '/quizzes', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getQuizzes'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createQuiz'],
                'permission_callback' => [$this, 'canManageQuizzes'],
            ],
        ]);
        register_rest_route($namespace, '/quizzes/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getQuiz'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateQuiz'],
                'permission_callback' => [$this, 'canManageQuizzes'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'deleteQuiz'],
                'permission_callback' => [$this, 'canManageQuizzes'],
            ],
        ]);

        // Users
        register_rest_route($namespace, '/users', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getUsers'],
                'permission_callback' => [$this, 'canManageUsers'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createUser'],
                'permission_callback' => [$this, 'canManageUsers'],
            ],
        ]);
        register_rest_route($namespace, '/users/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getUser'],
                'permission_callback' => [$this, 'canManageUsers'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateUser'],
                'permission_callback' => [$this, 'canManageUsers'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'deleteUser'],
                'permission_callback' => [$this, 'canManageUsers'],
            ],
        ]);

        // Enrollments
        register_rest_route($namespace, '/enrollments', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getEnrollments'],
                'permission_callback' => [$this, 'canManageEnrollments'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createEnrollment'],
                'permission_callback' => [$this, 'canManageEnrollments'],
            ],
        ]);
        register_rest_route($namespace, '/enrollments/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getEnrollment'],
                'permission_callback' => [$this, 'canManageEnrollments'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateEnrollment'],
                'permission_callback' => [$this, 'canManageEnrollments'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'deleteEnrollment'],
                'permission_callback' => [$this, 'canManageEnrollments'],
            ],
        ]);

        // Progress
        register_rest_route($namespace, '/progress', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getProgress'],
                'permission_callback' => [$this, 'canManageProgress'],
            ],
        ]);

        // Certificates
        register_rest_route($namespace, '/certificates', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getCertificates'],
                'permission_callback' => [$this, 'canManageCertificates'],
            ],
        ]);

        // Payments
        register_rest_route($namespace, '/payments', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getPayments'],
                'permission_callback' => [$this, 'canManagePayments'],
            ],
        ]);

        // Admin / curriculum / categories (REST → services → repositories)
        $admin_rest = new AdminRestRoutes($this->plugin);
        $admin_rest->register();

        AdminLicenseRestRoutes::register();

        $email_templates_rest = new AdminEmailTemplateRestRoutes($this->plugin);
        $email_templates_rest->register();

        $auth_rest = new AuthRestRoutes($this->plugin);
        $auth_rest->register();

        $public_rest = new PublicRestRoutes($this->plugin);
        $public_rest->register();

        $learner_rest = new LearnerRestRoutes($this->plugin);
        $learner_rest->register();

        $checkout_rest = new CheckoutRestRoutes($this->plugin);
        $checkout_rest->register();

        /**
         * Allow enabled add-ons to register additional REST routes.
         *
         * Core stays generic: add-on boot classes should hook this and register routes.
         */
        do_action('sikshya_register_addon_rest_routes', $this->plugin);

        // Addons enable/disable API for React admin.
        $addons_rest = new AdminAddonsRestRoutes($this->plugin);
        $addons_rest->register();
    }

    // --- Courses ---
    public function getCourses($request)
    {
        return $this->plugin->getService('api.course')->getCourses($request);
    }
    public function createCourse($request)
    {
        return $this->plugin->getService('api.course')->createCourse($request);
    }
    public function getCourse($request)
    {
        return $this->plugin->getService('api.course')->getCourse($request);
    }
    public function updateCourse($request)
    {
        return $this->plugin->getService('api.course')->updateCourse($request);
    }
    public function deleteCourse($request)
    {
        return $this->plugin->getService('api.course')->deleteCourse($request);
    }
    public function canManageCourses()
    {
        return current_user_can('manage_sikshya') || current_user_can('edit_sikshya_courses');
    }

    // --- Lessons ---
    public function getLessons($request)
    {
        return $this->plugin->getService('api.lesson')->getLessons($request);
    }
    public function createLesson($request)
    {
        return $this->plugin->getService('api.lesson')->createLesson($request);
    }
    public function getLesson($request)
    {
        return $this->plugin->getService('api.lesson')->getLesson($request);
    }
    public function updateLesson($request)
    {
        return $this->plugin->getService('api.lesson')->updateLesson($request);
    }
    public function deleteLesson($request)
    {
        return $this->plugin->getService('api.lesson')->deleteLesson($request);
    }
    public function canManageLessons()
    {
        return current_user_can('manage_sikshya') || current_user_can('edit_sikshya_lessons');
    }

    // --- Quizzes ---
    public function getQuizzes($request)
    {
        return $this->plugin->getService('api.quiz')->getQuizzes($request);
    }
    public function createQuiz($request)
    {
        return $this->plugin->getService('api.quiz')->createQuiz($request);
    }
    public function getQuiz($request)
    {
        return $this->plugin->getService('api.quiz')->getQuiz($request);
    }
    public function updateQuiz($request)
    {
        return $this->plugin->getService('api.quiz')->updateQuiz($request);
    }
    public function deleteQuiz($request)
    {
        return $this->plugin->getService('api.quiz')->deleteQuiz($request);
    }
    public function canManageQuizzes()
    {
        return current_user_can('manage_sikshya') || current_user_can('edit_sikshya_quizzes');
    }

    // --- Users ---
    public function getUsers($request)
    {
        return $this->plugin->getService('api.user')->getUsers($request);
    }
    public function createUser($request)
    {
        return $this->plugin->getService('api.user')->createUser($request);
    }
    public function getUser($request)
    {
        return $this->plugin->getService('api.user')->getUser($request);
    }
    public function updateUser($request)
    {
        return $this->plugin->getService('api.user')->updateUser($request);
    }
    public function deleteUser($request)
    {
        return $this->plugin->getService('api.user')->deleteUser($request);
    }
    public function canManageUsers()
    {
        return current_user_can('manage_sikshya') || current_user_can('manage_options');
    }

    // --- Enrollments ---
    public function getEnrollments($request)
    {
        return $this->plugin->getService('api.enrollment')->getEnrollments($request);
    }
    public function createEnrollment($request)
    {
        return $this->plugin->getService('api.enrollment')->createEnrollment($request);
    }
    public function getEnrollment($request)
    {
        return $this->plugin->getService('api.enrollment')->getEnrollment($request);
    }
    public function updateEnrollment($request)
    {
        return $this->plugin->getService('api.enrollment')->updateEnrollment($request);
    }
    public function deleteEnrollment($request)
    {
        return $this->plugin->getService('api.enrollment')->deleteEnrollment($request);
    }
    public function canManageEnrollments()
    {
        return current_user_can('manage_sikshya') || current_user_can('manage_options');
    }

    // --- Progress ---
    public function getProgress($request)
    {
        return $this->plugin->getService('api.progress')->getProgress($request);
    }
    public function canManageProgress()
    {
        return current_user_can('manage_sikshya') || current_user_can('manage_options');
    }

    // --- Certificates ---
    public function getCertificates($request)
    {
        return $this->plugin->getService('api.certificate')->getCertificates($request);
    }
    public function canManageCertificates()
    {
        return current_user_can('manage_sikshya') || current_user_can('manage_options');
    }

    // --- Payments ---
    public function getPayments($request)
    {
        return $this->plugin->getService('api.payment')->getPayments($request);
    }
    public function canManagePayments()
    {
        return current_user_can('manage_sikshya') || current_user_can('manage_options');
    }
}
