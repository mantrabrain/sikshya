<?php

namespace Sikshya\Admin;

use Sikshya\Admin\Controllers\SampleDataController;
use Sikshya\Core\Plugin;
use Sikshya\Services\PermalinkService;
use Sikshya\Services\Settings;

final class SetupWizardController
{
    public const MENU_SLUG = 'sikshya-setup';

    public const STEP_QUERY = 'step';

    /** Per-user transient that carries the optional sample-import result to the celebration screen. */
    private const SAMPLE_RESULT_TRANSIENT_PREFIX = 'sikshya_setup_wizard_sample_import_';

    private Plugin $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Wizard URL with a stable step in the query string (1–5, or "done" for the celebration screen).
     */
    public static function adminUrl(int $step = 1): string
    {
        $s = (string) max(1, min(5, $step));

        return add_query_arg(
            [
                'page' => self::MENU_SLUG,
                self::STEP_QUERY => $s,
            ],
            admin_url('admin.php')
        );
    }

    /**
     * “All set” screen after setup is complete (bookmarks and support links).
     */
    public static function doneUrl(): string
    {
        return add_query_arg(
            [
                'page' => self::MENU_SLUG,
                self::STEP_QUERY => 'done',
            ],
            admin_url('admin.php')
        );
    }

    /**
     * @return 'done'|numeric-string
     */
    public static function parseStepFromRequest(): string
    {
        if (empty($_GET[ self::STEP_QUERY ]) || !is_string($_GET[ self::STEP_QUERY ])) {
            return '1';
        }
        $raw = sanitize_key(wp_unslash((string) $_GET[ self::STEP_QUERY ]));
        if ($raw === 'done') {
            return 'done';
        }
        if ($raw === '' || $raw === '0') {
            return '1';
        }
        $n = (int) $raw;
        if ($n < 1) {
            return '1';
        }
        if ($n > 5) {
            return '5';
        }

        return (string) $n;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{success: bool, errors: string[]}
     */
    public static function processStep(int $step, array $data, ?Plugin $plugin = null): array
    {
        $errors = [];
        if ($step === 1) {
            self::persistUsageTrackingFromData($data);

            return ['success' => true, 'errors' => []];
        }
        if ($step === 2) {
            self::persistPermalinksFromData($data, $errors);
            if ($errors !== []) {
                return ['success' => false, 'errors' => $errors];
            }
            flush_rewrite_rules(false);

            return ['success' => true, 'errors' => []];
        }
        if ($step === 3) {
            self::persistCurrencyFromData($data, $errors);
            if ($errors !== []) {
                return ['success' => false, 'errors' => $errors];
            }

            return ['success' => true, 'errors' => []];
        }
        if ($step === 4) {
            self::persistLearnFromData($data);
            flush_rewrite_rules(false);

            return ['success' => true, 'errors' => []];
        }
        if ($step === 5) {
            Settings::set('setup_completed', '1');
            flush_rewrite_rules(false);
            do_action('sikshya_usage_setup_wizard_completed');
            // Sample-course import is now a separate, on-demand action driven
            // by the “Add sample course” button on the Finish step (see the
            // dedicated REST endpoint), so Finish setup stays a single-purpose
            // “mark wizard complete” call.
            unset($plugin);

            return ['success' => true, 'errors' => []];
        }

        return [
            'success' => false,
            'errors' => [__('That step is not valid.', 'sikshya')],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param string[] $errors
     */
    private static function persistPermalinksFromData(array $data, array &$errors): void
    {
        $map = [
            'permalink_cart' => 'cart',
            'permalink_checkout' => 'checkout',
            'permalink_account' => 'account',
            'permalink_learn' => 'learn',
            'permalink_order' => 'order',
        ];
        $slugs = [];
        foreach ($map as $key => $fallback) {
            $raw = isset($data[ $key ]) ? trim((string) wp_unslash((string) $data[ $key ])) : '';
            if ($raw === '') {
                $slug = $fallback;
            } else {
                $slug = PermalinkService::sanitizeSlug($raw);
                if ($slug === '') {
                    $slug = $fallback;
                }
            }
            $slugs[ $key ] = $slug;
        }
        $unique = array_unique(array_values($slugs));
        if (count($unique) < count($slugs)) {
            $errors[] = __(
                'Each short URL must be a different word (for example: cart, checkout, account, learn, order).',
                'sikshya'
            );
        }
        if ($errors !== []) {
            return;
        }
        foreach ($slugs as $key => $slug) {
            Settings::set($key, $slug);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function persistLearnFromData(array $data): void
    {
        $use_pid = isset($data['learn_permalink_use_public_id'])
            ? sanitize_key(wp_unslash((string) $data['learn_permalink_use_public_id'])) : '1';
        Settings::set('learn_permalink_use_public_id', $use_pid === '0' ? '0' : '1');
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function persistUsageTrackingFromData(array $data): void
    {
        $raw = isset($data['allow_usage_tracking']) ? wp_unslash((string) $data['allow_usage_tracking']) : '0';
        $val = sanitize_key((string) $raw);
        $allow = ($val === '1' || $val === 'yes' || $val === 'on');
        Settings::set('allow_usage_tracking', $allow ? '1' : '0');

        // Keep the telemetry scheduler in sync with the wizard consent choice.
        // This mirrors Sikshya's welcome-step behavior (enable/disable triggers immediate send + schedules).
        if (class_exists('\\Sikshya\\Services\\StatsUsage')) {
            $u = \Sikshya\Services\StatsUsage::instance();
            if ($allow) {
                $u->enable(true);
            } else {
                $u->disable();
            }
        }
    }

    /**
     * Currency settings are stored as `_sikshya_currency*` options and used by `sikshya_format_price_plain()`.
     *
     * @param array<string, mixed> $data
     * @param string[] $errors
     */
    private static function persistCurrencyFromData(array $data, array &$errors): void
    {
        $valid_currencies = function_exists('sikshya_get_currencies')
            ? array_keys(sikshya_get_currencies())
            : ['USD'];
        $valid_positions = ['left', 'right', 'left_space', 'right_space'];
        $valid_decimals = ['0', '2', '3'];

        $currency = isset($data['currency']) ? strtoupper(sanitize_key(wp_unslash((string) $data['currency']))) : 'USD';
        if (!in_array($currency, $valid_currencies, true)) {
            $errors[] = __('Please choose a valid currency.', 'sikshya');
            return;
        }

        $pos = isset($data['currency_position']) ? sanitize_key(wp_unslash((string) $data['currency_position'])) : 'left';
        if (!in_array($pos, $valid_positions, true)) {
            $errors[] = __('Please choose a valid currency position.', 'sikshya');
            return;
        }

        $dec = isset($data['currency_decimal_places']) ? sanitize_key(wp_unslash((string) $data['currency_decimal_places'])) : '2';
        if (!in_array($dec, $valid_decimals, true)) {
            $dec = '2';
        }

        $thousand = isset($data['currency_thousand_separator']) ? (string) wp_unslash((string) $data['currency_thousand_separator']) : ',';
        $decimal = isset($data['currency_decimal_separator']) ? (string) wp_unslash((string) $data['currency_decimal_separator']) : '.';

        // Keep separators to 0-2 printable chars; avoid multi-byte weirdness.
        $thousand = substr(sanitize_text_field($thousand), 0, 2);
        $decimal = substr(sanitize_text_field($decimal), 0, 2);
        if ($thousand === '') $thousand = ',';
        if ($decimal === '') $decimal = '.';

        // If same, number_format becomes ambiguous.
        if ($thousand === $decimal) {
            $errors[] = __('Thousand and decimal separators must be different.', 'sikshya');
            return;
        }

        Settings::set('currency', $currency);
        Settings::set('currency_position', $pos);
        Settings::set('currency_decimal_places', $dec);
        Settings::set('currency_thousand_separator', $thousand);
        Settings::set('currency_decimal_separator', $decimal);
    }

    /**
     * Run the bundled sample-course import once, on demand.
     *
     * Imports `sample-data/sample-lms.json` (the `default` pack) via the
     * existing Tools importer and stashes the result in a per-user transient
     * so the celebration screen can show "Sample course added — created N
     * lessons…" right after the user clicks Finish setup.
     *
     * Always returns a normalized payload — never throws — so the wizard
     * can keep moving even when the importer hits an edge case.
     *
     * @return array{success: bool, message: string, counts: array<string,int>}
     */
    public static function importBundledSampleCourse(Plugin $plugin): array
    {
        try {
            $result = (new SampleDataController($plugin))->importByPackKey('default');
        } catch (\Throwable $e) {
            $result = [
                'success' => false,
                'message' => __('Sample data could not be imported.', 'sikshya'),
            ];
        }

        $payload = [
            'success' => !empty($result['success']),
            'message' => isset($result['message']) ? (string) $result['message'] : '',
            'counts' => isset($result['counts']) && is_array($result['counts']) ? $result['counts'] : [],
        ];

        // 5 minutes is plenty for the click → Finish setup → celebration
        // round-trip; the celebration renderer also deletes the transient
        // explicitly so it never lingers across logins.
        set_transient(
            self::SAMPLE_RESULT_TRANSIENT_PREFIX . get_current_user_id(),
            $payload,
            5 * MINUTE_IN_SECONDS
        );

        return $payload;
    }

    /**
     * Pop the sample-import result for the current user (read-once).
     *
     * @return array<string, mixed>
     */
    private static function popSampleImportResult(): array
    {
        $key = self::SAMPLE_RESULT_TRANSIENT_PREFIX . get_current_user_id();
        $stored = get_transient($key);
        if ($stored !== false) {
            delete_transient($key);
        }
        if (!is_array($stored)) {
            return [];
        }

        return $stored;
    }

    /**
     * Previous releases registered the wizard under Tools; keep bookmarks working.
     */
    public function maybeRedirectLegacyWizardAdminUrl(): void
    {
        if (!is_admin() || wp_doing_ajax()) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        global $pagenow;
        if ($pagenow !== 'tools.php') {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
        if ($page !== self::MENU_SLUG) {
            return;
        }

        wp_safe_redirect(self::adminUrl());
        exit;
    }

    /**
     * Process wizard form POSTs (save/skip) early — BEFORE WordPress prints the
     * admin head — so `wp_safe_redirect()` works without "headers already sent"
     * warnings. Bound to `admin_init`.
     */
    public function handleEarlyPost(): void
    {
        if (!is_admin() || wp_doing_ajax()) {
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
        if ($page !== self::MENU_SLUG) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        if (!isset($_POST['sikshya_setup_nonce'])) {
            return;
        }

        $transient_key = 'sikshya_setup_wizard_errors_' . get_current_user_id();

        $nonce = (string) wp_unslash($_POST['sikshya_setup_nonce']);
        if (!wp_verify_nonce($nonce, 'sikshya_setup_wizard')) {
            set_transient($transient_key, [__('Security check failed. Please refresh and try again.', 'sikshya')], 60);
            return;
        }

        $action = isset($_POST['wizard_action']) ? sanitize_key(wp_unslash((string) $_POST['wizard_action'])) : '';

        if ($action === 'skip') {
            Settings::set('setup_completed', '1');
            flush_rewrite_rules(false);
            do_action('sikshya_usage_setup_wizard_completed');
            wp_safe_redirect(admin_url('admin.php?page=sikshya'));
            exit;
        }

        if ($action === 'save') {
            $errors = [];
            $this->handleSave($errors);
            if ($errors === []) {
                wp_safe_redirect(self::doneUrl());
                exit;
            }
            set_transient($transient_key, $errors, 60);
        }
    }

    public function maybeRedirectToWizard(): void
    {
        if (!is_admin() || wp_doing_ajax()) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        if (Settings::isTruthy(Settings::get('setup_completed', '0'))) {
            // In case a stale redirect flag exists.
            if (Settings::getRaw('sikshya_setup_redirect', 0)) {
                Settings::setRaw('sikshya_setup_redirect', 0, false);
                delete_option('sikshya_setup_redirect');
            }
            return;
        }

        // Only redirect when activation set the flag.
        if (!Settings::getRaw('sikshya_setup_redirect', 0)) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
        if ($page === self::MENU_SLUG) {
            // User opened the wizard; do not keep forcing redirects elsewhere.
            Settings::setRaw('sikshya_setup_redirect', 0, false);
            delete_option('sikshya_setup_redirect');
            return;
        }

        Settings::setRaw('sikshya_setup_redirect', 0, false);
        delete_option('sikshya_setup_redirect');

        wp_safe_redirect(self::adminUrl());
        exit;
    }

    public function renderWizard(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        $step_key = self::parseStepFromRequest();
        $is_done = Settings::isTruthy(Settings::get('setup_completed', '0'));

        if ($step_key === 'done' && !$is_done) {
            wp_safe_redirect(self::adminUrl(5));
            exit;
        }

        // POSTs (save/skip) are processed early on `admin_init` via
        // self::handleEarlyPost() so redirects work safely. Any validation
        // errors from a save attempt are stashed in a per-user transient
        // and displayed here on the re-render.
        $transient_key = 'sikshya_setup_wizard_errors_' . get_current_user_id();
        $stored = get_transient($transient_key);
        $errors = [];
        if (is_array($stored) && $stored !== []) {
            // Keep only non-empty string messages — defensive against stale data.
            $errors = array_values(array_filter($stored, static function ($v) {
                return is_string($v) && trim($v) !== '';
            }));
            delete_transient($transient_key);
        } elseif ($stored !== false) {
            // Clear non-array junk if anything ever set the key incorrectly.
            delete_transient($transient_key);
        }

        $permalinks = PermalinkService::get();
        $learn_use_pid = PermalinkService::learnUsePublicId();

        $initial_step = $step_key === 'done' ? 1 : (int) $step_key;
        $initial_step = max(1, min(5, $initial_step));

        if ($errors !== [] && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_step'])) {
            $initial_step = max(1, min(5, (int) wp_unslash((string) $_POST['return_step'])));
        }

        $show_done = ($step_key === 'done' && $is_done);
        // Only the celebration screen consumes sample_import; reading it here
        // also pops the transient so it never bleeds into a re-visit.
        $sample_import = $show_done ? self::popSampleImportResult() : [];

        $this->plugin->getView()->render(
            'admin/setup-wizard',
            [
                'plugin' => $this->plugin,
                'wizard_page_url' => add_query_arg(
                    [
                        'page' => self::MENU_SLUG,
                        self::STEP_QUERY => (string) $initial_step,
                    ],
                    admin_url('admin.php')
                ),
                'errors' => $errors,
                'permalinks' => $permalinks,
                'learn_use_public_id' => $learn_use_pid,
                'initial_step' => $initial_step,
                'show_done' => $show_done,
                'sample_import' => $sample_import,
            ]
        );
    }

    /**
     * @param string[] $errors
     */
    private function handleSave(array &$errors): void
    {
        self::persistPermalinksFromData($_POST, $errors);
        self::persistCurrencyFromData($_POST, $errors);
        if ($errors !== []) {
            return;
        }
        self::persistLearnFromData($_POST);
        self::persistUsageTrackingFromData($_POST);
        Settings::set('setup_completed', '1');
        flush_rewrite_rules(false);
        do_action('sikshya_usage_setup_wizard_completed');
        // Sample-course import is now an explicit, button-driven action on
        // the Finish step — see self::importBundledSampleCourse() and the
        // dedicated REST endpoint. Finish setup itself just marks the wizard
        // complete.
    }
}

