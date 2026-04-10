<?php
/**
 * Single course — presentation only; data from {@see \Sikshya\Frontend\Public\SingleCourseTemplateData}.
 *
 * @package Sikshya
 */

use Sikshya\Frontend\Public\SingleCourseTemplateData;

get_header();

while (have_posts()) :
    the_post();
    $vm = SingleCourseTemplateData::forPost(get_post());
    $pricing = $vm['pricing'];
    $course_id = (int) $vm['course_id'];
    $urls = $vm['urls'];
    ?>

<div class="sikshya-public sikshya-single-course">
    <div class="sikshya-container sikshya-container--narrow">
        <nav class="sikshya-breadcrumb sikshya-breadcrumb--public" aria-label="<?php esc_attr_e('Breadcrumb', 'sikshya'); ?>">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'sikshya'); ?></a>
            <span class="sikshya-breadcrumb-sep">/</span>
            <a href="<?php echo esc_url($urls['courses_archive']); ?>"><?php esc_html_e('Courses', 'sikshya'); ?></a>
            <span class="sikshya-breadcrumb-sep">/</span>
            <span><?php the_title(); ?></span>
        </nav>

        <?php do_action('sikshya_single_course_before_hero', $vm); ?>

        <header class="sikshya-single-course__hero">
            <div class="sikshya-single-course__media">
                <?php if (has_post_thumbnail()) : ?>
                    <?php the_post_thumbnail('large', ['class' => 'sikshya-single-course__thumb']); ?>
                <?php endif; ?>
            </div>
            <div class="sikshya-single-course__summary">
                <h1 class="sikshya-single-course__title"><?php the_title(); ?></h1>
                <div class="sikshya-single-course__price">
                    <?php
                    if (!empty($pricing['on_sale']) && null !== ($pricing['price'] ?? null) && null !== ($pricing['sale_price'] ?? null)) {
                        echo '<span class="sikshya-price-original">' . wp_kses_post(sikshya_format_price((float) $pricing['price'], $pricing['currency'], $course_id)) . '</span> ';
                        echo '<span class="sikshya-price-current">' . wp_kses_post(sikshya_format_price((float) $pricing['sale_price'], $pricing['currency'], $course_id)) . '</span>';
                    } elseif (!empty($vm['is_paid'])) {
                        echo '<span class="sikshya-price-current">' . wp_kses_post(sikshya_format_price((float) $pricing['effective'], $pricing['currency'], $course_id)) . '</span>';
                    } else {
                        echo '<span class="sikshya-price-free">' . esc_html__('Free', 'sikshya') . '</span>';
                    }
                    ?>
                </div>

                <?php do_action('sikshya_single_course_after_price', $vm); ?>

                <div class="sikshya-single-course__actions">
                    <?php if (!empty($vm['is_enrolled'])) : ?>
                        <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url($urls['learn']); ?>"><?php esc_html_e('Continue learning', 'sikshya'); ?></a>
                        <a class="sikshya-btn sikshya-btn--ghost" href="<?php echo esc_url($urls['account']); ?>"><?php esc_html_e('My learning', 'sikshya'); ?></a>
                    <?php elseif (!empty($vm['is_paid'])) : ?>
                        <form method="post" action="" class="sikshya-inline-form">
                            <?php wp_nonce_field('sikshya_cart', 'sikshya_cart_nonce'); ?>
                            <input type="hidden" name="sikshya_cart_action" value="add" />
                            <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $course_id); ?>" />
                            <button type="submit" class="sikshya-btn sikshya-btn--primary"><?php esc_html_e('Add to cart', 'sikshya'); ?></button>
                        </form>
                        <a class="sikshya-btn sikshya-btn--ghost" href="<?php echo esc_url($urls['cart']); ?>"><?php esc_html_e('View cart', 'sikshya'); ?></a>
                    <?php elseif (is_user_logged_in()) : ?>
                        <form method="post" action="" class="sikshya-inline-form">
                            <?php wp_nonce_field('sikshya_cart', 'sikshya_cart_nonce'); ?>
                            <input type="hidden" name="sikshya_cart_action" value="enroll_free" />
                            <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $course_id); ?>" />
                            <button type="submit" class="sikshya-btn sikshya-btn--primary"><?php esc_html_e('Enroll for free', 'sikshya'); ?></button>
                        </form>
                    <?php else : ?>
                        <a class="sikshya-btn sikshya-btn--primary" href="<?php echo esc_url($urls['login']); ?>"><?php esc_html_e('Log in to enroll', 'sikshya'); ?></a>
                    <?php endif; ?>
                </div>

                <?php do_action('sikshya_single_course_after_actions', $vm); ?>
            </div>
        </header>

        <div class="sikshya-single-course__content sikshya-prose">
            <?php the_content(); ?>
        </div>
    </div>
</div>

    <?php
endwhile;

get_footer();
