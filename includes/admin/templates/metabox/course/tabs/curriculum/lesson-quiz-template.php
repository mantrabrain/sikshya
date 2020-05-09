<div class="sikshya-card-item">
    <div class="sikshya-card-inner" id="lesson-">
        <div class="sikshya-card-body">
            <h3 class="title">
                        <span class="font-weight-light"><span
                                    class="dashicons <?php echo esc_attr($icon); ?>"></span></span>
                <?php echo esc_attr($title); ?>
                <input type="hidden" value="<?php echo absint($id); ?>"
                       name="sikshya_course_content<?php echo '[' . absint($section_id) . '][' . esc_attr($type) . ']'; ?>[]"
                       class="sikshya-course-content"
                       data-type-text="<?php echo esc_attr($type); ?>"
                />
                <input type="hidden" value="<?php echo absint($order_number); ?>"
                       name="sikshya_lesson_quiz_order<?php echo '[' . absint($id) . ']'; ?>" class="order-number"/>

            </h3>
            <div class="card-widgets">
                <?php
                $edit_link = get_edit_post_link($id);
                ?>
                <a href="<?php echo esc_url($edit_link); ?>" target="_blank"><span
                            class="dashicons dashicons-edit"></span></i></a>
                <a href="#" class="remove-lesson-quiz"><span class="dashicons dashicons-trash"></span></i></a>
            </div>

        </div>
    </div> <!-- end card-->
</div>
