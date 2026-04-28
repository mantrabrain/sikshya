<?php
/**
 * Single quiz template (learner-facing).
 *
 * @package Sikshya
 */

if (!defined('ABSPATH')) {
    exit;
}

use Sikshya\Services\Frontend\QuizPageService;
use Sikshya\Core\Plugin;
use Sikshya\Database\Repositories\QuizAttemptRepository;

$plugin = Plugin::getInstance();
$sheet_ver = rawurlencode((string) $plugin->version);
$ds_href = esc_url($plugin->getAssetUrl('css/public-design-system.css')) . '?ver=' . $sheet_ver;
$learn_href = esc_url($plugin->getAssetUrl('css/learn.css')) . '?ver=' . $sheet_ver;
$quiz_js = esc_url($plugin->getAssetUrl('js/quiz-taker.js')) . '?ver=' . $sheet_ver;

while (have_posts()) {
    the_post();
    $page_model = QuizPageService::forPost(get_post());
    $quiz_id = (int) $page_model->getPost()->ID;
    $page_title = sprintf(
        /* translators: 1: quiz title, 2: site name */
        '%1$s — %2$s',
        get_the_title(),
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
</head>
<body class="sikshya-learning-shell sikshya-learning-shell--quiz">
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
                $course_title = $page_model->getCourse() instanceof WP_Post
                    ? get_the_title($page_model->getCourse())
                    : __('Learn', 'sikshya');
                ?>
                <span class="sikshya-learnTopbar__title" title="<?php echo esc_attr($course_title); ?>"><?php echo esc_html($course_title); ?></span>
            </div>
            <?php if ($page_model->getShowProgress()) : ?>
                <?php
                $pct = $page_model->getStatsPercent();
                $done = $page_model->getStatsCompletedItems();
                $total = $page_model->getStatsTotalItems();
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
                <?php if ($page_model->getUrlAccount() !== '') : ?>
                    <a class="sikshya-btn sikshya-btn--outline sikshya-btn--sm" href="<?php echo esc_url($page_model->getUrlAccount()); ?>">
                        <?php echo sikshya_learn_icon('x'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php esc_html_e('Exit', 'sikshya'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </header>

            <?php if ($page_model->isLoggedIn() && $page_model->isEnrolled() && !$page_model->hasError()) : ?>
                <?php
                $quiz_id = (int) $page_model->getPost()->ID;
                // Time limit meta is the canonical source for learner timer UI.
                $duration_mins = $quiz_id > 0 ? (int) get_post_meta($quiz_id, '_sikshya_quiz_time_limit', true) : 0;
                if ($duration_mins <= 0 && $quiz_id > 0) {
                    $duration_mins = (int) get_post_meta($quiz_id, 'sikshya_quiz_time_limit', true);
                }
                // Back-compat: older installs may have stored duration under these keys.
                if ($duration_mins <= 0 && $quiz_id > 0) {
                    $duration_mins = (int) get_post_meta($quiz_id, '_sikshya_quiz_duration', true);
                }
                if ($duration_mins <= 0 && $quiz_id > 0) {
                    $duration_mins = (int) get_post_meta($quiz_id, 'sikshya_quiz_duration', true);
                }
                $attempt = null;
                try {
                    $repo = new QuizAttemptRepository();
                    $attempt = $repo->getLatestInProgressAttemptForUserQuiz(get_current_user_id(), $quiz_id);
                } catch (\Throwable $e) {
                    $attempt = null;
                }
                $attempt_started_ts = null;
                if ($attempt && isset($attempt->started_at)) {
                    try {
                        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $attempt->started_at, wp_timezone());
                        if ($dt instanceof \DateTimeImmutable) {
                            $attempt_started_ts = $dt->getTimestamp();
                        }
                    } catch (\Throwable $e) {
                        $attempt_started_ts = null;
                    }
                }
                $legacy = $page_model->toLegacyViewArray();
                $navNext = '';
                if (is_array($legacy) && isset($legacy['nav']) && is_array($legacy['nav']) && isset($legacy['nav']['next'])) {
                    $navNext = (string) $legacy['nav']['next'];
                }
                $navPrev = '';
                if (is_array($legacy) && isset($legacy['nav']) && is_array($legacy['nav']) && isset($legacy['nav']['prev'])) {
                    $navPrev = (string) $legacy['nav']['prev'];
                }
                ?>
                <script>
                  window.sikshyaQuizTaker = <?php
                  echo wp_json_encode(
                      [
                          'restUrl' => esc_url_raw(rest_url('sikshya/v1/')),
                          'restNonce' => wp_create_nonce('wp_rest'),
                          'quizId' => (string) $quiz_id,
                          'durationSeconds' => $duration_mins > 0 ? ((int) $duration_mins * 60) : 0,
                          'serverTime' => time(),
                          'locked' => (bool) $page_model->isAttemptsExhausted(),
                          'attempt' => $attempt
                              ? [
                                  'id' => (int) $attempt->id,
                                  'started_at' => (string) $attempt->started_at,
                                  'started_at_ts' => $attempt_started_ts,
                                  'status' => (string) $attempt->status,
                                  'attempt_number' => (int) $attempt->attempt_number,
                              ]
                              : null,
                          'nextUrl' => $navNext !== '' ? esc_url_raw($navNext) : null,
                          'prevUrl' => $navPrev !== '' ? esc_url_raw($navPrev) : null,
                          'advanced' => $page_model->getAdvanced(),
                          'i18n' => [
                              'score' => __('Your score: %s%%', 'sikshya'),
                              'passingScore' => __('Passing score: %s%%', 'sikshya'),
                              'passed' => __('You passed this quiz.', 'sikshya'),
                              'notPassed' => __('You did not reach the passing score.', 'sikshya'),
                              'error' => __('Could not submit the quiz. Please try again.', 'sikshya'),
                              'resultsTitle' => __('Quiz results', 'sikshya'),
                              'continue' => __('Continue', 'sikshya'),
                              'reviewAnswers' => __('Review answers', 'sikshya'),
                              'hideAnswers' => __('Hide answers', 'sikshya'),
                              'tryAgain' => __('Try again', 'sikshya'),
                          ],
                      ],
                      JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                  );
                  ?>;
                </script>
                <script src="<?php echo $quiz_js; ?>" defer></script>
            <?php endif; ?>

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
                        $outline_blocks = $page_model->getBlocks();
                        $outline_show_progress = $page_model->getShowProgress();
                        require __DIR__ . '/partials/learn-curriculum-outline.php';
                        ?>
                    </div>
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
                                    <p class="sikshya-learnEmptyState__message"><?php echo esc_html($page_model->getError()); ?></p>
                                    <div class="sikshya-learnEmptyState__actions">
                                        <?php if ($page_model->getCourse() instanceof WP_Post) : ?>
                                            <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url(get_permalink($page_model->getCourse())); ?>">
                                                <?php echo esc_html(sprintf(__('View %s', 'sikshya'), strtolower($label_course))); ?>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($page_model->getUrlAccount() !== '') : ?>
                                            <a class="sikshya-btn sikshya-btn--outline" href="<?php echo esc_url($page_model->getUrlAccount()); ?>">
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
                    $quiz_id = (int) $page_model->getPost()->ID;
                    $question_count = count($page_model->getQuestions());
                    // Time limit meta is the canonical source for learner timer UI.
                    $duration_mins = $quiz_id > 0 ? (int) get_post_meta($quiz_id, '_sikshya_quiz_time_limit', true) : 0;
                    if ($duration_mins <= 0 && $quiz_id > 0) {
                        $duration_mins = (int) get_post_meta($quiz_id, 'sikshya_quiz_time_limit', true);
                    }
                    // Back-compat: older installs may have stored duration under these keys.
                    if ($duration_mins <= 0 && $quiz_id > 0) {
                        $duration_mins = (int) get_post_meta($quiz_id, '_sikshya_quiz_duration', true);
                    }
                    if ($duration_mins <= 0 && $quiz_id > 0) {
                        $duration_mins = (int) get_post_meta($quiz_id, 'sikshya_quiz_duration', true);
                    }
                    $passing_score = $quiz_id > 0 ? (int) get_post_meta($quiz_id, '_sikshya_quiz_passing_score', true) : 0;
                    $passing_score = $passing_score > 0 ? $passing_score : 70;
                    $attempts_max = $page_model->getAttemptsMax();
                    $attempts_exhausted = $page_model->isAttemptsExhausted();
                    $attempts_message = $page_model->getAttemptsMessage();
                    ?>
                    <div class="sikshya-contentSection">
                        <div class="sikshya-contentPanel sikshya-contentPanel--header">
                            <div class="sikshya-learnHeader">
                                <div class="sikshya-learnHeader__top">
                                    <div class="sikshya-learnHeader__titles">
                                        <div class="sikshya-learnHeader__titleRow sikshya-learnHeader__titleRow--withMeta">
                                            <div class="sikshya-learnHeader__titleLeft">
                                                <span class="sikshya-learnHeader__typeIcon" aria-hidden="true">
                                                    <?php echo sikshya_learn_icon('clipboard'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                                </span>
                                                <h1 class="sikshya-learnHeader__title sikshya-zeroMargin"><?php echo esc_html(get_the_title()); ?></h1>
                                                <?php if ($page_model->getCurrentChapter() instanceof WP_Post) : ?>
                                                    <span class="sikshya-learnHeader__chapterInline" title="<?php echo esc_attr($page_model->getCurrentChapter() ? get_the_title($page_model->getCurrentChapter()) : ''); ?>">
                                                        <span class="sikshya-learnHeader__chapterIcon" aria-hidden="true">
                                                            <?php echo sikshya_learn_icon('book'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                                        </span>
                                                        <?php echo esc_html($page_model->getCurrentChapter() ? get_the_title($page_model->getCurrentChapter()) : ''); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="sikshya-learnHeader__metaInline" aria-label="<?php echo esc_attr__('Quiz details', 'sikshya'); ?>">
                                                <span class="sikshya-pill sikshya-pill--emphasis"><?php esc_html_e('Assessment', 'sikshya'); ?></span>
                                                <span class="sikshya-pill"><?php echo esc_html(sprintf(__('%d questions', 'sikshya'), (int) $question_count)); ?></span>
                                                <?php if ($duration_mins > 0) : ?>
                                                    <span class="sikshya-pill"><?php echo esc_html(sprintf(__('%d min', 'sikshya'), (int) $duration_mins)); ?></span>
                                                <?php endif; ?>
                                                <span class="sikshya-pill"><?php echo esc_html(sprintf(__('Pass %d%%', 'sikshya'), (int) $passing_score)); ?></span>
                                                <?php if ($attempts_max > 0) : ?>
                                                    <span class="sikshya-pill"><?php echo esc_html(sprintf(__('Attempts %d', 'sikshya'), (int) $attempts_max)); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="sikshya-learnHeader__actions">
                                        <?php if ($duration_mins > 0) : ?>
                                            <div class="sikshya-quizTimer" data-sikshya-quiz-timer aria-live="polite">
                                                <span class="sikshya-quizTimer__label"><?php esc_html_e('Time left', 'sikshya'); ?></span>
                                                <span class="sikshya-quizTimer__value" data-sikshya-quiz-timer-value>00:00:00</span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!$attempts_exhausted && $question_count > 0) : ?>
                                            <button
                                                type="button"
                                                class="sikshya-btn sikshya-btn--primary sikshya-btn--sm"
                                                data-sikshya-quiz-start
                                                aria-expanded="false"
                                                aria-controls="sikshya-learn-quiz-form"
                                            ><?php esc_html_e('Start quiz', 'sikshya'); ?></button>
                                        <?php elseif (!$attempts_exhausted && $question_count <= 0) : ?>
                                            <span class="sikshya-tooltipWrap" data-sikshya-tooltip-wrap>
                                                <button
                                                    type="button"
                                                    class="sikshya-btn sikshya-btn--primary sikshya-btn--sm"
                                                    disabled
                                                    aria-disabled="true"
                                                ><?php esc_html_e('Start quiz', 'sikshya'); ?></button>
                                                <span class="sikshya-tooltip" role="tooltip">
                                                    <?php esc_html_e('This quiz does not have questions yet.', 'sikshya'); ?>
                                                </span>
                                            </span>
                                        <?php else : ?>
                                            <?php
                                            $lock_tip = $attempts_message !== ''
                                                ? $attempts_message
                                                : __('You have reached the maximum number of attempts for this quiz.', 'sikshya');
                                            ?>
                                            <span class="sikshya-tooltipWrap" data-sikshya-tooltip-wrap>
                                                <button
                                                    type="button"
                                                    class="sikshya-btn sikshya-btn--primary sikshya-btn--sm"
                                                    disabled
                                                    aria-disabled="true"
                                                ><?php esc_html_e('Quiz locked', 'sikshya'); ?></button>
                                                <span class="sikshya-tooltip" role="tooltip">
                                                    <?php echo esc_html($lock_tip); ?>
                                                </span>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php
                        $show_quiz_intro = !$attempts_exhausted && $question_count > 0;
                        $intro_lead = '';
                        if ($show_quiz_intro) {
                            $ex = trim(get_the_excerpt());
                            if ($ex !== '') {
                                $intro_lead = $ex;
                            } else {
                                $raw = $page_model->getPostContentRaw();
                                $plain = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($raw)));
                                if (strlen($plain) > 40) {
                                    if (function_exists('mb_substr')) {
                                        $intro_lead = mb_substr($plain, 0, 320) . (mb_strlen($plain) > 320 ? '…' : '');
                                    } else {
                                        $intro_lead = substr($plain, 0, 320) . (strlen($plain) > 320 ? '…' : '');
                                    }
                                }
                            }
                            if ($intro_lead === '') {
                                $intro_lead = __(
                                    'This short quiz helps lock in what you learned. You can change your answers any time before you press Submit.',
                                    'sikshya'
                                );
                            }
                        }
                        ?>
                        <?php if ($show_quiz_intro) : ?>
                            <div class="sikshya-contentPanel sikshya-contentPanel--quizIntro" data-sikshya-quiz-intro>
                                <div class="sikshya-quizIntro">
                                    <?php
                                    $adv_notice = $page_model->getAdvanced();
                                    if (is_array($adv_notice) && !empty($adv_notice['learner_notice'])) :
                                        ?>
                                        <p class="sikshya-quizIntro__notice" role="status"><?php echo esc_html((string) $adv_notice['learner_notice']); ?></p>
                                    <?php endif; ?>
                                    <p class="sikshya-quizIntro__eyebrow"><?php esc_html_e('Your next step', 'sikshya'); ?></p>
                                    <h2 class="sikshya-quizIntro__title"><?php esc_html_e('Ready when you are', 'sikshya'); ?></h2>
                                    <p class="sikshya-quizIntro__lead"><?php echo esc_html($intro_lead); ?></p>
                                    <ul class="sikshya-quizIntro__facts">
                                        <li>
                                            <?php
                                            echo esc_html(
                                                sprintf(
                                                    /* translators: %d: number of questions */
                                                    _n('%d question in this attempt', '%d questions in this attempt', $question_count, 'sikshya'),
                                                    $question_count
                                                )
                                            );
                                            ?>
                                        </li>
                                        <li>
                                            <?php
                                            echo esc_html(
                                                sprintf(
                                                    /* translators: %d: passing score percentage */
                                                    __('You need %d%% or higher to pass.', 'sikshya'),
                                                    (int) $passing_score
                                                )
                                            );
                                            ?>
                                        </li>
                                        <?php if ($attempts_max > 0) : ?>
                                            <li>
                                                <?php
                                                if ((int) $attempts_max === 1) {
                                                    esc_html_e('You have one attempt for this quiz.', 'sikshya');
                                                } else {
                                                    echo esc_html(
                                                        sprintf(
                                                            /* translators: %d: max attempts */
                                                            __('You can try up to %d times if you need another shot.', 'sikshya'),
                                                            (int) $attempts_max
                                                        )
                                                    );
                                                }
                                                ?>
                                            </li>
                                        <?php endif; ?>
                                        <?php if ($duration_mins > 0) : ?>
                                            <li>
                                                <?php
                                                echo esc_html(
                                                    sprintf(
                                                        /* translators: %d: minutes */
                                                        __('Time limit: about %d minutes.', 'sikshya'),
                                                        (int) $duration_mins
                                                    )
                                                );
                                                ?>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                    <?php if ($question_count > 0) : ?>
                                        <button
                                            type="button"
                                            class="sikshya-btn sikshya-btn--primary sikshya-quizIntro__cta"
                                            data-sikshya-quiz-start
                                            aria-expanded="false"
                                            aria-controls="sikshya-learn-quiz-form"
                                        ><?php esc_html_e('Start quiz', 'sikshya'); ?></button>
                                    <?php endif; ?>
                                    <p class="sikshya-quizIntro__hint">
                                        <?php esc_html_e('Questions will open in this area. Extra study tips live in the Overview tab below.', 'sikshya'); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="sikshya-contentPanel sikshya-contentPanel--plain">
                            <?php if ($page_model->getQuestions() !== []) : ?>
                                <form
                                    id="sikshya-learn-quiz-form"
                                    class="sikshya-quiz-form"
                                    data-quiz-id="<?php echo esc_attr((string) $quiz_id); ?>"
                                    action="#"
                                    method="post"
                                    data-sikshya-quiz-form
                                    <?php echo $attempts_exhausted ? '' : 'hidden'; ?>
                                >
                                    <?php if ($attempts_exhausted) : ?>
                                        <fieldset disabled aria-disabled="true">
                                    <?php endif; ?>
                                    <?php foreach ($page_model->getQuestions() as $qi => $q) : ?>
                                        <?php
                                        if (!is_array($q) || (isset($q['id']) ? (int) $q['id'] : 0) <= 0) {
                                            continue;
                                        }
                                        require __DIR__ . '/partials/quiz-question-fieldset.php';
                                        ?>
                                    <?php endforeach; ?>
                                    <?php if ($attempts_exhausted) : ?>
                                        </fieldset>
                                    <?php endif; ?>
                                    <div class="sikshya-quiz-result" hidden aria-live="polite"></div>
                                    <div class="sikshya-quizActions">
                                        <?php
                                        $submit_label = $attempts_exhausted ? __('Quiz locked', 'sikshya') : __('Submit quiz', 'sikshya');
                                        $submit_title = '';
                                        if ($attempts_exhausted) {
                                            $submit_title = $attempts_message !== '' ? $attempts_message : __('No quiz attempts remaining.', 'sikshya');
                                        }
                                        ?>
                                        <button
                                            type="submit"
                                            class="sikshya-btn sikshya-btn--primary sikshya-quiz-submit"
                                            <?php echo $attempts_exhausted ? 'disabled aria-disabled="true"' : ''; ?>
                                            <?php echo $submit_title !== '' ? 'title="' . esc_attr($submit_title) . '"' : ''; ?>
                                        ><?php echo esc_html($submit_label); ?></button>
                                </div>
                                </form>
                            <?php else : ?>
                                <?php if (trim((string) get_the_content()) !== '') : ?>
                                    <?php the_content(); ?>
                                <?php else : ?>
                                    <p class="sikshya-zeroMargin"><?php esc_html_e('This quiz does not have questions yet.', 'sikshya'); ?></p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="sikshya-tabsSection" aria-label="<?php esc_attr_e('Tabs', 'sikshya'); ?>">
                        <div class="sikshya-tabsBar" role="tablist">
                            <button type="button" class="sikshya-tabBtn is-active" data-sikshya-tab="overview"><?php esc_html_e('Overview', 'sikshya'); ?></button>
                            <button type="button" class="sikshya-tabBtn" data-sikshya-tab="resources"><?php esc_html_e('Resources', 'sikshya'); ?></button>
                            <button type="button" class="sikshya-tabBtn" data-sikshya-tab="instructions"><?php esc_html_e('Instructions', 'sikshya'); ?></button>
                            <button type="button" class="sikshya-tabBtn" data-sikshya-tab="notes"><?php esc_html_e('Notes', 'sikshya'); ?></button>
                            <button type="button" class="sikshya-tabBtn" data-sikshya-tab="announcements"><?php esc_html_e('Announcements', 'sikshya'); ?></button>
                            <?php if ($page_model->isCourseFeatureDiscussions()) : ?>
                                <button type="button" class="sikshya-tabBtn" data-sikshya-tab="discussions"><?php esc_html_e('Discussions', 'sikshya'); ?></button>
                            <?php endif; ?>
                            <?php if ($page_model->isCourseFeatureReviews()) : ?>
                                <button type="button" class="sikshya-tabBtn" data-sikshya-tab="reviews"><?php esc_html_e('Reviews', 'sikshya'); ?></button>
                            <?php endif; ?>
                        </div>
                        <div class="sikshya-tabPanel is-active" data-sikshya-panel="overview">
                            <div class="sikshya-contentPanel sikshya-contentPanel--plain">
                                <?php if (trim((string) get_the_content()) !== '') : ?>
                                    <?php the_content(); ?>
                                <?php else : ?>
                                    <h2><?php esc_html_e('Before you start', 'sikshya'); ?></h2>
                                    <p><?php esc_html_e('This quiz checks your understanding of the key ideas. Take your time and read the options carefully — you can change answers before submitting.', 'sikshya'); ?></p>
                                    <ul>
                                        <li><?php esc_html_e('Aim for accuracy first, speed second.', 'sikshya'); ?></li>
                                        <li><?php esc_html_e('If you miss a question, revisit the related lesson section.', 'sikshya'); ?></li>
                                        <li><?php esc_html_e('Focus on fundamentals: definitions, flow, and intent.', 'sikshya'); ?></li>
                                    </ul>
                                    <blockquote>
                                        <p><?php esc_html_e('Tip: Treat quizzes as feedback loops. The goal is durable understanding.', 'sikshya'); ?></p>
                                    </blockquote>
                                    <h3><?php esc_html_e('Common mistakes', 'sikshya'); ?></h3>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('Mistake', 'sikshya'); ?></th>
                                                <th><?php esc_html_e('Fix', 'sikshya'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><?php esc_html_e('Rushing through options', 'sikshya'); ?></td>
                                                <td><?php esc_html_e('Slow down and eliminate wrong answers first', 'sikshya'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><?php esc_html_e('Guessing definitions', 'sikshya'); ?></td>
                                                <td><?php esc_html_e('Recall the exact meaning from the lesson notes', 'sikshya'); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="sikshya-tabPanel" data-sikshya-panel="resources">
                            <div class="sikshya-contentPanel sikshya-contentPanel--plain">
                                <h3 class="sikshya-learnH3"><?php esc_html_e('Downloads', 'sikshya'); ?></h3>
                                <?php
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
                                <?php if ($resources === []) : ?>
                                    <p class="sikshya-zeroMargin"><?php esc_html_e('No resources available for this quiz yet.', 'sikshya'); ?></p>
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
                            </div>
                        </div>
                        <div class="sikshya-tabPanel" data-sikshya-panel="instructions">
                            <div class="sikshya-contentPanel sikshya-contentPanel--plain">
                                <h3 class="sikshya-learnH3"><?php esc_html_e('Instructions', 'sikshya'); ?></h3>
                                <ul class="sikshya-learnList">
                                    <li><?php esc_html_e('Answer all questions. You can change answers before submitting.', 'sikshya'); ?></li>
                                    <li><?php esc_html_e('Keep an eye on the timer. Your attempt auto-submits when time ends.', 'sikshya'); ?></li>
                                    <li><?php esc_html_e('You need the passing score to complete this section.', 'sikshya'); ?></li>
                                </ul>
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
                                    placeholder="<?php echo esc_attr__('Write a private note for this quiz…', 'sikshya'); ?>"
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
                        <?php if ($page_model->isCourseFeatureDiscussions()) : ?>
                            <div class="sikshya-tabPanel" data-sikshya-panel="discussions">
                                <div class="sikshya-contentPanel sikshya-contentPanel--plain">
                                    <h3 class="sikshya-learnH3"><?php esc_html_e('Discussions', 'sikshya'); ?></h3>
                                    <p class="sikshya-zeroMargin"><?php esc_html_e('Discussions are enabled for this course, but the discussion UI is not available yet.', 'sikshya'); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($page_model->isCourseFeatureReviews()) : ?>
                            <div class="sikshya-tabPanel" data-sikshya-panel="reviews">
                                <div class="sikshya-contentPanel sikshya-contentPanel--plain">
                                    <h3 class="sikshya-learnH3"><?php esc_html_e('Reviews', 'sikshya'); ?></h3>
                                    <p class="sikshya-zeroMargin"><?php esc_html_e('Reviews are enabled for this course, but the reviews UI is not available yet.', 'sikshya'); ?></p>
                                </div>
                            </div>
            <?php endif; ?>
        </div>

        <script>
        (function(){
          // Notes (REST): private per-user note for this content.
          const cfg = window.sikshyaQuizTaker || {};
          const noteTa = document.querySelector('[data-sikshya-note]');
          const noteSave = document.querySelector('[data-sikshya-note-save]');
          const noteStatus = document.querySelector('[data-sikshya-note-status]');
          if (!noteTa || !noteSave || !cfg.restUrl || !cfg.restNonce || !cfg.quizId) return;
          const courseId = <?php echo wp_json_encode((int) $page_model->getCourseId(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
          const contentId = parseInt(cfg.quizId, 10) || 0;
          let loaded = false;
          async function loadNote(){
            if (loaded) return;
            loaded = true;
            try{
              const url = new URL(String(cfg.restUrl).replace(/\/?$/, '/') + 'me/content-note', window.location.href);
              url.searchParams.set('course_id', String(courseId));
              url.searchParams.set('content_id', String(contentId));
              const res = await fetch(url.toString(), { method:'GET', headers:{ 'X-WP-Nonce': cfg.restNonce }, credentials:'same-origin' });
              const json = await res.json().catch(() => null);
              if (res.ok && json && json.ok && json.data && typeof json.data.note === 'string') {
                noteTa.value = json.data.note;
              }
            }catch(e){ console.error(e); }
          }
          document.addEventListener('click', (e) => {
            const t = e.target;
            if (!(t instanceof Element)) return;
            if (t.matches('[data-sikshya-tab="notes"]')) void loadNote();
          });
          noteSave.addEventListener('click', async () => {
            noteSave.setAttribute('disabled','disabled');
            if (noteStatus) noteStatus.textContent = 'Saving…';
            try{
              const res = await fetch(String(cfg.restUrl).replace(/\/?$/, '/') + 'me/content-note', {
                method:'POST',
                credentials:'same-origin',
                headers:{ 'Content-Type':'application/json', 'X-WP-Nonce': cfg.restNonce },
                body: JSON.stringify({ course_id: Number(courseId), content_id: Number(contentId), note: String(noteTa.value || '') }),
              });
              const json = await res.json().catch(() => null);
              if (!res.ok || !json || json.ok !== true) throw new Error((json && (json.message || json.data?.message)) || 'Failed');
              if (noteStatus) noteStatus.textContent = 'Saved.';
              window.setTimeout(() => { if (noteStatus) noteStatus.textContent = ''; }, 1600);
            }catch(e){
              if (noteStatus) noteStatus.textContent = 'Could not save.';
              console.error(e);
            }finally{
              noteSave.removeAttribute('disabled');
            }
          });
        })();
        </script>

                    <?php
                    // Sticky Prev/Next: derive from curriculum blocks.
                    $flat = [];
                    foreach ($page_model->getBlocks() as $block) {
                        foreach ((array) ($block['items'] ?? []) as $it) {
                            if (!is_array($it)) {
                                continue;
                            }
                            $flat[] = $it;
                        }
                    }
                    $current_index = -1;
                    foreach ($flat as $i => $it) {
                        if (!empty($it['current'])) {
                            $current_index = (int) $i;
                            break;
                        }
                    }
                    $prev = ($current_index > 0) ? ($flat[$current_index - 1] ?? null) : null;
                    $next = ($current_index >= 0) ? ($flat[$current_index + 1] ?? null) : null;
                    $prev_url = is_array($prev) ? (string) ($prev['permalink'] ?? '') : '';
                    $next_url = is_array($next) ? (string) ($next['permalink'] ?? '') : '';
                    $prev_title = is_array($prev) ? (string) ($prev['title'] ?? '') : '';
                    $next_title = is_array($next) ? (string) ($next['title'] ?? '') : '';
                    ?>
                    <nav class="sikshya-learnDock" aria-label="<?php esc_attr_e('Quiz navigation', 'sikshya'); ?>">
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
  function setOpen(isOpen) {
    root.classList.toggle(openClass, isOpen);
    if (overlay) overlay.hidden = !isOpen;
  }
  const collapsedClass = 'sikshya-sidebarCollapsed';
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

  // When the top bar scrolls away, remove the sidebar's reserved gap.
  const topbar = document.querySelector('.sikshya-learnTopbar');
  if (topbar && 'IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      const e = entries[0];
      root.style.setProperty('--sikshya-learn-topbar-visible', e && e.isIntersecting ? '1' : '0');
    }, { threshold: [0] });
    io.observe(topbar);
  }

  const tabs = document.querySelectorAll('[data-sikshya-tab]');
  const panels = document.querySelectorAll('[data-sikshya-panel]');
  tabs.forEach((btn) => {
    btn.addEventListener('click', () => {
      const target = btn.getAttribute('data-sikshya-tab');
      tabs.forEach((b) => b.classList.toggle('is-active', b === btn));
      panels.forEach((p) => p.classList.toggle('is-active', p.getAttribute('data-sikshya-panel') === target));
    });
  });

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

  // Quiz start: reveal the form, hide the intro landing, focus first question.
  const startBtns = document.querySelectorAll('[data-sikshya-quiz-start]');
  const quizForm = document.querySelector('[data-sikshya-quiz-form]');
  const quizIntro = document.querySelector('[data-sikshya-quiz-intro]');
  if (quizForm && startBtns.length) {
    startBtns.forEach((startBtn) => {
      startBtn.addEventListener('click', () => {
        if (startBtn.hasAttribute('disabled') || startBtn.getAttribute('aria-disabled') === 'true') {
          return;
        }
        quizForm.hidden = false;
        if (quizIntro) {
          quizIntro.setAttribute('hidden', '');
        }
        startBtns.forEach((b) => {
          b.setAttribute('hidden', '');
          b.setAttribute('aria-expanded', 'true');
        });
        const first = quizForm.querySelector('input, textarea, select, button');
        if (first && first.focus) {
          first.focus({ preventScroll: true });
        }
        quizForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
  }
})();
</script>
    <?php
}
