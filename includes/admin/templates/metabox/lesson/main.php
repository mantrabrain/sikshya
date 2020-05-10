<div id="admin-editor-sik_lesson" class="sik-admin-editor sik-box-data admin-editor-sik-lesson">
    <div class="sik-box-data-head sik-row">
        <h3 class="heading"><?php echo __('Lesson Options', 'sikshya') ?></h3>
    </div>
    <div class="sik-box-data-content">
        <div class="sik-box-body-content">
            <div class="sikshya-lesson-option-container">
                <div class="sikshya-field-wrap">
                    <div class="sikshya-field-label">
                        <label for="sikshya_lesson_duration"><?php echo esc_html__('Lesson Duration', 'sikshya') ?></label>
                    </div>
                    <div class="sikshya-field-content">
                        <input class="widefat" id="sikshya_lesson_duration" name="sikshya_lesson_duration" type="number"
                               value="<?php echo esc_attr($sikshya_lesson_duration); ?>"
                               placeholder="<?php echo esc_attr__('Lesson Duration', 'sikshya') ?>">

                        <select name="sikshya_lesson_duration_time" id="sikshya_lesson_duration_time">

                            <?php
                            $sikshya_duration_times = sikshya_duration_times();

                            foreach ($sikshya_duration_times as $time_id => $duration_time) {
                                ?>
                                <option value="<?php echo esc_attr($time_id); ?>" <?php echo selected($time_id, $sikshya_lesson_duration_time); ?>><?php echo esc_html($duration_time) ?></option>
                            <?php } ?>

                        </select>
                    </div>

                </div>
                <div class="sikshya-field-wrap">
                    <div class="sikshya-field-label">
                        <label
                                for="sikshya_is_preview_lesson"><?php echo esc_attr__('Preview Lesson', 'sikshya') ?></label>
                    </div>
                    <div class="sikshya-field-content">
                        <div class="sikshya-switch-control-wrap">
                            <label class="sikshya-switch-control">
                                <input class="widefat" id="sikshya_is_preview_lesson"
                                       name="sikshya_is_preview_lesson" type="checkbox"
                                       value="1" <?php echo checked(1, $sikshya_is_preview_lesson) ?>>
                                <span class="slider round" data-on="On" data-off="Off"></span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="sikshya-field-wrap">
                    <div class="sikshya-field-label">
                        <label for="sikshya_lesson_video_source"><?php echo esc_html__('Lesson Video Source', 'sikshya') ?></label>
                    </div>
                    <select name="sikshya_lesson_video_source" id="sikshya_lesson_video_source">
                        <?php
                        $sikshya_video_sources = sikshya_video_sources();

                        foreach ($sikshya_video_sources as $source_id => $source) {
                            ?>
                            <option value="youtube" <?php echo selected($source_id, $sikshya_lesson_video_source); ?>><?php echo esc_html($source) ?></option>
                        <?php } ?>
                    </select>

                </div>
                <div class="sikshya-field-wrap">
                    <div class="sikshya-field-label">
                        <label for="sikshya_lesson_youtube_video_url"><?php echo esc_html__('Lesson Video', 'sikshya') ?></label>
                    </div>
                    <div class="sikshya-field-content">
                        <input class="widefat" id="sikshya_lesson_youtube_video_url"
                               name="sikshya_lesson_youtube_video_url" type="url"
                               value="<?php echo esc_attr($sikshya_lesson_youtube_video_url); ?>"
                               placeholder="<?php echo esc_attr__('Lesson Youtube Video URL', 'sikshya') ?>">

                    </div>

                </div>

            </div>
        </div>
    </div>
</div>


