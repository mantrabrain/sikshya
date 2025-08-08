<?php

namespace Sikshya\Frontend\Controllers;

use Sikshya\Core\Plugin;

/**
 * Frontend Certificate Controller
 *
 * @package Sikshya\Frontend\Controllers
 */
class CertificateController
{
    /**
     * Plugin instance
     *
     * @var Plugin
     */
    private Plugin $plugin;

    /**
     * Constructor
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Display certificates page
     */
    public function certificates(): void
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_redirect(wp_login_url());
            exit;
        }
        $certificates = $this->plugin->getService('certificate')->getUserCertificates($user_id);
        include $this->plugin->getTemplatePath('frontend/certificates.php');
    }

    /**
     * Handle AJAX requests
     */
    public function handleAjax(string $action): void
    {
        switch ($action) {
            case 'download_certificate':
                $this->downloadCertificate();
                break;
            default:
                wp_send_json_error(__('Invalid action.', 'sikshya'));
        }
    }

    /**
     * Download certificate
     */
    private function downloadCertificate(): void
    {
        $certificate_id = intval($_POST['certificate_id'] ?? 0);
        $user_id = get_current_user_id();
        if (!$user_id || !$certificate_id) {
            wp_send_json_error(__('Invalid request.', 'sikshya'));
        }
        $result = $this->plugin->getService('certificate')->downloadCertificate($certificate_id, $user_id);
        if ($result['success']) {
            wp_send_json_success(['url' => $result['url']]);
        } else {
            wp_send_json_error($result['message'] ?? __('Failed to download certificate.', 'sikshya'));
        }
    }
} 