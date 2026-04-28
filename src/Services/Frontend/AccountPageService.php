<?php

/**
 * @package Sikshya\Services\Frontend
 */

namespace Sikshya\Services\Frontend;

use Sikshya\Core\Plugin;
use Sikshya\Frontend\Public\AccountTemplateData;
use Sikshya\Frontend\Public\PublicPageUrls;
use Sikshya\Presentation\Models\AccountPageModel;
use Sikshya\Services\PermalinkService;

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

final class AccountPageService
{
    /**
     * Handles legacy redirects/guards and builds the model.
     *
     * Note: redirects are intentional (template safety + old URLs).
     */
    public static function fromRequest(): AccountPageModel
    {
        // Legacy single-page anchors: ?section=orders|quiz-attempts
        if (!empty($_GET['section'])) {
            $sec = sanitize_key((string) wp_unslash($_GET['section']));
            $legacyMap = [
                'orders' => 'payments',
                'quiz-attempts' => 'quiz-attempts',
            ];
            if (isset($legacyMap[$sec])) {
                wp_safe_redirect(PublicPageUrls::accountViewUrl($legacyMap[$sec]));
                exit;
            }
        }

        $rawView = sanitize_key((string) get_query_var(PermalinkService::ACCOUNT_VIEW_VAR));
        if ($rawView !== '' && !in_array($rawView, PublicPageUrls::allowedAccountViews(), true)) {
            wp_safe_redirect(PublicPageUrls::accountViewUrl('dashboard'));
            exit;
        }

        $legacy = AccountTemplateData::build();
        $page = AccountPageModel::fromLegacy($legacy);

        if ($page->getUserId() <= 0) {
            wp_safe_redirect(PublicPageUrls::login(PublicPageUrls::url('account')));
            exit;
        }

        return $page;
    }

    /**
     * Resolves the account section partial path (supports Pro/addon override filter).
     *
     * @return string absolute path
     */
    public static function resolvePartialPath(AccountPageModel $page): string
    {
        $plugin = Plugin::getInstance();
        $legacy = $page->toLegacyViewArray();
        $view = $page->getView();

        $partialMap = [
            'dashboard' => 'dashboard',
            'learning' => 'learning',
            'payments' => 'payments',
            'quiz-attempts' => 'quiz-attempts',
            'instructor' => 'instructor',
        ];

        $partialName = $partialMap[$view] ?? 'dashboard';
        $defaultPartial = $plugin->getTemplatePath('partials/account-view-' . $partialName . '.php');

        /**
         * Override path for an account section template (Pro / addons).
         *
         */
        $partialPath = apply_filters('sikshya_account_view_template', $defaultPartial, $view, $legacy);
        if (!is_string($partialPath) || !is_readable($partialPath)) {
            $partialPath = $plugin->getTemplatePath('partials/account-view-dashboard.php');
        }

        return (string) $partialPath;
    }
}

