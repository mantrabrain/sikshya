<?php

namespace Sikshya\Database\Tables;

final class Tables
{
    /**
     * @return array<class-string<TableInterface>>
     */
    public static function all(): array
    {
        return [
            EnrollmentsTable::class,
            ProgressTable::class,
            CertificatesTable::class,
            AssignmentSubmissionsTable::class,
            PaymentsTable::class,
            QuizAttemptsTable::class,
            QuizQuestionsTable::class,
            LessonContentTable::class,
            AchievementsTable::class,
            NotificationsTable::class,
            ReviewsTable::class,
            QuizAttemptItemsTable::class,
            OrdersTable::class,
            OrderItemsTable::class,
            CouponsTable::class,
            CouponRedemptionsTable::class,
            AnalyticsTable::class,
            LogsTable::class,
        ];
    }
}

