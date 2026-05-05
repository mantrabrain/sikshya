<?php

/**
 * Course category business logic (uses TaxonomyRepository).
 *
 * @package Sikshya\Services
 */

namespace Sikshya\Services;

use Sikshya\Constants\Taxonomies;
use Sikshya\Core\ServiceResult;
use Sikshya\Database\Repositories\TaxonomyRepository;
use Sikshya\Utils\RichText;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

class CategoryService
{
    private TaxonomyRepository $taxonomy;

    public function __construct(?TaxonomyRepository $taxonomy = null)
    {
        $this->taxonomy = $taxonomy ?? new TaxonomyRepository();
    }

    /**
     * @param array<string,mixed> $input
     * @return array{ok:bool,message?:string,data?:mixed,errors?:mixed,code?:string}
     */
    /**
     * Load a single course category for the React admin (includes featured image id).
     *
     * @return array{ok:bool,message?:string,data?:array<string,mixed>,code?:string}
     */
    public function get(int $term_id): array
    {
        if (!current_user_can('manage_categories') && !current_user_can('manage_sikshya')) {
            return ServiceResult::failure(__('Insufficient permissions', 'sikshya'), null, 'forbidden');
        }

        $category = $this->taxonomy->getTerm($term_id, Taxonomies::COURSE_CATEGORY);
        if (is_wp_error($category) || !$category instanceof \WP_Term) {
            return ServiceResult::failure(__('Category not found', 'sikshya'), null, 'not_found');
        }

        $image_raw = $this->taxonomy->getTermMeta($term_id, 'category_image', true);
        $image_id = (int) $image_raw;

        return ServiceResult::success(
            [
                'category' => [
                    'id' => (int) $category->term_id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => (string) $category->description,
                    'parent' => (int) $category->parent,
                    'image_id' => $image_id > 0 ? $image_id : 0,
                ],
            ]
        );
    }

    /**
     * @param array<string,mixed> $input
     * @return array{ok:bool,message?:string,data?:mixed,errors?:mixed,code?:string}
     */
    public function save(array $input): array
    {
        if (!current_user_can('manage_categories') && !current_user_can('manage_sikshya')) {
            return ServiceResult::failure(__('Insufficient permissions', 'sikshya'), null, 'forbidden');
        }

        $term_id = isset($input['term_id']) ? (int) $input['term_id'] : 0;
        $name = sanitize_text_field($input['name'] ?? '');
        $description = RichText::sanitize(isset($input['description']) ? (string) $input['description'] : '');
        $slug = sanitize_title($input['slug'] ?? '');
        $parent = isset($input['parent']) ? (int) $input['parent'] : 0;
        $image_id = isset($input['image']) ? (int) $input['image'] : 0;

        if ($name === '') {
            return ServiceResult::failure(__('Category name is required', 'sikshya'), null, 'validation');
        }

        $term_data = [
            'description' => $description,
            'slug' => $slug,
            'parent' => $parent,
        ];

        if ($term_id > 0) {
            $term_data['name'] = $name;
            $result = $this->taxonomy->updateTerm($term_id, Taxonomies::COURSE_CATEGORY, $term_data);
        } else {
            $result = $this->taxonomy->insertTerm(Taxonomies::COURSE_CATEGORY, $name, $term_data);
        }

        if (is_wp_error($result)) {
            return ServiceResult::failure($result->get_error_message(), null, 'term_error');
        }

        $term_id = (int) ($result['term_id'] ?? $term_id);

        if ($image_id > 0) {
            $this->taxonomy->updateTermMeta($term_id, 'category_image', $image_id);
        } else {
            $this->taxonomy->deleteTermMeta($term_id, 'category_image');
        }

        $category = $this->taxonomy->getTerm($term_id, Taxonomies::COURSE_CATEGORY);
        if (is_wp_error($category) || !$category) {
            return ServiceResult::failure(__('Failed to retrieve category data', 'sikshya'), null, 'term_load');
        }

        $saved_image = (int) $this->taxonomy->getTermMeta($category->term_id, 'category_image', true);

        return ServiceResult::success(
            [
                'category' => [
                    'id' => (int) $category->term_id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'parent' => (int) $category->parent,
                    'image_id' => $saved_image > 0 ? $saved_image : 0,
                ],
            ],
            __('Category saved successfully', 'sikshya')
        );
    }

    public function delete(int $term_id): array
    {
        if (!current_user_can('manage_categories') && !current_user_can('manage_sikshya')) {
            return ServiceResult::failure(__('Insufficient permissions', 'sikshya'), null, 'forbidden');
        }

        $del = $this->taxonomy->deleteTerm($term_id, Taxonomies::COURSE_CATEGORY);
        if (is_wp_error($del)) {
            return ServiceResult::failure($del->get_error_message(), null, 'term_error');
        }

        return ServiceResult::success(null, __('Category deleted successfully', 'sikshya'));
    }
}
