<?php
/**
 * Course curriculum / learn hub — view; data from {@see \Sikshya\Frontend\Public\LearnTemplateData}.
 *
 * @package Sikshya
 */

use Sikshya\Frontend\Public\LearnTemplateData;

$lv = LearnTemplateData::fromRequest();

get_header();
?>

<div class="sikshya-public sikshya-learn">
    <div class="sikshya-container sikshya-container--narrow">
        <?php if ($lv['error'] !== '') : ?>
            <div class="sikshya-learn-error sikshya-card sikshya-card--soft" role="alert">
                <h1 class="sikshya-learn-error__title"><?php esc_html_e('Learning unavailable', 'sikshya'); ?></h1>
                <p class="sikshya-learn-error__text"><?php echo esc_html($lv['error']); ?></p>
                <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url($lv['urls']['account']); ?>">
                    <?php esc_html_e('Go to account', 'sikshya'); ?>
                </a>
            </div>
        <?php else : ?>
            <nav class="sikshya-breadcrumb--public sikshya-learn-breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'sikshya'); ?>">
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'sikshya'); ?></a>
                <span class="sikshya-learn-breadcrumb__sep" aria-hidden="true">/</span>
                <a href="<?php echo esc_url($lv['urls']['account']); ?>"><?php esc_html_e('Account', 'sikshya'); ?></a>
                <?php if (! empty($lv['course']) && $lv['course'] instanceof WP_Post) : ?>
                    <span class="sikshya-learn-breadcrumb__sep" aria-hidden="true">/</span>
                    <a href="<?php echo esc_url($lv['urls']['course']); ?>"><?php echo esc_html(get_the_title($lv['course'])); ?></a>
                <?php endif; ?>
                <span class="sikshya-learn-breadcrumb__sep" aria-hidden="true">/</span>
                <span class="sikshya-learn-breadcrumb__current"><?php esc_html_e('Learn', 'sikshya'); ?></span>
            </nav>

            <header class="sikshya-learn-hero">
                <div class="sikshya-learn-hero__media">
                    <?php if (! empty($lv['course_thumb'])) : ?>
                        <img
                            class="sikshya-learn-hero__thumb"
                            src="<?php echo esc_url($lv['course_thumb']); ?>"
                            alt=""
                            loading="lazy"
                            width="640"
                            height="360"
                        />
                    <?php else : ?>
                        <div class="sikshya-learn-hero__placeholder" aria-hidden="true"></div>
                    <?php endif; ?>
                </div>
                <div class="sikshya-learn-hero__body">
                    <p class="sikshya-learn-hero__kicker"><?php esc_html_e('Your course', 'sikshya'); ?></p>
                    <h1 class="sikshya-learn-hero__title">
                        <?php
                        echo ! empty($lv['course']) && $lv['course'] instanceof WP_Post
                            ? esc_html(get_the_title($lv['course']))
                            : esc_html__('Course', 'sikshya');
                        ?>
                    </h1>
                    <?php if (! empty($lv['course']) && $lv['course'] instanceof WP_Post && $lv['course']->post_excerpt) : ?>
                        <p class="sikshya-learn-hero__excerpt"><?php echo esc_html(wp_strip_all_tags($lv['course']->post_excerpt)); ?></p>
                    <?php endif; ?>

                    <div class="sikshya-learn-progress" role="region" aria-labelledby="sikshya-learn-progress-label">
                        <div class="sikshya-learn-progress__head">
                            <span id="sikshya-learn-progress-label" class="sikshya-learn-progress__label">
                                <?php esc_html_e('Your progress', 'sikshya'); ?>
                            </span>
                            <span class="sikshya-learn-progress__value">
                                <?php
                                echo esc_html(
                                    sprintf(
                                        /* translators: 1: completed count, 2: total count, 3: percent */
                                        __('%1$d of %2$d completed (%3$d%%)', 'sikshya'),
                                        (int) $lv['stats']['completed_items'],
                                        (int) $lv['stats']['total_items'],
                                        (int) $lv['stats']['percent']
                                    )
                                );
                                ?>
                            </span>
                        </div>
                        <div class="sikshya-learn-progress__track" aria-hidden="true">
                            <div
                                class="sikshya-learn-progress__fill"
                                style="width: <?php echo esc_attr((string) (int) $lv['stats']['percent']); ?>%;"
                            ></div>
                        </div>
                    </div>

                    <div class="sikshya-learn-hero__actions">
                        <?php if (! empty($lv['urls']['course'])) : ?>
                            <a class="sikshya-btn sikshya-btn--ghost" href="<?php echo esc_url($lv['urls']['course']); ?>">
                                <?php esc_html_e('Course overview', 'sikshya'); ?>
                            </a>
                        <?php endif; ?>
                        <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url($lv['urls']['account']); ?>">
                            <?php esc_html_e('Back to account', 'sikshya'); ?>
                        </a>
                    </div>

                    <?php do_action('sikshya_learn_after_hero', $lv); ?>
                </div>
            </header>

            <section class="sikshya-learn-curriculum" aria-labelledby="sikshya-learn-curriculum-heading">
                <h2 id="sikshya-learn-curriculum-heading" class="sikshya-learn-curriculum__title">
                    <?php esc_html_e('Curriculum', 'sikshya'); ?>
                </h2>

                <?php if ($lv['blocks'] === []) : ?>
                    <p class="sikshya-muted sikshya-learn-empty"><?php esc_html_e('No curriculum items are published for this course yet.', 'sikshya'); ?></p>
                <?php else : ?>
                    <div class="sikshya-learn-chapters">
                        <?php foreach ($lv['blocks'] as $block) : ?>
                            <article class="sikshya-learn-chapter">
                                <h3 class="sikshya-learn-chapter__title"><?php echo esc_html($block['chapter']->post_title); ?></h3>
                                <ol class="sikshya-learn-items">
                                    <?php foreach ($block['items'] as $item) : ?>
                                        <?php
                                        $done = ! empty($item['completed']);
                                        $icon = $item['type_key'];
                                        ?>
                                        <li class="sikshya-learn-item<?php echo $done ? ' sikshya-learn-item--done' : ''; ?>">
                                            <span class="sikshya-learn-item__icon sikshya-learn-item__icon--<?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
                                            <a class="sikshya-learn-item__link" href="<?php echo esc_url($item['permalink']); ?>">
                                                <span class="sikshya-learn-item__title"><?php echo esc_html($item['title']); ?></span>
                                                <span class="sikshya-learn-item__meta">
                                                    <?php echo esc_html($item['type_label']); ?>
                                                    <?php if ($done) : ?>
                                                        <span class="sikshya-learn-item__badge"><?php esc_html_e('Done', 'sikshya'); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <?php do_action('sikshya_learn_after_curriculum', $lv); ?>
        <?php endif; ?>
    </div>
</div>

<?php
get_footer();
