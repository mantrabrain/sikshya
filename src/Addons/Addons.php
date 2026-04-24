<?php

namespace Sikshya\Addons;

use Sikshya\Licensing\FeatureRegistry;
use Sikshya\Services\Settings;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Persisted addon enablement state.
 */
final class Addons
{
    private const OPT_ENABLED = 'addons_enabled';

    /**
     * @return string[] enabled addon ids
     */
    public static function enabledIds(): array
    {
        $raw = Settings::get(self::OPT_ENABLED, null);

        if ($raw === null || $raw === '' || $raw === false) {
            $defaults = self::defaultEnabledIds();
            // Autoload false: keep option from bloating all page loads.
            Settings::set(self::OPT_ENABLED, $defaults);
            return $defaults;
        }

        if (!is_array($raw)) {
            return self::defaultEnabledIds();
        }

        $ids = [];
        foreach ($raw as $id) {
            $id = sanitize_key((string) $id);
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        // Free-tier modules are always considered enabled (not toggleable in the Addons UI).
        $ids = array_values(array_unique(array_merge($ids, self::defaultEnabledIds())));

        return $ids;
    }

    public static function isEnabled(string $addonId): bool
    {
        $addonId = sanitize_key($addonId);
        if ($addonId === '') {
            return false;
        }
        return in_array($addonId, self::enabledIds(), true);
    }

    /**
     * @param string[] $ids
     */
    public static function setEnabledIds(array $ids): bool
    {
        $clean = [];
        foreach ($ids as $id) {
            $id = sanitize_key((string) $id);
            if ($id !== '') {
                $clean[] = $id;
            }
        }
        $clean = array_values(array_unique($clean));

        return Settings::set(self::OPT_ENABLED, $clean);
    }

    public static function enable(string $addonId): bool
    {
        $addonId = sanitize_key($addonId);
        if ($addonId === '') {
            return false;
        }
        $ids = self::enabledIds();
        if (in_array($addonId, $ids, true)) {
            return true;
        }
        $ids[] = $addonId;
        return self::setEnabledIds($ids);
    }

    public static function disable(string $addonId): bool
    {
        $addonId = sanitize_key($addonId);
        if ($addonId === '') {
            return false;
        }
        $ids = array_values(array_filter(self::enabledIds(), static fn($id) => $id !== $addonId));
        return self::setEnabledIds($ids);
    }

    /**
     * Enabled-by-default set:
     * - all tier=free FeatureRegistry entries
     * - can be filtered for distribution changes
     *
     * @return string[]
     */
    public static function defaultEnabledIds(): array
    {
        $defs = FeatureRegistry::definitions();
        $ids = [];
        foreach ($defs as $id => $def) {
            $tier = isset($def['tier']) ? (string) $def['tier'] : 'free';
            if (strtolower($tier) === 'free') {
                $ids[] = sanitize_key((string) $id);
            }
        }

        // Filter default enabled addon IDs.
        $ids = apply_filters('sikshya_addons_default_enabled', $ids);

        $clean = [];
        foreach ((array) $ids as $id) {
            $id = sanitize_key((string) $id);
            if ($id !== '') {
                $clean[] = $id;
            }
        }

        return array_values(array_unique($clean));
    }
}

