<?php
if (!function_exists('sikshya_tippy_tooltip')) {
	function sikshya_tippy_tooltip($content, $echo = true)
	{
		$tippy_content = '<span class="sikshya-tippy-tooltip dashicons dashicons-editor-help" data-tippy-content="' . esc_attr($content) . '"></span>';

		if ($echo) {
			echo $tippy_content;
		}
		return $tippy_content;
	}
}

if (!function_exists('sikshya_enqueue_js')) {
	function sikshya_enqueue_js($code)
	{
		global $sikshya_queued_js;

		if (empty($sikshya_queued_js)) {
			$sikshya_queued_js = '';
		}

		$sikshya_queued_js .= "\n" . $code . "\n";
	}

}

function sikshya_print_js()
{
	global $sikshya_queued_js;

	if (!empty($sikshya_queued_js)) {
		// Sanitize.
		$sikshya_queued_js = wp_check_invalid_utf8($sikshya_queued_js);
		$sikshya_queued_js = preg_replace('/&#(x)?0*(?(1)27|39);?/i', "'", $sikshya_queued_js);
		$sikshya_queued_js = str_replace("\r", '', $sikshya_queued_js);

		$js = "<!-- Sikshya JavaScript -->\n<script type=\"text/javascript\">\njQuery(function($) { $sikshya_queued_js });\n</script>\n";

		echo apply_filters('sikshya_queued_js', $js); //

		unset($sikshya_queued_js);
	}
}

