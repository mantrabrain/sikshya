<?php
/**
 * Course content curriculum outline (sidebar).
 *
 * Expects `$outline_blocks` (array of blocks from template data) and `sikshya_learn_icon()`.
 *
 * @package Sikshya
 */

if (!isset($outline_blocks) || !is_array($outline_blocks)) {
    return;
}

$outline_show_progress = isset($outline_show_progress) ? (bool) $outline_show_progress : true;

$label_course = function_exists('sikshya_label') ? sikshya_label('course', __('Course', 'sikshya'), 'frontend') : __('Course', 'sikshya');
$label_chapter = function_exists('sikshya_label') ? sikshya_label('chapter', __('Chapter', 'sikshya'), 'frontend') : __('Chapter', 'sikshya');

if ($outline_blocks === []) {
    ?>
    <p class="sikshya-curriculumOutline__empty sikshya-muted">
        <?php
        echo esc_html(sprintf(
            /* translators: %s: singular label (e.g. course) */
            __('No curriculum items are published for this %s yet.', 'sikshya'),
            strtolower($label_course)
        ));
        ?>
    </p>
    <?php
    return;
}
?>
<nav class="sikshya-curriculumOutline" aria-label="<?php esc_attr_e('Curriculum', 'sikshya'); ?>">
    <?php foreach ($outline_blocks as $chapter_index => $block) : ?>
        <?php
        $chapter_post = $block['chapter'];
        $chapter_num = (int) $chapter_index + 1;
        $item_n = isset($block['item_count']) ? (int) $block['item_count'] : count((array) ($block['items'] ?? []));
        $done_n = $outline_show_progress && isset($block['completed_in_section']) ? (int) $block['completed_in_section'] : 0;
        $sec_min = isset($block['section_duration_minutes']) ? (int) $block['section_duration_minutes'] : 0;
        ?>
        <?php
        $chapter_has_current = false;
        foreach ((array) ($block['items'] ?? []) as $maybe_item) {
            if (!empty($maybe_item['current'])) {
                $chapter_has_current = true;
                break;
            }
        }
        ?>
        <details class="sikshya-curriculumOutline__chapter" open <?php echo $chapter_has_current ? 'data-sikshya-current-chapter="1"' : ''; ?>>
            <summary class="sikshya-curriculumOutline__sectionSummary">
                <span class="sikshya-curriculumOutline__sectionHead">
                    <span class="sikshya-curriculumOutline__sectionIcon" aria-hidden="true">
                        <?php echo sikshya_learn_icon('book'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>
                    <span class="sikshya-curriculumOutline__sectionText">
                        <span class="sikshya-curriculumOutline__sectionLabel">
                            <?php
                            echo esc_html(
                                sprintf(
                                    /* translators: %d: chapter sequence number (1-based) */
                                    __('%1$s %2$d', 'sikshya'),
                                    $label_chapter,
                                    $chapter_num
                                )
                            );
                            ?>
                        </span>
                        <span class="sikshya-curriculumOutline__sectionTitle"><?php echo esc_html($chapter_post->post_title); ?></span>
                        <span class="sikshya-curriculumOutline__sectionMeta">
                            <?php
                            $meta_bits = [];
                            if ($outline_show_progress) {
                                $meta_bits[] = sprintf(
                                    /* translators: 1: completed count, 2: total items */
                                    __('%1$d / %2$d', 'sikshya'),
                                    $done_n,
                                    $item_n
                                );
                            } else {
                                $meta_bits[] = sprintf(
                                    /* translators: %d: total items */
                                    _n('%d item', '%d items', $item_n, 'sikshya'),
                                    $item_n
                                );
                            }
                            if ($sec_min > 0) {
                                $meta_bits[] = sprintf(
                                    /* translators: 1: minutes */
                                    _n('%d min', '%d min', $sec_min, 'sikshya'),
                                    $sec_min
                                );
                            }
                            echo esc_html(implode(' • ', $meta_bits));
                            ?>
                        </span>
                    </span>
                    <span class="sikshya-curriculumOutline__sectionChevron" aria-hidden="true">
                        <?php echo sikshya_learn_icon('chevron-down'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>
                </span>
            </summary>
            <ol class="sikshya-curriculumOutline__list">
                <?php foreach ((array) ($block['items'] ?? []) as $item) : ?>
                    <?php
                    $current = !empty($item['current']);
                    $icon = (string) ($item['type_key'] ?? 'content');
                    $completed = $outline_show_progress && !empty($item['completed']);
                    $locked = !empty($item['locked']);
                    $lock_reason = trim((string) ($item['lock_reason'] ?? ''));
                    $lesson_type = sanitize_key((string) ($item['lesson_type'] ?? ''));
                    $idx = isset($item['index_in_section']) ? (int) $item['index_in_section'] : 0;
                    $sub = trim((string) ($item['subtitle_compact'] ?? ''));
                    if ($sub === '') {
                        $sub = trim((string) ($item['meta_line'] ?? ''));
                    }
                    $drip_hint = trim((string) ($item['drip_unlock_hint'] ?? ''));
                    $item_title = (string) ($item['title'] ?? '');
                    ?>
                    <li class="sikshya-curriculumOutline__item" <?php echo $current ? ' data-sikshya-current="1"' : ''; ?>>
                        <div class="sikshya-curriculumOutline__row<?php echo $current ? ' is-current' : ''; ?>">
                            <a
                                class="sikshya-curriculumOutline__link<?php echo $locked ? ' is-locked' : ''; ?>"
                                href="<?php echo esc_url((string) ($item['permalink'] ?? '')); ?>"
                                <?php echo $current ? 'aria-current="page"' : ''; ?>
                                <?php
                                if ($locked) {
                                    $aria_reason = $lock_reason !== '' ? $lock_reason : __('Locked', 'sikshya');
                                    echo 'aria-label="' . esc_attr($item_title . ' — ' . $aria_reason) . '"';
                                }
                                ?>
                            >
                                <span class="sikshya-curriculumOutline__check<?php echo $completed ? ' is-done' : ''; ?>" aria-hidden="true">
                                    <?php if ($completed) : ?>
                                        <svg viewBox="0 0 24 24" width="25" height="25" aria-hidden="true" focusable="false">
                                            <circle cx="12" cy="12" r="10" fill="currentColor"></circle>
                                            <path d="M8 12.5l2.5 2.5L16.5 9" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                        </svg>
                                    <?php endif; ?>
                                </span>
                                <span class="sikshya-curriculumOutline__itemIcon" aria-hidden="true">
                                    <?php
                                    if ($icon === 'lesson') {
                                        if ($lesson_type === 'video') {
                                            echo sikshya_learn_icon('play-video');
                                        } elseif ($lesson_type === 'audio') {
                                            echo sikshya_learn_icon('audio');
                                        } else {
                                            // text/document/unknown
                                            echo sikshya_learn_icon('doc');
                                        }
                                    } elseif ($icon === 'quiz') {
                                        echo sikshya_learn_icon('clipboard');
                                    } elseif ($icon === 'assignment') {
                                        echo sikshya_learn_icon('assignment');
                                    } else {
                                        echo sikshya_learn_icon('doc');
                                    }
                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                    ?>
                                </span>
                                <span class="sikshya-curriculumOutline__linkBody">
                                    <span class="sikshya-curriculumOutline__lessonTitle" title="<?php echo esc_attr($item_title); ?>">
                                        <?php echo esc_html($idx > 0 ? sprintf('%d. %s', $idx, $item_title) : $item_title); ?>
                                    </span>
                                    <?php if ($sub !== '') : ?>
                                        <span class="sikshya-curriculumOutline__lessonSub">
                                            <span class="sikshya-curriculumOutline__dur"><?php echo esc_html($sub); ?></span>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($drip_hint !== '') : ?>
                                        <span class="sikshya-curriculumOutline__lessonSub sikshya-curriculumOutline__dripHint" title="<?php echo esc_attr($drip_hint); ?>">
                                            <?php echo esc_html($drip_hint); ?>
                                        </span>
                                    <?php endif; ?>
                                </span>
                                <?php if ($locked) : ?>
                                    <span class="sikshya-curriculumOutline__lock" aria-hidden="true">
                                        <?php echo sikshya_learn_icon('lock'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        </details>
    <?php endforeach; ?>
</nav>
