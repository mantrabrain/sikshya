<?php
$currency_symbol = sikshya_get_active_currency_symbol();
?>
<div class="sikshya-field-wrap">
	<div class="sikshya-field-label">
		<label for="">

		</label>
	</div>
	<div class="sikshya-field-content">
		<label for="sikshya_is_free_course">
			<input class="widefat" id="sikshya_is_free_course" name="sikshya_is_free_course" type="checkbox"
				   value="1" <?php echo checked($sikshya_is_free_course, 1); ?>
			>


			<?php echo esc_html__('Check if this is a free course', 'sikshya'); ?>
		</label>
	</div>

</div>
<div class="sikshya-field-wrap">
	<div class="sikshya-field-label">
		<label for="sikshya_course_regular_price">
			<?php
			/* translators: %s: Currency Symbol. */
			printf(_x('Regular Price ( %s ) ', 'regular-price-title', 'sikshya'), $currency_symbol);
			?>
		</label>
	</div>
	<div class="sikshya-field-content">
		<input class="widefat" id="sikshya_course_regular_price" name="sikshya_course_regular_price" type="number"
			   value="<?php echo esc_attr($sikshya_course_regular_price); ?>"
			   placeholder="<?php echo esc_attr__('Regular Price', 'sikshya') ?>">

	</div>

</div>
<div class="sikshya-field-wrap">
	<div class="sikshya-field-label">
		<label for="">

		</label>
	</div>
	<div class="sikshya-field-content">
		<label for="sikshya_has_discounted_price">

			<input class="widefat" id="sikshya_has_discounted_price" name="sikshya_has_discounted_price" type="checkbox"
				   value="1" <?php checked($sikshya_has_discounted_price, 1); ?>
			>

			<?php echo esc_html__('Check if this course has discount', 'sikshya'); ?>
		</label>
	</div>

</div>
<div class="sikshya-field-wrap">
	<div class="sikshya-field-label">
		<label
			for="sikshya_course_discounted_price">
			<?php
			/* translators: %s: Currency Symbol. */
			printf(_x('Discounted Price ( %s ) ', 'discounted-price-title', 'sikshya'), $currency_symbol);
			?>
		</label>
	</div>
	<div class="sikshya-field-content">
		<input class="widefat" id="sikshya_course_discounted_price" name="sikshya_course_discounted_price" type="number"
			   value="<?php echo esc_attr($sikshya_course_discounted_price); ?>"
			   placeholder="<?php echo esc_attr__('Discounted Price', 'sikshya') ?>">

	</div>

</div>
