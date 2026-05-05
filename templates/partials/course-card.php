<?php
/**
 * Reusable course card partial for listings (archive, shortcodes, blocks).
 *
 * Expected variables:
 * - $course (\WP_Post)
 * - $type (string) Visual variant: default, featured, popular
 * - $pricing (array) From sikshya_get_course_pricing()
 * - $course_duration (string)
 * - $course_difficulty (string)
 * - $course_instructor (\WP_User|null)
 * - $course_thumbnail (string)
 * - $course_categories (array|\WP_Error|false)
 * - $curriculum_counts (array{lessons:int,quizzes:int,assignments:int,total:int})
 *
 * Primary CTA in the card body uses $pricing (effective price), enrollment, and cart POST handlers.
 *
 * @package Sikshya
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!isset($course) || !$course instanceof \WP_Post) {
    return;
}

$course_id   = (int) $course->ID;
$type        = isset($type) ? (string) $type : 'default';
$pricing     = isset($pricing) && is_array($pricing) ? $pricing : ['price' => null, 'sale_price' => null, 'currency' => 'USD', 'on_sale' => false];
$is_bundle   = sanitize_key((string) get_post_meta($course_id, '_sikshya_course_type', true)) === 'bundle';
$bundle_ids  = [];
if ($is_bundle) {
    $raw_ids = get_post_meta($course_id, '_sikshya_bundle_course_ids', true);
    if (is_string($raw_ids) && $raw_ids !== '') {
        $dec = json_decode($raw_ids, true);
        $raw_ids = is_array($dec) ? $dec : [];
    }
    $bundle_ids = is_array($raw_ids) ? array_filter(array_map('intval', $raw_ids)) : [];
}

$price_num = $pricing['price'] ?? null;
$sale_num = $pricing['sale_price'] ?? null;
$on_sale = !empty($pricing['on_sale']);
$currency = (string) ($pricing['currency'] ?? 'USD');

$effective_price = $pricing['effective'] ?? null;
$is_paid_course = null !== $effective_price && (float) $effective_price > 0.00001;
$is_user_enrolled = function_exists('sikshya_is_user_enrolled_in_course') && sikshya_is_user_enrolled_in_course($course_id);
$is_user_completed = function_exists('sikshya_is_user_completed_course') && $is_user_enrolled && sikshya_is_user_completed_course($course_id);
$cert_url = ($is_user_completed && function_exists('sikshya_get_user_course_certificate_download_url'))
    ? (string) sikshya_get_user_course_certificate_download_url($course_id)
    : '';
$can_admin_enroll_without_purchase = $is_paid_course && !$is_user_enrolled && is_user_logged_in()
    && function_exists('sikshya_current_user_can_admin_enroll_without_purchase')
    && sikshya_current_user_can_admin_enroll_without_purchase();
$course_permalink = get_permalink($course_id);
$course_permalink = is_string($course_permalink) ? $course_permalink : '';
$learn_entry_url = function_exists('sikshya_course_learn_entry_url') ? sikshya_course_learn_entry_url($course_id) : $course_permalink;

$cta_labels = \Sikshya\Services\CourseFrontendSettings::enrollmentButtonLabels();

$label_course = function_exists('sikshya_label') ? sikshya_label('course', __('Course', 'sikshya'), 'frontend') : __('Course', 'sikshya');
$label_lesson = function_exists('sikshya_label') ? sikshya_label('lesson', __('Lesson', 'sikshya'), 'frontend') : __('Lesson', 'sikshya');
$label_lessons = function_exists('sikshya_label_plural') ? sikshya_label_plural('lesson', 'lessons', __('Lessons', 'sikshya'), 'frontend') : __('Lessons', 'sikshya');
$label_quiz = function_exists('sikshya_label') ? sikshya_label('quiz', __('Quiz', 'sikshya'), 'frontend') : __('Quiz', 'sikshya');
$label_quizzes = function_exists('sikshya_label_plural') ? sikshya_label_plural('quiz', 'quizzes', __('Quizzes', 'sikshya'), 'frontend') : __('Quizzes', 'sikshya');
$label_assignment = function_exists('sikshya_label') ? sikshya_label('assignment', __('Assignment', 'sikshya'), 'frontend') : __('Assignment', 'sikshya');
$label_assignments = function_exists('sikshya_label_plural') ? sikshya_label_plural('assignment', 'assignments', __('Assignments', 'sikshya'), 'frontend') : __('Assignments', 'sikshya');

$duration_display = (string) $course_duration;
if ($duration_display !== '') {
    // Make very compact durations read nicer on cards (e.g. "3m" -> "3 min", "2h" -> "2 hr").
    $d = trim($duration_display);
    if (preg_match('/^(\d+)\s*m$/i', $d, $m)) {
        $duration_display = sprintf(
            /* translators: %s: number of minutes */
            __('%s min', 'sikshya'),
            $m[1]
        );
    } elseif (preg_match('/^(\d+)\s*h$/i', $d, $m)) {
        $duration_display = sprintf(
            /* translators: %s: number of hours */
            __('%s hr', 'sikshya'),
            $m[1]
        );
    }
}

