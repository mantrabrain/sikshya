<?php

namespace Sikshya\Services;

use Sikshya\Database\Repositories\EnrollmentRepository;
use WP_Error;

/**
 * Business logic for enrollment CRUD used by REST controllers.
 */
final class EnrollmentCrudService
{
    private EnrollmentRepository $repo;

    public function __construct(?EnrollmentRepository $repo = null)
    {
        $this->repo = $repo ?: new EnrollmentRepository();
    }

    /**
     * @return array{rows:array<int,object>,total:int,pages:int,page:int,per_page:int}|WP_Error
     */
    public function listPaged(int $page, int $per_page)
    {
        if (!$this->repo->tableExists()) {
            return new WP_Error('sikshya_missing_table', __('Enrollments table not found.', 'sikshya'));
        }

        $per_page = max(1, min(200, $per_page));
        $page = max(1, $page);
        $offset = ($page - 1) * $per_page;

        $total = $this->repo->countAll();
        $pages = $total > 0 ? (int) ceil($total / $per_page) : 0;
        $rows = $this->repo->listPaged($per_page, $offset);

        return [
            'rows' => $rows,
            'total' => $total,
            'pages' => $pages,
            'page' => $page,
            'per_page' => $per_page,
        ];
    }

    /**
     * @return object|WP_Error
     */
    public function getById(int $id)
    {
        if ($id <= 0) {
            return new WP_Error('sikshya_invalid_id', __('Invalid enrollment id.', 'sikshya'));
        }
        if (!$this->repo->tableExists()) {
            return new WP_Error('sikshya_missing_table', __('Enrollments table not found.', 'sikshya'));
        }

        $row = $this->repo->findById($id);
        if (!$row) {
            return new WP_Error('sikshya_not_found', __('Enrollment not found.', 'sikshya'), ['status' => 404]);
        }

        return $row;
    }

    /**
     * @return int|WP_Error
     */
    public function create(array $data)
    {
        if (!$this->repo->tableExists()) {
            return new WP_Error('sikshya_missing_table', __('Enrollments table not found.', 'sikshya'));
        }

        $user_id = isset($data['user_id']) ? (int) $data['user_id'] : 0;
        $course_id = isset($data['course_id']) ? (int) $data['course_id'] : 0;
        if ($user_id <= 0 || $course_id <= 0) {
            return new WP_Error('sikshya_invalid_payload', __('user_id and course_id are required.', 'sikshya'));
        }

        $id = $this->repo->create([
            'user_id' => $user_id,
            'course_id' => $course_id,
            'status' => isset($data['status']) ? sanitize_text_field((string) $data['status']) : 'enrolled',
            'payment_method' => isset($data['payment_method']) ? sanitize_text_field((string) $data['payment_method']) : '',
            'amount' => isset($data['amount']) ? (float) $data['amount'] : 0.0,
        ]);

        return $id > 0 ? $id : new WP_Error('sikshya_create_failed', __('Failed to create enrollment.', 'sikshya'));
    }

    /**
     * @return bool|WP_Error
     */
    public function update(int $id, array $data)
    {
        if ($id <= 0) {
            return new WP_Error('sikshya_invalid_id', __('Invalid enrollment id.', 'sikshya'));
        }
        if (!$this->repo->tableExists()) {
            return new WP_Error('sikshya_missing_table', __('Enrollments table not found.', 'sikshya'));
        }

        $patch = [];
        if (array_key_exists('status', $data)) {
            $patch['status'] = sanitize_text_field((string) ($data['status'] ?? ''));
        }
        if (array_key_exists('payment_method', $data)) {
            $patch['payment_method'] = sanitize_text_field((string) ($data['payment_method'] ?? ''));
        }
        if (array_key_exists('amount', $data)) {
            $patch['amount'] = (float) ($data['amount'] ?? 0);
        }

        if ($patch === []) {
            return true;
        }

        return $this->repo->update($id, $patch);
    }

    /**
     * @return bool|WP_Error
     */
    public function delete(int $id)
    {
        if ($id <= 0) {
            return new WP_Error('sikshya_invalid_id', __('Invalid enrollment id.', 'sikshya'));
        }
        if (!$this->repo->tableExists()) {
            return new WP_Error('sikshya_missing_table', __('Enrollments table not found.', 'sikshya'));
        }

        return $this->repo->delete($id);
    }
}

