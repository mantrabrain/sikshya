<?php

namespace Sikshya\Api;

use Sikshya\Core\Plugin;
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

        $auth_rest = new AuthRestRoutes($this->plugin);
        $auth_rest->register();

        $public_rest = new PublicRestRoutes($this->plugin);
        $public_rest->register();

        $learner_rest = new LearnerRestRoutes($this->plugin);
        $learner_rest->register();

        $checkout_rest = new CheckoutRestRoutes($this->plugin);
        $checkout_rest->register();

        $webhooks_rest = new WebhooksRestRoutes($this->plugin);
        $webhooks_rest->register();

        $cert_public = new CertificatesPublicRoutes();
        $cert_public->register();
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
