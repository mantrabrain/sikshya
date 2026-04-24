<?php

namespace Sikshya\Api;

use Sikshya\Services\ProgressQueryService;
use WP_REST_Request;
use WP_REST_Response;

class ProgressService
{
    private ProgressQueryService $svc;

    public function __construct(?ProgressQueryService $svc = null)
    {
        $this->svc = $svc ?: new ProgressQueryService();
    }

    public function getProgress(WP_REST_Request $request): WP_REST_Response
    {
        $user_id = (int) $request->get_param('user_id');
        $course_id = (int) $request->get_param('course_id');
        $progress = $this->svc->list($user_id, $course_id);

        return new WP_REST_Response([
            'progress' => array_map([$this, 'formatProgress'], $progress),
        ]);
    }

    private function formatProgress($progress): array
    {
        return [
            'id' => $progress->id,
            'user_id' => $progress->user_id,
            'course_id' => $progress->course_id,
            'lesson_id' => $progress->lesson_id,
            'status' => $progress->status,
            'percentage' => $progress->percentage,
            'completed_date' => $progress->completed_date,
            'updated_at' => $progress->updated_at ?? null,
        ];
    }
}
