<?php

namespace Sikshya\Admin;

use Sikshya\Constants\Taxonomies;

// phpcs:ignore
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Routes course category management away from native WordPress term screens.
 *
 * @package Sikshya\Admin
 */
final class CourseCategoryAdminRedirects
{
    public static function register(): void
    {
        add_filter('get_edit_term_link', [self::class, 'filterEditTermLink'], 10, 3);
        add_action('admin_init', [self::class, 'maybeRedirectNativeTermScreens'], 5);
    }

    /**
     * @param string $link     Default edit URL.
     * @param int    $term_id  Term ID.
     * @param string $taxonomy Taxonomy slug.
     */
    public static function filterEditTermLink($link, $term_id, $taxonomy)
    {
        if ($taxonomy !== Taxonomies::COURSE_CATEGORY) {
            return $link;
        }

        $term_id = (int) $term_id;
        if ($term_id <= 0 || !current_user_can('edit_term', $term_id, $taxonomy)) {
            return $link;
        }

        return self::reactUrl($term_id);
    }

    public static function maybeRedirectNativeTermScreens(): void
    {
        if (!is_admin() || !current_user_can('manage_categories')) {
            return;
        }

        global $pagenow;
        $pagenow = is_string($pagenow) ? $pagenow : '';

        if ($pagenow !== 'edit-tags.php' && $pagenow !== 'term.php') {
            return;
        }

        $taxonomy = isset($_GET['taxonomy']) ? sanitize_key((string) wp_unslash($_GET['taxonomy'])) : '';
        if ($taxonomy !== Taxonomies::COURSE_CATEGORY) {
            return;
        }

        $term_id = 0;
        if ($pagenow === 'term.php' && isset($_GET['tag_ID'])) {
            $term_id = absint($_GET['tag_ID']);
        }

        wp_safe_redirect(self::reactUrl($term_id));
        exit;
    }

    private static function reactUrl(int $term_id = 0): string
    {
        $extra = [];
        if ($term_id > 0) {
            $extra['category_id'] = (string) $term_id;
        }

        return ReactAdminConfig::reactAppUrl('course-categories', $extra);
    }
}
