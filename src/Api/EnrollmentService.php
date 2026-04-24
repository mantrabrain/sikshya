<?php

namespace Sikshya\Api;

use Sikshya\Services\EnrollmentCrudService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class EnrollmentService
{
    private EnrollmentCrudService $svc;

    public function __construct(?EnrollmentCrudService $svc = null)
    {
        $this->svc = $svc ?: new EnrollmentCrudService();
    }

    public function getEnrollments(WP_REST_Request $request): WP_REST_Response
    {
        $per_page = (int) ($request->get_param('per_page') ?: 10);
        $page = (int) ($request->get_param('page') ?: 1);

        $result = $this->svc->listPaged($page, $per_page);
        if ($result instanceof WP_Error) {
            return new WP_REST_Response(
                [
                    'enrollments' => [],
                    'total' => 0,
                    'pages' => 0,
                    'message' => $result->get_error_message(),
                ],
                200
            );
        }

        return new WP_REST_Response([
            'enrollments' => array_map([$this, 'formatEnrollment'], $result['rows']),
            'total' => (int) $result['total'],
            'pages' => (int) $result['pages'],
            'page' => (int) $result['page'],
            'per_page' => (int) $result['per_page'],
        ]);
    }

    public function createEnrollment(WP_REST_Request $request): WP_REST_Response
    {
        $data = $request->get_json_params();
        $data = is_array($data) ? $data : [];

        $id = $this->svc->create($data);
        if ($id instanceof WP_Error) {
            return new WP_REST_Response(['error' => $id->get_error_message()], 400);
        }

        $req = new WP_REST_Request('GET');
        $req->set_param('id', $id);

        return $this->getEnrollment($req);
    }

    public function getEnrollment(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $row = $this->svc->getById($id);
        if ($row instanceof WP_Error) {
            $status = (int) (($row->get_error_data()['status'] ?? 400));
            $status = $status > 0 ? $status : 400;

            return new WP_REST_Response(['error' => $row->get_error_message()], $status);
        }

        return new WP_REST_Response($this->formatEnrollment($row));
    }

    public function updateEnrollment(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $data = $request->get_json_params();
        $data = is_array($data) ? $data : [];

        $ok = $this->svc->update($id, $data);
        if ($ok instanceof WP_Error) {
            return new WP_REST_Response(['error' => $ok->get_error_message()], 400);
        }

        $req = new WP_REST_Request('GET');
        $req->set_param('id', $id);

        return $this->getEnrollment($req);
    }

    public function deleteEnrollment(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $ok = $this->svc->delete($id);
        if ($ok instanceof WP_Error) {
            return new WP_REST_Response(['error' => $ok->get_error_message()], 400);
        }

        return new WP_REST_Response(['success' => (bool) $ok]);
    }

    private function formatEnrollment($enrollment): array
    {
        return [
            'id' => $enrollment->id,
            'user_id' => $enrollment->user_id,
            'course_id' => $enrollment->course_id,
            'status' => $enrollment->status,
            'enrolled_date' => $enrollment->enrolled_date,
            'payment_method' => $enrollment->payment_method,
            'amount' => $enrollment->amount,
        ];
    }
}
