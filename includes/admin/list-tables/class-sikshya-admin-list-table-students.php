<?php

class Sikshya_Admin_List_Table_Students extends WP_List_Table
{
	private $table_name = '';

	function __construct()
	{
		global $wpdb;

		$this->table_name = $wpdb->prefix . 'sikshya_students';

		global $status, $page;

		parent::__construct(array(
			'singular' => 'student',
			'plural' => 'students',
		));
	}

	function column_default($item, $column_name)
	{
		return $item[$column_name];
	}

	function column_age($item)
	{
		return '<em>' . $item['age'] . '</em>';
	}


	function column_name($item)
	{
// links going to /admin.php?page=[your_plugin_page][&other_params]
// notice how we used $_REQUEST['page'], so action will be done on curren page
// also notice how we use $this->_args['singular'] so in this example it will
// be something like &student=2
		$actions = array(
			'edit' => sprintf('<a href="?page=students_form&id=%s">%s</a>', $item['id'], __('Edit', 'sikshya')),
			'delete' => sprintf('<a href="?page=%s&action=delete&id=%s">%s</a>', $_REQUEST['page'], $item['id'], __('Delete', 'sikshya')),
		);

		return sprintf('%s %s',
			$item['name'],
			$this->row_actions($actions)
		);
	}


	function column_cb($item)
	{
		return sprintf(
			'<input type="checkbox" name="id[]" value="%s" />',
			$item['id']
		);
	}

	function get_columns()
	{
		$columns = array(
			'cb' => '<input type="checkbox" />', //Render a checkbox instead of text
			'student_id' => __('ID', 'sikshya'),
			'username' => __('Username', 'sikshya'),
			'first_name' => __('First Name', 'sikshya'),
			'last_name' => __('Last Name', 'sikshya'),
			'email' => __('Email', 'sikshya'),
			'country' => __('Country', 'sikshya'),
			'city' => __('City', 'sikshya'),
			'state' => __('State', 'sikshya'),
			'postcode' => __('Postcode', 'sikshya'),
		);
		return $columns;
	}


	function get_sortable_columns()
	{
		$sortable_columns = array(
			'student_id' => array('student_id', true),
			'email' => array('email', false),
			'user_id' => array('user_id', false),
		);
		return $sortable_columns;
	}


	function get_bulk_actions()
	{
		return array();
		$actions = array(
			'delete' => 'Delete'
		);
		return $actions;
	}


	function process_bulk_action()
	{

		global $wpdb;

		return;

		if ('delete' === $this->current_action()) {
			$ids = isset($_REQUEST['id']) ? $_REQUEST['id'] : array();
			if (is_array($ids)) $ids = implode(',', $ids);

			if (!empty($ids)) {
				$wpdb->query("DELETE FROM {$this->table_name} WHERE id IN($ids)");
			}
		}
	}

	/**
	 * [REQUIRED] This is the most important method
	 *
	 * It will get rows from database and prepare them to be showed in table
	 */
	function prepare_items()
	{
		global $wpdb;

		$per_page = 5; // constant, how much records will be shown per page

		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();

// here we configure table headers, defined in our methods
		$this->_column_headers = array($columns, $hidden, $sortable);

// [OPTIONAL] process bulk action if any
		$this->process_bulk_action();

// will be used in pagination settings
		$total_items = $wpdb->get_var("SELECT COUNT(student_id) FROM {$this->table_name}");

// prepare query params, as usual current page, order by and order direction
		$paged = isset($_REQUEST['paged']) ? max(0, intval($_REQUEST['paged'] - 1) * $per_page) : 0;
		$orderby = (isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], array_keys($this->get_sortable_columns()))) ? $_REQUEST['orderby'] : 'student_id';
		$order = (isset($_REQUEST['order']) && in_array($_REQUEST['order'], array('asc', 'desc'))) ? $_REQUEST['order'] : 'asc';

// [REQUIRED] define $items array
// notice that last argument is ARRAY_A, so we will retrieve array
		$this->items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table_name} ORDER BY $orderby $order LIMIT %d OFFSET %d", $per_page, $paged), ARRAY_A);
// [REQUIRED] configure pagination
		$this->set_pagination_args(array(
			'total_items' => $total_items, // total items defined above
			'per_page' => $per_page, // per page constant defined at top of method
			'total_pages' => ceil($total_items / $per_page) // calculate pages count
		));
	}
}
