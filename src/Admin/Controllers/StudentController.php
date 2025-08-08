<?php

namespace Sikshya\Admin\Controllers;

use Sikshya\Core\Plugin;
use Sikshya\Admin\Views\DataTable;

/**
 * Student Controller
 *
 * @package Sikshya\Admin\Controllers
 */
class StudentController
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
        add_action('wp_ajax_sikshya_student_list', [$this, 'handleStudentList']);
        add_action('wp_ajax_sikshya_student_save', [$this, 'handleStudentSave']);
        add_action('wp_ajax_sikshya_student_delete', [$this, 'handleStudentDelete']);
    }

    /**
     * Render students list page
     */
    public function renderStudentsPage(): void
    {
        $dataTable = new DataTable($this->plugin, [
            'id' => 'sikshya-students-table',
            'title' => __('Students', 'sikshya'),
            'description' => __('Manage your students', 'sikshya'),
        ]);

        // Add columns
        $dataTable->addColumn('id', [
            'title' => __('ID', 'sikshya'),
            'sortable' => true,
            'width' => '80px',
        ]);

        $dataTable->addColumn('name', [
            'title' => __('Name', 'sikshya'),
            'sortable' => true,
            'searchable' => true,
        ]);

        $dataTable->addColumn('email', [
            'title' => __('Email', 'sikshya'),
            'sortable' => true,
            'searchable' => true,
        ]);

        $dataTable->addColumn('enrolled_courses', [
            'title' => __('Enrolled Courses', 'sikshya'),
            'sortable' => true,
        ]);

        $dataTable->addColumn('completed_courses', [
            'title' => __('Completed Courses', 'sikshya'),
            'sortable' => true,
        ]);

        $dataTable->addColumn('progress', [
            'title' => __('Average Progress', 'sikshya'),
            'sortable' => true,
        ]);

        $dataTable->addColumn('joined_date', [
            'title' => __('Joined Date', 'sikshya'),
            'sortable' => true,
        ]);

        $dataTable->addColumn('status', [
            'title' => __('Status', 'sikshya'),
            'sortable' => true,
        ]);

        // Add actions
        $dataTable->addAction('view', [
            'title' => __('View', 'sikshya'),
            'url' => admin_url('admin.php?page=sikshya-view-student&id={id}'),
            'class' => 'button button-small',
        ]);

        $dataTable->addAction('edit', [
            'title' => __('Edit', 'sikshya'),
            'url' => admin_url('admin.php?page=sikshya-edit-student&id={id}'),
            'class' => 'button button-small',
        ]);

        $dataTable->addAction('delete', [
            'title' => __('Delete', 'sikshya'),
            'url' => '#',
            'class' => 'button button-small button-link-delete',
            'onclick' => 'sikshya.deleteStudent({id})',
        ]);

        // Add bulk actions
        $dataTable->addBulkAction('delete', [
            'title' => __('Delete Selected', 'sikshya'),
            'action' => 'sikshya_bulk_delete_students',
        ]);

        $dataTable->addBulkAction('activate', [
            'title' => __('Activate Selected', 'sikshya'),
            'action' => 'sikshya_bulk_activate_students',
        ]);

        $dataTable->addBulkAction('deactivate', [
            'title' => __('Deactivate Selected', 'sikshya'),
            'action' => 'sikshya_bulk_deactivate_students',
        ]);

        // Set filters
        $dataTable->setFilters([
            'status' => [
                'type' => 'select',
                'title' => __('Status', 'sikshya'),
                'options' => [
                    '' => __('All Statuses', 'sikshya'),
                    'active' => __('Active', 'sikshya'),
                    'inactive' => __('Inactive', 'sikshya'),
                    'pending' => __('Pending', 'sikshya'),
                ],
            ],
            'role' => [
                'type' => 'select',
                'title' => __('Role', 'sikshya'),
                'options' => [
                    '' => __('All Roles', 'sikshya'),
                    'student' => __('Student', 'sikshya'),
                    'instructor' => __('Instructor', 'sikshya'),
                    'administrator' => __('Administrator', 'sikshya'),
                ],
            ],
        ]);

        echo $dataTable->renderTable();
    }

    /**
     * Handle student list AJAX request
     */
    public function handleStudentList(): void
    {
        check_ajax_referer('sikshya_nonce', 'nonce');

        $args = [
            'role' => 'student',
            'number' => 20,
            'paged' => $_POST['page'] ?? 1,
        ];

        $users = get_users($args);
        $total = count_users();

        wp_send_json_success([
            'data' => $users,
            'total' => $total['avail_roles']['student'] ?? 0,
        ]);
    }

    /**
     * Handle student save AJAX request
     */
    public function handleStudentSave(): void
    {
        check_ajax_referer('sikshya_nonce', 'nonce');

        $user_id = intval($_POST['id'] ?? 0);
        $user_data = [
            'user_login' => sanitize_user($_POST['username'] ?? ''),
            'user_email' => sanitize_email($_POST['email'] ?? ''),
            'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
            'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
            'role' => 'student',
        ];

        if (!empty($_POST['password'])) {
            $user_data['user_pass'] = $_POST['password'];
        }

        try {
            if ($user_id) {
                $user_data['ID'] = $user_id;
                $result = wp_update_user($user_data);
                $message = __('Student updated successfully!', 'sikshya');
            } else {
                $result = wp_insert_user($user_data);
                $message = __('Student created successfully!', 'sikshya');
            }

            if (is_wp_error($result)) {
                throw new \Exception($result->get_error_message());
            }

            wp_send_json_success([
                'id' => $result,
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle student delete AJAX request
     */
    public function handleStudentDelete(): void
    {
        check_ajax_referer('sikshya_nonce', 'nonce');

        $user_id = intval($_POST['id'] ?? 0);

        try {
            if (!current_user_can('delete_users')) {
                throw new \Exception(__('You do not have permission to delete users.', 'sikshya'));
            }

            $result = wp_delete_user($user_id);
            if (!$result) {
                throw new \Exception(__('Failed to delete student.', 'sikshya'));
            }

            wp_send_json_success(__('Student deleted successfully!', 'sikshya'));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
} 