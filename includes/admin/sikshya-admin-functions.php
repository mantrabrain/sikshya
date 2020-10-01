<?php
if (!function_exists('sikshya_get_current_screen_id')) {

	function sikshya_get_current_screen_id()
	{
		$screen = get_current_screen();

		$screen_id = $screen->id;

		return $screen_id;

	}
}

if (!function_exists('sikshya_load_admin_template')) {

	function sikshya_load_admin_template($template = null, $variables = array(), $include_once = false)
	{
		$variables = (array)$variables;

		$variables = apply_filters('sikshya_load_admin_template_variables', $variables);

		extract($variables);

		$isLoad = apply_filters('should_sikshya_load_admin_template', true, $template, $variables);
		if (!$isLoad) {
			return;
		}

		do_action('sikshya_load_admin_template_before', $template, $variables);

		if ($include_once) {

			include_once sikshya_get_admin_template($template);

		} else {

			include sikshya_get_admin_template($template);
		}
		do_action('sikshya_load_admin_template_after', $template, $variables);
	}
}

if (!function_exists('sikshya_get_admin_template')) {
	function sikshya_get_admin_template($template = null)
	{
		if (!$template) {
			return false;
		}
		$template = str_replace('.', DIRECTORY_SEPARATOR, $template);

		$template_location = trailingslashit(SIKSHYA_PATH) . "includes/admin/templates/{$template}.php";

		if (!file_exists($template_location)) {
			echo '<div class="sikshya-notice-warning"> ' . __(sprintf('The file you are trying to load is not exists in your theme or sikshya plugins location, if you are a developer and extending sikshya plugin, please create a php file at location %s ', "<code>{$template_location}</code>"), 'sikshya') . ' </div>';
		}


		return apply_filters('sikshya_get_admin_template_path', $template_location, $template);
	}
}
