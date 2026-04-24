<?php

namespace Sikshya\Services;

use Sikshya\Core\Plugin;
use Sikshya\Database\Repositories\AnalyticsRepository;

/**
 * Analytics Service
 *
 * @package Sikshya\Services
 */
class AnalyticsService
{
    /**
     * Plugin instance
     *
     * @var Plugin
     */
    private Plugin $plugin;

    /**
     * Constructor
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Track event
     *
     * @param string $eventType
     * @param array $eventData
     * @param int|null $userId
     * @param int|null $courseId
     */
    public function trackEvent(string $eventType, array $eventData = [], ?int $userId = null, ?int $courseId = null): void
    {
        if (!Settings::getRaw('sikshya_enable_analytics', 'yes')) {
            return;
        }

        (new AnalyticsRepository())->insert([
            'event_type' => $eventType,
            'event_data' => (string) wp_json_encode($eventData),
            'user_id' => $userId,
            'course_id' => $courseId,
            'session_id' => (string) session_id(),
            'ip_address' => $this->getClientIp(),
            'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'created_at' => (string) current_time('mysql'),
        ]);
    }

    /**
     * Get client IP
     *
     * @return string
     */
    private function getClientIp(): string
    {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
