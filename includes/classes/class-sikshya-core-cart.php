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
	public function add_to_cart($item)
	{
		if (isset($item->ID)) {

			if ('publish' == get_post_status($item->ID)) {

				$cart_items = sikshya()->session->get_all();

				if (isset($cart_items['cart_items'])) {
					if (isset($cart_items['cart_items'][$item->ID])) {
						$cart_items['cart_items'][$item->ID] = $item;
					} else {
						$cart_items['cart_items'][$item->ID] = $item;

					}
				} else {

					$cart_items['cart_items'][$item->ID] = $item;

				}
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


}
