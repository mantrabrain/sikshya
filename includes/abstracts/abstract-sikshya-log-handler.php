<?php
/**
 * Log handling functionality.
 *
 * @class Sikshya_Log_Handler
 * @package Sikshya\Abstracts
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

/**
 * Abstract Sikshya Log Handler Class
 *
 * @version        1.0.0
 * @package        Sikshya\Abstracts
 */
abstract class Sikshya_Log_Handler implements Sikshya_Interface_Log_Handler
{

	/**
	 * Formats a timestamp for use in log messages.
	 *
	 * @param int $timestamp Log timestamp.
	 * @return string Formatted time for use in log entry.
	 */
	protected static function format_time($timestamp)
	{
		return date('c', $timestamp);
	}

	/**
	 * Builds a log entry text from level, timestamp and message.
	 *
	 * @param int $timestamp Log timestamp.
	 * @param string $level emergency|alert|critical|error|warning|notice|info|debug.
	 * @param string $message Log message.
	 * @param array $context Additional information for log handlers.
	 *
	 * @return string Formatted log entry.
	 */
	protected static function format_entry($timestamp, $level, $message, $context)
	{
		$time_string = self::format_time($timestamp);
		$level_string = strtoupper($level);
		$entry = "{$time_string} {$level_string} {$message}";

		return apply_filters(
			'sikshya_format_log_entry',
			$entry,
			array(
				'timestamp' => $timestamp,
				'level' => $level,
				'message' => $message,
				'context' => $context,
			)
		);
	}
}
