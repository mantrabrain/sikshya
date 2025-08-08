<?php

namespace Sikshya\Admin\Controllers;

use Sikshya\Core\Plugin;
use Sikshya\Admin\Views\DataTable;

/**
 * Instructor Controller
 *
 * @package Sikshya\Admin\Controllers
 */
class InstructorController
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
        add_action('wp_ajax_sikshya_instructor_list', [$this, 'handleInstructorList']);
        add_action('wp_ajax_sikshya_instructor_save', [$this, 'handleInstructorSave']);
        add_action('wp_ajax_sikshya_instructor_delete', [$this, 'handleInstructorDelete']);
    }

    /**
     * Render instructors list page
     */
    public function renderInstructorsPage(): void
    {
        $dataTable = new DataTable($this->plugin, [
            'id' => 'sikshya-instructors-table',
            'title' => __('Instructors', 'sikshya'),
            'description' => __('Manage your instructors', 'sikshya'),
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

        $dataTable->addColumn('courses_created', [
            'title' => __('Courses Created', 'sikshya'),
            'sortable' => true,
        ]);

        $dataTable->addColumn('total_students', [
            'title' => __('Total Students', 'sikshya'),
            'sortable' => true,
        ]);

        $dataTable->addColumn('rating', [
            'title' => __('Rating', 'sikshya'),
            'sortable' => true,
        ]);

        $dataTable->addColumn('earnings', [
            'title' => __('Total Earnings', 'sikshya'),
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
            'url' => admin_url('admin.php?page=sikshya-view-instructor&id={id}'),
            'class' => 'button button-small',
        ]);

        $dataTable->addAction('edit', [
            'title' => __('Edit', 'sikshya'),
            'url' => admin_url('admin.php?page=sikshya-edit-instructor&id={id}'),
            'class' => 'button button-small',
        ]);

        $dataTable->addAction('delete', [
            'title' => __('Delete', 'sikshya'),
            'url' => '#',
            'class' => 'button button-small button-link-delete',
            'onclick' => 'sikshya.deleteInstructor({id})',
        ]);

        // Add bulk actions
        $dataTable->addBulkAction('delete', [
            'title' => __('Delete Selected', 'sikshya'),
            'action' => 'sikshya_bulk_delete_instructors',
        ]);

        $dataTable->addBulkAction('approve', [
            'title' => __('Approve Selected', 'sikshya'),
            'action' => 'sikshya_bulk_approve_instructors',
        ]);

        $dataTable->addBulkAction('reject', [
            'title' => __('Reject Selected', 'sikshya'),
            'action' => 'sikshya_bulk_reject_instructors',
        ]);

        // Set filters
        $dataTable->setFilters([
            'status' => [
                'type' => 'select',
                'title' => __('Status', 'sikshya'),
                'options' => [
                    '' => __('All Statuses', 'sikshya'),
                    'approved' => __('Approved', 'sikshya'),
                    'pending' => __('Pending', 'sikshya'),
                    'rejected' => __('Rejected', 'sikshya'),
                    'suspended' => __('Suspended', 'sikshya'),
                ],
            ],
            'rating' => [
                'type' => 'select',
                'title' => __('Rating', 'sikshya'),
                'options' => [
                    '' => __('All Ratings', 'sikshya'),
                    '5' => __('5 Stars', 'sikshya'),
                    '4' => __('4+ Stars', 'sikshya'),
                    '3' => __('3+ Stars', 'sikshya'),
                    '2' => __('2+ Stars', 'sikshya'),
                    '1' => __('1+ Stars', 'sikshya'),
                ],
            ],
        ]);

        echo $dataTable->renderTable();
    }

    /**
     * Handle instructor list AJAX request
     */
    public function handleInstructorList(): void
    {
        check_ajax_referer('sikshya_nonce', 'nonce');

        $args = [
            'role' => 'instructor',
            'number' => 20,
            'paged' => $_POST['page'] ?? 1,
        ];

        $users = get_users($args);
        $total = count_users();

        wp_send_json_success([
            'data' => $users,
            'total' => $total['avail_roles']['instructor'] ?? 0,
        ]);
    }

    /**
     * Handle instructor save AJAX request
     */
    public function handleInstructorSave(): void
    {
        check_ajax_referer('sikshya_nonce', 'nonce');

        $user_id = intval($_POST['id'] ?? 0);
        $user_data = [
            'user_login' => sanitize_user($_POST['username'] ?? ''),
            'user_email' => sanitize_email($_POST['email'] ?? ''),
            'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
            'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
            'role' => 'instructor',
        ];

        if (!empty($_POST['password'])) {
            $user_data['user_pass'] = $_POST['password'];
        }

        try {
            if ($user_id) {
                $user_data['ID'] = $user_id;
                $result = wp_update_user($user_data);
                $message = __('Instructor updated successfully!', 'sikshya');
            } else {
                $result = wp_insert_user($user_data);
                $message = __('Instructor created successfully!', 'sikshya');
            }

            if (is_wp_error($result)) {
                throw new \Exception($result->get_error_message());
            }

            // Update instructor meta
            if (isset($_POST['bio'])) {
                update_user_meta($result, 'sikshya_bio', sanitize_textarea_field($_POST['bio']));
            }

            if (isset($_POST['expertise'])) {
                update_user_meta($result, 'sikshya_expertise', sanitize_text_field($_POST['expertise']));
            }

            if (isset($_POST['website'])) {
                update_user_meta($result, 'sikshya_website', esc_url_raw($_POST['website']));
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
     * Handle instructor delete AJAX request
     */
    public function handleInstructorDelete(): void
    {
        check_ajax_referer('sikshya_nonce', 'nonce');

        $user_id = intval($_POST['id'] ?? 0);

        try {
            if (!current_user_can('delete_users')) {
                throw new \Exception(__('You do not have permission to delete users.', 'sikshya'));
            }

            $result = wp_delete_user($user_id);
            if (!$result) {
                throw new \Exception(__('Failed to delete instructor.', 'sikshya'));
            }

            wp_send_json_success(__('Instructor deleted successfully!', 'sikshya'));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
} 