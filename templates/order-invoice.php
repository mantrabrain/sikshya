<?php
/**
 * Order invoice (print-friendly HTML) — same access rules as {@see templates/order.php}.
 *
 * @package Sikshya
 */

use Sikshya\Services\Frontend\OrderPageService;
use Sikshya\Presentation\Models\OrderPageModel;

/** @var OrderPageModel $page_model */
$page_model = OrderPageService::fromRequest();
$u = $page_model->getUrls();
$o = $page_model->getOrder();

$meta = [];
if ($o && isset($o->meta) && is_string($o->meta) && $o->meta !== '') {
    $decoded = json_decode((string) $o->meta, true);
    if (is_array($decoded)) {
        $meta = $decoded;
    }
}
$inv = (isset($meta['invoice']) && is_array($meta['invoice'])) ? $meta['invoice'] : [];
$inv_no = isset($inv['number']) ? (string) $inv['number'] : '';
$issued = isset($inv['issued_at']) ? (string) $inv['issued_at'] : '';

$blocked = $page_model->hasError() || !$o || (string) ($o->status ?? '') !== 'paid' || $inv_no === '';

header('Content-Type: text/html; charset=' . get_bloginfo('charset'));

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo esc_html(sprintf(__('Invoice %s', 'sikshya'), $inv_no !== '' ? $inv_no : '')); ?> — <?php bloginfo('name'); ?></title>
    <style>
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;margin:0;padding:32px;color:#111;background:#fff}
        .wrap{max-width:900px;margin:0 auto}
        h1{font-size:22px;margin:0 0 6px}
        .muted{color:#555;font-size:13px}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin:22px 0}
        .box{border:1px solid #e6e6e6;border-radius:10px;padding:14px}
        table{width:100%;border-collapse:collapse;margin-top:14px}
        th,td{border-bottom:1px solid #eee;padding:10px 8px;text-align:left;font-size:14px}
        th{font-size:12px;letter-spacing:.04em;text-transform:uppercase;color:#666}
        .tot{font-weight:700}
        .actions{margin-top:18px;display:flex;gap:10px;flex-wrap:wrap}
        .btn{display:inline-block;border:1px solid #ddd;border-radius:10px;padding:10px 12px;text-decoration:none;color:#111;font-size:14px}
        @media print{
            .actions{display:none}
            body{padding:0}
        }
    </style>
</head>
<body>
<div class="wrap">
    <?php if ($blocked) : ?>
        <h1><?php esc_html_e('Invoice unavailable', 'sikshya'); ?></h1>
        <p class="muted">
            <?php esc_html_e('This invoice is not available for this order yet, or your link is invalid.', 'sikshya'); ?>
        </p>
        <div class="actions">
            <a class="btn" href="<?php echo esc_url($u->getAccountUrl()); ?>"><?php esc_html_e('My account', 'sikshya'); ?></a>
        </div>
    <?php else : ?>
        <h1><?php esc_html_e('Tax invoice', 'sikshya'); ?></h1>
        <div class="muted">
            <?php echo esc_html(get_bloginfo('name')); ?>
            <?php if ($issued !== '') : ?>
                · <?php echo esc_html(sprintf(__('Issued %s', 'sikshya'), $issued)); ?>
            <?php endif; ?>
        </div>

        <div class="grid">
            <div class="box">
                <div class="muted"><?php esc_html_e('Invoice', 'sikshya'); ?></div>
                <div style="font-size:18px;font-weight:700;margin-top:6px"><?php echo esc_html($inv_no); ?></div>
                <div class="muted" style="margin-top:10px">
                    <?php
                    printf(
                        /* translators: %d: internal order id */
                        esc_html__('Order #%d', 'sikshya'),
                        (int) $o->id
                    );
                    ?>
                </div>
            </div>
            <div class="box">
                <div class="muted"><?php esc_html_e('Bill to', 'sikshya'); ?></div>
                <?php
                $uid = (int) ($o->user_id ?? 0);
                $bill_name = '';
                $bill_email = '';
                if ($uid > 0) {
                    $urow = get_userdata($uid);
                    if ($urow) {
                        $bill_name = (string) ($urow->display_name ?: $urow->user_login);
                        $bill_email = (string) $urow->user_email;
                    }
                }
                if ($bill_name === '' && isset($meta['guest']['name'])) {
                    $bill_name = sanitize_text_field((string) $meta['guest']['name']);
                }
                if ($bill_email === '' && isset($meta['guest']['email'])) {
                    $bill_email = sanitize_email((string) $meta['guest']['email']);
                }
                ?>
                <div style="font-weight:700;margin-top:6px"><?php echo esc_html($bill_name !== '' ? $bill_name : __('Customer', 'sikshya')); ?></div>
                <?php if ($bill_email !== '') : ?>
                    <div class="muted" style="margin-top:6px"><?php echo esc_html($bill_email); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <table>
            <thead>
            <tr>
                <th><?php esc_html_e('Description', 'sikshya'); ?></th>
                <th><?php esc_html_e('Qty', 'sikshya'); ?></th>
                <th><?php esc_html_e('Unit', 'sikshya'); ?></th>
                <th><?php esc_html_e('Line total', 'sikshya'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php
            $currency = strtoupper((string) ($o->currency ?? 'USD'));
            foreach ($page_model->getItems() as $it) {
                $cid = (int) ($it->course_id ?? 0);
                $title = $cid > 0 ? get_the_title($cid) : '';
                if ($title === '') {
                    $title = sprintf(/* translators: %d: course id */ __('Course #%d', 'sikshya'), $cid);
                }
                $qty = isset($it->quantity) ? (int) $it->quantity : 1;
                $unit = isset($it->unit_price) ? (float) $it->unit_price : 0.0;
                $line = isset($it->line_total) ? (float) $it->line_total : 0.0;

                $fmt = static function (float $amount) use ($currency): string {
                    if (function_exists('sikshya_format_price')) {
                        return (string) sikshya_format_price($amount, $currency);
                    }

                    return number_format_i18n($amount, 2) . ' ' . $currency;
                };
                ?>
                <tr>
                    <td><?php echo esc_html((string) $title); ?></td>
                    <td><?php echo esc_html((string) max(1, $qty)); ?></td>
                    <td><?php echo wp_kses_post($fmt($unit)); ?></td>
                    <td class="tot"><?php echo wp_kses_post($fmt($line)); ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>

        <?php
        $discount = isset($o->discount_total) ? (float) $o->discount_total : 0.0;
        $subtotal = isset($o->subtotal) ? (float) $o->subtotal : (float) $o->total + $discount;
        $fmt2 = static function (float $amount) use ($currency): string {
            if (function_exists('sikshya_format_price')) {
                return (string) sikshya_format_price($amount, $currency);
            }

            return number_format_i18n($amount, 2) . ' ' . $currency;
        };
        ?>

        <div style="margin-top:16px;max-width:420px;margin-left:auto">
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eee">
                <span class="muted"><?php esc_html_e('Subtotal', 'sikshya'); ?></span>
                <span><?php echo wp_kses_post($fmt2($subtotal)); ?></span>
            </div>
            <?php if ($discount > 0.00001) : ?>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eee">
                    <span class="muted"><?php esc_html_e('Discount', 'sikshya'); ?></span>
                    <span>−<?php echo wp_kses_post($fmt2($discount)); ?></span>
                </div>
            <?php endif; ?>
            <div style="display:flex;justify-content:space-between;padding:10px 0">
                <span class="tot"><?php esc_html_e('Total', 'sikshya'); ?></span>
                <span class="tot"><?php echo wp_kses_post($fmt2((float) $o->total)); ?></span>
            </div>
            <div class="muted" style="margin-top:8px">
                <?php
                printf(
                    /* translators: 1: gateway id, 2: gateway reference */
                    esc_html__('Paid via %1$s · Reference: %2$s', 'sikshya'),
                    esc_html((string) ($o->gateway ?? '')),
                    esc_html((string) ($o->gateway_intent_id ?? ''))
                );
                ?>
            </div>
        </div>

        <div class="actions">
            <a class="btn" href="#" onclick="window.print();return false;"><?php esc_html_e('Print / Save as PDF', 'sikshya'); ?></a>
            <a class="btn" href="<?php echo esc_url($u->getAccountUrl()); ?>"><?php esc_html_e('My account', 'sikshya'); ?></a>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
