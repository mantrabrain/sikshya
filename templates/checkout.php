<?php
/**
 * Checkout — summary from {@see \Sikshya\Frontend\Public\CheckoutTemplateData}; payment via REST + checkout-page.js.
 *
 * @package Sikshya
 */

use Sikshya\Services\Frontend\CheckoutPageService;
use Sikshya\Presentation\Models\CheckoutPageModel;
use Sikshya\Services\Settings;

/** @var CheckoutPageModel $page_model */
$page_model = CheckoutPageService::build();
$co = $page_model->toLegacyViewArray();
$u = $page_model->getUrls();
$vw = $page_model->getViewer();
$fmt_subtotal = number_format_i18n($page_model->getSubtotalHint(), 2) . ' ' . $page_model->getCurrency();
$fmt_total = $fmt_subtotal;

sikshya_get_header();

$is_logged_in = is_user_logged_in();
$guest_enabled = Settings::isTruthy(Settings::get('enable_guest_checkout', true));

$uid = $is_logged_in ? (int) get_current_user_id() : 0;
$prefill = [
    'phone' => $uid > 0 ? (string) get_user_meta($uid, '_sikshya_billing_phone', true) : '',
    'address_1' => $uid > 0 ? (string) get_user_meta($uid, '_sikshya_billing_address_1', true) : '',
    'address_2' => $uid > 0 ? (string) get_user_meta($uid, '_sikshya_billing_address_2', true) : '',
    'city' => $uid > 0 ? (string) get_user_meta($uid, '_sikshya_billing_city', true) : '',
    'state' => $uid > 0 ? (string) get_user_meta($uid, '_sikshya_billing_state', true) : '',
    'postcode' => $uid > 0 ? (string) get_user_meta($uid, '_sikshya_billing_postcode', true) : '',
    'country' => $uid > 0 ? (string) get_user_meta($uid, '_sikshya_billing_country', true) : '',
];
?>

<?php
$root_attrs = [
    'class' => 'sikshya-public sikshya-checkout sikshya-checkout-page sik-f-scope',
    'id' => 'sikshya-checkout-root',
];

/**
 * Filter checkout root element attributes (used by checkout-page.js).
 *
 * Add-ons may inject extra `data-*` keys here.
 *
 * @param array<string, string> $root_attrs
 * @param array<string, mixed>  $co
 * @param CheckoutPageModel     $page_model
 */
$root_attrs = apply_filters('sikshya_checkout_root_attributes', $root_attrs, $co, $page_model);

$checkout_js_config = [
    'restUrl' => (string) $page_model->getRestUrl(),
    'restNonce' => (string) $page_model->getRestNonce(),
    'courseIds' => $page_model->getCourseIds(),
    'isLoggedIn' => $is_logged_in,
    'guestEnabled' => $guest_enabled,
    'guestNonce' => wp_create_nonce('sikshya_guest_checkout'),
];
/**
 * Checkout JS config (prefer this over huge data-* attributes).
 *
 * @param array<string, mixed> $checkout_js_config
 * @param array<string, mixed> $co
 * @param CheckoutPageModel    $page_model
 */
$checkout_js_config = apply_filters('sikshya_checkout_js_config', $checkout_js_config, $co, $page_model);
?>

