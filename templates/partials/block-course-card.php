<?php
/**
 * Course card for the sikshya/course-grid block.
 *
 * Theme-overridable: copy to `yourtheme/sikshya/partials/block-course-card.php`.
 * Data is prepared in {@see \Sikshya\Blocks\CourseGrid\CourseCardData}; this
 * file is markup only, so an override keeps every lookup and cache the block
 * already did.
 *
 * Expected variables:
 * - $course    (\WP_Post)              The course.
 * - $settings  (array<string, mixed>)   Normalised block attributes.
 * - $card_data (array<string, mixed>)   View model.
 *
 * Classes are scoped under `.sikshya-cg-card`, separate from the archive's
 * `.sikshya-course-card`, so restyling one listing never moves the other.
 *
 * @package Sikshya
 * @since 1.0.21
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!isset($course) || !$course instanceof \WP_Post) {
    return;
}

$settings = isset($settings) && is_array($settings) ? $settings : [];
$d = isset($card_data) && is_array($card_data) ? $card_data : [];

$course_id = (int) $course->ID;
$permalink = (string) ($d['permalink'] ?? '');
$title = (string) ($d['title'] ?? '');
if ($title === '') {
    $title = __('(no title)', 'sikshya');
}

$pricing = is_array($d['pricing'] ?? null) ? $d['pricing'] : null;
$effective = $pricing['effective'] ?? null;
$is_paid = null !== $effective && (float) $effective > 0.00001;
$on_sale = !empty($pricing['on_sale']);
$currency = (string) ($pricing['currency'] ?? '');

$is_enrolled = !empty($d['is_enrolled']);
$is_completed = !empty($d['is_completed']);
$progress = $d['progress'] ?? null;

$cta_url = $is_enrolled ? (string) ($d['learn_url'] ?? $permalink) : $permalink;
$cta_label = (string) ($settings['buttonLabel'] ?? '');
if ($cta_label === '') {
    if ($is_completed) {
        $cta_label = __('Review course', 'sikshya');
    } elseif ($is_enrolled) {
        $cta_label = __('Continue learning', 'sikshya');
    } elseif ($is_paid) {
        $cta_label = __('View course', 'sikshya');
    } else {
        $cta_label = __('Start free', 'sikshya');
    }
}

$card_classes = ['sikshya-cg-card'];
if ($is_enrolled) {
    $card_classes[] = 'is-enrolled';
}
if ($is_completed) {
    $card_classes[] = 'is-completed';
}
if ($is_paid) {
    $card_classes[] = 'is-paid';
} else {
    $card_classes[] = 'is-free';
}

$thumbnail_id = (int) ($d['thumbnail_id'] ?? 0);
$categories = is_array($d['categories'] ?? null) ? $d['categories'] : [];
$instructor = ($d['instructor'] ?? null) instanceof \WP_User ? $d['instructor'] : null;
$rating = is_array($d['rating'] ?? null) ? $d['rating'] : ['average' => 0.0, 'count' => 0];
$counts = is_array($d['counts'] ?? null) ? $d['counts'] : [];
$difficulty = (string) ($d['difficulty'] ?? '');
$duration = (string) ($d['duration'] ?? '');

/*
 * The whole card is one link target for pointer users, but only the title
 * carries the accessible link: a second link to the same place with the same
 * name is a duplicate for screen-reader users. The media and overlay links are
 * marked aria-hidden and taken out of the tab order.
 */
