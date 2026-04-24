<?php
/**
 * Account: overview dashboard (metrics + shortcuts).
 *
 * @package Sikshya
 *
 * @var array<string, mixed> $acc
 */

$display_name = (string) ($acc['display_name'] ?? '');
$today_line   = isset($today_line) ? (string) $today_line : '';
$greeting     = isset($greeting) ? (string) $greeting : '';
$is_instructor = !empty($acc['is_instructor']);
$enrollment_n  = (int) ($acc['enrollment_count'] ?? 0);
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
             * @param array<string, mixed> $acc Account template data.
             */
            do_action('sikshya_account_dashboard_after_hero', $acc);
            ?>

            <section class="sik-acc-metrics" aria-label="<?php esc_attr_e('Summary', 'sikshya'); ?>">
                <div class="sik-acc-metric">
                    <div class="sik-acc-metric__value"><?php echo esc_html((string) (int) ($acc['ongoing_count'] ?? 0)); ?></div>
                    <div class="sik-acc-metric__label"><?php esc_html_e('In progress', 'sikshya'); ?></div>
                    <div class="sik-acc-metric__hint"><?php esc_html_e('Courses you are still taking', 'sikshya'); ?></div>
                </div>
                <div class="sik-acc-metric">
                    <div class="sik-acc-metric__value"><?php echo esc_html((string) (int) ($acc['completed_count'] ?? 0)); ?></div>
                    <div class="sik-acc-metric__label"><?php esc_html_e('Completed', 'sikshya'); ?></div>
                    <div class="sik-acc-metric__hint"><?php esc_html_e('Finished courses', 'sikshya'); ?></div>
                </div>
                <div class="sik-acc-metric">
                    <div class="sik-acc-metric__value"><?php echo esc_html((string) (int) ($acc['orders_count'] ?? 0)); ?></div>
                    <div class="sik-acc-metric__label"><?php esc_html_e('Orders', 'sikshya'); ?></div>
                    <div class="sik-acc-metric__hint"><?php esc_html_e('Checkout history', 'sikshya'); ?></div>
                </div>
                <a class="sik-acc-metric sik-acc-metric--link" href="<?php echo esc_url($acc['urls']['courses']); ?>">
                    <div class="sik-acc-metric__value"><?php esc_html_e('Browse', 'sikshya'); ?> →</div>
                    <div class="sik-acc-metric__label"><?php esc_html_e('Course catalog', 'sikshya'); ?></div>
                    <div class="sik-acc-metric__hint"><?php esc_html_e('Discover new learning', 'sikshya'); ?></div>
                </a>
            </section>

            <section class="sik-acc-library" aria-label="<?php esc_attr_e('Shortcuts', 'sikshya'); ?>">
                <a class="sik-acc-lib-card" href="<?php echo esc_url($acc['urls']['account_learning']); ?>">
                    <div class="sik-acc-lib-card__icon sik-acc-lib-card__icon--blue" aria-hidden="true">▣</div>
                    <div class="sik-acc-lib-card__num"><?php echo esc_html((string) (int) ($acc['enrollment_count'] ?? 0)); ?></div>
                    <div class="sik-acc-lib-card__lbl"><?php esc_html_e('My learning', 'sikshya'); ?></div>
                </a>
                <a class="sik-acc-lib-card" href="<?php echo esc_url($acc['urls']['account_payments']); ?>">
                    <div class="sik-acc-lib-card__icon sik-acc-lib-card__icon--green" aria-hidden="true">≡</div>
                    <div class="sik-acc-lib-card__num"><?php echo esc_html((string) (int) ($acc['orders_count'] ?? 0)); ?></div>
                    <div class="sik-acc-lib-card__lbl"><?php esc_html_e('Payments', 'sikshya'); ?></div>
                </a>
                <a class="sik-acc-lib-card" href="<?php echo esc_url($acc['urls']['account_quiz_attempts']); ?>">
                    <div class="sik-acc-lib-card__icon sik-acc-lib-card__icon--slate" aria-hidden="true">◎</div>
                    <div class="sik-acc-lib-card__num"><?php echo esc_html((string) (int) ($acc['quiz_attempts_count'] ?? 0)); ?></div>
                    <div class="sik-acc-lib-card__lbl"><?php esc_html_e('Quiz attempts', 'sikshya'); ?></div>
                </a>
                <a class="sik-acc-lib-card" href="<?php echo esc_url($acc['urls']['learn']); ?>">
                    <div class="sik-acc-lib-card__icon sik-acc-lib-card__icon--blue" aria-hidden="true">▶</div>
                    <div class="sik-acc-lib-card__num">→</div>
                    <div class="sik-acc-lib-card__lbl"><?php esc_html_e('Learning hub', 'sikshya'); ?></div>
                </a>
            </section>

            <div class="sik-acc-panel" style="margin-top:0.5rem;">
                <div class="sik-acc-panel__head">
                    <h2 class="sik-acc-panel__title"><?php esc_html_e('Quick links', 'sikshya'); ?></h2>
                </div>
                <div class="sik-acc-shortcuts">
                    <a class="sik-acc-shortcut" href="<?php echo esc_url($acc['urls']['courses']); ?>">
                        <span class="sik-acc-shortcut__icon" aria-hidden="true">+</span>
                        <div>
                            <p class="sik-acc-shortcut__title"><?php esc_html_e('Browse courses', 'sikshya'); ?></p>
                            <p class="sik-acc-shortcut__desc"><?php esc_html_e('Open the public course catalog.', 'sikshya'); ?></p>
                        </div>
                    </a>
                    <a class="sik-acc-shortcut" href="<?php echo esc_url($acc['urls']['cart']); ?>">
                        <span class="sik-acc-shortcut__icon" aria-hidden="true">◇</span>
                        <div>
                            <p class="sik-acc-shortcut__title"><?php esc_html_e('Cart', 'sikshya'); ?></p>
                            <p class="sik-acc-shortcut__desc"><?php esc_html_e('Review items before checkout.', 'sikshya'); ?></p>
                        </div>
                    </a>
                    <a class="sik-acc-shortcut" href="<?php echo esc_url($acc['urls']['checkout']); ?>">
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
             * @param array<string, mixed> $acc Account template data.
             */
            do_action('sikshya_account_dashboard_after', $acc);
            ?>
