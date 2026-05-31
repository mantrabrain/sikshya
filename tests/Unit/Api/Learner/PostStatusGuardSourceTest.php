<?php

declare(strict_types=1);

namespace Sikshya\Tests\Unit\Api\Learner;

use PHPUnit\Framework\TestCase;

/**
 * Source-level regression guards for the post_status === 'publish' checks
 * added across learner endpoints. These supplement (do not replace) the
 * behaviour tests in AssignmentServicePostStatusTest.
 *
 * Background: pre-fix, learner endpoints only verified `post_type` matched
 * the expected CPT — true for draft and trashed posts. An admin unpublishing
 * a quiz/lesson/assignment mid-attempt did not block in-flight learner
 * actions. The fix adds `get_post_status(...) !== 'publish'` guards to each.
 *
 * These tests parse the source files and assert each guard is still in
 * place. They are **less** valuable than full behaviour tests but **cheaper**
 * — the alternative is mocking the full Plugin/service-locator dependency
 * graph for each REST controller, which is disproportionate for a guard
 * that's effectively one if-statement.
 *
 * Future work: replace with WP_UnitTestCase-based integration tests once
 * tests/Integration/ is wired up against a real WP test framework.
 */
final class PostStatusGuardSourceTest extends TestCase
{
    private const QUIZ_ROUTES = __DIR__ . '/../../../../src/Api/Learner/QuizRoutes.php';
    private const PROGRESS_ROUTES = __DIR__ . '/../../../../src/Api/Learner/ProgressRoutes.php';

    private function loadSource(string $path): string
    {
        self::assertFileExists($path, "Required source file missing: {$path}");
        $src = file_get_contents($path);
        self::assertIsString($src, "Failed to read source: {$path}");
        return $src;
    }

    /**
     * Extract one PHP method body by name from a source string.
     */
    private function extractMethod(string $src, string $methodName): string
    {
        $pattern = '/public function ' . preg_quote($methodName, '/') . '\s*\([^)]*\)[^{]*\{/m';
        if (!preg_match($pattern, $src, $m, PREG_OFFSET_CAPTURE)) {
            self::fail("Could not locate method `{$methodName}` in source. Has it been renamed?");
        }
        $start = $m[0][1] + strlen($m[0][0]);
        // Walk braces to find the matching close.
        $depth = 1;
        $len = strlen($src);
        for ($i = $start; $i < $len; $i++) {
            if ($src[$i] === '{') {
                $depth++;
            } elseif ($src[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($src, $start, $i - $start);
                }
            }
        }
        self::fail("Brace-unbalanced source while extracting `{$methodName}`.");
    }

    public function testQuizSubmitGuardsAgainstUnpublishedQuiz(): void
    {
        $body = $this->extractMethod($this->loadSource(self::QUIZ_ROUTES), 'quizSubmit');
        self::assertMatchesRegularExpression(
            "/get_post_status\\s*\\([^)]*\\)\\s*!==\\s*'publish'/",
            $body,
            'quizSubmit must reject any quiz whose post_status is not publish — otherwise admin unpublishing mid-attempt does not block grading.'
        );
        self::assertStringContainsString(
            'quiz_unavailable',
            $body,
            'quizSubmit must surface the dedicated quiz_unavailable error code so the learner UI can distinguish "deleted/unpublished" from generic "invalid".'
        );
    }

    public function testStartQuizAttemptGuardsAgainstUnpublishedQuiz(): void
    {
        $body = $this->extractMethod($this->loadSource(self::QUIZ_ROUTES), 'startMyQuizAttempt');
        self::assertMatchesRegularExpression(
            "/get_post_status\\s*\\([^)]*\\)\\s*!==\\s*'publish'/",
            $body,
            'startMyQuizAttempt must reject any quiz whose post_status is not publish.'
        );
    }

    public function testStartQuizAttemptEnforcesAttemptCapBeforeCreate(): void
    {
        $body = $this->extractMethod($this->loadSource(self::QUIZ_ROUTES), 'startMyQuizAttempt');

        // Verify both that the cap check exists AND that it precedes the
        // createAttempt call — otherwise a learner could accrue stale
        // in_progress attempts they can never submit.
        self::assertStringContainsString(
            'attempts_exhausted',
            $body,
            'startMyQuizAttempt must enforce the attempt cap with the dedicated attempts_exhausted error code.'
        );

        $capPos = strpos($body, 'attempts_exhausted');
        $createPos = strpos($body, 'createAttempt');
        self::assertIsInt($capPos);
        self::assertIsInt($createPos);
        self::assertLessThan(
            $createPos,
            $capPos,
            'The attempt-cap check must run BEFORE createAttempt — otherwise the learner can accumulate in_progress rows that they can never submit, only to hit the cap at submit-time.'
        );
    }

    public function testGetMyQuizAttemptGuardsAgainstUnpublishedQuiz(): void
    {
        $body = $this->extractMethod($this->loadSource(self::QUIZ_ROUTES), 'getMyQuizAttempt');
        self::assertMatchesRegularExpression(
            "/get_post_status\\s*\\([^)]*\\)\\s*!==\\s*'publish'/",
            $body,
            'getMyQuizAttempt must reject any quiz whose post_status is not publish (consistency with submit / start).'
        );
    }

    public function testLessonCompleteGuardsAgainstUnpublishedLesson(): void
    {
        $body = $this->extractMethod($this->loadSource(self::PROGRESS_ROUTES), 'lessonComplete');
        self::assertMatchesRegularExpression(
            "/get_post_status\\s*\\([^)]*\\)\\s*!==\\s*'publish'/",
            $body,
            'lessonComplete must reject draft/trashed lessons. LearnerCurriculumHelper::lessonIdsForCourse returns draft IDs too, so the explicit guard is required.'
        );
        self::assertStringContainsString(
            'lesson_unavailable',
            $body,
            'lessonComplete must surface the dedicated lesson_unavailable error code.'
        );
    }
}
