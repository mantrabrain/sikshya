<?php

declare(strict_types=1);

namespace Sikshya\Api\Learner;

use Sikshya\Api\PublicRestRoutes;
use Sikshya\Core\Plugin;
use Sikshya\Database\Repositories\EnrollmentRepository;
use Sikshya\Database\Repositories\ProgressRepository;
use Sikshya\Services\CertificateIssuanceService;
use Sikshya\Services\CourseCompletionEvaluator;
use Sikshya\Services\CourseService;
use Sikshya\Services\Settings;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared scaffolding for learner-facing REST controllers.
 *
 * Pulls out the bits every learner controller needs (auth gate, JSON error helper, course
 * service accessor, enrollment+progress repositories, completion sync) so the historical
 * god-class {@see \Sikshya\Api\LearnerRestRoutes} can be split into domain-bounded subclasses
 * one piece at a time without each subclass duplicating boilerplate.
 *
 * Pattern decision (2026-05-14): abstract base controller. See the project memory entry
 * `project-rest-split-decision` for the rationale and the alternatives considered.
 *
 * @package Sikshya\Api\Learner
 */
abstract class AbstractLearnerRestController
{
    protected Plugin $plugin;

    protected ProgressRepository $progress;

    protected EnrollmentRepository $enrollment;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->progress = new ProgressRepository();
        $this->enrollment = new EnrollmentRepository();
    }

    /**
     * Register this controller's routes against the REST API. Concrete subclasses implement.
     */
    abstract public function register(): void;

    /**
     * Permission callback shared by every learner route. Must remain `public` so WP REST can
     * invoke it via `[$this, 'requireLoginOrJwt']`.
     *
     * @return bool|\WP_Error
     */
    public function requireLoginOrJwt(WP_REST_Request $request)
    {
        $public = new PublicRestRoutes($this->plugin);

        return $public->requireLoginOrJwt($request);
    }

    /**
     * Standard learner-route error envelope: `{ok: false, code, message}`.
     *
     * Kept consistent with the existing {@see \Sikshya\Api\LearnerRestRoutes} shape so clients
     * (web app, mobile, third-party tools) don't see drift across extracted controllers.
     */
    protected function error(string $code, string $message, int $status): WP_REST_Response
    {
        return new WP_REST_Response(
            [
                'ok' => false,
                'code' => $code,
                'message' => $message,
            ],
            $status
        );
    }

    protected function getCourseService(): CourseService
    {
        $svc = $this->plugin->getService('course');
        if (!$svc instanceof CourseService) {
            throw new \RuntimeException('Course service unavailable');
        }

        return $svc;
    }

    /**
     * Recompute course completion percent + status after a learner activity (lesson complete,
     * quiz submit, assignment submit). Lives on the base because three different domain
     * controllers (lessons, quizzes, assignments) need it; duplicating it would be a recipe
     * for behavior drift.
     *
     * Fires `sikshya_course_completed` and `sikshya_certificate_issued` when the activity
     * transitions the enrollment to "completed".
     */
    protected function syncEnrollmentProgress(int $user_id, int $course_id): void
    {
        $row = $this->enrollment->findByUserAndCourse($user_id, $course_id);
        if (!$row) {
            return;
        }

        $criteria = (string) Settings::get('course_completion_criteria', 'all_lessons');
        $pct = CourseCompletionEvaluator::computeProgressPercent($user_id, $course_id, $this->progress);

        $patch = ['progress' => $pct];

        if ($criteria === 'manual') {
            $this->enrollment->update((int) $row->id, $patch);

            return;
        }

        $was_completed = (string) $row->status === 'completed';
        if (
            ! $was_completed
            && CourseCompletionEvaluator::shouldMarkEnrollmentCompleted($pct, $criteria)
        ) {
            $patch['status'] = 'completed';
            $patch['completed_date'] = current_time('mysql');
        }

        $this->enrollment->update((int) $row->id, $patch);

        $now_completed = ! $was_completed && isset($patch['status']) && (string) $patch['status'] === 'completed';
        if ($now_completed) {
            $issue = new CertificateIssuanceService();
            $issued_id = $issue->issueIfEnabled($user_id, $course_id);
            do_action('sikshya_course_completed', $user_id, $course_id);
            if ($issued_id) {
                do_action('sikshya_certificate_issued', $user_id, $course_id, $issued_id);
            }
        }
    }
}
