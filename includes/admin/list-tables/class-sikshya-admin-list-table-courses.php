<?php
/**
 * List tables: Courses.
 *
 * @package Sikshya\admin
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

if (class_exists('Sikshya_Admin_List_Table_Courses', false)) {
	return;
}

if (!class_exists('Sikshya_Admin_List_Table', false)) {
	include_once 'abstract-class-sikshya-admin-list-table.php';
}

/**
 * Sikshya_Admin_List_Table_Courses Class.
 */
class Sikshya_Admin_List_Table_Courses extends Sikshya_Admin_List_Table
{

	/**
	 * Post type.
	 *
	 * @var string
	 */
	protected $list_table_type = SIKSHYA_COURSES_CUSTOM_POST_TYPE;

	/**
	 * Constructor.
	 */
	public function __construct()
	{

		parent::__construct();
	}

	protected function render_blank_state()
	{
		echo '<div class="sikshya-blankstate">';

		echo '<h2 class="sikshya-blankstate-message">' . esc_html__('Ready to start your own online course?', 'sikshya') . '</h2>';

		echo '<div class="sikshya-blankstate-buttons">';

		echo '<a class="sikshya-blankstate-cta button-primary button" href="' . esc_url(admin_url('post-new.php?post_type=' . SIKSHYA_COURSES_CUSTOM_POST_TYPE)) . '">' . esc_html__('Create New Course', 'sikshya') . '</a>';

		echo '<a class="sikshya-blankstate-cta button" href="#" data-href="' . esc_url(admin_url('edit.php?post_type=' . SIKSHYA_COURSES_CUSTOM_POST_TYPE . '&page=course_importer')) . '">' . esc_html__('Start Import', 'sikshya') . '</a>';

		echo '</div>';

		echo '</div>';
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
		$show_columns['category'] = __('Category', 'sikshya');
		$show_columns['tag'] = __('Tags', 'sikshya');
		$show_columns['price'] = __('Price', 'sikshya');
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

	protected function render_category_column()
	{
		$terms = get_the_terms($this->object->ID, 'sik_course_category');
		if (!$terms) {
			echo '<span class="na">&ndash;</span>';
		} else {
			$termlist = array();
			foreach ($terms as $term) {
				$termlist[] = '<a href="' . esc_url(admin_url('edit.php?sik_course_category=' . $term->slug . '&post_type=' . SIKSHYA_COURSES_CUSTOM_POST_TYPE)) . ' ">' . esc_html($term->name) . '</a>';
			}

			echo apply_filters('sikshya_admin_course_term_list', implode(', ', $termlist), 'sik_course_category', $this->object->ID, $termlist, $terms); // WPCS: XSS ok.
		}
	}

	/**
	 * Render columm: product_tag.
	 */
	protected function render_tag_column()
	{
		$terms = get_the_terms($this->object->ID, 'sik_course_tag');
 		if (!$terms) {
			echo '<span class="na">&ndash;</span>';
		} else {
			$termlist = array();
			foreach ($terms as $term) {
				$termlist[] = '<a href="' . esc_url(admin_url('edit.php?sik_course_tag=' . $term->slug . '&post_type=' . SIKSHYA_COURSES_CUSTOM_POST_TYPE)) . ' ">' . esc_html($term->name) . '</a>';
			}

			echo apply_filters('sikshya_admin_course_term_list', implode(', ', $termlist), 'sik_course_tag', $this->object->ID, $termlist, $terms); // WPCS: XSS ok.
		}
	}


	protected function render_price_column()
	{
		$course_id = $this->object->ID;

		sikshya_get_course_price($course_id);

	}
}
