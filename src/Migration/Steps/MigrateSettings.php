<?php

/**
 * Copy legacy `wp_options` values into the rewrite's `_sikshya_*` storage.
 *
 * Each rewrite key is only written if the rewrite plugin hasn't already
 * stored a non-empty value for it — that way an admin who configured the
 * new plugin first never has their settings overwritten by stale legacy
 * values.
 *
 * Permalinks are unpacked from the legacy `sikshya_permalinks` serialized
 * blob into the rewrite's individual permalink options.
 *
 * @package Sikshya\Migration\Steps
 */

namespace Sikshya\Migration\Steps;

use Sikshya\Migration\LegacyMigrationLogger;
use Sikshya\Migration\MigrationState;
use Sikshya\Services\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class MigrateSettings extends AbstractStep
{
    /**
     * Direct legacy option key -> rewrite Settings key.
     *
     * @var array<string,string>
     */
    private const OPTION_MAP = [
        'sikshya_currency' => 'currency',
        'sikshya_currency_position' => 'currency_position',
        'sikshya_currency_symbol_type' => 'currency_symbol_type',
        'sikshya_thousand_separator' => 'thousand_separator',
        'sikshya_decimal_separator' => 'decimal_separator',
        'sikshya_price_number_decimals' => 'currency_decimal_places',
        'sikshya_payment_gateway_test_mode' => 'payment_test_mode',
        'sikshya_payment_gateway_enable_logging' => 'payment_enable_logging',
        'sikshya_payment_gateway_paypal_email' => 'paypal_email',
        'sikshya_payment_gateway_paypal_description' => 'paypal_description',
        'sikshya_payment_gateway_paypal_image_url' => 'paypal_image_url',
        'sikshya_payment_gateway_paypal_help_text' => 'paypal_help_text',
        'sikshya_payment_gateway_paypal_help_url' => 'paypal_help_url',
    ];

    /**
     * Page-id legacy options preserved as `_sikshya_legacy_page_<slug>`.
     *
     * @var array<string,string>
     */
    private const LEGACY_PAGE_MAP = [
        'sikshya_account_page' => 'legacy_page_account',
        'sikshya_login_page' => 'legacy_page_login',
        'sikshya_cart_page' => 'legacy_page_cart',
        'sikshya_checkout_page' => 'legacy_page_checkout',
        'sikshya_thankyou_page' => 'legacy_page_thankyou',
        'sikshya_registration_page' => 'legacy_page_registration',
    ];

    /**
     * Permalinks blob entries -> rewrite Settings keys.
     *
     * @var array<string,string>
     */
    private const PERMALINK_MAP = [
        'sikshya_course_base' => 'rewrite_base_course',
        'sikshya_lesson_base' => 'rewrite_base_lesson',
        'sikshya_quiz_base' => 'rewrite_base_quiz',
        'sikshya_course_category_base' => 'rewrite_tax_course_category',
    ];

    public function id(): string
    {
        return 'settings';
    }

    public function description(): string
    {
        return __('Copy legacy options, permalinks, and page references.', 'sikshya');
    }

    public function executeBatch(
        MigrationState $state,
        LegacyMigrationLogger $logger,
        int $batchSize,
        bool $dryRun
    ): int {
        $this->markRunning($state);

        $copied = 0;

        foreach (self::OPTION_MAP as $legacy => $rewrite_key) {
            $copied += $this->copyOptionToSetting($legacy, $rewrite_key, $logger, $dryRun, $state);
        }

        foreach (self::LEGACY_PAGE_MAP as $legacy => $rewrite_key) {
            $copied += $this->copyOptionToSetting($legacy, $rewrite_key, $logger, $dryRun, $state);
        }

        $permalinks = get_option('sikshya_permalinks', null);
        if (is_array($permalinks)) {
            foreach (self::PERMALINK_MAP as $sub => $rewrite_key) {
                if (!isset($permalinks[$sub]) || $permalinks[$sub] === '') {
                    continue;
                }
                $value = (string) $permalinks[$sub];
                if ($this->settingAlreadySet($rewrite_key)) {
                    continue;
                }
                if ($dryRun) {
                    $logger->info(sprintf(
                        '[dry-run] Would set permalink %s = %s (from sikshya_permalinks[%s]).',
                        $rewrite_key,
                        $value,
                        $sub
                    ));
                } else {
                    Settings::set($rewrite_key, $value);
                    $state->incrementStepCount($this->id(), 'permalinks', 1);
                    $logger->info(sprintf('Migrated permalink %s = %s', $rewrite_key, $value));
                }
                $copied++;
            }
        }

        $this->markComplete($state);
        return $copied;
    }

    private function copyOptionToSetting(
        string $legacyKey,
        string $rewriteKey,
        LegacyMigrationLogger $logger,
        bool $dryRun,
        MigrationState $state
    ): int {
        $value = get_option($legacyKey, null);
        if ($value === null || $value === '' || $value === false) {
            return 0;
        }
        if ($this->settingAlreadySet($rewriteKey)) {
            return 0;
        }
        if ($dryRun) {
            $logger->info(sprintf('[dry-run] Would set setting %s from %s.', $rewriteKey, $legacyKey));
            return 1;
        }
        Settings::set($rewriteKey, $value);
        $state->incrementStepCount($this->id(), 'options', 1);
        $logger->info(sprintf('Migrated option %s -> %s', $legacyKey, $rewriteKey));
        return 1;
    }

    private function settingAlreadySet(string $key): bool
    {
        if (!class_exists(Settings::class)) {
            return false;
        }
        $current = Settings::get($key, null);
        return !($current === null || $current === '' || $current === false);
    }
}
