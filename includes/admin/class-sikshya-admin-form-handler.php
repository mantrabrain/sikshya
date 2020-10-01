<?php

class Sikshya_Admin_Form_Handler
{
	public function __construct()
	{
		add_action('admin_init', array($this, 'export'));


	}

	public function export()
	{
		if (sikshya()->helper->array_get('sikshya_action', $_POST) !== 'sikshya_export') {
			return;
		}
		sikshya()->helper->validate_nonce(true);

		$sikshya_custom_post_types_for_export = isset($_POST['sikshya_custom_post_types_for_export']) ? $_POST['sikshya_custom_post_types_for_export'] : array();

		sikshya()->exporter->export($sikshya_custom_post_types_for_export);
	}


}

new Sikshya_Admin_Form_Handler();
