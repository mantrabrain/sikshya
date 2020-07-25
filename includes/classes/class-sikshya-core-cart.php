<?php

class Sikshya_Core_Cart
{

	public function get_cart_items()
	{
		$cart_items = sikshya()->session->get_all();


		if (isset($cart_items['cart_items'])) {
			return $cart_items['cart_items'];
		}
		return array();
	}


	/**
	 * add to cart.
	 *
	 * @param WP_Post $item .
	 * @return boolean
	 */
	public function add_to_cart($course_id, $quantity = 1)
	{
		if ('publish' == get_post_status($course_id)) {

			$course_model = new Sikshya_Model_Course($course_id, $quantity);

			$cart_items = sikshya()->session->get_all();

			if (isset($cart_items['cart_items'])) {
				if (isset($cart_items['cart_items'][$course_id])) {
					$cart_items['cart_items'][$course_id] = $course_model;
				} else {
					$cart_items['cart_items'][$course_id] = $course_model;

				}
			} else {

				$cart_items['cart_items'][$course_id] = $course_model;

			}
			$final_cart_items = $cart_items['cart_items'];

			sikshya()->session->set('cart_items', $final_cart_items);

			return true;
		}


		return false;

	}

	public function remove($item_id)
	{

		if (absint($item_id) < 1) {
			return;
		}
		$cart_items = sikshya()->session->get_all();

		if (isset($cart_items['cart_items'])) {

			if (isset($cart_items['cart_items'][$item_id])) {

				unset($cart_items['cart_items'][$item_id]);

				$final_cart_items = $cart_items['cart_items'];

				sikshya()->session->set('cart_items', $final_cart_items);

			}
		}
	}

	public function get_cart_page($permalink = false)
	{
		$page_id = absint(get_option('sikshya_cart_page', 0));


		if ($page_id < 1) {

			global $wpdb;

			$page_id = $wpdb->get_var('SELECT ID FROM ' . $wpdb->prefix . 'posts WHERE post_content LIKE "%[sikshya_cart]%" AND post_parent = 0');
		}

		$page_id = absint($page_id);

		$page_permalink = get_permalink($page_id);

		if ($permalink) {

			return $page_permalink;
		}

		return $page_id;


	}


}
