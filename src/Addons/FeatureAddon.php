<?php

namespace Sikshya\Addons;

use Sikshya\Core\Plugin;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generic addon backed by the FeatureRegistry definition.
 *
 * By default, FeatureAddons do not register any hooks. Concrete add-ons should
 * extend this and implement boot(), or register via a separate plugin.
 */
class FeatureAddon implements AddonInterface
{
    /** @var array{label:string,tier:string,group:string,description:string,detail_description?:string} */
    protected array $def;

    protected string $id;

    /**
     * @param array{label:string,tier:'free'|'starter'|'pro'|'scale',group:string,description:string,detail_description?:string} $def
     */
    public function __construct(string $id, array $def)
    {
        $this->id  = $id;
        $this->def = $def;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return (string) ($this->def['label'] ?? $this->id);
    }

    public function description(): string
    {
        return (string) ($this->def['description'] ?? '');
    }

    public function detailDescription(): string
    {
        $detail = $this->def['detail_description'] ?? null;
        if (is_string($detail) && $detail !== '') {
            return $detail;
        }

        return (string) ($this->def['description'] ?? '');
    }

    public function tier(): string
    {
        $tier = (string) ($this->def['tier'] ?? 'free');
        $tier = strtolower(trim($tier));

        return in_array($tier, ['free', 'starter', 'pro', 'scale'], true) ? $tier : 'free';
    }

    public function group(): string
    {
        return (string) ($this->def['group'] ?? 'general');
    }

    public function featureIds(): array
    {
        return [$this->id];
    }

    public function dependencies(): array
    {
        return [];
    }

    public function boot(Plugin $plugin): void
    {
        // Generic feature add-ons are catalog-only unless overridden.
    }
}

