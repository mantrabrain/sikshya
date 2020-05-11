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
    ?>

    <div class="top-image-block">
        <div class="sikshya-blur-background"<?php if ($course_thumbnail_url) { ?> style="background-image: url(<?php echo esc_attr($course_thumbnail_url); ?>)"<?php } ?>></div>
        <div class="sik-container">
            <div class="sik-row">
                <div class="sik-col-sm-8">
                    <h1><?php the_title(); ?></h1>
                    <?php echo apply_filters('the_content', $course_description); ?>
                </div>
                <div class="sik-col-sm-4">

                </div>
            </div>
        </div>
    </div>
    <div class="sikshya-xs-h15"></div>
    <div class="sik-container">
        <div class="sik-row">
            <div class="sik-col-sm-12">
                <div class="sikshya-sm-m-120t">
                    <div class="sikshya-block-shadow sikshya-description-table">
                        <table>
                            <?php if ($course_subject) { ?>
                                <tr>
                                    <td><i class="icon fa fa-graduation-cap" aria-hidden="true"></i></td>
                                    <th><?php esc_html_e('Subject', 'sikshya'); ?>:</th>
                                    <td><?php echo esc_html($course_subject); ?></td>
                                </tr>
                            <?php } ?>
                            <?php if ($course_sections_count) { ?>
                                <tr>
                                    <td><i class="icon fa fa-clock" aria-hidden="true"></i></td>
                                    <th><?php esc_html_e('Duration', 'sikshya'); ?>:</th>
                                    <td><?php printf(_n('%s section', '%s sections', $course_sections_count, 'sikshya'), $course_sections_count); ?><?php if ($course_duration) { ?>, <?php printf(_n('%s hr', '%s hrs', $course_duration, 'sikshya'), $course_duration); ?><?php } ?></td>
                                </tr>
                            <?php } ?>
                            <tr>
                                <td><i class="icon fa fa-tags" aria-hidden="true"></i></td>
                                <th><?php esc_html_e('Price', 'sikshya'); ?>:</th>
                                <td><?php echo 'free'; ?></td>
                            </tr>
                            <?php if ($course_level) { ?>
                                <tr>
                                    <td><i class="icon fa fa-level-up-alt" aria-hidden="true"></i></td>
                                    <th><?php esc_html_e('Level', 'sikshya'); ?>:</th>
                                    <td><?php echo esc_html($course_level); ?></td>
                                </tr>
                            <?php } ?>
                            <?php
                            $course_id = get_the_ID();
                            if (!sikshya()->course->has_enrolled($course_id) && is_user_logged_in()) {
                                ?>
                                <tr>
                                    <td><i class="icon fa fa-graduation-cap" aria-hidden="true"></i></td>
                                    <th colspan="2">
                                        <div class="sikshya-single-add-to-cart-box cart-required-login ">
                                            <form class="sikshya-enroll-form" method="post">
                                                <input type="hidden" name="sikshya_course_id"
                                                       value="<?php echo absint($course_id); ?>">
                                                <input type="hidden" value="sikshya_enroll_in_course"
                                                       name="sikshya_action"/>
                                                <input type="hidden" value="sikshya_notice"
                                                       name="sikshya_enroll_in_course"/>
                                                <input type="hidden"
                                                       value="<?php echo wp_create_nonce('wp_sikshya_enroll_in_course_nonce') ?>"
                                                       name="sikshya_nonce"/>

                                                <div class=" sikshya-course-enroll-wrap">
                                                    <button type="submit"
                                                            class="sikshya-btn-enroll sikshya-btn sikshya-course-purchase-btn">
                                                        <?php echo __('Enroll Now', 'sikshya') ?>
                                                    </button>
                                                </div>
                                            </form>

                                        </div>
                                    </th>

                                </tr>
                            <?php } ?>
                        </table>
                        <?php
                        do_action('sikshya_course_tab_content');
                        ?>
                    </div>
                </div>


            </div>


        </div>
    </div>

<?php } ?>

<?php
sikshya_footer();

