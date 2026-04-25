<?php
/**
 * Checkout — summary from {@see \Sikshya\Frontend\Public\CheckoutTemplateData}; payment via REST + checkout-page.js.
 *
 * @package Sikshya
 */

use Sikshya\Services\Frontend\CheckoutPageService;
use Sikshya\Presentation\Models\CheckoutPageModel;

/** @var CheckoutPageModel $page_model */
$page_model = CheckoutPageService::build();
$co = $page_model->toLegacyViewArray();
$u = $page_model->getUrls();
$vw = $page_model->getViewer();
$fmt_subtotal = number_format_i18n($page_model->getSubtotalHint(), 2) . ' ' . $page_model->getCurrency();
$fmt_total = $fmt_subtotal;

get_header();

if (!is_user_logged_in()) {
    wp_safe_redirect(wp_login_url(get_permalink()));
    exit;
}
?>

<div
    class="sikshya-public sikshya-checkout sikshya-checkout-page sik-f-scope"
    id="sikshya-checkout-root"
    data-rest-url="<?php echo esc_attr($page_model->getRestUrl()); ?>"
    data-rest-nonce="<?php echo esc_attr($page_model->getRestNonce()); ?>"
    data-course-ids="<?php echo esc_attr(wp_json_encode($page_model->getCourseIds())); ?>"
