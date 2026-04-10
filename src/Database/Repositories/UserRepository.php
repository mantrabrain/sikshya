<?php

/**
 * WordPress users (read/query only from repositories).
 *
 * @package Sikshya\Database\Repositories
 */

namespace Sikshya\Database\Repositories;

use Sikshya\Database\Repositories\Contracts\RepositoryInterface;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

class UserRepository implements RepositoryInterface
{
    /**
     * @return \WP_User[]
     */
    public function query(array $args = []): array
    {
        return get_users($args);
    }

    public function findById(int $id): ?\WP_User
    {
        $user = get_user_by('id', $id);
        return $user instanceof \WP_User ? $user : null;
    }

    public function findByLogin(string $login): ?\WP_User
    {
        $user = get_user_by('login', $login);
        return $user instanceof \WP_User ? $user : null;
    }
}
