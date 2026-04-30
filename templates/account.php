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

$label_course = function_exists('sikshya_label') ? sikshya_label('course', __('Course', 'sikshya'), 'frontend') : __('Course', 'sikshya');
$label_courses = function_exists('sikshya_label_plural') ? sikshya_label_plural('course', 'courses', __('Courses', 'sikshya'), 'frontend') : __('Courses', 'sikshya');
$label_quiz = function_exists('sikshya_label') ? sikshya_label('quiz', __('Quiz', 'sikshya'), 'frontend') : __('Quiz', 'sikshya');
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
<a class="sik-acc-skip-link" href="#sik-acc-main"><?php esc_html_e('Skip to content', 'sikshya'); ?></a>
<input type="checkbox" id="sik-acc-nav-open" class="sik-acc-nav-state" tabindex="-1" aria-hidden="true">
<div class="sik-acc-app">
    <aside class="sik-acc-sidebar" aria-label="<?php esc_attr_e('Account navigation', 'sikshya'); ?>">
        <div class="sik-acc-sidebar__brand">
            <p class="sik-acc-sidebar__logo"><?php echo esc_html(get_bloginfo('name')); ?></p>
        </div>
        <nav class="sik-acc-nav" aria-label="<?php esc_attr_e('Primary', 'sikshya'); ?>">
            <p class="sik-acc-nav__label"><?php esc_html_e('Learn', 'sikshya'); ?></p>
            <a class="<?php echo $view === 'dashboard' ? 'is-active' : ''; ?>" href="<?php echo esc_url($page_model->getUrls()->getDashboardUrl()); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">⌂</span>
                <?php esc_html_e('Overview', 'sikshya'); ?>
            </a>
            <a class="<?php echo $view === 'learning' ? 'is-active' : ''; ?>" href="<?php echo esc_url($page_model->getUrls()->getLearningUrl()); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">▦</span>
                <?php esc_html_e('My learning', 'sikshya'); ?>
            </a>
            <a href="<?php echo esc_url($page_model->getUrls()->getLearnHubUrl()); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">▶</span>
                <?php esc_html_e('Learning hub', 'sikshya'); ?>
            </a>
            <a class="<?php echo $view === 'quiz-attempts' ? 'is-active' : ''; ?>" href="<?php echo esc_url($page_model->getUrls()->getQuizAttemptsUrl()); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">◎</span>
                <?php
                echo esc_html(sprintf(
                    /* translators: %s: singular label (e.g. Quiz) */
                    __('%s attempts', 'sikshya'),
                    $label_quiz
                ));
                ?>
            </a>
            <a class="<?php echo $view === 'profile' ? 'is-active' : ''; ?>" href="<?php echo esc_url($page_model->getUrls()->getProfileUrl()); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">⚙</span>
                <?php esc_html_e('Profile & security', 'sikshya'); ?>
            </a>
            <a href="<?php echo esc_url($page_model->getUrls()->getCoursesUrl()); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">▥</span>
                <?php echo esc_html($label_courses); ?>
            </a>
            <?php
            /**
             * Extra "Learn" links placed right after Learning hub.
             * Passed values: legacy `$acc` array, current `$view` slug, and `$page_model`.
             */
            do_action('sikshya_account_sidebar_nav_after_learning_hub', $acc, $view, $page_model);
            ?>
            <p class="sik-acc-nav__label"><?php esc_html_e('Commerce', 'sikshya'); ?></p>
            <p class="sik-acc-nav__hint">
                <?php esc_html_e('View order history, receipts, and invoices.', 'sikshya'); ?>
            </p>
            <a class="<?php echo $view === 'payments' ? 'is-active' : ''; ?>" href="<?php echo esc_url($page_model->getUrls()->getPaymentsUrl()); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">≡</span>
                <?php esc_html_e('My orders & payments', 'sikshya'); ?>
            </a>
            <?php
            /*
             * Extra sidebar links (Pro / addons). Must stay inside `<nav class="sik-acc-nav">`
             * so anchors match `.sik-acc-nav a` (same padding, no underline, active state).
             */
            do_action('sikshya_account_sidebar_nav', $acc, $view);
            ?>
        </nav>
        <div class="sik-acc-sidebar__footer">
            <a class="sik-acc-footer-link" href="<?php echo esc_url($page_model->getUrls()->getHomeUrl()); ?>">
                <span class="sik-acc-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">
                        <path d="M15 18l-6-6 6-6" />
                    </svg>
                </span>
                <?php esc_html_e('Back to site', 'sikshya'); ?>
            </a>
            <a class="sik-acc-footer-link" href="<?php echo esc_url(wp_logout_url($page_model->getUrls()->getHomeUrl())); ?>">
                <span class="sik-acc-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                        <path d="M16 17l5-5-5-5" />
                        <path d="M21 12H9" />
                    </svg>
                </span>
                <?php esc_html_e('Log out', 'sikshya'); ?>
            </a>
        </div>
    </aside>

    <label class="sik-acc-nav-scrim" for="sik-acc-nav-open"><span class="sik-acc-sr-only"><?php esc_html_e('Close menu', 'sikshya'); ?></span></label>

    <div class="sik-acc-main" id="sik-acc-main">
        <header class="sik-acc-topbar">
            <label class="sik-acc-nav-toggle" for="sik-acc-nav-open">
                <span class="sik-acc-nav-toggle__bars" aria-hidden="true"></span>
                <span class="sik-acc-nav-toggle__text"><?php esc_html_e('Menu', 'sikshya'); ?></span>
            </label>
            <div class="sik-acc-topbar__titles">
                <h1><?php echo esc_html($page_model->getHeadlineTitle()); ?></h1>
                <p><?php echo esc_html($page_model->getHeadlineSubtitle()); ?></p>
            </div>
            <div class="sik-acc-topbar__actions">
                <a class="sik-acc-btn" href="<?php echo esc_url($page_model->getUrls()->getHomeUrl()); ?>">
                    <span class="sik-acc-icon" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">
                            <path d="M15 18l-6-6 6-6" />
                        </svg>
                    </span>
                    <?php esc_html_e('Back to site', 'sikshya'); ?>
                </a>

                <div class="sik-acc-user-menu">
                    <button class="sik-acc-user-btn" type="button" aria-haspopup="menu" aria-expanded="false">
                        <span class="sik-acc-user">
                            <?php if ($page_model->getAvatarUrl() !== '') : ?>
                                <img src="<?php echo esc_url($page_model->getAvatarUrl()); ?>" width="36" height="36" alt="" loading="lazy" decoding="async">
                            <?php else : ?>
                                <span class="sik-acc-user__fallback" aria-hidden="true"><?php echo esc_html($page_model->getInitial()); ?></span>
                            <?php endif; ?>
                            <span class="sik-acc-user__meta">
                                <span class="sik-acc-user__name"><?php echo esc_html($page_model->getDisplayName()); ?></span>
                                <?php if ($page_model->getEmail() !== '') : ?>
                                    <span class="sik-acc-user__email"><?php echo esc_html($page_model->getEmail()); ?></span>
                                <?php endif; ?>
                            </span>
                        </span>
                        <span class="sik-acc-user-caret" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">
                                <path d="M6 9l6 6 6-6" />
                            </svg>
                        </span>
                    </button>
                    <div class="sik-acc-user-dropdown" role="menu" aria-label="<?php esc_attr_e('Account menu', 'sikshya'); ?>">
                        <a role="menuitem" href="<?php echo esc_url(wp_logout_url($page_model->getUrls()->getHomeUrl())); ?>">
                            <span class="sik-acc-icon" aria-hidden="true">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                                    <path d="M16 17l5-5-5-5" />
                                    <path d="M21 12H9" />
                                </svg>
                            </span>
                            <?php esc_html_e('Log out', 'sikshya'); ?>
                        </a>
                    </div>
                </div>
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
<script>
  (function () {
    var root = document.documentElement;
    var menu = document.querySelector('.sik-acc-user-menu');
    if (!menu) return;
    var btn = menu.querySelector('.sik-acc-user-btn');
    var dd = menu.querySelector('.sik-acc-user-dropdown');
    if (!btn || !dd) return;

    function closeMenu() {
      menu.classList.remove('is-open');
      btn.setAttribute('aria-expanded', 'false');
    }
    function openMenu() {
      menu.classList.add('is-open');
      btn.setAttribute('aria-expanded', 'true');
    }
    function toggle() {
      if (menu.classList.contains('is-open')) closeMenu();
      else openMenu();
    }

    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      toggle();
    });
    document.addEventListener('click', function (e) {
      if (!menu.contains(e.target)) closeMenu();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeMenu();
    });
    // Ensure we never leave stale open menus on page transitions.
    root.addEventListener('focusin', function (e) {
      if (!menu.contains(e.target)) closeMenu();
    });
  })();
</script>
</body>
</html>
