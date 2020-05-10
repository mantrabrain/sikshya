<script type="text/html" id="sikshya_course_requirements_template">
    <div class="sikshya-field-wrap">
        <div class="sikshya-field-label">

        </div>
        <div class="sikshya-field-content">
            <input class="widefat sikshya_course_requirements" name="sikshya_course_requirements[]" type="text" value=""
                   placeholder="<?php echo esc_attr__('Requirements', 'sikshya') ?>">
            <button type="button"
                    class="sikshya-remove-requirements button button-primary sikshya-button btn-danger"><span
                        class="dashicons dashicons-minus"></span></button>
        </div>

    </div>
</script>
<?php
$course_requirements = isset($sikshya_course_requirements) ? $sikshya_course_requirements : array('');

foreach ($course_requirements as $requirement_key => $requirement) {
    ?>
    <div class="sikshya-field-wrap">
        <div class="sikshya-field-label">
            <?php if ($requirement_key == 0) { ?>
                <label for="sikshya_course_requirements"><?php echo esc_html__('Requirements', 'sikshya') ?></label>
            <?php } ?>
        </div>
        <div class="sikshya-field-content">
            <input class="widefat sikshya_course_requirements" name="sikshya_course_requirements[]" type="text"
                   value="<?php echo esc_attr($requirement) ?>"
                   placeholder="<?php echo esc_attr__('Requirements', 'sikshya') ?>">
            <?php if ($requirement_key == 0) {
                $button_class = 'sikshya-add-requirements button button-primary sikshya-button btn-success';
                $icon_class = 'dashicons dashicons-plus-alt';
            } else {
                $button_class = 'sikshya-remove-requirements button button-primary sikshya-button btn-danger';
                $icon_class = 'dashicons dashicons-minus';
            }
            ?>

            <button type="button"
                    class="<?php echo esc_attr($button_class); ?>"><span
                        class="<?php echo esc_attr($icon_class); ?>"></span></button>


        </div>
    </div>
<?php } ?>
