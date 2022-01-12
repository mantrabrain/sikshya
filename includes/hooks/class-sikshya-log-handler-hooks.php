<?php

class Sikshya_Log_Handler_Hooks
{

	public function __construct()
	{
		add_filter('sikshya_register_log_handlers', array($this, 'update_handler'));

	}

	public function update_handler($handlers)
	{
		$log_options = get_option('sikshya_log_options', 'db');

		if ($log_options === 'file') {

			$handler_class = 'Sikshya_Log_Handler_File';
		} else {
			$handler_class = 'Sikshya_Log_Handler_DB';
		}

		if (!class_exists($handler_class)) {

			$handler_class = Sikshya_Log_Handler_File::class;
		}

		array_push($handlers, new $handler_class());


		return $handlers;
	}
}

new Sikshya_Log_Handler_Hooks();
