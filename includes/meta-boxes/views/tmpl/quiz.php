<div class="group js-sikshya__quiz-item"  data-quiz-id="<?php echo $id; ?>">
    <h3 class="js-group__label ms-head" data-default="<?php echo esc_html($title); ?>">
        <span class="dashicons dashicons-clock"></span>
        <span class="js-group__label_text"><?php echo esc_html($title); ?></span>
        <span class="sikshya-count"><?php echo sikshya()->quiz->get_child_count_text($id); ?></span>

        <a class="js-sikshya__remove-quiz" href="#"><span
                class="dashicons dashicons-trash"></span></a>
    </h3>
    <div class="sikshya__quiz-form js-sikshya__quiz-form">
        <div class="ms-field ms-field-large">
            <label class="ms-label" for="quiz<?php echo $id; ?>"><?php _e('Quiz title', 'sikshya'); ?>:</label>
            <div class="ms-value">
                <input type="text" id="quiz<?php echo $id; ?>" name="<?php echo $name; ?>[title]" size="25"
                       class="js-sikshya__quiz-title" value="<?php echo esc_html($title); ?>"/>
            </div>
        </div>
        <div class="customEditor custom_upload_buttons">
            <div class="js-sikshya__quiz-editor"><?php echo $content; ?></div>
        </div>
        <div class="js-sikshya__wrapper-quiz-questions ms-quiz-questions-list">
            <?php echo $questionsHtml; ?>
        </div>
        <div class="sikshya-actions">
            <a class="js-sikshya__quiz-questions-add button button-primary button-large" href="javascript:void(0);">
                <span class="dashicons dashicons-warning"></span>
                <?php _e('Add question', 'sikshya'); ?>
            </a>
        </div>
    </div>
</div>