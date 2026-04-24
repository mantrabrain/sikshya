<?php
/**
 * Reviews section for the single-course template.
 *
 * @var array<string, mixed> $vm
 * @var array<string, mixed> $reviews_vm
 * @var int $course_id
 */

if (!defined('ABSPATH') || empty($reviews_vm['enabled'])) {
    return;
}

$ratings_enabled = !empty($reviews_vm['ratings_enabled']);
$reviews_enabled = !empty($reviews_vm['reviews_enabled']);
$aggregate = is_array($reviews_vm['aggregate'] ?? null) ? $reviews_vm['aggregate'] : ['count' => 0, 'average' => 0, 'breakdown' => []];
$items = is_array($reviews_vm['items'] ?? null) ? $reviews_vm['items'] : [];
$user_review = is_array($reviews_vm['user_review'] ?? null) ? $reviews_vm['user_review'] : null;
$can_review = !empty($reviews_vm['can_review']);
$cannot_reason = (string) ($reviews_vm['cannot_review_reason'] ?? '');
$is_logged_in = !empty($reviews_vm['is_logged_in']);
$login_url = (string) ($reviews_vm['login_url'] ?? wp_login_url());
$avg = (float) ($aggregate['average'] ?? 0);
$count = (int) ($aggregate['count'] ?? 0);
$breakdown = is_array($aggregate['breakdown'] ?? null) ? $aggregate['breakdown'] : [];
$approval_mode = (string) ($reviews_vm['approval_mode'] ?? 'auto');

$data_attrs = sprintf(
    'data-course-id="%d" data-rest-url="%s" data-nonce="%s" data-ratings-enabled="%s" data-reviews-enabled="%s" data-approval-mode="%s"',
    (int) $course_id,
    esc_attr((string) ($vm['rest']['url'] ?? '')),
    esc_attr((string) ($vm['rest']['nonce'] ?? '')),
    $ratings_enabled ? '1' : '0',
    $reviews_enabled ? '1' : '0',
    esc_attr($approval_mode)
);
?>
<section
    id="sikshya-reviews"
    class="sikshya-course-lp__panel sikshya-course-reviews"
    aria-labelledby="sikshya-reviews-heading"
    <?php echo $data_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped above ?>
