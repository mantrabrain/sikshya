<?php
/**
 * Login (virtual page) — uses WordPress auth but in Sikshya UI.
 *
 * @package Sikshya
 */

use Sikshya\Frontend\Public\PublicPageUrls;

sikshya_get_header();

// Already logged in: go to redirect target or account.
if (is_user_logged_in()) {
    $redir = isset($_GET['redirect_to']) ? (string) wp_unslash((string) $_GET['redirect_to']) : '';
    $redir = $redir !== '' ? esc_url_raw($redir) : '';
    wp_safe_redirect($redir !== '' ? $redir : PublicPageUrls::url('account'));
    exit;
}

$redirect_to = isset($_GET['redirect_to']) ? (string) wp_unslash((string) $_GET['redirect_to']) : '';
// If param is encoded already (we add it encoded), wp_login_form expects raw URL string.
$redirect_to = $redirect_to !== '' ? rawurldecode((string) $redirect_to) : '';
if ($redirect_to === '') {
    $redirect_to = home_url('/');
}
?>

<div class="sikshya-public sikshya-login-page sik-f-scope">
    <div class="sikshya-login-page__wrap" style="max-width:560px;margin:0 auto;padding:40px 16px;">
        <h1 style="margin:0 0 10px;font-size:26px;font-weight:700;">
            <?php esc_html_e('Log in', 'sikshya'); ?>
        </h1>
        <p style="margin:0 0 22px;color:#64748b;">
            <?php esc_html_e('Log in to continue to checkout or access your learning.', 'sikshya'); ?>
        </p>

        <div class="sikshya-login-page__card" style="background:#fff;border:1px solid rgba(226,232,240,1);border-radius:14px;padding:18px 18px 6px;">
            <?php
            echo wp_login_form(
                [
                    'echo' => false,
                    'redirect' => $redirect_to,
                    'remember' => true,
                    'label_username' => __('Email or username', 'sikshya'),
                    'label_password' => __('Password', 'sikshya'),
                    'label_remember' => __('Remember me', 'sikshya'),
                    'label_log_in' => __('Log in', 'sikshya'),
                ]
            );
            ?>
        </div>
    </div>
</div>

<?php
sikshya_get_footer();

