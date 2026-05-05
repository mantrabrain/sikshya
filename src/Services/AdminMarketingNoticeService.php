<?php

namespace Sikshya\Services;

use Sikshya\Constants\PostTypes;
use Sikshya\Database\Repositories\OrderRepository;
use WP_Error;

/**
 * Admin marketing notices (classic WP admin + Sikshya React shell), modelled on Yatra’s {@see \Yatra\Services\NoticeService}.
 *
 * - Review: after the first published course; dismiss cycles 15d → 60d → disabled.
 * - Upgrade: when Pro is not installed, after the first fulfilled order; dismiss cycles mirror Yatra (orders vs bookings).
 *
 * “Pro active” means the Sikshya Pro plugin is loaded — not license / {@see \Sikshya\Licensing\TierCapabilities::isActive()}.
 *
 * @package Sikshya\Services
 */
final class AdminMarketingNoticeService
{
    public const NOTICE_REVIEW = 'review';

    public const NOTICE_BUY_PRO = 'buy_pro';

    private const OPT_FIRST_COURSE_PUBLISHED_AT = 'sikshya_notice_first_course_published_at';

    private const OPT_FIRST_ORDER_FULFILLED_AT = 'sikshya_notice_first_order_fulfilled_at';

    private const META_REVIEW_DISABLED = 'sikshya_notice_review_disabled';

    private const META_REVIEW_DISMISS_COUNT = 'sikshya_notice_review_dismiss_count';

    private const META_REVIEW_NEXT_SHOW_AT = 'sikshya_notice_review_next_show_at';

    private const META_PRO_DISABLED = 'sikshya_notice_buy_pro_disabled';

    private const META_PRO_STAGE = 'sikshya_notice_buy_pro_stage';

    private const META_PRO_NEXT_SHOW_AT = 'sikshya_notice_buy_pro_next_show_at';

