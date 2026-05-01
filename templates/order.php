<?php
/**
 * Order receipt (scoped to current user) — {@see \Sikshya\Frontend\Site\OrderTemplateData}.
 *
 * @package Sikshya
 */

use Sikshya\Services\Frontend\OrderPageService;
use Sikshya\Presentation\Models\OrderPageModel;
use Sikshya\Core\Plugin;
use Sikshya\Frontend\Site\PublicPageUrls;

if (!empty($_GET['invoice'])) {
    $invoice_tpl = Plugin::getInstance()->getTemplatePath('order-invoice.php');
    if (is_string($invoice_tpl) && is_readable($invoice_tpl)) {
        require $invoice_tpl;
        return;
    }
}

/** @var OrderPageModel $page_model */
$page_model = OrderPageService::fromRequest();
$od = $page_model->toLegacyViewArray();
$u = $page_model->getUrls();

sikshya_get_header();

$label_course = function_exists('sikshya_label') ? sikshya_label('course', __('Course', 'sikshya'), 'frontend') : __('Course', 'sikshya');
$label_courses = function_exists('sikshya_label_plural') ? sikshya_label_plural('course', 'courses', __('Courses', 'sikshya'), 'frontend') : __('Courses', 'sikshya');
?>

<div class="sikshya-public sikshya-order sikshya-order-page sik-f-scope">
    <header class="sikshya-order-page__masthead">
        <div class="sikshya-order-page__masthead-inner">
            <nav class="sikshya-order-page__breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'sikshya'); ?>">
                <a href="<?php echo esc_url($u->getHomeUrl()); ?>"><?php esc_html_e('Home', 'sikshya'); ?></a>
                <span class="sikshya-order-page__bc-sep" aria-hidden="true">›</span>
                <a href="<?php echo esc_url($u->getAccountUrl()); ?>"><?php esc_html_e('Account', 'sikshya'); ?></a>
                <span class="sikshya-order-page__bc-sep" aria-hidden="true">›</span>
                <span class="sikshya-order-page__breadcrumb-current"><?php esc_html_e('Order', 'sikshya'); ?></span>
            </nav>
            <h1 class="sikshya-order-page__title"><?php esc_html_e('Order details', 'sikshya'); ?></h1>
            <p class="sikshya-order-page__lead">
                <?php if ($page_model->hasError()) : ?>
                    <?php esc_html_e('We could not load this order.', 'sikshya'); ?>
                <?php elseif ($page_model->getOrder()) : ?>
                    <?php
                    $lead_status = (string) $page_model->getOrder()->status;
                    if ($lead_status === 'paid') {
                        echo esc_html(sprintf(
                            /* translators: %s: singular label (e.g. course) */
                            __('Thank you. Your purchase is complete and %s access is available from your account.', 'sikshya'),
                            strtolower($label_course)
                        ));
                    } elseif ($lead_status === 'on-hold' || $lead_status === 'pending') {
                        esc_html_e('This order is waiting for payment or confirmation. Follow any instructions below, then check back after your administrator marks the order paid.', 'sikshya');
                    } else {
                        echo esc_html(sprintf(
                            /* translators: %s: plural label (e.g. courses) */
                            __('Summary of your order and purchased %s.', 'sikshya'),
                            strtolower($label_courses)
                        ));
                    }
                    ?>
                <?php else : ?>
                    <?php
                    echo esc_html(sprintf(
                        /* translators: %s: plural label (e.g. courses) */
                        __('Summary of your order and purchased %s.', 'sikshya'),
                        strtolower($label_courses)
                    ));
                    ?>
                <?php endif; ?>
            </p>
        </div>
    </header>

    <div class="sikshya-order-page__body">
        <?php if ($page_model->hasError()) : ?>
            <div class="sikshya-order-page__error" role="alert">
                <p class="sikshya-order-page__error-text"><?php echo esc_html($page_model->getError()); ?></p>
                <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url($u->getAccountUrl()); ?>"><?php esc_html_e('Back to account', 'sikshya'); ?></a>
            </div>
        <?php else : ?>
            <?php
            $o = $page_model->getOrder();
            if (!$o) {
                ?>
                <div class="sikshya-order-page__error" role="alert">
                    <p class="sikshya-order-page__error-text"><?php esc_html_e('Order not found.', 'sikshya'); ?></p>
                    <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url($u->getAccountUrl()); ?>"><?php esc_html_e('Back to account', 'sikshya'); ?></a>
                </div>
                <?php
            } else {
                $order_id = (int) $o->id;
                $currency = (string) $o->currency;
                $discount = isset($o->discount_total) ? (float) $o->discount_total : 0.0;
                $subtotal = isset($o->subtotal) ? (float) $o->subtotal : (float) $o->total + $discount;
                $order_meta = [];
                if (isset($o->meta) && is_string($o->meta) && $o->meta !== '') {
                    $decoded_meta = json_decode((string) $o->meta, true);
                    if (is_array($decoded_meta)) {
                        $order_meta = $decoded_meta;
                    }
                }
                $invoice = (isset($order_meta['invoice']) && is_array($order_meta['invoice'])) ? $order_meta['invoice'] : [];
                $invoice_no = isset($invoice['number']) ? (string) $invoice['number'] : '';
                $public_tok = isset($o->public_token) ? \Sikshya\Database\Repositories\OrderRepository::sanitizePublicToken((string) $o->public_token) : '';
                $invoice_href = ($invoice_no !== '' && $public_tok !== '' && (string) $o->status === 'paid')
                    ? PublicPageUrls::orderInvoiceView($public_tok)
                    : '';
                ?>
            <div class="sikshya-order-page__layout">
                <div class="sikshya-order-page__main">
                    <?php if ($page_model->getOfflineInstructionsHtml() !== '') : ?>
                        <section class="sikshya-order-page__offline" aria-labelledby="sikshya-order-offline-heading">
                            <h2 id="sikshya-order-offline-heading" class="sikshya-order-page__offline-title"><?php esc_html_e('How to pay', 'sikshya'); ?></h2>
                            <div class="sikshya-order-page__offline-body">
                                <?php echo wp_kses_post($page_model->getOfflineInstructionsHtml()); ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <section class="sikshya-order-page__panel" aria-labelledby="sikshya-order-items-heading">
                        <h2 id="sikshya-order-items-heading" class="sikshya-order-page__panel-title"><?php echo esc_html($label_courses); ?></h2>
                        <ul class="sikshya-order-page__lines">
                            <?php foreach ($page_model->getItems() as $it) : ?>
                                <?php
                                $cid = (int) $it->course_id;
                                $title = get_the_title($cid) ?: '#' . $cid;
                                $permalink = get_permalink($cid);
                                $line_total = isset($it->line_total) ? (float) $it->line_total : 0.0;
                                ?>
                                <li class="sikshya-order-page__line">
                                    <span class="sikshya-order-page__line-title">
                                        <?php if (is_string($permalink) && $permalink !== '') : ?>
                                            <a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a>
                                        <?php else : ?>
                                            <?php echo esc_html($title); ?>
                                        <?php endif; ?>
                                    </span>
                                    <span class="sikshya-order-page__line-price">
                                        <?php
                                        if ($line_total > 0 && function_exists('sikshya_format_price')) {
                                            echo wp_kses_post(sikshya_format_price($line_total, $currency));
                                        } elseif ($line_total <= 0) {
                                            esc_html_e('Free', 'sikshya');
                                        } else {
                                            echo esc_html(number_format_i18n($line_total, 2) . ' ' . $currency);
                                        }
                                        ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                </div>

                <aside class="sikshya-order-page__sidebar" aria-label="<?php esc_attr_e('Order summary', 'sikshya'); ?>">
                    <div class="sikshya-order-page__summary sik-f-card">
                        <p class="sikshya-order-page__summary-head"><?php esc_html_e('Order summary', 'sikshya'); ?></p>
                        <div class="sikshya-order-page__summary-meta">
                            <div class="sikshya-order-page__summary-row">
                                <span class="sikshya-order-page__summary-label"><?php esc_html_e('Order number', 'sikshya'); ?></span>
                                <span class="sikshya-order-page__summary-value">#<?php echo esc_html((string) $order_id); ?></span>
                            </div>
                            <div class="sikshya-order-page__summary-row">
                                <span class="sikshya-order-page__summary-label"><?php esc_html_e('Status', 'sikshya'); ?></span>
                                <span class="sikshya-order-page__summary-value">
                                    <?php
                                    $raw_status = (string) $o->status;
                                    $badge_cls = 'sikshya-order-page__badge sikshya-order-page__badge--pending';
                                    if ($raw_status === 'paid') {
                                        $badge_cls = 'sikshya-order-page__badge sikshya-order-page__badge--paid';
                                    } elseif ($raw_status === 'on-hold') {
                                        $badge_cls = 'sikshya-order-page__badge sikshya-order-page__badge--hold';
                                    }
                                    ?>
                                    <span class="<?php echo esc_attr($badge_cls); ?>">
                                        <?php echo esc_html($page_model->getStatusLabel()); ?>
                                    </span>
                                </span>
                            </div>
                            <div class="sikshya-order-page__summary-row">
                                <span class="sikshya-order-page__summary-label"><?php esc_html_e('Payment method', 'sikshya'); ?></span>
                                <span class="sikshya-order-page__summary-value"><?php echo esc_html($page_model->getGatewayLabel()); ?></span>
                            </div>
                            <?php if ($invoice_no !== '') : ?>
                                <div class="sikshya-order-page__summary-row">
                                    <span class="sikshya-order-page__summary-label"><?php esc_html_e('Invoice', 'sikshya'); ?></span>
                                    <span class="sikshya-order-page__summary-value"><?php echo esc_html($invoice_no); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($discount > 0.00001) : ?>
                            <div class="sikshya-order-page__summary-row sikshya-order-page__summary-row--subtotal">
                                <span class="sikshya-order-page__summary-label"><?php esc_html_e('Subtotal', 'sikshya'); ?></span>
                                <span class="sikshya-order-page__summary-value"><?php echo esc_html(number_format_i18n($subtotal, 2) . ' ' . $currency); ?></span>
                            </div>
                            <p class="sikshya-order-page__discount-note">
                                <?php
                                printf(
                                    /* translators: %s formatted discount amount */
                                    esc_html__('Discount: −%s', 'sikshya'),
                                    esc_html(number_format_i18n($discount, 2) . ' ' . $currency)
                                );
                                ?>
                            </p>
                        <?php endif; ?>
                        <div class="sikshya-order-page__total">
                            <span class="sikshya-order-page__total-label"><?php esc_html_e('Total', 'sikshya'); ?></span>
                            <span class="sikshya-order-page__total-value"><?php echo esc_html(number_format_i18n((float) $o->total, 2) . ' ' . $currency); ?></span>
                        </div>
                        <div class="sikshya-order-page__actions">
                            <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url($u->getAccountUrl()); ?>"><?php esc_html_e('My account', 'sikshya'); ?></a>
                        <?php if ($invoice_href !== '') : ?>
                            <a class="sikshya-btn sikshya-btn--ghost" href="<?php echo esc_url($invoice_href); ?>" target="_blank" rel="noopener noreferrer">
                                <?php esc_html_e('View invoice', 'sikshya'); ?>
                            </a>
                        <?php endif; ?>
                        <a class="sikshya-btn sikshya-btn--ghost" href="<?php echo esc_url($u->getCoursesUrl()); ?>">
                            <?php echo esc_html(sprintf(__('Browse %s', 'sikshya'), strtolower($label_courses))); ?>
                        </a>
                        </div>
                    </div>
                </aside>
            </div>
                <?php
            } // end $o present
            ?>
        <?php endif; ?>
    </div>
</div>

<?php
sikshya_get_footer();
