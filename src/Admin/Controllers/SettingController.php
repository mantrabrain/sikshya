<?php

namespace Sikshya\Admin\Controllers;

use Sikshya\Admin\ReactAdminView;
use Sikshya\Admin\Settings\SettingsManager;
use Sikshya\Core\Plugin;
use Sikshya\Admin\Views\BaseView;

/**
 * Setting Controller Class
 *
 * @package Sikshya\Admin\Controllers
 * @since 1.0.0
 */
class SettingController extends BaseView
{
    /**
     * Settings Manager instance
     *
     * @var SettingsManager
     */
    protected SettingsManager $settingsManager;

    /**
     * Constructor
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        parent::__construct($plugin);
        $this->settingsManager = new SettingsManager($plugin);
        $this->initHooks();
    }

    /**
     * Initialize hooks
     */
    private function initHooks(): void
    {
        // Admin UI uses REST + React.
    }

    /**
     * Render settings page
     */
    public function renderSettingsPage(): void
    {
        ReactAdminView::render('settings', []);
    }

    /**
     * Enqueue assets
     */
    public function enqueueAssets(): void
    {
        // Settings UI is now the React shell (`admin.php?page=sikshya&view=settings`).
        // The legacy `sikshya-admin` jQuery bundle is deprecated and no longer enqueued.
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $screen_id = $screen ? (string) ($screen->id ?? '') : '';
        if ($screen_id === 'toplevel_page_sikshya') {
            return;
        }

        wp_enqueue_style('sikshya-admin');
    }
}
