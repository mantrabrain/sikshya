<?php

declare(strict_types=1);

namespace Sikshya\Tests\Integration;

use Sikshya\Constants\PostTypes;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Paid material must not come back from core's `wp/v2` routes.
 *
 * Reported privately by a security researcher against 1.0.16 and reproduced:
 * `GET /wp-json/wp/v2/sik_question` returned
 * `meta._sikshya_question_correct_answer` for every question on the site to an
 * anonymous visitor, and `wp/v2/sik_lesson` returned lesson bodies, video
 * URLs, transcripts, live-class join links and SCORM launch URLs. The course
 * page and the Learn shell refused that same visitor — these are core's
 * routes, so the plugin's enrolment check never ran.
 *
 * The cause is a misread flag rather than a missing check: meta is registered
 * `show_in_rest` so the editor can save it, and core also treats that as
 * "publish to every reader". `auth_callback` governs writes only.
 *
 * These tests pin the read side. The meta assertions are deliberately written
 * against "no private key survives" rather than against the four keys the
 * report named, because listing keys to hide is what let this happen.
 *
 * @group security
 * @group rest_guard
 */
final class RestReadGuardIntegrationTest extends WP_UnitTestCase
{
    private int $course;
    private int $lesson;
    private int $question;

    public function set_up(): void
    {
        parent::set_up();

        (new \Sikshya\PostTypes\PostTypeManager())->registerRestAccessiblePostMeta();
        do_action('rest_api_init');

        $this->course = (int) self::factory()->post->create([
            'post_type' => PostTypes::COURSE,
            'post_status' => 'publish',
            'post_title' => 'Paid course',
        ]);

        $this->lesson = (int) self::factory()->post->create([
            'post_type' => PostTypes::LESSON,
            'post_status' => 'publish',
            'post_title' => 'Paid lesson',
            'post_content' => 'The body a learner pays for.',
        ]);
        update_post_meta($this->lesson, '_sikshya_lesson_course', $this->course);
        update_post_meta($this->lesson, '_sikshya_lesson_video_url', 'https://cdn.example.com/private.mp4');
        update_post_meta($this->lesson, '_sikshya_lesson_transcript_text', 'Every word of the lesson.');

        $this->question = (int) self::factory()->post->create([
            'post_type' => PostTypes::QUESTION,
            'post_status' => 'publish',
            'post_title' => 'What goes in the cash book?',
        ]);
        update_post_meta($this->question, '_sikshya_question_correct_answer', '0');
        update_post_meta($this->question, '_sikshya_question_options', ['Every sale', 'Only card sales']);
    }

    /** @return array{0:int,1:mixed} */
    private function get(string $route, array $params = []): array
    {
        $request = new WP_REST_Request('GET', $route);
        foreach ($params as $k => $v) {
            $request->set_param($k, $v);
        }
        $response = rest_do_request($request);

        return [$response->get_status(), $response->get_data()];
    }

    private function privateMetaKeys($row): array
    {
        $meta = is_array($row) && isset($row['meta']) && is_array($row['meta']) ? $row['meta'] : [];

        return array_values(array_filter(array_keys($meta), static fn($k): bool => strpos((string) $k, '_') === 0));
    }

    public function test_an_anonymous_visitor_cannot_read_the_question_bank(): void
    {
        wp_set_current_user(0);

        [$status] = $this->get('/wp/v2/sik_question');

        $this->assertSame(
            401,
            $status,
            'Question titles are the questions themselves, so the route is closed to non-authors.'
        );
    }

    public function test_no_private_lesson_meta_reaches_an_anonymous_visitor(): void
    {
        wp_set_current_user(0);

        [$status, $data] = $this->get('/wp/v2/sik_lesson/' . $this->lesson);

        $this->assertSame(200, $status);
        $this->assertSame(
            [],
            $this->privateMetaKeys($data),
            'Every underscore-prefixed key is withheld by default, including ones added later.'
        );
    }

    public function test_the_body_of_a_paid_lesson_is_withheld(): void
    {
        wp_set_current_user(0);

        [, $data] = $this->get('/wp/v2/sik_lesson/' . $this->lesson);

        $this->assertStringNotContainsString(
            'The body a learner pays for.',
            (string) ($data['content']['rendered'] ?? ''),
            'A lesson nobody has bought should not be readable over REST either.'
        );
    }

    public function test_a_lesson_the_author_marked_free_is_still_readable(): void
    {
        update_post_meta($this->lesson, '_sikshya_is_free', '1');
        wp_set_current_user(0);

        [, $data] = $this->get('/wp/v2/sik_lesson/' . $this->lesson);

        $this->assertStringContainsString(
            'The body a learner pays for.',
            (string) ($data['content']['rendered'] ?? ''),
            'A preview lesson is published on purpose; the guard must not take that away.'
        );
    }

    public function test_an_author_still_sees_everything(): void
    {
        wp_set_current_user((int) self::factory()->user->create(['role' => 'administrator']));

        [$qs, $qd] = $this->get('/wp/v2/sik_question', ['context' => 'edit']);
        $this->assertSame(200, $qs, 'The admin editor reads questions over this exact route.');
        $this->assertNotSame([], $this->privateMetaKeys($qd[0] ?? []), 'Authors need the answer key to edit it.');

        [$ls, $ld] = $this->get('/wp/v2/sik_lesson/' . $this->lesson, ['context' => 'edit']);
        $this->assertSame(200, $ls);
        $this->assertContains('_sikshya_lesson_video_url', $this->privateMetaKeys($ld));
        $this->assertStringContainsString('The body a learner pays for.', (string) ($ld['content']['raw'] ?? ''));
    }

    public function test_a_signed_in_learner_who_has_not_bought_the_course_gets_nothing_extra(): void
    {
        // The report noted this case explicitly: being logged in was no bar.
        wp_set_current_user((int) self::factory()->user->create(['role' => 'subscriber']));

        [, $data] = $this->get('/wp/v2/sik_lesson/' . $this->lesson);

        $this->assertSame([], $this->privateMetaKeys($data));
        $this->assertStringNotContainsString(
            'The body a learner pays for.',
            (string) ($data['content']['rendered'] ?? '')
        );
    }

    public function test_course_price_stays_public(): void
    {
        update_post_meta($this->course, '_sikshya_course_price', '49.00');
        wp_set_current_user(0);

        [, $data] = $this->get('/wp/v2/sik_course/' . $this->course);

        $this->assertSame(
            '49.00',
            $data['meta']['_sikshya_course_price'] ?? null,
            'The price is printed on the course page; hiding it would break catalogues for nothing.'
        );
    }
}
