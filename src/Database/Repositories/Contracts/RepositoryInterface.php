<?php

/**
 * Marker for data-access repositories (WordPress posts, meta, custom tables).
 *
 * Repositories must not contain HTTP, nonce checks, or UI logic — only persistence
 * and query operations. Business rules live in services.
 *
 * @package Sikshya\Database\Repositories\Contracts
 */

namespace Sikshya\Database\Repositories\Contracts;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

interface RepositoryInterface
{
}
