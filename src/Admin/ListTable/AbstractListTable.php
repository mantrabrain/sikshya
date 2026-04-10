<?php

/**
 * Abstract List Table Class
 *
 * Provides a reusable base for all list tables in Sikshya LMS
 *
 * @package Sikshya\Admin\ListTable
 * @since 1.0.0
 */

namespace Sikshya\Admin\ListTable;

use Sikshya\Admin\ReactAdminConfig;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include WordPress List Table class
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Abstract List Table Class
 *
 * Extends WordPress WP_List_Table to provide consistent functionality
 * across all Sikshya admin list tables.
 */
abstract class AbstractListTable extends \WP_List_Table
{
    /**
     * Plugin instance
     *
     * @var \Sikshya\Core\Plugin
     */
    protected $plugin;

    /**
     * Table configuration
     *
     * @var array
     */
    protected $config = [];

    /**
     * Constructor
     *
     * @param \Sikshya\Core\Plugin $plugin
     * @param array $config
     */
    public function __construct($plugin, array $config = [])
    {
        $this->plugin = $plugin;
        $this->config = array_merge($this->getDefaultConfig(), $config);

        parent::__construct([
            'singular' => $this->config['singular'] ?? 'item',
            'plural' => $this->config['plural'] ?? 'items',
            'ajax' => $this->config['ajax'] ?? false,
            'screen' => $this->config['screen'] ?? null,
        ]);
    }

    /**
     * Get default configuration
     *
     * @return array
     */
    protected function getDefaultConfig(): array
    {
        return [
            'title' => '',
            'description' => '',
            'singular' => 'item',
            'plural' => 'items',
            'ajax' => false,
            'per_page' => 20,
            'per_page_options' => [10, 20, 50, 100],
            'search' => true,
            'filters' => [],
            'bulk_actions' => [],
            'columns' => [],
            'sortable_columns' => [],
            'hidden_columns' => [],
            'primary_column' => 'title',
            'empty_message' => __('No items found.', 'sikshya'),
        ];
    }

    /**
     * Get columns
     *
     * @return array
     */
    public function get_columns(): array
    {
        $columns = $this->config['columns'];

        // Add checkbox column for bulk actions
        if (!empty($this->config['bulk_actions'])) {
            $columns = array_merge(['cb' => '<input type="checkbox" />'], $columns);
        }

        return $columns;
    }

    /**
     * Get sortable columns
     *
     * @return array
     */
    public function get_sortable_columns(): array
    {
        return $this->config['sortable_columns'];
    }

    /**
     * Get hidden columns
     *
     * @return array
     */
    public function get_hidden_columns(): array
    {
        return $this->config['hidden_columns'];
    }

    /**
     * Get bulk actions
     *
     * @return array
     */
    public function get_bulk_actions(): array
    {
        return $this->config['bulk_actions'];
    }

    /**
     * Handle bulk actions
     *
     * @param string $action
     * @param array $ids
     * @return void
     */
    protected function handle_bulk_actions($action, $ids): void
    {
        if (empty($action) || empty($ids)) {
            return;
        }

        $ids = array_map('intval', $ids);

        switch ($action) {
            case 'delete':
                $this->bulk_delete($ids);
                break;
            case 'publish':
                $this->bulk_publish($ids);
                break;
            case 'draft':
                $this->bulk_draft($ids);
                break;
            default:
                $this->custom_bulk_action($action, $ids);
                break;
        }
    }

    /**
     * Bulk delete items
     *
     * @param array $ids
     * @return void
     */
    protected function bulk_delete($ids): void
    {
        $deleted = 0;

        foreach ($ids as $id) {
            if ($this->delete_item($id)) {
                $deleted++;
            }
        }

        $this->add_admin_notice(
            sprintf(
                _n(
                    '%d item deleted successfully.',
                    '%d items deleted successfully.',
                    $deleted,
                    'sikshya'
                ),
                $deleted
            ),
            'success'
        );
    }

