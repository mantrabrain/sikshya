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

			<button type="submit" class="button alt" name="sikshya_checkout_place_order" id="place_order"
					value="Place order" data-value="Place order">Place order
			</button>
			
		</div>
	</div>

<?php } ?>
