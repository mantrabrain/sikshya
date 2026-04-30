<?php
/**
 * Account: instructor (teaching) overview.
 *
 * Shown to users that {@see Sikshya\Frontend\Public\InstructorContext::isInstructor()} reports as instructors.
 *
 * @package Sikshya
 *
 * @var array<string, mixed>                         $acc Back-compat view array for hooks.
 * @var \Sikshya\Presentation\Models\AccountPageModel $page_model
 */

use Sikshya\Frontend\Public\PublicPageUrls;

$inst = $page_model->getInstructorVm();
$published = (int) ($inst['published_courses'] ?? 0);
$enrolls = (int) ($inst['enrollments_total'] ?? 0);
$completed = (int) ($inst['enrollments_completed'] ?? 0);
$recent = is_array($inst['recent_courses'] ?? null) ? $inst['recent_courses'] : [];
$pro_blocks = is_array($inst['pro_blocks'] ?? null) ? $inst['pro_blocks'] : [];
$completion_rate = $enrolls > 0 ? round(100 * ($completed / $enrolls), 1) : 0.0;

$add_url = $page_model->getUrls()->getAddNewCourseUrl();
$manage_url = $page_model->getUrls()->getEditCoursesUrl();
$learn_url = $page_model->getUrls()->getLearningUrl();
$dash_url = $page_model->getUrls()->getDashboardUrl();
$learner_n = $page_model->getEnrollmentCount();
$ongoing_n = $page_model->getOngoingCount();
$done_n = $page_model->getCompletedCount();