    /**
     * Bulk publish items
     *
     * @param array $ids
     * @return void
     */
    protected function bulk_publish($ids): void
    {
        $published = 0;

        foreach ($ids as $id) {
            if ($this->update_item_status($id, 'publish')) {
                $published++;
            }
        }

        $this->add_admin_notice(
            sprintf(
                _n(
                    '%d item published successfully.',
                    '%d items published successfully.',
                    $published,
                    'sikshya'
                ),
                $published
            ),
            'success'
        );
    }

    /**
     * Bulk draft items
     *
     * @param array $ids
     * @return void
     */
    protected function bulk_draft($ids): void
    {
        $drafted = 0;

        foreach ($ids as $id) {
            if ($this->update_item_status($id, 'draft')) {
                $drafted++;
            }
        }

        $this->add_admin_notice(
            sprintf(
                _n(
                    '%d item moved to draft.',
                    '%d items moved to draft.',
                    $drafted,
                    'sikshya'
                ),
                $drafted
            ),
            'success'
        );
    }

    /**
     * Custom bulk action handler
     *
     * @param string $action
     * @param array $ids
     * @return void
     */
    protected function custom_bulk_action($action, $ids): void
    {
        // Override in child classes for custom bulk actions
    }

    /**
     * Delete a single item
     *
     * @param int $id
     * @return bool
     */
    protected function delete_item($id): bool
    {
        // Override in child classes
        return false;
    }

    /**
     * Update item status
     *
     * @param int $id
     * @param string $status
     * @return bool
     */
    protected function update_item_status($id, $status): bool
    {
        // Override in child classes
        return false;
    }

    /**
     * Add admin notice
     *
     * @param string $message
     * @param string $type
     * @return void
     */
    protected function add_admin_notice($message, $type = 'info'): void
    {
        $notice = [
            'message' => $message,
            'type' => $type,
        ];

        set_transient('sikshya_admin_notice', $notice, 30);
    }

