<?php
/**
 * Single course — Udemy-style landing layout; data from {@see \Sikshya\Frontend\Public\SingleCourseTemplateData}.
 *
 * @package Sikshya
 */

use Sikshya\Frontend\Public\SingleCourseTemplateData;
use Sikshya\Presentation\Models\SingleCoursePageModel;

sikshya_get_header();

while (have_posts()) :
    the_post();
    // NOTE: do NOT name this $page — that variable is reserved by WordPress
    // (see global $page in setup_postdata/get_the_content) and shadowing it
    // breaks the_content() with a TypeError on multipage posts.
    /** @var SingleCoursePageModel $page_model */
    $page_model = SingleCourseTemplateData::forPost(get_post());
    $legacy_vm = $page_model->toLegacyViewArray();
    $instructor_profiles = is_array($legacy_vm['instructor_profiles'] ?? null)
        ? $legacy_vm['instructor_profiles']
        : [];
    $pricing = $page_model->getPricing();
    $course_id = $page_model->getCourseId();
    $urls = $page_model->getUrls();
    $curriculum = $page_model->getCurriculum();
    $cart_flash = $page_model->getCartFlash();
    $permalink = get_permalink($course_id);
    $category_trail = $page_model->getCategoryTrail();
    $tag_pills = $page_model->getTagPills();
    $learning_outcomes = $page_model->getLearningOutcomes();
    $includes_lines    = $page_model->getIncludesLines();
    $curriculum_stats  = $page_model->getCurriculumStats();
    $is_bundle         = $page_model->isBundle();
    $bundle_courses    = $page_model->getBundleCourses();
    $video_preview = $page_model->getVideoPreview();
    $subtitle = $page_model->getSubtitle();

    $label_course = function_exists('sikshya_label') ? sikshya_label('course', __('Course', 'sikshya'), 'frontend') : __('Course', 'sikshya');
    $label_courses = function_exists('sikshya_label_plural') ? sikshya_label_plural('course', 'courses', __('Courses', 'sikshya'), 'frontend') : __('Courses', 'sikshya');
    $label_instructor = function_exists('sikshya_label') ? sikshya_label('instructor', __('Instructor', 'sikshya'), 'frontend') : __('Instructor', 'sikshya');
    ?>

