<?php
/**
 * Assignment submission + status on the Learn shell (single lesson template).
 *
 * @package Sikshya
 *
 * @var \Sikshya\Presentation\Models\SingleLessonPageModel $page_model
 */

// phpcs:ignore
if (!defined('ABSPATH')) {
    exit;
}

$asg = $page_model->getAssignmentLearnPayload();
if (!is_array($asg)) {
    return;
}

$points = (int) ($asg['points'] ?? 0);
$due_fmt = (string) ($asg['due_formatted'] ?? '');
$type_label = (string) ($asg['submission_type_label'] ?? '');
$subtype = (string) ($asg['submission_subtype'] ?? 'essay');
$can_submit = !empty($asg['can_submit']);
$is_past_due = !empty($asg['is_past_due']);
$allow_late = !empty($asg['allow_late']);
$min_files = (int) ($asg['min_files'] ?? 0);
$max_files = (int) ($asg['max_files'] ?? 0);
$require_text = !empty($asg['require_text']);
$submission = isset($asg['submission']) && is_array($asg['submission']) ? $asg['submission'] : null;
$status = is_array($submission) ? (string) ($submission['status'] ?? '') : '';
$graded = $status === 'graded';
$allow_resubmit = is_array($submission) && !empty($submission['allow_resubmit']);
$mf = max(0, $min_files);
$xf = max(0, $max_files);
$file_multiple = $xf === 0 || $xf > 1;
?>
<div class="sikshya-assignmentPanel" data-sikshya-assignment-panel>
    <div class="sikshya-assignmentPanel__meta">
        <?php if ($points > 0) : ?>
            <span class="sikshya-assignmentPanel__chip"><?php echo esc_html(sprintf(__('%d points', 'sikshya'), $points)); ?></span>
        <?php endif; ?>
        <?php if ($type_label !== '') : ?>
            <span class="sikshya-assignmentPanel__chip"><?php echo esc_html($type_label); ?></span>
        <?php endif; ?>
        <?php if ($due_fmt !== '') : ?>
            <span class="sikshya-assignmentPanel__chip<?php echo $is_past_due && !$allow_late ? ' sikshya-assignmentPanel__chip--warn' : ''; ?>">
                <?php
                echo esc_html(
                    $is_past_due && !$allow_late
                        /* translators: %s: due date */
                        ? sprintf(__('Past due · was due %s', 'sikshya'), $due_fmt)
                        /* translators: %s: due date */
                        : sprintf(__('Due %s', 'sikshya'), $due_fmt)
                );
                ?>
            </span>
        <?php endif; ?>
    </div>

    <?php if ($submission) : ?>
        <div class="sikshya-assignmentPanel__status" role="status">
            <h3 class="sikshya-learnH3"><?php esc_html_e('Your submission', 'sikshya'); ?></h3>
            <p class="sikshya-zeroMargin sikshya-muted">
                <?php
                if ($graded) {
                    esc_html_e('Graded — see feedback below.', 'sikshya');
                } elseif ($status === 'submitted') {
                    esc_html_e('Submitted — your instructor will review it.', 'sikshya');
                } else {
                    echo esc_html(sprintf(__('Status: %s', 'sikshya'), $status !== '' ? $status : __('Unknown', 'sikshya')));
                }
                ?>
            </p>
            <?php
            $sub_ts = !empty($submission['submitted_at']) ? strtotime((string) $submission['submitted_at']) : false;
            if ($sub_ts) :
                ?>
                <p class="sikshya-zeroMargin sikshya-muted" style="margin-top:6px;font-size:12px;">
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: %s: datetime */
                            __('Submitted: %s', 'sikshya'),
                            wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $sub_ts)
                        )
                    );
                    ?>
                </p>
            <?php endif; ?>
            <?php if ($graded && array_key_exists('grade', $submission) && $submission['grade'] !== null) : ?>
                <p class="sikshya-assignmentPanel__grade">
                    <?php
                    $g = (float) $submission['grade'];
                    $max_label = $points > 0 ? (string) $points : '—';
                    echo esc_html(
                        sprintf(
                            /* translators: 1: numeric grade, 2: max points */
                            __('Grade: %1$s / %2$s', 'sikshya'),
                            rtrim(rtrim(number_format($g, 2, '.', ''), '0'), '.'),
                            $max_label
                        )
                    );
                    ?>
                </p>
            <?php endif; ?>
            <?php if ($graded && !empty($submission['feedback'])) : ?>
                <div class="sikshya-assignmentPanel__feedback">
                    <p class="sikshya-assignmentPanel__subhead"><strong><?php esc_html_e('Instructor feedback', 'sikshya'); ?></strong></p>
                    <div class="sikshya-assignmentPanel__feedbackBody">
                        <?php echo sikshya_render_rich_text((string) $submission['feedback']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($submission['content']) && is_string($submission['content'])) : ?>
                <div class="sikshya-assignmentPanel__submittedContent">
                    <p class="sikshya-assignmentPanel__subhead"><strong><?php esc_html_e('What you submitted', 'sikshya'); ?></strong></p>
                    <div class="sikshya-prose"><?php echo sikshya_render_rich_text((string) $submission['content']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                </div>
            <?php endif; ?>
            <?php if (!empty($submission['attachments']) && is_array($submission['attachments'])) : ?>
                <div class="sikshya-assignmentPanel__files">
                    <p class="sikshya-assignmentPanel__subhead"><strong><?php esc_html_e('Files', 'sikshya'); ?></strong></p>
                    <ul class="sikshya-resList">
                        <?php foreach ($submission['attachments'] as $att) : ?>
                            <?php
                            if (!is_array($att)) {
                                continue;
                            }
                            $url = isset($att['url']) ? esc_url((string) $att['url']) : '';
                            $name = isset($att['name']) ? (string) $att['name'] : '';
                            if ($url === '') {
                                continue;
                            }
                            ?>
                            <li>
                                <a class="sikshya-resLink" href="<?php echo $url; ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($name !== '' ? $name : $url); ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($page_model->isEnrolled() && !$page_model->isPreview()) : ?>
        <?php if ($can_submit) : ?>
            <div class="sikshya-assignmentPanel__composer">
                <h3 class="sikshya-learnH3"><?php esc_html_e('Submit or update', 'sikshya'); ?></h3>
                <p class="sikshya-muted sikshya-zeroMargin" style="margin-bottom:10px;font-size:13px;">
                    <?php esc_html_e('Follow the instructions above, then use the fields below. Your outline progress updates when you submit successfully.', 'sikshya'); ?>
                </p>
                <form class="sikshya-assignmentPanel__form" data-sikshya-assignment-form enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="assignment_id" value="<?php echo esc_attr((string) (int) ($asg['assignment_id'] ?? 0)); ?>" />
                    <?php if ($subtype === 'essay') : ?>
                        <label class="sikshya-learnNotes__composerLabel" for="sikshya-assignment-essay"><?php esc_html_e('Your response', 'sikshya'); ?></label>
                        <textarea
                            id="sikshya-assignment-essay"
                            class="sikshya-quizQ__textarea"
                            name="content"
                            rows="8"
                            placeholder="<?php echo esc_attr__('Write your answer here…', 'sikshya'); ?>"
                        ><?php echo isset($submission['content']) ? esc_textarea((string) $submission['content']) : ''; ?></textarea>
                    <?php elseif ($subtype === 'file_upload') : ?>
                        <?php
                        $aid_files = (int) ($asg['assignment_id'] ?? 0);
                        $file_input_id = 'sikshya-assignment-files-' . max(1, $aid_files);
                        $ext_raw = trim(strtolower((string) get_post_meta($aid_files, '_sikshya_allowed_file_extensions', true)));
                        $accept_attr = '';
                        if ($ext_raw !== '') {
                            $dots = [];
                            foreach (array_filter(array_map('trim', explode(',', $ext_raw))) as $e) {
                                $e = ltrim((string) $e, '.');
                                if ($e !== '') {
                                    $dots[] = '.' . $e;
                                }
                            }
                            if ($dots !== []) {
                                $accept_attr = ' accept="' . esc_attr(implode(',', $dots)) . '"';
                            }
                        }
                        ?>
                        <span class="sikshya-learnNotes__composerLabel" id="<?php echo esc_attr($file_input_id); ?>-label"><?php esc_html_e('Upload file(s)', 'sikshya'); ?></span>
                        <div
                            class="sikshya-assignmentDropzone"
                            data-sikshya-dropzone
                            data-sikshya-max-files="<?php echo esc_attr((string) ($xf > 0 ? $xf : 0)); ?>"
                            role="group"
                            aria-labelledby="<?php echo esc_attr($file_input_id); ?>-label"
                        >
                            <input
                                id="<?php echo esc_attr($file_input_id); ?>"
                                type="file"
                                class="sikshya-assignmentDropzone__native"
                                name="<?php echo $file_multiple ? 'attachments[]' : 'attachments'; ?>"
                                <?php echo $file_multiple ? 'multiple' : ''; ?>
                                <?php
                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above with esc_attr()
                                echo $accept_attr;
                                ?>
                            />
                            <label class="sikshya-assignmentDropzone__surface" for="<?php echo esc_attr($file_input_id); ?>">
                                <p class="sikshya-assignmentDropzone__title"><?php esc_html_e('Drag & drop files here', 'sikshya'); ?></p>
                                <p class="sikshya-assignmentDropzone__hint">
                                    <?php esc_html_e('Or click to browse. Your files upload when you submit the assignment.', 'sikshya'); ?>
                                </p>
                                <span class="sikshya-assignmentDropzone__browse"><?php esc_html_e('Choose files', 'sikshya'); ?></span>
                            </label>
                            <ul class="sikshya-assignmentDropzone__list" data-sikshya-dropzone-list hidden></ul>
                        </div>
                        <?php if ($mf > 0 || $xf > 0) : ?>
                            <p class="sikshya-muted sikshya-zeroMargin" style="margin-top:6px;font-size:12px;">
                                <?php
                                if ($mf > 0 && $xf > 0) {
                                    echo esc_html(sprintf(__('Minimum %1$d file(s), maximum %2$d.', 'sikshya'), $mf, $xf));
                                } elseif ($mf > 0) {
                                    echo esc_html(sprintf(__('Minimum %d file(s).', 'sikshya'), $mf));
                                } else {
                                    echo esc_html(sprintf(__('Maximum %d file(s).', 'sikshya'), $xf));
                                }
                                ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($require_text) : ?>
                            <label class="sikshya-learnNotes__composerLabel" for="sikshya-assignment-note" style="margin-top:12px;"><?php esc_html_e('Note to instructor (required)', 'sikshya'); ?></label>
                            <textarea
                                id="sikshya-assignment-note"
                                class="sikshya-quizQ__textarea"
                                name="content"
                                rows="3"
                                placeholder="<?php echo esc_attr__('Briefly describe what you are uploading…', 'sikshya'); ?>"
                            ></textarea>
                        <?php endif; ?>
                    <?php else : ?>
                        <label class="sikshya-learnNotes__composerLabel" for="sikshya-assignment-url"><?php esc_html_e('URL', 'sikshya'); ?></label>
                        <input
                            id="sikshya-assignment-url"
                            type="url"
                            class="sikshya-assignmentPanel__urlInput"
                            name="content"
                            value="<?php echo isset($submission['content']) ? esc_attr((string) $submission['content']) : ''; ?>"
                            placeholder="https://"
                        />
                    <?php endif; ?>
                    <div class="sikshya-quizActions" style="margin-top:12px;">
                        <button type="submit" class="sikshya-btn sikshya-btn--primary" data-sikshya-assignment-submit>
                            <?php echo esc_html($submission ? __('Submit again', 'sikshya') : __('Submit assignment', 'sikshya')); ?>
                        </button>
                        <span class="sikshya-muted" data-sikshya-assignment-status style="font-size:12px;margin-left:8px;"></span>
                    </div>
                </form>
            </div>
        <?php elseif ($is_past_due && !$allow_late && !($graded && $allow_resubmit)) : ?>
            <p class="sikshya-zeroMargin sikshya-muted" role="alert">
                <?php esc_html_e('The due date has passed and late submissions are not enabled for this assignment.', 'sikshya'); ?>
            </p>
        <?php endif; ?>
    <?php else : ?>
        <p class="sikshya-zeroMargin sikshya-muted">
            <?php esc_html_e('Enroll in this course to submit this assignment.', 'sikshya'); ?>
        </p>
    <?php endif; ?>
</div>

<?php
$__rest = $page_model->getRest();
$boot = [
    'rest' => (string) $__rest->getUrl(),
    'nonce' => (string) $__rest->getNonce(),
    'courseId' => (int) $page_model->getCourseId(),
    'maxFiles' => $xf > 0 ? $xf : 0,
];
?>
<script type="application/json" id="sikshya-assignment-boot"><?php echo wp_json_encode($boot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
