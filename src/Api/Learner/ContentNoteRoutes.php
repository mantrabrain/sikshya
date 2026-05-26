<?php

declare(strict_types=1);

namespace Sikshya\Api\Learner;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Learner content-note routes (per-lesson / per-quiz personal notes).
 *
 * Extracted from {@see \Sikshya\Api\LearnerRestRoutes} during the 2026-05 hardening sprint as
 * the first domain-bounded subclass of {@see AbstractLearnerRestController}. Owns the
 * `/sikshya/v1/me/content-note` route (GET/POST/PUT/DELETE) plus all the storage helpers it
 * needs. Route paths and response shapes are preserved 1:1 with the original god-class —
 * external API consumers see no change.
 *
 * Storage model: per-user meta `_sikshya_learn_notes` is a 2D map keyed by `[course_id][content_id]`
 * holding an ordered list of `{id, text, created_at}` rows. Legacy installs may still hold a
 * single string in the cell; `learnNotesMigrateCellBucket()` upgrades on read.
 *
 * @package Sikshya\Api\Learner
 */
final class ContentNoteRoutes extends AbstractLearnerRestController
{
    private const LEARN_NOTES_META = '_sikshya_learn_notes';

    private const LEARN_NOTE_MAX_CHARS = 10000;

    /** Soft cap keeps user_meta payloads reasonable while allowing many short notes. */
    private const LEARN_NOTES_MAX_PER_CONTENT = 600;

