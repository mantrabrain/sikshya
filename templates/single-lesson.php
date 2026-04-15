<?php
/**
 * Single lesson — {@see \Sikshya\Frontend\Public\LessonTemplateData}.
 *
 * @package Sikshya
 */

use Sikshya\Frontend\Public\LessonTemplateData;
use Sikshya\Core\Plugin;

$plugin = Plugin::getInstance();
$sheet_ver = rawurlencode((string) $plugin->version);
$ds_href = esc_url($plugin->getAssetUrl('css/public-design-system.css')) . '?ver=' . $sheet_ver;
$learn_href = esc_url($plugin->getAssetUrl('css/learn.css')) . '?ver=' . $sheet_ver;

while (have_posts()) :
    the_post();
    $vm = LessonTemplateData::forPost(get_post());
    $page_title = sprintf(
        /* translators: 1: lesson title, 2: site name */
        '%1$s — %2$s',
        get_the_title(),
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
                $course_title = !empty($vm['course']) && $vm['course'] instanceof WP_Post
                    ? get_the_title($vm['course'])
                    : __('Learn', 'sikshya');
                ?>
                <span class="sikshya-learnTopbar__title" title="<?php echo esc_attr($course_title); ?>"><?php echo esc_html($course_title); ?></span>
            </div>
            <div class="sikshya-learnTopbar__right">
                <?php if (!empty($vm['urls']['account'])) : ?>
                    <a class="sikshya-btn sikshya-btn--outline sikshya-btn--sm" href="<?php echo esc_url($vm['urls']['account']); ?>">
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
                        $outline_blocks = $vm['blocks'];
                        if (!empty($_GET['mock_long'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                            $mock_chapters = [];
                            $k = 0;
                            for ($c = 1; $c <= 4; $c++) {
                                $items = [];
                                for ($i = 1; $i <= 7; $i++) {
                                    ++$k;
                                    $type_key = ($k % 6 === 0) ? 'quiz' : (($k % 9 === 0) ? 'assignment' : 'lesson');
                                    $lesson_type = ($type_key === 'lesson' && ($k % 5 === 0)) ? 'audio' : (($type_key === 'lesson' && ($k % 3 === 0)) ? 'video' : 'text');
                                    $is_current = $k === 3;
                                    $items[] = [
                                        'permalink' => $is_current ? (string) (get_permalink() ?: '') : '#',
                                        'title' => sprintf('Lesson %d — A very long title to test wrapping, truncation, and overall reading rhythm in the sidebar navigation', $k),
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
                                        'post_title' => sprintf('Chapter %d — How clients, servers, and protocols interact in real systems (long chapter title)', $c),
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

                <?php if (!empty($vm['error'])) : ?>
                    <div class="sikshya-contentSection sikshya-contentSection--centered">
                        <div class="sikshya-contentPanel sikshya-contentPanel--emptyState" role="alert" aria-live="polite">
                            <div class="sikshya-learnEmptyState">
                                <div class="sikshya-learnEmptyState__icon" aria-hidden="true">
                                    <?php echo sikshya_learn_icon('lock'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </div>
                                <div class="sikshya-learnEmptyState__body">
                                    <h2 class="sikshya-learnEmptyState__title"><?php esc_html_e('Access required', 'sikshya'); ?></h2>
                                    <p class="sikshya-learnEmptyState__message"><?php echo esc_html((string) $vm['error']); ?></p>
                                    <div class="sikshya-learnEmptyState__actions">
                                        <?php if (!empty($vm['course']) && $vm['course'] instanceof WP_Post) : ?>
                                            <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url(get_permalink($vm['course'])); ?>">
                                                <?php esc_html_e('View course', 'sikshya'); ?>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($vm['urls']['account'])) : ?>
                                            <a class="sikshya-btn sikshya-btn--outline" href="<?php echo esc_url($vm['urls']['account']); ?>">
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
                        'course' => !empty($vm['course']) && $vm['course'] instanceof WP_Post ? get_the_title($vm['course']) : __('Introduction to Web Development', 'sikshya'),
                    ];
                    ?>
                    <div class="sikshya-contentSection">
                        <div class="sikshya-contentPanel sikshya-contentPanel--header">
                            <div class="sikshya-learnHeader">
                                <div class="sikshya-learnHeader__top">
                                    <div class="sikshya-learnHeader__titles">
                                        <?php
                                        $lesson_type = sanitize_key((string) get_post_meta((int) get_the_ID(), '_sikshya_lesson_type', true));
                                        $lesson_icon = $lesson_type === 'video' ? 'play-video' : ($lesson_type === 'audio' ? 'audio' : 'doc');
                                        ?>
                                        <div class="sikshya-learnHeader__titleRow">
                                            <span class="sikshya-learnHeader__typeIcon" aria-hidden="true">
                                                <?php echo sikshya_learn_icon($lesson_icon); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                            </span>
                                            <h1 class="sikshya-learnHeader__title sikshya-zeroMargin"><?php echo esc_html(get_the_title()); ?></h1>
                                            <?php if (!empty($vm['current_chapter']) && $vm['current_chapter'] instanceof WP_Post) : ?>
                                                <span class="sikshya-learnHeader__chapterInline" title="<?php echo esc_attr(get_the_title($vm['current_chapter'])); ?>">
                                                    <span class="sikshya-learnHeader__chapterIcon" aria-hidden="true">
                                                        <?php echo sikshya_learn_icon('book'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                                    </span>
                                                    <?php echo esc_html(get_the_title($vm['current_chapter'])); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="sikshya-learnHeader__actions">
                                        <button type="button" class="sikshya-btn sikshya-btn--primary sikshya-btn--sm"><?php esc_html_e('Mark as complete', 'sikshya'); ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="sikshya-videoMock" role="group" aria-label="<?php echo esc_attr__('Lesson player', 'sikshya'); ?>">
                            <div class="sikshya-videoMock__stage">
                                <iframe
                                    class="sikshya-videoMock__embed"
                                    src="https://www.youtube-nocookie.com/embed/M7lc1UVf-VE?rel=0"
                                    title="<?php echo esc_attr__('Lesson video', 'sikshya'); ?>"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                    allowfullscreen
                                ></iframe>
                            </div>
                            <div class="sikshya-videoMock__controls">
                                <span class="sikshya-muted"><?php esc_html_e('00:00', 'sikshya'); ?></span>
                                <div class="sikshya-videoMock__scrub" aria-hidden="true"></div>
                                <span class="sikshya-muted"><?php esc_html_e('06:24', 'sikshya'); ?></span>
                                <span class="sikshya-videoMock__spacer" aria-hidden="true"></span>
                                <button type="button" class="sikshya-btn sikshya-btn--light sikshya-btn--sm"><?php esc_html_e('Prev', 'sikshya'); ?></button>
                                <button type="button" class="sikshya-btn sikshya-btn--light sikshya-btn--sm"><?php esc_html_e('Next', 'sikshya'); ?></button>
                            </div>
                        </div>
                    </div>

                    <div class="sikshya-tabsSection" aria-label="<?php esc_attr_e('Tabs', 'sikshya'); ?>">
                        <div class="sikshya-tabsBar" role="tablist">
                            <button type="button" class="sikshya-tabBtn is-active" data-sikshya-tab="overview"><?php esc_html_e('Overview', 'sikshya'); ?></button>
                            <button type="button" class="sikshya-tabBtn" data-sikshya-tab="resources"><?php esc_html_e('Resources', 'sikshya'); ?></button>
                            <button type="button" class="sikshya-tabBtn" data-sikshya-tab="notes"><?php esc_html_e('Notes', 'sikshya'); ?></button>
                            <button type="button" class="sikshya-tabBtn" data-sikshya-tab="announcements"><?php esc_html_e('Announcements', 'sikshya'); ?></button>
                        </div>
                        <div class="sikshya-tabPanel is-active" data-sikshya-panel="overview">
                            <div class="sikshya-contentPanel sikshya-contentPanel--plain">
                                <?php if (trim((string) get_the_content()) !== '') : ?>
                                    <?php the_content(); ?>
                                <?php else : ?>
                                    <h2><?php esc_html_e('What is HTTP?', 'sikshya'); ?></h2>
                                    <p><?php esc_html_e('HTTP is the protocol your browser uses to request resources (HTML, CSS, JS, images) from a server. Think of it as a structured conversation: the client asks, the server answers.', 'sikshya'); ?></p>

                                    <h3><?php esc_html_e('A request in one minute', 'sikshya'); ?></h3>
                                    <ul>
                                        <li><?php esc_html_e('Client sends a method + URL (e.g. GET /learn).', 'sikshya'); ?></li>
                                        <li><?php esc_html_e('Server returns a status code (200, 404, 500) + content.', 'sikshya'); ?></li>
                                        <li><?php esc_html_e('Headers carry metadata (cache, auth, content-type).', 'sikshya'); ?></li>
                                    </ul>

                                    <blockquote>
                                        <p><?php esc_html_e('Long sessions work best when the UI is quiet: readable text, soft contrast, predictable focus states.', 'sikshya'); ?></p>
                                    </blockquote>

                                    <h3><?php esc_html_e('Example: minimal fetch', 'sikshya'); ?></h3>
                                    <pre><code>async function loadLesson() {
  const res = await fetch('/api/lesson/123');
  if (!res.ok) throw new Error('Request failed');
  return await res.json();
}</code></pre>

                                    <h3><?php esc_html_e('Status codes cheat sheet', 'sikshya'); ?></h3>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('Code', 'sikshya'); ?></th>
                                                <th><?php esc_html_e('Meaning', 'sikshya'); ?></th>
                                                <th><?php esc_html_e('What to do', 'sikshya'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><code>200</code></td>
                                                <td><?php esc_html_e('Success', 'sikshya'); ?></td>
                                                <td><?php esc_html_e('Render the response', 'sikshya'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><code>401</code></td>
                                                <td><?php esc_html_e('Not authenticated', 'sikshya'); ?></td>
                                                <td><?php esc_html_e('Ask user to login', 'sikshya'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><code>404</code></td>
                                                <td><?php esc_html_e('Not found', 'sikshya'); ?></td>
                                                <td><?php esc_html_e('Show a helpful error', 'sikshya'); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <h3><?php esc_html_e('Quick check', 'sikshya'); ?></h3>
                                    <ol>
                                        <li><?php esc_html_e('What does a 404 mean?', 'sikshya'); ?></li>
                                        <li><?php esc_html_e('What header tells the browser the response is JSON?', 'sikshya'); ?></li>
                                    </ol>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="sikshya-tabPanel" data-sikshya-panel="resources">
                            <div class="sikshya-contentPanel sikshya-contentPanel--plain">
                                <h3 class="sikshya-learnH3"><?php esc_html_e('Downloads', 'sikshya'); ?></h3>
                                <ul class="sikshya-resList">
                                    <li><a href="#" class="sikshya-resLink"><?php esc_html_e('Lesson cheat-sheet (PDF)', 'sikshya'); ?></a><span class="sikshya-muted"> • 240 KB</span></li>
                                    <li><a href="#" class="sikshya-resLink"><?php esc_html_e('Starter files (ZIP)', 'sikshya'); ?></a><span class="sikshya-muted"> • 1.2 MB</span></li>
                                    <li><a href="#" class="sikshya-resLink"><?php esc_html_e('Reference links', 'sikshya'); ?></a><span class="sikshya-muted"> • 6 items</span></li>
                                </ul>
                            </div>
                        </div>
                        <div class="sikshya-tabPanel" data-sikshya-panel="notes">
                            <div class="sikshya-contentPanel sikshya-contentPanel--plain">
                                <h3 class="sikshya-learnH3"><?php esc_html_e('My notes', 'sikshya'); ?></h3>
                                <div class="sikshya-noteMock">
                                    <div class="sikshya-noteMock__time"><?php esc_html_e('02:14', 'sikshya'); ?></div>
                                    <div class="sikshya-noteMock__text"><?php esc_html_e('Remember: keep requests small and cacheable where possible.', 'sikshya'); ?></div>
                                </div>
                                <div class="sikshya-noteMock">
                                    <div class="sikshya-noteMock__time"><?php esc_html_e('04:58', 'sikshya'); ?></div>
                                    <div class="sikshya-noteMock__text"><?php esc_html_e('HTTP status codes: 2xx success, 4xx client errors, 5xx server errors.', 'sikshya'); ?></div>
                                </div>
                                <p class="sikshya-muted sikshya-zeroMargin"><?php esc_html_e('Notes are mock data for design preview.', 'sikshya'); ?></p>
                            </div>
                        </div>
                        <div class="sikshya-tabPanel" data-sikshya-panel="announcements">
                            <div class="sikshya-contentPanel sikshya-contentPanel--plain">
                                <h3 class="sikshya-learnH3"><?php esc_html_e('Announcements', 'sikshya'); ?></h3>
                                <div class="sikshya-announce">
                                    <div class="sikshya-announce__title"><?php esc_html_e('New: practice worksheet added', 'sikshya'); ?></div>
                                    <div class="sikshya-announce__meta sikshya-muted"><?php esc_html_e('Posted 2 days ago', 'sikshya'); ?></div>
                                    <p class="sikshya-zeroMargin"><?php esc_html_e('We added a worksheet to help you apply the lesson quickly. Check the Resources tab.', 'sikshya'); ?></p>
                                </div>
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
})();
</script>

    <?php
endwhile;
