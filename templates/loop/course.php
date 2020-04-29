<div class="sikshya-course-item sik-col-md-4">
    <?php $info = sikshya_get_course_info(get_the_ID());
    $course_level = isset($info['level']) ? $info['level'] : '';
    if (!empty($course_level)) {
        $course_level = sikshya_get_course_level($course_level);
    }
    ?>
    <div class="sikshya-course-loop">
        <div class="sikshya-course-header">

            <a href="<?php echo esc_url(get_permalink()) ?>">
                <?php sikshya_image(); ?>
            </a>
            <div class="sikshya-course-loop-header-meta">
                <span class="sikshya-course-loop-level"><?php echo esc_html($course_level); ?></span>
            </div>
        </div>
        <div class="sikshya-loop-course-container">

            <div class="sikshya-course-loop-title">
                <h2><a href="<?php echo esc_url(get_permalink()) ?>"><?php echo esc_html(get_the_title()) ?></a></h2>
            </div>

            <div class="sikshya-loop-author">
                <div class="sikshya-single-course-avatar">

                        <span class="sikshya-text-avatar"
                              style="background-color: #8d8fcc; color: #fff8e5; background-image: url('<?php echo get_avatar_url(get_the_author_meta('ID')) ?>')"></span>
                </div>
                <div class="sikshya-single-course-author-name">
                    <span>by</span>
                    <a href="<?php echo get_the_author_link() ?>"><?php

                        echo get_the_author(); ?></a>
                </div>

                <div class="sikshya-course-lising-category">
                </div>
            </div>
        </div>
        <div class="sikshya-loop-course-footer">

            <div class="sikshya-course-loop-price">
                <div class="price"> Free
                    <div class="sikshya-loop-cart-btn-wrap"><a href="<?php echo esc_url(get_permalink()) ?>">Enroll</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>