<div class="sikshya-public sikshya-single-course sikshya-course-lp sik-f-scope">
    <div class="sikshya-course-lp__masthead">
        <div class="sikshya-container sikshya-container--course sikshya-course-lp__masthead-inner">
            <nav class="sikshya-course-lp__breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'sikshya'); ?>">
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'sikshya'); ?></a>
                <span class="sikshya-course-lp__bc-sep" aria-hidden="true">›</span>
                <?php if ($urls->getCoursesArchiveUrl() !== '') : ?>
                    <a href="<?php echo esc_url($urls->getCoursesArchiveUrl()); ?>"><?php echo esc_html($label_courses); ?></a>
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

            <?php do_action('sikshya_single_course_before_hero', $legacy_vm, $page_model); ?>

            <h1 class="sikshya-course-lp__title"><?php the_title(); ?></h1>
            <?php if ($subtitle !== '') : ?>
                <p class="sikshya-course-lp__subtitle"><?php echo esc_html($subtitle); ?></p>
            <?php endif; ?>

            <?php
            $reviews_vm = $page_model->getReviewsVm();
            $rev_aggregate = $reviews_vm['enabled'] ? ($reviews_vm['aggregate'] ?? null) : null;
            if (is_array($rev_aggregate) && (int) ($rev_aggregate['count'] ?? 0) > 0) :
                $avg = (float) $rev_aggregate['average'];
                $rev_count = (int) $rev_aggregate['count'];
                ?>
                <a href="#sikshya-reviews" class="sikshya-course-lp__rating" aria-label="<?php echo esc_attr(sprintf(_n('%1$s out of 5 stars based on %2$s review', '%1$s out of 5 stars based on %2$s reviews', $rev_count, 'sikshya'), number_format_i18n($avg, 1), number_format_i18n($rev_count))); ?>">
                    <span class="sikshya-rating-stars" aria-hidden="true" data-rating="<?php echo esc_attr((string) $avg); ?>">
                        <?php
                        $full = (int) floor($avg);
                        $half = ($avg - $full) >= 0.5;
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= $full) {
                                echo '<span class="sikshya-rating-star sikshya-rating-star--full">★</span>';
                            } elseif ($i === $full + 1 && $half) {
                                echo '<span class="sikshya-rating-star sikshya-rating-star--half">★</span>';
                            } else {
                                echo '<span class="sikshya-rating-star">☆</span>';
                            }
                        }
                        ?>
                    </span>
                    <span class="sikshya-rating-avg"><?php echo esc_html(number_format_i18n($avg, 1)); ?></span>
                    <span class="sikshya-rating-count">
                        (<?php echo esc_html(sprintf(_n('%s rating', '%s ratings', $rev_count, 'sikshya'), number_format_i18n($rev_count))); ?>)
                    </span>
                </a>
            <?php endif; ?>

            <div class="sikshya-course-lp__stats">
                <?php if ($page_model->getInstructorUser() instanceof WP_User) : ?>
                    <span class="sikshya-course-lp__stat">
                        <?php esc_html_e('Created by', 'sikshya'); ?>
                        <strong><?php echo esc_html($page_model->getInstructorUser()->display_name); ?></strong>
                    </span>
                <?php endif; ?>
                <?php if ($page_model->getLastUpdatedLabel() !== '') : ?>
                    <span class="sikshya-course-lp__stat">
                        <?php esc_html_e('Last updated', 'sikshya'); ?>
                        <strong><?php echo esc_html($page_model->getLastUpdatedLabel()); ?></strong>
                    </span>
                <?php endif; ?>
                <?php if ($page_model->getLanguageLabel() !== '') : ?>
                    <span class="sikshya-course-lp__stat">
                        <?php esc_html_e('Language', 'sikshya'); ?>
                        <strong><?php echo esc_html($page_model->getLanguageLabel()); ?></strong>
                    </span>
                <?php endif; ?>
                <?php if ($page_model->getDifficultyKey() !== '') : ?>
                    <span class="sikshya-course-lp__stat">
                        <?php esc_html_e('Level', 'sikshya'); ?>
                        <strong class="sikshya-difficulty-badge sikshya-difficulty-badge--<?php echo esc_attr(sanitize_html_class($page_model->getDifficultyKey())); ?>">
                            <?php echo esc_html(ucfirst($page_model->getDifficultyKey())); ?>
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
                        <h2 id="sikshya-includes-heading" class="sikshya-course-lp__heading">
                            <?php
                            echo esc_html(sprintf(
                                /* translators: %s: singular label (e.g. course) */
                                __('This %s includes', 'sikshya'),
                                strtolower($label_course)
                            ));
                            ?>
                        </h2>
                        <ul class="sikshya-course-lp__checklist">
                            <?php foreach ($includes_lines as $line) : ?>
                                <li><?php echo esc_html((string) $line); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>

                <?php if (is_array($curriculum) && $curriculum !== []) : ?>
                    <?php if ($is_bundle && !empty($bundle_courses)) : ?>
                    <section class="sikshya-course-lp__panel sikshya-course-lp__bundle-courses" aria-labelledby="sikshya-bundle-courses-heading">
                        <h2 id="sikshya-bundle-courses-heading" class="sikshya-course-lp__heading">
                            <?php
                            $bundle_count = count($bundle_courses);
                            $bundle_label = $bundle_count === 1 ? strtolower($label_course) : strtolower($label_courses);
                            echo esc_html(sprintf(
                                /* translators: 1: item count, 2: pluralized item label */
                                __('This bundle includes %1$d %2$s', 'sikshya'),
                                $bundle_count,
                                $bundle_label
                            ));
                            ?>
                        </h2>
                        <ul class="sikshya-bundle-course-list">
                            <?php foreach ($bundle_courses as $bc) : ?>
                                <li class="sikshya-bundle-course-item">
                                    <?php if (!empty($bc['thumb'])) : ?>
                                        <img class="sikshya-bundle-course-item__thumb" src="<?php echo esc_url($bc['thumb']); ?>" alt="" loading="lazy" />
                                    <?php else : ?>
                                        <span class="sikshya-bundle-course-item__thumb-ph" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                        </span>
                                    <?php endif; ?>
                                    <div class="sikshya-bundle-course-item__body">
                                        <a class="sikshya-bundle-course-item__title" href="<?php echo esc_url($bc['url']); ?>">
                                            <?php echo esc_html($bc['title']); ?>
                                        </a>
                                    </div>
                                    <a class="sikshya-btn sikshya-btn--outline sikshya-btn--sm sikshya-bundle-course-item__cta" href="<?php echo esc_url($bc['url']); ?>">
                                        <?php esc_html_e('Preview', 'sikshya'); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                    <?php endif; ?>

                    <section class="sikshya-course-lp__panel sikshya-course-lp__curriculum<?php echo $is_bundle ? ' sikshya-course-lp__curriculum--hidden-for-bundle' : ''; ?>" aria-labelledby="sikshya-curriculum-heading"<?php echo $is_bundle ? ' hidden' : ''; ?>>
                        <div class="sikshya-course-lp__curriculum-head">
                            <h2 id="sikshya-curriculum-heading" class="sikshya-course-lp__heading">
                                <?php
                                echo esc_html(sprintf(
                                    /* translators: %s: singular label (e.g. Course) */
                                    __('%s content', 'sikshya'),
                                    $label_course
                                ));
                                ?>
                            </h2>
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
                                <?php if ($page_model->getDurationLabel() !== '') : ?>
                                    <span class="sikshya-course-lp__curriculum-meta-sep">·</span>
                                    <span><?php echo esc_html(sprintf(__('Est. %s hours total', 'sikshya'), $page_model->getDurationLabel())); ?></span>
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
                                                $can_open = $page_model->isEnrolled();
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

                <?php if ($page_model->getTargetAudienceHtml() !== '') : ?>
                    <section class="sikshya-course-lp__panel" aria-labelledby="sikshya-audience-heading">
                        <h2 id="sikshya-audience-heading" class="sikshya-course-lp__heading">
                            <?php
                            echo esc_html(sprintf(
                                /* translators: %s: singular label (e.g. course) */
                                __('Who this %s is for', 'sikshya'),
                                strtolower($label_course)
                            ));
                            ?>
                        </h2>
                        <div class="sikshya-course-lp__audience sikshya-prose">
                            <?php echo $page_model->getTargetAudienceHtml(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shaped via wp_kses_post in service ?>
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
                        <h2 id="sikshya-instructor-heading" class="sikshya-course-lp__heading"><?php echo esc_html($label_instructor); ?></h2>
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
                if (!empty($reviews_vm['enabled'])) {
                    // Back-compat: the reviews partial expects `$vm` (legacy view array).
                    $vm = $legacy_vm;
                    include __DIR__ . '/partials/single-course-reviews.php';
                }
                ?>

                <?php
                $faq = $legacy_vm['course_faq'] ?? [];
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

                <?php do_action('sikshya_single_course_after_main', $legacy_vm, $page_model); ?>
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
                        if ($thumb === '' && $page_model->getFeaturedImageUrl() !== '') {
                            $thumb = $page_model->getFeaturedImageUrl();
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
                                } elseif ($page_model->isPaid()) {
                                    echo '<span class="sikshya-price-current">' . wp_kses_post(sikshya_format_price((float) $pricing['effective'], $pricing['currency'], $course_id)) . '</span>';
                                } else {
                                    echo '<span class="sikshya-price-free">' . esc_html__('Free', 'sikshya') . '</span>';
                                }
                                ?>
                            </div>
                            <?php if ($page_model->getDiscountPercent() > 0) : ?>
                                <span class="sikshya-course-lp__badge-off"><?php echo esc_html(sprintf(__('%d%% off', 'sikshya'), $page_model->getDiscountPercent())); ?></span>
                            <?php endif; ?>
                        </div>

                        <?php do_action('sikshya_single_course_after_price', $legacy_vm, $page_model); ?>

                        <div class="sikshya-course-lp__actions">
                            <?php if ($page_model->isEnrolled()) : ?>
                                <a class="sikshya-btn sikshya-btn--sm sikshya-btn--primary sikshya-course-lp__btn-full" href="<?php echo esc_url($urls->getLearnFirstUrl() !== '' ? $urls->getLearnFirstUrl() : $urls->getLearnUrl()); ?>"><?php esc_html_e('Continue learning', 'sikshya'); ?></a>
                                <a class="sikshya-btn sikshya-btn--sm sikshya-btn--ghost sikshya-course-lp__btn-full" href="<?php echo esc_url($urls->getAccountUrl()); ?>"><?php esc_html_e('My learning', 'sikshya'); ?></a>
                            <?php elseif ($page_model->isPaid()) : ?>
                                <form method="post" action="<?php echo esc_url($permalink); ?>" class="sikshya-course-lp__form">
                                    <?php wp_nonce_field('sikshya_cart', 'sikshya_cart_nonce'); ?>
                                    <input type="hidden" name="sikshya_cart_action" value="add" />
                                    <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $course_id); ?>" />
                                    <input type="hidden" name="sikshya_redirect_to_checkout" value="1" />
                                    <button type="submit" class="sikshya-btn sikshya-btn--sm sikshya-btn--primary sikshya-course-lp__btn-full"><?php esc_html_e('Buy now', 'sikshya'); ?></button>
                                </form>
                                <form method="post" action="<?php echo esc_url($permalink); ?>" class="sikshya-course-lp__form">
                                    <?php wp_nonce_field('sikshya_cart', 'sikshya_cart_nonce'); ?>
                                    <input type="hidden" name="sikshya_cart_action" value="add" />
                                    <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $course_id); ?>" />
                                    <button type="submit" class="sikshya-btn sikshya-btn--sm sikshya-btn--ghost sikshya-course-lp__btn-full"><?php esc_html_e('Add to cart', 'sikshya'); ?></button>
                                </form>
                                <a class="sikshya-course-lp__sub-link" href="<?php echo esc_url($urls->getCartUrl()); ?>"><?php esc_html_e('View cart', 'sikshya'); ?></a>
                                <?php if ($page_model->canAdminEnrollWithoutPurchase()) : ?>
                                    <form method="post" action="<?php echo esc_url($permalink); ?>" class="sikshya-course-lp__form sikshya-course-lp__form--admin-enroll">
                                        <?php wp_nonce_field('sikshya_cart', 'sikshya_cart_nonce'); ?>
                                        <input type="hidden" name="sikshya_cart_action" value="admin_enroll_bypass" />
                                        <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $course_id); ?>" />
                                        <button type="submit" class="sikshya-btn sikshya-btn--sm sikshya-btn--ghost sikshya-course-lp__btn-full"><?php esc_html_e('Enroll without purchase', 'sikshya'); ?></button>
                                    </form>
                                    <p class="sikshya-course-lp__admin-enroll-hint"><?php esc_html_e('Administrator: access this course without checkout.', 'sikshya'); ?></p>
                                <?php endif; ?>
                            <?php elseif (is_user_logged_in()) : ?>
                                <form method="post" action="<?php echo esc_url($permalink); ?>" class="sikshya-course-lp__form">
                                    <?php wp_nonce_field('sikshya_cart', 'sikshya_cart_nonce'); ?>
                                    <input type="hidden" name="sikshya_cart_action" value="enroll_free" />
                                    <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $course_id); ?>" />
                                    <button type="submit" class="sikshya-btn sikshya-btn--sm sikshya-btn--primary sikshya-course-lp__btn-full"><?php esc_html_e('Enroll for free', 'sikshya'); ?></button>
                                </form>
                            <?php else : ?>
                                <a class="sikshya-btn sikshya-btn--sm sikshya-btn--primary sikshya-course-lp__btn-full" href="<?php echo esc_url($urls->getLoginUrl()); ?>"><?php esc_html_e('Log in to enroll', 'sikshya'); ?></a>
                            <?php endif; ?>
                        </div>

                        <?php if ($page_model->getMoneyBackText() !== '' && $page_model->isPaid() && !$page_model->isEnrolled()) : ?>
                            <p class="sikshya-course-lp__guarantee"><?php echo esc_html($page_model->getMoneyBackText()); ?></p>
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

                        <?php do_action('sikshya_single_course_after_actions', $legacy_vm, $page_model); ?>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>

    <?php
endwhile;

sikshya_get_footer();
