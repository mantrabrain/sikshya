<div class="sik-row sikshya-checkout-wrap">
	<div class="sik-row" id="sikshya_customer_details">
		<div class="sik-col-md-6 sik-col-sm-12">
			<div class="sikshya-billing-fields">

				<h3><?php echo __('Billing details', 'sikshya'); ?></h3>


				<div class="sikshya-billing-fields__field-wrapper">
					<?php

					sikshya()->checkout->get_billing_fields();
					?>
				</div>

			</div>


		</div>
		<div class="sik-col-md-6 sik-col-sm-12">
			<div class="sikshya-additional-fields">
				<h3><?php echo __('Additional information', 'sikshya'); ?></h3>
				<div class="sikshya-additional-fields__field-wrapper">
					<p class="form-row notes" id="order_comments_field" data-priority=""><label for="order_comments"
																								class="">
							Order
							notes&nbsp;<span
								class="optional">(optional)</span></label><span
							class="sikshya-input-wrapper"><textarea name="order_comments" class="input-text "
																	id="order_comments"
																	placeholder="Notes about your order, e.g. special notes for delivery."
																	rows="2" cols="5"
																	spellcheck="false"></textarea></span>
					</p></div>


			</div>
		</div>
	</div>
	<div class="sik-md-12">
		<h3 id="order_review_heading"><?php echo esc_html__('Your order', 'sikshya'); ?></h3>

		<table class="shop_table sikshya-checkout-review-order-table">
			<thead>
			<tr>
				<th class="product-name">Product</th>
				<th class="product-total">Total</th>
			</tr>
			</thead>
			<tbody>
			<?php
			$sikshya_cart_items = sikshya()->cart->get_cart_items();

			foreach ($sikshya_cart_items as $course_id => $sk_cart_item) {
				/**
				 * Sikshya_Core_Course instance.
				 *
				 * @var $sk_cart_item Sikshya_Model_Course
				 */
				?>
				<tr class="cart_item">
					<td class="product-name"><?php echo esc_html($sk_cart_item->title) ?>&nbsp;
						<strong class="product-quantity">× <?php echo esc_html($sk_cart_item->quantity) ?></strong>
						<small>(<?php
							sikshya_get_course_price($sk_cart_item->ID);
							?>)</small>
					</td>
					<td class="product-total">
					<span class="sikshya-Price-amount amount"><?php
						echo $sk_cart_item->total_price_string;
						?></span></td>
				</tr>
			<?php } ?>
			</tbody>
			<tfoot>

			<tr class="cart-subtotal">
				<th>Subtotal</th>
				<td><span class="sikshya-Price-amount amount">
							<?php
							echo sikshya_get_cart_price_subtotal();
							?>
						</span></td>
			</tr>
			<tr class="order-total">
				<th>Total</th>
				<td>
					<strong><span class="sikshya-Price-amount amount">
							<?php
							echo sikshya_get_cart_price_total();
							?>
						</span>
					</strong>
				</td>
			</tr>


			</tfoot>
		</table>
	</div>
	<?php
	sikshya_load_template('global.checkout-payment-gateways');
	?>
</div>
