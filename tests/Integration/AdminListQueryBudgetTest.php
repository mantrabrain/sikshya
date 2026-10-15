<?php

namespace Sikshya\Tests\Integration;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\AdminTablesRepository;
use Sikshya\Database\Repositories\OrderRepository;
use WP_UnitTestCase;

/**
 * Query budgets for the two heaviest admin listings.
 *
 * Measured on a real dataset before this work: the orders list cost **65
 * queries** and the quiz-attempts list **64**, because each row fetched its own
 * line items, course titles, payer and attempt count. After batching: **9** and
 * **6**.
 *
 * Both assert that cost does not grow per row rather than checking an absolute
 * figure, because a small fixture lets a per-row implementation pass unnoticed.
 *
 * @group performance
 */
final class AdminListQueryBudgetTest extends WP_UnitTestCase
{
    public function set_up(): void
    {
        parent::set_up();
        wp_set_current_user((int) self::factory()->user->create(['role' => 'administrator']));
    }

    private function queriesFor(callable $fn): int
    {
        global $wpdb;
        $before = (int) $wpdb->num_queries;
        $fn();

        return (int) $wpdb->num_queries - $before;
    }

    private function makeCourse(): int
    {
        return (int) self::factory()->post->create([
            'post_type' => PostTypes::COURSE,
            'post_status' => 'publish',
        ]);
    }

    /**
     * @return int[] Order ids.
     */
    private function seedOrders(OrderRepository $repo, int $count): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $order_id = $repo->createOrder([
                'user_id' => (int) self::factory()->user->create(),
                'status' => 'completed',
                'currency' => 'USD',
                'subtotal' => 50.00,
                'total' => 50.00,
            ]);
            // Two lines per order, each on its own course, so the per-row title
            // and line lookups would have real work to do.
            $repo->addOrderItem($order_id, $this->makeCourse(), 1, 25.00, 25.00);
            $repo->addOrderItem($order_id, $this->makeCourse(), 1, 25.00, 25.00);
            $ids[] = $order_id;
        }

        return $ids;
    }

    public function test_order_line_items_are_fetched_for_a_whole_page_at_once(): void
    {
        $repo = new OrderRepository();
        $few = $this->seedOrders($repo, 3);
        $many = array_merge($few, $this->seedOrders($repo, 9));

        $small = $this->queriesFor(static function () use ($repo, $few): void {
            $repo->getItemsForOrders($few);
        });
        $large = $this->queriesFor(static function () use ($repo, $many): void {
            $repo->getItemsForOrders($many);
        });

        self::assertSame(
            $small,
            $large,
            'fetching lines for 12 orders must cost the same as for 3 — it is one query either way'
        );
    }

    public function test_batched_order_lines_return_the_same_rows_as_per_order_reads(): void
    {
        $repo = new OrderRepository();
        $ids = $this->seedOrders($repo, 4);

        $batched = $repo->getItemsForOrders($ids);

        foreach ($ids as $id) {
            $one_by_one = $repo->getItems($id);
            self::assertCount(
                count($one_by_one),
                $batched[$id] ?? [],
                'the batched read must return exactly the lines the per-order read does'
            );
            self::assertSame(
                array_map(static fn ($r): int => (int) $r->course_id, $one_by_one),
                array_map(static fn ($r): int => (int) $r->course_id, $batched[$id]),
                'and in the same order'
            );
        }
    }

    public function test_an_empty_order_list_costs_nothing(): void
    {
        $repo = new OrderRepository();

        $cost = $this->queriesFor(static function () use ($repo): void {
            self::assertSame([], $repo->getItemsForOrders([]));
        });

        self::assertSame(0, $cost, 'no orders means no query at all');
    }

    /**
     * The quiz-attempts listing asked each row for its learner's attempt count.
     */
    public function test_attempt_counts_are_fetched_for_a_whole_page_at_once(): void
    {
        $repo = new \Sikshya\Database\Repositories\QuizAttemptRepository();
        if (!$repo->tableExists()) {
            self::markTestSkipped('quiz attempts table is not installed in this environment');
        }

        $users = [];
        $quizzes = [];
        for ($i = 0; $i < 6; $i++) {
            $users[] = (int) self::factory()->user->create();
            $quizzes[] = (int) self::factory()->post->create(['post_type' => PostTypes::QUIZ]);
        }

        $small = $this->queriesFor(static function () use ($repo, $users, $quizzes): void {
            $repo->countAttemptsForPairs(array_slice($users, 0, 2), array_slice($quizzes, 0, 2));
        });
        $large = $this->queriesFor(static function () use ($repo, $users, $quizzes): void {
            $repo->countAttemptsForPairs($users, $quizzes);
        });

        self::assertSame(
            $small,
            $large,
            'counting attempts for 6 learners must cost the same as for 2 — one grouped query either way'
        );
    }

    public function test_batched_attempt_counts_agree_with_the_single_lookup(): void
    {
        $repo = new \Sikshya\Database\Repositories\QuizAttemptRepository();
        if (!$repo->tableExists()) {
            self::markTestSkipped('quiz attempts table is not installed in this environment');
        }

        $user = (int) self::factory()->user->create();
        $quiz = (int) self::factory()->post->create(['post_type' => PostTypes::QUIZ]);

        $pairs = $repo->countAttemptsForPairs([$user], [$quiz]);

        self::assertSame(
            $repo->countAttemptsForUserQuiz($user, $quiz),
            (int) ($pairs[$user . ':' . $quiz] ?? 0),
            'the batched count must match what the per-pair query returns'
        );
    }

    public function test_attempt_counts_with_no_ids_cost_nothing(): void
    {
        $repo = new \Sikshya\Database\Repositories\QuizAttemptRepository();

        $cost = $this->queriesFor(static function () use ($repo): void {
            self::assertSame([], $repo->countAttemptsForPairs([], []));
        });

        self::assertSame(0, $cost);
    }
}