    public function register(): void
    {
        $namespace = 'sikshya/v1';

        $content_note_course_args = [
            'course_id' => [
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ],
            'content_id' => [
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ],
        ];

        register_rest_route($namespace, '/me/content-note', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getMyContentNote'],
                'permission_callback' => [$this, 'requireLoginOrJwt'],
                'args' => $content_note_course_args,
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'saveMyContentNote'],
                'permission_callback' => [$this, 'requireLoginOrJwt'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateMyContentNote'],
                'permission_callback' => [$this, 'requireLoginOrJwt'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'deleteMyContentNote'],
                'permission_callback' => [$this, 'requireLoginOrJwt'],
                'args' => array_merge($content_note_course_args, [
                    'note_id' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ]),
            ],
        ]);
    }

    public function getMyContentNote(WP_REST_Request $request): WP_REST_Response
    {
        $gate = $this->gateLearnNotesAccess($request);
        if ($gate instanceof WP_REST_Response) {
            return $gate;
        }
        [$uid, $course_id, $content_id] = $gate;

        $items = $this->learnNotesNormalizeCell(
            get_user_meta($uid, self::LEARN_NOTES_META, true),
            (string) absint($course_id),
            (string) absint($content_id)
        );
        usort($items, [$this, 'learnNotesCompareByCreated']);

        $payloadNotes = [];
        foreach ($items as $row) {
            $payloadNotes[] = $this->learnNotesExposeRow($row);
        }

        $legacyNote = $this->learnNotesFlattenForLegacyUi($payloadNotes);

        return new WP_REST_Response(
            [
                'ok' => true,
                'data' => [
                    /** Back-compat: newline-joined plaintext for callers that predated structured notes */
                    'note' => $legacyNote,
                    'notes' => $payloadNotes,
                ],
            ],
            200
        );
    }

    /**
     * POST: Accepts `{ text }` (append note) OR legacy `{ note }` (replace this content's notes with one entry).
     */
    public function saveMyContentNote(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }
        if (!is_array($params)) {
            $params = [];
        }

        $course_id = isset($params['course_id']) ? (int) $params['course_id'] : 0;
        $content_id = isset($params['content_id']) ? (int) $params['content_id'] : 0;
        $uid = get_current_user_id();

        $appendTextRaw = isset($params['text']) ? (string) $params['text'] : '';

        $courseService = $this->getCourseService();
        if ($uid <= 0 || $course_id <= 0 || $content_id <= 0) {
            return $this->error('invalid_request', __('Invalid request.', 'sikshya'), 400);
        }
        if (!$courseService->isUserEnrolled($uid, $course_id)) {
            return $this->error('not_enrolled', __('You are not enrolled in this course.', 'sikshya'), 403);
        }

        $notes = $this->learnNotesBootstrapStore(get_user_meta($uid, self::LEARN_NOTES_META, true));
        $cKey = (string) absint($course_id);
        $pKey = (string) absint($content_id);

        if ($appendTextRaw !== '') {
            $clean = $this->learnNotesSanitizeText($appendTextRaw);
            if ($clean === '') {
                return $this->error('invalid_request', __('Note cannot be empty.', 'sikshya'), 400);
            }

            $items = $this->learnNotesNormalizeCellRaw($notes, $cKey, $pKey);
            if (count($items) >= self::LEARN_NOTES_MAX_PER_CONTENT) {
                return $this->error(
                    'notes_limit_reached',
                    sprintf(
                        /* translators: %d: maximum notes per lesson/quiz allowed */
                        __('You reached the maximum of %d notes per item.', 'sikshya'),
                        self::LEARN_NOTES_MAX_PER_CONTENT
                    ),
                    400
                );
            }
            $items[] = [
                'id' => $this->learnNotesNewId(),
                'text' => $clean,
                'created_at' => current_time('mysql', true),
            ];
            usort($items, [$this, 'learnNotesCompareByCreated']);
            $notes = $this->learnNotesPersistCell($notes, $cKey, $pKey, $items);
            update_user_meta($uid, self::LEARN_NOTES_META, $notes);

            return new WP_REST_Response(
                ['ok' => true, 'message' => __('Note added.', 'sikshya')],
                200
            );
        }

        $note = isset($params['note']) ? (string) $params['note'] : '';
        $note = sanitize_textarea_field($note);
        if (strlen($note) > self::LEARN_NOTE_MAX_CHARS) {
            $note = substr($note, 0, self::LEARN_NOTE_MAX_CHARS);
        }

        if ($note === '') {
            unset($notes[$cKey][$pKey]);
        } else {
            $legacyRow = [[
                'id' => 'legacy-single',
                'text' => $note,
                'created_at' => current_time('mysql', true),
            ]];
            $notes = $this->learnNotesPersistCell($notes, $cKey, $pKey, $legacyRow);
        }

        if (isset($notes[$cKey]) && is_array($notes[$cKey]) && $notes[$cKey] === []) {
            unset($notes[$cKey]);
        }
        update_user_meta($uid, self::LEARN_NOTES_META, $notes);

        return new WP_REST_Response(['ok' => true, 'message' => __('Notes updated.', 'sikshya')], 200);
    }

    public function updateMyContentNote(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }
        if (!is_array($params)) {
            $params = [];
        }

        $uid = get_current_user_id();
        $course_id = isset($params['course_id']) ? (int) $params['course_id'] : 0;
        $content_id = isset($params['content_id']) ? (int) $params['content_id'] : 0;
        $note_id = isset($params['note_id']) ? sanitize_text_field((string) $params['note_id']) : '';
        $textRaw = isset($params['text']) ? (string) $params['text'] : '';

        if (
            $uid <= 0
            || $course_id <= 0
            || $content_id <= 0
            || $note_id === ''
            || ! $this->learnNotesValidateIdToken($note_id)
        ) {
            return $this->error('invalid_request', __('Invalid request.', 'sikshya'), 400);
        }

        $courseService = $this->getCourseService();
        if (! $courseService->isUserEnrolled($uid, $course_id)) {
            return $this->error('not_enrolled', __('You are not enrolled in this course.', 'sikshya'), 403);
        }

        $clean = $this->learnNotesSanitizeText($textRaw);
        if ($clean === '') {
            return $this->error('invalid_request', __('Note cannot be empty.', 'sikshya'), 400);
        }

        $notes = $this->learnNotesBootstrapStore(get_user_meta($uid, self::LEARN_NOTES_META, true));
        $cKey = (string) absint($course_id);
        $pKey = (string) absint($content_id);
        $items = $this->learnNotesNormalizeCellRaw($notes, $cKey, $pKey);
        $hit = false;
        foreach ($items as $i => $row) {
            if ((string) ($row['id'] ?? '') !== $note_id) {
                continue;
            }
            $items[(int) $i]['text'] = $clean;
            $hit = true;
            break;
        }
        if (! $hit) {
            return $this->error('not_found', __('Note not found.', 'sikshya'), 404);
        }

        $notes = $this->learnNotesPersistCell($notes, $cKey, $pKey, $items);
        update_user_meta($uid, self::LEARN_NOTES_META, $notes);

        return new WP_REST_Response(['ok' => true, 'message' => __('Note saved.', 'sikshya')], 200);
    }

    public function deleteMyContentNote(WP_REST_Request $request): WP_REST_Response
    {
        $uid = get_current_user_id();
        $course_id = (int) $request->get_param('course_id');
        $content_id = (int) $request->get_param('content_id');
        $note_id = sanitize_text_field((string) $request->get_param('note_id'));

        if (
            $uid <= 0
            || $course_id <= 0
            || $content_id <= 0
            || $note_id === ''
            || ! $this->learnNotesValidateIdToken($note_id)
        ) {
            return $this->error('invalid_request', __('Invalid request.', 'sikshya'), 400);
        }

        $courseService = $this->getCourseService();
        if (! $courseService->isUserEnrolled($uid, $course_id)) {
            return $this->error('not_enrolled', __('You are not enrolled in this course.', 'sikshya'), 403);
        }

        $notes = $this->learnNotesBootstrapStore(get_user_meta($uid, self::LEARN_NOTES_META, true));
        $cKey = (string) absint($course_id);
        $pKey = (string) absint($content_id);
        $items = $this->learnNotesNormalizeCellRaw($notes, $cKey, $pKey);
        $filtered = [];
        foreach ($items as $row) {
            if ((string) ($row['id'] ?? '') !== $note_id) {
                $filtered[] = $row;
            }
        }
        if (count($filtered) === count($items)) {
            return $this->error('not_found', __('Note not found.', 'sikshya'), 404);
        }

        $notes = $this->learnNotesPersistCell($notes, $cKey, $pKey, $filtered);
        if ($filtered === []) {
            unset($notes[$cKey][$pKey]);
        }
        if (isset($notes[$cKey]) && $notes[$cKey] === []) {
            unset($notes[$cKey]);
        }

        update_user_meta($uid, self::LEARN_NOTES_META, $notes);

        return new WP_REST_Response(['ok' => true, 'message' => __('Note removed.', 'sikshya')], 200);
    }

    /**
     * Shared GET-style guard for enrolment + identifiers.
     *
     * @return array{int,int,int}|\WP_REST_Response
     */
    private function gateLearnNotesAccess(WP_REST_Request $request)
    {
        $uid = get_current_user_id();
        $course_id = (int) $request->get_param('course_id');
        $content_id = (int) $request->get_param('content_id');
        if ($uid <= 0 || $course_id <= 0 || $content_id <= 0) {
            return $this->error('invalid_request', __('Invalid request.', 'sikshya'), 400);
        }

        $courseService = $this->getCourseService();
        if (! $courseService->isUserEnrolled($uid, $course_id)) {
            return $this->error('not_enrolled', __('You are not enrolled in this course.', 'sikshya'), 403);
        }

        return [$uid, $course_id, $content_id];
    }

    /** @param mixed $raw */
    private function learnNotesBootstrapStore($raw): array
    {
        return is_array($raw) ? $raw : [];
    }

    /** @param mixed $metaNotes */
    private function learnNotesNormalizeCell($metaNotes, string $cKey, string $pKey): array
    {
        return $this->learnNotesMigrateCellBucket(
            $this->learnNotesBootstrapStore($metaNotes)[$cKey][$pKey] ?? null
        );
    }

    /**
     * @return array<int,array{id:string,text:string,created_at:string}>
     */
    private function learnNotesNormalizeCellRaw(array $notes, string $cKey, string $pKey): array
    {
        return $this->learnNotesMigrateCellBucket($notes[$cKey][$pKey] ?? null);
    }

    /**
     * @param mixed $cell
     * @return array<int,array{id:string,text:string,created_at:string}>
     */
    private function learnNotesMigrateCellBucket($cell): array
    {
        if ($cell === null || $cell === '') {
            return [];
        }

        if (is_string($cell)) {
            $clean = sanitize_textarea_field($cell);

            return $clean === '' ? [] : [[
                'id' => 'legacy-' . md5($clean . '|strlen:' . strlen($clean)),
                'text' => $clean,
                'created_at' => current_time('mysql', true),
            ]];
        }

        if (! is_array($cell)) {
            return [];
        }

        $out = [];
        foreach ($cell as $row) {
            if (! is_array($row)) {
                continue;
            }
            $tid = sanitize_text_field((string) ($row['id'] ?? ''));
            $textRaw = isset($row['text']) ? (string) $row['text'] : '';
            $textClean = $this->learnNotesSanitizeText($textRaw);
            if ($textClean === '') {
                continue;
            }
            if ($tid === '' || ! $this->learnNotesValidateIdToken($tid)) {
                $tid = $this->learnNotesNewId();
            }
            $createdRaw = isset($row['created_at']) ? sanitize_text_field((string) $row['created_at']) : '';
            if ($createdRaw !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $createdRaw)) {
                $createdRaw = current_time('mysql', true);
            }
            $out[] = [
                'id' => $tid,
                'text' => $textClean,
                'created_at' => $createdRaw !== '' ? $createdRaw : current_time('mysql', true),
            ];
        }

        return $out;
    }

    private function learnNotesSanitizeText(string $note): string
    {
        $note = sanitize_textarea_field($note);
        if (strlen($note) > self::LEARN_NOTE_MAX_CHARS) {
            $note = substr($note, 0, self::LEARN_NOTE_MAX_CHARS);
        }

        return trim($note);
    }

    private function learnNotesNewId(): string
    {
        if (function_exists('wp_generate_uuid4')) {
            return (string) wp_generate_uuid4();
        }

        return 'n-' . strtolower(bin2hex(random_bytes(14)));
    }

    /**
     * @param array{id:string,text:string,created_at:string} $row
     * @return array{id:string,text:string,created_at:string}
     */
    private function learnNotesPersistRowEncode(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'text' => (string) $row['text'],
            'created_at' => (string) $row['created_at'],
        ];
    }

    /** @param array<int,array{id:string,text:string,created_at:string}> $items */
    private function learnNotesPersistCell(array $notesRoot, string $cKey, string $pKey, array $items): array
    {
        if ($items === []) {
            unset($notesRoot[$cKey][$pKey]);
        } else {
            if (! isset($notesRoot[$cKey]) || ! is_array($notesRoot[$cKey])) {
                $notesRoot[$cKey] = [];
            }

            $clean = [];
            foreach ($items as $row) {
                $clean[] = $this->learnNotesPersistRowEncode($row);
            }
            $notesRoot[$cKey][$pKey] = array_values($clean);
        }

        return $notesRoot;
    }

    /**
     * @param array{id:string,text:string,created_at:string} $row
     * @return array{id:string,text:string,created_at:string}
     */
    private function learnNotesExposeRow(array $row): array
    {
        $mysql = trim((string) ($row['created_at'] ?? ''));
        $t = strtotime($mysql . ' UTC');

        return [
            'id' => (string) $row['id'],
            'text' => (string) $row['text'],
            'created_at' => ($t !== false && $t > 0) ? gmdate('c', (int) $t) : gmdate('c'),
        ];
    }

    /**
     * @param array{id:string,text:string,created_at:string} $note
     * @param array{id:string,text:string,created_at:string} $compare
     */
    private function learnNotesCompareByCreated(array $note, array $compare): int
    {
        $sa = strtotime(trim((string) ($note['created_at'] ?? '')) . ' UTC');
        $sb = strtotime(trim((string) ($compare['created_at'] ?? '')) . ' UTC');
        $aa = (($sa !== false && $sa > 0) ? $sa : 0);
        $bb = (($sb !== false && $sb > 0) ? $sb : 0);

        return ($aa <=> $bb);
    }

    /** @param array<int,array{id:string,text:string,created_at:string}> $payloadNotes */
    private function learnNotesFlattenForLegacyUi(array $payloadNotes): string
    {
        if ($payloadNotes === []) {
            return '';
        }

        $parts = [];
        foreach ($payloadNotes as $snip) {
            $parts[] = (string) ($snip['text'] ?? '');
        }

        return implode("\n\n", array_filter($parts, static fn($s) => $s !== ''));
    }

    private function learnNotesValidateIdToken(string $id): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9_.-]{4,96}$/', $id);
    }
}
