<?php

/**
 * @package Sikshya\Presentation\Models
 */

namespace Sikshya\Presentation\Models;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

final class AccountPageModel
{
    /**
     * @param array<string, mixed> $legacy
     * @param array{title:string,subtitle:string} $headline
     */
    private function __construct(
        private array $legacy,
        private AccountUrlsModel $urls,
        private string $view,
        private array $headline,
        private string $pageTitle,
        private string $greeting,
        private string $todayLine,
        private string $initial
    ) {
    }

    /**
     * @param array<string, mixed> $legacy
     */
    public static function fromLegacy(array $legacy): self
    {
        $view = (string) ($legacy['account_view'] ?? 'dashboard');
        if ($view === '') {
            $view = 'dashboard';
        }

        $headlines = [
            'dashboard' => [
                'title' => __('Overview', 'sikshya'),
                'subtitle' => __('Summary, metrics, and quick links.', 'sikshya'),
            ],
            'learning' => [
                'title' => __('My learning', 'sikshya'),
                'subtitle' => __('Courses in progress and completed.', 'sikshya'),
            ],
            'payments' => [
                'title' => __('Payments', 'sikshya'),
                'subtitle' => __('Orders, receipts, and payment records.', 'sikshya'),
            ],
            'quiz-attempts' => [
                'title' => __('Quiz attempts', 'sikshya'),
                'subtitle' => __('Usage and limits for quizzes in your courses.', 'sikshya'),
            ],
            'instructor' => [
                'title' => __('Instructor overview', 'sikshya'),
                'subtitle' => __('Your authored courses, enrollments, and teaching tools.', 'sikshya'),
            ],
        ];

        /** @var array{title:string,subtitle:string} $headline */
        $headline = $headlines[$view] ?? $headlines['dashboard'];

        $pageTitle = sprintf(
            /* translators: 1: page name, 2: site name */
            '%1$s — %2$s',
            $view === 'dashboard' ? __('My account', 'sikshya') : (string) $headline['title'],
            get_bloginfo('name')
        );

        $hour = (int) wp_date('G');
        if ($hour < 12) {
            $greeting = __('Good morning', 'sikshya');
        } elseif ($hour < 17) {
            $greeting = __('Good afternoon', 'sikshya');
        } else {
            $greeting = __('Good evening', 'sikshya');
        }
        $todayLine = (string) wp_date(get_option('date_format'));

        $displayName = (string) ($legacy['display_name'] ?? '');
        $initial = '?';
        if ($displayName !== '') {
            $initial = strtoupper(function_exists('mb_substr') ? (string) mb_substr($displayName, 0, 1) : (string) substr($displayName, 0, 1));
        }

        $urls = AccountUrlsModel::fromRow(is_array($legacy['urls'] ?? null) ? $legacy['urls'] : []);

        return new self($legacy, $urls, $view, $headline, $pageTitle, (string) $greeting, $todayLine, $initial);
    }

    /**
     * Back-compat view model for hooks expecting the legacy array.
     *
     * @return array<string, mixed>
     */
    public function toLegacyViewArray(): array
    {
        return $this->legacy;
    }

    public function getUrls(): AccountUrlsModel
    {
        return $this->urls;
    }

    public function getView(): string
    {
        return $this->view;
    }

    public function getHeadlineTitle(): string
    {
        return (string) ($this->headline['title'] ?? '');
    }

    public function getHeadlineSubtitle(): string
    {
        return (string) ($this->headline['subtitle'] ?? '');
    }

    public function getPageTitle(): string
    {
        return $this->pageTitle;
    }

    public function getGreeting(): string
    {
        return $this->greeting;
    }

    public function getTodayLine(): string
    {
        return $this->todayLine;
    }

    public function getUserId(): int
    {
        return (int) ($this->legacy['user_id'] ?? 0);
    }

    public function getDisplayName(): string
    {
        return (string) ($this->legacy['display_name'] ?? '');
    }

    public function getEmail(): string
    {
        return (string) ($this->legacy['email'] ?? '');
    }

    public function getAvatarUrl(): string
    {
        return (string) ($this->legacy['avatar_url'] ?? '');
    }

    public function getInitial(): string
    {
        return $this->initial;
    }

    public function isInstructor(): bool
    {
        return !empty($this->legacy['is_instructor']);
    }

    public function getEnrollmentCount(): int
    {
        return (int) ($this->legacy['enrollment_count'] ?? 0);
    }

    public function getOngoingCount(): int
    {
        return (int) ($this->legacy['ongoing_count'] ?? 0);
    }

    public function getCompletedCount(): int
    {
        return (int) ($this->legacy['completed_count'] ?? 0);
    }

    public function getOrdersCount(): int
    {
        return (int) ($this->legacy['orders_count'] ?? 0);
    }

    public function getQuizAttemptsCount(): int
    {
        return (int) ($this->legacy['quiz_attempts_count'] ?? 0);
    }

    /**
     * Legacy instructor payload (Pro touchpoints populate this).
     *
     * @return array<string, mixed>
     */
    public function getInstructorVm(): array
    {
        return is_array($this->legacy['instructor'] ?? null) ? (array) $this->legacy['instructor'] : [];
    }

    /**
     * @return array<int, mixed>
     */
    public function getEnrollmentsOngoing(): array
    {
        return is_array($this->legacy['enrollments_ongoing'] ?? null) ? (array) $this->legacy['enrollments_ongoing'] : [];
    }

    /**
     * @return array<int, mixed>
     */
    public function getEnrollmentsCompleted(): array
    {
        return is_array($this->legacy['enrollments_completed'] ?? null) ? (array) $this->legacy['enrollments_completed'] : [];
    }

    /**
     * @return array<int, mixed>
     */
    public function getOrders(): array
    {
        return is_array($this->legacy['orders'] ?? null) ? (array) $this->legacy['orders'] : [];
    }

    /**
     * @return array<int, mixed>
     */
    public function getLegacyPayments(): array
    {
        return is_array($this->legacy['legacy_payments'] ?? null) ? (array) $this->legacy['legacy_payments'] : [];
    }

    /**
     * @return array<int, mixed>
     */
    public function getQuizAttempts(): array
    {
        return is_array($this->legacy['quiz_attempts'] ?? null) ? (array) $this->legacy['quiz_attempts'] : [];
    }
}