>
    <div class="sikshya-course-lp__section-head">
        <h2 id="sikshya-reviews-heading" class="sikshya-course-lp__heading">
            <?php esc_html_e('Student reviews', 'sikshya'); ?>
        </h2>
    </div>

    <?php if ($count > 0) : ?>
        <div class="sikshya-course-reviews__summary">
            <div class="sikshya-course-reviews__avg" role="img" aria-label="<?php echo esc_attr(sprintf(__('%s out of 5 stars', 'sikshya'), number_format_i18n($avg, 1))); ?>">
                <span class="sikshya-course-reviews__avg-number"><?php echo esc_html(number_format_i18n($avg, 1)); ?></span>
                <span class="sikshya-rating-stars" aria-hidden="true">
                    <?php
                    $full = (int) floor($avg);
                    $half = ($avg - $full) >= 0.5;
                    for ($i = 1; $i <= 5; $i++) {
                        if ($i <= $full) {
                            echo '<span class="sikshya-rating-star sikshya-rating-star--full">★</span>';
                        } elseif ($i === $full + 1 && $half) {
                            echo '<span class="sikshya-rating-star sikshya-rating-star--half">★</span>';
                        } else {
                            echo '<span class="sikshya-rating-star">☆</span>';
                        }
                    }
                    ?>
                </span>
                <span class="sikshya-course-reviews__avg-count">
                    <?php echo esc_html(sprintf(_n('%s rating', '%s ratings', $count, 'sikshya'), number_format_i18n($count))); ?>
                </span>
            </div>

            <ul class="sikshya-course-reviews__breakdown" aria-label="<?php esc_attr_e('Ratings breakdown', 'sikshya'); ?>">
                <?php for ($star = 5; $star >= 1; $star--) :
                    $n = (int) ($breakdown[$star] ?? 0);
                    $pct = $count > 0 ? (int) round(($n / $count) * 100) : 0;
                    ?>
                    <li class="sikshya-course-reviews__breakdown-row">
                        <span class="sikshya-course-reviews__breakdown-label"><?php echo esc_html($star); ?>★</span>
                        <span class="sikshya-course-reviews__breakdown-track">
                            <span class="sikshya-course-reviews__breakdown-fill" style="width: <?php echo esc_attr((string) $pct); ?>%"></span>
                        </span>
                        <span class="sikshya-course-reviews__breakdown-count"><?php echo esc_html(number_format_i18n($n)); ?></span>
                    </li>
                <?php endfor; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="sikshya-course-reviews__form-wrap" data-sikshya-review-form>
        <?php if (!$is_logged_in) : ?>
            <div class="sikshya-course-reviews__notice sikshya-course-reviews__notice--muted">
                <a href="<?php echo esc_url($login_url); ?>" class="sikshya-btn sikshya-btn--primary">
                    <?php esc_html_e('Sign in to leave a review', 'sikshya'); ?>
                </a>
            </div>
        <?php elseif ($user_review) : ?>
            <div class="sikshya-course-reviews__own" data-sikshya-own-review data-review-id="<?php echo esc_attr((string) $user_review['id']); ?>">
                <div class="sikshya-course-reviews__own-head">
                    <span class="sikshya-course-reviews__own-title"><?php esc_html_e('Your review', 'sikshya'); ?></span>
                    <?php if (!empty($user_review['pending_review'])) : ?>
                        <span class="sikshya-chip sikshya-chip--warning">
                            <?php esc_html_e('Pending approval', 'sikshya'); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?php if ($ratings_enabled && (int) $user_review['rating'] > 0) : ?>
                    <span class="sikshya-rating-stars" aria-hidden="true">
                        <?php
                        $r = (int) $user_review['rating'];
                        for ($i = 1; $i <= 5; $i++) {
                            echo $i <= $r
                                ? '<span class="sikshya-rating-star sikshya-rating-star--full">★</span>'
                                : '<span class="sikshya-rating-star">☆</span>';
                        }
                        ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($user_review['review_text'])) : ?>
                    <div class="sikshya-course-reviews__own-body sikshya-prose">
                        <?php echo wp_kses_post(wpautop((string) $user_review['review_text'])); ?>
                    </div>
                <?php endif; ?>
                <div class="sikshya-course-reviews__own-actions">
                    <button type="button" class="sikshya-btn sikshya-btn--ghost" data-sikshya-review-edit>
                        <?php esc_html_e('Edit', 'sikshya'); ?>
                    </button>
                    <button type="button" class="sikshya-btn sikshya-btn--ghost sikshya-btn--danger" data-sikshya-review-delete>
                        <?php esc_html_e('Delete', 'sikshya'); ?>
                    </button>
                </div>
            </div>
        <?php elseif (!$can_review) : ?>
            <div class="sikshya-course-reviews__notice sikshya-course-reviews__notice--muted">
                <?php echo esc_html($cannot_reason !== '' ? $cannot_reason : __('Only enrolled students can leave a review.', 'sikshya')); ?>
            </div>
        <?php endif; ?>

        <form
            class="sikshya-course-reviews__form <?php echo (!$can_review || $user_review) ? 'is-hidden' : ''; ?>"
            data-sikshya-review-form-el
            novalidate
        >
            <h3 class="sikshya-course-reviews__form-title">
                <?php esc_html_e('Share your experience', 'sikshya'); ?>
            </h3>

            <?php if ($ratings_enabled) : ?>
                <fieldset class="sikshya-course-reviews__rating-field">
                    <legend class="sikshya-course-reviews__field-label">
                        <?php esc_html_e('Your rating', 'sikshya'); ?>
                        <span class="sikshya-required" aria-hidden="true">*</span>
                    </legend>
                    <div class="sikshya-rating-input" data-sikshya-rating-input role="radiogroup" aria-label="<?php esc_attr_e('Rating', 'sikshya'); ?>">
                        <?php for ($i = 1; $i <= 5; $i++) : ?>
                            <button type="button"
                                class="sikshya-rating-input__star"
                                data-value="<?php echo esc_attr((string) $i); ?>"
                                role="radio"
                                aria-checked="false"
                                aria-label="<?php echo esc_attr(sprintf(_n('%s star', '%s stars', $i, 'sikshya'), $i)); ?>"
                            >☆</button>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="rating" value="0" data-sikshya-rating-value>
                </fieldset>
            <?php endif; ?>

            <?php if ($reviews_enabled) : ?>
                <label class="sikshya-course-reviews__field">
                    <span class="sikshya-course-reviews__field-label">
                        <?php esc_html_e('Your review', 'sikshya'); ?>
                    </span>
                    <textarea
                        name="review_text"
                        rows="4"
                        maxlength="5000"
                        class="sikshya-input sikshya-textarea"
                        placeholder="<?php esc_attr_e('What did you like or dislike? Keep it helpful for future students.', 'sikshya'); ?>"
                    ></textarea>
                </label>
            <?php endif; ?>

            <div class="sikshya-course-reviews__form-actions">
                <?php if ($approval_mode === 'manual') : ?>
                    <p class="sikshya-course-reviews__form-hint">
                        <?php esc_html_e('Your review will be published after a quick moderation check.', 'sikshya'); ?>
                    </p>
                <?php endif; ?>
                <button type="submit" class="sikshya-btn sikshya-btn--primary" data-sikshya-review-submit>
                    <?php esc_html_e('Submit review', 'sikshya'); ?>
                </button>
                <button type="button" class="sikshya-btn sikshya-btn--ghost is-hidden" data-sikshya-review-cancel>
                    <?php esc_html_e('Cancel', 'sikshya'); ?>
                </button>
            </div>

            <div class="sikshya-course-reviews__status" data-sikshya-review-status role="status" aria-live="polite"></div>
        </form>
    </div>

    <?php if ($count > 0) : ?>
        <ol class="sikshya-course-reviews__list" data-sikshya-review-list>
            <?php foreach ($items as $item) : ?>
                <li class="sikshya-course-reviews__item" data-review-id="<?php echo esc_attr((string) $item['id']); ?>">
                    <div class="sikshya-course-reviews__item-head">
                        <?php if (!empty($item['author_avatar'])) : ?>
                            <img src="<?php echo esc_url((string) $item['author_avatar']); ?>" alt="" class="sikshya-course-reviews__avatar" loading="lazy" />
                        <?php endif; ?>
                        <div class="sikshya-course-reviews__item-meta">
                            <span class="sikshya-course-reviews__author"><?php echo esc_html((string) ($item['author_name'] ?: __('Anonymous', 'sikshya'))); ?></span>
                            <span class="sikshya-course-reviews__time"><?php echo esc_html((string) ($item['created_at_label'] ?? '')); ?></span>
                        </div>
                        <?php if ($ratings_enabled && (int) $item['rating'] > 0) : ?>
                            <span class="sikshya-rating-stars sikshya-course-reviews__item-rating" aria-hidden="true">
                                <?php
                                $r = (int) $item['rating'];
                                for ($i = 1; $i <= 5; $i++) {
                                    echo $i <= $r
                                        ? '<span class="sikshya-rating-star sikshya-rating-star--full">★</span>'
                                        : '<span class="sikshya-rating-star">☆</span>';
                                }
                                ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($item['review_text'])) : ?>
                        <div class="sikshya-course-reviews__item-body sikshya-prose">
                            <?php echo wp_kses_post(wpautop((string) $item['review_text'])); ?>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>

        <?php
        $total_pages = (int) ($reviews_vm['total_pages'] ?? 0);
        if ($total_pages > 1) :
            ?>
            <div class="sikshya-course-reviews__load-more">
                <button type="button"
                    class="sikshya-btn sikshya-btn--ghost"
                    data-sikshya-review-load-more
                    data-next-page="2"
                    data-total-pages="<?php echo esc_attr((string) $total_pages); ?>"
                >
                    <?php esc_html_e('Load more reviews', 'sikshya'); ?>
                </button>
            </div>
        <?php endif; ?>
    <?php elseif (empty($user_review)) : ?>
        <div class="sikshya-course-reviews__empty">
            <?php esc_html_e('No reviews yet. Be the first to share your thoughts.', 'sikshya'); ?>
        </div>
    <?php endif; ?>
</section>
