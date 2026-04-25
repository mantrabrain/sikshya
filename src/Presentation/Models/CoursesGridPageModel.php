<?php

/**
 * @package Sikshya\Presentation\Models
 */

namespace Sikshya\Presentation\Models;

if (!defined('ABSPATH')) {
    exit;
}

final class CoursesGridPageModel
{
    /**
     * @param array<int, \WP_Term> $filterCategories
     */
    public function __construct(
        private \WP_Query $coursesQuery,
        private array $filterCategories
    ) {
    }

    /**
     * @param array{courses_query: \WP_Query, filter_categories: array<int, \WP_Term>} $row
     */
    public static function fromViewData(array $row): self
    {
        $q = $row['courses_query'] ?? null;
        if (!$q instanceof \WP_Query) {
            $q = new \WP_Query(['post_type' => 'any', 'post__in' => [0]]);
        }
        $cats = $row['filter_categories'] ?? [];
        if (!is_array($cats)) {
            $cats = [];
        }

        return new self($q, $cats);
    }

    public function getCoursesQuery(): \WP_Query
    {
        return $this->coursesQuery;
    }

    /**
     * @return array<int, \WP_Term>
     */
    public function getFilterCategories(): array
    {
        return $this->filterCategories;
    }

    /**
     * @return array<string, mixed>
     */
    public function toLegacyViewArray(): array
    {
        return [
            'courses_query' => $this->coursesQuery,
            'filter_categories' => $this->filterCategories,
        ];
    }
}
