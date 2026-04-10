<?php
/**
 * Cart template (lines from {@see \Sikshya\Frontend\Public\CartTemplateData}).
 *
 * @package Sikshya
 */

use Sikshya\Frontend\Public\CartTemplateData;

$cart = CartTemplateData::build();

get_header();
?>

<div class="sikshya-public sikshya-cart">
    <div class="sikshya-container sikshya-container--narrow">
        <h1 class="sikshya-page-title"><?php esc_html_e('Your cart', 'sikshya'); ?></h1>

        <?php if ($cart['lines'] === []) : ?>
            <p class="sikshya-muted"><?php esc_html_e('Your cart is empty.', 'sikshya'); ?></p>
            <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url($cart['urls']['courses']); ?>"><?php esc_html_e('Browse courses', 'sikshya'); ?></a>
        <?php else : ?>
            <ul class="sikshya-cart-lines">
                <?php foreach ($cart['lines'] as $line) : ?>
                    <li class="sikshya-cart-line">
                        <a href="<?php echo esc_url($line['permalink']); ?>"><?php echo esc_html($line['title']); ?></a>
                        <span class="sikshya-cart-line__price">
                            <?php
                            $pr = $line['pricing'];
                            $eff = isset($pr['effective']) && $pr['effective'] !== null ? (float) $pr['effective'] : 0;
                            if ($eff > 0) {
                                echo wp_kses_post(sikshya_format_price($eff, $pr['currency'] ?? 'USD'));
                            } else {
                                esc_html_e('Free', 'sikshya');
                            }
                            ?>
                        </span>
                        <form method="post" action="" class="sikshya-inline-form">
                            <?php wp_nonce_field('sikshya_cart', 'sikshya_cart_nonce'); ?>
                            <input type="hidden" name="sikshya_cart_action" value="remove" />
                            <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $line['course_id']); ?>" />
                            <button type="submit" class="sikshya-btn-link"><?php esc_html_e('Remove', 'sikshya'); ?></button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>

            <p class="sikshya-cart-subtotal">
                <strong><?php esc_html_e('Subtotal', 'sikshya'); ?>:</strong>
                <?php echo esc_html(number_format_i18n($cart['subtotal_hint'], 2) . ' ' . esc_html($cart['currency'])); ?>
            </p>

            <div class="sikshya-cart-actions">
                <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url($cart['urls']['checkout']); ?>"><?php esc_html_e('Proceed to checkout', 'sikshya'); ?></a>
                <form method="post" action="" class="sikshya-inline-form" onsubmit="return confirm('<?php echo esc_js(__('Clear the cart?', 'sikshya')); ?>');">
                    <?php wp_nonce_field('sikshya_cart', 'sikshya_cart_nonce'); ?>
                    <input type="hidden" name="sikshya_cart_action" value="clear" />
                    <button type="submit" class="sikshya-btn sikshya-btn--ghost"><?php esc_html_e('Clear cart', 'sikshya'); ?></button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
get_footer();