>
    <header class="sikshya-checkout-page__masthead">
        <div class="sikshya-checkout-page__masthead-inner">
            <nav class="sikshya-checkout-page__breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'sikshya'); ?>">
                <a href="<?php echo esc_url($u->getHomeUrl()); ?>"><?php esc_html_e('Home', 'sikshya'); ?></a>
                <span class="sikshya-checkout-page__bc-sep" aria-hidden="true">›</span>
                <a href="<?php echo esc_url($u->getCartUrl()); ?>"><?php esc_html_e('Cart', 'sikshya'); ?></a>
                <span class="sikshya-checkout-page__bc-sep" aria-hidden="true">›</span>
                <span class="sikshya-checkout-page__breadcrumb-current"><?php esc_html_e('Checkout', 'sikshya'); ?></span>
            </nav>
            <h1 class="sikshya-checkout-page__title"><?php esc_html_e('Checkout', 'sikshya'); ?></h1>
            <p class="sikshya-checkout-page__lead">
                <?php if ($page_model->isEmpty()) : ?>
                    <?php esc_html_e('Add courses to your cart to continue.', 'sikshya'); ?>
                <?php else : ?>
                    <?php esc_html_e('Complete your purchase securely.', 'sikshya'); ?>
                <?php endif; ?>
            </p>
        </div>
    </header>

    <div class="sikshya-checkout-page__body">
        <?php require __DIR__ . '/partials/course-cart-flash.php'; ?>
        <?php if ($page_model->isEmpty()) : ?>
            <div class="sikshya-checkout-page__empty">
                <div class="sikshya-checkout-page__empty-copy">
                    <h2 class="sikshya-checkout-page__empty-title"><?php esc_html_e('Nothing to check out yet', 'sikshya'); ?></h2>
                    <p class="sikshya-checkout-page__empty-text"><?php esc_html_e('Add courses to your cart, then return here to complete checkout.', 'sikshya'); ?></p>
                    <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url($u->getCartUrl()); ?>"><?php esc_html_e('Go to cart', 'sikshya'); ?></a>
                </div>

                <div class="sikshya-checkout-page__empty-illus" aria-hidden="true">
                    <svg viewBox="0 0 720 420" role="presentation" focusable="false">
                        <defs>
                            <linearGradient id="sikEmptyCoCard" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0" stop-color="rgba(99,102,241,0.18)" />
                                <stop offset="1" stop-color="rgba(99,102,241,0.06)" />
                            </linearGradient>
                            <linearGradient id="sikEmptyCoBg" x1="1" y1="0" x2="0" y2="1">
                                <stop offset="0" stop-color="rgba(15,23,42,0.06)" />
                                <stop offset="1" stop-color="rgba(15,23,42,0.02)" />
                            </linearGradient>
                        </defs>

                        <!-- soft backdrop -->
                        <rect x="56" y="46" width="608" height="328" rx="26" fill="url(#sikEmptyCoBg)" stroke="rgba(209,213,219,0.9)" />

                        <!-- “summary card” -->
                        <rect x="112" y="92" width="360" height="238" rx="18" fill="white" stroke="rgba(229,231,235,1)" />
                        <rect x="112" y="92" width="360" height="60" rx="18" fill="url(#sikEmptyCoCard)" />
                        <rect x="144" y="174" width="240" height="12" rx="6" fill="rgba(17,24,39,0.12)" />
                        <rect x="144" y="198" width="200" height="10" rx="5" fill="rgba(17,24,39,0.10)" />
                        <rect x="144" y="232" width="270" height="10" rx="5" fill="rgba(17,24,39,0.08)" />
                        <rect x="144" y="256" width="160" height="10" rx="5" fill="rgba(17,24,39,0.08)" />
                        <rect x="144" y="292" width="160" height="34" rx="17" fill="rgba(99,102,241,0.14)" stroke="rgba(99,102,241,0.30)" />

                        <!-- “secure checkout” tile -->
                        <g transform="translate(506 132)">
                            <rect x="0" y="0" width="132" height="132" rx="22" fill="white" stroke="rgba(229,231,235,1)" />
                            <path d="M66 46c-18 0-32 12-32 30v14c0 18 14 30 32 30s32-12 32-30V76c0-18-14-30-32-30z" fill="rgba(99,102,241,0.10)" />
                            <path d="M48 76v-10c0-10 8-18 18-18s18 8 18 18v10" fill="none" stroke="rgba(79,70,229,0.75)" stroke-width="8" stroke-linecap="round"/>
                            <rect x="42" y="76" width="48" height="40" rx="12" fill="none" stroke="rgba(79,70,229,0.75)" stroke-width="8" />
                            <circle cx="66" cy="96" r="6" fill="rgba(79,70,229,0.75)" />
                        </g>
                    </svg>
                </div>
            </div>
        <?php else : ?>
            <div class="sikshya-checkout-page__layout">
                <main class="sikshya-checkout-page__main" id="sikshya-checkout-main">
                    <section class="sikshya-checkout-page__panel sikshya-checkout-page__panel--account" aria-labelledby="sikshya-checkout-account-heading">
                        <h2 id="sikshya-checkout-account-heading" class="sikshya-checkout-page__panel-title"><?php esc_html_e('Your account', 'sikshya'); ?></h2>
                        <p class="sikshya-checkout-page__panel-intro">
                            <?php esc_html_e('You are buying digital course access. There is no appointment or date “booking” step in Sikshya—enrollment is tied to your WordPress account, and course access dates (if any) are set by each course.', 'sikshya'); ?>
                        </p>
                        <dl class="sikshya-checkout-page__account-dl">
                            <div class="sikshya-checkout-page__account-row">
                                <dt><?php esc_html_e('Name', 'sikshya'); ?></dt>
                                <dd><?php echo esc_html((string) ($vw['display_name'] ?? '')); ?></dd>
                            </div>
                            <div class="sikshya-checkout-page__account-row">
                                <dt><?php esc_html_e('Email', 'sikshya'); ?></dt>
                                <dd><?php echo esc_html((string) ($vw['email'] ?? '')); ?></dd>
                            </div>
                        </dl>
                        <p class="sikshya-checkout-page__account-note">
                            <?php esc_html_e('For card or PayPal, details are entered on the secure gateway screen. For offline payment, you will see bank or invoice instructions on your order page after you place the order.', 'sikshya'); ?>
                        </p>
                    </section>

                    <section class="sikshya-checkout-page__panel" aria-labelledby="sikshya-checkout-coupon-heading">
                        <h2 id="sikshya-checkout-coupon-heading" class="sikshya-checkout-page__panel-title"><?php esc_html_e('Discount code', 'sikshya'); ?></h2>
                        <p class="sikshya-checkout-page__panel-intro"><?php esc_html_e('Optional. Apply a code to update your totals before paying.', 'sikshya'); ?></p>
                        <div class="sikshya-checkout-page__coupon">
                            <label class="sikshya-screen-reader-text" for="sikshya-checkout-coupon"><?php esc_html_e('Coupon code', 'sikshya'); ?></label>
                            <input type="text" id="sikshya-checkout-coupon" class="sikshya-checkout-page__coupon-input" name="sikshya_coupon" autocomplete="off" placeholder="<?php esc_attr_e('Enter code', 'sikshya'); ?>" />
                            <button type="button" class="sikshya-btn sikshya-btn--ghost sikshya-checkout-page__coupon-apply" id="sikshya-checkout-apply-coupon"><?php esc_html_e('Apply', 'sikshya'); ?></button>
                        </div>
                    </section>

                    <section class="sikshya-checkout-page__panel" aria-labelledby="sikshya-checkout-payment-heading">
                        <h2 id="sikshya-checkout-payment-heading" class="sikshya-checkout-page__panel-title"><?php esc_html_e('Payment', 'sikshya'); ?></h2>
                        <p class="sikshya-checkout-page__panel-intro"><?php esc_html_e('Choose a payment method to continue.', 'sikshya'); ?></p>

                        <?php
                        $gw_ids = $page_model->getCheckoutGatewayIds();
                        $gw_labels = \Sikshya\Frontend\Public\CheckoutTemplateData::gatewayCheckoutLabels();
                        $any_gw = $gw_ids !== [];
                        ?>
                        <div class="sikshya-checkout-gateways">
                            <?php foreach ($gw_ids as $idx => $gid) : ?>
                                <?php
                                $gid = sanitize_key((string) $gid);
                                if ($gid === '') {
                                    continue;
                                }
                                $btn_class = $idx === 0
                                    ? 'sikshya-btn sikshya-btn--primary sikshya-checkout-page__gateway-btn'
                                    : 'sikshya-btn sikshya-btn--ghost sikshya-checkout-page__gateway-btn';
                                $label = $gw_labels[$gid] ?? ucwords(str_replace('_', ' ', $gid));
                                ?>
                                <button type="button" class="<?php echo esc_attr($btn_class); ?>" data-sikshya-gateway="<?php echo esc_attr($gid); ?>">
                                    <?php echo esc_html($label); ?>
                                </button>
                            <?php endforeach; ?>
                            <?php if (!$any_gw) : ?>
                                <p class="sikshya-checkout-gateways__notice">
                                    <?php esc_html_e('No payment method is available. Enable and configure gateways under Sikshya → Settings → Payment.', 'sikshya'); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <p class="sikshya-checkout-status" id="sikshya-checkout-status" role="status" aria-live="polite"></p>
                    </section>
                </main>

                <aside class="sikshya-checkout-page__sidebar" aria-label="<?php esc_attr_e('Order summary', 'sikshya'); ?>">
                    <div class="sikshya-checkout-page__summary sik-f-card">
                        <p class="sikshya-checkout-page__summary-head"><?php esc_html_e('Order summary', 'sikshya'); ?></p>
                        <ul class="sikshya-checkout-page__lines">
                            <?php foreach ($page_model->getLines() as $line) : ?>
                                <?php
                                $pr = $line['pricing'] ?? [];
                                $eff = isset($pr['effective']) && $pr['effective'] !== null ? (float) $pr['effective'] : 0;
                                ?>
                                <li class="sikshya-checkout-page__line">
                                    <?php if (!empty($line['thumbnail'])) : ?>
                                        <span class="sikshya-checkout-page__line-thumb" aria-hidden="true">
                                            <img class="sikshya-checkout-page__line-thumb-img" src="<?php echo esc_url((string) $line['thumbnail']); ?>" alt="" loading="lazy" decoding="async" />
                                        </span>
                                    <?php else : ?>
                                        <span class="sikshya-checkout-page__line-thumb sikshya-checkout-page__line-thumb--placeholder" aria-hidden="true"></span>
                                    <?php endif; ?>

                                    <span class="sikshya-checkout-page__line-title"><?php echo esc_html($line['title'] ?? ''); ?></span>
                                    <span class="sikshya-checkout-page__line-price">
                                        <?php
                                        if ($eff > 0) {
                                            echo wp_kses_post(sikshya_format_price($eff, $pr['currency'] ?? $page_model->getCurrency()));
                                        } else {
                                            esc_html_e('Free', 'sikshya');
                                        }
                                        ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php
                        /**
                         * Render Pro / addon blocks inside the checkout summary, above totals
                         * (advanced coupon notice, subscription terms, etc.).
                         */
                        do_action('sikshya_checkout_summary_before_totals', $co);
                        ?>
                        <div class="sikshya-checkout-page__summary-totals">
                            <div class="sikshya-checkout-page__summary-row">
                                <span><?php esc_html_e('Subtotal', 'sikshya'); ?></span>
                                <span id="sikshya-checkout-subtotal-display"><?php echo esc_html($fmt_subtotal); ?></span>
                            </div>
                            <div class="sikshya-checkout-page__summary-row sikshya-checkout-page__summary-row--discount" id="sikshya-checkout-discount-row" hidden>
                                <span><?php esc_html_e('Discount', 'sikshya'); ?></span>
                                <span id="sikshya-checkout-discount-display"></span>
                            </div>
                            <div class="sikshya-checkout-page__total">
                                <span class="sikshya-checkout-page__total-label"><?php esc_html_e('Total', 'sikshya'); ?></span>
                                <span class="sikshya-checkout-page__total-value" id="sikshya-checkout-total-value"><?php echo esc_html($fmt_total); ?></span>
                            </div>
                        </div>
                        <a class="sikshya-checkout-page__edit-cart" href="<?php echo esc_url($u->getCartUrl()); ?>"><?php esc_html_e('Edit cart', 'sikshya'); ?></a>
                    </div>
                </aside>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
get_footer();
