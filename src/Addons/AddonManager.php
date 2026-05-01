<?php

namespace Sikshya\Addons;

use Sikshya\Core\Plugin;
use Sikshya\Licensing\FeatureRegistry;
use Sikshya\Licensing\TierCapabilities;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds the addon registry and boots enabled addons.
 */
final class AddonManager
{
    /**
     * @return array<string, AddonInterface>
     */
    public function registry(): array
    {
        $addons = [];

        // Core catalog: one addon per FeatureRegistry feature id.
        foreach (FeatureRegistry::definitions() as $id => $def) {
            $id = sanitize_key((string) $id);
            if ($id === '' || !is_array($def)) {
                continue;
            }
            $addons[$id] = new FeatureAddon($id, $def);
        }

        // Allow external plugins (commercial add-on, extension packs) to register/override addons.
        $addons = apply_filters('sikshya_addons_registry', $addons);

        // Normalize/clean.
        $out = [];
        foreach ((array) $addons as $id => $addon) {
            if (!$addon instanceof AddonInterface) {
                continue;
            }
            $aid = sanitize_key($addon->id());
            if ($aid === '') {
                continue;
            }
            $out[$aid] = $addon;
        }

        return $out;
    }

    /**
     * Boot enabled addons only.
     *
     * Disabled add-ons never call {@see AddonInterface::boot()}, so hooks, services, and REST
     * registered from an addon class do not run. UI still lists routes with overlays from React.
     */
    public function bootEnabledAddons(Plugin $plugin): void
    {
        $enabled = Addons::enabledIds();
        $registry = $this->registry();

        foreach ($enabled as $id) {
            if (!isset($registry[$id])) {
                continue;
            }
            $addon = $registry[$id];

            // Boot dependencies first (if any are enabled or auto-enabled).
            foreach ($addon->dependencies() as $dep) {
                $dep = sanitize_key((string) $dep);
                if ($dep === '' || !isset($registry[$dep])) {
                    continue;
                }
                $depTier = $registry[$dep]->tier();
                if (($depTier === 'starter' || $depTier === 'pro' || $depTier === 'scale') && !TierCapabilities::feature($dep)) {
                    continue;
                }
                if (!Addons::isEnabled($dep)) {
                    // Keep boot deterministic: auto-enable dependencies.
                    Addons::enable($dep);
                }
                $registry[$dep]->boot($plugin);
            }

            $addon->boot($plugin);
        }
    }
}

