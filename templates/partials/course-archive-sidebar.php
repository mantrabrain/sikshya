<?php
/**
 * Course archive — filter sidebar (GET form).
 *
 * Expects `sikshya_course_archive_get_filter_request()` and `sikshya_course_archive_build_url()`.
 *
 * @package Sikshya
 */

if (!defined('ABSPATH')) {
    exit;
}

use Sikshya\Constants\PostTypes;
use Sikshya\Constants\Taxonomies;

$f = sikshya_course_archive_get_filter_request();
$archive_url = get_post_type_archive_link(PostTypes::COURSE);
if (!$archive_url) {
    $archive_url = home_url('/');
}

$categories = get_terms(
    [
        'taxonomy' => Taxonomies::COURSE_CATEGORY,
        'hide_empty' => true,
    ]
);
if (is_wp_error($categories)) {
    $categories = [];
}
?>

<aside class="sikshya-archive-filters" aria-label="<?php esc_attr_e('Filter courses', 'sikshya'); ?>">
    <form class="sikshya-archive-filters__form" method="get" action="<?php echo esc_url($archive_url); ?>">
        <input type="hidden" name="post_type" value="<?php echo esc_attr(PostTypes::COURSE); ?>" />
        <?php if ($f['s'] !== '') : ?>
            <input type="hidden" name="s" value="<?php echo esc_attr($f['s']); ?>" />
        <?php endif; ?>
        <input type="hidden" name="sikshya_sort" value="<?php echo esc_attr($f['sort']); ?>" />
        <input type="hidden" name="sikshya_view" value="<?php echo esc_attr($f['view']); ?>" />
        <input type="hidden" name="sikshya_per_page" value="<?php echo esc_attr((string) $f['per_page']); ?>" />

        <div class="sikshya-archive-filters__head">
            <h2 class="sikshya-archive-filters__title"><?php esc_html_e('Filters', 'sikshya'); ?></h2>
            <a class="sikshya-archive-filters__clear" href="<?php echo esc_url($archive_url); ?>"><?php esc_html_e('Clear all', 'sikshya'); ?></a>
        </div>

        <div class="sikshya-archive-filters__group">
            <label class="sikshya-archive-filters__label" for="sikshya-filter-cat"><?php esc_html_e('Category', 'sikshya'); ?></label>
            <select class="sikshya-archive-filters__select" id="sikshya-filter-cat" name="sikshya_cat" onchange="this.form.submit()">
                <option value=""><?php esc_html_e('All categories', 'sikshya'); ?></option>
                <?php foreach ($categories as $term) : ?>
                    <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($f['category_slug'], $term->slug); ?>>
                        <?php echo esc_html($term->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="sikshya-archive-filters__group">
            <span class="sikshya-archive-filters__label"><?php esc_html_e('Level', 'sikshya'); ?></span>
            <div class="sikshya-archive-filters__radios">
                <label class="sikshya-archive-filters__radio">
                    <input type="radio" name="sikshya_level" value="" <?php checked($f['level'], ''); ?> onchange="this.form.submit()" />
                    <span><?php esc_html_e('Any', 'sikshya'); ?></span>
                </label>
                <label class="sikshya-archive-filters__radio">
                    <input type="radio" name="sikshya_level" value="beginner" <?php checked($f['level'], 'beginner'); ?> onchange="this.form.submit()" />
                    <span><?php esc_html_e('Beginner', 'sikshya'); ?></span>
                </label>
                <label class="sikshya-archive-filters__radio">
                    <input type="radio" name="sikshya_level" value="intermediate" <?php checked($f['level'], 'intermediate'); ?> onchange="this.form.submit()" />
                    <span><?php esc_html_e('Intermediate', 'sikshya'); ?></span>
                </label>
                <label class="sikshya-archive-filters__radio">
                    <input type="radio" name="sikshya_level" value="advanced" <?php checked($f['level'], 'advanced'); ?> onchange="this.form.submit()" />
                    <span><?php esc_html_e('Advanced', 'sikshya'); ?></span>
                </label>
            </div>
        </div>

        <div class="sikshya-archive-filters__group">
            <span class="sikshya-archive-filters__label"><?php esc_html_e('Price', 'sikshya'); ?></span>
            <div class="sikshya-archive-filters__radios">
                <label class="sikshya-archive-filters__radio">
                    <input type="radio" name="sikshya_price" value="" <?php checked($f['price'], ''); ?> onchange="this.form.submit()" />
                    <span><?php esc_html_e('All', 'sikshya'); ?></span>
                </label>
                <label class="sikshya-archive-filters__radio">
                    <input type="radio" name="sikshya_price" value="free" <?php checked($f['price'], 'free'); ?> onchange="this.form.submit()" />
                    <span><?php esc_html_e('Free', 'sikshya'); ?></span>
                </label>
                <label class="sikshya-archive-filters__radio">
                    <input type="radio" name="sikshya_price" value="paid" <?php checked($f['price'], 'paid'); ?> onchange="this.form.submit()" />
                    <span><?php esc_html_e('Paid', 'sikshya'); ?></span>
                </label>
            </div>
        </div>

    </form>
</aside>
