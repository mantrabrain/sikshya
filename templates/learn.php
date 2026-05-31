<?php
/**
 * Course curriculum / learn hub — view; data from {@see \Sikshya\Presentation\Models\LearnPageModel}.
 *
 * @package Sikshya
 */

use Sikshya\Frontend\Site\LearnTemplateData;
use Sikshya\Core\Plugin;

$page_model = LearnTemplateData::fromRequest();
$urls = $page_model->getUrls();

$label_course = function_exists('sikshya_label') ? sikshya_label('course', __('Course', 'sikshya'), 'frontend') : __('Course', 'sikshya');
$label_courses = function_exists('sikshya_label_plural') ? sikshya_label_plural('course', 'courses', __('Courses', 'sikshya'), 'frontend') : __('Courses', 'sikshya');

$plugin = Plugin::getInstance();
$sheet_ver = rawurlencode((string) $plugin->version);
$ds_href = esc_url($plugin->getAssetUrl('css/public-design-system.css')) . '?ver=' . $sheet_ver;
$learn_href = esc_url($plugin->getAssetUrl('css/learn.css')) . '?ver=' . $sheet_ver;

$page_title = sprintf(
    /* translators: 1: page name, 2: site name */
    '%1$s — %2$s',
    __('Learn', 'sikshya'),
    get_bloginfo('name')
);
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
     * Extension point for learn-shell <head> (no wp_head() — keeps the shell minimal).
     * Passed value: `$page_model`.
     */
    do_action('sikshya_learn_shell_head', $page_model);
    ?>
