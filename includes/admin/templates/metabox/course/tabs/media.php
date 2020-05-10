<div class="sikshya-field-wrap">
    <div class="sikshya-field-label">
        <label for="sikshya_course_video_source"><?php echo esc_html__('Course Video Source', 'sikshya') ?></label>
    </div>
    <select name="sikshya_course_video_source" id="sikshya_course_video_source">
        <?php
        $sikshya_video_sources = sikshya_video_sources();

        foreach ($sikshya_video_sources as $source_id => $source) {
            ?>
            <option value="<?php echo esc_attr($source_id) ?>" <?php echo selected($source_id, $sikshya_course_video_source); ?>><?php echo esc_html($source) ?></option>
        <?php } ?>
    </select>

</div>
<div class="sikshya-field-wrap">
    <div class="sikshya-field-label">
        <label for="sikshya_course_youtube_video_url"><?php echo esc_html__('Course Video', 'sikshya') ?></label>
    </div>
    <div class="sikshya-field-content">
        <input class="widefat" id="sikshya_course_youtube_video_url" name="sikshya_course_youtube_video_url" type="url"
               value="<?php echo esc_attr($sikshya_course_youtube_video_url); ?>"
               placeholder="<?php echo esc_attr__('Course Youtube Video URL', 'sikshya') ?>">

    </div>

</div>