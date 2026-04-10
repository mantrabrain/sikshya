<?php
/**
 * Single course — Udemy-style landing layout; data from {@see \Sikshya\Frontend\Public\SingleCourseTemplateData}.
 *
 * @package Sikshya
 */

use Sikshya\Frontend\Public\SingleCourseTemplateData;

get_header();

while (have_posts()) :
    the_post();
    $vm = SingleCourseTemplateData::forPost(get_post());
    $pricing = $vm['pricing'];
    $course_id = (int) $vm['course_id'];
    $urls = $vm['urls'];
    $curriculum = $vm['curriculum'] ?? [];
    $cart_flash = $vm['cart_flash'] ?? null;
    $permalink = get_permalink($course_id);
    $category_trail = $vm['category_trail'] ?? [];
    $tag_pills = $vm['tag_pills'] ?? [];
    $learning_outcomes = $vm['learning_outcomes'] ?? [];
    $includes_lines = $vm['includes_lines'] ?? [];
    $curriculum_stats = $vm['curriculum_stats'] ?? ['chapters' => 0, 'items' => 0, 'lessons' => 0];
    $video_preview = $vm['video_preview'] ?? null;
    $subtitle = isset($vm['subtitle']) ? (string) $vm['subtitle'] : '';
    $instructor_profiles = $vm['instructor_profiles'] ?? [];
    ?>

