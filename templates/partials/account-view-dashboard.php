<?php
/**
 * Account: overview dashboard (learning snapshot + actions + commerce).
 *
 * @package Sikshya
 *
 * @var array<string, mixed>                         $acc Back-compat view array for hooks.
 * @var \Sikshya\Presentation\Models\AccountPageModel $page_model
 */

$display_name = $page_model->getDisplayName();
$today_line   = $page_model->getTodayLine();
$greeting     = $page_model->getGreeting();
$is_instructor = $page_model->isInstructor();
$enrollment_n  = $page_model->getEnrollmentCount();
$ongoing_n     = $page_model->getOngoingCount();
$completed_n   = $page_model->getCompletedCount();
$orders_n      = $page_model->getOrdersCount();
$quiz_rows_n   = $page_model->getQuizAttemptsCount();

$label_course = function_exists('sikshya_label') ? sikshya_label('course', __('Course', 'sikshya'), 'frontend') : __('Course', 'sikshya');
$label_courses = function_exists('sikshya_label_plural') ? sikshya_label_plural('course', 'courses', __('Courses', 'sikshya'), 'frontend') : __('Courses', 'sikshya');
$label_quiz = function_exists('sikshya_label') ? sikshya_label('quiz', __('Quiz', 'sikshya'), 'frontend') : __('Quiz', 'sikshya');
?>
            <section class="sik-acc-hero" aria-label="<?php esc_attr_e('Welcome', 'sikshya'); ?>">
                <p class="sik-acc-hero__date"><?php echo esc_html($today_line); ?></p>
                <h2 class="sik-acc-hero__greet">
                    <?php
                    printf(
                        /* translators: 1: time-of-day greeting, 2: display name */
                        esc_html__('%1$s, %2$s', 'sikshya'),
                        esc_html($greeting),
                        esc_html($display_name)
                    );
                    ?>
                </h2>
                <p class="sik-acc-hero__lead">
                    <?php
                    if ($is_instructor && $enrollment_n > 0) {
                        esc_html_e(
                            'You are teaching and learning here — use the sections below to jump to your classroom, receipts, or quizzes.',
                            'sikshya'
                        );
                    } elseif ($is_instructor) {
                        esc_html_e(
                            'Use the tiles below to open your catalog, teaching tools, or account areas — each destination has its own page.',
                            'sikshya'
                        );
                    } else {
                        esc_html_e('Pick up where you left off, review purchases, or explore new courses — everything below is one click away.', 'sikshya');
                    }
                    ?>
                </p>
            </section>

            <?php
            /**
             * Teaching summary for instructors (placed before learner metrics so dual-role users see both).
             */
            do_action('sikshya_account_dashboard_after_hero', $acc);
            ?>

            <div class="sik-acc-dash">
                <section class="sik-acc-dash-section" aria-labelledby="sik-acc-dash-snapshot-heading">
                    <header class="sik-acc-dash-section__head">
                        <h2 id="sik-acc-dash-snapshot-heading" class="sik-acc-dash-section__title"><?php esc_html_e('Learning snapshot', 'sikshya'); ?></h2>
                        <p class="sik-acc-dash-section__sub">
                            <?php
                            echo esc_html(sprintf(
                                /* translators: %s: plural label (e.g. courses) */
                                __('A quick read on your enrolled %s — numbers update when you finish lessons or complete a course.', 'sikshya'),
                                strtolower($label_courses)
                            ));
                            ?>
                        </p>
                    </header>
                    <div class="sik-acc-dash-snapshot">
                        <div class="sik-acc-dash-stat sik-acc-dash-stat--ongoing">
                            <span class="sik-acc-dash-stat__value"><?php echo esc_html((string) $ongoing_n); ?></span>
                            <span class="sik-acc-dash-stat__label"><?php esc_html_e('In progress', 'sikshya'); ?></span>
                            <span class="sik-acc-dash-stat__hint">
                                <?php
                                echo esc_html(sprintf(
                                    /* translators: %s: plural label (e.g. Courses) */
                                    __('%s you are actively taking', 'sikshya'),
                                    $label_courses
                                ));
                                ?>
                            </span>
                        </div>
                        <div class="sik-acc-dash-stat sik-acc-dash-stat--done">
                            <span class="sik-acc-dash-stat__value"><?php echo esc_html((string) $completed_n); ?></span>
                            <span class="sik-acc-dash-stat__label"><?php esc_html_e('Completed', 'sikshya'); ?></span>
                            <span class="sik-acc-dash-stat__hint"><?php echo esc_html(sprintf(__('Finished %s', 'sikshya'), strtolower($label_courses))); ?></span>
                        </div>
                        <div class="sik-acc-dash-stat sik-acc-dash-stat--total">
                            <span class="sik-acc-dash-stat__value"><?php echo esc_html((string) $enrollment_n); ?></span>
                            <span class="sik-acc-dash-stat__label"><?php esc_html_e('Enrolled', 'sikshya'); ?></span>
                            <span class="sik-acc-dash-stat__hint"><?php echo esc_html(sprintf(__('Total %s on your account', 'sikshya'), strtolower($label_courses))); ?></span>
                        </div>
                    </div>
                </section>

                <section class="sik-acc-dash-section" aria-labelledby="sik-acc-dash-actions-heading">
                    <header class="sik-acc-dash-section__head">
                        <h2 id="sik-acc-dash-actions-heading" class="sik-acc-dash-section__title"><?php esc_html_e('Go to', 'sikshya'); ?></h2>
                        <p class="sik-acc-dash-section__sub"><?php esc_html_e('Main areas of your learner account — open any card to continue.', 'sikshya'); ?></p>
                    </header>
                    <div class="sik-acc-dash-card-grid">
                        <a class="sik-acc-dash-card" href="<?php echo esc_url($page_model->getUrls()->getLearningUrl()); ?>">
                            <div class="sik-acc-dash-card__top">
                                <span class="sik-acc-dash-card__icon sik-acc-dash-card__icon--blue" aria-hidden="true">▣</span>
                                <?php if ($enrollment_n > 0) : ?>
                                    <span class="sik-acc-dash-card__badge" title="<?php esc_attr_e('Enrolled courses', 'sikshya'); ?>"><?php echo esc_html((string) $enrollment_n); ?></span>
                                <?php endif; ?>
                            </div>
                            <h3 class="sik-acc-dash-card__title"><?php esc_html_e('My learning', 'sikshya'); ?></h3>
                            <p class="sik-acc-dash-card__desc"><?php echo esc_html(sprintf(__('Continue %s, track progress, and open lessons.', 'sikshya'), strtolower($label_courses))); ?></p>
                            <span class="sik-acc-dash-card__cta"><?php esc_html_e('Open', 'sikshya'); ?><span aria-hidden="true"> →</span></span>
                        </a>
                        <a class="sik-acc-dash-card" href="<?php echo esc_url($page_model->getUrls()->getPaymentsUrl()); ?>">
                            <div class="sik-acc-dash-card__top">
                                <span class="sik-acc-dash-card__icon sik-acc-dash-card__icon--green" aria-hidden="true">≡</span>
                                <?php if ($orders_n > 0) : ?>
                                    <span class="sik-acc-dash-card__badge" title="<?php esc_attr_e('Orders', 'sikshya'); ?>"><?php echo esc_html((string) $orders_n); ?></span>
                                <?php endif; ?>
                            </div>
                            <h3 class="sik-acc-dash-card__title"><?php esc_html_e('Payments', 'sikshya'); ?></h3>
                            <p class="sik-acc-dash-card__desc"><?php esc_html_e('Orders, receipts, and payment history.', 'sikshya'); ?></p>
                            <span class="sik-acc-dash-card__cta"><?php esc_html_e('Open', 'sikshya'); ?><span aria-hidden="true"> →</span></span>
                        </a>
                        <a class="sik-acc-dash-card" href="<?php echo esc_url($page_model->getUrls()->getQuizAttemptsUrl()); ?>">
                            <div class="sik-acc-dash-card__top">
                                <span class="sik-acc-dash-card__icon sik-acc-dash-card__icon--slate" aria-hidden="true">◎</span>
                                <?php if ($quiz_rows_n > 0) : ?>
                                    <span class="sik-acc-dash-card__badge" title="<?php esc_attr_e('Tracked quizzes', 'sikshya'); ?>"><?php echo esc_html((string) $quiz_rows_n); ?></span>
                                <?php endif; ?>
                            </div>
                            <h3 class="sik-acc-dash-card__title"><?php echo esc_html(sprintf(__('%s attempts', 'sikshya'), $label_quiz)); ?></h3>
                            <p class="sik-acc-dash-card__desc"><?php esc_html_e('See limits, usage, and which quizzes you have opened.', 'sikshya'); ?></p>
                            <span class="sik-acc-dash-card__cta"><?php esc_html_e('Open', 'sikshya'); ?><span aria-hidden="true"> →</span></span>
                        </a>
                        <a class="sik-acc-dash-card" href="<?php echo esc_url($page_model->getUrls()->getLearnHubUrl()); ?>" role="listitem">
                            <div class="sik-acc-dash-card__top">
                                <span class="sik-acc-dash-card__icon sik-acc-dash-card__icon--violet" aria-hidden="true">▶</span>
                            </div>
                            <h3 class="sik-acc-dash-card__title"><?php esc_html_e('Learning hub', 'sikshya'); ?></h3>
                            <p class="sik-acc-dash-card__desc"><?php esc_html_e('Central place to browse your player and course shortcuts.', 'sikshya'); ?></p>
                            <span class="sik-acc-dash-card__cta"><?php esc_html_e('Open', 'sikshya'); ?><span aria-hidden="true"> →</span></span>
                        </a>
                        <a class="sik-acc-dash-card sik-acc-dash-card--wide" href="<?php echo esc_url($page_model->getUrls()->getCoursesUrl()); ?>">
                            <div class="sik-acc-dash-card__top">
                                <span class="sik-acc-dash-card__icon sik-acc-dash-card__icon--amber" aria-hidden="true">▤</span>
                            </div>
                            <h3 class="sik-acc-dash-card__title"><?php echo esc_html(sprintf(__('Browse %s', 'sikshya'), strtolower($label_courses))); ?></h3>
                            <p class="sik-acc-dash-card__desc"><?php echo esc_html(sprintf(__('Discover new %s in the public catalog.', 'sikshya'), strtolower($label_courses))); ?></p>
                            <span class="sik-acc-dash-card__cta"><?php esc_html_e('Open catalog', 'sikshya'); ?><span aria-hidden="true"> →</span></span>
                        </a>
                    </div>
                </section>

            </div>

            <?php
            /**
             * Extra dashboard blocks (Pro / addons).
             */
            do_action('sikshya_account_dashboard_after', $acc);
            ?>
