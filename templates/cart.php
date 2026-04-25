<?php
/**
 * Cart template (lines from {@see \Sikshya\Frontend\Public\CartTemplateData}).
 *
 * @package Sikshya
 */

use Sikshya\Services\Frontend\CartPageService;
use Sikshya\Presentation\Models\CartPageModel;

/** @var CartPageModel $page */
$page = CartPageService::build();
$cart = $page->toLegacyViewArray();
$u = $page->getUrls();

get_header();
?>

<div class="sikshya-public sikshya-cart sikshya-cart-page sik-f-scope">
    <header class="sikshya-cart-page__masthead">
        <div class="sikshya-cart-page__masthead-inner">
            <nav class="sikshya-cart-page__breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'sikshya'); ?>">
                <a href="<?php echo esc_url($u->getHomeUrl()); ?>"><?php esc_html_e('Home', 'sikshya'); ?></a>
                <span class="sikshya-cart-page__bc-sep" aria-hidden="true">›</span>
                <span class="sikshya-cart-page__breadcrumb-current"><?php esc_html_e('Cart', 'sikshya'); ?></span>
            </nav>
            <h1 class="sikshya-cart-page__title"><?php esc_html_e('Your cart', 'sikshya'); ?></h1>
            <p class="sikshya-cart-page__lead"><?php esc_html_e('Review your courses before checkout.', 'sikshya'); ?></p>
        </div>
    </header>

    <div class="sikshya-cart-page__body">
        <?php require __DIR__ . '/partials/course-cart-flash.php'; ?>
        <?php if ($page->isEmpty()) : ?>
            <div class="sikshya-cart-page__empty">
                <div class="sikshya-cart-page__empty-copy">
                    <h2 class="sikshya-cart-page__empty-title"><?php esc_html_e('Your cart is empty', 'sikshya'); ?></h2>
                    <p class="sikshya-cart-page__empty-text"><?php esc_html_e('Browse courses and add the ones you want to learn next.', 'sikshya'); ?></p>
                    <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url($u->getCoursesUrl()); ?>"><?php esc_html_e('Browse courses', 'sikshya'); ?></a>
                </div>

                <div class="sikshya-cart-page__empty-illus" aria-hidden="true">
                    <svg viewBox="0 0 720 420" role="presentation" focusable="false">
                        <defs>
                            <linearGradient id="sikEmptyCard" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0" stop-color="rgba(99,102,241,0.18)" />
                                <stop offset="1" stop-color="rgba(99,102,241,0.06)" />
                            </linearGradient>
                            <linearGradient id="sikEmptyBg" x1="1" y1="0" x2="0" y2="1">
                                <stop offset="0" stop-color="rgba(15,23,42,0.06)" />
                                <stop offset="1" stop-color="rgba(15,23,42,0.02)" />
                            </linearGradient>
                        </defs>

                        <!-- soft backdrop -->
                        <rect x="56" y="46" width="608" height="328" rx="26" fill="url(#sikEmptyBg)" stroke="rgba(209,213,219,0.9)" />

                        <!-- “course card” -->
                        <rect x="112" y="92" width="360" height="238" rx="18" fill="white" stroke="rgba(229,231,235,1)" />
                        <rect x="112" y="92" width="360" height="118" rx="18" fill="url(#sikEmptyCard)" />
                        <rect x="144" y="232" width="220" height="16" rx="8" fill="rgba(17,24,39,0.14)" />
                        <rect x="144" y="258" width="168" height="12" rx="6" fill="rgba(17,24,39,0.10)" />
                        <rect x="144" y="286" width="96" height="34" rx="17" fill="rgba(99,102,241,0.14)" stroke="rgba(99,102,241,0.30)" />

                        <!-- “empty cart” icon floating -->
                        <g transform="translate(506 132)">
                            <rect x="0" y="0" width="132" height="132" rx="22" fill="white" stroke="rgba(229,231,235,1)" />
                            <path d="M36 52h12l8 44h40l8-30H48" fill="none" stroke="rgba(79,70,229,0.78)" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="62" cy="108" r="7" fill="rgba(79,70,229,0.78)"/>
                            <circle cx="98" cy="108" r="7" fill="rgba(79,70,229,0.78)"/>
                            <path d="M60 68h40" stroke="rgba(79,70,229,0.45)" stroke-width="8" stroke-linecap="round"/>
                        </g>
                    </svg>
                </div>
            </div>
        <?php else : ?>
            <?php
            /**
             * Render Pro / addon blocks above the cart items list (subscription notes,
             * bundle suggestions, etc.).
             */
            do_action('sikshya_cart_before_lines', $cart);
            ?>
            <?php if ($page->getBundleId() > 0 && $page->getBundleTitle() !== '') : ?>
                <div class="sikshya-cart-page__bundle-notice" style="margin-bottom:1rem;padding:0.75rem 1rem;border-radius:8px;border:1px solid rgba(34,197,94,0.35);background:rgba(34,197,94,0.06);">
                    <strong><?php esc_html_e('Bundle', 'sikshya'); ?></strong>
                    <?php echo esc_html($page->getBundleTitle()); ?>
                    <span style="opacity:.85;"> — <?php esc_html_e('Checkout uses the bundle price for the whole pack.', 'sikshya'); ?></span>
                </div>
            <?php endif; ?>
            <div class="sikshya-cart-page__panel" role="region" aria-label="<?php esc_attr_e('Cart items', 'sikshya'); ?>">
                <div class="sikshya-cart-page__panel-head"><?php esc_html_e('Courses in cart', 'sikshya'); ?></div>
                <ul class="sikshya-cart-lines">
                    <?php foreach ($page->getLines() as $line) : ?>
                        <li class="sikshya-cart-line">
                            <a class="sikshya-cart-line__thumb" href="<?php echo esc_url($line['permalink']); ?>" aria-hidden="true" tabindex="-1">
                                <?php if (!empty($line['thumbnail'])) : ?>
                                    <img class="sikshya-cart-line__thumb-img" src="<?php echo esc_url($line['thumbnail']); ?>" alt="" loading="lazy" decoding="async" />
                                <?php else : ?>
                                    <span class="sikshya-cart-line__thumb-placeholder" aria-hidden="true"></span>
                                <?php endif; ?>
                            </a>

                            <div class="sikshya-cart-line__content">
                                <a class="sikshya-cart-line__title" href="<?php echo esc_url($line['permalink']); ?>"><?php echo esc_html($line['title']); ?></a>
                                <?php if (!empty($line['instructor'])) : ?>
                                    <p class="sikshya-cart-line__meta">
                                        <?php
                                        printf(
                                            /* translators: %s: instructor name */
                                            esc_html__('By %s', 'sikshya'),
                                            esc_html((string) $line['instructor'])
                                        );
                                        ?>
                                    </p>
                                <?php endif; ?>
                            </div>

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
                                <form method="post" action="<?php echo esc_url($u->getCartUrl()); ?>" class="sikshya-inline-form">
                                    <?php wp_nonce_field('sikshya_cart', 'sikshya_cart_nonce'); ?>
                                    <input type="hidden" name="sikshya_cart_action" value="remove" />
                                    <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $line['course_id']); ?>" />
                                    <button type="submit" class="sikshya-btn-link sikshya-btn-link--danger" aria-label="<?php esc_attr_e('Remove course from cart', 'sikshya'); ?>">
                                        <?php esc_html_e('Remove', 'sikshya'); ?>
                                    </button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="sikshya-cart-page__summary">
                <?php
                /**
                 * Render Pro / addon blocks inside the cart summary, above the subtotal
                 * (advanced coupon notices, bundle savings, etc.).
                 */
                do_action('sikshya_cart_summary_before', $cart);
                ?>
                <p class="sikshya-cart-subtotal">
                    <strong><?php esc_html_e('Subtotal', 'sikshya'); ?></strong>
                    <span><?php echo esc_html(number_format_i18n($page->getSubtotalHint(), 2) . ' ' . $page->getCurrency()); ?></span>
                </p>

                <p class="sikshya-cart-page__summary-note">
                    <?php esc_html_e('You’ll get instant access after successful payment. Free courses enroll immediately.', 'sikshya'); ?>
                </p>

                <div class="sikshya-cart-actions">
                    <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url($u->getCheckoutUrl()); ?>"><?php esc_html_e('Proceed to checkout', 'sikshya'); ?></a>
                    <form method="post" action="<?php echo esc_url($u->getCartUrl()); ?>" class="sikshya-inline-form" onsubmit="return confirm('<?php echo esc_js(__('Clear the cart?', 'sikshya')); ?>');">
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