<div class="sikshya-public sikshya-single-course sikshya-course-lp sik-f-scope">
    <div class="sikshya-course-lp__masthead">
        <div class="sikshya-container sikshya-container--course sikshya-course-lp__masthead-inner">
            <nav class="sikshya-course-lp__breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'sikshya'); ?>">
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'sikshya'); ?></a>
                <span class="sikshya-course-lp__bc-sep" aria-hidden="true">›</span>
                <?php if (!empty($urls['courses_archive'])) : ?>
                    <a href="<?php echo esc_url($urls['courses_archive']); ?>"><?php esc_html_e('Courses', 'sikshya'); ?></a>
                    <span class="sikshya-course-lp__bc-sep" aria-hidden="true">›</span>
                <?php endif; ?>
                <?php
                $trail_count = count($category_trail);
                foreach ($category_trail as $i => $crumb) {
                    $is_last = ($i === $trail_count - 1);
                    $name = isset($crumb['name']) ? (string) $crumb['name'] : '';
                    $url = isset($crumb['url']) ? (string) $crumb['url'] : '';
                    if ($name === '') {
                        continue;
                    }
                    if (!$is_last && $url !== '') {
                        echo '<a href="' . esc_url($url) . '">' . esc_html($name) . '</a>';
                    } else {
                        echo '<span>' . esc_html($name) . '</span>';
                    }
                    if (!$is_last) {
                        echo '<span class="sikshya-course-lp__bc-sep" aria-hidden="true">›</span>';
                    }
                }
                ?>
            </nav>

            <?php if (is_array($cart_flash) && !empty($cart_flash['message'])) : ?>
                <div class="sikshya-cart-flash sikshya-cart-flash--<?php echo esc_attr((string) ($cart_flash['type'] ?? 'info')); ?>" role="status">
                    <?php echo esc_html((string) $cart_flash['message']); ?>
                </div>
            <?php endif; ?>

            <?php do_action('sikshya_single_course_before_hero', $vm); ?>

            <h1 class="sikshya-course-lp__title"><?php the_title(); ?></h1>
            <?php if ($subtitle !== '') : ?>
                <p class="sikshya-course-lp__subtitle"><?php echo esc_html($subtitle); ?></p>
            <?php endif; ?>

            <div class="sikshya-course-lp__stats">
                <?php if (!empty($vm['instructor']) && $vm['instructor'] instanceof WP_User) : ?>
                    <span class="sikshya-course-lp__stat">
                        <?php esc_html_e('Created by', 'sikshya'); ?>
                        <strong><?php echo esc_html($vm['instructor']->display_name); ?></strong>
                    </span>
                <?php endif; ?>
                <?php if (!empty($vm['last_updated'])) : ?>
                    <span class="sikshya-course-lp__stat">
                        <?php esc_html_e('Last updated', 'sikshya'); ?>
                        <strong><?php echo esc_html((string) $vm['last_updated']); ?></strong>
                    </span>
                <?php endif; ?>
                <?php if (!empty($vm['language_label'])) : ?>
                    <span class="sikshya-course-lp__stat">
                        <?php esc_html_e('Language', 'sikshya'); ?>
                        <strong><?php echo esc_html((string) $vm['language_label']); ?></strong>
                    </span>
                <?php endif; ?>
                <?php if (!empty($vm['difficulty'])) : ?>
                    <span class="sikshya-course-lp__stat">
                        <?php esc_html_e('Level', 'sikshya'); ?>
                        <strong class="sikshya-difficulty-badge sikshya-difficulty-badge--<?php echo esc_attr(sanitize_html_class((string) $vm['difficulty'])); ?>">
                            <?php echo esc_html(ucfirst((string) $vm['difficulty'])); ?>
                        </strong>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="sikshya-container sikshya-container--course">
        <div class="sikshya-course-lp__layout">
            <main class="sikshya-course-lp__main" id="sikshya-course-main">
                <?php if (is_array($learning_outcomes) && $learning_outcomes !== []) : ?>
                    <section class="sikshya-course-lp__panel sikshya-course-lp__learn" aria-labelledby="sikshya-learn-heading">
                        <h2 id="sikshya-learn-heading" class="sikshya-course-lp__heading"><?php esc_html_e('What you’ll learn', 'sikshya'); ?></h2>
                        <ul class="sikshya-course-lp__learn-grid">
                            <?php foreach ($learning_outcomes as $outcome) : ?>
                                <li class="sikshya-course-lp__learn-item"><?php echo esc_html((string) $outcome); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>

                <?php if (is_array($tag_pills) && $tag_pills !== []) : ?>
                    <section class="sikshya-course-lp__tags" aria-label="<?php esc_attr_e('Related topics', 'sikshya'); ?>">
                        <h2 class="sikshya-course-lp__heading sikshya-course-lp__heading--sm"><?php esc_html_e('Explore related topics', 'sikshya'); ?></h2>
                        <div class="sikshya-course-lp__tag-row">
                            <?php foreach ($tag_pills as $pill) : ?>
                                <span class="sikshya-course-lp__tag"><?php echo esc_html((string) $pill); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (is_array($includes_lines) && $includes_lines !== []) : ?>
                    <section class="sikshya-course-lp__panel sikshya-course-lp__includes-main" aria-labelledby="sikshya-includes-heading">
                        <h2 id="sikshya-includes-heading" class="sikshya-course-lp__heading"><?php esc_html_e('This course includes', 'sikshya'); ?></h2>
                        <ul class="sikshya-course-lp__checklist">
                            <?php foreach ($includes_lines as $line) : ?>
                                <li><?php echo esc_html((string) $line); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>

                <?php if (is_array($curriculum) && $curriculum !== []) : ?>
                    <section class="sikshya-course-lp__panel sikshya-course-lp__curriculum" aria-labelledby="sikshya-curriculum-heading">
                        <div class="sikshya-course-lp__curriculum-head">
                            <h2 id="sikshya-curriculum-heading" class="sikshya-course-lp__heading"><?php esc_html_e('Course content', 'sikshya'); ?></h2>
                            <p class="sikshya-course-lp__curriculum-meta">
                                <?php
                                $chapters_n = (int) ($curriculum_stats['chapters'] ?? 0);
                                $lessons_n = (int) ($curriculum_stats['lessons'] ?? 0);
                                $items_n = (int) ($curriculum_stats['items'] ?? 0);
                                $section_part = sprintf(
                                    _n('%s section', '%s sections', $chapters_n, 'sikshya'),
                                    number_format_i18n($chapters_n)
                                );
                                if ($lessons_n > 0) {
                                    $second_part = sprintf(
                                        _n('%s lecture', '%s lectures', $lessons_n, 'sikshya'),
                                        number_format_i18n($lessons_n)
                                    );
                                } else {
                                    $second_part = sprintf(
                                        _n('%s item', '%s items', $items_n, 'sikshya'),
                                        number_format_i18n($items_n)
                                    );
                                }
                                echo esc_html(
                                    sprintf(
                                        /* translators: 1: sections phrase, 2: lectures/items phrase */
                                        __('%1$s · %2$s', 'sikshya'),
                                        $section_part,
                                        $second_part
                                    )
                                );
                                ?>
                                <?php if (!empty($vm['duration'])) : ?>
                                    <span class="sikshya-course-lp__curriculum-meta-sep">·</span>
                                    <span><?php echo esc_html(sprintf(__('Est. %s hours total', 'sikshya'), (string) $vm['duration'])); ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="sikshya-course-lp__accordion">
                            <?php foreach ($curriculum as $block) : ?>
                                <?php
                                $chapter = $block['chapter'] ?? null;
                                $contents = $block['contents'] ?? [];
                                if (!$chapter instanceof WP_Post) {
                                    continue;
                                }
                                $n = count($contents);
                                ?>
                                <details class="sikshya-course-lp__chapter">
                                    <summary class="sikshya-course-lp__chapter-summary">
                                        <span class="sikshya-course-lp__chapter-chevron" aria-hidden="true">
                                            <svg class="sikshya-course-lp__chevron-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                                                <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </span>
                                        <span class="sikshya-course-lp__chapter-row">
                                            <span class="sikshya-course-lp__chapter-title"><?php echo esc_html($chapter->post_title); ?></span>
                                            <span class="sikshya-course-lp__chapter-count">
                                                <?php
                                                echo esc_html(
                                                    sprintf(
                                                        /* translators: %s: number of lessons/items in a section */
                                                        _n('%s lecture', '%s lectures', $n, 'sikshya'),
                                                        number_format_i18n($n)
                                                    )
                                                );
                                                ?>
                                            </span>
                                        </span>
                                    </summary>
                                    <?php if ($n > 0) : ?>
                                        <ol class="sikshya-course-lp__outline">
                                            <?php foreach ($contents as $item) : ?>
                                                <?php
                                                if (!$item instanceof WP_Post) {
                                                    continue;
                                                }
                                                $type = get_post_type($item);
                                                $label = function_exists('sikshya_public_content_type_label') ? sikshya_public_content_type_label($type) : '';
                                                $icon_html = function_exists('sikshya_public_content_type_icon_html') ? sikshya_public_content_type_icon_html($type) : '';
                                                $can_open = !empty($vm['is_enrolled']);
                                                $item_url = get_permalink($item);
                                                ?>
                                                <li class="sikshya-course-lp__outline-item">
                                                    <span class="sikshya-course-lp__outline-icon">
                                                        <?php
                                                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup from sikshya_public_content_type_icon_html().
                                                        echo $icon_html;
                                                        ?>
                                                    </span>
                                                    <div class="sikshya-course-lp__outline-body">
                                                        <?php if ($can_open && is_string($item_url) && $item_url !== '') : ?>
                                                            <a class="sikshya-course-lp__outline-link" href="<?php echo esc_url($item_url); ?>">
                                                                <span class="sikshya-course-lp__outline-title"><?php echo esc_html($item->post_title); ?></span>
                                                                <span class="sikshya-course-lp__outline-type"><?php echo esc_html($label); ?></span>
                                                            </a>
                                                        <?php else : ?>
                                                            <span class="sikshya-course-lp__outline-locked">
                                                                <span class="sikshya-course-lp__outline-title"><?php echo esc_html($item->post_title); ?></span>
                                                                <span class="sikshya-course-lp__outline-type"><?php echo esc_html($label); ?></span>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ol>
                                    <?php else : ?>
                                        <p class="sikshya-course-lp__empty"><?php esc_html_e('Content coming soon.', 'sikshya'); ?></p>
                                    <?php endif; ?>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (!empty($vm['target_audience_html'])) : ?>
                    <section class="sikshya-course-lp__panel" aria-labelledby="sikshya-audience-heading">
                        <h2 id="sikshya-audience-heading" class="sikshya-course-lp__heading"><?php esc_html_e('Who this course is for', 'sikshya'); ?></h2>
                        <div class="sikshya-course-lp__audience sikshya-prose">
                            <?php echo $vm['target_audience_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post in VM ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php
                $content = get_post_field('post_content', $course_id);
                if (is_string($content) && trim($content) !== '') :
                    ?>
                    <section class="sikshya-course-lp__panel sikshya-course-lp__description" aria-labelledby="sikshya-desc-heading">
                        <h2 id="sikshya-desc-heading" class="sikshya-course-lp__heading"><?php esc_html_e('Description', 'sikshya'); ?></h2>
                        <div class="sikshya-prose sikshya-course-lp__prose">
                            <?php the_content(); ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (is_array($instructor_profiles) && $instructor_profiles !== []) : ?>
                    <section class="sikshya-course-lp__panel sikshya-course-lp__instructors" aria-labelledby="sikshya-instructor-heading">
                        <h2 id="sikshya-instructor-heading" class="sikshya-course-lp__heading"><?php esc_html_e('Instructor', 'sikshya'); ?></h2>
                        <?php foreach ($instructor_profiles as $prof) : ?>
                            <?php
                            if (!is_array($prof)) {
                                continue;
                            }
                            $pname = isset($prof['name']) ? (string) $prof['name'] : '';
                            $pbio = isset($prof['bio']) ? (string) $prof['bio'] : '';
                            $pavatar = isset($prof['avatar_url']) ? (string) $prof['avatar_url'] : '';
                            $plink = isset($prof['profile_url']) ? (string) $prof['profile_url'] : '';
                            ?>
                            <div class="sikshya-course-lp__instructor-card">
                                <?php if ($pavatar !== '') : ?>
                                    <img class="sikshya-course-lp__instructor-avatar" src="<?php echo esc_url($pavatar); ?>" alt="" width="96" height="96" loading="lazy" />
                                <?php endif; ?>
                                <div class="sikshya-course-lp__instructor-body">
                                    <?php if ($plink !== '') : ?>
                                        <a class="sikshya-course-lp__instructor-name" href="<?php echo esc_url($plink); ?>"><?php echo esc_html($pname); ?></a>
                                    <?php else : ?>
                                        <span class="sikshya-course-lp__instructor-name"><?php echo esc_html($pname); ?></span>
                                    <?php endif; ?>
                                    <?php if ($pbio !== '') : ?>
                                        <div class="sikshya-course-lp__instructor-bio sikshya-prose"><?php echo wp_kses_post(wpautop($pbio)); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>

                <?php
                $faq = $vm['course_faq'] ?? [];
                if (is_array($faq) && $faq !== []) :
                    ?>
                    <section class="sikshya-course-lp__panel" aria-labelledby="sikshya-faq-heading">
                        <h2 id="sikshya-faq-heading" class="sikshya-course-lp__heading"><?php esc_html_e('FAQ', 'sikshya'); ?></h2>
                        <div class="sikshya-course-lp__accordion sikshya-course-lp__accordion--faq">
                            <?php foreach ($faq as $row) : ?>
                                <?php
                                if (!is_array($row)) {
                                    continue;
                                }
                                $fq = isset($row['question']) ? (string) $row['question'] : '';
                                $fa = isset($row['answer']) ? (string) $row['answer'] : '';
                                if ($fq === '') {
                                    continue;
                                }
                                ?>
                                <details class="sikshya-course-lp__faq-item">
                                    <summary class="sikshya-course-lp__faq-q"><?php echo esc_html($fq); ?></summary>
                                    <div class="sikshya-course-lp__faq-a sikshya-prose"><?php echo wp_kses_post($fa); ?></div>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php do_action('sikshya_single_course_after_main', $vm); ?>
            </main>

            <aside class="sikshya-course-lp__sidebar" aria-label="<?php esc_attr_e('Purchase options', 'sikshya'); ?>">
                <div class="sikshya-course-lp__card sik-f-card">
                    <div class="sikshya-course-lp__preview">
                        <?php
                        $thumb = '';
                        $watch = '';
                        if (is_array($video_preview)) {
                            $thumb = isset($video_preview['thumb_url']) ? (string) $video_preview['thumb_url'] : '';
                            $watch = isset($video_preview['watch_url']) ? (string) $video_preview['watch_url'] : '';
                        }
                        if ($thumb === '' && !empty($vm['featured_image_url'])) {
                            $thumb = (string) $vm['featured_image_url'];
                        }
                        ?>
                        <?php if ($thumb !== '' && $watch !== '') : ?>
                            <a class="sikshya-course-lp__preview-link" href="<?php echo esc_url($watch); ?>" target="_blank" rel="noopener noreferrer">
                                <img src="<?php echo esc_url($thumb); ?>" alt="" class="sikshya-course-lp__preview-img" loading="lazy" />
                                <span class="sikshya-course-lp__play" aria-hidden="true"></span>
                                <span class="sikshya-screen-reader-text"><?php esc_html_e('Play course preview', 'sikshya'); ?></span>
                            </a>
                        <?php elseif ($watch !== '') : ?>
                            <a class="sikshya-course-lp__preview-fallback" href="<?php echo esc_url($watch); ?>" target="_blank" rel="noopener noreferrer">
                                <?php esc_html_e('Watch preview video', 'sikshya'); ?>
                            </a>
                        <?php elseif ($thumb !== '') : ?>
                            <span class="sikshya-course-lp__preview-static">
                                <img src="<?php echo esc_url($thumb); ?>" alt="" class="sikshya-course-lp__preview-img" loading="lazy" />
                            </span>
                        <?php else : ?>
                            <div class="sikshya-course-lp__preview-placeholder" aria-hidden="true"></div>
                        <?php endif; ?>
                    </div>

                    <div class="sikshya-course-lp__buy">
                        <div class="sikshya-course-lp__price-row">
                            <div class="sikshya-course-lp__price">
                                <?php
                                if (!empty($pricing['on_sale']) && null !== ($pricing['price'] ?? null) && null !== ($pricing['sale_price'] ?? null)) {
                                    echo '<span class="sikshya-price-current">' . wp_kses_post(sikshya_format_price((float) $pricing['sale_price'], $pricing['currency'], $course_id)) . '</span> ';
                                    echo '<span class="sikshya-price-original">' . wp_kses_post(sikshya_format_price((float) $pricing['price'], $pricing['currency'], $course_id)) . '</span>';
                                } elseif (!empty($vm['is_paid'])) {
                                    echo '<span class="sikshya-price-current">' . wp_kses_post(sikshya_format_price((float) $pricing['effective'], $pricing['currency'], $course_id)) . '</span>';
                                } else {
                                    echo '<span class="sikshya-price-free">' . esc_html__('Free', 'sikshya') . '</span>';
                                }
                                ?>
                            </div>
                            <?php if (!empty($vm['discount_percent'])) : ?>
                                <span class="sikshya-course-lp__badge-off"><?php echo esc_html(sprintf(__('%d%% off', 'sikshya'), (int) $vm['discount_percent'])); ?></span>
                            <?php endif; ?>
                        </div>

                        <?php do_action('sikshya_single_course_after_price', $vm); ?>

                        <div class="sikshya-course-lp__actions">
                            <?php if (!empty($vm['is_enrolled'])) : ?>
                                <a class="sikshya-btn sikshya-btn--primary sikshya-course-lp__btn-full" href="<?php echo esc_url($urls['learn']); ?>"><?php esc_html_e('Continue learning', 'sikshya'); ?></a>
                                <a class="sikshya-btn sikshya-btn--ghost sikshya-course-lp__btn-full" href="<?php echo esc_url($urls['account']); ?>"><?php esc_html_e('My learning', 'sikshya'); ?></a>
                            <?php elseif (!empty($vm['is_paid'])) : ?>
                                <form method="post" action="<?php echo esc_url($permalink); ?>" class="sikshya-course-lp__form">
                                    <?php wp_nonce_field('sikshya_cart', 'sikshya_cart_nonce'); ?>
                                    <input type="hidden" name="sikshya_cart_action" value="add" />
                                    <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $course_id); ?>" />
                                    <input type="hidden" name="sikshya_redirect_to_checkout" value="1" />
                                    <button type="submit" class="sikshya-btn sikshya-btn--primary sikshya-course-lp__btn-full"><?php esc_html_e('Buy now', 'sikshya'); ?></button>
                                </form>
                                <form method="post" action="<?php echo esc_url($permalink); ?>" class="sikshya-course-lp__form">
                                    <?php wp_nonce_field('sikshya_cart', 'sikshya_cart_nonce'); ?>
                                    <input type="hidden" name="sikshya_cart_action" value="add" />
                                    <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $course_id); ?>" />
                                    <button type="submit" class="sikshya-btn sikshya-btn--ghost sikshya-course-lp__btn-full"><?php esc_html_e('Add to cart', 'sikshya'); ?></button>
                                </form>
                                <a class="sikshya-course-lp__sub-link" href="<?php echo esc_url($urls['cart']); ?>"><?php esc_html_e('View cart', 'sikshya'); ?></a>
                            <?php elseif (is_user_logged_in()) : ?>
                                <form method="post" action="<?php echo esc_url($permalink); ?>" class="sikshya-course-lp__form">
                                    <?php wp_nonce_field('sikshya_cart', 'sikshya_cart_nonce'); ?>
                                    <input type="hidden" name="sikshya_cart_action" value="enroll_free" />
                                    <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $course_id); ?>" />
                                    <button type="submit" class="sikshya-btn sikshya-btn--primary sikshya-course-lp__btn-full"><?php esc_html_e('Enroll for free', 'sikshya'); ?></button>
                                </form>
                            <?php else : ?>
                                <a class="sikshya-btn sikshya-btn--primary sikshya-course-lp__btn-full" href="<?php echo esc_url($urls['login']); ?>"><?php esc_html_e('Log in to enroll', 'sikshya'); ?></a>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($vm['money_back_text']) && !empty($vm['is_paid']) && empty($vm['is_enrolled'])) : ?>
                            <p class="sikshya-course-lp__guarantee"><?php echo esc_html((string) $vm['money_back_text']); ?></p>
                        <?php endif; ?>

                        <?php if (is_array($includes_lines) && $includes_lines !== []) : ?>
                            <div class="sikshya-course-lp__sidebar-includes">
                                <p class="sikshya-course-lp__includes-title"><?php esc_html_e('This course includes:', 'sikshya'); ?></p>
                                <ul class="sikshya-course-lp__checklist sikshya-course-lp__checklist--compact">
                                    <?php foreach (array_slice($includes_lines, 0, 8) as $line) : ?>
                                        <li><?php echo esc_html((string) $line); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php do_action('sikshya_single_course_after_actions', $vm); ?>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>

    <?php
endwhile;

get_footer();
