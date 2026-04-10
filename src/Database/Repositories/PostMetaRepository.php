<?php

/**
 * Central access to post meta (wp_postmeta). All tab/meta writes should go through here.
 *
 * @package Sikshya\Database\Repositories
 */

namespace Sikshya\Database\Repositories;

use Sikshya\Database\Repositories\Contracts\RepositoryInterface;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

class PostMetaRepository implements RepositoryInterface
{
    /**
     * @param int    $post_id Post ID.
     * @param string $meta_key Meta key.
     * @param bool   $single Return first value only.
     * @return mixed
     */
    public function get(int $post_id, string $meta_key, bool $single = true)
    {
        return get_post_meta($post_id, $meta_key, $single);
    }

    /**
     * @param int    $post_id Post ID.
     * @param string $meta_key Meta key.
     * @param mixed  $value Value to store.
     * @return bool True on success; false may mean unchanged value.
     */
    public function update(int $post_id, string $meta_key, $value): bool
    {
        return (bool) update_post_meta($post_id, $meta_key, $value);
    }

    /**
     * @param int    $post_id Post ID.
     * @param string $meta_key Meta key.
     */
    public function delete(int $post_id, string $meta_key): bool
    {
        return (bool) delete_post_meta($post_id, $meta_key);
    }
}
