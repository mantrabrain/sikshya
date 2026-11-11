<?php

declare(strict_types=1);

namespace Sikshya\Tests\Integration;

use Sikshya\Constants\PostTypes;
use Sikshya\Services\QuizQuestionInverseLookup;
use WP_UnitTestCase;

/**
 * "Which quiz holds this question" must cost one scan per page, not one per row.
 *
 * The answer can only come from a `LIKE '%i:123;%'` over `postmeta`: the quiz's
 * question list is stored serialized, and a leading wildcard cannot use an
 * index, so each call reads the whole table. The question list screen asks for
 * it once per row through the `sikshya_learn_view_url` REST field — measured on
 * a site with 291,956 postmeta rows, a 20-row page spent 402ms of its 535ms in
 * twenty identical scans.
 *
 * These tests pin the shape of the fix rather than a millisecond figure, which
 * would be flaky: a page of questions must produce exactly one scan, and must
 * return precisely what the per-row lookup returned before.
 *
 * @group performance
 * @group rest
 */
final class QuizQuestionInverseLookupTest extends WP_UnitTestCase
{
    /** @var int[] */
    private array $questions = [];
    private int $quiz;
    private int $draftQuiz;

    public function set_up(): void
    {
        parent::set_up();
        QuizQuestionInverseLookup::forget();

        for ($i = 0; $i < 6; $i++) {
            $this->questions[] = (int) self::factory()->post->create([
                'post_type' => PostTypes::QUESTION,
                'post_status' => 'publish',
                'post_title' => 'Question ' . $i,
            ]);
        }

        $this->quiz = (int) self::factory()->post->create([
            'post_type' => PostTypes::QUIZ,
            'post_status' => 'publish',
        ]);
        // First three belong to a published quiz; the rest belong to nothing.
        update_post_meta($this->quiz, '_sikshya_quiz_questions', array_slice($this->questions, 0, 3));

        // A draft quiz must never be the answer, however it is looked up.
        $this->draftQuiz = (int) self::factory()->post->create([
            'post_type' => PostTypes::QUIZ,
            'post_status' => 'draft',
        ]);
        update_post_meta($this->draftQuiz, '_sikshya_quiz_questions', [$this->questions[5]]);
    }

    public function tear_down(): void
    {
        QuizQuestionInverseLookup::forget();
        parent::tear_down();
    }

    private function scanCount(callable $fn): int
    {
        global $wpdb;
        $before = $wpdb->num_queries;
        $seen = [];
        $spy = static function ($query) use (&$seen) {
            if (strpos((string) $query, '_sikshya_quiz_questions') !== false) {
                $seen[] = $query;
            }

            return $query;
        };
        add_filter('query', $spy);
        $fn();
        remove_filter('query', $spy);
        unset($before);

        return count($seen);
    }

    public function test_a_page_of_questions_costs_a_single_scan(): void
    {
        $scans = $this->scanCount(function (): void {
            QuizQuestionInverseLookup::prime($this->questions);
            foreach ($this->questions as $id) {
                QuizQuestionInverseLookup::firstPublishedQuizIdForQuestion($id);
            }
        });

        $this->assertSame(
            1,
            $scans,
            'Six questions must resolve in one pass over postmeta; one scan per row is what made the list slow.'
        );
    }

    public function test_the_answers_are_the_ones_the_per_row_lookup_gave(): void
    {
        QuizQuestionInverseLookup::prime($this->questions);

        foreach (array_slice($this->questions, 0, 3) as $id) {
            $this->assertSame(
                $this->quiz,
                QuizQuestionInverseLookup::firstPublishedQuizIdForQuestion($id),
                'A question inside a published quiz resolves to that quiz.'
            );
        }
        foreach (array_slice($this->questions, 3) as $id) {
            $this->assertSame(
                0,
                QuizQuestionInverseLookup::firstPublishedQuizIdForQuestion($id),
                'A question no published quiz references resolves to 0 — including one held only by a draft.'
            );
        }
    }

