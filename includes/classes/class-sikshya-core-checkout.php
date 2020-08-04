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
				'label' => __('Town/City', 'sikshya'),
				'type' => 'text',
				'validation' => array(
					'required' => true
				),
				'class' => 'form-row-last'
			),
			array(
				'id' => 'state',
				'label' => __('State/Zone', 'sikshya'),
				'type' => 'select',
				'validation' => array(
					'required' => true
				),
				'options' => array(
					'' => __('Select State', 'sikshya'),
					'state_1' => __('State 1', 'sikshya')
				),
				'class' => 'form-row-first'
			),
			array(
				'id' => 'phone',
				'label' => __('Phone', 'sikshya'),
				'type' => 'text',
				'validation' => array(
					'required' => true
				),
				'class' => 'form-row-last'
			),
			array(
				'id' => 'email',
				'label' => __('Email', 'sikshya'),
				'type' => 'email',
				'validation' => array(
					'required' => true
				),
				'class' => 'form-row-last'
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
									value="<?php echo esc_attr(sikshya()->helper->input($id)); ?>"
								/>
						</span>
						<?php
						break;
					case "select":
						$options = isset($field['options']) ? $field['options'] : array();
						$selected_option = sikshya()->helper->input($id);
						?>
						<span class="sikshya-input-wrapper">
								<select class="input-select "
										name="<?php echo esc_attr($id); ?>"
										id="<?php echo esc_attr($id); ?>" <?php echo $attributes_string; ?>
								>
									<?php foreach ($options as $option_key => $option) {
										echo '<option value="' . esc_attr($option_key) . '"';
										selected($selected_option, $option_key);
										echo '>';

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

	public function validate_billing_data($data)
	{
		$valid_data = array();

		$validation_status = true;

		$billing_fields = $this->billing_fields();

		foreach ($data as $data_key => $data_value) {

			foreach ($billing_fields as $field) {

				$id = isset($field['id']) ? $field['id'] : '';

				$type = isset($field['type']) ? $field['type'] : 'text';

				if ($data_key === $id) {

					$valid_single_data = $this->validate_single_field($field, $data_value);
					if ((boolean)$valid_single_data['status']) {
						$valid_data[$id] = $valid_single_data['data'];
					} else {
						$validation_status = false;
					}
				}
			}
		}
		return ['status' => $validation_status, 'data' => $valid_data];
	}

	private function validate_single_field($field, $data)
	{

		$type = isset($field['type']) ? $field['type'] : 'text';

		$label = isset($field['label']) ? $field['label'] : '';

		$validation = isset($field['validation']) ? $field['validation'] : array();

		$validation_pass = true;

		$valid_data = '';

		foreach ($validation as $validation_key => $validation_value) {

			switch ($validation_key) {

				case "required":

					if ((boolean)$validation_value) {

						if ('' == $data) {

							$validation_pass = false;

							sikshya()->errors->add(sikshya()->notice_key, sprintf(__('%s is required', 'sikshya'), $label));
						}
					}
					break;
			}

		}

		switch ($type) {
			case "text":
				$valid_data = sanitize_text_field($data);
				break;
			case "email":
				if (filter_var($data, FILTER_VALIDATE_EMAIL)) {
					$valid_data = sanitize_email($data);
				} else {
					$validation_pass = false;
					sikshya()->errors->add(sikshya()->notice_key, __('Invalid email field.', 'sikshya'));

				}
				break;
			case "select":
				$options = isset($field['options']) ? $field['options'] : array();
				if (in_array($data, $options)) {
					$valid_data = $data;
				}

				break;
		}
		return ['status' => $validation_pass, 'data' => $valid_data];

	}

}
