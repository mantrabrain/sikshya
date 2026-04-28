<?php
/**
 * Account: overview dashboard (metrics + shortcuts).
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
                            'You are teaching and learning here — your teaching summary is below, and your student progress stays in My learning.',
                            'sikshya'
                        );
                    } elseif ($is_instructor) {
                        esc_html_e(
                            'Manage your courses from Teaching or jump to the catalog — each area has its own page.',
                            'sikshya'
                        );
                    } else {
                        esc_html_e('Jump to your courses, receipts, or quizzes—each area has its own page.', 'sikshya');
                    }
                    ?>
                </p>
            </section>

            <?php
            /**
             * Teaching summary for instructors (placed before learner metrics so dual-role users see both).
             *
             */
            do_action('sikshya_account_dashboard_after_hero', $acc);
            ?>

            <section class="sik-acc-metrics" aria-label="<?php esc_attr_e('Summary', 'sikshya'); ?>">
                <div class="sik-acc-metric">
                    <div class="sik-acc-metric__value"><?php echo esc_html((string) $page_model->getOngoingCount()); ?></div>
                    <div class="sik-acc-metric__label"><?php esc_html_e('In progress', 'sikshya'); ?></div>
                    <div class="sik-acc-metric__hint">
                        <?php
                        echo esc_html(sprintf(
                            /* translators: %s: plural label (e.g. Courses) */
                            __('%s you are still taking', 'sikshya'),
                            $label_courses
                        ));
                        ?>
                    </div>
                </div>
                <div class="sik-acc-metric">
                    <div class="sik-acc-metric__value"><?php echo esc_html((string) $page_model->getCompletedCount()); ?></div>
                    <div class="sik-acc-metric__label"><?php esc_html_e('Completed', 'sikshya'); ?></div>
                    <div class="sik-acc-metric__hint">
                        <?php echo esc_html(sprintf(__('Finished %s', 'sikshya'), strtolower($label_courses))); ?>
                    </div>
                </div>
                <div class="sik-acc-metric">
                    <div class="sik-acc-metric__value"><?php echo esc_html((string) $page_model->getOrdersCount()); ?></div>
                    <div class="sik-acc-metric__label"><?php esc_html_e('Orders', 'sikshya'); ?></div>
                    <div class="sik-acc-metric__hint"><?php esc_html_e('Checkout history', 'sikshya'); ?></div>
                </div>
                <a class="sik-acc-metric sik-acc-metric--link" href="<?php echo esc_url($page_model->getUrls()->getCoursesUrl()); ?>">
                    <div class="sik-acc-metric__value"><?php esc_html_e('Browse', 'sikshya'); ?> →</div>
                    <div class="sik-acc-metric__label">
                        <?php echo esc_html(sprintf(__('%s catalog', 'sikshya'), $label_course)); ?>
                    </div>
                    <div class="sik-acc-metric__hint"><?php esc_html_e('Discover new learning', 'sikshya'); ?></div>
                </a>
            </section>

            <section class="sik-acc-library" aria-label="<?php esc_attr_e('Shortcuts', 'sikshya'); ?>">
                <a class="sik-acc-lib-card" href="<?php echo esc_url($page_model->getUrls()->getLearningUrl()); ?>">
                    <div class="sik-acc-lib-card__icon sik-acc-lib-card__icon--blue" aria-hidden="true">▣</div>
                    <div class="sik-acc-lib-card__num"><?php echo esc_html((string) $page_model->getEnrollmentCount()); ?></div>
                    <div class="sik-acc-lib-card__lbl"><?php esc_html_e('My learning', 'sikshya'); ?></div>
                </a>
                <a class="sik-acc-lib-card" href="<?php echo esc_url($page_model->getUrls()->getPaymentsUrl()); ?>">
                    <div class="sik-acc-lib-card__icon sik-acc-lib-card__icon--green" aria-hidden="true">≡</div>
                    <div class="sik-acc-lib-card__num"><?php echo esc_html((string) $page_model->getOrdersCount()); ?></div>
                    <div class="sik-acc-lib-card__lbl"><?php esc_html_e('Payments', 'sikshya'); ?></div>
                </a>
                <a class="sik-acc-lib-card" href="<?php echo esc_url($page_model->getUrls()->getQuizAttemptsUrl()); ?>">
                    <div class="sik-acc-lib-card__icon sik-acc-lib-card__icon--slate" aria-hidden="true">◎</div>
                    <div class="sik-acc-lib-card__num"><?php echo esc_html((string) $page_model->getQuizAttemptsCount()); ?></div>
                    <div class="sik-acc-lib-card__lbl"><?php echo esc_html(sprintf(__('%s attempts', 'sikshya'), $label_quiz)); ?></div>
                </a>
                <a class="sik-acc-lib-card" href="<?php echo esc_url($page_model->getUrls()->getLearnHubUrl()); ?>">
                    <div class="sik-acc-lib-card__icon sik-acc-lib-card__icon--blue" aria-hidden="true">▶</div>
                    <div class="sik-acc-lib-card__num">→</div>
                    <div class="sik-acc-lib-card__lbl"><?php esc_html_e('Learning hub', 'sikshya'); ?></div>
                </a>
            </section>

            <div class="sik-acc-panel">
                <div class="sik-acc-panel__head">
                    <h2 class="sik-acc-panel__title"><?php esc_html_e('Quick links', 'sikshya'); ?></h2>
                </div>
                <div class="sik-acc-shortcuts">
                    <a class="sik-acc-shortcut" href="<?php echo esc_url($page_model->getUrls()->getCoursesUrl()); ?>">
                        <span class="sik-acc-shortcut__icon" aria-hidden="true">+</span>
                        <div>
                            <p class="sik-acc-shortcut__title"><?php echo esc_html(sprintf(__('Browse %s', 'sikshya'), strtolower($label_courses))); ?></p>
                            <p class="sik-acc-shortcut__desc"><?php echo esc_html(sprintf(__('Open the public %s catalog.', 'sikshya'), strtolower($label_course))); ?></p>
                        </div>
                    </a>
                    <a class="sik-acc-shortcut" href="<?php echo esc_url($page_model->getUrls()->getCartUrl()); ?>">
                        <span class="sik-acc-shortcut__icon" aria-hidden="true">◇</span>
                        <div>
                            <p class="sik-acc-shortcut__title"><?php esc_html_e('Cart', 'sikshya'); ?></p>
                            <p class="sik-acc-shortcut__desc"><?php esc_html_e('Review items before checkout.', 'sikshya'); ?></p>
                        </div>
                    </a>
                    <a class="sik-acc-shortcut" href="<?php echo esc_url($page_model->getUrls()->getCheckoutUrl()); ?>">
                        <span class="sik-acc-shortcut__icon" aria-hidden="true">✓</span>
                        <div>
                            <p class="sik-acc-shortcut__title"><?php esc_html_e('Checkout', 'sikshya'); ?></p>
                            <p class="sik-acc-shortcut__desc"><?php esc_html_e('Complete a purchase securely.', 'sikshya'); ?></p>
                        </div>
                    </a>
                </div>
            </div>

            <?php
            /**
             * Extra dashboard blocks (Pro / addons).
             *
             */
            do_action('sikshya_account_dashboard_after', $acc);
            ?>
