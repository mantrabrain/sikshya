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
<body class="sikshya-learning-shell sikshya-learning-shell--learn">
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
            <div class="sikshya-learnOverlay" data-sikshya-outline-overlay hidden></div>
            <aside class="sikshya-learnSidebar" aria-label="<?php esc_attr_e('Course content', 'sikshya'); ?>" data-sikshya-outline>
                <div class="sikshya-learnSidebar__inner">
                    <div class="sikshya-learnSidebar__head">
                        <h2 class="sikshya-learnSidebar__heading"><?php esc_html_e('Course content', 'sikshya'); ?></h2>
                    </div>
                    <div class="sikshya-learnSidebar__scroll">
                        <?php
                        $outline_blocks = $lv['blocks'];
                        if (!empty($_GET['mock_long'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                            $mock_chapters = [];
                            $k = 0;
                            for ($c = 1; $c <= 5; $c++) {
                                $items = [];
                                for ($i = 1; $i <= 6; $i++) {
                                    ++$k;
                                    $type_key = ($k % 6 === 0) ? 'quiz' : (($k % 10 === 0) ? 'assignment' : 'lesson');
                                    $lesson_type = ($type_key === 'lesson' && ($k % 5 === 0)) ? 'audio' : (($type_key === 'lesson' && ($k % 3 === 0)) ? 'video' : 'text');
                                    $is_current = $k === 8;
                                    $items[] = [
                                        'permalink' => '#',
                                        'title' => sprintf('Curriculum item %d — long title to validate sidebar layout, icons, and scrolling behavior', $k),
                                        'type_key' => $type_key,
                                        'lesson_type' => $lesson_type,
                                        'meta_line' => $type_key === 'quiz' ? '10 questions · 15 min limit · Pass 70%' : ($type_key === 'assignment' ? 'File upload · 10 pts' : ucfirst($lesson_type) . ' · 12 min'),
                                        'subtitle_compact' => $type_key === 'quiz' ? '15min' : ($type_key === 'assignment' ? '10 pts' : '12min'),
                                        'index_in_section' => $i,
                                        'completed' => ($k % 4 === 0),
                                        'current' => $is_current,
                                    ];
                                }
                                $mock_chapters[] = [
                                    'chapter' => (object) [
                                        'post_title' => sprintf('Chapter %d — Curriculum section with enough items to test long sessions (long chapter title)', $c),
                                    ],
                                    'items' => $items,
                                    'item_count' => count($items),
                                    'completed_in_section' => (int) floor(count($items) / 3),
                                    'section_duration_minutes' => 75,
                                    'open' => true,
                                ];
                            }
                            $outline_blocks = $mock_chapters;
                        }
                        require __DIR__ . '/partials/learn-curriculum-outline.php';
                        ?>
                    </div>
                </div>
            </aside>

            <section class="sikshya-learnContent" aria-label="<?php esc_attr_e('Content', 'sikshya'); ?>">
                <?php if ($lv['error'] !== '') : ?>
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
                    $mock = [
                        'title' => !empty($lv['course']) && $lv['course'] instanceof WP_Post ? get_the_title($lv['course']) : __('Introduction to Web Development', 'sikshya'),
                        'instructor' => __('Sikshya Academy', 'sikshya'),
                        'rating' => '4.7',
                        'learners' => '12,480',
                        'updated' => __('Updated Mar 2026', 'sikshya'),
                        'hours' => '8',
                        'level' => __('Beginner', 'sikshya'),
                        'progress' => 32,
                    ];
                    ?>
                    <div class="sikshya-contentSection">
                        <div class="sikshya-contentPanel">
                            <div class="sikshya-learnHeader">
                                <div class="sikshya-learnHeader__top">
                                    <div class="sikshya-learnHeader__titles">
                                        <div class="sikshya-learnHeader__kicker"><?php esc_html_e('Course', 'sikshya'); ?></div>
                                        <h1 class="sikshya-learnHeader__title sikshya-zeroMargin"><?php echo esc_html($mock['title']); ?></h1>
                                    </div>
                                    <div class="sikshya-learnHeader__actions">
                                        <a class="sikshya-btn sikshya-btn--primary sikshya-btn--sm" href="#"><?php esc_html_e('Continue', 'sikshya'); ?></a>
                                    </div>
                                </div>
                                <div class="sikshya-learnMetaRow">
                                    <span class="sikshya-pill"><?php echo esc_html(sprintf(__('⭐ %s rating', 'sikshya'), $mock['rating'])); ?></span>
                                    <span class="sikshya-pill"><?php echo esc_html(sprintf(__('%s learners', 'sikshya'), $mock['learners'])); ?></span>
                                    <span class="sikshya-pill"><?php echo esc_html($mock['updated']); ?></span>
                                    <span class="sikshya-pill"><?php echo esc_html($mock['level']); ?></span>
                                    <span class="sikshya-pill"><?php echo esc_html(sprintf(__('%s hours', 'sikshya'), $mock['hours'])); ?></span>
                                    <span class="sikshya-pill"><?php echo esc_html(sprintf(__('Instructor: %s', 'sikshya'), $mock['instructor'])); ?></span>
                                </div>
                                <div class="sikshya-progress">
                                    <div class="sikshya-progress__top">
                                        <strong><?php esc_html_e('Your progress', 'sikshya'); ?></strong>
                                        <span class="sikshya-muted"><?php echo esc_html($mock['progress'] . '%'); ?></span>
                                    </div>
                                    <div class="sikshya-progress__bar" role="progressbar" aria-label="<?php echo esc_attr__('Course progress', 'sikshya'); ?>" aria-valuenow="<?php echo esc_attr((string) $mock['progress']); ?>" aria-valuemin="0" aria-valuemax="100">
                                        <span class="sikshya-progress__fill" style="width: <?php echo esc_attr((string) $mock['progress']); ?>%"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="sikshya-learnSplit">
                                <div>
                                    <h3 class="sikshya-learnH3"><?php esc_html_e('What you’ll learn', 'sikshya'); ?></h3>
                                    <ul class="sikshya-learnList">
                                        <li><?php esc_html_e('How the web works: clients, servers, and HTTP', 'sikshya'); ?></li>
                                        <li><?php esc_html_e('HTML structure + accessible semantic markup', 'sikshya'); ?></li>
                                        <li><?php esc_html_e('CSS basics: layout, spacing, and typography', 'sikshya'); ?></li>
                                        <li><?php esc_html_e('JavaScript fundamentals to make pages interactive', 'sikshya'); ?></li>
                                    </ul>
                                </div>
                                <div>
                                    <h3 class="sikshya-learnH3"><?php esc_html_e('This course includes', 'sikshya'); ?></h3>
                                    <ul class="sikshya-learnList">
                                        <li><?php esc_html_e('Downloadable resources', 'sikshya'); ?></li>
                                        <li><?php esc_html_e('Quizzes and practice tasks', 'sikshya'); ?></li>
                                        <li><?php esc_html_e('Mobile-friendly learning', 'sikshya'); ?></li>
                                        <li><?php esc_html_e('Certificate of completion', 'sikshya'); ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="sikshya-tabsSection" aria-label="<?php esc_attr_e('Tabs', 'sikshya'); ?>">
                        <div class="sikshya-tabsBar" role="tablist">
                            <button type="button" class="sikshya-tabBtn is-active" data-sikshya-tab="overview"><?php esc_html_e('Overview', 'sikshya'); ?></button>
                            <button type="button" class="sikshya-tabBtn" data-sikshya-tab="reviews"><?php esc_html_e('Reviews', 'sikshya'); ?></button>
                            <button type="button" class="sikshya-tabBtn" data-sikshya-tab="tools"><?php esc_html_e('Learning tools', 'sikshya'); ?></button>
                        </div>
                        <div class="sikshya-tabPanel is-active" data-sikshya-panel="overview">
                            <div class="sikshya-contentPanel">
                                <?php if (!empty($lv['course']) && $lv['course'] instanceof WP_Post) : ?>
                                    <?php echo wp_kses_post(apply_filters('the_content', (string) $lv['course']->post_content)); ?>
                                <?php else : ?>
                                    <p class="sikshya-zeroMargin"><?php esc_html_e('Welcome to your learning workspace. Use the sidebar to navigate lessons, track your progress, and stay focused while studying.', 'sikshya'); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="sikshya-tabPanel" data-sikshya-panel="reviews">
                            <div class="sikshya-contentPanel">
                                <h3 class="sikshya-learnH3"><?php esc_html_e('Student reviews', 'sikshya'); ?></h3>
                                <div class="sikshya-reviewMock">
                                    <strong><?php esc_html_e('Ayesha K.', 'sikshya'); ?></strong>
                                    <span class="sikshya-muted"> — <?php esc_html_e('5.0', 'sikshya'); ?></span>
                                    <p class="sikshya-zeroMargin"><?php esc_html_e('Clear explanations and great pacing. Perfect for beginners.', 'sikshya'); ?></p>
                                </div>
                                <div class="sikshya-reviewMock">
                                    <strong><?php esc_html_e('Ramesh S.', 'sikshya'); ?></strong>
                                    <span class="sikshya-muted"> — <?php esc_html_e('4.5', 'sikshya'); ?></span>
                                    <p class="sikshya-zeroMargin"><?php esc_html_e('Loved the practice tasks — helped me remember the concepts.', 'sikshya'); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="sikshya-tabPanel" data-sikshya-panel="tools">
                            <div class="sikshya-contentPanel">
                                <h3 class="sikshya-learnH3"><?php esc_html_e('Learning tools', 'sikshya'); ?></h3>
                                <ul class="sikshya-learnList">
                                    <li><?php esc_html_e('Personal notes (per lesson)', 'sikshya'); ?></li>
                                    <li><?php esc_html_e('Bookmarks', 'sikshya'); ?></li>
                                    <li><?php esc_html_e('Progress tracking', 'sikshya'); ?></li>
                                    <li><?php esc_html_e('Announcements', 'sikshya'); ?></li>
                                </ul>
                            </div>
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

  // Tabs
  const tabs = document.querySelectorAll('[data-sikshya-tab]');
  const panels = document.querySelectorAll('[data-sikshya-panel]');
  tabs.forEach((btn) => {
    btn.addEventListener('click', () => {
      const target = btn.getAttribute('data-sikshya-tab');
      tabs.forEach((b) => b.classList.toggle('is-active', b === btn));
      panels.forEach((p) => {
        const on = p.getAttribute('data-sikshya-panel') === target;
        p.classList.toggle('is-active', on);
        p.hidden = !on;
      });
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

})();
</script>
