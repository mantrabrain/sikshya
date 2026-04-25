<?php
/**
 * Standalone learner account shell (no theme header/footer). Separate URLs per section.
 *
 * @package Sikshya
 */

use Sikshya\Core\Plugin;
use Sikshya\Services\Frontend\AccountPageService;

$plugin = Plugin::getInstance();
$page_model = AccountPageService::fromRequest();
$acc = $page_model->toLegacyViewArray(); // Back-compat for hooks/filters.

$sheet_ver = rawurlencode((string) $plugin->version);
$sheet_href = esc_url($plugin->getAssetUrl('css/account-shell.css')) . '?ver=' . $sheet_ver;

$view = $page_model->getView();
$partial_path = AccountPageService::resolvePartialPath($page_model);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html($page_model->getPageTitle()); ?></title>
    <link rel="stylesheet" href="<?php echo $sheet_href; ?>">
    <?php
    /*
     * Minimal extension point for account-shell pages.
     * Note: This is NOT wp_head() intentionally (keeps the shell clean).
     */
    do_action('sikshya_account_shell_head', $acc, $view, $page_model);
    ?>
</head>
<body class="sikshya-account-shell" data-sikshya-account-view="<?php echo esc_attr($view); ?>">
<div class="sik-acc-app">
    <aside class="sik-acc-sidebar" aria-label="<?php esc_attr_e('Account navigation', 'sikshya'); ?>">
        <div class="sik-acc-sidebar__brand">
            <p class="sik-acc-sidebar__logo"><?php echo esc_html(get_bloginfo('name')); ?></p>
            <p class="sik-acc-sidebar__version"><?php echo esc_html(sprintf(__('LMS v%s', 'sikshya'), SIKSHYA_VERSION)); ?></p>
        </div>
        <nav class="sik-acc-nav" aria-label="<?php esc_attr_e('Primary', 'sikshya'); ?>">
            <p class="sik-acc-nav__label"><?php esc_html_e('Learn', 'sikshya'); ?></p>
            <a class="<?php echo $view === 'dashboard' ? 'is-active' : ''; ?>" href="<?php echo esc_url($page_model->getUrls()->getDashboardUrl()); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">◉</span>
                <?php esc_html_e('Overview', 'sikshya'); ?>
            </a>
            <a class="<?php echo $view === 'learning' ? 'is-active' : ''; ?>" href="<?php echo esc_url($page_model->getUrls()->getLearningUrl()); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">▣</span>
                <?php esc_html_e('My learning', 'sikshya'); ?>
            </a>
            <a class="<?php echo $view === 'quiz-attempts' ? 'is-active' : ''; ?>" href="<?php echo esc_url($page_model->getUrls()->getQuizAttemptsUrl()); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">◎</span>
                <?php esc_html_e('Quiz attempts', 'sikshya'); ?>
            </a>
            <a href="<?php echo esc_url($page_model->getUrls()->getCoursesUrl()); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">▤</span>
                <?php esc_html_e('Courses', 'sikshya'); ?>
            </a>
            <a href="<?php echo esc_url($page_model->getUrls()->getLearnHubUrl()); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">▶</span>
                <?php esc_html_e('Learning hub', 'sikshya'); ?>
            </a>
            <p class="sik-acc-nav__label"><?php esc_html_e('Commerce', 'sikshya'); ?></p>
            <a class="<?php echo $view === 'payments' ? 'is-active' : ''; ?>" href="<?php echo esc_url($page_model->getUrls()->getPaymentsUrl()); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">≡</span>
                <?php esc_html_e('Payments', 'sikshya'); ?>
            </a>
            <a href="<?php echo esc_url($page_model->getUrls()->getCartUrl()); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">◇</span>
                <?php esc_html_e('Cart', 'sikshya'); ?>
            </a>
            <a href="<?php echo esc_url($page_model->getUrls()->getCheckoutUrl()); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">✓</span>
                <?php esc_html_e('Checkout', 'sikshya'); ?>
            </a>
        </nav>
        <?php
        /*
         * Extra sidebar links (Pro / addons). Echo anchor rows matching `.sik-acc-nav a`.
         */
        do_action('sikshya_account_sidebar_nav', $acc, $view);
        ?>
        <div class="sik-acc-sidebar__footer">
            <a href="<?php echo esc_url($page_model->getUrls()->getHomeUrl()); ?>">
                <span aria-hidden="true">←</span>
                <?php esc_html_e('Back to site', 'sikshya'); ?>
            </a>
        </div>
    </aside>

    <div class="sik-acc-main">
        <header class="sik-acc-topbar">
            <div class="sik-acc-topbar__titles">
                <h1><?php echo esc_html($page_model->getHeadlineTitle()); ?></h1>
                <p><?php echo esc_html($page_model->getHeadlineSubtitle()); ?></p>
            </div>
            <div class="sik-acc-topbar__actions">
                <a class="sik-acc-btn" href="<?php echo esc_url($page_model->getUrls()->getHomeUrl()); ?>"><?php esc_html_e('Back to site', 'sikshya'); ?></a>
                <div class="sik-acc-user">
                    <?php if ($page_model->getAvatarUrl() !== '') : ?>
                        <img src="<?php echo esc_url($page_model->getAvatarUrl()); ?>" width="36" height="36" alt="" loading="lazy" decoding="async">
                    <?php else : ?>
                        <span class="sik-acc-user__fallback" aria-hidden="true"><?php echo esc_html($page_model->getInitial()); ?></span>
                    <?php endif; ?>
                    <div class="sik-acc-user__meta">
                        <div class="sik-acc-user__name"><?php echo esc_html($page_model->getDisplayName()); ?></div>
                        <?php if ($page_model->getEmail() !== '') : ?>
                            <div class="sik-acc-user__email"><?php echo esc_html($page_model->getEmail()); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <a class="sik-acc-btn sik-acc-btn--primary" href="<?php echo esc_url(wp_logout_url($page_model->getUrls()->getHomeUrl())); ?>"><?php esc_html_e('Log out', 'sikshya'); ?></a>
            </div>
        </header>

        <main class="sik-acc-content">
            <?php
            // Expose model to partials; keep $acc for legacy hooks.
            include $partial_path;
            ?>
        </main>
    </div>
</div>
<?php
/*
 * Minimal footer hook for account shell pages (for add-ons scripts).
 * Note: This is NOT wp_footer() intentionally.
 */
do_action('sikshya_account_shell_footer', $acc, $view, $page_model);
?>
</body>
</html>
