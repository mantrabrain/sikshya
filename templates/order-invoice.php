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

$pdf_mode = isset($_GET['pdf']) && (string) $_GET['pdf'] === '1';

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo esc_html(sprintf(__('Invoice %s', 'sikshya'), $inv_no !== '' ? $inv_no : '')); ?> — <?php bloginfo('name'); ?></title>
    <style>
        /* A4-friendly defaults for both print and PDF export */
        @page{size:A4;margin:12mm}
        :root{
            --ink:#0f172a;--muted:#475569;--subtle:#64748b;--line:#e2e8f0;--line2:#f1f5f9;
            --card:#ffffff;--bg:#ffffff;--accent:#2563eb;
        }
        *{box-sizing:border-box}
        body{font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;margin:0;padding:20px;color:var(--ink);background:var(--bg);-webkit-font-smoothing:antialiased}
        .wrap{max-width:920px;margin:0 auto}
        .topbar{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;padding-bottom:14px;border-bottom:1px solid var(--line)}
        .brand{display:flex;flex-direction:column;gap:6px;min-width:260px}
        .brand__name{font-weight:800;font-size:18px;letter-spacing:-0.02em}
        .brand__meta{color:var(--muted);font-size:12.5px;line-height:1.35}
        .docTitle{display:flex;flex-direction:column;align-items:flex-end;gap:6px;min-width:260px}
        .docTitle__h{font-weight:900;font-size:20px;letter-spacing:-0.02em}
        .pill{display:inline-flex;align-items:center;gap:8px;border:1px solid rgba(37,99,235,.25);background:rgba(37,99,235,.06);color:var(--ink);padding:6px 10px;border-radius:999px;font-size:12px}
        .pill strong{font-weight:800}
        .metaGrid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:16px}
        .card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:14px}
        .card__label{color:var(--subtle);font-size:11px;letter-spacing:.08em;text-transform:uppercase;font-weight:700}
        .card__value{margin-top:6px;font-size:14px;font-weight:700;color:var(--ink)}
        .card__sub{margin-top:6px;color:var(--muted);font-size:12.5px;line-height:1.35}
        .kv{display:grid;grid-template-columns:1fr auto;gap:10px;margin-top:10px}
        .kv div{padding:10px 0;border-bottom:1px solid var(--line2);font-size:13.5px}
        .kv div:last-child{border-bottom:none}
        .kv .k{color:var(--muted)}
        .kv .v{font-weight:800}
        table{width:100%;border-collapse:separate;border-spacing:0;margin-top:16px;border:1px solid var(--line);border-radius:14px;overflow:hidden}
        thead th{background:#f8fafc;color:var(--subtle);font-size:11px;letter-spacing:.08em;text-transform:uppercase;padding:12px 12px;text-align:left;border-bottom:1px solid var(--line)}
        tbody td{padding:12px 12px;border-bottom:1px solid var(--line2);font-size:13.5px;vertical-align:top}
        tbody tr:last-child td{border-bottom:none}
        td.num,th.num{text-align:right;font-variant-numeric:tabular-nums}
        .desc{font-weight:700;color:var(--ink)}
        .footerRow{display:flex;gap:14px;align-items:flex-start;justify-content:space-between;margin-top:16px}
        .note{flex:1;min-width:260px;color:var(--muted);font-size:12.5px;line-height:1.45}
        .totals{width:min(380px,100%)}
        .totals .kv{margin-top:0}
        .totals .kv .v{font-size:14px}
        .totals .grand{margin-top:10px;border-top:1px dashed var(--line);padding-top:10px}
        .totals .grand .v{font-size:15px}
        .actions{margin-top:18px;display:flex;gap:10px;flex-wrap:wrap}
        .btn{display:inline-flex;align-items:center;gap:8px;border:1px solid var(--line);border-radius:12px;padding:10px 12px;text-decoration:none;color:var(--ink);font-size:13px;font-weight:700;background:#fff}
        .btn:hover{border-color:#cbd5e1}
        .btn.primary{border-color:rgba(37,99,235,.35);background:rgba(37,99,235,.06)}
        .btn.primary:hover{border-color:rgba(37,99,235,.55)}
        @media print{
            .actions{display:none}
            body{padding:0}
            .wrap{max-width:none}
            .topbar{border-bottom-color:#d1d5db}
            table{border-color:#d1d5db}
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
        <?php
        $site_name = (string) get_bloginfo('name');
        $site_url = (string) home_url('/');
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
        $order_id_int = (int) ($o->id ?? 0);
        $gateway_id = (string) ($o->gateway ?? '');
        $gateway_ref = (string) ($o->gateway_intent_id ?? '');
        ?>

        <header class="topbar">
            <div class="brand">
                <div class="brand__name"><?php echo esc_html($site_name !== '' ? $site_name : __('Invoice', 'sikshya')); ?></div>
                <div class="brand__meta">
                    <?php echo esc_html(rtrim($site_url, '/')); ?><br/>
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: %d: order id */
                            __('Order #%d', 'sikshya'),
                            $order_id_int
                        )
                    );
                    ?>
                </div>
            </div>
            <div class="docTitle">
                <div class="docTitle__h"><?php esc_html_e('Tax invoice', 'sikshya'); ?></div>
                <div class="pill">
                    <strong><?php echo esc_html($inv_no); ?></strong>
                    <?php if ($issued !== '') : ?>
                        <span><?php echo esc_html(sprintf(__('Issued %s', 'sikshya'), $issued)); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <section class="metaGrid" aria-label="<?php esc_attr_e('Invoice summary', 'sikshya'); ?>">
            <div class="card">
                <div class="card__label"><?php esc_html_e('Bill to', 'sikshya'); ?></div>
                <div class="card__value"><?php echo esc_html($bill_name !== '' ? $bill_name : __('Customer', 'sikshya')); ?></div>
                <?php if ($bill_email !== '') : ?>
                    <div class="card__sub"><?php echo esc_html($bill_email); ?></div>
                <?php endif; ?>
            </div>
            <div class="card">
                <div class="card__label"><?php esc_html_e('Payment', 'sikshya'); ?></div>
                <div class="card__value">
                    <?php
                    echo esc_html($gateway_id !== '' ? strtoupper($gateway_id) : __('—', 'sikshya'));
                    ?>
                </div>
                <?php if ($gateway_ref !== '') : ?>
                    <div class="card__sub">
                        <?php
                        echo esc_html(__('Reference', 'sikshya') . ': ' . $gateway_ref);
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <table aria-label="<?php esc_attr_e('Invoice items', 'sikshya'); ?>">
            <thead>
            <tr>
                <th><?php esc_html_e('Description', 'sikshya'); ?></th>
                <th class="num"><?php esc_html_e('Qty', 'sikshya'); ?></th>
                <th class="num"><?php esc_html_e('Unit', 'sikshya'); ?></th>
                <th class="num"><?php esc_html_e('Line total', 'sikshya'); ?></th>
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
                    <td><div class="desc"><?php echo esc_html((string) $title); ?></div></td>
                    <td class="num"><?php echo esc_html((string) max(1, $qty)); ?></td>
                    <td class="num"><?php echo wp_kses_post($fmt($unit)); ?></td>
                    <td class="num"><span style="font-weight:800"><?php echo wp_kses_post($fmt($line)); ?></span></td>
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

        <div class="footerRow">
            <div class="note">
                <?php esc_html_e('Thank you for your purchase. Keep this invoice for your records.', 'sikshya'); ?>
            </div>
            <div class="totals card">
                <div class="card__label"><?php esc_html_e('Totals', 'sikshya'); ?></div>
                <div class="kv" aria-label="<?php esc_attr_e('Totals summary', 'sikshya'); ?>">
                    <div class="k"><?php esc_html_e('Subtotal', 'sikshya'); ?></div>
                    <div class="v"><?php echo wp_kses_post($fmt2($subtotal)); ?></div>
                    <?php if ($discount > 0.00001) : ?>
                        <div class="k"><?php esc_html_e('Discount', 'sikshya'); ?></div>
                        <div class="v">−<?php echo wp_kses_post($fmt2($discount)); ?></div>
                    <?php endif; ?>
                    <div class="k grand"><?php esc_html_e('Total', 'sikshya'); ?></div>
                    <div class="v grand"><?php echo wp_kses_post($fmt2((float) $o->total)); ?></div>
                </div>
            </div>
        </div>

        <div class="actions">
            <a class="btn" href="#" onclick="window.print();return false;"><?php esc_html_e('Print', 'sikshya'); ?></a>
            <a class="btn primary" href="#" id="sikshya-invoice-download-pdf"><?php esc_html_e('Download PDF', 'sikshya'); ?></a>
            <a class="btn" href="<?php echo esc_url($u->getAccountUrl()); ?>"><?php esc_html_e('My account', 'sikshya'); ?></a>
        </div>
    <?php endif; ?>
</div>

<?php if (!$blocked) : ?>
    <?php
    $jspdf_src = defined('SIKSHYA_PLUGIN_URL')
        ? rtrim((string) SIKSHYA_PLUGIN_URL, '/') . '/assets/public/vendor/jspdf.umd.min.js'
        : '';
    $html2canvas_src = defined('SIKSHYA_PLUGIN_URL')
        ? rtrim((string) SIKSHYA_PLUGIN_URL, '/') . '/assets/public/vendor/html2canvas.min.js'
        : '';
    $html_to_image_src = defined('SIKSHYA_PLUGIN_URL')
        ? rtrim((string) SIKSHYA_PLUGIN_URL, '/') . '/assets/public/vendor/html-to-image.min.js'
        : '';
    $fname = 'invoice-' . preg_replace('/[^a-zA-Z0-9_\\-]+/', '-', (string) ($inv_no !== '' ? $inv_no : ('order-' . (int) ($o->id ?? 0)))) . '.pdf';
    ?>
    <script>
      (function () {
        var btn = document.getElementById('sikshya-invoice-download-pdf');
        var wrap = document.querySelector('.wrap');
        if (!btn || !wrap) return;

        function toast(msg) {
          try {
            var el = document.querySelector('.actions');
            if (!el) return;
            btn.textContent = msg || 'Download PDF';
            window.clearTimeout(btn._sikshyaT || 0);
            btn._sikshyaT = window.setTimeout(function () {
              btn.textContent = <?php echo wp_json_encode(__('Download PDF', 'sikshya')); ?>;
            }, 1500);
          } catch (e) {}
        }

        async function ensureJsPdf() {
          if (window.jspdf && window.jspdf.jsPDF) return window.jspdf.jsPDF;
          return await new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = <?php echo wp_json_encode($jspdf_src); ?>;
            s.async = true;
            s.onload = function () {
              resolve(window.jspdf && window.jspdf.jsPDF ? window.jspdf.jsPDF : null);
            };
            s.onerror = function () {
              reject(new Error('jsPDF failed to load'));
            };
            document.head.appendChild(s);
          });
        }

        async function ensureHtml2Canvas() {
          if (window.html2canvas) return window.html2canvas;
          return await new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = <?php echo wp_json_encode($html2canvas_src); ?>;
            s.async = true;
            s.onload = function () {
              resolve(window.html2canvas);
            };
            s.onerror = function () {
              reject(new Error('html2canvas failed to load'));
            };
            document.head.appendChild(s);
          });
        }

        async function ensureHtmlToImage() {
          if (window.htmlToImage && window.htmlToImage.toPng) return window.htmlToImage;
          return await new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = <?php echo wp_json_encode($html_to_image_src); ?>;
            s.async = true;
            s.onload = function () {
              resolve(window.htmlToImage);
            };
            s.onerror = function () {
              reject(new Error('html-to-image failed to load'));
            };
            document.head.appendChild(s);
          });
        }

        function capturePixelRatio(el) {
          var rect = el.getBoundingClientRect();
          var tw = Math.max(1, Math.round(rect.width));
          var th = Math.max(1, Math.round(rect.height));
          var pr = 2;
          return { tw: tw, th: th, pr: pr };
        }

        async function rasterToJpegDataUrl(pngDataUrl, q) {
          var img = await new Promise(function (resolve, reject) {
            var i = new Image();
            i.onload = function () { resolve(i); };
            i.onerror = function () { reject(new Error('image load failed')); };
            i.src = pngDataUrl;
          });
          var c = document.createElement('canvas');
          c.width = img.naturalWidth;
          c.height = img.naturalHeight;
          var ctx = c.getContext('2d');
          ctx.fillStyle = '#ffffff';
          ctx.fillRect(0, 0, c.width, c.height);
          ctx.drawImage(img, 0, 0);
          return c.toDataURL('image/jpeg', q);
        }

        async function captureRasterPngDataUrl() {
          var el = wrap;
          var m = capturePixelRatio(el);
          try {
            var hti = await ensureHtmlToImage();
            return await hti.toPng(el, {
              pixelRatio: m.pr,
              cacheBust: true,
              backgroundColor: '#ffffff',
              width: m.tw,
              height: m.th,
              style: { transform: 'none' },
            });
          } catch (e) {}
          var h2c = await ensureHtml2Canvas();
          var canvas = await h2c(el, {
            backgroundColor: '#ffffff',
            scale: m.pr,
            useCORS: true,
            allowTaint: false,
            foreignObjectRendering: true,
            logging: false,
            width: m.tw,
            height: m.th,
          });
          return canvas.toDataURL('image/png');
        }

        async function downloadPdf() {
          try {
            toast(<?php echo wp_json_encode(__('Preparing…', 'sikshya')); ?>);
            var J = await ensureJsPdf();
            if (!J) throw new Error('jsPDF missing');
            var png = await captureRasterPngDataUrl();
            var jpeg = await rasterToJpegDataUrl(png, 0.93);
            var img = await new Promise(function (resolve, reject) {
              var i = new Image();
              i.onload = function () { resolve(i); };
              i.onerror = function () { reject(new Error('image load failed')); };
              i.src = jpeg;
            });

            // Always export to A4.
            var pdf = new J({ orientation: 'p', unit: 'mm', format: 'a4' });
            var pageW = 210, pageH = 297;
            var margin = 10;
            var contentW = pageW - margin * 2;
            var contentH = pageH - margin * 2;

            // Fit image to A4 width, paginate if needed.
            var imgW = contentW;
            var imgH = (img.naturalHeight * imgW) / Math.max(1, img.naturalWidth);

            // Helper to add a cropped slice of the image to a page.
            function addSliceToPage(srcImg, sx, sy, sw, sh) {
              var c = document.createElement('canvas');
              c.width = Math.max(1, Math.round(sw));
              c.height = Math.max(1, Math.round(sh));
              var ctx = c.getContext('2d');
              ctx.fillStyle = '#ffffff';
              ctx.fillRect(0, 0, c.width, c.height);
              ctx.drawImage(srcImg, sx, sy, sw, sh, 0, 0, c.width, c.height);
              var dataUrl = c.toDataURL('image/jpeg', 0.92);
              var mmH = (c.height * imgW) / Math.max(1, c.width); // scaled height in mm at imgW
              pdf.addImage(dataUrl, 'JPEG', margin, margin, imgW, Math.min(contentH, mmH));
            }

            if (imgH <= contentH + 0.01) {
              pdf.addImage(jpeg, 'JPEG', margin, margin, imgW, imgH);
            } else {
              // Slice the source image into page-sized chunks.
              var slicePxH = Math.floor((contentH * img.naturalWidth) / Math.max(1, imgW)); // pixels per page at this scale
              if (slicePxH < 50) slicePxH = 50;
              var y = 0;
              var first = true;
              while (y < img.naturalHeight) {
                if (!first) pdf.addPage('a4', 'p');
                first = false;
                var hpx = Math.min(slicePxH, img.naturalHeight - y);
                addSliceToPage(img, 0, y, img.naturalWidth, hpx);
                y += hpx;
              }
            }

            pdf.save(<?php echo wp_json_encode($fname); ?>);
            toast(<?php echo wp_json_encode(__('Downloaded', 'sikshya')); ?>);
          } catch (e) {
            toast(<?php echo wp_json_encode(__('Could not generate PDF. Use Print instead.', 'sikshya')); ?>);
          }
        }

        btn.addEventListener('click', function (ev) {
          ev.preventDefault();
          void downloadPdf();
        });

        <?php if ($pdf_mode) : ?>
        try { void downloadPdf(); } catch (e) {}
        <?php endif; ?>
      })();
    </script>
<?php endif; ?>
</body>
</html>
