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
            <?php if (!empty($course_categories) && !is_wp_error($course_categories)) : ?>
                <div class="sikshya-course-categories">
                    <?php foreach (array_slice($course_categories, 0, 2) as $category) : ?>
                        <span class="sikshya-course-category"><?php echo esc_html($category->name); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h3 class="sikshya-course-title">
                <a href="<?php echo esc_url(get_permalink($course_id)); ?>">
                    <?php echo esc_html($course->post_title); ?>
                </a>
            </h3>

            <p class="sikshya-course-excerpt">
                <?php echo esc_html(wp_trim_words($course->post_excerpt ?: $course->post_content, 18)); ?>
            </p>

            <?php if (!empty($curriculum_counts) && is_array($curriculum_counts) && !empty($curriculum_counts['total'])) : ?>
                <div class="sikshya-course-counts" aria-label="<?php echo esc_attr__('Course curriculum counts', 'sikshya'); ?>">
                    <?php if (!empty($curriculum_counts['lessons'])) : ?>
                        <span class="sikshya-course-counts__pill"><?php echo esc_html(sprintf(_n('%d lesson', '%d lessons', (int) $curriculum_counts['lessons'], 'sikshya'), (int) $curriculum_counts['lessons'])); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($curriculum_counts['quizzes'])) : ?>
                        <span class="sikshya-course-counts__pill"><?php echo esc_html(sprintf(_n('%d quiz', '%d quizzes', (int) $curriculum_counts['quizzes'], 'sikshya'), (int) $curriculum_counts['quizzes'])); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($curriculum_counts['assignments'])) : ?>
                        <span class="sikshya-course-counts__pill"><?php echo esc_html(sprintf(_n('%d assignment', '%d assignments', (int) $curriculum_counts['assignments'], 'sikshya'), (int) $curriculum_counts['assignments'])); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
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

            <div class="sikshya-course-aside-price">
                <?php if ($on_sale && null !== $price_num && null !== $sale_num) : ?>
                    <span class="sikshya-course-price-original" aria-hidden="true"><?php echo wp_kses_post(sikshya_format_price($price_num, $currency)); ?></span>
                    <span class="sikshya-course-price-current"><?php echo wp_kses_post(sikshya_format_price($sale_num, $currency)); ?></span>
                <?php elseif (null !== $price_num && (float) $price_num > 0) : ?>
                    <span class="sikshya-course-price-current"><?php echo wp_kses_post(sikshya_format_price((float) $price_num, $currency)); ?></span>
                <?php else : ?>
                    <span class="sikshya-course-price-free"><?php esc_html_e('Free', 'sikshya'); ?></span>
                <?php endif; ?>
            </div>

            <a href="<?php echo esc_url(get_permalink($course_id)); ?>" class="sikshya-button sikshya-button--small sikshya-course-aside-cta">
                <?php esc_html_e('View course', 'sikshya'); ?>
            </a>
        </aside>

        <div class="sikshya-course-card-footer">
            <div class="sikshya-course-price">
                <?php if ($on_sale && null !== $price_num && null !== $sale_num) : ?>
                    <span class="sikshya-course-price-original" aria-hidden="true"><?php echo wp_kses_post(sikshya_format_price($price_num, $currency)); ?></span>
                    <span class="sikshya-course-price-current"><?php echo wp_kses_post(sikshya_format_price($sale_num, $currency)); ?></span>
                <?php elseif (null !== $price_num && (float) $price_num > 0) : ?>
                    <span class="sikshya-course-price-current"><?php echo wp_kses_post(sikshya_format_price((float) $price_num, $currency)); ?></span>
                <?php else : ?>
                    <span class="sikshya-course-price-free"><?php esc_html_e('Free', 'sikshya'); ?></span>
                <?php endif; ?>
            </div>

            <a href="<?php echo esc_url(get_permalink($course_id)); ?>" class="sikshya-button sikshya-button--small">
                <?php esc_html_e('View course', 'sikshya'); ?>
            </a>
        </div>
    </div>
</article>
