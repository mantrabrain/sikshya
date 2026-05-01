<?php

namespace Sikshya\Services;

use Sikshya\Addons\Addons;
use Sikshya\Licensing\FeatureRegistry;
use Sikshya\Licensing\TierCapabilities;

/**
 * Add-on / plan gates for transactional email templates (match {@see SettingsManager} semantics).
 *
 * @package Sikshya\Services
 */
final class EmailTemplateGate
{
    /**
     * Custom templates: WordPress hook/event → [addon_id, feature_id].
     * System templates use {@see EmailTemplateCatalog} `required_addon` / `required_feature`.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function eventAddonRequirementMap(): array
    {
        /** @var array<string, array{0: string, 1: string}> $map */
        $map = [
            'sikshya_drip_lesson_unlocked' => ['drip_notifications', 'drip_notifications'],
            'sikshya_drip_course_unlocked' => ['drip_notifications', 'drip_notifications'],
            'sikshya_drip_lessons_unlocked' => ['drip_notifications', 'drip_notifications'],
            'sikshya_course_qa_question_posted' => ['community_discussions', 'community_discussions'],
        ];

        /**
         * Map trigger event key → [required addon id, required Pro feature id].
         *
         * @param array<string, array{0: string, 1: string}> $map
         */
        $filtered = apply_filters('sikshya_email_template_event_gate_map', $map);

