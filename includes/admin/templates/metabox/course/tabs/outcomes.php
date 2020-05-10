<script type="text/html" id="sikshya_course_outcomes_template">
    <div class="sikshya-field-wrap">
        <div class="sikshya-field-label">

        </div>
        <div class="sikshya-field-content">
            <input class="widefat sikshya_course_outcomes" name="sikshya_course_outcomes[]" type="text" value=""
                   placeholder="<?php echo esc_attr__('Outcomes', 'sikshya') ?>">
            <button type="button"
                    class="sikshya-remove-outcomes button button-primary sikshya-button btn-danger"><span
                        class="dashicons dashicons-minus"></span></button>
        </div>

    </div>
</script>
<?php
$course_outcomes = isset($sikshya_course_outcomes) ? $sikshya_course_outcomes : array('');

foreach ($course_outcomes as $outcome_key => $outcome) {
    ?>
    <div class="sikshya-field-wrap">
        <div class="sikshya-field-label">
            <?php if ($outcome_key == 0) { ?>
                <label for="sikshya_course_outcomes"><?php echo esc_html__('Outcomes', 'sikshya') ?></label>
            <?php } ?>
        </div>
        <div class="sikshya-field-content">
            <input class="widefat sikshya_course_outcomes" name="sikshya_course_outcomes[]" type="text"
                   value="<?php echo esc_attr($outcome) ?>"
                   placeholder="<?php echo esc_attr__('Outcomes', 'sikshya') ?>">
            <?php if ($outcome_key == 0) {
                $button_class = 'sikshya-add-outcomes button button-primary sikshya-button btn-success';
                $icon_class = 'dashicons dashicons-plus-alt';
            } else {
                $button_class = 'sikshya-remove-outcomes button button-primary sikshya-button btn-danger';
                $icon_class = 'dashicons dashicons-minus';
            }
            ?>

            <button type="button"
                    class="<?php echo esc_attr($button_class); ?>"><span
                        class="<?php echo esc_attr($icon_class); ?>"></span></button>


        </div>
    </div>
<?php } ?>