$label_course = function_exists('sikshya_label') ? sikshya_label('course', __('Course', 'sikshya'), 'frontend') : __('Course', 'sikshya');
$label_courses = function_exists('sikshya_label_plural') ? sikshya_label_plural('course', 'courses', __('Courses', 'sikshya'), 'frontend') : __('Courses', 'sikshya');
$label_instructor = function_exists('sikshya_label') ? sikshya_label('instructor', __('Instructor', 'sikshya'), 'frontend') : __('Instructor', 'sikshya');
$label_enrollments = function_exists('sikshya_label_plural') ? sikshya_label_plural('enrollment', 'enrollments', __('Enrollments', 'sikshya'), 'frontend') : __('Enrollments', 'sikshya');
?>
            <section class="sik-acc-hero" aria-label="<?php esc_attr_e('Teaching', 'sikshya'); ?>">
                <p class="sik-acc-hero__date"><?php echo esc_html(sprintf(__('%s view', 'sikshya'), $label_instructor)); ?></p>
                <h2 class="sik-acc-hero__greet"><?php esc_html_e('Your teaching at a glance', 'sikshya'); ?></h2>
                <p class="sik-acc-hero__lead">
                    <?php
                    if ($learner_n > 0) {
                        esc_html_e(
                            'You also have active enrollments as a student — use My learning for your own progress; this page focuses on courses you teach.',
                            'sikshya'
                        );
                    } else {
                        esc_html_e('Track how your published courses are performing and jump straight to course management.', 'sikshya');
                    }
                    ?>
                </p>
            </section>

            <?php if ($learner_n > 0 && $learn_url !== '') : ?>
            <section class="sik-acc-panel" aria-label="<?php esc_attr_e('Your learning', 'sikshya'); ?>">
                <div class="sik-acc-panel__head">
                    <h2 class="sik-acc-panel__title"><?php esc_html_e('Your learning (as a student)', 'sikshya'); ?></h2>
                    <a class="sik-acc-btn" href="<?php echo esc_url($learn_url); ?>"><?php esc_html_e('Open My learning', 'sikshya'); ?> →</a>
                </div>
                <div class="sik-acc-metrics sik-acc-metrics--panel">
                    <div class="sik-acc-metric">
                        <div class="sik-acc-metric__value"><?php echo esc_html((string) $learner_n); ?></div>
                        <div class="sik-acc-metric__label"><?php echo esc_html($label_enrollments); ?></div>
                    </div>
                    <div class="sik-acc-metric">
                        <div class="sik-acc-metric__value"><?php echo esc_html((string) $ongoing_n); ?></div>
                        <div class="sik-acc-metric__label"><?php esc_html_e('In progress', 'sikshya'); ?></div>
                    </div>
                    <div class="sik-acc-metric">
                        <div class="sik-acc-metric__value"><?php echo esc_html((string) $done_n); ?></div>
                        <div class="sik-acc-metric__label"><?php esc_html_e('Completed', 'sikshya'); ?></div>
                    </div>
                    <?php if ($dash_url !== '') : ?>
                    <a class="sik-acc-metric sik-acc-metric--link" href="<?php echo esc_url($dash_url); ?>">
                        <div class="sik-acc-metric__value">←</div>
                        <div class="sik-acc-metric__label"><?php esc_html_e('Account overview', 'sikshya'); ?></div>
                        <div class="sik-acc-metric__hint"><?php esc_html_e('Learner + teacher summary', 'sikshya'); ?></div>
                    </a>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>

            <div class="sik-acc-grid sik-acc-grid--split sik-acc-instructor" aria-label="<?php esc_attr_e('Teaching overview', 'sikshya'); ?>">
                <div class="sik-acc-grid__main">
                    <section class="sik-acc-panel" aria-label="<?php esc_attr_e('Teaching metrics', 'sikshya'); ?>">
                        <div class="sik-acc-panel__head">
                            <div class="sik-acc-panel__title-block">
                                <h2 class="sik-acc-panel__title"><?php esc_html_e('Teaching performance', 'sikshya'); ?></h2>
                                <p class="sik-acc-panel__sub"><?php esc_html_e('A quick summary of your published courses and learner progress.', 'sikshya'); ?></p>
                            </div>
                        </div>
                        <div class="sik-acc-metrics sik-acc-metrics--panel">
                            <div class="sik-acc-metric">
                                <div class="sik-acc-metric__value"><?php echo esc_html((string) $published); ?></div>
                                <div class="sik-acc-metric__label"><?php echo esc_html(sprintf(__('Published %s', 'sikshya'), strtolower($label_courses))); ?></div>
                                <div class="sik-acc-metric__hint"><?php esc_html_e('Live for learners', 'sikshya'); ?></div>
                            </div>
                            <div class="sik-acc-metric">
                                <div class="sik-acc-metric__value"><?php echo esc_html((string) $enrolls); ?></div>
                                <div class="sik-acc-metric__label"><?php echo esc_html(sprintf(__('Total %s', 'sikshya'), strtolower($label_enrollments))); ?></div>
                                <div class="sik-acc-metric__hint"><?php echo esc_html(sprintf(__('Across all your %s', 'sikshya'), strtolower($label_courses))); ?></div>
                            </div>
                            <div class="sik-acc-metric">
                                <div class="sik-acc-metric__value"><?php echo esc_html((string) $completed); ?></div>
                                <div class="sik-acc-metric__label"><?php esc_html_e('Completions', 'sikshya'); ?></div>
                                <div class="sik-acc-metric__hint"><?php echo esc_html(sprintf('%s%% completion rate', number_format_i18n($completion_rate, 1))); ?></div>
                            </div>
                            <a class="sik-acc-metric sik-acc-metric--link" href="<?php echo esc_url($manage_url !== '' ? $manage_url : $page_model->getUrls()->getCoursesUrl()); ?>">
                                <div class="sik-acc-metric__value"><?php esc_html_e('Manage', 'sikshya'); ?> →</div>
                                <div class="sik-acc-metric__label"><?php echo esc_html(sprintf(__('Your %s', 'sikshya'), strtolower($label_courses))); ?></div>
                                <div class="sik-acc-metric__hint"><?php esc_html_e('Edit, publish, and update content', 'sikshya'); ?></div>
                            </a>
                        </div>
                    </section>

                    <?php if (!empty($pro_blocks)) : ?>
                        <section class="sik-acc-panel" aria-label="<?php esc_attr_e('Pro insights', 'sikshya'); ?>">
                            <div class="sik-acc-panel__head">
                                <div class="sik-acc-panel__title-block">
                                    <h2 class="sik-acc-panel__title"><?php esc_html_e('Pro insights', 'sikshya'); ?></h2>
                                </div>
                            </div>
                            <div class="sik-acc-library sik-acc-library--panel">
                                <?php foreach ($pro_blocks as $block) :
                                    if (!is_array($block)) {
                                        continue;
                                    }
                                    $value = (string) ($block['value'] ?? '');
                                    $label = (string) ($block['label'] ?? '');
                                    $hint = (string) ($block['hint'] ?? '');
                                    if ($value === '' && $label === '') {
                                        continue;
                                    }
                                    ?>
                                    <div class="sik-acc-lib-card sik-acc-lib-card--static">
                                        <div class="sik-acc-lib-card__icon sik-acc-lib-card__icon--blue" aria-hidden="true">★</div>
                                        <div class="sik-acc-lib-card__num"><?php echo esc_html($value); ?></div>
                                        <div class="sik-acc-lib-card__lbl"><?php echo esc_html($label); ?></div>
                                        <?php if ($hint !== '') : ?>
                                            <p class="sik-acc-shortcut__desc sik-acc-shortcut__desc--in-card"><?php echo esc_html($hint); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <div class="sik-acc-panel">
                        <div class="sik-acc-panel__head">
                            <div class="sik-acc-panel__title-block">
                                <h2 class="sik-acc-panel__title"><?php echo esc_html(sprintf(__('My %s', 'sikshya'), strtolower($label_courses))); ?></h2>
                                <p class="sik-acc-panel__sub"><?php esc_html_e('Recent courses you published and quick actions.', 'sikshya'); ?></p>
                            </div>
                            <?php if ($manage_url !== '') : ?>
                                <a class="sik-acc-btn" href="<?php echo esc_url($manage_url); ?>"><?php esc_html_e('Manage all', 'sikshya'); ?> →</a>
                            <?php endif; ?>
                        </div>
                        <?php if ($recent === []) : ?>
                            <p class="sik-acc-cal__empty">
                                <?php
                                echo esc_html(sprintf(
                                    /* translators: 1: plural label, 2: singular label */
                                    __('You have not published any %1$s yet. Create your first %2$s to start enrolling learners.', 'sikshya'),
                                    strtolower($label_courses),
                                    strtolower($label_course)
                                ));
                                ?>
                            </p>
                        <?php else : ?>
                            <ul class="sik-acc-cal__list">
                                <?php foreach ($recent as $row) :
                                    if (!is_array($row)) {
                                        continue;
                                    }
                                    $title = (string) ($row['title'] ?? '');
                                    $count = (int) ($row['enrollments'] ?? 0);
                                    $edit = (string) ($row['edit_url'] ?? '');
                                    $view_url = (string) ($row['view_url'] ?? '');
                                    ?>
                                    <li class="sik-acc-cal__item">
                                        <span class="sik-acc-cal__date"><?php echo esc_html(sprintf(_n('%d enrollment', '%d enrollments', $count, 'sikshya'), $count)); ?></span>
                                        <div class="sik-acc-cal__main">
                                            <span class="sik-acc-cal__title"><?php echo esc_html($title); ?></span>
                                            <span class="sik-acc-cal__sub sik-acc-teach-row-actions">
                                                <?php if ($view_url !== '') : ?>
                                                    <a href="<?php echo esc_url($view_url); ?>"><?php esc_html_e('Public page', 'sikshya'); ?></a>
                                                <?php endif; ?>
                                                <?php if ($edit !== '') : ?>
                                                    <?php if ($view_url !== '') : ?> · <?php endif; ?>
                                                    <a href="<?php echo esc_url($edit); ?>"><?php esc_html_e('Edit', 'sikshya'); ?></a>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <?php
                    /**
                     * Slot for Pro / addon-rendered teaching widgets (gradebook health, revenue charts, etc.).
                     *
                     */
                    do_action('sikshya_account_instructor_after', $acc);
                    ?>
                </div>

                <aside class="sik-acc-panel" aria-label="<?php esc_attr_e('Quick actions', 'sikshya'); ?>">
                    <div class="sik-acc-panel__head">
                        <div class="sik-acc-panel__title-block">
                            <h2 class="sik-acc-panel__title"><?php esc_html_e('Quick actions', 'sikshya'); ?></h2>
                            <p class="sik-acc-panel__sub"><?php esc_html_e('Create, manage, and review your course catalog quickly.', 'sikshya'); ?></p>
                        </div>
                    </div>
                    <div class="sik-acc-shortcuts">
                        <?php if ($manage_url !== '') : ?>
                            <a class="sik-acc-shortcut" href="<?php echo esc_url($manage_url); ?>">
                                <span class="sik-acc-shortcut__icon" aria-hidden="true">▦</span>
                                <div>
                                    <p class="sik-acc-shortcut__title"><?php esc_html_e('Manage my courses', 'sikshya'); ?></p>
                                    <p class="sik-acc-shortcut__desc"><?php esc_html_e('Edit lessons, pricing, curriculum, and publish updates.', 'sikshya'); ?></p>
                                </div>
                            </a>
                        <?php endif; ?>
                        <?php if ($add_url !== '') : ?>
                            <a class="sik-acc-shortcut" href="<?php echo esc_url($add_url); ?>">
                                <span class="sik-acc-shortcut__icon" aria-hidden="true">+</span>
                                <div>
                                    <p class="sik-acc-shortcut__title"><?php esc_html_e('Add new course', 'sikshya'); ?></p>
                                    <p class="sik-acc-shortcut__desc"><?php esc_html_e('Start a new course draft and build content.', 'sikshya'); ?></p>
                                </div>
                            </a>
                        <?php endif; ?>
                        <a class="sik-acc-shortcut" href="<?php echo esc_url($page_model->getUrls()->getCoursesUrl()); ?>">
                            <span class="sik-acc-shortcut__icon" aria-hidden="true">▤</span>
                            <div>
                                <p class="sik-acc-shortcut__title"><?php esc_html_e('Browse courses', 'sikshya'); ?></p>
                                <p class="sik-acc-shortcut__desc"><?php esc_html_e('See the public catalog as learners do.', 'sikshya'); ?></p>
                            </div>
                        </a>
                    </div>
                </aside>
            </div>

            <?php // Grid end. ?>
