<?php
/**
 * Account: Certificates list.
 *
 * @package Sikshya
 *
 * @var array<string, mixed>                         $acc Back-compat view array for hooks.
 * @var \Sikshya\Presentation\Models\AccountPageModel $page_model
 */

if (!defined('ABSPATH')) {
    exit;
}

$certs = is_array($acc['certificates'] ?? null) ? (array) $acc['certificates'] : [];
?>
            <section class="sik-acc-panel" aria-label="<?php esc_attr_e('Certificates', 'sikshya'); ?>">
                <div class="sik-acc-panel__head">
                    <h2 class="sik-acc-panel__title"><?php esc_html_e('Certificates', 'sikshya'); ?></h2>
                </div>

                <?php if ($certs === []) : ?>
                    <div class="sik-acc-empty">
                        <?php esc_html_e('No certificates yet.', 'sikshya'); ?>
                    </div>
                <?php else : ?>
                    <div class="sik-acc-table-wrap">
                        <table class="sik-acc-table">
                            <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e('Course', 'sikshya'); ?></th>
                                <th scope="col"><?php esc_html_e('Issued', 'sikshya'); ?></th>
                                <th scope="col"><?php esc_html_e('Action', 'sikshya'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($certs as $c) : ?>
                                <?php
                                $course_id = (int) ($c['course_id'] ?? 0);
                                $issued_raw = (string) ($c['issued_date'] ?? '');
                                $issued_ts = $issued_raw !== '' ? strtotime($issued_raw) : false;
                                $issued_disp = $issued_ts ? wp_date(get_option('date_format'), $issued_ts) : '—';
                                $dl = (string) ($c['download_url'] ?? '');
                                ?>
                                <tr>
                                    <td>
                                        <?php if ($course_id > 0) : ?>
                                            <a href="<?php echo esc_url(get_permalink($course_id)); ?>"><?php echo esc_html(get_the_title($course_id)); ?></a>
                                        <?php else : ?>
                                            <?php echo esc_html((string) ($c['course_title'] ?? __('Course', 'sikshya'))); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($issued_disp); ?></td>
                                    <td>
                                        <?php if ($dl !== '') : ?>
                                            <a class="sikshya-btn sikshya-btn--primary sikshya-btn--sm" href="<?php echo esc_url($dl); ?>" target="_blank" rel="noopener">
                                                <?php esc_html_e('Download', 'sikshya'); ?>
                                            </a>
                                        <?php else : ?>
                                            <span class="sik-acc-badge sik-acc-badge--muted"><?php esc_html_e('Not available', 'sikshya'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

