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
	<link rel="stylesheet" href="<?php echo esc_url($ds_href); ?>">
	<link rel="stylesheet" href="<?php echo esc_url($learn_href); ?>">
</head>
<body class="sikshya-learning-shell sikshya-learning-shell--quiz">
<a class="sikshya-skipLink" href="#sikshya-learn-content"><?php esc_html_e('Skip to quiz content', 'sikshya'); ?></a>
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
                        <?php echo sikshya_learn_icon('exit-learn'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
                              'previous' => __('Previous', 'sikshya'),
                              'next' => __('Next', 'sikshya'),
                          ],
                      ],
                      JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                  );
                  ?>;
                </script>
				<script src="<?php echo esc_url($quiz_js); ?>" defer></script>
            <?php endif; ?>

        <main class="sikshya-learnMain">
            <div class="sikshya-learnOverlay" data-sikshya-outline-overlay hidden></div>
            <aside class="sikshya-learnSidebar" aria-label="<?php echo esc_attr(sprintf(__('%s content', 'sikshya'), $label_course)); ?>" data-sikshya-outline<?php echo $page_model->isLearnCurriculumSidebarScrollable() ? ' data-sik-curriculum-scroll="1"' : ''; ?>>
                <div class="sikshya-learnSidebar__inner">
                    <div class="sikshya-learnSidebar__head">
                        <?php
                        $sidebar_course_post = $page_model->getCourse();
                        $sidebar_course_title = $sidebar_course_post instanceof WP_Post ? (string) get_the_title($sidebar_course_post) : '';
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
                        $outline_blocks = $page_model->getBlocks();
                        $outline_show_progress = $page_model->getShowProgress();
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
                     * hook fires before the quiz body where $legacy is
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
                                        <?php
                                        $lock_message = $attempts_message !== ''
                                            ? $attempts_message
                                            : __('You have reached the maximum number of attempts for this quiz.', 'sikshya');
                                        ?>
                                        <div class="sikshya-quizLockNotice" role="alert">
                                            <p class="sikshya-quizLockNotice__title"><?php esc_html_e('This quiz is currently locked', 'sikshya'); ?></p>
                                            <p class="sikshya-quizLockNotice__desc"><?php echo esc_html($lock_message); ?></p>
                                        </div>
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
                                        $submit_label = $attempts_exhausted ? __('No attempts remaining', 'sikshya') : __('Submit quiz', 'sikshya');
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
                            <button type="button" id="sikshya-quiz-tab-overview" role="tab" aria-controls="sikshya-quiz-panel-overview" aria-selected="true" class="sikshya-tabBtn is-active" data-sikshya-tab="overview"><?php esc_html_e('Overview', 'sikshya'); ?></button>
                            <button type="button" id="sikshya-quiz-tab-resources" role="tab" aria-controls="sikshya-quiz-panel-resources" aria-selected="false" tabindex="-1" class="sikshya-tabBtn" data-sikshya-tab="resources"><?php esc_html_e('Resources', 'sikshya'); ?></button>
                            <button type="button" id="sikshya-quiz-tab-instructions" role="tab" aria-controls="sikshya-quiz-panel-instructions" aria-selected="false" tabindex="-1" class="sikshya-tabBtn" data-sikshya-tab="instructions"><?php esc_html_e('Instructions', 'sikshya'); ?></button>
                            <button type="button" id="sikshya-quiz-tab-notes" role="tab" aria-controls="sikshya-quiz-panel-notes" aria-selected="false" tabindex="-1" class="sikshya-tabBtn" data-sikshya-tab="notes"><?php esc_html_e('Notes', 'sikshya'); ?></button>
                            <button type="button" id="sikshya-quiz-tab-announcements" role="tab" aria-controls="sikshya-quiz-panel-announcements" aria-selected="false" tabindex="-1" class="sikshya-tabBtn" data-sikshya-tab="announcements"><?php esc_html_e('Announcements', 'sikshya'); ?></button>
                            <?php
                            /**
                             * Extra tab buttons in the learner content chrome.
                             * Passed values: legacy view array + `$page_model`.
                             */
                            do_action('sikshya_learn_tabs_bar_append', $page_model->toLegacyViewArray(), $page_model);
                            ?>
                            <?php if ($page_model->isCourseFeatureReviews()) : ?>
                                <button type="button" id="sikshya-quiz-tab-reviews" role="tab" aria-controls="sikshya-quiz-panel-reviews" aria-selected="false" tabindex="-1" class="sikshya-tabBtn" data-sikshya-tab="reviews"><?php esc_html_e('Reviews', 'sikshya'); ?></button>
                            <?php endif; ?>
                        </div>
                        <div class="sikshya-tabPanel is-active" data-sikshya-panel="overview" id="sikshya-quiz-panel-overview" role="tabpanel" aria-labelledby="sikshya-quiz-tab-overview">
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
                        <div class="sikshya-tabPanel" data-sikshya-panel="resources" id="sikshya-quiz-panel-resources" role="tabpanel" aria-labelledby="sikshya-quiz-tab-resources" hidden>
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
                                                // Signed proxy URL bound to the current learner — same
                                                // protection as the lesson template. Filterable.
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
                            </div>
                        </div>
                        <div class="sikshya-tabPanel" data-sikshya-panel="instructions" id="sikshya-quiz-panel-instructions" role="tabpanel" aria-labelledby="sikshya-quiz-tab-instructions" hidden>
                            <div class="sikshya-contentPanel sikshya-contentPanel--plain">
                                <h3 class="sikshya-learnH3"><?php esc_html_e('Instructions', 'sikshya'); ?></h3>
                                <ul class="sikshya-learnList">
                                    <li><?php esc_html_e('Answer all questions. You can change answers before submitting.', 'sikshya'); ?></li>
                                    <li><?php esc_html_e('Keep an eye on the timer. Your attempt auto-submits when time ends.', 'sikshya'); ?></li>
                                    <li><?php esc_html_e('You need the passing score to complete this section.', 'sikshya'); ?></li>
                                </ul>
                            </div>
                        </div>
                        <div class="sikshya-tabPanel" data-sikshya-panel="notes" id="sikshya-quiz-panel-notes" role="tabpanel" aria-labelledby="sikshya-quiz-tab-notes" hidden>
                            <div class="sikshya-contentPanel sikshya-contentPanel--plain sikshya-learnNotes" data-sikshya-notes-shell>
                                <h3 class="sikshya-learnH3"><?php esc_html_e('My notes', 'sikshya'); ?></h3>
                                <p class="sikshya-learnNotes__hint sikshya-muted"><?php esc_html_e('Private to you — add as many short notes as you need while taking this quiz.', 'sikshya'); ?></p>
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
                        <div class="sikshya-tabPanel" data-sikshya-panel="announcements" id="sikshya-quiz-panel-announcements" role="tabpanel" aria-labelledby="sikshya-quiz-tab-announcements" hidden>
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
                         * Extra tab panels (`data-sikshya-panel`).
                         * Passed values: legacy view array + `$page_model`.
                         */
                        do_action('sikshya_learn_tab_panels_append', $page_model->toLegacyViewArray(), $page_model);
                        ?>
                        <?php if ($page_model->isCourseFeatureReviews()) : ?>
                            <div class="sikshya-tabPanel" data-sikshya-panel="reviews" id="sikshya-quiz-panel-reviews" role="tabpanel" aria-labelledby="sikshya-quiz-tab-reviews" hidden>
                                <div class="sikshya-contentPanel sikshya-contentPanel--plain">
                                    <h3 class="sikshya-learnH3"><?php esc_html_e('Reviews', 'sikshya'); ?></h3>
                                    <p class="sikshya-zeroMargin"><?php esc_html_e('Reviews are enabled for this course, but the reviews UI is not available yet.', 'sikshya'); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
        </div>

                <?php endif; ?>
            </section>
            <?php if (!$page_model->hasError()) : ?>
        <script>
        (function(){
          const cfg = window.sikshyaQuizTaker || {};
          const notesShell = document.querySelector('[data-sikshya-notes-shell]');
          const notesList = document.querySelector('[data-sikshya-notes-list]');
          const notesEmpty = document.querySelector('[data-sikshya-notes-empty]');
          const noteNewTa = document.querySelector('[data-sikshya-note-new]');
          const noteAdd = document.querySelector('[data-sikshya-note-add]');
          const noteStatus = document.querySelector('[data-sikshya-note-status]');
          if (!notesShell || !notesList || !noteNewTa || !noteAdd || !cfg.restUrl || !cfg.restNonce || !cfg.quizId) return;
          const courseId = <?php echo wp_json_encode((int) $page_model->getCourseId(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
          const contentId = parseInt(cfg.quizId, 10) || 0;
          const msgs = <?php echo wp_json_encode([
              'saved' => __('Saved.', 'sikshya'),
              'added' => __('Note added.', 'sikshya'),
              'removed' => __('Note removed.', 'sikshya'),
              'saving' => __('Saving…', 'sikshya'),
              'failed' => __('Could not save. Try again.', 'sikshya'),
              'edit' => __('Edit', 'sikshya'),
              'save' => __('Save', 'sikshya'),
              'cancel' => __('Cancel', 'sikshya'),
              'delete' => __('Delete', 'sikshya'),
          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

          function setStatus(txt) {
            if (noteStatus) noteStatus.textContent = txt || '';
          }

          function noteUrl(noteId) {
            const u = new URL(String(cfg.restUrl).replace(/\/?$/, '/') + 'me/content-note', window.location.href);
            u.searchParams.set('course_id', String(courseId));
            u.searchParams.set('content_id', String(contentId));
            if (noteId) u.searchParams.set('note_id', noteId);
            return u.toString();
          }

          function formatWhen(iso) {
            try {
              const d = new Date(iso);
              return isNaN(d.getTime()) ? '' : d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
            } catch (_) {
              return '';
            }
          }

          function renderNotes(items) {
            notesList.innerHTML = '';
            const arr = Array.isArray(items) ? items.slice() : [];
            if (notesEmpty) notesEmpty.hidden = arr.length > 0;
            arr.forEach((n) => {
              const id = n && typeof n.id === 'string' ? n.id : '';
              const text = n && typeof n.text === 'string' ? n.text : '';
              if (!id || !text) return;
              const when = formatWhen(n.created_at || '');
              const li = document.createElement('li');
              li.className = 'sikshya-learnNotes__item';
              const card = document.createElement('div');
              card.className = 'sikshya-learnNotes__card';
              const meta = document.createElement('div');
              meta.className = 'sikshya-learnNotes__meta';
              const timeEl = document.createElement('time');
              timeEl.className = 'sikshya-learnNotes__time';
              timeEl.dateTime = n.created_at || '';
              timeEl.textContent = when || '';
              meta.appendChild(timeEl);
              const body = document.createElement('div');
              body.className = 'sikshya-learnNotes__body';
              body.textContent = text;
              const ta = document.createElement('textarea');
              ta.className = 'sikshya-learnNotes__edit sikshya-quizQ__textarea';
              ta.hidden = true;
              ta.rows = 4;
              ta.value = text;
              ta.setAttribute('aria-label', <?php echo wp_json_encode(__('Note text', 'sikshya'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>);
              const actions = document.createElement('div');
              actions.className = 'sikshya-learnNotes__actions';
              const editBtn = document.createElement('button');
              editBtn.type = 'button';
              editBtn.className = 'sikshya-btn sikshya-btn--outline sikshya-learnNotes__btn';
              editBtn.textContent = msgs.edit;
              const saveBtn = document.createElement('button');
              saveBtn.type = 'button';
              saveBtn.hidden = true;
              saveBtn.className = 'sikshya-btn sikshya-btn--outline sikshya-learnNotes__btn';
              saveBtn.textContent = msgs.save;
              const cancelBtn = document.createElement('button');
              cancelBtn.type = 'button';
              cancelBtn.hidden = true;
              cancelBtn.className = 'sikshya-learnNotes__btn sikshya-learnNotes__btn--muted';
              cancelBtn.textContent = msgs.cancel;
              const delBtn = document.createElement('button');
              delBtn.type = 'button';
              delBtn.className = 'sikshya-learnNotes__btn sikshya-learnNotes__btn--danger';
              delBtn.textContent = msgs.delete;
              actions.append(editBtn, saveBtn, cancelBtn, delBtn);
              card.append(meta, body, ta, actions);
              li.appendChild(card);
              notesList.appendChild(li);

              function setEditing(on) {
                body.hidden = on;
                ta.hidden = !on;
                editBtn.hidden = on;
                saveBtn.hidden = !on;
                cancelBtn.hidden = !on;
                if (!on) ta.value = body.textContent || '';
                else ta.focus();
              }
              editBtn.addEventListener('click', () => setEditing(true));
              cancelBtn.addEventListener('click', () => setEditing(false));
              saveBtn.addEventListener('click', async () => {
                saveBtn.disabled = true;
                setStatus(msgs.saving);
                try {
                  const res = await fetch(String(cfg.restUrl).replace(/\/?$/, '/') + 'me/content-note', {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.restNonce },
                    body: JSON.stringify({
                      course_id: Number(courseId),
                      content_id: Number(contentId),
                      note_id: id,
                      text: ta.value,
                    }),
                  });
                  const json = await res.json().catch(() => null);
                  if (!res.ok || !json || json.ok !== true) throw new Error('fail');
                  body.textContent = ta.value;
                  setEditing(false);
                  setStatus(msgs.saved);
                  window.setTimeout(() => setStatus(''), 1600);
                } catch (_) {
                  setStatus(msgs.failed);
                } finally {
                  saveBtn.disabled = false;
                }
              });
              delBtn.addEventListener('click', async () => {
                if (!window.confirm(<?php echo wp_json_encode(__('Delete this note?', 'sikshya'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>)) return;
                delBtn.disabled = true;
                setStatus(msgs.saving);
                try {
                  const res = await fetch(noteUrl(id), {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: { 'X-WP-Nonce': cfg.restNonce },
                  });
                  const json = await res.json().catch(() => null);
                  if (!res.ok || !json || json.ok !== true) throw new Error('fail');
                  li.remove();
                  const left = notesList.querySelectorAll('.sikshya-learnNotes__item').length;
                  if (notesEmpty) notesEmpty.hidden = left > 0;
                  setStatus(msgs.removed);
                  window.setTimeout(() => setStatus(''), 1600);
                } catch (_) {
                  setStatus(msgs.failed);
                } finally {
                  delBtn.disabled = false;
                }
              });
            });
          }

          async function loadNotes() {
            if (!courseId || !contentId) return;
            try {
              const res = await fetch(noteUrl(), { method:'GET', headers:{ 'X-WP-Nonce': cfg.restNonce }, credentials:'same-origin' });
              const json = await res.json().catch(() => null);
              if (res.ok && json && json.ok && json.data) {
                const items = Array.isArray(json.data.notes) ? json.data.notes : [];
                renderNotes(items.filter((it) => it && it.id && typeof it.text === 'string'));
              }
            } catch (e) {
              console.error(e);
            }
          }

          document.addEventListener('click', (e) => {
            const t = e.target;
            if (!(t instanceof Element)) return;
            if (t.matches('[data-sikshya-tab="notes"]')) void loadNotes();
          });

          noteAdd.addEventListener('click', async () => {
            const text = String(noteNewTa.value || '').trim();
            if (!text) return;
            noteAdd.disabled = true;
            setStatus(msgs.saving);
            try {
              const res = await fetch(String(cfg.restUrl).replace(/\/?$/, '/') + 'me/content-note', {
                method:'POST',
                credentials:'same-origin',
                headers:{ 'Content-Type':'application/json', 'X-WP-Nonce': cfg.restNonce },
                body: JSON.stringify({ course_id: Number(courseId), content_id: Number(contentId), text }),
              });
              const json = await res.json().catch(() => null);
              if (!res.ok || !json || json.ok !== true) throw new Error('fail');
              noteNewTa.value = '';
              await loadNotes();
              setStatus(msgs.added);
              window.setTimeout(() => setStatus(''), 1600);
            } catch (_) {
              setStatus(msgs.failed);
            } finally {
              noteAdd.disabled = false;
            }
          });
        })();
        </script>

                    <?php
                    // Prev/Next: derive from curriculum blocks.
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
                    <nav class="sikshya-learnContentNav" aria-label="<?php esc_attr_e('Quiz navigation', 'sikshya'); ?>">
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
                             * Center slot in the fixed quiz footer (e.g. Discussion / Q&A launcher from Pro add-ons).
                             *
                             * @param array<string, mixed>                              $legacy_vm  Back-compat view array.
                             * @param \Sikshya\Presentation\Models\SingleQuizPageModel $page_model
                             */
                            do_action('sikshya_learn_content_nav_center', $page_model->toLegacyViewArray(), $page_model);
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
            </div>
        </main>
    </div>
    <footer class="sikshya-learning-footer" aria-hidden="true"></footer>
</div>
<script>
/* Quiz-shell distraction-free hooks: Focus mode + scroll-aware topbar.
 * Mirrors the lesson-shell behavior (see assets/js/learn.js sections 9 & 11)
 * so Focus mode works identically across all content types. Inline because
 * the quiz shell already inlines its own client and doesn't load learn.js.
 * Guards: keystroke handler bails when typing inside form controls so quiz
 * text-answer typing stays unaffected.
 */
(function () {
  var root = document.documentElement;
  var FOCUS_KEY = 'sikshya:learn:focus';
  var focusBtn = document.querySelector('[data-sikshya-focus-toggle]');
  function setFocusMode(isOn, persist) {
    root.classList.toggle('sikshya-focusMode', !!isOn);
    if (focusBtn) focusBtn.setAttribute('aria-pressed', isOn ? 'true' : 'false');
    if (persist) {
      try {
        if (isOn) window.localStorage.setItem(FOCUS_KEY, '1');
        else window.localStorage.removeItem(FOCUS_KEY);
      } catch (_) { /* ignore */ }
    }
  }
  try {
    if (window.localStorage.getItem(FOCUS_KEY) === '1') setFocusMode(true, false);
  } catch (_) { /* ignore */ }
  if (focusBtn) {
    focusBtn.addEventListener('click', function () {
      setFocusMode(!root.classList.contains('sikshya-focusMode'), true);
    });
  }
  function isFormFocus() {
    var el = document.activeElement;
    if (!el || el === document.body) return false;
    var tag = (el.tagName || '').toLowerCase();
    if (tag === 'textarea' || tag === 'input' || tag === 'select') return true;
    return !!el.isContentEditable;
  }
  window.addEventListener('keydown', function (e) {
    if (e.altKey || e.ctrlKey || e.metaKey) return;
    if (isFormFocus()) return;
    var k = (e.key || '').toLowerCase();
    if (k === 'f') {
      setFocusMode(!root.classList.contains('sikshya-focusMode'), true);
      e.preventDefault();
    } else if (k === 'escape' && root.classList.contains('sikshya-focusMode')) {
      setFocusMode(false, true);
    }
  });

  /* Scroll-aware topbar (chrome auto-hide). */
  var lastY = 0, ticking = false;
  function apply() {
    var y = window.scrollY || document.documentElement.scrollTop || 0;
    var down = y > lastY;
    if (y < 200) root.classList.remove('sikshya-chromeHidden');
    else if (down && Math.abs(y - lastY) > 6) root.classList.add('sikshya-chromeHidden');
    else if (!down && Math.abs(y - lastY) > 6) root.classList.remove('sikshya-chromeHidden');
    lastY = y;
    ticking = false;
  }
  window.addEventListener('scroll', function () {
    if (ticking) return;
    ticking = true;
    window.requestAnimationFrame(apply);
  }, { passive: true });
})();
</script>
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

  const mainTabsSection = document.querySelector('.sikshya-learnContent > .sikshya-tabsSection');
  const tabs = mainTabsSection ? mainTabsSection.querySelectorAll('[data-sikshya-tab]') : [];
  const panels = mainTabsSection ? mainTabsSection.querySelectorAll('[data-sikshya-panel]') : [];
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

  // Quiz start UI + server attempt/timer: handled by quiz-taker.js (avoid duplicate click handlers).
})();
</script>
    <?php
}
