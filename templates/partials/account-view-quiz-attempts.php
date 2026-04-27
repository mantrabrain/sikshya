<?php
/**
 * Account: Quiz attempts overview.
 *
 * @package Sikshya
 *
 * @var array<string, mixed>                         $acc Back-compat view array for hooks.
 * @var \Sikshya\Presentation\Models\AccountPageModel $page_model
 */

?>
            <?php
            $label_quiz = function_exists('sikshya_label') ? sikshya_label('quiz', __('Quiz', 'sikshya'), 'frontend') : __('Quiz', 'sikshya');
            $label_quizzes = function_exists('sikshya_label_plural') ? sikshya_label_plural('quiz', 'quizzes', __('Quizzes', 'sikshya'), 'frontend') : __('Quizzes', 'sikshya');
            $label_course = function_exists('sikshya_label') ? sikshya_label('course', __('Course', 'sikshya'), 'frontend') : __('Course', 'sikshya');
            $label_courses = function_exists('sikshya_label_plural') ? sikshya_label_plural('course', 'courses', __('Courses', 'sikshya'), 'frontend') : __('Courses', 'sikshya');
            ?>
            <section class="sik-acc-panel" aria-label="<?php echo esc_attr(sprintf(__('%s attempts', 'sikshya'), $label_quiz)); ?>">
                <div class="sik-acc-panel__head">
                    <h2 class="sik-acc-panel__title"><?php echo esc_html(sprintf(__('%s attempts', 'sikshya'), $label_quiz)); ?></h2>
                </div>
                <?php if ($page_model->getQuizAttempts() === []) : ?>
                    <div class="sik-acc-empty">
                        <?php
                        echo esc_html(sprintf(
                            /* translators: 1: plural label (e.g. quizzes), 2: plural label (e.g. courses) */
                            __('No %1$s found in your enrolled %2$s yet.', 'sikshya'),
                            strtolower($label_quizzes),
                            strtolower($label_courses)
                        ));
                        ?>
                    </div>
                <?php else : ?>
                    <div class="sik-acc-table-wrap">
                        <table class="sik-acc-table">
                            <thead>
                            <tr>
                                <th scope="col"><?php echo esc_html($label_quiz); ?></th>
                                <th scope="col"><?php echo esc_html($label_course); ?></th>
                                <th scope="col"><?php esc_html_e('Attempts', 'sikshya'); ?></th>
                                <th scope="col"><?php esc_html_e('Status', 'sikshya'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($page_model->getQuizAttempts() as $qa) : ?>
                                <?php
                                if (!is_array($qa)) {
                                    continue;
                                }
                                $quiz_title = (string) ($qa['quiz_title'] ?? '');
                                $course_title = (string) ($qa['course_title'] ?? '');
                                $used = isset($qa['attempts_used']) ? (int) $qa['attempts_used'] : 0;
                                $limit = isset($qa['attempts_limit']) ? (int) $qa['attempts_limit'] : 0;
                                $remaining = array_key_exists('attempts_remaining', $qa) ? $qa['attempts_remaining'] : null;
                                $locked = !empty($qa['is_locked']);
                                $url = (string) ($qa['url'] ?? '');
                                ?>
                                <tr>
                                    <td>
                                        <?php if ($url !== '') : ?>
                                            <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($quiz_title !== '' ? $quiz_title : $label_quiz); ?></a>
                                        <?php else : ?>
                                            <?php echo esc_html($quiz_title !== '' ? $quiz_title : $label_quiz); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($course_title); ?></td>
                                    <td>
                                        <?php
                                        if ($limit > 0) {
                                            echo esc_html(sprintf(__('%1$d / %2$d', 'sikshya'), $used, $limit));
                                            if (is_int($remaining)) {
                                                echo esc_html(sprintf(__(' (%d left)', 'sikshya'), $remaining));
                                            }
                                        } else {
                                            echo esc_html(sprintf(__('%d (unlimited)', 'sikshya'), $used));
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($locked) : ?>
                                            <span class="sik-acc-badge sik-acc-badge--muted"><?php esc_html_e('Locked', 'sikshya'); ?></span>
                                        <?php else : ?>
                                            <span class="sik-acc-badge"><?php esc_html_e('Available', 'sikshya'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <?php do_action('sikshya_account_quiz_attempts_after', $acc); ?>
