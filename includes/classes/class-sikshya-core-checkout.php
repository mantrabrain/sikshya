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
			)
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
						?>
						<span class="sikshya-input-wrapper">
								<input type="text" class="input-text "
									   name="<?php echo esc_attr($id); ?>" id="<?php echo esc_attr($id); ?>"
									   placeholder="" value="" autocomplete="off"
								/>
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
