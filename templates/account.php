<?php
/**
 * Standalone learner account shell (no theme header/footer). Separate URLs per section.
 *
 * @package Sikshya
 */

use Sikshya\Core\Plugin;
use Sikshya\Database\Repositories\OrderRepository;
use Sikshya\Frontend\Public\AccountTemplateData;
use Sikshya\Frontend\Public\PublicPageUrls;
use Sikshya\Services\PermalinkService;

// Legacy single-page anchors: ?section=orders|quiz-attempts
if (!empty($_GET['section'])) {
    $sec = sanitize_key((string) wp_unslash($_GET['section']));
    $legacy_map = [
        'orders' => 'payments',
        'quiz-attempts' => 'quiz-attempts',
    ];
    if (isset($legacy_map[$sec])) {
        wp_safe_redirect(PublicPageUrls::accountViewUrl($legacy_map[$sec]));
        exit;
    }
}

$raw_account_view = sanitize_key((string) get_query_var(PermalinkService::ACCOUNT_VIEW_VAR));
if ($raw_account_view !== '' && !in_array($raw_account_view, PublicPageUrls::allowedAccountViews(), true)) {
    wp_safe_redirect(PublicPageUrls::accountViewUrl('dashboard'));
    exit;
}

$plugin = Plugin::getInstance();
$acc = AccountTemplateData::build();

if ((int) $acc['user_id'] <= 0) {
    wp_safe_redirect(wp_login_url(PublicPageUrls::url('account')));
    exit;
}

$order_repo = new OrderRepository();

$hour = (int) wp_date('G');
if ($hour < 12) {
    $greeting = __('Good morning', 'sikshya');
} elseif ($hour < 17) {
    $greeting = __('Good afternoon', 'sikshya');
} else {
    $greeting = __('Good evening', 'sikshya');
}

$today_line = wp_date(get_option('date_format'));
$display_name = (string) ($acc['display_name'] ?? '');
$email = (string) ($acc['email'] ?? '');
$avatar_url = (string) ($acc['avatar_url'] ?? '');
$initial = $display_name !== '' ? strtoupper(function_exists('mb_substr') ? mb_substr($display_name, 0, 1) : substr($display_name, 0, 1)) : '?';

$sheet_ver = rawurlencode((string) $plugin->version);
$sheet_href = esc_url($plugin->getAssetUrl('css/account-shell.css')) . '?ver=' . $sheet_ver;

$view = (string) ($acc['account_view'] ?? 'dashboard');

$view_headlines = [
    'dashboard' => [
        'title' => __('Overview', 'sikshya'),
        'subtitle' => __('Summary, metrics, and quick links.', 'sikshya'),
    ],
    'learning' => [
        'title' => __('My learning', 'sikshya'),
        'subtitle' => __('Courses in progress and completed.', 'sikshya'),
    ],
    'payments' => [
        'title' => __('Payments', 'sikshya'),
        'subtitle' => __('Orders, receipts, and payment records.', 'sikshya'),
    ],
    'quiz-attempts' => [
        'title' => __('Quiz attempts', 'sikshya'),
        'subtitle' => __('Usage and limits for quizzes in your courses.', 'sikshya'),
    ],
    'instructor' => [
        'title' => __('Instructor overview', 'sikshya'),
        'subtitle' => __('Your authored courses, enrollments, and teaching tools.', 'sikshya'),
    ],
];
$head = $view_headlines[ $view ] ?? $view_headlines['dashboard'];

$page_title = sprintf(
    /* translators: 1: page name, 2: site name */
    '%1$s — %2$s',
    $view === 'dashboard' ? __('My account', 'sikshya') : (string) $head['title'],
    get_bloginfo('name')
);

$partial_map = [
    'dashboard' => 'dashboard',
    'learning' => 'learning',
    'payments' => 'payments',
    'quiz-attempts' => 'quiz-attempts',
    'instructor' => 'instructor',
];
$partial_name = $partial_map[ $view ] ?? 'dashboard';
$default_partial = $plugin->getTemplatePath('partials/account-view-' . $partial_name . '.php');
/**
 * Override path for an account section template (Pro / addons).
 *
 * @param string               $path Absolute path to PHP partial.
 * @param string               $view Current view slug.
 * @param array<string, mixed> $acc  Account data (same keys as {@see AccountTemplateData::build()}).
 */