$course_price_ui = 'free';
if ($on_sale && null !== $price_num && null !== $sale_num) {
    $course_price_ui = 'sale';
} elseif ($is_paid_course) {
    $course_price_ui = 'paid';
}

$card_label = sprintf(
    /* translators: %s: course title */
    __('%1$s: %2$s', 'sikshya'),
    $label_course,
    $course->post_title
);
?>

<article class="sikshya-course-card sikshya-course-card--<?php echo esc_attr($type); ?>" aria-label="<?php echo esc_attr($card_label); ?>">
    <div class="sikshya-course-card-image">
        <?php if (!empty($course_thumbnail)) : ?>
            <a href="<?php echo esc_url(get_permalink($course_id)); ?>" class="sikshya-course-card-image-link" tabindex="-1" aria-hidden="true">
                <img src="<?php echo esc_url($course_thumbnail); ?>" alt="" class="sikshya-course-thumbnail" loading="lazy" decoding="async">
            </a>
        <?php else : ?>
            <div class="sikshya-course-placeholder" aria-hidden="true">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" focusable="false">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14,2 14,8 20,8"></polyline>
                </svg>
            </div>
        <?php endif; ?>

        <?php if ($on_sale) : ?>
            <div class="sikshya-course-badge sikshya-course-badge--sale">
                <?php esc_html_e('Sale', 'sikshya'); ?>
            </div>
        <?php endif; ?>

        <?php if (get_post_meta($course_id, '_sikshya_featured', true)) : ?>
            <div class="sikshya-course-badge sikshya-course-badge--featured">
                <?php esc_html_e('Featured', 'sikshya'); ?>
            </div>
        <?php endif; ?>

        <?php if ($is_bundle) : ?>
            <div class="sikshya-course-badge sikshya-course-badge--bundle">
                <?php esc_html_e('Bundle', 'sikshya'); ?>
            </div>
        <?php endif; ?>

        <?php
        /**
         * Extra badges over the course card thumbnail (bundle membership, subscription only, etc.).
         *
         * @param int $course_id
         */
        do_action('sikshya_course_card_badges', $course_id);
        ?>
    </div>

    <div class="sikshya-course-card-body">
        <div class="sikshya-course-card-primary">
            <?php
            $has_cats = !empty($course_categories) && !is_wp_error($course_categories);
            $has_counts = !empty($curriculum_counts) && is_array($curriculum_counts) && !empty($curriculum_counts['total']);
            ?>
            <?php if ($has_cats) : ?>
                <div class="sikshya-course-card-kicker">
                    <div class="sikshya-course-categories">
                        <?php foreach (array_slice($course_categories, 0, 2) as $category) : ?>
                            <?php
                            $category_term_link = get_term_link($category);
                            if (!is_wp_error($category_term_link)) :
                                ?>
                                <a class="sikshya-course-category" href="<?php echo esc_url($category_term_link); ?>"><?php echo esc_html($category->name); ?></a>
                            <?php else : ?>
                                <span class="sikshya-course-category"><?php echo esc_html($category->name); ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <h3 class="sikshya-course-title">
                <a href="<?php echo esc_url(get_permalink($course_id)); ?>">
                    <?php echo esc_html($course->post_title); ?>
                </a>
            </h3>

            <p class="sikshya-course-excerpt">
                <?php echo esc_html(wp_trim_words($course->post_excerpt ?: $course->post_content, 14)); ?>
            </p>

            <?php
            ob_start();
            /**
             * Extra meta inside the card body (multi-instructor count, marketplace vendor, etc.).
             *
             * @param int $course_id
             */
            do_action('sikshya_course_card_meta', $course_id);
            $extra_meta_html = trim((string) ob_get_clean());
            ?>

            <?php if (!empty($course_instructor) || $extra_meta_html !== '') : ?>
                <div class="sikshya-course-card-byline">
                    <?php if (!empty($course_instructor)) : ?>
                        <span class="sikshya-course-card__meta-item sikshya-course-card__meta-item--instructor">
                            <span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
                            <span>
                                <?php
                                echo esc_html(sprintf(
                                    /* translators: %s: instructor display name */
                                    __('Instructor: %s', 'sikshya'),
                                    $course_instructor->display_name
                                ));
                                ?>
                            </span>
                        </span>
                    <?php endif; ?>
                    <?php
                    // Printed by addons; assumes they escape their own output.
                    echo $extra_meta_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                </div>
            <?php endif; ?>

            <?php
            $rating_vm = apply_filters(
                'sikshya_course_card_rating_vm',
                [
                    'average' => 0.0,
                    'count' => 0,
                ],
                $course_id
            );
            $rating_avg = is_array($rating_vm) ? (float) ($rating_vm['average'] ?? 0) : 0.0;
            $rating_count = is_array($rating_vm) ? (int) ($rating_vm['count'] ?? 0) : 0;
            $rating_avg = max(0.0, min(5.0, $rating_avg));
            $rating_count = max(0, $rating_count);
            ?>
            <div class="sikshya-course-card-submeta" aria-label="<?php esc_attr_e('Course meta', 'sikshya'); ?>">
                <div class="sikshya-course-card-rating" role="img" aria-label="<?php echo esc_attr(sprintf(__('%s out of 5 stars', 'sikshya'), number_format_i18n($rating_avg, 1))); ?>">
                    <span class="sikshya-rating-stars" aria-hidden="true">
                        <?php
                        for ($i = 1; $i <= 5; $i++) {
                            if ($rating_avg >= $i) {
                                echo '<span class="sikshya-rating-star sikshya-rating-star--full">★</span>';
                            } elseif ($rating_avg >= ($i - 0.5)) {
                                echo '<span class="sikshya-rating-star sikshya-rating-star--half">★</span>';
                            } else {
                                echo '<span class="sikshya-rating-star">☆</span>';
                            }
                        }
                        ?>
                    </span>
                    <span class="sikshya-course-card-rating__count">
                        <?php
                        echo esc_html(sprintf(
                            /* translators: %s: rating count */
                            _n('(%s rating)', '(%s ratings)', $rating_count, 'sikshya'),
                            number_format_i18n($rating_count)
                        ));
                        ?>
                    </span>
                </div>
                <?php if (!empty($course_difficulty)) : ?>
                    <div class="sikshya-course-card-level">
                        <span class="sikshya-meta-chip sikshya-meta-chip--muted">
                            <?php echo esc_html(ucfirst((string) $course_difficulty)); ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($is_bundle && count($bundle_ids) > 0) : ?>
                <p class="sikshya-course-card-bundle-hint">
                    <?php
                    $n_bundle = count($bundle_ids);
                    echo esc_html(sprintf(
                        /* translators: %d: number of courses */
                        _n('Includes %d course', 'Includes %d courses', $n_bundle, 'sikshya'),
                        $n_bundle
                    ));
                    ?>
                </p>
            <?php endif; ?>

            <div class="sikshya-course-card-meta-actions<?php echo ($has_counts || $is_bundle) ? '' : ' sikshya-course-card-meta-actions--action-only'; ?>">
                <div class="sikshya-course-card-actions">
                    <?php if ($is_user_enrolled) : ?>
                        <a class="sikshya-btn sikshya-btn--primary sikshya-btn--sm sikshya-course-card-action" href="<?php echo esc_url($learn_entry_url); ?>">
                            <span class="sikshya-button__icon" aria-hidden="true">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            </span>
                            <span class="sikshya-button__label"><?php esc_html_e('Continue learning', 'sikshya'); ?></span>
                        </a>
                        <?php if ($cert_url !== '') : ?>
                            <a
                                class="sikshya-btn sikshya-btn--ghost sikshya-btn--sm sikshya-course-card-action sikshya-course-card-action--icon"
                                href="<?php echo esc_url($cert_url); ?>"
                                target="_blank"
                                rel="noopener"
                                aria-label="<?php echo esc_attr__('Download certificate', 'sikshya'); ?>"
                            >
                                <span class="sikshya-course-card-action__tooltip" aria-hidden="true"><?php esc_html_e('Download certificate', 'sikshya'); ?></span>
                                <span class="sikshya-button__icon" aria-hidden="true">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                        <polyline points="7 10 12 15 17 10"/>
                                        <line x1="12" y1="15" x2="12" y2="3"/>
                                    </svg>
                                </span>
                            </a>
                        <?php endif; ?>
                    <?php elseif ($is_paid_course) : ?>
                        <form method="post" action="<?php echo esc_url($course_permalink); ?>" class="sikshya-course-card-action-form">
                            <?php wp_nonce_field('sikshya_cart', 'sikshya_cart_nonce'); ?>
                            <input type="hidden" name="sikshya_cart_action" value="add" />
                            <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $course_id); ?>" />
                            <input type="hidden" name="sikshya_redirect_to_checkout" value="1" />
                            <button type="submit" class="sikshya-btn sikshya-btn--primary sikshya-btn--sm sikshya-course-card-action">
                                <span class="sikshya-button__icon" aria-hidden="true">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                                </span>
                                <span class="sikshya-button__label"><?php esc_html_e('Buy now', 'sikshya'); ?></span>
                            </button>
                        </form>
                        <?php if (!empty($can_admin_enroll_without_purchase)) : ?>
                            <form method="post" action="<?php echo esc_url($course_permalink); ?>" class="sikshya-course-card-action-form sikshya-course-card-action-form--admin-enroll">
                                <?php wp_nonce_field('sikshya_cart', 'sikshya_cart_nonce'); ?>
                                <input type="hidden" name="sikshya_cart_action" value="admin_enroll_bypass" />
                                <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $course_id); ?>" />
                                <button type="submit" class="sikshya-btn sikshya-btn--ghost sikshya-btn--sm sikshya-course-card-action">
                                    <span class="sikshya-button__label"><?php esc_html_e('Enroll without purchase', 'sikshya'); ?></span>
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php elseif (is_user_logged_in()) : ?>
                        <form method="post" action="<?php echo esc_url($course_permalink); ?>" class="sikshya-course-card-action-form">
                            <?php wp_nonce_field('sikshya_cart', 'sikshya_cart_nonce'); ?>
                            <input type="hidden" name="sikshya_cart_action" value="enroll_free" />
                            <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $course_id); ?>" />
                            <button type="submit" class="sikshya-btn sikshya-btn--primary sikshya-btn--sm sikshya-course-card-action">
                                <span class="sikshya-button__icon" aria-hidden="true">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                                </span>
                                <span class="sikshya-button__label"><?php echo esc_html($cta_labels['free']); ?></span>
                            </button>
                        </form>
                    <?php else : ?>
                        <a class="sikshya-btn sikshya-btn--primary sikshya-btn--sm sikshya-course-card-action" href="<?php echo esc_url(\Sikshya\Frontend\Site\PublicPageUrls::login($course_permalink)); ?>">
                            <span class="sikshya-button__icon" aria-hidden="true">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                            </span>
                            <span class="sikshya-button__label"><?php echo esc_html($cta_labels['enrollment']); ?></span>
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($has_counts) : ?>
                    <ul class="sikshya-course-curriculum-stats" aria-label="<?php echo esc_attr(sprintf(__('%s curriculum', 'sikshya'), $label_course)); ?>">
                        <?php if (!empty($curriculum_counts['lessons'])) : ?>
                            <?php
                            $n = (int) $curriculum_counts['lessons'];
                            $lessons_tip = sprintf(
                                _n('%1$d %2$s', '%1$d %3$s', $n, 'sikshya'),
                                $n,
                                $label_lesson,
                                $label_lessons
                            );
                            ?>
                            <li class="sikshya-course-curriculum-stats__item">
                                <span
                                    class="sikshya-course-curriculum-stats__badge sikshya-course-curriculum-stats__badge--icon-value"
                                    tabindex="0"
                                    aria-label="<?php echo esc_attr($lessons_tip); ?>"
                                >
                                    <span class="sikshya-course-curriculum-stats__tooltip" aria-hidden="true"><?php echo esc_html($lessons_tip); ?></span>
                                    <span class="sikshya-course-curriculum-stats__icon" aria-hidden="true">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" focusable="false"><path d="M2 3h6a4 4 0 0 1 4 4v14a4 4 0 0 1-4 4H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a4 4 0 0 0 4 4h6z"/></svg>
                                    </span>
                                    <span class="sikshya-course-curriculum-stats__value" aria-hidden="true"><?php echo esc_html((string) $n); ?></span>
                                </span>
                            </li>
                        <?php endif; ?>
                        <?php if (!empty($curriculum_counts['quizzes'])) : ?>
                            <?php
                            $n = (int) $curriculum_counts['quizzes'];
                            $quizzes_tip = sprintf(
                                _n('%1$d %2$s', '%1$d %3$s', $n, 'sikshya'),
                                $n,
                                $label_quiz,
                                $label_quizzes
                            );
                            ?>
                            <li class="sikshya-course-curriculum-stats__item">
                                <span
                                    class="sikshya-course-curriculum-stats__badge sikshya-course-curriculum-stats__badge--icon-value"
                                    tabindex="0"
                                    aria-label="<?php echo esc_attr($quizzes_tip); ?>"
                                >
                                    <span class="sikshya-course-curriculum-stats__tooltip" aria-hidden="true"><?php echo esc_html($quizzes_tip); ?></span>
                                    <span class="sikshya-course-curriculum-stats__icon" aria-hidden="true">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" focusable="false"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
                                    </span>
                                    <span class="sikshya-course-curriculum-stats__value" aria-hidden="true"><?php echo esc_html((string) $n); ?></span>
                                </span>
                            </li>
                        <?php endif; ?>
                        <?php if (!empty($curriculum_counts['assignments'])) : ?>
                            <?php
                            $n = (int) $curriculum_counts['assignments'];
                            $assign_tip = sprintf(
                                _n('%1$d %2$s', '%1$d %3$s', $n, 'sikshya'),
                                $n,
                                $label_assignment,
                                $label_assignments
                            );
                            ?>
                            <li class="sikshya-course-curriculum-stats__item">
                                <span
                                    class="sikshya-course-curriculum-stats__badge sikshya-course-curriculum-stats__badge--icon-value"
                                    tabindex="0"
                                    aria-label="<?php echo esc_attr($assign_tip); ?>"
                                >
                                    <span class="sikshya-course-curriculum-stats__tooltip" aria-hidden="true"><?php echo esc_html($assign_tip); ?></span>
                                    <span class="sikshya-course-curriculum-stats__icon" aria-hidden="true">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" focusable="false"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
                                    </span>
                                    <span class="sikshya-course-curriculum-stats__value" aria-hidden="true"><?php echo esc_html((string) $n); ?></span>
                                </span>
                            </li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <aside class="sikshya-course-card-aside" aria-label="<?php echo esc_attr(sprintf(__('%s details', 'sikshya'), $label_course)); ?>">
            <ul class="sikshya-course-aside-list">
                <li class="sikshya-aside-item sikshya-aside-item--no-leading-icon sikshya-course-aside-rating" aria-label="<?php esc_attr_e('Rating', 'sikshya'); ?>">
                    <span class="sikshya-aside-item__text">
                        <span class="sikshya-course-card-rating sikshya-course-card-rating--aside" role="img" aria-label="<?php echo esc_attr(sprintf(__('%s out of 5 stars', 'sikshya'), number_format_i18n($rating_avg, 1))); ?>">
                            <span class="sikshya-rating-stars" aria-hidden="true">
                                <?php
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($rating_avg >= $i) {
                                        echo '<span class="sikshya-rating-star sikshya-rating-star--full">★</span>';
                                    } elseif ($rating_avg >= ($i - 0.5)) {
                                        echo '<span class="sikshya-rating-star sikshya-rating-star--half">★</span>';
                                    } else {
                                        echo '<span class="sikshya-rating-star">☆</span>';
                                    }
                                }
                                ?>
                            </span>
                            <span class="sikshya-course-card-rating__count">
                                <?php
                                echo esc_html(sprintf(
                                    /* translators: %s: rating count */
                                    _n('(%s rating)', '(%s ratings)', $rating_count, 'sikshya'),
                                    number_format_i18n($rating_count)
                                ));
                                ?>
                            </span>
                        </span>
                    </span>
                </li>

                <?php if (!empty($course_difficulty)) : ?>
                    <li class="sikshya-aside-item sikshya-course-aside-level" aria-label="<?php esc_attr_e('Level', 'sikshya'); ?>">
                        <span class="sikshya-aside-item__icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M4 20h2v-8H4v8zm4 0h2V8H8v12zm4 0h2V4h-2v16zm4 0h2v-6h-2v6z"/></svg>
                        </span>
                        <span class="sikshya-aside-item__text sikshya-meta-chip sikshya-meta-chip--muted"><?php echo esc_html(ucfirst((string) $course_difficulty)); ?></span>
                    </li>
                <?php endif; ?>

                <?php if (!empty($course_duration)) : ?>
                    <li class="sikshya-aside-item">
                        <span class="sikshya-aside-item__icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        </span>
                        <span class="sikshya-aside-item__text sikshya-meta-chip"><?php echo esc_html($duration_display); ?></span>
                    </li>
                <?php endif; ?>

                <li class="sikshya-aside-item sikshya-aside-item--schedule">
                    <span class="sikshya-aside-item__icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    </span>
                    <span class="sikshya-aside-item__text"><?php esc_html_e('Self-paced', 'sikshya'); ?></span>
                </li>

            </ul>

            <div class="sikshya-course-aside-price sikshya-course-price sikshya-course-price--<?php echo esc_attr($course_price_ui); ?>">
                <?php if ($on_sale && null !== $price_num && null !== $sale_num) : ?>
                    <span class="sikshya-course-price-original" aria-hidden="true"><?php echo wp_kses_post(sikshya_format_price($price_num, $currency)); ?></span>
                    <span class="sikshya-course-price-current"><?php echo wp_kses_post(sikshya_format_price($sale_num, $currency)); ?></span>
                <?php elseif ($is_paid_course && null !== $effective_price) : ?>
                    <span class="sikshya-course-price-current"><?php echo wp_kses_post(sikshya_format_price((float) $effective_price, $currency)); ?></span>
                <?php else : ?>
                    <span class="sikshya-course-price-free"><?php esc_html_e('Free', 'sikshya'); ?></span>
                <?php endif; ?>
            </div>

            <a href="<?php echo esc_url(get_permalink($course_id)); ?>" class="sikshya-button sikshya-button--outline sikshya-button--small sikshya-course-aside-cta">
                <span class="sikshya-button__icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </span>
                <span class="sikshya-button__label"><?php echo esc_html(sprintf(__('View %s', 'sikshya'), strtolower($label_course))); ?></span>
            </a>
        </aside>

        <div class="sikshya-course-card-footer">
            <div class="sikshya-course-price  sikshya-course-price--<?php echo esc_attr($course_price_ui); ?>">
                <?php if ($on_sale && null !== $price_num && null !== $sale_num) : ?>
                    <span class="sikshya-course-price-original" aria-hidden="true"><?php echo wp_kses_post(sikshya_format_price($price_num, $currency)); ?></span>
                    <span class="sikshya-course-price-current"><?php echo wp_kses_post(sikshya_format_price($sale_num, $currency)); ?></span>
                <?php elseif ($is_paid_course && null !== $effective_price) : ?>
                    <span class="sikshya-course-price-current"><?php echo wp_kses_post(sikshya_format_price((float) $effective_price, $currency)); ?></span>
                <?php else : ?>
                    <span class="sikshya-course-price-free"><?php esc_html_e('Free', 'sikshya'); ?></span>
                <?php endif; ?>
            </div>

            <a href="<?php echo esc_url(get_permalink($course_id)); ?>" class="sikshya-button sikshya-button--outline sikshya-button--small">
                <span class="sikshya-button__icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </span>
                <span class="sikshya-button__label"><?php echo esc_html(sprintf(__('View %s', 'sikshya'), strtolower($label_course))); ?></span>
            </a>
        </div>
    </div>
</article>
