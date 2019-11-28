<?php
/**
 * List tables: Lessons.
 *
 * @package Sikshya\admin
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('Sikshya_Admin_List_Table_Lessons', false)) {
    return;
}

if (!class_exists('Sikshya_Admin_List_Table', false)) {
    include_once 'abstract-class-sikshya-admin-list-table.php';
}

/**
 * Sikshya_Admin_List_Table_Lessons Class.
 */
class Sikshya_Admin_List_Table_Lessons extends Sikshya_Admin_List_Table
{

    /**
     * Post type.
     *
     * @var string
     */
    protected $list_table_type = SIKSHYA_LESSONS_CUSTOM_POST_TYPE;

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
        $show_columns['title'] = __('Title', 'sikshya');
        $show_columns['course'] = __('Course', 'sikshya');
        $show_columns['date'] = __('Date', 'sikshya');

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

    }


    /**
     * Render columm: order_status.
     */
    protected function render_course_column()
    {
        $lesson_id = $this->object->ID;

        $courses = sikshya()->course->get_all_by_lesson($lesson_id);

        if (count($courses)) {

            echo '<div>';
            foreach ($courses as $course) {
                ?>
                <a href="<?php echo get_edit_post_link($course->ID); ?>"><?php echo get_the_title($course->ID); ?></a>
                <div class="sik-row-actions">
                    <a href="<?php echo get_edit_post_link($course->ID); ?>"><?php echo __('Edit', 'sikshya') ?></a>&nbsp;|&nbsp;
                    <a href="<?php echo get_post_permalink($course->ID) ?>"><?php echo __('View', 'sikshya') ?></a>
                </div>
                <?php
            }
            echo '</div>';
        } else {
            echo __('Not assigned yet', 'sikshya');
        }


    }
}