    public function test_a_single_lookup_still_works_without_priming(): void
    {
        // Anything outside the list screen calls this cold; it must not depend
        // on the batch having run.
        QuizQuestionInverseLookup::forget();

        $this->assertSame($this->quiz, QuizQuestionInverseLookup::firstPublishedQuizIdForQuestion($this->questions[0]));
        $this->assertSame(0, QuizQuestionInverseLookup::firstPublishedQuizIdForQuestion($this->questions[4]));
    }

    public function test_a_question_with_no_quiz_is_not_scanned_twice(): void
    {
        // "Found nothing" has to be remembered too, or half a page of orphan
        // questions re-scans the table on every call.
        QuizQuestionInverseLookup::prime($this->questions);

        $scans = $this->scanCount(function (): void {
            for ($i = 0; $i < 3; $i++) {
                QuizQuestionInverseLookup::firstPublishedQuizIdForQuestion($this->questions[4]);
            }
        });

        $this->assertSame(0, $scans, 'A resolved miss must stay resolved.');
    }

    public function test_priming_the_same_page_twice_is_free(): void
    {
        QuizQuestionInverseLookup::prime($this->questions);

        $scans = $this->scanCount(function (): void {
            QuizQuestionInverseLookup::prime($this->questions);
        });

        $this->assertSame(0, $scans);
    }

    public function test_changing_a_quiz_mid_request_is_not_answered_from_the_memo(): void
    {
        // Memoising is only safe because writes invalidate it. Imports, the
        // content bank and AI apply all rewrite `_sikshya_quiz_questions` and
        // may ask about those questions in the same request; without the
        // listener this returns the pre-change answer, which would make the
        // speed-up a correctness regression.
        QuizQuestionInverseLookup::listen();
        QuizQuestionInverseLookup::prime($this->questions);

        $orphan = $this->questions[4];
        self::assertSame(0, QuizQuestionInverseLookup::firstPublishedQuizIdForQuestion($orphan));

        update_post_meta($this->quiz, '_sikshya_quiz_questions', array_merge(
            array_slice($this->questions, 0, 3),
            [$orphan]
        ));

        self::assertSame(
            $this->quiz,
            QuizQuestionInverseLookup::firstPublishedQuizIdForQuestion($orphan),
            'a question added to a quiz in this request must resolve to it, not to the memoised miss'
        );
    }

    public function test_a_question_removed_from_a_quiz_stops_resolving_to_it(): void
    {
        QuizQuestionInverseLookup::listen();
        QuizQuestionInverseLookup::prime($this->questions);
        self::assertSame($this->quiz, QuizQuestionInverseLookup::firstPublishedQuizIdForQuestion($this->questions[0]));

        update_post_meta($this->quiz, '_sikshya_quiz_questions', array_slice($this->questions, 1, 2));

        self::assertSame(
            0,
            QuizQuestionInverseLookup::firstPublishedQuizIdForQuestion($this->questions[0]),
            'removal must be seen too, not just addition'
        );
    }

    public function test_a_question_in_several_quizzes_resolves_the_same_way_as_before(): void
    {
        // The old lookup took whichever row MySQL returned first under LIMIT 1.
        // The batch must still return one of the real holders, never 0.
        $second = (int) self::factory()->post->create([
            'post_type' => PostTypes::QUIZ,
            'post_status' => 'publish',
        ]);
        update_post_meta($second, '_sikshya_quiz_questions', [$this->questions[0]]);
        QuizQuestionInverseLookup::forget();

        QuizQuestionInverseLookup::prime($this->questions);
        $got = QuizQuestionInverseLookup::firstPublishedQuizIdForQuestion($this->questions[0]);

        $this->assertContains($got, [$this->quiz, $second], 'Must name a quiz that really holds the question.');
    }
}
