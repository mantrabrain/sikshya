<?php
/**
 * One-off flash after cart POST redirects (add/remove/enroll/errors).
 *
 * @package Sikshya
 */

use Sikshya\Frontend\Public\CartFlashResolver;
use Sikshya\Frontend\Public\PublicPageUrls;

if (!defined('ABSPATH')) {
    exit;
}

$__sikshya_cart_flash = CartFlashResolver::fromRequest();
if (!is_array($__sikshya_cart_flash) || empty($__sikshya_cart_flash['message'])) {
    return;
}
$__sikshya_show_view_cart = !empty($__sikshya_cart_flash['show_view_cart']);
$__sikshya_cart_url = PublicPageUrls::url('cart');
?>
<div class="sikshya-cart-flash sikshya-cart-flash--<?php echo esc_attr((string) ($__sikshya_cart_flash['type'] ?? 'info')); ?>" role="status">
    <span class="sikshya-cart-flash__msg"><?php echo esc_html((string) $__sikshya_cart_flash['message']); ?></span>
    <?php if ($__sikshya_show_view_cart && is_string($__sikshya_cart_url) && $__sikshya_cart_url !== '') : ?>
        <a class="sikshya-cart-flash__action" href="<?php echo esc_url($__sikshya_cart_url); ?>"><?php esc_html_e('View cart', 'sikshya'); ?></a>
    <?php endif; ?>
</div>
