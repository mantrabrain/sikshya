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
					<a href="https://demo.themeum.com/plugins/tutor/courses/java-beginner-programming-tutorials/"><img
							width="300" height="300"
							src="https://demo.themeum.com/plugins/tutor/wp-content/uploads/sikshya-placeholder.png"
							class="sikshya-placeholder wp-post-image" alt="Placeholder"
							srcset="https://demo.themeum.com/plugins/tutor/wp-content/uploads/sikshya-placeholder.png 1200w, https://demo.themeum.com/plugins/tutor/wp-content/uploads/sikshya-placeholder-150x150.png 150w, https://demo.themeum.com/plugins/tutor/wp-content/uploads/sikshya-placeholder-300x300.png 300w, https://demo.themeum.com/plugins/tutor/wp-content/uploads/sikshya-placeholder-768x768.png 768w, https://demo.themeum.com/plugins/tutor/wp-content/uploads/sikshya-placeholder-1024x1024.png 1024w"
							sizes="(max-width: 300px) 100vw, 300px"></a></td>

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
						<input type="number" id="quantity_5f1bfc52ca393" class="input-text qty text" step="1" min="0"
							   max=""
							   name="cart[903ce9225fca3e988c2af215d4e544d3][qty]" value="<?php echo esc_attr($sk_cart_item->quantity) ?>" title="Qty" size="4"
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
					<label for="coupon_code">Coupon:</label> <input type="text" name="coupon_code" class="input-text"
																	id="coupon_code" value="" placeholder="Coupon code">
					<input type="submit" class="button" name="apply_coupon" value="Apply coupon">
				</div>

				<input type="submit" class="button" name="update_cart" value="Update cart" disabled="">


				<input type="hidden" id="_wpnonce" name="_wpnonce" value="55ddc6f461"><input type="hidden"
																							 name="_wp_http_referer"
																							 value="/plugins/tutor/cart/">
			</td>
		</tr>

		</tbody>
	</table>
</form>
