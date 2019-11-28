<?php
/**
 * List tables: Orders.
 *
 * @package Sikshya\admin
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('Sikshya_Admin_List_Table_Orders', false)) {
    return;
}

if (!class_exists('Sikshya_Admin_List_Table', false)) {
    include_once 'abstract-class-sikshya-admin-list-table.php';
}

/**
 * Sikshya_Admin_List_Table_Orders Class.
 */
class Sikshya_Admin_List_Table_Orders extends Sikshya_Admin_List_Table
{
    protected $order_items = array();

    /**
     * Post type.
     *
     * @var string
     */
    protected $list_table_type = SIKSHYA_ORDERS_CUSTOM_POST_TYPE;

    /**
     * Constructor.
     */
    public function __construct()
    {

        parent::__construct();
    }


    /**
     * Define primary column.
     *
     * @return string
     */
    protected function get_primary_column()
    {
        return 'ID';
    }

    /**
     * Get row actions to show in the list table.
     *
     * @param array $actions Array of actions.
     * @param WP_Post $post Current post object.
     * @return array
     */
    /*  protected function get_row_actions($actions, $post)
      {
          return array();
      }*/

    /**
     * Define hidden columns.
     *
     * @return array
     */
    protected function define_hidden_columns()
    {
        return array();
    }

    /**
     * Define which columns are sortable.
     *
     * @param array $columns Existing columns.
     * @return array
     */
    public function define_sortable_columns($columns)
    {
        $custom = array(
            'cb' => 'cb',
        );
        unset($columns['comments']);

        return wp_parse_args($custom, $columns);
    }

    /**
     * Define which columns to show on this screen.
     *
     * @param array $columns Existing columns.
     * @return array
     */
    public function define_columns($columns)
    {
        $show_columns = array();
        $show_columns['cb'] = $columns['cb'];
        $show_columns['order_id'] = __('Order ID', 'sikshya');
        $show_columns['course'] = __('Course', 'sikshya');
        $show_columns['student'] = __('Student', 'sikshya');
        $show_columns['date'] = __('Booking Date', 'sikshya');

        return $show_columns;
    }

    /**
     * Define bulk actions.
     *
     * @param array $actions Existing actions.
     * @return array
     */
    public function define_bulk_actions($actions)
    {
        return $actions;
    }

    /**
     * Pre-fetch any data for the row each column has access to it. the_order global is there for bw compat.
     *
     * @param int $post_id Post ID being shown.
     */
    protected function prepare_row_data($post_id)
    {

        $this->object = get_post($post_id);

        $this->order_items = sikshya_get_order_items(array(), array('order_id' => $post_id));


    }

    protected function render_order_id_column()
    {
        $order_id = $this->object->ID;

        echo '<a href="">' . '#SIKORDER' . $order_id . '</a>';
    }

    protected function render_course_column()
    {
        foreach ($this->order_items as $order_item) {

            $order_item_id = isset($order_item->order_item_id) ? $order_item->order_item_id : 0;

            $course_id = sikshya_get_order_item_meta($order_item_id, '_course_id');

            $course_title = isset($order_item->item_name) ? $order_item->item_name : '';

            if (get_post_status($course_id)) {

                $course_title = get_the_title($course_id);
            }


            if (absint($course_id) > 0) {

                echo '<a href="' . get_edit_post_link($course_id) . '">' . esc_html($course_title) . '</a>';
            }
        }


    }

    protected function render_student_column()
    {
        $order_item_id = isset($this->order_items[0]) ? $this->order_items[0]->order_item_id : 0;

        $user_id = sikshya_get_order_item_meta($order_item_id, '_user_id');

        $user_data = get_userdata($user_id);

        $data = isset($user_data->data) ? $user_data->data : array();

        $display_name = isset($data->display_name) ? $data->display_name : '';

        echo '<a href="' . esc_url(admin_url('user-edit.php?user_id=' . absint($user_id))) . '">' . esc_html($display_name) . '</a>';
        
    }

}
