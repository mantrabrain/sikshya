<?php

namespace Sikshya\Services;

use Sikshya\Database\Repositories\InstructorApplicationsRepository;
use Sikshya\Frontend\Public\InstructorContext;

/**
 * Approve / reject instructor applications stored in user meta.
 *
 * @package Sikshya\Services
 */
final class InstructorApplicationsService
{
    public function __construct(
        private InstructorApplicationsRepository $repo = new InstructorApplicationsRepository()
    ) {
    }

    /**
     * @return array{ok: bool, rows: list<array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function listForRest(int $page, int $per_page, string $status, string $search): array
    {
        $page = max(1, $page);
        $per_page = max(1, min(100, $per_page));
        $result = $this->repo->listPaged($page, $per_page, $status, $search);
        $total = (int) ($result['total'] ?? 0);
        $pages = $per_page > 0 ? (int) ceil($total / $per_page) : 0;
        $rows = [];
        foreach ((array) ($result['rows'] ?? []) as $r) {
            if (!is_array($r)) {
                continue;
            }
            $uid = (int) ($r['user_id'] ?? 0);
            $app_raw = isset($r['application_json']) ? (string) $r['application_json'] : '';
            $headline = '';
            if ($app_raw !== '') {
                $decoded = json_decode($app_raw, true);
                if (is_array($decoded)) {
                    $headline = (string) ($decoded['headline'] ?? '');
                }
            }
            $rows[] = [
                'user_id' => $uid,
                'email' => (string) ($r['user_email'] ?? ''),
                'display_name' => (string) ($r['display_name'] ?? ''),
                'registered' => (string) ($r['user_registered'] ?? ''),
                'status' => (string) ($r['instructor_status'] ?? ''),
                'applied_at' => (string) ($r['applied_at'] ?? ''),
                'headline' => $headline,
            ];
        }

        return [
            'ok' => true,
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'pages' => $pages,
        ];
    }

    public function approve(int $user_id): bool|\WP_Error
    {
        if ($user_id <= 0) {
            return new \WP_Error('invalid_user', __('Invalid user.', 'sikshya'), ['status' => 400]);
        }
        $u = get_userdata($user_id);
        if (!$u) {
            return new \WP_Error('invalid_user', __('User not found.', 'sikshya'), ['status' => 404]);
        }

        update_user_meta($user_id, '_sikshya_instructor_status', 'active');
        $u->add_role('sikshya_instructor');
        InstructorContext::flush($user_id);

        return true;
    }

    public function reject(int $user_id): bool|\WP_Error
    {
        if ($user_id <= 0) {
            return new \WP_Error('invalid_user', __('Invalid user.', 'sikshya'), ['status' => 400]);
        }
        $u = get_userdata($user_id);
        if (!$u) {
            return new \WP_Error('invalid_user', __('User not found.', 'sikshya'), ['status' => 404]);
        }

        update_user_meta($user_id, '_sikshya_instructor_status', 'rejected');
        if (in_array('sikshya_instructor', (array) $u->roles, true)) {
            $u->remove_role('sikshya_instructor');
        }
        InstructorContext::flush($user_id);

        return true;
    }
}