$partial_path = apply_filters('sikshya_account_view_template', $default_partial, $view, $acc);
if (!is_string($partial_path) || !is_readable($partial_path)) {
    $partial_path = $plugin->getTemplatePath('partials/account-view-dashboard.php');
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html($page_title); ?></title>
    <link rel="stylesheet" href="<?php echo $sheet_href; ?>">
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
            <a class="<?php echo $view === 'dashboard' ? 'is-active' : ''; ?>" href="<?php echo esc_url($acc['urls']['account_dashboard']); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">◉</span>
                <?php esc_html_e('Overview', 'sikshya'); ?>
            </a>
            <a class="<?php echo $view === 'learning' ? 'is-active' : ''; ?>" href="<?php echo esc_url($acc['urls']['account_learning']); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">▣</span>
                <?php esc_html_e('My learning', 'sikshya'); ?>
            </a>
            <a class="<?php echo $view === 'quiz-attempts' ? 'is-active' : ''; ?>" href="<?php echo esc_url($acc['urls']['account_quiz_attempts']); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">◎</span>
                <?php esc_html_e('Quiz attempts', 'sikshya'); ?>
            </a>
            <a href="<?php echo esc_url($acc['urls']['courses']); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">▤</span>
                <?php esc_html_e('Courses', 'sikshya'); ?>
            </a>
            <a href="<?php echo esc_url($acc['urls']['learn']); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">▶</span>
                <?php esc_html_e('Learning hub', 'sikshya'); ?>
            </a>
            <p class="sik-acc-nav__label"><?php esc_html_e('Commerce', 'sikshya'); ?></p>
            <a class="<?php echo $view === 'payments' ? 'is-active' : ''; ?>" href="<?php echo esc_url($acc['urls']['account_payments']); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">≡</span>
                <?php esc_html_e('Payments', 'sikshya'); ?>
            </a>
            <a href="<?php echo esc_url($acc['urls']['cart']); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">◇</span>
                <?php esc_html_e('Cart', 'sikshya'); ?>
            </a>
            <a href="<?php echo esc_url($acc['urls']['checkout']); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">✓</span>
                <?php esc_html_e('Checkout', 'sikshya'); ?>
            </a>
        </nav>
        <?php
        /**
         * Extra sidebar links (Pro / addons). Echo anchor rows matching `.sik-acc-nav a`.
         *
         * @param array<string, mixed> $acc Account template data.
         * @param string $view Current account view slug.
         */
        do_action('sikshya_account_sidebar_nav', $acc, $view);
        ?>
        <div class="sik-acc-sidebar__footer">
            <a href="<?php echo esc_url($acc['urls']['home']); ?>">
                <span aria-hidden="true">←</span>
                <?php esc_html_e('Back to site', 'sikshya'); ?>
            </a>
        </div>
    </aside>

    <div class="sik-acc-main">
        <header class="sik-acc-topbar">
            <div class="sik-acc-topbar__titles">
                <h1><?php echo esc_html((string) $head['title']); ?></h1>
                <p><?php echo esc_html((string) $head['subtitle']); ?></p>
            </div>
            <div class="sik-acc-topbar__actions">
                <a class="sik-acc-btn" href="<?php echo esc_url($acc['urls']['home']); ?>"><?php esc_html_e('Back to site', 'sikshya'); ?></a>
                <div class="sik-acc-user">
                    <?php if ($avatar_url !== '') : ?>
                        <img src="<?php echo esc_url($avatar_url); ?>" width="36" height="36" alt="" loading="lazy" decoding="async">
                    <?php else : ?>
                        <span class="sik-acc-user__fallback" aria-hidden="true"><?php echo esc_html($initial); ?></span>
                    <?php endif; ?>
                    <div class="sik-acc-user__meta">
                        <div class="sik-acc-user__name"><?php echo esc_html($display_name); ?></div>
                        <?php if ($email !== '') : ?>
                            <div class="sik-acc-user__email"><?php echo esc_html($email); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <a class="sik-acc-btn sik-acc-btn--primary" href="<?php echo esc_url(wp_logout_url($acc['urls']['home'])); ?>"><?php esc_html_e('Log out', 'sikshya'); ?></a>
            </div>
        </header>

        <main class="sik-acc-content">
            <?php include $partial_path; ?>
        </main>
    </div>
</div>
</body>
</html>