    public static function init(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('admin_notices', [self::class, 'renderWordPressNotices'], 30);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueWordPressNoticeAssets']);
        add_action('wp_ajax_sikshya_dismiss_marketing_notice', [self::class, 'ajaxDismissNotice']);

        add_action('sikshya_order_fulfilled', [self::class, 'markFirstOrderFulfilled'], 10, 2);
        add_action('transition_post_status', [self::class, 'maybeMarkFirstCoursePublished'], 10, 3);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getActiveNoticesForCurrentUser(): array
    {
        $userId = get_current_user_id();
        if ($userId <= 0) {
            return [];
        }

        $notices = [];

        if (self::shouldShowReviewNotice($userId)) {
            $notices[] = self::buildReviewNotice();
        }

        if (self::shouldShowBuyProNotice($userId)) {
            $notices[] = self::buildBuyProNotice();
        }

        return $notices;
    }

    /**
     * @return true|WP_Error
     */
    public static function dismissForCurrentUser(string $noticeId)
    {
        $userId = get_current_user_id();
        if ($userId <= 0) {
            return new WP_Error('sikshya_notice_unauthorized', __('Unauthorized.', 'sikshya'), ['status' => 401]);
        }

        if (!self::currentUserCanSeeNotices()) {
            return new WP_Error('sikshya_notice_forbidden', __('Forbidden.', 'sikshya'), ['status' => 403]);
        }

        $now = current_time('timestamp');

        if ($noticeId === self::NOTICE_REVIEW) {
            if ((bool) get_user_meta($userId, self::META_REVIEW_DISABLED, true)) {
                return true;
            }

            $count = (int) get_user_meta($userId, self::META_REVIEW_DISMISS_COUNT, true);
            $count++;

            update_user_meta($userId, self::META_REVIEW_DISMISS_COUNT, $count);

            if ($count === 1) {
                update_user_meta($userId, self::META_REVIEW_NEXT_SHOW_AT, $now + 15 * DAY_IN_SECONDS);
            } elseif ($count === 2) {
                update_user_meta($userId, self::META_REVIEW_NEXT_SHOW_AT, $now + 60 * DAY_IN_SECONDS);
            } else {
                update_user_meta($userId, self::META_REVIEW_DISABLED, 1);
                delete_user_meta($userId, self::META_REVIEW_NEXT_SHOW_AT);
            }

            return true;
        }

        if ($noticeId === self::NOTICE_BUY_PRO) {
            if ((bool) get_user_meta($userId, self::META_PRO_DISABLED, true)) {
                return true;
            }

            $stage = (int) get_user_meta($userId, self::META_PRO_STAGE, true);
            $stage = max(0, $stage);
            $stage++;

            update_user_meta($userId, self::META_PRO_STAGE, $stage);

            if ($stage === 1) {
                update_user_meta($userId, self::META_PRO_NEXT_SHOW_AT, $now + 30 * DAY_IN_SECONDS);
            } elseif ($stage === 2) {
                update_user_meta($userId, self::META_PRO_NEXT_SHOW_AT, $now + 90 * DAY_IN_SECONDS);
            } else {
                update_user_meta($userId, self::META_PRO_DISABLED, 1);
                delete_user_meta($userId, self::META_PRO_NEXT_SHOW_AT);
            }

            return true;
        }

        return new WP_Error('sikshya_notice_invalid', __('Invalid notice.', 'sikshya'), ['status' => 400]);
    }

    public static function renderWordPressNotices(): void
    {
        if (!self::currentUserCanSeeNotices()) {
            return;
        }

        $notices = self::getActiveNoticesForCurrentUser();
        if ($notices === []) {
            return;
        }

        foreach ($notices as $notice) {
            $id = isset($notice['id']) ? (string) $notice['id'] : '';
            $title = isset($notice['title']) ? (string) $notice['title'] : '';
            $message = isset($notice['message']) ? (string) $notice['message'] : '';
            $actions = isset($notice['actions']) && is_array($notice['actions']) ? $notice['actions'] : [];

            if ($id === '' || $message === '') {
                continue;
            }

            if ($id === self::NOTICE_BUY_PRO) {
                $primary = $actions[0] ?? null;
                $ctaLabel = is_array($primary) && !empty($primary['label']) ? (string) $primary['label'] : esc_html__('Upgrade to Pro', 'sikshya');
                $ctaUrl = is_array($primary) && !empty($primary['url']) ? (string) $primary['url'] : self::defaultUpgradeUrl();
                $ctaTarget = is_array($primary) && !empty($primary['target']) ? (string) $primary['target'] : '_blank';
                $ctaAttrs = $ctaTarget === '_blank' ? ' target="_blank" rel="noopener noreferrer"' : '';

                $orderCount = self::getTotalOrdersCount();
                if ($orderCount < 0) {
                    $orderCount = 0;
                }

                echo '<div id="sikshya-promotion-notice" class="notice is-dismissible sikshya-marketing-notice sikshya-marketing-notice--upgrade" data-sikshya-notice-id="' . esc_attr($id) . '" style="background: linear-gradient(135deg, #fdf6f0 0%, #f8f9fa 50%, #fff5ee 100%); border: 1px solid #f0e6d8; border-left: 4px solid #ff9500; border-radius: 6px; margin: 15px 0; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08); position: relative;">';

                echo '<div style="position: absolute; top: 8px; right: 80px; background: #ff9500; color: white; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px;">⚡ ' . esc_html__('Limited Time', 'sikshya') . '</div>';

                echo '<div class="sikshya-promotion-content" style="padding: 18px;">';
                echo '<div style="display: flex; align-items: flex-start; gap: 15px;">';
                echo '<div style="flex: 1;">';

                echo '<h3 style="margin: 0 0 10px 0; color: #2c3e50; font-size: 17px; font-weight: 600;">🚀 ' . esc_html__('Upgrade to Sikshya Pro — up to 50% off!', 'sikshya') . '</h3>';

                echo '<p style="margin: 0 0 12px 0; font-size: 14px; line-height: 1.5; color: #495057;">';
                echo wp_kses_post(
                    sprintf(
                        /* translators: %d: number of orders in the store. */
                        __('You’ve recorded <strong style="color: #ff9500;">%d</strong> order(s)! Get <strong style="color: #ff9500;">up to 50%% off</strong> on Sikshya Pro. Unlock premium payment tools, advanced modules, automation, and priority support.', 'sikshya'),
                        (int) $orderCount
                    )
                );
                echo '</p>';

                echo '<div style="background: rgba(255, 149, 0, 0.08); padding: 10px; border-radius: 4px; margin-bottom: 15px; border-left: 3px solid #ff9500;">';
                echo '<p style="margin: 0; font-size: 13px; color: #495057; font-weight: 600;">🎉 <strong>' . esc_html__('Special Offer:', 'sikshya') . '</strong> ' . esc_html__('Save up to 50% on your Pro upgrade with premium features and priority support!', 'sikshya') . '</p>';
                echo '</div>';

                echo '<div style="display: flex; align-items: center; gap: 12px;">';
                echo '<a href="' . esc_url($ctaUrl) . '"' . $ctaAttrs . ' class="button button-primary" style="background-color: #ff9500; border-color: #ff9500; color: white; padding: 6px 14px; font-weight: 600; font-size: 13px; border-radius: 4px; text-decoration: none; box-shadow: 0 1px 4px rgba(255, 149, 0, 0.25); transition: all 0.3s ease;">⚡ ' . esc_html__('Save up to 50% — Upgrade to Pro', 'sikshya') . '</a>';
                echo '<a href="#" id="sikshya-promotion-dismiss" data-sikshya-notice-dismiss="1" style="color: #6c757d; text-decoration: none; font-size: 13px; transition: color 0.3s ease;">' . esc_html__('Maybe later', 'sikshya') . '</a>';
                echo '</div>';

                echo '</div></div></div>';

                echo '<button type="button" class="notice-dismiss"><span class="screen-reader-text">' . esc_html__('Dismiss this notice.', 'sikshya') . '</span></button>';
                echo '</div>';
                continue;
            }

            $class = 'notice is-dismissible sikshya-marketing-notice sikshya-notice-card sikshya-notice-card--review';

            $iconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 17.3l-6.18 3.7 1.64-7.03L2 9.24l7.19-.61L12 2l2.81 6.63 7.19.61-5.46 4.73L18.18 21z"/></svg>';

            echo '<div class="' . esc_attr($class) . '" data-sikshya-notice-id="' . esc_attr($id) . '">';
            echo '<div class="sikshya-notice-card__wrap">';
            echo '<div class="sikshya-notice-card__row">';

            echo '<div class="sikshya-notice-card__left">';
            echo '<div class="sikshya-notice-card__icon" aria-hidden="true">' . $iconSvg . '</div>';
            echo '<div class="sikshya-notice-card__content">';
            echo '<div class="sikshya-notice-card__meta">';
            if ($title !== '') {
                echo '<div class="sikshya-notice-card__title">' . esc_html($title) . '</div>';
            }
            echo '</div>';
            echo '<div class="sikshya-notice-card__message">' . wp_kses_post($message) . '</div>';
            echo '</div>';
            echo '</div>';

            echo '<div class="sikshya-notice-card__actions">';
            if ($actions !== []) {
                $primary = $actions[0] ?? null;
                if (is_array($primary)) {
                    $label = isset($primary['label']) ? (string) $primary['label'] : '';
                    $url = isset($primary['url']) ? (string) $primary['url'] : '';
                    $target = isset($primary['target']) ? (string) $primary['target'] : '';
                    if ($label !== '' && $url !== '') {
                        $attrs = $target === '_blank'
                            ? ' target="_blank" rel="noopener noreferrer"'
                            : '';
                        echo '<a class="button button-primary sikshya-notice-card__cta" href="' . esc_url($url) . '"' . $attrs . '>' . esc_html($label) . '</a>';
                    }
                }
            }
            echo '</div>';

            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
    }

    public static function enqueueWordPressNoticeAssets(): void
    {
        if (!is_admin() || !self::currentUserCanSeeNotices()) {
            return;
        }

        if (self::getActiveNoticesForCurrentUser() === []) {
            return;
        }

        wp_enqueue_style(
            'sikshya-admin-marketing-notices',
            SIKSHYA_PLUGIN_URL . 'assets/admin/css/sikshya-admin-marketing-notices.css',
            [],
            SIKSHYA_VERSION
        );

        wp_enqueue_script(
            'sikshya-wp-admin-marketing-notices',
            SIKSHYA_PLUGIN_URL . 'assets/admin/js/sikshya-wp-admin-marketing-notices.js',
            ['jquery'],
            SIKSHYA_VERSION,
            true
        );

        wp_localize_script('sikshya-wp-admin-marketing-notices', 'sikshyaWpMarketingNotices', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sikshya_dismiss_marketing_notice'),
        ]);
    }

    public static function ajaxDismissNotice(): void
    {
        check_ajax_referer('sikshya_dismiss_marketing_notice', 'nonce');

        $noticeId = isset($_POST['notice_id']) ? sanitize_key((string) wp_unslash($_POST['notice_id'])) : '';
        $result = self::dismissForCurrentUser($noticeId);
        if ($result === true) {
            wp_send_json_success(['dismissed' => true]);
        }

        wp_send_json_error([
            'code' => $result->get_error_code(),
            'message' => $result->get_error_message(),
        ], (int) ($result->get_error_data()['status'] ?? 400));
    }

    /**
     * @param int         $order_id
     * @param object|null $order
     */
    public static function markFirstOrderFulfilled(int $order_id, $order): void
    {
        unset($order_id, $order);
        if (get_option(self::OPT_FIRST_ORDER_FULFILLED_AT)) {
            return;
        }

        update_option(self::OPT_FIRST_ORDER_FULFILLED_AT, current_time('timestamp'), false);
    }

    /**
     * @param string  $new_status
     * @param string  $old_status
     * @param \WP_Post $post
     */
    public static function maybeMarkFirstCoursePublished($new_status, $old_status, $post): void
    {
        unset($old_status);
        if (!$post instanceof \WP_Post || $post->post_type !== PostTypes::COURSE) {
            return;
        }

        if ($new_status !== 'publish') {
            return;
        }

        if (get_option(self::OPT_FIRST_COURSE_PUBLISHED_AT)) {
            return;
        }

        update_option(self::OPT_FIRST_COURSE_PUBLISHED_AT, current_time('timestamp'), false);
    }

    private static function currentUserCanSeeNotices(): bool
    {
        return current_user_can('manage_options') || current_user_can('manage_sikshya');
    }

    /**
     * Pro plugin present (activated), regardless of license validity.
     */
    private static function isProPluginPresent(): bool
    {
        if (defined('SIKSHYA_PRO_FILE') && SIKSHYA_PRO_FILE) {
            return true;
        }

        /**
         * Commercial extension present without the default constant (custom builds).
         *
         * @param bool $installed Default false.
         */
        return (bool) apply_filters('sikshya_commercial_extension_installed', false);
    }

    private static function shouldShowReviewNotice(int $userId): bool
    {
        $publishedAt = (int) get_option(self::OPT_FIRST_COURSE_PUBLISHED_AT, 0);
        if ($publishedAt <= 0) {
            $publishedCount = self::countPublishedCourses();
            if ($publishedCount > 0) {
                $publishedAt = current_time('timestamp');
                update_option(self::OPT_FIRST_COURSE_PUBLISHED_AT, $publishedAt, false);
            }
        }
        if ($publishedAt <= 0) {
            return false;
        }

        if ((bool) get_user_meta($userId, self::META_REVIEW_DISABLED, true)) {
            return false;
        }

        $nextShowAt = (int) get_user_meta($userId, self::META_REVIEW_NEXT_SHOW_AT, true);
        if ($nextShowAt > 0 && current_time('timestamp') < $nextShowAt) {
            return false;
        }

        return true;
    }

    private static function shouldShowBuyProNotice(int $userId): bool
    {
        if (self::isProPluginPresent()) {
            return false;
        }

        $firstOrderAt = (int) get_option(self::OPT_FIRST_ORDER_FULFILLED_AT, 0);
        if ($firstOrderAt <= 0) {
            if (self::getTotalOrdersCount() > 0) {
                $firstOrderAt = current_time('timestamp');
                update_option(self::OPT_FIRST_ORDER_FULFILLED_AT, $firstOrderAt, false);
            }
        }
        if ($firstOrderAt <= 0) {
            return false;
        }

        if ((bool) get_user_meta($userId, self::META_PRO_DISABLED, true)) {
            return false;
        }

        $stage = (int) get_user_meta($userId, self::META_PRO_STAGE, true);
        $stage = max(0, $stage);

        $orderCount = self::getTotalOrdersCount();

        if ($stage === 0 && $orderCount < 1) {
            return false;
        }
        if ($stage === 1 && $orderCount < 2) {
            return false;
        }
        if ($stage >= 2 && $orderCount < 6) {
            return false;
        }

        $nextShowAt = (int) get_user_meta($userId, self::META_PRO_NEXT_SHOW_AT, true);
        if ($nextShowAt > 0 && current_time('timestamp') < $nextShowAt) {
            return false;
        }

        return true;
    }

    private static function getTotalOrdersCount(): int
    {
        $repo = new OrderRepository();

        return $repo->countAll();
    }

    private static function countPublishedCourses(): int
    {
        $q = new \WP_Query(
            [
                'post_type' => PostTypes::COURSE,
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'no_found_rows' => false,
            ]
        );
        $n = (int) $q->found_posts;
        wp_reset_postdata();

        return $n;
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildReviewNotice(): array
    {
        return [
            'id' => self::NOTICE_REVIEW,
            'type' => 'info',
            'title' => __('How’s Sikshya LMS working for you?', 'sikshya'),
            'message' => __(
                'You’ve published your first course — congratulations. If Sikshya is helping your learners, a quick 5‑star review would mean a lot and helps other site owners choose with confidence.',
                'sikshya'
            ),
            'actions' => [
                [
                    'label' => __('Leave a 5‑star review', 'sikshya'),
                    'url' => 'https://wordpress.org/support/plugin/sikshya/reviews/?filter=5#new-post',
                    'target' => '_blank',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildBuyProNotice(): array
    {
        $orderCount = self::getTotalOrdersCount();
        $message = sprintf(
            /* translators: %d: order count */
            __(
                'You’re now processing orders in Sikshya. Sikshya Pro helps you scale with premium payment tools, advanced modules, and automation — built to reduce admin work and increase conversions. You currently have %d order(s) on record.',
                'sikshya'
            ),
            max(0, $orderCount)
        );

        return [
            'id' => self::NOTICE_BUY_PRO,
            'type' => 'warning',
            'title' => __('Upgrade to Sikshya Pro — save time on every order', 'sikshya'),
            'message' => $message,
            /** @used-by React admin shell (upgrade strip mirrors classic admin markup). */
            'order_count' => max(0, $orderCount),
            'actions' => [
                [
                    'label' => __('Upgrade to Pro', 'sikshya'),
                    'url' => self::defaultUpgradeUrl(),
                    'target' => '_blank',
                ],
            ],
        ];
    }

    private static function defaultUpgradeUrl(): string
    {
        $default = 'https://mantrabrain.com/plugins/sikshya/#pricing';
        $url = apply_filters('sikshya_commercial_upgrade_url', $default);

        return is_string($url) && $url !== '' ? esc_url_raw($url) : esc_url_raw($default);
    }
}
