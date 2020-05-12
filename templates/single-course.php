<?php

sikshya_header();

rewind_posts();

while (have_posts()) {

    the_post();


    $info = sikshya_get_course_info(get_the_ID());

    $sections = sikshya()->section->get_all_by_course(get_the_ID());

    $course_description = empty($info['description']) ? '' : $info['description'];

    $course_subject = empty($info['subject']) ? '' : $info['subject'];

    $course_level = empty($info['level']) ? '' : $info['level'];

    $course_duration = empty($info['duration']) ? 0 : (int)$info['duration'];


    $course_thumbnail_url = sikshya_get_image_url(get_the_post_thumbnail_url(null, 'sikshya_block_small'));

    $course_sections_count = count($sections);

    $course_meta = sikshya()->course->get_course_meta(get_the_ID());

    $outcomes = isset($course_meta['sikshya_course_outcomes']) ? $course_meta['sikshya_course_outcomes'] : array();
    $requirements = isset($course_meta['sikshya_course_requirements']) ? $course_meta['sikshya_course_requirements'] : array();

    $course_level = sikshya_course_levels();

    ?>
    <section class="course-header-area">
        <div class="sik-container">
            <div class="row align-items-end">
                <div class="sik-col-lg-8">
                    <div class="course-header-wrap">
                        <h1 class="title"><?php echo get_the_title() ?></h1>
                        <p class="subtitle"><?php echo get_the_excerpt() ?></p>
                        <div class="rating-row">
                            <span class="sikshya-course-level"><?php echo esc_html($course_level[$course_meta['sikshya_course_level']]); ?></span>
                            <span class="enrolled-num"> 1 Students enrolled </span>
                        </div>
                        <div class="created-row">
          <span class="created-by">
            <?php echo esc_html__('Created by', 'sikshya'); ?><a
                      href="#"> <?php echo esc_attr(sikshya()->course->instructor('display_name', get_the_ID())) ?></a>
          </span>
                            <span class="last-updated-date"><?php echo esc_html__('Last updated', 'sikshya'); ?><?php echo get_the_modified_date() ?></span>
                        </div>
                    </div>
                </div>
                <div class="sik-col-lg-4">

                </div>
            </div>
        </div>
    </section>
    <section class="course-content-area">
        <div class="sik-container">
            <div class="row">
                <div class="sik-col-lg-8">
                    <?php
                    if (isset($outcomes[0]) && '' !== $outcomes[0]) {
                        ?>
                        <div class="what-you-get-box">
                            <div class="what-you-get-title"><?php echo esc_html__('What will i learn?', 'sikshya') ?></div>
                            <ul class="what-you-get__items">
                                <?php foreach ($outcomes as $outcome) { ?>
                                    <li>
                                        <span class="icon dashicons dashicons-yes"></span><?php echo esc_attr($outcome); ?>
                                    </li>
                                <?php } ?>

                            </ul>
                        </div>
                        <br>
                    <?php } ?>

                    <?php do_action('sikshya_course_tab_content'); ?>


                    <?php

                    if (isset($requirements[0]) && '' !== $requirements[0]) { ?>
                        <div class="requirements-box">
                            <div class="requirements-title">Requirements</div>
                            <div class="requirements-content">
                                <ul class="requirements__list">
                                    <?php foreach ($requirements as $requirement) { ?>
                                        <li><?php echo esc_html($requirement); ?></li>
                                    <?php } ?>

                                </ul>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="description-box view-more-parent">
                        <div class="description-title">Description</div>
                        <div class="description-content-wrap">
                            <div class="description-content">
                                <?php the_content(); ?>
                            </div>
                        </div>
                    </div>

                    <div class="about-instructor-box">
                        <div class="about-instructor-title">
                            About the instructor
                        </div>
                        <div class="row">
                            <div class="sik-col-lg-4">
                                <div class="about-instructor-image">
                                    <?php echo get_avatar(sikshya()->course->instructor('ID', get_the_ID())); ?>
                                    <ul>

                                        <li><i class="fas fa-user"></i><b>
                                                3 </b> Students
                                        </li>
                                        <li><i class="fas fa-play-circle"></i><b>
                                                <?php

                                                echo absint(sikshya()->course->get_course_count_by_instructor_id())
                                                ?> </b> Courses
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="sik-col-lg-8">
                                <div class="about-instructor-details view-more-parent">
                                    <div class="instructor-name">
                                        <a href="#"><?php echo esc_attr(sikshya()->course->instructor('display_name', get_the_ID())) ?></a>
                                    </div>
                                    <div class="instructor-bio">
                                        <?php echo esc_attr(sikshya()->course->instructor('description', get_the_ID(), true)) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="sik-col-lg-4">
                    <div class="course-sidebar natural">
                        <?php $sikshya_course_youtube_video_url = $course_meta['sikshya_course_youtube_video_url'];
                        if ('' != $sikshya_course_youtube_video_url) {
                            parse_str(parse_url($sikshya_course_youtube_video_url, PHP_URL_QUERY), $my_array_of_vars);
                            ?>
                            <div class="preview-video-box">
                                <a data-modal-title="<?php echo get_the_title(); ?>" id="CoursePreviewModal">
                                    <img src="<?php echo get_the_post_thumbnail_url() ?>"
                                         alt="" class="img-fluid">
                                    <span class="play-btn"></span>
                                </a>
                                <div class="video-content" style="display: none;">
                                    <iframe width="100%" height="100%"
                                            src="https://www.youtube.com/embed/<?php echo $my_array_of_vars['v']; ?>?controls=0&showinfo=0"
                                            frameborder="0"
                                            allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
                                            allowfullscreen style="height:100%"></iframe>
                                </div>
                            </div>
                        <?php } ?>
                        <div class="course-sidebar-text-box">
                            <div class="price">
                                <span class="current-price"><span class="current-price">Free</span></span>
                            </div>

                            <div class="buy-btns">

                                <button class="btn btn-add-cart" type="button" id="15" onclick="handleCartItems(this)">
                                    Enroll Now
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php

}

sikshya_footer();

