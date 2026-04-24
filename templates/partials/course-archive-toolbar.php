<?php
/**
 * Course archive — search, sort, grid/list toggle.
 *
 * @package Sikshya
 */

if (!defined('ABSPATH')) {
    exit;
}

use Sikshya\Constants\PostTypes;

$f = sikshya_course_archive_get_filter_request();
$archive_url = get_post_type_archive_link(PostTypes::COURSE);
if (!$archive_url) {
    $archive_url = home_url('/');
}
$search_on = \Sikshya\Services\CourseFrontendSettings::isCourseSearchEnabled();
?>

<div class="sikshya-archive-toolbar">
    <?php if ($search_on) : ?>
    <form class="sikshya-archive-toolbar__search" method="get" action="<?php echo esc_url($archive_url); ?>" role="search">
        <input type="hidden" name="post_type" value="<?php echo esc_attr(PostTypes::COURSE); ?>" />
        <?php if ($f['category_slug'] !== '') : ?>
            <input type="hidden" name="sikshya_cat" value="<?php echo esc_attr($f['category_slug']); ?>" />
        <?php endif; ?>
        <?php if ($f['level'] !== '') : ?>
            <input type="hidden" name="sikshya_level" value="<?php echo esc_attr($f['level']); ?>" />
        <?php endif; ?>
        <?php if ($f['price'] !== '') : ?>
            <input type="hidden" name="sikshya_price" value="<?php echo esc_attr($f['price']); ?>" />
        <?php endif; ?>
        <input type="hidden" name="sikshya_sort" value="<?php echo esc_attr($f['sort']); ?>" />
        <input type="hidden" name="sikshya_per_page" value="<?php echo esc_attr((string) $f['per_page']); ?>" />
        <label class="sikshya-sr-only" for="sikshya-archive-search"><?php esc_html_e('Search courses', 'sikshya'); ?></label>
        <input
            id="sikshya-archive-search"
            class="sikshya-archive-toolbar__search-input"
            type="search"
            name="s"
            value="<?php echo esc_attr($f['s']); ?>"
            placeholder="<?php esc_attr_e('Search courses…', 'sikshya'); ?>"
        />
        <button type="submit" class="sikshya-archive-toolbar__search-btn sikshya-button sikshya-button--primary sikshya-button--small"><?php esc_html_e('Search', 'sikshya'); ?></button>
    </form>
    <?php endif; ?>

    <div class="sikshya-archive-toolbar__row">
        <form class="sikshya-archive-toolbar__sort" method="get" action="<?php echo esc_url($archive_url); ?>">
            <input type="hidden" name="post_type" value="<?php echo esc_attr(PostTypes::COURSE); ?>" />
            <?php if ($f['s'] !== '') : ?>
                <input type="hidden" name="s" value="<?php echo esc_attr($f['s']); ?>" />
            <?php endif; ?>
            <?php if ($f['category_slug'] !== '') : ?>
                <input type="hidden" name="sikshya_cat" value="<?php echo esc_attr($f['category_slug']); ?>" />
            <?php endif; ?>
            <?php if ($f['level'] !== '') : ?>
                <input type="hidden" name="sikshya_level" value="<?php echo esc_attr($f['level']); ?>" />
            <?php endif; ?>
            <?php if ($f['price'] !== '') : ?>
                <input type="hidden" name="sikshya_price" value="<?php echo esc_attr($f['price']); ?>" />
            <?php endif; ?>
            <input type="hidden" name="sikshya_per_page" value="<?php echo esc_attr((string) $f['per_page']); ?>" />
            <label class="sikshya-archive-toolbar__sort-label" for="sikshya-archive-sort"><?php esc_html_e('Sort by', 'sikshya'); ?></label>
            <select id="sikshya-archive-sort" class="sikshya-archive-toolbar__select" name="sikshya_sort" onchange="this.form.submit()">
                <option value="date_desc" <?php selected($f['sort'], 'date_desc'); ?>><?php esc_html_e('Newest first', 'sikshya'); ?></option>
                <option value="date_asc" <?php selected($f['sort'], 'date_asc'); ?>><?php esc_html_e('Oldest first', 'sikshya'); ?></option>
                <option value="title_asc" <?php selected($f['sort'], 'title_asc'); ?>><?php esc_html_e('Title A–Z', 'sikshya'); ?></option>
                <option value="title_desc" <?php selected($f['sort'], 'title_desc'); ?>><?php esc_html_e('Title Z–A', 'sikshya'); ?></option>
                <option value="price_asc" <?php selected($f['sort'], 'price_asc'); ?>><?php esc_html_e('Price: low to high', 'sikshya'); ?></option>
                <option value="price_desc" <?php selected($f['sort'], 'price_desc'); ?>><?php esc_html_e('Price: high to low', 'sikshya'); ?></option>
            </select>
        </form>

        <div class="sikshya-archive-toolbar__view" role="group" aria-label="<?php esc_attr_e('Layout', 'sikshya'); ?>">
            <button
                type="button"
                class="sikshya-archive-toolbar__view-btn is-active"
                data-sikshya-archive-view="grid"
                aria-pressed="true"
                aria-label="<?php echo esc_attr__('Grid view', 'sikshya'); ?>"
                title="<?php echo esc_attr__('Grid view', 'sikshya'); ?>"
            >
                <span class="sikshya-sr-only"><?php esc_html_e('Grid', 'sikshya'); ?></span>
                <svg class="sikshya-archive-toolbar__view-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
                    <path d="M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z" fill="currentColor"></path>
                </svg>
            </button>
            <button
                type="button"
                class="sikshya-archive-toolbar__view-btn"
                data-sikshya-archive-view="list"
                aria-pressed="false"
                aria-label="<?php echo esc_attr__('List view', 'sikshya'); ?>"
                title="<?php echo esc_attr__('List view', 'sikshya'); ?>"
            >
                <span class="sikshya-sr-only"><?php esc_html_e('List', 'sikshya'); ?></span>
                <svg class="sikshya-archive-toolbar__view-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
                    <path d="M5 6h14v2H5V6zm0 5h14v2H5v-2zm0 5h14v2H5v-2z" fill="currentColor"></path>
                </svg>
            </button>
        </div>
    </div>
</div>
