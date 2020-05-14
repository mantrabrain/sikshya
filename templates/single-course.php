<?php

sikshya_header();

rewind_posts();

while (have_posts()) {

    the_post();

    $sections = sikshya()->section->get_all_by_course(get_the_ID());

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
                            <span class="enrolled-num"> <?php
                                echo sikshya()->student->get_enrolled_count();
                                ?> Students enrolled </span>
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

                    <?php do_action('sikshya_course_single_content'); ?>


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
                        <div class="description-title"><?php echo esc_html__('Description', 'sikshya'); ?></div>
                        <div class="description-content-wrap">
                            <div class="description-content">
                                <?php the_content(); ?>
                            </div>
                        </div>
                    </div>

                    <div class="about-instructor-box">
                        <div class="about-instructor-title">
                            <?php echo esc_html__('About the instructor', 'sikshya'); ?>
                        </div>
                        <div class="row">
                            <div class="sik-col-lg-4">
                                <div class="about-instructor-image">
                                    <?php

                                    echo get_avatar(sikshya()->course->instructor('ID', get_the_ID()));
                                    $all_course_by_instructor = sikshya()->course->get_courses_by_instructor_id();
                                    $all_course_ids = wp_list_pluck($all_course_by_instructor, 'ID');
                                    ?>
                                    <ul>

                                        <li><i class="fas fa-user"></i><b>
                                                <?php echo absint(sikshya()->student->get_enrolled_count_from_courses($all_course_ids)); ?> </b>
                                            <?php echo esc_html__('Students', 'sikshya'); ?>
                                        </li>
                                        <li><i class="fas fa-play-circle"></i><b>
                                                <?php

                                                echo count($all_course_by_instructor)
                                                ?> </b> <?php echo esc_html__('Courses', 'sikshya'); ?>
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
                                <span class="current-price"><span
                                            class="current-price"><?php echo esc_html__('Free', 'sikshya'); ?></span></span>
                            </div>

                            <div class="buy-btns">

                                <?php
                                $enroll_now_button_text = __('Enroll Now', 'sikshya');

                                if (!is_user_logged_in()) {
                                    $enroll_now_button_text = __('Login & Enroll Now', 'sikshya');
                                } else if (!sikshya()->course->has_enrolled(get_the_ID())) {
                                    $enroll_now_button_text = __('Enroll Now', 'sikshya');
                                } else if (!sikshya()->course->user_course_completed(get_the_ID())) {
                                    $enroll_now_button_text = __('Continue to Course', 'sikshya');
                                } else {
                                    $enroll_now_button_text = __('Restart The Course', 'sikshya');
                                }
                                ?>
                                <form class="sikshya-enroll-form" method="post">
                                    <input type="hidden" name="sikshya_course_id"
                                           value="<?php echo absint(get_the_ID()); ?>">
                                    <input type="hidden" value="sikshya_enroll_in_course"
                                           name="sikshya_action"/>
                                    <input type="hidden" value="sikshya_notice"
                                           name="sikshya_enroll_in_course"/>
                                    <input type="hidden"
                                           value="<?php echo wp_create_nonce('wp_sikshya_enroll_in_course_nonce') ?>"
                                           name="sikshya_nonce"/>

                                    <div class=" sikshya-course-enroll-wrap">

                                        <button type="submit"
                                                class="btn btn-add-cart">
                                            <?php echo esc_html($enroll_now_button_text); ?>
                                        </button>
                                    </div>
                                </form>


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

