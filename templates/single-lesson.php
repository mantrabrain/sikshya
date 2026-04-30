<?php
/**
 * Single lesson — {@see \Sikshya\Presentation\Models\SingleLessonPageModel}.
 *
 * @package Sikshya
 */

use Sikshya\Frontend\Public\LessonTemplateData;
use Sikshya\Core\Plugin;
use Sikshya\Presentation\Models\SingleLessonPageModel;

$plugin = Plugin::getInstance();
$sheet_ver = rawurlencode((string) $plugin->version);
$ds_href = esc_url($plugin->getAssetUrl('css/public-design-system.css')) . '?ver=' . $sheet_ver;
$learn_href = esc_url($plugin->getAssetUrl('css/learn.css')) . '?ver=' . $sheet_ver;

while (have_posts()) :
    the_post();
    /** @var SingleLessonPageModel $page_model */
    $page_model = LessonTemplateData::forPost(get_post());
    $urls = $page_model->getUrls();
    $page_title = sprintf(
        /* translators: 1: lesson title, 2: site name */
        '%1$s — %2$s',
        $page_model->getLessonH1Title(),
        get_bloginfo('name')
    );

    $label_course = function_exists('sikshya_label') ? sikshya_label('course', __('Course', 'sikshya'), 'frontend') : __('Course', 'sikshya');
    ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html($page_title); ?></title>
    <link rel="stylesheet" href="<?php echo $ds_href; ?>">
    <link rel="stylesheet" href="<?php echo $learn_href; ?>">
    <?php
    /**
     * Extension point for lesson-shell <head> (no wp_head() — keeps the shell minimal).
     *
     * @param \Sikshya\Presentation\Models\SingleLessonPageModel $page_model
     */
    do_action('sikshya_lesson_shell_head', $page_model);
    ?>
