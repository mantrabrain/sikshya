<?php
/**
 * Standalone learner account shell (no theme header/footer). Dashboard-style layout.
 *
 * @package Sikshya
 */

use Sikshya\Core\Plugin;
use Sikshya\Database\Repositories\OrderRepository;
use Sikshya\Frontend\Public\AccountTemplateData;
use Sikshya\Frontend\Public\PublicPageUrls;

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

$page_title = sprintf(
    /* translators: 1: page name, 2: site name */
    '%1$s — %2$s',
    __('My account', 'sikshya'),
    get_bloginfo('name')
);
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
<body class="sikshya-account-shell">
<div class="sik-acc-app">
    <aside class="sik-acc-sidebar" aria-label="<?php esc_attr_e('Account navigation', 'sikshya'); ?>">
        <div class="sik-acc-sidebar__brand">
            <p class="sik-acc-sidebar__logo"><?php echo esc_html(get_bloginfo('name')); ?></p>
            <p class="sik-acc-sidebar__version"><?php echo esc_html(sprintf(__('LMS v%s', 'sikshya'), SIKSHYA_VERSION)); ?></p>
        </div>
        <nav class="sik-acc-nav" aria-label="<?php esc_attr_e('Primary', 'sikshya'); ?>">
            <p class="sik-acc-nav__label"><?php esc_html_e('Learn', 'sikshya'); ?></p>
            <a class="is-active" href="<?php echo esc_url($acc['urls']['account']); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">◉</span>
                <?php esc_html_e('Account', 'sikshya'); ?>
            </a>
            <a href="<?php echo esc_url($acc['urls']['courses']); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">▣</span>
                <?php esc_html_e('Courses', 'sikshya'); ?>
            </a>
            <a href="<?php echo esc_url($acc['urls']['learn']); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">▶</span>
                <?php esc_html_e('Learn', 'sikshya'); ?>
            </a>
            <p class="sik-acc-nav__label"><?php esc_html_e('Commerce', 'sikshya'); ?></p>
            <a href="<?php echo esc_url($acc['urls']['cart']); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">◇</span>
                <?php esc_html_e('Cart', 'sikshya'); ?>
            </a>
            <a href="<?php echo esc_url($acc['urls']['checkout']); ?>">
                <span class="sik-acc-nav__icon" aria-hidden="true">✓</span>
                <?php esc_html_e('Checkout', 'sikshya'); ?>
            </a>
            <a href="<?php echo esc_url($acc['urls']['account']); ?>#orders">
                <span class="sik-acc-nav__icon" aria-hidden="true">≡</span>
                <?php esc_html_e('Orders', 'sikshya'); ?>
            </a>
        </nav>
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
                <h1><?php esc_html_e('Account', 'sikshya'); ?></h1>
                <p><?php esc_html_e('Your enrollments, orders, and shortcuts in one place.', 'sikshya'); ?></p>
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
            <section class="sik-acc-hero" aria-label="<?php esc_attr_e('Welcome', 'sikshya'); ?>">
                <p class="sik-acc-hero__date"><?php echo esc_html($today_line); ?></p>
                <h2 class="sik-acc-hero__greet">
                    <?php
                    printf(
                        /* translators: 1: time-of-day greeting, 2: display name */
                        esc_html__('%1$s, %2$s', 'sikshya'),
                        esc_html($greeting),
                        esc_html($display_name)
                    );
                    ?>
                </h2>
                <p class="sik-acc-hero__lead">
                    <?php esc_html_e('Pick up where you left off, review purchases, or explore new courses from your catalog.', 'sikshya'); ?>
                </p>
            </section>

            <section class="sik-acc-metrics" aria-label="<?php esc_attr_e('Summary', 'sikshya'); ?>">
                <div class="sik-acc-metric">
                    <div class="sik-acc-metric__value"><?php echo esc_html((string) (int) $acc['enrollment_count']); ?></div>
                    <div class="sik-acc-metric__label"><?php esc_html_e('Enrolled courses', 'sikshya'); ?></div>
                    <div class="sik-acc-metric__hint"><?php esc_html_e('Active seats in your library', 'sikshya'); ?></div>
                </div>
                <div class="sik-acc-metric">
                    <div class="sik-acc-metric__value"><?php echo esc_html((string) (int) $acc['orders_count']); ?></div>
                    <div class="sik-acc-metric__label"><?php esc_html_e('Orders', 'sikshya'); ?></div>
                    <div class="sik-acc-metric__hint"><?php esc_html_e('Receipts and payment history', 'sikshya'); ?></div>
                </div>
                <a class="sik-acc-metric sik-acc-metric--link" href="<?php echo esc_url($acc['urls']['courses']); ?>">
                    <div class="sik-acc-metric__value"><?php esc_html_e('Browse', 'sikshya'); ?> →</div>
                    <div class="sik-acc-metric__label"><?php esc_html_e('Course catalog', 'sikshya'); ?></div>
                    <div class="sik-acc-metric__hint"><?php esc_html_e('Discover your next lesson', 'sikshya'); ?></div>
                </a>
                <a class="sik-acc-metric sik-acc-metric--link" href="<?php echo esc_url($acc['urls']['learn']); ?>">
                    <div class="sik-acc-metric__value"><?php esc_html_e('Continue', 'sikshya'); ?> →</div>
                    <div class="sik-acc-metric__label"><?php esc_html_e('Learning hub', 'sikshya'); ?></div>
                    <div class="sik-acc-metric__hint"><?php esc_html_e('Resume progress', 'sikshya'); ?></div>
                </a>
            </section>

            <section class="sik-acc-library" aria-label="<?php esc_attr_e('Shortcuts', 'sikshya'); ?>">
                <a class="sik-acc-lib-card" href="<?php echo esc_url($acc['urls']['account']); ?>#courses">
                    <div class="sik-acc-lib-card__icon sik-acc-lib-card__icon--blue" aria-hidden="true">▣</div>
                    <div class="sik-acc-lib-card__num"><?php echo esc_html((string) (int) $acc['enrollment_count']); ?></div>
                    <div class="sik-acc-lib-card__lbl"><?php esc_html_e('My courses', 'sikshya'); ?></div>
                </a>
                <a class="sik-acc-lib-card" href="<?php echo esc_url($acc['urls']['account']); ?>#orders">
                    <div class="sik-acc-lib-card__icon sik-acc-lib-card__icon--green" aria-hidden="true">≡</div>
                    <div class="sik-acc-lib-card__num"><?php echo esc_html((string) (int) $acc['orders_count']); ?></div>
                    <div class="sik-acc-lib-card__lbl"><?php esc_html_e('Orders', 'sikshya'); ?></div>
                </a>
                <a class="sik-acc-lib-card" href="<?php echo esc_url($acc['urls']['cart']); ?>">
                    <div class="sik-acc-lib-card__icon sik-acc-lib-card__icon--slate" aria-hidden="true">◇</div>
                    <div class="sik-acc-lib-card__num">→</div>
                    <div class="sik-acc-lib-card__lbl"><?php esc_html_e('Cart', 'sikshya'); ?></div>
                </a>
                <a class="sik-acc-lib-card" href="<?php echo esc_url($acc['urls']['checkout']); ?>">
                    <div class="sik-acc-lib-card__icon sik-acc-lib-card__icon--blue" aria-hidden="true">✓</div>
                    <div class="sik-acc-lib-card__num">→</div>
                    <div class="sik-acc-lib-card__lbl"><?php esc_html_e('Checkout', 'sikshya'); ?></div>
                </a>
            </section>

            <div class="sik-acc-grid sik-acc-grid--split">
                <div class="sik-acc-panel" id="courses">
                    <div class="sik-acc-panel__head">
                        <h2 class="sik-acc-panel__title"><?php esc_html_e('My courses', 'sikshya'); ?></h2>
                        <a class="sik-acc-panel__link" href="<?php echo esc_url($acc['urls']['courses']); ?>"><?php esc_html_e('Browse all', 'sikshya'); ?></a>
                    </div>
                    <?php if ($acc['enrollments'] === []) : ?>
                        <div class="sik-acc-empty">
                            <?php esc_html_e('You are not enrolled in any courses yet.', 'sikshya'); ?>
                            <p style="margin-top:0.75rem;">
                                <a class="sik-acc-btn sik-acc-btn--primary" href="<?php echo esc_url($acc['urls']['courses']); ?>"><?php esc_html_e('Browse courses', 'sikshya'); ?></a>
                            </p>
                        </div>
                    <?php else : ?>
                        <div class="sik-acc-table-wrap">
                            <table class="sik-acc-table">
                                <thead>
                                <tr>
                                    <th scope="col"><?php esc_html_e('Course', 'sikshya'); ?></th>
                                    <th scope="col"><?php esc_html_e('Status', 'sikshya'); ?></th>
                                    <th scope="col"><?php esc_html_e('Enrolled', 'sikshya'); ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($acc['enrollments'] as $row) : ?>
                                    <?php
                                    $cid = is_object($row) ? (int) ($row->course_id ?? 0) : (int) ($row['course_id'] ?? 0);
                                    if ($cid <= 0) {
                                        continue;
                                    }
                                    $estatus = is_object($row) ? (string) ($row->status ?? '') : (string) ($row['status'] ?? '');
                                    $enrolled_raw = is_object($row) ? ($row->enrolled_date ?? '') : ($row['enrolled_date'] ?? '');
                                    $enrolled_ts = $enrolled_raw ? strtotime((string) $enrolled_raw) : false;
                                    $enrolled_disp = $enrolled_ts ? wp_date(get_option('date_format'), $enrolled_ts) : '—';
                                    ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo esc_url(get_permalink($cid)); ?>"><?php echo esc_html(get_the_title($cid)); ?></a>
                                            <br>
                                            <a href="<?php echo esc_url(PublicPageUrls::learnForCourse($cid)); ?>"><?php esc_html_e('Open player', 'sikshya'); ?></a>
                                        </td>
                                        <td>
                                            <?php if ($estatus === 'completed') : ?>
                                                <span class="sik-acc-badge"><?php esc_html_e('Completed', 'sikshya'); ?></span>
                                            <?php else : ?>
                                                <span class="sik-acc-badge sik-acc-badge--muted"><?php echo esc_html($estatus !== '' ? ucfirst($estatus) : __('Active', 'sikshya')); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html($enrolled_disp); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <div class="sik-acc-panel" id="orders">
                        <div class="sik-acc-panel__head">
                            <h2 class="sik-acc-panel__title"><?php esc_html_e('Orders', 'sikshya'); ?></h2>
                        </div>
                        <?php if ($acc['orders'] === []) : ?>
                            <div class="sik-acc-empty"><?php esc_html_e('No orders yet.', 'sikshya'); ?></div>
                        <?php else : ?>
                            <div class="sik-acc-table-wrap">
                                <table class="sik-acc-table">
                                    <thead>
                                    <tr>
                                        <th scope="col"><?php esc_html_e('Order', 'sikshya'); ?></th>
                                        <th scope="col"><?php esc_html_e('Status', 'sikshya'); ?></th>
                                        <th scope="col"><?php esc_html_e('Date', 'sikshya'); ?></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($acc['orders'] as $ord) : ?>
                                        <?php
                                        $otok = isset($ord->public_token) ? OrderRepository::sanitizePublicToken((string) $ord->public_token) : '';
                                        if ($otok === '') {
                                            $otok = $order_repo->ensurePublicToken((int) $ord->id);
                                        }
                                        $order_href = $otok !== '' ? PublicPageUrls::orderView($otok) : PublicPageUrls::url('order');
                                        $created = isset($ord->created_at) ? strtotime((string) $ord->created_at) : false;
                                        $created_disp = $created ? wp_date(get_option('date_format'), $created) : '—';
                                        $ostatus = strtolower((string) ($ord->status ?? ''));
                                        ?>
                                        <tr>
                                            <td>
                                                <a href="<?php echo esc_url($order_href); ?>">
                                                    <?php printf(esc_html__('Order #%d', 'sikshya'), (int) $ord->id); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?php if (in_array($ostatus, ['paid', 'completed'], true)) : ?>
                                                    <span class="sik-acc-badge"><?php echo esc_html(ucfirst($ostatus)); ?></span>
                                                <?php else : ?>
                                                    <span class="sik-acc-badge sik-acc-badge--muted"><?php echo esc_html($ostatus !== '' ? ucfirst($ostatus) : __('Unknown', 'sikshya')); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo esc_html($created_disp); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="sik-acc-panel" style="margin-top:1.25rem;">
                        <div class="sik-acc-panel__head">
                            <h2 class="sik-acc-panel__title"><?php esc_html_e('Shortcuts', 'sikshya'); ?></h2>
                        </div>
                        <div class="sik-acc-shortcuts">
                            <a class="sik-acc-shortcut" href="<?php echo esc_url($acc['urls']['courses']); ?>">
                                <span class="sik-acc-shortcut__icon" aria-hidden="true">+</span>
                                <div>
                                    <p class="sik-acc-shortcut__title"><?php esc_html_e('Browse courses', 'sikshya'); ?></p>
                                    <p class="sik-acc-shortcut__desc"><?php esc_html_e('Open the public course catalog.', 'sikshya'); ?></p>
                                </div>
                            </a>
                            <a class="sik-acc-shortcut" href="<?php echo esc_url($acc['urls']['learn']); ?>">
                                <span class="sik-acc-shortcut__icon" aria-hidden="true">▶</span>
                                <div>
                                    <p class="sik-acc-shortcut__title"><?php esc_html_e('Learning hub', 'sikshya'); ?></p>
                                    <p class="sik-acc-shortcut__desc"><?php esc_html_e('Jump into the lesson player.', 'sikshya'); ?></p>
                                </div>
                            </a>
                            <a class="sik-acc-shortcut" href="<?php echo esc_url($acc['urls']['cart']); ?>">
                                <span class="sik-acc-shortcut__icon" aria-hidden="true">◇</span>
                                <div>
                                    <p class="sik-acc-shortcut__title"><?php esc_html_e('Shopping cart', 'sikshya'); ?></p>
                                    <p class="sik-acc-shortcut__desc"><?php esc_html_e('Review items before checkout.', 'sikshya'); ?></p>
                                </div>
                            </a>
                            <a class="sik-acc-shortcut" href="<?php echo esc_url($acc['urls']['checkout']); ?>">
                                <span class="sik-acc-shortcut__icon" aria-hidden="true">✓</span>
                                <div>
                                    <p class="sik-acc-shortcut__title"><?php esc_html_e('Checkout', 'sikshya'); ?></p>
                                    <p class="sik-acc-shortcut__desc"><?php esc_html_e('Complete a purchase securely.', 'sikshya'); ?></p>
                                </div>
                            </a>
                        </div>
                        <div class="sik-acc-tipbox">
                            <h3><?php esc_html_e('Keep learning', 'sikshya'); ?></h3>
                            <ul>
                                <li><?php esc_html_e('Use the learning hub to resume the last lesson.', 'sikshya'); ?></li>
                                <li><?php esc_html_e('Orders include a receipt link you can revisit anytime.', 'sikshya'); ?></li>
                                <li><?php esc_html_e('New courses appear in the catalog as your school publishes them.', 'sikshya'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
</body>
</html>
