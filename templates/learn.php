<?php
/**
 * Course curriculum / learn hub — view; data from {@see \Sikshya\Frontend\Public\LearnTemplateData}.
 *
 * @package Sikshya
 */

use Sikshya\Frontend\Public\LearnTemplateData;
use Sikshya\Core\Plugin;

$lv = LearnTemplateData::fromRequest();

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
</head>
<?php
$learn_mode = (string) ($lv['mode'] ?? 'course');
$is_hub = $learn_mode === 'hub';
$is_bundle = $learn_mode === 'bundle';
$has_course = !empty($lv['course_id']);
$is_shell_without_course = $is_hub || $is_bundle || !$has_course;
?>
<body class="sikshya-learning-shell sikshya-learning-shell--learn<?php echo $is_shell_without_course ? ' sikshya-learning-shell--hub' : ''; ?>">
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
                $learn_topbar_label = !empty($lv['course']) && $lv['course'] instanceof WP_Post
                    ? get_the_title($lv['course'])
                    : __('Learn', 'sikshya');
                ?>
                <span class="sikshya-learnTopbar__title" title="<?php echo esc_attr($learn_topbar_label); ?>">
                    <?php echo esc_html($learn_topbar_label); ?>
                </span>
            </div>
            <div class="sikshya-learnTopbar__right">
                <?php if (!empty($lv['urls']['account'])) : ?>
                    <a class="sikshya-btn sikshya-btn--outline sikshya-btn--sm" href="<?php echo esc_url($lv['urls']['account']); ?>">
                        <?php echo sikshya_learn_icon('x'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php esc_html_e('Exit', 'sikshya'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <main class="sikshya-learnMain">
            <?php
            /**
             * Fires once at the top of the learn page below the header.
             *
             * Pro addons (prerequisites, content drip) render lock banners and
             * scheduled-access previews here.
             *
             * @param array<string, mixed> $lv Learn template view-model.
             */
            do_action('sikshya_learn_after_hero', $lv);
            ?>
            <div class="sikshya-learnOverlay" data-sikshya-outline-overlay hidden></div>
            <aside class="sikshya-learnSidebar" aria-label="<?php esc_attr_e('Course content', 'sikshya'); ?>" data-sikshya-outline>
                <div class="sikshya-learnSidebar__inner">
                    <div class="sikshya-learnSidebar__head">
                        <h2 class="sikshya-learnSidebar__heading"><?php esc_html_e('Course content', 'sikshya'); ?></h2>
                    </div>
                    <div class="sikshya-learnSidebar__scroll">
                        <?php
                        $outline_blocks = $lv['blocks'];
                        $outline_show_progress = !empty($lv['show_progress']);
                        require __DIR__ . '/partials/learn-curriculum-outline.php';
                        ?>
                    </div>
                </div>
            </aside>

            <section class="sikshya-learnContent" aria-label="<?php esc_attr_e('Content', 'sikshya'); ?>">
                <?php if ($is_bundle && empty($lv['error'])) : ?>
                    <?php
                    $bundle_post   = ($has_course && !empty($lv['course'])) ? $lv['course'] : get_post((int) ($lv['course_id'] ?? 0));
                    $bundle_title  = $bundle_post instanceof WP_Post ? get_the_title($bundle_post) : __('Bundle', 'sikshya');
                    $bundle_courses = $lv['hub_courses'] ?? [];
                    $bundle_url    = $bundle_post instanceof WP_Post ? (get_permalink($bundle_post) ?: '') : '';
                    $total_c = count($bundle_courses);
                    $done_c  = count(array_filter($bundle_courses, static fn($r) => ($r['progress'] ?? 0) >= 100));
                    $avg_pct = $total_c > 0
                        ? (int) round(array_sum(array_column($bundle_courses, 'progress')) / $total_c)
                        : 0;
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

                    <?php if (!empty($bundle_courses)) : ?>
                        <div class="sikshya-learnHubGrid" role="list">
                            <?php foreach ($bundle_courses as $row) : ?>
                                <?php
                                $course    = $row['course'] ?? null;
                                if (!$course instanceof WP_Post) { continue; }
                                $thumb     = (string) ($row['thumb'] ?? '');
                                $progress  = (int) ($row['progress'] ?? 0);
                                $continue  = (string) ($row['continue_url'] ?? '');
                                $course_url = (string) ($row['course_url'] ?? '');
                                $enrolled  = !empty($row['enrolled']);
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
                                            <a href="<?php echo esc_url($course_url); ?>"><?php echo esc_html(get_the_title($course)); ?></a>
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
                                                <?php esc_html_e('View course', 'sikshya'); ?>
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
                                        <h2 class="sikshya-learnEmptyState__title"><?php esc_html_e('No courses in this bundle yet', 'sikshya'); ?></h2>
                                        <p class="sikshya-learnEmptyState__message"><?php esc_html_e('The instructor has not added any courses to this bundle yet.', 'sikshya'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php elseif (($lv['mode'] ?? 'course') === 'hub' && empty($lv['error'])) : ?>
                    <div class="sikshya-contentSection">
                        <div class="sikshya-contentPanel sikshya-contentPanel--header">
                            <div class="sikshya-learnHeader">
                                <div class="sikshya-learnHeader__top">
                                    <div class="sikshya-learnHeader__titles">
                                        <div class="sikshya-learnHeader__kicker"><?php esc_html_e('Learn', 'sikshya'); ?></div>
                                        <h1 class="sikshya-learnHeader__title sikshya-zeroMargin"><?php esc_html_e('My learning', 'sikshya'); ?></h1>
                                    </div>
                                    <div class="sikshya-learnHeader__actions">
                                        <?php if (!empty($lv['urls']['courses_archive'])) : ?>
                                            <a class="sikshya-btn sikshya-btn--outline sikshya-btn--sm" href="<?php echo esc_url($lv['urls']['courses_archive']); ?>">
                                                <?php esc_html_e('Browse courses', 'sikshya'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($lv['hub_courses']) && is_array($lv['hub_courses'])) : ?>
                            <div class="sikshya-learnHubGrid" role="list">
                                <?php foreach ($lv['hub_courses'] as $row) : ?>
                                    <?php
                                    $course = $row['course'] ?? null;
                                    if (!$course instanceof WP_Post) {
                                        continue;
                                    }
                                    $thumb = (string) ($row['thumb'] ?? '');
                                    $progress = (int) ($row['progress'] ?? 0);
                                    $continue = (string) ($row['continue_url'] ?? '');
                                    $course_url = (string) ($row['course_url'] ?? '');
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
                                                <a href="<?php echo esc_url($course_url); ?>"><?php echo esc_html(get_the_title($course)); ?></a>
                                            </h2>
                                            <div class="sikshya-learnHubCard__progress" aria-label="<?php echo esc_attr__('Progress', 'sikshya'); ?>">
                                                <div class="sikshya-learnHubCard__bar" role="progressbar" aria-valuenow="<?php echo esc_attr((string) $progress); ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <span style="<?php echo esc_attr('width:' . $progress . '%'); ?>"></span>
                                                </div>
                                                <span class="sikshya-learnHubCard__pct"><?php echo esc_html($progress . '%'); ?></span>
                                            </div>
                                            <div class="sikshya-learnHubCard__actions">
                                                <?php if ($continue !== '') : ?>
                                                    <a class="sikshya-btn sikshya-btn--primary sikshya-btn--sm" href="<?php echo esc_url($continue); ?>"><?php esc_html_e('Continue', 'sikshya'); ?></a>
                                                <?php endif; ?>
                                                <a class="sikshya-btn sikshya-btn--outline sikshya-btn--sm" href="<?php echo esc_url($course_url); ?>"><?php esc_html_e('View course', 'sikshya'); ?></a>
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
                                            <h2 class="sikshya-learnEmptyState__title"><?php esc_html_e('No enrolled courses yet', 'sikshya'); ?></h2>
                                            <p class="sikshya-learnEmptyState__message"><?php esc_html_e('You haven’t enrolled in any courses yet. Browse the catalog to find something to start today.', 'sikshya'); ?></p>
                                            <div class="sikshya-learnEmptyState__actions">
                                                <?php if (!empty($lv['urls']['courses_archive'])) : ?>
                                                    <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url($lv['urls']['courses_archive']); ?>"><?php esc_html_e('Browse courses', 'sikshya'); ?></a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($lv['hub_recommended']) && is_array($lv['hub_recommended'])) : ?>
                                <div class="sikshya-learnHubSection" aria-label="<?php echo esc_attr__('Recommended courses', 'sikshya'); ?>">
                                    <div class="sikshya-learnHubSection__head">
                                        <h2 class="sikshya-learnHubSection__title"><?php esc_html_e('Recommended courses', 'sikshya'); ?></h2>
                                        <?php if (!empty($lv['urls']['courses_archive'])) : ?>
                                            <a class="sikshya-btn sikshya-btn--outline sikshya-btn--sm" href="<?php echo esc_url($lv['urls']['courses_archive']); ?>">
                                                <?php esc_html_e('View all', 'sikshya'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>

                                    <div class="sikshya-learnHubGrid" role="list">
                                        <?php foreach ($lv['hub_recommended'] as $row) : ?>
                                            <?php
                                            $course = $row['course'] ?? null;
                                            if (!$course instanceof WP_Post) {
                                                continue;
                                            }
                                            $thumb = (string) ($row['thumb'] ?? '');
                                            $course_url = (string) ($row['course_url'] ?? '');
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
                                                    <h3 class="sikshya-learnHubCard__title">
                                                        <a href="<?php echo esc_url($course_url); ?>"><?php echo esc_html(get_the_title($course)); ?></a>
                                                    </h3>
                                                    <div class="sikshya-learnHubCard__actions">
                                                        <a class="sikshya-btn sikshya-btn--primary sikshya-btn--sm" href="<?php echo esc_url($course_url); ?>"><?php esc_html_e('View course', 'sikshya'); ?></a>
                                                    </div>
                                                </div>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php elseif ($lv['error'] !== '') : ?>
                    <div class="sikshya-contentSection sikshya-contentSection--centered">
                        <div class="sikshya-contentPanel sikshya-contentPanel--emptyState" role="alert" aria-live="polite">
                            <div class="sikshya-learnEmptyState">
                                <div class="sikshya-learnEmptyState__icon" aria-hidden="true">
                                    <?php echo sikshya_learn_icon('lock'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </div>
                                <div class="sikshya-learnEmptyState__body">
                                    <h2 class="sikshya-learnEmptyState__title"><?php esc_html_e('Access required', 'sikshya'); ?></h2>
                                    <p class="sikshya-learnEmptyState__message"><?php echo esc_html($lv['error']); ?></p>
                                    <div class="sikshya-learnEmptyState__actions">
                                        <?php if (!empty($lv['course']) && $lv['course'] instanceof WP_Post) : ?>
                                            <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url(get_permalink($lv['course'])); ?>">
                                                <?php esc_html_e('View course', 'sikshya'); ?>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($lv['urls']['login'])) : ?>
                                            <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url($lv['urls']['login']); ?>">
                                                <?php esc_html_e('Log in', 'sikshya'); ?>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($lv['urls']['courses_archive'])) : ?>
                                            <a class="sikshya-btn sikshya-btn--outline" href="<?php echo esc_url($lv['urls']['courses_archive']); ?>">
                                                <?php esc_html_e('Browse courses', 'sikshya'); ?>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($lv['urls']['account'])) : ?>
                                            <a class="sikshya-btn sikshya-btn--outline" href="<?php echo esc_url($lv['urls']['account']); ?>">
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
                    $course = (!empty($lv['course']) && $lv['course'] instanceof WP_Post) ? $lv['course'] : null;
                    $course_title = $course ? get_the_title($course) : __('Course', 'sikshya');
                    $continue_url = !empty($lv['urls']['learn']) ? (string) $lv['urls']['learn'] : '#';
                    ?>
                    <div class="sikshya-contentSection">
                        <div class="sikshya-contentPanel sikshya-contentPanel--header">
                            <div class="sikshya-learnHeader">
                                <div class="sikshya-learnHeader__top">
                                    <div class="sikshya-learnHeader__titles">
                                        <div class="sikshya-learnHeader__kicker"><?php esc_html_e('Course', 'sikshya'); ?></div>
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
                            <?php if ($course) : ?>
                                <?php
                                $excerpt = trim((string) get_the_excerpt($course));
                                if ($excerpt !== '') :
                                    ?>
                                    <p class="sikshya-zeroMargin"><?php echo esc_html($excerpt); ?></p>
                                <?php endif; ?>

                                <?php echo wp_kses_post(apply_filters('the_content', (string) $course->post_content)); ?>
                            <?php else : ?>
                                <p class="sikshya-zeroMargin"><?php esc_html_e('Course not found.', 'sikshya'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
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
