<?php
/**
 * Single lesson — {@see \Sikshya\Presentation\Models\SingleLessonPageModel}.
 *
 * @package Sikshya
 */

use Sikshya\Frontend\Site\LessonLearnContent;
use Sikshya\Frontend\Site\LessonTemplateData;
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
     * Passed value: `$page_model`.
     */
    do_action('sikshya_lesson_shell_head', $page_model);
    ?>
</head>
<?php
    $body_classes = ['sikshya-learning-shell', 'sikshya-learning-shell--lesson'];
    $body_classes[] = 'sikshya-learning-shell--type-' . sanitize_html_class((string) $page_model->getLessonTypeKey());
    ?>
<body class="<?php echo esc_attr(implode(' ', $body_classes)); ?>">
<a class="sikshya-skipLink" href="#sikshya-learn-content"><?php esc_html_e('Skip to lesson content', 'sikshya'); ?></a>
<div class="sikshya-learning-app">
    <?php require_once __DIR__ . '/partials/learn-icons.php'; ?>
    <div class="sikshya-learnLayout">
        <header class="sikshya-learnTopbar" role="banner">
            <div class="sikshya-learnTopbar__left">
                <button class="sikshya-iconBtn sikshya-iconBtn--menuToggle" type="button" aria-label="<?php echo esc_attr__('Menu', 'sikshya'); ?>" data-sikshya-toggle-outline>
                    <span class="sikshya-iconBtn__menuOpen" aria-hidden="true">
                        <?php echo sikshya_learn_icon('menu'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>
                    <span class="sikshya-iconBtn__menuClose" aria-hidden="true">
                        <?php echo sikshya_learn_icon('x'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>
                </button>
                <a class="sikshya-learnTopbar__brand" href="<?php echo esc_url(home_url('/')); ?>">
                    <?php echo esc_html(get_bloginfo('name')); ?>
                </a>
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
            <aside class="sikshya-learnSidebar" aria-label="<?php echo esc_attr(sprintf(__('%s content', 'sikshya'), $label_course)); ?>" data-sikshya-outline<?php echo $page_model->isLearnCurriculumSidebarScrollable() ? ' data-sik-curriculum-scroll="1"' : ''; ?>>
                <div class="sikshya-learnSidebar__inner">
                    <div class="sikshya-learnSidebar__head">
                        <?php
                        $sidebar_course_post = $page_model->getCoursePost();
                        $sidebar_course_title = $page_model->getCourseTitleForTopbar();
                        $sidebar_course_url = $sidebar_course_post instanceof WP_Post ? get_permalink($sidebar_course_post) : '';
                        ?>
                        <?php if ($sidebar_course_title !== '') : ?>
                            <h2 class="sikshya-learnSidebar__heading sikshya-zeroMargin" title="<?php echo esc_attr($sidebar_course_title); ?>">
                                <?php if ($sidebar_course_url !== '') : ?>
                                    <a class="sikshya-learnSidebar__courseLink" href="<?php echo esc_url($sidebar_course_url); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr(sprintf(__('%s (opens in a new tab)', 'sikshya'), $sidebar_course_title)); ?>">
                                        <?php echo esc_html($sidebar_course_title); ?>
                                    </a>
                                <?php else : ?>
                                    <?php echo esc_html($sidebar_course_title); ?>
                                <?php endif; ?>
                            </h2>
                        <?php endif; ?>
                        <p class="sikshya-learnSidebar__kicker sikshya-zeroMargin">
                            <?php
                            echo esc_html(sprintf(
                                /* translators: %s: singular label (e.g. Course) */
                                __('%s content', 'sikshya'),
                                $label_course
                            ));
                            ?>
                        </p>
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
                     * Passed values: legacy view array + `$page_model`.
                     */
                    do_action('sikshya_learn_sidebar_footer', $page_model->toLegacyViewArray(), $page_model);
                    ?>
                </div>
            </aside>

            <div class="sikshya-learnContentCol">
            <section id="sikshya-learn-content" class="sikshya-learnContent" aria-label="<?php esc_attr_e('Content', 'sikshya'); ?>">

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
                                        <button
                                            type="button"
                                            class="sikshya-iconBtn sikshya-learnHeader__focusBtn"
                                            data-sikshya-focus-toggle
                                            aria-pressed="false"
                                            aria-label="<?php echo esc_attr__('Focus mode (F)', 'sikshya'); ?>"
                                            title="<?php echo esc_attr__('Focus mode (F)', 'sikshya'); ?>"
                                        >
                                            <span class="sikshya-learnHeader__focusBtn-enter" aria-hidden="true">
                                                <?php echo sikshya_learn_icon('focus'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                            </span>
                                            <span class="sikshya-learnHeader__focusBtn-exit" aria-hidden="true">
                                                <?php echo sikshya_learn_icon('focus-exit'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                            </span>
                                        </button>
                                        <?php if ($page_model->isEnrolled() && !$page_model->isPreview() && !$page_model->isAssignmentPost()) : ?>
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

                    <?php
                    $lesson_primary_media_html = $page_model->getLessonPrimaryMediaHtml();
                    if ($lesson_primary_media_html !== '') :
                        ?>
                    <div class="sikshya-contentSection sikshya-contentSection--lessonPlayer">
                        <div class="sikshya-contentPanel sikshya-contentPanel--plain">
                            <?php echo LessonLearnContent::ksesLessonBody($lesson_primary_media_html); ?>
                        </div>
                    </div>
                        <?php
                    endif;
                    ?>

                    <?php
                    /**
                     * Full-width player slot above Overview / Resources tabs (same band as hosted video).
                     * Sikshya Pro registers SCORM + H5P interactive shells here — see
                     * `SikshyaPro\Addons\ScormH5pPro\Frontend\InteractivePlayerPresentation`.
                     *
                     * @param array<string, mixed>               $legacy_vm  Legacy lesson view data.
                     * @param \Sikshya\Presentation\Models\SingleLessonPageModel $page_model Typed model.
                     */
                    ob_start();
                    do_action('sikshya_lesson_above_tabs', $legacy_vm, $page_model);
                    $lesson_above_tabs_html = trim((string) ob_get_clean());
                    if ($lesson_above_tabs_html !== '') :
                        ?>
                    <div class="sikshya-contentSection sikshya-contentSection--lessonPlayer sikshya-contentSection--lessonInteractive">
                        <div class="sikshya-contentPanel sikshya-contentPanel--plain">
                            <?php
                            // Pro-registered hooks output controlled markup (player shell + data-config).
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            echo $lesson_above_tabs_html;
                            ?>
                        </div>
                    </div>
                        <?php
                    endif;
                    ?>

                    <?php
                    /**
                     * Course-completion CTA slot. Fires once per page render
                     * above the tabs strip — Pro addons (e.g. Certificates
                     * Advanced) can register a card here that links to the
                     * issued certificate. Listeners should self-check that
                     * the enrollment is actually completed before rendering;
                     * a passive default no-ops so Free installs stay clean.
                     *
                     * @param \Sikshya\Presentation\Models\SingleLessonPageModel $page_model
                     */
                    do_action('sikshya_learn_course_completion_cta', $page_model);
                    ?>

                    <div class="sikshya-tabsSection" aria-label="<?php esc_attr_e('Tabs', 'sikshya'); ?>">
                        <div class="sikshya-tabsBar" role="tablist">
                            <button type="button" id="sikshya-tab-overview" role="tab" aria-controls="sikshya-panel-overview" aria-selected="true" class="sikshya-tabBtn is-active" data-sikshya-tab="overview"><?php esc_html_e('Overview', 'sikshya'); ?></button>
                            <button type="button" id="sikshya-tab-resources" role="tab" aria-controls="sikshya-panel-resources" aria-selected="false" tabindex="-1" class="sikshya-tabBtn" data-sikshya-tab="resources"><?php esc_html_e('Resources', 'sikshya'); ?></button>
                            <button type="button" id="sikshya-tab-notes" role="tab" aria-controls="sikshya-panel-notes" aria-selected="false" tabindex="-1" class="sikshya-tabBtn" data-sikshya-tab="notes"><?php esc_html_e('Notes', 'sikshya'); ?></button>
                            <button type="button" id="sikshya-tab-announcements" role="tab" aria-controls="sikshya-panel-announcements" aria-selected="false" tabindex="-1" class="sikshya-tabBtn" data-sikshya-tab="announcements"><?php esc_html_e('Announcements', 'sikshya'); ?></button>
                            <?php
                            /**
                             * Extra tab buttons in the learner content chrome (same strip as Overview / Notes).
                             * Passed values: legacy view array + `$page_model`.
                             */
                            do_action('sikshya_learn_tabs_bar_append', $legacy_vm, $page_model);
                            ?>
                            <?php if ($page_model->hasReviewsTab()) : ?>
                                <button type="button" id="sikshya-tab-reviews" role="tab" aria-controls="sikshya-panel-reviews" aria-selected="false" tabindex="-1" class="sikshya-tabBtn" data-sikshya-tab="reviews"><?php esc_html_e('Reviews', 'sikshya'); ?></button>
                            <?php endif; ?>
                        </div>
                        <div id="sikshya-panel-overview" role="tabpanel" aria-labelledby="sikshya-tab-overview" class="sikshya-tabPanel is-active" data-sikshya-panel="overview">
                            <div class="sikshya-contentPanel sikshya-contentPanel--plain" data-sikshya-overview-well>
                                <?php
                                /**
                                 * 1) Legacy lesson view array. 2) {@see SingleLessonPageModel}.
                                 */
                                do_action('sikshya_lesson_before_content', $legacy_vm, $page_model);
                                ?>
                                <?php if ($page_model->hasRenderableLessonBody()) : ?>
                                    <?php echo LessonLearnContent::ksesLessonBody($page_model->getLessonPostContentHtml()); ?>
                                <?php elseif (!$page_model->isAssignmentPost()) : ?>
                                    <p class="sikshya-zeroMargin"><?php esc_html_e('This lesson does not have content yet.', 'sikshya'); ?></p>
                                <?php endif; ?>
                                <?php
                                do_action('sikshya_lesson_after_content', $legacy_vm, $page_model);
                                ?>
                                <?php if ($page_model->getAssignmentLearnPayload()) : ?>
                                    <?php require __DIR__ . '/partials/learn-assignment-panel.php'; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div id="sikshya-panel-resources" role="tabpanel" aria-labelledby="sikshya-tab-resources" hidden class="sikshya-tabPanel" data-sikshya-panel="resources">
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
                                                    // Issue a signed proxy URL bound to the current learner.
                                                    // Falls back to the raw uploads URL when the filter
                                                    // `sikshya_protect_attachments` is set to false (sites
                                                    // that already protect uploads/ at the server layer).
                                                    $url = \Sikshya\Security\AttachmentTokenService::signedUrlFor(
                                                        $aid,
                                                        (int) get_current_user_id()
                                                    );
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
                        <div id="sikshya-panel-notes" role="tabpanel" aria-labelledby="sikshya-tab-notes" hidden class="sikshya-tabPanel" data-sikshya-panel="notes">
                            <div class="sikshya-contentPanel sikshya-contentPanel--plain sikshya-learnNotes" data-sikshya-notes-shell>
                                <h3 class="sikshya-learnH3"><?php esc_html_e('My notes', 'sikshya'); ?></h3>
                                <p class="sikshya-learnNotes__hint sikshya-muted"><?php esc_html_e('Private to you — add as many short notes as you need while studying this lesson.', 'sikshya'); ?></p>
                                <p class="sikshya-learnNotes__empty sikshya-muted" data-sikshya-notes-empty hidden><?php esc_html_e('No notes yet. Add one below.', 'sikshya'); ?></p>
                                <ul class="sikshya-learnNotes__list" data-sikshya-notes-list></ul>
                                <div class="sikshya-learnNotes__composer">
                                    <label class="sikshya-learnNotes__composerLabel" for="sikshya-learn-note-new"><?php esc_html_e('New note', 'sikshya'); ?></label>
                                    <textarea
                                        id="sikshya-learn-note-new"
                                        class="sikshya-quizQ__textarea sikshya-learnNotes__textarea"
                                        rows="4"
                                        placeholder="<?php echo esc_attr__('Capture a takeaway, bookmark, or question…', 'sikshya'); ?>"
                                        data-sikshya-note-new
                                    ></textarea>
                                    <div class="sikshya-quizActions" style="margin-top:10px;">
                                        <button type="button" class="sikshya-btn sikshya-btn--outline" data-sikshya-note-add>
                                            <?php esc_html_e('Add note', 'sikshya'); ?>
                                        </button>
                                        <span class="sikshya-muted" data-sikshya-note-status style="font-size:12px;" role="status" aria-live="polite" aria-atomic="true"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="sikshya-panel-announcements" role="tabpanel" aria-labelledby="sikshya-tab-announcements" hidden class="sikshya-tabPanel" data-sikshya-panel="announcements">
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
                        /**
                         * Extra tab panels (same `data-sikshya-panel` system as Overview / Notes).
                         * Passed values: legacy view array + `$page_model`.
                         */
                        do_action('sikshya_learn_tab_panels_append', $legacy_vm, $page_model);
                        ?>
                        <?php if ($page_model->hasReviewsTab()) : ?>
                            <div id="sikshya-panel-reviews" role="tabpanel" aria-labelledby="sikshya-tab-reviews" hidden class="sikshya-tabPanel" data-sikshya-panel="reviews">
                                <div class="sikshya-contentPanel sikshya-contentPanel--plain">
                                    <h3 class="sikshya-learnH3"><?php esc_html_e('Reviews', 'sikshya'); ?></h3>
                                    <p class="sikshya-zeroMargin"><?php esc_html_e('Reviews are enabled for this course, but the reviews UI is not available yet.', 'sikshya'); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php endif; ?>
            </section>
            </div>
        </main>
    </div>
</div><?php // closes .sikshya-learning-app early so the dock + pill below are direct children of <body>. ?>
    <?php
    /*
     * Bottom prev/next dock — moved OUTSIDE `.sikshya-learning-app` to be a
     * direct sibling at body level. Previously nested inside .learning-app
     * → .learnLayout → .learnMain → .learnContentCol, all of which have
     * their own height / overflow rules (the base shell locks the app to
     * `max-height: 100dvh; overflow: hidden`). Even with position:fixed +
     * !important overrides, Chrome / Safari mobile sometimes pinned the
     * dock inside the constrained parent's box rather than the viewport,
     * making it invisible on mobile.
     * Now it's a direct child of <body>, so its containing block is the
     * initial containing block (viewport). Pure pinned chrome — visible
     * at viewport bottom on every device. */
    if (!$page_model->hasError()) :
        $dock_prev = $page_model->getDockPrevious();
        $dock_next = $page_model->getDockNext();
        $prev_url = $dock_prev ? $dock_prev->getUrl() : '';
        $next_url = $dock_next ? $dock_next->getUrl() : '';
        $prev_title = $dock_prev ? $dock_prev->getTitle() : '';
        $next_title = $dock_next ? $dock_next->getTitle() : '';
        ?>
        <nav class="sikshya-learnContentNav" aria-label="<?php esc_attr_e('Lesson navigation', 'sikshya'); ?>">
            <div class="sikshya-learnContentNav__side sikshya-learnContentNav__side--prev">
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
            </div>
            <div class="sikshya-learnContentNav__center">
                <?php
                ob_start();
                /**
                 * Center slot in the fixed lesson footer (e.g. Discussion / Q&A launcher from Pro add-ons).
                 *
                 * @param array<string, mixed>                         $legacy_vm  Back-compat view array.
                 * @param \Sikshya\Presentation\Models\SingleLessonPageModel $page_model
                 */
                do_action('sikshya_learn_content_nav_center', $legacy_vm, $page_model);
                $learn_nav_center = (string) ob_get_clean();
                if (trim($learn_nav_center) === '') {
                    echo '<span class="sikshya-learnContentNav__spacer" aria-hidden="true"></span>';
                } else {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- add-on HTML.
                    echo $learn_nav_center;
                }
                ?>
            </div>
            <div class="sikshya-learnContentNav__side sikshya-learnContentNav__side--next">
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
            </div>
        </nav>
    <?php endif; ?>
    <?php
    /*
     * Floating "you're done — confirm" pill. Hidden by default; learn.js fades it
     * in after the learner has watched >=80% of a video lesson OR scrolled past
     * ~70% of a text lesson. Shares the same data attributes as the header
     * button, so the existing mark-complete handler in learn.js handles the
     * click identically.
     */
    if (!$page_model->hasError()
        && $page_model->isEnrolled()
        && !$page_model->isPreview()
        && !$page_model->isAssignmentPost()
        && !$page_model->isCurrentCompleted()
    ) : ?>
        <button
            type="button"
            class="sikshya-learnCompletePill"
            data-sikshya-mark-complete
            data-sikshya-complete-pill
            data-course-id="<?php echo esc_attr((string) $page_model->getCourseId()); ?>"
            data-lesson-id="<?php echo esc_attr((string) $page_model->getLessonId()); ?>"
            aria-label="<?php echo esc_attr__('Mark this lesson complete', 'sikshya'); ?>"
            hidden
        >
            <span class="sikshya-btn__icon" aria-hidden="true">
                <?php echo sikshya_learn_icon('check'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </span>
            <?php esc_html_e('Mark as complete', 'sikshya'); ?>
        </button>
    <?php endif; ?>
    <?php
    /**
     * Footer hook for lesson shell scripts (no wp_footer()). Used by Sikshya Pro SCORM/H5P
     * player and other add-ons that need to print after lesson markup.
     */
    do_action('sikshya_lesson_shell_footer', $page_model);
    ?>
</body>
</html>

<?php
/*
 * Learn-shell client. The interactive surfaces (outline toggle, tabs,
 * progress popover, mark-complete, notes CRUD, assignment dropzone + submit)
 * used to live in a ~500-line inline `<script>` block right here. They now
 * live in `assets/js/learn.js`; this PHP block produces only the per-page
 * config (REST URL, nonce, course/lesson ids, translatable strings) that
 * the client reads from the `<script id="sikshya-learn-config">` tag.
 *
 * The learn shell intentionally does NOT call `wp_footer()`, so we emit the
 * `<script src>` tag directly rather than going through `wp_enqueue_script` —
 * see also `do_action('sikshya_lesson_shell_footer')` above for the addon
 * extension point.
 */
$__sikshya_learn_rest = $page_model->getRest();
$__sikshya_learn_cfg = [
    'rest' => [
        'url'   => (string) $__sikshya_learn_rest->getUrl(),
        'nonce' => (string) $__sikshya_learn_rest->getNonce(),
    ],
    'course_id' => (int) $page_model->getCourseId(),
    'lesson_id' => (int) $page_model->getLessonId(),
    'i18n' => [
        'saving'              => __('Saving…', 'sikshya'),
        'saved'               => __('Saved.', 'sikshya'),
        'added'               => __('Note added.', 'sikshya'),
        'removed'             => __('Note removed.', 'sikshya'),
        'failed'              => __('Could not save. Try again.', 'sikshya'),
        'failed_complete'     => __('Could not mark complete. Try again.', 'sikshya'),
        'edit'                => __('Edit', 'sikshya'),
        'save'                => __('Save', 'sikshya'),
        'cancel'              => __('Cancel', 'sikshya'),
        'delete'              => __('Delete', 'sikshya'),
        'confirm_delete_note' => __('Delete this note?', 'sikshya'),
        'note_text_aria'      => __('Note text', 'sikshya'),
        'asg_remove'          => __('Remove', 'sikshya'),
        'asg_too_many'        => __('Too many files for this assignment.', 'sikshya'),
        'asg_failed'          => __('Could not submit. Try again.', 'sikshya'),
        'asg_failed_short'    => __('Could not submit.', 'sikshya'),
        'shortcuts_title'     => __('Keyboard shortcuts', 'sikshya'),
        'sc_focus'            => __('Toggle focus mode', 'sikshya'),
        'sc_menu'             => __('Toggle sidebar', 'sikshya'),
        'sc_open'             => __('Open More', 'sikshya'),
        'sc_next'             => __('Next lesson', 'sikshya'),
        'sc_prev'             => __('Previous lesson', 'sikshya'),
        'sc_complete'         => __('Mark complete', 'sikshya'),
        'sc_esc'              => __('Close / exit', 'sikshya'),
    ],
];
$__sikshya_learn_js_url = SIKSHYA_PLUGIN_URL . 'assets/js/learn.js';
?>
<script id="sikshya-learn-config" type="application/json"><?php echo wp_json_encode($__sikshya_learn_cfg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
<script src="<?php echo esc_url($__sikshya_learn_js_url); ?>?ver=<?php echo esc_attr(SIKSHYA_VERSION); ?>" defer></script>

    <?php
endwhile;