?>
<article class="<?php echo esc_attr(implode(' ', $card_classes)); ?>" data-course-id="<?php echo esc_attr((string) $course_id); ?>">

	<?php if (!empty($settings['showThumbnail'])) : ?>
		<div class="sikshya-cg-card__media">
			<a
				href="<?php echo esc_url($permalink); ?>"
				class="sikshya-cg-card__media-link"
				tabindex="-1"
				aria-hidden="true"
			>
				<?php
				if ($thumbnail_id > 0) {
					echo wp_get_attachment_image(
						$thumbnail_id,
						'medium_large',
						false,
						[
							'class' => 'sikshya-cg-card__image',
							'loading' => 'lazy',
							'decoding' => 'async',
							// Decorative here: the title link next to it names the course.
							'alt' => '',
						]
					);
				} else {
					echo '<span class="sikshya-cg-card__image sikshya-cg-card__image--placeholder" aria-hidden="true"></span>';
				}
				?>
			</a>

			<?php if ($on_sale || ($difficulty !== '' && !empty($settings['showDifficulty']))) : ?>
				<div class="sikshya-cg-card__badges">
					<?php if ($on_sale) : ?>
						<span class="sikshya-cg-card__badge sikshya-cg-card__badge--sale"><?php esc_html_e('Sale', 'sikshya'); ?></span>
					<?php endif; ?>
					<?php if ($difficulty !== '' && !empty($settings['showDifficulty'])) : ?>
						<span class="sikshya-cg-card__badge sikshya-cg-card__badge--level">
							<?php echo esc_html(ucfirst($difficulty)); ?>
						</span>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ($is_enrolled && null !== $progress && !empty($settings['showProgress'])) : ?>
				<div
					class="sikshya-cg-card__progress"
					role="progressbar"
					aria-valuenow="<?php echo esc_attr((string) (int) $progress); ?>"
					aria-valuemin="0"
					aria-valuemax="100"
					aria-label="<?php echo esc_attr(sprintf(
						/* translators: %d: completion percentage */
						__('%d%% complete', 'sikshya'),
						(int) $progress
					)); ?>"
				>
					<span class="sikshya-cg-card__progress-bar" style="width:<?php echo esc_attr((string) (int) $progress); ?>%"></span>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="sikshya-cg-card__body">

		<?php if ($categories !== [] && !empty($settings['showCategory'])) : ?>
			<p class="sikshya-cg-card__terms">
				<?php foreach ($categories as $term) : ?>
					<?php if (!$term instanceof \WP_Term) {
						continue;
					} ?>
					<?php $term_link = get_term_link($term); ?>
					<a
						class="sikshya-cg-card__term"
						href="<?php echo esc_url(is_string($term_link) ? $term_link : ''); ?>"
					><?php echo esc_html($term->name); ?></a>
				<?php endforeach; ?>
			</p>
		<?php endif; ?>

		<h3 class="sikshya-cg-card__title">
			<a class="sikshya-cg-card__title-link" href="<?php echo esc_url($permalink); ?>">
				<?php echo esc_html($title); ?>
			</a>
		</h3>

		<?php if ($instructor instanceof \WP_User && !empty($settings['showInstructor'])) : ?>
			<p class="sikshya-cg-card__instructor">
				<?php echo esc_html($instructor->display_name); ?>
			</p>
		<?php endif; ?>

		<?php if (!empty($settings['showRating']) && (int) $rating['count'] > 0) : ?>
			<?php
			$avg = (float) $rating['average'];
			$stars = '';
			for ($i = 1; $i <= 5; $i++) {
				$stars .= $avg >= $i - 0.25 ? '★' : '☆';
			}
			?>
			<p
				class="sikshya-cg-card__rating"
				aria-label="<?php echo esc_attr(sprintf(
					/* translators: 1: average rating, 2: number of ratings */
					_n(
						'Rated %1$s out of 5 from %2$s rating',
						'Rated %1$s out of 5 from %2$s ratings',
						(int) $rating['count'],
						'sikshya'
					),
					number_format_i18n($avg, 1),
					number_format_i18n((int) $rating['count'])
				)); ?>"
			>
				<span class="sikshya-cg-card__stars" aria-hidden="true"><?php echo esc_html($stars); ?></span>
				<span class="sikshya-cg-card__rating-value" aria-hidden="true"><?php echo esc_html(number_format_i18n($avg, 1)); ?></span>
				<span class="sikshya-cg-card__rating-count" aria-hidden="true">(<?php echo esc_html(number_format_i18n((int) $rating['count'])); ?>)</span>
			</p>
		<?php endif; ?>

		<?php if (!empty($settings['showExcerpt']) && (string) ($d['excerpt'] ?? '') !== '') : ?>
			<p class="sikshya-cg-card__excerpt"><?php echo esc_html((string) $d['excerpt']); ?></p>
		<?php endif; ?>

		<?php
		if (!empty($settings['showMeta'])) {
			$meta_items = [];
			$lessons = (int) ($counts['lessons'] ?? 0);
			if ($lessons > 0) {
				$meta_items[] = sprintf(
					/* translators: %s: number of lessons */
					_n('%s lesson', '%s lessons', $lessons, 'sikshya'),
					number_format_i18n($lessons)
				);
			}
			$quizzes = (int) ($counts['quizzes'] ?? 0);
			if ($quizzes > 0) {
				$meta_items[] = sprintf(
					/* translators: %s: number of quizzes */
					_n('%s quiz', '%s quizzes', $quizzes, 'sikshya'),
					number_format_i18n($quizzes)
				);
			}
			if ($duration !== '') {
				$meta_items[] = $duration;
			}

			if ($meta_items !== []) {
				echo '<ul class="sikshya-cg-card__meta" role="list">';
				foreach ($meta_items as $item) {
					echo '<li class="sikshya-cg-card__meta-item">' . esc_html($item) . '</li>';
				}
				echo '</ul>';
			}
		}
		?>
	</div>

	<?php if (!empty($settings['showPrice']) || !empty($settings['showButton'])) : ?>
		<footer class="sikshya-cg-card__footer">
			<?php if (!empty($settings['showPrice'])) : ?>
				<p class="sikshya-cg-card__price">
					<?php
					if ($is_enrolled) {
						echo '<span class="sikshya-cg-card__price-enrolled">' . esc_html__('Enrolled', 'sikshya') . '</span>';
					} elseif (!$is_paid) {
						echo '<span class="sikshya-cg-card__price-free">' . esc_html__('Free', 'sikshya') . '</span>';
					} else {
						if ($on_sale && null !== ($pricing['price'] ?? null)) {
							echo '<del class="sikshya-cg-card__price-was">';
							echo function_exists('sikshya_format_price')
								? wp_kses_post(sikshya_format_price((float) $pricing['price'], $currency))
								: esc_html((string) $pricing['price']);
							echo '</del> ';
						}
						echo '<span class="sikshya-cg-card__price-now">';
						echo function_exists('sikshya_format_price')
							? wp_kses_post(sikshya_format_price((float) $effective, $currency))
							: esc_html((string) $effective);
						echo '</span>';
					}
					?>
				</p>
			<?php endif; ?>

			<?php if (!empty($settings['showButton'])) : ?>
				<?php
				/*
				 * `wp-element-button` is the class block themes style their
				 * buttons with, so the CTA inherits the theme's button rather
				 * than carrying the plugin's blue into every design.
				 */
				?>
				<a
					class="sikshya-cg-card__cta wp-element-button"
					href="<?php echo esc_url($cta_url); ?>"
				><?php echo esc_html($cta_label); ?></a>
			<?php endif; ?>
		</footer>
	<?php endif; ?>
</article>
