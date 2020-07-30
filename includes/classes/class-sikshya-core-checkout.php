<?php

class Sikshya_Core_Checkout
{
	public function billing_fields()
	{

		$fields = array(
			array(
				'id' => 'first_name',
				'label' => __('First Name', 'Sikshya'),
				'type' => 'text',
				'validation' => array(
					'required' => true
				),
				'class' => 'form-row-first'
			),
			array(
				'id' => 'last_name',
				'label' => __('Last Name', 'Sikshya'),
				'type' => 'text',
				'validation' => array(
					'required' => true
				),
				'class' => 'form-row-last'
			),
			array(
				'id' => 'company_name',
				'label' => __('Company Name (Optional)', 'Sikshya'),
				'type' => 'text',
				'validation' => array(
					'required' => false
				),
				'class' => 'form-row-first'
			),
			array(
				'id' => 'country',
				'label' => __('Country', 'Sikshya'),
				'type' => 'select',
				'validation' => array(
					'required' => true
				),
				'options' => sikshya_get_countries(),
				'class' => 'form-row-last'
			),
			array(
				'id' => 'street_address_1',
				'label' => __('Street Address', 'Sikshya'),
				'type' => 'text',
				'validation' => array(
					'required' => true
				),
				'attributes' => array(
					'placeholder' => __('House number and street name', 'sikshya')
				),
				'class' => 'form-row-first'
			),
			array(
				'id' => 'street_address_2',
				'label' => '',
				'type' => 'text',
				'attributes' => array(
					'placeholder' => __('Apartment, suite, unit etc. (optional)', 'sikshya')
				),
				'class' => 'form-row-last'
			),
			array(
				'id' => 'postcode',
				'label' => __('Postcode / ZIP', 'sikshya'),
				'type' => 'text',
				'validation' => array(
					'required' => true
				),
				'class' => 'form-row-first'
			),
			array(
				'id' => 'city',
				'label' => __('Town/City', 'Sikshya'),
				'type' => 'select',
				'validation' => array(
					'required' => true
				),
				'options' => array(),
				'class' => 'form-row-last'
			),
			array(
				'id' => 'phone',
				'label' => __('Phone', 'sikshya'),
				'type' => 'text',
				'validation' => array(
					'required' => true
				),
				'class' => 'form-row-first'
			),
			array(
				'id' => 'email',
				'label' => __('Email', 'sikshya'),
				'type' => 'email',
				'validation' => array(
					'required' => true
				),
				'class' => 'form-row-first'
			),
		);

		return apply_filters('sikshya_billing_fields', $fields);
	}

	public function get_billing_fields()
	{
		$fields = $this->billing_fields();

		foreach ($fields as $field) {
			$id = $field['id'];
			$class = isset($field['class']) ? $field['class'] : '';
			$label = isset($field['label']) ? $field['label'] : '';
			$validation = isset($field['validation']) ? $field['validation'] : array();
			$required = isset($validation['required']) ? (boolean)$validation['required'] : false;
			$type = isset($field['type']) ? $field['type'] : 'text';
			$attributes = isset($field['attributes']) ? $field['attributes'] : array();
			$attributes_string = '';
			foreach ($attributes as $att_key => $att_val) {
				$attributes_string .= ' ' . esc_attr($att_key) . '="' . esc_attr($att_val) . '"';
			}

			?>
			<p class="form-row form-row-first validate-required" id="<?php echo esc_attr($id) ?>">
				<label for="<?php echo esc_attr($id) ?>" class="<?php echo esc_attr($class); ?>">
					<?php
					echo esc_html($label);
					if ($required) {
						?>
						<abbr
							class="required" title="required">*</abbr>
					<?php } ?>
				</label>

				<?php
				switch ($type) {
					case "text":
					case "email":
						?>
						<span class="sikshya-input-wrapper">
								<input type="<?php echo esc_attr($type); ?>" class="input-text "
									   name="<?php echo esc_attr($id); ?>" id="<?php echo esc_attr($id); ?>"
									   autocomplete="off" <?php echo $attributes_string; ?>
								/>
						</span>
						<?php
						break;
					case "select":
						$options = isset($field['options']) ? $field['options'] : array();
						?>
						<span class="sikshya-input-wrapper">
								<select class="input-select "
										name="<?php echo esc_attr($id); ?>"
										id="<?php echo esc_attr($id); ?>" <?php echo $attributes_string; ?>>
									<?php foreach ($options as $option_key => $option) {
										echo '<option value="' . esc_attr($option_key) . '">';

										echo esc_html($option);

										echo '</option>';
									}
									?>
								</select>
						</span>
						<?php
						break;
				}
				?>

			</p>
			<?php
		}
	}

}
