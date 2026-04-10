<?php
/**
 * Single quiz template (learner-facing).
 *
 * @package Sikshya
 */

if (!defined('ABSPATH')) {
    exit;
}

use Sikshya\Frontend\Public\QuizTemplateData;

get_header();

while (have_posts()) {
    the_post();
    $vm = QuizTemplateData::forPost(get_post());
    $quiz_id = (int) $vm['post']->ID;
    ?>
    <div class="sikshya-quiz-page">
        <nav class="sikshya-quiz-page__breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'sikshya'); ?>">
            <a href="<?php echo esc_url($vm['urls']['courses']); ?>"><?php esc_html_e('Courses', 'sikshya'); ?></a>
            <span class="sikshya-quiz-page__sep" aria-hidden="true">/</span>
            <span><?php echo esc_html(get_the_title()); ?></span>
        </nav>

        <header class="sikshya-quiz-page__header">
            <h1 class="sikshya-quiz-page__title"><?php the_title(); ?></h1>
        </header>

        <div class="sikshya-quiz-page__content entry-content">
            <?php the_content(); ?>

            <?php if (!$vm['logged_in']) : ?>
                <p class="sikshya-quiz-page__notice">
                    <?php esc_html_e('Log in to take this quiz.', 'sikshya'); ?>
                    <a href="<?php echo esc_url($vm['urls']['login']); ?>"><?php esc_html_e('Log in', 'sikshya'); ?></a>
                </p>
            <?php elseif (!$vm['enrolled']) : ?>
                <p class="sikshya-quiz-page__notice">
                    <?php esc_html_e('You must be enrolled in the course to take this quiz.', 'sikshya'); ?>
                </p>
            <?php elseif ($vm['attempts_limited'] && (int) $vm['attempts_remaining'] <= 0) : ?>
                <p class="sikshya-quiz-page__notice">
                    <?php esc_html_e('You have used all allowed attempts for this quiz.', 'sikshya'); ?>
                </p>
            <?php elseif (empty($vm['questions'])) : ?>
                <p class="sikshya-quiz-page__notice">
                    <?php esc_html_e('This quiz has no questions yet.', 'sikshya'); ?>
                </p>
            <?php else : ?>
                <?php if ($vm['attempts_limited']) : ?>
                    <p class="sikshya-quiz-page__meta">
                        <?php
                        printf(
                            /* translators: 1: used attempts, 2: max attempts */
                            esc_html__('Attempts used: %1$d of %2$d.', 'sikshya'),
                            (int) $vm['attempts_used'],
                            (int) $vm['attempts_max']
                        );
                        ?>
                    </p>
                <?php endif; ?>

                <form class="sikshya-quiz-form" data-quiz-id="<?php echo esc_attr((string) $quiz_id); ?>" method="post" action="#">
                    <?php wp_nonce_field('sikshya_quiz_take', 'sikshya_quiz_take_nonce'); ?>

                    <?php foreach ($vm['questions'] as $q) : ?>
                        <?php
                        $qid = (int) $q['id'];
                        $type = (string) $q['type'];
                        $name = 'question_' . $qid;
                        ?>
                        <div class="sikshya-q" data-qid="<?php echo esc_attr((string) $qid); ?>" data-qtype="<?php echo esc_attr($type); ?>">
                            <p class="sikshya-q__title"><?php echo esc_html($q['title']); ?></p>

                            <?php if ($type === 'multiple_choice' && !empty($q['options'])) : ?>
                                <ul class="sikshya-q__choices">
                                    <?php foreach ($q['options'] as $i => $label) : ?>
                                        <li>
                                            <label>
                                                <input type="radio" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr((string) $i); ?>">
                                                <?php echo esc_html($label); ?>
                                            </label>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>

                            <?php elseif ($type === 'multiple_response' && !empty($q['options'])) : ?>
                                <ul class="sikshya-q__choices sikshya-q__choices--multi">
                                    <?php foreach ($q['options'] as $i => $label) : ?>
                                        <li>
                                            <label>
                                                <input type="checkbox" class="sikshya-q__mr" value="<?php echo esc_attr((string) $i); ?>">
                                                <?php echo esc_html($label); ?>
                                            </label>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>

                            <?php elseif ($type === 'true_false') : ?>
                                <ul class="sikshya-q__choices">
                                    <li>
                                        <label>
                                            <input type="radio" name="<?php echo esc_attr($name); ?>" value="true">
                                            <?php esc_html_e('True', 'sikshya'); ?>
                                        </label>
                                    </li>
                                    <li>
                                        <label>
                                            <input type="radio" name="<?php echo esc_attr($name); ?>" value="false">
                                            <?php esc_html_e('False', 'sikshya'); ?>
                                        </label>
                                    </li>
                                </ul>

                            <?php elseif ($type === 'ordering' && !empty($q['ordering_display'])) : ?>
                                <ol class="sikshya-ordering" data-qid="<?php echo esc_attr((string) $qid); ?>">
                                    <?php foreach ($q['ordering_display'] as $pair) : ?>
                                        <li data-item-index="<?php echo esc_attr((string) (int) $pair['index']); ?>">
                                            <?php echo esc_html((string) $pair['text']); ?>
                                            <span class="sikshya-ordering__btns">
                                                <button type="button" class="sikshya-ordering__up" aria-label="<?php esc_attr_e('Move up', 'sikshya'); ?>">↑</button>
                                                <button type="button" class="sikshya-ordering__down" aria-label="<?php esc_attr_e('Move down', 'sikshya'); ?>">↓</button>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>

                            <?php elseif ($type === 'matching' && !empty($q['matching_left']) && !empty($q['matching_right'])) : ?>
                                <div class="sikshya-matching" data-qid="<?php echo esc_attr((string) $qid); ?>">
                                    <?php foreach ($q['matching_left'] as $li => $left_text) : ?>
                                        <div class="sikshya-matching__row">
                                            <span class="sikshya-matching__left"><?php echo esc_html($left_text); ?></span>
                                            <select class="sikshya-matching__select" aria-label="<?php esc_attr_e('Match', 'sikshya'); ?>">
                                                <?php foreach ($q['matching_right'] as $ri => $right_text) : ?>
                                                    <option value="<?php echo esc_attr((string) $ri); ?>"><?php echo esc_html($right_text); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                            <?php else : ?>
                                <textarea class="sikshya-q__text" name="<?php echo esc_attr($name); ?>" rows="4" cols="60"></textarea>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="sikshya-quiz-form__footer">
                        <button type="submit" class="sikshya-quiz-submit button"><?php esc_html_e('Submit quiz', 'sikshya'); ?></button>
                    </div>

                    <div class="sikshya-quiz-result" hidden></div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

get_footer();
