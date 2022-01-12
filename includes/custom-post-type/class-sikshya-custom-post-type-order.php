<?php
if (!class_exists('Sikshya_Custom_Post_Type_Order')) {

	class Sikshya_Custom_Post_Type_Order
	{

		public function register()
		{
			$labels = array(
				'name' => _x('Orders', 'post type general name', 'sikshya'),
				'singular_name' => _x('Order', 'post type singular name', 'sikshya'),
				'menu_name' => _x('Orders', 'admin menu', 'sikshya'),
				'name_admin_bar' => _x('Order', 'add new on admin bar', 'sikshya'),
				'add_new' => _x('Add New', 'orders', 'sikshya'),
				'add_new_item' => __('Add New Order', 'sikshya'),
				'new_item' => __('New Order', 'sikshya'),
				'edit_item' => __('Edit Order', 'sikshya'),
				'view_item' => __('View Order', 'sikshya'),
				'all_items' => __('Orders', 'sikshya'),
				'search_items' => __('Search Orders', 'sikshya'),
				'parent_item_colon' => __('Parent Orders:', 'sikshya'),
				'not_found' => __('No orders found.', 'sikshya'),
				'not_found_in_trash' => __('No orders found in Trash.', 'sikshya')
			);
			$args = array(
				'labels' => $labels,
				'description' => __('Description.', 'sikshya'),
				'public' => false,
				'publicly_queryable' => false,
				'show_ui' => true,
				'show_in_menu' => 'edit.php?post_type=sik_courses',
				'query_var' => true,
				'capability_type' => 'post',
				'hierarchical' => false,
				'menu_position' => 130,
				'show_in_rest' => true,
				'menu_icon' => 'dashicons-book-alt',
				'has_archive' => false,
				'capabilities' => array(
					'create_posts' => false,
					'delete_posts' => true,

				),
			);

			register_post_type(SIKSHYA_ORDERS_CUSTOM_POST_TYPE, $args);
			if (function_exists('remove_meta_box')) {
				remove_meta_box('commentstatusdiv', SIKSHYA_ORDERS_CUSTOM_POST_TYPE, 'normal'); //removes comments status
				remove_meta_box('commentsdiv', SIKSHYA_ORDERS_CUSTOM_POST_TYPE, 'normal'); //removes comments
			}

			do_action('sikshya_after_register_post_type');

		}


		public function register_post_status()
		{
			$sikshya_get_order_statuses = sikshya_get_order_statuses();
			foreach ($sikshya_get_order_statuses as $status_id => $status_title) {
				register_post_status($status_id, array(
					'label' => _x('Pending', 'post status label', 'sikshya'),
					'public' => true,
					'label_count' => _n_noop($status_title . ' <span class="count">(%s)</span>', $status_title . '<span class="count">(%s)</span>', 'sikshya'),
					'post_type' => array(SIKSHYA_ORDERS_CUSTOM_POST_TYPE), // Define one or more post types the status can be applied to.
					'show_in_metabox_dropdown' => true,
					'show_in_inline_dropdown' => true,
				));
			}

		}

		public function init()
		{


			$this->register_post_status();
			add_action('init', array($this, 'register'));


		}
	}


}
