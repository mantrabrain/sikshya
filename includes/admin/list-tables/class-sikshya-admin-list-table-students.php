<?php

class Sikshya_Admin_List_Table_Students extends WP_List_Table
{
	private $table_name = '';

	function __construct()
	{
		global $wpdb;

		global $status, $page;

		parent::__construct(array(
			'singular' => 'student',
			'plural' => 'students',
		));
	}

	/**
	 * Define primary column.
	 *
	 * @return string
	 */
	public function get_primary_column()
	{
		return 'student_id';
	}

	function column_default($item, $column_name)
	{
		return isset($item->$column_name) ? $item->$column_name : '';
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
			$item->ID
		);
	}

	function get_columns()
	{
		$columns = array(
			'cb' => '<input type="checkbox" />', //Render a checkbox instead of text
			'ID' => __('ID', 'sikshya'),
			'username' => __('Username', 'sikshya'),
			'first_name' => __('First Name', 'sikshya'),
			'last_name' => __('Last Name', 'sikshya'),
			'email' => __('Email', 'sikshya'),
			'phone' => __('Phone', 'sikshya'),
			'country' => __('Country', 'sikshya'),
			'city' => __('City', 'sikshya'),
			'state' => __('State', 'sikshya'),
			'postcode' => __('Postcode', 'sikshya'),
		);
		return $columns;
	}


	protected function column_username($item)
	{
		echo $item->data->user_login;
	}

	protected function column_email($item)
	{
		echo $item->data->user_email;
	}

	protected function column_phone($item)
	{
		$user_id = $item->ID;
		echo get_user_meta($user_id, 'billing_phone', true);
	}

	protected function column_country($item)
	{
		$user_id = $item->ID;
		echo get_user_meta($user_id, 'billing_country', true);

	}

	protected function column_first_name($item)
	{
		$user_id = $item->ID;
		echo get_user_meta($user_id, 'billing_first_name', true);

	}

	protected function column_last_name($item)
	{
		$user_id = $item->ID;
		echo get_user_meta($user_id, 'billing_last_name', true);

	}

	protected function column_city($item)
	{
		$user_id = $item->ID;
		echo get_user_meta($user_id, 'billing_city', true);

	}

	protected function column_state($item)
	{
		$user_id = $item->ID;
		echo get_user_meta($user_id, 'billing_state', true);

	}

	protected function column_postcode($item)
	{
		$user_id = $item->ID;
		echo get_user_meta($user_id, 'billing_postcode', true);


	}

	function get_sortable_columns()
	{
		$sortable_columns = array(
			'ID' => array('ID', true),
			'email' => array('email', false),
		);
		return $sortable_columns;
	}


	function get_bulk_actions()
	{
		return array();

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


		$count_user_query = new WP_User_Query(array('role' => 'sikshya_student'));
// Get the total number of users for the current query. I use (int) only for sanitize.
		$total_items = (int)$count_user_query->get_total();
// prepare query params, as usual current page, order by and order direction
		$paged = isset($_REQUEST['paged']) ? max(0, intval($_REQUEST['paged'] - 1) * $per_page) : 0;
		$orderby = (isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], array_keys($this->get_sortable_columns()))) ? $_REQUEST['orderby'] : 'student_id';
		$order = (isset($_REQUEST['order']) && in_array($_REQUEST['order'], array('asc', 'desc'))) ? $_REQUEST['order'] : 'asc';

// [REQUIRED] define $items array
// notice that last argument is ARRAY_A, so we will retrieve array
		$user_query = new WP_User_Query(array(
				'role' => 'sikshya_student',
				'query_orderby' => $orderby,
				'order' => $order,
				'paged' => $per_page,
				'offset' => $paged,

			)
		);

		$this->items = $user_query->get_results();

// [REQUIRED] configure pagination
		$this->set_pagination_args(array(
			'total_items' => $total_items, // total items defined above
			'per_page' => $per_page, // per page constant defined at top of method
			'total_pages' => ceil($total_items / $per_page) // calculate pages count
		));
	}
}
