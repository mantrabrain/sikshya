<?php

namespace Sikshya\Frontend\Site;

/**
 * Resolves cart / enrollment flash messages from the request (query args + short-lived transients).
 *
 * @package Sikshya\Frontend\Site
 */
final class CartFlashResolver
{
    /**
     * @return array{type: string, message: string, show_view_cart?: bool}|null
     */
    public static function fromRequest(): ?array
    {
        if (isset($_GET['sikshya_cart_flash'])) {
            $token = sanitize_text_field(wp_unslash((string) $_GET['sikshya_cart_flash']));
            if ($token !== '') {
                $key = 'sikshya_cart_flash_' . $token;
                $data = get_transient($key);
                delete_transient($key);
                if (is_array($data) && !empty($data['message']) && is_string($data['message'])) {
                    $type = isset($data['type']) && is_string($data['type']) ? $data['type'] : 'info';
                    $out = [
                        'type' => $type,
                        'message' => $data['message'],
                    ];
                    if (!empty($data['show_view_cart'])) {
                        $out['show_view_cart'] = true;
                    }

                    return $out;
                }
            }
        }

        if (!isset($_GET['sikshya_cart'])) {
            return null;
        }

        $code = sanitize_key(wp_unslash((string) $_GET['sikshya_cart']));
        $map = [
            'added' => [
                'type' => 'success',
                'message' => __('Course added to your cart.', 'sikshya'),
                'show_view_cart' => true,
            ],
            'exists' => [
                'type' => 'info',
                'message' => __('This course is already in your cart.', 'sikshya'),
                'show_view_cart' => true,
            ],
            'removed' => ['type' => 'success', 'message' => __('Course removed from your cart.', 'sikshya')],
            'cleared' => ['type' => 'success', 'message' => __('Your cart was cleared.', 'sikshya')],
            'enrolled' => ['type' => 'success', 'message' => __('You are now enrolled in this course.', 'sikshya')],
            'enroll_failed' => ['type' => 'error', 'message' => __('Could not complete enrollment. Please try again.', 'sikshya')],
            'login_required' => ['type' => 'info', 'message' => __('Log in to enroll in this course.', 'sikshya')],
        ];

        /**
         * Extend fixed cart flash codes (e.g. Pro add-ons).
         *
         * @param array<string, array{type: string, message: string}> $map
         * @return array<string, array{type: string, message: string}>
         */
        $map = apply_filters('sikshya_cart_flash_code_map', $map);

        return $map[$code] ?? null;
    }
}
