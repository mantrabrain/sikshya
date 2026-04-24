<?php

namespace Sikshya\Frontend\Public;

use Sikshya\Constants\PostTypes;
use Sikshya\Core\Plugin;
use Sikshya\Database\Repositories\InstructorMetricsRepository;

/**
 * Wires the "Teaching" (instructor) account view + sidebar group on the learner
 * account page so signed-in instructors get a role-aware experience without a
 * separate dashboard area.
 *
 * Free tier exposes published-course count and enrollment count on their
 * authored courses; Pro `instructor_dashboard` enriches the data via
 * {@see 'sikshya_account_instructor_data'} (revenue share, recent learners…).
 *
 * @package Sikshya\Frontend\Public
 */
final class InstructorAccountView
{
    private static bool $registered = false;

    public const VIEW_SLUG = 'instructor';

    public static function init(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        add_filter('sikshya_account_allowed_views', [self::class, 'registerView']);
        add_filter('sikshya_account_template_data', [self::class, 'injectInstructorData'], 30);
        add_filter('sikshya_account_view_template', [self::class, 'overrideViewTemplate'], 10, 3);
        add_action('sikshya_account_sidebar_nav', [self::class, 'renderSidebarNav'], 5, 2);
        add_action('sikshya_account_dashboard_after_hero', [self::class, 'renderDashboardTeaser'], 5, 1);
    }

    /**
     * @param string[] $views
     * @return string[]
     */
    public static function registerView($views): array
    {
        $views = is_array($views) ? $views : [];
        if (InstructorContext::isInstructor(get_current_user_id())) {
            $views[] = self::VIEW_SLUG;
        }

        return array_values(array_unique($views));
    }

    /**
     * @param array<string, mixed> $acc
     * @return array<string, mixed>
     */
    public static function injectInstructorData($acc): array
    {
        if (!is_array($acc)) {
            return is_array($acc) ? $acc : [];
        }

        $uid = (int) ($acc['user_id'] ?? 0);
        $is_instructor = InstructorContext::isInstructor($uid);
        $acc['is_instructor'] = $is_instructor;
        $acc['urls']['account_instructor'] = PublicPageUrls::accountViewUrl(self::VIEW_SLUG);
        $acc['urls']['edit_courses'] = current_user_can('edit_sikshya_courses')
            ? admin_url('edit.php?post_type=' . PostTypes::COURSE)
            : '';
        $acc['urls']['add_new_course'] = current_user_can('edit_sikshya_courses')
            ? admin_url('post-new.php?post_type=' . PostTypes::COURSE)
            : '';

        if (!$is_instructor || $uid <= 0) {
            return $acc;
        }

        $acc['instructor'] = self::buildInstructorData($uid);

        return $acc;
    }

    /**
     * Use the instructor partial when the current view is the teaching view.
     *
     * @param string                $path
     * @param string                $view
     * @param array<string, mixed>  $acc
     */
    public static function overrideViewTemplate($path, $view, $acc): string
    {
        if ($view !== self::VIEW_SLUG) {
            return is_string($path) ? $path : '';
        }

        $candidate = Plugin::getInstance()->getTemplatePath('partials/account-view-instructor.php');
        if (is_readable($candidate)) {
            return $candidate;
        }

        return is_string($path) ? $path : '';
    }

    /**
     * @param array<string, mixed> $acc
     */
    public static function renderSidebarNav($acc, string $view): void
    {
        if (!is_array($acc) || empty($acc['is_instructor'])) {
            return;
        }
        $url = (string) ($acc['urls']['account_instructor'] ?? '');
        if ($url === '') {
            return;
        }

        echo '<p class="sik-acc-nav__label">' . esc_html__('Teaching', 'sikshya') . '</p>';
        echo '<a class="' . ($view === self::VIEW_SLUG ? 'is-active' : '') . '" href="' . esc_url($url) . '">';
        echo '<span class="sik-acc-nav__icon" aria-hidden="true">▤</span>';
        echo esc_html__('Instructor overview', 'sikshya');
        echo '</a>';

        $edit_url = (string) ($acc['urls']['edit_courses'] ?? '');
        if ($edit_url !== '') {
            echo '<a href="' . esc_url($edit_url) . '">';
            echo '<span class="sik-acc-nav__icon" aria-hidden="true">▦</span>';
            echo esc_html__('Manage my courses', 'sikshya');
            echo '</a>';
        }

        $new_url = (string) ($acc['urls']['add_new_course'] ?? '');
        if ($new_url !== '') {
            echo '<a href="' . esc_url($new_url) . '">';
            echo '<span class="sik-acc-nav__icon" aria-hidden="true">+</span>';
            echo esc_html__('Add new course', 'sikshya');
            echo '</a>';
        }
    }

