<?php

namespace Sikshya\Services;

use PHPMailer\PHPMailer\PHPMailer;
use Sikshya\Addons\Addons;
use Sikshya\Licensing\Pro;

/**
 * Optional SMTP transport for wp_mail (Growth+ when {@see FeatureRegistry} email_advanced_customization is licensed).
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
     * @param PHPMailer $phpmailer
     */
    public static function configure($phpmailer): void
    {
        if (!$phpmailer instanceof PHPMailer) {
            return;
        }

        if (!Pro::feature('email_advanced_customization') || !Addons::isEnabled('email_advanced_customization')) {
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
            $phpmailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($enc === 'tls') {
            $phpmailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
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
