<form class="sikshya-cart-form" action="<?php echo admin_url('admin-ajax.php'); ?>" method="post">

	<?php
	$cart_items = sikshya()->cart->get_cart_items();

	sikshya_load_template('global.cart-form', array('sikshya_cart_items' => $cart_items));
	?>

</form>
