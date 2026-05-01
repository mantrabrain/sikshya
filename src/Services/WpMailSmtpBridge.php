<?php

namespace Sikshya\Services;

use Sikshya\Addons\Addons;
use Sikshya\Licensing\TierCapabilities;

/**
 * Optional SMTP transport for wp_mail (commercial tier when email_advanced_customization is licensed).
 *
 * Uses the PHPMailer instance WordPress provides on {@see 'phpmailer_init'} — no extra Composer package.
 *
 * @package Sikshya\Services
 */
final class WpMailSmtpBridge
{
    public static function register(): void
    {
        add_action('phpmailer_init', [self::class, 'configure'], 10, 1);
    }

    /**
     * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer
     */
    public static function configure($phpmailer): void
    {
        if (!is_object($phpmailer) || !method_exists($phpmailer, 'isSMTP')) {
            return;
        }

        if (!TierCapabilities::feature('email_advanced_customization') || !Addons::isEnabled('email_advanced_customization')) {
            return;
        }

        if (!Settings::isTruthy(Settings::get('smtp_enabled'))) {
            return;
        }

        $host = trim((string) Settings::get('smtp_host', ''));
        if ($host === '') {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->CharSet = 'UTF-8';
        $phpmailer->Timeout = max(5, min(120, (int) Settings::get('smtp_timeout', 30)));
        $phpmailer->Host = $host;

        $port = (int) Settings::get('smtp_port', 587);
        $phpmailer->Port = $port > 0 && $port <= 65535 ? $port : 587;

        $enc = strtolower((string) Settings::get('smtp_encryption', 'tls'));
        if ($enc === 'ssl') {
            $phpmailer->SMTPSecure = 'ssl';
        } elseif ($enc === 'tls') {
            $phpmailer->SMTPSecure = 'tls';
        } else {
            $phpmailer->SMTPSecure = '';
        }

        $user = trim((string) Settings::get('smtp_username', ''));
        $pass = (string) Settings::get('smtp_password', '');

        if ($user !== '') {
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = $user;
            $phpmailer->Password = $pass;
        } else {
            $phpmailer->SMTPAuth = false;
        }
    }
}