</head>
<?php
$learn_mode = $page_model->getMode();
$is_hub = $learn_mode === 'hub';
$is_bundle = $learn_mode === 'bundle';
$has_course = $page_model->getCourseId() > 0;
$is_shell_without_course = $is_hub || $is_bundle || !$has_course;
?>
<body class="sikshya-learning-shell sikshya-learning-shell--learn<?php echo $is_shell_without_course ? ' sikshya-learning-shell--hub' : ''; ?>">
<a class="sikshya-skipLink" href="#sikshya-learn-content"><?php esc_html_e('Skip to content', 'sikshya'); ?></a>
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
                $top_label = $page_model->getLearnTopbarLabel();
                $learn_topbar_label = $top_label !== '' ? $top_label : __('Learn', 'sikshya');
                ?>
                <span class="sikshya-learnTopbar__title" title="<?php echo esc_attr($learn_topbar_label); ?>">
                    <?php echo esc_html($learn_topbar_label); ?>
                </span>
            </div>
            <div class="sikshya-learnTopbar__right">
                <?php if ($urls->getAccountUrl() !== '') : ?>
                    <a class="sikshya-btn sikshya-btn--outline sikshya-btn--sm" href="<?php echo esc_url($urls->getAccountUrl()); ?>">
                        <?php esc_html_e('My account', 'sikshya'); ?>
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
                        $outline_blocks = $page_model->getCurriculumBlocks();
                        $outline_show_progress = $page_model->isShowProgress();
                        require __DIR__ . '/partials/learn-curriculum-outline.php';
                        ?>
                    </div>
                </div>
            </aside>

            <div class="sikshya-learnContentCol">
            <section id="sikshya-learn-content" class="sikshya-learnContent" aria-label="<?php esc_attr_e('Content', 'sikshya'); ?>">
                <?php if ($is_bundle && !$page_model->hasError()) : ?>
                    <?php
                    $bpc = $page_model->getBundleProgressCounts() ?? ['total' => 0, 'done' => 0, 'average' => 0];
                    $total_c = (int) $bpc['total'];
                    $done_c  = (int) $bpc['done'];
                    $avg_pct = (int) $bpc['average'];
                    $bundle_courses = $page_model->getHubOrBundleRows();
                    $bundle_url     = $page_model->getBundlePermalinkForActions();
                    $bundle_title   = $page_model->getBundleHeadlineTitle();
                    ?>
                    <div class="sikshya-contentSection">
                        <div class="sikshya-contentPanel sikshya-contentPanel--header">
                            <div class="sikshya-learnHeader">
                                <div class="sikshya-learnHeader__top">
                                    <div class="sikshya-learnHeader__titles">
                                        <div class="sikshya-learnHeader__kicker">
                                            <?php esc_html_e('Bundle', 'sikshya'); ?>
                                            <?php if ($total_c > 0) : ?>
                                                · <?php echo esc_html(sprintf(_n('%d course', '%d courses', $total_c, 'sikshya'), $total_c)); ?>
                                            <?php endif; ?>
                                        </div>
                                        <h1 class="sikshya-learnHeader__title sikshya-zeroMargin"><?php echo esc_html($bundle_title); ?></h1>
                                    </div>
                                    <div class="sikshya-learnHeader__actions">
                                        <?php if ($bundle_url !== '') : ?>
                                            <a class="sikshya-btn sikshya-btn--outline sikshya-btn--sm" href="<?php echo esc_url($bundle_url); ?>">
                                                <?php esc_html_e('Bundle page', 'sikshya'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($total_c > 0) : ?>
                                    <div class="sikshya-bundleLearnProgress" aria-label="<?php echo esc_attr__('Bundle progress', 'sikshya'); ?>">
                                        <div class="sikshya-learnHubCard__bar" style="margin-top:.75rem" role="progressbar" aria-valuenow="<?php echo esc_attr((string) $avg_pct); ?>" aria-valuemin="0" aria-valuemax="100">
                                            <span style="<?php echo esc_attr('width:' . $avg_pct . '%'); ?>"></span>
                                        </div>
                                        <span class="sikshya-learnHubCard__pct">
                                            <?php
                                            echo esc_html(sprintf(
                                                /* translators: 1: overall percent, 2: completed count, 3: total count */
                                                __('%1$s%% overall · %2$d / %3$d courses completed', 'sikshya'),
                                                $avg_pct,
                                                $done_c,
                                                $total_c
                                            ));
                                            ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($bundle_courses !== []) : ?>
                        <div class="sikshya-learnHubGrid" role="list">
                            <?php foreach ($bundle_courses as $brow) : ?>
                                <?php
                                $course     = $brow->getCoursePost();
                                if (!$course instanceof WP_Post) {
                                    continue;
                                }
                                $thumb     = $brow->getThumbUrl();
                                $progress  = $brow->getProgressPercent();
                                $continue  = $brow->getContinueUrl();
                                $course_url = $brow->getViewCourseUrl();
                                $enrolled  = $brow->isEnrolled();
                                ?>
                                <article class="sikshya-learnHubCard" role="listitem">
                                    <div class="sikshya-learnHubCard__media" aria-hidden="true">
                                        <?php if ($thumb !== '') : ?>
                                            <img class="sikshya-learnHubCard__img" src="<?php echo esc_url($thumb); ?>" alt="" loading="lazy" />
                                        <?php else : ?>
                                            <div class="sikshya-learnHubCard__ph"></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="sikshya-learnHubCard__body">
                                        <h2 class="sikshya-learnHubCard__title">
                                            <a href="<?php echo esc_url($course_url); ?>"><?php echo esc_html($brow->getTitle()); ?></a>
                                        </h2>
                                        <?php if ($enrolled) : ?>
                                            <div class="sikshya-learnHubCard__progress" aria-label="<?php echo esc_attr__('Progress', 'sikshya'); ?>">
                                                <div class="sikshya-learnHubCard__bar" role="progressbar" aria-valuenow="<?php echo esc_attr((string) $progress); ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <span style="<?php echo esc_attr('width:' . $progress . '%'); ?>"></span>
                                                </div>
                                                <span class="sikshya-learnHubCard__pct"><?php echo esc_html($progress . '%'); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="sikshya-learnHubCard__actions">
                                            <?php if ($enrolled && $continue !== '') : ?>
                                                <a class="sikshya-btn sikshya-btn--primary sikshya-btn--sm" href="<?php echo esc_url($continue); ?>">
                                                    <?php $progress >= 100 ? esc_html_e('Review', 'sikshya') : esc_html_e('Continue', 'sikshya'); ?>
                                                </a>
                                            <?php endif; ?>
                                            <a class="sikshya-btn sikshya-btn--outline sikshya-btn--sm" href="<?php echo esc_url($course_url); ?>">
                                                <?php echo esc_html(sprintf(__('View %s', 'sikshya'), strtolower($label_course))); ?>
                                            </a>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <div class="sikshya-contentSection sikshya-contentSection--centered">
                            <div class="sikshya-contentPanel sikshya-contentPanel--emptyState" role="status">
                                <div class="sikshya-learnEmptyState">
                                    <div class="sikshya-learnEmptyState__icon" aria-hidden="true">
                                        <?php echo sikshya_learn_icon('book'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    </div>
                                    <div class="sikshya-learnEmptyState__body">
                                        <h2 class="sikshya-learnEmptyState__title">
                                            <?php
                                            echo esc_html(sprintf(
                                                /* translators: %s: plural label (e.g. courses) */
                                                __('No %s in this bundle yet', 'sikshya'),
                                                strtolower($label_courses)
                                            ));
                                            ?>
                                        </h2>
                                        <p class="sikshya-learnEmptyState__message">
                                            <?php
                                            echo esc_html(sprintf(
                                                /* translators: %s: plural label (e.g. courses) */
                                                __('The instructor has not added any %s to this bundle yet.', 'sikshya'),
                                                strtolower($label_courses)
                                            ));
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php elseif ($page_model->getMode() === 'hub' && !$page_model->hasError()) : ?>
                    <div class="sikshya-contentSection">
                        <div class="sikshya-contentPanel sikshya-contentPanel--header">
                            <div class="sikshya-learnHeader">
                                <div class="sikshya-learnHeader__top">
                                    <div class="sikshya-learnHeader__titles">
                                        <div class="sikshya-learnHeader__kicker"><?php esc_html_e('Learn', 'sikshya'); ?></div>
                                        <h1 class="sikshya-learnHeader__title sikshya-zeroMargin"><?php esc_html_e('My learning', 'sikshya'); ?></h1>
                                    </div>
                                    <div class="sikshya-learnHeader__actions">
                                        <?php if ($urls->getCoursesArchiveUrl() !== '') : ?>
                                            <a class="sikshya-btn sikshya-btn--outline sikshya-btn--sm" href="<?php echo esc_url($urls->getCoursesArchiveUrl()); ?>">
                                                <?php echo esc_html(sprintf(__('Browse %s', 'sikshya'), strtolower($label_courses))); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php
                        $hub_courses = $page_model->getHubOrBundleRows();
                        if ($hub_courses !== []) : ?>
                            <div class="sikshya-learnHubGrid" role="list">
                                <?php foreach ($hub_courses as $brow) : ?>
                                    <?php
                                    $hcourse = $brow->getCoursePost();
                                    if (!$hcourse instanceof WP_Post) {
                                        continue;
                                    }
                                    $hthumb     = $brow->getThumbUrl();
                                    $hprogress  = $brow->getProgressPercent();
                                    $hcontinue  = $brow->getContinueUrl();
                                    $hcourse_url = $brow->getViewCourseUrl();
                                    ?>
                                    <article class="sikshya-learnHubCard" role="listitem">
                                        <div class="sikshya-learnHubCard__media" aria-hidden="true">
                                            <?php if ($hthumb !== '') : ?>
                                                <img class="sikshya-learnHubCard__img" src="<?php echo esc_url($hthumb); ?>" alt="" loading="lazy" />
                                            <?php else : ?>
                                                <div class="sikshya-learnHubCard__ph"></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="sikshya-learnHubCard__body">
                                            <h2 class="sikshya-learnHubCard__title">
                                                <a href="<?php echo esc_url($hcourse_url); ?>"><?php echo esc_html($brow->getTitle()); ?></a>
                                            </h2>
                                            <div class="sikshya-learnHubCard__progress" aria-label="<?php echo esc_attr__('Progress', 'sikshya'); ?>">
                                                <div class="sikshya-learnHubCard__bar" role="progressbar" aria-valuenow="<?php echo esc_attr((string) $hprogress); ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <span style="<?php echo esc_attr('width:' . $hprogress . '%'); ?>"></span>
                                                </div>
                                                <span class="sikshya-learnHubCard__pct"><?php echo esc_html($hprogress . '%'); ?></span>
                                            </div>
                                            <div class="sikshya-learnHubCard__actions">
                                                <?php if ($hcontinue !== '') : ?>
                                                    <a class="sikshya-btn sikshya-btn--primary sikshya-btn--sm" href="<?php echo esc_url($hcontinue); ?>"><?php esc_html_e('Continue', 'sikshya'); ?></a>
                                                <?php endif; ?>
                                                <a class="sikshya-btn sikshya-btn--outline sikshya-btn--sm" href="<?php echo esc_url($hcourse_url); ?>">
                                                    <?php echo esc_html(sprintf(__('View %s', 'sikshya'), strtolower($label_course))); ?>
                                                </a>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <div class="sikshya-contentSection sikshya-contentSection--centered">
                                <div class="sikshya-contentPanel sikshya-contentPanel--emptyState" role="status" aria-live="polite">
                                    <div class="sikshya-learnEmptyState">
                                        <div class="sikshya-learnEmptyState__icon" aria-hidden="true">
                                            <?php echo sikshya_learn_icon('book'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        </div>
                                        <div class="sikshya-learnEmptyState__body">
                                            <h2 class="sikshya-learnEmptyState__title">
                                                <?php
                                                echo esc_html(sprintf(
                                                    /* translators: %s: plural label (e.g. courses) */
                                                    __('No enrolled %s yet', 'sikshya'),
                                                    strtolower($label_courses)
                                                ));
                                                ?>
                                            </h2>
                                            <p class="sikshya-learnEmptyState__message">
                                                <?php
                                                echo esc_html(sprintf(
                                                    /* translators: %s: plural label (e.g. courses) */
                                                    __('You haven’t enrolled in any %s yet. Browse the catalog to find something to start today.', 'sikshya'),
                                                    strtolower($label_courses)
                                                ));
                                                ?>
                                            </p>
                                            <div class="sikshya-learnEmptyState__actions">
                                                <?php if ($urls->getCoursesArchiveUrl() !== '') : ?>
                                                    <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url($urls->getCoursesArchiveUrl()); ?>">
                                                        <?php echo esc_html(sprintf(__('Browse %s', 'sikshya'), strtolower($label_courses))); ?>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php
                            $recs = $page_model->getRecommendedCourses();
                            if ($recs !== []) : ?>
                                <div class="sikshya-learnHubSection" aria-label="<?php echo esc_attr(sprintf(__('Recommended %s', 'sikshya'), strtolower($label_courses))); ?>">
                                    <div class="sikshya-learnHubSection__head">
                                        <h2 class="sikshya-learnHubSection__title"><?php echo esc_html(sprintf(__('Recommended %s', 'sikshya'), strtolower($label_courses))); ?></h2>
                                        <?php if ($urls->getCoursesArchiveUrl() !== '') : ?>
                                            <a class="sikshya-btn sikshya-btn--outline sikshya-btn--sm" href="<?php echo esc_url($urls->getCoursesArchiveUrl()); ?>">
                                                <?php esc_html_e('View all', 'sikshya'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>

                                    <div class="sikshya-learnHubGrid" role="list">
                                        <?php foreach ($recs as $rec) : ?>
                                            <article class="sikshya-learnHubCard" role="listitem">
                                                <div class="sikshya-learnHubCard__media" aria-hidden="true">
                                                    <?php if ($rec->getThumbUrl() !== '') : ?>
                                                        <img class="sikshya-learnHubCard__img" src="<?php echo esc_url($rec->getThumbUrl()); ?>" alt="" loading="lazy" />
                                                    <?php else : ?>
                                                        <div class="sikshya-learnHubCard__ph"></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="sikshya-learnHubCard__body">
                                                    <h3 class="sikshya-learnHubCard__title">
                                                        <a href="<?php echo esc_url($rec->getCourseUrl()); ?>"><?php echo esc_html($rec->getTitle()); ?></a>
                                                    </h3>
                                                    <div class="sikshya-learnHubCard__actions">
                                                        <a class="sikshya-btn sikshya-btn--primary sikshya-btn--sm" href="<?php echo esc_url($rec->getCourseUrl()); ?>">
                                                            <?php echo esc_html(sprintf(__('View %s', 'sikshya'), strtolower($label_course))); ?>
                                                        </a>
                                                    </div>
                                                </div>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php elseif ($page_model->hasError()) : ?>
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
                                        $em = $page_model->getCourseModel();
                                        if ($em !== null) : ?>
                                            <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url($em->getPermalink()); ?>">
                                                <?php echo esc_html(sprintf(__('View %s', 'sikshya'), strtolower($label_course))); ?>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($urls->getLoginUrl() !== '') : ?>
                                            <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url($urls->getLoginUrl()); ?>">
                                                <?php esc_html_e('Log in', 'sikshya'); ?>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($urls->getCoursesArchiveUrl() !== '') : ?>
                                            <a class="sikshya-btn sikshya-btn--outline" href="<?php echo esc_url($urls->getCoursesArchiveUrl()); ?>">
                                                <?php echo esc_html(sprintf(__('Browse %s', 'sikshya'), strtolower($label_courses))); ?>
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
                    $cm = $page_model->getCourseModel();
                    $course_title = $cm ? $cm->getTitle() : $label_course;
                    $continue_url = $page_model->getContinueLearnUrlForHero();
                    ?>
                    <div class="sikshya-contentSection">
                        <div class="sikshya-contentPanel sikshya-contentPanel--header">
                            <div class="sikshya-learnHeader">
                                <div class="sikshya-learnHeader__top">
                                    <div class="sikshya-learnHeader__titles">
                                        <div class="sikshya-learnHeader__kicker"><?php echo esc_html($label_course); ?></div>
                                        <h1 class="sikshya-learnHeader__title sikshya-zeroMargin"><?php echo esc_html($course_title); ?></h1>
                                    </div>
                                    <div class="sikshya-learnHeader__actions">
                                        <a class="sikshya-btn sikshya-btn--primary sikshya-btn--sm" href="<?php echo esc_url($continue_url); ?>"><?php esc_html_e('Continue learning', 'sikshya'); ?></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="sikshya-contentSection">
                        <div class="sikshya-contentPanel">
                            <?php if ($cm) : ?>
                                <?php
                                $cm_content_html = $cm->getContentHtml();
                                $cm_excerpt_text = $cm->getExcerptText();
                                // Render the manual excerpt as a lead paragraph ONLY when
                                // it was explicitly set and differs from the post content;
                                // otherwise we duplicate auto-generated excerpts on top of
                                // the body and show the same paragraph twice.
                                $cm_post = $cm->getPost();
                                $has_manual_excerpt = $cm_post instanceof \WP_Post
                                    && trim((string) $cm_post->post_excerpt) !== '';
                                if ($has_manual_excerpt && $cm_excerpt_text !== '') :
                                    ?>
                                    <p class="sikshya-zeroMargin sikshya-learnCoursesumLead"><?php echo esc_html($cm_excerpt_text); ?></p>
                                <?php endif; ?>

                                <?php if (trim(wp_strip_all_tags($cm_content_html)) !== '') : ?>
                                    <?php echo wp_kses_post($cm_content_html); ?>
                                <?php elseif (!$has_manual_excerpt) : ?>
                                    <p class="sikshya-zeroMargin sikshya-learnCoursesumEmpty">
                                        <?php esc_html_e('No description has been added for this course yet.', 'sikshya'); ?>
                                    </p>
                                <?php endif; ?>
                            <?php else : ?>
                                <p class="sikshya-zeroMargin">
                                    <?php
                                    echo esc_html(sprintf(
                                        /* translators: %s: singular label (e.g. course) */
                                        __('%s not found.', 'sikshya'),
                                        $label_course
                                    ));
                                    ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php
                /**
                 * Addon hook: contextual notices that augment the current
                 * lesson / course view (Calendar "Upcoming in this course"
                 * teaser, drip lock / prerequisite notices, upsell banners,
                 * …). Fires AFTER the primary content header so the course's
                 * own title + "Continue learning" CTA always sit at the top
                 * of the column where the learner expects them.
                 *
                 * Passed: legacy view array + LearnPageModel (newer addons).
                 */
                do_action('sikshya_learn_after_hero', $page_model->toLegacyViewArray(), $page_model);
                ?>
            </section>
            <?php
            /**
             * Account-flavoured shortcuts (CSV exports, activity log,
             * certificate downloads) intentionally do NOT render on the learn
             * shell — they belong on the learner account page so the learn
             * page stays focused. To re-enable rendering at this position
             * for a custom build, set the filter below to true (passed the
             * LearnPageModel for context).
             *
             * @since 1.x
             * @param bool                                  $show
             * @param \Sikshya\Presentation\Models\LearnPageModel $page_model
             */
            if (apply_filters('sikshya_learn_show_content_extras', false, $page_model)) {
                echo '<div class="sikshya-learnContentExtras" data-sikshya-content-extras>';
                do_action('sikshya_learn_content_footer', $page_model->toLegacyViewArray(), $page_model);
                do_action('sikshya_learn_sidebar_footer', $page_model->toLegacyViewArray(), $page_model);
                echo '</div>';
            }
            ?>
            </div>
        </main>
    </div>
<footer class="sikshya-learning-footer" aria-hidden="true"></footer>
</div>
</body>
</html>

<script>
(() => {
  // Drawer (mobile)
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

})();
</script>
