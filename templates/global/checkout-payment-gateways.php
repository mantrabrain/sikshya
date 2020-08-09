<?php
$sikshya_payment_gateways = sikshya_get_payment_gateways();

if (count($sikshya_payment_gateways) > 0) {
	?>
	<div id="payment" class="sikshya-checkout-payment">
		<ul class="sikshya_payment_methods payment_methods methods">
			<?php

			$sikshya_get_active_payment_gateways = sikshya_get_active_payment_gateways();

			foreach ($sikshya_payment_gateways as $sikshya_payment_gateway) {
				$gateway_id = $sikshya_payment_gateway['id'];
				if (in_array($gateway_id, $sikshya_get_active_payment_gateways)) {


					?>
					<li class="sikshya_payment_method payment_method_<?php echo esc_attr($gateway_id); ?>">
						<input id="payment_method_<?php echo esc_attr($gateway_id); ?>" type="radio"
							   class="input-radio" name="sikshya_payment_gateway"
							   value="<?php echo esc_attr($gateway_id); ?>"
							   checked="checked" data-order_button_text="">

						<label for="payment_method_<?php echo esc_attr($gateway_id); ?>">
							<?php echo esc_html($sikshya_payment_gateway['frontend_title']); ?>
							<?php if ('' != $sikshya_payment_gateway['image_url']) { ?>
								<img src="<?php echo esc_attr($sikshya_payment_gateway['image_url']) ?>"
									 alt="<?php echo esc_attr($sikshya_payment_gateway['frontend_title']); ?>"/>
							<?php } ?>
							<?php if ('' != $sikshya_payment_gateway['help_text'] && '' != $sikshya_payment_gateway['help_url']) { ?>
								<a
									href="<?php echo esc_attr($sikshya_payment_gateway['help_url']) ?>"
									class="help_<?php echo esc_attr($gateway_id); ?>"
									onclick="javascript:window.open('<?php echo esc_attr($sikshya_payment_gateway['help_url']) ?>','Gateway Title','toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=1060, height=700'); return false;">
									<?php echo esc_attr($sikshya_payment_gateway['help_text']) ?>
								</a>
							<?php } ?>
						</label>
						<?php if ('' != $sikshya_payment_gateway['description']) { ?>
							<div class="payment_box payment_method_<?php echo esc_attr($gateway_id); ?>">
								<p>
									<?php
									echo esc_html($sikshya_payment_gateway['description']); ?>
								</p>
							</div>
						<?php } ?>
					</li>
				<?php }
			} ?>

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
											 value="/plugins/tutor/?sikshya-ajax=update_order_review"></div>
	</div>

<?php } ?>
