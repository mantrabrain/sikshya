<?php

namespace Sikshya\Services;

/**
 * Profile updates for learner dashboard AJAX (core WordPress user APIs).
 *
 * @package Sikshya\Services
 */
final class FrontendUserService
{
    /**
     * @param array<string, mixed> $profile_data
     */
    public function updateProfile(int $user_id, array $profile_data): bool
    {
        if ($user_id <= 0) {
            return false;
        }

        $args = [
            'ID' => $user_id,
            'first_name' => sanitize_text_field((string) ($profile_data['first_name'] ?? '')),
            'last_name' => sanitize_text_field((string) ($profile_data['last_name'] ?? '')),
            'display_name' => sanitize_text_field((string) ($profile_data['display_name'] ?? '')),
            'description' => sanitize_textarea_field((string) ($profile_data['bio'] ?? '')),
            'user_url' => esc_url_raw((string) ($profile_data['website'] ?? '')),
        ];

        $result = wp_update_user($args);
        if (is_wp_error($result)) {
            return false;
        }

        update_user_meta($user_id, 'sikshya_user_phone', sanitize_text_field((string) ($profile_data['phone'] ?? '')));
        update_user_meta($user_id, 'sikshya_user_location', sanitize_text_field((string) ($profile_data['location'] ?? '')));

        $social = $profile_data['social_links'] ?? [];
        if (is_array($social)) {
            update_user_meta($user_id, 'sikshya_user_facebook', esc_url_raw((string) ($social['facebook'] ?? '')));
            update_user_meta($user_id, 'sikshya_user_twitter', esc_url_raw((string) ($social['twitter'] ?? '')));
            update_user_meta($user_id, 'sikshya_user_linkedin', esc_url_raw((string) ($social['linkedin'] ?? '')));
            update_user_meta($user_id, 'sikshya_user_instagram', esc_url_raw((string) ($social['instagram'] ?? '')));
        }

        return true;
    }

    /**
     * @param array<string, mixed> $file {@see $_FILES} single file entry.
     * @return array{avatar_url: string}|false
     */
    public function updateAvatar(int $user_id, array $file)
    {
        if ($user_id <= 0 || empty($file['tmp_name'])) {
            return false;
        }

        if (! current_user_can('upload_files')) {
            return false;
        }

        if (! function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $aid = media_handle_upload('avatar', 0);
        if (is_wp_error($aid)) {
            return false;
        }

        update_user_meta($user_id, 'sikshya_avatar_attachment_id', (int) $aid);

        $url = wp_get_attachment_image_url((int) $aid, 'thumbnail');

        return $url ? ['avatar_url' => $url] : false;
    }
}
