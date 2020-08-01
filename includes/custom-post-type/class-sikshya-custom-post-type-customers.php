<?php
if (!class_exists('Sikshya_Custom_Post_Type_Customers')) {

	class Sikshya_Custom_Post_Type_Customers
	{

		public function register()
		{
			$labels = array(
				'name' => __('Customers', 'sikshya'),
				'singular_name' => __('Customer', 'sikshya'),
				'edit_item' => __('Edit Customers', 'sikshya'),
				'all_items' => __('Customers', 'sikshya'),
				'view_item' => __('View Customer', 'sikshya'),
				'search_items' => __('Search Customer', 'sikshya'),
				'not_found' => __('No Customers found', 'sikshya'),
				'not_found_in_trash' => __('No Customers found in the Trash', 'sikshya'),
				'parent_item_colon' => '',
				'menu_name' => __('Customers', 'sikshya'),
			);
			$args = array(
				'labels' => $labels,
				'public' => true,
				'supports' => array('title'),
				'has_archive' => false,
				'show_in_menu' => 'sikshya',
				'publicly_queryable' => false,
				'exclude_from_search' => true,
				'capabilities' => array(
					'create_posts' => 'do_not_allow', // false < WP 4.5, credit @Ewout
					'delete_posts' => 'do_not_allow', // false < WP 4.5, credit @Ewout
				),


			);
			register_post_type(SIKSHYA_CUSTOMERS_CUSTOM_POST_TYPE, $args);

		}

		public function init()
		{
			add_action('init', array($this, 'register'));
			add_filter('bulk_actions-' . 'edit-' . SIKSHYA_CUSTOMERS_CUSTOM_POST_TYPE, '__return_empty_array');
			add_filter('views_' . 'edit-' . SIKSHYA_CUSTOMERS_CUSTOM_POST_TYPE, '__return_empty_array');


		}

	}
}
