<?php

/**
 * Terms and term meta (taxonomies).
 *
 * @package Sikshya\Database\Repositories
 */

namespace Sikshya\Database\Repositories;

use Sikshya\Database\Repositories\Contracts\RepositoryInterface;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

class TaxonomyRepository implements RepositoryInterface
{
    /**
     * @return array|\WP_Error
     */
    public function insertTerm(string $taxonomy, string $name, array $args = [])
    {
        return wp_insert_term($name, $taxonomy, $args);
    }

    /**
     * @return array|\WP_Error
     */
    public function updateTerm(int $term_id, string $taxonomy, array $args = [])
    {
        return wp_update_term($term_id, $taxonomy, $args);
    }

    /**
     * @return bool|\WP_Error
     */
    public function deleteTerm(int $term_id, string $taxonomy)
    {
        return wp_delete_term($term_id, $taxonomy);
    }

    public function getTerm(int $term_id, string $taxonomy)
    {
        return get_term($term_id, $taxonomy);
    }

    public function updateTermMeta(int $term_id, string $meta_key, $value): bool
    {
        return update_term_meta($term_id, $meta_key, $value) !== false;
    }

    public function deleteTermMeta(int $term_id, string $meta_key): bool
    {
        return delete_term_meta($term_id, $meta_key);
    }

    public function getTermMeta(int $term_id, string $meta_key, bool $single = true)
    {
        return get_term_meta($term_id, $meta_key, $single);
    }
}