<div <?php foreach ($root_attrs as $attr => $val) echo ' ' . esc_attr((string) $attr) . '="' . esc_attr((string) $val) . '"'; ?>>
    <script>
      window.sikshyaCheckoutConfig = <?php echo wp_json_encode($checkout_js_config); ?>;
    </script>
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
            <?php if ($u->getAccountUrl() !== '') : ?>
                <p class="sikshya-checkout-page__util-links">
                    <a href="<?php echo esc_url($u->getAccountUrl()); ?>"><?php esc_html_e('My account — orders & receipts', 'sikshya'); ?></a>
                </p>
            <?php endif; ?>
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
                    <div id="sikshya-checkout-errors" class="sikshya-checkout-errors" hidden></div>
                    <section class="sikshya-checkout-page__panel sikshya-checkout-page__panel--account" aria-labelledby="sikshya-checkout-account-heading">
                        <?php if (!$is_logged_in) : ?>
                            <?php if ($guest_enabled) : ?>
                                <h2 id="sikshya-checkout-account-heading" class="sikshya-checkout-page__panel-title"><?php esc_html_e('Checkout details', 'sikshya'); ?></h2>
                                <p class="sikshya-checkout-page__panel-intro">
                                    <?php esc_html_e('Enter your email to receive course access and receipts. We’ll create an account automatically after payment succeeds.', 'sikshya'); ?>
                                </p>
                                <div class="sikshya-checkout-auth">
                                    <div class="sikshya-checkout-auth__tabs" role="tablist" aria-label="<?php esc_attr_e('Checkout options', 'sikshya'); ?>">
                                        <button type="button" class="sikshya-btn sikshya-btn--primary sikshya-checkout-auth__tab" data-sikshya-auth-tab="guest">
                                            <?php esc_html_e('Guest checkout', 'sikshya'); ?>
                                        </button>
                                        <button type="button" class="sikshya-btn sikshya-btn--ghost sikshya-checkout-auth__tab" data-sikshya-auth-tab="login">
                                            <?php esc_html_e('Sign in', 'sikshya'); ?>
                                        </button>
                                    </div>

                                    <div class="sikshya-checkout-auth__panel" data-sikshya-auth-panel="guest">
                                        <div class="sikshya-checkout-guest" id="sikshya-checkout-guest" style="margin-top:0.75rem;">
                                            <p style="margin:0 0 0.75rem;">
                                                <label style="display:block;font-weight:600;margin:0 0 0.25rem;"><?php esc_html_e('Email', 'sikshya'); ?></label>
                                                <input id="sikshya-checkout-guest-email" type="email" class="sikshya-input" autocomplete="email" required style="width:100%;" />
                                            </p>
                                            <?php
                                            /**
                                             * Render extra checkout fields for guest checkout (Dynamic Fields add-on).
                                             *
                                             * @param array<string, mixed> $ctx
                                             */
                                            do_action(
                                                'sikshya_checkout_after_billing_fields',
                                                [
                                                    'context' => 'guest',
                                                    'is_logged_in' => $is_logged_in,
                                                    'guest_enabled' => $guest_enabled,
                                                    'co' => $co,
                                                    'page_model' => $page_model,
                                                ]
                                            );
                                            ?>
                                        </div>
                                    </div>

                                    <div class="sikshya-checkout-auth__panel" data-sikshya-auth-panel="login" hidden style="margin-top:0.75rem;">
                                        <?php echo do_shortcode('[sikshya_login redirect_to="' . esc_attr(get_permalink()) . '"]'); ?>
                                    </div>
                                </div>
                            <?php else : ?>
                                <h2 id="sikshya-checkout-account-heading" class="sikshya-checkout-page__panel-title"><?php esc_html_e('Sign in to continue', 'sikshya'); ?></h2>
                                <p class="sikshya-checkout-page__panel-intro">
                                    <?php esc_html_e('Checkout requires an account so we can grant course access and send receipts. Sign in, or create a new account to continue.', 'sikshya'); ?>
                                </p>
                                <div class="sikshya-checkout-auth">
                                    <div class="sikshya-checkout-auth__tabs" role="tablist" aria-label="<?php esc_attr_e('Checkout authentication', 'sikshya'); ?>">
                                        <button type="button" class="sikshya-btn sikshya-btn--primary sikshya-checkout-auth__tab" data-sikshya-auth-tab="login">
                                            <?php esc_html_e('Sign in', 'sikshya'); ?>
                                        </button>
                                        <button type="button" class="sikshya-btn sikshya-btn--ghost sikshya-checkout-auth__tab" data-sikshya-auth-tab="register">
                                            <?php esc_html_e('Create account', 'sikshya'); ?>
                                        </button>
                                    </div>
                                    <div class="sikshya-checkout-auth__panel" data-sikshya-auth-panel="login">
                                        <?php echo do_shortcode('[sikshya_login redirect_to="' . esc_attr(get_permalink()) . '"]'); ?>
                                    </div>
                                    <div class="sikshya-checkout-auth__panel" data-sikshya-auth-panel="register" hidden>
                                        <?php echo do_shortcode('[sikshya_registration type="student" redirect_to="' . esc_attr(get_permalink()) . '"]'); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php else : ?>
                            <h2 id="sikshya-checkout-account-heading" class="sikshya-checkout-page__panel-title"><?php esc_html_e('Your account', 'sikshya'); ?></h2>
                            <p class="sikshya-checkout-page__panel-intro">
                                <?php
                                $brand = function_exists('sikshya_brand_name') ? sikshya_brand_name('frontend') : __('Sikshya LMS', 'sikshya');
                                echo esc_html(
                                    sprintf(
                                        /* translators: %s: brand name */
                                        __('You are buying digital course access. There is no appointment or date “booking” step in %s—enrollment is tied to your WordPress account, and course access dates (if any) are set by each course.', 'sikshya'),
                                        $brand
                                    )
                                );
                                ?>
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

                            <div class="sikshya-checkout-billing" style="margin-top:1rem;">
                                <h3 style="margin:0 0 0.5rem;font-size:0.95rem;"><?php esc_html_e('Billing details', 'sikshya'); ?></h3>
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                                    <p style="margin:0;">
                                        <label style="display:block;font-weight:600;margin:0 0 0.25rem;"><?php esc_html_e('Phone', 'sikshya'); ?></label>
                                        <input id="sikshya-checkout-billing-phone" type="tel" class="sikshya-input" autocomplete="tel" style="width:100%;" value="<?php echo esc_attr($prefill['phone']); ?>" />
                                    </p>
                                    <p style="margin:0;">
                                        <label style="display:block;font-weight:600;margin:0 0 0.25rem;"><?php esc_html_e('Country', 'sikshya'); ?></label>
                                        <input id="sikshya-checkout-billing-country" type="text" class="sikshya-input" autocomplete="country-name" style="width:100%;" value="<?php echo esc_attr($prefill['country']); ?>" />
                                    </p>
                                </div>
                                <p style="margin:0.75rem 0 0.75rem;">
                                    <label style="display:block;font-weight:600;margin:0 0 0.25rem;"><?php esc_html_e('Address line 1', 'sikshya'); ?></label>
                                    <input id="sikshya-checkout-billing-address-1" type="text" class="sikshya-input" autocomplete="address-line1" style="width:100%;" value="<?php echo esc_attr($prefill['address_1']); ?>" />
                                </p>
                                <p style="margin:0 0 0.75rem;">
                                    <label style="display:block;font-weight:600;margin:0 0 0.25rem;"><?php esc_html_e('Address line 2 (optional)', 'sikshya'); ?></label>
                                    <input id="sikshya-checkout-billing-address-2" type="text" class="sikshya-input" autocomplete="address-line2" style="width:100%;" value="<?php echo esc_attr($prefill['address_2']); ?>" />
                                </p>
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                                    <p style="margin:0;">
                                        <label style="display:block;font-weight:600;margin:0 0 0.25rem;"><?php esc_html_e('City', 'sikshya'); ?></label>
                                        <input id="sikshya-checkout-billing-city" type="text" class="sikshya-input" autocomplete="address-level2" style="width:100%;" value="<?php echo esc_attr($prefill['city']); ?>" />
                                    </p>
                                    <p style="margin:0;">
                                        <label style="display:block;font-weight:600;margin:0 0 0.25rem;"><?php esc_html_e('Postal code (optional)', 'sikshya'); ?></label>
                                        <input id="sikshya-checkout-billing-postcode" type="text" class="sikshya-input" autocomplete="postal-code" style="width:100%;" value="<?php echo esc_attr($prefill['postcode']); ?>" />
                                    </p>
                                </div>
                                <p style="margin:0.75rem 0 0;">
                                    <label style="display:block;font-weight:600;margin:0 0 0.25rem;"><?php esc_html_e('State / Province (optional)', 'sikshya'); ?></label>
                                    <input id="sikshya-checkout-billing-state" type="text" class="sikshya-input" autocomplete="address-level1" style="width:100%;" value="<?php echo esc_attr($prefill['state']); ?>" />
                                </p>
                            </div>
                            <?php
                            /**
                             * Render extra checkout fields after billing fields (logged-in checkout).
                             *
                             * @param array<string, mixed> $ctx
                             */
                            do_action(
                                'sikshya_checkout_after_billing_fields',
                                [
                                    'context' => 'account',
                                    'is_logged_in' => $is_logged_in,
                                    'guest_enabled' => $guest_enabled,
                                    'co' => $co,
                                    'page_model' => $page_model,
                                ]
                            );
                            ?>
                        <?php endif; ?>
                    </section>

                    <?php if ($is_logged_in) : ?>
                    <section class="sikshya-checkout-page__panel" aria-labelledby="sikshya-checkout-coupon-heading">
                        <h2 id="sikshya-checkout-coupon-heading" class="sikshya-checkout-page__panel-title"><?php esc_html_e('Discount code', 'sikshya'); ?></h2>
                        <p class="sikshya-checkout-page__panel-intro"><?php esc_html_e('Optional. Apply a code to update your totals before paying.', 'sikshya'); ?></p>
                        <div class="sikshya-checkout-page__coupon">
                            <label class="sikshya-screen-reader-text" for="sikshya-checkout-coupon"><?php esc_html_e('Coupon code', 'sikshya'); ?></label>
                            <input type="text" id="sikshya-checkout-coupon" class="sikshya-checkout-page__coupon-input" name="sikshya_coupon" autocomplete="off" placeholder="<?php esc_attr_e('Enter code', 'sikshya'); ?>" />
                            <button type="button" class="sikshya-btn sikshya-btn--ghost sikshya-checkout-page__coupon-apply" id="sikshya-checkout-apply-coupon"><?php esc_html_e('Apply', 'sikshya'); ?></button>
                        </div>
                    </section>
                    <?php endif; ?>

                    <?php if ($is_logged_in || $guest_enabled) : ?>
                    <section class="sikshya-checkout-page__panel" aria-labelledby="sikshya-checkout-payment-heading">
                        <h2 id="sikshya-checkout-payment-heading" class="sikshya-checkout-page__panel-title"><?php esc_html_e('Payment', 'sikshya'); ?></h2>
                        <p class="sikshya-checkout-page__panel-intro"><?php esc_html_e('Choose a payment method to continue.', 'sikshya'); ?></p>

                        <?php
                        $gw_ids = $page_model->getCheckoutGatewayIds();
                        $gw_labels = \Sikshya\Frontend\Public\CheckoutTemplateData::gatewayCheckoutLabels();
                        $gw_statuses = isset($co['gateway_statuses']) && is_array($co['gateway_statuses']) ? $co['gateway_statuses'] : [];
                        $any_gw = $gw_ids !== [];
                        ?>
                        <div class="sikshya-checkout-gateways">
                            <?php foreach ($gw_ids as $idx => $gid) : ?>
                                <?php
                                $gid = sanitize_key((string) $gid);
                                if ($gid === '') {
                                    continue;
                                }
                                $row = isset($gw_statuses[$gid]) && is_array($gw_statuses[$gid]) ? $gw_statuses[$gid] : [];
                                $is_configured = !empty($row['configured']);
                                $is_wired = !empty($row['wired']);
                                $is_locked = !empty($row['locked']);
                                $is_enabled = !array_key_exists('enabled', $row) || !empty($row['enabled']);
                                $disabled = (!$is_configured) || (!$is_wired) || $is_locked;
                                if (!$is_enabled) {
                                    continue;
                                }
                                $btn_class = 'sikshya-btn sikshya-btn--ghost sikshya-checkout-page__gateway-btn';
                                $label = $gw_labels[$gid] ?? ucwords(str_replace('_', ' ', $gid));
                                $icons = [
                                    'offline' => 'pay-later.svg',
                                    'paypal' => 'paypal.svg',
                                    'stripe' => 'stripe.svg',
                                    'razorpay' => 'razorpay.svg',
                                    'mollie' => 'mollie.svg',
                                    'paystack' => 'paystack.svg',
                                    'square' => 'square.svg',
                                    'authorize_net' => 'authorize-net.svg',
                                    'bank_transfer' => 'bank_transfer.svg',
                                ];
                                $icon_file = $icons[$gid] ?? '';
                                $icon_url = ($icon_file !== '' && defined('SIKSHYA_PLUGIN_URL'))
                                    ? (string) constant('SIKSHYA_PLUGIN_URL') . 'assets/images/payment-gateways/' . $icon_file
                                    : '';

                                $ui_title = __('Payment details', 'sikshya');
                                $ui_text = '';
                                $disabled_reason = $is_locked
                                    ? ($row['locked_reason'] ?? __('Requires Sikshya Pro.', 'sikshya'))
                                    : (!$is_wired ? __('Requires an add-on to enable this gateway.', 'sikshya') : (!$is_configured ? __('Needs setup in admin settings.', 'sikshya') : ''));
                                if ($disabled_reason !== '' && !$is_configured) {
                                    $ui_text = $disabled_reason;
                                } elseif ($gid === 'offline' || $gid === 'bank_transfer') {
                                    $ui_text = __('You will place the order first. Payment instructions will appear on your receipt page.', 'sikshya');
                                } elseif ($gid === 'paypal') {
                                    $ui_text = __('You will be redirected to PayPal to complete payment securely.', 'sikshya');
                                } elseif ($gid === 'stripe') {
                                    $ui_text = __('Enter card details below to pay securely via Stripe.', 'sikshya');
                                } else {
                                    $ui_text = __('Continue to the secure payment step for this gateway.', 'sikshya');
                                }
                                ?>
                                <div class="sikshya-checkout-gateway-row" data-sikshya-gateway-row="<?php echo esc_attr($gid); ?>">
                                    <button
                                        type="button"
                                        class="<?php echo esc_attr($btn_class); ?>"
                                        data-sikshya-gateway="<?php echo esc_attr($gid); ?>"
                                        data-sikshya-gateway-configured="<?php echo esc_attr($is_configured ? '1' : '0'); ?>"
                                        data-sikshya-gateway-disabled-reason="<?php echo esc_attr($disabled_reason); ?>"
                                        <?php echo $disabled ? 'disabled="disabled"' : ''; ?>
                                        title="<?php echo esc_attr($disabled_reason); ?>"
                                    >
                                        <span class="sikshya-checkout-gateways__selector" aria-hidden="true"></span>
                                        <?php if ($icon_url !== '') : ?>
                                            <img
                                                src="<?php echo esc_url($icon_url); ?>"
                                                alt=""
                                                class="sikshya-checkout-gateways__icon"
                                                loading="lazy"
                                                decoding="async"
                                                aria-hidden="true"
                                            />
                                        <?php endif; ?>
                                        <span class="sikshya-checkout-gateways__meta">
                                            <span class="sikshya-checkout-gateways__label"><?php echo esc_html($label); ?></span>
                                            <?php if ($disabled) : ?>
                                                <span class="sikshya-checkout-gateways__hint"><?php echo esc_html($disabled_reason); ?></span>
                                            <?php else : ?>
                                                <span class="sikshya-checkout-gateways__hint"><?php esc_html_e('Selected method will be used on checkout.', 'sikshya'); ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <?php if ($is_locked) : ?>
                                            <span class="sikshya-checkout-gateways__badge" aria-hidden="true"><?php esc_html_e('Pro', 'sikshya'); ?></span>
                                        <?php elseif (!$is_wired) : ?>
                                            <span class="sikshya-checkout-gateways__badge" aria-hidden="true"><?php esc_html_e('Requires add-on', 'sikshya'); ?></span>
                                        <?php elseif (!$is_configured) : ?>
                                            <span class="sikshya-checkout-gateways__badge" aria-hidden="true"><?php esc_html_e('Needs setup', 'sikshya'); ?></span>
                                        <?php endif; ?>
                                    </button>

                                    <div class="sikshya-checkout-gateway-ui" data-sikshya-gateway-ui="<?php echo esc_attr($gid); ?>" hidden>
                                        <div class="sikshya-checkout-gateway-ui__title"><?php echo esc_html($ui_title); ?></div>
                                        <p class="sikshya-checkout-gateway-ui__text"><?php echo esc_html($ui_text); ?></p>
                                        <?php if ($gid === 'stripe') : ?>
                                            <div class="sikshya-stripe-inline">
                                                <div class="sikshya-stripe-inline__title"><?php esc_html_e('Pay securely with Stripe', 'sikshya'); ?></div>
                                                <div class="sikshya-stripe-inline__lede"><?php esc_html_e('Enter payment details below and click Pay to complete enrollment.', 'sikshya'); ?></div>
                                                <div id="sikshya-stripe-inline-element"></div>
                                                <div id="sikshya-stripe-inline-error" class="sikshya-stripe-inline__error" hidden></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$any_gw) : ?>
                                <p class="sikshya-checkout-gateways__notice">
                                    <?php
                                    $short = function_exists('sikshya_brand_profile')
                                        ? (string) (sikshya_brand_profile('frontend')['brandShortName'] ?? '')
                                        : '';
                                    $short = $short !== '' ? $short : __('Sikshya', 'sikshya');
                                    echo esc_html(
                                        sprintf(
                                            /* translators: %s: brand short name */
                                            __('No payment method is available. Enable and configure gateways under %s → Settings → Payment.', 'sikshya'),
                                            $short
                                        )
                                    );
                                    ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <?php if ($any_gw) : ?>
                            <?php
                            $first_gid = sanitize_key((string) ($gw_ids[0] ?? ''));
                            $first_label = $gw_labels[$first_gid] ?? __('Continue', 'sikshya');
                            ?>
                            <div class="sikshya-checkout-page__primary-action" style="margin-top:0.75rem;">
                                <button
                                    type="button"
                                    class="sikshya-btn sikshya-btn--primary"
                                    id="sikshya-checkout-primary-action"
                                    data-sikshya-primary-gateway="<?php echo esc_attr($first_gid); ?>"
                                >
                                    <?php echo esc_html($first_label); ?>
                                </button>
                            </div>
                        <?php endif; ?>
                        <p class="sikshya-checkout-status" id="sikshya-checkout-status" role="status" aria-live="polite"></p>
                    </section>
                    <?php endif; ?>
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
sikshya_get_footer();