    /**
     * Compact teaching panel on the dashboard so instructors see their numbers
     * before the learner library section.
     *
     * @param array<string, mixed> $acc
     */
    public static function renderDashboardTeaser($acc): void
    {
        if (!is_array($acc) || empty($acc['is_instructor']) || empty($acc['instructor'])) {
            return;
        }

        $data = $acc['instructor'];
        $published = (int) ($data['published_courses'] ?? 0);
        $enrollments = (int) ($data['enrollments_total'] ?? 0);
        $completed = (int) ($data['enrollments_completed'] ?? 0);
        $url = (string) ($acc['urls']['account_instructor'] ?? '');

        echo '<section class="sik-acc-panel" aria-label="' . esc_attr__('Teaching summary', 'sikshya') . '" style="margin-top:0.5rem;">';
        echo '<div class="sik-acc-panel__head">';
        echo '<h2 class="sik-acc-panel__title">' . esc_html__('Teaching summary', 'sikshya') . '</h2>';
        if ($url !== '') {
            echo '<a class="sik-acc-btn" href="' . esc_url($url) . '">' . esc_html__('Open instructor view', 'sikshya') . ' →</a>';
        }
        echo '</div>';
        echo '<div class="sik-acc-metrics">';
        echo '<div class="sik-acc-metric"><div class="sik-acc-metric__value">' . esc_html((string) $published) . '</div><div class="sik-acc-metric__label">' . esc_html__('Published courses', 'sikshya') . '</div></div>';
        echo '<div class="sik-acc-metric"><div class="sik-acc-metric__value">' . esc_html((string) $enrollments) . '</div><div class="sik-acc-metric__label">' . esc_html__('Enrollments on my courses', 'sikshya') . '</div></div>';
        echo '<div class="sik-acc-metric"><div class="sik-acc-metric__value">' . esc_html((string) $completed) . '</div><div class="sik-acc-metric__label">' . esc_html__('Completions', 'sikshya') . '</div></div>';
        echo '</div>';
        echo '</section>';
    }

    /**
     * Free baseline: counts of authored courses, enrollments and completions.
     * Pro tier hooks {@see 'sikshya_account_instructor_data'} to add revenue,
     * recent learners and other premium data.
     *
     * @return array<string, mixed>
     */
    private static function buildInstructorData(int $uid): array
    {
        $metrics = new InstructorMetricsRepository();
        $published = $metrics->countPublishedCoursesByAuthor($uid);
        $course_ids = $metrics->getAuthoredCourseIds($uid);
        $enroll = $metrics->getEnrollmentStatsForCourseIds($course_ids);
        $enrollments_total = $enroll['enrollments_total'];
        $enrollments_completed = $enroll['enrollments_completed'];
        $recent_courses = $enroll['recent_courses'];

        $data = [
            'user_id' => $uid,
            'published_courses' => $published,
            'authored_course_ids' => $course_ids,
            'enrollments_total' => $enrollments_total,
            'enrollments_completed' => $enrollments_completed,
            'recent_courses' => $recent_courses,
            'pro_blocks' => [],
        ];

        /**
         * Allow Pro / addons to add deeper instructor metrics
         * (revenue share, recent learners, gradebook health, etc.).
         *
         * @param array<string, mixed> $data
         * @param int                  $uid
         */
        $data = apply_filters('sikshya_account_instructor_data', $data, $uid);

        return is_array($data) ? $data : [];
    }
}
