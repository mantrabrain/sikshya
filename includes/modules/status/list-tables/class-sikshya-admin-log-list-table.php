<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Sikshya_Admin_Log_List_Table extends WP_List_Table
{

    private function get_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'sikshya_' . Sikshya_Tables::LOGS;
    }

    /**
     * Initialize the log table list.
     */
    public function __construct()
    {
        parent::__construct(
            array(
                'singular' => 'log',
                'plural' => 'logs',
                'ajax' => false,
            )
        );
    }

    /**
     * Display level dropdown
     *
     * @global wpdb $wpdb
     */
    public function level_dropdown()
    {

        $levels = array(
            array(
                'value' => Sikshya_Log_Levels::EMERGENCY,
                'label' => __('Emergency', 'sikshya'),
            ),
            array(
                'value' => Sikshya_Log_Levels::ALERT,
                'label' => __('Alert', 'sikshya'),
            ),
            array(
                'value' => Sikshya_Log_Levels::CRITICAL,
                'label' => __('Critical', 'sikshya'),
            ),
            array(
                'value' => Sikshya_Log_Levels::ERROR,
                'label' => __('Error', 'sikshya'),
            ),
            array(
                'value' => Sikshya_Log_Levels::WARNING,
                'label' => __('Warning', 'sikshya'),
            ),
            array(
                'value' => Sikshya_Log_Levels::NOTICE,
                'label' => __('Notice', 'sikshya'),
            ),
            array(
                'value' => Sikshya_Log_Levels::INFO,
                'label' => __('Info', 'sikshya'),
            ),
            array(
                'value' => Sikshya_Log_Levels::DEBUG,
                'label' => __('Debug', 'sikshya'),
            ),
        );

        $selected_level = isset($_REQUEST['level']) ? $_REQUEST['level'] : '';
        ?>
        <label for="filter-by-level"
               class="screen-reader-text"><?php esc_html_e('Filter by level', 'sikshya'); ?></label>
        <select name="level" id="filter-by-level">
            <option<?php selected($selected_level, ''); ?>
                    value=""><?php esc_html_e('All levels', 'sikshya'); ?></option>
            <?php
            foreach ($levels as $l) {
                printf(
                    '<option%1$s value="%2$s">%3$s</option>',
                    selected($selected_level, $l['value'], false),
                    esc_attr($l['value']),
                    esc_html($l['label'])
                );
            }
            ?>
        </select>
        <?php
    }

    /**
     * Get list columns.
     *
     * @return array
     */
    public function get_columns()
    {
        return array(
            'cb' => '<input type="checkbox" />',
            'timestamp' => __('Timestamp', 'sikshya'),
            'level' => __('Level', 'sikshya'),
            'message' => __('Message', 'sikshya'),
            'source' => __('Source', 'sikshya'),
        );
    }

    /**
     * Column cb.
     *
     * @param array $log
     * @return string
     */
    public function column_cb($log)
    {
        return sprintf('<input type="checkbox" name="log[]" value="%1$s" />', esc_attr($log['log_id']));
    }

    /**
     * Timestamp column.
     *
     * @param array $log
     * @return string
     */
    public function column_timestamp($log)
    {
        return esc_html(
            mysql2date(
                'Y-m-d H:i:s',
                $log['timestamp']
            )
        );
    }

    /**
     * Level column.
     *
     * @param array $log
     * @return string
     */
    public function column_level($log)
    {
        $level_key = Sikshya_Log_Levels::get_severity_level($log['level']);
        $levels = array(
            'emergency' => __('Emergency', 'sikshya'),
            'alert' => __('Alert', 'sikshya'),
            'critical' => __('Critical', 'sikshya'),
            'error' => __('Error', 'sikshya'),
            'warning' => __('Warning', 'sikshya'),
            'notice' => __('Notice', 'sikshya'),
            'info' => __('Info', 'sikshya'),
            'debug' => __('Debug', 'sikshya'),
        );

        if (!isset($levels[$level_key])) {
            return '';
        }

        $level = $levels[$level_key];
        $level_class = sanitize_html_class('log-level--' . $level_key);
        return '<span class="log-level ' . $level_class . '">' . esc_html($level) . '</span>';
    }

    /**
     * Message column.
     *
     * @param array $log
     * @return string
     */
    public function column_message($log)
    {
        return esc_html($log['message']);
    }

    /**
     * Source column.
     *
     * @param array $log
     * @return string
     */
    public function column_source($log)
    {
        return esc_html($log['source']);
    }

    /**
     * Get bulk actions.
     *
     * @return array
     */
    protected function get_bulk_actions()
    {
        return array(
            'delete' => __('Delete', 'sikshya'),
        );
    }

    /**
     * Extra controls to be displayed between bulk actions and pagination.
     *
     * @param string $which
     */
    protected function extra_tablenav($which)
    {
        if ('top' === $which) {
            echo '<div class="alignleft actions">';
            $this->level_dropdown();
            $this->source_dropdown();
            submit_button(__('Filter', 'sikshya'), '', 'filter-action', false);
            echo '</div>';
        }
    }

    /**
     * Get a list of sortable columns.
     *
     * @return array
     */
    protected function get_sortable_columns()
    {
        return array(
            'timestamp' => array('timestamp', true),
            'level' => array('level', true),
            'source' => array('source', true),
        );
    }

    /**
     * Display source dropdown
     *
     * @global wpdb $wpdb
     */
    protected function source_dropdown()
    {
        global $wpdb;

        $sources = $wpdb->get_col(
            "SELECT DISTINCT source
			FROM " . $this->get_table() . "
			WHERE source != ''
			ORDER BY source ASC"
        );

        if (!empty($sources)) {
            $selected_source = isset($_REQUEST['source']) ? $_REQUEST['source'] : '';
            ?>
            <label for="filter-by-source"
                   class="screen-reader-text"><?php esc_html_e('Filter by source', 'sikshya'); ?></label>
            <select name="source" id="filter-by-source">
                <option<?php selected($selected_source, ''); ?>
                        value=""><?php esc_html_e('All sources', 'sikshya'); ?></option>
                <?php
                foreach ($sources as $s) {
                    printf(
                        '<option%1$s value="%2$s">%3$s</option>',
                        selected($selected_source, $s, false),
                        esc_attr($s),
                        esc_html($s)
                    );
                }
                ?>
            </select>
            <?php
        }
    }

    /**
     * Prepare table list items.
     *
     * @global wpdb $wpdb
     */
    public function prepare_items()
    {
        global $wpdb;

        $this->prepare_column_headers();

        $per_page = $this->get_items_per_page('sikshya_status_log_items_per_page', 50);

        $where = $this->get_items_query_where();
        $order = $this->get_items_query_order();
        $limit = $this->get_items_query_limit();
        $offset = $this->get_items_query_offset();

        $query_items = "
			SELECT log_id, timestamp, level, message, source
			FROM " . $this->get_table() . "
			{$where} {$order} {$limit} {$offset}
		";

        $this->items = $wpdb->get_results($query_items, ARRAY_A);

        $query_count = "SELECT COUNT(log_id) FROM " . $this->get_table() . " {$where}";
        $total_items = $wpdb->get_var($query_count);

        $this->set_pagination_args(
            array(
                'total_items' => $total_items,
                'per_page' => $per_page,
                'total_pages' => ceil($total_items / $per_page),
            )
        );
    }

    /**
     * Get prepared LIMIT clause for items query
     *
     * @return string Prepared LIMIT clause for items query.
     * @global wpdb $wpdb
     *
     */
    protected function get_items_query_limit()
    {
        global $wpdb;

        $per_page = $this->get_items_per_page('sikshya_status_log_items_per_page', 50);
        return $wpdb->prepare('LIMIT %d', $per_page);
    }

    /**
     * Get prepared OFFSET clause for items query
     *
     * @return string Prepared OFFSET clause for items query.
     * @global wpdb $wpdb
     *
     */
    protected function get_items_query_offset()
    {
        global $wpdb;

        $per_page = $this->get_items_per_page('sikshya_status_log_items_per_page', 10);
        $current_page = $this->get_pagenum();
        if (1 < $current_page) {
            $offset = $per_page * ($current_page - 1);
        } else {
            $offset = 0;
        }

        return $wpdb->prepare('OFFSET %d', $offset);
    }

    /**
     * Get prepared ORDER BY clause for items query
     *
     * @return string Prepared ORDER BY clause for items query.
     */
    protected function get_items_query_order()
    {
        $valid_orders = array('level', 'source', 'timestamp');
        if (!empty($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], $valid_orders)) {
            $by = sikshya_clean($_REQUEST['orderby']);
        } else {
            $by = 'timestamp';
        }
        $by = esc_sql($by);

        if (!empty($_REQUEST['order']) && 'asc' === strtolower($_REQUEST['order'])) {
            $order = 'ASC';
        } else {
            $order = 'DESC';
        }

        return "ORDER BY {$by} {$order}, log_id {$order}";
    }

    /**
     * Get prepared WHERE clause for items query
     *
     * @return string Prepared WHERE clause for items query.
     * @global wpdb $wpdb
     *
     */
    protected function get_items_query_where()
    {
        global $wpdb;

        $where_conditions = array();
        $where_values = array();
        if (!empty($_REQUEST['level']) && Sikshya_Log_Levels::is_valid_level($_REQUEST['level'])) {
            $where_conditions[] = 'level >= %d';
            $where_values[] = Sikshya_Log_Levels::get_level_severity($_REQUEST['level']);
        }
        if (!empty($_REQUEST['source'])) {
            $where_conditions[] = 'source = %s';
            $where_values[] = sikshya_clean($_REQUEST['source']);
        }
        if (!empty($_REQUEST['s'])) {
            $where_conditions[] = 'message like %s';
            $where_values[] = '%' . $wpdb->esc_like(sikshya_clean(wp_unslash($_REQUEST['s']))) . '%';
        }

        if (empty($where_conditions)) {
            return '';
        }

        return $wpdb->prepare('WHERE 1 = 1 AND ' . implode(' AND ', $where_conditions), $where_values);
    }

    /**
     * Set _column_headers property for table list
     */
    protected function prepare_column_headers()
    {
        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns(),
        );
    }
}
