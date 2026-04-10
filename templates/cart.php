<?php
/**
 * Cart template (lines from {@see \Sikshya\Frontend\Public\CartTemplateData}).
 *
 * @package Sikshya
 */

use Sikshya\Frontend\Public\CartTemplateData;

$cart = CartTemplateData::build();
$u = $cart['urls'];

get_header();
?>

<div class="sikshya-public sikshya-cart sikshya-cart-page sik-f-scope">
    <header class="sikshya-cart-page__masthead">
        <div class="sikshya-cart-page__masthead-inner">
            <nav class="sikshya-cart-page__breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'sikshya'); ?>">
                <a href="<?php echo esc_url($u['home']); ?>"><?php esc_html_e('Home', 'sikshya'); ?></a>
                <span class="sikshya-cart-page__bc-sep" aria-hidden="true">›</span>
                <span class="sikshya-cart-page__breadcrumb-current"><?php esc_html_e('Cart', 'sikshya'); ?></span>
            </nav>
            <h1 class="sikshya-cart-page__title"><?php esc_html_e('Your cart', 'sikshya'); ?></h1>
            <p class="sikshya-cart-page__lead"><?php esc_html_e('Review your courses before checkout.', 'sikshya'); ?></p>
        </div>
    </header>

    <div class="sikshya-cart-page__body">
        <?php if ($cart['lines'] === []) : ?>
            <div class="sikshya-cart-page__empty">
                <div class="sikshya-cart-page__empty-icon" aria-hidden="true"></div>
                <p class="sikshya-muted"><?php esc_html_e('Your cart is empty.', 'sikshya'); ?></p>
                <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url($u['courses']); ?>"><?php esc_html_e('Browse courses', 'sikshya'); ?></a>
            </div>
        <?php else : ?>
            <div class="sikshya-cart-page__panel" role="region" aria-label="<?php esc_attr_e('Cart items', 'sikshya'); ?>">
                <div class="sikshya-cart-page__panel-head"><?php esc_html_e('Courses in cart', 'sikshya'); ?></div>
                <ul class="sikshya-cart-lines">
                    <?php foreach ($cart['lines'] as $line) : ?>
                        <li class="sikshya-cart-line">
                            <a class="sikshya-cart-line__title" href="<?php echo esc_url($line['permalink']); ?>"><?php echo esc_html($line['title']); ?></a>
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
                            <div class="sikshya-cart-line__remove">
                                <form method="post" action="<?php echo esc_url($u['cart']); ?>" class="sikshya-inline-form">
                                    <?php wp_nonce_field('sikshya_cart', 'sikshya_cart_nonce'); ?>
                                    <input type="hidden" name="sikshya_cart_action" value="remove" />
                                    <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $line['course_id']); ?>" />
                                    <button type="submit" class="sikshya-btn-link"><?php esc_html_e('Remove', 'sikshya'); ?></button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="sikshya-cart-page__summary">
                <p class="sikshya-cart-subtotal">
                    <strong><?php esc_html_e('Subtotal', 'sikshya'); ?></strong>
                    <span><?php echo esc_html(number_format_i18n($cart['subtotal_hint'], 2) . ' ' . $cart['currency']); ?></span>
                </p>

                <div class="sikshya-cart-actions">
                    <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url($u['checkout']); ?>"><?php esc_html_e('Proceed to checkout', 'sikshya'); ?></a>
                    <form method="post" action="<?php echo esc_url($u['cart']); ?>" class="sikshya-inline-form" onsubmit="return confirm('<?php echo esc_js(__('Clear the cart?', 'sikshya')); ?>');">
                        <?php wp_nonce_field('sikshya_cart', 'sikshya_cart_nonce'); ?>
                        <input type="hidden" name="sikshya_cart_action" value="clear" />
                        <button type="submit" class="sikshya-btn sikshya-btn--ghost"><?php esc_html_e('Clear cart', 'sikshya'); ?></button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
get_footer();
