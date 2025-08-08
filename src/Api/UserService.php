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
            return new WP_REST_Response(['error' => 'User not found'], 404);
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
            return new WP_REST_Response(['error' => 'Failed to delete user'], 400);
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
            'meta' => get_user_meta($user->ID),
        ];
    }
} 