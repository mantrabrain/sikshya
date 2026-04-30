<?php
/**
 * Account: My learning (ongoing + completed enrollments).
 *
 * @package Sikshya
 *
 * @var array<string, mixed>                         $acc Back-compat view array for hooks.
 * @var \Sikshya\Presentation\Models\AccountPageModel $page_model
 */

use Sikshya\Frontend\Public\PublicPageUrls;

$label_course = function_exists('sikshya_label') ? sikshya_label('course', __('Course', 'sikshya'), 'frontend') : __('Course', 'sikshya');
$label_courses = function_exists('sikshya_label_plural') ? sikshya_label_plural('course', 'courses', __('Courses', 'sikshya'), 'frontend') : __('Courses', 'sikshya');

$certs_by_course = is_array($acc['certificates_by_course'] ?? null) ? (array) $acc['certificates_by_course'] : [];
$render_enrollment_row = static function ($row) use ($certs_by_course): void {
    $cid = is_object($row) ? (int) ($row->course_id ?? 0) : (int) ($row['course_id'] ?? 0);
    if ($cid <= 0) {
        return;
    }
    $estatus = is_object($row) ? (string) ($row->status ?? '') : (string) ($row['status'] ?? '');
    $enrolled_raw = is_object($row) ? ($row->enrolled_date ?? '') : ($row['enrolled_date'] ?? '');
    $enrolled_ts = $enrolled_raw ? strtotime((string) $enrolled_raw) : false;
    $enrolled_disp = $enrolled_ts ? wp_date(get_option('date_format'), $enrolled_ts) : '—';
    ?>
    <tr>
        <td>
            <a href="<?php echo esc_url(get_permalink($cid)); ?>"><?php echo esc_html(get_the_title($cid)); ?></a>
            <br>
            <a href="<?php echo esc_url(PublicPageUrls::learnForCourse($cid)); ?>"><?php esc_html_e('Open player', 'sikshya'); ?></a>
            <?php
            $cert = ($estatus === 'completed' && isset($certs_by_course[$cid]) && is_array($certs_by_course[$cid])) ? $certs_by_course[$cid] : null;
            $cert_dl = is_array($cert) ? (string) ($cert['download_url'] ?? '') : '';
            if ($cert_dl !== '') :
                ?>
                <br>
                <a href="<?php echo esc_url($cert_dl); ?>" target="_blank" rel="noopener"><?php esc_html_e('Download certificate', 'sikshya'); ?></a>
            <?php endif; ?>
        </td>
        <td>
            <?php if ($estatus === 'completed') : ?>
                <span class="sik-acc-badge"><?php esc_html_e('Completed', 'sikshya'); ?></span>
            <?php else : ?>
                <span class="sik-acc-badge sik-acc-badge--muted"><?php echo esc_html($estatus !== '' ? ucfirst($estatus) : __('Active', 'sikshya')); ?></span>
            <?php endif; ?>
        </td>
        <td><?php echo esc_html($enrolled_disp); ?></td>
    </tr>
    <?php
};
?>
            <section class="sik-acc-panel" aria-label="<?php echo esc_attr(sprintf(__('Ongoing %s', 'sikshya'), strtolower($label_courses))); ?>">
                <div class="sik-acc-panel__head">
                    <div class="sik-acc-panel__title-block">
                        <h2 class="sik-acc-panel__title"><?php esc_html_e('Ongoing', 'sikshya'); ?></h2>
                        <p class="sik-acc-panel__sub"><?php echo esc_html(sprintf(__('Continue your active %s and jump into the player directly.', 'sikshya'), strtolower($label_courses))); ?></p>
                    </div>
                </div>
                <?php if ($page_model->getEnrollmentsOngoing() === []) : ?>
                    <div class="sik-acc-empty">
                        <?php
                        echo esc_html(sprintf(
                            /* translators: %s: plural label (e.g. courses) */
                            __('You have no %s in progress.', 'sikshya'),
                            strtolower($label_courses)
                        ));
                        ?>
                    </div>
                <?php else : ?>
                    <div class="sik-acc-table-wrap">
                        <table class="sik-acc-table">
                            <thead>
                            <tr>
                                <th scope="col"><?php echo esc_html($label_course); ?></th>
                                <th scope="col"><?php esc_html_e('Status', 'sikshya'); ?></th>
                                <th scope="col"><?php esc_html_e('Enrolled', 'sikshya'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            foreach ($page_model->getEnrollmentsOngoing() as $row) {
                                $render_enrollment_row($row);
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="sik-acc-panel" aria-label="<?php echo esc_attr(sprintf(__('Completed %s', 'sikshya'), strtolower($label_courses))); ?>">
                <div class="sik-acc-panel__head">
                    <div class="sik-acc-panel__title-block">
                        <h2 class="sik-acc-panel__title"><?php esc_html_e('Completed', 'sikshya'); ?></h2>
                        <p class="sik-acc-panel__sub"><?php echo esc_html(sprintf(__('Finished %s and certificate shortcuts.', 'sikshya'), strtolower($label_courses))); ?></p>
                    </div>
                </div>
                <?php if ($page_model->getEnrollmentsCompleted() === []) : ?>
                    <div class="sik-acc-empty">
                        <?php
                        echo esc_html(sprintf(
                            /* translators: %s: plural label (e.g. courses) */
                            __('No completed %s yet.', 'sikshya'),
                            strtolower($label_courses)
                        ));
                        ?>
                    </div>
                <?php else : ?>
                    <div class="sik-acc-table-wrap">
                        <table class="sik-acc-table">
                            <thead>
                            <tr>
                                <th scope="col"><?php echo esc_html($label_course); ?></th>
                                <th scope="col"><?php esc_html_e('Status', 'sikshya'); ?></th>
                                <th scope="col"><?php esc_html_e('Enrolled', 'sikshya'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            foreach ($page_model->getEnrollmentsCompleted() as $row) {
                                $render_enrollment_row($row);
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <?php
            /**
             * @param array<string, mixed>                         $acc
             * @param \Sikshya\Presentation\Models\AccountPageModel $page_model
             */
            do_action('sikshya_account_learning_after', $acc, $page_model);
            ?>
