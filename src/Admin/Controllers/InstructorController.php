<?php

namespace Sikshya\Admin\Controllers;

use Sikshya\Core\Plugin;
use Sikshya\Admin\Views\DataTable;
use Sikshya\Admin\ListTable\InstructorsListTable;

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
        // Create and prepare the list table
        $list_table = new InstructorsListTable($this->plugin);
        $list_table->prepare_items();
        
        // Render the page with proper Sikshya design
        ?>
        <div class="sikshya-dashboard">
            <!-- Header -->
            <div class="sikshya-header">
                <div class="sikshya-header-title">
                    <h1>
                        <i class="fas fa-chalkboard-teacher"></i>
                        <?php _e('Instructors', 'sikshya'); ?>
                    </h1>
                    <span class="sikshya-version">v<?php echo esc_html(SIKSHYA_VERSION); ?></span>
                </div>
                <div class="sikshya-header-actions">
                    <a href="<?php echo admin_url('admin.php?page=sikshya-add-course'); ?>" class="sikshya-btn sikshya-btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        <?php _e('Add New Instructor', 'sikshya'); ?>
                    </a>
                </div>
            </div>

            <div class="sikshya-main-content">
                <div class="sikshya-content-card">
                    <div class="sikshya-content-card-header">
                        <div class="sikshya-content-card-header-left">
                            <h3 class="sikshya-content-card-title">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                <?php _e('Manage Instructors', 'sikshya'); ?>
                            </h3>
                            <p class="sikshya-content-card-subtitle"><?php _e('View and manage your course instructors', 'sikshya'); ?></p>
                        </div>
                        <div class="sikshya-content-card-header-right">
                            <?php $this->display_status_filter_tabs(); ?>
                        </div>
                    </div>
                    <div class="sikshya-content-card-body">
                        <form method="post">
                            <?php
                            $list_table->display();
                            ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
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

    /**
     * Display status filter tabs
     * 
     * @return void
     */
    private function display_status_filter_tabs(): void
    {
        $current_status = $_GET['post_status'] ?? 'all';
        $base_url = remove_query_arg(['post_status', 'paged']);
        
        $status_counts = $this->get_status_counts();
        
        echo '<ul class="subsubsub">';
        
        // All tab
        $all_count = array_sum($status_counts);
        $all_class = ($current_status === 'all') ? 'current' : '';
        $all_url = $base_url;
        echo '<li class="all">';
        echo '<a href="' . esc_url($all_url) . '" class="' . esc_attr($all_class) . '"' . ($all_class ? ' aria-current="page"' : '') . '>';
        echo esc_html__('All', 'sikshya') . ' <span class="count">(' . esc_html($all_count) . ')</span>';
        echo '</a> |</li>';
        
        // Approved tab
        if (isset($status_counts['approved'])) {
            $approved_class = ($current_status === 'approved') ? 'current' : '';
            $approved_url = add_query_arg('post_status', 'approved', $base_url);
            echo '<li class="approved">';
            echo '<a href="' . esc_url($approved_url) . '" class="' . esc_attr($approved_class) . '">';
            echo esc_html__('Approved', 'sikshya') . ' <span class="count">(' . esc_html($status_counts['approved']) . ')</span>';
            echo '</a> |</li>';
        }
        
        // Pending tab
        if (isset($status_counts['pending'])) {
            $pending_class = ($current_status === 'pending') ? 'current' : '';
            $pending_url = add_query_arg('post_status', 'pending', $base_url);
            echo '<li class="pending">';
            echo '<a href="' . esc_url($pending_url) . '" class="' . esc_attr($pending_class) . '">';
            echo esc_html__('Pending', 'sikshya') . ' <span class="count">(' . esc_html($status_counts['pending']) . ')</span>';
            echo '</a> |</li>';
        }
        
        // Rejected tab
        if (isset($status_counts['rejected'])) {
            $rejected_class = ($current_status === 'rejected') ? 'current' : '';
            $rejected_url = add_query_arg('post_status', 'rejected', $base_url);
            echo '<li class="rejected">';
            echo '<a href="' . esc_url($rejected_url) . '" class="' . esc_attr($rejected_class) . '">';
            echo esc_html__('Rejected', 'sikshya') . ' <span class="count">(' . esc_html($status_counts['rejected']) . ')</span>';
            echo '</a> |</li>';
        }
        
        // Suspended tab
        if (isset($status_counts['suspended'])) {
            $suspended_class = ($current_status === 'suspended') ? 'current' : '';
            $suspended_url = add_query_arg('post_status', 'suspended', $base_url);
            echo '<li class="suspended">';
            echo '<a href="' . esc_url($suspended_url) . '" class="' . esc_attr($suspended_class) . '">';
            echo esc_html__('Suspended', 'sikshya') . ' <span class="count">(' . esc_html($status_counts['suspended']) . ')</span>';
            echo '</a> |</li>';
        }
        
        echo '</ul>';
    }

    /**
     * Get status counts for filter tabs
     *
     * @return array
     */
    private function get_status_counts(): array
    {
        // For demo purposes, return dummy counts
        return [
            'all' => 8,
            'approved' => 5,
            'pending' => 2,
            'rejected' => 1,
            'suspended' => 0,
        ];
        
        // TODO: Implement actual status counting
        /*
        global $wpdb;
        
        $counts = [
            'all' => 0,
            'approved' => 0,
            'pending' => 0,
            'rejected' => 0,
            'suspended' => 0,
        ];
        
        $results = $wpdb->get_results("
            SELECT um_status.meta_value as status, COUNT(*) as count
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um_status ON u.ID = um_status.user_id AND um_status.meta_key = '_sikshya_instructor_status'
            WHERE u.ID IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '{$wpdb->prefix}capabilities' AND meta_value LIKE '%instructor%')
            GROUP BY um_status.meta_value
        ");
        
        foreach ($results as $result) {
            $status = $result->status ?: 'pending';
            $counts[$status] = $result->count;
            $counts['all'] += $result->count;
        }
        
        return $counts;
        */
    }
} 