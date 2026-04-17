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

$course_id = (int) $course->ID;
$type = isset($type) ? (string) $type : 'default';
$pricing = isset($pricing) && is_array($pricing) ? $pricing : ['price' => null, 'sale_price' => null, 'currency' => 'USD', 'on_sale' => false];

$price_num = $pricing['price'] ?? null;
$sale_num = $pricing['sale_price'] ?? null;
$on_sale = !empty($pricing['on_sale']);
$currency = (string) ($pricing['currency'] ?? 'USD');

$effective_price = $pricing['effective'] ?? null;
$is_paid_course = null !== $effective_price && (float) $effective_price > 0.00001;
$is_user_enrolled = function_exists('sikshya_is_user_enrolled_in_course') && sikshya_is_user_enrolled_in_course($course_id);
$course_permalink = get_permalink($course_id);
$course_permalink = is_string($course_permalink) ? $course_permalink : '';
$learn_entry_url = function_exists('sikshya_course_learn_entry_url') ? sikshya_course_learn_entry_url($course_id) : $course_permalink;

$course_price_ui = 'free';
if ($on_sale && null !== $price_num && null !== $sale_num) {
    $course_price_ui = 'sale';
} elseif (null !== $price_num && (float) $price_num > 0) {
    $course_price_ui = 'paid';
}

