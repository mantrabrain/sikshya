<?php
/**
 * Account: My learning (ongoing + completed enrollments).
 *
 * @package Sikshya
 *
 * @var array<string, mixed> $acc
 */

use Sikshya\Frontend\Public\PublicPageUrls;

/**
 * @param mixed $row Enrollment row.
 */
$render_enrollment_row = static function ($row): void {
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
            <section class="sik-acc-panel" aria-label="<?php esc_attr_e('Ongoing courses', 'sikshya'); ?>">
                <div class="sik-acc-panel__head">
                    <h2 class="sik-acc-panel__title"><?php esc_html_e('Ongoing', 'sikshya'); ?></h2>
                    <a class="sik-acc-panel__link" href="<?php echo esc_url($acc['urls']['courses']); ?>"><?php esc_html_e('Browse all courses', 'sikshya'); ?></a>
                </div>
                <?php if (empty($acc['enrollments_ongoing'])) : ?>
                    <div class="sik-acc-empty">
                        <?php esc_html_e('You have no courses in progress.', 'sikshya'); ?>
                    </div>
                <?php else : ?>
                    <div class="sik-acc-table-wrap">
                        <table class="sik-acc-table">
                            <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e('Course', 'sikshya'); ?></th>
                                <th scope="col"><?php esc_html_e('Status', 'sikshya'); ?></th>
                                <th scope="col"><?php esc_html_e('Enrolled', 'sikshya'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            foreach ((array) $acc['enrollments_ongoing'] as $row) {
                                $render_enrollment_row($row);
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="sik-acc-panel" style="margin-top:1.25rem;" aria-label="<?php esc_attr_e('Completed courses', 'sikshya'); ?>">
                <div class="sik-acc-panel__head">
                    <h2 class="sik-acc-panel__title"><?php esc_html_e('Completed', 'sikshya'); ?></h2>
                </div>
                <?php if (empty($acc['enrollments_completed'])) : ?>
                    <div class="sik-acc-empty">
                        <?php esc_html_e('No completed courses yet.', 'sikshya'); ?>
                    </div>
                <?php else : ?>
                    <div class="sik-acc-table-wrap">
                        <table class="sik-acc-table">
                            <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e('Course', 'sikshya'); ?></th>
                                <th scope="col"><?php esc_html_e('Status', 'sikshya'); ?></th>
                                <th scope="col"><?php esc_html_e('Enrolled', 'sikshya'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            foreach ((array) $acc['enrollments_completed'] as $row) {
                                $render_enrollment_row($row);
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <?php do_action('sikshya_account_learning_after', $acc); ?>
