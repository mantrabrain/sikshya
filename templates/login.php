<?php
/**
 * Login (virtual page) — uses WordPress auth but in Sikshya UI.
 *
 * @package Sikshya
 */

use Sikshya\Frontend\Public\PublicPageUrls;
use Sikshya\Shortcodes\AuthShortcodes;

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
    <div class="sikshya-login-page__shell">
        <div class="sikshya-login-page__inner">
            <h1 class="sikshya-login-page__title">
                <?php esc_html_e('Log in', 'sikshya'); ?>
            </h1>
            <p class="sikshya-login-page__lead">
                <?php esc_html_e('Log in to continue to checkout or access your learning.', 'sikshya'); ?>
            </p>

            <div class="sikshya-login-page__card">
                <?php
                echo AuthShortcodes::renderLogin(['redirect_to' => $redirect_to]);
                ?>
            </div>
        </div>
    </div>
</div>

<?php
sikshya_get_footer();

