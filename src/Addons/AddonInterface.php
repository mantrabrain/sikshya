<?php

namespace Sikshya\Addons;

use Sikshya\Core\Plugin;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Addon contract.
 *
 * Addons are modular feature units that can be enabled/disabled at runtime.
 * An addon MUST NOT register hooks/routes unless it is enabled and booted.
 */
interface AddonInterface
{
    public function id(): string;

    public function label(): string;

    public function description(): string;

    /**
     * Longer help text for admin tooltips (plain text; UI may split on blank lines).
     */
    public function detailDescription(): string;

    /**
     * @return 'free'|'starter'|'pro'|'scale'
     */
    public function tier(): string;

    /**
     * Group key for UI filtering (course, commerce, assessment, analytics, integrations, ...).
     */
    public function group(): string;

    /**
     * Stable feature IDs from {@see \Sikshya\Licensing\FeatureRegistry} that this addon maps to.
     *
     * @return string[]
     */
    public function featureIds(): array;

    /**
     * Addon dependencies: if this addon is enabled, these must be enabled too.
     *
     * @return string[]
     */
    public function dependencies(): array;

    /**
     * Register hooks, routes, and services for this addon.
     */
    public function boot(Plugin $plugin): void;
}

