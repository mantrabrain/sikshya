<form class="sikshya-cart-form" action="https://demo.themeum.com/plugins/tutor/cart/" method="post">

	<table class="shop_table shop_table_responsive cart sikshya-cart-form__contents" cellspacing="0">
		<thead>
		<tr>
			<th class="product-remove">&nbsp;</th>
			<th class="product-thumbnail">&nbsp;</th>
			<th class="product-name">Course</th>
			<th class="product-price">Price</th>
			<th class="product-quantity">Quantity</th>
			<th class="product-subtotal">Total</th>
		</tr>
		</thead>
		<tbody>

		<?php

		foreach ($sikshya_cart_items as $course_id => $sk_cart_item) {
			/**
			 * Sikshya_Core_Course instance.
			 *
			 * @var $sk_cart_item Sikshya_Model_Course
			 */

			?>
			<tr class="sikshya-cart-form__cart-item cart_item">

				<td class="product-remove">
					<a href="https://demo.themeum.com/plugins/tutor/cart/?remove_item=903ce9225fca3e988c2af215d4e544d3&amp;_wpnonce=55ddc6f461"
					   class="remove" aria-label="Remove this item" data-product_id="143" data-product_sku="">×</a></td>

				<td class="product-thumbnail">
					<?php

					?>
				</td>

				<td class="product-name">
					<a href="<?php echo esc_attr(get_permalink($sk_cart_item->ID)); ?>"><?php
						echo esc_html($sk_cart_item->title);
						?></a></td>

				<td class="product-price">
					<?php
					sikshya_get_course_price($sk_cart_item->ID);
					?>
				</td>

				<td class="product-quantity">
					<div class="quantity">
						<label class="screen-reader-text" for="quantity_5f1bfc52ca393">Java (Beginner) Programming
							Tutorials
							quantity</label>
						<input type="number" id="quantity_5f1bfc52ca393" class="course-qty" step="1" min="0"
							   max=""
							   name="cart[903ce9225fca3e988c2af215d4e544d3][qty]"
							   value="<?php echo esc_attr($sk_cart_item->quantity) ?>" title="Qty" size="4"
							   inputmode="numeric">
					</div>
				</td>

				<td class="product-subtotal" data-title="Total">
					<span
						class="sikshya-Price-amount amount"><?php echo esc_html($sk_cart_item->total_price_string) ?></span>
				</td>
			</tr>
		<?php } ?>

		<tr>
			<td colspan="6" class="actions">

				<div class="coupon">
					<input type="text" name="coupon_code" class="input-text"
						   id="coupon_code" value="" placeholder="Coupon code">
					<input type="submit" class="button" name="apply_coupon" value="Apply coupon">
				</div>

				<input type="submit" class="button sikshya-update-cart" name="update_cart" value="Update cart" disabled="">


				<input type="hidden" id="_wpnonce" name="_wpnonce" value="55ddc6f461"><input type="hidden"
																							 name="_wp_http_referer"
																							 value="/plugins/tutor/cart/">
			</td>
		</tr>

		</tbody>
	</table>
</form>
<div class="sikshya-cart-collaterals">
	<div class="cart_totals">

		<h2>Cart totals</h2>

		<table cellspacing="0" class="shop_table shop_table_responsive">

			<tbody>
			<tr class="cart-subtotal">
				<th>Subtotal</th>
				<td data-title="subtotal"><span class="sikshya-Price-amount amount">
						<?php sikshya_get_cart_price_subtotal(); ?>

					</span></td>
			</tr>


			<tr class="order-total">
				<th>Total</th>
				<td data-title="Total"><strong><span class="sikshya-Price-amount amount">
													<?php sikshya_get_cart_price_total(); ?>

						</span></strong></td>
			</tr>


			</tbody>
		</table>

		<div class="sikshya-proceed-to-checkout">

			<a href="https://demo.themeum.com/plugins/tutor/checkout/"
			   class="checkout-button button alt sikshya-forward">
				Proceed to checkout</a>
		</div>


	</div>
</div>
