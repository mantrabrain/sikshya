<?php

namespace Sikshya\Admin\Controllers;

use Sikshya\Core\Plugin;
use Sikshya\Services\SecurityService;
use Sikshya\Services\LogService;

/**
 * User Controller
 *
 * @package Sikshya\Admin\Controllers
 */
class UserController
{
    /**
     * Plugin instance
     *
     * @var Plugin
     */
    private Plugin $plugin;

    /**
     * Security service
     *
     * @var SecurityService
     */
    private SecurityService $security;

    /**
     * Log service
     *
     * @var LogService
     */
    private LogService $log;

    /**
     * Constructor
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->security = $plugin->getService('security');
        $this->log = $plugin->getService('log');
    }

    /**
     * Initialize controller
     */
    public function init(): void
    {
        add_action('wp_ajax_sikshya_user_action', [$this, 'handleAjax']);
    }

    /**
     * Students page
     */
    public function students(): void
    {
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'add':
                $this->addStudent();
                break;
            case 'edit':
                $this->editStudent();
                break;
            case 'view':
                $this->viewStudent();
                break;
            default:
                $this->listStudents();
        }
    }

    /**
     * Instructors page
     */
    public function instructors(): void
    {
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'add':
                $this->addInstructor();
                break;
            case 'edit':
                $this->editInstructor();
                break;
            case 'view':
                $this->viewInstructor();
                break;
            default:
                $this->listInstructors();
        }
    }

    /**
     * List students
     */
    private function listStudents(): void
    {
        $page = max(1, intval($_GET['paged'] ?? 1));
        $per_page = 20;
        $search = sanitize_text_field($_GET['s'] ?? '');
        $status = sanitize_text_field($_GET['status'] ?? '');

        $students = $this->getStudents($page, $per_page, $search, $status);
        $total_students = $this->getTotalStudents($search, $status);
        $total_pages = ceil($total_students / $per_page);

        include $this->plugin->getTemplatePath('admin/users/students-list.php');
    }

    /**
     * List instructors
     */
    private function listInstructors(): void
    {
        $page = max(1, intval($_GET['paged'] ?? 1));
        $per_page = 20;
        $search = sanitize_text_field($_GET['s'] ?? '');
        $status = sanitize_text_field($_GET['status'] ?? '');

        $instructors = $this->getInstructors($page, $per_page, $search, $status);
        $total_instructors = $this->getTotalInstructors($search, $status);
        $total_pages = ceil($total_instructors / $per_page);

        include $this->plugin->getTemplatePath('admin/users/instructors-list.php');
    }

    /**
     * Add student page
     */
    private function addStudent(): void
    {
        $student = null;
        include $this->plugin->getTemplatePath('admin/users/student-form.php');
    }

    /**
     * Add instructor page
     */
    private function addInstructor(): void
    {
        $instructor = null;
        include $this->plugin->getTemplatePath('admin/users/instructor-form.php');
    }

    /**
     * Edit student page
     */
    private function editStudent(): void
    {
        $user_id = intval($_GET['id'] ?? 0);
        
        if (!$user_id) {
            wp_die(__('Student not found.', 'sikshya'));
        }

        $student = get_user_by('id', $user_id);
        
        if (!$student || !in_array('sikshya_student', $student->roles)) {
            wp_die(__('Student not found.', 'sikshya'));
        }

        $enrollments = $this->getUserEnrollments($user_id);
        $progress = $this->getUserProgress($user_id);
        $certificates = $this->getUserCertificates($user_id);

        include $this->plugin->getTemplatePath('admin/users/student-form.php');
    }

    /**
     * Edit instructor page
     */
    private function editInstructor(): void
    {
        $user_id = intval($_GET['id'] ?? 0);
        
        if (!$user_id) {
            wp_die(__('Instructor not found.', 'sikshya'));
        }

        $instructor = get_user_by('id', $user_id);
        
        if (!$instructor || !in_array('sikshya_instructor', $instructor->roles)) {
            wp_die(__('Instructor not found.', 'sikshya'));
        }

        $courses = $this->getInstructorCourses($user_id);
        $earnings = $this->getInstructorEarnings($user_id);
        $students = $this->getInstructorStudents($user_id);

        include $this->plugin->getTemplatePath('admin/users/instructor-form.php');
    }

    /**
     * View student page
     */
    private function viewStudent(): void
    {
        $user_id = intval($_GET['id'] ?? 0);
        
        if (!$user_id) {
            wp_die(__('Student not found.', 'sikshya'));
        }

        $student = get_user_by('id', $user_id);
        
        if (!$student || !in_array('sikshya_student', $student->roles)) {
            wp_die(__('Student not found.', 'sikshya'));
        }

        $enrollments = $this->getUserEnrollments($user_id);
        $progress = $this->getUserProgress($user_id);
        $certificates = $this->getUserCertificates($user_id);
        $quiz_results = $this->getUserQuizResults($user_id);
        $activities = $this->getUserActivities($user_id);

        include $this->plugin->getTemplatePath('admin/users/student-view.php');
    }

    /**
     * View instructor page
     */
    private function viewInstructor(): void
    {
        $user_id = intval($_GET['id'] ?? 0);
        
        if (!$user_id) {
            wp_die(__('Instructor not found.', 'sikshya'));
        }

        $instructor = get_user_by('id', $user_id);
        
        if (!$instructor || !in_array('sikshya_instructor', $instructor->roles)) {
            wp_die(__('Instructor not found.', 'sikshya'));
        }

        $courses = $this->getInstructorCourses($user_id);
        $earnings = $this->getInstructorEarnings($user_id);
        $students = $this->getInstructorStudents($user_id);
        $analytics = $this->getInstructorAnalytics($user_id);

        include $this->plugin->getTemplatePath('admin/users/instructor-view.php');
    }

    /**
     * Get students
     *
     * @param int $page
     * @param int $per_page
     * @param string $search
     * @param string $status
     * @return array
     */
    private function getStudents(int $page, int $per_page, string $search = '', string $status = ''): array
    {
        $args = [
            'role' => 'sikshya_student',
            'number' => $per_page,
            'paged' => $page,
            'orderby' => 'registered',
            'order' => 'DESC',
        ];

        if (!empty($search)) {
            $args['search'] = "*{$search}*";
            $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }

        if (!empty($status)) {
            $args['meta_query'] = [
                [
                    'key' => 'sikshya_student_status',
                    'value' => $status,
                    'compare' => '='
                ]
            ];
        }

        return get_users($args);
    }

    /**
     * Get total students
     *
     * @param string $search
     * @param string $status
     * @return int
     */
    private function getTotalStudents(string $search = '', string $status = ''): int
    {
        $args = [
            'role' => 'sikshya_student',
            'count_total' => true,
        ];

        if (!empty($search)) {
            $args['search'] = "*{$search}*";
            $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }

        if (!empty($status)) {
            $args['meta_query'] = [
                [
                    'key' => 'sikshya_student_status',
                    'value' => $status,
                    'compare' => '='
                ]
            ];
        }

        return count_users($args);
    }

    /**
     * Get instructors
     *
     * @param int $page
     * @param int $per_page
     * @param string $search
     * @param string $status
     * @return array
     */
    private function getInstructors(int $page, int $per_page, string $search = '', string $status = ''): array
    {
        $args = [
            'role' => 'sikshya_instructor',
            'number' => $per_page,
            'paged' => $page,
            'orderby' => 'registered',
            'order' => 'DESC',
        ];

        if (!empty($search)) {
            $args['search'] = "*{$search}*";
            $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }

        if (!empty($status)) {
            $args['meta_query'] = [
                [
                    'key' => 'sikshya_instructor_approved',
                    'value' => $status === 'approved' ? '1' : '0',
                    'compare' => '='
                ]
            ];
        }

        return get_users($args);
    }

    /**
     * Get total instructors
     *
     * @param string $search
     * @param string $status
     * @return int
     */
    private function getTotalInstructors(string $search = '', string $status = ''): int
    {
        $args = [
            'role' => 'sikshya_instructor',
            'count_total' => true,
        ];

        if (!empty($search)) {
            $args['search'] = "*{$search}*";
            $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }

        if (!empty($status)) {
            $args['meta_query'] = [
                [
                    'key' => 'sikshya_instructor_approved',
                    'value' => $status === 'approved' ? '1' : '0',
                    'compare' => '='
                ]
            ];
        }

        return count_users($args);
    }

    /**
     * Get user enrollments
     *
     * @param int $user_id
     * @return array
     */
    private function getUserEnrollments(int $user_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT e.*, c.post_title as course_title, u.display_name as instructor_name
             FROM {$wpdb->prefix}sikshya_enrollments e
             JOIN {$wpdb->prefix}sikshya_courses sc ON e.course_id = sc.id
             JOIN {$wpdb->posts} c ON sc.post_id = c.ID
             LEFT JOIN {$wpdb->users} u ON sc.instructor_id = u.ID
             WHERE e.user_id = %d
             ORDER BY e.enrollment_date DESC",
            $user_id
        ));
    }

    /**
     * Get user progress
     *
     * @param int $user_id
     * @return array
     */
    private function getUserProgress(int $user_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, c.post_title as course_title, l.post_title as lesson_title
             FROM {$wpdb->prefix}sikshya_progress p
             JOIN {$wpdb->prefix}sikshya_courses sc ON p.course_id = sc.id
             JOIN {$wpdb->posts} c ON sc.post_id = c.ID
             LEFT JOIN {$wpdb->prefix}sikshya_lessons sl ON p.lesson_id = sl.id
             LEFT JOIN {$wpdb->posts} l ON sl.post_id = l.ID
             WHERE p.user_id = %d
             ORDER BY p.updated_at DESC",
            $user_id
        ));
    }

    /**
     * Get user certificates
     *
     * @param int $user_id
     * @return array
     */
    private function getUserCertificates(int $user_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, co.post_title as course_title
             FROM {$wpdb->prefix}sikshya_certificates c
             JOIN {$wpdb->prefix}sikshya_courses sc ON c.course_id = sc.id
             JOIN {$wpdb->posts} co ON sc.post_id = co.ID
             WHERE c.user_id = %d
             ORDER BY c.created_at DESC",
            $user_id
        ));
    }

    /**
     * Get user quiz results
     *
     * @param int $user_id
     * @return array
     */
    private function getUserQuizResults(int $user_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, q.post_title as quiz_title, c.post_title as course_title
             FROM {$wpdb->prefix}sikshya_quiz_results r
             JOIN {$wpdb->prefix}sikshya_quizzes sq ON r.quiz_id = sq.id
             JOIN {$wpdb->posts} q ON sq.post_id = q.ID
             LEFT JOIN {$wpdb->prefix}sikshya_courses sc ON sq.course_id = sc.id
             LEFT JOIN {$wpdb->posts} c ON sc.post_id = c.ID
             WHERE r.user_id = %d
             ORDER BY r.completed_at DESC",
            $user_id
        ));
    }

    /**
     * Get user activities
     *
     * @param int $user_id
     * @return array
     */
    private function getUserActivities(int $user_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sikshya_analytics
             WHERE user_id = %d
             ORDER BY created_at DESC
             LIMIT 50",
            $user_id
        ));
    }

    /**
     * Get instructor courses
     *
     * @param int $user_id
     * @return array
     */
    private function getInstructorCourses(int $user_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, p.post_title, p.post_status
             FROM {$wpdb->prefix}sikshya_courses c
             JOIN {$wpdb->posts} p ON c.post_id = p.ID
             WHERE c.instructor_id = %d
             ORDER BY c.created_at DESC",
            $user_id
        ));
    }

    /**
     * Get instructor earnings
     *
     * @param int $user_id
     * @return array
     */
    private function getInstructorEarnings(int $user_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                SUM(amount) as total_earnings,
                COUNT(*) as total_sales,
                AVG(amount) as avg_sale,
                DATE_FORMAT(created_at, '%Y-%m') as month
             FROM {$wpdb->prefix}sikshya_payments
             WHERE instructor_id = %d AND status = 'completed'
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')
             ORDER BY month DESC
             LIMIT 12",
            $user_id
        ));
    }

    /**
     * Get instructor students
     *
     * @param int $user_id
     * @return array
     */
    private function getInstructorStudents(int $user_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT u.ID, u.display_name, u.user_email, COUNT(e.id) as enrollment_count
             FROM {$wpdb->users} u
             JOIN {$wpdb->prefix}sikshya_enrollments e ON u.ID = e.user_id
             JOIN {$wpdb->prefix}sikshya_courses c ON e.course_id = c.id
             WHERE c.instructor_id = %d
             GROUP BY u.ID
             ORDER BY enrollment_count DESC",
            $user_id
        ));
    }

    /**
     * Get instructor analytics
     *
     * @param int $user_id
     * @return array
     */
    private function getInstructorAnalytics(int $user_id): array
    {
        global $wpdb;

        $analytics = [];

        // Course statistics
        $analytics['courses'] = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_courses,
                COUNT(CASE WHEN status = 'published' THEN 1 END) as published_courses,
                COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_courses
             FROM {$wpdb->prefix}sikshya_courses
             WHERE instructor_id = %d",
            $user_id
        ));

        // Enrollment statistics
        $analytics['enrollments'] = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_enrollments,
                COUNT(CASE WHEN e.status = 'active' THEN 1 END) as active_enrollments,
                COUNT(CASE WHEN e.status = 'completed' THEN 1 END) as completed_enrollments
             FROM {$wpdb->prefix}sikshya_enrollments e
             JOIN {$wpdb->prefix}sikshya_courses c ON e.course_id = c.id
             WHERE c.instructor_id = %d",
            $user_id
        ));

        // Revenue statistics
        $analytics['revenue'] = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(amount) as total_revenue,
                COUNT(*) as total_sales,
                AVG(amount) as avg_sale
             FROM {$wpdb->prefix}sikshya_payments
             WHERE instructor_id = %d AND status = 'completed'",
            $user_id
        ));

        return $analytics;
    }

    /**
     * Handle AJAX requests
     *
     * @param string $action
     */
    public function handleAjax(string $action): void
    {
        switch ($action) {
            case 'approve_instructor':
                $this->approveInstructor();
                break;
            case 'reject_instructor':
                $this->rejectInstructor();
                break;
            case 'suspend_user':
                $this->suspendUser();
                break;
            case 'activate_user':
                $this->activateUser();
                break;
            case 'delete_user':
                $this->deleteUser();
                break;
            case 'bulk_action':
                $this->handleBulkAction();
                break;
            default:
                wp_send_json_error(__('Invalid action.', 'sikshya'));
        }
    }

    /**
     * Approve instructor
     */
    private function approveInstructor(): void
    {
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if (!$user_id) {
            wp_send_json_error(__('User ID is required.', 'sikshya'));
        }

        $user = get_user_by('id', $user_id);
        
        if (!$user || !in_array('sikshya_instructor', $user->roles)) {
            wp_send_json_error(__('Instructor not found.', 'sikshya'));
        }

        update_user_meta($user_id, 'sikshya_instructor_approved', '1');
        update_user_meta($user_id, 'sikshya_instructor_approved_date', current_time('mysql'));

        // Send approval email
        $this->sendInstructorApprovalEmail($user);

        $this->log->info('Instructor approved', [
            'user_id' => $user_id,
            'admin_id' => get_current_user_id(),
        ]);

        wp_send_json_success(__('Instructor approved successfully.', 'sikshya'));
    }

    /**
     * Reject instructor
     */
    private function rejectInstructor(): void
    {
        $user_id = intval($_POST['user_id'] ?? 0);
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');
        
        if (!$user_id) {
            wp_send_json_error(__('User ID is required.', 'sikshya'));
        }

        $user = get_user_by('id', $user_id);
        
        if (!$user || !in_array('sikshya_instructor', $user->roles)) {
            wp_send_json_error(__('Instructor not found.', 'sikshya'));
        }

        update_user_meta($user_id, 'sikshya_instructor_approved', '0');
        update_user_meta($user_id, 'sikshya_instructor_rejection_reason', $reason);

        // Send rejection email
        $this->sendInstructorRejectionEmail($user, $reason);

        $this->log->info('Instructor rejected', [
            'user_id' => $user_id,
            'reason' => $reason,
            'admin_id' => get_current_user_id(),
        ]);

        wp_send_json_success(__('Instructor rejected successfully.', 'sikshya'));
    }

    /**
     * Suspend user
     */
    private function suspendUser(): void
    {
        $user_id = intval($_POST['user_id'] ?? 0);
        $reason = sanitize_textarea_field($_POST['reason'] ?? '');
        
        if (!$user_id) {
            wp_send_json_error(__('User ID is required.', 'sikshya'));
        }

        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            wp_send_json_error(__('User not found.', 'sikshya'));
        }

        update_user_meta($user_id, 'sikshya_user_suspended', '1');
        update_user_meta($user_id, 'sikshya_suspension_reason', $reason);
        update_user_meta($user_id, 'sikshya_suspension_date', current_time('mysql'));

        $this->log->info('User suspended', [
            'user_id' => $user_id,
            'reason' => $reason,
            'admin_id' => get_current_user_id(),
        ]);

        wp_send_json_success(__('User suspended successfully.', 'sikshya'));
    }

    /**
     * Activate user
     */
    private function activateUser(): void
    {
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if (!$user_id) {
            wp_send_json_error(__('User ID is required.', 'sikshya'));
        }

        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            wp_send_json_error(__('User not found.', 'sikshya'));
        }

        delete_user_meta($user_id, 'sikshya_user_suspended');
        delete_user_meta($user_id, 'sikshya_suspension_reason');
        delete_user_meta($user_id, 'sikshya_suspension_date');

        $this->log->info('User activated', [
            'user_id' => $user_id,
            'admin_id' => get_current_user_id(),
        ]);

        wp_send_json_success(__('User activated successfully.', 'sikshya'));
    }

    /**
     * Delete user
     */
    private function deleteUser(): void
    {
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if (!$user_id) {
            wp_send_json_error(__('User ID is required.', 'sikshya'));
        }

        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            wp_send_json_error(__('User not found.', 'sikshya'));
        }

        // Delete user data
        global $wpdb;
        
        $wpdb->delete($wpdb->prefix . 'sikshya_enrollments', ['user_id' => $user_id]);
        $wpdb->delete($wpdb->prefix . 'sikshya_progress', ['user_id' => $user_id]);
        $wpdb->delete($wpdb->prefix . 'sikshya_certificates', ['user_id' => $user_id]);
        $wpdb->delete($wpdb->prefix . 'sikshya_quiz_results', ['user_id' => $user_id]);
        $wpdb->delete($wpdb->prefix . 'sikshya_reviews', ['user_id' => $user_id]);
        $wpdb->delete($wpdb->prefix . 'sikshya_analytics', ['user_id' => $user_id]);

        // Delete user
        wp_delete_user($user_id);

        $this->log->info('User deleted', [
            'user_id' => $user_id,
            'admin_id' => get_current_user_id(),
        ]);

        wp_send_json_success(__('User deleted successfully.', 'sikshya'));
    }

    /**
     * Handle bulk actions
     */
    private function handleBulkAction(): void
    {
        $action = sanitize_text_field($_POST['bulk_action'] ?? '');
        $user_ids = array_map('intval', $_POST['user_ids'] ?? []);
        $user_type = sanitize_text_field($_POST['user_type'] ?? '');

        if (empty($user_ids)) {
            wp_send_json_error(__('No users selected.', 'sikshya'));
        }

        switch ($action) {
            case 'approve_instructors':
                if ($user_type !== 'instructor') {
                    wp_send_json_error(__('Invalid user type for this action.', 'sikshya'));
                }
                foreach ($user_ids as $user_id) {
                    update_user_meta($user_id, 'sikshya_instructor_approved', '1');
                }
                $message = __('Selected instructors approved successfully.', 'sikshya');
                break;

            case 'suspend_users':
                foreach ($user_ids as $user_id) {
                    update_user_meta($user_id, 'sikshya_user_suspended', '1');
                }
                $message = __('Selected users suspended successfully.', 'sikshya');
                break;

            case 'activate_users':
                foreach ($user_ids as $user_id) {
                    delete_user_meta($user_id, 'sikshya_user_suspended');
                }
                $message = __('Selected users activated successfully.', 'sikshya');
                break;

            case 'delete_users':
                foreach ($user_ids as $user_id) {
                    $this->deleteUserData($user_id);
                    wp_delete_user($user_id);
                }
                $message = __('Selected users deleted successfully.', 'sikshya');
                break;

            default:
                wp_send_json_error(__('Invalid bulk action.', 'sikshya'));
        }

        $this->log->info('Bulk action performed', [
            'action' => $action,
            'user_ids' => $user_ids,
            'user_type' => $user_type,
            'admin_id' => get_current_user_id(),
        ]);

        wp_send_json_success($message);
    }

    /**
     * Delete user data
     *
     * @param int $user_id
     */
    private function deleteUserData(int $user_id): void
    {
        global $wpdb;
        
        $wpdb->delete($wpdb->prefix . 'sikshya_enrollments', ['user_id' => $user_id]);
        $wpdb->delete($wpdb->prefix . 'sikshya_progress', ['user_id' => $user_id]);
        $wpdb->delete($wpdb->prefix . 'sikshya_certificates', ['user_id' => $user_id]);
        $wpdb->delete($wpdb->prefix . 'sikshya_quiz_results', ['user_id' => $user_id]);
        $wpdb->delete($wpdb->prefix . 'sikshya_reviews', ['user_id' => $user_id]);
        $wpdb->delete($wpdb->prefix . 'sikshya_analytics', ['user_id' => $user_id]);
    }

    /**
     * Send instructor approval email
     *
     * @param \WP_User $user
     */
    private function sendInstructorApprovalEmail(\WP_User $user): void
    {
        $subject = __('Your instructor application has been approved', 'sikshya');
        $message = sprintf(
            __('Hello %s,

Your instructor application has been approved. You can now start creating and publishing courses.

Login to your dashboard: %s

Best regards,
%s Team', 'sikshya'),
            $user->display_name,
            admin_url(),
            get_bloginfo('name')
        );

        wp_mail($user->user_email, $subject, $message);
    }

    /**
     * Send instructor rejection email
     *
     * @param \WP_User $user
     * @param string $reason
     */
    private function sendInstructorRejectionEmail(\WP_User $user, string $reason): void
    {
        $subject = __('Your instructor application status', 'sikshya');
        $message = sprintf(
            __('Hello %s,

We regret to inform you that your instructor application has not been approved at this time.

Reason: %s

You may reapply in the future with additional qualifications or experience.

Best regards,
%s Team', 'sikshya'),
            $user->display_name,
            $reason,
            get_bloginfo('name')
        );

        wp_mail($user->user_email, $subject, $message);
    }
} 