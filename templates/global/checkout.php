<?php
do_action('sikshya_before_checkout_form');
?>
<form class="sikshya-checkout-form" method="post"
	  enctype="multipart/form-data">
	<?php

	sikshya_load_template('global.checkout-form', array('sikshya_checkout_items' => array()));

	?>
	<input type="hidden" value="sikshya_place_order" name="sikshya_action"/>
	<input type="hidden" value="sikshya_checkout" name="sikshya_checkout_notice"/>
	<input type="hidden" value="<?php echo wp_create_nonce('wp_sikshya_place_order_nonce') ?>"
		   name="sikshya_nonce"/>
</form>
