<?php
/**
 * Checkout — summary from {@see \Sikshya\Frontend\Public\CheckoutTemplateData}; payment via REST + checkout-page.js.
 *
 * @package Sikshya
 */

use Sikshya\Frontend\Public\CheckoutTemplateData;

$co = CheckoutTemplateData::build();

get_header();

if (!is_user_logged_in()) {
    wp_safe_redirect(wp_login_url(get_permalink()));
    exit;
}
?>

<div class="sikshya-public sikshya-checkout" id="sikshya-checkout-root"
    data-rest-url="<?php echo esc_attr($co['rest_url']); ?>"
    data-rest-nonce="<?php echo esc_attr($co['rest_nonce']); ?>"
    data-course-ids="<?php echo esc_attr(wp_json_encode($co['course_ids'])); ?>"
>
    <div class="sikshya-container sikshya-container--narrow">
        <h1 class="sikshya-page-title"><?php esc_html_e('Checkout', 'sikshya'); ?></h1>

        <?php if (!empty($co['empty'])) : ?>
            <p><?php esc_html_e('Your cart is empty.', 'sikshya'); ?></p>
            <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url($co['urls']['cart']); ?>"><?php esc_html_e('Back to cart', 'sikshya'); ?></a>
        <?php else : ?>
            <ul class="sikshya-checkout-lines">
                <?php foreach ($co['lines'] as $line) : ?>
                    <li><?php echo esc_html($line['title']); ?></li>
                <?php endforeach; ?>
            </ul>
            <p><strong><?php esc_html_e('Total', 'sikshya'); ?>:</strong> <?php echo esc_html(number_format_i18n($co['subtotal_hint'], 2) . ' ' . $co['currency']); ?></p>

            <?php
            $gw = $co['gateways'] ?? ['stripe' => false, 'paypal' => false];
            $any_gw = !empty($gw['stripe']) || !empty($gw['paypal']);
            ?>
            <div class="sikshya-checkout-gateways">
                <?php if (!empty($gw['stripe'])) : ?>
                    <button type="button" class="sikshya-btn sikshya-btn--primary" data-sikshya-gateway="stripe"><?php esc_html_e('Pay with Stripe', 'sikshya'); ?></button>
                <?php endif; ?>
                <?php if (!empty($gw['paypal'])) : ?>
                    <button type="button" class="sikshya-btn sikshya-btn--ghost" data-sikshya-gateway="paypal"><?php esc_html_e('Pay with PayPal', 'sikshya'); ?></button>
                <?php endif; ?>
                <?php if (!$any_gw) : ?>
                    <p class="sikshya-checkout-gateways__notice">
                        <?php esc_html_e('No payment gateway is configured. Add your Stripe secret key or PayPal REST credentials under Sikshya → Settings → Payment.', 'sikshya'); ?>
                    </p>
                <?php endif; ?>
            </div>
            <p class="sikshya-checkout-status" id="sikshya-checkout-status" role="status" aria-live="polite"></p>
        <?php endif; ?>
    </div>
</div>

<?php
get_footer();
