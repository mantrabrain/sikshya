<?php

namespace Sikshya\Admin\Controllers;

use Sikshya\Core\Plugin;
use Sikshya\Admin\Views\DataTable;
use Sikshya\Admin\ListTable\StudentsListTable;

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
        // Create and prepare the list table
        $list_table = new StudentsListTable($this->plugin);
        $list_table->prepare_items();
        
        // Render the page with proper Sikshya design
        ?>
        <div class="sikshya-dashboard">
            <!-- Header -->
            <div class="sikshya-header">
                <div class="sikshya-header-title">
                    <h1>
                        <i class="fas fa-users"></i>
                        <?php _e('Students', 'sikshya'); ?>
                    </h1>
                    <span class="sikshya-version">v<?php echo esc_html(SIKSHYA_VERSION); ?></span>
                </div>
                <div class="sikshya-header-actions">
                    <a href="<?php echo admin_url('admin.php?page=sikshya-add-course'); ?>" class="sikshya-btn sikshya-btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        <?php _e('Add New Student', 'sikshya'); ?>
                    </a>
                </div>
            </div>

            <div class="sikshya-main-content">
                <div class="sikshya-content-card">
                    <div class="sikshya-content-card-header">
                        <div class="sikshya-content-card-header-left">
                            <h3 class="sikshya-content-card-title">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                </svg>
                                <?php _e('Manage Students', 'sikshya'); ?>
                            </h3>
                            <p class="sikshya-content-card-subtitle"><?php _e('View and manage your enrolled students', 'sikshya'); ?></p>
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
        
        // Active tab
        if (isset($status_counts['active'])) {
            $active_class = ($current_status === 'active') ? 'current' : '';
            $active_url = add_query_arg('post_status', 'active', $base_url);
            echo '<li class="active">';
            echo '<a href="' . esc_url($active_url) . '" class="' . esc_attr($active_class) . '">';
            echo esc_html__('Active', 'sikshya') . ' <span class="count">(' . esc_html($status_counts['active']) . ')</span>';
            echo '</a> |</li>';
        }
        
        // Inactive tab
        if (isset($status_counts['inactive'])) {
            $inactive_class = ($current_status === 'inactive') ? 'current' : '';
            $inactive_url = add_query_arg('post_status', 'inactive', $base_url);
            echo '<li class="inactive">';
            echo '<a href="' . esc_url($inactive_url) . '" class="' . esc_attr($inactive_class) . '">';
            echo esc_html__('Inactive', 'sikshya') . ' <span class="count">(' . esc_html($status_counts['inactive']) . ')</span>';
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
            'all' => 12,
            'active' => 8,
            'inactive' => 3,
            'pending' => 1,
        ];
        
        // TODO: Implement actual status counting
        /*
        global $wpdb;
        
        $counts = [
            'all' => 0,
            'active' => 0,
            'inactive' => 0,
            'pending' => 0,
        ];
        
        $results = $wpdb->get_results("
            SELECT um_status.meta_value as status, COUNT(*) as count
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um_status ON u.ID = um_status.user_id AND um_status.meta_key = '_sikshya_student_status'
            WHERE u.ID IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '{$wpdb->prefix}capabilities' AND meta_value LIKE '%student%')
            GROUP BY um_status.meta_value
        ");
        
        foreach ($results as $result) {
            $status = $result->status ?: 'active';
            $counts[$status] = $result->count;
            $counts['all'] += $result->count;
        }
        
        return $counts;
        */
    }
} 