<?php

namespace Sikshya\Api;

use WP_REST_Request;
use WP_REST_Response;

class UserService
{
    public function getUsers(WP_REST_Request $request): WP_REST_Response
    {
        $args = [
            'role__in' => ['sikshya_student', 'sikshya_instructor', 'administrator'],
            'number' => $request->get_param('per_page') ?: 10,
            'paged' => $request->get_param('page') ?: 1,
        ];
        $user_query = new \WP_User_Query($args);
        $users = [];
        foreach ($user_query->get_results() as $user) {
            $users[] = $this->formatUser($user);
        }
        return new WP_REST_Response([
            'users' => $users,
            'total' => $user_query->get_total(),
        ]);
    }

    public function createUser(WP_REST_Request $request): WP_REST_Response
    {
        $data = $request->get_json_params();
        $user_id = wp_create_user(
            sanitize_user($data['username'] ?? ''),
            $data['password'] ?? '',
            sanitize_email($data['email'] ?? '')
        );
        if (is_wp_error($user_id)) {
            return new WP_REST_Response(['error' => $user_id->get_error_message()], 400);
        }
        if (!empty($data['role'])) {
            $user = get_userdata($user_id);
            $user->set_role($data['role']);
        }
        return $this->getUser(new WP_REST_Request('GET', '', ['id' => $user_id]));
    }

    public function getUser(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $user = get_userdata($id);
        if (!$user) {
            return new WP_REST_Response(['error' => __('User not found', 'sikshya')], 404);
        }
        return new WP_REST_Response($this->formatUser($user));
    }

    public function updateUser(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $data = $request->get_json_params();
        $userdata = [
            'ID' => $id,
            'user_email' => sanitize_email($data['email'] ?? ''),
            'display_name' => sanitize_text_field($data['display_name'] ?? ''),
            'first_name' => sanitize_text_field($data['first_name'] ?? ''),
            'last_name' => sanitize_text_field($data['last_name'] ?? ''),
        ];
        $result = wp_update_user($userdata);
        if (is_wp_error($result)) {
            return new WP_REST_Response(['error' => $result->get_error_message()], 400);
        }
        return $this->getUser(new WP_REST_Request('GET', '', ['id' => $id]));
    }

    public function deleteUser(WP_REST_Request $request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $result = wp_delete_user($id);
        if (!$result) {
            return new WP_REST_Response(['error' => __('Failed to delete user', 'sikshya')], 400);
        }
        return new WP_REST_Response(['success' => true]);
    }

    private function formatUser($user): array
    {
        return [
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'roles' => $user->roles,
            'registered' => $user->user_registered,
            'meta' => $this->formatSafeMeta((int) $user->ID),
        ];
    }

    /**
     * Return a filtered view of user meta that's safe to expose through the
     * users REST surface. The previous implementation returned the entire
     * `get_user_meta($id)` payload — which includes WordPress core's
     * `session_tokens` (serialised list of every active login session with
     * tokens, IPs, user agents), `_password_reset_token`, role/cap snapshots,
     * and whatever private meta any installed plugin (2FA, security, social
     * login) happens to use. The endpoint is gated to `manage_sikshya` OR
     * `manage_options`, but `manage_sikshya` is granted to non-administrator
     * LMS managers, who would otherwise be able to read administrator session
     * tokens and other plugins' private user data.
     *
     * Strategy: allowlist only Sikshya-prefixed keys + a small set of
     * harmless WordPress-public keys (bio, nickname). Customers needing extra
     * keys exposed (e.g. a custom CRM integration) can extend the allowlist
     * via the `sikshya_user_meta_safe_keys` /
     * `sikshya_user_meta_safe_prefixes` filters.
     */
    private function formatSafeMeta(int $user_id): array
    {
        $all = get_user_meta($user_id);
        if (!is_array($all)) {
            return [];
        }

        $allowed_prefixes = (array) apply_filters(
            'sikshya_user_meta_safe_prefixes',
            ['sikshya_']
        );
        $allowed_keys = (array) apply_filters(
            'sikshya_user_meta_safe_keys',
            ['description', 'nickname']
        );
        // Explicit denylist for well-known sensitive WP-core keys that don't
        // start with `_`. The underscore-prefix convention is also denied as
        // a belt-and-suspenders default below.
        $always_denied = ['session_tokens', 'wp_capabilities', 'wp_user_level'];

        $safe = [];
        foreach ($all as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            if (in_array($key, $always_denied, true)) {
                continue;
            }
            // `_xxx` is the WP convention for hidden/internal meta — never
            // expose unless the customer explicitly allowlists the key.
            if (strncmp($key, '_', 1) === 0 && !in_array($key, $allowed_keys, true)) {
                continue;
            }
            if (in_array($key, $allowed_keys, true)) {
                $safe[$key] = $value;
                continue;
            }
            foreach ($allowed_prefixes as $prefix) {
                if (!is_string($prefix) || $prefix === '') {
                    continue;
                }
                if (strncmp($key, $prefix, strlen($prefix)) === 0) {
                    $safe[$key] = $value;
                    break;
                }
            }
        }
        return $safe;
    }
}
