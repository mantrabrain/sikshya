<?php

namespace Sikshya\Frontend\Public;

/**
 * Shared view-model for archive/taxonomy templates.
 *
 * Templates stay markup-only; anything derived from $wp_query or pagination lives here.
 *
 * @package Sikshya\Frontend\Public
 */
final class ArchiveContextTemplateData
{
    /**
     * @return array{found:int,max_pages:int,paged:int}
     */
    public static function fromWpQuery(): array
    {
        global $wp_query;

        $found = isset($wp_query->found_posts) ? (int) $wp_query->found_posts : 0;
        $max_pages = isset($wp_query->max_num_pages) ? (int) $wp_query->max_num_pages : 0;

        $paged = (int) get_query_var('paged');
        if ($paged < 1) {
            $paged = (int) get_query_var('page');
        }
        if ($paged < 1) {
            $paged = 1;
        }

        return [
            'found' => $found,
            'max_pages' => $max_pages,
            'paged' => $paged,
        ];
    }
}

