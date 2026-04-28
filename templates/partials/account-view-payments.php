<?php
/**
 * Account: Payments (orders + legacy payment rows).
 *
 * @package Sikshya
 *
 * @var array<string, mixed>                         $acc Back-compat view array for hooks.
 * @var \Sikshya\Presentation\Models\AccountPageModel $page_model
 */

use Sikshya\Frontend\Public\PublicPageUrls;

?>
            <section class="sik-acc-panel" aria-label="<?php esc_attr_e('Orders', 'sikshya'); ?>">
                <div class="sik-acc-panel__head">
                    <h2 class="sik-acc-panel__title"><?php esc_html_e('Orders', 'sikshya'); ?></h2>
                    <a class="sik-acc-panel__link" href="<?php echo esc_url($page_model->getUrls()->getCheckoutUrl()); ?>"><?php esc_html_e('Checkout', 'sikshya'); ?></a>
                </div>
                <?php if ($page_model->getOrders() === []) : ?>
                    <div class="sik-acc-empty"><?php esc_html_e('No orders yet.', 'sikshya'); ?></div>
                <?php else : ?>
                    <div class="sik-acc-table-wrap">
                        <table class="sik-acc-table sik-acc-table--wide">
                            <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e('Order', 'sikshya'); ?></th>
                                <th scope="col"><?php esc_html_e('Total', 'sikshya'); ?></th>
                                <th scope="col"><?php esc_html_e('Gateway', 'sikshya'); ?></th>
                                <th scope="col"><?php esc_html_e('Status', 'sikshya'); ?></th>
                                <th scope="col"><?php echo esc_html(_x('Date', 'order date column', 'sikshya')); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($page_model->getOrders() as $ord) : ?>
                                <?php
                                $otok = isset($ord->public_token) ? \Sikshya\Database\Repositories\OrderRepository::sanitizePublicToken((string) $ord->public_token) : '';
                                $order_href = $otok !== '' ? PublicPageUrls::orderView($otok) : PublicPageUrls::url('order');
                                $created = isset($ord->created_at) ? strtotime((string) $ord->created_at) : false;
                                $created_disp = $created ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $created) : '—';
                                $ostatus = strtolower((string) ($ord->status ?? ''));
                                $currency = strtoupper((string) ($ord->currency ?? 'USD'));
                                $total = isset($ord->total) ? (float) $ord->total : 0.0;
                                $gateway = (string) ($ord->gateway ?? '');
                                ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo esc_url($order_href); ?>">
                                            <?php printf(esc_html__('Order #%d', 'sikshya'), (int) $ord->id); ?>
                                        </a>
                                        <div class="sik-acc-muted"><?php esc_html_e('View receipt', 'sikshya'); ?></div>
                                    </td>
                                    <td><?php echo esc_html(number_format_i18n($total, 2) . ' ' . $currency); ?></td>
                                    <td><?php echo esc_html($gateway !== '' ? $gateway : '—'); ?></td>
                                    <td>
                                        <?php if (in_array($ostatus, ['paid', 'completed'], true)) : ?>
                                            <span class="sik-acc-badge"><?php echo esc_html(ucfirst($ostatus)); ?></span>
                                        <?php else : ?>
                                            <span class="sik-acc-badge sik-acc-badge--muted"><?php echo esc_html($ostatus !== '' ? ucfirst($ostatus) : __('Unknown', 'sikshya')); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($created_disp); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($page_model->getLegacyPayments() !== []) : ?>
            <section class="sik-acc-panel" aria-label="<?php esc_attr_e('Payment records', 'sikshya'); ?>">
                <div class="sik-acc-panel__head">
                    <h2 class="sik-acc-panel__title"><?php esc_html_e('Payment records', 'sikshya'); ?></h2>
                </div>
                <p class="sik-acc-panel__lead"><?php esc_html_e('Additional payment entries stored for your account (legacy or gateway logs).', 'sikshya'); ?></p>
                <div class="sik-acc-table-wrap">
                    <table class="sik-acc-table sik-acc-table--wide">
                        <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('Amount', 'sikshya'); ?></th>
                            <th scope="col"><?php esc_html_e('Status', 'sikshya'); ?></th>
                            <th scope="col"><?php esc_html_e('Method', 'sikshya'); ?></th>
                            <th scope="col"><?php esc_html_e('Reference', 'sikshya'); ?></th>
                            <th scope="col"><?php esc_html_e('Date', 'sikshya'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($page_model->getLegacyPayments() as $lp) : ?>
                            <?php
                            if (!is_array($lp)) {
                                continue;
                            }
                            $amt = isset($lp['amount']) ? (float) $lp['amount'] : 0.0;
                            $cur = strtoupper((string) ($lp['currency'] ?? 'USD'));
                            $pst = strtolower((string) ($lp['status'] ?? ''));
                            $pm = (string) ($lp['payment_method'] ?? '');
                            $tx = (string) ($lp['transaction_id'] ?? '');
                            $pd = (string) ($lp['payment_date'] ?? '');
                            $pts = $pd ? strtotime($pd) : false;
                            $pd_disp = $pts ? wp_date(get_option('date_format'), $pts) : '—';
                            $course_id = isset($lp['course_id']) ? (int) $lp['course_id'] : 0;
                            ?>
                            <tr>
                                <td><?php echo esc_html(number_format_i18n($amt, 2) . ' ' . $cur); ?></td>
                                <td>
                                    <?php if ($pst === 'completed') : ?>
                                        <span class="sik-acc-badge"><?php esc_html_e('Completed', 'sikshya'); ?></span>
                                    <?php else : ?>
                                        <span class="sik-acc-badge sik-acc-badge--muted"><?php echo esc_html($pst !== '' ? ucfirst($pst) : '—'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($pm !== '' ? $pm : '—'); ?></td>
                                <td>
                                    <?php if ($tx !== '') : ?>
                                        <code class="sik-acc-code"><?php echo esc_html($tx); ?></code>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo esc_html($pd_disp); ?>
                                    <?php if ($course_id > 0) : ?>
                                        <div class="sik-acc-muted">
                                            <a href="<?php echo esc_url(get_permalink($course_id)); ?>"><?php echo esc_html(get_the_title($course_id)); ?></a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endif; ?>

            <?php do_action('sikshya_account_payments_after', $acc); ?>
