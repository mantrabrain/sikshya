<?php
/**
 * One-off flash after cart POST redirects (add/remove/enroll/errors).
 *
 * @package Sikshya
 */

use Sikshya\Frontend\Public\CartFlashResolver;

if (!defined('ABSPATH')) {
    exit;
}

$__sikshya_cart_flash = CartFlashResolver::fromRequest();
if (!is_array($__sikshya_cart_flash) || empty($__sikshya_cart_flash['message'])) {
    return;
}
?>
<div class="sikshya-cart-flash sikshya-cart-flash--<?php echo esc_attr((string) ($__sikshya_cart_flash['type'] ?? 'info')); ?>" role="status">
    <?php echo esc_html((string) $__sikshya_cart_flash['message']); ?>
</div>