</head>
<body class="sikshya-learning-shell sikshya-learning-shell--lesson">
<div class="sikshya-learning-app">
    <?php
    if (!function_exists('sikshya_learn_icon')) {
        /**
         * @return string
         */
        function sikshya_learn_icon(string $name): string
        {
            switch ($name) {
                case 'menu':
                    return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M4 6h16M4 12h16M4 18h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
                case 'x':
                    return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
                case 'exit-learn':
                    return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4m7 14l5-5-5-5m5 5H9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                case 'chevron-up':
                    return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M6 15l6-6 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                case 'chevron-right':
                    return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                case 'chevron-left':
                    return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                case 'book':
                    return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                case 'clipboard':
                    return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                case 'doc':
                    return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                case 'audio':
                    return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M11 5L6 9H3v6h3l5 4V5z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M15.5 8.5a4 4 0 010 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M18.5 6a7 7 0 010 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
                case 'folder':
                    return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>';
                case 'assignment':
                    return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                case 'chevron-down':
                    return '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                case 'play-video':
                    return '<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false"><rect x="4" y="5" width="14" height="14" rx="2.5" fill="none" stroke="currentColor" stroke-width="1.75"/><path d="M11 10.5v5l3.5-2.5L11 10.5z" fill="currentColor"/></svg>';
                case 'check':
                    return '<svg viewBox="0 0 24 24" width="11" height="11" aria-hidden="true" focusable="false"><path d="M5.5 12.5l2.5 2.5 6.5-8" fill="none" stroke="#ffffff" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                case 'lock':
                    return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M7 11V8a5 5 0 0110 0v3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M6.5 11h11A2.5 2.5 0 0120 13.5v6A2.5 2.5 0 0117.5 22h-11A2.5 2.5 0 014 19.5v-6A2.5 2.5 0 016.5 11z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>';
                default:
                    return '';
            }
        }
    }
    ?>
    <div class="sikshya-learnLayout">
        <header class="sikshya-learnTopbar" role="banner">
            <div class="sikshya-learnTopbar__left">
                <button class="sikshya-iconBtn" type="button" aria-label="<?php echo esc_attr__('Menu', 'sikshya'); ?>" data-sikshya-toggle-outline>
                    <?php echo sikshya_learn_icon('menu'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </button>
                <a class="sikshya-learnTopbar__brand" href="<?php echo esc_url(home_url('/')); ?>">
                    <?php echo esc_html(get_bloginfo('name')); ?>
                </a>
                <?php
                $course_title = $page_model->getCourseTitleForTopbar();
                ?>
                <span class="sikshya-learnTopbar__title" title="<?php echo esc_attr($course_title); ?>"><?php echo esc_html($course_title); ?></span>
            </div>
            <?php if ($page_model->isShowProgress()) : ?>
                <?php
                $pct = $page_model->getProgressPercent();
                $done = $page_model->getProgressCompleted();
                $total = $page_model->getProgressTotal();
                ?>
                <div class="sikshya-learnTopbar__middle">
                    <div class="sikshya-learnHeader__progressWrap">
                        <button
                            type="button"
                            class="sikshya-learnHeader__progressBtn"
                            data-sikshya-progress-btn
                            aria-haspopup="dialog"
                            aria-expanded="false"
                            aria-label="<?php echo esc_attr(sprintf(__('%s progress', 'sikshya'), $label_course)); ?>"
                        >
                            <span class="sikshya-learnHeader__progressBar" aria-hidden="true">
                                <span class="sikshya-learnHeader__progressFill" style="<?php echo esc_attr('width:' . max(0, min(100, $pct)) . '%'); ?>"></span>
                            </span>
                            <span class="sikshya-learnHeader__progressCount"><?php echo esc_html($done . '/' . $total); ?></span>
                            <span class="sikshya-learnHeader__progressPct"><?php echo esc_html($pct . '%'); ?></span>
                        </button>
                        <div class="sikshya-progressPopover" data-sikshya-progress-popover hidden>
                            <h3 class="sikshya-progressPopover__title"><?php esc_html_e('Progress', 'sikshya'); ?></h3>
                            <div class="sikshya-progressPopover__meta">
                                <?php
                                echo esc_html(
                                    sprintf(
                                        /* translators: 1: completed, 2: total */
                                        __('%1$d of %2$d items completed', 'sikshya'),
                                        $done,
                                        $total
                                    )
                                );
                                ?>
                            </div>
                            <div class="sikshya-progressPopover__bar" aria-hidden="true">
                                <span style="<?php echo esc_attr('width:' . max(0, min(100, $pct)) . '%'); ?>"></span>
                            </div>
                            <div class="sikshya-progressPopover__grid">
                                <div class="sikshya-progressPopover__stat">
                                    <p class="sikshya-progressPopover__k"><?php esc_html_e('Completed', 'sikshya'); ?></p>
                                    <p class="sikshya-progressPopover__v"><?php echo esc_html((string) $done); ?></p>
                                </div>
                                <div class="sikshya-progressPopover__stat">
                                    <p class="sikshya-progressPopover__k"><?php esc_html_e('Remaining', 'sikshya'); ?></p>
                                    <p class="sikshya-progressPopover__v"><?php echo esc_html((string) max(0, $total - $done)); ?></p>
                                </div>
                                <div class="sikshya-progressPopover__stat">
                                    <p class="sikshya-progressPopover__k"><?php esc_html_e('Total', 'sikshya'); ?></p>
                                    <p class="sikshya-progressPopover__v"><?php echo esc_html((string) $total); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <div class="sikshya-learnTopbar__right">
                <?php if ($urls->getAccountUrl() !== '') : ?>
                    <a class="sikshya-btn sikshya-btn--outline sikshya-btn--sm" href="<?php echo esc_url($urls->getAccountUrl()); ?>">
                        <?php echo sikshya_learn_icon('exit-learn'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php esc_html_e('Exit', 'sikshya'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <main class="sikshya-learnMain">
            <div class="sikshya-learnOverlay" data-sikshya-outline-overlay hidden></div>
            <aside class="sikshya-learnSidebar" aria-label="<?php echo esc_attr(sprintf(__('%s content', 'sikshya'), $label_course)); ?>" data-sikshya-outline>
                <div class="sikshya-learnSidebar__inner">
                    <div class="sikshya-learnSidebar__head">
                        <h2 class="sikshya-learnSidebar__heading">
                            <?php
                            echo esc_html(sprintf(
                                /* translators: %s: singular label (e.g. Course) */
                                __('%s content', 'sikshya'),
                                $label_course
                            ));
                            ?>
                        </h2>
                    </div>
                    <div class="sikshya-learnSidebar__scroll">
                        <?php
                        $outline_blocks = $page_model->getCurriculumBlocks();
                        $outline_show_progress = $page_model->isShowProgress();
                        require __DIR__ . '/partials/learn-curriculum-outline.php';
                        ?>
                    </div>
                    <?php
                    /**
                     * Footer slot inside the Learn sidebar (Pro add-ons render
                     * compact widgets here: Discussions/Q&A, Activity log,
                     * Certificate share, Coupons, etc.).
                     *
                     * The legacy view array is materialized inline because this
                     * hook fires before the lesson body where $legacy_vm is
                     * otherwise computed.
                     *
                     * @param array<string, mixed> $legacy_vm Legacy view array.
                     * @param \Sikshya\Presentation\Models\SingleLessonPageModel $page_model
                     */
                    do_action('sikshya_learn_sidebar_footer', $page_model->toLegacyViewArray(), $page_model);
                    ?>
                </div>
            </aside>

            <section class="sikshya-learnContent" aria-label="<?php esc_attr_e('Content', 'sikshya'); ?>">

                <?php if ($page_model->hasError()) : ?>
                    <div class="sikshya-contentSection sikshya-contentSection--centered">
                        <div class="sikshya-contentPanel sikshya-contentPanel--emptyState" role="alert" aria-live="polite">
                            <div class="sikshya-learnEmptyState">
                                <div class="sikshya-learnEmptyState__icon" aria-hidden="true">
                                    <?php echo sikshya_learn_icon('lock'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </div>
                                <div class="sikshya-learnEmptyState__body">
                                    <h2 class="sikshya-learnEmptyState__title"><?php esc_html_e('Access required', 'sikshya'); ?></h2>
                                    <p class="sikshya-learnEmptyState__message"><?php echo esc_html($page_model->getErrorMessage()); ?></p>
                                    <div class="sikshya-learnEmptyState__actions">
                                        <?php
                                        $errCourse = $page_model->getCoursePost();
                                        if ($errCourse instanceof WP_Post) : ?>
                                            <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url(get_permalink($errCourse)); ?>">
                                                <?php echo esc_html(sprintf(__('View %s', 'sikshya'), strtolower($label_course))); ?>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($urls->getAccountUrl() !== '') : ?>
                                            <a class="sikshya-btn sikshya-btn--outline" href="<?php echo esc_url($urls->getAccountUrl()); ?>">
                                                <?php esc_html_e('Go to account', 'sikshya'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else : ?>
                    <?php
                    $legacy_vm = $page_model->toLegacyViewArray();
                    ?>
                    <div class="sikshya-contentSection">
                        <div class="sikshya-contentPanel sikshya-contentPanel--header">
                            <div class="sikshya-learnHeader">
                                <div class="sikshya-learnHeader__top">
                                    <div class="sikshya-learnHeader__titles">
                                        <?php $lesson_icon = $page_model->getLessonIconForHeader(); ?>
                                        <div class="sikshya-learnHeader__titleRow">
                                            <span class="sikshya-learnHeader__typeIcon" aria-hidden="true">
                                                <?php echo sikshya_learn_icon($lesson_icon); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                            </span>
                                            <h1 class="sikshya-learnHeader__title sikshya-zeroMargin"><?php echo esc_html($page_model->getLessonH1Title()); ?></h1>
                                            <?php
                                            $ch = $page_model->getCurrentChapter();
                                            if ($ch instanceof WP_Post) : ?>
                                                <span class="sikshya-learnHeader__chapterInline" title="<?php echo esc_attr(get_the_title($ch)); ?>">
                                                    <span class="sikshya-learnHeader__chapterIcon" aria-hidden="true">
                                                        <?php echo sikshya_learn_icon('book'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                                    </span>
                                                    <?php echo esc_html(get_the_title($ch)); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="sikshya-learnHeader__actions">
                                        <?php if ($page_model->isEnrolled() && !$page_model->isPreview()) : ?>
                                            <?php
                                            $is_done = $page_model->isCurrentCompleted();
                                            $done_tip = __('Completed — this lesson is already marked complete in your progress.', 'sikshya');
                                            ?>
                                            <?php if ($is_done) : ?>
                                                <span class="sikshya-tooltipWrap" data-sikshya-tooltip-wrap>
                                            <?php endif; ?>
                                            <button
                                                type="button"
                                                class="sikshya-btn sikshya-btn--primary sikshya-btn--sm"
                                                data-sikshya-mark-complete
                                                data-course-id="<?php echo esc_attr((string) $page_model->getCourseId()); ?>"
                                                data-lesson-id="<?php echo esc_attr((string) $page_model->getLessonId()); ?>"
                                                <?php echo $is_done ? 'disabled aria-disabled="true"' : ''; ?>
                                            >
                                                <?php if ($is_done) : ?>
                                                    <span class="sikshya-btn__icon" aria-hidden="true">
                                                        <?php echo sikshya_learn_icon('check'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php echo $is_done ? esc_html__('Completed', 'sikshya') : esc_html__('Mark as complete', 'sikshya'); ?>
                                            </button>
                                            <?php if ($is_done) : ?>
                                                <span class="sikshya-tooltip" role="tooltip">
                                                    <?php echo esc_html($done_tip); ?>
                                                </span>
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="sikshya-tabsSection" aria-label="<?php esc_attr_e('Tabs', 'sikshya'); ?>">
                        <div class="sikshya-tabsBar" role="tablist">
                            <button type="button" class="sikshya-tabBtn is-active" data-sikshya-tab="overview"><?php esc_html_e('Overview', 'sikshya'); ?></button>
                            <button type="button" class="sikshya-tabBtn" data-sikshya-tab="resources"><?php esc_html_e('Resources', 'sikshya'); ?></button>
                            <button type="button" class="sikshya-tabBtn" data-sikshya-tab="notes"><?php esc_html_e('Notes', 'sikshya'); ?></button>
                            <button type="button" class="sikshya-tabBtn" data-sikshya-tab="announcements"><?php esc_html_e('Announcements', 'sikshya'); ?></button>
                            <?php if ($page_model->hasReviewsTab()) : ?>
                                <button type="button" class="sikshya-tabBtn" data-sikshya-tab="reviews"><?php esc_html_e('Reviews', 'sikshya'); ?></button>
                            <?php endif; ?>
                        </div>
                        <div class="sikshya-tabPanel is-active" data-sikshya-panel="overview">
                            <div class="sikshya-contentPanel sikshya-contentPanel--plain">
                                <?php
                                /**
                                 * 1) Legacy lesson view array. 2) {@see SingleLessonPageModel}.
                                 */
                                do_action('sikshya_lesson_before_content', $legacy_vm, $page_model);
                                ?>
                                <?php if ($page_model->hasRenderableLessonBody()) : ?>
                                    <?php echo wp_kses_post($page_model->getLessonContentHtml()); ?>
                                <?php else : ?>
                                    <p class="sikshya-zeroMargin"><?php esc_html_e('This lesson does not have content yet.', 'sikshya'); ?></p>
                                <?php endif; ?>
                                <?php
                                do_action('sikshya_lesson_after_content', $legacy_vm, $page_model);
                                ?>
                            </div>
                        </div>
                        <div class="sikshya-tabPanel" data-sikshya-panel="resources">
                            <div class="sikshya-contentPanel sikshya-contentPanel--plain">
                                <?php
                                $lesson_resources_rendered = apply_filters('sikshya_lesson_resources_rendered', false, $legacy_vm);
                                if (!$lesson_resources_rendered) :
                                    $course_id_for_resources = (int) $page_model->getCourseId();
                                    $resources = get_post_meta($course_id_for_resources, '_sikshya_course_resources', true);
                                    if (is_string($resources) && $resources !== '') {
                                        $decoded = json_decode($resources, true);
                                        if (is_array($decoded)) {
                                            $resources = $decoded;
                                        }
                                    }
                                    if (!is_array($resources)) {
                                        $resources = [];
                                    }
                                    ?>
                                    <h3 class="sikshya-learnH3"><?php esc_html_e('Downloads', 'sikshya'); ?></h3>
                                    <?php if ($resources === []) : ?>
                                        <p class="sikshya-zeroMargin"><?php esc_html_e('No resources available for this lesson yet.', 'sikshya'); ?></p>
                                    <?php else : ?>
                                        <ul class="sikshya-resList">
                                            <?php foreach ($resources as $r) : ?>
                                                <?php
                                                if (!is_array($r)) {
                                                    continue;
                                                }
                                                $rt = isset($r['title']) ? sanitize_text_field((string) $r['title']) : '';
                                                $url = isset($r['url']) ? esc_url_raw((string) $r['url']) : '';
                                                $aid = isset($r['attachment_id']) ? absint($r['attachment_id']) : 0;
                                                if ($url === '' && $aid > 0) {
                                                    $url = wp_get_attachment_url($aid) ?: '';
                                                }
                                                if ($url === '') {
                                                    continue;
                                                }
                                                ?>
                                                <li>
                                                    <a class="sikshya-resLink" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">
                                                        <?php echo esc_html($rt !== '' ? $rt : wp_basename($url)); ?>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php do_action('sikshya_lesson_resources_after', $legacy_vm, $page_model); ?>
                            </div>
                        </div>
                        <div class="sikshya-tabPanel" data-sikshya-panel="notes">
                            <div class="sikshya-contentPanel sikshya-contentPanel--plain">
                                <h3 class="sikshya-learnH3"><?php esc_html_e('My notes', 'sikshya'); ?></h3>
                                <label class="sikshya-screen-reader-text" for="sikshya-learn-note"><?php esc_html_e('My notes', 'sikshya'); ?></label>
                                <textarea
                                    id="sikshya-learn-note"
                                    class="sikshya-quizQ__textarea"
                                    rows="6"
                                    placeholder="<?php echo esc_attr__('Write a private note for this lesson…', 'sikshya'); ?>"
                                    data-sikshya-note
                                ></textarea>
                                <div class="sikshya-quizActions" style="margin-top:10px;">
                                    <button type="button" class="sikshya-btn sikshya-btn--outline" data-sikshya-note-save>
                                        <?php esc_html_e('Save note', 'sikshya'); ?>
                                    </button>
                                    <span class="sikshya-muted" data-sikshya-note-status style="font-size:12px;"></span>
                                </div>
                            </div>
                        </div>
                        <div class="sikshya-tabPanel" data-sikshya-panel="announcements">
                            <div class="sikshya-contentPanel sikshya-contentPanel--plain">
                                <h3 class="sikshya-learnH3"><?php esc_html_e('Announcements', 'sikshya'); ?></h3>
                                <?php
                                $ann = get_post_meta((int) $page_model->getCourseId(), '_sikshya_course_announcements', true);
                                if (is_string($ann) && $ann !== '') {
                                    $decoded = json_decode($ann, true);
                                    if (is_array($decoded)) {
                                        $ann = $decoded;
                                    }
                                }
                                if (!is_array($ann)) {
                                    $ann = [];
                                }
                                ?>
                                <?php if ($ann === []) : ?>
                                    <p class="sikshya-zeroMargin"><?php esc_html_e('No announcements available.', 'sikshya'); ?></p>
                                <?php else : ?>
                                    <?php foreach ($ann as $a) : ?>
                                        <?php
                                        if (!is_array($a)) {
                                            continue;
                                        }
                                        $at = isset($a['title']) ? sanitize_text_field((string) $a['title']) : '';
                                        $ad = isset($a['date']) ? sanitize_text_field((string) $a['date']) : '';
                                        $am = isset($a['message']) ? (string) $a['message'] : '';
                                        ?>
                                        <div class="sikshya-announce" style="margin-top:12px;">
                                            <div class="sikshya-announce__title"><?php echo esc_html($at !== '' ? $at : __('Announcement', 'sikshya')); ?></div>
                                            <?php if ($ad !== '') : ?>
                                                <div class="sikshya-announce__meta sikshya-muted"><?php echo esc_html($ad); ?></div>
                                            <?php endif; ?>
                                            <div class="sikshya-zeroMargin"><?php echo sikshya_render_rich_text($am); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php
                        // Discussions/Q&A now live in the Learn page left sidebar footer
                        // (rendered by the Community Discussions Pro add-on via
                        // `sikshya_learn_sidebar_footer`). The standalone tab here is removed
                        // to keep a single source of truth for course conversations.
                        ?>
                        <?php if ($page_model->hasReviewsTab()) : ?>
                            <div class="sikshya-tabPanel" data-sikshya-panel="reviews">
                                <div class="sikshya-contentPanel sikshya-contentPanel--plain">
                                    <h3 class="sikshya-learnH3"><?php esc_html_e('Reviews', 'sikshya'); ?></h3>
                                    <p class="sikshya-zeroMargin"><?php esc_html_e('Reviews are enabled for this course, but the reviews UI is not available yet.', 'sikshya'); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
        </div>

                    <?php
                    $dock_prev = $page_model->getDockPrevious();
                    $dock_next = $page_model->getDockNext();
                    $prev_url = $dock_prev ? $dock_prev->getUrl() : '';
                    $next_url = $dock_next ? $dock_next->getUrl() : '';
                    $prev_title = $dock_prev ? $dock_prev->getTitle() : '';
                    $next_title = $dock_next ? $dock_next->getTitle() : '';
                    ?>
                    <nav class="sikshya-learnDock" aria-label="<?php esc_attr_e('Lesson navigation', 'sikshya'); ?>">
                        <?php if ($prev_url !== '') : ?>
                            <a class="sikshya-learnDock__btn sikshya-learnDock__btn--prev" href="<?php echo esc_url($prev_url); ?>">
                                <span class="sikshya-learnDock__icon" aria-hidden="true"><?php echo sikshya_learn_icon('chevron-left'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                <span class="sikshya-learnDock__meta">
                                    <span class="sikshya-learnDock__kicker"><?php esc_html_e('Previous', 'sikshya'); ?></span>
                                    <span class="sikshya-learnDock__title"><?php echo esc_html($prev_title); ?></span>
                                </span>
                            </a>
                        <?php else : ?>
                            <span class="sikshya-learnDock__btn is-disabled" aria-disabled="true">
                                <span class="sikshya-learnDock__icon" aria-hidden="true"><?php echo sikshya_learn_icon('chevron-left'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                <span class="sikshya-learnDock__meta">
                                    <span class="sikshya-learnDock__kicker"><?php esc_html_e('Previous', 'sikshya'); ?></span>
                                    <span class="sikshya-learnDock__title"><?php esc_html_e('Start', 'sikshya'); ?></span>
                                </span>
                            </span>
                        <?php endif; ?>

                        <?php if ($next_url !== '') : ?>
                            <a class="sikshya-learnDock__btn sikshya-learnDock__btn--next" href="<?php echo esc_url($next_url); ?>">
                                <span class="sikshya-learnDock__meta">
                                    <span class="sikshya-learnDock__kicker"><?php esc_html_e('Next', 'sikshya'); ?></span>
                                    <span class="sikshya-learnDock__title"><?php echo esc_html($next_title); ?></span>
                                </span>
                                <span class="sikshya-learnDock__icon" aria-hidden="true"><?php echo sikshya_learn_icon('chevron-right'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                            </a>
                        <?php else : ?>
                            <span class="sikshya-learnDock__btn sikshya-learnDock__btn--next is-disabled" aria-disabled="true">
                                <span class="sikshya-learnDock__meta">
                                    <span class="sikshya-learnDock__kicker"><?php esc_html_e('Next', 'sikshya'); ?></span>
                                    <span class="sikshya-learnDock__title"><?php esc_html_e('End', 'sikshya'); ?></span>
                                </span>
                                <span class="sikshya-learnDock__icon" aria-hidden="true"><?php echo sikshya_learn_icon('chevron-right'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                            </span>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <footer class="sikshya-learning-footer" aria-hidden="true"></footer>
</div>
</body>
</html>

<script>
(() => {
  const root = document.documentElement;
  const overlay = document.querySelector('[data-sikshya-outline-overlay]');
  const toggleBtn = document.querySelector('[data-sikshya-toggle-outline]');
  const openClass = 'sikshya-outlineOpen';
  const collapsedClass = 'sikshya-sidebarCollapsed';

  function setOpen(isOpen) {
    root.classList.toggle(openClass, isOpen);
    if (overlay) overlay.hidden = !isOpen;
  }

  toggleBtn?.addEventListener('click', () => {
    if (window.matchMedia && window.matchMedia('(min-width: 1024px)').matches) {
      root.classList.toggle(collapsedClass);
      return;
    }
    setOpen(!root.classList.contains(openClass));
  });
  overlay?.addEventListener('click', () => setOpen(false));
  window.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    setOpen(false);
    root.classList.remove(collapsedClass);
  });

  // Tabs
  const tabs = document.querySelectorAll('[data-sikshya-tab]');
  const panels = document.querySelectorAll('[data-sikshya-panel]');
  tabs.forEach((btn) => {
    btn.addEventListener('click', () => {
      const target = btn.getAttribute('data-sikshya-tab');
      tabs.forEach((b) => b.classList.toggle('is-active', b === btn));
      panels.forEach((p) => p.classList.toggle('is-active', p.getAttribute('data-sikshya-panel') === target));
    });
  });

  // When the top bar scrolls away, remove the sidebar's reserved gap.
  const topbar = document.querySelector('.sikshya-learnTopbar');
  if (topbar && 'IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      const e = entries[0];
      root.style.setProperty('--sikshya-learn-topbar-visible', e && e.isIntersecting ? '1' : '0');
    }, { threshold: [0] });
    io.observe(topbar);
  }

  // Auto-focus current chapter + item in the sidebar (lesson/quiz load)
  window.addEventListener('load', () => {
    const scrollWrap = document.querySelector('[data-sikshya-outline] .sikshya-learnSidebar__scroll');
    if (!scrollWrap) return;

    const currentChapterSummary = scrollWrap.querySelector('[data-sikshya-current-chapter="1"] > summary');
    const currentLink = scrollWrap.querySelector('li[data-sikshya-current="1"] a');

    // Ensure the chapter header is visible, then the current item (centered).
    currentChapterSummary?.scrollIntoView({ block: 'nearest' });
    currentLink?.scrollIntoView({ block: 'center' });
  }, { once: true });

  // Progress popover
  const progressBtn = document.querySelector('[data-sikshya-progress-btn]');
  const popover = document.querySelector('[data-sikshya-progress-popover]');
  function closeProgress() {
    if (!progressBtn || !popover) return;
    popover.hidden = true;
    progressBtn.setAttribute('aria-expanded', 'false');
  }
  if (progressBtn && popover) {
    progressBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const next = popover.hidden ? false : true;
      popover.hidden = !popover.hidden;
      progressBtn.setAttribute('aria-expanded', popover.hidden ? 'false' : 'true');
    });
    document.addEventListener('click', (e) => {
      if (popover.hidden) return;
      const t = e.target;
      if (!(t instanceof Node)) return;
      if (popover.contains(t) || progressBtn.contains(t)) return;
      closeProgress();
    });
    window.addEventListener('keydown', (e) => {
      if (e.key !== 'Escape') return;
      closeProgress();
    });
  }

  // Mark lesson complete (REST)
  const completeBtn = document.querySelector('[data-sikshya-mark-complete]');
  if (completeBtn) {
    const restBase = <?php
    $__lesson_rest = $page_model->getRest();
    echo wp_json_encode((string) $__lesson_rest->getUrl(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    ?>;
    const restNonce = <?php echo wp_json_encode((string) $__lesson_rest->getNonce(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    completeBtn.addEventListener('click', async () => {
      const courseId = completeBtn.getAttribute('data-course-id') || '';
      const lessonId = completeBtn.getAttribute('data-lesson-id') || '';
      if (!restBase || !restNonce || !courseId || !lessonId) return;

      const prevText = completeBtn.textContent || '';
      completeBtn.setAttribute('disabled', 'disabled');
      completeBtn.textContent = 'Saving...';
      try {
        const res = await fetch(restBase.replace(/\/?$/, '/') + 'me/lesson-complete', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': restNonce,
          },
          body: JSON.stringify({ course_id: Number(courseId), lesson_id: Number(lessonId) }),
          credentials: 'same-origin',
        });
        const json = await res.json().catch(() => null);
        if (!res.ok || !json || json.ok !== true) {
          throw new Error((json && (json.message || json.data?.message)) || 'Failed');
        }
        window.location.reload();
      } catch (e) {
        completeBtn.removeAttribute('disabled');
        completeBtn.textContent = prevText;
        console.error(e);
      }
    });
  }

  // Notes (REST): private per-user note for this content.
  const noteTa = document.querySelector('[data-sikshya-note]');
  const noteSave = document.querySelector('[data-sikshya-note-save]');
  const noteStatus = document.querySelector('[data-sikshya-note-status]');
  if (noteTa && noteSave) {
    const restBase = <?php echo wp_json_encode((string) $page_model->getRest()->getUrl(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const restNonce = <?php echo wp_json_encode((string) $page_model->getRest()->getNonce(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const courseId = <?php echo wp_json_encode((int) $page_model->getCourseId(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const contentId = <?php echo wp_json_encode((int) $page_model->getLessonId(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    let loaded = false;
    async function loadNote() {
      if (loaded) return;
      loaded = true;
      try {
        const url = new URL(restBase.replace(/\/?$/, '/') + 'me/content-note', window.location.href);
        url.searchParams.set('course_id', String(courseId));
        url.searchParams.set('content_id', String(contentId));
        const res = await fetch(url.toString(), { method: 'GET', headers: { 'X-WP-Nonce': restNonce }, credentials: 'same-origin' });
        const json = await res.json().catch(() => null);
        if (res.ok && json && json.ok && json.data && typeof json.data.note === 'string') {
          noteTa.value = json.data.note;
        }
      } catch (e) {
        console.error(e);
      }
    }
    // Load when user switches to Notes tab.
    document.addEventListener('click', (e) => {
      const t = e.target;
      if (!(t instanceof Element)) return;
      if (t.matches('[data-sikshya-tab="notes"]')) {
        void loadNote();
      }
    });
    noteSave.addEventListener('click', async () => {
      if (!restBase || !restNonce) return;
      noteSave.setAttribute('disabled', 'disabled');
      if (noteStatus) noteStatus.textContent = 'Saving…';
      try {
        const res = await fetch(restBase.replace(/\/?$/, '/') + 'me/content-note', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': restNonce },
          body: JSON.stringify({ course_id: Number(courseId), content_id: Number(contentId), note: String(noteTa.value || '') }),
          credentials: 'same-origin',
        });
        const json = await res.json().catch(() => null);
        if (!res.ok || !json || json.ok !== true) {
          throw new Error((json && (json.message || json.data?.message)) || 'Failed');
        }
        if (noteStatus) noteStatus.textContent = 'Saved.';
        window.setTimeout(() => { if (noteStatus) noteStatus.textContent = ''; }, 1600);
      } catch (e) {
        if (noteStatus) noteStatus.textContent = 'Could not save.';
        console.error(e);
      } finally {
        noteSave.removeAttribute('disabled');
      }
    });
  }
})();
</script>

    <?php
endwhile;