        return is_array($filtered) ? $filtered : $map;
    }

    /**
     * Same rule as {@see SettingsManager::isGateMet}: both addon (if set) and feature (if set) must pass.
     */
    public static function isGateOpen(string $required_addon, string $required_feature): bool
    {
        if ($required_addon === '' && $required_feature === '') {
            return true;
        }
        if ($required_addon !== '' && !Addons::isEnabled($required_addon)) {
            return false;
        }
        if ($required_feature !== '' && !TierCapabilities::feature($required_feature)) {
            return false;
        }

        return true;
    }

    /**
     * Whether a custom template listening to this hook may send (and be edited in admin).
     */
    public static function isEventDispatchAllowed(string $event_key): bool
    {
        $event_key = self::normalizeEventKey($event_key);
        $map = self::eventAddonRequirementMap();
        if (!isset($map[$event_key])) {
            return true;
        }
        [$addon, $feature] = $map[$event_key];

        return self::isGateOpen($addon, $feature);
    }

    /**
     * @param array<string, mixed> $def Catalog definition (may include required_addon / required_feature).
     * @return array{
     *   locked: bool,
     *   locked_reason: string,
     *   required_addon: string,
     *   required_feature: string,
     *   required_addon_label: string,
     *   required_plan_label: string
     * }
     */
    public static function metadataFromCatalogDef(array $def): array
    {
        $addon = (string) ($def['required_addon'] ?? '');
        $feature = (string) ($def['required_feature'] ?? '');
        $open = self::isGateOpen($addon, $feature);
        $labels = self::gateLabels($addon, $feature);

        return [
            'locked' => !$open,
            'locked_reason' => $open ? '' : self::lockReason($addon, $feature),
            'required_addon' => $addon,
            'required_feature' => $feature,
            'required_addon_label' => $labels['required_addon_label'],
            'required_plan_label' => $labels['required_plan_label'],
        ];
    }

    /**
     * Gate info for a custom template row (by stored event key).
     *
     * @return array{
     *   locked: bool,
     *   locked_reason: string,
     *   required_addon: string,
     *   required_feature: string,
     *   required_addon_label: string,
     *   required_plan_label: string
     * }
     */
    public static function metadataFromCustomEvent(string $event_raw): array
    {
        $event_raw = self::normalizeEventKey($event_raw);
        $map = self::eventAddonRequirementMap();
        if (!isset($map[$event_raw])) {
            return [
                'locked' => false,
                'locked_reason' => '',
                'required_addon' => '',
                'required_feature' => '',
                'required_addon_label' => '',
                'required_plan_label' => '',
            ];
        }
        [$addon, $feature] = $map[$event_raw];

        return self::metadataFromAddonFeature($addon, $feature);
    }

    /**
     * @return array{
     *   locked: bool,
     *   locked_reason: string,
     *   required_addon: string,
     *   required_feature: string,
     *   required_addon_label: string,
     *   required_plan_label: string
     * }
     */
    public static function metadataFromAddonFeature(string $addon, string $feature): array
    {
        $open = self::isGateOpen($addon, $feature);
        $labels = self::gateLabels($addon, $feature);

        return [
            'locked' => !$open,
            'locked_reason' => $open ? '' : self::lockReason($addon, $feature),
            'required_addon' => $addon,
            'required_feature' => $feature,
            'required_addon_label' => $labels['required_addon_label'],
            'required_plan_label' => $labels['required_plan_label'],
        ];
    }

    public static function assertSystemEditableForId(string $template_id): ?\WP_Error
    {
        $def = EmailTemplateCatalog::get($template_id);
        if ($def === null) {
            return new \WP_Error('sikshya_unknown_template', __('Unknown template.', 'sikshya'), ['status' => 404]);
        }

        return self::errorIfLocked(self::metadataFromCatalogDef($def));
    }

    public static function assertCustomEditable(string $event_after_patch): ?\WP_Error
    {
        $meta = self::metadataFromCustomEvent($event_after_patch);

        return self::errorIfLocked($meta);
    }

    /**
     * @param array{
     *   locked: bool,
     *   locked_reason: string,
     *   required_addon: string,
     *   required_feature: string,
     *   required_addon_label: string,
     *   required_plan_label: string
     * } $meta
     */
    private static function errorIfLocked(array $meta): ?\WP_Error
    {
        if (!$meta['locked']) {
            return null;
        }

        return new \WP_Error(
            'sikshya_addon_disabled',
            $meta['locked_reason'] !== ''
                ? $meta['locked_reason']
                : __('This template is not available until its add-on is enabled and licensed.', 'sikshya'),
            [
                'status' => 403,
                'required_addon' => $meta['required_addon'],
                'required_feature' => $meta['required_feature'],
            ]
        );
    }

    /**
     * @return array{required_addon_label: string, required_plan_label: string}
     */
    private static function gateLabels(string $addon, string $feature): array
    {
        $addon_label = '';
        $plan_label = '';

        $id = $feature !== '' ? $feature : $addon;
        if ($id !== '') {
            $def = FeatureRegistry::get($id);
            if (is_array($def)) {
                if (isset($def['label'])) {
                    $addon_label = (string) $def['label'];
                }
                $tier = isset($def['tier']) ? (string) $def['tier'] : '';
                switch ($tier) {
                    case 'starter':
                        $plan_label = __('Starter', 'sikshya');
                        break;
                    case 'pro':
                        $plan_label = __('Growth', 'sikshya');
                        break;
                    case 'scale':
                        $plan_label = __('Scale', 'sikshya');
                        break;
                    default:
                        $plan_label = '';
                        break;
                }
            }
        }

        return [
            'required_addon_label' => $addon_label,
            'required_plan_label' => $plan_label,
        ];
    }

    private static function lockReason(string $addon, string $feature): string
    {
        $labels = self::gateLabels($addon, $feature);
        $addon_label = $labels['required_addon_label'] !== '' ? $labels['required_addon_label'] : $addon;
        $plan_label = $labels['required_plan_label'];

        if ($addon !== '' && !Addons::isEnabled($addon)) {
            if ($feature !== '' && !TierCapabilities::feature($feature)) {
                return sprintf(
                    /* translators: 1: plan label, 2: addon label */
                    __('Requires a paid plan (%1$s+) and the add-on “%2$s” to be enabled.', 'sikshya'),
                    $plan_label !== '' ? $plan_label : __('Pro', 'sikshya'),
                    $addon_label
                );
            }

            return sprintf(
                /* translators: %s: addon label */
                __('Enable the add-on “%s” under Addons to edit this template.', 'sikshya'),
                $addon_label
            );
        }
        if ($feature !== '' && !TierCapabilities::feature($feature)) {
            return $plan_label !== ''
                ? sprintf(
                    /* translators: %s: plan label */
                    __('Available on paid Sikshya plans (%s+).', 'sikshya'),
                    $plan_label
                )
                : __('Available on a higher paid plan.', 'sikshya');
        }

        return '';
    }

    /**
     * Keep in sync with {@see EmailTemplateStore::sanitizeEventKey} (avoid circular class refs).
     */
    private static function normalizeEventKey(string $event): string
    {
        $event = strtolower(trim($event));
        $clean = preg_replace('/[^a-z0-9._-]/', '', $event);
        $event = is_string($clean) ? $clean : $event;

        return $event !== '' ? $event : 'custom.manual';
    }
}
