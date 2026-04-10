<?php
/**
 * Order receipt (scoped to current user) — {@see \Sikshya\Frontend\Public\OrderTemplateData}.
 *
 * @package Sikshya
 */

use Sikshya\Frontend\Public\OrderTemplateData;

$od = OrderTemplateData::fromRequest();

get_header();
?>

<div class="sikshya-public sikshya-order">
    <div class="sikshya-container sikshya-container--narrow">
        <h1 class="sikshya-page-title"><?php esc_html_e('Order details', 'sikshya'); ?></h1>

        <?php if ($od['error'] !== '') : ?>
            <p class="sikshya-notice sikshya-notice--error"><?php echo esc_html($od['error']); ?></p>
            <a href="<?php echo esc_url($od['urls']['account']); ?>"><?php esc_html_e('Back to account', 'sikshya'); ?></a>
        <?php else : ?>
            <?php $o = $od['order']; ?>
            <dl class="sikshya-order-meta">
                <dt><?php esc_html_e('Order ID', 'sikshya'); ?></dt>
                <dd>#<?php echo esc_html((string) $o->id); ?></dd>
                <dt><?php esc_html_e('Status', 'sikshya'); ?></dt>
                <dd><?php echo esc_html((string) $o->status); ?></dd>
                <dt><?php esc_html_e('Total', 'sikshya'); ?></dt>
                <dd><?php echo esc_html(number_format_i18n((float) $o->total, 2) . ' ' . (string) $o->currency); ?></dd>
            </dl>
            <h2><?php esc_html_e('Items', 'sikshya'); ?></h2>
            <ul>
                <?php foreach ($od['items'] as $it) : ?>
                    <li>
                        <?php
                        $cid = (int) $it->course_id;
                        echo esc_html(get_the_title($cid) ?: '#' . $cid);
                        ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php
get_footer();
