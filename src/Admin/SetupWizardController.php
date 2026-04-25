<?php

namespace Sikshya\Admin;

use Sikshya\Core\Plugin;
use Sikshya\Services\PermalinkService;
use Sikshya\Services\Settings;

final class SetupWizardController
{
    public const MENU_SLUG = 'sikshya-setup';

    private Plugin $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
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

        // Avoid redirect loops.
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
        if ($page === self::MENU_SLUG) {
            return;
        }

        delete_option('sikshya_setup_redirect');

        wp_safe_redirect(
            add_query_arg(
                [
                    'page' => self::MENU_SLUG,
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }

    public function renderWizard(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'sikshya'));
        }

        $saved = false;
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sikshya_setup_nonce'])) {
            $nonce = (string) wp_unslash($_POST['sikshya_setup_nonce']);
            if (!wp_verify_nonce($nonce, 'sikshya_setup_wizard')) {
                $errors[] = __('Security check failed. Please refresh and try again.', 'sikshya');
            } else {
                $action = isset($_POST['wizard_action']) ? sanitize_key(wp_unslash((string) $_POST['wizard_action'])) : '';

                if ($action === 'save') {
                    $this->handleSave($errors);
                    $saved = $errors === [];
                } elseif ($action === 'skip') {
                    Settings::set('setup_completed', '1');
                    wp_safe_redirect(admin_url('admin.php?page=sikshya'));
                    exit;
                }
            }
        }

        $permalinks = PermalinkService::get();
        $learn_use_pid = PermalinkService::learnUsePublicId();

        $this->plugin->getView()->render(
            'admin/setup-wizard',
            [
                'plugin' => $this->plugin,
                'saved' => $saved,
                'errors' => $errors,
                'permalinks' => $permalinks,
                'learn_use_public_id' => $learn_use_pid,
            ]
        );
    }

    /**
     * @param string[] $errors
     */
    private function handleSave(array &$errors): void
    {
        $map = [
            'permalink_cart' => 'cart',
            'permalink_checkout' => 'checkout',
            'permalink_account' => 'account',
            'permalink_learn' => 'learn',
            'permalink_order' => 'order',
        ];

        foreach ($map as $key => $fallback) {
            $raw = isset($_POST[$key]) ? (string) wp_unslash($_POST[$key]) : '';
            $slug = PermalinkService::sanitizeSlug($raw);
            if ($slug === '') {
                $slug = $fallback;
            }
            Settings::set($key, $slug);
        }

        $use_pid = isset($_POST['learn_permalink_use_public_id']) ? sanitize_key(wp_unslash((string) $_POST['learn_permalink_use_public_id'])) : '1';
        Settings::set('learn_permalink_use_public_id', $use_pid === '0' ? '0' : '1');

        Settings::set('setup_completed', '1');
    }
}