$card_label = sprintf(
    /* translators: %s: course title */
    __('Course: %s', 'sikshya'),
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

            <div class="sikshya-course-card-meta-actions<?php echo $has_counts ? '' : ' sikshya-course-card-meta-actions--action-only'; ?>">
                <div class="sikshya-course-card-actions">
                    <?php if ($is_user_enrolled) : ?>
                        <a class="sikshya-button sikshya-button--primary sikshya-button--small sikshya-course-card-action" href="<?php echo esc_url($learn_entry_url); ?>">
                            <span class="sikshya-button__icon" aria-hidden="true">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            </span>
                            <span class="sikshya-button__label"><?php esc_html_e('Continue learning', 'sikshya'); ?></span>
                        </a>
                    <?php elseif ($is_paid_course) : ?>
                        <form method="post" action="<?php echo esc_url($course_permalink); ?>" class="sikshya-course-card-action-form">
                            <?php wp_nonce_field('sikshya_cart', 'sikshya_cart_nonce'); ?>
                            <input type="hidden" name="sikshya_cart_action" value="add" />
                            <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $course_id); ?>" />
                            <input type="hidden" name="sikshya_redirect_to_checkout" value="1" />
                            <button type="submit" class="sikshya-button sikshya-button--primary sikshya-button--small sikshya-course-card-action">
                                <span class="sikshya-button__icon" aria-hidden="true">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                                </span>
                                <span class="sikshya-button__label"><?php esc_html_e('Buy now', 'sikshya'); ?></span>
                            </button>
                        </form>
                    <?php elseif (is_user_logged_in()) : ?>
                        <form method="post" action="<?php echo esc_url($course_permalink); ?>" class="sikshya-course-card-action-form">
                            <?php wp_nonce_field('sikshya_cart', 'sikshya_cart_nonce'); ?>
                            <input type="hidden" name="sikshya_cart_action" value="enroll_free" />
                            <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $course_id); ?>" />
                            <button type="submit" class="sikshya-button sikshya-button--primary sikshya-button--small sikshya-course-card-action">
                                <span class="sikshya-button__icon" aria-hidden="true">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                                </span>
                                <span class="sikshya-button__label"><?php esc_html_e('Enroll now', 'sikshya'); ?></span>
                            </button>
                        </form>
                    <?php else : ?>
                        <a class="sikshya-button sikshya-button--primary sikshya-button--small sikshya-course-card-action" href="<?php echo esc_url(wp_login_url($course_permalink)); ?>">
                            <span class="sikshya-button__icon" aria-hidden="true">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                            </span>
                            <span class="sikshya-button__label"><?php esc_html_e('Enroll now', 'sikshya'); ?></span>
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($has_counts) : ?>
                    <ul class="sikshya-course-curriculum-stats" aria-label="<?php echo esc_attr__('Course curriculum', 'sikshya'); ?>">
                        <?php if (!empty($curriculum_counts['lessons'])) : ?>
                            <?php
                            $n = (int) $curriculum_counts['lessons'];
                            $lessons_tip = sprintf(_n('%d Lesson', '%d Lessons', $n, 'sikshya'), $n);
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
                            $quizzes_tip = sprintf(_n('%d Quiz', '%d Quizzes', $n, 'sikshya'), $n);
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
                            $assign_tip = sprintf(_n('%d Assignment', '%d Assignments', $n, 'sikshya'), $n);
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

        <aside class="sikshya-course-card-aside" aria-label="<?php esc_attr_e('Course details', 'sikshya'); ?>">
            <ul class="sikshya-course-aside-list">
                <?php if (!empty($course_instructor)) : ?>
                    <li class="sikshya-aside-item">
                        <span class="sikshya-aside-item__icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                        </span>
                        <span class="sikshya-aside-item__text"><?php echo esc_html($course_instructor->display_name); ?></span>
                    </li>
                <?php endif; ?>

                <?php if (!empty($course_duration)) : ?>
                    <li class="sikshya-aside-item">
                        <span class="sikshya-aside-item__icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        </span>
                        <span class="sikshya-aside-item__text"><?php echo esc_html($course_duration); ?></span>
                    </li>
                <?php endif; ?>

                <li class="sikshya-aside-item sikshya-aside-item--schedule">
                    <span class="sikshya-aside-item__icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    </span>
                    <span class="sikshya-aside-item__text"><?php esc_html_e('Self-paced', 'sikshya'); ?></span>
                </li>

                <?php if (!empty($course_difficulty)) : ?>
                    <li class="sikshya-aside-item">
                        <span class="sikshya-aside-item__icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        </span>
                        <span class="sikshya-aside-item__text sikshya-aside-item__text--cap"><?php echo esc_html(ucfirst((string) $course_difficulty)); ?></span>
                    </li>
                <?php endif; ?>
            </ul>

            <div class="sikshya-course-aside-price sikshya-course-price sikshya-course-price--<?php echo esc_attr($course_price_ui); ?>">
                <?php if ($on_sale && null !== $price_num && null !== $sale_num) : ?>
                    <span class="sikshya-course-price-original" aria-hidden="true"><?php echo wp_kses_post(sikshya_format_price($price_num, $currency)); ?></span>
                    <span class="sikshya-course-price-current"><?php echo wp_kses_post(sikshya_format_price($sale_num, $currency)); ?></span>
                <?php elseif (null !== $price_num && (float) $price_num > 0) : ?>
                    <span class="sikshya-course-price-current"><?php echo wp_kses_post(sikshya_format_price((float) $price_num, $currency)); ?></span>
                <?php else : ?>
                    <span class="sikshya-course-price-free"><?php esc_html_e('Free', 'sikshya'); ?></span>
                <?php endif; ?>
            </div>

            <a href="<?php echo esc_url(get_permalink($course_id)); ?>" class="sikshya-button sikshya-button--outline sikshya-button--small sikshya-course-aside-cta">
                <span class="sikshya-button__icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </span>
                <span class="sikshya-button__label"><?php esc_html_e('View course', 'sikshya'); ?></span>
            </a>
        </aside>

        <div class="sikshya-course-card-footer">
            <div class="sikshya-course-price sikshya-course-price--<?php echo esc_attr($course_price_ui); ?>">
                <?php if ($on_sale && null !== $price_num && null !== $sale_num) : ?>
                    <span class="sikshya-course-price-original" aria-hidden="true"><?php echo wp_kses_post(sikshya_format_price($price_num, $currency)); ?></span>
                    <span class="sikshya-course-price-current"><?php echo wp_kses_post(sikshya_format_price($sale_num, $currency)); ?></span>
                <?php elseif (null !== $price_num && (float) $price_num > 0) : ?>
                    <span class="sikshya-course-price-current"><?php echo wp_kses_post(sikshya_format_price((float) $price_num, $currency)); ?></span>
                <?php else : ?>
                    <span class="sikshya-course-price-free"><?php esc_html_e('Free', 'sikshya'); ?></span>
                <?php endif; ?>
            </div>

            <a href="<?php echo esc_url(get_permalink($course_id)); ?>" class="sikshya-button sikshya-button--outline sikshya-button--small">
                <span class="sikshya-button__icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </span>
                <span class="sikshya-button__label"><?php esc_html_e('View course', 'sikshya'); ?></span>
            </a>
        </div>
    </div>
</article>
