<?php
/**
 * Checkout — summary from {@see \Sikshya\Frontend\Public\CheckoutTemplateData}; payment via REST + checkout-page.js.
 *
 * @package Sikshya
 */

use Sikshya\Frontend\Public\CheckoutTemplateData;

$co = CheckoutTemplateData::build();
$u = $co['urls'];
$vw = $co['viewer'] ?? ['display_name' => '', 'email' => ''];
$fmt_subtotal = number_format_i18n($co['subtotal_hint'], 2) . ' ' . $co['currency'];
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
    data-rest-url="<?php echo esc_attr($co['rest_url']); ?>"
    data-rest-nonce="<?php echo esc_attr($co['rest_nonce']); ?>"
    data-course-ids="<?php echo esc_attr(wp_json_encode($co['course_ids'])); ?>"
>
    <header class="sikshya-checkout-page__masthead">
        <div class="sikshya-checkout-page__masthead-inner">
            <nav class="sikshya-checkout-page__breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'sikshya'); ?>">
                <a href="<?php echo esc_url($u['home']); ?>"><?php esc_html_e('Home', 'sikshya'); ?></a>
                <span class="sikshya-checkout-page__bc-sep" aria-hidden="true">›</span>
                <a href="<?php echo esc_url($u['cart']); ?>"><?php esc_html_e('Cart', 'sikshya'); ?></a>
                <span class="sikshya-checkout-page__bc-sep" aria-hidden="true">›</span>
                <span class="sikshya-checkout-page__breadcrumb-current"><?php esc_html_e('Checkout', 'sikshya'); ?></span>
            </nav>
            <h1 class="sikshya-checkout-page__title"><?php esc_html_e('Checkout', 'sikshya'); ?></h1>
            <p class="sikshya-checkout-page__lead">
                <?php if (!empty($co['empty'])) : ?>
                    <?php esc_html_e('Add courses to your cart to continue.', 'sikshya'); ?>
                <?php else : ?>
                    <?php esc_html_e('Complete your purchase securely.', 'sikshya'); ?>
                <?php endif; ?>
            </p>
        </div>
    </header>

    <div class="sikshya-checkout-page__body">
        <?php if (!empty($co['empty'])) : ?>
            <div class="sikshya-checkout-page__empty">
                <p class="sikshya-checkout-page__empty-text"><?php esc_html_e('Your cart is empty.', 'sikshya'); ?></p>
                <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url($u['cart']); ?>"><?php esc_html_e('Back to cart', 'sikshya'); ?></a>
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
                        $gw = $co['gateways'] ?? ['offline' => true, 'stripe' => false, 'paypal' => false];
                        $any_gw = !empty($gw['offline']) || !empty($gw['stripe']) || !empty($gw['paypal']);
                        ?>
                        <div class="sikshya-checkout-gateways">
                            <?php if (!empty($gw['offline'])) : ?>
                                <button type="button" class="sikshya-btn sikshya-btn--primary sikshya-checkout-page__gateway-btn" data-sikshya-gateway="offline"><?php esc_html_e('Offline payment', 'sikshya'); ?></button>
                            <?php endif; ?>
                            <?php if (!empty($gw['stripe'])) : ?>
                                <button type="button" class="sikshya-btn sikshya-btn--ghost sikshya-checkout-page__gateway-btn" data-sikshya-gateway="stripe"><?php esc_html_e('Pay with Stripe', 'sikshya'); ?></button>
                            <?php endif; ?>
                            <?php if (!empty($gw['paypal'])) : ?>
                                <button type="button" class="sikshya-btn sikshya-btn--ghost sikshya-checkout-page__gateway-btn" data-sikshya-gateway="paypal"><?php esc_html_e('Pay with PayPal', 'sikshya'); ?></button>
                            <?php endif; ?>
                            <?php if (!$any_gw) : ?>
                                <p class="sikshya-checkout-gateways__notice">
                                    <?php esc_html_e('No payment method is available. Enable offline payment or add Stripe or PayPal under Sikshya → Settings → Payment.', 'sikshya'); ?>
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
                            <?php foreach ($co['lines'] as $line) : ?>
                                <?php
                                $pr = $line['pricing'] ?? [];
                                $eff = isset($pr['effective']) && $pr['effective'] !== null ? (float) $pr['effective'] : 0;
                                ?>
                                <li class="sikshya-checkout-page__line">
                                    <span class="sikshya-checkout-page__line-title"><?php echo esc_html($line['title'] ?? ''); ?></span>
                                    <span class="sikshya-checkout-page__line-price">
                                        <?php
                                        if ($eff > 0) {
                                            echo wp_kses_post(sikshya_format_price($eff, $pr['currency'] ?? $co['currency']));
                                        } else {
                                            esc_html_e('Free', 'sikshya');
                                        }
                                        ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
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
                        <a class="sikshya-checkout-page__edit-cart" href="<?php echo esc_url($u['cart']); ?>"><?php esc_html_e('Edit cart', 'sikshya'); ?></a>
                    </div>
                </aside>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
get_footer();
