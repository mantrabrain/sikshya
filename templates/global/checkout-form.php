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
							sikshya_get_cart_price_subtotal();
							?>
						</span></td>
			</tr>
			<tr class="order-total">
				<th>Total</th>
				<td>
					<strong><span class="sikshya-Price-amount amount">
							<?php
							sikshya_get_cart_price_total();
							?>
						</span>
					</strong>
				</td>
			</tr>


			</tfoot>
		</table>
	</div>
	<div id="payment" class="sikshya-checkout-payment">
		<ul class="wc_payment_methods payment_methods methods">
			<li class="wc_payment_method payment_method_bacs">
				<input id="payment_method_bacs" type="radio" class="input-radio" name="payment_method" value="bacs"
					   checked="checked" data-order_button_text="">

				<label for="payment_method_bacs">
					Direct bank transfer </label>
				<div class="payment_box payment_method_bacs">
					<p>Make your payment directly into our bank account. Please use your Order ID as the payment
						reference. Your order will not be shipped until the funds have cleared in our account.</p>
				</div>
			</li>
			<li class="wc_payment_method payment_method_cheque">
				<input id="payment_method_cheque" type="radio" class="input-radio" name="payment_method"
					   value="cheque" data-order_button_text="">

				<label for="payment_method_cheque">
					Check payments </label>
				<div class="payment_box payment_method_cheque">
					<p>Please send a check to Store Name, Store Street, Store Town, Store State / County, Store
						Postcode.</p>
				</div>
			</li>
			<li class="wc_payment_method payment_method_cod">
				<input id="payment_method_cod" type="radio" class="input-radio" name="payment_method" value="cod"
					   data-order_button_text="">

				<label for="payment_method_cod">
					Cash on delivery </label>
				<div class="payment_box payment_method_cod">
					<p>Pay with cash upon delivery.</p>
				</div>
			</li>
			<li class="wc_payment_method payment_method_paypal">
				<input id="payment_method_paypal" type="radio" class="input-radio" name="payment_method"
					   value="paypal" data-order_button_text="Proceed to PayPal">

				<label for="payment_method_paypal">
					PayPal <img src="https://www.paypalobjects.com/webstatic/mktg/Logo/AM_mc_vs_ms_ae_UK.png"
								alt="PayPal acceptance mark"><a
						href="https://www.paypal.com/gb/webapps/mpp/paypal-popup" class="about_paypal"
						onclick="javascript:window.open('https://www.paypal.com/gb/webapps/mpp/paypal-popup','WIPaypal','toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=1060, height=700'); return false;">What
						is PayPal?</a> </label>
				<div class="payment_box payment_method_paypal">
					<p>Pay via PayPal; you can pay with your credit card if you don’t have a PayPal account.</p>
				</div>
			</li>
		</ul>
		<div class="form-row place-order">
			<noscript>
				Since your browser does not support JavaScript, or it is disabled, please ensure you click the <em>Update
					Totals</em> button before placing your order. You may be charged more than the amount stated
				above if you fail to do so. <br/>
				<button type="submit" class="button alt" name="sikshya_checkout_update_totals"
						value="Update totals">Update totals
				</button>
			</noscript>

			<div class="sikshya-terms-and-conditions-wrapper">
				<div class="sikshya-privacy-policy-text"><p>Your personal data will be used to process your
						order, support your experience throughout this website, and for other purposes described in
						our <a href="https://demo.themeum.com/plugins/tutor/?page_id=3"
							   class="sikshya-privacy-policy-link" target="_blank">privacy policy</a>.</p>
				</div>
			</div>


			<button type="submit" class="button alt" name="sikshya_checkout_place_order" id="place_order"
					value="Place order" data-value="Place order">Place order
			</button>

			<input type="hidden" id="sikshya-process-checkout-nonce" name="sikshya-process-checkout-nonce"
				   value="656461d28c"><input type="hidden" name="_wp_http_referer"
											 value="/plugins/tutor/?wc-ajax=update_order_review"></div>
	</div>
</div>
