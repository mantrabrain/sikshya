<?php
/**
 * Account: apply for instructor view.
 *
 * @package Sikshya
 *
 * @var array<string, mixed>                         $acc Back-compat view array for hooks.
 * @var \Sikshya\Presentation\Models\AccountPageModel $page_model
 */

$uid = $page_model->getUserId();
$app = is_array($acc['instructor_application'] ?? null) ? (array) $acc['instructor_application'] : [];
$status = (string) ($app['status'] ?? '');
$submitted_at = (string) ($app['submitted_at'] ?? '');

$shortcode = do_shortcode('[sikshya_instructor_registration]');

$label_instructor = function_exists('sikshya_label') ? sikshya_label('instructor', __('Instructor', 'sikshya'), 'frontend') : __('Instructor', 'sikshya');
$label_courses = function_exists('sikshya_label_plural') ? sikshya_label_plural('course', 'courses', __('Courses', 'sikshya'), 'frontend') : __('Courses', 'sikshya');
?>
            <section class="sik-acc-hero" aria-label="<?php esc_attr_e('Teaching', 'sikshya'); ?>">
                <p class="sik-acc-hero__date"><?php echo esc_html(sprintf(__('%s application', 'sikshya'), $label_instructor)); ?></p>
                <h2 class="sik-acc-hero__greet"><?php echo esc_html(sprintf(__('Apply to become an %s', 'sikshya'), strtolower($label_instructor))); ?></h2>
                <p class="sik-acc-hero__lead">
                    <?php
                    echo esc_html(sprintf(
                        /* translators: %s: plural label (e.g. courses) */
                        __('Submit your profile for review. Once approved you’ll be able to create and manage %s.', 'sikshya'),
                        strtolower($label_courses)
                    ));
                    ?>
                </p>
            </section>

            <?php if ($status !== '') : ?>
                <section class="sik-acc-panel" style="margin-top:0;">
                    <div class="sik-acc-panel__head">
                        <h2 class="sik-acc-panel__title"><?php esc_html_e('Status', 'sikshya'); ?></h2>
                    </div>
                    <p class="sik-acc-cal__empty" style="margin-top:0;">
                        <?php
                        if ($status === 'pending') {
                            esc_html_e('Pending review', 'sikshya');
                        } elseif ($status === 'active') {
                            esc_html_e('Approved', 'sikshya');
                        } elseif ($status === 'inactive' || $status === 'rejected') {
                            esc_html_e('Not approved', 'sikshya');
                        } else {
                            echo esc_html($status);
                        }
                        ?>
                        <?php if ($submitted_at !== '') : ?>
                            <span style="opacity:.75;">
                                <?php
                                $ts = strtotime($submitted_at);
                                $when = $ts ? wp_date(get_option('date_format'), $ts) : $submitted_at;
                                echo esc_html(' · ' . sprintf(__('Last submitted: %s', 'sikshya'), $when));
                                ?>
                            </span>
                        <?php endif; ?>
                    </p>
                </section>
            <?php endif; ?>

            <section style="margin-top:0.75rem;">
                <?php echo $shortcode; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </section>