    /**
     * Display admin notices
     *
     * @return void
     */
    public function display_admin_notices(): void
    {
        $notice = get_transient('sikshya_admin_notice');

        if ($notice) {
            $class = 'notice notice-' . $notice['type'];
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($notice['message']));
            delete_transient('sikshya_admin_notice');
        }
    }

    /**
     * Get items per page
     *
     * @param string $option
     * @param int $default
     * @return int
     */
    public function get_items_per_page($option = 'per_page', $default = 20): int
    {
        return $this->config['per_page'];
    }

    /**
     * Display the table
     *
     * @return void
     */
    public function display(): void
    {
        $this->display_admin_notices();

        // Display the table with modern design
        $this->display_table();
    }

    /**
     * Display the table with modern SaaS design
     *
     * @return void
     */
    protected function display_table(): void
    {
        // Add nonce field for security
        wp_nonce_field('bulk-' . $this->_args['plural']);

        echo '<div class="sikshya-list-table-container">';

        // Display header with search and filters
        $this->display_table_header();

        // Display bulk actions
        $this->display_bulk_actions();

        // Display the table
        $this->display_table_content();

        // Display pagination
        $this->display_pagination();

        echo '</div>';
    }

    /**
     * Display table header with search and filters
     *
     * @return void
     */
    protected function display_table_header(): void
    {
        echo '<div class="sikshya-list-table-header">';

        // Add filters if any (left side)
        if (!empty($this->config['filters'])) {
            $this->display_filters();
        }

        // Add search box if enabled (right side)
        if ($this->config['search']) {
            $this->display_search_box();
        }

        echo '</div>';
    }

    /**
     * Capture status filter tabs HTML for use in layout partials.
     *
     * @return string
     */
    public function render_status_filter_tabs_html(): string
    {
        ob_start();
        $this->display_status_filter_tabs();

        return (string) ob_get_clean();
    }

    /**
     * Display status filter tabs
     *
     * @return void
     */
    protected function display_status_filter_tabs(): void
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

        // Published tab
        if (isset($status_counts['publish'])) {
            $publish_class = ($current_status === 'publish') ? 'current' : '';
            $publish_url = add_query_arg('post_status', 'publish', $base_url);
            echo '<li class="publish">';
            echo '<a href="' . esc_url($publish_url) . '" class="' . esc_attr($publish_class) . '">';
            echo esc_html__('Published', 'sikshya') . ' <span class="count">(' . esc_html($status_counts['publish']) . ')</span>';
            echo '</a> |</li>';
        }

        // Draft tab
        if (isset($status_counts['draft'])) {
            $draft_class = ($current_status === 'draft') ? 'current' : '';
            $draft_url = add_query_arg('post_status', 'draft', $base_url);
            echo '<li class="draft">';
            echo '<a href="' . esc_url($draft_url) . '" class="' . esc_attr($draft_class) . '">';
            echo esc_html__('Draft', 'sikshya') . ' <span class="count">(' . esc_html($status_counts['draft']) . ')</span>';
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

        // Private tab
        if (isset($status_counts['private'])) {
            $private_class = ($current_status === 'private') ? 'current' : '';
            $private_url = add_query_arg('post_status', 'private', $base_url);
            echo '<li class="private">';
            echo '<a href="' . esc_url($private_url) . '" class="' . esc_attr($private_class) . '">';
            echo esc_html__('Private', 'sikshya') . ' <span class="count">(' . esc_html($status_counts['private']) . ')</span>';
            echo '</a></li>';
        }

        echo '</ul>';
    }

    /**
     * Get status counts for filter tabs
     *
     * @return array
     */
    protected function get_status_counts(): array
    {
        // For demo purposes, return dummy counts
        return [
            'publish' => 5,
            'draft' => 2,
            'pending' => 1,
            'private' => 1
        ];

        // TODO: Implement actual status counting logic
        // $counts = [];
        // $statuses = ['publish', 'draft', 'pending', 'private'];
        // foreach ($statuses as $status) {
        //     $args = [
        //         'post_type' => $this->config['post_type'] ?? 'post',
        //         'post_status' => $status,
        //         'posts_per_page' => -1,
        //         'fields' => 'ids'
        //     ];
        //     $query = new \WP_Query($args);
        //     $counts[$status] = $query->found_posts;
        // }
        // return $counts;
    }

    /**
     * Display table footer with column headers
     *
     * @return void
     */
    protected function display_table_footer(): void
    {
        echo '<tfoot>';
        echo '<tr>';

        $columns = $this->get_columns();
        $sortable_columns = $this->get_sortable_columns();

        foreach ($columns as $column_key => $column_display_name) {
            $class = ['manage-column', 'column-' . $column_key];

            if ($column_key === $this->get_primary_column()) {
                $class[] = 'column-primary';
            }

            if (isset($sortable_columns[$column_key])) {
                $class[] = 'sortable';
                $class[] = $this->get_current_order($column_key);

                $order = $this->get_current_order($column_key) === 'asc' ? 'desc' : 'asc';
                $url = add_query_arg(['orderby' => $column_key, 'order' => $order]);

                echo '<th scope="col" class="' . esc_attr(implode(' ', $class)) . '">';
                echo '<a href="' . esc_url($url) . '">';
                echo '<span>' . esc_html($column_display_name) . '</span>';
                echo '<div class="sikshya-sort-indicator">';
                echo '<div class="sikshya-sort-arrow up"></div>';
                echo '<div class="sikshya-sort-arrow down"></div>';
                echo '</div>';
                echo '</a>';
                echo '</th>';
            } else {
                echo '<th scope="col" class="' . esc_attr(implode(' ', $class)) . '">';
                if ($column_key === 'cb') {
                    echo '<input type="checkbox" class="sikshya-checkbox" id="cb-select-all-2">';
                } else {
                    echo esc_html($column_display_name);
                }
                echo '</th>';
            }
        }

        echo '</tr>';
        echo '</tfoot>';
    }

    /**
     * Display modern search box
     *
     * @return void
     */
    protected function display_search_box(): void
    {
        $search_term = $this->getSearchTerm();
        echo '<div class="sikshya-search-container">';
        echo '<input type="search" class="sikshya-search-input" name="s" value="' . esc_attr($search_term) . '" placeholder="' . esc_attr__('Search courses...', 'sikshya') . '">';
        echo '<button type="submit" class="sikshya-search-button">';
        echo '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        echo '<circle cx="11" cy="11" r="8"></circle>';
        echo '<path d="m21 21-4.35-4.35"></path>';
        echo '</svg>';
        echo esc_html__('Search', 'sikshya');
        echo '</button>';
        echo '</div>';
    }

    /**
     * Display modern filters
     *
     * @return void
     */
    protected function display_filters(): void
    {
        echo '<div class="sikshya-filters-container">';

        foreach ($this->config['filters'] as $key => $filter) {
            $this->display_filter($key, $filter);
        }

        echo '</div>';
    }

    /**
     * Display a single filter
     *
     * @param string $key
     * @param array $filter
     * @return void
     */
    protected function display_filter($key, $filter): void
    {
        $current_value = $_GET[$key] ?? '';
        $type = $filter['type'] ?? 'select';

        echo '<div class="sikshya-filter-group">';

        switch ($type) {
            case 'select':
                echo '<select id="filter-' . esc_attr($key) . '" name="' . esc_attr($key) . '" class="sikshya-filter-select">';
                foreach ($filter['options'] as $value => $label) {
                    $selected = selected($current_value, $value, false);
                    echo '<option value="' . esc_attr($value) . '" ' . $selected . '>';
                    echo esc_html($label);
                    echo '</option>';
                }
                echo '</select>';
                break;

            case 'text':
                echo '<input type="text" id="filter-' . esc_attr($key) . '" name="' . esc_attr($key) . '" ';
                echo 'value="' . esc_attr($current_value) . '" class="sikshya-filter-select" ';
                echo 'placeholder="' . esc_attr($filter['placeholder'] ?? '') . '">';
                break;

            case 'date':
                echo '<input type="date" id="filter-' . esc_attr($key) . '" name="' . esc_attr($key) . '" ';
                echo 'value="' . esc_attr($current_value) . '" class="sikshya-filter-select">';
                break;
        }

        echo '</div>';
    }

    /**
     * Display bulk actions
     *
     * @return void
     */
    protected function display_bulk_actions(): void
    {
        $bulk_actions = $this->get_bulk_actions();
        if (empty($bulk_actions)) {
            return;
        }

        echo '<div class="sikshya-bulk-actions">';
        echo '<select name="action" class="sikshya-bulk-select">';
        echo '<option value="-1">' . esc_html__('Bulk Actions', 'sikshya') . '</option>';

        foreach ($bulk_actions as $action => $label) {
            echo '<option value="' . esc_attr($action) . '">' . esc_html($label) . '</option>';
        }

        echo '</select>';
        echo '<button type="submit" class="sikshya-bulk-button">' . esc_html__('Apply', 'sikshya') . '</button>';
        echo '</div>';
    }

    /**
     * Display table content
     *
     * @return void
     */
    protected function display_table_content(): void
    {
        echo '<div class="sikshya-table-container">';
        echo '<table class="sikshya-table">';

        // Display headers
        $this->display_table_headers();

        // Display body
        $this->display_table_body();

        // Display footer
        $this->display_table_footer();

        echo '</table>';
        echo '</div>';
    }

    /**
     * Display table headers
     *
     * @return void
     */
    protected function display_table_headers(): void
    {
        echo '<thead>';
        echo '<tr>';

        $columns = $this->get_columns();
        $sortable_columns = $this->get_sortable_columns();

        foreach ($columns as $column_key => $column_display_name) {
            $class = ['manage-column', 'column-' . $column_key];

            if ($column_key === $this->get_primary_column()) {
                $class[] = 'column-primary';
            }

            if (isset($sortable_columns[$column_key])) {
                $class[] = 'sortable';
                $class[] = $this->get_current_order($column_key);

                $order = $this->get_current_order($column_key) === 'asc' ? 'desc' : 'asc';
                $url = add_query_arg(['orderby' => $column_key, 'order' => $order]);

                echo '<th scope="col" class="' . esc_attr(implode(' ', $class)) . '">';
                echo '<a href="' . esc_url($url) . '">';
                echo '<span>' . esc_html($column_display_name) . '</span>';
                echo '<div class="sikshya-sort-indicator">';
                echo '<div class="sikshya-sort-arrow up"></div>';
                echo '<div class="sikshya-sort-arrow down"></div>';
                echo '</div>';
                echo '</a>';
                echo '</th>';
            } else {
                echo '<th scope="col" class="' . esc_attr(implode(' ', $class)) . '">';
                if ($column_key === 'cb') {
                    echo '<input type="checkbox" class="sikshya-checkbox" id="cb-select-all-1">';
                } else {
                    echo esc_html($column_display_name);
                }
                echo '</th>';
            }
        }

        echo '</tr>';
        echo '</thead>';
    }

    /**
     * Display table body
     *
     * @return void
     */
    protected function display_table_body(): void
    {
        echo '<tbody>';

        $items = $this->get_items();

        if (empty($items)) {
            $this->display_empty_state();
        } else {
            foreach ($items as $item) {
                $this->display_table_row($item);
            }
        }

        echo '</tbody>';
    }

    /**
     * Display table row
     *
     * @param mixed $item
     * @return void
     */
    protected function display_table_row($item): void
    {
        $columns = $this->get_columns();
        $primary_column = $this->get_primary_column();

        echo '<tr>';

        foreach ($columns as $column_key => $column_display_name) {
            $class = ['column-' . $column_key];

            if ($column_key === $primary_column) {
                $class[] = 'column-primary';
            }

            if ($column_key === 'cb') {
                $class[] = 'sikshya-checkbox-column';
            }

            echo '<td class="' . esc_attr(implode(' ', $class)) . '">';

            if ($column_key === 'cb') {
                echo '<input type="checkbox" class="sikshya-checkbox" name="post[]" value="' . esc_attr($item->ID ?? '') . '">';
            } else {
                $this->display_column_content($column_key, $item);
            }

            echo '</td>';
        }

        echo '</tr>';
    }

    /**
     * Display column content
     *
     * @param string $column_key
     * @param mixed $item
     * @return void
     */
    protected function display_column_content($column_key, $item): void
    {
        // Try to call the specific column method first
        $method_name = 'column' . ucfirst($column_key);
        if (method_exists($this, $method_name)) {
            echo $this->$method_name($item);
        } else {
            // Fall back to column_default
            echo $this->column_default($item, $column_key);
        }
    }

    /**
     * Display empty state
     *
     * @return void
     */
    protected function display_empty_state(): void
    {
        echo '<tr>';
        echo '<td colspan="' . count($this->get_columns()) . '" class="sikshya-no-items">';
        echo '<div class="sikshya-empty-state">';
        echo '<svg class="sikshya-empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        echo '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>';
        echo '</svg>';
        echo '<h3 class="sikshya-empty-state-title">' . esc_html($this->config['empty_message']) . '</h3>';
        echo '<p class="sikshya-empty-state-description">' . esc_html__('Get started by creating your first course.', 'sikshya') . '</p>';
        echo '<a href="' . esc_url(ReactAdminConfig::reactAppUrl('add-course')) . '" class="sikshya-btn sikshya-btn-primary">';
        echo '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        echo '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>';
        echo '</svg>';
        echo esc_html__('Create Course', 'sikshya');
        echo '</a>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
    }

    /**
     * Display pagination
     *
     * @return void
     */
    protected function display_pagination(): void
    {
        $total_items = $this->get_total_items();
        $per_page = $this->get_items_per_page();
        $current_page = $this->get_pagenum();
        $total_pages = ceil($total_items / $per_page);

        if ($total_pages <= 1) {
            return;
        }

        echo '<div class="sikshya-pagination">';

        // Pagination info
        $start = (($current_page - 1) * $per_page) + 1;
        $end = min($current_page * $per_page, $total_items);
        echo '<div class="sikshya-pagination-info">';
        echo sprintf(
            esc_html__('Showing %1$s to %2$s of %3$s results', 'sikshya'),
            number_format_i18n($start),
            number_format_i18n($end),
            number_format_i18n($total_items)
        );
        echo '</div>';

        // Pagination controls
        echo '<div class="sikshya-pagination-controls">';

        // Previous button
        if ($current_page > 1) {
            $prev_url = add_query_arg('paged', $current_page - 1);
            echo '<a href="' . esc_url($prev_url) . '" class="sikshya-pagination-button">';
            echo '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>';
            echo '</svg>';
            echo '</a>';
        } else {
            echo '<span class="sikshya-pagination-button disabled">';
            echo '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>';
            echo '</svg>';
            echo '</span>';
        }

        // Page numbers
        $start_page = max(1, $current_page - 2);
        $end_page = min($total_pages, $current_page + 2);

        for ($i = $start_page; $i <= $end_page; $i++) {
            if ($i == $current_page) {
                echo '<span class="sikshya-pagination-button current">' . esc_html($i) . '</span>';
            } else {
                $page_url = add_query_arg('paged', $i);
                echo '<a href="' . esc_url($page_url) . '" class="sikshya-pagination-button">' . esc_html($i) . '</a>';
            }
        }

        // Next button
        if ($current_page < $total_pages) {
            $next_url = add_query_arg('paged', $current_page + 1);
            echo '<a href="' . esc_url($next_url) . '" class="sikshya-pagination-button">';
            echo '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>';
            echo '</svg>';
            echo '</a>';
        } else {
            echo '<span class="sikshya-pagination-button disabled">';
            echo '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>';
            echo '</svg>';
            echo '</span>';
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * Get current order for column
     *
     * @param string $column_key
     * @return string
     */
    protected function get_current_order($column_key): string
    {
        $orderby = $_GET['orderby'] ?? '';
        $order = $_GET['order'] ?? 'asc';

        if ($orderby === $column_key) {
            return $order;
        }

        return '';
    }



    /**
     * Get current page URL with parameters
     *
     * @return string
     */
    protected function get_current_page_url(): string
    {
        $url = remove_query_arg(['paged', 'orderby', 'order']);

        // Add back current parameters
        if (!empty($_GET['paged'])) {
            $url = add_query_arg('paged', intval($_GET['paged']), $url);
        }

        if (!empty($_GET['orderby'])) {
            $url = add_query_arg('orderby', sanitize_text_field($_GET['orderby']), $url);
        }

        if (!empty($_GET['order'])) {
            $url = add_query_arg('order', sanitize_text_field($_GET['order']), $url);
        }

        return $url;
    }

    /**
     * Get sortable column URL
     *
     * @param string $column
     * @return string
     */
    protected function get_sortable_column_url($column): string
    {
        $url = $this->get_current_page_url();
        $current_order = $_GET['order'] ?? 'asc';
        $current_orderby = $_GET['orderby'] ?? '';

        $new_order = ($current_orderby === $column && $current_order === 'asc') ? 'desc' : 'asc';

        return add_query_arg([
            'orderby' => $column,
            'order' => $new_order,
        ], $url);
    }

    /**
     * Render sortable column header
     *
     * @param string $column
     * @param string $title
     * @return string
     */
    protected function render_sortable_column_header($column, $title): string
    {
        $url = $this->get_sortable_column_url($column);
        $current_order = $_GET['order'] ?? 'asc';
        $current_orderby = $_GET['orderby'] ?? '';

        $class = 'manage-column sortable';
        $order_icon = '';

        if ($current_orderby === $column) {
            $class .= ' sorted';
            $order_icon = $current_order === 'asc' ? ' ↑' : ' ↓';
        }

        return sprintf(
            '<a href="%s"><span>%s</span><span class="sorting-indicator">%s</span></a>',
            esc_url($url),
            esc_html($title),
            $order_icon
        );
    }

    /**
     * Get primary column
     *
     * @return string
     */
    public function get_primary_column(): string
    {
        return $this->config['primary_column'];
    }

    /**
     * Get row actions
     *
     * @param object $item
     * @return array
     */
    protected function get_row_actions($item): array
    {
        return [];
    }

    /**
     * Render row actions
     *
     * @param array $actions
     * @param bool $always_visible
     * @return string
     */
    protected function row_actions($actions, $always_visible = false): string
    {
        $action_count = count($actions);
        $i = 0;

        if (!$action_count) {
            return '';
        }

        $out = '<div class="' . ($always_visible ? 'row-actions visible' : 'row-actions') . '">';

        foreach ($actions as $action => $link) {
            ++$i;
            ($i == $action_count) ? $sep = '' : $sep = ' | ';
            $out .= "<span class='$action'>$link$sep</span>";
        }

        $out .= '</div>';

        return $out;
    }

    /**
     * Prepare items for display
     *
     * @return void
     */
    public function prepare_items(): void
    {
        // Set column headers
        $this->_column_headers = [
            $this->get_columns(),
            $this->get_hidden_columns(),
            $this->get_sortable_columns(),
            $this->get_primary_column(),
        ];

        // Handle bulk actions
        $this->process_bulk_action();

        // Get items
        $this->items = $this->get_items();

        // Set pagination
        $this->set_pagination_args([
            'total_items' => $this->get_total_items(),
            'per_page' => $this->get_items_per_page($this->config['per_page']),
            'total_pages' => ceil($this->get_total_items() / $this->get_items_per_page($this->config['per_page'])),
        ]);
    }

    /**
     * Get items for the table
     *
     * @return array
     */
    abstract protected function get_items(): array;

    /**
     * Get total number of items
     *
     * @return int
     */
    abstract protected function get_total_items(): int;

    /**
     * Process bulk actions
     *
     * @return void
     */
    protected function process_bulk_action(): void
    {
        $action = $this->current_action();

        if ($action && isset($_POST['item'])) {
            $this->handle_bulk_actions($action, $_POST['item']);
        }
    }

    /**
     * Get current action
     *
     * @return string|false
     */
    public function current_action(): string|false
    {
        if (isset($_REQUEST['action']) && -1 != $_REQUEST['action']) {
            return $_REQUEST['action'];
        }

        if (isset($_REQUEST['action2']) && -1 != $_REQUEST['action2']) {
            return $_REQUEST['action2'];
        }

        return false;
    }



    /**
     * Display when no items are found
     *
     * @return void
     */
    public function no_items(): void
    {
        echo '<tr><td colspan="' . esc_attr($this->get_column_count()) . '" class="sikshya-no-items">';
        echo '<div class="sikshya-empty-state">';
        echo '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        echo '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>';
        echo '</svg>';
        echo '<p>' . esc_html($this->config['empty_message']) . '</p>';
        echo '</div>';
        echo '</td></tr>';
    }

    /**
     * Get column count
     *
     * @return int
     */
    public function get_column_count(): int
    {
        $columns = $this->get_columns();
        return count($columns);
    }
}
